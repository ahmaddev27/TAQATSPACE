import { test, expect } from "@playwright/test";
import { apiBase } from "./support/accounts";

const email = process.env.E2E_SSO_EMAIL;
const password = process.env.E2E_SSO_PASSWORD;

// CI artifacts are public, and a screenshot of this form would show the account's email.
test.use({ screenshot: "off" });

test.describe("sign in through the identity provider", () => {
  test.skip(!email || !password, "needs the SSO test account");
  // A real account on a real login form: no retries, so it can never be locked out by this suite.
  test.describe.configure({ retries: 0 });

  test("a real account reaches its dashboard", async ({ page, baseURL }) => {
    const start = `${new URL(apiBase()).origin}/api/auth/taqat/redirect`;
    const appHost = new URL(baseURL as string).host;

    await page.goto(start, { waitUntil: "networkidle" });
    await page.locator('input[type="email"]').first().fill(email as string);
    await page.locator('input[type="password"]').first().fill(password as string);
    await page.getByRole("button", { name: "دخول", exact: true }).click();

    // Automated browsers abort the redirect after the form post, though a person's browser
    // follows it. The session exists by then, so starting again goes straight through.
    await page.waitForURL((url) => url.host === appHost, { timeout: 15_000 }).catch(() => undefined);
    if (new URL(page.url()).host !== appHost) {
      await page.goto(start);
    }

    await expect(page).toHaveURL(/\/(ar|en)\/(cashier|owner|freelancer|admin)(\/|$|\?)/, { timeout: 45_000 });
  });
});
