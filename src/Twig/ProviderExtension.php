<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ProviderExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('providerLogo', [$this, 'providerLogo'], ['is_safe' => ['html']]),
            new TwigFunction('providerName', [$this, 'providerName'], ['is_safe' => ['html']]),
        ];
    }

    function providerLogo(array $providers, int $providerId): string
    {
        $provider = array_filter($providers, function ($provider) use ($providerId) {
            return $provider['id'] === $providerId;
        });
        $provider = array_shift($provider);
        return "<img src='{$provider['logoPath']}' alt='{$provider['name']}' title='{$provider['name']}' />";
    }

    function providerName(array $providers, int $providerId): string
    {
        $provider = array_filter($providers, function ($provider) use ($providerId) {
            return $provider['id'] === $providerId;
        });
        $provider = array_shift($provider);
        return $provider['name'];
    }
}
