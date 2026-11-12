<?php
declare(strict_types=1);

/**
 * Conversion d'un montant en toutes lettres (français), pour l'impression
 * des chèques et des fiches PDF.
 */
final class MontantEnLettres
{
    private const UNITS = [
        '', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
        'dix-sept', 'dix-huit', 'dix-neuf',
    ];

    private const TENS = [
        '', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante',
        'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix',
    ];

    public static function convertir(float|int|string|null $montant): string
    {
        if ($montant === null || $montant === '') {
            return '';
        }
        $value = round((float) $montant, 2, PHP_ROUND_HALF_UP);
        $dirhams = (int) floor($value);
        $centimes = (int) round(($value - $dirhams) * 100);

        $result = self::capitalize(self::enLettres($dirhams));
        if ($dirhams >= 1_000_000 && $dirhams % 1_000_000 === 0) {
            $result .= ' de';
        }
        $result .= $dirhams > 1 ? ' dirhams' : ' dirham';

        if ($centimes > 0) {
            $result .= ' et ' . self::enLettres($centimes)
                . ($centimes > 1 ? ' centimes' : ' centime');
        }

        return $result;
    }

    private static function enLettres(int $number): string
    {
        if ($number === 0) {
            return 'zéro';
        }

        $sb = '';
        $milliards = intdiv($number, 1_000_000_000);
        $millions = intdiv($number % 1_000_000_000, 1_000_000);
        $milliers = intdiv($number % 1_000_000, 1_000);
        $reste = $number % 1_000;

        if ($milliards > 0) {
            $sb .= self::below1000($milliards) . ' milliard' . ($milliards > 1 ? 's' : '');
        }
        if ($millions > 0) {
            $sb = self::appendSpace($sb);
            $sb .= self::below1000($millions) . ' million' . ($millions > 1 ? 's' : '');
        }
        if ($milliers > 0) {
            $sb = self::appendSpace($sb);
            if ($milliers === 1) {
                $sb .= 'mille';
            } else {
                $words = self::below1000($milliers);
                if (str_ends_with($words, 'cents') || str_ends_with($words, 'vingts')) {
                    $words = substr($words, 0, -1);
                }
                $sb .= $words . ' mille';
            }
        }
        if ($reste > 0) {
            $sb = self::appendSpace($sb);
            $sb .= self::below1000($reste);
        }

        return $sb;
    }

    private static function below1000(int $number): string
    {
        $hundreds = intdiv($number, 100);
        $rest = $number % 100;

        $sb = '';
        if ($hundreds === 1) {
            $sb .= 'cent';
        } elseif ($hundreds > 1) {
            $sb .= self::UNITS[$hundreds] . ' cent';
            if ($rest === 0) {
                $sb .= 's';
            }
        }
        if ($rest > 0) {
            if ($sb !== '') {
                $sb .= ' ';
            }
            $sb .= self::below100($rest);
        }

        return $sb;
    }

    private static function below100(int $number): string
    {
        if ($number < 20) {
            return self::UNITS[$number];
        }
        if ($number < 70) {
            $tens = intdiv($number, 10);
            $unit = $number % 10;
            if ($unit === 0) {
                return self::TENS[$tens];
            }
            if ($unit === 1) {
                return self::TENS[$tens] . ' et un';
            }
            return self::TENS[$tens] . '-' . self::UNITS[$unit];
        }
        if ($number < 80) {
            if ($number === 71) {
                return 'soixante et onze';
            }
            return 'soixante-' . self::below100($number - 60);
        }
        $rest = $number - 80;
        if ($rest === 0) {
            return 'quatre-vingts';
        }
        return 'quatre-vingt-' . self::below100($rest);
    }

    private static function appendSpace(string $text): string
    {
        return $text !== '' ? $text . ' ' : $text;
    }

    private static function capitalize(string $text): string
    {
        if ($text === '') {
            return $text;
        }
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }
}
