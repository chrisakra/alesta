/* Alesta — SEO Meta Box JS (porté depuis Alesta AI Pro seo-meta-box.js). Handle : alesta-seo-mb */
(function ($) {
    'use strict';

    var cfg  = window.AlestaSeoMB || {};
    var i18n = cfg.i18n || {};
    function t(key, fallback) { return i18n[key] || fallback; }

    // =========================================================================
    // ONGLETS
    // =========================================================================

    $(document).on('click', '.aseo-tab', function () {
        var target = $(this).data('target');
        $('.aseo-tab').removeClass('active');
        $(this).addClass('active');
        $('.aseo-pane').removeClass('active');
        $('#' + target).addClass('active');
    });

    // =========================================================================
    // COMPTEURS + APERÇU SERP
    // =========================================================================

    function updateCounter($input, $count, $bar, min, max) {
        var n = $input.val().length;
        $count.text(n);

        var pct = Math.min(n / max, 1) * 100;
        var cls = n === 0 ? '' : (n < min ? 'bar-warn' : (n <= max ? 'bar-good' : 'bar-danger'));
        $bar.css('width', pct + '%').removeClass('bar-good bar-warn bar-danger').addClass(cls);
    }

    function syncSerp() {
        var title = $.trim($('#aseo-seo-title').val()) || $('#title').val() || document.title;
        var desc  = $.trim($('#aseo-meta-desc').val());
        $('#serp-title-preview').text(title || t('no_title', 'Titre SEO non défini'));
        $('#serp-desc-preview').text(desc || t('no_desc', 'Aucune méta description — définissez-en une ci-dessous.'));
    }

    $('#aseo-seo-title').on('input', function () {
        updateCounter($(this), $('#aseo-title-count'), $('#aseo-title-bar'), 30, 60);
        syncSerp();
    }).trigger('input');

    $('#aseo-meta-desc').on('input', function () {
        updateCounter($(this), $('#aseo-desc-count'), $('#aseo-desc-bar'), 120, 160);
        syncSerp();
    }).trigger('input');

    // =========================================================================
    // IMAGE OG — médiathèque WordPress
    // =========================================================================

    var mediaFrame;

    $('#aseo-og-image-btn').on('click', function (e) {
        e.preventDefault();
        if (typeof wp === 'undefined' || !wp.media) return;

        if (mediaFrame) { mediaFrame.open(); return; }

        mediaFrame = wp.media({
            title:    t('choose_image', 'Choisir l\'image Open Graph'),
            button:   { text: t('use_image', 'Utiliser cette image') },
            multiple: false,
            library:  { type: 'image' }
        });

        mediaFrame.on('select', function () {
            var att   = mediaFrame.state().get('selection').first().toJSON();
            var thumb = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
            $('#aseo-og-image-id').val(att.id);
            $('#aseo-og-image-preview').empty().append($('<img>', { src: thumb, alt: '' }));
            $('#aseo-og-image-btn').text(t('change_image', 'Changer l\'image'));
            if (!$('#aseo-og-image-remove').length) {
                $('<button type="button" id="aseo-og-image-remove" class="button button-small"></button>')
                    .text(t('remove', 'Supprimer'))
                    .insertAfter('#aseo-og-image-btn');
            }
        });

        mediaFrame.open();
    });

    $(document).on('click', '#aseo-og-image-remove', function () {
        $('#aseo-og-image-id').val('');
        $('#aseo-og-image-preview').empty();
        $('#aseo-og-image-btn').text(t('pick_image', 'Choisir une image'));
        $(this).remove();
    });

    // =========================================================================
    // GÉNÉRATION IA — panneau de proposition
    // =========================================================================

    function ensureProposalPanel() {
        if ($('#aseo-ai-proposal').length) return;
        var html =
            '<div id="aseo-ai-proposal" style="display:none;margin-top:14px;border:1px solid #bfdbfe;border-radius:8px;background:#eff6ff;padding:16px;">' +
                '<div style="font-size:12px;font-weight:600;color:#1e40af;margin-bottom:12px;display:flex;align-items:center;gap:6px;">' +
                    '<span>🤖 ' + escHtml(t('suggestions', 'Suggestions Claude — cochez ce que vous souhaitez appliquer')) + '</span>' +
                '</div>' +
                '<div style="margin-bottom:10px;">' +
                    '<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">' +
                        '<input type="checkbox" id="aseo-prop-title-chk" checked style="margin-top:3px;flex-shrink:0;">' +
                        '<div style="flex:1;">' +
                            '<div style="font-size:11px;font-weight:600;color:#6b7280;margin-bottom:3px;">' + escHtml(t('prop_title', 'TITRE SEO PROPOSÉ')) + '</div>' +
                            '<div id="aseo-prop-title-text" style="font-size:13px;color:#1e3a5f;background:#fff;border:1px solid #dbeafe;border-radius:4px;padding:6px 10px;line-height:1.4;"></div>' +
                        '</div>' +
                    '</label>' +
                '</div>' +
                '<div style="margin-bottom:10px;">' +
                    '<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">' +
                        '<input type="checkbox" id="aseo-prop-desc-chk" checked style="margin-top:3px;flex-shrink:0;">' +
                        '<div style="flex:1;">' +
                            '<div style="font-size:11px;font-weight:600;color:#6b7280;margin-bottom:3px;">' + escHtml(t('prop_desc', 'MÉTA DESCRIPTION PROPOSÉE')) + '</div>' +
                            '<div id="aseo-prop-desc-text" style="font-size:13px;color:#374151;background:#fff;border:1px solid #dbeafe;border-radius:4px;padding:6px 10px;line-height:1.5;"></div>' +
                        '</div>' +
                    '</label>' +
                '</div>' +
                '<div id="aseo-prop-kw-row" style="display:none;margin-bottom:10px;">' +
                    '<label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">' +
                        '<input type="checkbox" id="aseo-prop-kw-chk" checked style="margin-top:3px;flex-shrink:0;">' +
                        '<div style="flex:1;">' +
                            '<div style="font-size:11px;font-weight:600;color:#6b7280;margin-bottom:3px;">' + escHtml(t('prop_kw', 'MOT-CLÉ PRINCIPAL PROPOSÉ')) + '</div>' +
                            '<div id="aseo-prop-kw-text" style="font-size:13px;color:#374151;background:#fff;border:1px solid #dbeafe;border-radius:4px;padding:6px 10px;"></div>' +
                        '</div>' +
                    '</label>' +
                '</div>' +
                '<div style="display:flex;gap:8px;margin-top:14px;">' +
                    '<button type="button" id="aseo-prop-apply" class="button button-primary" style="font-size:13px;">✅ ' + escHtml(t('apply', 'Appliquer la sélection')) + '</button>' +
                    '<button type="button" id="aseo-prop-dismiss" class="button" style="font-size:13px;">' + escHtml(t('dismiss', 'Ignorer')) + '</button>' +
                '</div>' +
            '</div>';

        $('.aseo-ai-row').after(html);
    }

    function showProposal(data, keywordWasEmpty) {
        ensureProposalPanel();

        $('#aseo-prop-title-text').text(data.title || '');
        $('#aseo-prop-desc-text').text(data.desc   || '');
        $('#aseo-prop-title-chk').prop('checked', true);
        $('#aseo-prop-desc-chk').prop('checked', true);

        if (data.keyword && keywordWasEmpty) {
            $('#aseo-prop-kw-text').text(data.keyword);
            $('#aseo-prop-kw-row').show();
            $('#aseo-prop-kw-chk').prop('checked', true);
        } else {
            $('#aseo-prop-kw-row').hide();
        }

        $('#aseo-ai-proposal').slideDown(200);
    }

    $(document).on('click', '#aseo-prop-apply', function () {
        if ($('#aseo-prop-title-chk').is(':checked')) {
            var tt = $('#aseo-prop-title-text').text();
            if (tt) $('#aseo-seo-title').val(tt).trigger('input');
        }
        if ($('#aseo-prop-desc-chk').is(':checked')) {
            var d = $('#aseo-prop-desc-text').text();
            if (d) $('#aseo-meta-desc').val(d).trigger('input');
        }
        if ($('#aseo-prop-kw-chk').is(':checked') && $('#aseo-prop-kw-row').is(':visible')) {
            var k = $('#aseo-prop-kw-text').text();
            if (k) $('#aseo-focus-kw').val(k);
        }
        $('#aseo-ai-proposal').slideUp(200);
        $('#aseo-ai-msg').addClass('ok').text('✅ ' + t('applied', 'Appliqué !'));
        setTimeout(function () { $('#aseo-ai-msg').text('').removeClass('ok error'); }, 3000);
    });

    $(document).on('click', '#aseo-prop-dismiss', function () {
        $('#aseo-ai-proposal').slideUp(200);
    });

    $('#aseo-btn-ai').on('click', function () {
        var $btn     = $(this).prop('disabled', true);
        var $spinner = $('#aseo-ai-spinner').addClass('is-active');
        var $msg     = $('#aseo-ai-msg').text('').removeClass('ok error');
        var keyword  = $.trim($('#aseo-focus-kw').val());

        $('#aseo-ai-proposal').slideUp(100);

        $.post(cfg.ajax_url, {
            action:  'alesta_seo_mb_generate',
            nonce:   cfg.nonce,
            post_id: cfg.post_id,
            keyword: keyword
        }, function (res) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (res && res.success) {
                showProposal(res.data, !keyword);
            } else {
                $msg.addClass('error').text('❌ ' + (res && res.data && res.data.message ? res.data.message : t('error', 'Erreur.')));
                if (window.AlestaKeyNotice) {
                    window.AlestaKeyNotice.appendTo($msg.parent(), res);
                }
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $msg.addClass('error').text('❌ ' + t('network', 'Erreur réseau.'));
        });
    });

    function escHtml(s) {
        return String(s === undefined || s === null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

}(jQuery));
