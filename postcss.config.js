import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';

export default {
    plugins: [
        // DHTMLX's distributed CSS includes Google-hosted Inter fonts. Do not
        // send the user's browser to a third party; retain the system fallback.
        {
            postcssPlugin: 'remove-remote-gantt-fonts',
            AtRule(rule) {
                if (rule.name === 'font-face' && rule.source?.input?.file?.includes('dhtmlx-gantt') && rule.toString().includes('https://fonts.gstatic.com/')) {
                    rule.remove();
                }
            },
        },
        tailwindcss(),
        autoprefixer(),
    ],
};
