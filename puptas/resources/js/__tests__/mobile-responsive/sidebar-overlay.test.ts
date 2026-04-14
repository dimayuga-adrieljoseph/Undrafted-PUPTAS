// Feature: mobile-responsive-ui, Property 3: Sidebar is an overlay on mobile

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
    faMoon: {},
    faSun: {},
    faSignOutAlt: {},
    faUpload: {},
    faList: {},
    faWrench: {},
    faUserShield: {},
    faHome: {},
    faUserCircle: {},
    faHistory: {},
}))

// --- Sub-component stubs ---
vi.mock('@/Components/NavLink.vue', () => ({
    default: { template: '<a><slot /></a>' },
}))
vi.mock('@/Components/DropdownLink.vue', () => ({
    default: { template: '<a><slot /></a>' },
}))
vi.mock('@/Components/ApplicationMark.vue', () => ({
    default: { template: '<span />' },
}))

// --- Global `route` stub (Ziggy) ---
// Sidebar.vue uses route two ways:
//   1. route('name') → returns a URL string (for :href bindings)
//   2. route().current('name') → returns boolean (for active route checks)
// The stub handles both: when called with no args, returns an object with .current();
// when called with a name, returns '#'.
function routeStub(name?: string): string | { current: (name?: string) => boolean } {
    if (name === undefined) {
        return { current: (_n?: string) => false }
    }
    return '#'
}
(routeStub as unknown as { current: (name?: string) => boolean }).current = (_n?: string) => false
vi.stubGlobal('route', routeStub)

// --- Sidebar import (after mocks) ---
import Sidebar from '@/Components/Sidebar.vue'

// Sidebar variants to test across
const sidebarVariants = ['default', 'record', 'interviewer', 'evaluator', 'applicant']

/**
 * Checks that the Sidebar root element has `fixed` and `z-[9999]` classes,
 * confirming it renders as an overlay rather than a side-by-side panel.
 *
 * Since jsdom does not compute CSS, we verify CLASS presence:
 *   - `fixed`     → maps to `position: fixed`
 *   - `z-[9999]`  → maps to `z-index: 9999` (higher than main content z-40)
 */
function sidebarIsOverlay(wrapper: ReturnType<typeof mount>): { result: boolean; classes: string[] } {
    const root = wrapper.find('.sidebar')
    if (!root.exists()) {
        return { result: false, classes: [] }
    }
    const classes = root.classes()
    const hasFixed = classes.includes('fixed')
    const hasZIndex = classes.includes('z-[9999]')
    return { result: hasFixed && hasZIndex, classes }
}

describe('Property 3: Sidebar is an overlay on mobile', () => {
    it('should have fixed positioning and z-[9999] class when isMobileOpen is true (100 iterations)', () => {
        // Validates: Requirements 1.5, 3.3
        fc.assert(
            fc.property(
                fc.constantFrom(...sidebarVariants),
                fc.integer({ min: 320, max: 767 }),
                (variant, _viewportWidth) => {
                    const wrapper = mount(Sidebar, {
                        props: {
                            variant,
                            isMobileOpen: true,
                        },
                        global: {
                            stubs: {
                                NavLink: { template: '<a><slot /></a>' },
                                DropdownLink: { template: '<a><slot /></a>' },
                                ApplicationMark: { template: '<span />' },
                                FontAwesomeIcon: { template: '<span />' },
                            },
                            mocks: {
                                route: routeStub,
                            },
                        },
                    })

                    const { result, classes } = sidebarIsOverlay(wrapper)

                    if (!result) {
                        const root = wrapper.find('.sidebar')
                        if (!root.exists()) {
                            throw new Error(
                                `[variant="${variant}"] No element with class "sidebar" found on the root.\n` +
                                `  Expected: Sidebar root element to have class "sidebar"`
                            )
                        }
                        const hasFixed = classes.includes('fixed')
                        const hasZIndex = classes.includes('z-[9999]')
                        throw new Error(
                            `[variant="${variant}", viewport=${_viewportWidth}px] Sidebar is not an overlay.\n` +
                            `  has "fixed": ${hasFixed}\n` +
                            `  has "z-[9999]": ${hasZIndex}\n` +
                            `  actual classes: "${classes.join(' ')}"\n` +
                            `  Expected: "fixed" class (position: fixed) AND "z-[9999]" class (z-index > main content z-40)`
                        )
                    }

                    return result
                }
            ),
            { numRuns: 100 }
        )
    })
})
