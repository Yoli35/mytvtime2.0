<?php

namespace App\Entity;

use App\Repository\VideoRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VideoRepository::class)]
class Video
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title;

    #[ORM\Column(length: 255)]
    private ?string $link;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnail = null;

    #[ORM\ManyToOne(inversedBy: 'videos')]
    private ?VideoChannel $channel = null;

    #[ORM\Column]
    private ?DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $duration = null;

    private ?string $durationString = null;

    /**
     * @var Collection<int, VideoCategory>
     */
    #[ORM\ManyToMany(targetEntity: VideoCategory::class, inversedBy: 'videos')]
    private Collection $categories;

    public function __construct(string $title, string $link)
    {
        $this->title = $title;
        $this->link = $link;
        $this->categories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(string $link): static
    {
        $this->link = $link;

        return $this;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    public function getChannel(): ?VideoChannel
    {
        return $this->channel;
    }

    public function setChannel(?VideoChannel $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function getPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getDurationString(): ?string
    {
        return $this->durationString;
    }

    public function setDurationString(?string $durationString): void
    {
        $this->durationString = $durationString;
    }

    /**
     * @return Collection<int, VideoCategory>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(VideoCategory $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(VideoCategory $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }
}
