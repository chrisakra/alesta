/* Debug Manager — Alesta */
jQuery(function ($) {
    'use strict';

    var ajaxUrl = AlestaDebug.ajax_url;
    var nonce   = AlestaDebug.nonce;

    // Chargement automatique du log au démarrage de la page
    if ($('#log-loading').length) {
        loadLog();
    }

    // -------------------------------------------------------------------------
    // Toggle WP_DEBUG
    // -------------------------------------------------------------------------

    $('#btn-debug-toggle').on('click', function () {
        var action = $(this).data('action');
        var msg    = action === 'enable'
            ? "Activer WP_DEBUG ?\n\nLes erreurs PHP seront enregistrées dans debug.log.\nRien ne sera affiché aux visiteurs (WP_DEBUG_DISPLAY restera false)."
            : "Désactiver WP_DEBUG ?\n\nLe journal d'erreurs sera arrêté.";

        if (!window.confirm(msg)) return;

        $('#toggle-spinner').css('visibility', 'visible');
        $('#btn-debug-toggle').prop('disabled', true);
        $('#toggle-msg').hide().removeClass('msg-success msg-error');

        $.post(ajaxUrl, {
            action:       'alesta_debug_toggle',
            nonce:        nonce,
            debug_action: action,
        })
        .done(function (res) {
            if (res.success) {
                $('#toggle-msg').text(res.data.message).addClass('msg-success').show();
                setTimeout(function () { location.reload(); }, 1500);
            } else {
                showToggleError(res.data ? res.data.message : 'Erreur inconnue.');
            }
        })
        .fail(function () {
            showToggleError('Erreur de connexion au serveur.');
        });
    });

    function showToggleError(msg) {
        $('#toggle-spinner').css('visibility', 'hidden');
        $('#btn-debug-toggle').prop('disabled', false);
        $('#toggle-msg').text(msg).addClass('msg-error').show();
    }

    // -------------------------------------------------------------------------
    // Rafraîchir le log
    // -------------------------------------------------------------------------

    $('#btn-refresh-log').on('click', function () {
        loadLog();
    });

    // -------------------------------------------------------------------------
    // Vider le log
    // -------------------------------------------------------------------------

    $('#btn-clear-log').on('click', function () {
        if (!window.confirm("Vider le fichier debug.log ?\n\nCette action est irréversible.")) return;

        var $btn = $(this).prop('disabled', true);

        $.post(ajaxUrl, {
            action: 'alesta_debug_clear_log',
            nonce:  nonce,
        })
        .done(function (res) {
            if (res.success) {
                $('#log-wrap').html(
                    '<div class="alesta-debug-empty">' +
                    '<span class="dashicons dashicons-yes-alt" style="color:#22c55e;font-size:22px;vertical-align:middle;"></span> ' +
                    'debug.log vidé avec succès.' +
                    '</div>'
                );
                $btn.prop('disabled', true);
            } else {
                window.alert(res.data ? res.data.message : 'Erreur lors du vidage.');
                $btn.prop('disabled', false);
            }
        })
        .fail(function () {
            window.alert('Erreur de connexion au serveur.');
            $btn.prop('disabled', false);
        });
    });

    // -------------------------------------------------------------------------
    // Analyser avec Claude
    // -------------------------------------------------------------------------

    $('#btn-analyze-log').on('click', function () {
        if (!AlestaDebug.has_api) {
            alert('Clé API Anthropic non configurée.\nRendez-vous dans Alesta → Réglages → Configuration.');
            return;
        }

        var $btn  = $(this).prop('disabled', true).text('Analyse en cours…');
        var $wrap = $('#claude-analysis-wrap');
        var $load = $('#claude-analysis-loading');
        var $content = $('#claude-analysis-content');

        $wrap.show();
        $load.show();
        $content.text('');

        // Scroll to analysis
        $('html, body').animate({ scrollTop: $wrap.offset().top - 80 }, 400);

        $.post(ajaxUrl, {
            action: 'alesta_debug_analyze',
            nonce:  nonce,
        })
        .done(function (res) {
            $load.hide();
            if (res.success) {
                var d = res.data;
                $content.html(
                    formatAnalysis(d.analysis) +
                    '<div style="margin-top:14px;padding-top:12px;border-top:1px solid #e5e7eb;font-size:11px;color:#9ca3af;">' +
                    '📊 ' + d.lines_analyzed + ' lignes analysées · ' +
                    (d.input_tokens + d.output_tokens) + ' tokens utilisés' +
                    '</div>'
                );
            } else {
                $content.html('<div style="color:#dc2626;">❌ ' + (res.data ? res.data.message : 'Erreur inconnue') + '</div>');
            }
        })
        .fail(function () {
            $load.hide();
            $content.html('<div style="color:#dc2626;">❌ Erreur de connexion au serveur.</div>');
        })
        .always(function () {
            $btn.prop('disabled', false).text('🤖 Analyser avec Claude');
        });
    });

    $('#btn-close-analysis').on('click', function () {
        $('#claude-analysis-wrap').hide();
    });

    function formatAnalysis(text) {
        // Convert markdown-like **bold** and headers to HTML
        return text
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/^(#{1,3} .+)$/gm, function(m, h) {
                var level = h.match(/^#+/)[0].length;
                var txt = h.replace(/^#+\s*/, '');
                var size = level === 1 ? '15px' : level === 2 ? '14px' : '13px';
                return '<div style="font-size:' + size + ';font-weight:700;color:#1d2327;margin:14px 0 6px;">' + txt + '</div>';
            })
            .replace(/\n/g, '<br>');
    }

    // Animation 'spin' is provided via debug-admin.css (no inline <style> injection).

    // -------------------------------------------------------------------------
    // Lecture du log via AJAX
    // -------------------------------------------------------------------------

    function loadLog() {
        $('#log-loading').show();
        $('#log-content').hide();
        $('#btn-refresh-log').prop('disabled', true);

        $.post(ajaxUrl, {
            action: 'alesta_debug_get_log',
            nonce:  nonce,
        })
        .done(function (res) {
            $('#log-loading').hide();
            $('#btn-refresh-log').prop('disabled', false);

            if (!res.success) {
                $('#log-content').text('Erreur : ' + (res.data ? res.data.message : 'Erreur inconnue.')).show();
                return;
            }

            if (!res.data.exists) {
                $('#log-wrap').html(
                    '<div class="alesta-debug-empty">' +
                    '<span class="dashicons dashicons-yes-alt" style="color:#22c55e;font-size:22px;vertical-align:middle;"></span> ' +
                    'Aucun fichier debug.log trouvé — aucune erreur enregistrée.' +
                    '</div>'
                );
                return;
            }

            $('#log-content').text(res.data.content).show();
            $('#btn-clear-log').prop('disabled', false);

            // Mise à jour du compteur dans le titre de la carte
            var total = parseInt(res.data.total, 10);
            if (!isNaN(total) && total > 0) {
                var label = total.toLocaleString() + ' ligne' + (total > 1 ? 's' : '') + ' au total';
                if ($('#log-line-count').length) {
                    $('#log-line-count').text(label);
                }
            }
        })
        .fail(function () {
            $('#log-loading').hide();
            $('#btn-refresh-log').prop('disabled', false);
            $('#log-content').text('Erreur de connexion au serveur.').show();
        });
    }
});
