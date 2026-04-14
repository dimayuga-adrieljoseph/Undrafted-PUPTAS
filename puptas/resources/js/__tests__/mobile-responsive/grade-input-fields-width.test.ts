// Feature: mobile-responsive-ui, Property 9: Grade input fields fill container width

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
 * Checks that all <input> elements within subject grids carry the `w-full` class.
 * Returns { pass, details }.
 */
function allInputsHaveWFull(wrapper: ReturnType<typeof mount>): {
    pass: boolean
    details: string
} {
    // Find all subject grid containers (grid-cols-1 md:grid-cols-2)
    const allElements = wrapper.findAll('*')
    const subjectGrids = allElements.filter(el => {
        const classes = el.classes()
        return classes.includes('grid-cols-1') && classes.includes('md:grid-cols-2')
    })

    if (subjectGrids.length === 0) {
        // Fall back to checking all inputs in the page
        const inputs = wrapper.findAll('input')
        if (inputs.length === 0) {
            return { pass: true, details: 'No <input> elements found in the page' }
        }

        const missing = inputs.filter(input => !input.classes().includes('w-full'))
        if (missing.length > 0) {
            return {
                pass: false,
                details:
                    `${missing.length} of ${inputs.length} <input> element(s) are missing the "w-full" class.\n` +
                    missing
                        .slice(0, 3)
                        .map(
                            el =>
                                `  - type="${el.attributes('type') ?? 'text'}" classes="${el.classes().join(' ')}"`
                        )
                        .join('\n'),
            }
        }

        return { pass: true, details: `All ${inputs.length} input(s) carry "w-full"` }
    }

    // Check inputs inside each subject grid
    let totalInputs = 0
    const missingDetails: string[] = []

    for (const grid of subjectGrids) {
        const inputs = grid.findAll('input')
        totalInputs += inputs.length

        for (const input of inputs) {
            if (!input.classes().includes('w-full')) {
                missingDetails.push(
                    `type="${input.attributes('type') ?? 'text'}" placeholder="${input.attributes('placeholder') ?? ''}" classes="${input.classes().join(' ')}"`
                )
            }
        }
    }

    if (missingDetails.length > 0) {
        return {
            pass: false,
            details:
                `${missingDetails.length} of ${totalInputs} <input> element(s) inside subject grids are missing "w-full":\n` +
                missingDetails.slice(0, 5).map(d => `  - ${d}`).join('\n'),
        }
    }

    return {
        pass: true,
        details: `All ${totalInputs} input(s) inside subject grids carry "w-full"`,
    }
}

describe('Property 9: Grade input fields fill container width', () => {
    it('all <input> elements within subject grids shall carry the w-full class (100 iterations)', () => {
        // Validates: Requirements 4.4
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

                    const { pass, details } = allInputsHaveWFull(wrapper)
                    wrapper.unmount()

                    if (!pass) {
                        throw new Error(
                            `[${name}] Grade input fields width check failed.\n  ${details}`
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
