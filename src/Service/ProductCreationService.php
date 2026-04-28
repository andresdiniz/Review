<?php
// src/Service/ProductCreationService.php

namespace App\Service;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Serviço de alto nível para criação de produtos no banco de dados.
 * Orquestra o scraping, geração de review por IA e persistência.
 */
class ProductCreationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProductAIService $productAIService
    ) {}

    /**
     * Cria um produto completo a partir de um link do Mercado Livre.
     * Faz scraping, gera review via IA e persiste na base de dados.
     *
     * @throws \RuntimeException em caso de falha
     */
    public function createFromUrl(string $url): Product
    {
        $data = $this->productAIService->generateFromAffiliateLink($url);

        $product = new Product();
        $product->setName($data['name']);
        $product->setSlug($data['slug']);
        $product->setAffiliateLink($data['affiliateLink']);
        $product->setCurrentPrice((string) $data['currentPrice']);
        $product->setAiVerdict($data['aiVerdict']);
        $product->setPros($data['pros']);
        $product->setCons($data['cons']);
        $product->setFullReviewMarkdown($data['fullReviewMarkdown']);
        $product->setImageUrl($data['imageUrl']);
        $product->setMercadolivreId($data['mercadolivreId']);
        $product->setCategory($data['category']);
        // youtubeVideoId, description podem ser definidos depois pelo admin

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    /**
     * Garante que o slug gerado é único, adicionando sufixo numérico se necessário.
     */
    public function ensureUniqueSlug(Product $product): void
    {
        $repo     = $this->em->getRepository(Product::class);
        $baseSlug = $product->getSlug();
        $slug     = $baseSlug;
        $i        = 2;

        while ($repo->findOneBy(['slug' => $slug]) !== null) {
            $slug = "{$baseSlug}-{$i}";
            $i++;
        }

        $product->setSlug($slug);
    }
}
