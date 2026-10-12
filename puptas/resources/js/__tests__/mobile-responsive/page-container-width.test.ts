// Feature: mobile-responsive-ui, Property 29: Page content uses a fluid width
//
// The PUPTAS content width is defined once, in Components/PageContainer.vue, and
// mirrors the System Logs / Audit Logs page (the reference implementation):
// fill the shell's width and keep only a horizontal gutter. Pages must not
// reintroduce a centred `max-w-*` column around data-heavy content.

import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import * as fs from 'fs'
import * as path from 'path'

import PageContainer from '@/Components/PageContainer.vue'

const PAGES_ROOT = path.resolve(__dirname, '../../Pages')

/**
 * Pages whose content is tables / filters / cards and therefore must use the
 * shared fluid container. Each one is expected to import and render
 * <PageContainer> with no page-level max-w.
 */
const FLUID_PAGES = [
    'SuperAdmin/Logs.vue',            // reference implementation
    'SuperAdmin/WaiverManagement.vue',
    'SuperAdmin/ScoreOverrides.vue',
    'SuperAdmin/ApiClients.vue',
    'SuperAdmin/CutoffSettings.vue',
    'Grades/ABMGradeInput.vue',
    'Grades/GASGradeInput.vue',
    'Grades/HUMSSGradeInput.vue',
    'Grades/ICTGradeInput.vue',
    'Grades/STEMGradeInput.vue',
    'Grades/TVLGradeInput.vue',
    'Profile/Applicant.vue',
    'Programs/Qualified.vue',
    'Programs/StaffPrograms.vue',
    'Reports/ControlList.vue',
    'Reports/Logbook.vue',
]

/**
 * Pages that are intentionally narrow. These must KEEP their constraint so a
 * future "remove every max-w" pass cannot wreck auth screens, modals or
 * single-purpose data-entry forms.
 */
const INTENTIONALLY_NARROW_PAGES = [
    'Programs/Create.vue',   // focused program creation form
    'Uploads/Form.vue',      // focused upload form
    'UserManagement/AddUser.vue',
    'UserManagement/EditUser.vue',
    'Auth/Login.vue',
    'Auth/Register.vue',
    'Public/CheckStatus.vue',
]

/** Reads a page and returns its source. */
function readPage(relative: string): string {
    return fs.readFileSync(path.join(PAGES_ROOT, relative), 'utf8')
}

describe('Property 29: Page content uses a fluid width', () => {
    describe('PageContainer itself', () => {
        it('renders the reference fluid width used by System Logs', () => {
            const wrapper = mount(PageContainer, { slots: { default: '<p>content</p>' } })
            const classes = wrapper.find('div').classes()

            expect(classes).toContain('w-full')
            expect(classes).toContain('px-4')
            expect(classes).toContain('md:px-8')
            expect(classes).toContain('py-8')
        })

        it('never constrains the content width', () => {
            const classes = mount(PageContainer).find('div').classes()
            expect(classes.some(c => c.startsWith('max-w-'))).toBe(false)
            expect(classes).not.toContain('mx-auto')
        })

        it('omits vertical padding when an ancestor already provides it', () => {
            const wrapper = mount(PageContainer, { props: { verticalPadding: false } })
            const classes = wrapper.find('div').classes()

            expect(classes).toContain('w-full')
            expect(classes).not.toContain('py-8')
        })

        it('merges caller classes onto the container (attribute fallthrough)', () => {
            const wrapper = mount(PageContainer, { attrs: { class: 'space-y-6' } })
            const classes = wrapper.find('div').classes()

            expect(classes).toContain('space-y-6')
            expect(classes).toContain('w-full')
        })
    })

    describe('data-heavy pages use the shared container', () => {
        for (const page of FLUID_PAGES) {
            it(`${page} renders <PageContainer> and has no max-w-* wrapper`, () => {
                const source = readPage(page)

                expect(source).toContain('PageContainer.vue')
                expect(source).toMatch(/<PageContainer[\s>]/)

                // No page-level width cap left behind.
                expect(source).not.toMatch(/max-w-(?:5xl|6xl|7xl|9xl)\s+mx-auto/)
                expect(source).not.toMatch(/mx-auto[^"']*max-w-(?:5xl|6xl|7xl|9xl)/)
            })
        }
    })

    describe('intentionally narrow pages keep their constraint', () => {
        for (const page of INTENTIONALLY_NARROW_PAGES) {
            it(`${page} retains a deliberate width constraint`, () => {
                const source = readPage(page)
                const hasConstraint =
                    /max-w-(?:xs|sm|md|lg|xl|2xl|3xl|4xl|5xl|6xl|7xl)\b/.test(source) ||
                    /max-width:\s*\d/.test(source)

                expect(hasConstraint).toBe(true)
            })
        }
    })
})
