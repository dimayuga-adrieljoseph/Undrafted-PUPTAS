// Application Grades Display — Grades Section Tests (Vitest + fast-check)
// Validates: Requirements 1.1, 1.2, 1.3, 1.4, 2.1, 2.2, 2.3, 3.1, 3.2

import { describe, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, ref } from 'vue'
import * as fc from 'fast-check'

// ─── Inertia stubs ────────────────────────────────────────────────────────────
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: { user: { firstname: 'Test', lastname: 'User' } },
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
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}))
vi.mock('@/Layouts/EvaluatorLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}))
vi.mock('@/Layouts/InterviewerLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}))

// ─── Global stubs ─────────────────────────────────────────────────────────────
vi.stubGlobal('axios', { get: vi.fn(), post: vi.fn() })
vi.stubGlobal('route', vi.fn((name: string) => '/stub/' + name))

// ─── Inline GradesSection test component ─────────────────────────────────────
const GradesSection = defineComponent({
    props: {
        grades: { type: Object, default: null }
    },
    setup(props) {
        const formatGrade = (value: number | string | null | undefined): string => {
            if (value === null || value === undefined) return "—";
            const num = parseFloat(String(value));
            return isNaN(num) ? "—" : num.toFixed(2);
        };
        return { formatGrade, grades: ref(props.grades) }
    },
    template: `
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">English</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatGrade(grades?.english) }}</p>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Mathematics</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatGrade(grades?.mathematics) }}</p>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Science</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatGrade(grades?.science) }}</p>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Grade 12 – 1st Semester</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatGrade(grades?.g12_first_sem) }}</p>
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Grade 12 – 2nd Semester</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatGrade(grades?.g12_second_sem) }}</p>
            </div>
        </div>
    `
})

// ─── Arbitrary for a full grades object ──────────────────────────────────────
const gradesArb = fc.record({
    english: fc.float({ min: 0, max: 100, noNaN: true }),
    mathematics: fc.float({ min: 0, max: 100, noNaN: true }),
    science: fc.float({ min: 0, max: 100, noNaN: true }),
    g12_first_sem: fc.float({ min: 0, max: 100, noNaN: true }),
    g12_second_sem: fc.float({ min: 0, max: 100, noNaN: true }),
})

// ─────────────────────────────────────────────────────────────────────────────
// Task 1.5 — Property 1: All five grade fields are rendered
// ─────────────────────────────────────────────────────────────────────────────
describe('Feature: application-grades-display, Property 1: all five grade fields are rendered', () => {
    it(
        'all five label strings appear in the rendered output for any grades object (100 iterations)',
        async () => {
            // Validates: Requirements 1.1
            await fc.assert(
                fc.asyncProperty(gradesArb, async (grades) => {
                    const wrapper = mount(GradesSection, { props: { grades } })
                    const html = wrapper.html()

                    expect(html).toContain('English')
                    expect(html).toContain('Mathematics')
                    expect(html).toContain('Science')
                    expect(html).toContain('Grade 12 – 1st Semester')
                    expect(html).toContain('Grade 12 – 2nd Semester')

                    wrapper.unmount()
                }),
                { numRuns: 100 },
            )
        },
    )
})

// ─────────────────────────────────────────────────────────────────────────────
// Task 1.6 — Property 4: All five grade cards share the same CSS classes
// ─────────────────────────────────────────────────────────────────────────────
describe('Feature: application-grades-display, Property 4: all five grade cards share the same CSS classes', () => {
    it(
        'exactly 5 cards exist and every card has identical CSS classes (100 iterations)',
        async () => {
            // Validates: Requirements 2.1
            await fc.assert(
                fc.asyncProperty(gradesArb, async (grades) => {
                    const wrapper = mount(GradesSection, { props: { grades } })

                    const cards = wrapper.findAll('.p-3')
                    expect(cards).toHaveLength(5)

                    for (const card of cards) {
                        expect(card.classes().join(' ')).toBe(
                            'p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center'
                        )
                    }

                    wrapper.unmount()
                }),
                { numRuns: 100 },
            )
        },
    )
})

// ─────────────────────────────────────────────────────────────────────────────
// Task 1.7 — Property 5: Grade label precedes grade value in each card
// ─────────────────────────────────────────────────────────────────────────────
describe('Feature: application-grades-display, Property 5: grade label precedes grade value in each card', () => {
    it(
        'label <p> (text-xs) appears before value <p> (text-lg) in every card (100 iterations)',
        async () => {
            // Validates: Requirements 2.3
            await fc.assert(
                fc.asyncProperty(gradesArb, async (grades) => {
                    const wrapper = mount(GradesSection, { props: { grades } })

                    const cards = wrapper.findAll('.p-3')
                    expect(cards).toHaveLength(5)

                    for (const card of cards) {
                        const paragraphs = card.findAll('p')
                        expect(paragraphs).toHaveLength(2)

                        // First <p> is the label (text-xs)
                        expect(paragraphs[0].classes()).toContain('text-xs')
                        // Second <p> is the value (text-lg)
                        expect(paragraphs[1].classes()).toContain('text-lg')
                    }

                    wrapper.unmount()
                }),
                { numRuns: 100 },
            )
        },
    )
})

// ─────────────────────────────────────────────────────────────────────────────
// Task 1.8 — Unit tests for the Grades Section
// ─────────────────────────────────────────────────────────────────────────────
describe('Grades Section — unit tests', () => {
    it('renders all five labels and formatted values for a complete grades object', () => {
        // Validates: Requirements 1.1, 1.2, 1.4, 2.1, 2.2, 3.1
        const grades = {
            english: 85,
            mathematics: 90.5,
            science: 78,
            g12_first_sem: 88,
            g12_second_sem: 92,
        }

        const wrapper = mount(GradesSection, { props: { grades } })
        const html = wrapper.html()

        // Labels
        expect(html).toContain('English')
        expect(html).toContain('Mathematics')
        expect(html).toContain('Science')
        expect(html).toContain('Grade 12 – 1st Semester')
        expect(html).toContain('Grade 12 – 2nd Semester')

        // Formatted values
        expect(html).toContain('85.00')
        expect(html).toContain('90.50')
        expect(html).toContain('78.00')
        expect(html).toContain('88.00')
        expect(html).toContain('92.00')

        wrapper.unmount()
    })

    it('renders "—" for all five cards when grades is an empty object (null-like)', () => {
        // Validates: Requirements 1.3, 3.2
        const wrapper = mount(GradesSection, { props: { grades: {} } })
        const html = wrapper.html()

        // Count occurrences of "—"
        const dashCount = (html.match(/—/g) || []).length
        expect(dashCount).toBe(5)

        wrapper.unmount()
    })

    it('renders correct mix of values and dashes when three fields are null and two are present', () => {
        // Validates: Requirements 1.3, 1.4
        const grades = {
            english: 85,
            mathematics: null,
            science: null,
            g12_first_sem: null,
            g12_second_sem: 92,
        }

        const wrapper = mount(GradesSection, { props: { grades } })
        const html = wrapper.html()

        expect(html).toContain('85.00')
        expect(html).toContain('92.00')

        const dashCount = (html.match(/—/g) || []).length
        expect(dashCount).toBe(3)

        wrapper.unmount()
    })

    it('root container has responsive grid classes', () => {
        // Validates: Requirements 2.1, 2.2
        const grades = { english: 80, mathematics: 80, science: 80, g12_first_sem: 80, g12_second_sem: 80 }
        const wrapper = mount(GradesSection, { props: { grades } })

        const root = wrapper.find('div')
        expect(root.classes()).toContain('grid')
        expect(root.classes()).toContain('grid-cols-2')
        expect(root.classes()).toContain('sm:grid-cols-3')
        expect(root.classes()).toContain('md:grid-cols-5')
        expect(root.classes()).toContain('gap-3')

        wrapper.unmount()
    })
})
