<?php

namespace App\Twig;

use Twig\Attribute\AsTwigFunction;

class CountryExtension
{
    // From Smaine Milianni : https://smaine-milianni.medium.com/emoji-flag-in-the-symfony-countrytype-f794f39e6ac9
    #[AsTwigFunction('getEmojiFlag')]
    public function getEmojiFlag(string $countryCode): string
    {
        if ($countryCode === 'all') {
            return '🌍'; // ← Réponse donnée pas Copilot
        }
        $regionalOffset = 0x1F1A5;

        return mb_chr($regionalOffset + mb_ord($countryCode[0], 'UTF-8'), 'UTF-8')
            . mb_chr($regionalOffset + mb_ord($countryCode[1], 'UTF-8'), 'UTF-8');
    }
}
