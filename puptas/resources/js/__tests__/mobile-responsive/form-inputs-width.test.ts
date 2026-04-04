// Feature: mobile-responsive-ui, Property 13: Form inputs fill container width

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
    useForm: (initial: Record<string, unknown>) => {
        const form = { ...initial, errors: {}, processing: false }
        return new Proxy(form, {
            get(target, prop) {
                if (prop === 'put' || prop === 'post' || prop === 'patch' || prop === 'delete') {
                    return vi.fn()
                }
                return (target as Record<string | symbol, unknown>)[prop]
            },
            set(target, prop, value) {
                ;(target as Record<string | symbol, unknown>)[prop] = value
                return true
            },
        })
    },
    router: {
        post: vi.fn(),
        put: vi.fn(),
        reload: vi.fn(),
    },
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
    faArrowLeft: {},
    faSave: {},
    faTimes: {},
}))

// --- Layout stubs ---
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
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

// --- Form component imports (after mocks) ---
import ScheduleForm from '@/Components/ScheduleForm.vue'
import AddUser from '@/Pages/UserManagement/AddUser.vue'
import EditUser from '@/Pages/UserManagement/EditUser.vue'

const scheduleFormEntry = {
    name: 'ScheduleForm',
    component: ScheduleForm,
    props: {},
}

const addUserEntry = {
    name: 'AddUser (UserManagement)',
    component: AddUser,
    props: {
        programs: [{ id: 1, code: 'BSCS', name: 'BS Computer Science' }],
        totalUsers: 5,
        userCountsByRole: { 1: 2, 2: 1 },
        roles: { 1: 'Applicant', 2: 'Admin', 3: 'Evaluator', 4: 'Interviewer', 5: 'Medical Staff', 6: 'Registrar' },
    },
}

const editUserEntry = {
    name: 'EditUser (UserManagement)',
    component: EditUser,
    props: {
        user: {
            id: 1,
            firstname: 'John',
            lastname: 'Doe',
            middlename: '',
            extension_name: '',
            email: 'john@gmail.com',
            contactnumber: '9123456789',
            role_id: '2',
            programs: [],
        },
        programs: [{ id: 1, code: 'BSCS', name: 'BS Computer Science' }],
        roles: { 1: 'Applicant', 2: 'Admin', 3: 'Evaluator', 4: 'Interviewer', 5: 'Medical Staff', 6: 'Registrar' },
        userCountsByRole: { 1: 2, 2: 1 },
        totalUsers: 5,
    },
}

const formEntries = [scheduleFormEntry, addUserEntry, editUserEntry]

/**
 * Checks that every <input>, <select>, and <textarea> in the mounted component
 * carries the `w-full` Tailwind class.
 *
 * Returns an object with `pass` (boolean) and `details` (string) for diagnostics.
 */
function allFormInputsHaveWFull(wrapper: ReturnType<typeof mount>): {
    pass: boolean
    details: string
} {
    const inputs = wrapper.findAll('input, select, textarea')

    if (inputs.length === 0) {
        return { pass: false, details: 'No input, select, or textarea elements found in the component.' }
    }

    const failing: string[] = []

    for (const el of inputs) {
        const classes = el.classes()
        if (!classes.includes('w-full')) {
            const tag = el.element.tagName.toLowerCase()
            const id = el.attributes('id') ?? ''
            const type = el.attributes('type') ?? ''
            const classStr = classes.join(' ') || '(no classes)'
            failing.push(`<${tag}${id ? ` id="${id}"` : ''}${type ? ` type="${type}"` : ''}> classes: "${classStr}"`)
        }
    }

    if (failing.length > 0) {
        return {
            pass: false,
            details:
                `${failing.length} of ${inputs.length} form element(s) are missing the 'w-full' class:\n` +
                failing.map(f => `  - ${f}`).join('\n'),
        }
    }

    return { pass: true, details: `All ${inputs.length} form element(s) have 'w-full'.` }
}

describe('Property 13: Form inputs fill container width', () => {
    it('should hold for all form components — every input/select/textarea must have w-full (100 iterations)', () => {
        // Validates: Requirements 5.5, 6.2
        fc.assert(
            fc.property(
                fc.constantFrom(...formEntries),
                ({ name, component, props }) => {
                    const wrapper = mount(component, {
                        props,
                        global: {
                            stubs: {
                                FontAwesomeIcon: { template: '<span />' },
                                AppLayout: { template: '<div><slot /></div>' },
                                Link: { template: '<a><slot /></a>' },
                                Head: { template: '<div />' },
                            },
                            mocks: {
                                route: routeStub,
                            },
                        },
                    })

                    const { pass, details } = allFormInputsHaveWFull(wrapper)

                    wrapper.unmount()

                    if (!pass) {
                        throw new Error(
                            `[${name}] Not all form inputs carry the 'w-full' class.\n` +
                            `  ${details}\n` +
                            `  Expected: every <input>, <select>, <textarea> to have 'w-full'\n` +
                            `  Fix: add 'w-full' to all form input elements in ${name}.`
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
