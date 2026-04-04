// Feature: mobile-responsive-ui, Property 14: Multi-field form rows stack on mobile

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
 * Finds all multi-field row containers that lay out form fields side by side on desktop.
 * A qualifying container is any element that:
 *   - has 2 or more direct child elements that each contain at least one form field, AND
 *   - uses a multi-column layout class (grid with multiple cols, or flex-row / flex without flex-col)
 *     indicating it is intended as a side-by-side row rather than a vertical stack.
 *
 * The property requires such containers to carry `flex-col` as the base
 * flex direction and `md:flex-row` for the desktop breakpoint.
 *
 * Returns an object with `pass` (boolean) and `details` (string).
 */
function multiFieldRowsHaveStackingClasses(wrapper: ReturnType<typeof mount>): {
    pass: boolean
    details: string
} {
    const allElements = wrapper.findAll('*')

    const multiFieldContainers: Array<{ el: Element; classes: string[] }> = []

    // Regex to detect multi-column grid classes like grid-cols-2, grid-cols-3, md:grid-cols-2, etc.
    // Tests individual class tokens, not the joined string.
    const isMultiColGridClass = (cls: string) => /^(?:(?:sm|md|lg|xl):)?grid-cols-[2-9]/.test(cls)

    for (const vueWrapper of allElements) {
        const el = vueWrapper.element as Element
        const classes = Array.from(el.classList)

        const directChildren = Array.from(el.children)

        // Count direct children that contain at least one form field
        const childrenWithFields = directChildren.filter(child =>
            child.querySelector('input, select, textarea') !== null
        )

        if (childrenWithFields.length < 2) continue

        // Only flag containers that are explicitly using a multi-column or row layout:
        // - grid with multiple columns (grid-cols-2, grid-cols-3, md:grid-cols-2, etc.)
        // - flex-row (explicit row direction)
        // - flex without flex-col (implicit row direction in CSS flex)
        const isMultiColGrid = classes.some(isMultiColGridClass)
        const isFlexRow = classes.includes('flex-row') || classes.some(c => /^(?:sm|md|lg|xl):flex-row$/.test(c))
        const isFlex = classes.includes('flex')
        const isFlexCol = classes.includes('flex-col')

        // A flex container without flex-col is a row layout (flex default is row)
        const isImplicitFlexRow = isFlex && !isFlexCol

        if (isMultiColGrid || isFlexRow || isImplicitFlexRow) {
            multiFieldContainers.push({ el, classes })
        }
    }

    if (multiFieldContainers.length === 0) {
        return {
            pass: false,
            details: 'No multi-field containers (with 2+ child groups each containing a form field) were found in the component.',
        }
    }

    const failing: string[] = []

    for (const { el, classes } of multiFieldContainers) {
        const hasFlexCol = classes.includes('flex-col')
        const hasMdFlexRow = classes.includes('md:flex-row')

        if (!hasFlexCol || !hasMdFlexRow) {
            const tag = el.tagName.toLowerCase()
            const id = el.getAttribute('id') ?? ''
            const classStr = classes.join(' ') || '(no classes)'
            const missing: string[] = []
            if (!hasFlexCol) missing.push("'flex-col'")
            if (!hasMdFlexRow) missing.push("'md:flex-row'")
            failing.push(
                `<${tag}${id ? ` id="${id}"` : ''}> classes: "${classStr}" — missing: ${missing.join(', ')}`
            )
        }
    }

    if (failing.length > 0) {
        return {
            pass: false,
            details:
                `${failing.length} of ${multiFieldContainers.length} multi-field container(s) are missing required stacking classes:\n` +
                failing.map(f => `  - ${f}`).join('\n'),
        }
    }

    return {
        pass: true,
        details: `All ${multiFieldContainers.length} multi-field container(s) have 'flex-col' and 'md:flex-row'.`,
    }
}

describe('Property 14: Multi-field form rows stack on mobile', () => {
    it('should hold for all form components — every multi-field row container must have flex-col and md:flex-row (100 iterations)', () => {
        // Validates: Requirements 6.1, 6.3
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

                    const { pass, details } = multiFieldRowsHaveStackingClasses(wrapper)

                    wrapper.unmount()

                    if (!pass) {
                        throw new Error(
                            `[${name}] Multi-field row containers are missing responsive stacking classes.\n` +
                            `  ${details}\n` +
                            `  Expected: every container with 2+ field groups to have 'flex-col' and 'md:flex-row'\n` +
                            `  Fix: add 'flex flex-col md:flex-row gap-4' to multi-field row containers in ${name}.`
                        )
                    }

                    return true
                }
            ),
            { numRuns: 100 }
        )
    })
})
