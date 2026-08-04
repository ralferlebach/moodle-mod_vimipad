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
 * Playwright configuration for mod_vimipad browser/collaboration tests.
 *
 * These tests need a running Moodle with mod_vimipad installed and a seeded
 * course-mode map (see seed.php and README.md). They run in CI or locally
 * against a live site — never inside moodle-plugin-ci's static jobs.
 */

import {defineConfig, devices} from '@playwright/test';

const baseURL = process.env.VIMIPAD_BASE_URL ?? 'http://localhost:8000';

export default defineConfig({
    testDir: '.',
    testMatch: '**/*.spec.ts',
    // Collaboration relies on polling, so give assertions time to converge.
    timeout: 60_000,
    expect: {timeout: 15_000},
    // Two clients edit the same map, so run serially and never share state.
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['github'], ['html', {open: 'never'}]] : [['list']],
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: {...devices['Desktop Chrome']},
        },
    ],
});
