/* ===== Lightbox der Projektseiten (02.09.2026) =====
   Macht auf einer Projektseite ALLE Inhaltsbilder ausserhalb des Heros
   in Grossansicht durchblaetterbar – die Beitrags-Kacheln (button.tile
   mit data-src/data-typ) genauso wie die Fotos in den Bild-Rastern und
   Kampagnen-Karten. Reihenfolge = Dokumentreihenfolge. Bedienung:
   Klick/Enter/Leertaste oeffnet, Pfeile + Pfeiltasten blaettern, Escape
   oder Klick auf den Vorhang schliesst, Fokus geht zurueck. Braucht das
   #lightbox-Markup (Schliessen, zwei Pfeile, figure.lightbox__inhalt,
   p.lightbox__zaehler) und das Lightbox-CSS der Seite. */
(function(){
  'use strict';
  function init(){
    var lb = document.getElementById('lightbox');
    var main = document.querySelector('main');
    if(!lb || !main) return;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var eintraege = [];
    main.querySelectorAll('.tile, img').forEach(function(el){
      if(el.closest('.sm-hero') || el.closest('.lightbox')) return;
      if(el.tagName === 'IMG'){
        if(el.closest('.tile') || el.closest('a') || el.getAttribute('aria-hidden') === 'true') return;
        if(!el.getAttribute('src')) return;
        eintraege.push({ el: el, typ: 'bild', src: el.currentSrc || el.src, alt: el.alt || '' });
      } else {
        if(!el.dataset.src) return;
        eintraege.push({ el: el, typ: el.dataset.typ || 'bild', src: el.dataset.src, alt: el.getAttribute('aria-label') || '' });
      }
    });
    if(!eintraege.length) return;

    var inhalt = lb.querySelector('.lightbox__inhalt');
    var zaehler = lb.querySelector('.lightbox__zaehler');
    var aktiv = -1, zuletzt = null;

    eintraege.forEach(function(e, i){
      var el = e.el;
      if(el.tagName === 'IMG'){
        el.classList.add('lb-bild');
        el.setAttribute('tabindex', '0');
        el.setAttribute('role', 'button');
        el.setAttribute('aria-label', (el.alt ? el.alt + ' – ' : '') + 'in Großansicht öffnen');
        el.addEventListener('keydown', function(ev){
          if(ev.key === 'Enter' || ev.key === ' '){ ev.preventDefault(); oeffnen(i); }
        });
      }
      el.addEventListener('click', function(){ oeffnen(i); });
      var v = el.querySelector && el.querySelector('video');
      if(v && !reduce){
        el.addEventListener('mouseenter', function(){ v.play().catch(function(){}); });
        el.addEventListener('mouseleave', function(){ v.pause(); });
      }
    });

    function zeigen(i){
      aktiv = (i + eintraege.length) % eintraege.length;
      var e = eintraege[aktiv];
      inhalt.innerHTML = '';
      var el;
      if(e.typ === 'video'){
        el = document.createElement('video');
        el.src = e.src; el.controls = true; el.playsInline = true; el.autoplay = !reduce;
      } else {
        el = document.createElement('img');
        el.src = e.src; el.alt = e.alt;
      }
      inhalt.appendChild(el);
      if(zaehler) zaehler.textContent = (aktiv + 1) + ' / ' + eintraege.length;
    }
    function oeffnen(i){
      zuletzt = document.activeElement;
      lb.hidden = false;
      document.body.style.overflow = 'hidden';
      zeigen(i);
      var x = lb.querySelector('.lightbox__schliessen'); if(x) x.focus();
    }
    function schliessen(){
      lb.hidden = true;
      document.body.style.overflow = '';
      inhalt.innerHTML = '';
      if(zuletzt && zuletzt.focus) zuletzt.focus();
    }
    var x = lb.querySelector('.lightbox__schliessen');
    var l = lb.querySelector('.lightbox__pfeil--links');
    var r = lb.querySelector('.lightbox__pfeil--rechts');
    if(x) x.addEventListener('click', schliessen);
    if(l) l.addEventListener('click', function(){ zeigen(aktiv - 1); });
    if(r) r.addEventListener('click', function(){ zeigen(aktiv + 1); });
    lb.addEventListener('click', function(ev){ if(ev.target === lb || ev.target === inhalt) schliessen(); });
    document.addEventListener('keydown', function(ev){
      if(lb.hidden) return;
      if(ev.key === 'Escape') schliessen();
      else if(ev.key === 'ArrowLeft') zeigen(aktiv - 1);
      else if(ev.key === 'ArrowRight') zeigen(aktiv + 1);
    });
  }
  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
