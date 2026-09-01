<?php

/**
 * Normalisiert Rohdaten aus einer externen Quelle (Spec Punkt 1a, Schritt 2)
 * auf das einheitliche interne Schema. Die genaue Spaltenbenennung der
 * externen Quelle ist noch nicht final festgelegt – deshalb wird hier über
 * eine Alias-Liste pro Feld gemappt. Neue Spaltennamen einfach in
 * FIELD_ALIASES ergänzen, sobald die reale Quelle bekannt ist.
 */
final class Normalizer
{
    private const FIELD_ALIASES = [
        'vorname' => ['vorname', 'first_name', 'firstname', 'first'],
        'nachname' => ['nachname', 'last_name', 'lastname', 'last', 'name'],
        'telefon' => ['telefon', 'phone', 'telefonnummer', 'mobile', 'tel'],
        'email' => ['email', 'e-mail', 'mail'],
        'plz' => ['plz', 'zip', 'postleitzahl', 'postal_code'],
        'alter_int' => ['alter', 'age', 'alter_int'],
        'wohnsituation' => ['wohnsituation', 'housing', 'living_situation', 'eigentuemer'],
        'interesse' => ['interesse', 'interest', 'produkt', 'product'],
        'energiekosten' => ['energiekosten', 'energy_costs', 'monatliche_kosten'],
        'baujahr' => ['baujahr', 'build_year', 'construction_year', 'jahr'],
        'datenschutz_ok' => ['datenschutz_ok', 'consent', 'einwilligung', 'opt_in'],
    ];

    /**
     * @param array<string,mixed> $rohdaten Eine Zeile aus der externen Quelle
     *   (z.B. eine CSV-Zeile als assoziatives Array mit Header als Keys)
     */
    public function normalisieren(array $rohdaten): array
    {
        // Keys case-insensitiv/trim vergleichbar machen
        $rohdatenNormalisiert = [];
        foreach ($rohdaten as $key => $value) {
            $rohdatenNormalisiert[strtolower(trim((string) $key))] = is_string($value) ? trim($value) : $value;
        }

        $lead = [];
        foreach (self::FIELD_ALIASES as $zielFeld => $aliase) {
            $lead[$zielFeld] = $this->findeWert($rohdatenNormalisiert, $aliase);
        }

        $lead['vorname'] = $lead['vorname'] ?? '';
        $lead['nachname'] = $lead['nachname'] ?? '';
        $lead['email'] = $lead['email'] !== null ? strtolower($lead['email']) : null;
        $lead['plz'] = $lead['plz'] !== null ? preg_replace('/\D/', '', (string) $lead['plz']) : null;
        $lead['alter_int'] = $this->zuInt($lead['alter_int']);
        $lead['wohnsituation'] = $this->normalisiereWohnsituation($lead['wohnsituation']);
        $lead['interesse'] = $this->normalisiereInteresse($lead['interesse']);
        $lead['energiekosten'] = $this->normalisiereEnergiekosten($lead['energiekosten']);
        $lead['baujahr_bucket'] = $this->baujahrZuBucket($lead['baujahr'] ?? null);
        unset($lead['baujahr']);
        $lead['datenschutz_ok'] = $this->zuBool($lead['datenschutz_ok']);

        return $lead;
    }

    private function findeWert(array $rohdaten, array $aliase): mixed
    {
        foreach ($aliase as $alias) {
            if (array_key_exists($alias, $rohdaten) && $rohdaten[$alias] !== '') {
                return $rohdaten[$alias];
            }
        }
        return null;
    }

    private function zuInt(mixed $wert): ?int
    {
        if ($wert === null || $wert === '') {
            return null;
        }
        if (!preg_match('/-?\d+/', (string) $wert, $treffer)) {
            return null;
        }
        return (int) $treffer[0];
    }

    private function zuBool(mixed $wert): bool
    {
        if (is_bool($wert)) {
            return $wert;
        }
        $wert = strtolower(trim((string) $wert));
        return in_array($wert, ['1', 'true', 'ja', 'yes', 'y', 'on'], true);
    }

    /**
     * Akzeptiert entweder ein konkretes Baujahr (z.B. "1972") oder bereits
     * einen Bucket-Text und bildet auf unsere drei Buckets ab.
     */
    private function baujahrZuBucket(mixed $wert): ?string
    {
        if ($wert === null || $wert === '') {
            return null;
        }
        $text = strtolower(trim((string) $wert));
        if (in_array($text, ['vor-1980', '1980-2000', 'nach-2000'], true)) {
            return $text;
        }
        if (preg_match('/\d{4}/', $text, $treffer)) {
            $jahr = (int) $treffer[0];
            return match (true) {
                $jahr < 1980 => 'vor-1980',
                $jahr <= 2000 => '1980-2000',
                default => 'nach-2000',
            };
        }
        return null;
    }

    /**
     * Bildet freie Schreibweisen auf dieselben ASCII-Tokens ab, die auch die
     * Sofort-Check-Tap-Buttons als data-value nutzen (photovoltaik/waermepumpe/
     * stromtarif/landwirtschaft/vermieter/sonstiges) — wichtig, damit Scorer und
     * Admin-Filter Import- und Sofort-Check-Leads gleich behandeln.
     */
    private function normalisiereInteresse(mixed $wert): ?string
    {
        if ($wert === null || $wert === '') {
            return null;
        }
        $text = strtolower(trim((string) $wert));
        $text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        return match (true) {
            str_contains($text, 'photovolt') || $text === 'pv' || str_contains($text, 'solar') => 'photovoltaik',
            str_contains($text, 'waermepump') || $text === 'wp' => 'waermepumpe',
            str_contains($text, 'stromtarif') || str_contains($text, 'strom') => 'stromtarif',
            str_contains($text, 'landwirtschaft') || str_contains($text, 'agri') || str_contains($text, 'hof') => 'landwirtschaft',
            str_contains($text, 'vermieter') || str_contains($text, 'mieterstrom') => 'vermieter',
            default => 'sonstiges',
        };
    }

    /**
     * Entfernt Währungszeichen/Leerzeichen, damit "150-200€" auf denselben
     * Bucket-Text abbildet wie der Tap-Button-Wert "150-200".
     */
    private function normalisiereEnergiekosten(mixed $wert): ?string
    {
        if ($wert === null || $wert === '') {
            return null;
        }
        $text = strtolower(trim((string) $wert));
        $text = str_replace(['€', 'eur', ' '], '', $text);
        $text = str_replace('über', 'ueber', $text);
        return $text;
    }

    private function normalisiereWohnsituation(mixed $wert): ?string
    {
        if ($wert === null) {
            return null;
        }
        $wert = strtolower(trim((string) $wert));
        return match (true) {
            in_array($wert, ['eigentuemer', 'eigentümer', 'owner', 'homeowner', '1', 'true', 'ja'], true) => 'eigentuemer',
            in_array($wert, ['mieter', 'tenant', 'renter', '0', 'false', 'nein'], true) => 'mieter',
            default => $wert,
        };
    }
}
