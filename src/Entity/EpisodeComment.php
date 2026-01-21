<?php

namespace App\Entity;

use App\Repository\EpisodeCommentRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EpisodeCommentRepository::class)]
class EpisodeComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'episodeComments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'episodeComments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Series $series = null;

    #[ORM\Column]
    private ?int $tmdbId = null;

    #[ORM\Column]
    private ?int $seasonNumber = null;

    #[ORM\Column]
    private ?int $episodeNumber = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'episodeComments')]
    private ?self $replyTo = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'replyTo')]
    private Collection $episodeComments;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, EpisodeCommentImage>
     */
    #[ORM\OneToMany(targetEntity: EpisodeCommentImage::class, mappedBy: 'episodeComment', orphanRemoval: true)]
    private Collection $episodeCommentImages;

    public function __construct(User $user, Series $series, int $tmdbId, int $seasonNumber, int $episodeNumber, string $message, DateTimeImmutable $createdAt)
    {
        $this->user = $user;
        $this->series = $series;
        $this->tmdbId = $tmdbId;
        $this->seasonNumber = $seasonNumber;
        $this->episodeNumber = $episodeNumber;
        $this->message = $message;
        $this->createdAt = $createdAt;
        $this->episodeComments = new ArrayCollection();
        $this->episodeCommentImages = new ArrayCollection();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'user' => [
                'id' => $this->getUser()?->getId(),
                'username' => $this->getUser()?->getUsername(),
                'avatar' => $this->getUser()?->getAvatar(),
            ],
            'tmdbId' => $this->getTmdbId(),
            'seasonNumber' => $this->getSeasonNumber(),
            'episodeNumber' => $this->getEpisodeNumber(),
            'message' => $this->getMessage(),
            'createdAt' => $this->getCreatedAt()->format('Y-m-d H:i:s'),
            'replyTo' => $this->getReplyTo()?->getId(),
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeries(): ?Series
    {
        return $this->series;
    }

    public function setSeries(?Series $series): static
    {
        $this->series = $series;

        return $this;
    }

    public function getTmdbId(): ?int
    {
        return $this->tmdbId;
    }

    public function setTmdbId(int $tmdbId): static
    {
        $this->tmdbId = $tmdbId;

        return $this;
    }

    public function getSeasonNumber(): ?int
    {
        return $this->seasonNumber;
    }

    public function setSeasonNumber(int $seasonNumber): static
    {
        $this->seasonNumber = $seasonNumber;

        return $this;
    }

    public function getEpisodeNumber(): ?int
    {
        return $this->episodeNumber;
    }

    public function setEpisodeNumber(int $episodeNumber): static
    {
        $this->episodeNumber = $episodeNumber;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getReplyTo(): ?self
    {
        return $this->replyTo;
    }

    public function setReplyTo(?self $replyTo): static
    {
        $this->replyTo = $replyTo;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getEpisodeComments(): Collection
    {
        return $this->episodeComments;
    }

    public function addEpisodeComment(self $episodeComment): static
    {
        if (!$this->episodeComments->contains($episodeComment)) {
            $this->episodeComments->add($episodeComment);
            $episodeComment->setReplyTo($this);
        }

        return $this;
    }

    public function removeEpisodeComment(self $episodeComment): static
    {
        if ($this->episodeComments->removeElement($episodeComment)) {
            // set the owning side to null (unless already changed)
            if ($episodeComment->getReplyTo() === $this) {
                $episodeComment->setReplyTo(null);
            }
        }

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, EpisodeCommentImage>
     */
    public function getEpisodeCommentImages(): Collection
    {
        return $this->episodeCommentImages;
    }

    public function addEpisodeCommentImage(EpisodeCommentImage $episodeCommentImage): static
    {
        if (!$this->episodeCommentImages->contains($episodeCommentImage)) {
            $this->episodeCommentImages->add($episodeCommentImage);
            $episodeCommentImage->setEpisodeComment($this);
        }

        return $this;
    }

    public function removeEpisodeCommentImage(EpisodeCommentImage $episodeCommentImage): static
    {
        if ($this->episodeCommentImages->removeElement($episodeCommentImage)) {
            // set the owning side to null (unless already changed)
            if ($episodeCommentImage->getEpisodeComment() === $this) {
                $episodeCommentImage->setEpisodeComment(null);
            }
        }

        return $this;
    }
}
