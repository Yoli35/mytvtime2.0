<?php

namespace App\Entity;

use App\Repository\PointOfInterestCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PointOfInterestCategoryRepository::class)]
class PointOfInterestCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, PointOfInterest>
     */
    #[ORM\ManyToMany(targetEntity: PointOfInterest::class, mappedBy: 'category')]
    private Collection $pointOfInterests;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $icon = null;

    public function __construct()
    {
        $this->pointOfInterests = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, PointOfInterest>
     */
    public function getPointOfInterests(): Collection
    {
        return $this->pointOfInterests;
    }

    public function addPointOfInterest(PointOfInterest $pointOfInterest): static
    {
        if (!$this->pointOfInterests->contains($pointOfInterest)) {
            $this->pointOfInterests->add($pointOfInterest);
            $pointOfInterest->addCategory($this);
        }

        return $this;
    }

    public function removePointOfInterest(PointOfInterest $pointOfInterest): static
    {
        if ($this->pointOfInterests->removeElement($pointOfInterest)) {
            $pointOfInterest->removeCategory($this);
        }

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }
}
