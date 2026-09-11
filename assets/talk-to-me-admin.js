jQuery(function ($) {

    /* ── Tabs ─────────────────────────────────────────────────────── */
    $('.alesta-ttm-tab').on('click', function () {
        var tab = $(this).data('tab');
        $('.alesta-ttm-tab').removeClass('is-active');
        $(this).addClass('is-active');
        $('.alesta-ttm-panel').removeClass('is-active');
        $('.alesta-ttm-panel[data-panel="' + tab + '"]').addClass('is-active');
    });

    /* ── Visual selectors (mode + position) ───────────────────────── */
    $('.alesta-ttm-mode, .alesta-ttm-pos').on('click', function () {
        var $card = $(this);
        var input = $card.find('input[type="radio"]');
        var group = input.attr('name');
        $('input[name="' + group + '"]').each(function () {
            $(this).closest('label').removeClass('is-active');
        });
        $card.addClass('is-active');
        input.prop('checked', true);
    });

    /* ── Color picker ─────────────────────────────────────────────── */
    if ($.fn.wpColorPicker) {
        $('#ttm-color').wpColorPicker();
    }

    /* ── Sortable channels ────────────────────────────────────────── */
    if ($.fn.sortable) {
        $('#ttm-channels').sortable({
            handle: '.alesta-ttm-handle',
            placeholder: 'alesta-ttm-channel-row',
            forcePlaceholderSize: true,
            tolerance: 'pointer',
            opacity: .85,
            cursor: 'grabbing'
        });
    }

    /* ── Auto-enable a channel as soon as user types a value ──────── */
    /*    Prevents the "I filled the field but the widget doesn't show" */
    /*    pitfall where users miss the per-channel toggle.              */
    $(document).on('input change', '.ttm-ch-value', function () {
        var $row    = $(this).closest('.alesta-ttm-channel-row');
        var $toggle = $row.find('.ttm-ch-enabled');
        if ($(this).val().trim() !== '' && !$toggle.is(':checked')) {
            $toggle.prop('checked', true);
        }
    });

    /* ── Page filter — show/hide IDs field ────────────────────────── */
    $('#ttm-page-filter').on('change', function () {
        $('#ttm-page-ids-wrap').toggle($(this).val() !== 'all');
    });

    /* ── Hours toggle ─────────────────────────────────────────────── */
    $('#ttm-hours-enabled').on('change', function () {
        $('#ttm-hours-wrap').toggle($(this).is(':checked'));
    });

    /* ── Avatar media picker ──────────────────────────────────────── */
    $('#ttm-avatar-pick').on('click', function (e) {
        e.preventDefault();
        if (!window.wp || !wp.media) return;
        var frame = wp.media({
            title: 'Choisir une photo',
            button: { text: 'Utiliser cette image' },
            library: { type: 'image' },
            multiple: false
        });
        frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            $('#ttm-avatar-url').val(att.url);
        });
        frame.open();
    });

    /* ── Build payload ────────────────────────────────────────────── */
    function buildPayload() {
        var pageIds = ($('#ttm-page-ids').val() || '')
            .split(',')
            .map(function (s) { return parseInt(s.trim(), 10); })
            .filter(function (n) { return !isNaN(n) && n > 0; });

        var hours = {};
        $('.alesta-ttm-hours tr').each(function () {
            var $row = $(this);
            var day  = $row.data('day');
            if (!day) return;
            hours[day] = {
                enabled: $row.find('.ttm-day-enabled').is(':checked'),
                start:   $row.find('.ttm-day-start').val(),
                end:     $row.find('.ttm-day-end').val()
            };
        });

        var channels = {};
        $('#ttm-channels .alesta-ttm-channel-row').each(function (idx) {
            var $row = $(this);
            var key  = $row.data('channel');
            channels[key] = {
                enabled: $row.find('.ttm-ch-enabled').is(':checked'),
                value:   $row.find('.ttm-ch-value').val()   || '',
                message: $row.find('.ttm-ch-message').val() || '',
                subject: $row.find('.ttm-ch-subject').val() || '',
                label:   $row.find('.ttm-ch-label').val()   || '',
                order:   idx + 1
            };
        });

        return {
            enabled:         $('#ttm-enabled').is(':checked'),
            mode:            $('input[name="ttm-mode"]:checked').val(),
            position:        $('input[name="ttm-position"]:checked').val(),
            main_color:      $('#ttm-color').val(),
            main_label:      $('#ttm-main-label').val(),
            avatar_url:      $('#ttm-avatar-url').val(),
            avatar_name:     $('#ttm-avatar-name').val(),
            avatar_status:   $('#ttm-avatar-status').val(),
            animation:       $('#ttm-animation').val(),
            show_mobile:     $('#ttm-show-mobile').is(':checked'),
            show_desktop:    $('#ttm-show-desktop').is(':checked'),
            page_filter:     $('#ttm-page-filter').val(),
            page_ids:        pageIds,
            hours_enabled:   $('#ttm-hours-enabled').is(':checked'),
            offline_message: $('#ttm-offline-msg').val(),
            hide_branding:   $('#ttm-hide-branding').is(':checked'),
            hours:           hours,
            channels:        channels
        };
    }

    /* ── Save ─────────────────────────────────────────────────────── */
    $('#ttm-save').on('click', function () {
        var $btn  = $(this);
        var $fb   = $('#ttm-feedback');
        var orig  = $btn.text();
        $btn.prop('disabled', true).text(AlestaTtm.i18n.saving);
        $fb.removeClass('is-ok is-error').text('');

        $.post(AlestaTtm.ajax_url, {
            action: 'alesta_ttm_save',
            nonce:  AlestaTtm.nonce,
            data:   JSON.stringify(buildPayload())
        }, function (r) {
            $btn.prop('disabled', false).text(orig);
            if (r && r.success) {
                $fb.addClass('is-ok').text(r.data && r.data.message ? r.data.message : AlestaTtm.i18n.saved);
            } else {
                $fb.addClass('is-error').text((r && r.data && r.data.message) ? r.data.message : AlestaTtm.i18n.error);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text(orig);
            $fb.addClass('is-error').text(AlestaTtm.i18n.error);
        });
    });
});
