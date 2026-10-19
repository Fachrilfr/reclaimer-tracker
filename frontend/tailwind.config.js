/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        indofood: {
          blue: '#005baa',
          red: '#e31b23',
          dark: '#0f172a',
          surface: '#1e293b',
        }
      }
    },
  },
  plugins: [],
}
