<?php

namespace App\Entity;

use App\Repository\ContactMessageRepository;
use DateInvalidTimeZoneException;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactMessageRepository::class)]
class ContactMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Please enter your name')]
    #[Assert\Length(min: 2, minMessage: 'Your name should be at least 2 characters')]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Please enter your email')]
    #[Assert\Length(min: 2, minMessage: 'Please enter a valid email')]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Please enter a subject')]
    #[Assert\Length(min: 2, minMessage: 'Your subject should be at least 2 characters')]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Please enter a message')]
    #[Assert\Length(min: 2, minMessage: 'Your message should be at least 10 characters')]
    private ?string $message = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?DateTimeImmutable $date;

    #[ORM\Column]
    private ?bool $messageRead;

    public function __construct(string $timezone = 'UTC')
    {
        try {
            $this->date = new DateTimeImmutable('now', new DateTimeZone($timezone));
        } catch (DateInvalidTimeZoneException) {
            $this->date = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        } catch (DateMalformedStringException ) {
            $this->date = new DateTimeImmutable();
        }
        $this->messageRead = false;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

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

    public function getDate(): ?DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function isMessageRead(): ?bool
    {
        return $this->messageRead;
    }

    public function setMessageRead(bool $messageRead): static
    {
        $this->messageRead = $messageRead;

        return $this;
    }
}
