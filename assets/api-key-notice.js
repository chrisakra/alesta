/* Erreurs de configuration IA — bouton « Configurer la clé API » (Alesta) */
(function (window, document) {
	'use strict';

	var cfg = window.AlestaKeyNoticeCfg || {};

	/* Codes d'erreur renvoyés par le serveur qui se résolvent sur la page Configuration. */
	var CONFIG_CODES = ['no_api_key', 'no_model'];

	function data(res) {
		return (res && res.data && typeof res.data === 'object') ? res.data : null;
	}

	/**
	 * Vrai si la réponse AJAX échoue faute de configuration (clé/modèle).
	 */
	function isConfigError(res) {
		var d = data(res);
		if (!d || !d.code) { return false; }
		for (var i = 0; i < CONFIG_CODES.length; i++) {
			if (d.code === CONFIG_CODES[i]) { return true; }
		}
		return false;
	}

	/**
	 * URL de la page Configuration : celle fournie par la réponse, sinon celle
	 * localisée à l'enqueue.
	 */
	function url(res) {
		var d = data(res);
		if (d && typeof d.settings_url === 'string' && d.settings_url) { return d.settings_url; }
		return cfg.settings_url || '';
	}

	/**
	 * Construit le bouton (élément DOM, aucun HTML injecté depuis le serveur).
	 */
	function button(res) {
		var href = url(res);
		if (!href) { return null; }

		var a = document.createElement('a');
		a.className = 'button button-primary alesta-key-notice-btn';
		a.href = href;
		a.textContent = cfg.configure || 'Configurer la clé API';
		a.style.marginTop = '12px';
		a.style.display = 'inline-block';
		return a;
	}

	function resolve(target) {
		if (!target) { return null; }
		if (typeof target === 'string') { return document.querySelector(target); }
		if (target.nodeType === 1) { return target; }
		if (target.jquery && target.length) { return target.get(0); }
		return null;
	}

	/**
	 * Ajoute le bouton dans un conteneur (modale, encart) si l'erreur le mérite.
	 *
	 * @return {boolean} True si un bouton a été ajouté.
	 */
	function appendTo(target, res) {
		if (!isConfigError(res)) { return false; }

		var node = resolve(target);
		var btn  = node ? button(res) : null;
		if (!node || !btn) { return false; }

		var wrap = document.createElement('p');
		wrap.style.margin = '0';
		wrap.appendChild(btn);
		node.appendChild(wrap);
		return true;
	}

	/**
	 * Variante pour les parcours qui n'ont qu'un alert() : propose d'ouvrir
	 * directement la page Configuration.
	 */
	function notify(res, message) {
		var text = message || '';

		if (!isConfigError(res)) {
			if (text) { window.alert(text); }
			return false;
		}

		var href = url(res);
		var ask  = text + '\n\n' + (cfg.open_now || 'Ouvrir la page Configuration maintenant ?');

		if (href && window.confirm(ask)) {
			window.location.href = href;
			return true;
		}
		return false;
	}

	window.AlestaKeyNotice = {
		isConfigError: isConfigError,
		url: url,
		button: button,
		appendTo: appendTo,
		notify: notify
	};

})(window, document);
