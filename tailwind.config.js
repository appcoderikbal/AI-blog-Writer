/** @type {import('tailwindcss').Config} */
export default {
  // Scope ALL Tailwind utilities to #tzaw-root so they don't fight WP admin CSS
  important: '#tzaw-root',
  content: [
    './src/**/*.{tsx,ts,jsx,js}',
  ],
  theme: {
    extend: {
      colors: {
        sidebar: {
          DEFAULT: '#f7f7f8',
          dark: '#202123',
        },
        surface: {
          DEFAULT: '#ffffff',
          hover: '#f7f7f8',
          border: '#e5e5e5',
        },
        brand: {
          DEFAULT: '#10a37f',
          hover: '#0d8a6c',
          light: '#e6f7f3',
        },
        text: {
          primary: '#0d0d0d',
          secondary: '#6e6e80',
          muted: '#acacbe',
        },
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'sans-serif'],
        mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
      },
      boxShadow: {
        card: '0 1px 3px 0 rgba(0,0,0,0.08), 0 1px 2px -1px rgba(0,0,0,0.06)',
        popup: '0 4px 16px rgba(0,0,0,0.12)',
      },
      borderRadius: {
        xl: '12px',
        '2xl': '16px',
      },
      animation: {
        'fade-in': 'fadeIn 0.2s ease-out',
        'slide-up': 'slideUp 0.3s ease-out',
        'pulse-soft': 'pulseSoft 1.5s ease-in-out infinite',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { opacity: '0', transform: 'translateY(8px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        pulseSoft: {
          '0%, 100%': { opacity: '1' },
          '50%': { opacity: '0.5' },
        },
      },
    },
  },
  plugins: [],
};
