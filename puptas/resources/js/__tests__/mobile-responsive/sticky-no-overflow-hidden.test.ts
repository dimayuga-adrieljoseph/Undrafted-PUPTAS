// Feature: mobile-responsive-ui, Property 25: Sticky elements have no overflow-hidden ancestors

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

// --- Modal import ---
import ApplicationReviewModal from '@/Pages/Modal/ApplicationReviewModal.vue'

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
        name: 'ApplicationReviewModal',
        component: ApplicationReviewModal,
        props: { show: true, userEmail: 'test@example.com' },
        stubs: { FontAwesomeIcon: { template: '<span />' } },
    },
    {
        name: 'Register',
        component: Register,
        props: {},
        stubs: { FontAwesomeIcon: { template: '<span />' }, Head: { template: '<div />' }, Link: { template: '<a><slot /></a>' } },
    },
]

/**
 * Returns true if the given class string contains `overflow-hidden`.
 * Checks both the Tailwind class `overflow-hidden` and inline style `overflow: hidden`.
 */
function hasOverflowHidden(el: Element): boolean {
    const classList = Array.from(el.classList)
    if (classList.includes('overflow-hidden')) {
        return true
    }
    const inlineStyle = (el as HTMLElement).style?.overflow
    if (inlineStyle === 'hidden') {
        return true
    }
    return false
}

/**
 * Walks up the ancestor chain of `el` (excluding the element itself)
 * and returns the first ancestor that has overflow-hidden, or null.
 */
function findOverflowHiddenAncestor(el: Element): Element | null {
    let current = el.parentElement
    while (current !== null) {
        if (hasOverflowHidden(current)) {
            return current
        }
        current = current.parentElement
    }
    return null
}

/**
 * Finds all elements with `position: sticky` (via Tailwind class `sticky`
 * or inline style `position: sticky`) and checks that none of their
 * ancestors have `overflow: hidden`.
 *
 * Returns a list of violation descriptions (empty = all good).
 */
function findStickyOverflowHiddenViolations(wrapper: ReturnType<typeof mount>): string[] {
    const violations: string[] = []
    const allElements = wrapper.findAll('*')

    for (const el of allElements) {
        const classes = el.classes()
        const inlinePosition = (el.element as HTMLElement).style?.position

        const isSticky =
            classes.includes('sticky') ||
            inlinePosition === 'sticky'

        if (!isSticky) continue

        const offendingAncestor = findOverflowHiddenAncestor(el.element)

        if (offendingAncestor !== null) {
            const tag = el.element.tagName.toLowerCase()
            const stickyClasses = classes.join(' ')
            const ancestorTag = offendingAncestor.tagName.toLowerCase()
            const ancestorClasses = Array.from(offendingAncestor.classList).join(' ')
            violations.push(
                `<${tag} class="${stickyClasses}"> has ancestor ` +
                `<${ancestorTag} class="${ancestorClasses}"> with overflow-hidden`
            )
        }
    }

    return violations
}

describe('Property 25: Sticky elements have no overflow-hidden ancestors', () => {
    it('should hold for all Vue components — no sticky element shall have an overflow-hidden ancestor (100 iterations)', () => {
        // Validates: Requirements 12.3
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

                    const violations = findStickyOverflowHiddenViolations(wrapper)
                    wrapper.unmount()

                    if (violations.length > 0) {
                        throw new Error(
                            `[${name}] Found ${violations.length} sticky element(s) with overflow-hidden ancestor(s):\n` +
                            violations.map(v => `  - ${v}`).join('\n') +
                            '\n  Expected: no ancestor of a sticky element has overflow: hidden.' +
                            '\n  Reason: overflow: hidden on an ancestor breaks position: sticky in iOS Safari.' +
                            '\n  Fix: remove overflow-hidden from the ancestor, or use overflow-clip / overflow-y-auto instead.'
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
