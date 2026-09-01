# Lead-Generator Energie

Eigenständiges Projekt, getrennt vom Comentra-Projekt. Volle Spezifikation: [SPEC.md](SPEC.md).

## Architektur

- **Priorität 1 – Import/Filter** (`import/`): liest Rohdaten einer externen Lead-Quelle ein
  (aktuell CSV, da das reale Format noch nicht feststeht), normalisiert sie und wendet die
  Qualifizierungslogik an. Nur qualifizierte Leads landen in der DB.
- **Priorität 2 – "Sofort-Check"** (`public_html/`): ersetzt das klassische mehrstufige
  Kontaktformular durch einen Vollbild-Tap-Ablauf ("wie eine App"): Fragen werden nur
  angetippt, jede Antwort wird sofort im Hintergrund gespeichert (kein Datenverlust bei
  Abbruch), disqualifizierte Besucher:innen bekommen sofort ehrliches Feedback, am Ende
  steht nur noch Vorname + PLZ + Telefonnummer, und der Abschluss läuft über einen
  WhatsApp-Deep-Link statt "wir rufen Sie an". Design: "Zählerstand" — Anthrazit/Amber,
  custom Icon-System, siehe Gesprächsverlauf für Design-Entscheidungen.
- **Admin-Dashboard** (`public_html/admin/`): passwortgeschützte Übersicht aller Leads —
  filtern nach Status/Kontaktstatus/Zeitraum, Kontaktstatus je Lead pflegen (offen →
  kontaktiert → Termin → abgeschlossen/kein Interesse), CSV-Export, Direkt-Link zu WhatsApp.
- **Geteilte Logik** (`lib/`): `Qualifier` (Qualifizierungsregeln, inkl. Notfall-Modus),
  `Normalizer` (Feld-Mapping externer Rohdaten), `Scorer` (Lead-Scoring), `ReferralCode`
  (Empfehlungscodes), `Database` (PDO-Verbindung), `Mailer`/`SmtpMailer` (Benachrichtigung),
  `Auth` (Admin-Login).
- **Wartung** (`cron/`): `cleanup.php` löscht abgebrochene Sofort-Check-Sessions und alte
  Rate-Limit-Zeilen nach konfigurierbarer Frist (DSGVO-Speicherbegrenzung).
- **Nischen-/Wachstums-Features** (Antwort auf "was macht uns unschlagbar"):
  - **Lead-Scoring** (`lib/Scorer.php`): 0-100-Score statt Ja/Nein, berücksichtigt
    Energiekosten, Interesse, Baujahr×Interesse-Kombination (z.B. Altbau+Wärmepumpe),
    Datenqualität — rein für Priorisierung, ändert nichts an der harten Qualifizierung.
  - **Baujahr-Segmentierung**: neue Sofort-Check-Frage, zielt auf die Altbau-Sanierer-Nische
    (Baujahr vor 1980), die generische Portale nicht gezielt ansprechen.
  - **Partner-Empfehlungssystem** (`public_html/partner/`, `admin/partner.php`): EIN
    generisches System für alle Kanal-Partner-Nischen gleichzeitig (Schornsteinfeger,
    Dachdecker, Bank-/Finanzberater) — Partner meldet sich an, bekommt eigenen `?ref=CODE`
    Link, jeder darüber ankommende Lead wird automatisch zugeordnet.
  - **Kunden-Weiterempfehlung**: dieselbe Code-Infrastruktur — jeder qualifizierte Lead
    bekommt nach Abschluss einen eigenen Empfehlungslink zum Teilen (viraler Loop statt nur
    bezahlter/organischer Reichweite).
  - **Notfall-Schnelleinstieg**: zweiter, kleinerer CTA ("Heizung akut ausgefallen?") —
    überspringt die Alters-Qualifizierung (`Qualifier::pruefen($lead, notfall: true)`),
    springt direkt zur Kontaktaufnahme. Zielt auf die höchste Kaufbereitschaft überhaupt.
  - **Saison-Trigger** (`enhance.js`): andere Hero-Headline Okt-Feb (Nebenkostenabrechnungs-
    Saison, höchste Preissensibilität im Jahr).
  - **Transparenz-Sektion**: "Kein Kaltbesuch/kein Vertragsdruck" — positioniert bewusst
    gegen das Drückerkolonne-Image der Branche.

```
lead-generator-energie/
├── SPEC.md                  Vollständige Spezifikation
├── db/schema.sql             MySQL-Schema (leads, import_runs, rate_limits)
├── config/config.example.php Vorlage — nach config.php kopieren & befüllen
├── lib/                      Database, Qualifier, Normalizer, Mailer, SmtpMailer, Auth
├── import/
│   ├── import_leads.php      CLI-Import-/Filter-Skript (Priorität 1)
│   └── sample_data/          Beispiel-CSV zum Testen
├── cron/
│   └── cleanup.php           DSGVO-Aufräum-Skript, als Hostinger-Cronjob einrichten
└── public_html/               Deployment-Ziel auf Hostinger (Web-Root)
    ├── .htaccess              Security-Header, sperrt config.php/_*.php
    ├── index.html             Landingpage mit Sofort-Check-Overlay
    ├── impressum.html / datenschutz.html
    ├── assets/                CSS/JS (sofort-check.js, enhance.js, tracking.js)
    ├── admin/                 Login, Leads-Übersicht (+Score/Baujahr/Partner), CSV-Export,
    │                          Status-Update, Partner-Übersicht (partner.php)
    ├── partner/anmelden.html  Partner-Anmeldeseite (Schornsteinfeger/Dachdecker/Bank/...)
    └── api/
        ├── lead-step.php      Progressiver Upsert pro Tap + Finalisierung/Qualifizierung
        ├── partner-anmeldung.php  Partner-Registrierung, generiert Empfehlungscode
        └── stats.php          Liefert echte Zählung für den Social-Proof-Hinweis
```

## Wie der Sofort-Check technisch funktioniert

1. Beim ersten Tap generiert der Browser eine zufällige `session_id` (in `localStorage`)
   und schickt sie ab jetzt bei jedem Schritt mit.
2. Jeder Tap löst sofort einen Hintergrund-Request an `api/lead-step.php` aus, der die
   Zeile in `leads` per `session_id` anlegt/aktualisiert (Status zunächst `in_progress`).
   IP-Rate-Limiting greift nur beim allerersten Request einer Session.
3. Direkt nach "Wohnsituation = Mieter" oder "Alter außerhalb 40–60" wird sofort ehrlich
   abgebrochen, ohne weitere Schritte.
4. Danach zeigt das Frontend eine grobe Ersparnis-Schätzung (illustrativ, klar als
   unverbindlich gekennzeichnet).
5. Der letzte Schritt fragt Vorname, PLZ, Telefonnummer und Datenschutz-Checkbox ab.
   Optional wird zuerst reCAPTCHA v3 geprüft (falls `recaptcha.secret_key` gesetzt ist).
   Erst wenn Telefonnummer **und** Einwilligung vorliegen, wendet `lead-step.php` die
   Qualifizierungslogik an, setzt `status` und verschickt bei Qualifikation die E-Mail
   (SMTP, falls konfiguriert, sonst `mail()`-Fallback).
6. Bei Erfolg öffnet das Frontend einen `wa.me`-Link und feuert — falls konfiguriert —
   eine Meta-Pixel-/Google-Ads-Conversion (`window.fireLeadConversion()`).

## Setup

1. **Datenbank anlegen** (Hostinger hPanel → Datenbanken) und `db/schema.sql` importieren.
2. **Config anlegen:**
   ```
   cp config/config.example.php config/config.php
   ```
   und mit echten DB-/Mail-/reCAPTCHA-/WhatsApp-/Admin-Zugangsdaten befüllen. `config.php`
   ist in `.gitignore` und darf nie committet werden.
3. **Admin-Passwort setzen:**
   ```
   php -r "echo password_hash('DEIN_PASSWORT', PASSWORD_DEFAULT);"
   ```
   Ergebnis in `config.php` unter `admin.password_hash` eintragen. Dashboard dann unter
   `/admin/login.php` erreichbar.
4. **WhatsApp-Nummer eintragen:** in `config/config.php` (`whatsapp.number`) UND zusätzlich
   in `public_html/index.html` (`window.LEAD_WHATSAPP_NUMMER`), da das Frontend statisches
   HTML ist und die PHP-Config nicht direkt einlesen kann.
5. **Tracking aktivieren (optional):** `META_PIXEL_ID` / `GOOGLE_ADS_ID` /
   `GOOGLE_ADS_CONVERSION_LABEL` in `public_html/index.html` eintragen — lädt erst nach
   Cookie-Einwilligung (Banner erscheint automatisch, sobald eine ID gesetzt ist).
6. **SMTP statt `mail()` nutzen (empfohlen):** `mail.smtp_host/smtp_port/smtp_user/smtp_pass`
   in `config.php` setzen (Hostinger Business Mail: meist Port 587 mit STARTTLS oder 465
   implizites TLS). Leer lassen = `mail()`-Fallback.
7. **Deployment-Layout auf Hostinger:** Nur der Inhalt von `public_html/` gehört in den
   Web-Root. `lib/`, `config/`, `import/`, `cron/`, `db/` liegen eine Ebene darüber
   (Account-Root, nicht öffentlich erreichbar) — das schützt DB-Zugangsdaten. Die Dateien
   in `public_html/api/` und `public_html/admin/` referenzieren `lib/`/`config/` relativ
   über `../../`, das Pfad-Layout muss also erhalten bleiben.
8. **Import testen:**
   ```
   php import/import_leads.php import/sample_data/example_input.csv
   ```
9. **Cronjobs einrichten** (Hostinger hPanel → Cronjobs):
   ```
   # Wiederkehrender Import, sobald die externe Quelle regelmäßig liefert
   php /home/USER/lead-generator-energie/import/import_leads.php /pfad/zu/neuen/daten.csv
   # Täglich: DSGVO-Aufräumen abgebrochener Sessions
   php /home/USER/lead-generator-energie/cron/cleanup.php
   ```

## Bekannte Vereinfachungen

- Die Alters-Tap-Buttons speichern nur einen repräsentativen `alter_int`-Wert (30/50/65),
  kein exaktes Alter — ausreichend für die 40–60-Qualifizierung.
- Die Ersparnis-Schätzung ist eine grobe, clientseitige Faustformel zu Marketingzwecken,
  klar als "unverbindliche Schätzung" gekennzeichnet.
- Der Social-Proof-Zähler zeigt nur eine echte Zahl aus der DB, ausgeblendet unter 5
  (kein Fake-Social-Proof).
- `SmtpMailer` unterstützt genau den hier benötigten Fall (eine feste Empfängeradresse,
  STARTTLS oder implizites TLS, AUTH LOGIN) — kein vollwertiger Mail-Client.

## Offene Punkte

- Anbindungsdetails der externen Lead-Quelle (Format, API/CSV/DB-Sync) stehen noch nicht
  fest — `Normalizer` ist per Alias-Liste erweiterbar, sobald die Quelle klar ist.
- Impressum/Datenschutz enthalten Platzhalter (`[...]`) — vor Live-Schaltung mit echten
  Firmendaten befüllen bzw. juristisch prüfen lassen.
- reCAPTCHA v3, Meta-Pixel/Google-Ads-IDs, WhatsApp-Nummer und Admin-Passwort sind bewusst
  leer/Platzhalter in `config.example.php` bzw. `index.html` — vor Go-Live befüllen.
- Kein Hostinger-Zugang aktuell vorhanden — Deployment steht noch aus, sobald
  Domain/FTP-SSH-/DB-Zugangsdaten vorliegen.
- Provisionshöhe im Partner-Anmeldetext ist Platzhalter (`[XX] €`) — echten Betrag eintragen,
  sobald das Geschäftsmodell dafür steht.
- Echtzeit-Lead-Verteilung an mehrere Abnehmer (Webhook/SMS statt nur eine feste E-Mail-
  Adresse) und ein automatisierter WhatsApp-Speed-to-Lead-Bot sind bewusst NICHT gebaut —
  beides setzt echte Accounts (Installateur-Partner, WhatsApp Business API/Twilio) voraus,
  die noch nicht existieren.
