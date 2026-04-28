<?php
// src/Service/GeminiService.php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiService
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
        private ?LoggerInterface $logger = null
    ) {}

    /**
     * Gera análise completa de um produto a partir dos seus dados.
     * Tenta até 2 vezes com prompts ligeiramente diferentes para maximizar chance de JSON válido.
     */
    public function analyzeProduct(array $productData): array
    {
        // Tentativa 1: prompt padrão
        $text = $this->callApi($this->buildPrompt($productData));
        $result = $this->parseResponse($text);

        if ($result !== null) {
            return $result;
        }

        // Tentativa 2: prompt mais explícito pedindo SOMENTE JSON
        $this->logger?->warning('GeminiService: primeira tentativa falhou no parse, retentando.', [
            'raw' => substr($text, 0, 500),
        ]);

        $text2 = $this->callApi($this->buildStrictPrompt($productData));
        $result2 = $this->parseResponse($text2);

        if ($result2 !== null) {
            return $result2;
        }

        // Fallback final — log do texto bruto para diagnóstico
        $this->logger?->error('GeminiService: ambas tentativas falharam no parse.', [
            'raw_attempt1' => substr($text, 0, 1000),
            'raw_attempt2' => substr($text2, 0, 1000),
        ]);

        return $this->fallbackResult();
    }

    // ── Chamada HTTP ────────────────────────────────────────────────────────

    private function callApi(string $prompt): string
    {
        $response = $this->httpClient->request('POST', self::API_URL . '?key=' . $this->apiKey, [
            'headers' => ['Content-Type' => 'application/json'],
            'json'    => [
                'contents' => [
                    ['parts' => [['text' => $prompt]]],
                ],
                'generationConfig' => [
                    'temperature'     => 0.4,
                    'maxOutputTokens' => 2048,
                    'responseMimeType' => 'application/json',
                ],
            ],
        ]);

        $data = $response->toArray();
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    // ── Prompts ─────────────────────────────────────────────────────────────

    private function buildPrompt(array $productData): string
    {
        $name  = $productData['name']        ?? 'Produto desconhecido';
        $price = $productData['price']        ?? 0;
        $desc  = mb_substr($productData['description'] ?? '', 0, 800);
        $cat   = $productData['category']     ?? '';

        return <<<PROMPT
Você é um especialista em avaliação de produtos de e-commerce brasileiro.
Analise o produto abaixo e retorne EXCLUSIVAMENTE um objeto JSON válido, sem nenhum texto antes ou depois, sem blocos de código markdown.

Produto: {$name}
Categoria: {$cat}
Preço: R$ {$price}
Descrição: {$desc}

Estrutura JSON exigida (sem desvios):
{"aiVerdict":"string 2-3 frases","pros":["string","string","string"],"cons":["string","string"],"fullReviewMarkdown":"string markdown minimo 300 palavras","score":7.5}

Regras:
- score: número decimal de 0.0 a 10.0
- pros: 3 a 5 pontos positivos reais do produto
- cons: 2 a 4 pontos negativos reais do produto
- fullReviewMarkdown: review completo em markdown com no mínimo 300 palavras, com subtítulos ##
- Responda SOMENTE o JSON. Nenhuma palavra fora do JSON.
PROMPT;
    }

    private function buildStrictPrompt(array $productData): string
    {
        $name  = $productData['name']  ?? 'Produto desconhecido';
        $price = $productData['price'] ?? 0;
        $desc  = mb_substr($productData['description'] ?? '', 0, 500);

        return 'Retorne apenas JSON puro sem markdown. Produto: ' . $name
            . ' | Preço: R$' . $price
            . ' | Descrição: ' . $desc
            . ' | JSON: {"aiVerdict":"","pros":[""],"cons":[""],"fullReviewMarkdown":"","score":0.0}'
            . ' Preencha todos os campos com análise real. Responda somente o JSON.';
    }

    // ── Parsing robusto ──────────────────────────────────────────────────────

    private function parseResponse(string $text): ?array
    {
        if (empty(trim($text))) {
            return null;
        }

        // 1. Remove blocos de código markdown ```json ... ``` ou ``` ... ```
        $clean = preg_replace('/^```(?:json)?\s*/im', '', $text);
        $clean = preg_replace('/```\s*$/im', '', $clean);
        $clean = trim($clean);

        // 2. Tenta decode direto
        $parsed = json_decode($clean, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            return $this->buildResult($parsed);
        }

        // 3. Extrai o primeiro objeto JSON do texto por regex
        if (preg_match('/\{[\s\S]+\}/u', $clean, $m)) {
            $parsed = json_decode($m[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                return $this->buildResult($parsed);
            }
        }

        return null;
    }

    private function buildResult(array $parsed): array
    {
        // Normaliza pros/cons: aceita array ou string separada por vírgula/newline
        $pros = $this->normalizeList($parsed['pros'] ?? []);
        $cons = $this->normalizeList($parsed['cons'] ?? []);

        $score = null;
        if (isset($parsed['score'])) {
            $s = (float) $parsed['score'];
            $score = (string) round(max(0.0, min(10.0, $s)), 1);
        }

        return [
            'aiVerdict'          => trim((string) ($parsed['aiVerdict'] ?? '')),
            'pros'               => $pros,
            'cons'               => $cons,
            'fullReviewMarkdown' => trim((string) ($parsed['fullReviewMarkdown'] ?? '')),
            'score'              => $score,
        ];
    }

    private function normalizeList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }
        if (is_string($value) && !empty($value)) {
            // Tenta separar por newline ou ponto-e-vírgula
            $items = preg_split('/[\n;]+/', $value);
            return array_values(array_filter(array_map('trim', $items ?: [])));
        }
        return [];
    }

    private function fallbackResult(): array
    {
        return [
            'aiVerdict'          => '',
            'pros'               => [],
            'cons'               => [],
            'fullReviewMarkdown' => '',
            'score'              => null,
        ];
    }
}
