/**
 * Alesta AI — Talk to Me (front-end widget).
 * Vanilla JS, no dependency.
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    function init(root) {
        var main     = root.querySelector('.alesta-ttm__main');
        var closeBtn = root.querySelector('.alesta-ttm__panel-close');
        if (!main) return;

        function open()  {
            root.classList.add('is-open');
            main.setAttribute('aria-expanded', 'true');
            var panel = root.querySelector('.alesta-ttm__panel');
            if (panel) panel.setAttribute('aria-hidden', 'false');
        }
        function close() {
            root.classList.remove('is-open');
            main.setAttribute('aria-expanded', 'false');
            var panel = root.querySelector('.alesta-ttm__panel');
            if (panel) panel.setAttribute('aria-hidden', 'true');
        }
        function toggle() {
            if (root.classList.contains('is-open')) close();
            else open();
        }

        main.addEventListener('click', function (e) {
            e.preventDefault();
            toggle();
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                close();
            });
        }

        // Close on outside click.
        document.addEventListener('click', function (e) {
            if (!root.contains(e.target) && root.classList.contains('is-open')) close();
        });

        // Close on Escape.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && root.classList.contains('is-open')) close();
        });
    }

    ready(function () {
        var widgets = document.querySelectorAll('.alesta-ttm');
        for (var i = 0; i < widgets.length; i++) init(widgets[i]);
    });
}());
