/* Alesta — Title & Meta JS (porté depuis Alesta AI Pro meta.js). Handle : alesta-meta */
jQuery(function ($) {

    var cfg  = window.AlestaMeta || {};
    var i18n = cfg.i18n || {};
    function t(key, fallback) { return i18n[key] || fallback; }

    // ── Erreurs de configuration IA (clé API manquante) ──────────────────────
    // Ajoute un bouton « Configurer la clé API » sous le message d'erreur,
    // ou propose d'ouvrir la page Configuration quand il n'y a qu'un alert().
    function appendKeyButton(target, res) {
        return !!(window.AlestaKeyNotice && window.AlestaKeyNotice.appendTo(target, res));
    }
    function notifyError(res, message) {
        if (window.AlestaKeyNotice) {
            window.AlestaKeyNotice.notify(res, message);
            return;
        }
        window.alert(message);
    }

    // ── Onglets de la page ────────────────────────────────────────────────────
    function switchPageTab(tab) {
        try { localStorage.setItem('alesta_meta_active_tab', tab); } catch (e) {}
        $('.am-page-tab').removeClass('active');
        $('.am-page-tab[data-tab="' + tab + '"]').addClass('active');
        $('.am-tab-pane').hide();
        $('#am-tab-' + tab).show();
    }
    $('.am-page-tab').on('click', function () {
        switchPageTab($(this).data('tab'));
    });
    var urlTab = new URLSearchParams(window.location.search).get('tab');
    var storedTab = null;
    try { storedTab = localStorage.getItem('alesta_meta_active_tab'); } catch (e) {}
    switchPageTab(urlTab || storedTab || 'meta');

    // ─────────────────────────────────────────────────────────────────────────

    var batchQueue = [];
    var batchDone  = 0;

    // ── Lancer / régénérer le rapport ────────────────────────────────────────
    function runReport() {
        var types = $('.opt-type:checked').map(function () { return this.value; }).get();
        if (!types.length) { alert(t('select_type', 'Sélectionnez au moins un type de contenu.')); return; }

        $('#btn-run-report, #btn-run-report-empty').prop('disabled', true).text(t('analyzing', 'Analyse en cours...'));

        $.post(cfg.ajax_url, {
            action:     'alesta_meta_report',
            nonce:      cfg.nonce,
            post_types: types
        }, function (r) {
            if (r && r.success) {
                location.reload();
            } else {
                notifyError(r, t('error', 'Erreur') + ' : ' + (r && r.data && r.data.message ? r.data.message : t('unknown', 'inconnue')));
                $('#btn-run-report, #btn-run-report-empty').prop('disabled', false).text(t('regenerate', 'Régénérer le rapport'));
            }
        }).fail(function () {
            alert(t('network_error', 'Erreur réseau.'));
            $('#btn-run-report, #btn-run-report-empty').prop('disabled', false).text(t('regenerate', 'Régénérer le rapport'));
        });
    }

    $('#btn-run-report').on('click', function () {
        if (!confirm(t('confirm_report', 'Régénérer le rapport ? (Gratuit — aucun appel Claude)'))) return;
        runReport();
    });
    $('#btn-run-report-empty').on('click', runReport);

    // ── Pagination / filtres ─────────────────────────────────────────────────
    var amPage    = 1;
    var amPerPage = 25;

    function amGetVisible() {
        var statusFilter = $('.am-filter-btn.active').data('filter') || 'all';
        var scoreFilter  = $('#am-filter-score').val() || 'all';
        var search       = ($('#am-search').val() || '').toLowerCase();

        return $('#am-tbody tr.am-row').filter(function () {
            var status = $(this).data('status');
            var score  = parseInt($(this).data('score'), 10) || 0;
            var txt    = $(this).find('.am-page-name a').text().toLowerCase();

            if (statusFilter === 'duplicate') {
                // Les doublons ont le statut global "warning" : attribut dédié.
                if (String($(this).data('duplicate')) !== '1') return false;
            } else if (statusFilter !== 'all' && status !== statusFilter) {
                return false;
            }
            if (scoreFilter === 'lt50'  && score >= 50)                return false;
            if (scoreFilter === '50-80' && (score < 50 || score > 80)) return false;
            if (scoreFilter === 'gt80'  && score <= 80)                return false;
            if (search && txt.indexOf(search) === -1)                  return false;
            return true;
        });
    }

    function amRender() {
        var $all = $('#am-tbody tr.am-row');
        if (!$all.length) return;
        $all.hide();

        var $visible = amGetVisible();
        var total    = $visible.length;
        var pages    = Math.max(1, Math.ceil(total / amPerPage));
        amPage       = Math.max(1, Math.min(amPage, pages));

        var start = (amPage - 1) * amPerPage;
        $visible.slice(start, start + amPerPage).show();

        var info = total === 0 ? t('no_result', 'Aucun résultat') :
            t('showing', 'Affichage %1$s-%2$s sur %3$s')
                .replace('%1$s', start + 1)
                .replace('%2$s', Math.min(start + amPerPage, total))
                .replace('%3$s', total);
        $('#am-pagination-info').text(info);

        var btns = '';
        btns += '<button type="button" class="button am-pg-btn" data-p="' + Math.max(1, amPage - 1) + '"' + (amPage === 1 ? ' disabled' : '') + '>&laquo;</button>';
        for (var p = 1; p <= pages; p++) {
            btns += '<button type="button" class="button am-pg-btn' + (p === amPage ? ' button-primary' : '') + '" data-p="' + p + '">' + p + '</button>';
        }
        btns += '<button type="button" class="button am-pg-btn" data-p="' + Math.min(pages, amPage + 1) + '"' + (amPage === pages ? ' disabled' : '') + '>&raquo;</button>';
        $('#am-pagination-btns').html(btns);
    }

    amRender();

    $(document).on('click', '.am-filter-btn', function () {
        $('.am-filter-btn').removeClass('active');
        $(this).addClass('active');
        amPage = 1;
        amRender();
    });
    $('#am-filter-score').on('change', function () { amPage = 1; amRender(); });
    $('#am-per-page').on('change', function () {
        amPerPage = parseInt($(this).val(), 10) || 25;
        amPage    = 1;
        amRender();
    });
    $('#am-search').on('input', function () { amPage = 1; amRender(); });
    $(document).on('click', '.am-pg-btn', function () {
        amPage = parseInt($(this).data('p'), 10) || 1;
        amRender();
    });

    // ── Select all ───────────────────────────────────────────────────────────
    $('#am-select-all').on('change', function () {
        $('.am-row-check:visible').prop('checked', this.checked);
    });

    // ── Générer (1 page) ─────────────────────────────────────────────────────
    $(document).on('click', '.am-btn-generate', function () {
        var id    = $(this).data('id');
        var title = $(this).data('title');
        openModal(title, id);
        generateOne(id);
    });

    function getOptions() {
        return {
            tone:     $('#opt-tone').val()    || 'professionnel',
            lang:     $('#opt-lang').val()    || 'fr',
            length:   $('#opt-length').val()  || 'standard',
            keyword:  $('#opt-keyword').val() || '',
            add_site: $('#opt-addsite').is(':checked') ? 1 : 0
        };
    }

    function generateOne(post_id) {
        $.post(cfg.ajax_url, $.extend({
            action:  'alesta_meta_gen_one',
            nonce:   cfg.nonce,
            post_id: post_id
        }, getOptions()), function (r) {
            if (!r || !r.success) {
                $('#am-modal-body').html('<p style="color:#ef4444;">' + t('error', 'Erreur') + ' : ' + escHtml(r && r.data && r.data.message ? r.data.message : t('unknown', 'inconnue')) + '</p>');
                appendKeyButton('#am-modal-body', r);
                return;
            }
            renderModalResult(post_id, r.data);
        }).fail(function () {
            $('#am-modal-body').html('<p style="color:#ef4444;">' + t('network_error', 'Erreur réseau.') + '</p>');
        });
    }

    function renderModalResult(post_id, d) {
        var titles  = d.titles  || [];
        var metas   = d.metas   || [];
        var advice  = d.content_advice || d.advice || '';
        var html    = '';

        // Aperçu Google
        html += '<div class="am-google-preview">';
        html += '<div class="am-gp-label">Aperçu Google (title sélectionné)</div>';
        html += '<div class="am-gp-box">';
        html += '<div class="am-gp-url">&#127760; ' + escHtml(window.location.hostname) + ' &rsaquo; ...</div>';
        html += '<div class="am-gp-title" id="am-gp-title">' + escHtml(titles[0] || '') + '</div>';
        html += '<div class="am-gp-desc" id="am-gp-desc">' + escHtml(metas[0] || '') + '</div>';
        html += '</div></div>';

        // Titles
        html += '<div class="am-suggestions-section"><div class="am-sug-label">Titres suggérés</div>';
        titles.forEach(function (tt, i) {
            var len = String(tt).length;
            var lc  = len <= 60 ? '#065f46' : '#991b1b';
            html += '<div class="am-sug-row' + (i === 0 ? ' am-sug-selected' : '') + '" data-field="title" data-val="' + escAttr(tt) + '">';
            html += '<span class="am-sug-text">' + escHtml(tt) + '</span>';
            html += '<span class="am-sug-len" style="color:' + lc + ';">' + len + ' car.</span>';
            html += '</div>';
        });
        html += '</div>';

        // Metas
        html += '<div class="am-suggestions-section"><div class="am-sug-label">Meta descriptions suggérées</div>';
        metas.forEach(function (m, i) {
            var len = String(m).length;
            var lc  = (len >= 120 && len <= 160) ? '#065f46' : '#991b1b';
            html += '<div class="am-sug-row' + (i === 0 ? ' am-sug-selected' : '') + '" data-field="meta" data-val="' + escAttr(m) + '">';
            html += '<span class="am-sug-text">' + escHtml(m) + '</span>';
            html += '<span class="am-sug-len" style="color:' + lc + ';">' + len + ' car.</span>';
            html += '</div>';
        });
        html += '</div>';

        // Mots-clés cliquables
        var all_kws = [];
        if (d.keyword_main) all_kws.push(d.keyword_main);
        (d.keywords_secondary || []).forEach(function (k) { if (k && all_kws.indexOf(k) === -1) all_kws.push(k); });

        if (all_kws.length) {
            html += '<div class="am-suggestions-section">';
            html += '<div class="am-sug-label">Mots-clés — cliquez pour sélectionner le focus keyphrase</div>';
            html += '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;">';
            all_kws.forEach(function (k, i) {
                html += '<span class="am-kw-chip' + (i === 0 ? ' am-kw-selected' : '') + '" data-kw="' + escAttr(k) + '">' + escHtml(k) + '</span>';
            });
            html += '</div>';
            html += '<div style="margin-top:6px;font-size:11px;color:#9ca3af;">Focus keyphrase sélectionné : <strong id="am-kw-preview">' + escHtml(all_kws[0] || '') + '</strong></div>';
            html += '</div>';
        }

        html += '<input type="hidden" id="am-keyword-selected" value="' + escAttr(d.keyword_main || '') + '">';
        html += '<input type="hidden" id="am-keywords-extra-selected" value="' + escAttr((d.keywords_secondary || []).join(', ')) + '">';

        if (advice) {
            html += '<div style="padding:10px 12px;background:#fffbeb;border-radius:6px;font-size:12px;color:#713f12;margin-top:4px;">&#128161; ' + escHtml(advice) + '</div>';
        }
        html += '<div style="margin-top:1rem;display:flex;gap:8px;">';
        html += '<button type="button" class="button button-primary am-btn-apply-selected" data-id="' + parseInt(post_id, 10) + '">Appliquer les balises SEO</button>';
        html += '<button type="button" class="button am-modal-dismiss">Fermer</button>';
        html += '</div>';

        $('#am-modal-body').html(html);
    }

    // Sélection d'une suggestion
    $(document).on('click', '.am-sug-row', function () {
        var $el   = $(this);
        var field = $el.data('field');
        var val   = $el.data('val');
        $el.siblings('.am-sug-row[data-field="' + field + '"]').removeClass('am-sug-selected');
        $el.addClass('am-sug-selected');
        if (field === 'title') $('#am-gp-title').text(val);
        if (field === 'meta')  $('#am-gp-desc').text(val);
    });

    // Sélection d'un mot-clé
    $(document).on('click', '.am-kw-chip', function () {
        $('.am-kw-chip').removeClass('am-kw-selected');
        $(this).addClass('am-kw-selected');
        var kw = $(this).data('kw');
        $('#am-keyword-selected').val(kw);
        $('#am-kw-preview').text(kw);
    });

    // Appliquer la sélection
    $(document).on('click', '.am-btn-apply-selected', function () {
        var post_id        = $(this).data('id');
        var title          = $('.am-sug-row.am-sug-selected[data-field="title"]').data('val') || '';
        var meta           = $('.am-sug-row.am-sug-selected[data-field="meta"]').data('val')  || '';
        var keyword        = $('#am-keyword-selected').val() || '';
        var keywords_extra = $('#am-keywords-extra-selected').val() || '';
        if (!title || !meta) { alert(t('select_both', 'Sélectionnez un title et une meta.')); return; }
        var $btn = $(this).prop('disabled', true);

        $.post(cfg.ajax_url, {
            action:         'alesta_meta_apply_fields',
            nonce:          cfg.nonce,
            post_id:        post_id,
            title:          title,
            meta:           meta,
            keyword:        keyword,
            keywords_extra: keywords_extra,
            og_title:       title,
            og_desc:        meta,
            twitter_title:  title,
            twitter_desc:   meta,
            robots:         'index,follow',
            canonical:      ''
        }, function (r) {
            $btn.prop('disabled', false);
            if (r && r.success) {
                closeModal();
                var $row = $('tr.am-row[data-id="' + post_id + '"]');
                $row.find('.am-inline-title').val(title).trigger('input');
                $row.find('.am-inline-meta').val(meta).trigger('input');
                if (keyword) $row.find('.am-inline-keyword').val(keyword);
                if (!$row.find('.am-applied-badge').length) {
                    $row.find('.am-page-name').append('<span class="am-applied-badge">' + escHtml(t('modified', 'Modifié')) + '</span>');
                }
                var target = (r.data && r.data.target) ? r.data.target : 'Alesta';
                toast('&#10003; ' + escHtml(t('saved_in', 'Enregistré dans %s').replace('%s', target)));
            } else {
                notifyError(r, r && r.data && r.data.message ? r.data.message : t('error', 'Erreur'));
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            alert(t('network_error', 'Erreur réseau.'));
        });
    });

    // ── Génération en lot ────────────────────────────────────────────────────
    $('#btn-batch-missing').on('click', function () {
        var missing = [];
        $('#am-tbody tr.am-row[data-status="error"]').each(function () {
            missing.push($(this).data('id'));
        });
        if (!missing.length) { alert(t('no_missing', 'Aucune page avec erreur critique.')); return; }
        if (!confirm(t('confirm_batch', 'Générer et appliquer automatiquement le title + meta pour %s page(s) ?').replace('%s', missing.length))) return;
        startBatch(missing);
    });

    function startBatch(ids) {
        batchQueue = ids.slice();
        batchDone  = 0;
        $('#am-batch-progress').show();
        updateBatchUI();
        processNextBatch();
    }

    function processNextBatch() {
        if (!batchQueue.length) {
            setTimeout(function () { location.reload(); }, 1200);
            return;
        }
        var id = batchQueue.shift();
        $.post(cfg.ajax_url, $.extend({
            action:  'alesta_meta_gen_batch',
            nonce:   cfg.nonce,
            post_id: id
        }, getOptions()), function () {
            batchDone++;
            updateBatchUI();
            setTimeout(processNextBatch, 400);
        }).fail(function () {
            batchDone++;
            updateBatchUI();
            setTimeout(processNextBatch, 500);
        });
    }

    function updateBatchUI() {
        var total = batchDone + batchQueue.length;
        var pct   = total ? Math.round((batchDone / total) * 100) : 0;
        $('#am-batch-fill').css('width', pct + '%');
        $('#am-batch-count').text(batchDone + ' / ' + total);
    }

    // ── Compteurs de caractères inline ───────────────────────────────────────
    $(document).on('input', '.am-inline-title', function () {
        var len = $(this).val().length;
        $(this).closest('.am-inline-field').find('.am-title-count').text(len + ' car.').css('color',
            len >= 30 && len <= 60 ? '#065f46' : (len === 0 ? '#9ca3af' : '#991b1b')
        );
    });

    $(document).on('input', '.am-inline-meta', function () {
        var len = $(this).val().length;
        $(this).closest('.am-inline-field').find('.am-meta-count').text(len + ' car.').css('color',
            len >= 120 && len <= 160 ? '#065f46' : (len === 0 ? '#9ca3af' : '#991b1b')
        );
    });

    // ── Sauvegarde manuelle ──────────────────────────────────────────────────
    $(document).on('click', '.am-btn-save-manual', function () {
        var $btn    = $(this);
        var post_id = $btn.data('id');
        var $row    = $btn.closest('tr');
        var title   = $.trim($row.find('.am-inline-title').val());
        var meta    = $.trim($row.find('.am-inline-meta').val());
        var keyword = $.trim($row.find('.am-inline-keyword').val());

        if (!title && !meta) { alert(t('fill_fields', 'Saisissez un title ou une meta avant de sauvegarder.')); return; }

        $btn.text('...').prop('disabled', true);

        $.post(cfg.ajax_url, {
            action:  'alesta_meta_manual_save',
            nonce:   cfg.nonce,
            post_id: post_id,
            title:   title,
            meta:    meta,
            keyword: keyword
        }, function (r) {
            if (r && r.success) {
                $btn.text(t('saved', 'Sauvegardé !')).css({ 'background': '#065f46', 'border-color': '#065f46' });
                if (!$row.find('.am-applied-badge').length) {
                    $row.find('.am-page-name').append('<span class="am-applied-badge">' + escHtml(t('modified', 'Modifié')) + '</span>');
                }
                setTimeout(function () {
                    $btn.text(t('save', 'Sauvegarder')).css({ 'background': '#1e3a5f', 'border-color': '#1e3a5f' }).prop('disabled', false);
                }, 2000);
            } else {
                notifyError(r, r && r.data && r.data.message ? r.data.message : t('error', 'Erreur'));
                $btn.text(t('save', 'Sauvegarder')).prop('disabled', false);
            }
        }).fail(function () {
            alert(t('network_error', 'Erreur réseau.'));
            $btn.text(t('save', 'Sauvegarder')).prop('disabled', false);
        });
    });

    // ── Annuler (revert) ─────────────────────────────────────────────────────
    $(document).on('click', '.am-btn-revert', function () {
        var id = $(this).data('id');
        if (!confirm(t('confirm_revert', 'Restaurer les anciennes valeurs pour cette page ?'))) return;
        $.post(cfg.ajax_url, { action: 'alesta_meta_revert_post', nonce: cfg.nonce, post_id: id }, function (r) {
            if (r && r.success) location.reload();
            else alert(r && r.data && r.data.message ? r.data.message : t('no_history', 'Aucun historique trouvé.'));
        });
    });

    // ── Modal ────────────────────────────────────────────────────────────────
    function openModal(title, id) {
        $('#am-modal-title').text(t('generating_for', 'Génération pour : ') + title);
        $('#am-modal-sub').text('ID ' + id + ' — ' + t('claude_analyzes', 'Claude analyse le contenu...'));
        $('#am-modal-body').html('<div class="am-loader">' + escHtml(t('claude_analyzes', 'Claude analyse le contenu...')) + '</div>');
        $('#am-modal').css('display', 'flex');
    }
    function closeModal() { $('#am-modal').css('display', 'none'); }
    $(document).on('click', '#am-modal-close, .am-modal-dismiss', closeModal);
    $('#am-modal').on('click', function (e) { if (e.target === this) closeModal(); });

    function toast(html) {
        $('#alesta-meta-toast').remove();
        $('body').append('<div id="alesta-meta-toast" style="position:fixed;bottom:24px;right:24px;background:#065f46;color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;z-index:99999;">' + html + '</div>');
        setTimeout(function () { $('#alesta-meta-toast').remove(); }, 2500);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    function escHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function escAttr(s) { return escHtml(s).replace(/'/g, '&#39;'); }
});
