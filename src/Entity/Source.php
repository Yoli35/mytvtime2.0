<?php

namespace App\Entity;

use App\Repository\SourceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SourceRepository::class)]
class Source
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $path = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoPath = null;

    #[ORM\OneToMany(targetEntity: SeriesAdditionalOverview::class, mappedBy: 'source')]
    #[ORM\JoinColumn(nullable: false)]
    private Collection $seriesAdditionalOverviews;

    /**
     * @var Collection<int, MovieAdditionalOverview>
     */
    #[ORM\OneToMany(targetEntity: MovieAdditionalOverview::class, mappedBy: 'source')]
    private Collection $movieAdditionalOverviews;

    public function __construct()
    {
        $this->seriesAdditionalOverviews = new ArrayCollection();
        $this->movieAdditionalOverviews = new ArrayCollection();
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

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function setLogoPath(?string $logoPath): static
    {
        $this->logoPath = $logoPath;

        return $this;
    }

    public function getSeriesAdditionalOverviews(): Collection
    {
        return $this->seriesAdditionalOverviews;
    }

    public function addSeriesAdditionalOverview(SeriesAdditionalOverview $seriesAdditionalOverview): static
    {
        if (!$this->seriesAdditionalOverviews->contains($seriesAdditionalOverview)) {
            $this->seriesAdditionalOverviews->add($seriesAdditionalOverview);
            $seriesAdditionalOverview->setSource($this);
        }

        return $this;
    }

    public function removeSeriesAdditionalOverview(SeriesAdditionalOverview $seriesAdditionalOverview): static
    {
        if ($this->seriesAdditionalOverviews->removeElement($seriesAdditionalOverview)) {
            // set the owning side to null (unless already changed)
            if ($seriesAdditionalOverview->getSource() === $this) {
                $seriesAdditionalOverview->setSource(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, MovieAdditionalOverview>
     */
    public function getMovieAdditionalOverviews(): Collection
    {
        return $this->movieAdditionalOverviews;
    }

    public function addMovieAdditionalOverview(MovieAdditionalOverview $movieAdditionalOverview): static
    {
        if (!$this->movieAdditionalOverviews->contains($movieAdditionalOverview)) {
            $this->movieAdditionalOverviews->add($movieAdditionalOverview);
            $movieAdditionalOverview->setSource($this);
        }

        return $this;
    }

    public function removeMovieAdditionalOverview(MovieAdditionalOverview $movieAdditionalOverview): static
    {
        if ($this->movieAdditionalOverviews->removeElement($movieAdditionalOverview)) {
            // set the owning side to null (unless already changed)
            if ($movieAdditionalOverview->getSource() === $this) {
                $movieAdditionalOverview->setSource(null);
            }
        }

        return $this;
    }
}
