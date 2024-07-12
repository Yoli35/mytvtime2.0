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
            // If your filter generates SAFE HTML, you should add a third
            // parameter: ['is_safe' => ['html']]
            // Reference: https://twig.symfony.com/doc/3.x/advanced.html#automatic-escaping
            new TwigFilter('time', [FormatRuntime::class, 'time']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('time', [FormatRuntime::class, 'time']),
        ];
    }
}
