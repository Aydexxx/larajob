import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * LaraJob design tokens.
 *
 * `brand` is our signature colour — a deep, confident teal, deliberately
 * chosen over the default indigo/violet every other job board reaches for.
 * It doubles as the "strong match" colour in the AI match ring, so the
 * identity and the product's core idea (great matches) are the same hue.
 *
 * We alias Tailwind's default `indigo` to this same scale, so every legacy
 * `indigo-*` utility already in the app adopts the signature automatically —
 * one source of truth, zero drift.
 */
const brand = {
    50: '#eefdfb',
    100: '#d3f8f3',
    200: '#abefe8',
    300: '#72e1d8',
    400: '#33c9c0',
    500: '#14ada6',
    600: '#0b8c87',
    700: '#0e6f6c',
    800: '#125858',
    900: '#144a4a',
    950: '#052e2e',
};

/** Warm amber accent — the counterweight to teal: salary, "new", mid-match. */
const accent = {
    50: '#fffbeb',
    100: '#fef3c7',
    200: '#fde68a',
    300: '#fcd34d',
    400: '#fbbf24',
    500: '#f59e0b',
    600: '#d97706',
    700: '#b45309',
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                brand,
                accent,
                indigo: brand,
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                '2xs': ['0.6875rem', { lineHeight: '1rem' }],
            },
            borderRadius: {
                '4xl': '2rem',
            },
            boxShadow: {
                soft: '0 1px 2px 0 rgb(16 24 40 / 0.04), 0 1px 3px 0 rgb(16 24 40 / 0.06)',
                card: '0 1px 3px 0 rgb(16 24 40 / 0.08), 0 1px 2px -1px rgb(16 24 40 / 0.06)',
                elevated: '0 10px 25px -5px rgb(16 24 40 / 0.10), 0 8px 10px -6px rgb(16 24 40 / 0.06)',
                glow: '0 10px 40px -12px rgb(11 140 135 / 0.45)',
                'glow-lg': '0 18px 60px -14px rgb(11 140 135 / 0.5)',
            },
            keyframes: {
                'fade-in-up': {
                    '0%': { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'toast-in': {
                    '0%': { opacity: '0', transform: 'translateY(-12px) scale(0.98)' },
                    '100%': { opacity: '1', transform: 'translateY(0) scale(1)' },
                },
                /* Slow, barely-there drift for the hero's decorative glow blobs. */
                'float-slow': {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-16px)' },
                },
            },
            animation: {
                'fade-in-up': 'fade-in-up 0.5s ease-out both',
                'toast-in': 'toast-in 0.25s ease-out both',
                'float-slow': 'float-slow 9s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
