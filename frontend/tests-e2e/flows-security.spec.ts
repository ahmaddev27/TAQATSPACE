import { test, expect, type Browser, type Page } from "@playwright/test";
import { demoPassword, emailOf } from "./support/accounts";
import { ApiClient } from "./support/api";
import { en } from "./support/messages";
import { dialog, fieldInput, firstArray, tab } from "./support/ui";
import { freshTotp } from "./support/totp";

// All of this runs on the throwaway owner: it enrols two-factor, which would
// block the API logins other specs make for the shared accounts.
const THROWAWAY = "owner_throwaway" as const;
const THROWAWAY_SLUG = "demo-throwaway";

// No retries: these steps change the account, so a retry would fail on the changed state and hide the real error.
test.describe.configure({ mode: "serial", retries: 0 });

let secret = "";
let recoveryCodes: string[] = [];
let lastCode: string | undefined;

async function nextCode(): Promise<string> {
  lastCode = await freshTotp(secret, lastCode);
  return lastCode;
}

/** A fresh browser session for the throwaway owner, signed in through the app's login route. */
async function signIn(browser: Browser): Promise<{ page: Page; challenged: boolean }> {
  const context = await browser.newContext();
  const login = () =>
    context.request.post("/api/auth/login", {
      data: { email: emailOf(THROWAWAY), password: demoPassword() },
      failOnStatusCode: false,
    });
  // A dropped connection says nothing about the login, so only that is tried again; a refusal still fails.
  const response = await login().catch(() => login());
  expect(response.status(), "login of the throwaway owner").toBe(200);
  const body = (await response.json()) as { two_factor_required?: boolean };
  return { page: await context.newPage(), challenged: body.two_factor_required === true };
}

async function answerChallenge(page: Page, value: string, recovery = false): Promise<void> {
  await page.goto("/en/auth/two-factor");
  if (recovery) await page.getByRole("button", { name: en("base", "auth.twoFactor.useRecovery") }).click();
  await fieldInput(page, en("base", recovery ? "auth.twoFactor.recoveryLabel" : "auth.twoFactor.codeLabel")).fill(value);
  await page.getByRole("button", { name: en("base", "auth.twoFactor.verify") }).click();
}

test("two-factor is enabled from the security page and shows recovery codes", async ({ browser }) => {
  const { page, challenged } = await signIn(browser);
  expect(challenged, "two-factor must be off on a freshly seeded account").toBe(false);

  await page.goto("/en/settings/security");
  await page.getByRole("button", { name: en("base", "auth.twoFactor.enable"), exact: true }).click();
  secret = (await dialog(page).locator("code").innerText()).trim();
  expect(secret.length).toBeGreaterThan(10);

  await dialog(page).locator("input").fill(await nextCode());
  await dialog(page).getByRole("button", { name: en("base", "auth.twoFactor.confirm") }).click();

  await expect(dialog(page).getByText(en("base", "auth.twoFactor.recoveryTitle"))).toBeVisible();
  recoveryCodes = (await dialog(page).locator("li").allInnerTexts()).map((code) => code.trim());
  expect(recoveryCodes.length).toBeGreaterThan(0);
  await dialog(page).getByRole("button", { name: en("base", "auth.twoFactor.done") }).click();
  await expect(page.getByText(en("base", "auth.twoFactor.statusOn"))).toBeVisible();
  await page.context().close();
});

test("logging in now asks for a code, and a wrong code is refused", async ({ browser }) => {
  const { page, challenged } = await signIn(browser);
  expect(challenged).toBe(true);

  await answerChallenge(page, "000000");
  await expect(page.getByText(en("base", "auth.twoFactor.invalidCode"))).toBeVisible();

  await fieldInput(page, en("base", "auth.twoFactor.codeLabel")).fill(await nextCode());
  await page.getByRole("button", { name: en("base", "auth.twoFactor.verify") }).click();
  await expect(page).toHaveURL(/\/en\/owner/);
  await page.context().close();
});

test("a recovery code works once and is refused the second time", async ({ browser }) => {
  const recoveryCode = recoveryCodes[0];

  const first = await signIn(browser);
  expect(first.challenged).toBe(true);
  await answerChallenge(first.page, recoveryCode, true);
  await expect(first.page).toHaveURL(/\/en\/owner/);
  await first.page.context().close();

  const second = await signIn(browser);
  await answerChallenge(second.page, recoveryCode, true);
  await expect(second.page.getByText(en("base", "auth.twoFactor.invalidCode"))).toBeVisible();
  await second.page.context().close();
});

test("the workspace reset removes the throwaway workspace's data and nothing else", async ({ browser }) => {
  const owner1 = await ApiClient.login("owner1");
  const expensesBefore = firstArray(await owner1.json(await owner1.get("workspace/expenses"))).length;
  expect(expensesBefore, "owner1 has demo expenses").toBeGreaterThan(0);

  const { page } = await signIn(browser);
  await answerChallenge(page, await nextCode());
  await expect(page).toHaveURL(/\/en\/owner/);

  await page.goto("/en/owner/settings");
  await tab(page, en("owner", "owner.settings.tabDanger")).click();
  await page.getByRole("button", { name: en("owner", "owner.settings.danger.reset"), exact: true }).click();
  await dialog(page).getByRole("button", { name: en("owner", "owner.settings.danger.warnContinue") }).click();

  const modal = dialog(page);
  await fieldInput(modal, en("owner", "owner.settings.danger.reasonLabel")).fill("End-to-end reset check");
  await modal.locator("input").first().fill(THROWAWAY_SLUG);
  await modal.locator("input.tnum").fill(await nextCode());
  await modal.getByRole("button", { name: en("owner", "owner.settings.danger.confirm") }).click();
  await expect(page.getByText(en("owner", "owner.settings.danger.done"))).toBeVisible();

  const expensesAfter = firstArray(await owner1.json(await owner1.get("workspace/expenses"))).length;
  expect(expensesAfter, "the other workspace keeps its data").toBe(expensesBefore);
  await owner1.dispose();
  await page.context().close();
});
