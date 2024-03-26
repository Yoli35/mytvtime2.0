<?php

namespace App\Entity;

use App\Repository\EpisodeSubstituteNameRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EpisodeSubstituteNameRepository::class)]
class EpisodeSubstituteName
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $episodeId = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    public function __construct($episodeId, $name)
    {
        $this->episodeId = $episodeId;
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEpisodeId(): ?int
    {
        return $this->episodeId;
    }

    public function setEpisodeId(int $episodeId): static
    {
        $this->episodeId = $episodeId;

        return $this;
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
}
