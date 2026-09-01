<?php

/**
 * Einfache Session-basierte Authentifizierung für das Admin-Dashboard.
 * Kein eigenes User-System nötig — ein Admin-Account reicht für dieses Projekt.
 */
final class Auth
{
    public function __construct(private array $config)
    {
    }

    public function versuchLogin(string $username, string $password): bool
    {
        if ($this->config['password_hash'] === '') {
            return false; // kein Passwort konfiguriert -> Login gesperrt
        }
        $gueltig = hash_equals($this->config['username'], $username)
            && password_verify($password, $this->config['password_hash']);

        if ($gueltig) {
            session_regenerate_id(true);
            $_SESSION['admin_eingeloggt'] = true;
        }
        return $gueltig;
    }

    public function istEingeloggt(): bool
    {
        return !empty($_SESSION['admin_eingeloggt']);
    }

    public function erzwingeLogin(string $loginUrl): void
    {
        if (!$this->istEingeloggt()) {
            header('Location: ' . $loginUrl);
            exit;
        }
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }
}
