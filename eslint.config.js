// run npm run lint from root directory

import js from "@eslint/js";
import globals from "globals";
import nextPlugin from "@next/eslint-plugin-next";
import reactPlugin from "eslint-plugin-react";
import hooksPlugin from "eslint-plugin-react-hooks";
import playwright from "eslint-plugin-playwright";

export default [
  // --- GLOBAL IGNORES ---
  // Tells ESLint to ignore PHP files, build folders, and DB snapshots
  { 
    ignores: [".next/", "node_modules/", "dist/", "*.php", "out/", "**/vendor/**"] 
  },

  // --- FRONTEND (Next.js, React, Tailwind) ---
  {
    files: ["**/*.{js,jsx,ts,tsx}"],
    plugins: {
      react: reactPlugin,
      "react-hooks": hooksPlugin,
      "@next/next": nextPlugin,
    },
    languageOptions: {
      ecmaVersion: "latest",
      sourceType: "module",
      parserOptions: {
        ecmaFeatures: {
          jsx: true,
        },
      },
      globals: {
        ...globals.browser,
        ...globals.node,
      },
    },
    rules: {
      ...js.configs.recommended.rules,
      ...reactPlugin.configs.recommended.rules,
      ...hooksPlugin.configs.recommended.rules,
      ...nextPlugin.configs.recommended.rules,
      "react/react-in-jsx-scope": "off", // Not needed in Next.js
      "react/prop-types": "off",         // Usually off if using TypeScript
    },
  },

  // --- TESTING (Playwright) ---
  {
    files: ["tests/**/*.{js,ts}", "**/*.spec.{js,ts}", "**/*.test.{js,ts}"],
    ...playwright.configs["flat/recommended"],
    languageOptions: {
      globals: {
        ...globals.jest, // Playwright uses similar globals
      },
    },
  },
];