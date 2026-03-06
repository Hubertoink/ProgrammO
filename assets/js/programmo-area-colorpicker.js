/**
 * ProgrammO – Enhanced Colorpicker for Area Metabox
 *
 * Tabbed UI: Volltonfarbe / Verlauf / Frei (CSS)
 * Writes result to hidden inputs for form submission.
 */
(function () {
    'use strict';

    /* ---- Helpers ---- */
    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
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

    function parseColorSpec(color, gradient) {
        var state = {
            mode: 'solid',
            color1: color || '#90d7f3',
            color2: '#4facfe',
            angle: '135',
            css: gradient || '',
        };

        if (gradient && gradient.trim()) {
            var raw = gradient.trim();
            if (raw.indexOf('linear-gradient(') === 0) {
                var m = raw.match(/linear-gradient\(\s*(\d+)deg\s*,\s*([^,]+)\s*,\s*([^)]+)\s*\)/i);
                if (m) {
                    state.mode = 'gradient';
                    state.angle = m[1];
                    state.color1 = normalizeHexColor(m[2].trim()) || state.color1;
                    state.color2 = normalizeHexColor(m[3].trim()) || state.color2;
                } else {
                    state.mode = 'free';
                    state.css = raw;
                }
            } else {
                state.mode = 'free';
                state.css = raw;
            }
        }

        return state;
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

    /* ---- Init ---- */
    function init() {
        var root = document.getElementById('programmo-area-colorpicker');
        if (!root) return;

        var colorHidden = document.getElementById('programmo_area_color_hidden');
        var gradientHidden = document.getElementById('programmo_area_gradient_hidden');

        var initColor = root.getAttribute('data-color') || '#90d7f3';
        var initGradient = root.getAttribute('data-gradient') || '';
        var state = parseColorSpec(initColor, initGradient);
        var mode = state.mode;

        root.innerHTML =
            '<div class="programmo-area-cp">' +
                '<div class="programmo-area-cp__tabs">' +
                    '<button type="button" class="programmo-area-cp__tab" data-mode="solid">Volltonfarbe</button>' +
                    '<button type="button" class="programmo-area-cp__tab" data-mode="gradient">Verlauf</button>' +
                    '<button type="button" class="programmo-area-cp__tab" data-mode="free">Frei (CSS)</button>' +
                '</div>' +

                '<div class="programmo-area-cp__group" data-group="solid">' +
                    '<div class="programmo-area-cp__field-row">' +
                        '<label>Farbe</label>' +
                        '<div class="programmo-area-cp__input-pair">' +
                            '<input type="color" class="programmo-area-cp__color-input" data-bind="solid"      value="' + escHtml(state.color1) + '">' +
                            '<input type="text"  class="programmo-area-cp__hex-input"   data-bind="solid-hex"  value="' + escHtml(state.color1) + '" maxlength="7" placeholder="#RRGGBB">' +
                        '</div>' +
                    '</div>' +
                '</div>' +

                '<div class="programmo-area-cp__group" data-group="gradient">' +
                    '<div class="programmo-area-cp__field-row">' +
                        '<label>Farbe 1</label>' +
                        '<div class="programmo-area-cp__input-pair">' +
                            '<input type="color" class="programmo-area-cp__color-input" data-bind="grad1"      value="' + escHtml(state.color1) + '">' +
                            '<input type="text"  class="programmo-area-cp__hex-input"   data-bind="grad1-hex"  value="' + escHtml(state.color1) + '" maxlength="7" placeholder="#RRGGBB">' +
                        '</div>' +
                    '</div>' +
                    '<div class="programmo-area-cp__field-row">' +
                        '<label>Farbe 2</label>' +
                        '<div class="programmo-area-cp__input-pair">' +
                            '<input type="color" class="programmo-area-cp__color-input" data-bind="grad2"      value="' + escHtml(state.color2) + '">' +
                            '<input type="text"  class="programmo-area-cp__hex-input"   data-bind="grad2-hex"  value="' + escHtml(state.color2) + '" maxlength="7" placeholder="#RRGGBB">' +
                        '</div>' +
                    '</div>' +
                    '<div class="programmo-area-cp__field-row">' +
                        '<label>Winkel</label>' +
                        '<select class="programmo-area-cp__angle">' +
                            '<option value="0">0°</option>' +
                            '<option value="45">45°</option>' +
                            '<option value="90">90°</option>' +
                            '<option value="135">135°</option>' +
                            '<option value="180">180°</option>' +
                            '<option value="225">225°</option>' +
                            '<option value="270">270°</option>' +
                            '<option value="315">315°</option>' +
                        '</select>' +
                    '</div>' +
                '</div>' +

                '<div class="programmo-area-cp__group" data-group="free">' +
                    '<div class="programmo-area-cp__field-row">' +
                        '<label>Freier CSS-Background</label>' +
                        '<input type="text" class="programmo-area-cp__free large-text" placeholder="z.B. linear-gradient(135deg, #90d7f3, #4facfe)" value="' + escHtml(state.css || '') + '">' +
                    '</div>' +
                '</div>' +

                '<div class="programmo-area-cp__preview"></div>' +

                '<div class="programmo-area-cp__presets">' +
                    '<button type="button" class="programmo-area-cp__preset" data-val="#90d7f3" style="background:#90d7f3" title="Standard Hellblau"></button>' +
                    '<button type="button" class="programmo-area-cp__preset" data-val="linear-gradient(135deg, #90d7f3, #4facfe)" style="background:linear-gradient(135deg, #90d7f3, #4facfe)" title="Blau-Verlauf"></button>' +
                    '<button type="button" class="programmo-area-cp__preset" data-val="linear-gradient(135deg, #fbc2eb, #a6c1ee)" style="background:linear-gradient(135deg, #fbc2eb, #a6c1ee)" title="Rosa-Blau"></button>' +
                    '<button type="button" class="programmo-area-cp__preset" data-val="linear-gradient(135deg, #a8edea, #fed6e3)" style="background:linear-gradient(135deg, #a8edea, #fed6e3)" title="Mint-Rosa"></button>' +
                    '<button type="button" class="programmo-area-cp__preset" data-val="linear-gradient(135deg, #f5af19, #f12711)" style="background:linear-gradient(135deg, #f5af19, #f12711)" title="Orange-Rot"></button>' +
                    '<button type="button" class="programmo-area-cp__preset" data-val="linear-gradient(135deg, #11998e, #38ef7d)" style="background:linear-gradient(135deg, #11998e, #38ef7d)" title="Grün"></button>' +
                '</div>' +
            '</div>';

        /* Grab controls */
        var solidInput = root.querySelector('[data-bind="solid"]');
        var solidHex   = root.querySelector('[data-bind="solid-hex"]');
        var grad1Input = root.querySelector('[data-bind="grad1"]');
        var grad1Hex   = root.querySelector('[data-bind="grad1-hex"]');
        var grad2Input = root.querySelector('[data-bind="grad2"]');
        var grad2Hex   = root.querySelector('[data-bind="grad2-hex"]');
        var angleInput = root.querySelector('.programmo-area-cp__angle');
        var freeInput  = root.querySelector('.programmo-area-cp__free');
        var preview    = root.querySelector('.programmo-area-cp__preview');

        angleInput.value = state.angle;

        /* Pair syncing: color input ↔ hex text input */
        function syncColorToHex(colorEl, hexEl) {
            colorEl.addEventListener('input', function () {
                hexEl.value = colorEl.value;
                updatePreview();
            });
            hexEl.addEventListener('input', function () {
                var norm = normalizeHexColor(hexEl.value);
                if (norm) {
                    colorEl.value = norm;
                    updatePreview();
                }
            });
            hexEl.addEventListener('change', function () {
                var norm = normalizeHexColor(hexEl.value);
                if (norm) {
                    hexEl.value = norm;
                    colorEl.value = norm;
                    updatePreview();
                }
            });
        }

        syncColorToHex(solidInput, solidHex);
        syncColorToHex(grad1Input, grad1Hex);
        syncColorToHex(grad2Input, grad2Hex);

        angleInput.addEventListener('change', updatePreview);
        freeInput.addEventListener('input', updatePreview);

        function applyModeUI() {
            root.querySelectorAll('.programmo-area-cp__tab').forEach(function (btn) {
                btn.classList.toggle('is-active', btn.getAttribute('data-mode') === mode);
            });
            root.querySelectorAll('.programmo-area-cp__group').forEach(function (group) {
                group.style.display = group.getAttribute('data-group') === mode ? 'block' : 'none';
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

            /* Write to hidden inputs */
            if (mode === 'solid') {
                colorHidden.value = solidInput.value;
                gradientHidden.value = '';
            } else if (mode === 'gradient') {
                colorHidden.value = grad1Input.value;
                gradientHidden.value = val;
            } else {
                colorHidden.value = '';
                gradientHidden.value = val;
            }
        }

        /* Tab clicks */
        root.querySelectorAll('.programmo-area-cp__tab').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                mode = btn.getAttribute('data-mode');
                applyModeUI();
            });
        });

        /* Presets */
        root.querySelectorAll('.programmo-area-cp__preset').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var val = btn.getAttribute('data-val') || '';
                var parsed = parseColorSpec(val.indexOf('linear-gradient') === 0 ? '' : val, val.indexOf('linear-gradient') === 0 ? val : '');
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

        applyModeUI();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
