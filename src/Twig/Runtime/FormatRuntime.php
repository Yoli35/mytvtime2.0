<?php

namespace App\Twig\Runtime;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\RuntimeExtensionInterface;

readonly class FormatRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private TranslatorInterface $translator,
    )
    {
        // Inject dependencies if needed
    }

    public function time(int $minutes): string
    {
        if ($minutes < 60) {
            return $this->translator->trans('%minutes% minutes', ['%minutes%' => $minutes]);
        }

        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;

        if ($hours > 1)
            $hours = $hours . ' ' . $this->translator->trans('hours');
        else
            $hours = $hours . ' ' . $this->translator->trans('hour');

        if ($minutes > 0) {
            if ($minutes > 1)
                $minutes = $minutes . ' ' . $this->translator->trans('minutes');
            else
                $minutes = $minutes . ' ' . $this->translator->trans('minute');

            return $hours . ' ' . $minutes;
        }
        return $hours;
    }
}
