(function () {
    'use strict';

    function initializeDebugbar() {
        var bar = document.getElementById('psoft-debugbar');
        if (!bar || bar.getAttribute('data-debugbar-ready') === '1') {
            return;
        }

        bar.setAttribute('data-debugbar-ready', '1');
        var body = document.body;
        var panel = bar.querySelector('.psoft-debugbar__panel');
        var detailButtons = bar.querySelectorAll('[data-debugbar-action="details"]');
        var tabs = bar.querySelectorAll('[data-debugbar-tab]');
        var sections = bar.querySelectorAll('[data-debugbar-panel]');
        var collapsed = window.localStorage
            && (window.localStorage.getItem('psoftDebugbarCollapsed') === '1'
                || window.localStorage.getItem('psoftDevbarCollapsed') === '1');

        function selectTab(name) {
            Array.prototype.forEach.call(tabs, function (tab) {
                tab.classList.toggle('is-active', tab.getAttribute('data-debugbar-tab') === name);
            });
            Array.prototype.forEach.call(sections, function (section) {
                section.hidden = section.getAttribute('data-debugbar-panel') !== name;
            });
        }

        function setDetails(open, section) {
            if (!panel) {
                return;
            }
            panel.hidden = !open;
            Array.prototype.forEach.call(detailButtons, function (button) {
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            if (open) {
                var requested = section || (tabs[0] && tabs[0].getAttribute('data-debugbar-tab'));
                if (requested) {
                    selectTab(requested);
                }
            }
        }

        function setCollapsed(state) {
            collapsed = state;
            bar.classList.toggle('psoft-debugbar--collapsed', state);
            body.classList.toggle('psoft-debugbar-collapsed', state);
            if (state) {
                setDetails(false);
            }
            if (window.localStorage) {
                window.localStorage.setItem('psoftDebugbarCollapsed', state ? '1' : '0');
            }
        }

        body.classList.add('psoft-debugbar-visible');
        setCollapsed(collapsed);

        bar.addEventListener('click', function (event) {
            var target = event.target.closest('button');
            if (!target) {
                return;
            }

            var action = target.getAttribute('data-debugbar-action');
            var section = target.getAttribute('data-debugbar-section');
            var tab = target.getAttribute('data-debugbar-tab');

            if (action === 'details') {
                if (collapsed) {
                    setCollapsed(false);
                }
                setDetails(panel ? panel.hidden : false);
            } else if (action === 'close-details') {
                setDetails(false);
            } else if (action === 'collapse') {
                setCollapsed(true);
            } else if (section) {
                if (collapsed) {
                    setCollapsed(false);
                }
                setDetails(true, section);
            } else if (tab) {
                selectTab(tab);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeDebugbar);
    } else {
        initializeDebugbar();
    }
}());
