// Feature: mobile-responsive-ui, Property 7: Hamburger button meets touch target size

import { describe, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import * as fc from 'fast-check'

// --- Inertia stubs ---
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: { user: { firstname: 'Test', lastname: 'User' } },
            privacy_consent: { required: false },
        },
    }),
    router: {
        post: vi.fn(),
        reload: vi.fn(),
    },
    Link: { template: '<a><slot /></a>' },
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

// --- Component stubs ---
vi.mock('@/Components/Sidebar.vue', () => ({
    default: { template: '<div class="sidebar-stub" />' },
}))
vi.mock('@/Components/Footer.vue', () => ({
    default: { template: '<footer class="footer-stub" />' },
}))
vi.mock('@/Pages/Modal/TermsandConditionsModal.vue', () => ({
    default: { template: '<div />' },
}))
vi.mock('@/Composables/useGlobalLoading', () => ({
    useGlobalLoading: () => ({ isLoading: { value: false } }),
}))

// --- Layout imports (after mocks) ---
import AppLayout from '@/Layouts/AppLayout.vue'
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue'
import EvaluatorLayout from '@/Layouts/EvaluatorLayout.vue'
import InterviewerLayout from '@/Layouts/InterviewerLayout.vue'
import RecordStaffLayout from '@/Layouts/RecordStaffLayout.vue'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const layoutComponents = [
    { name: 'AppLayout', component: AppLayout },
    { name: 'ApplicantLayout', component: ApplicantLayout },
    { name: 'EvaluatorLayout', component: EvaluatorLayout },
    { name: 'InterviewerLayout', component: InterviewerLayout },
    { name: 'RecordStaffLayout', component: RecordStaffLayout },
    { name: 'SuperAdminLayout', component: SuperAdminLayout },
]

/**
 * Checks that the hamburger button with aria-label="Open navigation menu"
 * carries both `min-h-[44px]` and `min-w-[44px]` Tailwind classes.
 *
 * Since jsdom does not compute actual pixel dimensions, we verify CLASS presence.
 * These Tailwind classes are the mechanism that enforces the 44px minimum touch target.
 */
function hamburgerMeetsTouchTargetSize(wrapper: ReturnType<typeof mount>): boolean {
    const hamburger = wrapper.find('button[aria-label="Open navigation menu"]')

    if (!hamburger.exists()) {
        return false
    }

    const classes = hamburger.classes()
    return classes.includes('min-h-[44px]') && classes.includes('min-w-[44px]')
}

describe('Property 7: Hamburger button meets touch target size', () => {
    it('should hold for all layout components — hamburger button has min-h-[44px] and min-w-[44px] classes (100 iterations)', () => {
        // Validates: Requirements 2.5
        fc.assert(
            fc.property(
                fc.constantFrom(...layoutComponents),
                ({ name, component }) => {
                    const wrapper = mount(component, {
                        global: {
                            stubs: {
                                Sidebar: { template: '<div class="sidebar-stub" />' },
                                Footer: { template: '<footer />' },
                                TermsandConditionsModal: { template: '<div />' },
                                FontAwesomeIcon: { template: '<span />' },
                            },
                        },
                    })

                    const result = hamburgerMeetsTouchTargetSize(wrapper)

                    if (!result) {
                        const hamburger = wrapper.find('button[aria-label="Open navigation menu"]')
                        if (!hamburger.exists()) {
                            throw new Error(
                                `[${name}] No button with aria-label="Open navigation menu" found.\n` +
                                `  Expected: a hamburger button with classes "min-h-[44px]" and "min-w-[44px]"`
                            )
                        }
                        const classes = hamburger.classes().join(' ')
                        throw new Error(
                            `[${name}] Hamburger button found but missing touch target size classes.\n` +
                            `  classes: "${classes}"\n` +
                            `  Expected: both "min-h-[44px]" and "min-w-[44px]" classes to enforce 44px minimum touch target`
                        )
                    }

                    return result
                }
            ),
            { numRuns: 100 }
        )
    })
})
