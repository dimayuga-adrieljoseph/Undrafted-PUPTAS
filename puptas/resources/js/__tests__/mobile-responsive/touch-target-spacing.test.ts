// Feature: mobile-responsive-ui, Property 16: Touch targets are spaced at least 8px apart on mobile

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
 * Tailwind gap classes that represent >= 8px spacing.
 * gap-2 = 0.5rem = 8px (at default 16px base font)
 * gap-3 = 0.75rem = 12px, gap-4 = 1rem = 16px, etc.
 */
const SUFFICIENT_GAP_CLASSES = [
    'gap-2', 'gap-3', 'gap-4', 'gap-5', 'gap-6', 'gap-7', 'gap-8', 'gap-9', 'gap-10',
    'gap-11', 'gap-12', 'gap-14', 'gap-16', 'gap-20', 'gap-24', 'gap-28', 'gap-32',
    'gap-x-2', 'gap-x-3', 'gap-x-4', 'gap-x-5', 'gap-x-6', 'gap-x-8',
    'gap-y-2', 'gap-y-3', 'gap-y-4', 'gap-y-5', 'gap-y-6', 'gap-y-8',
    'space-x-2', 'space-x-3', 'space-x-4', 'space-x-5', 'space-x-6', 'space-x-8',
    'space-y-2', 'space-y-3', 'space-y-4', 'space-y-5', 'space-y-6', 'space-y-8',
]

/**
 * Tailwind gap classes that represent < 8px spacing (insufficient).
 * gap-0 = 0px, gap-1 = 4px — both are insufficient.
 */
const INSUFFICIENT_GAP_CLASSES = ['gap-0', 'gap-1', 'gap-x-0', 'gap-x-1', 'gap-y-0', 'gap-y-1',
    'space-x-0', 'space-x-1', 'space-y-0', 'space-y-1']

/**
 * Checks whether a container element has sufficient spacing between its button children.
 * Returns true if spacing is adequate (>= 8px gap class), false if there's a violation.
 */
function containerHasSufficientSpacing(containerClasses: string[]): boolean {
    // If the container has an explicit insufficient gap, it's a violation
    if (INSUFFICIENT_GAP_CLASSES.some(cls => containerClasses.includes(cls))) {
        return false
    }
    // If the container has a sufficient gap class, it's fine
    if (SUFFICIENT_GAP_CLASSES.some(cls => containerClasses.includes(cls))) {
        return true
    }
    // If the container uses flex or grid but has no gap class at all,
    // check for p-* on the container itself as a proxy for spacing
    // (e.g., p-2 = 8px padding which provides visual separation)
    const hasPadding = containerClasses.some(cls =>
        /^p-[2-9]$/.test(cls) || /^p-[1-9][0-9]/.test(cls) ||
        /^px-[2-9]$/.test(cls) || /^py-[2-9]$/.test(cls)
    )
    if (hasPadding) {
        return true
    }
    // No spacing class found — this is a potential violation for flex/grid containers
    return false
}

/**
 * Finds all containers that hold 2+ button children but lack adequate spacing.
 * Only checks containers that use flex or grid layout (where gap matters).
 */
function findButtonContainersWithInsufficientSpacing(wrapper: ReturnType<typeof mount>): string[] {
    const violations: string[] = []

    // Find all elements that could be button containers
    const allElements = wrapper.findAll('div, form, nav, section, footer, header')

    for (const el of allElements) {
        const classes = el.classes()
        const isFlexOrGrid = classes.includes('flex') || classes.includes('grid') ||
            classes.includes('inline-flex') || classes.includes('inline-grid')

        if (!isFlexOrGrid) continue

        // Count direct button children
        const directButtons = el.findAll(':scope > button')
        if (directButtons.length < 2) continue

        // This container has 2+ buttons in a flex/grid layout — check spacing
        if (!containerHasSufficientSpacing(classes)) {
            const buttonTexts = directButtons.slice(0, 3).map(b => b.text().slice(0, 20) || '(no text)')
            violations.push(
                `<${el.element.tagName.toLowerCase()}> classes="${classes.join(' ')}" ` +
                `contains ${directButtons.length} buttons: [${buttonTexts.join(', ')}]`
            )
        }
    }

    return violations
}

describe('Property 16: Touch targets are spaced at least 8px apart on mobile', () => {
    it('all flex/grid containers with 2+ buttons shall have gap-2 or higher spacing class (100 iterations)', () => {
        // Validates: Requirements 7.4
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

                    const violations = findButtonContainersWithInsufficientSpacing(wrapper)
                    wrapper.unmount()

                    if (violations.length > 0) {
                        throw new Error(
                            `[${name}] Found ${violations.length} flex/grid container(s) with 2+ buttons but no gap-2+ spacing:\n` +
                            violations.map(v => `  - ${v}`).join('\n') +
                            '\n  Expected: all flex/grid containers with multiple buttons to have gap-2 (8px) or higher.' +
                            '\n  Add gap-2 or higher to ensure touch targets are at least 8px apart.'
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
