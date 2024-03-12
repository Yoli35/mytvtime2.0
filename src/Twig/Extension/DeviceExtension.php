<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\DeviceRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class DeviceExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // If your filter generates SAFE HTML, you should add a third
            // parameter: ['is_safe' => ['html']]
            // Reference: https://twig.symfony.com/doc/3.x/advanced.html#automatic-escaping
            new TwigFilter('deviceLogo', [DeviceRuntime::class, 'getDeviceLogPath']),
            new TwigFilter('deviceName', [DeviceRuntime::class, 'getDeviceName']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('deviceLogo', [DeviceRuntime::class, 'getDeviceLogPath']),
            new TwigFunction('deviceName', [DeviceRuntime::class, 'getDeviceName']),
        ];
    }
}
