/* Budget API — Alesta */
(function ($) {
    'use strict';

    var ajaxUrl = AlestaBudget.ajax_url;
    var nonce   = AlestaBudget.nonce;

    /* ── Slider de seuil ── */
    $(document).on('input', '#bgt-threshold', function () {
        $('#bgt-threshold-val').text($(this).val());
    });

    /* ── Toggle "Bloquer / Avertir" ── */
    $(document).on('click', '.bgt-toggle-opt', function () {
        $('.bgt-toggle-opt').removeClass('active');
        $(this).addClass('active');
        $('#bgt-block').val($(this).data('val'));
    });

    /* ── Enregistrer les réglages ── */
    $('#bgt-btn-save').on('click', function () {
        var $btn = $(this);
        var $msg = $('#bgt-settings-msg');
        var limit = parseFloat($('#bgt-limit').val()) || 0;

        $btn.prop('disabled', true).text('Enregistrement…');
        $msg.text('').css('color', '#16a34a');

        $.post(ajaxUrl, {
            action:           'alesta_budget_save',
            nonce:            nonce,
            monthly_limit:    limit,
            alert_threshold:  parseInt($('#bgt-threshold').val(), 10),
            block_on_limit:   $('#bgt-block').val(),
            alert_email:      $('#bgt-email').val().trim(),
        }, function (res) {
            if (res.success) {
                $msg.text('✓ ' + res.data.msg);
                setTimeout(function () { location.reload(); }, 1200);
            } else {
                $msg.css('color', '#dc2626').text('⚠ ' + (res.data || 'Erreur inconnue.'));
            }
        }).fail(function () {
            $msg.css('color', '#dc2626').text('⚠ Erreur réseau.');
        }).always(function () {
            $btn.prop('disabled', false).text('Enregistrer les réglages');
        });
    });

    /* ── Reset mois en cours ── */
    $('#bgt-btn-reset-month').on('click', function () {
        if (!confirm('Remettre à zéro les statistiques du mois en cours ?\n\nCette action est irréversible.')) return;

        var $btn = $(this).prop('disabled', true).text('Réinitialisation…');

        $.post(ajaxUrl, {
            action: 'alesta_budget_reset_month',
            nonce:  nonce,
        }, function (res) {
            if (res.success) {
                showToast('✓ ' + res.data.msg, 'success');
                setTimeout(function () { location.reload(); }, 800);
            } else {
                showToast('⚠ ' + (res.data || 'Erreur.'), 'error');
                $btn.prop('disabled', false).text('Remettre à zéro ce mois');
            }
        }).fail(function () {
            showToast('⚠ Erreur réseau.', 'error');
            $btn.prop('disabled', false).text('Remettre à zéro ce mois');
        });
    });

    /* ── Reset global ── */
    $('#bgt-btn-reset-global').on('click', function () {
        if (!confirm('⚠️ ATTENTION !\n\nCette action supprime TOUT l\'historique de consommation Claude.\nIl n\'est pas possible d\'annuler.\n\nConfirmer ?')) return;
        if (!confirm('Dernière confirmation : supprimer tout l\'historique ?')) return;

        var $btn = $(this).prop('disabled', true).text('Suppression…');

        $.post(ajaxUrl, {
            action: 'alesta_budget_reset_global',
            nonce:  nonce,
        }, function (res) {
            if (res.success) {
                showToast('✓ ' + res.data.msg, 'success');
                setTimeout(function () { location.reload(); }, 800);
            } else {
                showToast('⚠ ' + (res.data || 'Erreur.'), 'error');
                $btn.prop('disabled', false).text('Réinitialiser tout l\'historique');
            }
        }).fail(function () {
            showToast('⚠ Erreur réseau.', 'error');
            $btn.prop('disabled', false).text('Réinitialiser tout l\'historique');
        });
    });

    /* ── Export CSV ── */
    $('#bgt-btn-export').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('Génération…');

        $.post(ajaxUrl, {
            action: 'alesta_budget_export',
            nonce:  nonce,
        }, function (res) {
            if (res.success && res.data.csv) {
                var blob     = new Blob(['﻿' + res.data.csv], { type: 'text/csv;charset=utf-8;' });
                var url      = URL.createObjectURL(blob);
                var link     = document.createElement('a');
                link.href    = url;
                link.download = res.data.filename || 'alesta-budget.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            } else {
                showToast('⚠ Aucune donnée à exporter.', 'error');
            }
        }).fail(function () {
            showToast('⚠ Erreur réseau.', 'error');
        }).always(function () {
            $btn.prop('disabled', false).text('⬇ Exporter CSV (90 jours)');
        });
    });

    /* ── Toast notification ── */
    function showToast(msg, type) {
        var bg = (type === 'success') ? '#16a34a' : '#dc2626';
        var $t = $('<div>').text(msg).css({
            position: 'fixed', bottom: '28px', right: '28px',
            background: bg, color: '#fff',
            padding: '12px 20px', borderRadius: '8px',
            fontSize: '13px', fontWeight: '600',
            boxShadow: '0 4px 16px rgba(0,0,0,.18)',
            zIndex: 99999, opacity: 0,
            transition: 'opacity .25s',
        });
        $('body').append($t);
        $t[0].offsetHeight; // reflow
        $t.css('opacity', 1);
        setTimeout(function () {
            $t.css('opacity', 0);
            setTimeout(function () { $t.remove(); }, 300);
        }, 3500);
    }

}(jQuery));
