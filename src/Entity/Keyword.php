<?php

namespace App\Entity;

use App\Repository\KeywordRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KeywordRepository::class)]
class Keyword
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $keywordId = null;

    /**
     * @var Collection<int, Series>
     */
    #[ORM\ManyToMany(targetEntity: Series::class, mappedBy: 'keywords')]
    private Collection $series;

    public function __construct(string $name, int $keywordId)
    {
        $this->name = $name;
        $this->keywordId = $keywordId;
        $this->series = new ArrayCollection();
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

    public function getKeywordId(): ?int
    {
        return $this->keywordId;
    }

    public function setKeywordId(int $keywordId): static
    {
        $this->keywordId = $keywordId;

        return $this;
    }

    /**
     * @return Collection<int, Series>
     */
    public function getSeries(): Collection
    {
        return $this->series;
    }

    public function addSeries(Series $series): static
    {
        if (!$this->series->contains($series)) {
            $this->series->add($series);
            $series->addKeyword($this);
        }

        return $this;
    }

    public function removeSeries(Series $series): static
    {
        if ($this->series->removeElement($series)) {
            $series->removeKeyword($this);
        }

        return $this;
    }
}
