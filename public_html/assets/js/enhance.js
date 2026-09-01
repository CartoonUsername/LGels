(function () {
  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Saison-Trigger: Okt-Feb ist Nebenkostenabrechnungs-Saison (Nachzahlungen für
  // das Vorjahr kommen meist in diesem Fenster an) — höchste Preissensibilität
  // im ganzen Jahr, deshalb andere Headline in dieser Zeit.
  const monat = new Date().getMonth() + 1; // 1-12
  if (monat >= 10 || monat <= 2) {
    const eyebrow = document.getElementById('hero-eyebrow');
    const headline = document.getElementById('hero-headline');
    const sub = document.getElementById('hero-subheadline');
    if (eyebrow) eyebrow.textContent = 'Gerade eine Nachzahlung bekommen?';
    if (headline) {
      headline.innerHTML =
        '<span class="line">Die nächste Abrechnung</span>' +
        '<span class="line accent">muss nicht so hoch sein.</span>';
    }
    if (sub) sub.textContent = 'Photovoltaik, Wärmepumpe oder ein günstigerer Stromtarif – in 20 Sekunden antippen statt Formular ausfüllen.';
  }

  // Sanftes Einblenden der Sektionen beim Scrollen
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

  // Animierte Zahlen-Counter in der Live-Metriken-Sektion
  const counters = document.querySelectorAll('.metric-value[data-count-to]');
  const runCounter = (el) => {
    const target = parseFloat(el.getAttribute('data-count-to')) || 0;
    const prefix = el.getAttribute('data-prefix') || '';
    const suffix = el.getAttribute('data-suffix') || '';
    const useThousand = el.getAttribute('data-format') === 'thousand';
    const fmt = (n) => {
      const v = Math.round(n);
      return useThousand ? v.toLocaleString('de-DE') : String(v);
    };
    if (reduceMotion) { el.innerHTML = prefix + fmt(target) + suffix; return; }
    const duration = 1600;
    const start = performance.now();
    const tick = (now) => {
      const p = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - p, 3); // easeOutCubic
      el.innerHTML = prefix + fmt(target * eased) + suffix;
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  };
  if (counters.length) {
    if ('IntersectionObserver' in window) {
      const counterObserver = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              runCounter(entry.target);
              counterObserver.unobserve(entry.target);
            }
          });
        },
        { threshold: 0.5 }
      );
      counters.forEach((el) => counterObserver.observe(el));
    } else {
      counters.forEach(runCounter);
    }
  }

  // Testimonial-Karussell (Auto-Rotation + Punkte)
  const carousel = document.getElementById('testimonial-carousel');
  if (carousel) {
    const slides = carousel.querySelectorAll('.carousel-slide');
    const dots = carousel.querySelectorAll('.carousel-dot');
    let current = 0;
    let timer = null;
    const show = (i) => {
      current = (i + slides.length) % slides.length;
      slides.forEach((s, idx) => s.classList.toggle('is-active', idx === current));
      dots.forEach((d, idx) => d.classList.toggle('is-active', idx === current));
    };
    const startAuto = () => {
      if (reduceMotion || slides.length < 2) return;
      stopAuto();
      timer = setInterval(() => show(current + 1), 5500);
    };
    const stopAuto = () => { if (timer) { clearInterval(timer); timer = null; } };
    dots.forEach((dot) => {
      dot.addEventListener('click', () => {
        show(parseInt(dot.getAttribute('data-slide'), 10) || 0);
        startAuto();
      });
    });
    carousel.addEventListener('mouseenter', stopAuto);
    carousel.addEventListener('mouseleave', startAuto);
    show(0);
    startAuto();
  }

  // Sticky-Mini-CTA + zusätzliche CTA-Buttons an den Sofort-Check koppeln
  const heroButton = document.getElementById('start-sofort-check');
  const stickyCta = document.getElementById('sticky-cta');
  const stickyCtaBtn = document.getElementById('sticky-cta-btn');
  const finalCtaBtn = document.getElementById('final-cta-btn');
  if (heroButton) {
    if (stickyCtaBtn) stickyCtaBtn.addEventListener('click', () => heroButton.click());
    if (finalCtaBtn) finalCtaBtn.addEventListener('click', () => heroButton.click());
    if (stickyCta && 'IntersectionObserver' in window) {
      const heroObserver = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            stickyCta.classList.toggle('is-visible', !entry.isIntersecting);
          });
        },
        { threshold: 0 }
      );
      heroObserver.observe(heroButton);
    }
  }
})();
