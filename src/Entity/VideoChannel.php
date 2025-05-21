<?php

namespace App\Entity;

use App\Repository\VideoChannelRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VideoChannelRepository::class)]
class VideoChannel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $youTubeId = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnail = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Video>
     */
    #[ORM\OneToMany(targetEntity: Video::class, mappedBy: 'channel')]
    private Collection $videos;

    public function __construct(string $youTubeId, string $title, ?string $customUrl, ?string $thumbnailUrl, DateTimeImmutable $updatedAt)
    {
        $this->customUrl = $customUrl;
        $this->thumbnail = $thumbnailUrl;
        $this->title = $title;
        $this->updatedAt = $updatedAt;
        $this->videos = new ArrayCollection();
        $this->youTubeId = $youTubeId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getYouTubeId(): ?string
    {
        return $this->youTubeId;
    }

    public function setYouTubeId(string $youTubeId): static
    {
        $this->youTubeId = $youTubeId;

        return $this;
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

    public function getCustomUrl(): ?string
    {
        return $this->customUrl;
    }

    public function setCustomUrl(?string $customUrl): static
    {
        $this->customUrl = $customUrl;

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

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, Video>
     */
    public function getVideos(): Collection
    {
        return $this->videos;
    }

    public function addVideo(Video $video): static
    {
        if (!$this->videos->contains($video)) {
            $this->videos->add($video);
            $video->setChannel($this);
        }

        return $this;
    }

    public function removeVideo(Video $video): static
    {
        if ($this->videos->removeElement($video)) {
            // set the owning side to null (unless already changed)
            if ($video->getChannel() === $this) {
                $video->setChannel(null);
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'youTubeId' => $this->getYouTubeId(),
            'title' => $this->getTitle(),
            'customUrl' => $this->getCustomUrl(),
            'thumbnail' => $this->getThumbnail(),
            'updatedAt' => $this->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}
