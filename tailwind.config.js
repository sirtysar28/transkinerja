import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Plus Jakarta Sans', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                /* Palet DigiTrans OSDMRB — teal petrol + aksen emas */
                navy: {
                    50: '#F2F6F8',
                    100: '#E2ECF0',
                    200: '#C3D8E0',
                    300: '#8AB3C5',
                    400: '#5E8FA6',
                    500: '#3A7490',
                    600: '#2B5F78',
                    700: '#1F4C61',
                    800: '#163D4F',
                    900: '#0E2A37',
                    950: '#081C26',
                },
                gold: {
                    400: '#E8A13C',
                    500: '#CF8F2E',
                },
            },
            boxShadow: {
                card: '0 1px 3px 0 rgb(11 42 91 / 0.08), 0 4px 14px -4px rgb(11 42 91 / 0.12)',
            },
        },
    },
    plugins: [],
};
