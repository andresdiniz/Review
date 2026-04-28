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
     * Body JSON: { "productId": 123 }
     *
     * Busca dados do produto no ML via affiliateLink,
     * chama o Gemini e retorna o resultado como JSON.
     * Também persiste os dados gerados no produto.
     */
    #[Route('/generate', name: 'generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        // Validação CSRF
        $token = $request->headers->get('X-CSRF-Token', '');
        if (!$this->isCsrfTokenValid('admin_ai_generate', $token)) {
            return $this->json(['error' => 'Token de segurança inválido.'], 403);
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $productId = (int) ($body['productId'] ?? 0);

        if ($productId <= 0) {
            return $this->json(['error' => 'ID do produto inválido.'], 400);
        }

        $product = $this->productRepository->find($productId);
        if (!$product) {
            return $this->json(['error' => 'Produto não encontrado.'], 404);
        }

        $affiliateLink = $product->getAffiliateLink();
        if (empty($affiliateLink)) {
            return $this->json(['error' => 'Este produto não possui link afiliado. Salve o campo "Link Afiliado" primeiro.'], 422);
        }

        try {
            // 1. Busca dados reais do produto no ML
            $mlData = $this->scraperService->extractFromUrl($affiliateLink);

            // 2. Chama Gemini com dados enriquecidos
            $aiData = $this->geminiService->analyzeProduct([
                'name'        => $mlData['name']        ?? $product->getName() ?? '',
                'price'       => $mlData['price']       ?? $product->getCurrentPrice() ?? 0,
                'description' => $mlData['description'] ?? '',
                'category'    => $mlData['category']    ?? $product->getCategory() ?? '',
            ]);

            // Verifica se a IA retornou algo útil
            if (empty($aiData['aiVerdict']) && empty($aiData['fullReviewMarkdown'])) {
                return $this->json([
                    'error' => 'A IA não conseguiu gerar o conteúdo. Verifique a chave GEMINI_API_KEY e tente novamente.',
                ], 500);
            }

            // 3. Persiste no produto
            $product->setAiVerdict($aiData['aiVerdict']);
            $product->setPros($aiData['pros']);
            $product->setCons($aiData['cons']);
            $product->setFullReviewMarkdown($aiData['fullReviewMarkdown']);
            if ($aiData['score'] !== null) {
                $product->setScore((string) $aiData['score']);
            }
            // Atualiza também dados do ML se estiverem vazios no produto
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
                'aiVerdict'          => $aiData['aiVerdict'],
                'pros'               => $aiData['pros'],
                'cons'               => $aiData['cons'],
                'fullReviewMarkdown' => $aiData['fullReviewMarkdown'],
                'score'              => $aiData['score'],
                // Extras do ML que podem ter sido atualizados
                'imageUrl'           => $product->getImageUrl(),
                'category'           => $product->getCategory(),
                'mercadolivreId'     => $product->getMercadolivreId(),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Erro: ' . $e->getMessage()], 500);
        }
    }
}
