// Feature: mobile-responsive-ui, Property 6: Body scroll is locked when sidebar is open on mobile

import { describe, it, vi, afterEach } from 'vitest'
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

afterEach(() => {
    // Clean up any overflow-hidden left on body between test runs
    document.body.classList.remove('overflow-hidden')
})

describe('Property 6: Body scroll is locked when sidebar is open on mobile', () => {
    it('should lock body scroll when sidebar is open and unlock when closed (100 iterations)', async () => {
        // Validates: Requirements 3.4
        await fc.assert(
            fc.asyncProperty(
                fc.constantFrom(...layoutComponents),
                async ({ name, component }) => {
                    // Ensure body starts clean
                    document.body.classList.remove('overflow-hidden')

                    const wrapper = mount(component, {
                        attachTo: document.body,
                        global: {
                            stubs: {
                                Sidebar: { template: '<div class="sidebar-stub" />' },
                                Footer: { template: '<footer />' },
                                TermsandConditionsModal: { template: '<div />' },
                                FontAwesomeIcon: { template: '<span />' },
                            },
                        },
                    })

                    // --- Step 1: Open the sidebar via the hamburger button ---
                    const hamburger = wrapper.find('button[aria-label="Open navigation menu"]')

                    if (!hamburger.exists()) {
                        wrapper.unmount()
                        throw new Error(
                            `[${name}] No hamburger button found (aria-label="Open navigation menu").\n` +
                            `  The layout must render a hamburger button to test scroll lock.`
                        )
                    }

                    await hamburger.trigger('click')
                    await wrapper.vm.$nextTick()

                    // --- Step 2: Assert body has overflow-hidden after opening ---
                    const lockedAfterOpen = document.body.classList.contains('overflow-hidden')

                    if (!lockedAfterOpen) {
                        wrapper.unmount()
                        document.body.classList.remove('overflow-hidden')
                        throw new Error(
                            `[${name}] Body does NOT have 'overflow-hidden' after hamburger click.\n` +
                            `  Expected: document.body.classList.contains('overflow-hidden') === true\n` +
                            `  Actual body classes: "${document.body.className}"\n` +
                            `  The layout's watchEffect must add overflow-hidden when isMobileSidebarOpen is true.`
                        )
                    }

                    // --- Step 3: Close the sidebar via the backdrop or close button ---
                    // Try backdrop first (a div with bg-black/50 or similar), then close button
                    const backdrop = wrapper.find('[aria-label="Close navigation menu"]')
                        || wrapper.find('.bg-black\\/50')

                    if (backdrop && backdrop.exists()) {
                        await backdrop.trigger('click')
                    } else {
                        // Fallback: directly set isMobileSidebarOpen to false via vm
                        const vm = wrapper.vm as Record<string, unknown>
                        if (typeof vm.isMobileSidebarOpen !== 'undefined') {
                            (vm as { isMobileSidebarOpen: boolean }).isMobileSidebarOpen = false
                        }
                    }

                    await wrapper.vm.$nextTick()

                    // --- Step 4: Assert body no longer has overflow-hidden after closing ---
                    const unlockedAfterClose = !document.body.classList.contains('overflow-hidden')

                    wrapper.unmount()
                    document.body.classList.remove('overflow-hidden')

                    if (!unlockedAfterClose) {
                        throw new Error(
                            `[${name}] Body STILL has 'overflow-hidden' after sidebar was closed.\n` +
                            `  Expected: document.body.classList.contains('overflow-hidden') === false\n` +
                            `  Actual body classes: "${document.body.className}"\n` +
                            `  The layout's watchEffect must remove overflow-hidden when isMobileSidebarOpen is false.`
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
