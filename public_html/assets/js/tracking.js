/**
 * Meta Pixel / Google Ads Tracking (Spec Punkt 8) — lädt nur, wenn eine ID in
 * index.html gesetzt ist, UND nur nach Cookie-Einwilligung (TTDSG/ePrivacy:
 * nicht-essenzielle Tracking-Cookies brauchen vorherige Zustimmung).
 */
(function () {
  var TRACKING_KONFIGURIERT = !!(window.META_PIXEL_ID || window.GOOGLE_ADS_ID);
  if (!TRACKING_KONFIGURIERT) return;

  function ladeMetaPixel() {
    if (!window.META_PIXEL_ID || window.fbq) return;
    !(function (f, b, e, v, n, t, s) {
      if (f.fbq) return;
      n = f.fbq = function () {
        n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments);
      };
      if (!f._fbq) f._fbq = n;
      n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
      t = b.createElement(e); t.async = true; t.src = v;
      s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
    })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', window.META_PIXEL_ID);
    fbq('track', 'PageView');
  }

  function ladeGoogleAds() {
    if (!window.GOOGLE_ADS_ID || window.gtag) return;
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(window.GOOGLE_ADS_ID);
    document.head.appendChild(s);
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { dataLayer.push(arguments); };
    gtag('js', new Date());
    gtag('config', window.GOOGLE_ADS_ID);
  }

  function trackingLaden() {
    ladeMetaPixel();
    ladeGoogleAds();
  }

  window.fireLeadConversion = function () {
    if (window.fbq) fbq('track', 'Lead');
    if (window.gtag && window.GOOGLE_ADS_ID && window.GOOGLE_ADS_CONVERSION_LABEL) {
      gtag('event', 'conversion', { send_to: window.GOOGLE_ADS_ID + '/' + window.GOOGLE_ADS_CONVERSION_LABEL });
    }
  };

  var einwilligung = null;
  try { einwilligung = localStorage.getItem('cookie_consent'); } catch (e) {}

  if (einwilligung === 'granted') {
    trackingLaden();
    return;
  }
  if (einwilligung === 'denied') {
    return;
  }

  // Noch keine Entscheidung getroffen -> Banner zeigen
  document.addEventListener('DOMContentLoaded', function () {
    var banner = document.createElement('div');
    banner.className = 'cookie-consent';
    banner.innerHTML =
      '<p>Wir nutzen Cookies für Marketing-Tracking (Meta/Google Ads). Details in der ' +
      '<a href="datenschutz.html">Datenschutzerklärung</a>.</p>' +
      '<div class="cookie-consent-buttons">' +
      '<button type="button" class="btn btn-secondary" id="cookie-deny">Ablehnen</button>' +
      '<button type="button" class="btn" id="cookie-accept">Akzeptieren</button>' +
      '</div>';
    document.body.appendChild(banner);

    document.getElementById('cookie-accept').addEventListener('click', function () {
      try { localStorage.setItem('cookie_consent', 'granted'); } catch (e) {}
      trackingLaden();
      banner.remove();
    });
    document.getElementById('cookie-deny').addEventListener('click', function () {
      try { localStorage.setItem('cookie_consent', 'denied'); } catch (e) {}
      banner.remove();
    });
  });
})();
