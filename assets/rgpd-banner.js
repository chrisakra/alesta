/* ── Bannière RGPD — Alesta AI (frontend) ── */
(function () {
    'use strict';

    var COOKIE = 'alesta_rgpd_consent';
    var cfg    = window.AlestaRGPD || {};

    /* ── Utilitaires cookie ── */
    function getCookie(name) {
        var m = document.cookie.match('(?:^|;\\s*)' + name.replace(/[[\]{}()*+?.\\^$|]/g, '\\$&') + '=([^;]*)');
        try { return m ? JSON.parse(decodeURIComponent(m[1])) : null; } catch (e) { return null; }
    }

    function setCookie(name, value, days) {
        var exp = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = name + '=' + encodeURIComponent(JSON.stringify(value))
                        + '; expires=' + exp + '; path=/; SameSite=Lax';
    }

    /* ── Dispatch de l'événement de consentement ── */
    function fireEvent(consent) {
        if (window.CustomEvent && window.dispatchEvent) {
            window.dispatchEvent(new CustomEvent('alestaRgpdConsent', { detail: consent, bubbles: true }));
        }
        // Compatibilité GTM dataLayer
        if (window.dataLayer) {
            window.dataLayer.push({
                event:             'alestaRgpdConsent',
                rgpd_necessary:    true,
                rgpd_analytics:    !!consent.analytics,
                rgpd_marketing:    !!consent.marketing,
                rgpd_preferences:  !!consent.preferences,
            });
        }
    }

    /* ── Sauvegarde du consentement ── */
    function saveConsent(cats) {
        var consent = {
            necessary:   true,
            analytics:   !!cats.analytics,
            marketing:   !!cats.marketing,
            preferences: !!cats.preferences,
            timestamp:   Date.now(),
            version:     '1.0',
        };
        setCookie(COOKIE, consent, cfg.lifetime || 365);
        fireEvent(consent);
        return consent;
    }

    /* ── Masquer la bannière ── */
    function hideBanner(banner) {
        banner.classList.remove('alesta-rgpd--visible');
        banner.classList.add('alesta-rgpd--hiding');
        setTimeout(function () {
            banner.style.display = 'none';
            banner.setAttribute('aria-hidden', 'true');
        }, 400);
    }

    /* ── Initialisation ── */
    function init() {
        /* Consentement déjà donné → relancer l'événement et sortir */
        var existing = getCookie(COOKIE);
        if (existing) {
            fireEvent(existing);
            return;
        }

        var banner = document.getElementById('alesta-rgpd-banner');
        if (!banner) return;

        /* Afficher après 600ms */
        setTimeout(function () {
            banner.removeAttribute('style');
            banner.removeAttribute('aria-hidden');
            banner.classList.add('alesta-rgpd--visible');
        }, 600);

        var btnAccept    = document.getElementById('alesta-rgpd-accept');
        var btnReject    = document.getElementById('alesta-rgpd-reject');
        var btnCustomize = document.getElementById('alesta-rgpd-customize');
        var btnSave      = document.getElementById('alesta-rgpd-save');
        var panel        = document.getElementById('alesta-rgpd-panel');

        /* Tout accepter */
        if (btnAccept) {
            btnAccept.addEventListener('click', function () {
                saveConsent({ analytics: true, marketing: true, preferences: true });
                hideBanner(banner);
            });
        }

        /* Tout refuser */
        if (btnReject) {
            btnReject.addEventListener('click', function () {
                saveConsent({ analytics: false, marketing: false, preferences: false });
                hideBanner(banner);
            });
        }

        /* Personnaliser — ouvrir / fermer le panneau */
        if (btnCustomize && panel) {
            btnCustomize.addEventListener('click', function () {
                var isOpen = panel.classList.contains('alesta-rgpd__panel--open');
                panel.classList.toggle('alesta-rgpd__panel--open', !isOpen);
                panel.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
                btnCustomize.textContent = isOpen
                    ? (btnCustomize.dataset.originalText || 'Personnaliser')
                    : '✕ Fermer';
                if (!btnCustomize.dataset.originalText) {
                    btnCustomize.dataset.originalText = btnCustomize.textContent;
                }
            });
        }

        /* Toggles dans le panneau */
        banner.querySelectorAll('.alesta-rgpd__toggle:not(.alesta-rgpd__toggle--locked)').forEach(function (t) {
            t.addEventListener('click', function () {
                var on = this.getAttribute('aria-checked') === 'true';
                this.setAttribute('aria-checked', on ? 'false' : 'true');
                this.classList.toggle('alesta-rgpd__toggle--on', !on);
            });
        });

        /* Enregistrer les choix depuis le panneau */
        if (btnSave) {
            btnSave.addEventListener('click', function () {
                var cats = {};
                banner.querySelectorAll('.alesta-rgpd__toggle:not(.alesta-rgpd__toggle--locked)').forEach(function (t) {
                    var cat = t.getAttribute('data-category');
                    if (cat) cats[cat] = t.getAttribute('aria-checked') === 'true';
                });
                saveConsent(cats);
                hideBanner(banner);
            });
        }

        /* Accessibilité : fermeture avec Échap */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && banner.classList.contains('alesta-rgpd--visible')) {
                saveConsent({ analytics: false, marketing: false, preferences: false });
                hideBanner(banner);
            }
        });
    }

    /* ── Point d'entrée ── */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
