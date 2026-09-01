# Lead-Generator – Energie-Branche | Spezifikation für Claude Code

## Ziel
Landingpage + Formular zur Generierung von 100% qualifizierten Leads in der Energie-Branche (z.B. Solar, Wärmepumpe, Photovoltaik, Stromtarife), Zielgruppe 40-60 Jahre. Hosting/Deployment auf Hostinger.

---

## 1. Tech-Stack-Vorschlag (Hostinger-kompatibel)
- **Frontend:** Statisches HTML/CSS/JS oder Next.js (Static Export) – läuft stabil auf Hostinger Shared/Business Hosting
- **Formular-Backend:** PHP-Skript (nativ auf Hostinger verfügbar) ODER Hostinger-eigene Datenbank (MySQL) via einfachem PHP-Endpoint
- **Lead-Speicherung:** MySQL-Tabelle `leads` (Hostinger hPanel → Datenbanken)
- **E-Mail-Benachrichtigung:** PHP `mail()` oder SMTP (Hostinger Business Mail) bei jedem neuen Lead
- **Tracking:** Meta Pixel / Google Ads Conversion Tag (Platzhalter im Head einbauen)
- **SSL:** automatisch über Hostinger aktivieren

---

## 1a. Primärer Weg: Filterung einer bestehenden externen Datenbank
Statt (oder zusätzlich zu) einem eigenen Formular werden Leads aus einer bereits vorhandenen externen Datenquelle importiert und serverseitig gefiltert. Die Formular-Lösung (Punkt 3) dient nur als Notfall/Fallback, falls keine externe Quelle verfügbar ist.

**Ablauf:**
1. **Import:** Rohdaten aus der externen Quelle werden eingelesen (Format/Anbindung wird noch festgelegt, sobald die Quelle bekannt ist – z.B. CSV-Upload, API-Import oder DB-Sync)
2. **Normalisierung:** Felder auf einheitliches Schema mappen (Vorname, Nachname, Telefon, E-Mail, PLZ, Alter, Wohnsituation, Interesse)
3. **Filterung:** Anwendung derselben Qualifizierungslogik wie in Punkt 4 (Alter 40-60, Eigentümer, valide Telefonnummer/E-Mail, keine Duplikate) auf die importierten Datensätze
4. **Ergebnis:** Nur qualifizierte Datensätze landen in der `leads`-Tabelle (Punkt 5) mit `status = "qualifiziert"`; unqualifizierte werden verworfen oder separat archiviert
5. **Wiederholbarkeit:** Import/Filter-Skript so bauen, dass es bei neuen Datenlieferungen erneut ausgeführt werden kann (z.B. als CLI-Skript oder Cronjob auf Hostinger)

*Anbindungsdetails (Quelle, Format, Zugangsdaten) werden ergänzt, sobald die Datenquelle final feststeht.*

---

## 2. Seitenstruktur (One-Pager) – nur relevant für Formular-Fallback
1. **Hero-Bereich:** Headline (Ersparnis-Nutzenversprechen), Subheadline, CTA-Button ("Jetzt kostenlos prüfen")
2. **Vertrauens-Leiste:** Logos/Siegel (TÜV, Made in Germany, Kundenanzahl o.ä.)
3. **Nutzen-Sektion:** 3 Vorteile mit Icons (z.B. Kosten senken, unabhängig werden, staatliche Förderung)
4. **Formular-Sektion** (siehe Punkt 3)
5. **Social Proof:** Testimonials/Bewertungen
6. **FAQ:** 3-5 typische Einwände
7. **Footer:** Impressum, Datenschutz, Kontakt

---

## 3. Formularfelder (mehrstufig empfohlen für höhere Conversion)

**Schritt 1 – Grobfilter:**
- Wohnsituation: Eigentümer / Mieter (Pflichtfeld – nur Eigentümer sind qualifiziert)
- Interesse an: Photovoltaik / Wärmepumpe / Stromtarif / Sonstiges

**Schritt 2 – Qualifizierung:**
- Alter (Dropdown oder Slider) – Pflichtfeld, nur 40-60 akzeptieren
- PLZ / Wohnort
- Aktuelle monatliche Energiekosten (Range-Auswahl)

**Schritt 3 – Kontaktdaten:**
- Vorname, Nachname
- Telefonnummer (Pflicht, für Vertrieb)
- E-Mail
- Checkbox: Einwilligung Datenschutz + Telefonkontakt (TTDSG-konform, Pflicht!)

---

## 4. Qualifizierungslogik ("100% saubere Leads")
Ein Lead gilt nur als **qualifiziert**, wenn ALLE Kriterien serverseitig geprüft werden, bevor er gespeichert/versendet wird:

```
IF wohnsituation == "Eigentümer"
AND alter >= 40 AND alter <= 60
AND telefonnummer ist valide (Regex-Check, deutsches Format)
AND datenschutz_checkbox == true
THEN status = "qualifiziert" → in DB speichern + E-Mail an Vertrieb
ELSE status = "unqualifiziert" → NICHT weiterleiten (nur intern loggen, optional)
```

Zusätzlich empfohlen:
- **Honeypot-Feld** (unsichtbar) gegen Bots
- **reCAPTCHA v3** gegen Spam-Einträge
- **Duplikat-Check** über Telefonnummer/E-Mail (kein Lead doppelt verkaufen)
- **IP-Rate-Limiting** (max. X Einträge pro IP/Tag)

---

## 5. Datenbankstruktur (MySQL – Vorschlag)
```sql
CREATE TABLE leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vorname VARCHAR(100),
  nachname VARCHAR(100),
  telefon VARCHAR(30),
  email VARCHAR(150),
  plz VARCHAR(10),
  alter_int INT,
  wohnsituation VARCHAR(20),
  interesse VARCHAR(50),
  energiekosten VARCHAR(30),
  status VARCHAR(20) DEFAULT 'neu',
  qualifiziert BOOLEAN DEFAULT 0,
  erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 6. Auftrag an Claude Code
1. Projektstruktur auf Hostinger anlegen (public_html)
2. **Priorität 1:** Import-/Filter-Skript nach Punkt 1a bauen – liest externe Rohdaten ein, normalisiert Felder, wendet Qualifizierungslogik (Punkt 4) an, schreibt nur qualifizierte Leads in MySQL (Punkt 5)
3. Skript wiederholbar gestalten (CLI-Aufruf oder Cronjob)
4. Landingpage nach Struktur in Punkt 2 bauen (mobile-first) – **nur als Fallback, niedrigere Priorität**
5. Mehrstufiges Formular nach Punkt 3 mit Client- und Server-Validierung – **nur als Fallback**
6. E-Mail-Benachrichtigung bei neuem qualifizierten Lead einrichten
7. Datenschutzerklärung + Impressum-Seite ergänzen (Pflicht für Formulare in DE)
8. Meta Pixel / Google Ads Tag als Platzhalter einbauen (nur relevant für Formular-Fallback)
