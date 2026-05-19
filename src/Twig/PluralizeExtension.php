<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PluralizeExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('pluralize', $this->pluralize(...)),
        ];
    }

    /**
     * Returns the correct Polish plural form for a given count.
     *
     * Rules:
     *   n = 1                                    → $one   (e.g. "korekta")
     *   n % 10 in [2,3,4] AND n % 100 not in [12,13,14] → $few   (e.g. "korekty")
     *   otherwise                                → $many  (e.g. "korekt")
     */
    public function pluralize(int $count, string $one, string $few, string $many): string
    {
        $mod10 = abs($count) % 10;
        $mod100 = abs($count) % 100;

        if (1 === $count) {
            return $one;
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
            return $few;
        }

        return $many;
    }
}
