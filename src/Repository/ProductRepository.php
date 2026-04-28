<?php
// src/Repository/ProductRepository.php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    private const DEFAULT_PER_PAGE = 12;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Busca produtos paginados com filtros e ordenação.
     *
     * @param array  $filters  ['category', 'min_price', 'max_price', 'search']
     * @param int    $page     Página atual (começa em 1)
     * @param int    $perPage  Itens por página
     * @param string $orderBy  Campo de ordenação: 'createdAt', 'currentPrice', 'name'
     * @param string $order    'ASC' ou 'DESC'
     * @return array{items: Product[], total: int, pages: int, page: int, perPage: int}
     */
    public function findPaginated(
        array $filters = [],
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        string $orderBy = 'createdAt',
        string $order = 'DESC'
    ): array {
        $qb = $this->buildFilteredQuery($filters, $orderBy, $order);

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);
        $total     = count($paginator);
        $pages     = (int) ceil($total / $perPage);
        $page      = max(1, min($page, max(1, $pages)));

        $paginator->getQuery()
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        return [
            'items'   => iterator_to_array($paginator),
            'total'   => $total,
            'pages'   => $pages,
            'page'    => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Busca todos os produtos filtrados (sem paginação).
     * Mantido por compatibilidade com código existente.
     */
    public function findFiltered(array $filters = [], string $orderBy = 'createdAt', string $order = 'DESC'): array
    {
        return $this->buildFilteredQuery($filters, $orderBy, $order)
            ->getQuery()
            ->getResult();
    }

    /**
     * Conta o total de produtos que correspondem aos filtros.
     */
    public function countFiltered(array $filters = []): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)');

        $this->applyFilters($qb, $filters);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Retorna categorias distintas que possuem produtos cadastrados.
     *
     * @return string[]
     */
    public function findDistinctCategories(): array
    {
        $results = $this->createQueryBuilder('p')
            ->select('DISTINCT p.category')
            ->where('p.category IS NOT NULL')
            ->andWhere('p.category != :empty')
            ->setParameter('empty', '')
            ->orderBy('p.category', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_filter($results);
    }

    /**
     * Busca produtos mais recentes (para widgets/sidebar).
     *
     * @return Product[]
     */
    public function findLatest(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca produtos similares (mesma categoria, excluindo o atual).
     *
     * @return Product[]
     */
    public function findRelated(Product $product, int $limit = 4): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.category = :category')
            ->andWhere('p.id != :id')
            ->setParameter('category', $product->getCategory())
            ->setParameter('id', $product->getId())
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Estatísticas gerais para o dashboard admin.
     */
    public function getStats(): array
    {
        $qb = $this->createQueryBuilder('p');

        $total = (int) $qb->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        $avgPrice = (float) $this->createQueryBuilder('p')
            ->select('AVG(p.currentPrice)')
            ->getQuery()
            ->getSingleScalarResult();

        $updatedToday = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.lastUpdateAt >= :today')
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getSingleScalarResult();

        $categoriesCount = (int) $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.category)')
            ->where('p.category IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total'           => $total,
            'avgPrice'        => $avgPrice,
            'updatedToday'    => $updatedToday,
            'categoriesCount' => $categoriesCount,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    private function buildFilteredQuery(array $filters, string $orderBy, string $order): \Doctrine\ORM\QueryBuilder
    {
        $allowedOrderBy = ['createdAt', 'currentPrice', 'name', 'lastUpdateAt'];
        $allowedOrder   = ['ASC', 'DESC'];

        if (!in_array($orderBy, $allowedOrderBy, true)) {
            $orderBy = 'createdAt';
        }
        if (!in_array(strtoupper($order), $allowedOrder, true)) {
            $order = 'DESC';
        }

        $qb = $this->createQueryBuilder('p');
        $this->applyFilters($qb, $filters);
        $qb->orderBy("p.{$orderBy}", $order);

        return $qb;
    }

    private function applyFilters(\Doctrine\ORM\QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['category'])) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $filters['category']);
        }

        if (!empty($filters['min_price'])) {
            $qb->andWhere('p.currentPrice >= :minPrice')
               ->setParameter('minPrice', (float) $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $qb->andWhere('p.currentPrice <= :maxPrice')
               ->setParameter('maxPrice', (float) $filters['max_price']);
        }

        if (!empty($filters['search'])) {
            $qb->andWhere('p.name LIKE :search OR p.description LIKE :search OR p.aiVerdict LIKE :search')
               ->setParameter('search', '%' . addcslashes($filters['search'], '%_') . '%');
        }
    }
}
