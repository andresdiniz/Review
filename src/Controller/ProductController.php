<?php
// src/Controller/ProductController.php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductFilterType;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;

class ProductController extends AbstractController
{
    private const PER_PAGE = 12;

    #[Route('/', name: 'product_index')]
    public function index(Request $request, ProductRepository $productRepository): Response
    {
        $categories = $productRepository->findDistinctCategories();

        $form = $this->createForm(ProductFilterType::class, null, [
            'categories' => $categories,
        ]);
        $form->handleRequest($request);

        // Lê filtros do form (quando submetido) OU direto da query string
        $formData = ($form->isSubmitted() && $form->isValid())
            ? ($form->getData() ?? [])
            : $request->query->all('product_filter_type');

        $filters = array_filter($formData, fn($v) => $v !== null && $v !== '');

        [$orderBy, $order] = $this->parseOrderBy($filters['order_by'] ?? null);
        unset($filters['order_by']);

        $page      = max(1, (int) $request->query->get('page', 1));
        $paginated = $productRepository->findPaginated($filters, $page, self::PER_PAGE, $orderBy, $order);

        return $this->render('product/index.html.twig', [
            'products'    => $paginated['items'],
            'total'       => $paginated['total'],
            'pages'       => $paginated['pages'],
            'page'        => $paginated['page'],
            'perPage'     => $paginated['perPage'],
            'filter_form' => $form->createView(),
            'categories'  => $categories,
        ]);
    }

    #[Route('/produto/{slug}', name: 'product_show')]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])] Product $product,
        ProductRepository $productRepository
    ): Response {
        $related = $productRepository->findRelated($product, 4);

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'related' => $related,
        ]);
    }

    #[Route('/categoria/{category}', name: 'product_category')]
    public function category(string $category, Request $request, ProductRepository $productRepository): Response
    {
        $categories = $productRepository->findDistinctCategories();
        $filters    = ['category' => $category];
        $page       = max(1, (int) $request->query->get('page', 1));
        $paginated  = $productRepository->findPaginated($filters, $page, self::PER_PAGE);

        return $this->render('product/category.html.twig', [
            'category'   => $category,
            'categories' => $categories,
            'products'   => $paginated['items'],
            'total'      => $paginated['total'],
            'pages'      => $paginated['pages'],
            'page'       => $paginated['page'],
            'perPage'    => $paginated['perPage'],
        ]);
    }

    private function parseOrderBy(?string $orderBy): array
    {
        return match ($orderBy) {
            'currentPrice_asc'  => ['currentPrice', 'ASC'],
            'currentPrice_desc' => ['currentPrice', 'DESC'],
            'name_asc'          => ['name', 'ASC'],
            'score_desc'        => ['score', 'DESC'],
            default             => ['createdAt', 'DESC'],
        };
    }
}
