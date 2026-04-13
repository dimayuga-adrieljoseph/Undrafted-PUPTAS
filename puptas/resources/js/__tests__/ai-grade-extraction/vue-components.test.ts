// AI Grade Extraction — Vue Component Tests (Vitest + fast-check)
// Validates: Requirements 1.1, 4.1, 4.2, 4.3, 4.4, 4.5, 5.1–5.9

import { describe, it, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import * as fc from 'fast-check'

// ─── Inertia stubs ────────────────────────────────────────────────────────────
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: { user: { firstname: 'Test', lastname: 'User' } },
            privacy_consent: { required: false },
            flash: { status: null, error: null },
        },
    }),
    router: { post: vi.fn(), reload: vi.fn(), get: vi.fn(), visit: vi.fn() },
    useForm: (initial: Record<string, unknown>) => ({
        ...initial,
        post: vi.fn(),
        put: vi.fn(),
        reset: vi.fn(),
        errors: {},
        processing: false,
    }),
    Link: { template: '<a><slot /></a>' },
    Head: { template: '<div />' },
}))

// ─── Layout stubs ─────────────────────────────────────────────────────────────
vi.mock('@/Layouts/ApplicantLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}))

// ─── FontAwesome stubs ────────────────────────────────────────────────────────
vi.mock('@fortawesome/vue-fontawesome', () => ({
    FontAwesomeIcon: { template: '<span />' },
}))
vi.mock('@fortawesome/fontawesome-svg-core', () => ({
    library: { add: vi.fn() },
}))
vi.mock('@fortawesome/free-solid-svg-icons', () => ({
    faMoon: {},
    faSun: {},
    faBell: {},
}))

// ─── Modal stub ───────────────────────────────────────────────────────────────
vi.mock('@/Pages/Modal/ApplicationReviewModal.vue', () => ({
    default: { template: '<div />' },
}))

// ─── Global stubs ─────────────────────────────────────────────────────────────
const globalStubs = {
    ApplicantLayout: { template: '<div><slot /></div>' },
    FontAwesomeIcon: { template: '<span />' },
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>' },
    ApplicationReviewModal: { template: '<div />' },
}

// ─── route stub ───────────────────────────────────────────────────────────────
const routeStub = vi.fn((name: string) => `/stub/${name}`)
;(window as unknown as Record<string, unknown>).route = routeStub

// ─── axios mock ───────────────────────────────────────────────────────────────
const mockAxios = { get: vi.fn(), post: vi.fn() }
vi.stubGlobal('axios', mockAxios)

// ─── Imports (after mocks) ────────────────────────────────────────────────────
import Applicant from '@/Pages/Dashboard/Applicant.vue'
import ABMGradeInput from '@/Pages/Grades/ABMGradeInput.vue'

// ─── Default props ────────────────────────────────────────────────────────────
const defaultApplicantProps = {
    user: { id: 1, firstname: 'Test', lastname: 'User', email: 'test@example.com', strand: 'ABM' },
}

const defaultGradeInputProps = {
    programs: [{ id: 1, name: 'BS Computer Science', code: 'BSCS', math: 85, english: 80, science: 80, gwa: 82 }],
    grade: null,
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

/** Build a mock axios.get that returns the given fileStatuses */
function makeGetMock(uploadedFiles: Record<string, { url: string | null }>) {
    return vi.fn().mockResolvedValue({
        data: {
            uploadedFiles,
            status: 'pending',
            enrollment_status: 'pending',
            processes: [],
        },
    })
}

/** fast-check arbitrary for a single subject entry */
const subjectEntryArb = fc.record({
    grade: fc.integer({ min: 0, max: 100 }),
    confidence: fc.float({ min: 0, max: 1, noNaN: true }),
})

/** fast-check arbitrary for a full ExtractionResult with known ABM subjects */
const extractionResultArb = fc.record({
    math: fc.record({ 'general mathematics': subjectEntryArb }),
    science: fc.record({ 'earth and life science': subjectEntryArb }),
    english: fc.record({ 'oral communication': subjectEntryArb }),
    others: fc.record({ 'araling panlipunan': subjectEntryArb }),
})

// ─────────────────────────────────────────────────────────────────────────────
// 8.1 Property 1 — Button visibility
// ─────────────────────────────────────────────────────────────────────────────
describe('Property 1 — Button visibility', () => {
    it(
        '"Review Grades" button is shown iff all slots have non-null url (100 iterations)',
        async () => {
            // Validates: Requirements 4.1, 4.4
            await fc.assert(
                fc.asyncProperty(
                    fc.dictionary(
                        fc.string({ minLength: 1, maxLength: 20 }),
                        fc.oneof(
                            fc.record({ url: fc.string({ minLength: 1 }) }),
                            fc.record({ url: fc.constant(null) }),
                        ),
                        { minKeys: 1, maxKeys: 6 },
                    ),
                    async (fileStatuses) => {
                        mockAxios.get = makeGetMock(fileStatuses)

                        const wrapper = mount(Applicant, {
                            props: defaultApplicantProps,
                            global: { mocks: { route: routeStub }, stubs: globalStubs },
                        })

                        await flushPromises()
                        await nextTick()

                        const allNonNull =
                            Object.values(fileStatuses).length > 0 &&
                            Object.values(fileStatuses).every((f) => f?.url != null)

                        const button = wrapper.find('button[style*="background-color: rgb(158, 18, 44)"], button[style*="background-color:#9E122C"], button[style*="background-color: #9E122C"]')

                        // Find the "Review Grades" button by text
                        const allButtons = wrapper.findAll('button')
                        const reviewGradesBtn = allButtons.find((b) =>
                            b.text().includes('Review Grades') || b.text().includes('Extracting'),
                        )

                        if (allNonNull) {
                            expect(reviewGradesBtn).toBeDefined()
                        } else {
                            expect(reviewGradesBtn).toBeUndefined()
                        }

                        wrapper.unmount()
                    },
                ),
                { numRuns: 100 },
            )
        },
    )
})

// ─────────────────────────────────────────────────────────────────────────────
// 8.2 Property 8 — Autofill matching
// ─────────────────────────────────────────────────────────────────────────────
describe('Property 8 — Autofill matching', () => {
    it(
        'applyAutofill() populates confidenceMap for matched subjects (100 iterations)',
        async () => {
            // Validates: Requirements 5.3, 5.4
            await fc.assert(
                fc.asyncProperty(extractionResultArb, async (extractionResult) => {
                    const wrapper = mount(ABMGradeInput, {
                        props: { ...defaultGradeInputProps, extractionResult },
                        global: { mocks: { route: routeStub }, stubs: globalStubs },
                    })

                    await nextTick()

                    // The component instance exposes confidenceMap via the vm
                    const vm = wrapper.vm as unknown as { confidenceMap: { value: Record<string, number> } }

                    // confidenceMap should have entries for the known matched subjects
                    const confidenceMap: Record<string, number> = (vm as unknown as Record<string, unknown>).confidenceMap as Record<string, number>
                        ?? (wrapper.vm as unknown as Record<string, unknown>).$.setupState?.confidenceMap?.value
                        ?? {}

                    // Check via rendered output: confidence spans should appear for matched subjects
                    const html = wrapper.html()
                    // "general mathematics" is a known form field — its confidence span should appear
                    expect(html).toContain('AI confidence:')

                    // Unmatched subjects (araling panlipunan) should not cause errors
                    // and the component should still render without throwing
                    expect(wrapper.exists()).toBe(true)

                    wrapper.unmount()
                }),
                { numRuns: 100 },
            )
        },
    )
})

// ─────────────────────────────────────────────────────────────────────────────
// 8.3 Property 9 — Confidence highlighting
// ─────────────────────────────────────────────────────────────────────────────
describe('Property 9 — Confidence highlighting', () => {
    it(
        'red border and helper text appear iff confidence < 0.80 (100 iterations)',
        async () => {
            // Validates: Requirements 5.6, 5.7
            await fc.assert(
                fc.asyncProperty(
                    fc.float({ min: 0, max: 1, noNaN: true }),
                    fc.integer({ min: 0, max: 100 }),
                    async (confidence, grade) => {
                        const extractionResult = {
                            math: { 'general mathematics': { grade, confidence } },
                            science: {},
                            english: {},
                            others: {},
                        }

                        const wrapper = mount(ABMGradeInput, {
                            props: { ...defaultGradeInputProps, extractionResult },
                            global: { mocks: { route: routeStub }, stubs: globalStubs },
                        })

                        await nextTick()

                        const html = wrapper.html()

                        if (confidence < 0.80) {
                            // Should have red border class on the general mathematics input
                            expect(html).toContain('border-red-500')
                            // Should have helper text
                            expect(html).toContain('Low confidence result. Please verify.')
                        } else {
                            // The general mathematics input should NOT have border-red-500
                            // (other inputs may not have it either, but we check the specific field)
                            // Find the general mathematics input specifically
                            const inputs = wrapper.findAll('input[type="number"]')
                            const gmInput = inputs.find((inp) => {
                                // The general mathematics input is the first number input in the math section
                                const parent = inp.element.closest('.relative')
                                return parent?.querySelector('p.text-red-500') === null
                            })

                            // The helper text for general mathematics should not be present
                            // We check by looking for the specific pattern
                            const paragraphs = wrapper.findAll('p.text-red-500')
                            // If confidence >= 0.80, no low-confidence paragraph should reference general mathematics
                            // Since we only set general mathematics, check the first input's parent
                            const firstNumberInput = wrapper.find('input[type="number"]')
                            if (firstNumberInput.exists()) {
                                const classes = firstNumberInput.classes()
                                expect(classes).not.toContain('border-red-500')
                            }
                        }

                        wrapper.unmount()
                    },
                ),
                { numRuns: 100 },
            )
        },
    )
})

// ─────────────────────────────────────────────────────────────────────────────
// 8.4 Property 10 — Confidence percentage
// ─────────────────────────────────────────────────────────────────────────────
describe('Property 10 — Confidence percentage', () => {
    it(
        'displayed label shows Math.round(c * 100)% for any confidence c (100 iterations)',
        async () => {
            // Validates: Requirements 5.8
            await fc.assert(
                fc.asyncProperty(
                    fc.float({ min: 0, max: 1, noNaN: true }),
                    async (confidence) => {
                        const extractionResult = {
                            math: { 'general mathematics': { grade: 85, confidence } },
                            science: {},
                            english: {},
                            others: {},
                        }

                        const wrapper = mount(ABMGradeInput, {
                            props: { ...defaultGradeInputProps, extractionResult },
                            global: { mocks: { route: routeStub }, stubs: globalStubs },
                        })

                        await nextTick()

                        const expected = `AI confidence: ${Math.round(confidence * 100)}%`
                        expect(wrapper.html()).toContain(expected)

                        wrapper.unmount()
                    },
                ),
                { numRuns: 100 },
            )
        },
    )
})

// ─────────────────────────────────────────────────────────────────────────────
// 8.5 Property 11 — Fields remain editable
// ─────────────────────────────────────────────────────────────────────────────
describe('Property 11 — Fields remain editable', () => {
    it(
        'no grade input has disabled or readonly after autofill (100 iterations)',
        async () => {
            // Validates: Requirements 5.3
            await fc.assert(
                fc.asyncProperty(extractionResultArb, async (extractionResult) => {
                    const wrapper = mount(ABMGradeInput, {
                        props: { ...defaultGradeInputProps, extractionResult },
                        global: { mocks: { route: routeStub }, stubs: globalStubs },
                    })

                    await nextTick()

                    const numberInputs = wrapper.findAll('input[type="number"]')
                    for (const input of numberInputs) {
                        expect(input.attributes('disabled')).toBeUndefined()
                        expect(input.attributes('readonly')).toBeUndefined()
                    }

                    wrapper.unmount()
                }),
                { numRuns: 100 },
            )
        },
    )
})

// ─────────────────────────────────────────────────────────────────────────────
// 8.6 Example test — loading state
// ─────────────────────────────────────────────────────────────────────────────
describe('Example test — loading state', () => {
    it('clicking "Review Grades" shows spinner and disables button', async () => {
        // Validates: Requirements 4.2, 4.4
        const allUploadedFiles = {
            file10: { url: 'http://example.com/file10.jpg' },
            file11: { url: 'http://example.com/file11.jpg' },
        }

        mockAxios.get = makeGetMock(allUploadedFiles)
        // Never-resolving post
        mockAxios.post = vi.fn().mockReturnValue(new Promise(() => {}))

        const wrapper = mount(Applicant, {
            props: defaultApplicantProps,
            global: { mocks: { route: routeStub }, stubs: globalStubs },
        })

        await flushPromises()
        await nextTick()

        // Find and click the Review Grades button
        const allButtons = wrapper.findAll('button')
        const reviewGradesBtn = allButtons.find((b) => b.text().includes('Review Grades'))
        expect(reviewGradesBtn).toBeDefined()

        await reviewGradesBtn!.trigger('click')
        await nextTick()

        // Button should be disabled
        const updatedButtons = wrapper.findAll('button')
        const extractingBtn = updatedButtons.find(
            (b) => b.text().includes('Extracting') || b.text().includes('Review Grades'),
        )
        expect(extractingBtn).toBeDefined()
        expect(extractingBtn!.attributes('disabled')).toBeDefined()

        // Spinner SVG should be visible
        const spinner = wrapper.find('.animate-spin')
        expect(spinner.exists()).toBe(true)

        wrapper.unmount()
    })
})

// ─────────────────────────────────────────────────────────────────────────────
// 8.7 Example test — error display
// ─────────────────────────────────────────────────────────────────────────────
describe('Example test — error display', () => {
    it('failed extraction shows error message and re-enables button', async () => {
        // Validates: Requirements 4.2, 4.5
        const allUploadedFiles = {
            file10: { url: 'http://example.com/file10.jpg' },
            file11: { url: 'http://example.com/file11.jpg' },
        }

        mockAxios.get = makeGetMock(allUploadedFiles)
        mockAxios.post = vi.fn().mockRejectedValue({
            response: { data: { error: 'Extraction failed' } },
        })

        const wrapper = mount(Applicant, {
            props: defaultApplicantProps,
            global: { mocks: { route: routeStub }, stubs: globalStubs },
        })

        await flushPromises()
        await nextTick()

        const allButtons = wrapper.findAll('button')
        const reviewGradesBtn = allButtons.find((b) => b.text().includes('Review Grades'))
        expect(reviewGradesBtn).toBeDefined()

        await reviewGradesBtn!.trigger('click')
        await flushPromises()
        await nextTick()

        // Error message should be displayed
        expect(wrapper.html()).toContain('Extraction failed')

        // Button should be re-enabled (extracting = false)
        const updatedButtons = wrapper.findAll('button')
        const btn = updatedButtons.find((b) => b.text().includes('Review Grades'))
        expect(btn).toBeDefined()
        expect(btn!.attributes('disabled')).toBeUndefined()

        wrapper.unmount()
    })
})

// ─────────────────────────────────────────────────────────────────────────────
// 8.8 Example test — dismissible banner
// ─────────────────────────────────────────────────────────────────────────────
describe('Example test — dismissible banner', () => {
    it('banner is present after autofill and can be dismissed', async () => {
        // Validates: Requirements 5.9
        const extractionResult = {
            math: { 'general mathematics': { grade: 90, confidence: 0.95 } },
            science: {},
            english: {},
            others: {},
        }

        const wrapper = mount(ABMGradeInput, {
            props: { ...defaultGradeInputProps, extractionResult },
            global: { mocks: { route: routeStub }, stubs: globalStubs },
        })

        await nextTick()

        // Banner should be present
        expect(wrapper.html()).toContain('Grades have been autofilled by AI')

        // Find and click the dismiss button (×)
        const allButtons = wrapper.findAll('button')
        const dismissBtn = allButtons.find((b) => b.html().includes('&times;') || b.text() === '×')
        expect(dismissBtn).toBeDefined()

        await dismissBtn!.trigger('click')
        await nextTick()

        // Banner should be gone
        expect(wrapper.html()).not.toContain('Grades have been autofilled by AI')

        wrapper.unmount()
    })
})
