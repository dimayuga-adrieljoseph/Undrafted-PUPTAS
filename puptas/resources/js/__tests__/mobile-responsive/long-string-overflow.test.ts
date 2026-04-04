// Feature: mobile-responsive-ui, Property 20: Long strings do not overflow on small viewports

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
 * Tags that may contain long strings (email addresses, names, etc.)
 */
const TEXT_TAGS = new Set(['p', 'span', 'td', 'th', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'label', 'a', 'li'])

/**
 * A "long unbroken string" is a token with no whitespace longer than 20 characters.
 * This simulates email addresses or long names that could overflow a narrow viewport.
 */
const LONG_UNBROKEN_STRING_RE = /\S{21,}/

/**
 * Classes that indicate the element handles long-string overflow safely:
 * - break-words  → word-break: break-word
 * - break-all    → word-break: break-all
 * - truncate     → overflow: hidden; text-overflow: ellipsis; white-space: nowrap
 * - overflow-wrap: break-word in inline style
 */
function hasBreakContainment(el: ReturnType<typeof mount>['element']): boolean {
    const classList = el.className ?? ''
    if (
        classList.includes('break-words') ||
        classList.includes('break-all') ||
        classList.includes('truncate')
    ) {
        return true
    }
    const style = (el as HTMLElement).getAttribute?.('style') ?? ''
    if (/overflow-wrap\s*:\s*break-word/i.test(style) || /word-break\s*:\s*break/i.test(style)) {
        return true
    }
    return false
}

/**
 * Walk up the DOM tree (within the wrapper root) to check if any ancestor
 * has break containment applied.
 */
function ancestorHasBreakContainment(el: Element, root: Element): boolean {
    let current: Element | null = el.parentElement
    while (current && current !== root && root.contains(current)) {
        if (hasBreakContainment(current)) return true
        current = current.parentElement
    }
    return false
}

/**
 * Find all elements that contain a long unbroken string but lack break containment
 * on themselves or any ancestor within the component root.
 */
function findLongStringViolations(wrapper: ReturnType<typeof mount>): string[] {
    const violations: string[] = []
    const root = wrapper.element as Element
    const allElements = wrapper.findAll('*')

    for (const elWrapper of allElements) {
        const el = elWrapper.element as Element
        const tag = el.tagName.toLowerCase()

        if (!TEXT_TAGS.has(tag)) continue

        // Only look at the direct text content of this node (not children)
        // to avoid double-reporting nested elements
        let directText = ''
        for (const child of Array.from(el.childNodes)) {
            if (child.nodeType === Node.TEXT_NODE) {
                directText += child.textContent ?? ''
            }
        }

        if (!LONG_UNBROKEN_STRING_RE.test(directText)) continue

        // Check if this element or any ancestor has break containment
        if (hasBreakContainment(el) || ancestorHasBreakContainment(el, root)) continue

        const snippet = directText.trim().slice(0, 50)
        violations.push(`<${tag}> text="${snippet}" (no break-words/break-all/truncate/overflow-wrap)`)
    }

    return violations
}

describe('Property 20: Long strings do not overflow on small viewports', () => {
    it('elements with long unbroken strings (>20 chars) must have break-words, break-all, truncate, or overflow-wrap:break-word (100 iterations)', () => {
        // Validates: Requirements 8.5
        fc.assert(
            fc.property(
                fc.constantFrom(...allComponents),
                ({ name, component, props, stubs }) => {
                    const wrapper = mount(component as Parameters<typeof mount>[0], {
                        props: props as Record<string, unknown>,
                        global: {
                            mocks: { route: routeStub },
                            stubs: stubs ?? globalStubs,
                        },
                    })

                    const violations = findLongStringViolations(wrapper)
                    wrapper.unmount()

                    if (violations.length > 0) {
                        throw new Error(
                            `[${name}] Found ${violations.length} element(s) with long unbroken strings lacking overflow containment:\n` +
                            violations.map(v => `  - ${v}`).join('\n') +
                            '\n  Expected: elements with long strings (>20 chars, no spaces) must have' +
                            '\n  break-words, break-all, truncate class, or overflow-wrap:break-word style.' +
                            '\n  Requirement 8.5: apply break-words where email addresses or long names may appear.'
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
