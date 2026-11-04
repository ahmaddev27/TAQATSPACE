import fs from "node:fs";
import path from "node:path";
import { test as setup, expect } from "@playwright/test";
import { DASHBOARD, SESSION_ACCOUNTS, demoPassword, emailOf, roleOf, statePath } from "./support/accounts";

// Log in through the app's own /api/auth/login route (the same one the login
// form posts to) and keep the resulting cookies per account.
for (const key of SESSION_ACCOUNTS) {
  setup(`log in as ${key}`, async ({ playwright, baseURL }) => {
    const context = await playwright.request.newContext({ baseURL });
    const response = await context.post("/api/auth/login", {
      data: { email: emailOf(key), password: demoPassword() },
      failOnStatusCode: false,
    });

    // Only the status is asserted: the body would carry the user record.
    expect(response.status(), `login of ${key}`).toBe(200);
    const body = (await response.json()) as { role?: string; two_factor_required?: boolean };
    expect(body.two_factor_required ?? false, `${key} must not require two-factor`).toBe(false);
    expect(body.role, `role of ${key}`).toBe(roleOf(key));
    expect(DASHBOARD[roleOf(key)]).toBeTruthy();

    fs.mkdirSync(path.dirname(statePath(key)), { recursive: true });
    await context.storageState({ path: statePath(key) });
    await context.dispose();
  });
}
