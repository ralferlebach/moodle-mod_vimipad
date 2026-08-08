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
 * Environment configuration for the mod_vimipad Playwright tests. The seeding
 * step (seed.php) fills these; locally you can export them by hand.
 */

/** A test user's credentials. */
export interface TestUser {
    username: string;
    password: string;
    fullname: string;
}

/** The resolved environment for a collaboration run. */
export interface VimipadEnv {
    baseURL: string;
    /** Path to the shared (course-mode) activity, e.g. /mod/vimipad/view.php?id=42. */
    activityPath: string;
    userA: TestUser;
    userB: TestUser;
    teacher: TestUser;
}

/**
 * Read the environment, throwing a clear error if the seeding step has not run.
 *
 * @returns The resolved environment.
 */
export function readEnv(): VimipadEnv {
    const need = (name: string): string => {
        const value = process.env[name];
        if (!value) {
            throw new Error(
                `Missing ${name}. Run tests/playwright/seed.php first and export its output ` +
                `(see tests/playwright/README.md).`
            );
        }
        return value;
    };

    return {
        baseURL: process.env.VIMIPAD_BASE_URL ?? 'http://localhost:8000',
        activityPath: need('VIMIPAD_ACTIVITY_PATH'),
        userA: {
            username: need('VIMIPAD_USER_A'),
            password: need('VIMIPAD_PASS_A'),
            fullname: process.env.VIMIPAD_NAME_A ?? 'Ada Author',
        },
        userB: {
            username: need('VIMIPAD_USER_B'),
            password: need('VIMIPAD_PASS_B'),
            fullname: process.env.VIMIPAD_NAME_B ?? 'Ben Builder',
        },
        teacher: {
            username: need('VIMIPAD_TEACHER'),
            password: need('VIMIPAD_TEACHER_PASS'),
            fullname: process.env.VIMIPAD_TEACHER_NAME ?? 'Tay Teacher',
        },
    };
}
