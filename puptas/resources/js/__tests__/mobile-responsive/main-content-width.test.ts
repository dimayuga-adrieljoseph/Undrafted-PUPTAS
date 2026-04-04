// Feature: mobile-responsive-ui, Property 1: Main content has no fixed pixel width on mobile

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
 * Returns true if the main content wrapper has no fixed pixel width on mobile.
 * Passes when:
 *   - The element has NO inline `style` attribute with `width: Xpx`
 *   - The element carries `flex-1` class (fills available width in a flex container)
 */
function mainContentHasNoFixedPixelWidth(wrapper: ReturnType<typeof mount>): boolean {
    const mainArea = wrapper.find('.flex-1.flex.flex-col')
    if (!mainArea.exists()) return false

    const classes = mainArea.classes()
    const inlineStyle = mainArea.attributes('style') ?? ''

    // Check 1: must have flex-1 class (implies full width in a flex container)
    if (!classes.includes('flex-1')) return false

    // Check 2: must NOT have an inline width with a fixed pixel value
    const hasFixedPixelWidth = /width\s*:\s*[\d.]+px/.test(inlineStyle)
    return !hasFixedPixelWidth
}

describe('Property 1: Main content has no fixed pixel width on mobile', () => {
    it('should hold for all layout components across mobile viewport widths (100 iterations)', () => {
        // Validates: Requirements 1.1
        fc.assert(
            fc.property(
                fc.constantFrom(...layoutComponents),
                fc.integer({ min: 320, max: 1023 }),
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

                    const result = mainContentHasNoFixedPixelWidth(wrapper)

                    if (!result) {
                        const mainArea = wrapper.find('.flex-1.flex.flex-col')
                        const style = mainArea.exists() ? mainArea.attributes('style') : 'element not found'
                        const classes = mainArea.exists() ? mainArea.classes().join(' ') : ''
                        throw new Error(
                            `[${name}] Main content area has a fixed pixel width on mobile.\n` +
                            `  inline style: "${style}"\n` +
                            `  classes: "${classes}"\n` +
                            `  Expected: flex-1 class AND no inline width: Xpx style`
                        )
                    }

                    return result
                }
            ),
            { numRuns: 100 }
        )
    })
})
