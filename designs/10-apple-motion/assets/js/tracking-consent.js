/* ===== Consent-Banner + Meta-Pixel =====
   Nur auf Kampagnenseiten eingebunden (Social Media Marketing,
   Projekte Austrotrucker & Paltentaler). Das Pixel lädt strikt erst
   nach Klick auf „Akzeptieren" – vorher geht keine Anfrage an Meta.
   Die Wahl liegt als „kortschak-consent" im Local Storage
   (ja/nein); „Tracking-Einstellungen" im Footer öffnet den Banner
   erneut. Google (Ads/GA4) kann später in pixelStarten() ergänzt
   werden – IDs siehe Norbert. */
(function(){
  'use strict';

  var PIXEL_ID = '1040170651052070';
  var KEY = 'kortschak-consent';

  function lesen(){ try { return localStorage.getItem(KEY); } catch(e){ return null; } }
  function schreiben(wert){ try { localStorage.setItem(KEY, wert); } catch(e){} }

  /* ---- Meta-Pixel laden + Conversion-Events verdrahten ---- */
  var pixelAktiv = false;
  function pixelStarten(){
    if (pixelAktiv) return;
    pixelAktiv = true;
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
    document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', PIXEL_ID);
    fbq('track', 'PageView');

    /* Haupt-Conversion: Formular erfolgreich abgeschickt */
    document.addEventListener('anfrage:erfolg', function(){
      fbq('track', 'Lead');
    });
    /* Neben-Conversion: Klick auf Telefon oder E-Mail */
    document.addEventListener('click', function(ev){
      var a = ev.target.closest && ev.target.closest('a[href^="tel:"], a[href^="mailto:"]');
      if (a) fbq('track', 'Contact');
    });
  }

  /* ---- Banner ---- */
  var banner = null;
  function bannerZeigen(){
    if (banner) return;
    var css = document.createElement('style');
    css.textContent =
      '.consent-banner{position:fixed;left:16px;right:16px;bottom:16px;z-index:2000;' +
      'max-width:430px;margin-right:auto;background:var(--bg-alt,#fff);color:var(--text,#111);' +
      'border:1px solid var(--border,rgba(0,0,0,.12));border-radius:var(--radius-md,14px);' +
      'box-shadow:0 12px 40px rgba(0,0,0,.22);padding:1.1rem 1.2rem;font-size:.88rem;line-height:1.5;}' +
      '.consent-banner p{margin:0 0 .85rem;}' +
      '.consent-banner a{color:inherit;text-decoration:underline;text-underline-offset:2px;}' +
      '.consent-banner__aktionen{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;}' +
      '.consent-banner__aktionen .btn{font-size:.8rem;padding:.55em 1.2em;}';
    document.head.appendChild(css);

    banner = document.createElement('div');
    banner.className = 'consent-banner';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-label', 'Einwilligung Werbe-Messung');
    banner.innerHTML =
      '<p><strong>Werbung, die ankommt?</strong> Wir würden gern mit dem Meta-Pixel messen, ' +
      'ob unsere Anzeigen zu Anfragen führen. Das passiert nur mit Ihrer Zustimmung. ' +
      'Details in der <a href="/datenschutz/#meta-pixel">Datenschutzerklärung</a>.</p>' +
      '<div class="consent-banner__aktionen">' +
      '<button type="button" class="btn btn--primary" data-consent="ja">Akzeptieren</button>' +
      '<button type="button" class="btn btn--secondary" data-consent="nein">Ablehnen</button>' +
      '</div>';
    banner.addEventListener('click', function(ev){
      var b = ev.target.closest && ev.target.closest('[data-consent]');
      if (!b) return;
      var wahl = b.getAttribute('data-consent');
      schreiben(wahl);
      banner.remove(); banner = null;
      if (wahl === 'ja') pixelStarten();
    });
    document.body.appendChild(banner);
  }

  /* ---- Footer-Link: Einwilligung später ändern ---- */
  function einstellungsLink(){
    var legal = document.querySelector('.footer__legal');
    if (!legal) return;
    var a = document.createElement('a');
    a.href = '#';
    a.textContent = 'Tracking-Einstellungen';
    a.addEventListener('click', function(ev){
      ev.preventDefault();
      try { localStorage.removeItem(KEY); } catch(e){}
      bannerZeigen();
    });
    legal.appendChild(a);
  }

  function start(){
    einstellungsLink();
    var wahl = lesen();
    if (wahl === 'ja') { pixelStarten(); }
    else if (wahl !== 'nein') { bannerZeigen(); }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
