(function () {
  // Saison-Trigger: Okt-Feb ist Nebenkostenabrechnungs-Saison (Nachzahlungen für
  // das Vorjahr kommen meist in diesem Fenster an) — höchste Preissensibilität
  // im ganzen Jahr, deshalb andere Headline in dieser Zeit.
  const monat = new Date().getMonth() + 1; // 1-12
  if (monat >= 10 || monat <= 2) {
    const eyebrow = document.getElementById('hero-eyebrow');
    const headline = document.getElementById('hero-headline');
    const sub = document.getElementById('hero-subheadline');
    if (eyebrow) eyebrow.textContent = 'Gerade eine Nachzahlung bekommen?';
    if (headline) headline.textContent = 'Die nächste Abrechnung muss nicht wieder so hoch sein.';
    if (sub) sub.textContent = 'Photovoltaik, Wärmepumpe oder ein günstigerer Stromtarif – in 20 Sekunden antippen statt Formular ausfüllen.';
  }

  // Sanftes Einblenden der Sektionen beim Scrollen (respektiert prefers-reduced-motion via CSS)
  const reveals = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && reveals.length) {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15 }
    );
    reveals.forEach((el) => observer.observe(el));
  } else {
    reveals.forEach((el) => el.classList.add('is-visible'));
  }

  // Sticky-Mini-CTA: erscheint erst, sobald der Hero-Button aus dem Blickfeld ist
  const heroButton = document.getElementById('start-sofort-check');
  const stickyCta = document.getElementById('sticky-cta');
  const stickyCtaBtn = document.getElementById('sticky-cta-btn');
  if (heroButton && stickyCta && stickyCtaBtn && 'IntersectionObserver' in window) {
    const heroObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          stickyCta.classList.toggle('is-visible', !entry.isIntersecting);
        });
      },
      { threshold: 0 }
    );
    heroObserver.observe(heroButton);
    stickyCtaBtn.addEventListener('click', () => heroButton.click());
  }
})();
