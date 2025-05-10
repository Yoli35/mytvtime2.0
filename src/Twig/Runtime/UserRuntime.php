<?php

namespace App\Twig\Runtime;

use App\Repository\UserRepository;
use Twig\Extension\RuntimeExtensionInterface;

readonly class UserRuntime implements RuntimeExtensionInterface
{
    public function __construct(private UserRepository $repository)
    {
    }

    public function getUsers(): array
    {
        return $this->repository->findAll();
    }
}
