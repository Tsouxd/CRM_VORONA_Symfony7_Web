<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class NumberToWordsExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('en_lettres', [$this, 'convertirEnLettres']),
        ];
    }

    public function convertirEnLettres($number): string
    {
        $formatter = new \NumberFormatter('fr_FR', \NumberFormatter::SPELLOUT);
        $parts = explode('.', number_format($number, 2, '.', ''));

        $lettres = ucfirst($formatter->format($parts[0])) . ' Ariary';
        if ((int)$parts[1] > 0) {
            $lettres .= ' et ' . $formatter->format($parts[1]) . ' cents';
        }

        return $lettres;
    }
}
