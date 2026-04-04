// Feature: mobile-responsive-ui, Property 5: Sidebar open/close round trip

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
function routeStub(name?: string): string | { current: (name?: string) => boolean } {
    if (name === undefined) {
        return { current: (_n?: string) => false }
    }
    return '#'
}
;(routeStub as unknown as { current: (name?: string) => boolean }).current = (_n?: string) => false
vi.stubGlobal('route', routeStub)

// --- Sidebar import (after mocks) ---
import Sidebar from '@/Components/Sidebar.vue'

const sidebarVariants = ['default', 'record', 'interviewer', 'evaluator', 'applicant']

const globalStubs = {
    stubs: {
        NavLink: { template: '<a><slot /></a>' },
        DropdownLink: { template: '<a><slot /></a>' },
        ApplicationMark: { template: '<span />' },
        FontAwesomeIcon: { template: '<span />' },
    },
    mocks: {
        route: routeStub,
    },
}

/**
 * Validates: Requirements 2.2, 2.3, 2.4, 3.1
 *
 * Property 5: Sidebar open/close round trip
 *
 * For any layout in mobile viewport, opening the sidebar (isMobileOpen: true)
 * shall show translate-x-0, and closing it (isMobileOpen: false) shall return
 * the sidebar to its hidden state (-translate-x-full).
 */
describe('Property 5: Sidebar open/close round trip', () => {
    it('open state: sidebar has translate-x-0 when isMobileOpen is true (100 iterations)', () => {
        fc.assert(
            fc.property(
                fc.constantFrom(...sidebarVariants),
                fc.integer({ min: 320, max: 767 }),
                (variant, _viewportWidth) => {
                    const wrapper = mount(Sidebar, {
                        props: { variant, isMobileOpen: true },
                        global: globalStubs,
                    })

                    const root = wrapper.find('.sidebar')
                    if (!root.exists()) {
                        throw new Error(
                            `[variant="${variant}"] No element with class "sidebar" found.\n` +
                            `  Expected: Sidebar root element to have class "sidebar"`
                        )
                    }

                    const classes = root.classes()
                    const hasTranslateOpen = classes.includes('translate-x-0')

                    if (!hasTranslateOpen) {
                        throw new Error(
                            `[variant="${variant}", viewport=${_viewportWidth}px] Sidebar is NOT in open state.\n` +
                            `  Expected: "translate-x-0" class to be present\n` +
                            `  Actual classes: "${classes.join(' ')}"`
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })

    it('closed state: sidebar has -translate-x-full when isMobileOpen is false (100 iterations)', () => {
        fc.assert(
            fc.property(
                fc.constantFrom(...sidebarVariants),
                fc.integer({ min: 320, max: 767 }),
                (variant, _viewportWidth) => {
                    const wrapper = mount(Sidebar, {
                        props: { variant, isMobileOpen: false },
                        global: globalStubs,
                    })

                    const root = wrapper.find('.sidebar')
                    if (!root.exists()) {
                        throw new Error(
                            `[variant="${variant}"] No element with class "sidebar" found.\n` +
                            `  Expected: Sidebar root element to have class "sidebar"`
                        )
                    }

                    const classes = root.classes()
                    // Design doc: when isMobileOpen is false → '-translate-x-full md:translate-x-0'
                    const hasTranslateClosed = classes.includes('-translate-x-full')

                    if (!hasTranslateClosed) {
                        throw new Error(
                            `[variant="${variant}", viewport=${_viewportWidth}px] Sidebar is NOT in closed/hidden state.\n` +
                            `  Expected: "-translate-x-full" class to be present\n` +
                            `  Actual classes: "${classes.join(' ')}"`
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
