/* Alesta — Audit SEO JS (porté depuis Alesta AI Pro audit.js). Handle : alesta-audit */
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

    var RUN_LABEL = '🔍 ' + t('run_audit', 'Lancer l\'audit');
    var GEN_LABEL = '✨ ' + t('generate', 'Générer');

    // État courant de l'audit (rafraîchir une ligne sans relancer tout le scan)
    var currentAuditData = null;

    // ── Chargement du dernier audit persisté ─────────────────────────────────
    if ($('#btn-run-audit').length) {
        loadLastAudit();
    }

    function loadLastAudit() {
        $.post(cfg.ajax_url, {
            action: 'alesta_audit_last',
            nonce:  cfg.nonce
        }, function (r) {
            if (!r || !r.success || !r.data || !r.data.results) return;
            var last = r.data;

            if (Array.isArray(last.post_types)) {
                $('.audit-type').each(function () {
                    $(this).prop('checked', last.post_types.indexOf(this.value) !== -1);
                });
            }
            if (Array.isArray(last.checks)) {
                $('.audit-check').each(function () {
                    $(this).prop('checked', last.checks.indexOf(this.value) !== -1);
                });
            }

            renderResults(last.results);
            if (last.timestamp) {
                var d = new Date(String(last.timestamp).replace(' ', 'T'));
                var dateStr = isNaN(d.getTime())
                    ? last.timestamp
                    : d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                $('#last-audit-info').text(t('last_audit', 'Dernier audit : ') + dateStr).show();
            }
        });
    }

    // ── Lancer l'audit ────────────────────────────────────────────────────────
    $('#btn-run-audit').on('click', function () {
        var types  = $('.audit-type:checked').map(function () { return this.value; }).get();
        var checks = $('.audit-check:checked').map(function () { return this.value; }).get();
        if (!types.length)  { alert(t('select_type', 'Sélectionnez au moins un type de contenu.')); return; }
        if (!checks.length) { alert(t('select_check', 'Sélectionnez au moins une vérification.')); return; }

        $('#btn-run-audit').prop('disabled', true).text(t('analyzing', 'Analyse en cours...'));
        $('#audit-status').show();
        $('#audit-results').hide();

        $.post(cfg.ajax_url, {
            action:     'alesta_audit_run',
            nonce:      cfg.nonce,
            post_types: types,
            checks:     checks
        }, function (r) {
            $('#btn-run-audit').prop('disabled', false).text(RUN_LABEL);
            $('#audit-status').hide();
            if (!r || !r.success) { notifyError(r, t('error', 'Erreur') + ' : ' + (r && r.data && r.data.message ? r.data.message : t('unknown', 'inconnue'))); return; }
            renderResults(r.data);
            var now = new Date();
            $('#last-audit-info').text(
                t('last_audit', 'Dernier audit : ') + now.toLocaleDateString() + ' ' +
                now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            ).show();
        }).fail(function () {
            $('#btn-run-audit').prop('disabled', false).text(RUN_LABEL);
            $('#audit-status').hide();
            alert(t('network_error', 'Erreur réseau.'));
        });
    });

    // ── Rendu des résultats ───────────────────────────────────────────────────
    function renderResults(data) {
        currentAuditData = data;
        $('#audit-results').show();
        renderSummary(data.summary || {}, data.total_posts || 0);
        renderSEO(data.seo || []);
        renderImages(data.images || []);
        renderContent(data.content || []);
        renderLinks(data.links || []);
        renderPerformance(data.performance || []);
    }

    // Même logique que Alesta_Audit::build_summary() côté PHP.
    function recomputeSummary() {
        if (!currentAuditData) return;
        var s   = currentAuditData.summary || {};
        var seo = currentAuditData.seo || [];

        if (seo.length) {
            var sum = 0;
            seo.forEach(function (it) { sum += (it.score || 0); });
            s.global_score  = Math.round(sum / seo.length);
            s.avg_seo_score = s.global_score;
        } else {
            s.global_score  = 0;
            s.avg_seo_score = 0;
        }

        var errors = 0, warnings = 0;
        ['seo', 'content', 'performance', 'links'].forEach(function (k) {
            (currentAuditData[k] || []).forEach(function (it) {
                (it.issues || []).forEach(function (i) {
                    if (i.type === 'error') errors++;
                    else if (i.type === 'warning') warnings++;
                });
            });
        });
        s.errors   = errors;
        s.warnings = warnings;

        var missingAlt = 0;
        (currentAuditData.images || []).forEach(function (it) { missingAlt += (it.missing_alt || 0); });
        s.missing_alt = missingAlt;

        var broken = 0;
        (currentAuditData.links || []).forEach(function (it) { broken += ((it.broken || []).length); });
        s.broken_links = broken;

        currentAuditData.summary = s;
        renderSummary(s, currentAuditData.total_posts);
    }

    function renderSummary(s, total) {
        var score = s.global_score || 0;
        var scoreColor = score >= 80 ? '#22c55e' : score >= 50 ? '#f59e0b' : '#ef4444';
        $('#audit-summary').html(
            metric(score + '/100', 'Score global', scoreColor) +
            metric(total, 'Pages analysées', '') +
            metric(s.errors || 0, 'Erreurs', (s.errors || 0) > 0 ? '#ef4444' : '#22c55e') +
            metric(s.warnings || 0, 'Avertissements', (s.warnings || 0) > 0 ? '#f59e0b' : '#22c55e') +
            metric(s.broken_links || 0, 'Liens cassés', (s.broken_links || 0) > 0 ? '#ef4444' : '#22c55e') +
            metric(s.missing_alt || 0, 'Alt manquants', (s.missing_alt || 0) > 0 ? '#f59e0b' : '#22c55e')
        );
    }

    function metric(val, label, color) {
        var style = color ? 'color:' + color + ';' : '';
        return '<div class="alesta-stat" style="background:#f9fafb;border:1px solid #e5e7eb;">' +
               '<span style="font-size:24px;font-weight:600;' + style + '">' + escHtml(val) + '</span>' +
               '<small>' + label + '</small></div>';
    }

    // ── SEO ───────────────────────────────────────────────────────────────────
    function renderSEO(items) {
        if (!items.length) { $('#tab-seo').html(emptyState('Aucune page analysée pour le SEO.')); return; }

        var sorted = items.slice().sort(function (a, b) { return a.score - b.score; });
        var html = '<table class="widefat striped" style="margin-top:1rem;"><thead><tr>' +
            '<th>Page</th><th style="width:90px;">Score</th><th>Problèmes</th><th>Actions</th>' +
            '</tr></thead><tbody>';

        sorted.forEach(function (item) {
            var scoreColor = item.score >= 80 ? '#22c55e' : item.score >= 50 ? '#f59e0b' : '#ef4444';
            var issues = (item.issues || []).map(function (i) {
                return '<span class="audit-issue audit-' + escAttr(i.type) + '">' + escHtml(i.msg) + '</span>';
            }).join('');
            if (!issues) issues = '<span class="audit-issue audit-ok">✓ OK</span>';

            html += '<tr>' +
                '<td><a href="' + escAttr(item.url) + '" target="_blank" rel="noopener"><strong>' + escHtml(item.title) + '</strong></a>' +
                '<br><small style="color:#9ca3af;">' + escHtml(item.type) + ' · ' + escHtml(item.url) + '</small></td>' +
                '<td><strong style="font-size:18px;color:' + scoreColor + ';">' + parseInt(item.score, 10) + '</strong><span style="color:#9ca3af;">/100</span></td>' +
                '<td>' + issues + '</td>' +
                '<td><button type="button" class="button btn-generate-seo" data-id="' + parseInt(item.post_id, 10) + '" data-title="' + escAttr(item.title) + '">' + GEN_LABEL + '</button></td>' +
                '</tr>';
        });
        html += '</tbody></table>';
        $('#tab-seo').html(html);
    }

    // ── Images : liste des pages avec images sans alt ─────────────────────────
    function renderImages(items) {
        var problems = items.filter(function (i) { return i.missing_alt > 0; });

        if (!problems.length) {
            $('#tab-images').html(emptyState('✓ Toutes les images ont un attribut alt.'));
            return;
        }

        var totalMissing = 0;
        problems.forEach(function (p) { totalMissing += (p.missing_alt || 0); });

        var html = '<p style="margin:1rem 0;color:#6b7280;">' + totalMissing + ' image(s) sans texte alternatif sur ' + problems.length + ' page(s). ' +
            'Ajoutez un attribut alt descriptif depuis l\'éditeur de chaque page.</p>' +
            '<table class="widefat striped"><thead><tr><th>Page</th><th style="width:90px;">Images</th><th style="width:110px;">Sans alt</th><th>Fichiers (max 5)</th></tr></thead><tbody>';

        problems.forEach(function (item) {
            var color = item.status === 'error' ? '#ef4444' : '#f59e0b';
            var srcs  = (item.missing_srcs || []).map(function (s) {
                var name = String(s).split('/').pop().split('?')[0];
                return '<code style="font-size:11px;">' + escHtml(name) + '</code>';
            }).join('<br>');
            html += '<tr>' +
                '<td><a href="' + escAttr(item.url) + '" target="_blank" rel="noopener">' + escHtml(item.title) + '</a></td>' +
                '<td>' + parseInt(item.total_images, 10) + '</td>' +
                '<td><strong style="color:' + color + ';">' + parseInt(item.missing_alt, 10) + '</strong></td>' +
                '<td style="word-break:break-all;">' + srcs + '</td></tr>';
        });
        html += '</tbody></table>';
        $('#tab-images').html(html);
    }

    // ── Contenu ───────────────────────────────────────────────────────────────
    function renderContent(items) {
        var problems = items.filter(function (i) { return i.status !== 'ok'; });
        if (!problems.length) { $('#tab-content').html(emptyState('✓ Toutes les pages ont un contenu suffisant.')); return; }

        var html = '<p style="margin:1rem 0;color:#6b7280;">' + problems.length + ' page(s) avec un contenu insuffisant.</p>' +
            '<table class="widefat striped"><thead><tr><th>Page</th><th>Mots</th><th>Statut</th><th>Détail</th></tr></thead><tbody>';

        problems.forEach(function (item) {
            var color  = item.status === 'error' ? '#ef4444' : '#f59e0b';
            var issues = (item.issues || []).map(function (i) { return escHtml(i.msg); }).join('<br>');
            html += '<tr>' +
                '<td><a href="' + escAttr(item.url) + '" target="_blank" rel="noopener">' + escHtml(item.title) + '</a></td>' +
                '<td><strong style="color:' + color + ';">' + parseInt(item.word_count, 10) + '</strong></td>' +
                '<td><span style="color:' + color + ';">' + (item.status === 'error' ? 'Erreur' : 'Avertissement') + '</span></td>' +
                '<td style="font-size:12px;">' + issues + '</td></tr>';
        });
        html += '</tbody></table>';
        $('#tab-content').html(html);
    }

    // ── Liens cassés ──────────────────────────────────────────────────────────
    function renderLinks(items) {
        var problems = items.filter(function (i) { return i.broken && i.broken.length > 0; });
        if (!problems.length) { $('#tab-links').html(emptyState('✓ Aucun lien cassé détecté.')); return; }

        var html = '<p style="margin:1rem 0;color:#6b7280;">' + problems.length + ' page(s) avec des liens cassés.</p>' +
            '<table class="widefat striped"><thead><tr><th>Page</th><th>Lien cassé</th><th>Code HTTP</th><th>Raison</th></tr></thead><tbody>';

        problems.forEach(function (item) {
            item.broken.forEach(function (b) {
                html += '<tr>' +
                    '<td><a href="' + escAttr(item.url) + '" target="_blank" rel="noopener">' + escHtml(item.title) + '</a></td>' +
                    '<td style="font-size:11px;word-break:break-all;"><code>' + escHtml(b.url) + '</code></td>' +
                    '<td><strong style="color:#ef4444;">' + (b.code ? parseInt(b.code, 10) : '—') + '</strong></td>' +
                    '<td style="font-size:12px;">' + escHtml(b.reason) + '</td></tr>';
            });
        });
        html += '</tbody></table>';
        $('#tab-links').html(html);
    }

    // ── Performance ───────────────────────────────────────────────────────────
    function renderPerformance(items) {
        if (!items.length) { $('#tab-performance').html(emptyState('✓ Aucun problème de performance détecté.')); return; }

        var html = '<table class="widefat striped" style="margin-top:1rem;"><thead><tr><th>Page</th><th>Problèmes</th></tr></thead><tbody>';
        items.forEach(function (item) {
            var issues = (item.issues || []).map(function (i) {
                return '<span class="audit-issue audit-' + escAttr(i.type) + '">' + escHtml(i.msg) + '</span>';
            }).join('');
            html += '<tr><td><a href="' + escAttr(item.url) + '" target="_blank" rel="noopener">' + escHtml(item.title) + '</a></td>' +
                '<td>' + issues + '</td></tr>';
        });
        html += '</tbody></table>';
        $('#tab-performance').html(html);
    }

    // ── Modal génération SEO Claude ───────────────────────────────────────────
    var currentSelection = { postId: 0, title: '', meta: '', keyword: '' };

    $(document).on('click', '.btn-generate-seo', function () {
        var id    = $(this).data('id');
        var title = $(this).data('title');
        var $btn  = $(this).prop('disabled', true).text(t('loading', 'Chargement…'));

        $('#modal-title').text('Suggestions Claude pour : ' + title);
        $('#modal-content').html('<p style="color:#6b7280;">' + escHtml(t('claude_analyzes', 'Claude analyse le contenu...')) + '</p>');
        $('#seo-modal').css('display', 'flex');

        $.post(cfg.ajax_url, {
            action:  'alesta_audit_generate',
            nonce:   cfg.nonce,
            post_id: id
        }, function (r) {
            $btn.prop('disabled', false).text(GEN_LABEL);
            if (!r || !r.success) {
                $('#modal-content').html('<p style="color:#ef4444;">' + escHtml(r && r.data && r.data.message ? r.data.message : t('error', 'Erreur')) + '</p>');
                appendKeyButton('#modal-content', r);
                return;
            }
            renderSuggestionsModal(id, r.data);
        }).fail(function () {
            $btn.prop('disabled', false).text(GEN_LABEL);
            $('#modal-content').html('<p style="color:#ef4444;">' + escHtml(t('network_error', 'Erreur réseau.')) + '</p>');
        });
    });

    function renderSuggestionsModal(id, d) {
        var metas = [];
        if (Array.isArray(d.meta_suggestions) && d.meta_suggestions.length) {
            metas = d.meta_suggestions.slice(0, 3);
        } else if (d.meta_description) {
            metas = [d.meta_description];
        }

        var titles         = Array.isArray(d.title_suggestions) ? d.title_suggestions.slice(0, 3) : [];
        var keywordMain    = d.keyword_main || '';
        var keywordsSecond = Array.isArray(d.keywords_secondary) ? d.keywords_secondary : [];

        currentSelection = { postId: id, title: titles[0] || '', meta: metas[0] || '', keyword: keywordMain };

        var selLabel   = '✓ ' + t('selected', 'Sélectionné');
        var applyLabel = t('apply', 'Appliquer');

        var html = '<div style="display:flex;flex-direction:column;gap:16px;">';

        // Titres
        html += '<div><div style="font-size:11px;color:#6b7280;margin-bottom:6px;font-weight:500;">TITRES SUGGÉRÉS</div>';
        titles.forEach(function (tt, i) {
            var isSel = (i === 0);
            html += '<div class="alesta-suggestion-row" data-field="title" data-value="' + escAttr(tt) + '" ' +
                'style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;' +
                'background:' + (isSel ? '#ecfdf5' : '#f9fafb') + ';' +
                'border:1px solid ' + (isSel ? '#6ee7b7' : '#e5e7eb') + ';border-radius:6px;margin-bottom:4px;gap:10px;">' +
                '<span style="font-size:13px;flex:1;">' + escHtml(tt) + '</span>' +
                '<button type="button" class="button btn-apply-seo" data-id="' + parseInt(id, 10) + '" data-field="title" data-value="' + escAttr(tt) + '">' +
                (isSel ? selLabel : applyLabel) + '</button>' +
                '</div>';
        });
        html += '</div>';

        // Metas
        html += '<div><div style="font-size:11px;color:#6b7280;margin-bottom:6px;font-weight:500;">META DESCRIPTION (3 variantes)</div>';
        metas.forEach(function (m, i) {
            var isSel    = (i === 0);
            var len      = String(m || '').length;
            var lenColor = (len >= 120 && len <= 160) ? '#22c55e' : (len < 120 ? '#f59e0b' : '#ef4444');
            html += '<div class="alesta-suggestion-row" data-field="meta" data-value="' + escAttr(m) + '" ' +
                'style="padding:10px;background:' + (isSel ? '#ecfdf5' : '#f9fafb') + ';' +
                'border:1px solid ' + (isSel ? '#6ee7b7' : '#e5e7eb') + ';border-radius:6px;margin-bottom:6px;">' +
                '<p style="font-size:13px;margin:0 0 8px;">' + escHtml(m) + '</p>' +
                '<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">' +
                '<small style="color:' + lenColor + ';">' + len + ' caractères' +
                    (len < 120 ? ' (trop court)' : len > 160 ? ' (trop long)' : ' ✓') + '</small>' +
                '<button type="button" class="button btn-apply-seo" data-id="' + parseInt(id, 10) + '" data-field="meta" data-value="' + escAttr(m) + '">' +
                (isSel ? selLabel : applyLabel) + '</button>' +
                '</div></div>';
        });
        if (!metas.length) {
            html += '<p style="font-size:12px;color:#9ca3af;">Aucune suggestion de meta reçue.</p>';
        }
        html += '</div>';

        // Mots-clés
        var allKeywords = [];
        if (keywordMain) allKeywords.push(keywordMain);
        keywordsSecond.forEach(function (k) { if (k && allKeywords.indexOf(k) === -1) allKeywords.push(k); });

        if (allKeywords.length) {
            html += '<div><div style="font-size:11px;color:#6b7280;margin-bottom:6px;font-weight:500;">MOT-CLÉ PRINCIPAL (cliquez pour choisir)</div>' +
                '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
            allKeywords.forEach(function (k) {
                var isSel = (k === keywordMain);
                html += '<span class="alesta-keyword-chip" data-value="' + escAttr(k) + '" ' +
                    'style="cursor:pointer;user-select:none;padding:4px 12px;border-radius:20px;font-size:13px;border:1px solid ' +
                    (isSel ? '#2563eb' : '#e5e7eb') + ';background:' + (isSel ? '#dbeafe' : '#f3f4f6') + ';' +
                    'color:' + (isSel ? '#1e40af' : '#374151') + ';font-weight:' + (isSel ? '500' : '400') + ';">' +
                    (isSel ? '★ ' : '') + escHtml(k) + '</span>';
            });
            html += '</div><small style="display:block;margin-top:6px;color:#9ca3af;font-size:11px;">Le mot-clé sélectionné sera enregistré comme focus keyword (compatible Yoast / RankMath si présents).</small></div>';
        }

        if (d.content_advice) {
            html += '<div style="padding:10px 12px;background:#fef9c3;border-radius:6px;font-size:13px;color:#713f12;">💡 ' + escHtml(d.content_advice) + '</div>';
        }

        html += '<button type="button" class="button button-primary btn-apply-all" data-id="' + parseInt(id, 10) + '">' +
                '✓ ' + escHtml(t('apply_selection', 'Appliquer la sélection (titre + meta + mot-clé)')) + '</button>';

        html += '</div>';
        $('#modal-content').html(html);
    }

    // Sélectionner un titre ou une meta (enregistre immédiatement, sans fermer)
    $(document).on('click', '.btn-apply-seo', function () {
        var id    = $(this).data('id');
        var field = $(this).data('field');
        var val   = $(this).data('value');
        var $btn  = $(this).prop('disabled', true).text(t('saving', 'Enregistrement…'));

        $.post(cfg.ajax_url, {
            action:  'alesta_audit_apply',
            nonce:   cfg.nonce,
            post_id: id,
            title:   field === 'title' ? val : (currentSelection.title || ''),
            meta:    field === 'meta'  ? val : (currentSelection.meta  || ''),
            keyword: currentSelection.keyword || ''
        }, function () {
            if (field === 'title') currentSelection.title = val;
            if (field === 'meta')  currentSelection.meta  = val;

            $('#modal-content').find('.alesta-suggestion-row[data-field="' + field + '"]').each(function () {
                var isThis = ($(this).data('value') === val);
                $(this).css('background', isThis ? '#ecfdf5' : '#f9fafb')
                       .css('border-color', isThis ? '#6ee7b7' : '#e5e7eb');
                $(this).find('.btn-apply-seo').prop('disabled', false).text(isThis ? '✓ ' + t('selected', 'Sélectionné') : t('apply', 'Appliquer'));
            });

            refreshPostRow(id);
        }).fail(function () {
            $btn.prop('disabled', false).text(t('apply', 'Appliquer'));
            alert(t('network_error', 'Erreur réseau.'));
        });
    });

    // Choisir un mot-clé principal (sélection locale)
    $(document).on('click', '.alesta-keyword-chip', function () {
        var val = $(this).data('value');
        currentSelection.keyword = val;
        $('#modal-content').find('.alesta-keyword-chip').each(function () {
            var isThis = ($(this).data('value') === val);
            $(this).css('border-color', isThis ? '#2563eb' : '#e5e7eb')
                   .css('background',   isThis ? '#dbeafe' : '#f3f4f6')
                   .css('color',        isThis ? '#1e40af' : '#374151')
                   .css('font-weight',  isThis ? '500' : '400');
            var text = $(this).text().replace(/^★\s*/, '');
            $(this).text(isThis ? '★ ' + text : text);
        });
    });

    // Bouton global : applique la sélection puis ferme + rafraîchit
    $(document).on('click', '.btn-apply-all', function () {
        var $btn = $(this).prop('disabled', true).text(t('saving', 'Enregistrement…'));
        var id   = $(this).data('id');

        $.post(cfg.ajax_url, {
            action:  'alesta_audit_apply',
            nonce:   cfg.nonce,
            post_id: id,
            title:   currentSelection.title   || '',
            meta:    currentSelection.meta    || '',
            keyword: currentSelection.keyword || ''
        }, function () {
            $btn.text('✓ ' + t('applied', 'Appliqué !')).css('background', '#22c55e').css('border-color', '#22c55e');
            refreshPostAndClose(id);
        }).fail(function () {
            $btn.prop('disabled', false).text('✓ ' + t('apply_selection', 'Appliquer la sélection'));
            alert(t('network_error', 'Erreur réseau.'));
        });
    });

    function mergeUpdated(updated) {
        var list  = currentAuditData.seo || [];
        var found = false;
        for (var i = 0; i < list.length; i++) {
            if (parseInt(list[i].post_id, 10) === parseInt(updated.post_id, 10)) {
                list[i] = updated;
                found = true;
                break;
            }
        }
        if (!found) list.push(updated);
        currentAuditData.seo = list;
        renderSEO(list);
        recomputeSummary();
    }

    // Ré-audit silencieux d'une ligne SEO sans fermer la modal
    function refreshPostRow(postId) {
        $.post(cfg.ajax_url, { action: 'alesta_audit_one', nonce: cfg.nonce, post_id: postId }, function (r) {
            if (!r || !r.success || !r.data || !currentAuditData) return;
            mergeUpdated(r.data);
        });
    }

    // Ferme la modal après un court délai puis rafraîchit la ligne SEO
    function refreshPostAndClose(postId) {
        setTimeout(function () {
            $('#seo-modal').css('display', 'none');
            $.post(cfg.ajax_url, { action: 'alesta_audit_one', nonce: cfg.nonce, post_id: postId }, function (r) {
                if (!r || !r.success || !r.data || !currentAuditData) return;
                mergeUpdated(r.data);
                var $row = $('#tab-seo').find('button.btn-generate-seo[data-id="' + r.data.post_id + '"]').closest('tr');
                if ($row.length) {
                    $row.css('background', '#ecfdf5').css('transition', 'background 1.5s ease');
                    setTimeout(function () { $row.css('background', ''); }, 1500);
                }
            });
        }, 400);
    }

    // ── Fermeture modal ───────────────────────────────────────────────────────
    $(document).on('click', '#seo-modal-close', function () { $('#seo-modal').css('display', 'none'); });
    $('#seo-modal').on('click', function (e) { if (e.target === this) $('#seo-modal').css('display', 'none'); });

    // ── Onglets ───────────────────────────────────────────────────────────────
    $(document).on('click', '.audit-tab', function () {
        $('.audit-tab').removeClass('active');
        $('.audit-tab-content').hide();
        $(this).addClass('active');
        $('#tab-' + $(this).data('tab')).show();
    });

    // ── Helpers ───────────────────────────────────────────────────────────────
    function escHtml(s) {
        return String(s === undefined || s === null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function escAttr(s) { return escHtml(s).replace(/'/g, '&#39;'); }
    function emptyState(msg) {
        return '<p style="padding:2rem;text-align:center;color:#6b7280;">' + msg + '</p>';
    }
});
