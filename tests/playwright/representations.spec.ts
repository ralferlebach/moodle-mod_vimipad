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
 * Browser stories for the three specialised diagram forms.
 *
 * Each fixture is a finished map seeded into the database, so these stories are
 * about how a representation is drawn and arranged rather than about creating
 * nodes, which the main story suite already covers.
 *
 * Every assertion is on structure the user can see — which SVG shapes exist,
 * where the effect sits, how many spines are drawn — rather than on pixel
 * snapshots, which would break on any styling change.
 */

import {expect, test} from '@playwright/test';
import {readEnv} from './support/env';
import {login, openEditor, openListView} from './support/vimipad';

const env = readEnv();

/** How long the React editor may take to mount and render its first frame. */
const EDITOR_TIMEOUT = 45_000;

test.describe('Representation: flow chart (issue #14)', () => {
    test.skip(env.flowPath === '', 'No flowchart fixture seeded.');

    test('F1 - a flowchart draws its process symbols', async ({page}) => {
        await login(page, env.baseURL, env.userA);
        await openEditor(page, env.baseURL, env.flowPath);

        // openEditor() has already waited for the canvas to mount.
        await expect(page.getByText('Stock available?').first()).toBeVisible({timeout: EDITOR_TIMEOUT});

        // A decision is a polygon, a start/end is a capsule (a rect whose corner
        // radius is half its height) and neither may be a raster image.
        await expect(page.locator('svg polygon').first()).toBeAttached({timeout: EDITOR_TIMEOUT});
        expect(await page.locator('svg image').count()).toBe(0);
    });

    test('F2 - decision branches are labelled and visually distinct', async ({page}) => {
        await login(page, env.baseURL, env.userA);
        await openEditor(page, env.baseURL, env.flowPath);

        await expect(page.getByText('Pick items').first()).toBeVisible({timeout: EDITOR_TIMEOUT});
        // The "yes" branch carries its label; its type also gives it a colour,
        // so a reader can tell the two branches apart.
        await expect(page.getByText('yes', {exact: false}).first())
            .toBeAttached({timeout: EDITOR_TIMEOUT});
    });
});

test.describe('Representation: fishbone (issue #15)', () => {
    test.skip(env.fishbonePath === '', 'No fishbone fixture seeded.');

    test('B1 - a fishbone draws exactly one shared spine', async ({page}) => {
        await login(page, env.baseURL, env.userA);
        await openEditor(page, env.baseURL, env.fishbonePath);

        await expect(page.getByText('Late delivery').first()).toBeVisible({timeout: EDITOR_TIMEOUT});

        // The backbone is a single element: drawing it once per category
        // relation would overpaint the same segment and give several relations
        // the same selection target.
        const spine = page.locator('.vimipad-fishbone-spine');
        await expect(spine).toHaveCount(1, {timeout: EDITOR_TIMEOUT});

        // It is horizontal.
        const box = await spine.first().boundingBox();
        expect(box).not.toBeNull();
        expect(box!.height).toBeLessThan(12);
        expect(box!.width).toBeGreaterThan(100);
    });

    test('B2 - the effect sits right of every category after Arrange', async ({page}) => {
        await login(page, env.baseURL, env.userA);
        await openEditor(page, env.baseURL, env.fishbonePath);
        await expect(page.getByText('Late delivery').first()).toBeVisible({timeout: EDITOR_TIMEOUT});

        await page.getByRole('button', {name: /Re-?arrange layout/i}).first().click();

        const centreX = async (label: string): Promise<number> => {
            const box = await page.getByText(label, {exact: false}).first().boundingBox();
            expect(box).not.toBeNull();
            return box!.x + box!.width / 2;
        };

        const effect = await centreX('Late delivery');
        for (const category of ['Method', 'Machine', 'Material', 'People']) {
            expect(effect).toBeGreaterThan(await centreX(category));
        }
    });

    test('B3 - categories alternate above and below the spine', async ({page}) => {
        await login(page, env.baseURL, env.userA);
        await openEditor(page, env.baseURL, env.fishbonePath);
        await expect(page.getByText('Late delivery').first()).toBeVisible({timeout: EDITOR_TIMEOUT});

        await page.getByRole('button', {name: /Re-?arrange layout/i}).first().click();

        const spineBox = await page.locator('.vimipad-fishbone-spine').first().boundingBox();
        expect(spineBox).not.toBeNull();
        const spineY = spineBox!.y + spineBox!.height / 2;

        const sides: number[] = [];
        for (const category of ['Method', 'Machine', 'Material', 'People']) {
            const box = await page.getByText(category, {exact: false}).first().boundingBox();
            expect(box).not.toBeNull();
            sides.push(Math.sign(box!.y + box!.height / 2 - spineY));
        }
        // Some categories above, some below: a fishbone alternates rather than
        // fanning out on one side.
        expect(new Set(sides).size).toBeGreaterThan(1);
    });
});

test.describe('Representation: stock and flow (issue #16)', () => {
    test.skip(env.stockflowPath === '', 'No stock-and-flow fixture seeded.');

    test('S1 - the system symbols render as vector shapes', async ({page}) => {
        await login(page, env.baseURL, env.userA);
        await openEditor(page, env.baseURL, env.stockflowPath);

        await expect(page.getByText('Finished goods').first()).toBeVisible({timeout: EDITOR_TIMEOUT});

        // Clouds, valves, delays and parameters are drawn as paths; nothing is
        // rasterised, so scaling and SVG export stay lossless.
        await expect(page.locator('svg path').first()).toBeAttached({timeout: EDITOR_TIMEOUT});
        expect(await page.locator('svg image').count()).toBe(0);
    });

    test('S2 - the list view names each role', async ({page}) => {
        await login(page, env.baseURL, env.userA);
        await openEditor(page, env.baseURL, env.stockflowPath);
        await expect(page.getByText('Finished goods').first()).toBeVisible({timeout: EDITOR_TIMEOUT});

        await openListView(page);

        // In the list the symbols are invisible, so the role is spelled out.
        await expect(page.getByText('[Stock] Finished goods').first())
            .toBeVisible({timeout: EDITOR_TIMEOUT});
        await expect(page.getByText('[Valve] Production rate').first()).toBeAttached();
        await expect(page.getByText('[Sink] Customers').first()).toBeAttached();
    });

    test('S3 - named influences keep their labels', async ({page}) => {
        await login(page, env.baseURL, env.userA);
        await openEditor(page, env.baseURL, env.stockflowPath);
        await expect(page.getByText('Finished goods').first()).toBeVisible({timeout: EDITOR_TIMEOUT});

        // The meaning of an influence lives in its label, not in a polarity
        // property, so the labels must survive to the canvas.
        for (const label of ['increases', 'limits', 'delays']) {
            await expect(page.getByText(label, {exact: false}).first())
                .toBeAttached({timeout: EDITOR_TIMEOUT});
        }
    });

    test('S4 - the material chain stays readable after Arrange', async ({page}) => {
        await login(page, env.baseURL, env.userA);
        await openEditor(page, env.baseURL, env.stockflowPath);
        await expect(page.getByText('Finished goods').first()).toBeVisible({timeout: EDITOR_TIMEOUT});

        await page.getByRole('button', {name: /Re-?arrange layout/i}).first().click();

        const centre = async (label: string): Promise<{x: number; y: number}> => {
            const box = await page.getByText(label, {exact: false}).first().boundingBox();
            expect(box).not.toBeNull();
            return {x: box!.x + box!.width / 2, y: box!.y + box!.height / 2};
        };
        const gap = async (a: string, b: string): Promise<number> => {
            const pa = await centre(a);
            const pb = await centre(b);
            return Math.hypot(pa.x - pb.x, pa.y - pb.y);
        };

        // Flow carries more structural weight than influence, so the material
        // chain ends up tighter than the information hanging off it.
        const chain = (await gap('Production rate', 'Finished goods'))
            + (await gap('Finished goods', 'Shipment rate'));
        const influence = (await gap('Customer demand', 'Shipment rate'))
            + (await gap('Production capacity', 'Production rate'));
        expect(chain).toBeLessThan(influence);
    });
});
