<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\logHistoryRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class logHistoryExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('logHistory', [logHistoryRuntime::class, 'logHistory']),
            new TwigFilter('getHistory', [logHistoryRuntime::class, 'getHistory']),
            new TwigFilter('getHistoryCount', [logHistoryRuntime::class, 'getHistoryCount']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('logHistory', [logHistoryRuntime::class, 'logHistory']),
            new TwigFunction('getHistory', [logHistoryRuntime::class, 'getHistory']),
            new TwigFunction('getHistoryCount', [logHistoryRuntime::class, 'getHistoryCount']),
        ];
    }
}
