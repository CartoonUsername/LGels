(function () {
  const overlay = document.getElementById('sofort-check');
  if (!overlay) return;

  const DATA_STEPS = ['wohnsituation', 'baujahr', 'alter', 'interesse', 'energiekosten'];
  const progressBar = document.getElementById('sc-progress-bar');
  const feedback = document.getElementById('sc-feedback');

  const antworten = { interesse: null, energiekosten: null };
  let whatsappFenster = null;
  let notfallModus = false;

  function getSessionId() {
    let id = localStorage.getItem('lead_session_id');
    if (!id) {
      id = (crypto.randomUUID ? crypto.randomUUID() : Date.now() + '' + Math.random()).replace(/-/g, '');
      localStorage.setItem('lead_session_id', id);
    }
    return id;
  }
  const sessionId = getSessionId();

  // Empfehlungs-Code aus der URL (?ref=CODE) merken — von einem Partner oder
  // von einem früheren Lead, der weiterempfohlen hat. Bleibt auch bei
  // erneutem Besuch ohne ?ref= erhalten.
  (function merkeEmpfehlungscode() {
    const ref = new URLSearchParams(window.location.search).get('ref');
    if (ref && /^[A-Za-z0-9-]{1,30}$/.test(ref)) {
      localStorage.setItem('partner_code', ref.toUpperCase());
    }
  })();
  function getPartnerCode() {
    return localStorage.getItem('partner_code') || '';
  }

  function sendeSchritt(felder) {
    const body = new FormData();
    body.append('session_id', sessionId);
    const partnerCode = getPartnerCode();
    if (partnerCode) body.append('partner_code', partnerCode);
    Object.entries(felder).forEach(([k, v]) => body.append(k, v));
    return fetch('api/lead-step.php', { method: 'POST', body })
      .then((r) => r.json())
      .catch(() => null);
  }

  function showStep(name) {
    overlay.querySelectorAll('.sc-step').forEach((el) => {
      el.hidden = el.dataset.step !== name;
    });
    const idx = DATA_STEPS.indexOf(name);
    if (idx >= 0) {
      progressBar.style.width = ((idx + 1) / DATA_STEPS.length) * 100 + '%';
      progressBar.parentElement.hidden = false;
    } else {
      progressBar.parentElement.hidden = true;
    }
  }

  function openOverlay(notfall) {
    notfallModus = !!notfall;
    overlay.hidden = false;
    document.body.style.overflow = 'hidden';
    showStep('wohnsituation');
  }

  function closeOverlay() {
    overlay.hidden = true;
    document.body.style.overflow = '';
  }

  document.getElementById('start-sofort-check').addEventListener('click', () => openOverlay(false));
  document.getElementById('start-notfall').addEventListener('click', () => openOverlay(true));
  document.getElementById('sc-close').addEventListener('click', closeOverlay);

  // --- Schritt 1: Wohnsituation ---
  overlay.querySelector('[data-step="wohnsituation"]').addEventListener('click', (e) => {
    const btn = e.target.closest('.sc-tap');
    if (!btn) return;
    const value = btn.dataset.value;
    sendeSchritt({ wohnsituation: value });
    if (value === 'mieter') {
      zeigeAusschluss('Aktuell richten wir uns an Eigentümer:innen von Wohneigentum.');
      return;
    }
    if (notfallModus) {
      // Notfall: Qualifizierung überspringen, direkt zur Kontaktaufnahme
      showStep('kontakt');
      return;
    }
    showStep('baujahr');
  });

  // --- Schritt 1b: Baujahr ---
  overlay.querySelector('[data-step="baujahr"]').addEventListener('click', (e) => {
    const btn = e.target.closest('.sc-tap');
    if (!btn) return;
    sendeSchritt({ baujahr_bucket: btn.dataset.value });
    showStep('alter');
  });

  // --- Schritt 2: Alter ---
  overlay.querySelector('[data-step="alter"]').addEventListener('click', (e) => {
    const btn = e.target.closest('.sc-tap');
    if (!btn) return;
    const value = btn.dataset.value;
    sendeSchritt({ alter_bucket: value });
    if (value !== '40-60') {
      zeigeAusschluss('Unser Angebot richtet sich aktuell an die Altersgruppe 40–60.');
      return;
    }
    showStep('interesse');
  });

  // --- Schritt 3: Interesse ---
  overlay.querySelector('[data-step="interesse"]').addEventListener('click', (e) => {
    const btn = e.target.closest('.sc-tap');
    if (!btn) return;
    antworten.interesse = btn.dataset.value;
    sendeSchritt({ interesse: antworten.interesse });
    showStep('energiekosten');
  });

  // --- Schritt 4: Energiekosten -> sofort Ergebnis anzeigen ---
  const ENERGIEKOSTEN_MITTE = {
    'unter-50': 40, '50-100': 75, '100-150': 125, '150-200': 175, '200-250': 225, 'ueber-250': 275,
  };
  const SPARQUOTE = {
    photovoltaik: 0.35, waermepumpe: 0.25, stromtarif: 0.15,
    landwirtschaft: 0.4, vermieter: 0.2, sonstiges: 0.1,
  };

  overlay.querySelector('[data-step="energiekosten"]').addEventListener('click', (e) => {
    const btn = e.target.closest('.sc-tap');
    if (!btn) return;
    antworten.energiekosten = btn.dataset.value;
    sendeSchritt({ energiekosten: antworten.energiekosten });

    const basis = ENERGIEKOSTEN_MITTE[antworten.energiekosten] ?? 100;
    const quote = SPARQUOTE[antworten.interesse] ?? 0.1;
    const ersparnis = Math.round(basis * quote);
    document.getElementById('sc-ersparnis').textContent = 'ca. ' + ersparnis + ' € / Monat';
    showStep('ergebnis');
  });

  document.getElementById('sc-weiter-zu-kontakt').addEventListener('click', () => {
    showStep('kontakt');
  });

  function zeigeAusschluss(text) {
    document.getElementById('sc-exit-text').textContent = text;
    showStep('nicht-qualifiziert');
  }

  // --- Schritt 5: Kontakt (finaler Schritt) ---
  document.getElementById('sc-kontakt-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const submitBtn = form.querySelector('button[type="submit"]');

    // Popup-Fenster SOFORT im Klick-Kontext öffnen (sonst blockieren Browser
    // den späteren window.open nach dem asynchronen fetch).
    whatsappFenster = window.open('about:blank', '_blank');

    submitBtn.disabled = true;
    feedback.textContent = 'Wird gesendet …';
    feedback.className = '';

    const daten = {
      website: form.website.value, // Honeypot
      vorname: form.vorname.value.trim(),
      plz: form.plz.value.trim(),
      telefon: form.telefon.value.trim(),
      datenschutz_ok: form.datenschutz_ok.checked ? '1' : '',
    };
    if (notfallModus) daten.ist_notfall = '1';

    if (window.grecaptcha && window.RECAPTCHA_SITE_KEY) {
      daten.recaptcha_token = await window.grecaptcha.execute(window.RECAPTCHA_SITE_KEY, { action: 'lead_submit' });
    }

    const antwort = await sendeSchritt(daten);

    if (antwort && antwort.ok && (antwort.qualifiziert !== false)) {
      const nachricht = encodeURIComponent(
        `Hallo! Ich habe den Sofort-Check gemacht: Interesse an ${antworten.interesse || 'Energie-Beratung'}, ` +
        `Energiekosten ${antworten.energiekosten || 'k.A.'} €/Monat, PLZ ${daten.plz}. Bitte meldet euch bei mir.`
      );
      const waLink = `https://wa.me/${window.LEAD_WHATSAPP_NUMMER}?text=${nachricht}`;
      document.getElementById('sc-whatsapp-link').href = waLink;
      if (whatsappFenster) {
        whatsappFenster.location.href = waLink;
      }
      if (window.fireLeadConversion) window.fireLeadConversion();

      const referralBox = document.getElementById('referral-box');
      if (antwort.eigener_code) {
        const shareLink = `${window.location.origin}${window.location.pathname}?ref=${antwort.eigener_code}`;
        document.getElementById('referral-share-link').value = shareLink;
        referralBox.hidden = false;
      } else {
        referralBox.hidden = true;
      }

      showStep('erfolg');
    } else {
      if (whatsappFenster) whatsappFenster.close();
      submitBtn.disabled = false;
      feedback.textContent = 'Bitte überprüfe deine Telefonnummer und versuche es erneut.';
      feedback.className = 'error';
    }
  });

  // --- Exit-Intent (Desktop): Maus verlässt oben das Fenster ---
  const exitIntent = document.getElementById('sc-exit-intent');
  let exitIntentGezeigt = false;
  document.addEventListener('mouseout', (e) => {
    if (exitIntentGezeigt || overlay.hidden || e.clientY > 0) return;
    const aktuellerSchritt = [...overlay.querySelectorAll('.sc-step')].find((el) => !el.hidden)?.dataset.step;
    if (aktuellerSchritt === 'erfolg' || aktuellerSchritt === 'nicht-qualifiziert') return;
    exitIntentGezeigt = true;
    const link = `https://wa.me/${window.LEAD_WHATSAPP_NUMMER}?text=${encodeURIComponent('Hallo! Ich habe Interesse an einer kostenlosen Energiekosten-Prüfung.')}`;
    document.getElementById('sc-exit-intent-whatsapp').href = link;
    exitIntent.hidden = false;
  });
  document.getElementById('sc-exit-intent-close').addEventListener('click', () => {
    exitIntent.hidden = true;
  });

  // --- Referral-Link kopieren ---
  document.getElementById('referral-copy-btn')?.addEventListener('click', function () {
    const input = document.getElementById('referral-share-link');
    input.select();
    navigator.clipboard?.writeText(input.value);
    this.textContent = 'Kopiert!';
    setTimeout(() => { this.textContent = 'Kopieren'; }, 2000);
  });

  // --- Social-Proof-Zähler: nur echte Zahlen, erst ab einer Mindestgröße zeigen ---
  fetch('api/stats.php')
    .then((r) => r.json())
    .then((data) => {
      if (data && data.count >= 5) {
        const el = document.getElementById('social-proof-counter');
        el.textContent = `In den letzten 30 Tagen haben ${data.count} Personen den Sofort-Check gemacht.`;
        el.hidden = false;
      }
    })
    .catch(() => {});
})();
