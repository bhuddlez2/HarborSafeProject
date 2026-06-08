import { defineConfig, devices } from '@playwright/test';

const isCI = !!process.env.CI;

export default defineConfig({
  testDir: './tests',
  fullyParallel: false,
  forbidOnly: isCI,
  retries: 0,
  workers: 1,
  reporter: isCI ? 'html' : 'list',
  use: {
    baseURL: 'http://localhost:3000',
    trace: 'off',
    headless: true,
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
  webServer: {
    command: isCI
      ? 'npm run build --prefix website/frontend && npm run start --prefix website/frontend'
      : 'npm run dev --prefix website/frontend',
    url: 'http://localhost:3000',
    reuseExistingServer: !isCI,
    timeout: 180000,
  },
});