/* Configuration (fournisseur IA, clé API, modèle) — Alesta */
(function ($) {
	'use strict';

	if (typeof AlestaSettings === 'undefined') { return; }

	var ajaxUrl = AlestaSettings.ajax_url;
	var nonce   = AlestaSettings.nonce;
	var i18n    = AlestaSettings.i18n || {};

	var $provider = $('#alesta-provider');
	var $feedback = $('#alesta-settings-feedback');
	var $badge    = $('#alesta-claude-badge');

	function provider() {
		return $provider.val() || 'anthropic';
	}

	function $keyField(p) {
		return $('#alesta-key-' + (p || provider()));
	}

	function $modelField(p) {
		return $('#alesta-model-' + (p || provider()));
	}

	/* Champs POST attendus par le serveur, pour les deux fournisseurs. */
	function payload() {
		return {
			nonce:        nonce,
			provider:     provider(),
			api_key:      ($keyField('anthropic').val()   || '').trim(),
			openai_key:   ($keyField('openai').val()      || '').trim(),
			model:        ($modelField('anthropic').val() || '').trim(),
			openai_model: ($modelField('openai').val()    || '').trim()
		};
	}

	function feedback(ok, msg) {
		$feedback
			.stop(true, true)
			.css({
				display:    'block',
				background: ok ? '#d1fae5' : '#fee2e2',
				color:      ok ? '#065f46' : '#991b1b',
				border:     '1px solid ' + (ok ? '#6ee7b7' : '#fca5a5')
			})
			.text((ok ? '✓ ' : '⚠ ') + msg);
	}

	function setBadge(connected) {
		$badge
			.attr('data-connected', connected ? '1' : '0')
			.css({
				background: connected ? '#d1fae5' : '#fee2e2',
				color:      connected ? '#065f46' : '#991b1b',
				border:     '1px solid ' + (connected ? '#6ee7b7' : '#fca5a5')
			})
			.text((connected ? '✓ ' : '✗ ') + (connected ? i18n.connected : i18n.not_configured));
	}

	function errorMessage(res) {
		if (res && res.data && res.data.message) { return res.data.message; }
		if (res && typeof res.data === 'string') { return res.data; }
		return i18n.unknown_error || 'Erreur inconnue.';
	}

	/* ── Bascule de fournisseur : les deux blocs restent dans le DOM ── */
	function syncProviderBlocks() {
		var current = provider();
		$('.alesta-provider-block').each(function () {
			$(this).toggle($(this).data('provider') === current);
		});
	}

	$provider.on('change', syncProviderBlocks);
	syncProviderBlocks();

	/* ── Afficher / masquer une clé ── */
	$('.alesta-toggle-key').on('click', function () {
		var $input = $('#' + $(this).data('target'));
		var isPwd  = $input.attr('type') === 'password';
		$input.attr('type', isPwd ? 'text' : 'password');
		$(this).find('.dashicons')
			.toggleClass('dashicons-visibility', !isPwd)
			.toggleClass('dashicons-hidden', isPwd);
	});

	/* ── Enregistrer ── */
	$('#alesta-btn-save').on('click', function () {
		var $btn = $(this).prop('disabled', true).text(i18n.saving || 'Enregistrement…');

		$.post(ajaxUrl, $.extend({ action: 'alesta_settings_save' }, payload()), function (res) {
			if (res.success) {
				feedback(true, res.data.message);
				setBadge(!!res.data.has_key);
				if (res.data.has_key) {
					$keyField().val('');
					if (res.data.masked) {
						$keyField().attr('placeholder', res.data.masked + ' — laisser vide pour conserver');
					}
					if (!$('.alesta-btn-delete[data-provider="' + provider() + '"]').length) {
						setTimeout(function () { location.reload(); }, 900);
					}
				}
			} else {
				feedback(false, errorMessage(res));
			}
		}).fail(function () {
			feedback(false, i18n.network_error || 'Erreur réseau.');
		}).always(function () {
			$btn.prop('disabled', false).text(i18n.save || 'Enregistrer');
		});
	});

	/* ── Tester la clé (celle du champ si remplie, sinon la clé stockée) ── */
	$('#alesta-btn-test').on('click', function () {
		var $btn = $(this).prop('disabled', true).text(i18n.testing || 'Test en cours…');

		$.post(ajaxUrl, $.extend({ action: 'alesta_test_api_key' }, payload()), function (res) {
			if (res.success) {
				feedback(true, res.data.message);
			} else {
				feedback(false, errorMessage(res));
			}
		}).fail(function () {
			feedback(false, i18n.network_error || 'Erreur réseau.');
		}).always(function () {
			$btn.prop('disabled', false).text(i18n.test || 'Tester la clé');
		});
	});

	/* Remplace la liste de modeles d'un fournisseur sans recharger la page.
	   Le champ peut etre un <input text> (aucun modele en cache au rendu) : on
	   le transforme alors en <select>. La valeur courante est conservee. */
	function fillModels(target, models) {
		var $field = $('.alesta-model-input[data-provider="' + target + '"]');
		if (!$field.length || !models || typeof models !== 'object') { return; }

		var current = $field.val() || '';
		var $select = $('<select>', {
			id:    $field.attr('id'),
			class: 'alesta-model-input',
			style: 'flex:1;min-width:260px;max-width:420px;'
		}).attr('data-provider', target);

		var seen = false;
		$.each(models, function (id, label) {
			if (id === current) { seen = true; }
			$select.append($('<option>', { value: id, text: label }));
		});
		if (current && !seen) {
			$select.append($('<option>', { value: current, text: current }));
		}
		$select.val(current);
		$field.replaceWith($select);
	}

	/* ── Rafraîchir la liste des modèles du fournisseur ── */
	$('.alesta-btn-refresh-models').on('click', function () {
		var target = $(this).data('provider');
		var $btn   = $(this).prop('disabled', true).text(i18n.refreshing || 'Récupération…');

		$.post(ajaxUrl, $.extend({ action: 'alesta_settings_refresh_models' }, payload(), { provider: target }), function (res) {
			if (res.success) {
				feedback(true, res.data.message);
				// Mise a jour sur place : un rechargement perdrait la cle saisie
				// mais pas encore enregistree.
				fillModels(target, res.data.models);
			} else {
				feedback(false, errorMessage(res));
			}
		}).fail(function () {
			feedback(false, i18n.network_error || 'Erreur réseau.');
		}).always(function () {
			$btn.prop('disabled', false).text(i18n.refresh || 'Rafraîchir la liste');
		});
	});

	/* ── Supprimer la clé d'un fournisseur ── */
	$('.alesta-btn-delete').on('click', function () {
		if (!window.confirm(i18n.confirm_delete || 'Supprimer la clé API ?')) { return; }
		var target = $(this).data('provider');
		var $btn   = $(this).prop('disabled', true);

		$.post(ajaxUrl, {
			action:   'alesta_settings_delete_key',
			nonce:    nonce,
			provider: target
		}, function (res) {
			if (res.success) {
				feedback(true, res.data.message);
				if (target === provider()) { setBadge(false); }
				setTimeout(function () { location.reload(); }, 900);
			} else {
				feedback(false, errorMessage(res));
				$btn.prop('disabled', false);
			}
		}).fail(function () {
			feedback(false, i18n.network_error || 'Erreur réseau.');
			$btn.prop('disabled', false);
		});
	});

})(jQuery);
