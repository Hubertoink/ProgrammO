/**
 * ProgrammO – Gutenberg Block: Wochenplan
 *
 * Uses ServerSideRender for live preview, with InspectorControls
 * to configure display options per block instance.
 */
(function () {
    'use strict';

    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls, BlockControls } = wp.blockEditor;
    const {
        PanelBody,
        PanelRow,
        TextControl,
        ToggleControl,
        RangeControl,
        CheckboxControl,
        Placeholder,
        ToolbarGroup,
        ToolbarButton,
    } = wp.components;
    const ServerSideRender = wp.serverSideRender;
    const { __ } = wp.i18n;

    /* ------------------------------------------------------------------ */
    /*  Localized data from PHP                                           */
    /* ------------------------------------------------------------------ */
    const weekdays = (window.programmoBlockData && window.programmoBlockData.weekdays) || {
        monday: 'Montag',
        tuesday: 'Dienstag',
        wednesday: 'Mittwoch',
        thursday: 'Donnerstag',
        friday: 'Freitag',
        saturday: 'Samstag',
        sunday: 'Sonntag',
    };

    const globalDays = (window.programmoBlockData && window.programmoBlockData.globalDays) || [];
    const globalMobileCollapse = (window.programmoBlockData && window.programmoBlockData.globalMobileCollapse) !== false;

    /* ------------------------------------------------------------------ */
    /*  Block Icon (SVG)                                                  */
    /* ------------------------------------------------------------------ */
    const blockIcon = el(
        'svg',
        { xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24', width: 24, height: 24 },
        el('path', {
            fill: 'currentColor',
            d: 'M19 3h-1V1h-2v2H8V1H6v2H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2V5a2 2 0 00-2-2zm0 16H5V9h14v10zm0-12H5V5h14v2z',
        })
    );

    /* ------------------------------------------------------------------ */
    /*  Edit Component                                                    */
    /* ------------------------------------------------------------------ */
    function ProgrammOEdit(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;

        var title = attributes.title || '';
        var showValidFrom = attributes.showValidFrom !== false;
        var showTeam = attributes.showTeam !== false;
        var showBadges = attributes.showBadges !== false;
        var showDetails = attributes.showDetails !== false;
        var compactMode = attributes.compactMode || false;
        var mobileCollapse = attributes.mobileCollapse;
        if (typeof mobileCollapse === 'undefined') {
            mobileCollapse = globalMobileCollapse;
        }
        var enablePdfExport = !!attributes.enablePdfExport;
        var showGrain = !!attributes.showGrain;
        var showTooltips = attributes.showTooltips !== false;
        var backUrl = attributes.backUrl || '';
        var columns = attributes.columns || 0;
        var overrideDays = attributes.overrideDays || [];

        /* Helper: toggle a day in overrideDays */
        function toggleDay(day) {
            var current = overrideDays.slice();
            var idx = current.indexOf(day);
            if (idx >= 0) {
                current.splice(idx, 1);
            } else {
                current.push(day);
            }
            setAttributes({ overrideDays: current });
        }

        /* Is a day checked? If overrideDays is empty → use global */
        function isDayActive(day) {
            if (overrideDays.length === 0) {
                return globalDays.indexOf(day) >= 0;
            }
            return overrideDays.indexOf(day) >= 0;
        }

        /* Sidebar: Inspector Controls */
        var inspector = el(
            InspectorControls,
            null,

            /* --- Panel: Allgemein --- */
            el(
                PanelBody,
                { title: __('Allgemein', 'programmo'), initialOpen: true },

                el(TextControl, {
                    label: __('Überschrift', 'programmo'),
                    help: __('Optionaler Titel über dem Wochenplan.', 'programmo'),
                    value: title,
                    onChange: function (val) {
                        setAttributes({ title: val });
                    },
                }),

                el(ToggleControl, {
                    label: __('„Gültig ab" anzeigen', 'programmo'),
                    help: __('Zeigt den in den Einstellungen hinterlegten Gültigkeits-Hinweis.', 'programmo'),
                    checked: showValidFrom,
                    onChange: function (val) {
                        setAttributes({ showValidFrom: val });
                    },
                }),

                el(ToggleControl, {
                    label: __('Kompakt-Modus', 'programmo'),
                    help: __('Reduzierte Kartengröße — ideal für schmale Spalten oder Sidebar.', 'programmo'),
                    checked: compactMode,
                    onChange: function (val) {
                        setAttributes({ compactMode: val });
                    },
                }),

                el(ToggleControl, {
                    label: __('Mobile: Tage auf-/zuklappbar', 'programmo'),
                    help: __('Zeigt auf kleinen Bildschirmen pro Wochentag einen Auf-/Zuklappen-Button.', 'programmo'),
                    checked: !!mobileCollapse,
                    onChange: function (val) {
                        setAttributes({ mobileCollapse: val });
                    },
                }),

                el(ToggleControl, {
                    label: __('PDF-Export Button anzeigen', 'programmo'),
                    help: __('Zeigt ein schwebendes Export-Icon, um den Plan als PDF zu speichern.', 'programmo'),
                    checked: enablePdfExport,
                    onChange: function (val) {
                        setAttributes({ enablePdfExport: val });
                    },
                }),

                el(ToggleControl, {
                    label: __('Kachel-Textur (Grain)', 'programmo'),
                    help: __('Fügt den Bereichs-Kacheln einen dezenten Film-Grain-Effekt hinzu.', 'programmo'),
                    checked: showGrain,
                    onChange: function (val) {
                        setAttributes({ showGrain: val });
                    },
                }),

                el(TextControl, {
                    label: __('Zurück-URL für Angebote', 'programmo'),
                    help: __('URL, zu der der Zurück-Button auf Angebots-Einzelseiten führt (z. B. /programm-angebote). Leer = Standard-Archiv des OKJA-Plugins.', 'programmo'),
                    value: backUrl,
                    onChange: function (val) {
                        setAttributes({ backUrl: val });
                    },
                    placeholder: '/programm-angebote',
                })
            ),

            /* --- Panel: Inhalte anzeigen --- */
            el(
                PanelBody,
                { title: __('Angezeigte Inhalte', 'programmo'), initialOpen: false },

                el(ToggleControl, {
                    label: __('Team / Betreuer anzeigen', 'programmo'),
                    checked: showTeam,
                    onChange: function (val) {
                        setAttributes({ showTeam: val });
                    },
                }),

                el(ToggleControl, {
                    label: __('Angebots-Details (aufklappbar)', 'programmo'),
                    checked: showDetails,
                    onChange: function (val) {
                        setAttributes({ showDetails: val });
                    },
                }),

                el(ToggleControl, {
                    label: __('Kategorien-Badges', 'programmo'),
                    help: __('Badges aus Jugendarbeit / Pädagogik Taxonomien.', 'programmo'),
                    checked: showBadges,
                    onChange: function (val) {
                        setAttributes({ showBadges: val });
                    },
                }),

                el(ToggleControl, {
                    label: __('Hover-Tooltips (Desktop)', 'programmo'),
                    help: __('Zeigt beim Überfahren der Angebote ein Info-Tooltip mit Bild, Zeit und Beschreibung.', 'programmo'),
                    checked: showTooltips,
                    onChange: function (val) {
                        setAttributes({ showTooltips: val });
                    },
                })
            ),

            /* --- Panel: Layout --- */
            el(
                PanelBody,
                { title: __('Layout', 'programmo'), initialOpen: false },

                el(RangeControl, {
                    label: __('Spalten', 'programmo'),
                    help: __('0 = automatisch (responsive). 1–6 für feste Spaltenanzahl.', 'programmo'),
                    value: columns,
                    onChange: function (val) {
                        setAttributes({ columns: val });
                    },
                    min: 0,
                    max: 6,
                    step: 1,
                })
            ),

            /* --- Panel: Wochentage --- */
            el(
                PanelBody,
                { title: __('Wochentage (Block-Override)', 'programmo'), initialOpen: false },

                el(
                    'p',
                    { className: 'components-base-control__help', style: { marginTop: 0 } },
                    overrideDays.length === 0
                        ? __('Aktuell werden die globalen Einstellungen verwendet. Aktiviere einzelne Tage, um sie nur für diesen Block zu überschreiben.', 'programmo')
                        : __('Dieser Block zeigt nur die hier gewählten Tage an (unabhängig von den globalen Einstellungen).', 'programmo')
                ),

                Object.keys(weekdays).map(function (key) {
                    return el(CheckboxControl, {
                        key: key,
                        label: weekdays[key],
                        checked: isDayActive(key),
                        onChange: function () {
                            toggleDay(key);
                        },
                    });
                }),

                overrideDays.length > 0
                    ? el(
                          wp.components.Button,
                          {
                              variant: 'link',
                              isDestructive: true,
                              onClick: function () {
                                  setAttributes({ overrideDays: [] });
                              },
                              style: { marginTop: '8px' },
                          },
                          __('Zurücksetzen auf globale Einstellung', 'programmo')
                      )
                    : null
            )
        );

        /* --- Block Toolbar --- */
        var toolbar = el(
            BlockControls,
            null,
            el(
                ToolbarGroup,
                null,
                el(ToolbarButton, {
                    icon: compactMode ? 'editor-expand' : 'editor-contract',
                    label: compactMode ? __('Normal-Modus', 'programmo') : __('Kompakt-Modus', 'programmo'),
                    onClick: function () {
                        setAttributes({ compactMode: !compactMode });
                    },
                })
            )
        );

        /* --- Live Preview via ServerSideRender --- */
        var preview = el(ServerSideRender, {
            block: 'programmo/weekplan',
            attributes: attributes,
            EmptyResponsePlaceholder: function () {
                return el(
                    Placeholder,
                    {
                        icon: blockIcon,
                        label: __('ProgrammO Wochenplan', 'programmo'),
                    },
                    el('p', null, __('Noch keine Slots vorhanden. Erstelle zuerst Offene Bereiche und Slots im ProgrammO Dashboard.', 'programmo'))
                );
            },
        });

        return el(
            Fragment,
            null,
            inspector,
            toolbar,
            title
                ? el(
                      'div',
                      { className: 'programmo-block-wrapper' },
                      el('h2', { className: 'programmo-block-title' }, title),
                      preview
                  )
                : preview
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Register Block                                                    */
    /* ------------------------------------------------------------------ */
    registerBlockType('programmo/weekplan', {
        edit: ProgrammOEdit,

        /* Dynamic block → no save (rendered server-side) */
        save: function () {
            return null;
        },
    });
})();
