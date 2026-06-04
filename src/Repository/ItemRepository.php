<?php

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\ItemListPosition;
use App\Service\CacheTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Item> */
class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function findPageByCategory(Category $category, int $page, int $limit): array
    {
        $offset = max(0, ($page - 1) * $limit);

        return $this->createQueryBuilder('i')
            ->select('i.id', 'i.name')
            ->innerJoin('i.categories', 'c')
            ->where('c = :category')
            ->setParameter('category', $category)
            ->orderBy('CASE WHEN i.listPosition = :last THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('CASE WHEN i.listPosition = :last THEN i.id ELSE 0 END', 'ASC')
            ->addOrderBy('CASE WHEN i.listPosition = :last THEN 0 ELSE i.id END', 'DESC')
            ->setParameter('last', ItemListPosition::Last)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function countByCategory(Category $category): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(DISTINCT i.id)')
            ->innerJoin('i.categories', 'c')
            ->where('c = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function lastPageForCategory(Category $category, int $limit = CacheTag::API_PAGE_LIMIT): int
    {
        return CacheTag::lastPage($this->countByCategory($category), $limit);
    }
}
