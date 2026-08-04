/* Nettoyeur BDD — Alesta */
jQuery(function ($) {
    'use strict';

    var ajaxUrl = AlestaDbCleaner.ajax_url;
    var nonce   = AlestaDbCleaner.nonce;

    // -------------------------------------------------------------------------
    // Analyser la BDD
    // -------------------------------------------------------------------------

    $('#btn-analyze').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('⏳ Analyse…');
        $('#db-global-msg').hide();

        $.post(ajaxUrl, { action: 'alesta_db_analyze', nonce: nonce })
            .done(function (res) {
                $btn.prop('disabled', false).text('🔍 Analyser la BDD');
                if (!res.success) return;

                var data       = res.data;
                var totalCount = data.total_count;
                var totalSize  = data.total_size;

                /* Mettre à jour les stats globales */
                $('#stat-total-count').text(totalCount > 0 ? totalCount + ' élément(s)' : '0 élément');
                $('#stat-total-size').text(totalSize > 0 ? totalSize.toFixed(1) + ' Ko' : '—');

                /* Activer/désactiver "Tout nettoyer" */
                $('#btn-clean-all').prop('disabled', totalCount === 0);

                /* Mettre à jour chaque carte */
                $.each(data.categories, function (key, item) {
                    var count   = item.count;
                    var sizeKb  = item.size_kb;
                    var $badge  = $('#badge-' + key);
                    var $size   = $('#size-' + key);
                    var $card   = $('.alesta-db-card[data-key="' + key + '"]');
                    var $btn    = $card.find('.btn-clean-cat');

                    $badge.text(count > 0 ? count : '✓');
                    $badge.removeClass('badge-dirty badge-clean')
                          .addClass(count > 0 ? 'badge-dirty' : 'badge-clean');

                    $card.removeClass('has-items is-clean')
                         .addClass(count > 0 ? 'has-items' : 'is-clean');

                    $size.text(sizeKb > 0 ? sizeKb.toFixed(1) + ' Ko' : '');
                    $btn.prop('disabled', count === 0);

                    /* Réinitialiser les messages de résultat */
                    $('#msg-' + key).text('').removeClass('msg-ok msg-error');
                });
            })
            .fail(function () {
                $btn.prop('disabled', false).text('🔍 Analyser la BDD');
            });
    });

    // -------------------------------------------------------------------------
    // Nettoyer une catégorie
    // -------------------------------------------------------------------------

    $(document).on('click', '.btn-clean-cat', function () {
        var $btn  = $(this).prop('disabled', true);
        var key   = $btn.data('key');
        var $spin = $('#spin-' + key).addClass('is-active');
        var $msg  = $('#msg-' + key).text('').removeClass('msg-ok msg-error');

        $.post(ajaxUrl, {
            action:   'alesta_db_clean_category',
            nonce:    nonce,
            category: key,
        })
        .done(function (res) {
            $spin.removeClass('is-active');
            $btn.prop('disabled', true); /* reste désactivé car vidé */

            if (res.success) {
                $msg.text(res.data.message).addClass('msg-ok');
                /* Remettre le badge à zéro */
                $('#badge-' + key).text('✓').removeClass('badge-dirty').addClass('badge-clean');
                $('#size-' + key).text('');
                $('.alesta-db-card[data-key="' + key + '"]')
                    .removeClass('has-items').addClass('is-clean');
                /* Mettre à jour le dernier nettoyage */
                updateLastRun();
            } else {
                $msg.text(res.data ? res.data.message : 'Erreur.').addClass('msg-error');
                $btn.prop('disabled', false);
            }
        })
        .fail(function () {
            $spin.removeClass('is-active');
            $btn.prop('disabled', false);
            $msg.text('Erreur de connexion.').addClass('msg-error');
        });
    });

    // -------------------------------------------------------------------------
    // Tout nettoyer
    // -------------------------------------------------------------------------

    $('#btn-clean-all').on('click', function () {
        if (!window.confirm('Supprimer tous les éléments détectés ? Cette action est irréversible.')) return;

        var $btn = $(this).prop('disabled', true).text('⏳ Nettoyage…');
        var $msg = $('#db-global-msg').hide().removeClass('msg-success msg-error');

        /* Désactiver les boutons individuels */
        $('.btn-clean-cat').prop('disabled', true);

        $.post(ajaxUrl, { action: 'alesta_db_clean_all', nonce: nonce })
            .done(function (res) {
                $btn.prop('disabled', true).text('🧹 Tout nettoyer');

                if (res.success) {
                    /* Remettre toutes les cartes à zéro */
                    $.each(res.data.results, function (key, deleted) {
                        $('#badge-' + key).text('✓').removeClass('badge-dirty').addClass('badge-clean');
                        $('#size-' + key).text('');
                        $('#msg-' + key).text('').removeClass('msg-ok msg-error');
                        $('.alesta-db-card[data-key="' + key + '"]')
                            .removeClass('has-items').addClass('is-clean');
                    });

                    $msg.text(res.data.message).addClass('msg-success').show();
                    $('#stat-total-count').text('0 élément');
                    $('#stat-total-size').text('—');
                    $('#stat-last-run').text(res.data.last_run);
                } else {
                    $msg.text(res.data ? res.data.message : 'Erreur.').addClass('msg-error').show();
                }
            })
            .fail(function () {
                $btn.prop('disabled', false).text('🧹 Tout nettoyer');
                $msg.text('Erreur de connexion.').addClass('msg-error').show();
            });
    });

    // -------------------------------------------------------------------------
    // Enregistrer la planification
    // -------------------------------------------------------------------------

    $('#btn-save-schedule').on('click', function () {
        var $btn  = $(this).prop('disabled', true);
        var $spin = $('#spin-schedule').addClass('is-active');
        var $msg  = $('#msg-schedule').text('').removeClass('msg-success msg-error');
        var val   = $('input[name="db_schedule"]:checked').val();
        var cats  = [];
        $('input[name="db_schedule_cats[]"]:checked').each(function () {
            cats.push($(this).val());
        });

        $.post(ajaxUrl, {
            action:         'alesta_db_save_schedule',
            nonce:          nonce,
            schedule:       val,
            schedule_cats:  cats,
        })
        .done(function (res) {
            $spin.removeClass('is-active');
            $btn.prop('disabled', false);
            if (res.success) {
                $msg.text('✅ ' + res.data.message).addClass('msg-success');
            } else {
                $msg.text(res.data ? res.data.message : 'Erreur.').addClass('msg-error');
            }
        })
        .fail(function () {
            $spin.removeClass('is-active');
            $btn.prop('disabled', false);
            $msg.text('Erreur de connexion.').addClass('msg-error');
        });
    });

    // -------------------------------------------------------------------------
    // Utilitaire
    // -------------------------------------------------------------------------

    function updateLastRun() {
        var now = new Date();
        var pad = function (n) { return n < 10 ? '0' + n : n; };
        var str = pad(now.getDate()) + '/' + pad(now.getMonth() + 1) + '/' + now.getFullYear()
                + ' à ' + pad(now.getHours()) + ':' + pad(now.getMinutes());
        $('#stat-last-run').text(str);
    }

    // Lancer l'analyse automatiquement au chargement
    $('#btn-analyze').trigger('click');
});
