// Feature: mobile-responsive-ui, Property 8: Grade input grids are single-column on mobile

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
 * Checks that all subject input grid containers carry `grid-cols-1 md:grid-cols-2`.
 * Returns { pass, details }.
 */
function subjectGridsAreSingleColumn(wrapper: ReturnType<typeof mount>): {
    pass: boolean
    details: string
} {
    // Find all elements that have grid-cols-1 AND md:grid-cols-2 (subject grids)
    const allElements = wrapper.findAll('*')
    const subjectGrids = allElements.filter(el => {
        const classes = el.classes()
        return classes.includes('grid-cols-1') && classes.includes('md:grid-cols-2')
    })

    if (subjectGrids.length === 0) {
        return {
            pass: false,
            details: 'No subject grid containers with "grid-cols-1 md:grid-cols-2" found. Expected at least one.',
        }
    }

    return {
        pass: true,
        details: `Found ${subjectGrids.length} subject grid(s) with "grid-cols-1 md:grid-cols-2"`,
    }
}

/**
 * Checks that the summary cards grid carries `grid-cols-2 md:grid-cols-4`.
 * Returns { pass, details }.
 */
function summaryCardsGridHasTwoColumns(wrapper: ReturnType<typeof mount>): {
    pass: boolean
    details: string
} {
    const allElements = wrapper.findAll('*')
    const summaryGrids = allElements.filter(el => {
        const classes = el.classes()
        return classes.includes('grid-cols-2') && classes.includes('md:grid-cols-4')
    })

    if (summaryGrids.length === 0) {
        return {
            pass: false,
            details:
                'No summary cards grid with "grid-cols-2 md:grid-cols-4" found. ' +
                'Expected the summary cards container to use grid-cols-2 md:grid-cols-4 (two per row on mobile).',
        }
    }

    return {
        pass: true,
        details: `Found ${summaryGrids.length} summary grid(s) with "grid-cols-2 md:grid-cols-4"`,
    }
}

describe('Property 8: Grade input grids are single-column on mobile', () => {
    it('subject grids shall have grid-cols-1 md:grid-cols-2 for all grade input pages (100 iterations)', () => {
        // Validates: Requirements 4.1, 4.2, 4.5
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

                    const { pass, details } = subjectGridsAreSingleColumn(wrapper)
                    wrapper.unmount()

                    if (!pass) {
                        throw new Error(`[${name}] Subject grid check failed.\n  ${details}`)
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })

    it('summary cards grid shall have grid-cols-2 md:grid-cols-4 for all grade input pages (100 iterations)', () => {
        // Validates: Requirements 4.5
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

                    const { pass, details } = summaryCardsGridHasTwoColumns(wrapper)
                    wrapper.unmount()

                    if (!pass) {
                        throw new Error(`[${name}] Summary cards grid check failed.\n  ${details}`)
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
