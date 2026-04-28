<?php
// src/Controller/Admin/AiGenerateController.php

namespace App\Controller\Admin;

use App\Repository\ProductRepository;
use App\Service\GeminiService;
use App\Service\MercadoLivreScraperService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/ai', name: 'admin_ai_')]
class AiGenerateController extends AbstractController
{
    public function __construct(
        private ProductRepository $productRepository,
        private MercadoLivreScraperService $scraperService,
        private GeminiService $geminiService,
        private EntityManagerInterface $em
    ) {}

    /**
     * POST /admin/ai/generate
     * Gera conteúdo IA para UM produto.
     * Body JSON: { "productId": 123, "force": false }
     * force=true sobrescreve campos que já têm conteúdo.
     */
    #[Route('/generate', name: 'generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        $token = $request->headers->get('X-CSRF-Token', '');
        if (!$this->isCsrfTokenValid('admin_ai_generate', $token)) {
            return $this->json(['error' => 'Token de segurança inválido.'], 403);
        }

        $body      = json_decode($request->getContent(), true) ?? [];
        $productId = (int) ($body['productId'] ?? 0);
        $force     = (bool) ($body['force'] ?? false);

        if ($productId <= 0) {
            return $this->json(['error' => 'ID do produto inválido.'], 400);
        }

        $product = $this->productRepository->find($productId);
        if (!$product) {
            return $this->json(['error' => 'Produto não encontrado.'], 404);
        }

        // Verifica se já tem conteúdo e não foi pedido force
        $hasContent = !empty($product->getAiVerdict()) || !empty($product->getFullReviewMarkdown());
        if ($hasContent && !$force) {
            return $this->json([
                'needs_confirmation' => true,
                'productId'          => $productId,
                'productName'        => $product->getName(),
                'message'            => 'Este produto já possui conteúdo gerado pela IA. Deseja sobrescrever?',
            ], 200);
        }

        $affiliateLink = $product->getAffiliateLink();
        if (empty($affiliateLink)) {
            return $this->json(['error' => 'Produto não possui link afiliado. Adicione-o antes de usar a IA.'], 422);
        }

        try {
            $mlData = $this->scraperService->extractFromUrl($affiliateLink);

            $aiData = $this->geminiService->analyzeProduct([
                'name'        => $mlData['name']        ?? $product->getName() ?? '',
                'price'       => $mlData['price']       ?? $product->getCurrentPrice() ?? 0,
                'description' => $mlData['description'] ?? '',
                'category'    => $mlData['category']    ?? $product->getCategory() ?? '',
            ]);

            if (empty($aiData['aiVerdict']) && empty($aiData['fullReviewMarkdown'])) {
                return $this->json(['error' => 'A IA não gerou conteúdo. Verifique a chave GEMINI_API_KEY.'], 500);
            }

            $product->setAiVerdict($aiData['aiVerdict']);
            $product->setPros($aiData['pros']);
            $product->setCons($aiData['cons']);
            $product->setFullReviewMarkdown($aiData['fullReviewMarkdown']);
            if ($aiData['score'] !== null) {
                $product->setScore((string) $aiData['score']);
            }
            if (!$product->getImageUrl() && !empty($mlData['imageUrl'])) {
                $product->setImageUrl($mlData['imageUrl']);
            }
            if (!$product->getCategory() && !empty($mlData['category'])) {
                $product->setCategory($mlData['category']);
            }
            if (!$product->getMercadolivreId() && !empty($mlData['mercadolivreId'])) {
                $product->setMercadolivreId($mlData['mercadolivreId']);
            }

            $this->em->flush();

            return $this->json([
                'success'            => true,
                'productId'          => $productId,
                'productName'        => $product->getName(),
                'aiVerdict'          => $aiData['aiVerdict'],
                'pros'               => $aiData['pros'],
                'cons'               => $aiData['cons'],
                'fullReviewMarkdown' => $aiData['fullReviewMarkdown'],
                'score'              => $aiData['score'],
                'imageUrl'           => $product->getImageUrl(),
                'category'           => $product->getCategory(),
                'mercadolivreId'     => $product->getMercadolivreId(),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /admin/ai/generate-batch
     * Gera conteúdo IA para múltiplos produtos.
     * Body JSON: { "productIds": [1,2,3], "force": false }
     */
    #[Route('/generate-batch', name: 'generate_batch', methods: ['POST'])]
    public function generateBatch(Request $request): JsonResponse
    {
        $token = $request->headers->get('X-CSRF-Token', '');
        if (!$this->isCsrfTokenValid('admin_ai_generate', $token)) {
            return $this->json(['error' => 'Token de segurança inválido.'], 403);
        }

        $body       = json_decode($request->getContent(), true) ?? [];
        $productIds = array_filter(array_map('intval', $body['productIds'] ?? []));
        $force      = (bool) ($body['force'] ?? false);

        if (empty($productIds)) {
            return $this->json(['error' => 'Nenhum produto selecionado.'], 400);
        }

        $results = [];

        foreach ($productIds as $productId) {
            $product = $this->productRepository->find($productId);

            if (!$product) {
                $results[] = ['productId' => $productId, 'status' => 'error', 'message' => 'Não encontrado'];
                continue;
            }

            $hasContent = !empty($product->getAiVerdict()) || !empty($product->getFullReviewMarkdown());
            if ($hasContent && !$force) {
                $results[] = [
                    'productId'   => $productId,
                    'productName' => $product->getName(),
                    'status'      => 'skipped',
                    'message'     => 'Já possui conteúdo (use forçar para sobrescrever)',
                ];
                continue;
            }

            $affiliateLink = $product->getAffiliateLink();
            if (empty($affiliateLink)) {
                $results[] = [
                    'productId'   => $productId,
                    'productName' => $product->getName(),
                    'status'      => 'error',
                    'message'     => 'Sem link afiliado',
                ];
                continue;
            }

            try {
                $mlData = $this->scraperService->extractFromUrl($affiliateLink);
                $aiData = $this->geminiService->analyzeProduct([
                    'name'        => $mlData['name']        ?? $product->getName() ?? '',
                    'price'       => $mlData['price']       ?? $product->getCurrentPrice() ?? 0,
                    'description' => $mlData['description'] ?? '',
                    'category'    => $mlData['category']    ?? $product->getCategory() ?? '',
                ]);

                if (empty($aiData['aiVerdict']) && empty($aiData['fullReviewMarkdown'])) {
                    $results[] = [
                        'productId'   => $productId,
                        'productName' => $product->getName(),
                        'status'      => 'error',
                        'message'     => 'IA não gerou conteúdo',
                    ];
                    continue;
                }

                $product->setAiVerdict($aiData['aiVerdict']);
                $product->setPros($aiData['pros']);
                $product->setCons($aiData['cons']);
                $product->setFullReviewMarkdown($aiData['fullReviewMarkdown']);
                if ($aiData['score'] !== null) {
                    $product->setScore((string) $aiData['score']);
                }
                if (!$product->getImageUrl() && !empty($mlData['imageUrl'])) {
                    $product->setImageUrl($mlData['imageUrl']);
                }
                if (!$product->getCategory() && !empty($mlData['category'])) {
                    $product->setCategory($mlData['category']);
                }
                if (!$product->getMercadolivreId() && !empty($mlData['mercadolivreId'])) {
                    $product->setMercadolivreId($mlData['mercadolivreId']);
                }

                $this->em->flush();

                $results[] = [
                    'productId'   => $productId,
                    'productName' => $product->getName(),
                    'status'      => 'success',
                    'score'       => $aiData['score'],
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'productId'   => $productId,
                    'productName' => $product->getName() ?? '?',
                    'status'      => 'error',
                    'message'     => $e->getMessage(),
                ];
            }
        }

        $success = count(array_filter($results, fn($r) => $r['status'] === 'success'));
        $errors  = count(array_filter($results, fn($r) => $r['status'] === 'error'));
        $skipped = count(array_filter($results, fn($r) => $r['status'] === 'skipped'));

        return $this->json([
            'results' => $results,
            'summary' => compact('success', 'errors', 'skipped'),
        ]);
    }
}
