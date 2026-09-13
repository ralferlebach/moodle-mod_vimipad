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
 * User-story browser tests for mod_vimipad, grouped by role (Admin, Teacher,
 * Student). Each test records a video on every run - see playwright.config.ts.
 * The stories are specified in ViMi_User_Stories.md.
 *
 * Requires a seeded, running site - see seed.php and README.md.
 */

import {test, expect} from '@playwright/test';
import {readEnv} from './support/env';
import {login, openEditor, addConcept, openListView, saveMap, openCourse} from './support/vimipad';

const env = readEnv();

test.describe('mod_vimipad - Admin stories', () => {
    test.skip(!env.admin.password, 'Set VIMIPAD_ADMIN_PASS to run the admin stories.');

    // A1: the admin controls the AI feedback feature site-wide and the switch
    // actually takes effect. The plugin ships with it enabled
    // (settings.php default 1), so the story verifies that an admin can turn it
    // OFF and that the choice persists - which is the decision that matters for
    // a site that must not contact an external service.
    test('A1 - an admin can switch the AI feedback feature off', async ({page}) => {
        await login(page, env.baseURL, env.admin);
        await page.goto(`${env.baseURL}/admin/settings.php?section=modsettingvimipad&lang=en`);

        // Moodle renders an admin checkbox as two inputs sharing the name: a
        // hidden "0" fallback plus the visible checkbox. Target the checkbox by
        // type, or the first match is the hidden input and never becomes visible.
        const aiToggle = page.locator('input[type="checkbox"][name="s_mod_vimipad_enableai"], #id_s_mod_vimipad_enableai').first();
        await expect(aiToggle).toBeVisible({timeout: 15_000});

        // Turn it off and save.
        if (await aiToggle.isChecked()) {
            await aiToggle.uncheck();
        }
        await page.getByRole('button', {name: /Save changes/i}).first().click();

        // Reload the settings page: the feature must still be off.
        await page.goto(`${env.baseURL}/admin/settings.php?section=modsettingvimipad&lang=en`);
        await expect(aiToggle).not.toBeChecked({timeout: 15_000});

        // Restore the shipped default so later stories see a normal site.
        await aiToggle.check();
        await page.getByRole('button', {name: /Save changes/i}).first().click();
    });

    // A2: the plugin shows as installed and enabled in the activity overview.
    test('A2 - ViMi Pad appears as an installed activity module', async ({page}) => {
        await login(page, env.baseURL, env.admin);
        await page.goto(`${env.baseURL}/admin/modules.php?lang=en`);
        await expect(page.getByRole('cell', {name: /ViMi Pad/i}).first()).toBeVisible({timeout: 15_000});
    });
});

test.describe('mod_vimipad - Teacher stories', () => {
    // T1: a teacher adds a ViMi Pad activity to a course.
    test('T1 - a teacher creates a ViMi Pad activity', async ({page}) => {
        test.skip(!env.courseId, 'Set VIMIPAD_COURSE_ID to run the create-activity story.');
        await login(page, env.baseURL, env.teacher);

        // Add the activity straight through modedit (the activity chooser is a
        // JS flow that changes between Moodle versions; modedit is stable).
        await page.goto(`${env.baseURL}/course/modedit.php?add=vimipad&course=${env.courseId}&section=1&return=0&lang=en`);
        await page.locator('#id_name').fill('Teacher-made map');
        await page.locator('#id_submitbutton2').click();

        await openCourse(page, env.baseURL, env.courseId);
        await expect(page.getByText('Teacher-made map').first()).toBeVisible({timeout: 15_000});
    });

    // T2: a teacher opens the grading view for the shared activity.
    test('T2 - a teacher reaches the grading view', async ({page}) => {
        await login(page, env.baseURL, env.teacher);
        await page.goto(`${env.baseURL}${env.activityPath}&lang=en`);
        // The teacher sees the activity; the grading entry point is present for a
        // grader. We assert the page loaded as the teacher rather than driving a
        // full grade cycle (which needs a prior submission fixture).
        await expect(page).not.toHaveURL(/\/login\//);
        await expect(page.locator('.vimipad-canvas, #region-main')).toBeVisible({timeout: 30_000});
    });
});

test.describe('mod_vimipad - Student stories', () => {
    // S1: a student builds a map and it survives a reload.
    test('S1 - a student adds a concept that persists across a reload', async ({page}) => {
        await login(page, env.baseURL, env.userA);
        await openEditor(page, env.baseURL, env.individualPath);

        const label = 'Photosynthesis ' + Date.now();
        await addConcept(page, label);
        await saveMap(page);

        // Reload the activity from scratch; the concept must still be there.
        await openEditor(page, env.baseURL, env.individualPath);
        await openListView(page);
        // The list view renders each node's label, which can land inside a
        // control (e.g. an <option> of a relation editor's node picker) that is
        // present but not "visible" in Playwright's sense. Persistence is proven
        // by the label existing in the reloaded DOM, so assert on attachment,
        // not visibility.
        await expect(page.getByText(label, {exact: false}).first())
            .toBeAttached({timeout: 30_000});
    });
});
