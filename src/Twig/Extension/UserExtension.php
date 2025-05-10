<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\UserRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class UserExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('getUsers', [UserRuntime::class, 'getUsers']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getUsers', [UserRuntime::class, 'getUsers']),
        ];
    }
}
