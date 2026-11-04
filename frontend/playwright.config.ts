import { defineConfig, devices } from "@playwright/test";

const isCi = !!process.env.CI;

export default defineConfig({
  testDir: "./tests-e2e",
  fullyParallel: false,
  workers: isCi ? 2 : undefined,
  forbidOnly: isCi,
  retries: isCi ? 1 : 0,
  timeout: 90_000,
  expect: { timeout: 15_000 },
  reporter: isCi ? [["list"], ["github"]] : "list",
  use: {
    baseURL: process.env.E2E_BASE_URL ?? "http://localhost:3000",
    // The repository is public, so CI artifacts are too: screenshots of failures
    // only. A trace or video could capture a password or a session token.
    screenshot: "only-on-failure",
    trace: "off",
    video: "off",
  },
  projects: [
    // Logs every demo role in once (through the existing /api/auth/login route)
    // and stores the session per role for the specs below.
    { name: "setup", testMatch: /auth\.setup\.ts/ },
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
      dependencies: ["setup"],
      testIgnore: [/auth\.setup\.ts/, /smoke\.spec\.ts/],
    },
    {
      // The original smoke check; needs no demo data.
      name: "smoke",
      use: { ...devices["Desktop Chrome"] },
      testMatch: /smoke\.spec\.ts/,
    },
  ],
});
