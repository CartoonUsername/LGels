<?php

/**
 * Zentrale Qualifizierungslogik (Spec Punkt 4).
 * Wird sowohl vom Import-Skript (Punkt 1a) als auch vom Formular-Endpoint
 * (Punkt 3, Fallback) verwendet, damit beide Wege dieselben Kriterien prüfen.
 */
final class Qualifier
{
    public function __construct(private array $rules)
    {
    }

    /**
     * Erwartet ein normalisiertes Lead-Array mit den Schlüsseln:
     * vorname, nachname, telefon, email, plz, alter_int, wohnsituation,
     * interesse, energiekosten, datenschutz_ok
     *
     * $notfall = true (akuter Heizungsausfall, Spec-Erweiterung "Notfall-Einstieg"):
     * überspringt die Alters-Prüfung, da Dringlichkeit hier wichtiger ist als die
     * sonstige Zielgruppen-Eingrenzung — alle anderen Kriterien bleiben scharf.
     *
     * @return array{qualifiziert: bool, grund: ?string}
     */
    public function pruefen(array $lead, bool $notfall = false): array
    {
        if (empty($lead['wohnsituation']) || !in_array($lead['wohnsituation'], $this->rules['erlaubte_wohnsituation'], true)) {
            return $this->abgelehnt('wohnsituation nicht "eigentuemer"');
        }

        if (!$notfall) {
            $alter = $lead['alter_int'] ?? null;
            if (!is_int($alter) || $alter < $this->rules['alter_min'] || $alter > $this->rules['alter_max']) {
                return $this->abgelehnt("alter außerhalb {$this->rules['alter_min']}-{$this->rules['alter_max']}");
            }
        }

        if (empty($lead['telefon']) || !self::istGueltigeDeutscheTelefonnummer($lead['telefon'])) {
            return $this->abgelehnt('telefonnummer ungültig');
        }

        if (!empty($lead['email']) && !filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->abgelehnt('email ungültig');
        }

        if (empty($lead['datenschutz_ok'])) {
            return $this->abgelehnt('datenschutz-einwilligung fehlt');
        }

        return ['qualifiziert' => true, 'grund' => null];
    }

    public static function istGueltigeDeutscheTelefonnummer(string $telefon): bool
    {
        $normalisiert = preg_replace('/[\s\-\/\(\)]/', '', $telefon);
        return (bool) preg_match('/^(\+49|0049|0)[1-9][0-9]{6,14}$/', $normalisiert);
    }

    private function abgelehnt(string $grund): array
    {
        return ['qualifiziert' => false, 'grund' => $grund];
    }
}
