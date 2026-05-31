<?php

namespace App\Entity;

use App\Repository\HomepageSlotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HomepageSlotRepository::class)]
#[ORM\Table(name: 'homepage_slot')]
class HomepageSlot
{
    public const COUNT = 10;

    #[ORM\Id]
    #[ORM\Column]
    private int $slotNumber = 1;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Category $category = null;

    public function getSlotNumber(): int
    {
        return $this->slotNumber;
    }

    public function setSlotNumber(int $slotNumber): static
    {
        $this->slotNumber = $slotNumber;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }
}
