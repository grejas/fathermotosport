import type { Config } from "tailwindcss";

const config: Config = {
  content: [
    "./app/**/*.{js,ts,jsx,tsx,mdx}",
    "./components/**/*.{js,ts,jsx,tsx,mdx}",
    "./animations/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          red: "#E8001D",
          gold: "#C9A84C",
          carbon: "#0A0A0A",
          dark: "#111111",
          card: "#141414",
          white: "#F5F5F5",
          muted: "#888888",
        },
        cat: {
          helmets: "#E8001D",
          gloves: "#6C63FF",
          boots: "#00C897",
          jackets: "#FF7A00",
          parts: "#C9A84C",
          accs: "#00B4D8",
        },
      },
      fontFamily: {
        sans: ["var(--font-inter)", "system-ui", "sans-serif"],
      },
      animation: {
        float: "float 6s ease-in-out infinite",
        "pulse-red": "pulse-red 2s ease-in-out infinite",
        marquee: "marquee 30s linear infinite",
        "fade-in": "fade-in 0.6s ease-out forwards",
        "slide-up": "slide-up 0.6s ease-out forwards",
      },
      keyframes: {
        float: {
          "0%, 100%": { transform: "translateY(0)" },
          "50%": { transform: "translateY(-12px)" },
        },
        "pulse-red": {
          "0%, 100%": { opacity: "1", boxShadow: "0 0 0 0 rgba(232,0,29,0.6)" },
          "50%": { opacity: "0.7", boxShadow: "0 0 0 8px rgba(232,0,29,0)" },
        },
        marquee: {
          "0%": { transform: "translateX(0)" },
          "100%": { transform: "translateX(-50%)" },
        },
        "fade-in": {
          "0%": { opacity: "0" },
          "100%": { opacity: "1" },
        },
        "slide-up": {
          "0%": { opacity: "0", transform: "translateY(24px)" },
          "100%": { opacity: "1", transform: "translateY(0)" },
        },
      },
      boxShadow: {
        "red-glow": "0 0 24px rgba(232,0,29,0.35)",
        "gold-glow": "0 0 24px rgba(201,168,76,0.30)",
        card: "0 8px 24px rgba(0,0,0,0.4)",
        "card-hover": "0 16px 40px rgba(0,0,0,0.55)",
      },
    },
  },
  plugins: [],
};

export default config;
