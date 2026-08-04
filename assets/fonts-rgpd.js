/* Google Fonts RGPD — Alesta */
(function ($) {
    'use strict';

    var cfg = AlestaFonts;

    // =========================================================================
    // SÉLECTEUR DE MODE
    // =========================================================================

    $(document).on('click', '.gf-mode-option', function () {
        $('.gf-mode-option').removeClass('gf-mode-active');
        $(this).addClass('gf-mode-active');
        $(this).find('input[type="radio"]').prop('checked', true);
    });

    $('#btn-save-mode').on('click', function () {
        var $btn     = $(this).prop('disabled', true);
        var $spinner = $('#spinner-mode').addClass('is-active');
        var $msg     = $('#msg-mode').text('').removeClass('ok error');
        var mode     = $('input[name="gf_mode"]:checked').val() || 'disabled';

        $.post(cfg.ajax_url, {
            action: 'alesta_fonts_save_settings',
            nonce:  cfg.nonce,
            mode:   mode,
        }, function (res) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            if (res.success) {
                $msg.addClass('ok').text('✅ ' + res.data.message);
                // Mettre à jour le badge header
                var labels = {
                    disabled:  '⏸ Désactivé',
                    auto_host: '✅ Auto-hébergement',
                    block:     '🚫 Blocage total',
                };
                var classes = {
                    disabled:  'gf-mode-disabled',
                    auto_host: 'gf-mode-auto_host',
                    block:     'gf-mode-block',
                };
                var $badge = $('.gf-mode-badge');
                $badge.text(labels[mode] || mode)
                      .removeClass('gf-mode-disabled gf-mode-auto_host gf-mode-block')
                      .addClass(classes[mode] || '');
            } else {
                $msg.addClass('error').text('❌ ' + (res.data ? res.data.message : 'Erreur.'));
            }
            setTimeout(function () { $msg.text('').removeClass('ok error'); }, 4000);
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $msg.addClass('error').text('❌ Erreur réseau.');
        });
    });

    // =========================================================================
    // SCANNER LE SITE
    // =========================================================================

    function doScan($btn) {
        var $spinner = $('#spinner-actions').addClass('is-active');
        var $msg     = $('#msg-actions').text('Scan en cours…').removeClass('ok error');
        if ($btn) $btn.prop('disabled', true);

        $.post(cfg.ajax_url, {
            action: 'alesta_fonts_scan',
            nonce:  cfg.nonce,
        }, function (res) {
            $spinner.removeClass('is-active');
            if ($btn) $btn.prop('disabled', false);

            if (res.success) {
                var count = res.data.count || 0;
                $msg.addClass('ok').text('✅ ' + count + ' requête(s) détectée(s). Rechargement…');
                setTimeout(function () { location.reload(); }, 1200);
            } else {
                $msg.addClass('error').text('❌ ' + (res.data ? res.data.message : 'Erreur.'));
            }
        }).fail(function () {
            $spinner.removeClass('is-active');
            if ($btn) $btn.prop('disabled', false);
            $msg.addClass('error').text('❌ Erreur réseau.');
        });
    }

    $('#btn-scan').on('click', function () { doScan($(this)); });
    $(document).on('click', '#btn-scan-empty', function () { doScan($(this)); });

    // =========================================================================
    // TÉLÉCHARGER TOUT
    // =========================================================================

    $('#btn-download-all').on('click', function () {
        var $btn     = $(this).prop('disabled', true);
        var $spinner = $('#spinner-actions').addClass('is-active');
        var $msg     = $('#msg-actions').text('Téléchargement en cours…').removeClass('ok error');

        $.post(cfg.ajax_url, {
            action: 'alesta_fonts_download_all',
            nonce:  cfg.nonce,
        }, function (res) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            if (res.success) {
                var d = res.data;
                updateStats(d.stats);
                $msg.addClass('ok').text(
                    '✅ ' + d.success + ' téléchargée(s)' +
                    (d.errors > 0 ? ' — ' + d.errors + ' erreur(s).' : '.')
                );
                if (d.success > 0) setTimeout(function () { location.reload(); }, 1500);
            } else {
                $msg.addClass('error').text('❌ ' + (res.data ? res.data.message : 'Erreur.'));
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $msg.addClass('error').text('❌ Erreur réseau.');
        });
    });

    // =========================================================================
    // SUPPRIMER TOUT
    // =========================================================================

    $('#btn-clear-all').on('click', function () {
        if (!confirm('Supprimer tous les fichiers de polices locaux ? Le registre sera réinitialisé.')) return;

        var $btn     = $(this).prop('disabled', true);
        var $spinner = $('#spinner-actions').addClass('is-active');
        var $msg     = $('#msg-actions').text('').removeClass('ok error');

        $.post(cfg.ajax_url, {
            action: 'alesta_fonts_clear_all',
            nonce:  cfg.nonce,
        }, function (res) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            if (res.success) {
                $msg.addClass('ok').text('✅ ' + res.data.message);
                setTimeout(function () { location.reload(); }, 1000);
            } else {
                $msg.addClass('error').text('❌ ' + (res.data ? res.data.message : 'Erreur.'));
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $msg.addClass('error').text('❌ Erreur réseau.');
        });
    });

    // =========================================================================
    // TÉLÉCHARGER UNE POLICE
    // =========================================================================

    $(document).on('click', '.gf-btn-download, .gf-btn-redownload', function () {
        var key  = $(this).data('key');
        downloadOne(key, $(this));
    });

    function downloadOne(key, $btn) {
        var $card    = $('#gf-card-' + key);
        var $spinner = $('#gf-spinner-' + key).show();
        var $status  = $('#gf-status-' + key);
        if ($btn) $btn.prop('disabled', true);

        $status.text('⏳ Téléchargement…')
               .removeClass('gf-status-ok gf-status-error gf-status-pending')
               .addClass('gf-status-pending');

        $.post(cfg.ajax_url, {
            action: 'alesta_fonts_download_one',
            nonce:  cfg.nonce,
            key:    key,
        }, function (res) {
            $spinner.hide();
            if ($btn) $btn.prop('disabled', false);

            if (res.success) {
                var entry = res.data.entry;
                $status.text('✅ Hébergé localement')
                       .removeClass('gf-status-pending gf-status-error')
                       .addClass('gf-status-ok');
                $card.removeClass('gf-status-pending gf-status-error').addClass('gf-status-ok');
                // Mettre à jour l'info téléchargée (rechargement partiel)
                setTimeout(function () { location.reload(); }, 800);
                updateStats(res.data.stats);
            } else {
                $status.text('❌ Erreur')
                       .removeClass('gf-status-pending gf-status-ok')
                       .addClass('gf-status-error');
                $card.removeClass('gf-status-pending gf-status-ok').addClass('gf-status-error');

                // Afficher l'erreur
                if ($card.find('.gf-error-msg').length) {
                    $card.find('.gf-error-msg').text('⚠️ ' + (res.data ? res.data.message : 'Erreur inconnue.'));
                } else {
                    $card.append('<div class="gf-error-msg">⚠️ ' + escHtml(res.data ? res.data.message : 'Erreur.') + '</div>');
                }
            }
        }).fail(function () {
            $spinner.hide();
            if ($btn) $btn.prop('disabled', false);
            $status.text('❌ Erreur réseau').addClass('gf-status-error');
        });
    }

    // =========================================================================
    // SUPPRIMER UNE POLICE
    // =========================================================================

    $(document).on('click', '.gf-btn-delete', function () {
        var key  = $(this).data('key');
        var $card = $('#gf-card-' + key);

        if (!confirm('Supprimer les fichiers locaux de cette police ?')) return;

        $.post(cfg.ajax_url, {
            action: 'alesta_fonts_delete_one',
            nonce:  cfg.nonce,
            key:    key,
        }, function (res) {
            if (res.success) {
                updateStats(res.data.stats);
                // Remettre la carte en état "pending"
                $card.removeClass('gf-status-ok gf-status-error').addClass('gf-status-pending');
                $('#gf-status-' + key)
                    .text('⏳ En attente')
                    .removeClass('gf-status-ok gf-status-error')
                    .addClass('gf-status-pending');
                $card.find('.gf-font-local, .gf-downloaded-info').remove();
                $card.find('.gf-btn-redownload').addClass('gf-btn-download').removeClass('gf-btn-redownload').text('⬇️ Télécharger');
            }
        });
    });

    // =========================================================================
    // HELPERS
    // =========================================================================

    function updateStats(stats) {
        if (!stats) return;
        $('#gf-stat-total').text(stats.total || 0);
        $('#gf-stat-downloaded').text(stats.downloaded || 0);
        $('#gf-stat-pending').text(stats.pending || 0);
        $('#gf-stat-errors').text(stats.errors || 0);
        $('#gf-stat-size').text((stats.size_kb || 0) + ' Ko');
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

}(jQuery));
