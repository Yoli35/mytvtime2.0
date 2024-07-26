<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\FormatRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class FormatExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('time', [FormatRuntime::class, 'time'], ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('time', [FormatRuntime::class, 'time'], ['is_safe' => ['html']]),
        ];
    }
}
