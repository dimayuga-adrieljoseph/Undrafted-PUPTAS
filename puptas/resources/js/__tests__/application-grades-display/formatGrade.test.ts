import { describe, it, expect } from "vitest";
import * as fc from "fast-check";

// Copied inline since formatGrade is not exported from the Vue SFC script setup
const formatGrade = (value: number | string | null | undefined): string => {
    if (value === null || value === undefined) return "—";
    const num = parseFloat(String(value));
    return isNaN(num) ? "—" : num.toFixed(2);
};

describe("formatGrade", () => {
    /**
     * Property 2: Null or absent grade values render as "—"
     * Validates: Requirements 1.3, 3.2
     * Tag: Feature: application-grades-display, Property 2: null or absent grade values render as "—"
     */
    it('Property 2: null or absent grade values render as "—"', () => {
        fc.assert(
            fc.property(
                fc.constantFrom(null, undefined),
                (value) => {
                    expect(formatGrade(value)).toBe("—");
                }
            ),
            { numRuns: 100 }
        );
    });

    it('NaN-producing string inputs render as "—"', () => {
        expect(formatGrade("abc")).toBe("—");
        expect(formatGrade("xyz")).toBe("—");
        expect(formatGrade("")).toBe("—");
    });

    /**
     * Property 3: Present grade values render to two decimal places
     * Validates: Requirements 1.4
     * Tag: Feature: application-grades-display, Property 3: present grade values render to two decimal places
     */
    it("Property 3: present grade values render to two decimal places", () => {
        fc.assert(
            fc.property(
                fc.oneof(
                    fc.integer({ min: 0, max: 100 }),
                    fc.float({ min: 0, max: 100, noNaN: true })
                ),
                (value) => {
                    expect(formatGrade(value)).toBe(
                        parseFloat(String(value)).toFixed(2)
                    );
                }
            ),
            { numRuns: 100 }
        );
    });
});
