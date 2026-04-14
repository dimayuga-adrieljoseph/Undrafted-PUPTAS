// Feature: mobile-responsive-ui, Property 19: No fixed pixel font sizes in inline styles

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
import ICTGradeInput from '@/Pages/Grades/ICTGradeInput.vue'
import STEMGradeInput from '@/Pages/Grades/STEMGradeInput.vue'
import ABMGradeInput from '@/Pages/Grades/ABMGradeInput.vue'

// --- Layout imports (after mocks) ---
import AppLayout from '@/Layouts/AppLayout.vue'
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue'

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

const gradeInputProps = {
    user: { id: 1, firstname: 'Test', lastname: 'User', email: 'test@example.com' },
    application: { id: 1, status: 'pending' },
}

const allComponents = [
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
    {
        name: 'ICT Grade Input',
        component: ICTGradeInput,
        props: gradeInputProps,
    },
    {
        name: 'STEM Grade Input',
        component: STEMGradeInput,
        props: gradeInputProps,
    },
    {
        name: 'ABM Grade Input',
        component: ABMGradeInput,
        props: gradeInputProps,
    },
    {
        name: 'AppLayout',
        component: AppLayout,
        props: {},
        stubs: {
            Sidebar: { template: '<div />' },
            Footer: { template: '<footer />' },
            TermsandConditionsModal: { template: '<div />' },
            FontAwesomeIcon: { template: '<span />' },
        },
    },
    {
        name: 'ApplicantLayout',
        component: ApplicantLayout,
        props: {},
        stubs: {
            Sidebar: { template: '<div />' },
            Footer: { template: '<footer />' },
            TermsandConditionsModal: { template: '<div />' },
            FontAwesomeIcon: { template: '<span />' },
        },
    },
]

/**
 * Regex that matches inline font-size with a pixel value.
 * Matches patterns like: font-size: 14px, font-size:14px, font-size: 1.5px
 */
const FIXED_FONT_SIZE_PATTERN = /font-size\s*:\s*[\d.]+px/i

/**
 * Recursively walks all DOM elements in the wrapper and checks for
 * inline style attributes containing font-size with a pixel value.
 */
function findElementsWithFixedFontSize(wrapper: ReturnType<typeof mount>): string[] {
    const violations: string[] = []
    const allElements = wrapper.findAll('*')

    for (const el of allElements) {
        const style = el.attributes('style')
        if (style && FIXED_FONT_SIZE_PATTERN.test(style)) {
            const tag = el.element.tagName.toLowerCase()
            const text = el.text().slice(0, 30)
            violations.push(`<${tag}> style="${style}" text="${text}"`)
        }
    }

    return violations
}

describe('Property 19: No fixed pixel font sizes in inline styles', () => {
    it('no element in any Vue component shall have an inline font-size with a pixel value (100 iterations)', () => {
        // Validates: Requirements 8.4
        fc.assert(
            fc.property(
                fc.constantFrom(...allComponents),
                ({ name, component, props, stubs }) => {
                    const wrapper = mount(component, {
                        props,
                        global: {
                            mocks: { route: routeStub },
                            stubs: stubs ?? globalStubs,
                        },
                    })

                    const violations = findElementsWithFixedFontSize(wrapper)
                    wrapper.unmount()

                    if (violations.length > 0) {
                        throw new Error(
                            `[${name}] Found ${violations.length} element(s) with inline pixel font-size:\n` +
                            violations.map(v => `  - ${v}`).join('\n') +
                            '\n  Expected: no inline style="font-size: Npx" on any element.' +
                            '\n  Use Tailwind text-* classes instead.'
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
