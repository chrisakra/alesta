jQuery(function ($) {

    var state   = {};
    var options = {};

    // =========================================================================
    // INIT
    // =========================================================================
    function loadState() {
        $.post(AlestaSitemap.ajax_url, {
            action: 'alesta_sitemap_read',
            nonce:  AlestaSitemap.nonce,
        }, function (r) {
            if (!r.success) {
                $('#sitemap-status-bar').text('Erreur de lecture').css('color', '#fca5a5');
                return;
            }
            state   = r.data;
            options = r.data.saved_options || {};
            renderState();
            renderOptions();
            renderCounts(r.data.counts);
            renderSettings(r.data);
        });
    }

    // =========================================================================
    // RENDU ETAT GLOBAL
    // =========================================================================
    function renderState() {
        $('#sitemap-global-status').show();
        $('#sitemap-status-bar').text('Sitemap charge').css('color', '#6ee7b7');

        $('#sitemap-file-status').html(
            state.exists
                ? '<span style="color:#065f46;">Fichier present (' + Math.round(state.size / 1024 * 10) / 10 + ' Ko)</span>'
                : '<span style="color:#f59e0b;">Pas encore genere</span>'
        );

        $('#sitemap-gen-date').text(state.last_gen  || 'Jamais');
        $('#sitemap-ping-date').text(state.last_ping || 'Jamais');
        $('#sitemap-url').html('<a href="' + escHtml(state.url) + '" target="_blank" style="font-size:12px;color:#1e3a5f;">' + escHtml(state.url) + '</a>');

        if (state.exists) {
            $('#btn-sitemap-ping').prop('disabled', false);
            $('#btn-sitemap-delete').prop('disabled', false);
        }
        if (!state.can_write) {
            $('#btn-sitemap-generate').prop('disabled', true).attr('title', 'Dossier racine non accessible en ecriture');
        }
        if (state.wp_native) {
            $('#sitemap-wp-native-url').html('<a href="' + escHtml(state.wp_native) + '" target="_blank" style="color:#1e40af;">' + escHtml(state.wp_native) + '</a>');
            $('#sitemap-native-notice').show();
        }
    }

    // =========================================================================
    // RENDU OPTIONS (post types + taxonomies + enrichissements)
    // =========================================================================
    function renderOptions() {
        var avail   = state.available   || {};
        var saved   = state.saved_options || {};

        // --- Post types ---
        var ptHtml = '';
        var ptData = avail.post_types || {};
        $.each(ptData, function (slug, info) {
            var checked = (saved.post_types && saved.post_types.indexOf(slug) !== -1) ? 'checked' : '';
            ptHtml += '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">'
                    + '<input type="checkbox" class="opt-pt" data-slug="' + escHtml(slug) + '" ' + checked + ' style="width:15px;height:15px;">'
                    + '<span style="font-size:13px;color:#374151;">' + escHtml(info.label)
                    + ' <span style="color:#9ca3af;font-size:12px;">(' + info.count + ')</span></span>'
                    + '</label>';
        });
        $('#opt-post-types').html(ptHtml || '<span style="color:#9ca3af;font-size:12px;">Aucun contenu trouve</span>');

        // --- Images ---
        $('#opt-images').prop('checked', saved.include_images !== false);

        // --- Videos ---
        $('#opt-videos').prop('checked', saved.include_videos !== false);

        // --- Taxonomies ---
        var taxChecked = saved.include_taxonomies ? 'checked' : '';
        $('#opt-taxonomies').prop('checked', saved.include_taxonomies);
        toggleTaxPanel(saved.include_taxonomies);

        var taxHtml = '';
        var taxData = avail.taxonomies || {};
        $.each(taxData, function (slug, info) {
            var checked = (saved.taxonomies && saved.taxonomies.indexOf(slug) !== -1) ? 'checked' : '';
            taxHtml += '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">'
                     + '<input type="checkbox" class="opt-tax" data-slug="' + escHtml(slug) + '" ' + checked + ' style="width:13px;height:13px;">'
                     + '<span style="font-size:12px;color:#374151;">' + escHtml(info.label)
                     + ' <span style="color:#9ca3af;">(' + info.count + ')</span></span>'
                     + '</label>';
        });
        $('#opt-tax-checkboxes').html(taxHtml || '<span style="color:#9ca3af;font-size:12px;">Aucune taxonomie disponible</span>');

        // --- Auteurs ---
        $('#opt-authors').prop('checked', saved.include_authors);
        var authorCount = avail.authors || 0;
        $('#opt-authors-count').text('Archives par auteur (' + authorCount + ' auteur' + (authorCount > 1 ? 's' : '') + ')');
    }

    function toggleTaxPanel(show) {
        if (show) {
            $('#opt-taxonomies-list').slideDown(150);
        } else {
            $('#opt-taxonomies-list').slideUp(150);
        }
    }

    // Afficher/masquer le panneau taxonomies
    $('#opt-taxonomies').on('change', function () {
        toggleTaxPanel($(this).is(':checked'));
        updateCountPreview();
    });

    // Mise a jour du compteur preview quand les options changent
    $(document).on('change', '.opt-pt, .opt-tax, #opt-images, #opt-videos, #opt-authors', function () {
        updateCountPreview();
    });

    function updateCountPreview() {
        var avail  = state.available || {};
        var ptData = avail.post_types || {};
        var taxData= avail.taxonomies || {};

        var total = 1; // homepage

        // Post types selectionnes
        $('.opt-pt:checked').each(function () {
            var slug = $(this).data('slug');
            if (ptData[slug]) total += ptData[slug].count;
        });

        // Taxonomies selectionnees
        if ($('#opt-taxonomies').is(':checked')) {
            $('.opt-tax:checked').each(function () {
                var slug = $(this).data('slug');
                if (taxData[slug]) total += taxData[slug].count;
            });
        }

        // Auteurs
        if ($('#opt-authors').is(':checked')) {
            total += avail.authors || 0;
        }

        renderCounts(buildCountsFromUI(ptData, taxData));
    }

    function buildCountsFromUI(ptData, taxData) {
        var counts = { total: 1, terms: 0, authors: 0 };

        $('.opt-pt:checked').each(function () {
            var slug  = $(this).data('slug');
            var count = ptData[slug] ? ptData[slug].count : 0;
            counts[slug] = count;
            counts.total += count;
        });

        if ($('#opt-taxonomies').is(':checked')) {
            $('.opt-tax:checked').each(function () {
                var slug = $(this).data('slug');
                counts.terms += taxData[slug] ? taxData[slug].count : 0;
            });
            counts.total += counts.terms;
        }

        if ($('#opt-authors').is(':checked')) {
            counts.authors = (state.available || {}).authors || 0;
            counts.total  += counts.authors;
        }

        return counts;
    }

    // =========================================================================
    // RENDU COMPTEURS
    // =========================================================================
    function renderCounts(counts) {
        if (!counts) return;

        $('#count-total-badge').text(counts.total || 0);

        var avail  = state.available || {};
        var ptData = avail.post_types || {};
        var html   = '';

        // Homepage
        html += countRow('Accueil', 1, '#1e3a5f');

        // Post types
        $.each(ptData, function (slug, info) {
            var n = counts[slug] !== undefined ? counts[slug] : info.count;
            var color = slug === 'page' ? '#065f46' : (slug === 'product' ? '#78350f' : '#713f12');
            html += countRow(info.label, n, color);
        });

        // Taxonomies
        if (counts.terms > 0) {
            html += countRow('Categories & Tags', counts.terms, '#5b21b6');
        }

        // Auteurs
        if (counts.authors > 0) {
            html += countRow('Auteurs', counts.authors, '#0369a1');
        }

        // Images (indicatif)
        if ($('#opt-images').is(':checked')) {
            html += '<div style="padding:6px 10px;border-radius:6px;background:#f0fdf4;border:1px solid #d1fae5;display:flex;justify-content:space-between;font-size:12px;">'
                  + '<span style="color:#374151;">Images (extension image:)</span>'
                  + '<span style="color:#065f46;font-weight:600;">Actif</span>'
                  + '</div>';
        }

        // Videos (indicatif)
        if ($('#opt-videos').is(':checked')) {
            html += '<div style="padding:6px 10px;border-radius:6px;background:#eff6ff;border:1px solid #bfdbfe;display:flex;justify-content:space-between;font-size:12px;">'
                  + '<span style="color:#374151;">Vidéos (extension video:)</span>'
                  + '<span style="color:#1e40af;font-weight:600;">Actif</span>'
                  + '</div>';
        }

        $('#sitemap-counts-list').html(html || '<span style="color:#9ca3af;">Aucun contenu selectionne</span>');
    }

    function countRow(label, count, color) {
        return '<div style="padding:6px 10px;border-radius:6px;background:#f8fafc;border:1px solid #e5e7eb;display:flex;justify-content:space-between;font-size:12px;">'
             + '<span style="color:#374151;">' + escHtml(label) + '</span>'
             + '<span style="font-weight:700;color:' + color + ';">' + count + '</span>'
             + '</div>';
    }

    // =========================================================================
    // COLLECTE DES OPTIONS DEPUIS L'UI
    // =========================================================================
    function collectOptions() {
        var post_types = [];
        $('.opt-pt:checked').each(function () { post_types.push($(this).data('slug')); });

        var taxonomies = [];
        if ($('#opt-taxonomies').is(':checked')) {
            $('.opt-tax:checked').each(function () { taxonomies.push($(this).data('slug')); });
        }

        return {
            post_types:          post_types,
            include_images:      $('#opt-images').is(':checked'),
            include_videos:      $('#opt-videos').is(':checked'),
            include_taxonomies:  $('#opt-taxonomies').is(':checked'),
            taxonomies:          taxonomies,
            include_authors:     $('#opt-authors').is(':checked'),
        };
    }

    // =========================================================================
    // RENDU PARAMETRES AVANCES
    // =========================================================================
    function renderSettings(data) {
        $('#opt-auto-regen').prop('checked', !!data.auto_regen);
        $('#opt-disable-native').prop('checked', !!data.disable_native);
        if (data.yoast_active) {
            $('#native-yoast-badge').show();
        }
    }

    // =========================================================================
    // SAUVEGARDER LES PARAMETRES
    // =========================================================================
    $('#btn-sitemap-save-settings').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('Enregistrement...');
        $('#settings-feedback').hide();

        $.post(AlestaSitemap.ajax_url, {
            action:         'alesta_sitemap_save_settings',
            nonce:          AlestaSitemap.nonce,
            auto_regen:     $('#opt-auto-regen').is(':checked') ? '1' : '',
            disable_native: $('#opt-disable-native').is(':checked') ? '1' : '',
        }, function (r) {
            $btn.prop('disabled', false).text('Enregistrer les parametres');
            if (r.success) {
                $('#settings-feedback')
                    .css({'color': '#065f46', 'background': '#f0fdf4', 'border': '1px solid #d1fae5', 'padding': '6px 12px', 'border-radius': '6px'})
                    .text('Parametres enregistres.')
                    .show();
                setTimeout(function () { $('#settings-feedback').fadeOut(400); }, 3000);
            } else {
                $('#settings-feedback')
                    .css({'color': '#991b1b'})
                    .text('Erreur lors de l\'enregistrement.')
                    .show();
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Enregistrer les parametres');
        });
    });

    loadState();

    // =========================================================================
    // GENERER
    // =========================================================================
    $('#btn-sitemap-generate').on('click', function () {
        var opts = collectOptions();
        if (!opts.post_types.length && !opts.include_taxonomies && !opts.include_authors) {
            alert('Selectionnez au moins un type de contenu a inclure.');
            return;
        }

        var $btn = $(this).prop('disabled', true).text('Generation en cours...');
        $('#sitemap-feedback').hide();

        $.post(AlestaSitemap.ajax_url, {
            action:  'alesta_sitemap_generate',
            nonce:   AlestaSitemap.nonce,
            options: JSON.stringify(opts),
        }, function (r) {
            $btn.prop('disabled', false).text('Generer le sitemap');
            if (r.success) {
                toast(r.data.message);
                feedback('ok', r.data.message);
                state.exists   = true;
                state.size     = r.data.size;
                state.last_gen = r.data.last_gen;
                renderState();
                renderCounts(r.data.counts);
                $('#btn-sitemap-ping').prop('disabled', false);
                $('#btn-sitemap-delete').prop('disabled', false);
            } else {
                feedback('error', r.data && r.data.message ? r.data.message : 'Erreur inconnue');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Generer le sitemap');
            feedback('error', 'Erreur reseau.');
        });
    });

    // =========================================================================
    // NOTIFIER GOOGLE & BING
    // =========================================================================
    $('#btn-sitemap-ping').on('click', function () {
        var $btn = $(this).prop('disabled', true).html('Envoi...');
        $('#sitemap-ping-result').hide();

        $.post(AlestaSitemap.ajax_url, {
            action: 'alesta_sitemap_ping',
            nonce:  AlestaSitemap.nonce,
        }, function (r) {
            $btn.prop('disabled', false).html('Notifier Google &amp; Bing');
            var $res = $('#sitemap-ping-result').show().css({'background': '#fff', 'border': '1px solid #e5e7eb', 'border-radius': '8px', 'padding': '14px 16px'});

            if (r.success) {
                state.last_ping = r.data.last_ping;
                $('#sitemap-ping-date').text(r.data.last_ping);

                var html = '<div style="font-size:13px;font-weight:600;color:#111827;margin-bottom:10px;">Resultats :</div>';
                html += '<div style="display:flex;flex-direction:column;gap:8px;">';
                $.each(r.data.results, function (i, item) {
                    var isOk   = item.status === 'ok';
                    var isInfo = item.status === 'info';
                    var bg     = isOk ? '#f0fdf4' : (isInfo ? '#eff6ff' : '#fef2f2');
                    var border = isOk ? '#d1fae5' : (isInfo ? '#bfdbfe' : '#fecaca');
                    var color  = isOk ? '#065f46' : (isInfo ? '#1e40af' : '#991b1b');
                    var icon   = isOk ? '&#10003;' : (isInfo ? '&#8594;' : '&#10007;');
                    html += '<div style="display:flex;align-items:center;gap:10px;padding:8px 14px;border-radius:6px;background:' + bg + ';border:1px solid ' + border + ';">';
                    html += '<span style="font-size:15px;color:' + color + ';">' + icon + '</span>';
                    html += '<span style="font-size:13px;font-weight:600;color:' + color + ';min-width:180px;">' + escHtml(item.engine) + '</span>';
                    html += '<span style="font-size:12px;color:#6b7280;">' + escHtml(item.message) + '</span>';
                    if (item.link) {
                        html += '<a href="' + escHtml(item.link) + '" target="_blank" style="margin-left:auto;font-size:12px;color:#1e3a5f;white-space:nowrap;">Ouvrir &#8599;</a>';
                    }
                    html += '</div>';
                });
                html += '</div>';
                html += '<div style="margin-top:10px;font-size:12px;color:#9ca3af;">Les endpoints de ping automatique ont ete supprimes par Google (jan. 2024) et Bing. La soumission via les outils webmaster est desormais la methode recommandee.</div>';
                $res.html(html);
            } else {
                $res.css({'background': '#fef2f2', 'border-color': '#fecaca'});
                $res.html('<span style="color:#991b1b;">' + escHtml(r.data && r.data.message ? r.data.message : 'Erreur inconnue') + '</span>');
            }
        }).fail(function () {
            $btn.prop('disabled', false).html('Notifier Google &amp; Bing');
        });
    });

    // =========================================================================
    // SUPPRIMER
    // =========================================================================
    $('#btn-sitemap-delete').on('click', function () {
        if (!confirm('Supprimer le fichier sitemap.xml ?')) return;
        var $btn = $(this).prop('disabled', true).text('...');

        $.post(AlestaSitemap.ajax_url, {
            action: 'alesta_sitemap_delete',
            nonce:  AlestaSitemap.nonce,
        }, function (r) {
            $btn.prop('disabled', false).text('Supprimer');
            if (r.success) {
                toast(r.data.message);
                state.exists   = false;
                state.size     = 0;
                state.last_gen = '';
                renderState();
                $('#btn-sitemap-ping').prop('disabled', true);
                $('#btn-sitemap-delete').prop('disabled', true);
                $('#sitemap-feedback').hide();
            } else {
                alert(r.data && r.data.message ? r.data.message : 'Erreur');
                $btn.prop('disabled', false);
            }
        });
    });

    // =========================================================================
    // HELPERS
    // =========================================================================
    function feedback(type, msg) {
        var color  = type === 'ok' ? '#065f46' : '#991b1b';
        var bg     = type === 'ok' ? '#f0fdf4' : '#fef2f2';
        var border = type === 'ok' ? '#d1fae5' : '#fecaca';
        $('#sitemap-feedback')
            .css({'background': bg, 'border': '1px solid ' + border, 'color': color})
            .text(msg)
            .show();
    }

    function toast(msg) {
        var $t = $('<div style="position:fixed;bottom:24px;right:24px;background:#065f46;color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,.2);">' + msg + '</div>');
        $('body').append($t);
        setTimeout(function () { $t.fadeOut(400, function () { $t.remove(); }); }, 3500);
    }

    function escHtml(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
});
