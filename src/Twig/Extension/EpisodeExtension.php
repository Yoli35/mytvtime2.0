<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\EpisodeExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class EpisodeExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // If your filter generates SAFE HTML, you should add a third
            // parameter: ['is_safe' => ['html']]
            // Reference: https://twig.symfony.com/doc/3.x/advanced.html#automatic-escaping
            new TwigFilter('countEpisodeNotifications', [EpisodeExtensionRuntime::class, 'countEpisodeNotifications']),
            new TwigFilter('countNewEpisodeNotifications', [EpisodeExtensionRuntime::class, 'countNewEpisodeNotifications']),
            new TwigFilter('listEpisodeNotifications', [EpisodeExtensionRuntime::class, 'listEpisodeNotifications']),
            new TwigFilter('listEpisodeOfTheInterval', [EpisodeExtensionRuntime::class, 'listEpisodeOfTheInterval']),
            new TwigFilter('inProgressSeries', [EpisodeExtensionRuntime::class, 'inProgressSeries']),
            new TwigFilter('getLastEpisodeOfTheDayId', [EpisodeExtensionRuntime::class, 'getLastEpisodeOfTheDayId']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('countEpisodeNotifications', [EpisodeExtensionRuntime::class, 'countEpisodeNotifications']),
            new TwigFunction('countNewEpisodeNotifications', [EpisodeExtensionRuntime::class, 'countNewEpisodeNotifications']),
            new TwigFunction('listEpisodeNotifications', [EpisodeExtensionRuntime::class, 'listEpisodeNotifications']),
            new TwigFunction('listEpisodeOfTheInterval', [EpisodeExtensionRuntime::class, 'listEpisodeOfTheInterval']),
            new TwigFunction('inProgressSeries', [EpisodeExtensionRuntime::class, 'inProgressSeries']),
        ];
    }
}
