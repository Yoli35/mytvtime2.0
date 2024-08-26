<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\EpisodeNotificationExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class EpisodeNotificationExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // If your filter generates SAFE HTML, you should add a third
            // parameter: ['is_safe' => ['html']]
            // Reference: https://twig.symfony.com/doc/3.x/advanced.html#automatic-escaping
            new TwigFilter('countEpisodeNotifications', [EpisodeNotificationExtensionRuntime::class, 'countEpisodeNotifications']),
            new TwigFilter('listEpisodeNotifications', [EpisodeNotificationExtensionRuntime::class, 'listEpisodeNotifications']),
            new TwigFilter('listEpisodeOfTheDay', [EpisodeNotificationExtensionRuntime::class, 'listEpisodeOfTheDay']),
            new TwigFilter('seriesHistory', [EpisodeNotificationExtensionRuntime::class, 'seriesHistory']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('countEpisodeNotifications', [EpisodeNotificationExtensionRuntime::class, 'countEpisodeNotifications']),
            new TwigFunction('listEpisodeNotifications', [EpisodeNotificationExtensionRuntime::class, 'listEpisodeNotifications']),
            new TwigFunction('listEpisodeOfTheDay', [EpisodeNotificationExtensionRuntime::class, 'listEpisodeOfTheDay']),
            new TwigFunction('seriesHistory', [EpisodeNotificationExtensionRuntime::class, 'seriesHistory']),
        ];
    }
}
