// Feature: mobile-responsive-ui, Property 26: backdrop-blur elements have solid fallback background

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
    faTachometerAlt: {},
    faUsers: {},
    faCaretDown: {},
    faCaretRight: {},
    faCog: {},
    faGraduationCap: {},
    faPencilAlt: {},
    faEnvelopeOpenText: {},
    faCalendarCheck: {},
    faUserGroup: {},
    faSignOutAlt: {},
    faUpload: {},
    faList: {},
    faWrench: {},
    faUserShield: {},
    faHome: {},
    faUserCircle: {},
    faHistory: {},
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
vi.mock('@/Composables/useGlobalLoading', () => ({
    useGlobalLoading: () => ({ isLoading: { value: false } }),
}))

// --- axios stub ---
;(window as unknown as Record<string, unknown>).axios = {
    get: vi.fn().mockResolvedValue({ data: {} }),
    post: vi.fn().mockResolvedValue({ data: {} }),
}

// --- route stub ---
const routeStub = vi.fn((name?: string) => (name ? `/stub/${name}` : { current: () => false }))
;(window as unknown as Record<string, unknown>).route = routeStub

// --- Layout imports (after mocks) ---
import AppLayout from '@/Layouts/AppLayout.vue'
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue'
import EvaluatorLayout from '@/Layouts/EvaluatorLayout.vue'
import InterviewerLayout from '@/Layouts/InterviewerLayout.vue'
import RecordStaffLayout from '@/Layouts/RecordStaffLayout.vue'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

// --- Auth page import ---
import Register from '@/Pages/Auth/Register.vue'

const layoutStubs = {
    Sidebar: { template: '<div class="sidebar-stub" />' },
    Footer: { template: '<footer />' },
    TermsandConditionsModal: { template: '<div />' },
    FontAwesomeIcon: { template: '<span />' },
}

const componentEntries = [
    {
        name: 'AppLayout',
        component: AppLayout,
        props: {},
        stubs: layoutStubs,
    },
    {
        name: 'ApplicantLayout',
        component: ApplicantLayout,
        props: {},
        stubs: layoutStubs,
    },
    {
        name: 'EvaluatorLayout',
        component: EvaluatorLayout,
        props: {},
        stubs: layoutStubs,
    },
    {
        name: 'InterviewerLayout',
        component: InterviewerLayout,
        props: {},
        stubs: layoutStubs,
    },
    {
        name: 'RecordStaffLayout',
        component: RecordStaffLayout,
        props: {},
        stubs: layoutStubs,
    },
    {
        name: 'SuperAdminLayout',
        component: SuperAdminLayout,
        props: {},
        stubs: layoutStubs,
    },
    {
        name: 'Register',
        component: Register,
        props: {},
        stubs: {
            FontAwesomeIcon: { template: '<span />' },
            Head: { template: '<div />' },
            Link: { template: '<a><slot /></a>' },
        },
    },
]

/**
 * Returns true if the given class list contains a backdrop-blur Tailwind class.
 * Matches: backdrop-blur, backdrop-blur-sm, backdrop-blur-md, backdrop-blur-lg,
 * backdrop-blur-xl, backdrop-blur-2xl, backdrop-blur-3xl, backdrop-blur-[*]
 */
function hasBackdropBlur(classes: string[]): boolean {
    return classes.some(c => /^backdrop-blur(-\S+)?$/.test(c))
}

/**
 * Returns true if the given class list contains a solid background color class
 * that provides a fallback for browsers without backdrop-filter support.
 *
 * Accepted patterns:
 *   - bg-white, bg-white/<opacity>  (e.g. bg-white/80, bg-white/40)
 *   - bg-black, bg-black/<opacity>
 *   - bg-gray-<n>, bg-gray-<n>/<opacity>
 *   - bg-<color>-<n>, bg-<color>-<n>/<opacity>  (any Tailwind color scale)
 *   - bg-<color>/<opacity>  (e.g. bg-green-50/90)
 */
function hasSolidBackgroundFallback(classes: string[]): boolean {
    return classes.some(c => /^bg-[a-z]+(-\d+)?(\/\d+)?$/.test(c))
}

/**
 * Finds all elements with a backdrop-blur class that are missing a solid
 * background color fallback class.
 *
 * Returns a list of violation descriptions (empty = all good).
 */
function findBackdropBlurWithoutFallback(wrapper: ReturnType<typeof mount>): string[] {
    const violations: string[] = []
    const allElements = wrapper.findAll('*')

    for (const el of allElements) {
        const classes = el.classes()

        if (!hasBackdropBlur(classes)) continue

        if (!hasSolidBackgroundFallback(classes)) {
            const tag = el.element.tagName.toLowerCase()
            const classStr = classes.join(' ')
            violations.push(
                `<${tag} class="${classStr}"> uses backdrop-blur but has no solid background fallback class`
            )
        }
    }

    return violations
}

describe('Property 26: backdrop-blur elements have solid fallback background', () => {
    it('should hold for all Vue components — every backdrop-blur element shall carry a solid background color class (100 iterations)', () => {
        // Validates: Requirements 12.4
        fc.assert(
            fc.property(
                fc.constantFrom(...componentEntries),
                ({ name, component, props, stubs }) => {
                    const wrapper = mount(component, {
                        props,
                        global: {
                            mocks: { route: routeStub },
                            stubs,
                        },
                    })

                    const violations = findBackdropBlurWithoutFallback(wrapper)
                    wrapper.unmount()

                    if (violations.length > 0) {
                        throw new Error(
                            `[${name}] Found ${violations.length} backdrop-blur element(s) without a solid background fallback:\n` +
                            violations.map(v => `  - ${v}`).join('\n') +
                            '\n  Expected: every element with a backdrop-blur-* class also carries a bg-* color class.' +
                            '\n  Reason: browsers that do not support backdrop-filter will show a transparent background.' +
                            '\n  Fix: add a solid background color class (e.g. bg-white/80 or bg-gray-900/80) to the element.'
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
