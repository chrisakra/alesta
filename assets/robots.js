/**
 * Robots.txt module — frontend jQuery logic.
 *
 * Ported from Alesta AI Free v1.2.7. Adapted for the "alesta" plugin:
 * localized object renamed AlestaAI -> AlestaConfig, strings translated
 * to English (WordPress.org language requirement since 2025-07).
 */
jQuery(function ($) {

    var state = {};

    // =========================================================================
    // INIT
    // =========================================================================
    function loadState() {
        $.post(AlestaConfig.ajax_url, {
            action: 'alesta_robots_read',
            nonce:  AlestaConfig.nonce,
        }, function (r) {
            if (!r.success) {
                $('#robots-status-bar').text('Read error').css('color', '#fca5a5');
                return;
            }
            state = r.data;
            renderState();
        });
    }

    function renderState() {
        $('#robots-global-status').show();
        $('#robots-status-bar').text('robots.txt loaded').css('color', '#6ee7b7');

        // File
        $('#robots-file-status').html(
            state.exists
                ? '<span style="color:#065f46;">Physical file present</span>'
                : '<span style="color:#f59e0b;">No file (WordPress virtual)</span>'
        );

        // Writable
        $('#robots-write-status').html(
            state.can_write
                ? '<span style="color:#065f46;">Writable</span>'
                : '<span style="color:#991b1b;">Read only</span>'
        );

        // Backup
        $('#robots-backup-date').text(state.backup_date || 'No backup');
        if (state.has_backup) $('#btn-robots-restore').prop('disabled', false);

        // URL
        $('#robots-url').html('<a href="' + escHtml(state.url) + '" target="_blank" style="font-size:12px;color:#1e3a5f;">' + escHtml(state.url) + '</a>');

        // Editor
        if (state.content) {
            $('#robots-editor').val(state.content);
        } else {
            $('#robots-editor').val(state.default);
        }

        // Default content preview
        $('#robots-default-preview').text(state.default);

        // WordPress virtual notice
        if (state.is_virtual) {
            $('#robots-virtual-notice').show();
        }

        // Disable editor if not writable
        if (!state.can_write) {
            $('#robots-editor').prop('readonly', true).css('background', '#f9fafb').attr('title', 'File is not writable');
            $('#btn-robots-save').prop('disabled', true);
            $('#btn-robots-reset').prop('disabled', true);
        }
    }

    loadState();

    // =========================================================================
    // SAVE
    // =========================================================================
    $('#btn-robots-save').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaConfig.ajax_url, {
            action:  'alesta_robots_save',
            nonce:   AlestaConfig.nonce,
            content: $('#robots-editor').val(),
        }, function (r) {
            $btn.prop('disabled', false).text('Save robots.txt');
            if (r.success) {
                toast(r.data.message);
                feedback('ok', r.data.message);
                state.exists     = true;
                state.is_virtual = false;
                $('#robots-virtual-notice').hide();
                $('#robots-file-status').html('<span style="color:#065f46;">Physical file present</span>');
            } else {
                feedback('error', r.data && r.data.message ? r.data.message : 'Unknown error');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Save robots.txt');
            feedback('error', 'Network error.');
        });
    });

    // =========================================================================
    // RESET
    // =========================================================================
    $('#btn-robots-reset').on('click', function () {
        if (!confirm('Reset robots.txt to default content? The current content will be backed up.')) return;
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaConfig.ajax_url, {
            action: 'alesta_robots_reset',
            nonce:  AlestaConfig.nonce,
        }, function (r) {
            $btn.prop('disabled', false).text('Reset to default');
            if (r.success) {
                $('#robots-editor').val(r.data.content);
                toast(r.data.message);
                feedback('ok', r.data.message);
            } else {
                feedback('error', r.data && r.data.message ? r.data.message : 'Unknown error');
            }
        });
    });

    // =========================================================================
    // USE DEFAULT CONTENT
    // =========================================================================
    $('#btn-use-default').on('click', function () {
        $('#robots-editor').val(state.default);
    });

    // =========================================================================
    // MANUAL BACKUP
    // =========================================================================
    $('#btn-robots-backup').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaConfig.ajax_url, { action: 'alesta_robots_backup', nonce: AlestaConfig.nonce }, function (r) {
            $btn.prop('disabled', false).text('Backup');
            if (r.success) {
                $('#robots-backup-date').text(r.data.date);
                $('#btn-robots-restore').prop('disabled', false);
                toast(r.data.message);
            } else {
                alert(r.data && r.data.message ? r.data.message : 'Error');
            }
        });
    });

    // =========================================================================
    // RESTORE
    // =========================================================================
    $('#btn-robots-restore').on('click', function () {
        if (!confirm('Restore robots.txt from backup?')) return;
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaConfig.ajax_url, { action: 'alesta_robots_restore', nonce: AlestaConfig.nonce }, function (r) {
            $btn.prop('disabled', false).text('Restore');
            if (r.success) {
                $('#robots-editor').val(r.data.content);
                toast(r.data.message);
                feedback('ok', r.data.message);
            } else {
                alert(r.data && r.data.message ? r.data.message : 'Error');
            }
        });
    });

    // =========================================================================
    // CHECK ACCESSIBILITY
    // =========================================================================
    $('#btn-robots-ping').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaConfig.ajax_url, { action: 'alesta_robots_ping', nonce: AlestaConfig.nonce }, function (r) {
            $btn.prop('disabled', false).text('Check accessibility');
            var $res = $('#robots-ping-result').show();
            if (r.success) {
                var ok = r.data.ok;
                var color = ok ? '#065f46' : '#991b1b';
                var bg    = ok ? '#f0fdf4' : '#fef2f2';
                var border= ok ? '#d1fae5' : '#fecaca';
                $res.css({'background': bg, 'border-color': border, 'color': color});
                $res.html(
                    '<strong>' + (ok ? 'robots.txt is accessible (HTTP ' + r.data.code + ')' : 'HTTP error ' + r.data.code) + '</strong>'
                    + (r.data.preview ? '<br><pre style="margin:8px 0 0;font-size:11px;background:#f8fafc;padding:10px;border-radius:4px;overflow:auto;max-height:120px;">' + escHtml(r.data.preview) + '</pre>' : '')
                );
            } else {
                $res.css({'background': '#fef2f2', 'border-color': '#fecaca', 'color': '#991b1b'});
                $res.html('<strong>Error:</strong> ' + escHtml(r.data && r.data.message ? r.data.message : 'Unknown'));
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Check accessibility');
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
