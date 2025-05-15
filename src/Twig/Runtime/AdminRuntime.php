<?php

namespace App\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

class AdminRuntime implements RuntimeExtensionInterface
{
    public function __construct()
    {
        // Inject dependencies if needed
    }

    public function adminType($value): string
    {
        if ($value === null) {
            return 'null';
        }
        // Déterminer le type de $value
        if (is_string($value)) {
            return 'string';
        } elseif (is_int($value)) {
            return 'integer';
        } elseif (is_float($value)) {
            return 'float';
        } elseif (is_bool($value)) {
            return 'boolean';
        } elseif (is_array($value)) {
            return 'array';
        } elseif ($value instanceof \DateTimeInterface) {
            return 'datetime';
        } else {
            return 'unknown';
        }
    }
}
