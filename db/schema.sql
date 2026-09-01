-- Lead-Generator Energie – Datenbankschema (MySQL / Hostinger)

CREATE TABLE IF NOT EXISTS leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(64) UNIQUE,
  vorname VARCHAR(100),
  nachname VARCHAR(100),
  telefon VARCHAR(30),
  email VARCHAR(150),
  plz VARCHAR(10),
  alter_int INT,
  wohnsituation VARCHAR(20),
  interesse VARCHAR(50),
  energiekosten VARCHAR(30),
  baujahr_bucket VARCHAR(20),
  datenschutz_ok TINYINT(1) DEFAULT 0,
  quelle VARCHAR(50) DEFAULT 'sofort-check',
  status VARCHAR(20) DEFAULT 'in_progress',
  qualifiziert BOOLEAN DEFAULT 0,
  ablehnungsgrund VARCHAR(255) DEFAULT NULL,
  kontakt_status VARCHAR(20) DEFAULT 'offen',
  lead_score INT DEFAULT NULL,
  -- Attribution: welcher Partner-Code oder welcher andere Lead (Weiterempfehlung)
  -- diese Person gebracht hat. Bewusst ohne Fremdschlüssel auf `partner`, da ein
  -- Code entweder ein echter Partner ODER der persönliche Empfehlungscode eines
  -- früheren Leads sein kann.
  partner_code VARCHAR(30) DEFAULT NULL,
  -- Persönlicher Empfehlungscode, den DIESER Lead nach erfolgreichem Abschluss
  -- selbst zum Weiterempfehlen bekommt.
  eigener_code VARCHAR(20) UNIQUE DEFAULT NULL,
  ist_notfall TINYINT(1) DEFAULT 0,
  erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  aktualisiert_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_telefon (telefon),
  UNIQUE KEY uniq_email (email),
  KEY idx_status_qualifiziert (status, qualifiziert),
  KEY idx_erstellt_am (erstellt_am),
  KEY idx_partner_code (partner_code)
);

-- Registrierte Vertriebspartner (Schornsteinfeger, Dachdecker, Bank-/Finanzberater, ...),
-- die ihren eigenen Empfehlungslink (?ref=CODE) an Kunden weitergeben.
CREATE TABLE IF NOT EXISTS partner (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) UNIQUE NOT NULL,
  name VARCHAR(150),
  betrieb VARCHAR(150),
  partnertyp VARCHAR(50),
  plz_gebiet VARCHAR(10),
  telefon VARCHAR(30),
  email VARCHAR(150),
  status VARCHAR(20) DEFAULT 'neu',
  erstellt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Protokoll jedes Import-Laufs (Punkt 1a), damit Läufe nachvollziehbar sind
CREATE TABLE IF NOT EXISTS import_runs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  dateiname VARCHAR(255),
  zeilen_gesamt INT DEFAULT 0,
  qualifiziert_anzahl INT DEFAULT 0,
  abgelehnt_anzahl INT DEFAULT 0,
  duplikate_anzahl INT DEFAULT 0,
  gestartet_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  beendet_am TIMESTAMP NULL
);

-- IP-Rate-Limiting: wird nur einmal pro neuer Sofort-Check-Session gezählt,
-- nicht bei jedem einzelnen Antworten-Schritt.
CREATE TABLE IF NOT EXISTS rate_limits (
  ip VARCHAR(45) NOT NULL,
  tag DATE NOT NULL,
  anzahl INT DEFAULT 0,
  PRIMARY KEY (ip, tag)
);
