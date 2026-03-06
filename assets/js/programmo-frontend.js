(function () {
    'use strict';

    var modalRoot = null;
    var modalTitle = null;
    var modalImageWrap = null;
    var modalImage = null;
    var modalMeta = null;
    var modalBadges = null;
    var modalExcerpt = null;
    var modalMoreBtn = null;
    var exportModalRoot = null;

    function ensureOfferModal() {
        if (modalRoot) {
            return;
        }

        var host = document.createElement('div');
        host.innerHTML = '' +
            '<div class="programmo-offer-modal" data-programmo-offer-modal aria-hidden="true">' +
                '<div class="programmo-offer-modal__backdrop" data-programmo-offer-close></div>' +
                '<div class="programmo-offer-modal__content" role="dialog" aria-modal="true" aria-label="Angebotsdetails">' +
                    '<button type="button" class="programmo-offer-modal__close" data-programmo-offer-close aria-label="Schließen">&times;</button>' +
                    '<div class="programmo-offer-modal__image-wrap" hidden>' +
                        '<img class="programmo-offer-modal__image" alt="">' +
                    '</div>' +
                    '<h3 class="programmo-offer-modal__title"></h3>' +
                    '<div class="programmo-offer-modal__meta"></div>' +
                    '<div class="programmo-offer-modal__badges"></div>' +
                    '<p class="programmo-offer-modal__excerpt"></p>' +
                    '<div class="programmo-offer-modal__actions">' +
                        '<a class="programmo-offer-modal__more" href="#" target="_self" rel="noopener">Mehr erfahren</a>' +
                    '</div>' +
                '</div>' +
            '</div>';

        modalRoot = host.firstChild;
        document.body.appendChild(modalRoot);

        modalTitle = modalRoot.querySelector('.programmo-offer-modal__title');
        modalImageWrap = modalRoot.querySelector('.programmo-offer-modal__image-wrap');
        modalImage = modalRoot.querySelector('.programmo-offer-modal__image');
        modalMeta = modalRoot.querySelector('.programmo-offer-modal__meta');
        modalBadges = modalRoot.querySelector('.programmo-offer-modal__badges');
        modalExcerpt = modalRoot.querySelector('.programmo-offer-modal__excerpt');
        modalMoreBtn = modalRoot.querySelector('.programmo-offer-modal__more');

        modalRoot.querySelectorAll('[data-programmo-offer-close]').forEach(function (el) {
            el.addEventListener('click', closeOfferModal);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modalRoot && modalRoot.getAttribute('aria-hidden') === 'false') {
                closeOfferModal();
            }
        });
    }

    function openOfferModal(trigger) {
        ensureOfferModal();

        var title = trigger.getAttribute('data-programmo-offer-title') || '';
        var excerpt = trigger.getAttribute('data-programmo-offer-excerpt') || '';
        var link = trigger.getAttribute('data-programmo-offer-link') || '';
        var image = trigger.getAttribute('data-programmo-offer-image') || '';
        var badgesRaw = trigger.getAttribute('data-programmo-offer-badges') || '';
        var time = trigger.getAttribute('data-programmo-offer-time') || '';
        var date = trigger.getAttribute('data-programmo-offer-date') || '';

        modalTitle.textContent = title;

        if (image) {
            modalImage.src = image;
            modalImage.alt = title;
            modalImageWrap.hidden = false;
        } else {
            modalImage.src = '';
            modalImage.alt = '';
            modalImageWrap.hidden = true;
        }

        var meta = [];
        if (date) meta.push(date);
        if (time) meta.push(time);
        modalMeta.textContent = meta.join(' • ');
        modalMeta.style.display = meta.length ? '' : 'none';

        modalBadges.innerHTML = '';
        if (badgesRaw) {
            var badges = [];
            try { badges = JSON.parse(badgesRaw); } catch (e) {
                /* Legacy fallback: plain '||' delimited string list */
                badges = badgesRaw.split('||').map(function (s) { return { name: s.trim(), color: '', is_person: false }; });
            }
            badges.forEach(function (b) {
                var label = (b.name || '').trim();
                if (!label) return;
                var span = document.createElement('span');
                span.className = 'programmo-offer-modal__badge';
                if (b.is_person) span.className += ' programmo-offer-modal__badge--person';
                span.textContent = label;
                if (b.color) {
                    if (b.is_person) {
                        span.style.setProperty('--badge-person-color', b.color);
                    } else {
                        span.style.background = b.color;
                        span.style.color = '#fff';
                    }
                }
                modalBadges.appendChild(span);
            });
        }
        modalBadges.style.display = modalBadges.childElementCount ? '' : 'none';

        modalExcerpt.textContent = excerpt || 'Keine Kurzbeschreibung verfügbar.';

        if (link) {
            modalMoreBtn.href = link;
            modalMoreBtn.style.display = 'inline-flex';
        } else {
            modalMoreBtn.removeAttribute('href');
            modalMoreBtn.style.display = 'none';
        }

        /* Force a reflow so the browser registers the initial opacity:0 state
           before transitioning to opacity:1 — fixes missing enter animation
           on first open when the modal DOM was just created. */
        void modalRoot.offsetHeight;
        modalRoot.setAttribute('aria-hidden', 'false');
        document.body.classList.add('programmo-modal-open');
        var closeBtn = modalRoot.querySelector('.programmo-offer-modal__close');
        if (closeBtn) closeBtn.focus();
    }

    function closeOfferModal() {
        if (!modalRoot) return;
        modalRoot.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('programmo-modal-open');
    }

    function setupMobileCollapse(root) {
        var rows = root.querySelectorAll('.programmo-weekrow');
        rows.forEach(function (row) {
            var toggle = row.querySelector('.programmo-weekrow__toggle');
            if (!toggle) {
                return;
            }
            toggle.addEventListener('click', function () {
                var isOpen = row.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        });
    }

    function ensureExportModal() {
        if (exportModalRoot) {
            return;
        }

        var host = document.createElement('div');
        host.innerHTML = '' +
            '<div class="programmo-export-modal" data-programmo-export-modal aria-hidden="true">' +
                '<div class="programmo-export-modal__backdrop" data-programmo-export-close></div>' +
                '<div class="programmo-export-modal__content" role="dialog" aria-modal="true" aria-label="PDF Export Vorschau">' +
                    '<div class="programmo-export-modal__toolbar">' +
                        '<strong class="programmo-export-modal__title">PDF Export</strong>' +
                        '<div class="programmo-export-modal__actions">' +
                            '<button type="button" class="button button-primary" data-programmo-export-print>' +
                                '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>' +
                                ' Als PDF drucken' +
                            '</button>' +
                            '<button type="button" class="button" data-programmo-export-close>' +
                                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>' +
                                ' Schlie\u00DFen' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="programmo-export-modal__plan" data-programmo-export-plan></div>' +
                '</div>' +
            '</div>';

        exportModalRoot = host.firstChild;
        document.body.appendChild(exportModalRoot);

        exportModalRoot.querySelectorAll('[data-programmo-export-close]').forEach(function (el) {
            el.addEventListener('click', closeExportModal);
        });

        var printBtn = exportModalRoot.querySelector('[data-programmo-export-print]');
        if (printBtn) {
            printBtn.addEventListener('click', function () {
                document.body.classList.add('programmo-export-printing');
                /* Allow mobile browsers enough time to recalculate styles
                   before capturing the print snapshot */
                setTimeout(function () {
                    window.print();
                }, 350);
            });
        }
    }

    function openExportModal(planEl, fileBase) {
        ensureExportModal();
        if (!planEl || !exportModalRoot) return;

        var safeBase = (fileBase || 'wochenplan').replace(/[^a-zA-Z0-9-_]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
        if (!safeBase) safeBase = 'wochenplan';

        var planHost = exportModalRoot.querySelector('[data-programmo-export-plan]');
        var titleEl = exportModalRoot.querySelector('.programmo-export-modal__title');
        if (titleEl) {
            titleEl.textContent = 'PDF Export – ' + safeBase;
        }

        var clone = planEl.cloneNode(true);
        clone.querySelectorAll('.programmo-weekplan__pdf-export').forEach(function (el) { el.remove(); });
        clone.classList.remove('programmo-weekplan--mobile-collapse');
        clone.querySelectorAll('.programmo-weekrow').forEach(function (row) {
            row.classList.add('is-open');
        });

        planHost.innerHTML = '';
        planHost.appendChild(clone);

        var scrollbarComp = Math.max(0, window.innerWidth - document.documentElement.clientWidth);
        if (scrollbarComp > 0) {
            document.body.style.setProperty('--programmo-scrollbar-comp', scrollbarComp + 'px');
        } else {
            document.body.style.removeProperty('--programmo-scrollbar-comp');
        }

        exportModalRoot.setAttribute('aria-hidden', 'false');
        document.body.classList.add('programmo-modal-open');
    }

    function closeExportModal() {
        if (!exportModalRoot) return;
        exportModalRoot.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('programmo-modal-open');
        document.body.classList.remove('programmo-export-printing');
        document.body.style.removeProperty('--programmo-scrollbar-comp');
    }

    /* ------------------------------------------------------------------ */
    /*  Hover Tooltip (desktop only)                                      */
    /* ------------------------------------------------------------------ */
    var tooltipEl = null;
    var tooltipTimeout = null;
    var canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    function ensureTooltip() {
        if (tooltipEl) return;
        tooltipEl = document.createElement('div');
        tooltipEl.className = 'programmo-tooltip';
        tooltipEl.setAttribute('role', 'tooltip');
        tooltipEl.setAttribute('aria-hidden', 'true');
        tooltipEl.innerHTML = '' +
            '<div class="programmo-tooltip__image-wrap" hidden>' +
                '<img class="programmo-tooltip__image" alt="" loading="eager">' +
            '</div>' +
            '<div class="programmo-tooltip__body">' +
                '<strong class="programmo-tooltip__title"></strong>' +
                '<span class="programmo-tooltip__meta"></span>' +
                '<div class="programmo-tooltip__badges"></div>' +
                '<span class="programmo-tooltip__excerpt"></span>' +
            '</div>';
        document.body.appendChild(tooltipEl);
    }

    function showTooltip(trigger) {
        ensureTooltip();

        var title = trigger.getAttribute('data-programmo-offer-title') || '';
        var excerpt = trigger.getAttribute('data-programmo-offer-excerpt') || '';
        var image = trigger.getAttribute('data-programmo-offer-image') || '';
        var time = trigger.getAttribute('data-programmo-offer-time') || '';
        var date = trigger.getAttribute('data-programmo-offer-date') || '';
        var badgesRaw = trigger.getAttribute('data-programmo-offer-badges') || '';

        var titleEl = tooltipEl.querySelector('.programmo-tooltip__title');
        var excerptEl = tooltipEl.querySelector('.programmo-tooltip__excerpt');
        var metaEl = tooltipEl.querySelector('.programmo-tooltip__meta');
        var badgesEl = tooltipEl.querySelector('.programmo-tooltip__badges');
        var imgWrap = tooltipEl.querySelector('.programmo-tooltip__image-wrap');
        var imgEl = tooltipEl.querySelector('.programmo-tooltip__image');

        titleEl.textContent = title;

        /* Badges (team / category) */
        badgesEl.innerHTML = '';
        if (badgesRaw) {
            var badges = [];
            try { badges = JSON.parse(badgesRaw); } catch (e) {
                badges = badgesRaw.split('||').map(function (s) { return { name: s.trim(), color: '', is_person: false }; });
            }
            badges.forEach(function (b) {
                var label = (b.name || '').trim();
                if (!label) return;
                var span = document.createElement('span');
                span.className = 'programmo-tooltip__badge';
                if (b.is_person) span.className += ' programmo-tooltip__badge--person';
                span.textContent = label;
                if (b.color) {
                    if (b.is_person) {
                        span.style.setProperty('--badge-person-color', b.color);
                    } else {
                        span.style.background = b.color;
                    }
                }
                badgesEl.appendChild(span);
            });
        }
        badgesEl.style.display = badgesEl.childElementCount ? 'flex' : 'none';

        var meta = [];
        if (time) meta.push(time);
        if (date) meta.push(date);
        metaEl.textContent = meta.join(' · ');
        metaEl.style.display = meta.length ? '' : 'none';

        excerptEl.textContent = excerpt || '';
        excerptEl.style.display = excerpt ? '' : 'none';

        if (image) {
            imgEl.src = image;
            imgEl.alt = title;
            imgWrap.hidden = false;
        } else {
            imgEl.src = '';
            imgWrap.hidden = true;
        }

        tooltipEl.setAttribute('aria-hidden', 'false');

        /* Position: centered above the trigger, clamped to viewport */
        var rect = trigger.getBoundingClientRect();
        var tipW = 280;
        var left = rect.left + rect.width / 2 - tipW / 2;
        left = Math.max(8, Math.min(left, window.innerWidth - tipW - 8));
        tooltipEl.style.left = left + 'px';
        tooltipEl.style.top = (rect.top + window.scrollY) + 'px';
    }

    function hideTooltip() {
        if (!tooltipEl) return;
        tooltipEl.setAttribute('aria-hidden', 'true');
    }

    function setupTooltips() {
        if (!canHover) return;

        document.addEventListener('mouseover', function (e) {
            var trigger = e.target.closest('.programmo-weekplan--tooltips [data-programmo-offer-trigger="1"]');
            if (!trigger) return;
            clearTimeout(tooltipTimeout);
            tooltipTimeout = setTimeout(function () {
                showTooltip(trigger);
            }, 220);
        });

        document.addEventListener('mouseout', function (e) {
            var trigger = e.target.closest('[data-programmo-offer-trigger="1"]');
            if (!trigger) return;
            clearTimeout(tooltipTimeout);
            hideTooltip();
        });
    }

    function init() {
        document.querySelectorAll('.programmo-weekplan--mobile-collapse').forEach(setupMobileCollapse);
        setupTooltips();

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-programmo-offer-trigger="1"]');
            if (!trigger) {
                return;
            }
            event.preventDefault();
            openOfferModal(trigger);
        });

        document.addEventListener('click', function (event) {
            var exportBtn = event.target.closest('[data-programmo-export-pdf="1"]');
            if (!exportBtn) {
                return;
            }
            event.preventDefault();
            var root = exportBtn.closest('.programmo-weekplan');
            var fileBase = exportBtn.getAttribute('data-programmo-export-file') || '';
            openExportModal(root, fileBase);
        });

        window.addEventListener('afterprint', function () {
            document.body.classList.remove('programmo-export-printing');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
