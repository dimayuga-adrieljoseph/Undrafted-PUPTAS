import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', 'Fira Code', 'Cascadia Code', ...defaultTheme.fontFamily.mono],
            },

            colors: {
                // ── PUPTAS primary brand palette ──────────────────────────
                maroon: {
                    DEFAULT: '#800000',
                    dark:    '#6a0000',
                    light:   '#9E122C',
                    50:      '#FFF5F5',
                    100:     '#FFE3E3',
                    200:     '#FFC5C5',
                    800:     '#5c0000',
                    900:     '#3d0000',
                },
                gold: {
                    DEFAULT: '#FFD700',
                    muted:   '#C18C5D',
                    warm:    '#D4A017',
                    light:   '#FFFBEA',
                    50:      '#FFFDE7',
                    100:     '#FFF8C4',
                },
                // ── Neutral scale ─────────────────────────────────────────
                ink: {
                    DEFAULT: '#151515',
                    secondary: '#333333',
                    muted:     '#555555',
                    subtle:    '#6B7280',
                    ghost:     '#9CA3AF',
                },
                surface: {
                    DEFAULT: '#FFFFFF',
                    soft:    '#FAFAF9',
                    warm:    '#F9F7F4',
                    muted:   '#F3F2F1',
                },
                // ── Dashboard semantic status colors ──────────────────────
                // These are deliberately kept as descriptive names so they
                // don't clash with Tailwind's built-in semantic names.
                status: {
                    success:  '#15803D', // green-700
                    warning:  '#B45309', // amber-700
                    danger:   '#B91C1C', // red-700
                    info:     '#1D4ED8', // blue-700
                },
            },

            // ── Dashboard-specific spacing / sizing ───────────────────────
            spacing: {
                'dash-gutter': '1.5rem',
            },

            borderRadius: {
                'card': '0.875rem',
            },

            boxShadow: {
                'card':       '0 1px 3px 0 rgb(0 0 0 / 0.07), 0 1px 2px -1px rgb(0 0 0 / 0.07)',
                'card-hover': '0 4px 12px 0 rgb(0 0 0 / 0.10), 0 2px 4px -1px rgb(0 0 0 / 0.06)',
                'maroon':     '0 4px 14px 0 rgb(128 0 0 / 0.20)',
            },

            animation: {
                'fade-in':    'fadeIn 0.4s ease forwards',
                'slide-up':   'slideUp 0.35s ease forwards',
                'count-up':   'countUp 0.6s ease forwards',
            },

            keyframes: {
                fadeIn: {
                    from: { opacity: '0' },
                    to:   { opacity: '1' },
                },
                slideUp: {
                    from: { opacity: '0', transform: 'translateY(12px)' },
                    to:   { opacity: '1', transform: 'translateY(0)' },
                },
                countUp: {
                    from: { opacity: '0', transform: 'translateY(6px)' },
                    to:   { opacity: '1', transform: 'translateY(0)' },
                },
            },
        },
    },

    plugins: [forms, typography],
};
