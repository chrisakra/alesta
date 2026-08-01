jQuery(function ($) {
    console.log('[Alesta Errors] errors.js charge. AlestaErrors:', typeof AlestaErrors !== 'undefined' ? 'OK' : 'MANQUANT');
    console.log('[Alesta Errors] Bouton scan:', $('#btn-scan').length);

    // =========================================================================
    // FILTRES
    // =========================================================================
    function filterTable() {
        var code   = $('#err-filter-code').val();
        var type   = $('#err-filter-type').val();
        var search = $('#err-search').val().toLowerCase();
        var visible = 0;

        $('#err-tbody .err-row').each(function () {
            var ok = true;
            if (code !== 'all' && $(this).data('code') !== code) ok = false;
            if (type !== 'all' && $(this).data('type') !== type) ok = false;
            if (search && !($(this).data('url') || '').includes(search)) ok = false;
            $(this).toggle(ok);
            if (ok) visible++;
        });
        $('#err-count').text(visible + ' erreur(s)');
    }

    if ($('#err-tbody .err-row').length) filterTable();
    $('#err-filter-code, #err-filter-type').on('change', filterTable);
    $('#err-search').on('input', filterTable);

    // =========================================================================
    // SCAN
    // =========================================================================
    // Confirmation inline au lieu de confirm() natif
    $('#btn-scan').on('click', function () {
        console.log('[Alesta Errors] Bouton scan clique');
        var $btn = $(this);

        // Afficher confirmation inline
        if (!$('#scan-confirm').length) {
            $btn.after('<div id="scan-confirm" style="margin-top:10px;padding:12px 14px;background:#fef9c3;border:1px solid #fde68a;border-radius:6px;font-size:13px;display:flex;align-items:center;gap:10px;">'
                + '<span>Lancer le scan ? Cela peut prendre quelques minutes selon le nombre de liens.</span>'
                + '<button id="scan-confirm-yes" class="button button-primary" style="font-size:12px;">Oui, lancer</button>'
                + '<button id="scan-confirm-no" class="button" style="font-size:12px;">Annuler</button>'
                + '</div>');
        } else {
            $('#scan-confirm').toggle();
        }
    });

    $(document).on('click', '#scan-confirm-no', function () {
        $('#scan-confirm').remove();
    });

    $(document).on('click', '#scan-confirm-yes', function () {
        $('#scan-confirm').remove();
        var $btn = $('#btn-scan').prop('disabled', true).text('Scan en cours...');
        $('#scan-progress-bar').show();
        $('#scan-bar-fill').css('width', '0%');
        $('#scan-bar-text').text('Recuperation des pages...');

        // Etape 1 : recuperer la liste des posts
        $.post(AlestaErrors.ajax_url, {
            action: 'alesta_errors_scan',
            nonce:  AlestaErrors.nonce,
        }, function (r) {
            console.log('[Alesta Errors] Reponse scan:', r);
            if (!r.success) {
                $btn.prop('disabled', false).text('Lancer le scan');
                alert(r.data && r.data.message ? r.data.message : 'Erreur');
                return;
            }

            var ids   = r.data.post_ids || [];
            // S'assurer que c'est bien un tableau
            if (!Array.isArray(ids)) ids = Object.values(ids);
            var total = ids.length;
            var idx   = 0;

            $('#scan-bar-text').text('0 / ' + total + ' pages scannees...');

            // Etape 2 : scanner chaque post en sequence
            function scanNext() {
                if (idx >= ids.length) {
                    $('#scan-bar-fill').css('width', '100%');
                    $('#scan-bar-text').text('Scan termine ! Rechargement...');
                    setTimeout(function () { location.reload(); }, 1000);
                    return;
                }
                var pct = Math.round(idx / total * 100);
                $('#scan-bar-fill').css('width', pct + '%');
                $('#scan-bar-text').text((idx + 1) + ' / ' + total + ' pages scannees...');

                $.ajax({
                    url:     AlestaErrors.ajax_url,
                    type:    'POST',
                    timeout: 20000,
                    data:    { action: 'alesta_errors_scan_post', nonce: AlestaErrors.nonce, post_id: ids[idx] },
                    complete: function () { idx++; scanNext(); },
                });
            }
            scanNext();

        }).fail(function () {
            $btn.prop('disabled', false).text('Lancer le scan');
            alert('Erreur reseau.');
        });
    }); // fin scan-confirm-yes

    // =========================================================================
    // MODAL CORRECTION
    // =========================================================================
    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    $(document).on('click', '.err-btn-fix', function () {
        var post_id = $(this).data('post-id');
        var old_url = $(this).data('old-url');

        var html = '';
        html += '<div style="margin-bottom:14px;">';
        html += '<label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;">URL CASSEE</label>';
        html += '<div style="font-size:12px;background:#fee2e2;padding:8px 12px;border-radius:4px;color:#991b1b;word-break:break-all;">' + esc(old_url) + '</div>';
        html += '</div>';

        html += '<div style="margin-bottom:16px;">';
        html += '<label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:4px;">NOUVELLE URL <span style="color:#991b1b;">*</span></label>';
        html += '<input type="text" id="err-new-url" placeholder="https://..." '
              + 'style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;box-sizing:border-box;">';
        html += '<div style="font-size:11px;color:#9ca3af;margin-top:4px;">Entrez la nouvelle URL qui remplacera le lien casse dans le contenu de la page.</div>';
        html += '</div>';

        html += '<div style="display:flex;gap:8px;">';
        html += '<button id="err-btn-apply-fix" class="button button-primary" data-post-id="' + post_id + '" data-old-url="' + esc(old_url) + '">Appliquer la correction</button>';
        html += '<button id="err-modal-close2" class="button">Annuler</button>';
        html += '</div>';

        $('#err-modal-body').html(html);
        $('#err-modal').show();
        $('#err-new-url').focus();
    });

    $(document).on('click', '#err-modal-close, #err-modal-close2', function () {
        $('#err-modal').hide();
    });
    $(document).on('click', '#err-modal', function (e) {
        if (e.target.id === 'err-modal') $('#err-modal').hide();
    });

    // Appliquer la correction
    $(document).on('click', '#err-btn-apply-fix', function () {
        var $btn    = $(this).prop('disabled', true).text('...');
        var post_id = $btn.data('post-id');
        var old_url = $btn.data('old-url');
        var new_url = $('#err-new-url').val().trim();

        if (!new_url) {
            alert('Saisissez une nouvelle URL.');
            $btn.prop('disabled', false).text('Appliquer la correction');
            return;
        }

        $.post(AlestaErrors.ajax_url, {
            action:  'alesta_errors_fix',
            nonce:   AlestaErrors.nonce,
            post_id: post_id,
            old_url: old_url,
            new_url: new_url,
        }, function (r) {
            if (r.success) {
                $('#err-modal').hide();
                // Supprimer la ligne du tableau
                $('#err-tbody .err-row').filter(function () {
                    return $(this).data('url') === old_url.toLowerCase();
                }).fadeOut(300, function () { $(this).remove(); filterTable(); });

                // Toast
                var $t = $('<div style="position:fixed;bottom:24px;right:24px;background:#065f46;color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,.2);">Lien corrige avec succes</div>');
                $('body').append($t);
                setTimeout(function () { $t.fadeOut(400, function () { $t.remove(); }); }, 3000);
            } else {
                var msg = r.data && r.data.message ? r.data.message : 'Erreur';
                $btn.prop('disabled', false).text('Appliquer la correction');
                alert(msg);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Appliquer la correction');
            alert('Erreur reseau.');
        });
    });
});
