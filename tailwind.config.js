import defaultTheme from 'tailwindcss/defaultTheme';

export default {
    content: ['./resources/**/*.blade.php', './resources/**/*.js'],
    theme: {
        extend: {
            colors: {
                brand: { 50: '#EEF2FF', 100: '#E0E7FF', 500: '#4F46E5', 600: '#4338CA', 700: '#3730A3' },
                canvas: '#F6F8FC', ink: '#172033', accent: '#0F766E',
                success: '#047857', warning: '#92400E', danger: '#B91C1C', info: '#0369A1',
            },
            boxShadow: { soft: '0 12px 32px -20px rgba(23, 32, 51, 0.2)' },
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', ...defaultTheme.fontFamily.sans],
                arabic: ['Cairo', 'Tahoma', 'Arial', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [],
};
