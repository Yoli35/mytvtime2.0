<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\AdminRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AdminExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('adminType', [AdminRuntime::class, 'adminType']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('adminType', [AdminRuntime::class, 'adminType']),
        ];
    }
}
