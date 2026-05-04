<?php
// src/Controller/PriceHistoryController.php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\PriceHistoryRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class PriceHistoryController extends AbstractController
{
    #[Route('/api/produto/{slug}/historico-precos', name: 'api_price_history', methods: ['GET'])]
    public function history(
        #[MapEntity(mapping: ['slug' => 'slug'])] Product $product,
        PriceHistoryRepository $priceHistoryRepository
    ): JsonResponse {
        $records = $priceHistoryRepository->findByProduct($product, 60);
        $lowest  = $priceHistoryRepository->findLowestPrice($product);

        $data = array_map(fn($r) => [
            'date'  => $r->getRecordedAt()->format('d/m/Y'),
            'price' => (float) $r->getPrice(),
        ], $records);

        return $this->json([
            'history' => $data,
            'lowest'  => $lowest ? (float) $lowest : null,
            'current' => (float) $product->getCurrentPrice(),
        ]);
    }

    #[Route('/api/comparar', name: 'api_compare', methods: ['GET'])]
    public function compare(
        \Symfony\Component\HttpFoundation\Request $request,
        \App\Repository\ProductRepository $productRepository
    ): JsonResponse {
        $slugs = array_filter(explode(',', $request->query->get('slugs', '')));
        $slugs = array_slice($slugs, 0, 3); // máximo 3

        $products = [];
        foreach ($slugs as $slug) {
            $p = $productRepository->findOneBy(['slug' => trim($slug)]);
            if ($p) {
                $products[] = [
                    'slug'        => $p->getSlug(),
                    'name'        => $p->getName(),
                    'imageUrl'    => $p->getImageUrl(),
                    'currentPrice'=> (float) $p->getCurrentPrice(),
                    'score'       => $p->getScore() ? (float) $p->getScore() : null,
                    'pros'        => $p->getPros(),
                    'cons'        => $p->getCons(),
                    'aiVerdict'   => $p->getAiVerdict(),
                    'category'    => $p->getCategory(),
                    'url'         => '/produto/' . $p->getSlug(),
                ];
            }
        }

        return $this->json($products);
    }
}
