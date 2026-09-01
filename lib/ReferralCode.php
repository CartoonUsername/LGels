<?php

/**
 * Kurze, gut vorlesbare Codes für Empfehlungslinks (Partner UND Kunden-
 * Weiterempfehlung) — ohne verwechselbare Zeichen (0/O, 1/I) und ohne
 * DB-Zugriff, Eindeutigkeit wird beim Insert über UNIQUE KEY erzwungen
 * (bei Kollision wird einfach neu generiert).
 */
final class ReferralCode
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function generiere(int $laenge = 6): string
    {
        $code = '';
        for ($i = 0; $i < $laenge; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        return $code;
    }
}
