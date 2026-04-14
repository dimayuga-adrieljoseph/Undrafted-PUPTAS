// Feature: mobile-responsive-ui, Property 12: Dashboard main grid stacks below 1024px

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

// --- vue-chart-3 stub ---
vi.mock('vue-chart-3', () => ({
    LineChart: { template: '<canvas />' },
    BarChart: { template: '<canvas />' },
}))

// --- chart.js stub ---
vi.mock('chart.js', () => ({
    Chart: { register: vi.fn() },
    ChartJS: { register: vi.fn() },
    LineController: {},
    LineElement: {},
    CategoryScale: {},
    LinearScale: {},
    PointElement: {},
    Tooltip: {},
    Legend: {},
    Filler: {},
    BarController: {},
    BarElement: {},
}))

// --- Layout stubs ---
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}))
vi.mock('@/Layouts/EvaluatorLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}))
vi.mock('@/Layouts/InterviewerLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}))
vi.mock('@/Layouts/RecordStaffLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}))
vi.mock('@/Layouts/ApplicantLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}))

// --- Modal stubs ---
vi.mock('@/Pages/Modal/ApplicationReviewModal.vue', () => ({
    default: { template: '<div />' },
}))

// --- route stub ---
const routeStub = vi.fn((name: string) => `/stub/${name}`)
;(window as unknown as Record<string, unknown>).route = routeStub

// --- axios stub ---
;(window as unknown as Record<string, unknown>).axios = {
    get: vi.fn().mockResolvedValue({ data: { uploadedFiles: {}, user: { application: {} }, processes: [] } }),
    post: vi.fn().mockResolvedValue({ data: {} }),
}

// --- Dashboard page imports (after mocks) ---
import AdminDashboard from '@/Pages/Dashboard/Admin.vue'
import EvaluatorDashboard from '@/Pages/Dashboard/Evaluator.vue'
import InterviewerDashboard from '@/Pages/Dashboard/Interviewer.vue'
import RecordsDashboard from '@/Pages/Dashboard/Records.vue'

const dashboardPages = [
    {
        name: 'Admin',
        component: AdminDashboard,
        props: {
            allUsers: [],
            summary: { total: 10, accepted: 5, pending: 3, returned: 2 },
            chartData: { submitted: [], accepted: [], returned: [], labels: [] },
        },
    },
    {
        name: 'Evaluator',
        component: EvaluatorDashboard,
        props: {
            user: { id: 1, firstname: 'Test', lastname: 'User' },
            pendingUsers: [],
            summary: { total: 10, accepted: 5, pending: 3, returned: 2 },
            chartData: { submitted: [], accepted: [], returned: [], labels: [] },
        },
    },
    {
        name: 'Interviewer',
        component: InterviewerDashboard,
        props: {
            user: { id: 1, firstname: 'Test', lastname: 'User' },
            pendingUsers: [],
            summary: { total: 10, accepted: 5, pending: 3, returned: 2 },
            chartData: { submitted: [], accepted: [], returned: [], labels: [] },
        },
    },
    {
        name: 'Records',
        component: RecordsDashboard,
        props: {
            user: { id: 1, firstname: 'Test', lastname: 'User' },
            allUsers: [],
        },
    },
]

const globalStubs = {
    AppLayout: { template: '<div><slot /></div>' },
    EvaluatorLayout: { template: '<div><slot /></div>' },
    InterviewerLayout: { template: '<div><slot /></div>' },
    RecordStaffLayout: { template: '<div><slot /></div>' },
    ApplicantLayout: { template: '<div><slot /></div>' },
    FontAwesomeIcon: { template: '<span />' },
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>' },
    LineChart: { template: '<canvas />' },
    ApplicationReviewModal: { template: '<div />' },
}

/**
 * Checks that the main content grid carries both grid-cols-1 and lg:grid-cols-3.
 * This ensures the grid stacks to a single column below 1024px (lg breakpoint).
 *
 * The main content grid is the one that holds the chart panel and recent applications
 * panel side by side on desktop. It must NOT also be the stats grid (which has
 * sm:grid-cols-2 and lg:grid-cols-4).
 */
function mainContentGridHasStackingClasses(wrapper: ReturnType<typeof mount>): {
    pass: boolean
    details: string
} {
    const allElements = wrapper.findAll('*')

    // Find a grid that has grid-cols-1 and lg:grid-cols-3 but is NOT the stats grid
    // (stats grid has sm:grid-cols-2 and lg:grid-cols-4)
    const mainGrid = allElements.find(el => {
        const classes = el.classes()
        return (
            classes.includes('grid-cols-1') &&
            classes.includes('lg:grid-cols-3') &&
            !classes.includes('sm:grid-cols-2') &&
            !classes.includes('lg:grid-cols-4')
        )
    })

    if (!mainGrid) {
        return {
            pass: false,
            details:
                'No main content grid found with "grid-cols-1 lg:grid-cols-3". ' +
                'Expected the chart + recent applications grid to carry these classes ' +
                'so it stacks vertically below the lg (1024px) breakpoint.',
        }
    }

    return {
        pass: true,
        details: `Found main content grid with classes: ${mainGrid.classes().join(' ')}`,
    }
}

describe('Property 12: Dashboard main grid stacks below 1024px', () => {
    it('main content grid shall carry grid-cols-1 and lg:grid-cols-3 for all dashboard pages (100 iterations)', () => {
        // Validates: Requirements 5.2
        fc.assert(
            fc.property(
                fc.constantFrom(...dashboardPages),
                ({ name, component, props }) => {
                    const wrapper = mount(component, {
                        props,
                        global: {
                            mocks: { route: routeStub },
                            stubs: globalStubs,
                        },
                    })

                    const { pass, details } = mainContentGridHasStackingClasses(wrapper)
                    wrapper.unmount()

                    if (!pass) {
                        throw new Error(
                            `[${name}] Main content grid stacking classes check failed.\n  ${details}`
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
