// Feature: mobile-responsive-ui, Property 10: Step indicator does not overflow on small viewports

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

/**
 * Finds the progress step indicator container and checks it carries `flex-wrap`.
 *
 * The step indicator is identified as a flex container that holds step items
 * (elements with rounded-full circles and step labels).
 *
 * Returns { pass, details }.
 */
function stepIndicatorHasFlexWrap(wrapper: ReturnType<typeof mount>): {
    pass: boolean
    details: string
} {
    // The step indicator outer container wraps the flex row of steps.
    // It is a div with `flex items-center` that contains step circles (w-8 h-8 rounded-full).
    const allElements = wrapper.findAll('*')

    // Look for the flex container that directly contains step circles
    const stepContainers = allElements.filter(el => {
        const classes = el.classes()
        // Must be a flex container
        if (!classes.includes('flex')) return false
        // Must contain at least one step circle (w-8 h-8 rounded-full)
        const circles = el.findAll('.rounded-full')
        return circles.length >= 2
    })

    if (stepContainers.length === 0) {
        return {
            pass: false,
            details:
                'No step indicator flex container found. ' +
                'Expected a flex container with at least 2 rounded-full step circles.',
        }
    }

    // Check that at least one step container has flex-wrap
    const hasFlexWrap = stepContainers.some(el => el.classes().includes('flex-wrap'))

    if (!hasFlexWrap) {
        const containerClasses = stepContainers
            .slice(0, 3)
            .map(el => `"${el.classes().join(' ')}"`)
            .join(', ')

        return {
            pass: false,
            details:
                `Step indicator container(s) are missing "flex-wrap" class.\n` +
                `  Found ${stepContainers.length} step container(s) with classes: ${containerClasses}\n` +
                `  Fix: add "flex-wrap" to the step indicator flex container to prevent overflow on small viewports.`,
        }
    }

    return {
        pass: true,
        details: `Step indicator container carries "flex-wrap"`,
    }
}

describe('Property 10: Step indicator does not overflow on small viewports', () => {
    it('the progress step indicator container shall carry flex-wrap for all grade input pages (100 iterations)', () => {
        // Validates: Requirements 4.3
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

                    const { pass, details } = stepIndicatorHasFlexWrap(wrapper)
                    wrapper.unmount()

                    if (!pass) {
                        throw new Error(
                            `[${name}] Step indicator overflow check failed.\n  ${details}`
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
