<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[Vich\Uploadable]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['username'], message: 'There is already an account with this username')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $username = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $preferredLanguage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $timezone = null;

    #[Vich\UploadableField(mapping: 'avatars', fileNameProperty: 'avatar', size: 'avatarSize')]
    private ?File $avatarFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(nullable: true)]
    private ?int $avatarSize = null;

    #[Vich\UploadableField(mapping: 'banners', fileNameProperty: 'banner', size: 'bannerSize')]
    private ?File $bannerFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $banner = null;

    #[ORM\Column(nullable: true)]
    private ?int $bannerSize = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(targetEntity: UserSeries::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $series;

    #[ORM\ManyToMany(targetEntity: Provider::class)]
    private Collection $providers;

    #[ORM\OneToMany(targetEntity: UserEpisode::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $userEpisodes;

    /**
     * @var Collection<int, UserEpisodeNotification>
     */
    #[ORM\OneToMany(targetEntity: UserEpisodeNotification::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $userEpisodeNotifications;

    /**
     * @var Collection<int, UserPinnedSeries>
     */
    #[ORM\OneToMany(targetEntity: UserPinnedSeries::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $userPinnedSeries;

    public function __construct()
    {
        $this->series = new ArrayCollection();
        $this->providers = new ArrayCollection();
        $this->userEpisodes = new ArrayCollection();
        $this->userEpisodeNotifications = new ArrayCollection();
        $this->userPinnedSeries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getPreferredLanguage(): ?string
    {
        return $this->preferredLanguage;
    }

    public function setPreferredLanguage(?string $preferredLanguage): static
    {
        $this->preferredLanguage = $preferredLanguage;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getBanner(): ?string
    {
        return $this->banner;
    }

    public function setBanner(?string $banner): static
    {
        $this->banner = $banner;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    /**
     * @return Collection<int, UserSeries>
     */
    public function getSeries(): Collection
    {
        return $this->series;
    }

    public function addSeries(UserSeries $series): static
    {
        if (!$this->series->contains($series)) {
            $this->series->add($series);
            $series->setUser($this);
        }

        return $this;
    }

    public function removeSeries(UserSeries $series): static
    {
        if ($this->series->removeElement($series)) {
            // set the owning side to null (unless already changed)
            if ($series->getUser() === $this) {
                $series->setUser(null);
            }
        }

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getAvatarSize(): ?int
    {
        return $this->avatarSize;
    }

    public function setAvatarSize(?int $avatarSize): void
    {
        $this->avatarSize = $avatarSize;
    }

    public function getBannerSize(): ?int
    {
        return $this->bannerSize;
    }

    public function setBannerSize(?int $bannerSize): void
    {
        $this->bannerSize = $bannerSize;
    }

    public function getAvatarFile(): ?File
    {
        return $this->avatarFile;
    }

    public function setAvatarFile(?File $avatarFile): void
    {
        if (null !== $avatarFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
        $this->avatarFile = $avatarFile;
    }

    public function getBannerFile(): ?File
    {
        return $this->bannerFile;
    }

    public function setBannerFile(?File $bannerFile): void
    {
        if (null !== $bannerFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
        $this->bannerFile = $bannerFile;
    }

    /**
     * @return Collection<int, Provider>
     */
    public function getProviders(): Collection
    {
        return $this->providers;
    }

    public function addProvider(Provider $provider): static
    {
        if (!$this->providers->contains($provider)) {
            $this->providers->add($provider);
        }

        return $this;
    }

    public function removeProvider(Provider $provider): static
    {
        $this->providers->removeElement($provider);

        return $this;
    }

    /**
     * @return Collection<int, UserEpisode>
     */
    public function getUserEpisodes(): Collection
    {
        return $this->userEpisodes;
    }

    public function addUserEpisode(UserEpisode $userEpisode): static
    {
        if (!$this->userEpisodes->contains($userEpisode)) {
            $this->userEpisodes->add($userEpisode);
            $userEpisode->setUser($this);
        }

        return $this;
    }

    public function removeUserEpisode(UserEpisode $userEpisode): static
    {
        if ($this->userEpisodes->removeElement($userEpisode)) {
            // set the owning side to null (unless already changed)
            if ($userEpisode->getUser() === $this) {
                $userEpisode->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserEpisodeNotification>
     */
    public function getUserEpisodeNotifications(): Collection
    {
        return $this->userEpisodeNotifications;
    }

    public function addUserEpisodeNotification(UserEpisodeNotification $userEpisodeNotification): static
    {
        if (!$this->userEpisodeNotifications->contains($userEpisodeNotification)) {
            $this->userEpisodeNotifications->add($userEpisodeNotification);
            $userEpisodeNotification->addUser($this);
        }

        return $this;
    }

    public function removeUserEpisodeNotification(UserEpisodeNotification $userEpisodeNotification): static
    {
        if ($this->userEpisodeNotifications->removeElement($userEpisodeNotification)) {
            $userEpisodeNotification->removeUser($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, UserPinnedSeries>
     */
    public function getUserPinnedSeries(): Collection
    {
        return $this->userPinnedSeries;
    }

    public function addUserPinnedSeries(UserPinnedSeries $userPinnedSeries): static
    {
        if (!$this->userPinnedSeries->contains($userPinnedSeries)) {
            $this->userPinnedSeries->add($userPinnedSeries);
            $userPinnedSeries->setUser($this);
        }

        return $this;
    }

    public function removeUserPinnedSeries(UserPinnedSeries $userPinnedSeries): static
    {
        if ($this->userPinnedSeries->removeElement($userPinnedSeries)) {
            // set the owning side to null (unless already changed)
            if ($userPinnedSeries->getUser() === $this) {
                $userPinnedSeries->setUser(null);
            }
        }

        return $this;
    }
}
