/** @type {import('tailwindcss').Config} */
const plex = ['"IBM Plex Sans"', '"IBM Plex Sans Arabic"', 'Inter', 'system-ui', '-apple-system', '"Segoe UI"', 'sans-serif'];

module.exports = {
    content: [
        "./src/Resources/**/*.blade.php",
        "./src/Resources/**/*.js",
        "../Experiment/src/Resources/**/*.blade.php",
        "../Security/src/Resources/**/*.blade.php",
    ],

    theme: {
        container: {
            center: true,

            screens: {
                "4xl": "1920px",
            },

            padding: {
                DEFAULT: "16px",
            },
        },

        screens: {
            sm: "525px",
            md: "768px",
            lg: "1024px",
            xl: "1240px",
            "2xl": "1440px",
            "3xl": "1680px",
            "4xl": "1920px",
        },

        extend: {
            colors: {
                brandColor: 'var(--brand-color, #0F766E)',

                /**
                 * Workbench neutrals (docs/design.md): the stock gray ramp tinted
                 * toward the brand hue (OKLCH hue 190), so every admin screen picks
                 * up the same paper, hairline and ink without per-view edits.
                 */
                gray: {
                    50: 'oklch(0.985 0.003 190 / <alpha-value>)',
                    100: 'oklch(0.968 0.005 190 / <alpha-value>)',
                    200: 'oklch(0.935 0.007 190 / <alpha-value>)',
                    300: 'oklch(0.89 0.009 190 / <alpha-value>)',
                    400: 'oklch(0.72 0.012 190 / <alpha-value>)',
                    500: 'oklch(0.55 0.015 190 / <alpha-value>)',
                    600: 'oklch(0.45 0.018 190 / <alpha-value>)',
                    700: 'oklch(0.37 0.02 190 / <alpha-value>)',
                    800: 'oklch(0.28 0.022 190 / <alpha-value>)',
                    900: 'oklch(0.195 0.013 190 / <alpha-value>)',
                    950: 'oklch(0.155 0.012 190 / <alpha-value>)',
                },

                /* The agent and unsigned work. Never decorative. */
                pencil: 'var(--lum-pencil)',
                'pencil-wash': 'var(--lum-pencil-wash)',
            },

            fontFamily: {
                sans: plex,
                inter: plex,
                mono: ['"IBM Plex Mono"', 'ui-monospace', '"Cascadia Mono"', 'Consolas', 'monospace'],
                icon: ['icomoon']
            }
        },
    },

    darkMode: 'class',

    plugins: [],

    safelist: [
        {
            pattern: /icon-/,
        }
    ]
};
