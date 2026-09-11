/* ── Mode Maintenance Admin — Alesta ── */
(function ($) {
    'use strict';

    var ajaxUrl = AlestaMaint.ajax_url;
    var nonce   = AlestaMaint.nonce;

    /* ══════════════════════════════════════════════════════════
       ONGLETS
    ══════════════════════════════════════════════════════════ */
    $(document).on('click', '.maint-tab', function () {
        var tab = $(this).data('tab');
        $('.maint-tab').removeClass('active');
        $(this).addClass('active');
        $('.maint-tab-panel').removeClass('active');
        $('#tab-' + tab).addClass('active');
    });

    /* ══════════════════════════════════════════════════════════
       TOGGLE MAINTENANCE ON/OFF
    ══════════════════════════════════════════════════════════ */
    $('#maint-enabled').on('change', function () {
        $.post(ajaxUrl, {
            action: 'alesta_maintenance_toggle',
            nonce:  nonce,
        }, function (res) {
            if (!res.success) { showToast('Erreur : ' + (res.data || 'inconnue'), 'error'); return; }
            updateStatus(res.data.enabled);
            showToast(res.data.msg, res.data.enabled ? 'error' : 'success');
        }).fail(function () { showToast('Erreur réseau.', 'error'); });
    });

    function updateStatus(enabled) {
        var $header = $('#maint-header');
        var $label  = $('#maint-status-label');
        var $toggle = $('#maint-toggle-label');
        var $alert  = $('#maint-alert');

        if (enabled) {
            $header.addClass('maint-header--active');
            $label.removeClass('maint-status--off').addClass('maint-status--on').text('🟢 Mode actif');
            $toggle.text('Désactiver');
            $alert.removeClass('hidden');
        } else {
            $header.removeClass('maint-header--active');
            $label.removeClass('maint-status--on').addClass('maint-status--off').text('⚫ Mode inactif');
            $toggle.text('Activer');
            $alert.addClass('hidden');
        }
        $('#maint-enabled').prop('checked', enabled);
    }

    /* ══════════════════════════════════════════════════════════
       ENREGISTREMENT
    ══════════════════════════════════════════════════════════ */
    $('#maint-save-btn').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('Enregistrement…');
        var $msg = $('#maint-save-msg');

        $.post(ajaxUrl, buildData('alesta_maintenance_save'), function (res) {
            if (res.success) {
                $msg.css('color', '#6ee7b7').text('✓ Enregistré');
                showToast('Réglages enregistrés.', 'success');
            } else {
                $msg.css('color', '#f87171').text('⚠ Erreur');
                showToast('Erreur lors de la sauvegarde.', 'error');
            }
            setTimeout(function () { $msg.text(''); }, 3000);
        }).fail(function () {
            $msg.css('color', '#f87171').text('⚠ Erreur réseau');
        }).always(function () {
            $btn.prop('disabled', false).text('💾 Enregistrer');
        });
    });

    function buildData(action) {
        var roles = [];
        $('input[name="allowed_roles[]"]:checked').each(function () { roles.push(this.value); });
        if (roles.indexOf('administrator') === -1) roles.push('administrator'); // toujours inclus

        return {
            action:             action,
            nonce:              nonce,
            enabled:            $('#maint-enabled').is(':checked') ? '1' : '',
            title:              $('#maint-title').val(),
            headline:           $('#maint-headline').val(),
            message:            $('#maint-message').val(),
            contact_email:      $('#maint-contact-email').val(),
            logo_url:           $('#maint-logo-url').val(),
            bg_type:            $('input[name="bg_type"]:checked').val() || 'color',
            bg_color:           $('#maint-bg-color').val(),
            bg_image_url:       $('#maint-bg-image-url').val(),
            text_color:         $('#maint-text-color').val(),
            accent_color:       $('#maint-accent-color').val(),
            countdown_enabled:  $('#maint-countdown-enabled').is(':checked') ? '1' : '',
            countdown_date:     ($('#maint-countdown-date').val() || '').replace('T', ' '),
            social_twitter:     $('#maint-social-twitter').val(),
            social_facebook:    $('#maint-social-facebook').val(),
            social_instagram:   $('#maint-social-instagram').val(),
            social_linkedin:    $('#maint-social-linkedin').val(),
            allowed_ips:        $('#maint-allowed-ips').val(),
            allowed_roles:      roles,
            bypass_param:       $('#maint-bypass-param').val(),
            meta_robots:        $('#maint-meta-robots').val(),
        };
    }

    /* ══════════════════════════════════════════════════════════
       PRÉVISUALISATION (nouvelle fenêtre)
    ══════════════════════════════════════════════════════════ */
    $('#maint-preview-btn').on('click', function () {
        // Ouvre la page d'accueil avec un cookie bypass temporaire
        // (On passe par un paramètre secret si configuré, sinon via l'admin)
        var bypass = $('#maint-bypass-param').val();
        var url    = bypass
            ? (AlestaMaint.preview_url.split('?')[0] + '?' + bypass + '=1')
            : home_url_preview();
        window.open(url, '_blank');
    });

    function home_url_preview() {
        // Construit l'URL de preview depuis AlestaMaint.preview_url
        return AlestaMaint.preview_url || window.location.origin + '/';
    }

    /* ══════════════════════════════════════════════════════════
       PRÉVISUALISATION LIVE (mock)
    ══════════════════════════════════════════════════════════ */
    $('#maint-headline').on('input', function () {
        $('#mock-headline').text($(this).val() || 'Nous revenons bientôt');
    });
    $('#maint-message').on('input', function () {
        var t = $(this).val() || 'Site en maintenance.';
        $('#mock-message').text(t.length > 100 ? t.substring(0, 100) + '…' : t);
    });

    // Fond
    $(document).on('change', 'input[name="bg_type"]', function () {
        $('.maint-bg-opt').removeClass('active');
        $(this).closest('.maint-bg-opt').addClass('active');
        var val = $(this).val();
        if (val === 'color') {
            $('#bg-color-row').removeClass('hidden');
            $('#bg-image-row').addClass('hidden');
            updateMockBg();
        } else {
            $('#bg-color-row').addClass('hidden');
            $('#bg-image-row').removeClass('hidden');
        }
    });

    function updateMockBg() {
        var bgType = $('input[name="bg_type"]:checked').val() || 'color';
        var $mock  = $('#maint-mock');
        if (bgType === 'color') {
            var c = $('#maint-bg-color').val();
            $mock.css({ 'background-color': c, 'background-image': 'none' });
        } else {
            var img = $('#maint-bg-image-url').val();
            if (img) $mock.css({ 'background-image': 'url(' + img + ')', 'background-size': 'cover', 'background-color': '' });
        }
    }

    /* ══════════════════════════════════════════════════════════
       COULEURS
    ══════════════════════════════════════════════════════════ */
    $(document).on('input change', '.maint-color-picker', function () {
        var val = this.value;
        var id  = this.id;
        $('[data-for="' + id + '"]').val(val);
        applyColorPreview(id, val);
    });
    $(document).on('input', '.maint-color-hex', function () {
        var val = this.value.trim();
        var key = $(this).data('for');
        if (/^#[0-9a-fA-F]{6}$/.test(val)) {
            $('#' + key).val(val);
            applyColorPreview(key, val);
        }
    });

    function applyColorPreview(id, val) {
        if (id === 'maint-bg-color') {
            if ($('input[name="bg_type"]:checked').val() === 'color') {
                $('#maint-mock').css('background-color', val);
            }
        }
        if (id === 'maint-text-color') {
            $('#mock-headline, #mock-message').css('color', val);
        }
        if (id === 'maint-accent-color') {
            $('#mock-bar-fill').css('background', val);
        }
    }

    /* ══════════════════════════════════════════════════════════
       PRÉRÉGLAGES
    ══════════════════════════════════════════════════════════ */
    $(document).on('click', '.maint-preset', function () {
        var bg     = $(this).data('bg');
        var text   = $(this).data('text');
        var accent = $(this).data('accent');

        if (bg) {
            $('#maint-bg-color').val(bg);
            $('[data-for="maint-bg-color"]').val(bg);
            $('#maint-mock').css('background-color', bg);
        }
        if (text) {
            $('#maint-text-color').val(text);
            $('[data-for="maint-text-color"]').val(text);
            $('#mock-headline, #mock-message').css('color', text);
        }
        if (accent) {
            $('#maint-accent-color').val(accent);
            $('[data-for="maint-accent-color"]').val(accent);
            $('#mock-bar-fill').css('background', accent);
        }
    });

    /* ══════════════════════════════════════════════════════════
       COMPTE À REBOURS
    ══════════════════════════════════════════════════════════ */
    $('#maint-countdown-enabled').on('change', function () {
        $('#maint-countdown-row').toggle(this.checked);
    });

    /* ══════════════════════════════════════════════════════════
       MÉDIATHÈQUE — Logo
    ══════════════════════════════════════════════════════════ */
    var logoFrame;
    $(document).on('click', '#maint-logo-btn', function (e) {
        e.preventDefault();
        if (logoFrame) { logoFrame.open(); return; }
        logoFrame = wp.media({ title: 'Choisir le logo', button: { text: 'Utiliser' }, multiple: false, library: { type: 'image' } });
        logoFrame.on('select', function () {
            var att = logoFrame.state().get('selection').first().toJSON();
            $('#maint-logo-url').val(att.url);
            $('#maint-logo-preview').html('<img src="' + att.url + '" alt="Logo" style="max-width:100%;max-height:100%;object-fit:contain;">');
            // Preview mock
            if (!$('#maint-mock .mock-logo').length) {
                $('#maint-mock .mock-icon').before('<img class="mock-logo" src="' + att.url + '" style="max-height:28px;max-width:100px;margin-bottom:10px;object-fit:contain;">');
            } else {
                $('#maint-mock .mock-logo').attr('src', att.url);
            }
        });
        logoFrame.open();
    });

    $(document).on('click', '#maint-logo-remove', function () {
        $('#maint-logo-url').val('');
        $('#maint-logo-preview').html('<span>Aucun logo</span>');
        $('#maint-mock .mock-logo').remove();
        $(this).remove();
    });

    /* ══════════════════════════════════════════════════════════
       MÉDIATHÈQUE — Image de fond
    ══════════════════════════════════════════════════════════ */
    var bgFrame;
    $(document).on('click', '#maint-bg-image-btn', function (e) {
        e.preventDefault();
        if (bgFrame) { bgFrame.open(); return; }
        bgFrame = wp.media({ title: 'Image de fond', button: { text: 'Utiliser' }, multiple: false, library: { type: 'image' } });
        bgFrame.on('select', function () {
            var att = bgFrame.state().get('selection').first().toJSON();
            $('#maint-bg-image-url').val(att.url);
            $('#maint-mock').css({ 'background-image': 'url(' + att.url + ')', 'background-size': 'cover' });
        });
        bgFrame.open();
    });

    /* ══════════════════════════════════════════════════════════
       TOAST
    ══════════════════════════════════════════════════════════ */
    function showToast(msg, type) {
        var $t = $('<div class="maint-toast maint-toast--' + (type || 'success') + '">' + esc(msg) + '</div>');
        $('body').append($t);
        setTimeout(function () { $t.addClass('show'); }, 50);
        setTimeout(function () { $t.removeClass('show'); setTimeout(function () { $t.remove(); }, 350); }, 3500);
    }

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* Init preview */
    updateMockBg();

}(jQuery));
