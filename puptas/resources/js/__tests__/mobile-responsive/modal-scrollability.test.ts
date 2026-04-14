// Feature: mobile-responsive-ui, Property 24: Modal components are scrollable on mobile

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

// --- axios stub (ApplicationReviewModal uses window.axios) ---
;(window as unknown as Record<string, unknown>).axios = {
    get: vi.fn().mockResolvedValue({ data: {} }),
    post: vi.fn().mockResolvedValue({ data: {} }),
}

// --- Modal component imports (after mocks) ---
import ApplicationReviewModal from '@/Pages/Modal/ApplicationReviewModal.vue'
import AuditLogDetailsModal from '@/Pages/Modal/AuditLogDetailsModal.vue'
import AddPasserModal from '@/Pages/Modal/AddPasserModal.vue'
import EditPasserModal from '@/Pages/Modal/EditPasserModal.vue'
import TermsandConditionsModal from '@/Pages/Modal/TermsandConditionsModal.vue'

/**
 * Each entry describes a modal component and the props needed to make it visible.
 * The `contentSelector` is a CSS selector that should match the scrollable content
 * container — the element that must carry `overflow-y-auto` and `max-h-[90vh]`.
 */
const modalEntries = [
    {
        name: 'ApplicationReviewModal',
        component: ApplicationReviewModal,
        // show prop drives internal showModal ref; mount with show=true
        props: { show: true, userEmail: 'test@example.com' },
    },
    {
        name: 'AuditLogDetailsModal',
        component: AuditLogDetailsModal,
        props: { show: true, log: { id: 1, created_at: new Date().toISOString(), action_type: 'VIEW' } },
    },
    {
        name: 'AddPasserModal',
        component: AddPasserModal,
        props: { show: true, saving: false },
    },
    {
        name: 'EditPasserModal',
        component: EditPasserModal,
        props: { show: true, saving: false, passer: { surname: 'Doe', first_name: 'John' } },
    },
    {
        name: 'TermsandConditionsModal',
        component: TermsandConditionsModal,
        props: { show: true, canClose: true },
    },
]

/**
 * Checks whether the mounted modal has at least one element that carries
 * both `overflow-y-auto` and `max-h-[90vh]` Tailwind classes.
 *
 * Returns an object with `pass` (boolean) and `details` (string) for diagnostics.
 */
function modalContentIsScrollable(wrapper: ReturnType<typeof mount>): {
    pass: boolean
    details: string
} {
    // Find all elements in the rendered output
    const allElements = wrapper.findAll('*')

    for (const el of allElements) {
        const classes = el.classes()
        const hasOverflowYAuto = classes.includes('overflow-y-auto')
        const hasMaxH90vh = classes.includes('max-h-[90vh]')

        if (hasOverflowYAuto && hasMaxH90vh) {
            return { pass: true, details: `Found element with classes: ${classes.join(' ')}` }
        }
    }

    // Collect all class lists for diagnostics
    const classSummary = allElements
        .filter(el => el.classes().some(c => c.includes('overflow') || c.includes('max-h')))
        .map(el => el.classes().join(' '))
        .join('\n  ')

    return {
        pass: false,
        details: `No element found with both 'overflow-y-auto' and 'max-h-[90vh]'.\n` +
            `  Elements with overflow/max-h classes:\n  ${classSummary || '(none)'}`,
    }
}

describe('Property 24: Modal components are scrollable on mobile', () => {
    it('should hold for all modal components — content container must have overflow-y-auto and max-h-[90vh] (100 iterations)', () => {
        // Validates: Requirements 11.4
        fc.assert(
            fc.property(
                fc.constantFrom(...modalEntries),
                ({ name, component, props }) => {
                    const wrapper = mount(component, {
                        props,
                        global: {
                            stubs: {
                                FontAwesomeIcon: { template: '<span />' },
                            },
                        },
                    })

                    const { pass, details } = modalContentIsScrollable(wrapper)

                    wrapper.unmount()

                    if (!pass) {
                        throw new Error(
                            `[${name}] Modal content container is missing scrollability classes.\n` +
                            `  ${details}\n` +
                            `  Expected: an element with both 'overflow-y-auto' and 'max-h-[90vh]'\n` +
                            `  Fix: add 'overflow-y-auto max-h-[90vh]' to the modal content container.`
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
