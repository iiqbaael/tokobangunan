/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    // Tema (terang/gelap) di-switch lewat atribut data-theme di <html>,
    // bukan lewat class Tailwind. Warna semua ambil dari CSS var di
    // resources/css/app.css (Design System v1.2), jadi otomatis ikut
    // berubah tanpa perlu varian dark: di tiap komponen.
    darkMode: 'selector',

    theme: {
        extend: {
            fontFamily: {
                sans: ['Plus Jakarta Sans', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
            },
            colors: {
                page: 'var(--bg-page)',
                surface: {
                    DEFAULT: 'var(--bg-surface)',
                    muted: 'var(--bg-surface-muted)',
                },
                border: 'var(--border)',
                text: {
                    primary: 'var(--text-primary)',
                    secondary: 'var(--text-secondary)',
                    disabled: 'var(--text-disabled)',
                },
                brand: {
                    primary: 'var(--brand-primary)',
                    'primary-hover': 'var(--brand-primary-hover)',
                    'primary-soft': 'var(--brand-primary-soft)',
                    secondary: 'var(--brand-secondary)',
                    dark: 'var(--brand-dark)',
                },
                status: {
                    success: 'var(--status-success)',
                    'success-soft': 'var(--status-success-soft)',
                    warning: 'var(--status-warning)',
                    'warning-soft': 'var(--status-warning-soft)',
                    danger: 'var(--status-danger)',
                    'danger-soft': 'var(--status-danger-soft)',
                    neutral: 'var(--status-neutral)',
                    'neutral-soft': 'var(--status-neutral-soft)',
                    info: 'var(--status-info)',
                    'info-soft': 'var(--status-info-soft)',
                },
            },
            borderRadius: {
                sm: 'var(--radius-sm)',
                md: 'var(--radius-md)',
                lg: 'var(--radius-lg)',
            },
        },
    },

    plugins: [require('@tailwindcss/forms')],
};