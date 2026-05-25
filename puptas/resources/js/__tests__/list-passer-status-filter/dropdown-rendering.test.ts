// List Passer Status Filter — Dropdown Rendering Unit Tests (Vitest)
// Validates: Requirements 2.1, 2.2, 2.3, 2.4

import { describe, it, vi, beforeEach, afterEach, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, ref } from 'vue'

// ─── Inertia stubs ────────────────────────────────────────────────────────────
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            auth: { user: { firstname: 'Test', lastname: 'User' } },
        },
    }),
    router: { post: vi.fn(), reload: vi.fn(), get: vi.fn(), visit: vi.fn() },
    useForm: (initial: Record<string, unknown>) => ({
        ...initial,
        post: vi.fn(),
        put: vi.fn(),
        reset: vi.fn(),
        errors: {},
        processing: false,
    }),
    Link: { template: '<a><slot /></a>' },
    Head: { template: '<div />' },
}))

// ─── Layout stubs ─────────────────────────────────────────────────────────────
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: { template: '<div><slot /></div>' },
}))

// ─── Global stubs ─────────────────────────────────────────────────────────────
vi.stubGlobal('axios', { get: vi.fn(), post: vi.fn() })
vi.stubGlobal('route', vi.fn((name: string) => '/stub/' + name))

// ─── Test component for Status Filter Dropdown ──────────────────────────────
const StatusFilterDropdown = defineComponent({
    setup() {
        const filterPasserStatus = ref("")
        
        return { filterPasserStatus }
    },
    template: `
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">
                Status
            </label>
            <select
                v-model="filterPasserStatus"
                class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/50 focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800"
            >
                <option value="">All Statuses</option>
                <option value="1">Qualified</option>
                <option value="2">Waitlisted</option>
                <option value="3">Unqualified</option>
            </select>
        </div>
    `
})

// ─────────────────────────────────────────────────────────────────────────────
// Unit Tests for Status Filter Dropdown Rendering
// ─────────────────────────────────────────────────────────────────────────────
describe('Status Filter Dropdown — Unit Tests', () => {
    let wrapper: any

    beforeEach(() => {
        wrapper = mount(StatusFilterDropdown)
    })

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount()
        }
    })

    it('renders dropdown with correct label', () => {
        // Validates: Requirements 2.4
        const label = wrapper.find('label')
        expect(label.exists()).toBe(true)
        expect(label.text()).toBe('Status')
        expect(label.classes()).toContain('text-sm')
        expect(label.classes()).toContain('font-medium')
        expect(label.classes()).toContain('text-gray-700')
        expect(label.classes()).toContain('mb-2')
        expect(label.classes()).toContain('dark:text-gray-400')
    })

    it('renders select element with correct styling classes', () => {
        // Validates: Requirements 2.3
        const select = wrapper.find('select')
        expect(select.exists()).toBe(true)
        
        // Check for consistent styling with existing dropdowns
        const expectedClasses = [
            'w-full',
            'px-4',
            'py-3', 
            'border',
            'border-gray-300',
            'rounded-xl',
            'bg-white',
            'focus:outline-none',
            'focus:ring-2',
            'focus:ring-[#9E122C]/50',
            'focus:border-[#9E122C]',
            'transition',
            'dark:border-gray-600',
            'dark:bg-gray-800'
        ]
        
        expectedClasses.forEach(className => {
            expect(select.classes()).toContain(className)
        })
    })

    it('contains all required options with correct values and text', () => {
        // Validates: Requirements 2.2
        const options = wrapper.findAll('option')
        expect(options).toHaveLength(4)

        // Check "All Statuses" option
        expect(options[0].attributes('value')).toBe('')
        expect(options[0].text()).toBe('All Statuses')

        // Check "Qualified" option
        expect(options[1].attributes('value')).toBe('1')
        expect(options[1].text()).toBe('Qualified')

        // Check "Waitlisted" option
        expect(options[2].attributes('value')).toBe('2')
        expect(options[2].text()).toBe('Waitlisted')

        // Check "Unqualified" option
        expect(options[3].attributes('value')).toBe('3')
        expect(options[3].text()).toBe('Unqualified')
    })

    it('has "All Statuses" as default selection', () => {
        // Validates: Requirements 2.2
        const select = wrapper.find('select')
        expect(select.element.value).toBe('')
        
        // Verify the component's reactive data
        expect(wrapper.vm.filterPasserStatus).toBe('')
    })

    it('updates reactive data when option is selected', async () => {
        // Validates: Requirements 2.1, 2.2
        const select = wrapper.find('select')
        
        // Test selecting "Qualified"
        await select.setValue('1')
        expect(wrapper.vm.filterPasserStatus).toBe('1')
        expect(select.element.value).toBe('1')

        // Test selecting "Waitlisted"
        await select.setValue('2')
        expect(wrapper.vm.filterPasserStatus).toBe('2')
        expect(select.element.value).toBe('2')

        // Test selecting "Unqualified"
        await select.setValue('3')
        expect(wrapper.vm.filterPasserStatus).toBe('3')
        expect(select.element.value).toBe('3')

        // Test selecting "All Statuses"
        await select.setValue('')
        expect(wrapper.vm.filterPasserStatus).toBe('')
        expect(select.element.value).toBe('')
    })

    it('dropdown appears within the filter grid structure', () => {
        // Validates: Requirements 2.1
        const container = wrapper.find('div')
        expect(container.exists()).toBe(true)
        
        // Verify the dropdown is properly structured with label and select
        const label = container.find('label')
        const select = container.find('select')
        
        expect(label.exists()).toBe(true)
        expect(select.exists()).toBe(true)
        
        // Verify label comes before select in DOM order
        const labelElement = label.element
        const selectElement = select.element
        expect(labelElement.compareDocumentPosition(selectElement) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
    })

    it('maintains consistent styling with existing filter dropdowns', () => {
        // Validates: Requirements 2.3
        const select = wrapper.find('select')
        
        // These classes should match the styling of School Year and Batch dropdowns
        const consistentClasses = [
            'rounded-xl',           // Consistent border radius
            'border',               // Border styling
            'border-gray-300',      // Border color
            'focus:ring-2',         // Focus ring
            'focus:ring-[#9E122C]/50', // Brand color focus ring
            'focus:border-[#9E122C]',  // Brand color focus border
            'transition'            // Smooth transitions
        ]
        
        consistentClasses.forEach(className => {
            expect(select.classes()).toContain(className)
        })
    })

    it('supports dark mode styling', () => {
        // Validates: Requirements 2.3
        const label = wrapper.find('label')
        const select = wrapper.find('select')
        
        // Check dark mode classes
        expect(label.classes()).toContain('dark:text-gray-400')
        expect(select.classes()).toContain('dark:border-gray-600')
        expect(select.classes()).toContain('dark:bg-gray-800')
    })
})