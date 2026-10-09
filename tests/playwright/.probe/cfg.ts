import { defineConfig } from '@playwright/test';
export default defineConfig({ testDir: './', reporter: [['line']], timeout: 180000,
  use: { ignoreHTTPSErrors: true, baseURL: process.env.PLAYWRIGHT_BASE_URL } });
