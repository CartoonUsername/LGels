<?php

/**
 * Lead-Scoring (0-100) statt reinem Ja/Nein. Läuft NACH der harten
 * Qualifizierung (Qualifier) und dient nur der Priorisierung/Triage im
 * Admin-Dashboard bzw. für spätere Verteilung an Abnehmer — beeinflusst nicht,
 * ob ein Lead überhaupt gespeichert wird.
 */
final class Scorer
{
    private const ENERGIEKOSTEN_PUNKTE = [
        'unter-50' => 5, '50-100' => 15, '100-150' => 25, '150-200' => 30, '200-250' => 35, 'ueber-250' => 40,
    ];

    private const INTERESSE_PUNKTE = [
        'photovoltaik' => 25, 'waermepumpe' => 25, 'landwirtschaft' => 30, 'vermieter' => 20,
        'stromtarif' => 10, 'sonstiges' => 8,
    ];

    public function berechne(array $lead): int
    {
        $punkte = 20; // Basis: hat die harte Qualifizierung bereits bestanden

        $punkte += self::ENERGIEKOSTEN_PUNKTE[$lead['energiekosten'] ?? ''] ?? 10;
        $punkte += self::INTERESSE_PUNKTE[$lead['interesse'] ?? ''] ?? 8;

        // Altbau vor 1980 + Interesse Wärmepumpe: hoher Sanierungsdruck -> Bonus
        if (($lead['baujahr_bucket'] ?? '') === 'vor-1980' && ($lead['interesse'] ?? '') === 'waermepumpe') {
            $punkte += 15;
        }

        // Vollständige Kontaktdaten (E-Mail zusätzlich zu Telefon) -> etwas höhere
        // Erreichbarkeit/Datenqualität
        if (!empty($lead['email'])) {
            $punkte += 5;
        }

        return max(0, min(100, $punkte));
    }

    public static function einstufung(int $score): string
    {
        return match (true) {
            $score >= 70 => 'hoch',
            $score >= 45 => 'mittel',
            default => 'niedrig',
        };
    }
}
