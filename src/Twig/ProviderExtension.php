<?php

namespace App\Twig;

use Twig\Attribute\AsTwigFunction;

class ProviderExtension
{
    #[AsTwigFunction('providerLogo')]
    function providerLogo(array $providers, int $providerId): string
    {
        $provider = array_filter($providers, function ($provider) use ($providerId) {
            return $provider['id'] === $providerId;
        });
        $provider = array_shift($provider);
        return "<img src='{$provider['logoPath']}' alt='{$provider['name']}' title='{$provider['name']}' />";
    }

    #[AsTwigFunction('providerName')]
    function providerName(array $providers, int $providerId): string
    {
        $provider = array_filter($providers, function ($provider) use ($providerId) {
            return $provider['id'] === $providerId;
        });
        $provider = array_shift($provider);
        return $provider['name'];
    }
}
