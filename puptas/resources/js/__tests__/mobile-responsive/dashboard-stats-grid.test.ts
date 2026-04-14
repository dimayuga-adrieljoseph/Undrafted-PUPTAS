// Feature: mobile-responsive-ui, Property 11: Dashboard stats grid uses responsive column classes

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
 * Checks that the stats grid container carries all three responsive column classes:
 * grid-cols-1, sm:grid-cols-2, lg:grid-cols-4
 */
function statsGridHasResponsiveClasses(wrapper: ReturnType<typeof mount>): {
    pass: boolean
    details: string
} {
    const allElements = wrapper.findAll('*')

    const statsGrid = allElements.find(el => {
        const classes = el.classes()
        return (
            classes.includes('grid-cols-1') &&
            classes.includes('sm:grid-cols-2') &&
            classes.includes('lg:grid-cols-4')
        )
    })

    if (!statsGrid) {
        return {
            pass: false,
            details:
                'No stats grid container found with all three classes: ' +
                '"grid-cols-1 sm:grid-cols-2 lg:grid-cols-4". ' +
                'Expected the statistics grid to carry all three responsive column classes.',
        }
    }

    return {
        pass: true,
        details: `Found stats grid with classes: ${statsGrid.classes().join(' ')}`,
    }
}

describe('Property 11: Dashboard stats grid uses responsive column classes', () => {
    it('stats grid shall carry grid-cols-1, sm:grid-cols-2, and lg:grid-cols-4 for all dashboard pages (100 iterations)', () => {
        // Validates: Requirements 5.1
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

                    const { pass, details } = statsGridHasResponsiveClasses(wrapper)
                    wrapper.unmount()

                    if (!pass) {
                        throw new Error(
                            `[${name}] Stats grid responsive classes check failed.\n  ${details}`
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
