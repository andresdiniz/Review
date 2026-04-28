<?php
// src/Service/GeminiService.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiService
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey
    ) {}

    /**
     * Gera análise completa de um produto a partir dos seus dados.
     * Retorna array com todos os campos incluindo score.
     */
    public function analyzeProduct(array $productData): array
    {
        $prompt = $this->buildPrompt($productData);

        $response = $this->httpClient->request('POST', self::API_URL . '?key=' . $this->apiKey, [
            'json' => [
                'contents' => [
                    ['parts' => [['text' => $prompt]]]
                ],
                'generationConfig' => [
                    'temperature'     => 0.7,
                    'maxOutputTokens' => 2048,
                ],
            ],
        ]);

        $data = $response->toArray();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return $this->parseResponse($text, $productData);
    }

    private function buildPrompt(array $productData): string
    {
        $name  = $productData['name']  ?? 'Produto desconhecido';
        $price = $productData['price'] ?? 0;
        $desc  = $productData['description'] ?? '';

        return <<<PROMPT
Você é um especialista em avaliação de produtos. Analise o produto abaixo e responda EXCLUSIVAMENTE em JSON válido, sem markdown, sem comentários.

Produto: {$name}
Preço: R$ {$price}
Descrição: {$desc}

Retorne exatamente este JSON:
{
  "aiVerdict": "Veredito objetivo em 2-3 frases",
  "pros": ["ponto positivo 1", "ponto positivo 2", "ponto positivo 3"],
  "cons": ["ponto negativo 1", "ponto negativo 2"],
  "fullReviewMarkdown": "## Review\\n\\nConteúdo completo em markdown...",
  "score": 7.5
}

Regras:
- score: número decimal de 0.0 a 10.0, representando a qualidade geral do produto
- pros: lista de 3 a 5 pontos positivos reais
- cons: lista de 2 a 4 pontos negativos reais
- fullReviewMarkdown: mínimo 300 palavras em markdown
- Responda SOMENTE o JSON, sem nenhum texto fora dele
PROMPT;
    }

    private function parseResponse(string $text, array $productData): array
    {
        // Remove possíveis blocos de código markdown
        $text = preg_replace('/```(?:json)?\s*([\s\S]*?)```/', '$1', $text);
        $text = trim($text);

        $parsed = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            // Fallback seguro
            return [
                'aiVerdict'           => 'Análise gerada automaticamente.',
                'pros'                => ['Produto disponível no Mercado Livre'],
                'cons'                => ['Informações limitadas disponíveis'],
                'fullReviewMarkdown'  => '## Review\n\nProduto analisado automaticamente.',
                'score'               => null,
            ];
        }

        return [
            'aiVerdict'          => (string) ($parsed['aiVerdict'] ?? ''),
            'pros'               => (array)  ($parsed['pros']      ?? []),
            'cons'               => (array)  ($parsed['cons']      ?? []),
            'fullReviewMarkdown' => (string) ($parsed['fullReviewMarkdown'] ?? ''),
            // ── bug #5 corrigido: score extraído e validado ────────────────
            'score'              => isset($parsed['score'])
                                    ? (string) max(0, min(10, (float) $parsed['score']))
                                    : null,
        ];
    }
}
