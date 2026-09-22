/* Alesta - FAQ Schema admin JS */
jQuery(function ($) {

    var cfg  = window.AlestaFaq || {};
    var i18n = cfg.i18n || {};
    var ajaxUrl = cfg.ajax_url;
    var nonce   = cfg.nonce;

    function t(key, fallback) {
        return (i18n[key] !== undefined) ? i18n[key] : (fallback || key);
    }
    function fmt(str, val) {
        return String(str).replace('%d', val).replace('%s', val);
    }

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

    // =========================================================================
    // STATUT API (badge dans le header)
    // =========================================================================
    (function checkApi() {
        var $badge = $('#faq-api-status');
        if (!$badge.length) return;
        function paint(ok, label) {
            $badge.text((ok ? '✓ ' : '✗ ') + label)
                  .css('background', ok ? '#d1fae5' : '#fee2e2')
                  .css('color', ok ? '#065f46' : '#991b1b')
                  .css('border-color', ok ? '#6ee7b7' : '#fca5a5');
        }
        $.post(ajaxUrl, { action: 'alesta_faq_schema_test_api', nonce: nonce }, function (r) {
            var ok = r && r.success;
            paint(ok, ok ? t('api_ok') : t('api_ko'));
        }).fail(function () {
            paint(false, t('api_unreachable'));
        });
    })();

    // =========================================================================
    // FILTRES
    // =========================================================================
    function filterTable() {
        var types  = $('.faq-filter-type:checked').map(function () { return String(this.value); }).get();
        var status = $('#faq-filter-status').val();
        var search = ($('#faq-search').val() || '').toLowerCase();

        $('#faq-tbody .faq-row').each(function () {
            var ok = true;
            // Aucun type coche = aucune ligne (filtre strict)
            if (!types.length) ok = false;
            else if (types.indexOf(String($(this).data('type'))) === -1) ok = false;

            if (ok && status !== 'all' && $(this).data('status') !== status) ok = false;
            if (ok && search) {
                var title = ($(this).find('a').first().text() || '').toLowerCase();
                if (title.indexOf(search) === -1) ok = false;
            }
            $(this).toggle(ok);
        });
        syncHeaderCheckbox();
        refreshSelectionBar();
    }
    $(document).on('change', '.faq-filter-type, #faq-filter-status', filterTable);
    $('#faq-search').on('input', filterTable);

    // =========================================================================
    // SELECTION : cases a cocher par ligne + "tout selectionner" en-tete
    // =========================================================================
    function selectedIds() {
        return $('#faq-tbody .faq-row:visible .faq-row-check:checked')
            .map(function () { return $(this).data('id'); }).get();
    }
    function refreshSelectionBar() {
        var ids = selectedIds();
        if (ids.length) {
            $('#faq-selection-bar').css('display', 'flex');
            $('#btn-faq-batch').hide();
            $('#faq-selection-count').text(fmt(t('selected'), ids.length));
        } else {
            $('#faq-selection-bar').hide();
            $('#btn-faq-batch').show();
        }
    }
    function syncHeaderCheckbox() {
        var $visible = $('#faq-tbody .faq-row:visible .faq-row-check');
        var checked  = $visible.filter(':checked').length;
        var $all     = $('#faq-check-all');
        if (checked === 0) {
            $all.prop({ checked: false, indeterminate: false });
        } else if (checked === $visible.length) {
            $all.prop({ checked: true, indeterminate: false });
        } else {
            $all.prop({ checked: false, indeterminate: true });
        }
    }

    $(document).on('change', '.faq-row-check', function () {
        syncHeaderCheckbox();
        refreshSelectionBar();
    });

    $(document).on('change', '#faq-check-all', function () {
        var state = $(this).is(':checked');
        $('#faq-tbody .faq-row:visible .faq-row-check').prop('checked', state);
        refreshSelectionBar();
    });

    // =========================================================================
    // HELPERS MODAL
    // =========================================================================
    function showModal(title, sub, body) {
        $('#faq-modal-title').text(title);
        $('#faq-modal-sub').text(sub || '');
        $('#faq-modal-body').html(body);
        $('#faq-modal').show();
    }
    function hideModal() {
        $('#faq-modal').hide();
    }
    $(document).on('click', '.faq-modal-close', hideModal);
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#faq-modal').is(':visible')) hideModal();
    });

    function spinner(msg) {
        return '<div style="text-align:center;padding:40px;">'
             + '<div class="alesta-faq-spinner"></div>'
             + '<p style="color:#9ca3af;margin-top:12px;">' + esc(msg) + '</p></div>';
    }

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // =========================================================================
    // RENDU FORMULAIRE FAQ (generation + edition)
    // =========================================================================
    function renderFaqForm(faqs, post_id) {
        var html = '<div id="faq-items">';
        (faqs || []).forEach(function (faq, i) {
            html += faqItem(faq.q, faq.a, i);
        });
        html += '</div>';

        html += '<div style="margin-top:12px;">';
        html += '<details style="border:1px solid #e5e7eb;border-radius:6px;padding:10px 14px;">';
        html += '<summary style="font-size:12px;color:#6b7280;cursor:pointer;">' + esc(t('preview_title')) + '</summary>';
        html += '<pre id="faq-preview" style="font-size:11px;color:#374151;margin:10px 0 0;overflow:auto;max-height:200px;background:#f8fafc;padding:10px;border-radius:4px;"></pre>';
        html += '</details></div>';

        html += '<div style="display:flex;gap:8px;margin-top:16px;">';
        html += '<button type="button" id="faq-btn-add-q" class="button" style="font-size:12px;">' + esc(t('add_question')) + '</button>';
        html += '<button type="button" id="faq-btn-save" class="button button-primary" data-id="' + esc(post_id) + '">' + esc(t('save_and_activate')) + '</button>';
        html += '<button type="button" class="button faq-modal-close">' + esc(t('close')) + '</button>';
        html += '</div>';

        setTimeout(updatePreview, 100);
        return html;
    }

    function faqItem(q, a, i) {
        return '<div class="faq-item" style="border:1px solid #e5e7eb;border-radius:6px;padding:14px;margin-bottom:10px;position:relative;">'
             + '<button type="button" class="faq-item-remove" title="' + esc(t('remove')) + '" style="position:absolute;top:8px;right:8px;background:none;border:none;cursor:pointer;color:#9ca3af;font-size:16px;">&times;</button>'
             + '<div style="margin-bottom:8px;">'
             + '<label style="display:block;font-size:11px;font-weight:600;color:#374151;margin-bottom:4px;">' + esc(fmt(t('question_n'), i + 1)) + '</label>'
             + '<input type="text" class="faq-q" value="' + esc(q) + '" placeholder="' + esc(t('question_ph')) + '" '
             + 'style="width:100%;padding:7px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;box-sizing:border-box;">'
             + '</div>'
             + '<div>'
             + '<label style="display:block;font-size:11px;font-weight:600;color:#374151;margin-bottom:4px;">' + esc(t('answer')) + '</label>'
             + '<textarea class="faq-a" rows="3" placeholder="' + esc(t('answer_ph')) + '" '
             + 'style="width:100%;padding:7px 10px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;resize:vertical;box-sizing:border-box;">'
             + esc(a) + '</textarea>'
             + '</div></div>';
    }

    function collectFaqs() {
        var faqs = [];
        $('#faq-items .faq-item').each(function () {
            var q = $.trim($(this).find('.faq-q').val());
            var a = $.trim($(this).find('.faq-a').val());
            if (q && a) faqs.push({ q: q, a: a });
        });
        return faqs;
    }

    function updatePreview() {
        var faqs = collectFaqs();
        if (!faqs.length) { $('#faq-preview').text(t('no_question')); return; }
        var schema = {
            '@context': 'https://schema.org',
            '@type':    'FAQPage',
            'mainEntity': faqs.map(function (f) {
                return {
                    '@type': 'Question',
                    'name': f.q,
                    'acceptedAnswer': { '@type': 'Answer', 'text': f.a }
                };
            })
        };
        $('#faq-preview').text(JSON.stringify(schema, null, 2));
    }

    $(document).on('input change', '.faq-q, .faq-a', updatePreview);

    $(document).on('click', '#faq-btn-add-q', function () {
        var i = $('#faq-items .faq-item').length;
        $('#faq-items').append(faqItem('', '', i));
    });

    $(document).on('click', '.faq-item-remove', function () {
        $(this).closest('.faq-item').remove();
        $('#faq-items .faq-item').each(function (i) {
            $(this).find('label').first().text(fmt(t('question_n'), i + 1));
        });
        updatePreview();
    });

    // =========================================================================
    // Mise a jour en place d'une ligne apres save (pas de reload)
    // =========================================================================
    function updateRowAfterSave(post_id, faqs, active) {
        var $row = $('#faq-tbody .faq-row[data-id="' + post_id + '"]');
        if (!$row.length) return;

        var count  = (faqs || []).length;
        var hasFaq = count > 0;
        var status = hasFaq ? (active ? 'active' : 'with_faq') : 'no_faq';
        $row.attr('data-status', status);
        $row.data('status', status);

        // Cellule FAQ (nombre de questions)
        if (hasFaq) {
            $row.find('.faq-cell-count').html(
                '<span style="background:#d1fae5;color:#065f46;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;">' + count + ' Q</span>'
            );
        } else {
            $row.find('.faq-cell-count').html('<span style="color:#d1d5db;font-size:12px;">-</span>');
        }

        // Cellule ACTIF (toggle checkbox)
        if (hasFaq) {
            $row.find('.faq-cell-active').html(
                '<label style="display:inline-flex;align-items:center;cursor:pointer;gap:6px;">' +
                '<input type="checkbox" class="faq-toggle" data-id="' + esc(post_id) + '"' + (active ? ' checked' : '') + ' style="width:16px;height:16px;cursor:pointer;">' +
                '<span style="font-size:11px;color:#6b7280;">' + esc(active ? t('yes') : t('no')) + '</span>' +
                '</label>'
            );
        } else {
            $row.find('.faq-cell-active').html('<span style="color:#d1d5db;font-size:12px;">-</span>');
        }

        // Cellule ACTIONS
        var title = $.trim($row.find('a').first().text());
        var actionsHtml =
            '<div style="display:flex;justify-content:center;gap:4px;">' +
            '<button class="button button-small faq-btn-generate" data-id="' + esc(post_id) + '" data-title="' + esc(title) + '" style="font-size:11px;">' + esc(t('generate')) + '</button>';
        if (hasFaq) {
            actionsHtml +=
                '<button class="button button-small faq-btn-edit" data-id="' + esc(post_id) + '" data-title="' + esc(title) + '" data-faqs="' + esc(JSON.stringify(faqs)) + '" style="font-size:11px;">' + esc(t('edit')) + '</button>' +
                '<button class="button button-small faq-btn-delete" data-id="' + esc(post_id) + '" style="font-size:11px;color:#991b1b;border-color:#fca5a5;">' + esc(t('delete')) + '</button>';
        }
        actionsHtml += '</div>';
        $row.find('.faq-cell-actions').html(actionsHtml);

        // Date de generation (ajout si pas encore la)
        if (hasFaq && !$row.find('.faq-row-date').length) {
            var today = new Date();
            var dd   = String(today.getDate()).padStart(2, '0');
            var mm   = String(today.getMonth() + 1).padStart(2, '0');
            var yyyy = today.getFullYear();
            $row.find('td').eq(1).find('a').after(
                '<div class="faq-row-date" style="font-size:11px;color:#9ca3af;margin-top:2px;">' + esc(fmt(t('generated_on'), dd + '/' + mm + '/' + yyyy)) + '</div>'
            );
        }
        if (!hasFaq) {
            $row.find('.faq-row-date').remove();
        }

        refreshGlobalStats();

        // Surligner la ligne brievement en vert
        $row.css({ 'background': '#ecfdf5', 'transition': 'background 1.5s ease' });
        setTimeout(function () { $row.css('background', ''); }, 1500);
    }

    function refreshGlobalStats() {
        var $rows   = $('#faq-tbody .faq-row');
        var total   = $rows.length;
        var withFaq = $rows.filter(function () { var s = $(this).data('status'); return s === 'with_faq' || s === 'active'; }).length;
        var active  = $rows.filter('[data-status="active"]').length;
        var $stats  = $('#faq-stats .faq-stat-value');
        if ($stats.length >= 3) {
            $stats.eq(0).text(total);
            $stats.eq(1).text(withFaq);
            $stats.eq(2).text(active);
        }
    }

    // =========================================================================
    // GENERER
    // =========================================================================
    $(document).on('click', '.faq-btn-generate', function () {
        var post_id = $(this).data('id');
        var title   = $(this).data('title');
        showModal(t('faq_prefix') + title, t('analyzing'), spinner(t('generating')));

        $.post(ajaxUrl, {
            action:  'alesta_faq_schema_generate',
            nonce:   nonce,
            post_id: post_id
        }, function (r) {
            if (r.success && r.data.faqs) {
                $('#faq-modal-sub').text(t('check_and_save'));
                $('#faq-modal-body').html(renderFaqForm(r.data.faqs, post_id));
            } else {
                var msg = (r.data && r.data.message) ? r.data.message : t('unknown_error');
                $('#faq-modal-body').html('<p style="color:#991b1b;padding:20px;">' + esc(msg) + '</p>');
                appendKeyButton('#faq-modal-body', r);
            }
        }).fail(function () {
            $('#faq-modal-body').html('<p style="color:#991b1b;padding:20px;">' + esc(t('network_error')) + '</p>');
        });
    });

    // MODIFIER (pre-rempli)
    $(document).on('click', '.faq-btn-edit', function () {
        var post_id = $(this).data('id');
        var title   = $(this).data('title');
        var faqs    = $(this).data('faqs');
        if (typeof faqs === 'string') { try { faqs = JSON.parse(faqs); } catch (e) { faqs = []; } }
        showModal(t('edit_prefix') + title, t('edit_and_save'), renderFaqForm(faqs, post_id));
    });

    // SAUVEGARDER - mise a jour en place, pas de location.reload()
    $(document).on('click', '#faq-btn-save', function () {
        var $btn    = $(this);
        var post_id = $btn.data('id');
        var faqs    = collectFaqs();
        if (!faqs.length) { window.alert(t('add_at_least_one')); return; }
        $btn.text('...').prop('disabled', true);

        $.post(ajaxUrl, {
            action:  'alesta_faq_schema_save',
            nonce:   nonce,
            post_id: post_id,
            faqs:    faqs
        }, function (r) {
            if (r.success) {
                hideModal();
                var active = (r.data && r.data.active !== undefined) ? !!r.data.active : true;
                updateRowAfterSave(post_id, faqs, active);
            } else {
                $btn.text(t('save_and_activate')).prop('disabled', false);
                notifyError(r, (r.data && r.data.message) ? r.data.message : t('save_error'));
            }
        }).fail(function () {
            $btn.text(t('save_and_activate')).prop('disabled', false);
            window.alert(t('network_error'));
        });
    });

    // SUPPRIMER
    $(document).on('click', '.faq-btn-delete', function () {
        if (!window.confirm(t('confirm_delete'))) return;
        var post_id = $(this).data('id');
        $.post(ajaxUrl, {
            action: 'alesta_faq_schema_delete', nonce: nonce, post_id: post_id
        }, function (r) {
            if (r.success) updateRowAfterSave(post_id, [], false);
        });
    });

    // =========================================================================
    // TOGGLE ACTIF / INACTIF
    // =========================================================================
    $(document).on('change', '.faq-toggle', function () {
        var $cb     = $(this);
        var post_id = $cb.data('id');
        var active  = $cb.is(':checked') ? 1 : 0;
        var $label  = $cb.siblings('span');
        $.post(ajaxUrl, {
            action: 'alesta_faq_schema_toggle', nonce: nonce, post_id: post_id, active: active
        }, function (r) {
            if (r.success) {
                $label.text(active ? t('yes') : t('no'));
                var $row = $cb.closest('tr');
                $row.attr('data-status', active ? 'active' : 'with_faq');
                $row.data('status', active ? 'active' : 'with_faq');
                refreshGlobalStats();
            } else {
                $cb.prop('checked', !active);
            }
        }).fail(function () {
            $cb.prop('checked', !active);
        });
    });

    // =========================================================================
    // GENERATION EN LOT - mode "manquants visibles"
    // =========================================================================
    $('#btn-faq-batch').on('click', function () {
        var ids = [];
        $('#faq-tbody .faq-row[data-status="no_faq"]:visible').each(function () {
            ids.push($(this).data('id'));
        });
        if (!ids.length) { window.alert(t('no_missing_visible')); return; }
        if (!window.confirm(fmt(t('confirm_batch'), ids.length))) return;
        batchGenerate(ids, $(this), t('batch_missing'));
    });

    // =========================================================================
    // ACTIONS EN LOT SUR LA SELECTION
    // =========================================================================
    $('#btn-faq-batch-selected').on('click', function () {
        var ids = selectedIds();
        if (!ids.length) { window.alert(t('no_selection')); return; }
        if (!window.confirm(fmt(t('confirm_batch_sel'), ids.length))) return;
        batchGenerate(ids, $(this), t('batch_selected'));
    });

    $('#btn-faq-activate-selected').on('click', function () {
        bulkToggle(selectedIds(), true, $(this), t('activate_selected'));
    });

    $('#btn-faq-deactivate-selected').on('click', function () {
        bulkToggle(selectedIds(), false, $(this), t('deactivate_selected'));
    });

    function batchGenerate(ids, $btn, labelDefault) {
        $btn.prop('disabled', true);
        var idx = 0;
        function next() {
            if (idx >= ids.length) {
                $btn.prop('disabled', false).text(labelDefault);
                return;
            }
            $btn.text((idx + 1) + '/' + ids.length);
            var pid = ids[idx];
            $.post(ajaxUrl, {
                action: 'alesta_faq_schema_generate', nonce: nonce, post_id: pid
            }, function (r) {
                if (r.success && r.data.faqs) {
                    $.post(ajaxUrl, {
                        action: 'alesta_faq_schema_save', nonce: nonce,
                        post_id: pid, faqs: r.data.faqs
                    }, function (r2) {
                        if (r2.success) {
                            var active = (r2.data && r2.data.active !== undefined) ? !!r2.data.active : true;
                            updateRowAfterSave(pid, r.data.faqs, active);
                        }
                        idx++; next();
                    }).fail(function () { idx++; next(); });
                } else {
                    idx++; next();
                }
            }).fail(function () { idx++; next(); });
        }
        next();
    }

    function bulkToggle(ids, active, $btn, labelDefault) {
        if (!ids.length) { window.alert(t('no_selection')); return; }
        var eligible = ids.filter(function (id) {
            var $row = $('#faq-tbody .faq-row[data-id="' + id + '"]');
            var st   = $row.data('status');
            return st === 'with_faq' || st === 'active';
        });
        if (!eligible.length) {
            window.alert(t('no_eligible'));
            return;
        }
        $btn.prop('disabled', true);
        var idx = 0;
        function next() {
            if (idx >= eligible.length) {
                $btn.prop('disabled', false).text(labelDefault);
                refreshGlobalStats();
                return;
            }
            $btn.text((idx + 1) + '/' + eligible.length);
            var pid = eligible[idx];
            $.post(ajaxUrl, {
                action: 'alesta_faq_schema_toggle', nonce: nonce, post_id: pid, active: active ? 1 : 0
            }, function (r) {
                if (r.success) {
                    var $row = $('#faq-tbody .faq-row[data-id="' + pid + '"]');
                    $row.attr('data-status', active ? 'active' : 'with_faq');
                    $row.data('status', active ? 'active' : 'with_faq');
                    $row.find('.faq-toggle').prop('checked', !!active).siblings('span').text(active ? t('yes') : t('no'));
                }
                idx++; next();
            }).fail(function () { idx++; next(); });
        }
        next();
    }
});
