import type { Config } from "tailwindcss";

const config: Config = {
  content: [
    "./src/pages/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/components/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/app/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          purple: "#667eea",
          "purple-dark": "#764ba2",
          teal: "#11998e",
          "teal-light": "#38ef7d",
        },
      },
      backgroundImage: {
        "gradient-brand": "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
        "gradient-teal": "linear-gradient(135deg, #11998e 0%, #38ef7d 100%)",
        "gradient-warm": "linear-gradient(135deg, #f093fb 0%, #f5576c 100%)",
        "gradient-cool": "linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)",
        "gradient-gold": "linear-gradient(135deg, #f6d365 0%, #fda085 100%)",
      },
      fontFamily: {
        sans: ["Inter", "ui-sans-serif", "system-ui", "sans-serif"],
      },
      boxShadow: {
        card: "0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)",
        "card-hover":
          "0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)",
      },
    },
  },
  plugins: [],
};

export default config;
