(function () {
  var form = document.getElementById('contact-form');
  if (!form) return;
  var status = document.getElementById('form-status');
  var elapsed = document.getElementById('f-elapsed');
  var start = Date.now();

  function setStatus(msg, ok) {
    status.textContent = msg;
    status.className = 'form__status ' + (ok ? 'ok' : 'err');
  }

  // Retour sans JavaScript (redirection depuis contact.php)
  var params = new URLSearchParams(window.location.search);
  if (params.get('envoi') === 'ok') setStatus('Merci, votre message a bien été envoyé. Je reviens vers vous rapidement.', true);
  if (params.get('envoi') === 'erreur') setStatus('Le message n’a pas pu être envoyé. Vous pouvez écrire directement à maylis@yuny.fr.', false);

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    elapsed.value = Math.round((Date.now() - start) / 1000);

    var invalid = false;
    form.querySelectorAll('[required]').forEach(function (el) {
      var bad = !el.checkValidity();
      el.closest('.field').classList.toggle('is-invalid', bad);
      el.setAttribute('aria-invalid', bad ? 'true' : 'false');
      if (bad && !invalid) { invalid = true; el.focus(); }
    });
    if (invalid) { setStatus('Merci de compléter les champs obligatoires (message de 10 caractères minimum, email valide).', false); return; }

    var btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    setStatus('Envoi en cours…', true);

    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'Accept': 'application/json' }
    })
      .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
      .then(function (data) {
        if (data.ok) {
          form.reset();
          start = Date.now();
          setStatus('Merci, votre message a bien été envoyé. Je reviens vers vous rapidement.', true);
        } else {
          setStatus(data.error || 'Le message n’a pas pu être envoyé. Vous pouvez écrire directement à maylis@yuny.fr.', false);
        }
      })
      .catch(function () {
        setStatus('Le message n’a pas pu être envoyé. Vous pouvez écrire directement à maylis@yuny.fr.', false);
      })
      .then(function () { btn.disabled = false; });
  });
})();
