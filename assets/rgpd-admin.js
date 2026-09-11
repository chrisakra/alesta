/* ── Bannière RGPD Admin — Alesta AI ── */
(function ($) {
    'use strict';

    var ajaxUrl  = AlestaRgpdAdmin.ajax_url;
    var nonce    = AlestaRgpdAdmin.nonce;
    var preview  = document.getElementById('rgpd-preview-banner');

    /* ══ Onglets ══════════════════════════════════════════════════════════ */
    $(document).on('click', '.rgpd-tab', function () {
        var tab = $(this).data('tab');
        $('.rgpd-tab').removeClass('active');
        $(this).addClass('active');
        $('.rgpd-tab-panel').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });

    /* ══ Toggle Layout ════════════════════════════════════════════════════ */
    $(document).on('change', 'input[name="layout"]', function () {
        $('.rgpd-layout-opt').removeClass('active');
        $(this).closest('.rgpd-layout-opt').addClass('active');
        updatePreviewLayout();
    });

    $(document).on('change', 'input[name="position"]', function () {
        $('.rgpd-pos-opt').removeClass('active');
        $(this).closest('.rgpd-pos-opt').addClass('active');
    });

    /* ══ Toggle ON/OFF label ══════════════════════════════════════════════ */
    $('#rgpd-enabled').on('change', function () {
        $('#rgpd-enabled-label').text(this.checked ? 'Bannière active' : 'Bannière inactive');
    });

    /* ══ Couleurs : color picker ↔ champ hex ═════════════════════════════ */
    $(document).on('input change', '.rgpd-color-picker', function () {
        var val = this.value;
        var key = this.name;
        // Sync hex field
        $('[data-for="' + key + '"]').val(val);
        // Mise à jour prévisualisation
        applyColorVar(key, val);
    });

    $(document).on('input', '.rgpd-color-hex', function () {
        var val = this.value.trim();
        var key = $(this).data('for');
        if (/^#[0-9a-fA-F]{6}$/.test(val)) {
            $('#' + key).val(val);
            applyColorVar(key, val);
        }
    });

    function applyColorVar(key, val) {
        if (!preview) return;
        var varMap = {
            color_bg:             '--rgpd-bg',
            color_text:           '--rgpd-text',
            color_accent:         '--rgpd-accent',
            color_accent_text:    '--rgpd-acc-txt',
            color_secondary:      '--rgpd-sec',
            color_secondary_text: '--rgpd-sec-txt',
            color_border:         '--rgpd-border',
        };
        if (varMap[key]) {
            preview.style.setProperty(varMap[key], val);
        }
    }

    /* ══ Préréglages couleurs ════════════════════════════════════════════ */
    $(document).on('click', '.rgpd-preset', function () {
        var d = $(this).data();
        var map = {
            color_bg:             d.bg,
            color_text:           d.text,
            color_accent:         d.accent,
            color_accent_text:    d.accentText,
            color_secondary:      d.sec,
            color_secondary_text: d.secTxt,
            color_border:         d.border,
        };
        $.each(map, function (key, val) {
            if (!val) return;
            $('#' + key).val(val);
            $('[data-for="' + key + '"]').val(val);
            applyColorVar(key, val);
        });
        // Flash d'animation
        $(this).addClass('rgpd-preset--active');
        setTimeout(function () { $('.rgpd-preset').removeClass('rgpd-preset--active'); }, 500);
    });

    /* ══ Textes → mise à jour prévisualisation ═══════════════════════════ */
    $('#rgpd-title').on('input', function () {
        $('#prev-title').text($(this).val());
    });
    $('#rgpd-description').on('input', function () {
        var t = $(this).val();
        $('#prev-desc').text(t.length > 120 ? t.substring(0, 120) + '…' : t);
    });
    $('#rgpd-btn-accept').on('input', function () { $('#prev-btn-accept').text($(this).val()); });
    $('#rgpd-btn-reject').on('input', function () { $('#prev-btn-reject').text($(this).val()); });
    $('#rgpd-btn-customize').on('input', function () { $('#prev-btn-customize').text($(this).val()); });

    $('#rgpd-show-reject').on('change', function () {
        $('#prev-btn-reject').parent().toggle(this.checked);
    });
    $('#rgpd-show-customize').on('change', function () {
        $('#prev-btn-customize').toggle(this.checked);
    });

    /* ══ Layout prévisualisation ═════════════════════════════════════════ */
    function updatePreviewLayout() {
        var layout = $('input[name="layout"]:checked').val() || 'bar';
        var $p     = $('#rgpd-preview-banner');
        $p.removeClass('alesta-rgpd-preview--bar alesta-rgpd-preview--popup alesta-rgpd-preview--corner');
        $p.addClass('alesta-rgpd-preview--' + layout);
    }

    /* ══ Slider durée ════════════════════════════════════════════════════ */
    $('#rgpd-lifetime').on('input', function () {
        $('#rgpd-lifetime-val').text($(this).val());
    });

    /* ══ Device switcher ═════════════════════════════════════════════════ */
    $(document).on('click', '.rgpd-device-btn', function () {
        $('.rgpd-device-btn').removeClass('active');
        $(this).addClass('active');
        var device = $(this).data('device');
        $('#rgpd-preview-screen').toggleClass('mobile', device === 'mobile');
    });

    /* ══ Sauvegarde ══════════════════════════════════════════════════════ */
    $('#rgpd-btn-save').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('Enregistrement…');
        var $msg = $('#rgpd-save-msg');

        // Collecter tous les champs du formulaire
        var data = {
            action:  'alesta_rgpd_save',
            nonce:   nonce,
            enabled: $('#rgpd-enabled').is(':checked') ? '1' : '',
            layout:  $('input[name="layout"]:checked').val()    || 'bar',
            position:$('input[name="position"]:checked').val()  || 'bottom',
        };

        // Couleurs
        ['color_bg','color_text','color_accent','color_accent_text',
         'color_secondary','color_secondary_text','color_border'].forEach(function (k) {
            data[k] = $('#' + k).val();
        });

        // Textes
        data.title         = $('#rgpd-title').val();
        data.description   = $('#rgpd-description').val();
        data.policy_url    = $('#rgpd-policy-url').val();
        data.policy_label  = $('#rgpd-policy-label').val();
        data.btn_accept    = $('#rgpd-btn-accept').val();
        data.btn_reject    = $('#rgpd-btn-reject').val();
        data.btn_customize = $('#rgpd-btn-customize').val();
        data.btn_save      = $('#rgpd-btn-save-label').val();
        data.show_reject   = $('#rgpd-show-reject').is(':checked') ? '1' : '';
        data.show_customize= $('#rgpd-show-customize').is(':checked') ? '1' : '';

        // Catégories
        $('[name^="cat_"]').each(function () {
            data[this.name] = this.value;
        });

        // Avancé
        data.cookie_lifetime = $('#rgpd-lifetime').val();

        $.post(ajaxUrl, data, function (res) {
            if (res.success) {
                $msg.css('color', '#6ee7b7').text('✓ ' + res.data.msg);
            } else {
                $msg.css('color', '#f87171').text('⚠ Erreur lors de la sauvegarde.');
            }
            setTimeout(function () { $msg.text(''); }, 3500);
        }).fail(function () {
            $msg.css('color', '#f87171').text('⚠ Erreur réseau.');
        }).always(function () {
            $btn.prop('disabled', false).text('Enregistrer');
        });
    });

    /* ══ Init : appliquer les couleurs initiales sur le preview ══════════ */
    (function initPreviewColors() {
        var s = AlestaRgpdAdmin.settings || {};
        var colorKeys = ['color_bg','color_text','color_accent','color_accent_text',
                         'color_secondary','color_secondary_text','color_border'];
        colorKeys.forEach(function (k) {
            if (s[k]) applyColorVar(k, s[k]);
        });
        // Boutons visibilité
        if (!s.show_reject)   $('#prev-btn-reject').parent().hide();
        if (!s.show_customize) $('#prev-btn-customize').hide();
    }());

}(jQuery));
