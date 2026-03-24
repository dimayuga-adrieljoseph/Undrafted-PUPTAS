@if (config('app.sienna_widget.enabled'))
    <script
        src="https://cdn.jsdelivr.net/npm/sienna-accessibility@latest/dist/sienna-accessibility.umd.js"
        defer
        data-asw-position="{{ config('app.sienna_widget.position', 'bottom-right') }}"
        data-asw-offset="{{ config('app.sienna_widget.offset', '20,20') }}"
    ></script>

    <style>
:root {
    --asw-main-color: #800000;
    --asw-accent-color: #FFD700;
    --asw-bg: #ffffff;
    --asw-card-bg: #f9fafb;
    --asw-border: #e5e7eb;
    --asw-text: #1f2937;
}

/* =========================
   TRIGGER BUTTON
========================= */
.asw-menu-btn {
    background: linear-gradient(135deg, #800000, #5a0000) !important;
    border-radius: 16px !important;
    outline: none !important;
    box-shadow: 0 8px 20px rgba(0,0,0,0.25) !important;
    width: 60px !important;
    height: 60px !important;
}

/* =========================
   PANEL CONTAINER
========================= */
.asw-menu {
    border-radius: 20px !important;
    overflow: hidden !important;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3) !important;
}

/* =========================
   HEADER
========================= */
.asw-menu-header,
.asw-menu-header *:not(svg):not(button) {
    background: #800000 !important;
    background-color: #800000 !important;
    background-image: none !important;
    color: white !important;
}

.asw-menu-header {
    padding: 18px !important;
    font-size: 16px !important;
    font-weight: 600 !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.asw-menu-header svg,
.asw-menu-header button svg,
.asw-menu-header svg path,
.asw-menu-header svg line,
.asw-menu-header svg circle,
.asw-menu-header svg polyline {
    fill: white !important;
    stroke: white !important;
    color: white !important;
}

/* =========================
   CONTENT AREA
========================= */
.asw-menu-content {
    padding: 12px 14px !important;
    background: var(--asw-bg);
}

/* =========================
   SECTION CARDS
========================= */
.asw-card {
    background: var(--asw-card-bg);
    border: 1px solid var(--asw-border);
    border-radius: 15px;
    padding: 12px 14px;
}

.asw-card-title {
    font-size: 11px;
    font-weight: 700;
    color: #800000 !important;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 10px;
}

/* =========================
   BUTTON GRID
========================= */
.asw-btn-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    padding: 4px;
}

.asw-btn {
    border-radius: 12px !important;
    padding: 10px !important;
    font-size: 13px !important;
    border: 1px solid var(--asw-border) !important;
    background: white !important;
    transition: all 0.2s ease;
}

.asw-btn:hover {
    background: #f3f4f6 !important;
}

.asw-btn.active {
    background: #800000 !important;
    color: white !important;
    border-color: #800000 !important;
}

/* =========================
   SPEECH TO TEXT MIC BUTTON
========================= */
.stt-wrapper {
    position: relative;
    display: inline-block;
    width: 100%;
}

.stt-mic-btn {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: none;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    color: #9ca3af;
    transition: color 0.2s, background 0.2s;
    z-index: 10;
}

.stt-mic-btn:hover {
    color: #800000;
    background: rgba(128, 0, 0, 0.08);
}

.stt-mic-btn.stt-listening {
    color: #dc2626;
    animation: stt-pulse 1s ease-in-out infinite;
}

@keyframes stt-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.4; }
}

/* nudge the input text so it doesn't overlap the mic */
.stt-wrapper input,
.stt-wrapper textarea {
    padding-right: 40px !important;
}

.asw-footer {
    display: none !important;
}

/* =========================
   RESET BUTTON & BLUE OVERRIDES
========================= */
.asw-menu [class*="reset"],
.asw-menu button[class*="reset"],
.asw-menu [class*="Reset"] {
    background: #800000 !important;
    background-color: #800000 !important;
    border-color: #800000 !important;
    color: white !important;
}

/* catch any remaining blue text/links inside the widget */
.asw-menu [style*="color: #2563eb"],
.asw-menu [style*="color: rgb(37, 99, 235)"],
.asw-menu [style*="color:#2563eb"] {
    color: #800000 !important;
}

.asw-menu a,
.asw-menu [class*="link"] {
    color: #800000 !important;
}
    </style>

    <script>
        (function () {
            // --- Sienna color theming ---
            function applyStyles() {
                const btn = document.querySelector('.asw-menu-btn');
                const header = document.querySelector('.asw-menu-header');

                if (btn) {
                    btn.style.setProperty('background', 'linear-gradient(96deg, #800000 0, #800000 100%)', 'important');
                    btn.style.setProperty('outline', '5px solid #800000', 'important');
                }

                if (header) {
                    header.style.setProperty('background', '#800000', 'important');
                    header.style.setProperty('background-color', '#800000', 'important');
                    header.style.setProperty('background-image', 'none', 'important');
                }

                // Header icons
                document.querySelectorAll('.asw-menu-header svg, .asw-menu-header button').forEach(el => {
                    el.style.setProperty('color', 'white', 'important');
                    el.style.setProperty('fill', 'white', 'important');
                    el.style.setProperty('stroke', 'white', 'important');
                });
                document.querySelectorAll('.asw-menu-header svg *').forEach(el => {
                    el.style.setProperty('fill', 'white', 'important');
                    el.style.setProperty('stroke', 'white', 'important');
                });
                document.querySelectorAll('.asw-menu button, .asw-menu [role="button"]').forEach(el => {
                    const text = (el.textContent || '').toLowerCase().trim();
                    if (text.includes('reset')) {
                        el.style.setProperty('background', '#800000', 'important');
                        el.style.setProperty('background-color', '#800000', 'important');
                        el.style.setProperty('border-color', '#800000', 'important');
                        el.style.setProperty('color', 'white', 'important');
                    }
                });

                // Section titles
                document.querySelectorAll('.asw-card-title, [class*="card-title"], [class*="section-title"]').forEach(el => {
                    el.style.setProperty('color', '#800000', 'important');
                });
            }

            // --- MutationObserver to watch for Sienna rendering ---
            const observer = new MutationObserver(function () {
                applyStyles();
            });

            observer.observe(document.body, { childList: true, subtree: true });
            document.addEventListener('DOMContentLoaded', function () {
                applyStyles();
            });

            // --- Speech-to-Text logic ---
            const STT = window.SpeechRecognition || window.webkitSpeechRecognition;
            let sttRecognition = null;
            let sttActiveField = null;
            let sttShouldRestart = false;
            let sttActiveMicBtn = null;

            const micSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                <line x1="12" y1="19" x2="12" y2="23"/>
                <line x1="8" y1="23" x2="16" y2="23"/>
            </svg>`;

            function stopSTT() {
                sttShouldRestart = false;
                if (sttActiveMicBtn) sttActiveMicBtn.classList.remove('stt-listening');
                if (sttRecognition) {
                    try { sttRecognition.abort(); } catch(e) {}
                    sttRecognition = null;
                }
                sttActiveField = null;
                sttActiveMicBtn = null;
            }

            function createRecognition(field, micBtn) {
                const r = new STT();
                r.lang = 'en-US';
                r.interimResults = false;
                r.continuous = false;
                r.maxAlternatives = 1;

                r.onresult = function (event) {
                    const result = event.results[event.results.length - 1];
                    if (!result.isFinal) return;
                    const transcript = result[0].transcript;
                    const start = field.selectionStart ?? field.value.length;
                    const end = field.selectionEnd ?? field.value.length;
                    const current = field.value;
                    const spacer = current.length > 0 && !current.endsWith(' ') ? ' ' : '';
                    field.value = current.slice(0, start) + spacer + transcript + current.slice(end);
                    const newPos = start + spacer.length + transcript.length;
                    field.setSelectionRange(newPos, newPos);
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                };

                r.onerror = function (event) {
                    if (event.error === 'no-speech' || event.error === 'aborted') return;
                    console.warn('STT error:', event.error);
                    stopSTT();
                };

                r.onend = function () {
                    if (sttShouldRestart && sttActiveField === field) {
                        setTimeout(() => {
                            if (sttShouldRestart && sttActiveField === field) {
                                sttRecognition = createRecognition(field, micBtn);
                                try { sttRecognition.start(); } catch(e) { console.warn('STT restart failed:', e); }
                            }
                        }, 150);
                    } else {
                        if (sttActiveMicBtn) sttActiveMicBtn.classList.remove('stt-listening');
                    }
                };

                return r;
            }

            function startSTT(field, micBtn) {
                if (!STT) {
                    alert('Speech recognition is not supported in this browser.');
                    return;
                }

                stopSTT();

                sttActiveField = field;
                sttActiveMicBtn = micBtn;
                sttShouldRestart = true;
                micBtn.classList.add('stt-listening');

                sttRecognition = createRecognition(field, micBtn);
                try {
                    sttRecognition.start();
                } catch(e) {
                    console.warn('STT start failed:', e);
                    stopSTT();
                }
            }

            function attachMicToField(field) {
                if (field.dataset.sttAttached || field.closest('.asw-widget, .asw-menu')) return;
                if (field.type === 'hidden' || field.readOnly || field.disabled) return;

                field.dataset.sttAttached = 'true';

                const wrapper = document.createElement('div');
                wrapper.className = 'stt-wrapper';
                wrapper.style.cssText = window.getComputedStyle(field).display === 'block' ? 'display:block' : 'display:inline-block';

                field.parentNode.insertBefore(wrapper, field);
                wrapper.appendChild(field);

                const micBtn = document.createElement('button');
                micBtn.type = 'button';
                micBtn.className = 'stt-mic-btn';
                micBtn.setAttribute('aria-label', 'Speak to fill this field');
                micBtn.innerHTML = micSvg;

                if (field.tagName === 'TEXTAREA') {
                    micBtn.style.top = '10px';
                    micBtn.style.transform = 'none';
                }

                micBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    if (sttActiveField === field) {
                        stopSTT();
                    } else {
                        startSTT(field, micBtn);
                    }
                });

                wrapper.appendChild(micBtn);
            }

            function attachMicToAllFields() {
                const selector = 'input[type="text"], input[type="search"], input[type="email"], input:not([type]), textarea';
                document.querySelectorAll(selector).forEach(attachMicToField);
            }

            document.addEventListener('DOMContentLoaded', attachMicToAllFields);

            const sttObserver = new MutationObserver(() => attachMicToAllFields());
            sttObserver.observe(document.body, { childList: true, subtree: true });

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) stopSTT();
            });
            window.addEventListener('beforeunload', stopSTT);
        })();
    </script>
@endif
