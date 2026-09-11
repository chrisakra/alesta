/**
 * Alesta — Minify & Preload admin
 * AJAX handlers for the single-view page (CSS / JS / HTML / Preload).
 */
(function ($) {
    'use strict';

    function msg($msg, $spinner, text, ok) {
        if ($spinner) $spinner.removeClass('is-active');
        $msg.text(text).css('color', ok ? '#16a34a' : '#dc2626');
        setTimeout(function () { $msg.text(''); }, 4000);
    }

    function updateStats(stats) {
        if (!stats) return;
        $('#stat-css-files').text(stats.css_files  || 0);
        $('#stat-js-files' ).text(stats.js_files   || 0);
        $('#stat-total-size').text(stats.total_size || '0 Ko');
    }

    function post(payload, done, fail) {
        return $.post(AlestaMinify.ajax_url, $.extend({ nonce: AlestaMinify.nonce }, payload))
                .done(done)
                .fail(fail || function () {});
    }

    // ──────────────────────────────────────────────────────────────────
    // Toggles (CSS / JS / HTML / Preload)
    // ──────────────────────────────────────────────────────────────────
    $(document).on('change', '.mnf-switch', function () {
        var $sw     = $(this);
        var type    = $sw.data('type');
        var value   = $sw.is(':checked') ? 1 : 0;
        var $slider = $sw.siblings('.mnf-slider');
        var $lbl    = $('.mnf-status-label[data-type="' + type + '"]');

        // Feedback immédiat
        $slider.toggleClass('on', !!value);
        $lbl.text(value ? 'Actif' : 'Inactif').toggleClass('on', !!value);

        post({ action: 'alesta_minify_toggle', type: type, value: value });
    });

    // ──────────────────────────────────────────────────────────────────
    // Vider le cache (boutons partagés)
    // ──────────────────────────────────────────────────────────────────
    $(document).on('click', '#btn-clear-minify-cache, .btn-clear-minify-cache-js', function () {
        var $btn = $(this).prop('disabled', true);
        post({ action: 'alesta_minify_clear_cache' }, function (res) {
            $btn.prop('disabled', false);
            if (res && res.success) {
                updateStats(res.data.stats);
                // Petit feedback dans le premier msg dispo
                var $slot = $('#msg-css').length ? $('#msg-css') : $('#msg-js');
                msg($slot, null, '🗑 ' + res.data.message, true);
            }
        });
    });

    // ──────────────────────────────────────────────────────────────────
    // CSS — Enregistrer
    // ──────────────────────────────────────────────────────────────────
    $('#btn-save-css').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        var $sp  = $('#spinner-css').addClass('is-active');
        var $m   = $('#msg-css');

        post({
            action:   'alesta_minify_save',
            type:     'css',
            excludes: $('#minify-css-excludes').val()
        }, function (res) {
            $btn.prop('disabled', false);
            msg($m, $sp,
                res.success ? '✅ ' + res.data.message : '❌ ' + (res.data ? res.data.message : 'Erreur.'),
                !!res.success
            );
        }, function () {
            $btn.prop('disabled', false);
            msg($m, $sp, '❌ Erreur réseau.', false);
        });
    });

    // ──────────────────────────────────────────────────────────────────
    // JS — Enregistrer
    // ──────────────────────────────────────────────────────────────────
    $('#btn-save-js').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        var $sp  = $('#spinner-js').addClass('is-active');
        var $m   = $('#msg-js');

        post({
            action:   'alesta_minify_save',
            type:     'js',
            excludes: $('#minify-js-excludes').val()
        }, function (res) {
            $btn.prop('disabled', false);
            msg($m, $sp,
                res.success ? '✅ ' + res.data.message : '❌ ' + (res.data ? res.data.message : 'Erreur.'),
                !!res.success
            );
        }, function () {
            $btn.prop('disabled', false);
            msg($m, $sp, '❌ Erreur réseau.', false);
        });
    });

    // ──────────────────────────────────────────────────────────────────
    // HTML — Enregistrer
    // ──────────────────────────────────────────────────────────────────
    $('#btn-save-html').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        var $sp  = $('#spinner-html').addClass('is-active');
        var $m   = $('#msg-html');

        post({
            action:            'alesta_minify_save',
            type:              'html',
            remove_comments:   $('#html-remove-comments'  ).is(':checked') ? 1 : 0,
            remove_whitespace: $('#html-remove-whitespace').is(':checked') ? 1 : 0
        }, function (res) {
            $btn.prop('disabled', false);
            msg($m, $sp,
                res.success ? '✅ ' + res.data.message : '❌ ' + (res.data ? res.data.message : 'Erreur.'),
                !!res.success
            );
        }, function () {
            $btn.prop('disabled', false);
            msg($m, $sp, '❌ Erreur réseau.', false);
        });
    });

    // ──────────────────────────────────────────────────────────────────
    // Preload — Mode radio + Enregistrer
    // ──────────────────────────────────────────────────────────────────
    $(document).on('change', 'input[name="preload_mode"]', function () {
        $('#preload-manual-section').toggle($(this).val() === 'manual');
    });

    $('#btn-save-preload').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        var $sp  = $('#spinner-preload').addClass('is-active');
        var $m   = $('#msg-preload');

        post({
            action:           'alesta_minify_save',
            type:             'preload',
            preload_mode:     $('input[name="preload_mode"]:checked').val() || 'all',
            preload_handles:  $('#preload-handles' ).val(),
            preload_excludes: $('#preload-excludes').val()
        }, function (res) {
            $btn.prop('disabled', false);
            msg($m, $sp,
                res.success ? '✅ ' + res.data.message : '❌ ' + (res.data ? res.data.message : 'Erreur.'),
                !!res.success
            );
        }, function () {
            $btn.prop('disabled', false);
            msg($m, $sp, '❌ Erreur réseau.', false);
        });
    });

    // Init : les labels "Actif/Inactif" ont besoin de la classe .on si toggle déjà ON au chargement
    $(function () {
        $('.mnf-switch').each(function () {
            var $sw   = $(this);
            var isOn  = $sw.is(':checked');
            var type  = $sw.data('type');
            $sw.siblings('.mnf-slider').toggleClass('on', isOn);
            $('.mnf-status-label[data-type="' + type + '"]').toggleClass('on', isOn);
        });
    });

}(jQuery));
