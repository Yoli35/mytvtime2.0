<?php

namespace App\Entity;

use App\Repository\PhotoRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PhotoRepository::class)]
class Photo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'photos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToMany(targetEntity: Album::class, inversedBy: 'photos')]
    private Collection $albums;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(length: 255)]
    private ?string $image_path = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $date = null;

    #[ORM\Column(nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitude = null;

    public function __construct(
        ?User              $user = null,
        ?Album             $album = null,
        ?string            $caption = null,
        string             $image_path = '',
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        ?DateTimeImmutable $date = null,
        ?float             $latitude = null,
        ?float             $longitude = null
    ) {
        $this->user = $user;
        $this->albums = new ArrayCollection();
        $album?->addPhoto($this);
        $this->caption = $caption;
        $this->image_path = $image_path;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
        $this->date = $date ?? new DateTimeImmutable();
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public function __toString(): string
    {
        return $this->image_path;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'user_id' => $this->getUser()->getId(),
            /*'album_id' => $this->getAlbum()->getId(),*/
            'caption' => $this->getCaption(),
            'image_path' => $this->getImagePath(),
            'created_at_string' => $this->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at_string' => $this->getUpdatedAt()->format('Y-m-d H:i:s'),
            'date_string' => $this->getDate()->format('Y-m-d H:i:s'),
            'latitude' => $this->getLatitude(),
            'longitude' => $this->getLongitude(),
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): static
    {
        $this->caption = $caption;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->image_path;
    }

    public function setImagePath(string $image_path): static
    {
        $this->image_path = $image_path;

        return $this;
    }

    /** @return Collection<int, Album> */
    public function getAlbums(): Collection
    {
        return $this->albums;
    }

    public function addAlbum(Album $album): static
    {
        if (!$this->albums->contains($album)) {
            $this->albums->add($album);
        }

        return $this;
    }

    public function removeAlbum(Album $album): static
    {
        $this->albums->removeElement($album);

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

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDate(): ?DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }
}
