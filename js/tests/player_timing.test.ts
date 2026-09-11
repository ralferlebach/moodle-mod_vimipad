import {PLAYBACK_SPEEDS, stepDelayMs, normaliseSpeed} from '../src/graph/player_timing';

describe('stepDelayMs', () => {
    test('1x keeps the base delay', () => {
        expect(stepDelayMs(900, 1)).toBe(900);
    });

    test('higher speeds shorten the delay proportionally', () => {
        expect(stepDelayMs(900, 10)).toBe(90);
        expect(stepDelayMs(900, 100)).toBe(9);
    });

    test('never returns less than 1ms', () => {
        expect(stepDelayMs(50, 100)).toBe(1);
        expect(stepDelayMs(5, 1000)).toBe(1);
    });

    test('a non-positive speed falls back to real time', () => {
        expect(stepDelayMs(900, 0)).toBe(900);
        expect(stepDelayMs(900, -5)).toBe(900);
    });
});

describe('normaliseSpeed', () => {
    test('accepts the offered speeds', () => {
        for (const s of PLAYBACK_SPEEDS) {
            expect(normaliseSpeed(s)).toBe(s);
        }
    });

    test('falls back to 1x for anything else', () => {
        expect(normaliseSpeed(3)).toBe(1);
        expect(normaliseSpeed(0)).toBe(1);
        expect(normaliseSpeed(50)).toBe(1);
    });
});
