<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
class ContactDTO
{
    #[
        Assert\NotBlank(message: 'Please enter your name'),
        Assert\Length(min: 2, minMessage: 'Your name should be at least 2 characters')
    ]
    private string $name = "";

    #[
        Assert\NotBlank(message: 'Please enter your email'),
        Assert\Email(message: 'Please enter a valid email')
    ]
    private string $email = "";

    #[
        Assert\NotBlank(message: 'Please enter a subject'),
        Assert\Length(min: 2, minMessage: 'Your subject should be at least 2 characters')
    ]
    private string $subject = "";

    #[
        Assert\NotBlank(message: 'Please enter a message'),
        Assert\Length(min: 10, minMessage: 'Your message should be at least 10 characters')
    ]
    private string $message = "";

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }
}