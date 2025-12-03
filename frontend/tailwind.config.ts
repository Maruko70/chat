import type { Config } from 'tailwindcss'
import PrimeUI from 'tailwindcss-primeui'

export default {
  content: [
    './app/components/**/*.{vue,js,ts}',
    './app/layouts/**/*.vue',
    './app/pages/**/*.vue',
    './app/plugins/**/*.{js,ts}',
    './app/app.vue',
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: 'var(--site-primary-color, #450924)',
          50: '#fef2f3',
          100: '#fde4e6',
          200: '#fbcbd1',
          300: '#f7a1ab',
          400: '#f16d7d',
          500: '#e8455a',
          600: '#d6283f',
          700: '#b31e32',
          800: '#941c2d',
          900: '#7d1d2a',
          950: '#450924',
        },
        secondary: {
          DEFAULT: 'var(--site-secondary-color, #ffffff)',
        },
        button: {
          DEFAULT: 'var(--site-button-color, #3D0821)',
        },
      },
    },
  },
  plugins: [
    PrimeUI
  ],
} satisfies Config