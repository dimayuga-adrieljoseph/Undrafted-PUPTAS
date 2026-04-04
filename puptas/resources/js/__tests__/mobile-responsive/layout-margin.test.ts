// Feature: mobile-responsive-ui, Property 2: Main content left margin is zero on mobile

import { describe, it, expect, vi, beforeAll } from 'vitest'
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
 * Returns true if the main content wrapper has no fixed margin-left on mobile.
 * Passes when:
 *   - The element carries the `ml-0` Tailwind class, OR
 *   - The element has no inline `margin-left` style with a pixel/rem/var value
 */
function mainContentHasNoMarginOnMobile(wrapper: ReturnType<typeof mount>): boolean {
    // The main content area is the direct sibling of the sidebar — a flex-1 div
    const mainArea = wrapper.find('.flex-1.flex.flex-col')
    if (!mainArea.exists()) return false

    const classes = mainArea.classes()
    const inlineStyle = mainArea.attributes('style') ?? ''

    // Check 1: has ml-0 class (the expected post-implementation state)
    if (classes.includes('ml-0')) return true

    // Check 2: no inline margin-left with a fixed value (var(), px, rem)
    // A margin-left inline style with var(--sidebar-width) is the bug we're testing for
    const hasFixedMarginLeft = /margin-left\s*:\s*(var\(|[\d.]+(?:px|rem))/.test(inlineStyle)
    return !hasFixedMarginLeft
}

describe('Property 2: Main content left margin is zero on mobile', () => {
    it('should hold for all layout components across mobile viewport widths (100 iterations)', () => {
        // Validates: Requirements 1.2
        fc.assert(
            fc.property(
                fc.constantFrom(...layoutComponents),
                fc.integer({ min: 320, max: 767 }),
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

                    const result = mainContentHasNoMarginOnMobile(wrapper)

                    if (!result) {
                        const mainArea = wrapper.find('.flex-1.flex.flex-col')
                        const style = mainArea.exists() ? mainArea.attributes('style') : 'element not found'
                        const classes = mainArea.exists() ? mainArea.classes().join(' ') : ''
                        throw new Error(
                            `[${name}] Main content area has a fixed margin-left on mobile.\n` +
                            `  inline style: "${style}"\n` +
                            `  classes: "${classes}"\n` +
                            `  Expected: ml-0 class OR no inline margin-left style`
                        )
                    }

                    return result
                }
            ),
            { numRuns: 100 }
        )
    })
})
