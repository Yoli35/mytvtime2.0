<?php

namespace App\Entity;

use App\Repository\TimezoneBookmarkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TimezoneBookmarkRepository::class)]
class TimezoneBookmark
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private ?string $code;

    #[ORM\Column(length: 255)]
    private ?string $nameFR;


    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nameEN;


    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nameKO;

    public function __construct(string $code, string $nameFR, string $nameEN, string $nameKO)
    {
        $this->code = $code;
        $this->nameFR = $nameFR;
        $this->nameEN = $nameEN;
        $this->nameKO = $nameKO;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNameFR(): ?string
    {
        return $this->nameFR;
    }

    public function setNameFR(string $name): static
    {
        $this->nameFR = $name;

        return $this;
    }

    public function getNameEN(): ?string
    {
        return $this->nameEN;
    }

    public function setNameEN(string $name): static
    {
        $this->nameEN = $name;

        return $this;
    }

    public function getNameKO(): ?string
    {
        return $this->nameKO;
    }

    public function setNameKO(string $name): static
    {
        $this->nameKO = $name;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }
}
