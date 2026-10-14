// Newsletter-Anmeldung im Footer (alle Seiten). Gleiches Muster wie das
// Kontaktformular: Zeit-Token von /api/anfrage.php?token holen, per fetch
// absenden, Antwort {ok, meldung} anzeigen. Ohne JavaScript geht das Formular
// normal ab und anfrage.php zeigt eine eigene Seite.
(function () {
  var formular = document.querySelector('form[data-newsletter]');
  if (!formular) return;
  var tokenFeld = formular.querySelector('input[name="fz"]');
  var status = formular.querySelector('.footer-nl__status');
  var knopf = formular.querySelector('button[type="submit"]');
  var tokenGeholt = false;

  // Token erst holen, wenn jemand ins Feld tippt — spart Anfragen auf jeder Seite
  function tokenHolen() {
    if (tokenGeholt) return;
    tokenGeholt = true;
    fetch('/api/anfrage.php?token', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d && d.token) tokenFeld.value = d.token; })
      .catch(function () { tokenGeholt = false; });
  }
  formular.addEventListener('focusin', tokenHolen);

  formular.addEventListener('submit', function (ev) {
    ev.preventDefault();
    status.textContent = '';
    status.classList.remove('is-fehler');
    knopf.disabled = true;
    fetch(formular.action, {
      method: 'POST',
      body: new FormData(formular),
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        status.textContent = d.meldung || '';
        if (d.ok) {
          formular.classList.add('is-gesendet');
        } else {
          status.classList.add('is-fehler');
          knopf.disabled = false;
          tokenGeholt = false;
          tokenHolen();
        }
      })
      .catch(function () {
        status.textContent = 'Das hat gerade nicht geklappt. Bitte versuchen Sie es später noch einmal.';
        status.classList.add('is-fehler');
        knopf.disabled = false;
      });
  });
})();
