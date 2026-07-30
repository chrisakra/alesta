jQuery(function ($) {

    var state = {};

    // =========================================================================
    // INIT : Charger l'etat depuis le serveur
    // =========================================================================
    function loadState() {
        $.post(AlestaHtaccess.ajax_url, {
            action: 'alesta_htaccess_read',
            nonce:  AlestaHtaccess.nonce,
        }, function (r) {
            if (!r.success) {
                $('#htaccess-status-bar').text('Erreur de lecture du .htaccess').css('color', '#fca5a5');
                return;
            }
            state = r.data;
            renderState();
        });
    }

    function renderState() {
        // Statut global
        $('#htaccess-global-status').show();
        $('#htaccess-status-bar').text('.htaccess OK').css('color', '#6ee7b7');

        // Fichier
        $('#htaccess-file-status').html(
            state.exists
                ? '<span style="color:#065f46;">Trouve (' + Math.round(state.size / 1024 * 10) / 10 + ' Ko)</span>'
                : '<span style="color:#991b1b;">Introuvable</span>'
        );

        // Ecriture
        $('#htaccess-write-status').html(
            state.can_write
                ? '<span style="color:#065f46;">Accessible</span>'
                : '<span style="color:#991b1b;">Lecture seule</span>'
        );

        // Backup
        $('#htaccess-backup-date').text(state.backup_date || 'Aucune sauvegarde');
        if (state.has_backup) $('#btn-restore').prop('disabled', false);

        // Previews
        $('#cache-preview').text(state.preview.cache);
        $('#gzip-preview').text(state.preview.gzip);
        $('#https-preview').text(state.preview.https);

        // Statuts badges
        renderBadge('cache', state.cache_active);
        renderBadge('gzip',  state.gzip_active);
        renderBadge('https', state.https_active);

        // Alerte URL HTTPS
        if (!state.is_https) {
            $('#https-url-alert').show();
        }
    }

    function renderBadge(type, active) {
        var $badge  = $('#' + type + '-status-badge');
        var $apply  = $('#btn-apply-' + type);
        var $remove = $('#btn-remove-' + type);

        if (active) {
            $badge.text('Actif').css({ 'background': '#d1fae5', 'color': '#065f46' });
            $apply.text('Mettre a jour').removeClass('button-primary');
            $remove.show();
        } else {
            $badge.text('Inactif').css({ 'background': '#fee2e2', 'color': '#991b1b' });
            $apply.addClass('button-primary');
            $remove.hide();
        }

        if (!state.can_write) {
            $apply.prop('disabled', true).attr('title', '.htaccess non accessible en ecriture');
            $remove.prop('disabled', true);
        }
    }

    loadState();

    // =========================================================================
    // ONGLETS
    // =========================================================================
    $(document).on('click', '.htaccess-tab', function () {
        var tab = $(this).data('tab');
        $('.htaccess-tab').css({ 'font-weight': '400', 'color': '#6b7280', 'border-bottom-color': 'transparent' });
        $(this).css({ 'font-weight': '600', 'color': '#1e3a5f', 'border-bottom-color': '#1e3a5f' });
        $('.htaccess-tab-content').hide();
        $('#tab-' + tab).show();
    });

    // =========================================================================
    // SAUVEGARDE / RESTAURATION
    // =========================================================================
    $('#btn-backup').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaHtaccess.ajax_url, { action: 'alesta_htaccess_backup', nonce: AlestaHtaccess.nonce }, function (r) {
            $btn.prop('disabled', false).text('Sauvegarder maintenant');
            if (r.success) {
                $('#htaccess-backup-date').text(r.data.date);
                $('#btn-restore').prop('disabled', false);
                toast('Sauvegarde effectuee');
            } else {
                alert(r.data && r.data.message ? r.data.message : 'Erreur');
            }
        });
    });

    $('#btn-restore').on('click', function () {
        if (!confirm('Restaurer le .htaccess depuis la sauvegarde ? Les regles Alesta AI actuelles seront remplacees.')) return;
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaHtaccess.ajax_url, { action: 'alesta_htaccess_restore', nonce: AlestaHtaccess.nonce }, function (r) {
            $btn.prop('disabled', false).text('Restaurer la sauvegarde');
            if (r.success) {
                toast('Sauvegarde restauree avec succes');
                loadState();
            } else {
                alert(r.data && r.data.message ? r.data.message : 'Erreur');
            }
        });
    });

    // =========================================================================
    // CACHE NAVIGATEUR
    // =========================================================================
    $('#btn-apply-cache').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaHtaccess.ajax_url, {
            action:         'alesta_htaccess_apply_cache',
            nonce:          AlestaHtaccess.nonce,
            img_duration:   $('#cache-img-duration').val(),
            css_duration:   $('#cache-css-duration').val(),
            font_duration:  $('#cache-font-duration').val(),
        }, function (r) {
            $btn.prop('disabled', false);
            if (r.success) {
                toast(r.data.message);
                state.cache_active = true;
                renderBadge('cache', true);
                $btn.text('Mettre a jour');
            } else {
                alert(r.data && r.data.message ? r.data.message : 'Erreur');
                $btn.text('Activer le cache navigateur');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Activer le cache navigateur');
            alert('Erreur reseau.');
        });
    });

    $('#btn-remove-cache').on('click', function () {
        if (!confirm('Desactiver le cache navigateur ?')) return;
        removeRule('Alesta AI - Cache navigateur', 'cache', $(this));
    });

    // =========================================================================
    // GZIP
    // =========================================================================
    $('#btn-apply-gzip').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaHtaccess.ajax_url, {
            action: 'alesta_htaccess_apply_gzip',
            nonce:  AlestaHtaccess.nonce,
        }, function (r) {
            $btn.prop('disabled', false);
            if (r.success) {
                toast(r.data.message);
                state.gzip_active = true;
                renderBadge('gzip', true);
                $btn.text('Mettre a jour');
            } else {
                alert(r.data && r.data.message ? r.data.message : 'Erreur');
                $btn.text('Activer la compression GZIP');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Activer la compression GZIP');
            alert('Erreur reseau.');
        });
    });

    $('#btn-remove-gzip').on('click', function () {
        if (!confirm('Desactiver la compression GZIP ?')) return;
        removeRule('Alesta AI - Compression GZIP', 'gzip', $(this));
    });

    // =========================================================================
    // HTTPS
    // =========================================================================
    $('#btn-apply-https').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaHtaccess.ajax_url, {
            action: 'alesta_htaccess_apply_https',
            nonce:  AlestaHtaccess.nonce,
        }, function (r) {
            $btn.prop('disabled', false);
            if (r.success) {
                toast(r.data.message);
                state.https_active = true;
                renderBadge('https', true);
                $btn.text('Mettre a jour');
            } else {
                alert(r.data && r.data.message ? r.data.message : 'Erreur');
                $btn.text('Activer la redirection HTTPS');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Activer la redirection HTTPS');
            alert('Erreur reseau.');
        });
    });

    $('#btn-remove-https').on('click', function () {
        if (!confirm('Desactiver la redirection HTTPS ?')) return;
        removeRule('Alesta AI - HTTPS', 'https', $(this));
    });

    $('#btn-fix-https-url').on('click', function () {
        if (!confirm('Mettre a jour les URLs WordPress de HTTP vers HTTPS ? Cette action modifie les reglages WordPress.')) return;
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaHtaccess.ajax_url, {
            action: 'alesta_htaccess_fix_https_url',
            nonce:  AlestaHtaccess.nonce,
        }, function (r) {
            $btn.prop('disabled', false).text('Corriger l\'URL WordPress en HTTPS');
            if (r.success) {
                toast('URL WordPress mise a jour : ' + r.data.siteurl);
                $('#https-url-alert').slideUp();
            } else {
                alert(r.data && r.data.message ? r.data.message : 'Erreur');
            }
        });
    });

    // =========================================================================
    // WWW — .htaccess redirect
    // =========================================================================
    $('#btn-apply-www').on('click', function () {
        if (!confirm('Activer la redirection WWW dans .htaccess ?\nVérifiez que votre certificat SSL couvre www avant de continuer.')) return;
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaHtaccess.ajax_url, { action: 'alesta_htaccess_apply_www', nonce: AlestaHtaccess.nonce }, function (r) {
            $btn.prop('disabled', false).text('Activer la redirection WWW');
            if (r.success) {
                toast(r.data.message);
                $('#www-status-badge').text('✅ Actif').css({ background: '#dcfce7', color: '#166534' });
                $('#www-preview').text(r.data.preview);
                $('#btn-apply-www').hide();
                $('#btn-remove-www').show();
            } else {
                alert(r.data || 'Erreur');
            }
        });
    });

    $('#btn-remove-www').on('click', function () {
        if (!confirm('Désactiver la redirection WWW ?')) return;
        var $btn = $(this).prop('disabled', true).text('...');
        $.post(AlestaHtaccess.ajax_url, { action: 'alesta_htaccess_apply_www', nonce: AlestaHtaccess.nonce, remove: 1 }, function (r) {
            $btn.prop('disabled', false).text('Désactiver');
            if (r.success) {
                toast(r.data.message);
                $('#www-status-badge').text('⚫ Inactif').css({ background: '#f3f4f6', color: '#6b7280' });
                $('#www-preview').text('— Inactif —');
                $('#btn-remove-www').hide();
                $('#btn-apply-www').show();
            } else {
                alert(r.data || 'Erreur');
            }
        });
    });

    // =========================================================================
    // WWW — URL WordPress
    // =========================================================================
    $('#btn-add-www-url').on('click', function () {
        if (!confirm(
            '⚠️ ATTENTION — Vous allez être déconnecté !\n\n' +
            'WordPress va modifier les URLs "Adresse du site" et "Adresse WordPress" pour y ajouter www.\n\n' +
            'Vous serez automatiquement redirigé vers la page de connexion.\n' +
            'Assurez-vous de connaître vos identifiants avant de continuer.\n\n' +
            'Continuer ?'
        )) return;
        switchWwwUrl('add', $(this));
    });

    $('#btn-remove-www-url').on('click', function () {
        if (!confirm(
            '⚠️ ATTENTION — Vous allez être déconnecté !\n\n' +
            'WordPress va supprimer le www dans les URLs "Adresse du site" et "Adresse WordPress".\n\n' +
            'Vous serez automatiquement redirigé vers la page de connexion.\n' +
            'Assurez-vous de connaître vos identifiants avant de continuer.\n\n' +
            'Continuer ?'
        )) return;
        switchWwwUrl('remove', $(this));
    });

    function switchWwwUrl(mode, $btn) {
        var origText = $btn.text();
        $btn.prop('disabled', true).text('Mise à jour…');
        $('#www-url-msg').text('').css('color', '#374151');

        $.post(AlestaHtaccess.ajax_url, {
            action: 'alesta_htaccess_switch_www',
            nonce:  AlestaHtaccess.nonce,
            mode:   mode
        }, function (r) {
            if (r.success) {
                $('#www-url-msg').text('✅ ' + r.data.message).css('color', '#166534');
                setTimeout(function () {
                    window.location.href = r.data.login_url
                        ? r.data.login_url
                        : window.location.href;
                }, 2500);
            } else {
                $btn.prop('disabled', false).text(origText);
                $('#www-url-msg').text('❌ ' + (r.data || 'Erreur')).css('color', '#dc2626');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text(origText);
            $('#www-url-msg').text('❌ Erreur réseau.').css('color', '#dc2626');
        });
    }

    // =========================================================================
    // HELPER : Supprimer une regle
    // =========================================================================
    function removeRule(marker, type, $btn) {
        $btn.prop('disabled', true).text('...');
        $.post(AlestaHtaccess.ajax_url, {
            action: 'alesta_htaccess_remove',
            nonce:  AlestaHtaccess.nonce,
            marker: marker,
        }, function (r) {
            $btn.prop('disabled', false);
            if (r.success) {
                toast('Regle supprimee du .htaccess');
                state[type + '_active'] = false;
                renderBadge(type, false);
            } else {
                alert(r.data && r.data.message ? r.data.message : 'Erreur');
                $btn.text('Desactiver');
            }
        });
    }

    // =========================================================================
    // TOAST
    // =========================================================================
    function toast(msg) {
        var $t = $('<div style="position:fixed;bottom:24px;right:24px;background:#065f46;color:#fff;padding:12px 20px;border-radius:8px;font-size:13px;z-index:99999;box-shadow:0 4px 12px rgba(0,0,0,.2);">' + msg + '</div>');
        $('body').append($t);
        setTimeout(function () { $t.fadeOut(400, function () { $t.remove(); }); }, 3000);
    }

    // =========================================================================
    // MINIFY — Helpers communs
    // =========================================================================

    function minifyMsg($msg, $spinner, text, ok) {
        $spinner.removeClass('is-active');
        $msg.text(text).css('color', ok ? '#16a34a' : '#dc2626');
        setTimeout(function () { $msg.text(''); }, 4000);
    }

    function updateStats(stats) {
        if (!stats) return;
        $('#stat-css-files').text(stats.css_files || 0);
        $('#stat-js-files').text(stats.js_files  || 0);
        $('#stat-total-size').text(stats.total_size || '0 Ko');
    }

    // Toggles génériques pour les 4 interrupteurs Minify
    $(document).on('change', '.minify-switch', function () {
        var type  = $(this).data('type');
        var value = $(this).is(':checked') ? 1 : 0;
        var $lbl  = $('.minify-status-label[data-type="' + type + '"]');
        var $knob = $(this).siblings('.minify-slider').find('span');

        // Feedback immédiat
        $(this).siblings('.minify-slider').css('background', value ? '#1e3a5f' : '#d1d5db');
        $knob.css('left', value ? '23px' : '3px');
        $lbl.text(value ? 'Actif' : 'Inactif').css('color', value ? '#16a34a' : '#9ca3af');

        $.post(AlestaHtaccess.ajax_url, {
            action: 'alesta_minify_toggle',
            nonce:  AlestaHtaccess.minify_nonce,
            type:   type,
            value:  value,
        });
    });

    // Vider le cache (boutons partagés)
    $(document).on('click', '#btn-clear-minify-cache, .btn-clear-minify-cache-js', function () {
        var $btn = $(this).prop('disabled', true);
        $.post(AlestaHtaccess.ajax_url, {
            action: 'alesta_minify_clear_cache',
            nonce:  AlestaHtaccess.minify_nonce,
        }, function (res) {
            $btn.prop('disabled', false);
            if (res.success) {
                toast('🗑 ' + res.data.message);
                updateStats(res.data.stats);
            }
        });
    });

    // =========================================================================
    // MINIFY CSS — Enregistrer
    // =========================================================================

    $('#btn-save-css').on('click', function () {
        var $btn     = $(this).prop('disabled', true);
        var $spinner = $('#spinner-css').addClass('is-active');
        var $msg     = $('#msg-css');

        $.post(AlestaHtaccess.ajax_url, {
            action:   'alesta_minify_save',
            nonce:    AlestaHtaccess.minify_nonce,
            type:     'css',
            excludes: $('#minify-css-excludes').val(),
        }, function (res) {
            $btn.prop('disabled', false);
            minifyMsg($msg, $spinner,
                res.success ? '✅ ' + res.data.message : '❌ ' + (res.data ? res.data.message : 'Erreur.'),
                res.success
            );
        }).fail(function () {
            $btn.prop('disabled', false);
            minifyMsg($msg, $spinner, '❌ Erreur réseau.', false);
        });
    });

    // =========================================================================
    // MINIFY JS — Enregistrer
    // =========================================================================

    $('#btn-save-js').on('click', function () {
        var $btn     = $(this).prop('disabled', true);
        var $spinner = $('#spinner-js').addClass('is-active');
        var $msg     = $('#msg-js');

        $.post(AlestaHtaccess.ajax_url, {
            action:   'alesta_minify_save',
            nonce:    AlestaHtaccess.minify_nonce,
            type:     'js',
            excludes: $('#minify-js-excludes').val(),
        }, function (res) {
            $btn.prop('disabled', false);
            minifyMsg($msg, $spinner,
                res.success ? '✅ ' + res.data.message : '❌ ' + (res.data ? res.data.message : 'Erreur.'),
                res.success
            );
        }).fail(function () {
            $btn.prop('disabled', false);
            minifyMsg($msg, $spinner, '❌ Erreur réseau.', false);
        });
    });

    // =========================================================================
    // MINIFY HTML — Enregistrer
    // =========================================================================

    $('#btn-save-html').on('click', function () {
        var $btn     = $(this).prop('disabled', true);
        var $spinner = $('#spinner-html').addClass('is-active');
        var $msg     = $('#msg-html');

        $.post(AlestaHtaccess.ajax_url, {
            action:             'alesta_minify_save',
            nonce:              AlestaHtaccess.minify_nonce,
            type:               'html',
            remove_comments:    $('#html-remove-comments').is(':checked')   ? 1 : 0,
            remove_whitespace:  $('#html-remove-whitespace').is(':checked') ? 1 : 0,
        }, function (res) {
            $btn.prop('disabled', false);
            minifyMsg($msg, $spinner,
                res.success ? '✅ ' + res.data.message : '❌ ' + (res.data ? res.data.message : 'Erreur.'),
                res.success
            );
        }).fail(function () {
            $btn.prop('disabled', false);
            minifyMsg($msg, $spinner, '❌ Erreur réseau.', false);
        });
    });

    // =========================================================================
    // PRELOAD CSS — Mode radio + Enregistrer
    // =========================================================================

    $('input[name="preload_mode"]').on('change', function () {
        var manual = $(this).val() === 'manual';
        $('#preload-manual-section').toggle(manual);
    });

    $('#btn-save-preload').on('click', function () {
        var $btn     = $(this).prop('disabled', true);
        var $spinner = $('#spinner-preload').addClass('is-active');
        var $msg     = $('#msg-preload');

        $.post(AlestaHtaccess.ajax_url, {
            action:          'alesta_minify_save',
            nonce:           AlestaHtaccess.minify_nonce,
            type:            'preload',
            preload_mode:    $('input[name="preload_mode"]:checked').val() || 'all',
            preload_handles: $('#preload-handles').val(),
            preload_excludes: $('#preload-excludes').val(),
        }, function (res) {
            $btn.prop('disabled', false);
            minifyMsg($msg, $spinner,
                res.success ? '✅ ' + res.data.message : '❌ ' + (res.data ? res.data.message : 'Erreur.'),
                res.success
            );
        }).fail(function () {
            $btn.prop('disabled', false);
            minifyMsg($msg, $spinner, '❌ Erreur réseau.', false);
        });
    });

});
