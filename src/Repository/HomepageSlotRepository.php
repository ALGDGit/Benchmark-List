<?php

namespace App\Repository;

use App\Entity\HomepageSlot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<HomepageSlot> */
class HomepageSlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomepageSlot::class);
    }

    /** @return HomepageSlot[] */
    public function findAllOrderedBySlot(): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.category', 'c')
            ->addSelect('c')
            ->orderBy('s.slotNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlotNumber(int $slotNumber): ?HomepageSlot
    {
        return $this->find($slotNumber);
    }

    /**
     * @return int[] Slot numbers (1–10) that display this category on the homepage
     */
    public function findSlotNumbersByCategoryId(int $categoryId): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('s.slotNumber')
            ->where('s.category = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map('intval', $rows);
    }
}
