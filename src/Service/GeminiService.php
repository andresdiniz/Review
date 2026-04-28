<?php
// src/Service/GeminiService.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class GeminiService
{
    private HttpClientInterface $httpClient;
    private string $apiKey;

    // Modelos disponíveis em ordem de preferência
    private const MODEL = 'gemini-2.0-flash';
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(HttpClientInterface $httpClient, string $apiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey     = $apiKey;
    }

    /**
     * Gera um review completo do produto usando IA.
     *
     * @param array $productData Dados do produto (name, description, category, condition, attributes, soldQuantity)
     * @return array{fullReviewMarkdown: string, pros: list<string>, cons: list<string>, aiVerdict: string, category: string}
     * @throws \RuntimeException em caso de falha na API
     */
    public function generateReview(array $productData): array
    {
        $attributes = '';
        if (!empty($productData['attributes'])) {
            $attrs = [];
            foreach ($productData['attributes'] as $key => $value) {
                $attrs[] = "{$key}: {$value}";
            }
            $attributes = implode(', ', $attrs);
        }

        $soldQuantity = $productData['soldQuantity'] ?? 0;
        $soldInfo     = $soldQuantity > 0 ? "Vendidos: {$soldQuantity}" : '';

        $prompt = <<<PROMPT
Você é um especialista em reviews de produtos para um site brasileiro de curadoria. Analise o produto abaixo e gere uma review completa, honesta e útil para o consumidor.

**Dados do Produto:**
- Nome: {$productData['name']}
- Categoria: {$productData['category']}
- Condição: {$productData['condition']}
- Descrição: {$productData['description']}
- Atributos técnicos: {$attributes}
- {$soldInfo}

**Instruções:**
1. Seja específico e mencione características reais do produto.
2. Prós devem ser objetivos e verificáveis.
3. Contras devem ser construtivos, não negativistas.
4. O veredito final deve ser conciso (máximo 2 frases).
5. O reviewMarkdown deve ter pelo menos 3 parágrafos com conteúdo rico.
6. A category deve ser uma categoria legível em português (ex: "Eletrônicos", "Ferramentas Elétricas").

Responda APENAS com o JSON abaixo, sem explicações, sem blocos de código markdown:
{
  "reviewMarkdown": "texto completo em markdown com ## subtítulos, parágrafos e negrito",
  "pros": ["pró 1 específico", "pró 2 específico", "pró 3 específico"],
  "cons": ["contra 1 construtivo", "contra 2 construtivo"],
  "verdict": "veredito final conciso em 1-2 frases",
  "category": "categoria do produto em português"
}
PROMPT;

        $rawText = $this->callGeminiApi($prompt);
        return $this->parseReviewResponse($rawText, $productData);
    }

    private function callGeminiApi(string $prompt, int $attempt = 1): string
    {
        $maxAttempts = 3;

        try {
            $url      = sprintf('%s/%s:generateContent?key=%s', self::API_BASE, self::MODEL, $this->apiKey);
            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.7,
                        'maxOutputTokens' => 3072,
                        'responseMimeType' => 'application/json',
                    ],
                    'safetySettings' => [
                        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
                    ],
                ],
            ]);

            $data      = $response->toArray();
            $candidate = $data['candidates'][0] ?? null;

            if (!$candidate) {
                throw new \RuntimeException('Gemini não retornou candidatos na resposta.');
            }

            // Verifica se foi bloqueado por segurança
            $finishReason = $candidate['finishReason'] ?? '';
            if ($finishReason === 'SAFETY') {
                throw new \RuntimeException('Resposta bloqueada pelos filtros de segurança do Gemini.');
            }

            return $candidate['content']['parts'][0]['text'] ?? '';
        } catch (TransportExceptionInterface $e) {
            if ($attempt < $maxAttempts) {
                sleep($attempt * 2); // Backoff exponencial
                return $this->callGeminiApi($prompt, $attempt + 1);
            }
            throw new \RuntimeException('Erro de conexão com Gemini após ' . $maxAttempts . ' tentativas: ' . $e->getMessage(), 0, $e);
        }
    }

    private function parseReviewResponse(string $rawText, array $productData): array
    {
        // Remove possíveis blocos de código markdown (```json ... ```)
        $clean = preg_replace('/^```(?:json)?\s*/m', '', $rawText);
        $clean = preg_replace('/```\s*$/m', '', $clean ?? $rawText);
        $clean = trim($clean ?? $rawText);

        // Tenta decodificar JSON direto
        $result = json_decode($clean, true);

        // Tenta extrair JSON do texto se a decodificação falhar
        if (!is_array($result)) {
            if (preg_match('/\{.*\}/s', $clean, $matches)) {
                $result = json_decode($matches[0], true);
            }
        }

        if (!is_array($result)) {
            // Fallback: usa o texto bruto como review
            return [
                'fullReviewMarkdown' => $rawText,
                'pros'               => [],
                'cons'               => [],
                'aiVerdict'          => '',
                'category'           => $productData['category'] ?? '',
            ];
        }

        return [
            'fullReviewMarkdown' => $result['reviewMarkdown'] ?? $rawText,
            'pros'               => array_filter((array) ($result['pros'] ?? [])),
            'cons'               => array_filter((array) ($result['cons'] ?? [])),
            'aiVerdict'          => $result['verdict'] ?? '',
            'category'           => $result['category'] ?? $productData['category'] ?? '',
        ];
    }
}
