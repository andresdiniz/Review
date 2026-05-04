<?php
// src/Repository/PriceHistoryRepository.php

namespace App\Repository;

use App\Entity\PriceHistory;
use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PriceHistory> */
class PriceHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PriceHistory::class);
    }

    /**
     * Últimos N registros de preço de um produto, ordenados por data ASC.
     */
    public function findByProduct(Product $product, int $limit = 30): array
    {
        return $this->createQueryBuilder('ph')
            ->where('ph.product = :product')
            ->setParameter('product', $product)
            ->orderBy('ph.recordedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Preço mais baixo já registrado para o produto.
     */
    public function findLowestPrice(Product $product): ?string
    {
        return $this->createQueryBuilder('ph')
            ->select('MIN(ph.price)')
            ->where('ph.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Salva o preço atual do produto se for diferente do último registrado.
     */
    public function recordIfChanged(Product $product): void
    {
        $last = $this->createQueryBuilder('ph')
            ->where('ph.product = :product')
            ->setParameter('product', $product)
            ->orderBy('ph.recordedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($last === null || (string) $last->getPrice() !== (string) $product->getCurrentPrice()) {
            $history = new PriceHistory();
            $history->setProduct($product);
            $history->setPrice($product->getCurrentPrice());
            $history->setRecordedAt(new \DateTimeImmutable());
            $this->getEntityManager()->persist($history);
            $this->getEntityManager()->flush();
        }
    }
}
