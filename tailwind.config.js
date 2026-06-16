import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * LaraJob design tokens.
 * `brand` is the primary identity colour (a refined indigo-violet). We also
 * alias Tailwind's default `indigo` to the same scale so every legacy
 * `indigo-*` utility already in the app adopts the brand automatically —
 * one source of truth, zero drift.
 */
const brand = {
    50: '#eef2ff',
    100: '#e0e7ff',
    200: '#c7d2fe',
    300: '#a5b4fc',
    400: '#818cf8',
    500: '#6366f1',
    600: '#4f46e5',
    700: '#4338ca',
    800: '#3730a3',
    900: '#312e81',
    950: '#1e1b4b',
};

/** Warm accent, used sparingly for highlights (salary, "new", etc.). */
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
                glow: '0 10px 40px -12px rgb(79 70 229 / 0.45)',
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
            },
            animation: {
                'fade-in-up': 'fade-in-up 0.5s ease-out both',
                'toast-in': 'toast-in 0.25s ease-out both',
            },
        },
    },

    plugins: [forms],
};
