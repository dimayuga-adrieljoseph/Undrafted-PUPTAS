// Feature: mobile-responsive-ui, Property 22: Blade template tables are wrapped in overflow-x-auto

import { describe, it } from 'vitest'
import * as fc from 'fast-check'
import * as fs from 'fs'
import * as path from 'path'

// ---------------------------------------------------------------------------
// Blade template paths to check (relative to this test file)
// ---------------------------------------------------------------------------
const VIEWS_ROOT = path.resolve(__dirname, '../../../../resources/views')

/**
 * All Blade template file paths under resources/views/ that may contain tables.
 * We enumerate them statically so fast-check can pick from them.
 */
function collectBladeFiles(dir: string): string[] {
    const results: string[] = []
    const entries = fs.readdirSync(dir, { withFileTypes: true })
    for (const entry of entries) {
        const fullPath = path.join(dir, entry.name)
        if (entry.isDirectory()) {
            results.push(...collectBladeFiles(fullPath))
        } else if (entry.isFile() && entry.name.endsWith('.blade.php')) {
            results.push(fullPath)
        }
    }
    return results
}

const ALL_BLADE_FILES = collectBladeFiles(VIEWS_ROOT)

// ---------------------------------------------------------------------------
// Helper: check whether a <table> occurrence in raw Blade content has an
// ancestor element with overflow-x-auto class or overflow-x: auto inline style.
//
// Strategy (regex/string-based, no DOM):
//   1. Find each <table occurrence (position in string).
//   2. Walk backwards through the content from that position.
//   3. For each opening tag found, check if it carries overflow-x-auto.
//   4. Stop walking when we reach the start of the string.
// ---------------------------------------------------------------------------

/** Returns true if the given HTML/Blade tag string contains overflow-x-auto. */
function tagHasOverflowXAuto(tag: string): boolean {
    // class="... overflow-x-auto ..."
    if (/class\s*=\s*["'][^"']*overflow-x-auto[^"']*["']/.test(tag)) return true
    // style="... overflow-x: auto ..." or style="... overflow-x:auto ..."
    if (/style\s*=\s*["'][^"']*overflow-x\s*:\s*auto[^"']*["']/.test(tag)) return true
    return false
}

/**
 * Given the full content of a Blade file and the character index of a `<table`
 * occurrence, walk backwards through the content to find any ancestor opening
 * tag that carries overflow-x-auto.
 *
 * Returns `{ found: boolean, ancestorTags: string[] }` for diagnostics.
 */
function findOverflowXAutoAncestor(
    content: string,
    tableIndex: number
): { found: boolean; ancestorTags: string[] } {
    const ancestorTags: string[] = []

    // We scan backwards from tableIndex looking for opening tags.
    // A simple approach: find all opening tags before tableIndex and check them.
    const contentBefore = content.slice(0, tableIndex)

    // Match all opening tags (not self-closing, not closing tags)
    // We look for <tagname ... > patterns
    const openTagRegex = /<([a-zA-Z][a-zA-Z0-9-]*)(\s[^>]*)?\s*>/g
    let match: RegExpExecArray | null

    while ((match = openTagRegex.exec(contentBefore)) !== null) {
        const fullTag = match[0]
        ancestorTags.push(fullTag)
        if (tagHasOverflowXAuto(fullTag)) {
            return { found: true, ancestorTags }
        }
    }

    return { found: false, ancestorTags }
}

/**
 * Checks whether every `<table` in the given Blade file content has an ancestor
 * with overflow-x-auto class or overflow-x: auto inline style.
 *
 * Returns `{ pass: boolean; details: string }`.
 */
function allTablesHaveOverflowXAutoAncestor(
    filePath: string,
    content: string
): { pass: boolean; details: string } {
    // Find all <table occurrences (case-insensitive)
    const tableRegex = /<table(\s|>)/gi
    let match: RegExpExecArray | null
    const tablePositions: number[] = []

    while ((match = tableRegex.exec(content)) !== null) {
        tablePositions.push(match.index)
    }

    if (tablePositions.length === 0) {
        return {
            pass: true,
            details: `No <table> elements found in ${path.relative(VIEWS_ROOT, filePath)}`,
        }
    }

    for (const tableIndex of tablePositions) {
        const { found, ancestorTags } = findOverflowXAutoAncestor(content, tableIndex)

        if (!found) {
            const relPath = path.relative(VIEWS_ROOT, filePath)
            const snippet = content.slice(Math.max(0, tableIndex - 100), tableIndex + 50)
            return {
                pass: false,
                details:
                    `[${relPath}] Found <table> at position ${tableIndex} without an overflow-x-auto ancestor.\n` +
                    `  Context around table:\n    ...${snippet.trim()}...\n` +
                    `  Ancestor tags found (last 3):\n` +
                    ancestorTags
                        .slice(-3)
                        .map(t => `    - ${t.slice(0, 120)}`)
                        .join('\n') +
                    `\n  Fix: wrap the <table> in <div class="overflow-x-auto"> or <div style="overflow-x: auto;">`,
            }
        }
    }

    const relPath = path.relative(VIEWS_ROOT, filePath)
    return {
        pass: true,
        details: `[${relPath}] All ${tablePositions.length} table(s) have an overflow-x-auto ancestor`,
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('Property 22: Blade template tables are wrapped in overflow-x-auto', () => {
    it('all Blade templates with tables have overflow-x-auto wrappers (100 iterations)', () => {
        // Validates: Requirements 10.3, 11.2

        // Filter to only blade files that actually contain <table elements
        // (fast-check will sample from this list)
        const bladeFilesWithTables = ALL_BLADE_FILES.filter(filePath => {
            const content = fs.readFileSync(filePath, 'utf-8')
            return /<table(\s|>)/i.test(content)
        })

        if (bladeFilesWithTables.length === 0) {
            // No blade files with tables — property trivially holds
            return
        }

        fc.assert(
            fc.property(
                fc.constantFrom(...bladeFilesWithTables),
                (filePath: string) => {
                    const content = fs.readFileSync(filePath, 'utf-8')
                    const { pass, details } = allTablesHaveOverflowXAutoAncestor(filePath, content)

                    if (!pass) {
                        throw new Error(details)
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })

    it('sar/template.blade.php tables are wrapped in overflow-x-auto', () => {
        // Validates: Requirements 10.3, 11.2 — explicit check for the SAR template
        const sarTemplatePath = path.join(VIEWS_ROOT, 'sar', 'template.blade.php')
        const content = fs.readFileSync(sarTemplatePath, 'utf-8')
        const { pass, details } = allTablesHaveOverflowXAutoAncestor(sarTemplatePath, content)

        if (!pass) {
            throw new Error(details)
        }
    })

    it('all discovered Blade files with tables pass the overflow-x-auto check', () => {
        // Validates: Requirements 10.3, 11.2 — exhaustive check over all blade files
        const failures: string[] = []

        for (const filePath of ALL_BLADE_FILES) {
            const content = fs.readFileSync(filePath, 'utf-8')
            const { pass, details } = allTablesHaveOverflowXAutoAncestor(filePath, content)
            if (!pass) {
                failures.push(details)
            }
        }

        if (failures.length > 0) {
            throw new Error(
                `${failures.length} Blade template(s) have unwrapped tables:\n\n` +
                failures.join('\n\n')
            )
        }
    })
})
