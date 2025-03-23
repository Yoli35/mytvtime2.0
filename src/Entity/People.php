<?php

namespace App\Entity;

use App\Repository\PeopleRepository;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity(repositoryClass: PeopleRepository::class)]
class People
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $adult;

    #[ORM\Column(type: Types::ARRAY)]
    private array $alsoKnownAs;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $biography;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?DateTimeInterface $birthday;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $deathday;

    #[ORM\Column]
    private ?int $gender;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $homepage;

    #[ORM\Column]
    private ?int $tmdbId;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $imdbId;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $knownForDepartment;

    #[ORM\Column(length: 255)]
    private ?string $name;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $placeOfBirth;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profilePath;

    public function __construct(
        bool               $adult,
        array              $alsoKnownAs,
        ?string            $biography,
        DateTimeInterface  $birthday,
        ?DateTimeInterface $deathday,
        int                $gender,
        ?string            $homepage,
        int                $tmdbId,
        ?string            $imdbId,
        ?string            $knownForDepartment,
        string             $name,
        ?string            $placeOfBirth,
        ?string            $profilePath
    )
    {
        $this->adult = $adult;
        $this->alsoKnownAs = $alsoKnownAs;
        $this->biography = $biography;
        $this->birthday = $birthday;
        $this->deathday = $deathday;
        $this->gender = $gender;
        $this->homepage = $homepage;
        $this->tmdbId = $tmdbId;
        $this->imdbId = $imdbId;
        $this->knownForDepartment = $knownForDepartment;
        $this->name = $name;
        $this->placeOfBirth = $placeOfBirth;
        $this->profilePath = $profilePath;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isAdult(): ?bool
    {
        return $this->adult;
    }

    public function setAdult(bool $adult): static
    {
        $this->adult = $adult;

        return $this;
    }

    public function getAlsoKnownAs(): array
    {
        return $this->alsoKnownAs;
    }

    public function setAlsoKnownAs(array $alsoKnownAs): static
    {
        $this->alsoKnownAs = $alsoKnownAs;

        return $this;
    }

    public function getBiography(): ?string
    {
        return $this->biography;
    }

    public function setBiography(?string $biography): static
    {
        $this->biography = $biography;

        return $this;
    }

    public function getBirthday(): ?DateTimeInterface
    {
        return $this->birthday;
    }

    public function setBirthday(DateTimeInterface $birthday): static
    {
        $this->birthday = $birthday;

        return $this;
    }

    public function getDeathday(): ?DateTimeInterface
    {
        return $this->deathday;
    }

    public function setDeathday(?DateTimeInterface $deathday): static
    {
        $this->deathday = $deathday;

        return $this;
    }

    public function getGender(): ?int
    {
        return $this->gender;
    }

    public function setGender(int $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    public function getHomepage(): ?string
    {
        return $this->homepage;
    }

    public function setHomepage(?string $homepage): static
    {
        $this->homepage = $homepage;

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

    public function getImdbId(): ?string
    {
        return $this->imdbId;
    }

    public function setImdbId(?string $imdbId): static
    {
        $this->imdbId = $imdbId;

        return $this;
    }

    public function getKnownForDepartment(): ?string
    {
        return $this->knownForDepartment;
    }

    public function setKnownForDepartment(?string $knownForDepartment): static
    {
        $this->knownForDepartment = $knownForDepartment;

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

    public function getPlaceOfBirth(): ?string
    {
        return $this->placeOfBirth;
    }

    public function setPlaceOfBirth(?string $placeOfBirth): static
    {
        $this->placeOfBirth = $placeOfBirth;

        return $this;
    }

    public function getProfilePath(): ?string
    {
        return $this->profilePath;
    }

    public function setProfilePath(?string $profilePath): static
    {
        $this->profilePath = $profilePath;

        return $this;
    }

    public function toArray(): array
    {
        $slugger = new AsciiSlugger();
        return [
            'id' => $this->getId(),
            'adult' => $this->isAdult(),
            'also_known_as' => $this->getAlsoKnownAs(),
            'biography' => $this->getBiography(),
            'birthday' => $this->getBirthday(),
            'deathday' => $this->getDeathday(),
            'gender' => $this->getGender(),
            'homepage' => $this->getHomepage(),
            'tmdb_id' => $this->getTmdbId(),
            'imdb_id' => $this->getImdbId(),
            'known_for_department' => $this->getKnownForDepartment(),
            'name' => $this->getName(),
            'slug' => $slugger->slug($this->getName())->lower(),
            'place_of_birth' => $this->getPlaceOfBirth(),
            'profile_path' => $this->getProfilePath(),
        ];
    }
}
