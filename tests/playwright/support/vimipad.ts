// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Page-object helpers for driving the mod_vimipad editor in Playwright. The
 * selectors mirror the accessible labels the editor exposes (the same ones the
 * Behat suite relies on), so they stay stable across styling changes. Because
 * those labels are localised, the helpers pin the session to English (via
 * `?lang=en`) — matching the Behat suite — so the tests pass on sites whose
 * default language is not English. The login form is targeted by its stable
 * Moodle field IDs, since it renders before any language preference applies.
 */

import {Page, expect} from '@playwright/test';
import {TestUser} from './env';

/**
 * Log a user in through the standard Moodle login form.
 *
 * @param page The browser page.
 * @param baseURL The site base URL.
 * @param user The user to log in as.
 */
export async function login(page: Page, baseURL: string, user: TestUser): Promise<void> {
    // Target the stable Moodle field IDs (language-independent); English for the
    // editor is forced later on the activity URL, not here — putting ?lang=en on
    // the login page can trigger a language redirect that staleness the login
    // token and makes the sign-in silently fail.
    for (let attempt = 1; attempt <= 2; attempt++) {
        await page.goto(`${baseURL}/login/index.php`);
        await page.locator('#username').fill(user.username);
        await page.locator('#password').fill(user.password);
        await page.locator('#loginbtn').click();
        try {
            // Signed in once we have navigated away from the login area (Moodle
            // redirects to the dashboard). This is robust: it does not depend on
            // element presence (a logged-in page can still contain a #loginbtn,
            // and re-visiting the login URL while already signed in renders one),
            // and it does not falsely match login/index.php the way an /index/
            // URL check would.
            await expect(page).not.toHaveURL(/\/login\//, {timeout: 20_000});
            return;
        } catch (error) {
            if (attempt === 2) {
                throw error;
            }
        }
    }
}

/**
 * Open a vimipad activity and wait for the React editor to mount (the loading
 * placeholder is gone and the "Add concept" control is present).
 *
 * @param page The browser page.
 * @param baseURL The site base URL.
 * @param activityPath The activity path.
 */
export async function openEditor(page: Page, baseURL: string, activityPath: string): Promise<void> {
    // Keep the whole session in English so the editor's accessible labels
    // elsewhere resolve on a site whose default language is not English.
    const sep = activityPath.includes('?') ? '&' : '?';
    const url = `${baseURL}${activityPath}${sep}lang=en`;
    // Wait for the editor to finish mounting. The canvas SVG (.vimipad-canvas)
    // renders as soon as React mounts on the default Canvas tab — a reliable,
    // language-independent signal (unlike the "Add concept" legend, which is a
    // form further down and is localised). A fresh browser context lazy-loads
    // the editor bundle, which can exceed a tight timeout on a real (slower)
    // site, so allow a generous timeout and one reload retry for transient
    // mount misses.
    for (let attempt = 1; attempt <= 2; attempt++) {
        await page.goto(url);
        try {
            await expect(page.locator('.vimipad-canvas')).toBeVisible({timeout: 30_000});
            return;
        } catch (error) {
            if (attempt === 2) {
                throw error;
            }
        }
    }
}

/**
 * Add a concept (node) with the given label through the editor's add form.
 *
 * @param page The browser page.
 * @param label The concept label.
 */
export async function addConcept(page: Page, label: string): Promise<void> {
    const addGroup = page.getByRole('group', {name: /Add concept/i});
    await addGroup.getByLabel(/Concept label/i).fill(label);
    await addGroup.getByRole('button', {name: /^Add$/}).click();
}

/**
 * Switch to the list view, where node labels render as plain, easily-asserted
 * table text (robust against canvas rendering differences).
 *
 * @param page The browser page.
 */
export async function openListView(page: Page): Promise<void> {
    await page.getByRole('link', {name: /^List$/}).click();
    await expect(page.getByRole('group', {name: /Add concept/i})).toBeVisible();
}

/**
 * Assert that a concept with the given label eventually appears — used to prove
 * a collaborator's change propagated through the polling sync.
 *
 * @param page The browser page.
 * @param label The concept label to wait for.
 */
export async function expectConceptEventually(page: Page, label: string): Promise<void> {
    // Assert the node on the live canvas, where the polling sync delivers a
    // collaborator's change. Scope to the canvas SVG so the label text of the
    // node is matched and not the identical <option>s in the relation editor's
    // Subject/Object selects (which would trip strict mode). We deliberately do
    // not switch to the List tab: the view tabs are server-side links, so a
    // switch is a full page reload that drops the live poll state.
    await expect(page.locator('.vimipad-canvas').getByText(label, {exact: false}))
        .toBeVisible({timeout: 30_000});
}

/**
 * Press and hold the pointer on the first node on the canvas. Pointer-down
 * acquires a collaboration edit lease on that node (released on pointer-up), so
 * this is how one client signals "I am editing this" to the others. Remember to
 * call releaseNode() afterwards.
 *
 * @param page The browser page.
 */
export async function beginHoldingNode(page: Page): Promise<void> {
    const node = page.locator('.vimipad-canvas-node').first();
    await expect(node).toBeVisible({timeout: 30_000});
    await node.hover();
    await page.mouse.down();
}

/**
 * Release the pointer, dropping the edit lease taken by beginHoldingNode().
 *
 * @param page The browser page.
 */
export async function releaseNode(page: Page): Promise<void> {
    await page.mouse.up();
}

/**
 * Assert that some node eventually renders as locked by another collaborator —
 * the observable form of presence (the client marks elements another user holds
 * with the vimipad-canvas-node-locked class; no collaborator name is rendered).
 *
 * @param page The browser page.
 */
export async function expectNodeLockedForOther(page: Page): Promise<void> {
    await expect(page.locator('.vimipad-canvas-node-locked').first())
        .toBeVisible({timeout: 30_000});
}
