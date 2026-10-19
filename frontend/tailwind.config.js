import defaultTheme from 'tailwindcss/defaultTheme';

// Token mengikuti design.md §2, §3, §4. Ubah di sana dulu, baru di sini.
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          blue: {
            50: '#EAF3FB', 100: '#CFE3F5', 500: '#2A84C9',
            600: '#1B75BB', 700: '#155F98', 800: '#0F4A78', 900: '#0A3557',
          },
          red: { 600: '#E41E26', 700: '#B8171E' },
        },
        gray: {
          0: '#FFFFFF', 25: '#F7F9FC', 100: '#EEF1F5', 200: '#DDE3EA',
          400: '#9AA5B1', 600: '#5B6673', 800: '#2B333D', 900: '#141A21',
        },
        success: { fg: '#157F3D', bg: '#E8F6EE', border: '#A7DDBC' },
        // design.md memakai #1B75BB, tapi di atas info.bg kontrasnya 4,34:1 (di bawah AA 4,5:1)
        info: { fg: '#155F98', bg: '#EAF3FB', border: '#B7D6EF' },
        warning: { fg: '#9A6700', bg: '#FFF6DB', border: '#F2D27A' },
        urgent: { fg: '#C2410C', bg: '#FFEDE3', border: '#F7B894' },
        danger: { fg: '#B42318', bg: '#FDECEA', border: '#F4B4AE' },
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
        mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
      },
      fontSize: {
        caption: ['12px', '16px'],
        label: ['13px', '18px'],
        body: ['14px', '22px'],
        'body-lg': ['16px', '24px'],
        h3: ['16px', '24px'],
        h2: ['20px', '28px'],
        h1: ['24px', '32px'],
        display: ['30px', '38px'],
      },
      borderRadius: { card: '12px', modal: '16px' },
      boxShadow: {
        card: '0 1px 2px rgba(16,24,40,.06), 0 1px 3px rgba(16,24,40,.08)',
        pop: '0 8px 24px rgba(16,24,40,.12)',
      },
      maxWidth: { content: '1440px' },
    },
  },
  plugins: [],
};
