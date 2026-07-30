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

import {nextInterval, AdaptiveConfig} from '../src/collab/adaptive';

const cfg: AdaptiveConfig = {min: 1000, max: 10000, base: 1000, adaptive: true};

describe('adaptive polling interval', () => {
    test('starts at the base interval', () => {
        expect(nextInterval(cfg, cfg.base, {changed: true, rttMs: 50})).toBeLessThanOrEqual(cfg.base);
    });

    test('lengthens after an empty (no-change) poll', () => {
        const next = nextInterval(cfg, 1000, {changed: false, rttMs: 50});
        expect(next).toBeGreaterThan(1000);
    });

    test('keeps lengthening on repeated empty polls but never exceeds max', () => {
        let interval = 1000;
        for (let i = 0; i < 50; i++) {
            interval = nextInterval(cfg, interval, {changed: false, rttMs: 50});
        }
        expect(interval).toBe(cfg.max);
    });

    test('snaps back toward min when a change arrives', () => {
        const next = nextInterval(cfg, 8000, {changed: true, rttMs: 50});
        expect(next).toBeLessThan(8000);
    });

    test('never drops below min', () => {
        let interval = cfg.min;
        for (let i = 0; i < 10; i++) {
            interval = nextInterval(cfg, interval, {changed: true, rttMs: 10});
        }
        expect(interval).toBeGreaterThanOrEqual(cfg.min);
    });

    test('lengthens when round-trip latency is high, even with changes', () => {
        const next = nextInterval(cfg, 1000, {changed: true, rttMs: 3000});
        expect(next).toBeGreaterThan(1000);
    });

    test('when adaptive is off, always returns the base interval', () => {
        const fixed: AdaptiveConfig = {...cfg, adaptive: false};
        expect(nextInterval(fixed, 5000, {changed: false, rttMs: 9000})).toBe(fixed.base);
        expect(nextInterval(fixed, 5000, {changed: true, rttMs: 10})).toBe(fixed.base);
    });

    test('clamps a base that sits outside min/max', () => {
        const odd: AdaptiveConfig = {min: 2000, max: 4000, base: 500, adaptive: true};
        expect(nextInterval(odd, 500, {changed: true, rttMs: 10})).toBeGreaterThanOrEqual(2000);
    });
});
