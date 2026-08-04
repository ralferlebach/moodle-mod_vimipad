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
 * Multi-client collaboration tests for mod_vimipad. Two users open the same
 * course-mode map in separate browser contexts; changes made by one must appear
 * for the other through the polling sync. This is the niche Behat cannot cover
 * (Behat drives a single session).
 *
 * Requires a seeded environment — see seed.php and README.md.
 */

import {test, expect, Browser} from '@playwright/test';
import {readEnv} from './support/env';
import {login, openEditor, addConcept, openListView, expectConceptEventually} from './support/vimipad';

const env = readEnv();

/** Open the shared map as a user in a fresh, isolated browser context. */
async function openAs(browser: Browser, user: {username: string; password: string; fullname: string}) {
    const context = await browser.newContext();
    const page = await context.newPage();
    await login(page, env.baseURL, user);
    await openEditor(page, env.baseURL, env.activityPath);
    return {context, page};
}

test.describe('mod_vimipad real-time collaboration', () => {
    test('a concept added by one user appears for the other', async ({browser}) => {
        const a = await openAs(browser, env.userA);
        const b = await openAs(browser, env.userB);

        const label = `Photosynthesis ${Date.now()}`;
        await addConcept(a.page, label);

        // The second client should receive it through the poll loop.
        await openListView(b.page);
        await expectConceptEventually(b.page, label);

        await a.context.close();
        await b.context.close();
    });

    test('both users see each other in the presence list', async ({browser}) => {
        const a = await openAs(browser, env.userA);
        const b = await openAs(browser, env.userB);

        // Presence is surfaced as the collaborators' names somewhere on the page.
        await expect(a.page.getByText(env.userB.fullname, {exact: false})).toBeVisible({timeout: 30_000});
        await expect(b.page.getByText(env.userA.fullname, {exact: false})).toBeVisible({timeout: 30_000});

        await a.context.close();
        await b.context.close();
    });

    test('concurrent edits from both users converge for everyone', async ({browser}) => {
        const a = await openAs(browser, env.userA);
        const b = await openAs(browser, env.userB);

        const labelA = `FromA ${Date.now()}`;
        const labelB = `FromB ${Date.now()}`;
        await addConcept(a.page, labelA);
        await addConcept(b.page, labelB);

        await openListView(a.page);
        await openListView(b.page);

        // Each client ends up showing both concepts.
        await expectConceptEventually(a.page, labelA);
        await expectConceptEventually(a.page, labelB);
        await expectConceptEventually(b.page, labelA);
        await expectConceptEventually(b.page, labelB);

        await a.context.close();
        await b.context.close();
    });
});
