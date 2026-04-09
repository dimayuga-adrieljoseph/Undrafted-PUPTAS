// Feature: mobile-responsive-ui, Property 4: Hamburger button visibility is breakpoint-gated

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
 * Checks that a hamburger button with aria-label="Open navigation menu" exists
 * and carries the `md:hidden` Tailwind class (the breakpoint-gating mechanism).
 *
 * Since jsdom does not apply CSS media queries, we verify the CLASS presence
 * rather than computed visibility. The `md:hidden` class is the Tailwind
 * mechanism that hides the button on viewports >= 768px.
 */
function hamburgerButtonIsBreakpointGated(wrapper: ReturnType<typeof mount>): boolean {
    const hamburger = wrapper.find('button[aria-label="Open navigation menu"]')

    if (!hamburger.exists()) {
        return false
    }

    return hamburger.classes().includes('md:hidden')
}

describe('Property 4: Hamburger button visibility is breakpoint-gated', () => {
    it('should hold for all layout components — hamburger button exists with md:hidden class (100 iterations)', () => {
        // Validates: Requirements 2.1, 2.6
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

                    const result = hamburgerButtonIsBreakpointGated(wrapper)

                    if (!result) {
                        const hamburger = wrapper.find('button[aria-label="Open navigation menu"]')
                        if (!hamburger.exists()) {
                            throw new Error(
                                `[${name}] No button with aria-label="Open navigation menu" found.\n` +
                                `  Expected: a hamburger button with aria-label="Open navigation menu" and class "md:hidden"`
                            )
                        }
                        throw new Error(
                            `[${name}] Hamburger button found but missing "md:hidden" class.\n` +
                            `  classes: "${hamburger.classes().join(' ')}"\n` +
                            `  Expected: "md:hidden" class to gate visibility at the 768px breakpoint`
                        )
                    }

                    return result
                }
            ),
            { numRuns: 100 }
        )
    })
})
