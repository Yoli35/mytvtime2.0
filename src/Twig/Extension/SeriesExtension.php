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
            new TwigFilter('getUserCountrySettings', [SeriesExtensionRuntime::class, 'getUserCountrySettings']),
            new TwigFilter('getUserProviderSettings', [SeriesExtensionRuntime::class, 'getUserCProviderSettings']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('commentCountBySeries', [SeriesExtensionRuntime::class, 'commentCountBySeries']),
            new TwigFunction('seriesHistory', [SeriesExtensionRuntime::class, 'seriesHistory']),
            new TwigFunction('getUserCountrySettings', [SeriesExtensionRuntime::class, 'getUserCountrySettings']),
            new TwigFunction('getUserProviderSettings', [SeriesExtensionRuntime::class, 'getUserProviderSettings']),
        ];
    }
}
