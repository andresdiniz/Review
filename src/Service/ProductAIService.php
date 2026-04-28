<?php
// src/Service/ProductAIService.php

namespace App\Service;

class ProductAIService
{
    private MercadoLivreScraperService $scraper;
    private GeminiService $gemini;

    public function __construct(MercadoLivreScraperService $scraper, GeminiService $gemini)
    {
        $this->scraper = $scraper;
        $this->gemini  = $gemini;
    }

    /**
     * Gera um produto completo com review de IA a partir de um link do Mercado Livre.
     *
     * @throws \RuntimeException em caso de falha no scraping ou na IA
     */
    public function generateFromAffiliateLink(string $url): array
    {
        $productData = $this->scraper->extractFromUrl($url);
        $reviewData  = $this->gemini->generateReview($productData);

        // Categoria: prefere a devolvida pela IA (mais legível), senão usa a do ML
        $category = !empty($reviewData['category'])
            ? $reviewData['category']
            : $productData['category'];

        return [
            'name'                => $productData['name'],
            'slug'                => $this->slugify($productData['name']),
            'currentPrice'        => $productData['price'],
            'affiliateLink'       => $productData['url'],
            'aiVerdict'           => $reviewData['aiVerdict'],
            'pros'                => array_values($reviewData['pros']),
            'cons'                => array_values($reviewData['cons']),
            'fullReviewMarkdown'  => $reviewData['fullReviewMarkdown'],
            'youtubeVideoId'      => null,
            'imageUrl'            => $productData['imageUrl'],
            'mercadolivreId'      => $productData['mercadolivreId'],
            'category'            => $category,
        ];
    }

    /**
     * Converte uma string em slug URL-amigável.
     */
    public function slugify(string $text): string
    {
        if (empty($text)) {
            return 'produto';
        }

        // Converte para ASCII (transliteração)
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;

        // Remove caracteres não alfanuméricos (exceto hífens)
        $text = preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $text) ?? $text;

        // Converte espaços e underscores para hífens
        $text = preg_replace('/[\s_]+/', '-', $text) ?? $text;

        // Remove hífens duplicados
        $text = preg_replace('/-{2,}/', '-', $text) ?? $text;

        // Remove hífens no início e fim
        $text = trim($text, '-');

        return strtolower($text) ?: 'produto';
    }
}
