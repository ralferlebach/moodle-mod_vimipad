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
 * Behat suite relies on), so they stay stable across styling changes.
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
    await page.goto(`${baseURL}/login/index.php`);
    await page.getByLabel('Username').fill(user.username);
    await page.getByLabel('Password').fill(user.password);
    await page.getByRole('button', {name: /Log in/i}).click();
    await expect(page).toHaveURL(/my|dashboard|index/i);
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
    await page.goto(`${baseURL}${activityPath}`);
    await expect(page.getByText('Loading the ViMi Pad editor')).toHaveCount(0, {timeout: 30_000});
    await expect(page.getByRole('group', {name: /Add concept/i})).toBeVisible();
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
    await expect(page.getByText(label, {exact: false})).toBeVisible({timeout: 30_000});
}
