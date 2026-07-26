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

import {createTween, sampleTween, tweenValue} from '../src/collab/tween';

describe('position tweening', () => {
    test('eases from start to end over the duration', () => {
        const t = createTween({x: 0, y: 0}, {x: 100, y: 200}, 1000, 0);
        expect(sampleTween(t, 0)).toEqual({x: 0, y: 0});
        const mid = sampleTween(t, 500);
        expect(mid.x).toBeGreaterThan(0);
        expect(mid.x).toBeLessThan(100);
        expect(sampleTween(t, 1000)).toEqual({x: 100, y: 200});
    });

    test('clamps beyond the duration to the end position', () => {
        const t = createTween({x: 0, y: 0}, {x: 50, y: 50}, 1000, 0);
        expect(sampleTween(t, 5000)).toEqual({x: 50, y: 50});
    });

    test('easing is monotonic and stays within bounds', () => {
        const t = createTween({x: 0, y: 0}, {x: 100, y: 0}, 1000, 0);
        let prev = -1;
        for (let ms = 0; ms <= 1000; ms += 100) {
            const x = sampleTween(t, ms).x;
            expect(x).toBeGreaterThanOrEqual(0);
            expect(x).toBeLessThanOrEqual(100);
            expect(x).toBeGreaterThanOrEqual(prev);
            prev = x;
        }
    });

    test('respects a non-zero start time', () => {
        const t = createTween({x: 0, y: 0}, {x: 100, y: 100}, 1000, 2000);
        expect(sampleTween(t, 2000)).toEqual({x: 0, y: 0});
        expect(sampleTween(t, 3000)).toEqual({x: 100, y: 100});
    });

    test('tweenValue eases a scalar with the same curve', () => {
        expect(tweenValue(0, 10, 0)).toBe(0);
        expect(tweenValue(0, 10, 1)).toBe(10);
        const half = tweenValue(0, 10, 0.5);
        expect(half).toBeGreaterThan(0);
        expect(half).toBeLessThan(10);
    });

    test('a zero-duration tween is immediately at the end', () => {
        const t = createTween({x: 1, y: 1}, {x: 9, y: 9}, 0, 0);
        expect(sampleTween(t, 0)).toEqual({x: 9, y: 9});
    });
});
