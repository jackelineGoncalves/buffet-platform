import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{jsx,tsx}',
    ],

    theme: {
        extend: {
            colors: {
                // Brand
                primary: {
                    DEFAULT:  '#FF5353',
                    foreground: '#FFFFFF',
                },
                // Surfaces
                background: '#0E131A',
                header: '#252D39',

                surface: {
                    DEFAULT: '#181E27',
                    muted: '#202732',
                    interactive: '#252D39',
                },

                // Content
                foreground: {
                    DEFAULT: '#F7F8FA',
                    muted: '#8993A3',
                    subtle: '#606B7A',
                },

                // Structure
                border: {
                    DEFAULT: '#28313D',
                    strong: '#566273',
                },

                // Kitchen status
                status: {
                    new: '#FF5C57',
                    preparing: '#F5A524',
                    ready: '#49C78E',
                },

                // Kitchen stations
                station: {
                    sushi: '#FF5C57',
                    hot: '#F5A524',
                    fryer: '#B547EB',
                    cold: '#4DA3F5',
                    bar: '#59B94C',
                },

                // Feedback
                success: '#49C78E',
                warning: '#F5A524',
                danger: '#EF4444',
                info: '#4DA3F5',
            },
            // ==========================================
        
            fontFamily: {
                sans:    ['Fredoka', ...defaultTheme.fontFamily.sans],
                display: ['Baloo Bhaijaan 2', ...defaultTheme.fontFamily.sans],
            },

            borderRadius: {
                card: '1.25rem',
                control: '0.75rem',
                pill: '9999px',
            },
        },
    },


    plugins: [forms],
};
