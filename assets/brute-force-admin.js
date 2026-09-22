/* Protection Brute Force — Page admin (Alesta) */
jQuery(function ($) {
    'use strict';

    var cfg  = window.AlestaBruteForce || {};
    var i18n = cfg.i18n || {};

    function feedback($el, ok, text) {
        $el.removeClass('is-ok is-err')
           .addClass(ok ? 'is-ok' : 'is-err')
           .text((ok ? '✓ ' : '✗ ') + text)
           .prop('hidden', false);
    }

    /* Sauvegarde des paramètres */
    $('#bf-save-btn').on('click', function () {
        var $btn = $(this);
        var $fb  = $('#bf-save-feedback');
        var orig = $btn.text();

        $btn.prop('disabled', true).text(i18n.saving || '…');
        $fb.prop('hidden', true);

        $.post(cfg.ajax_url, {
            action:         'alesta_bf_save_settings',
            nonce:          cfg.nonce_save,
            enabled:        $('#bf-enabled').is(':checked') ? 1 : 0,
            notify_email:   $('#bf-notify').is(':checked')  ? 1 : 0,
            threshold:      $('#bf-threshold').val(),
            window_seconds: $('#bf-window').val(),
            ban_seconds:    $('#bf-ban').val(),
            whitelist:      $('#bf-whitelist').val()
        }, function (r) {
            $btn.prop('disabled', false).text(orig);
            if (r && r.success) {
                feedback($fb, true, (r.data && r.data.message) ? r.data.message : (i18n.saved || 'OK'));
                setTimeout(function () { window.location.reload(); }, 800);
            } else {
                feedback($fb, false, (r && r.data && r.data.message) ? r.data.message : (i18n.error || 'Error'));
            }
        }).fail(function () {
            $btn.prop('disabled', false).text(orig);
            feedback($fb, false, i18n.network_error || 'Network error');
        });
    });

    /* Ajouter mon IP à la whitelist (côté client, il faut ensuite enregistrer) */
    $('#bf-add-my-ip').on('click', function () {
        var ip = cfg.client_ip || '';
        if (!ip) { return; }
        var $ta   = $('#bf-whitelist');
        var lines = $ta.val().split(/[\r\n,]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        var $fb   = $('#bf-save-feedback');
        if (lines.indexOf(ip) !== -1) {
            feedback($fb, false, i18n.ip_present || '');
            return;
        }
        lines.push(ip);
        $ta.val(lines.join('\n'));
        feedback($fb, true, i18n.ip_added || '');
        $ta.trigger('focus');
    });

    /* Unban manuel */
    $('.bf-unban-btn').on('click', function () {
        var $btn = $(this);
        var ip   = String($btn.data('ip') || '');
        var msg  = (i18n.confirm_unban || '%s').replace('%s', ip);

        if (!window.confirm(msg)) { return; }
        $btn.prop('disabled', true).text('…');

        $.post(cfg.ajax_url, {
            action: 'alesta_bf_unban',
            nonce:  cfg.nonce_unban,
            ip:     ip
        }, function (r) {
            if (r && r.success) {
                $btn.closest('tr').fadeOut(400, function () { $(this).remove(); });
            } else {
                $btn.prop('disabled', false).text(i18n.unban || 'Unban');
                var err = (r && r.data && r.data.message) ? r.data.message : (i18n.error || 'Error');
                window.alert((i18n.unban_failed || '%s').replace('%s', err));
            }
        }).fail(function () {
            $btn.prop('disabled', false).text(i18n.unban || 'Unban');
            window.alert((i18n.unban_failed || '%s').replace('%s', i18n.network_error || 'Network error'));
        });
    });
});
