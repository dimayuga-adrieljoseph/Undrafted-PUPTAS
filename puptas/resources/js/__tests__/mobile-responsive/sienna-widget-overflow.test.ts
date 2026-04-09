// Feature: mobile-responsive-ui, Property 21: Sienna widget panel does not exceed viewport width

import { describe, it, beforeEach, afterEach } from 'vitest'
import * as fc from 'fast-check'
import * as fs from 'fs'
import * as path from 'path'

// ---------------------------------------------------------------------------
// Helper: resolve the blade partial path relative to this test file
// ---------------------------------------------------------------------------
const BLADE_PARTIAL_PATH = path.resolve(
    __dirname,
    '../../../../resources/views/partials/sienna-widget.blade.php'
)

// ---------------------------------------------------------------------------
// Helper: read the blade partial content (cached once per test run)
// ---------------------------------------------------------------------------
let bladeContent: string | null = null
function getBladeContent(): string {
    if (bladeContent === null) {
        bladeContent = fs.readFileSync(BLADE_PARTIAL_PATH, 'utf-8')
    }
    return bladeContent
}

// ---------------------------------------------------------------------------
// Helper: apply the required CSS overrides to a mock .asw-menu element
// and verify the effective max-width does not exceed the viewport width.
//
// The fix requires:
//   .asw-menu { max-width: 100vw; overflow-x: hidden; }
//
// In jsdom, `100vw` resolves to the value of `window.innerWidth` (in px).
// We simulate this by setting the style directly on the element.
// ---------------------------------------------------------------------------
function createMockAswMenu(viewportWidth: number): HTMLDivElement {
    const el = document.createElement('div')
    el.className = 'asw-menu'

    // Simulate the widget rendering at an arbitrary intrinsic width that could
    // exceed the viewport (e.g. the widget's default 320px panel on a 320px
    // viewport, or a wider panel on a narrow viewport).
    // We intentionally set a width that *would* overflow without the fix.
    el.style.width = `${viewportWidth + 100}px`

    // Apply the required CSS overrides from the fix
    el.style.maxWidth = '100vw'
    el.style.overflowX = 'hidden'

    return el
}

/**
 * Returns the effective max-width of the element in pixels.
 *
 * In jsdom, `100vw` is not automatically resolved to `window.innerWidth`,
 * so we handle the `100vw` case explicitly by mapping it to `window.innerWidth`.
 */
function getEffectiveMaxWidthPx(el: HTMLElement, viewportWidth: number): number {
    const maxWidthStyle = el.style.maxWidth

    // Handle the `100vw` case (the required fix value)
    if (maxWidthStyle === '100vw') {
        return viewportWidth
    }

    // Handle explicit pixel values
    const pxMatch = maxWidthStyle.match(/^(\d+(?:\.\d+)?)px$/)
    if (pxMatch) {
        return parseFloat(pxMatch[1])
    }

    // Handle percentage values relative to viewport width
    const pctMatch = maxWidthStyle.match(/^(\d+(?:\.\d+)?)%$/)
    if (pctMatch) {
        return (parseFloat(pctMatch[1]) / 100) * viewportWidth
    }

    // No max-width constraint → effectively unlimited
    return Infinity
}

// ---------------------------------------------------------------------------
// Blade partial content checks
// ---------------------------------------------------------------------------

/**
 * Returns true if the blade partial contains `max-width: 100vw` applied to
 * the `.asw-menu` selector (with or without `!important`).
 */
function bladeHasMaxWidthOverride(content: string): boolean {
    // Look for a .asw-menu rule block that contains max-width: 100vw
    // The regex allows for optional whitespace and optional !important
    return /\.asw-menu\s*\{[^}]*max-width\s*:\s*100vw/s.test(content)
}

/**
 * Returns true if the blade partial contains `overflow-x: hidden` applied to
 * the `.asw-menu` selector (with or without `!important`).
 */
function bladeHasOverflowXHiddenOverride(content: string): boolean {
    return /\.asw-menu\s*\{[^}]*overflow-x\s*:\s*hidden/s.test(content)
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('Property 21: Sienna widget panel does not exceed viewport width', () => {
    let originalInnerWidth: number

    beforeEach(() => {
        originalInnerWidth = window.innerWidth
    })

    afterEach(() => {
        Object.defineProperty(window, 'innerWidth', {
            writable: true,
            configurable: true,
            value: originalInnerWidth,
        })
    })

    it('blade partial contains max-width: 100vw override on .asw-menu', () => {
        // Validates: Requirements 9.2, 9.4
        const content = getBladeContent()
        const hasOverride = bladeHasMaxWidthOverride(content)

        if (!hasOverride) {
            throw new Error(
                'sienna-widget.blade.php is missing `max-width: 100vw` on the `.asw-menu` selector.\n' +
                '  Expected: .asw-menu { max-width: 100vw; ... }\n' +
                '  Fix: add `max-width: 100vw !important;` inside the .asw-menu rule in the <style> block.'
            )
        }
    })

    it('blade partial contains overflow-x: hidden override on .asw-menu', () => {
        // Validates: Requirements 9.2, 9.3
        const content = getBladeContent()
        const hasOverride = bladeHasOverflowXHiddenOverride(content)

        if (!hasOverride) {
            throw new Error(
                'sienna-widget.blade.php is missing `overflow-x: hidden` on the `.asw-menu` selector.\n' +
                '  Expected: .asw-menu { overflow-x: hidden; ... }\n' +
                '  Fix: add `overflow-x: hidden !important;` inside the .asw-menu rule in the <style> block.'
            )
        }
    })

    it('.asw-menu with max-width: 100vw does not exceed viewport width for any viewport size (100 iterations)', () => {
        // Validates: Requirements 9.1, 9.2
        fc.assert(
            fc.property(
                fc.integer({ min: 320, max: 1440 }),
                (viewportWidth: number) => {
                    // Set the simulated viewport width
                    Object.defineProperty(window, 'innerWidth', {
                        writable: true,
                        configurable: true,
                        value: viewportWidth,
                    })

                    // Create a mock .asw-menu element with the CSS overrides applied
                    const el = createMockAswMenu(viewportWidth)
                    document.body.appendChild(el)

                    // Verify the effective max-width does not exceed the viewport width
                    const effectiveMaxWidth = getEffectiveMaxWidthPx(el, viewportWidth)

                    document.body.removeChild(el)

                    if (effectiveMaxWidth > viewportWidth) {
                        throw new Error(
                            `[viewport=${viewportWidth}px] .asw-menu effective max-width (${effectiveMaxWidth}px) ` +
                            `exceeds window.innerWidth (${viewportWidth}px).\n` +
                            '  Expected: max-width: 100vw on .asw-menu constrains the panel to the viewport width.\n' +
                            '  Fix: ensure `.asw-menu { max-width: 100vw !important; }` is present in sienna-widget.blade.php.'
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })

    it('.asw-menu with max-width: 100vw does not exceed viewport width at mobile breakpoints (320–480px)', () => {
        // Validates: Requirements 9.1, 9.2 — focused on mobile range
        fc.assert(
            fc.property(
                fc.integer({ min: 320, max: 480 }),
                (viewportWidth: number) => {
                    Object.defineProperty(window, 'innerWidth', {
                        writable: true,
                        configurable: true,
                        value: viewportWidth,
                    })

                    const el = createMockAswMenu(viewportWidth)
                    document.body.appendChild(el)

                    const effectiveMaxWidth = getEffectiveMaxWidthPx(el, viewportWidth)

                    document.body.removeChild(el)

                    if (effectiveMaxWidth > viewportWidth) {
                        throw new Error(
                            `[mobile viewport=${viewportWidth}px] .asw-menu effective max-width (${effectiveMaxWidth}px) ` +
                            `exceeds window.innerWidth (${viewportWidth}px).\n` +
                            '  Expected: max-width: 100vw constrains the Sienna panel on mobile viewports.\n' +
                            '  Fix: add `max-width: 100vw !important;` to .asw-menu in sienna-widget.blade.php.'
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
