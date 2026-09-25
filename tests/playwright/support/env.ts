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
    /** An individual-mode activity path, for the single-student stories. */
    individualPath: string;
    /** The seeded course id and name, for the teacher create-activity story. */
    courseId: string;
    courseName: string;
    /** The site administrator, for the admin stories. */
    admin: TestUser;
    /** Path to the flowchart fixture, if seeded. */
    flowPath: string;
    /** Path to the fishbone fixture, if seeded. */
    fishbonePath: string;
    /** Path to the stock-and-flow fixture, if seeded. */
    stockflowPath: string;
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
        individualPath: process.env.VIMIPAD_INDIVIDUAL_PATH ?? need('VIMIPAD_ACTIVITY_PATH'),
        // Representation fixtures: one ready-made map per diagram form, so the
        // stories check how a finished map is drawn rather than re-testing node
        // creation.
        flowPath: process.env.VIMIPAD_FLOW_PATH ?? '',
        fishbonePath: process.env.VIMIPAD_FISHBONE_PATH ?? '',
        stockflowPath: process.env.VIMIPAD_STOCKFLOW_PATH ?? '',
        courseId: process.env.VIMIPAD_COURSE_ID ?? '',
        courseName: process.env.VIMIPAD_COURSE_NAME ?? 'ViMi Pad collaboration',
        admin: {
            username: process.env.VIMIPAD_ADMIN ?? 'admin',
            password: process.env.VIMIPAD_ADMIN_PASS ?? '',
            fullname: process.env.VIMIPAD_ADMIN_NAME ?? 'Admin User',
        },
    };
}
