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
import {login, openEditor, addConcept, expectConceptEventually, beginHoldingNode, releaseNode, expectNodeLockedForOther} from './support/vimipad';

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

        // The second client should receive it on its live canvas through the
        // poll loop (no tab switch: that would reload and drop the poll state).
        await expectConceptEventually(b.page, label);

        await a.context.close();
        await b.context.close();
    });

    // Presence in mod_vimipad is lock-based, not name-based: the client holds a
    // PresenceMap of element -> holder userid (from leases) and surfaces it by
    // rendering a held element as locked (CSS class vimipad-canvas-node-locked)
    // for other users. So the observable presence signal is: while one client
    // holds a node (pointer-down takes an edit lease), the other client sees that
    // node marked as locked. No collaborator name is rendered anywhere.
    test('a node one user is editing shows as locked for the other', async ({browser}) => {
        const a = await openAs(browser, env.userA);
        const b = await openAs(browser, env.userB);

        const label = `Locktest ${Date.now()}`;
        await addConcept(a.page, label);
        // Both clients must have the node before we can assert a lock on it.
        await expectConceptEventually(a.page, label);
        await expectConceptEventually(b.page, label);

        // User A grabs the node and holds it (pointer-down acquires the lease).
        await beginHoldingNode(a.page);
        try {
            // User B should see that node become locked by another collaborator.
            await expectNodeLockedForOther(b.page);
        } finally {
            await releaseNode(a.page);
        }

        await a.context.close();
        await b.context.close();
    });

    test('edits from both users converge for everyone', async ({browser}) => {
        const a = await openAs(browser, env.userA);
        const b = await openAs(browser, env.userB);

        // Poll-based sync converges edits; it does not promise conflict-free
        // MERGING of truly simultaneous edits (the design uses polling, not a
        // CRDT — see the Lastenheft). So each edit is made and allowed to
        // converge on both canvases before the next, which is the guarantee the
        // architecture actually makes: every user can contribute and all clients
        // end up consistent.
        const labelA = `FromA ${Date.now()}`;
        await addConcept(a.page, labelA);
        await expectConceptEventually(a.page, labelA);
        await expectConceptEventually(b.page, labelA);

        const labelB = `FromB ${Date.now()}`;
        await addConcept(b.page, labelB);
        await expectConceptEventually(b.page, labelB);
        await expectConceptEventually(a.page, labelB);

        await a.context.close();
        await b.context.close();
    });
});
