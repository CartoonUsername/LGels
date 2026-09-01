<?php

/**
 * Minimaler, abhängigkeitsfreier SMTP-Client (STARTTLS + AUTH LOGIN) für
 * Hostinger Business Mail. Bewusst ohne PHPMailer/Composer, damit das Projekt
 * ohne Build-Schritt per einfachem Upload auf Hostinger läuft.
 * Unterstützt nur den hier benötigten Fall: eine Benachrichtigungs-Mail an
 * eine feste Vertriebsadresse.
 */
final class SmtpMailer
{
    public function __construct(
        private string $host,
        private int $port,
        private string $user,
        private string $pass
    ) {
    }

    public function send(string $to, string $from, string $fromName, string $subject, string $body): bool
    {
        $ziel = $this->port === 465 ? "ssl://{$this->host}:{$this->port}" : "{$this->host}:{$this->port}";
        $socket = @stream_socket_client($ziel, $errno, $errstr, 10);
        if ($socket === false) {
            error_log("SMTP-Verbindung fehlgeschlagen: {$errstr}");
            return false;
        }

        try {
            $this->erwarte($socket, '220');
            $this->befehl($socket, 'EHLO localhost', '250');

            if ($this->port !== 465) {
                $this->befehl($socket, 'STARTTLS', '220');
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS fehlgeschlagen');
                }
                $this->befehl($socket, 'EHLO localhost', '250');
            }

            $this->befehl($socket, 'AUTH LOGIN', '334');
            $this->befehl($socket, base64_encode($this->user), '334');
            $this->befehl($socket, base64_encode($this->pass), '235');

            $this->befehl($socket, "MAIL FROM:<{$from}>", '250');
            $this->befehl($socket, "RCPT TO:<{$to}>", '250');
            $this->befehl($socket, 'DATA', '354');

            $nachricht = "From: {$fromName} <{$from}>\r\n"
                . "To: <{$to}>\r\n"
                . "Subject: {$subject}\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
                . str_replace("\n.", "\n..", $body) . "\r\n.\r\n";
            fwrite($socket, $nachricht);
            $this->erwarte($socket, '250');

            $this->befehl($socket, 'QUIT', '221');
            return true;
        } catch (RuntimeException $e) {
            error_log('SMTP-Fehler: ' . $e->getMessage());
            return false;
        } finally {
            fclose($socket);
        }
    }

    private function befehl($socket, string $command, string $erwarteterCode): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->erwarte($socket, $erwarteterCode);
    }

    private function erwarte($socket, string $erwarteterCode): string
    {
        $antwort = '';
        while (($zeile = fgets($socket, 515)) !== false) {
            $antwort .= $zeile;
            if (strlen($zeile) < 4 || $zeile[3] === ' ') {
                break; // letzte Zeile einer mehrzeiligen SMTP-Antwort
            }
        }
        if (!str_starts_with($antwort, $erwarteterCode)) {
            throw new RuntimeException("Unerwartete SMTP-Antwort (erwartet {$erwarteterCode}): {$antwort}");
        }
        return $antwort;
    }
}
