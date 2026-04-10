// Feature: mobile-responsive-ui, Property 22: Blade template tables are wrapped in overflow-x-auto

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
    router: {
        post: vi.fn(),
        reload: vi.fn(),
        get: vi.fn(),
    },
    useForm: () => ({
        salutation: '',
        lastname: '',
        firstname: '',
        contactnumber: '',
        email: '',
        role: '',
        programs: [],
        post: vi.fn(),
        delete: vi.fn(),
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
}))

// --- axios stub ---
;(window as unknown as Record<string, unknown>).axios = {
    get: vi.fn().mockResolvedValue({ data: { users: [], programs: [] } }),
    post: vi.fn().mockResolvedValue({ data: {} }),
}

// --- Layout stubs ---
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}))
vi.mock('@/Layouts/RecordStaffLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}))
vi.mock('@/Layouts/EvaluatorLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}))
vi.mock('@/Layouts/InterviewerLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}))

// --- route stub (global window + Vue globalProperties) ---
const routeStub = vi.fn(
    (name: string, params?: unknown) => `/stub-route/${name}${params ? `/${params}` : ''}`
)
;(window as unknown as Record<string, unknown>).route = routeStub

// --- Component imports (after mocks) ---
import AssignVue from '@/Pages/UserManagement/Assign.vue'
import ManageUsersVue from '@/Pages/UserManagement/ManageUsers.vue'

/**
 * Checks whether every <table> element in the mounted component has an ancestor
 * with the `overflow-x-auto` class (or equivalent inline style).
 *
 * Returns an object with `pass` (boolean) and `details` (string) for diagnostics.
 */
function allTablesHaveOverflowXAutoAncestor(wrapper: ReturnType<typeof mount>): {
    pass: boolean
    details: string
} {
    const tables = wrapper.findAll('table')

    if (tables.length === 0) {
        // No tables rendered — nothing to check (e.g., v-if hides the table)
        return { pass: true, details: 'No <table> elements rendered (v-if may be hiding them)' }
    }

    for (const table of tables) {
        // Walk up the DOM tree looking for overflow-x-auto
        let el = table.element.parentElement
        let found = false

        while (el && el !== wrapper.element.parentElement) {
            const classes = el.className ? el.className.split(' ') : []
            const inlineStyle = el.getAttribute('style') ?? ''

            if (
                classes.includes('overflow-x-auto') ||
                inlineStyle.includes('overflow-x: auto') ||
                inlineStyle.includes('overflow-x:auto')
            ) {
                found = true
                break
            }
            el = el.parentElement
        }

        if (!found) {
            // Collect ancestor class chain for diagnostics
            const ancestorChain: string[] = []
            let ancestor = table.element.parentElement
            while (ancestor && ancestor !== wrapper.element.parentElement) {
                ancestorChain.push(ancestor.className || '(no class)')
                ancestor = ancestor.parentElement
            }

            return {
                pass: false,
                details:
                    `Found <table> without an overflow-x-auto ancestor.\n` +
                    `  Table classes: "${table.classes().join(' ')}"\n` +
                    `  Ancestor class chain (innermost first):\n` +
                    ancestorChain.map(c => `    - "${c}"`).join('\n') +
                    `\n  Fix: wrap the <table> in <div class="overflow-x-auto">`,
            }
        }
    }

    return { pass: true, details: `All ${tables.length} table(s) have an overflow-x-auto ancestor` }
}

/**
 * Component entries that contain <table> elements and need overflow-x-auto wrappers.
 * These are the Vue components identified as lacking the wrapper (TDD — tests fail first).
 */
const componentEntries = [
    {
        name: 'Assign.vue',
        component: AssignVue,
        props: {
            programs: [{ id: 1, name: 'BSCS', code: 'BSCS' }],
            assignedUsers: [
                {
                    user_id: 1,
                    name: 'John Doe',
                    email: 'john@example.com',
                    role_id: 3,
                    programs: [{ name: 'BSCS' }],
                },
            ],
        },
    },
    {
        name: 'ManageUsers.vue',
        component: ManageUsersVue,
        props: {
            users: [
                {
                    id: 1,
                    firstname: 'Jane',
                    middlename: 'A',
                    lastname: 'Doe',
                    extension_name: '',
                    email: 'jane@example.com',
                    contactnumber: '09123456789',
                    role_id: 2,
                    program: { name: 'BSCS' },
                    created_at: '2024-01-01T00:00:00Z',
                },
            ],
            userCountsByRole: { 1: 1, 2: 1, 3: 0, 4: 0 },
        },
    },
]

describe('Property 22: Vue component tables are wrapped in overflow-x-auto', () => {
    it('should hold for all Vue components with tables — every <table> must have an overflow-x-auto ancestor (100 iterations)', () => {
        // Validates: Requirements 10.3, 11.2
        fc.assert(
            fc.property(
                fc.constantFrom(...componentEntries),
                ({ name, component, props }) => {
                    const wrapper = mount(component, {
                        props,
                        global: {
                            mocks: {
                                route: routeStub,
                            },
                            stubs: {
                                Head: { template: '<div />' },
                                Link: { template: '<a><slot /></a>' },
                                FontAwesomeIcon: { template: '<span />' },
                                AppLayout: { template: '<div><slot /></div>' },
                                RecordStaffLayout: { template: '<div><slot /></div>' },
                                EvaluatorLayout: { template: '<div><slot /></div>' },
                                InterviewerLayout: { template: '<div><slot /></div>' },
                            },
                        },
                    })

                    const { pass, details } = allTablesHaveOverflowXAutoAncestor(wrapper)

                    wrapper.unmount()

                    if (!pass) {
                        throw new Error(
                            `[${name}] Table overflow-x-auto wrapper missing.\n` +
                            `  ${details}`
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
