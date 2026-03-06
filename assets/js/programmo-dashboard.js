/**
 * ProgrammO – Interactive Dashboard
 *
 * Features:
 *  - Inline slot creation per weekday column
 *  - Drag & drop Angebote from the sidebar onto slots
 *  - Delete / unlink events from slots
 *  - All operations via REST API (no page reload)
 */
(function () {
    'use strict';

    /* ================================================================ */
    /*  Config & Data (injected via wp_localize_script)                 */
    /* ================================================================ */
    var cfg = window.programmoDashboard || {};
    var restBase = cfg.restBase || '/wp-json/programmo/v1';
    var nonce = cfg.nonce || '';
    var weekdays = cfg.weekdays || {};
    var weekdayKeys = cfg.weekdayKeys || ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
    var jugendTerms = Array.isArray(cfg.jugendTerms) ? cfg.jugendTerms : [];
    var programmoPeople = Array.isArray(cfg.programmoPeople) ? cfg.programmoPeople : [];
    var okjaPeople = Array.isArray(cfg.okjaPeople) ? cfg.okjaPeople : [];

    /* State */
    var slots = [];
    var events = [];
    var areas = [];
    var dayColors = {};
    var draggedEvent = null;
    var sidebarSourceFilter = 'all';
    var sidebarCreateOutsideHandler = null;
    var mediaFrame = null;

    /* DOM references */
    var matrixEl = document.getElementById('programmo-interactive-matrix');
    var sidebarEl = document.getElementById('programmo-angebote-pool');
    var sidebarSearchEl = document.getElementById('programmo-angebote-search');
    var toggleSaturdayEl = document.getElementById('programmo-toggle-saturday');
    var toggleSundayEl = document.getElementById('programmo-toggle-sunday');

    if (!matrixEl) return; // Not on dashboard

    var visibleWeekdayKeys = weekdayKeys.slice();
    var weekendToggleStorageKey = 'programmo.dashboard.weekendVisibility.v1';

    /* ================================================================ */
    /*  REST helpers                                                    */
    /* ================================================================ */

    function api(method, path, body) {
        var opts = {
            method: method,
            headers: {
                'X-WP-Nonce': nonce,
                'Content-Type': 'application/json',
            },
        };
        if (body) {
            opts.body = JSON.stringify(body);
        }
        return fetch(restBase + path, opts).then(function (res) {
            if (!res.ok) {
                return res.json().then(function (err) {
                    throw new Error(err.message || err.error || 'API Error');
                });
            }
            return res.json();
        });
    }

    /* ================================================================ */
    /*  Initial data load                                              */
    /* ================================================================ */

    function loadAll() {
        bindWeekendToggles();
        return Promise.all([
            api('GET', '/slots'),
            api('GET', '/events'),
            api('GET', '/areas'),
            api('GET', '/day-colors'),
        ]).then(function (results) {
            slots = results[0];
            events = results[1];
            areas = results[2];
            dayColors = results[3] || {};
            render();
        }).catch(function (err) {
            matrixEl.innerHTML = '<div class="notice notice-error inline"><p>Fehler beim Laden: ' + escHtml(err.message) + '</p></div>';
        });
    }

    /* ================================================================ */
    /*  Render: Week Matrix                                            */
    /* ================================================================ */

    function render() {
        renderMatrix();
        renderSidebar();
    }

    function renderMatrix() {
        var html = '';

        matrixEl.style.setProperty('--programmo-visible-cols', visibleWeekdayKeys.length);

        visibleWeekdayKeys.forEach(function (dayKey) {
            var label = weekdays[dayKey] || dayKey;
            var daySlots = slots
                .filter(function (s) { return s.weekday === dayKey; })
                .sort(function (a, b) { return (a.start || '').localeCompare(b.start || ''); });

            var dayBg = dayColors[dayKey] || '';
            var swatchStyle = dayBg ? dayBg : 'linear-gradient(135deg, #2279d4 0%, #2d8ae7 40%, #4cc0e8 100%)';
            html += '<div class="programmo-week-column" data-weekday="' + dayKey + '">';
            html += '<div class="programmo-week-column__header">';
            html += escHtml(label);
            html += '<span class="programmo-day-color-swatch" style="background:' + escHtml(swatchStyle) + '" title="Tagesfarbe"></span>';
            html += '<button type="button" class="programmo-day-color-edit" data-day="' + dayKey + '" title="Tagesfarbe bearbeiten">🎨</button>';
            html += '</div>';

            /* Existing slots */
            daySlots.forEach(function (slot) {
                html += renderSlotCard(slot);
            });

            /* Add-slot button & inline form */
            html += '<div class="programmo-add-slot-area">';
            html += '<button type="button" class="programmo-add-slot-btn" data-day="' + dayKey + '" title="Neuer Slot">';
            html += '<span class="dashicons dashicons-plus-alt2"></span> Slot hinzufügen';
            html += '</button>';
            html += '<div class="programmo-add-slot-form" data-day="' + dayKey + '" style="display:none">';
            html += renderInlineForm(dayKey);
            html += '</div>';
            html += '</div>';

            html += '</div>'; // column end
        });

        matrixEl.innerHTML = html;
        bindMatrixEvents();
    }

    function bindWeekendToggles() {
        function loadSavedWeekendVisibility() {
            try {
                var raw = window.localStorage.getItem(weekendToggleStorageKey);
                if (!raw) return null;
                var data = JSON.parse(raw);
                if (!data || typeof data !== 'object') return null;
                return {
                    saturday: data.saturday !== false,
                    sunday: data.sunday !== false,
                };
            } catch (e) {
                return null;
            }
        }

        function saveWeekendVisibility() {
            try {
                if (!toggleSaturdayEl && !toggleSundayEl) return;
                var state = {
                    saturday: toggleSaturdayEl ? !!toggleSaturdayEl.checked : true,
                    sunday: toggleSundayEl ? !!toggleSundayEl.checked : true,
                };
                window.localStorage.setItem(weekendToggleStorageKey, JSON.stringify(state));
            } catch (e) {
                /* ignore storage errors */
            }
        }

        function refreshVisibleDays() {
            visibleWeekdayKeys = weekdayKeys.filter(function (dayKey) {
                if (dayKey === 'saturday' && toggleSaturdayEl && !toggleSaturdayEl.checked) {
                    return false;
                }
                if (dayKey === 'sunday' && toggleSundayEl && !toggleSundayEl.checked) {
                    return false;
                }
                return true;
            });
        }

        var saved = loadSavedWeekendVisibility();
        if (saved) {
            if (toggleSaturdayEl) toggleSaturdayEl.checked = !!saved.saturday;
            if (toggleSundayEl) toggleSundayEl.checked = !!saved.sunday;
        }

        refreshVisibleDays();

        [toggleSaturdayEl, toggleSundayEl].forEach(function (el) {
            if (!el || el.dataset.bound === '1') return;
            el.addEventListener('change', function () {
                refreshVisibleDays();
                saveWeekendVisibility();
                renderMatrix();
            });
            el.dataset.bound = '1';
        });
    }

    function renderSlotCard(slot) {
        var slotEvents = slot.events || [];
        var hasWarning = slotEvents.some(function (ev) { return ev.has_warning; });
        var warnClass = hasWarning ? ' programmo-week-slot--warn' : '';
        var html = '';
        html += '<div class="programmo-week-slot programmo-week-slot--interactive' + warnClass + '" ';
        html += 'data-slot-id="' + slot.id + '">';

        /* Time + Age Range */
        html += '<div class="programmo-week-slot__time">';
        var timeText = (slot.start || '?') + ' \u2013 ' + (slot.end || '?');
        if (slot.age_range) {
            timeText += ' (' + slot.age_range + ')';
        }
        html += escHtml(timeText);
        html += '<span class="programmo-slot-actions">';
        html += '<a href="' + escHtml(slot.edit_url || '#') + '" class="programmo-slot-action" title="Bearbeiten">✎</a>';
        html += '<button type="button" class="programmo-slot-action programmo-slot-delete" data-slot-id="' + slot.id + '" title="Löschen">✕</button>';
        html += '</span>';
        html += '</div>';

        /* Area */
        if (slot.area_title) {
            html += '<div class="programmo-week-slot__area" style="background:' + escHtml(slot.area_color || '#bde9ff') + '">';
            html += escHtml(slot.area_title);
            html += '</div>';
        }

        /* People (from area) */
        if (slot.area_people && slot.area_people.length > 0) {
            html += '<div class="programmo-week-slot__people">';
            html += '<span class="dashicons dashicons-groups"></span> ';
            html += escHtml(slot.area_people.join(', '));
            html += '</div>';
        }

        /* Events (multiple) */
        if (slotEvents.length > 0) {
            html += '<div class="programmo-week-slot__events">';
            slotEvents.forEach(function (ev) {
                /* Sync fresh color from sidebar events array */
                var freshEv = events.find(function (e) { return e.id === ev.id; });
                var currentColor = freshEv ? freshEv.event_color : ev.event_color;
                var evWarnClass = ev.has_warning ? ' programmo-week-slot__offer--warn' : '';
                var offerBg = currentColor ? ' style="background:' + escHtml(currentColor) + '"' : '';
                var offerStart = ev.offer_start || slot.start || '';
                var offerEnd = ev.offer_end || slot.end || '';
                var offerTime = ev.offer_time || ((offerStart || '?') + ' – ' + (offerEnd || '?'));
                html += '<div class="programmo-week-slot__offer' + evWarnClass + '"' + offerBg + '>';
                html += '<div class="programmo-offer-body">';
                html += '<span class="programmo-offer-time">' + escHtml(offerTime) + '</span>';
                if (ev.image_url) {
                    html += '<img class="programmo-offer-thumb" src="' + escHtml(ev.image_url) + '" alt="">';
                }
                html += '<span class="programmo-offer-text">' + escHtml(ev.title) + '</span>';
                html += '</div>';
                html += '<span class="programmo-offer-actions">';
                html += '<button type="button" class="programmo-edit-offer-time" data-slot-id="' + slot.id + '" data-event-id="' + ev.id + '" data-offer-start="' + escHtml(offerStart) + '" data-offer-end="' + escHtml(offerEnd) + '" title="Angebotszeit bearbeiten">✎</button>';
                html += '<button type="button" class="programmo-unlink-event" data-slot-id="' + slot.id + '" data-event-id="' + ev.id + '" title="Angebot entfernen">✕</button>';
                if (ev.has_warning) {
                    html += '<span class="programmo-offer-warn" title="A-Event ohne Angebot">⚠</span>';
                }
                html += '</span>';
                html += '</div>';
            });
            html += '</div>';
        }

        /* Drop zone – always visible so more events can be added */
        html += '<div class="programmo-drop-zone" data-slot-id="' + slot.id + '">';
        html += '<span class="dashicons dashicons-move"></span> ';
        html += slotEvents.length > 0 ? 'Weiteres Angebot hinzufügen' : 'Angebot hierhin ziehen';
        html += '</div>';

        html += '</div>';
        return html;
    }

    function renderInlineForm(dayKey) {
        var html = '';
        html += '<div class="programmo-inline-form">';

        html += '<div class="programmo-inline-form__time-row">';
        html += '<div class="programmo-inline-form__field">';
        html += '<label>Von</label>';
        html += '<input type="time" class="programmo-inline-start" value="14:00">';
        html += '</div>';
        html += '<div class="programmo-inline-form__field">';
        html += '<label>Bis</label>';
        html += '<input type="time" class="programmo-inline-end" value="17:00">';
        html += '</div>';
        html += '</div>';

        html += '<div class="programmo-inline-form__field">';
        html += '<label>Alter</label>';
        html += '<input type="text" class="programmo-inline-age-range" placeholder="z.B. ab 6 Jahre">';
        html += '</div>';

        html += '<div class="programmo-inline-form__field">';
        html += '<label>Bereich</label>';
        html += '<select class="programmo-inline-area">';
        html += '<option value="0">&mdash; Ohne &mdash;</option>';
        areas.forEach(function (a) {
            html += '<option value="' + a.id + '">' + escHtml(a.title) + '</option>';
        });
        html += '</select>';
        html += '</div>';

        html += '<div class="programmo-inline-form__field">';
        html += '<label>Personen (optional)</label>';
        html += '<select class="programmo-inline-jugendarbeit" multiple>';
        jugendTerms.forEach(function (term) {
            html += '<option value="' + escHtml(String(term.id)) + '">' + escHtml(term.label || '') + '</option>';
        });
        html += '</select>';
        html += '</div>';

        html += '<div class="programmo-inline-form__actions">';
        html += '<button type="button" class="button button-primary programmo-inline-save" data-day="' + dayKey + '">Anlegen</button>';
        html += '<button type="button" class="button programmo-inline-cancel" data-day="' + dayKey + '">Abbrechen</button>';
        html += '</div>';

        html += '</div>';
        return html;
    }

    /* ================================================================ */
    /*  Render: Angebote Sidebar                                       */
    /* ================================================================ */

    function renderSidebar() {
        if (!sidebarEl) return;

        var html = renderCreateOfferPanel();
        html += renderSourceFilter();
        html += '<div class="programmo-sidebar-offer-list">';
        events.forEach(function (ev) {
            var typeClass = 'programmo-chip--angebot';
            if (ev.type === 'angebotsevent') {
                typeClass = 'programmo-chip--event';
            } else if (ev.type === 'programmo_offer') {
                typeClass = 'programmo-chip--local';
            }
            var warnClass = ev.has_warning ? ' programmo-chip--warn' : '';
            html += '<div class="programmo-chip ' + typeClass + warnClass + '" ';
            html += 'draggable="true" ';
            html += 'data-event-id="' + ev.id + '" ';
            html += 'data-source="' + escHtml(ev.source || 'okja') + '" ';
            html += 'title="' + escHtml(ev.label) + '">';
            if (ev.image_url) {
                html += '<img class="programmo-chip__thumb" src="' + escHtml(ev.image_url) + '" alt="">';
            } else {
                html += '<span class="programmo-chip__icon dashicons dashicons-move"></span>';
            }
            html += '<span class="programmo-chip__text">' + escHtml(ev.title) + '</span>';
            if (ev.has_warning) {
                html += '<span class="programmo-chip__warn" title="Kein verknüpftes Angebot">⚠</span>';
            }
            /* Color swatch + edit icon */
            var swatchBg = ev.event_color || '';
            html += '<span class="programmo-chip__color-swatch" style="background:' + escHtml(swatchBg || '#ccc') + '"'
                  + (swatchBg ? '' : ' data-empty="1"') + '></span>';
            if (ev.type === 'programmo_offer') {
                html += '<button type="button" class="programmo-chip__edit" data-event-id="' + ev.id + '" title="Eigenes Angebot bearbeiten">✎</button>';
            }
            html += '<button type="button" class="programmo-chip__color-edit" data-event-id="' + ev.id + '" title="Farbe / Gradient bearbeiten">🎨</button>';
            html += '</div>';
        });

        if (events.length === 0) {
            html += '<p class="programmo-pool-empty">Keine Angebote gefunden. Du kannst direkt hier ein eigenes Angebot anlegen.</p>';
        }

        html += '</div>';

        sidebarEl.innerHTML = html;
        bindSidebarEvents();
        applySidebarSearchFilter();
    }

    function renderCreateOfferPanel() {
        var html = '';
        html += '<div class="programmo-sidebar-create">';
        html += '<button type="button" class="button button-secondary programmo-create-offer-toggle">Eigenes Angebot anlegen</button>';
        html += '<div class="programmo-sidebar-create__form" hidden>';
        html += '<div class="programmo-sidebar-create__field">';
        html += '<label>Titel</label>';
        html += '<input type="text" class="programmo-create-offer-title" placeholder="z.B. Kreativwerkstatt">';
        html += '</div>';
        html += '<div class="programmo-sidebar-create__field">';
        html += '<label>Kurzbeschreibung</label>';
        html += '<textarea class="programmo-create-offer-description" rows="4" placeholder="Kurze Beschreibung für das Angebots-Modal"></textarea>';
        html += '</div>';
        html += '<div class="programmo-sidebar-create__field">';
        html += '<label>Bild</label>';
        html += '<div class="programmo-create-offer-image-picker">';
        html += '<div class="programmo-create-offer-image-preview" hidden><img class="programmo-create-offer-image-preview-img" src="" alt=""></div>';
        html += '<input type="hidden" class="programmo-create-offer-image-id" value="0">';
        html += '<div class="programmo-create-offer-image-actions">';
        html += '<button type="button" class="button programmo-create-offer-image-select">Bild auswählen</button>';
        html += '<button type="button" class="button-link-delete programmo-create-offer-image-remove" hidden>Bild entfernen</button>';
        html += '</div>';
        html += '</div>';
        html += '</div>';

        if (programmoPeople.length > 0) {
            html += '<div class="programmo-sidebar-create__field">';
            html += '<label>ProgrammO-Personen</label>';
            html += '<select class="programmo-create-offer-programmo-people" multiple>';
            programmoPeople.forEach(function (person) {
                html += '<option value="' + escHtml(String(person.id || '')) + '">' + escHtml(person.label || '') + '</option>';
            });
            html += '</select>';
            html += '</div>';
        }

        if (okjaPeople.length > 0) {
            html += '<div class="programmo-sidebar-create__field">';
            html += '<label>OKJA Personenpool</label>';
            html += '<select class="programmo-create-offer-okja-people" multiple>';
            okjaPeople.forEach(function (person) {
                html += '<option value="' + escHtml(String(person.id || '')) + '">' + escHtml(person.label || '') + '</option>';
            });
            html += '</select>';
            html += '</div>';
        }

        if (programmoPeople.length === 0 && okjaPeople.length === 0) {
            html += '<p class="programmo-sidebar-create__hint">Es sind aktuell keine Personenpools verfügbar. Das Angebot kann trotzdem angelegt werden.</p>';
        } else {
            html += '<p class="programmo-sidebar-create__hint">Mehrfachauswahl ist möglich. Die Auswahl erscheint später als Personen-Badges im Angebots-Modal.</p>';
        }

        html += '<div class="programmo-sidebar-create__actions">';
        html += '<button type="button" class="button button-primary programmo-create-offer-save">Angebot speichern</button>';
        html += '<button type="button" class="button programmo-create-offer-cancel">Abbrechen</button>';
        html += '</div>';
        html += '</div>';
        html += '</div>';

        return html;
    }

    function renderSourceFilter() {
        return '' +
            '<div class="programmo-sidebar-filters">' +
                '<button type="button" class="programmo-sidebar-filter' + (sidebarSourceFilter === 'all' ? ' is-active' : '') + '" data-source-filter="all">Alle</button>' +
                '<button type="button" class="programmo-sidebar-filter' + (sidebarSourceFilter === 'programmo' ? ' is-active' : '') + '" data-source-filter="programmo">ProgrammO</button>' +
                '<button type="button" class="programmo-sidebar-filter' + (sidebarSourceFilter === 'okja' ? ' is-active' : '') + '" data-source-filter="okja">OKJA</button>' +
            '</div>';
    }

    /* ================================================================ */
    /*  Event binding: Matrix                                          */
    /* ================================================================ */

    function positionAddSlotForm(form) {
        if (!form) return;

        form.style.left = '50%';
        form.style.transform = 'translateX(-50%)';

        var viewportWidth = window.innerWidth || document.documentElement.clientWidth;
        var safePadding = 12;
        var rect = form.getBoundingClientRect();
        var shift = 0;

        if (rect.left < safePadding) {
            shift = safePadding - rect.left;
        }
        if (rect.right > viewportWidth - safePadding) {
            shift = (viewportWidth - safePadding) - rect.right;
        }

        if (shift !== 0) {
            form.style.transform = 'translateX(calc(-50% + ' + shift + 'px))';
        }
    }

    function bindMatrixEvents() {
        /* Day color edit buttons */
        matrixEl.querySelectorAll('.programmo-day-color-edit').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var dayKey = btn.getAttribute('data-day');
                openDayColorPopover(btn, dayKey);
            });
        });

        /* Add-slot buttons */
        matrixEl.querySelectorAll('.programmo-add-slot-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var day = btn.getAttribute('data-day');
                var form = matrixEl.querySelector('.programmo-add-slot-form[data-day="' + day + '"]');
                if (form) {
                    btn.style.display = 'none';
                    form.style.display = 'block';
                    positionAddSlotForm(form);
                    var startInput = form.querySelector('.programmo-inline-start');
                    if (startInput) startInput.focus();
                }
            });
        });

        /* Cancel buttons */
        matrixEl.querySelectorAll('.programmo-inline-cancel').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var day = btn.getAttribute('data-day');
                var form = matrixEl.querySelector('.programmo-add-slot-form[data-day="' + day + '"]');
                var addBtn = matrixEl.querySelector('.programmo-add-slot-btn[data-day="' + day + '"]');
                if (form) form.style.display = 'none';
                if (addBtn) addBtn.style.display = '';
            });
        });

        /* Save buttons */
        matrixEl.querySelectorAll('.programmo-inline-save').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var day = btn.getAttribute('data-day');
                var form = matrixEl.querySelector('.programmo-add-slot-form[data-day="' + day + '"]');
                if (!form) return;

                var start = form.querySelector('.programmo-inline-start').value;
                var end = form.querySelector('.programmo-inline-end').value;
                var areaId = parseInt(form.querySelector('.programmo-inline-area').value, 10) || 0;
                var ageRange = form.querySelector('.programmo-inline-age-range').value;
                var jugendSelect = form.querySelector('.programmo-inline-jugendarbeit');
                var jugendTermIds = [];
                if (jugendSelect) {
                    jugendTermIds = Array.prototype.slice.call(jugendSelect.selectedOptions || []).map(function (opt) {
                        return parseInt(opt.value, 10) || 0;
                    }).filter(function (id) {
                        return id > 0;
                    });
                }

                if (!start || !end) {
                    alert('Start- und Endzeit sind Pflicht.');
                    return;
                }

                btn.disabled = true;
                btn.textContent = 'Speichert…';

                api('POST', '/slots', {
                    weekday: day,
                    start: start,
                    end: end,
                    area_id: areaId,
                    age_range: ageRange,
                    jugend_term_ids: jugendTermIds,
                }).then(function (newSlot) {
                    slots.push(newSlot);
                    render();
                    showToast('Slot erstellt ✓');
                }).catch(function (err) {
                    alert('Fehler: ' + err.message);
                    btn.disabled = false;
                    btn.textContent = 'Anlegen';
                });
            });
        });

        /* Delete buttons */
        matrixEl.querySelectorAll('.programmo-slot-delete').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var slotId = parseInt(btn.getAttribute('data-slot-id'), 10);
                if (!confirm('Slot wirklich löschen?')) return;

                api('DELETE', '/slots/' + slotId).then(function () {
                    slots = slots.filter(function (s) { return s.id !== slotId; });
                    render();
                    showToast('Slot gelöscht');
                }).catch(function (err) {
                    alert('Fehler: ' + err.message);
                });
            });
        });

        if (!matrixEl.dataset.resizeBound) {
            window.addEventListener('resize', function () {
                matrixEl.querySelectorAll('.programmo-add-slot-form').forEach(function (form) {
                    if (form.style.display === 'block') {
                        positionAddSlotForm(form);
                    }
                });
            });
            matrixEl.dataset.resizeBound = '1';
        }

        /* Unlink event buttons (removes a specific event from the slot) */
        matrixEl.querySelectorAll('.programmo-unlink-event').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var slotId = parseInt(btn.getAttribute('data-slot-id'), 10);
                var eventId = parseInt(btn.getAttribute('data-event-id'), 10);

                api('PATCH', '/slots/' + slotId, { remove_event_id: eventId }).then(function (updated) {
                    replaceSlotInState(updated);
                    render();
                    showToast('Angebot entfernt');
                }).catch(function (err) {
                    alert('Fehler: ' + err.message);
                });
            });
        });

        /* Edit offer time buttons */
        matrixEl.querySelectorAll('.programmo-edit-offer-time').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();

                var slotId = parseInt(btn.getAttribute('data-slot-id'), 10);
                var eventId = parseInt(btn.getAttribute('data-event-id'), 10);
                var currentStart = (btn.getAttribute('data-offer-start') || '').trim();
                var currentEnd = (btn.getAttribute('data-offer-end') || '').trim();
                if (!slotId || !eventId) return;

                openOfferTimePopover(btn, slotId, eventId, currentStart, currentEnd);
            });
        });

        /* Drop zones */
        matrixEl.querySelectorAll('.programmo-drop-zone, .programmo-week-slot--interactive').forEach(function (el) {
            el.addEventListener('dragover', function (e) {
                e.preventDefault();
                el.classList.add('programmo-drop-hover');
            });
            el.addEventListener('dragleave', function () {
                el.classList.remove('programmo-drop-hover');
            });
            el.addEventListener('drop', function (e) {
                e.preventDefault();
                el.classList.remove('programmo-drop-hover');
                handleDrop(el);
            });
        });
    }

    /* ================================================================ */
    /*  Event binding: Sidebar (drag)                                  */
    /* ================================================================ */

    function bindSidebarEvents() {
        sidebarEl.querySelectorAll('.programmo-chip[draggable]').forEach(function (chip) {
            chip.addEventListener('dragstart', function (e) {
                draggedEvent = parseInt(chip.getAttribute('data-event-id'), 10);
                chip.classList.add('programmo-chip--dragging');
                e.dataTransfer.effectAllowed = 'copy';
                e.dataTransfer.setData('text/plain', String(draggedEvent));

                /* Highlight all drop zones */
                matrixEl.querySelectorAll('.programmo-drop-zone').forEach(function (z) {
                    z.classList.add('programmo-drop-zone--active');
                });
            });
            chip.addEventListener('dragend', function () {
                chip.classList.remove('programmo-chip--dragging');
                draggedEvent = null;
                matrixEl.querySelectorAll('.programmo-drop-zone').forEach(function (z) {
                    z.classList.remove('programmo-drop-zone--active');
                    z.classList.remove('programmo-drop-hover');
                });
            });
        });

        /* Color edit buttons */
        sidebarEl.querySelectorAll('.programmo-chip__color-edit').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var eventId = parseInt(btn.getAttribute('data-event-id'), 10);
                var ev = events.find(function (x) { return x.id === eventId; });
                if (!ev) return;
                openColorPopover(btn, ev);
            });
        });

        var createToggle = sidebarEl.querySelector('.programmo-create-offer-toggle');
        var createForm = sidebarEl.querySelector('.programmo-sidebar-create__form');
        var createWrap = sidebarEl.querySelector('.programmo-sidebar-create');
        var createSave = sidebarEl.querySelector('.programmo-create-offer-save');
        var createCancel = sidebarEl.querySelector('.programmo-create-offer-cancel');
        var createImageSelect = sidebarEl.querySelector('.programmo-create-offer-image-select');
        var createImageRemove = sidebarEl.querySelector('.programmo-create-offer-image-remove');
        var editButtons = sidebarEl.querySelectorAll('.programmo-chip__edit');

        sidebarEl.querySelectorAll('.programmo-sidebar-filter').forEach(function (btn) {
            btn.addEventListener('click', function () {
                sidebarSourceFilter = btn.getAttribute('data-source-filter') || 'all';
                renderSidebar();
            });
        });

        function setCreateFormVisible(isVisible) {
            if (!createForm || !createToggle) return;
            createForm.hidden = !isVisible;
            if (createWrap) {
                createWrap.classList.toggle('is-open', !!isVisible);
            }
            createToggle.setAttribute('aria-expanded', isVisible ? 'true' : 'false');
            if (!isVisible) {
                detachCreateFlyoutOutsideHandler();
                resetOfferForm(createForm, createToggle);
                return;
            }

            var mode = createForm.getAttribute('data-mode') || 'create';
            createToggle.textContent = mode === 'edit' ? 'Bearbeitung schließen' : 'Eigenes Angebot schließen';
            attachCreateFlyoutOutsideHandler(createWrap || createForm, createToggle, createForm);
        }

        if (createToggle) {
            createToggle.addEventListener('click', function () {
                setCreateFormVisible(createForm ? createForm.hidden : false);
                if (createForm && !createForm.hidden) {
                    var titleInput = createForm.querySelector('.programmo-create-offer-title');
                    if (titleInput) titleInput.focus();
                }
            });
        }

        if (createCancel) {
            createCancel.addEventListener('click', function () {
                setCreateFormVisible(false);
            });
        }

        if (createImageSelect) {
            createImageSelect.addEventListener('click', function (e) {
                e.preventDefault();
                openOfferImagePicker(createForm);
            });
        }

        if (createImageRemove) {
            createImageRemove.addEventListener('click', function (e) {
                e.preventDefault();
                updateOfferImageField(createForm, 0, '');
            });
        }

        editButtons.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (!createForm || !createToggle) return;
                var eventId = parseInt(btn.getAttribute('data-event-id'), 10);
                var ev = events.find(function (item) { return item.id === eventId; });
                if (!ev) return;

                populateOfferForm(createForm, createToggle, ev);
                setCreateFormVisible(true);
                var titleInput = createForm.querySelector('.programmo-create-offer-title');
                if (titleInput) titleInput.focus();
            });
        });

        if (createSave) {
            createSave.addEventListener('click', function () {
                if (!createForm) return;

                var titleInput = createForm.querySelector('.programmo-create-offer-title');
                var descriptionInput = createForm.querySelector('.programmo-create-offer-description');
                var title = titleInput ? titleInput.value.trim() : '';
                var description = descriptionInput ? descriptionInput.value.trim() : '';
                var programmoPeopleSelect = createForm.querySelector('.programmo-create-offer-programmo-people');
                var okjaPeopleSelect = createForm.querySelector('.programmo-create-offer-okja-people');
                var imageIdInput = createForm.querySelector('.programmo-create-offer-image-id');
                var editingId = parseInt(createForm.getAttribute('data-editing-id') || '0', 10);
                var isEditMode = createForm.getAttribute('data-mode') === 'edit' && editingId > 0;

                if (!title) {
                    alert('Bitte einen Titel für das Angebot eingeben.');
                    if (titleInput) titleInput.focus();
                    return;
                }

                createSave.disabled = true;
                createSave.textContent = isEditMode ? 'Aktualisiert…' : 'Speichert…';

                var payload = {
                    title: title,
                    description: description,
                    programmo_person_ids: getSelectedValues(programmoPeopleSelect),
                    okja_person_ids: getSelectedValues(okjaPeopleSelect),
                    image_id: parseInt(imageIdInput ? imageIdInput.value : '0', 10) || 0
                };

                var method = isEditMode ? 'PATCH' : 'POST';
                var path = isEditMode ? '/events/' + editingId : '/events';

                api(method, path, payload).then(function (saved) {
                    if (isEditMode) {
                        replaceEventInState(saved);
                    } else {
                        events.unshift(saved);
                    }
                    render();
                    showToast(isEditMode ? 'Angebot aktualisiert ✓' : 'Angebot erstellt ✓');
                }).catch(function (err) {
                    alert('Fehler: ' + err.message);
                    createSave.disabled = false;
                    createSave.textContent = isEditMode ? 'Angebot aktualisieren' : 'Angebot speichern';
                });
            });
        }

        /* Search filter */
        if (sidebarSearchEl && sidebarSearchEl.dataset.bound !== '1') {
            sidebarSearchEl.addEventListener('input', function () {
                applySidebarSearchFilter();
            });
            sidebarSearchEl.dataset.bound = '1';
        }
    }

    /* ================================================================ */
    /*  Offer Time Popover                                              */
    /* ================================================================ */

    function openOfferTimePopover(anchor, slotId, eventId, currentStart, currentEnd) {
        closeOfferTimePopover();

        var pop = document.createElement('div');
        pop.className = 'programmo-time-popover';
        pop.innerHTML =
            '<label class="programmo-time-popover__label">Angebotszeit</label>' +
            '<div class="programmo-time-popover__row">' +
                '<div class="programmo-time-popover__field">' +
                    '<label>Von</label>' +
                    '<input type="time" class="programmo-time-popover__start" value="' + escHtml(currentStart || '') + '">' +
                '</div>' +
                '<div class="programmo-time-popover__field">' +
                    '<label>Bis</label>' +
                    '<input type="time" class="programmo-time-popover__end" value="' + escHtml(currentEnd || '') + '">' +
                '</div>' +
            '</div>' +
            '<p class="programmo-time-popover__hint">Leer lassen, um Slot-Zeit zu verwenden.</p>' +
            '<div class="programmo-time-popover__actions">' +
                '<button type="button" class="button button-primary programmo-time-popover__save">Speichern</button>' +
                '<button type="button" class="button programmo-time-popover__reset">Zurücksetzen</button>' +
                '<button type="button" class="button programmo-time-popover__cancel">Abbrechen</button>' +
            '</div>';

        document.body.appendChild(pop);
        var rect = anchor.getBoundingClientRect();
        pop.style.top = (rect.bottom + window.scrollY + 6) + 'px';
        pop.style.left = Math.max(4, rect.left + window.scrollX - 140) + 'px';

        var startInput = pop.querySelector('.programmo-time-popover__start');
        var endInput = pop.querySelector('.programmo-time-popover__end');
        var saveBtn = pop.querySelector('.programmo-time-popover__save');
        var resetBtn = pop.querySelector('.programmo-time-popover__reset');
        var cancelBtn = pop.querySelector('.programmo-time-popover__cancel');

        function applyOfferTime(payload, successMessage) {
            saveBtn.disabled = true;
            resetBtn.disabled = true;
            cancelBtn.disabled = true;

            api('PATCH', '/slots/' + slotId, payload).then(function (updated) {
                replaceSlotInState(updated);
                closeOfferTimePopover();
                render();
                showToast(successMessage);
            }).catch(function (err) {
                alert('Fehler: ' + err.message);
                saveBtn.disabled = false;
                resetBtn.disabled = false;
                cancelBtn.disabled = false;
            });
        }

        saveBtn.addEventListener('click', function () {
            var start = (startInput.value || '').trim();
            var end = (endInput.value || '').trim();

            if (!start && !end) {
                applyOfferTime({ clear_event_time: eventId }, 'Angebotszeit zurückgesetzt');
                return;
            }

            if (!isValidTimeInput(start) || !isValidTimeInput(end)) {
                alert('Bitte gültige Uhrzeiten wählen.');
                return;
            }

            if (start >= end) {
                alert('Die Endzeit muss nach der Startzeit liegen.');
                return;
            }

            applyOfferTime({
                set_event_time: {
                    event_id: eventId,
                    start: start,
                    end: end,
                },
            }, 'Angebotszeit gespeichert ✓');
        });

        resetBtn.addEventListener('click', function () {
            applyOfferTime({ clear_event_time: eventId }, 'Angebotszeit zurückgesetzt');
        });

        cancelBtn.addEventListener('click', function () {
            closeOfferTimePopover();
        });

        setTimeout(function () {
            document.addEventListener('mousedown', closeOnOutsideClick);
        }, 0);

        function closeOnOutsideClick(e) {
            if (!pop.contains(e.target) && e.target !== anchor) {
                closeOfferTimePopover();
            }
        }
        pop._outsideHandler = closeOnOutsideClick;

        startInput.focus();
    }

    function closeOfferTimePopover() {
        var existing = document.querySelector('.programmo-time-popover');
        if (existing) {
            if (existing._outsideHandler) {
                document.removeEventListener('mousedown', existing._outsideHandler);
            }
            existing.remove();
        }
    }

    function isValidTimeInput(value) {
        return /^([01]\d|2[0-3]):[0-5]\d$/.test(value || '');
    }

    /* ================================================================ */
    /*  Color Popover                                                  */
    /* ================================================================ */

    function parseInitialColorSpec(value) {
        var raw = (value || '').trim();
        var state = {
            mode: 'solid',
            color1: '#a12ed2',
            color2: '#ff4fa1',
            angle: '90',
            css: raw,
        };

        if (!raw) {
            return state;
        }

        if (raw.indexOf('linear-gradient(') === 0) {
            state.mode = 'gradient';
            var m = raw.match(/linear-gradient\(\s*(\d+)deg\s*,\s*([^,]+)\s*,\s*([^)]+)\s*\)/i);
            if (m) {
                state.angle = m[1];
                state.color1 = normalizeHexColor(m[2].trim()) || state.color1;
                state.color2 = normalizeHexColor(m[3].trim()) || state.color2;
            } else {
                state.mode = 'free';
            }
            return state;
        }

        var hex = normalizeHexColor(raw);
        if (hex) {
            state.mode = 'solid';
            state.color1 = hex;
            return state;
        }

        state.mode = 'free';
        return state;
    }

    function normalizeHexColor(val) {
        if (!val) return null;
        var color = val.trim();
        if (/^#[0-9a-fA-F]{6}$/.test(color)) return color;
        if (/^#[0-9a-fA-F]{3}$/.test(color)) {
            return '#' + color[1] + color[1] + color[2] + color[2] + color[3] + color[3];
        }
        return null;
    }

    function buildColorSpec(mode, color1, color2, angle, cssInput) {
        if (mode === 'gradient') {
            return 'linear-gradient(' + angle + 'deg, ' + color1 + ', ' + color2 + ')';
        }
        if (mode === 'free') {
            return (cssInput || '').trim();
        }
        return color1;
    }

    function openColorPopover(anchor, ev) {
        closeColorPopover(); // close any open one

        var init = parseInitialColorSpec(ev.event_color || '');

        var pop = document.createElement('div');
        pop.className = 'programmo-color-popover';
        pop.innerHTML =
            '<label class="programmo-color-popover__label">Farbe / Gradient</label>' +
            '<div class="programmo-color-popover__tabs">' +
                '<button type="button" class="programmo-color-tab" data-mode="solid">Volltonfarbe</button>' +
                '<button type="button" class="programmo-color-tab" data-mode="gradient">Verlauf</button>' +
                '<button type="button" class="programmo-color-tab" data-mode="free">Frei (CSS)</button>' +
            '</div>' +

            '<div class="programmo-color-popover__group" data-group="solid">' +
                '<label>Farbe</label>' +
                '<div class="programmo-color-row">' +
                    '<input type="color" class="programmo-color-solid" value="' + escHtml(init.color1) + '">' +
                    '<input type="text" class="programmo-color-hex programmo-color-solid-hex" value="' + escHtml(init.color1) + '" maxlength="7" placeholder="#RRGGBB">' +
                '</div>' +
            '</div>' +

            '<div class="programmo-color-popover__group" data-group="gradient">' +
                '<label>Verlaufsfarben</label>' +
                '<div class="programmo-color-row">' +
                    '<input type="color" class="programmo-color-gradient-1" value="' + escHtml(init.color1) + '">' +
                    '<input type="text" class="programmo-color-hex programmo-color-grad1-hex" value="' + escHtml(init.color1) + '" maxlength="7" placeholder="#RRGGBB">' +
                '</div>' +
                '<div class="programmo-color-row">' +
                    '<input type="color" class="programmo-color-gradient-2" value="' + escHtml(init.color2) + '">' +
                    '<input type="text" class="programmo-color-hex programmo-color-grad2-hex" value="' + escHtml(init.color2) + '" maxlength="7" placeholder="#RRGGBB">' +
                '</div>' +
                '<label>Winkel</label>' +
                '<select class="programmo-color-angle">' +
                    '<option value="0">0°</option>' +
                    '<option value="45">45°</option>' +
                    '<option value="90">90°</option>' +
                    '<option value="135">135°</option>' +
                    '<option value="180">180°</option>' +
                '</select>' +
            '</div>' +

            '<div class="programmo-color-popover__group" data-group="free">' +
                '<label>Freier CSS-Background</label>' +
                '<input type="text" class="programmo-color-popover__input" placeholder="z.B. linear-gradient(135deg, #111, #555)" value="' + escHtml(init.css || '') + '">' +
            '</div>' +

            '<div class="programmo-color-popover__preview" style="background:' + escHtml(ev.event_color || '#ccc') + '"></div>' +
            '<div class="programmo-color-popover__presets">' +
                '<button type="button" class="programmo-color-preset" data-val="linear-gradient(90deg, #a12ed2, #ff4fa1)" style="background:linear-gradient(90deg, #a12ed2, #ff4fa1)" title="Lila-Pink"></button>' +
                '<button type="button" class="programmo-color-preset" data-val="linear-gradient(90deg, #ff6a00, #ee0979)" style="background:linear-gradient(90deg, #ff6a00, #ee0979)" title="Orange-Rot"></button>' +
                '<button type="button" class="programmo-color-preset" data-val="linear-gradient(90deg, #11998e, #38ef7d)" style="background:linear-gradient(90deg, #11998e, #38ef7d)" title="Grün"></button>' +
                '<button type="button" class="programmo-color-preset" data-val="linear-gradient(90deg, #2193b0, #6dd5ed)" style="background:linear-gradient(90deg, #2193b0, #6dd5ed)" title="Blau"></button>' +
                '<button type="button" class="programmo-color-preset" data-val="linear-gradient(90deg, #f7971e, #ffd200)" style="background:linear-gradient(90deg, #f7971e, #ffd200)" title="Gelb-Orange"></button>' +
                '<button type="button" class="programmo-color-preset" data-val="#2f8ce8" style="background:#2f8ce8" title="Blau (Vollton)"></button>' +
                '<button type="button" class="programmo-color-preset" data-val="" style="background:#ccc" title="Zurücksetzen (Standard)">✕</button>' +
            '</div>' +
            '<div class="programmo-color-popover__actions">' +
                '<button type="button" class="button button-primary programmo-color-save">Speichern</button>' +
                '<button type="button" class="button programmo-color-cancel">Abbrechen</button>' +
            '</div>';

        /* Position near the anchor */
        document.body.appendChild(pop);
        var rect = anchor.getBoundingClientRect();
        pop.style.top = (rect.bottom + window.scrollY + 4) + 'px';
        pop.style.left = Math.max(4, rect.left + window.scrollX - 120) + 'px';

        var mode = init.mode;
        var solidInput = pop.querySelector('.programmo-color-solid');
        var solidHex = pop.querySelector('.programmo-color-solid-hex');
        var grad1Input = pop.querySelector('.programmo-color-gradient-1');
        var grad1Hex = pop.querySelector('.programmo-color-grad1-hex');
        var grad2Input = pop.querySelector('.programmo-color-gradient-2');
        var grad2Hex = pop.querySelector('.programmo-color-grad2-hex');
        var angleInput = pop.querySelector('.programmo-color-angle');
        var freeInput = pop.querySelector('.programmo-color-popover__input');
        var preview = pop.querySelector('.programmo-color-popover__preview');
        angleInput.value = init.angle;

        /* Sync color picker ↔ hex text input */
        function syncPair(colorEl, hexEl) {
            colorEl.addEventListener('input', function () {
                hexEl.value = colorEl.value;
                updatePreview();
            });
            hexEl.addEventListener('input', function () {
                var norm = normalizeHexColor(hexEl.value);
                if (norm) { colorEl.value = norm; updatePreview(); }
            });
            hexEl.addEventListener('change', function () {
                var norm = normalizeHexColor(hexEl.value);
                if (norm) { hexEl.value = norm; colorEl.value = norm; updatePreview(); }
            });
        }
        syncPair(solidInput, solidHex);
        syncPair(grad1Input, grad1Hex);
        syncPair(grad2Input, grad2Hex);

        function applyModeUI() {
            pop.querySelectorAll('.programmo-color-tab').forEach(function (btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-mode') === mode);
            });
            pop.querySelectorAll('.programmo-color-popover__group').forEach(function (group) {
                var isMatch = group.getAttribute('data-group') === mode;
                group.style.display = isMatch ? 'block' : 'none';
            });
            updatePreview();
        }

        function updatePreview() {
            var val;
            if (mode === 'gradient') {
                val = buildColorSpec('gradient', grad1Input.value, grad2Input.value, angleInput.value, '');
            } else if (mode === 'free') {
                val = buildColorSpec('free', '', '', '', freeInput.value);
            } else {
                val = buildColorSpec('solid', solidInput.value, '', '', '');
            }
            preview.style.background = val || '#ccc';
        }

        pop.querySelectorAll('.programmo-color-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                mode = btn.getAttribute('data-mode');
                applyModeUI();
            });
        });

        [angleInput, freeInput].forEach(function (field) {
            field.addEventListener('input', updatePreview);
            field.addEventListener('change', updatePreview);
        });

        applyModeUI();

        /* Presets */
        pop.querySelectorAll('.programmo-color-preset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var val = btn.getAttribute('data-val') || '';
                if (!val) {
                    mode = 'free';
                    freeInput.value = '';
                    applyModeUI();
                    return;
                }
                var parsed = parseInitialColorSpec(val);
                mode = parsed.mode;
                solidInput.value = parsed.color1;
                solidHex.value = parsed.color1;
                grad1Input.value = parsed.color1;
                grad1Hex.value = parsed.color1;
                grad2Input.value = parsed.color2;
                grad2Hex.value = parsed.color2;
                angleInput.value = parsed.angle;
                freeInput.value = parsed.css;
                applyModeUI();
            });
        });

        /* Save */
        pop.querySelector('.programmo-color-save').addEventListener('click', function () {
            var val = '';
            if (mode === 'gradient') {
                val = buildColorSpec('gradient', grad1Input.value, grad2Input.value, angleInput.value, '');
            } else if (mode === 'free') {
                val = buildColorSpec('free', '', '', '', freeInput.value);
            } else {
                val = buildColorSpec('solid', solidInput.value, '', '', '');
            }
            pop.querySelector('.programmo-color-save').disabled = true;
            pop.querySelector('.programmo-color-save').textContent = 'Speichert…';

            api('PATCH', '/events/' + ev.id, { event_color: val }).then(function (res) {
                ev.event_color = res.event_color;
                closeColorPopover();
                renderSidebar();
                render(); // re-render matrix too, if offers show color
                showToast('Farbe gespeichert ✓');
            }).catch(function (err) {
                alert('Fehler: ' + err.message);
                pop.querySelector('.programmo-color-save').disabled = false;
                pop.querySelector('.programmo-color-save').textContent = 'Speichern';
            });
        });

        /* Cancel */
        pop.querySelector('.programmo-color-cancel').addEventListener('click', function () {
            closeColorPopover();
        });

        /* Close on outside click (deferred) */
        setTimeout(function () {
            document.addEventListener('mousedown', closeOnOutsideClick);
        }, 0);

        function closeOnOutsideClick(e) {
            if (!pop.contains(e.target) && e.target !== anchor) {
                closeColorPopover();
            }
        }
        pop._outsideHandler = closeOnOutsideClick;

        if (mode === 'free') {
            freeInput.focus();
        } else if (mode === 'gradient') {
            grad1Input.focus();
        } else {
            solidInput.focus();
        }
    }

    function closeColorPopover() {
        var existing = document.querySelector('.programmo-color-popover');
        if (existing) {
            if (existing._outsideHandler) {
                document.removeEventListener('mousedown', existing._outsideHandler);
            }
            existing.remove();
        }
        closeDayColorPopover();
        closeOfferTimePopover();
    }

    /* ================================================================ */
    /*  Day Color Popover                                              */
    /* ================================================================ */

    function openDayColorPopover(anchor, dayKey) {
        closeDayColorPopover();
        closeColorPopover();

        var currentVal = dayColors[dayKey] || '';
        var init = parseInitialColorSpec(currentVal);

        var pop = document.createElement('div');
        pop.className = 'programmo-color-popover programmo-day-color-popover';

        /* Build copy-to checkboxes */
        var copyHtml = '<div class="programmo-day-color-copy">';
        copyHtml += '<label class="programmo-day-color-copy__label">Auf andere Tage kopieren:</label>';
        copyHtml += '<div class="programmo-day-color-copy__days">';
        visibleWeekdayKeys.forEach(function (dk) {
            if (dk === dayKey) return;
            var dl = weekdays[dk] || dk;
            copyHtml += '<label class="programmo-day-color-copy__day"><input type="checkbox" value="' + dk + '"> ' + escHtml(dl) + '</label>';
        });
        copyHtml += '</div></div>';

        pop.innerHTML =
            '<label class="programmo-color-popover__label">Tagesfarbe: ' + escHtml(weekdays[dayKey] || dayKey) + '</label>' +
            '<div class="programmo-color-popover__tabs">' +
                '<button type="button" class="programmo-color-tab" data-mode="solid">Volltonfarbe</button>' +
                '<button type="button" class="programmo-color-tab" data-mode="gradient">Verlauf</button>' +
                '<button type="button" class="programmo-color-tab" data-mode="free">Frei (CSS)</button>' +
            '</div>' +

            '<div class="programmo-color-popover__group" data-group="solid">' +
                '<label>Farbe</label>' +
                '<div class="programmo-color-row">' +
                    '<input type="color" class="programmo-color-solid" value="' + escHtml(init.color1) + '">' +
                    '<input type="text" class="programmo-color-hex programmo-color-solid-hex" value="' + escHtml(init.color1) + '" maxlength="7" placeholder="#RRGGBB">' +
                '</div>' +
            '</div>' +

            '<div class="programmo-color-popover__group" data-group="gradient">' +
                '<label>Verlaufsfarben</label>' +
                '<div class="programmo-color-row">' +
                    '<input type="color" class="programmo-color-gradient-1" value="' + escHtml(init.color1) + '">' +
                    '<input type="text" class="programmo-color-hex programmo-color-grad1-hex" value="' + escHtml(init.color1) + '" maxlength="7" placeholder="#RRGGBB">' +
                '</div>' +
                '<div class="programmo-color-row">' +
                    '<input type="color" class="programmo-color-gradient-2" value="' + escHtml(init.color2) + '">' +
                    '<input type="text" class="programmo-color-hex programmo-color-grad2-hex" value="' + escHtml(init.color2) + '" maxlength="7" placeholder="#RRGGBB">' +
                '</div>' +
                '<label>Winkel</label>' +
                '<select class="programmo-color-angle">' +
                    '<option value="0">0\u00B0</option>' +
                    '<option value="45">45\u00B0</option>' +
                    '<option value="90">90\u00B0</option>' +
                    '<option value="135">135\u00B0</option>' +
                    '<option value="180">180\u00B0</option>' +
                '</select>' +
            '</div>' +

            '<div class="programmo-color-popover__group" data-group="free">' +
                '<label>Freier CSS-Background</label>' +
                '<input type="text" class="programmo-color-popover__input" placeholder="z.B. linear-gradient(135deg, #111, #555)" value="' + escHtml(init.css || '') + '">' +
            '</div>' +

            '<div class="programmo-color-popover__preview" style="background:' + escHtml(currentVal || 'linear-gradient(135deg, #2279d4 0%, #2d8ae7 40%, #4cc0e8 100%)') + '"></div>' +
            '<div class="programmo-color-popover__presets">' +
                '<button type="button" class="programmo-color-preset" data-val="linear-gradient(135deg, #2279d4 0%, #2d8ae7 40%, #4cc0e8 100%)" style="background:linear-gradient(135deg, #2279d4, #4cc0e8)" title="Standard-Blau"></button>' +
                '<button type="button" class="programmo-color-preset" data-val="linear-gradient(135deg, #1a3a5c, #2279d4)" style="background:linear-gradient(135deg, #1a3a5c, #2279d4)" title="Dunkelblau"></button>' +
                '<button type="button" class="programmo-color-preset" data-val="linear-gradient(135deg, #11998e, #38ef7d)" style="background:linear-gradient(135deg, #11998e, #38ef7d)" title="Gr\u00fcn"></button>' +
                '<button type="button" class="programmo-color-preset" data-val="linear-gradient(135deg, #a12ed2, #ff4fa1)" style="background:linear-gradient(135deg, #a12ed2, #ff4fa1)" title="Lila-Pink"></button>' +
                '<button type="button" class="programmo-color-preset" data-val="linear-gradient(135deg, #ff6a00, #ee0979)" style="background:linear-gradient(135deg, #ff6a00, #ee0979)" title="Orange-Rot"></button>' +
                '<button type="button" class="programmo-color-preset" data-val="linear-gradient(135deg, #f7971e, #ffd200)" style="background:linear-gradient(135deg, #f7971e, #ffd200)" title="Gelb-Orange"></button>' +
                '<button type="button" class="programmo-color-preset" data-val="" style="background:linear-gradient(135deg, #2279d4, #4cc0e8)" title="Zur\u00fccksetzen (Standard)">\u2715</button>' +
            '</div>' +
            copyHtml +
            '<div class="programmo-color-popover__actions">' +
                '<button type="button" class="button button-primary programmo-color-save">Speichern</button>' +
                '<button type="button" class="button programmo-color-cancel">Abbrechen</button>' +
            '</div>';

        document.body.appendChild(pop);
        var rect = anchor.getBoundingClientRect();
        pop.style.top = (rect.bottom + window.scrollY + 4) + 'px';
        pop.style.left = Math.max(4, rect.left + window.scrollX - 120) + 'px';

        var mode = init.mode;
        var solidInput = pop.querySelector('.programmo-color-solid');
        var solidHex = pop.querySelector('.programmo-color-solid-hex');
        var grad1Input = pop.querySelector('.programmo-color-gradient-1');
        var grad1Hex = pop.querySelector('.programmo-color-grad1-hex');
        var grad2Input = pop.querySelector('.programmo-color-gradient-2');
        var grad2Hex = pop.querySelector('.programmo-color-grad2-hex');
        var angleInput = pop.querySelector('.programmo-color-angle');
        var freeInput = pop.querySelector('.programmo-color-popover__input');
        var preview = pop.querySelector('.programmo-color-popover__preview');
        angleInput.value = init.angle;

        function syncPair(colorEl, hexEl) {
            colorEl.addEventListener('input', function () {
                hexEl.value = colorEl.value;
                updatePreview();
            });
            hexEl.addEventListener('input', function () {
                var norm = normalizeHexColor(hexEl.value);
                if (norm) { colorEl.value = norm; updatePreview(); }
            });
            hexEl.addEventListener('change', function () {
                var norm = normalizeHexColor(hexEl.value);
                if (norm) { hexEl.value = norm; colorEl.value = norm; updatePreview(); }
            });
        }
        syncPair(solidInput, solidHex);
        syncPair(grad1Input, grad1Hex);
        syncPair(grad2Input, grad2Hex);

        function applyModeUI() {
            pop.querySelectorAll('.programmo-color-tab').forEach(function (btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-mode') === mode);
            });
            pop.querySelectorAll('.programmo-color-popover__group').forEach(function (group) {
                var isMatch = group.getAttribute('data-group') === mode;
                group.style.display = isMatch ? 'block' : 'none';
            });
            updatePreview();
        }

        function updatePreview() {
            var val;
            if (mode === 'gradient') {
                val = buildColorSpec('gradient', grad1Input.value, grad2Input.value, angleInput.value, '');
            } else if (mode === 'free') {
                val = buildColorSpec('free', '', '', '', freeInput.value);
            } else {
                val = buildColorSpec('solid', solidInput.value, '', '', '');
            }
            preview.style.background = val || 'linear-gradient(135deg, #2279d4, #4cc0e8)';
        }

        pop.querySelectorAll('.programmo-color-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                mode = btn.getAttribute('data-mode');
                applyModeUI();
            });
        });

        [angleInput, freeInput].forEach(function (field) {
            field.addEventListener('input', updatePreview);
            field.addEventListener('change', updatePreview);
        });

        applyModeUI();

        /* Presets */
        pop.querySelectorAll('.programmo-color-preset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var val = btn.getAttribute('data-val') || '';
                if (!val) {
                    mode = 'free';
                    freeInput.value = '';
                    applyModeUI();
                    return;
                }
                var parsed = parseInitialColorSpec(val);
                mode = parsed.mode;
                solidInput.value = parsed.color1;
                solidHex.value = parsed.color1;
                grad1Input.value = parsed.color1;
                grad1Hex.value = parsed.color1;
                grad2Input.value = parsed.color2;
                grad2Hex.value = parsed.color2;
                angleInput.value = parsed.angle;
                freeInput.value = parsed.css;
                applyModeUI();
            });
        });

        /* Save */
        pop.querySelector('.programmo-color-save').addEventListener('click', function () {
            var val = '';
            if (mode === 'gradient') {
                val = buildColorSpec('gradient', grad1Input.value, grad2Input.value, angleInput.value, '');
            } else if (mode === 'free') {
                val = buildColorSpec('free', '', '', '', freeInput.value);
            } else {
                val = buildColorSpec('solid', solidInput.value, '', '', '');
            }

            /* Collect copy-to targets */
            var targetDays = [dayKey];
            pop.querySelectorAll('.programmo-day-color-copy__days input:checked').forEach(function (cb) {
                targetDays.push(cb.value);
            });

            var payload = {};
            targetDays.forEach(function (dk) { payload[dk] = val; });

            pop.querySelector('.programmo-color-save').disabled = true;
            pop.querySelector('.programmo-color-save').textContent = 'Speichert\u2026';

            api('POST', '/day-colors', payload).then(function (res) {
                dayColors = res;
                closeDayColorPopover();
                render();
                var msg = targetDays.length > 1
                    ? 'Tagesfarbe auf ' + targetDays.length + ' Tage gespeichert \u2713'
                    : 'Tagesfarbe gespeichert \u2713';
                showToast(msg);
            }).catch(function (err) {
                alert('Fehler: ' + err.message);
                pop.querySelector('.programmo-color-save').disabled = false;
                pop.querySelector('.programmo-color-save').textContent = 'Speichern';
            });
        });

        /* Cancel */
        pop.querySelector('.programmo-color-cancel').addEventListener('click', function () {
            closeDayColorPopover();
        });

        /* Close on outside click */
        setTimeout(function () {
            document.addEventListener('mousedown', outsideClick);
        }, 0);

        function outsideClick(e) {
            if (!pop.contains(e.target) && e.target !== anchor) {
                closeDayColorPopover();
            }
        }
        pop._outsideHandler = outsideClick;

        if (mode === 'free') {
            freeInput.focus();
        } else if (mode === 'gradient') {
            grad1Input.focus();
        } else {
            solidInput.focus();
        }
    }

    function closeDayColorPopover() {
        var existing = document.querySelector('.programmo-day-color-popover');
        if (existing) {
            if (existing._outsideHandler) {
                document.removeEventListener('mousedown', existing._outsideHandler);
            }
            existing.remove();
        }
    }

    /* ================================================================ */
    /*  Drop handler                                                   */
    /* ================================================================ */

    function handleDrop(targetEl) {
        if (!draggedEvent) return;

        /* Find the slot ID from the drop target */
        var slotId = parseInt(targetEl.getAttribute('data-slot-id'), 10);
        if (!slotId) {
            /* Maybe dropped on the slot card itself */
            var parentSlot = targetEl.closest('.programmo-week-slot--interactive');
            if (parentSlot) {
                slotId = parseInt(parentSlot.getAttribute('data-slot-id'), 10);
            }
        }

        if (!slotId) return;

        /* Prevent duplicate: check if event already in this slot */
        var current = slots.find(function (s) { return s.id === slotId; });
        if (current && current.event_ids && current.event_ids.indexOf(draggedEvent) >= 0) {
            showToast('Angebot bereits zugeordnet');
            return;
        }

        /* Show loading state */
        targetEl.innerHTML = '<span class="programmo-loading">Verknüpfe…</span>';

        api('PATCH', '/slots/' + slotId, { add_event_id: draggedEvent }).then(function (updated) {
            replaceSlotInState(updated);
            render();
            showToast('Angebot zugeordnet ✓');
        }).catch(function (err) {
            alert('Fehler: ' + err.message);
            render();
        });
    }

    /* ================================================================ */
    /*  State helpers                                                   */
    /* ================================================================ */

    function replaceSlotInState(updated) {
        for (var i = 0; i < slots.length; i++) {
            if (slots[i].id === updated.id) {
                slots[i] = updated;
                return;
            }
        }
        slots.push(updated);
    }

    function replaceEventInState(updated) {
        for (var i = 0; i < events.length; i++) {
            if (events[i].id === updated.id) {
                events[i] = updated;
                return;
            }
        }
        events.unshift(updated);
    }

    /* ================================================================ */
    /*  UI helpers                                                     */
    /* ================================================================ */

    function showToast(msg) {
        var toast = document.createElement('div');
        toast.className = 'programmo-toast';
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(function () { toast.classList.add('programmo-toast--visible'); }, 10);
        setTimeout(function () {
            toast.classList.remove('programmo-toast--visible');
            setTimeout(function () { toast.remove(); }, 300);
        }, 2200);
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    function getSelectedValues(selectEl) {
        if (!selectEl) return [];
        return Array.prototype.slice.call(selectEl.selectedOptions || []).map(function (opt) {
            return parseInt(opt.value, 10) || 0;
        }).filter(function (id) {
            return id > 0;
        });
    }

    function applySidebarSearchFilter() {
        if (!sidebarSearchEl || !sidebarEl) return;
        var q = (sidebarSearchEl.value || '').toLowerCase();
        sidebarEl.querySelectorAll('.programmo-chip').forEach(function (chip) {
            var text = (chip.getAttribute('title') || '').toLowerCase();
            var source = chip.getAttribute('data-source') || 'okja';
            var sourceMatch = sidebarSourceFilter === 'all' || source === sidebarSourceFilter;
            var searchMatch = text.indexOf(q) >= 0;
            chip.style.display = sourceMatch && searchMatch ? '' : 'none';
        });
    }

    function populateOfferForm(form, toggleBtn, ev) {
        form.setAttribute('data-mode', 'edit');
        form.setAttribute('data-editing-id', String(ev.id));

        var titleInput = form.querySelector('.programmo-create-offer-title');
        var descriptionInput = form.querySelector('.programmo-create-offer-description');
        var saveBtn = form.querySelector('.programmo-create-offer-save');

        if (titleInput) titleInput.value = ev.title || '';
        if (descriptionInput) descriptionInput.value = ev.description_raw || ev.description || '';
        setSelectValues(form.querySelector('.programmo-create-offer-programmo-people'), ev.programmo_person_ids || []);
        setSelectValues(form.querySelector('.programmo-create-offer-okja-people'), ev.okja_person_ids || []);
        updateOfferImageField(form, ev.image_id || 0, ev.image_url || '');
        if (saveBtn) saveBtn.textContent = 'Angebot aktualisieren';
        if (toggleBtn) toggleBtn.textContent = 'Bearbeitung schließen';
    }

    function resetOfferForm(form, toggleBtn) {
        detachCreateFlyoutOutsideHandler();
        form.hidden = true;
        form.setAttribute('data-mode', 'create');
        form.removeAttribute('data-editing-id');
        var wrap = form.closest('.programmo-sidebar-create');
        if (wrap) {
            wrap.classList.remove('is-open');
        }

        var titleInput = form.querySelector('.programmo-create-offer-title');
        var descriptionInput = form.querySelector('.programmo-create-offer-description');
        var saveBtn = form.querySelector('.programmo-create-offer-save');
        if (titleInput) titleInput.value = '';
        if (descriptionInput) descriptionInput.value = '';
        setSelectValues(form.querySelector('.programmo-create-offer-programmo-people'), []);
        setSelectValues(form.querySelector('.programmo-create-offer-okja-people'), []);
        updateOfferImageField(form, 0, '');
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Angebot speichern';
        }
        if (toggleBtn) toggleBtn.textContent = 'Eigenes Angebot anlegen';
        if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
    }

    function attachCreateFlyoutOutsideHandler(container, toggleBtn, form) {
        detachCreateFlyoutOutsideHandler();

        sidebarCreateOutsideHandler = function (event) {
            if (!form || form.hidden) {
                detachCreateFlyoutOutsideHandler();
                return;
            }

            var mediaModal = document.querySelector('.media-modal, .media-frame, .media-modal-backdrop');
            if (mediaModal && mediaModal.contains(event.target)) {
                return;
            }

            if (event.target && event.target.closest && event.target.closest('.media-modal, .media-frame, .media-modal-backdrop')) {
                return;
            }

            if (container && container.contains(event.target)) {
                return;
            }

            resetOfferForm(form, toggleBtn);
        };

        setTimeout(function () {
            if (sidebarCreateOutsideHandler) {
                document.addEventListener('mousedown', sidebarCreateOutsideHandler);
            }
        }, 0);
    }

    function detachCreateFlyoutOutsideHandler() {
        if (!sidebarCreateOutsideHandler) return;
        document.removeEventListener('mousedown', sidebarCreateOutsideHandler);
        sidebarCreateOutsideHandler = null;
    }

    function setSelectValues(selectEl, values) {
        if (!selectEl) return;
        var lookup = {};
        (values || []).forEach(function (value) {
            lookup[String(value)] = true;
        });
        Array.prototype.slice.call(selectEl.options || []).forEach(function (opt) {
            opt.selected = !!lookup[String(opt.value)];
        });
    }

    function openOfferImagePicker(form) {
        if (!form || !window.wp || !wp.media) return;

        if (!mediaFrame) {
            mediaFrame = wp.media({
                title: 'Bild für Angebot auswählen',
                button: { text: 'Bild verwenden' },
                multiple: false,
                library: { type: 'image' }
            });
        }

        mediaFrame.off('select');
        mediaFrame.on('select', function () {
            var attachment = mediaFrame.state().get('selection').first();
            if (!attachment) return;
            var data = attachment.toJSON();
            var imageUrl = (data.sizes && data.sizes.thumbnail && data.sizes.thumbnail.url) || data.url || '';
            updateOfferImageField(form, parseInt(data.id, 10) || 0, imageUrl);
        });

        mediaFrame.open();
    }

    function updateOfferImageField(form, imageId, imageUrl) {
        if (!form) return;
        var imageIdInput = form.querySelector('.programmo-create-offer-image-id');
        var preview = form.querySelector('.programmo-create-offer-image-preview');
        var previewImg = form.querySelector('.programmo-create-offer-image-preview-img');
        var removeBtn = form.querySelector('.programmo-create-offer-image-remove');
        var selectBtn = form.querySelector('.programmo-create-offer-image-select');

        if (imageIdInput) imageIdInput.value = String(imageId || 0);
        if (preview && previewImg) {
            if (imageId > 0 && imageUrl) {
                previewImg.src = imageUrl;
                preview.hidden = false;
            } else {
                previewImg.src = '';
                preview.hidden = true;
            }
        }
        if (removeBtn) {
            removeBtn.hidden = !(imageId > 0);
        }
        if (selectBtn) {
            selectBtn.textContent = imageId > 0 ? 'Bild ersetzen' : 'Bild auswählen';
        }
    }

    /* ================================================================ */
    /*  Boot                                                           */
    /* ================================================================ */

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadAll);
    } else {
        loadAll();
    }
})();
