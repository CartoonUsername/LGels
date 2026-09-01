<?php

/**
 * E-Mail-Benachrichtigung bei neuem qualifizierten Lead (Spec Punkt 6.6).
 * Nutzt SMTP (SmtpMailer), sobald smtp_host in der Config gesetzt ist —
 * zuverlässiger als PHP mail() (bessere Zustellrate, landet seltener im Spam).
 * Ohne smtp_host bleibt mail() als Fallback erhalten.
 */
final class Mailer
{
    public function __construct(private array $config)
    {
    }

    public function sendeNeuerLeadBenachrichtigung(array $lead): bool
    {
        $name = trim(($lead['vorname'] ?? '') . ' ' . ($lead['nachname'] ?? ''));
        $to = $this->config['to'];
        $subject = 'Neuer qualifizierter Lead: ' . $name;
        $fromName = $this->config['from_name'] ?? 'Lead-Generator Energie';
        $from = $this->config['from'];

        $body = "Neuer qualifizierter Lead eingegangen:\n\n"
            . "Name: {$name}\n"
            . "Telefon: {$lead['telefon']}\n"
            . "E-Mail: {$lead['email']}\n"
            . "PLZ: {$lead['plz']}\n"
            . "Alter: {$lead['alter_int']}\n"
            . "Wohnsituation: {$lead['wohnsituation']}\n"
            . "Interesse: {$lead['interesse']}\n"
            . "Energiekosten: {$lead['energiekosten']}\n"
            . "Quelle: {$lead['quelle']}\n";

        if (!empty($this->config['smtp_host'])) {
            $smtp = new SmtpMailer(
                $this->config['smtp_host'],
                (int) ($this->config['smtp_port'] ?? 587),
                $this->config['smtp_user'] ?? '',
                $this->config['smtp_pass'] ?? ''
            );
            return $smtp->send($to, $from, $fromName, $subject, $body);
        }

        $headers = "From: {$fromName} <{$from}>\r\n"
            . "Reply-To: {$from}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n";

        return mail($to, $subject, $body, $headers);
    }
}
