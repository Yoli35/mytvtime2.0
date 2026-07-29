<?php

namespace App\Entity;

use App\Repository\UserSeasonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserSeasonRepository::class)]
class UserSeason
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userSeasons')]
    #[ORM\JoinColumn(nullable: false)]
    private ?UserSeries $userSeries = null;

    #[ORM\ManyToMany(targetEntity: SeriesBroadcastSchedule::class, inversedBy: 'userSeasons')]
    private Collection $broadcastSchedules;

    #[ORM\Column]
    private ?int $seasonNumber = null;

    #[ORM\OneToMany(targetEntity: UserEpisode::class, mappedBy: 'userSeason', fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[ORM\OrderBy(['seasonNumber' => 'ASC', 'episodeNumber' => 'ASC'])]
    private Collection $userEpisodes;

    public function __construct(UserSeries $userSeries, int $seasonNumber)
    {
        $this->userSeries = $userSeries;
        $this->seasonNumber = $seasonNumber;
        $this->userEpisodes = new ArrayCollection();
        $this->broadcastSchedules = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserSeries(): ?UserSeries
    {
        return $this->userSeries;
    }

    public function setUserSeries(?UserSeries $userSeries): static
    {
        $this->userSeries = $userSeries;

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
            $userEpisode->setUserSeason($this);
        }

        return $this;
    }

    public function removeUserEpisode(UserEpisode $userEpisode): static
    {
        if ($this->userEpisodes->removeElement($userEpisode)) {
            // set the owning side to null (unless already changed)
            if ($userEpisode->getUserSeason() === $this) {
                $userEpisode->setUserSeason(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, SeriesBroadcastSchedule>
     */
    public function getBroadcastSchedules(): Collection
    {
        return $this->broadcastSchedules;
    }

    public function addBroadcastSchedule(SeriesBroadcastSchedule $seriesBroadcastSchedule): static
    {
        if (!$this->broadcastSchedules->contains($seriesBroadcastSchedule)) {
            $this->broadcastSchedules->add($seriesBroadcastSchedule);
        }

        return $this;
    }

    public function removeBroadcastSchedule(SeriesBroadcastSchedule $seriesBroadcastSchedule): static
    {
        $this->broadcastSchedules->removeElement($seriesBroadcastSchedule);

        return $this;
    }
}
