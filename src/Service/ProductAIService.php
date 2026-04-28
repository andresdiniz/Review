<?php
// src/Service/ProductAIService.php

namespace App\Service;

class ProductAIService
{
    public function __construct(
        private MercadoLivreScraperService $mercadoLivreService,
        private GeminiService $geminiService
    ) {}

    /**
     * A partir de um link afiliado do Mercado Livre, gera todos os dados
     * do produto incluindo análise de IA com score.
     */
    public function generateFromAffiliateLink(string $url): array
    {
        // 1. Extrai ID e dados do ML via scraper
        $mlData = $this->mercadoLivreService->extractFromUrl($url);

        $mlId = $mlData['mercadolivreId'] ?? null;

        if (!$mlId) {
            throw new \InvalidArgumentException('URL inválida. Não foi possível extrair o ID do Mercado Livre.');
        }

        // 2. Gera análise com IA
        $aiData = $this->geminiService->analyzeProduct([
            'name'        => $mlData['name']        ?? '',
            'price'       => $mlData['price']        ?? 0,
            'description' => $mlData['description']  ?? '',
        ]);

        // 3. Gera slug
        $slug = $this->generateSlug($mlData['name'] ?? 'produto');

        return [
            'name'               => $mlData['name']          ?? 'Produto sem nome',
            'slug'               => $slug,
            'currentPrice'       => (string) ($mlData['price'] ?? '0.00'),
            'affiliateLink'      => $url,
            'imageUrl'           => $mlData['imageUrl']       ?? null,
            'mercadolivreId'     => $mlId,
            'category'           => $mlData['category']       ?? null,
            'youtubeVideoId'     => null,
            // IA
            'aiVerdict'          => $aiData['aiVerdict'],
            'pros'               => $aiData['pros'],
            'cons'               => $aiData['cons'],
            'fullReviewMarkdown' => $aiData['fullReviewMarkdown'],
            'score'              => $aiData['score'] ?? null,
        ];
    }

    private function generateSlug(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n',
        ]);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', trim($text));
        return substr($text, 0, 200);
    }
}
