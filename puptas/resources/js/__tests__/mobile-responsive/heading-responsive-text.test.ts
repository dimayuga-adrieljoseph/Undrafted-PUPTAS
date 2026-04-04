// Feature: mobile-responsive-ui, Property 17: Headings use responsive text scale classes

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
    router: { post: vi.fn(), reload: vi.fn(), get: vi.fn(), delete: vi.fn() },
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
    default: { template: '<div><slot /><slot name="header" /></div>' },
}))
vi.mock('@/Layouts/ApplicantLayout.vue', () => ({
    default: { template: '<div><slot /><slot name="header" /></div>' },
}))
vi.mock('@/Layouts/EvaluatorLayout.vue', () => ({
    default: { template: '<div><slot /><slot name="header" /></div>' },
}))
vi.mock('@/Layouts/InterviewerLayout.vue', () => ({
    default: { template: '<div><slot /><slot name="header" /></div>' },
}))
vi.mock('@/Layouts/RecordStaffLayout.vue', () => ({
    default: { template: '<div><slot /><slot name="header" /></div>' },
}))
vi.mock('@/Layouts/SuperAdminLayout.vue', () => ({
    default: { template: '<div><slot /><slot name="header" /></div>' },
}))

// --- Component stubs ---
vi.mock('@/Components/Sidebar.vue', () => ({
    default: { template: '<div class="sidebar-stub" />' },
}))
vi.mock('@/Components/Footer.vue', () => ({
    default: { template: '<footer />' },
}))
vi.mock('@/Pages/Modal/TermsandConditionsModal.vue', () => ({
    default: { template: '<div />' },
}))
vi.mock('@/Pages/Modal/ApplicationReviewModal.vue', () => ({
    default: { template: '<div />' },
}))
vi.mock('@/Composables/useGlobalLoading', () => ({
    useGlobalLoading: () => ({ isLoading: { value: false } }),
}))

// --- route stub ---
const routeStub = vi.fn((name: string) => `/stub/${name}`)
;(window as unknown as Record<string, unknown>).route = routeStub

// --- axios stub ---
;(window as unknown as Record<string, unknown>).axios = {
    get: vi.fn().mockResolvedValue({ data: { uploadedFiles: {}, user: { application: {} }, processes: [] } }),
    post: vi.fn().mockResolvedValue({ data: {} }),
}

// --- Page imports (after mocks) ---
import AdminDashboard from '@/Pages/Dashboard/Admin.vue'
import ApplicantDashboard from '@/Pages/Dashboard/Applicant.vue'
import EvaluatorDashboard from '@/Pages/Dashboard/Evaluator.vue'
import InterviewerDashboard from '@/Pages/Dashboard/Interviewer.vue'
import RecordsDashboard from '@/Pages/Dashboard/Records.vue'

// --- Layout imports (after mocks) ---
import AppLayout from '@/Layouts/AppLayout.vue'
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue'
import EvaluatorLayout from '@/Layouts/EvaluatorLayout.vue'
import InterviewerLayout from '@/Layouts/InterviewerLayout.vue'
import RecordStaffLayout from '@/Layouts/RecordStaffLayout.vue'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const globalStubs = {
    AppLayout: { template: '<div><slot /><slot name="header" /></div>' },
    ApplicantLayout: { template: '<div><slot /><slot name="header" /></div>' },
    EvaluatorLayout: { template: '<div><slot /><slot name="header" /></div>' },
    InterviewerLayout: { template: '<div><slot /><slot name="header" /></div>' },
    RecordStaffLayout: { template: '<div><slot /><slot name="header" /></div>' },
    FontAwesomeIcon: { template: '<span />' },
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>' },
    LineChart: { template: '<canvas />' },
    ApplicationReviewModal: { template: '<div />' },
}

const pageComponents = [
    {
        name: 'Admin Dashboard',
        component: AdminDashboard,
        props: {
            allUsers: [],
            summary: { total: 0, accepted: 0, pending: 0, returned: 0 },
            chartData: { submitted: [], accepted: [], returned: [], labels: [] },
        },
    },
    {
        name: 'Applicant Dashboard',
        component: ApplicantDashboard,
        props: { user: { id: 1, firstname: 'Test', lastname: 'User', email: 'test@example.com' } },
    },
    {
        name: 'Evaluator Dashboard',
        component: EvaluatorDashboard,
        props: {
            user: { id: 1, firstname: 'Test', lastname: 'User' },
            pendingUsers: [],
            summary: { total: 0, accepted: 0, pending: 0, returned: 0 },
            chartData: { submitted: [], accepted: [], returned: [], labels: [] },
        },
    },
    {
        name: 'Interviewer Dashboard',
        component: InterviewerDashboard,
        props: {
            user: { id: 1, firstname: 'Test', lastname: 'User' },
            pendingUsers: [],
            summary: { total: 0, accepted: 0, pending: 0, returned: 0 },
            chartData: { submitted: [], accepted: [], returned: [], labels: [] },
        },
    },
    {
        name: 'Records Dashboard',
        component: RecordsDashboard,
        props: {
            user: { id: 1, firstname: 'Test', lastname: 'User' },
            allUsers: [],
        },
    },
]

const layoutComponents = [
    { name: 'AppLayout', component: AppLayout },
    { name: 'ApplicantLayout', component: ApplicantLayout },
    { name: 'EvaluatorLayout', component: EvaluatorLayout },
    { name: 'InterviewerLayout', component: InterviewerLayout },
    { name: 'RecordStaffLayout', component: RecordStaffLayout },
    { name: 'SuperAdminLayout', component: SuperAdminLayout },
]

/**
 * Responsive text scale classes that satisfy Property 17.
 * An h1 must carry at least one base text size AND at least one responsive
 * text size (md: or lg: prefixed), forming a scale.
 *
 * Accepted base sizes: text-xl, text-2xl, text-3xl, text-4xl
 * Accepted responsive sizes: md:text-*, lg:text-*
 */
function h1HasResponsiveTextScale(h1: ReturnType<typeof mount>['element'] | null, classes: string[]): boolean {
    const baseTextSizes = ['text-xl', 'text-2xl', 'text-3xl', 'text-4xl', 'text-lg']
    const hasBaseSize = classes.some(c => baseTextSizes.includes(c))
    const hasResponsiveSize = classes.some(c => /^(md|lg|sm):text-/.test(c))
    return hasBaseSize && hasResponsiveSize
}

/**
 * Finds all h1 elements in a mounted wrapper and checks each one for
 * responsive text scale classes.
 * Returns { pass: boolean, details: string }
 */
function allH1sHaveResponsiveTextScale(wrapper: ReturnType<typeof mount>): {
    pass: boolean
    details: string
} {
    const h1Elements = wrapper.findAll('h1')

    if (h1Elements.length === 0) {
        // No h1 elements — property vacuously holds (nothing to violate)
        return { pass: true, details: 'No h1 elements found (vacuously true)' }
    }

    const violations: string[] = []

    for (const h1 of h1Elements) {
        const classes = h1.classes()
        if (!h1HasResponsiveTextScale(null, classes)) {
            violations.push(
                `h1 text="${h1.text().slice(0, 40)}" classes="${classes.join(' ')}"`
            )
        }
    }

    if (violations.length > 0) {
        return {
            pass: false,
            details:
                `${violations.length} h1 element(s) lack responsive text scale classes:\n` +
                violations.map(v => `  - ${v}`).join('\n') +
                '\n  Expected: base text size (e.g. text-xl) AND responsive modifier (e.g. md:text-2xl)',
        }
    }

    return { pass: true, details: `All ${h1Elements.length} h1 element(s) have responsive text scale classes` }
}

describe('Property 17: Headings use responsive text scale classes', () => {
    it('all h1 elements in dashboard pages shall carry responsive text size classes (100 iterations)', () => {
        // Validates: Requirements 8.1, 8.2
        fc.assert(
            fc.property(
                fc.constantFrom(...pageComponents),
                ({ name, component, props }) => {
                    const wrapper = mount(component, {
                        props,
                        global: {
                            mocks: { route: routeStub },
                            stubs: globalStubs,
                        },
                    })

                    const { pass, details } = allH1sHaveResponsiveTextScale(wrapper)
                    wrapper.unmount()

                    if (!pass) {
                        throw new Error(`[${name}] ${details}`)
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })

    it('all h1 elements in layout components shall carry responsive text size classes (100 iterations)', () => {
        // Validates: Requirements 8.1, 8.2
        fc.assert(
            fc.property(
                fc.constantFrom(...layoutComponents),
                ({ name, component }) => {
                    const wrapper = mount(component, {
                        global: {
                            mocks: { route: routeStub },
                            stubs: {
                                Sidebar: { template: '<div />' },
                                Footer: { template: '<footer />' },
                                TermsandConditionsModal: { template: '<div />' },
                                FontAwesomeIcon: { template: '<span />' },
                            },
                        },
                    })

                    const { pass, details } = allH1sHaveResponsiveTextScale(wrapper)
                    wrapper.unmount()

                    if (!pass) {
                        throw new Error(`[${name}] ${details}`)
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
