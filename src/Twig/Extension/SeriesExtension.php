<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\SeriesExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class SeriesExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('seriesHistory', [SeriesExtensionRuntime::class, 'seriesHistory']),
            new TwigFilter('hasSeriesStartedAiring', [SeriesExtensionRuntime::class, 'hasSeriesStartedAiring']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('seriesHistory', [SeriesExtensionRuntime::class, 'seriesHistory']),
        ];
    }
}
