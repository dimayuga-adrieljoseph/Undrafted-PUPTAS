// Feature: mobile-responsive-ui, Property 23: Multi-step workflows render all steps on mobile

import { describe, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import * as fc from 'fast-check'

// --- Inertia stubs ---
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: { user: { firstname: 'Test', lastname: 'User' } },
            privacy_consent: { required: false },
            flash: { status: null, error: null },
        },
    }),
    router: { post: vi.fn(), reload: vi.fn(), get: vi.fn() },
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

// --- FontAwesome stubs ---
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

// --- Layout stubs ---
vi.mock('@/Layouts/ApplicantLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}))

// --- route stub ---
const routeStub = vi.fn((name: string) => `/stub/${name}`)
;(window as unknown as Record<string, unknown>).route = routeStub

// --- Grade input page imports (after mocks) ---
import ICTGradeInput from '@/Pages/Grades/ICTGradeInput.vue'
import STEMGradeInput from '@/Pages/Grades/STEMGradeInput.vue'
import ABMGradeInput from '@/Pages/Grades/ABMGradeInput.vue'
import GASGradeInput from '@/Pages/Grades/GASGradeInput.vue'
import HUMSSGradeInput from '@/Pages/Grades/HUMSSGradeInput.vue'
import TVLGradeInput from '@/Pages/Grades/TVLGradeInput.vue'

const gradeInputPages = [
    { name: 'ICTGradeInput', component: ICTGradeInput },
    { name: 'STEMGradeInput', component: STEMGradeInput },
    { name: 'ABMGradeInput', component: ABMGradeInput },
    { name: 'GASGradeInput', component: GASGradeInput },
    { name: 'HUMSSGradeInput', component: HUMSSGradeInput },
    { name: 'TVLGradeInput', component: TVLGradeInput },
]

const defaultProps = {
    programs: [
        {
            id: 1,
            name: 'BS Computer Science',
            code: 'BSCS',
            math: 85,
            english: 80,
            science: 80,
            gwa: 82,
        },
    ],
    existingGrades: null,
}

const globalStubs = {
    ApplicantLayout: { template: '<div><slot /></div>' },
    FontAwesomeIcon: { template: '<span />' },
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>' },
}

// Expected step labels present in all grade input pages
const EXPECTED_STEP_LABELS = ['Grade 11', 'Grade 12', 'Program Selection']

/**
 * Checks that all step indicator elements are present in the DOM and not hidden
 * with `display: none` or `visibility: hidden` inline styles.
 *
 * Returns { pass, details }.
 */
function allStepsAreVisibleInDOM(wrapper: ReturnType<typeof mount>): {
    pass: boolean
    details: string
} {
    // Find step circles: elements with w-8 h-8 rounded-full (the numbered step indicators)
    const stepCircles = wrapper.findAll('.rounded-full').filter(el => {
        const classes = el.classes()
        return classes.includes('w-8') && classes.includes('h-8')
    })

    if (stepCircles.length < EXPECTED_STEP_LABELS.length) {
        return {
            pass: false,
            details:
                `Expected at least ${EXPECTED_STEP_LABELS.length} step circles in the DOM, ` +
                `but found ${stepCircles.length}. ` +
                `All steps must be rendered on mobile (not removed or conditionally hidden).`,
        }
    }

    // Check none of the step circles are hidden via inline style
    const hiddenCircles = stepCircles.filter(el => {
        const style = el.attributes('style') ?? ''
        return (
            style.includes('display: none') ||
            style.includes('display:none') ||
            style.includes('visibility: hidden') ||
            style.includes('visibility:hidden')
        )
    })

    if (hiddenCircles.length > 0) {
        return {
            pass: false,
            details:
                `${hiddenCircles.length} step circle(s) are hidden via inline style. ` +
                `All steps must remain in the DOM and visible on mobile viewports.`,
        }
    }

    // Check that step label text is present in the rendered HTML
    const html = wrapper.html()
    const missingLabels = EXPECTED_STEP_LABELS.filter(label => !html.includes(label))

    if (missingLabels.length > 0) {
        return {
            pass: false,
            details:
                `The following step labels are missing from the DOM: ${missingLabels.join(', ')}. ` +
                `All step labels must be rendered on mobile (not removed with v-if or display:none).`,
        }
    }

    return {
        pass: true,
        details:
            `All ${stepCircles.length} step circles are present and all step labels ` +
            `(${EXPECTED_STEP_LABELS.join(', ')}) are in the DOM.`,
    }
}

describe('Property 23: Multi-step workflows render all steps on mobile', () => {
    it('all step indicator elements shall be present in the DOM and not hidden (100 iterations)', () => {
        // Validates: Requirements 11.3
        fc.assert(
            fc.property(
                fc.constantFrom(...gradeInputPages),
                ({ name, component }) => {
                    const wrapper = mount(component, {
                        props: defaultProps,
                        global: {
                            mocks: { route: routeStub },
                            stubs: globalStubs,
                        },
                    })

                    const { pass, details } = allStepsAreVisibleInDOM(wrapper)
                    wrapper.unmount()

                    if (!pass) {
                        throw new Error(
                            `[${name}] Multi-step mobile render check failed.\n  ${details}`
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
