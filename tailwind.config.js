import defaultTheme from 'tailwindcss/defaultTheme';

export default {
    content: ['./resources/**/*.blade.php', './resources/**/*.js'],
    theme: {
        extend: {
            fontFamily: {
                sans: ['ui-sans-serif', 'system-ui', ...defaultTheme.fontFamily.sans],
                arabic: ['Tahoma', 'Arial', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [],
};
