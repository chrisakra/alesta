jQuery(function ($) {

    var state = {};

    // =========================================================================
    // INIT
    // =========================================================================
    function loadState() {
        $.post(AlestaRobots.ajax_url, {
            action: 'alesta_robots_read',
            nonce:  AlestaRobots.nonce,
        }, function (r) {
            if (!r.success) {
                $('#robots-status-bar').text('Erreur de lecture').css('color', '#fca5a5');
                return;
            }
            state = r.data;
            renderState();
        });
    }

    function renderState() {
        $('#robots-global-status').show();
        $('#robots-status-bar').text('robots.txt charge').css('color', '#6ee7b7');

        // Fichier
        $('#robots-file-status').html(
            state.exists
                ? '<span style="color:#065f46;">Fichier physique present</span>'
                : '<span style="color:#f59e0b;">Aucun fichier (WordPress virtuel)</span>'
        );

        // Ecriture
        $('#robots-write-status').html(
            state.can_write
                ? '<span style="color:#065f46;">Accessible</span>'
                : '<span style="color:#991b1b;">Lecture seule</span>'
        );

        // Backup
        $('#robots-backup-date').text(state.backup_date || 'Aucune sauvegarde');
        if (state.has_backup) $('#btn-robots-restore').prop('disabled', false);

        // URL
        $('#robots-url').html('<a href="' + escHtml(state.url) + '" target="_blank" style="font-size:12px;color:#1e3a5f;">' + escHtml(state.url) + '</a>');

        // Editeur
        if (state.content) {
            $('#robots-editor').val(state.content);
        } else {
            $('#robots-editor').val(state.default);
        }

        // Contenu par defaut
        $('#robots-default-preview').text(state.default);

        // Notice WordPress virtuel
        if (state.is_virtual) {
            $('#robots-virtual-notice').show();
        }

        // Desactiver editeur si pas d'ecriture
        if (!state.can_write) {
            $('#robots-editor').prop('readonly', true).css('background', '#f9fafb').attr('title', 'Fichier non accessible en ecriture');
            $('#btn-robots-save').prop('disabled', true);
            $('#btn-robots-reset').prop('disabled', true);
        }
    }

    loadState();

    // =========================================================================
    // SAUVEGARDER
    // =========================================================================
    $('#btn-robots-save').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaRobots.ajax_url, {
            action:  'alesta_robots_save',
            nonce:   AlestaRobots.nonce,
            content: $('#robots-editor').val(),
        }, function (r) {
            $btn.prop('disabled', false).text('Enregistrer robots.txt');
            if (r.success) {
                toast(r.data.message);
                feedback('ok', r.data.message);
                state.exists     = true;
                state.is_virtual = false;
                $('#robots-virtual-notice').hide();
                $('#robots-file-status').html('<span style="color:#065f46;">Fichier physique present</span>');
            } else {
                feedback('error', r.data && r.data.message ? r.data.message : 'Erreur inconnue');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Enregistrer robots.txt');
            feedback('error', 'Erreur reseau.');
        });
    });

    // =========================================================================
    // REINITIALISER
    // =========================================================================
    $('#btn-robots-reset').on('click', function () {
        if (!confirm('Reinitialiser le robots.txt avec le contenu par defaut ? Le contenu actuel sera sauvegarde.')) return;
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaRobots.ajax_url, {
            action: 'alesta_robots_reset',
            nonce:  AlestaRobots.nonce,
        }, function (r) {
            $btn.prop('disabled', false).text('Reinitialiser par defaut');
            if (r.success) {
                $('#robots-editor').val(r.data.content);
                toast(r.data.message);
                feedback('ok', r.data.message);
            } else {
                feedback('error', r.data && r.data.message ? r.data.message : 'Erreur inconnue');
            }
        });
    });

    // =========================================================================
    // UTILISER LE CONTENU PAR DEFAUT
    // =========================================================================
    $('#btn-use-default').on('click', function () {
        $('#robots-editor').val(state.default);
    });

    // =========================================================================
    // SAUVEGARDER MANUELLEMENT
    // =========================================================================
    $('#btn-robots-backup').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaRobots.ajax_url, { action: 'alesta_robots_backup', nonce: AlestaRobots.nonce }, function (r) {
            $btn.prop('disabled', false).text('Sauvegarder');
            if (r.success) {
                $('#robots-backup-date').text(r.data.date);
                $('#btn-robots-restore').prop('disabled', false);
                toast(r.data.message);
            } else {
                alert(r.data && r.data.message ? r.data.message : 'Erreur');
            }
        });
    });

    // =========================================================================
    // RESTAURER
    // =========================================================================
    $('#btn-robots-restore').on('click', function () {
        if (!confirm('Restaurer le robots.txt depuis la sauvegarde ?')) return;
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaRobots.ajax_url, { action: 'alesta_robots_restore', nonce: AlestaRobots.nonce }, function (r) {
            $btn.prop('disabled', false).text('Restaurer');
            if (r.success) {
                $('#robots-editor').val(r.data.content);
                toast(r.data.message);
                feedback('ok', r.data.message);
            } else {
                alert(r.data && r.data.message ? r.data.message : 'Erreur');
            }
        });
    });

    // =========================================================================
    // VERIFIER ACCESSIBILITE
    // =========================================================================
    $('#btn-robots-ping').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaRobots.ajax_url, { action: 'alesta_robots_ping', nonce: AlestaRobots.nonce }, function (r) {
            $btn.prop('disabled', false).text('Verifier accessibilite');
            var $res = $('#robots-ping-result').show();
            if (r.success) {
                var ok = r.data.ok;
                var color = ok ? '#065f46' : '#991b1b';
                var bg    = ok ? '#f0fdf4' : '#fef2f2';
                var border= ok ? '#d1fae5' : '#fecaca';
                $res.css({'background': bg, 'border-color': border, 'color': color});
                $res.html(
                    '<strong>' + (ok ? 'robots.txt accessible (HTTP ' + r.data.code + ')' : 'Erreur HTTP ' + r.data.code) + '</strong>'
                    + (r.data.preview ? '<br><pre style="margin:8px 0 0;font-size:11px;background:#f8fafc;padding:10px;border-radius:4px;overflow:auto;max-height:120px;">' + escHtml(r.data.preview) + '</pre>' : '')
                );
            } else {
                $res.css({'background': '#fef2f2', 'border-color': '#fecaca', 'color': '#991b1b'});
                $res.html('<strong>Erreur :</strong> ' + escHtml(r.data && r.data.message ? r.data.message : 'Inconnue'));
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Verifier accessibilite');
        });
    });

    // =========================================================================
    // HELPERS
    // =========================================================================
    function feedback(type, msg) {
        var color  = type === 'ok' ? '#065f46'  : '#991b1b';
        var bg     = type === 'ok' ? '#f0fdf4'  : '#fef2f2';
        var border = type === 'ok' ? '#d1fae5'  : '#fecaca';
        $('#robots-feedback')
            .css({'background': bg, 'border': '1px solid ' + border, 'color': color, 'border-radius': '6px', 'padding': '10px 14px'})
            .text(msg)
            .show();
    }

    function toast(msg) {
        var $t = $('<div style="position:fixed;bottom:24px;right:24px;background:#065f46;color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,.2);">' + msg + '</div>');
        $('body').append($t);
        setTimeout(function () { $t.fadeOut(400, function () { $t.remove(); }); }, 3000);
    }

    function escHtml(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
});
