import { test as base, expect, type Page } from "@playwright/test";
import fs from "fs";
import { ADMIN_AUTH_STATE_PATH } from "../playwright.config";

import { AdminPage as Admin } from "../pages/AdminPage";

interface AdminPage extends Page {
    fillInTinymce: (iframeSelector: string, content: string) => Promise<void>;
}

type AdminFixtures = {
    adminPage: AdminPage;
};

export const test = base.extend<AdminFixtures>({
    adminPage: async ({ browser }, use) => {
        const authExists = fs.existsSync(ADMIN_AUTH_STATE_PATH);

        const context = await browser.newContext(
            authExists ? { storageState: ADMIN_AUTH_STATE_PATH } : {}
        );

        const page = await context.newPage();
        const admin = await new Admin(page)


        /**
         * Signing in redirects to the user's home page, which is not always the dashboard, and the
         * click returns before that redirect lands. Waiting for it and then opening the dashboard
         * means every test starts from the same page whether or not a saved session existed, and
         * the saved session always carries the signed-in cookie.
         */
        const signIn = async () => {
            await admin.adminLogin("admin@example.com", "admin123");

            await page.waitForURL((url) => ! url.pathname.includes("admin/login"));

            await context.storageState({ path: ADMIN_AUTH_STATE_PATH });

            await page.goto("admin/dashboard");
        };

        if (! authExists) {
            await signIn();
        } else {
            await page.goto("admin/dashboard");

            if (page.url().includes("admin/login")) {
                await signIn();
            }
        }

        /**
        * Extend the page object with custom methods.}
        
        */
        (page as AdminPage).fillInTinymce = async function (
            iframeSelector: string,
            content: string
        ) {
            await page.waitForSelector(iframeSelector);
            const iframe = page.frameLocator(iframeSelector);
            const editorBody = iframe.locator("body");
            await editorBody.click();
            await editorBody.press("Control+a");
            await editorBody.press("Backspace");
            await editorBody.pressSequentially(content);
            await expect(editorBody).toHaveText(content);
        };

        await use(page as AdminPage);

        /**
         * A close error is swallowed on purpose. With `trace: "retain-on-failure"` the context's
         * trace is still being written as it closes, and the two race — the loser throws `ENOENT`
         * on a recording file that has already been moved. That is teardown noise: it says nothing
         * about the behaviour under test, but thrown from a fixture it fails a test whose
         * assertions all passed, and hides the real result.
         */
        try {
            await context.close();
        } catch {
            // The context is going away regardless; a cleanup failure is not a test failure.
        }
    },
});

export { expect };