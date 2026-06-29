import { test, expect } from "@playwright/test";

// Smoke test: requires a running dev/prod server at baseURL.
// Run with `npm run e2e` after starting the app.
test("home page loads", async ({ page }) => {
  await page.goto("/");
  await expect(page).toHaveTitle(/.+/);
});
