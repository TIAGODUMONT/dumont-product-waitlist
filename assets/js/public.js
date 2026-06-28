/**
 * Dumont Product Waitlist — JS público.
 *
 * Pequena melhoria de UX: evita duplo envio desabilitando o botão ao submeter.
 * A validação real (obrigatórios, e-mail, nonce) é feita no servidor.
 */
(function () {
	'use strict';

	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (!form || !form.classList || !form.classList.contains('dumont-waitlist-form')) {
			return;
		}
		var btn = form.querySelector('.dumont-waitlist-form__submit');
		if (btn) {
			// Deixa o navegador enviar normalmente; só trava reenvio acidental.
			setTimeout(function () {
				btn.setAttribute('disabled', 'disabled');
			}, 0);
		}
	});
})();
