// Verifies the WCAG 2.1 contrast of the design-system colour pairs used by the
// interface. Automated thresholds complement, but do not replace, a human review
// of rendered pages and of antialiasing on the target screens.
const tokens = {
    canvas: '#F6F8FC',
    surface: '#FFFFFF',
    ink: '#172033',
    slate600: '#475569',
    brand500: '#4F46E5',
    brand600: '#4338CA',
    brand700: '#3730A3',
    brand50: '#EEF2FF',
    white: '#FFFFFF',
    amber50: '#FFFBEB',
    amber900: '#78350F',
    emerald50: '#ECFDF5',
    emerald900: '#064E3B',
    red50: '#FEF2F2',
    red900: '#7F1D1D',
    blue50: '#EFF6FF',
    blue900: '#1E3A8A',
    slate50: '#F8FAFC',
    slate200: '#E2E8F0',
};

function channel(value) {
    const c = value / 255;
    return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
}

function luminance(hex) {
    const value = hex.replace('#', '');
    const [r, g, b] = [0, 2, 4].map(offset => parseInt(value.slice(offset, offset + 2), 16));
    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);
}

function ratio(foreground, background) {
    const a = luminance(tokens[foreground] ?? foreground);
    const b = luminance(tokens[background] ?? background);
    const [light, dark] = a > b ? [a, b] : [b, a];
    return (light + 0.05) / (dark + 0.05);
}

// [foreground, background, minimum ratio, context]
const pairs = [
    ['ink', 'canvas', 4.5, 'body text on canvas'],
    ['ink', 'surface', 4.5, 'body text on surface'],
    ['slate600', 'surface', 4.5, 'secondary text on surface'],
    ['slate600', 'canvas', 4.5, 'secondary text on canvas'],
    ['brand700', 'surface', 4.5, 'links and eyebrow labels'],
    ['brand700', 'canvas', 4.5, 'links on canvas'],
    ['white', 'brand600', 4.5, 'primary button label'],
    ['brand700', 'brand50', 4.5, 'brand badge'],
    ['amber900', 'amber50', 4.5, 'warning alert'],
    ['emerald900', 'emerald50', 4.5, 'success alert'],
    ['red900', 'red50', 4.5, 'error alert'],
    ['blue900', 'blue50', 4.5, 'information alert'],
    ['ink', 'slate50', 4.5, 'table header text'],
    ['brand600', 'surface', 3, 'focus outline against surface'],
    ['slate200', 'surface', 1.2, 'subtle borders (informational)'],
];

const failures = pairs.filter(([foreground, background, minimum]) => ratio(foreground, background) < minimum - 0.005);
for (const [foreground, background, minimum, context] of pairs) {
    console.log(`${context}: ${ratio(foreground, background).toFixed(2)}:1 (minimum ${minimum}:1)`);
}
if (failures.length) {
    for (const [foreground, background, minimum, context] of failures) {
        console.error(`::error title=Contrast::${context} is ${ratio(foreground, background).toFixed(2)}:1, below ${minimum}:1`);
    }
    process.exitCode = 1;
} else {
    console.log(`All ${pairs.length} token pairs meet their contrast threshold.`);
}
