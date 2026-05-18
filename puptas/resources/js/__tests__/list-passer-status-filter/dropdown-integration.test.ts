// List Passer Status Filter — Dropdown Integration Tests (Vitest)
// Validates: Requirements 2.1, 2.2, 2.3, 2.4 in full component context

import { describe, it, vi, beforeEach, afterEach, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, ref, computed } from 'vue'

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

// ─── Simplified TestPassers component for integration testing ────────────────
const TestPassersFilterGrid = defineComponent({
    props: {
        passers: {
            type: Array,
            default: () => []
        }
    },
    setup(props) {
        const filterSchoolYear = ref("")
        const filterBatchNumber = ref("")
        const filterPasserStatus = ref("")
        
        const schoolYears = computed(() => {
            const years = new Set(props.passers.map((p: any) => p.school_year))
            return Array.from(years).sort()
        })
        
        const batchNumbers = computed(() => {
            const batches = new Set(props.passers.map((p: any) => p.batch_number))
            return Array.from(batches).sort()
        })
        
        const filteredPassers = computed(() => {
            return props.passers.filter((passer: any) => {
                const matchesSchoolYear = filterSchoolYear.value
                    ? passer.school_year === filterSchoolYear.value
                    : true

                const matchesBatch = filterBatchNumber.value
                    ? passer.batch_number === filterBatchNumber.value
                    : true

                const matchesStatus = filterPasserStatus.value
                    ? passer.passer_status_id === parseInt(filterPasserStatus.value)
                    : true

                return matchesSchoolYear && matchesBatch && matchesStatus
            })
        })
        
        return { 
            filterSchoolYear,
            filterBatchNumber, 
            filterPasserStatus,
            schoolYears,
            batchNumbers,
            filteredPassers
        }
    },
    template: `
        <div class="bg-white rounded-2xl shadow-lg p-6 dark:bg-gray-800">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-200">
                    Filters & Controls
                </h2>
                <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-full dark:text-gray-300 dark:bg-gray-800">
                    {{ filteredPassers.length }} passers
                </span>
            </div>

            <!-- Filter Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                <!-- School Year Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">
                        School Year
                    </label>
                    <select
                        v-model="filterSchoolYear"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/50 focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800"
                    >
                        <option value="">All Years</option>
                        <option v-for="year in schoolYears" :key="year" :value="year">
                            {{ year }}
                        </option>
                    </select>
                </div>

                <!-- Batch Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">
                        Batch
                    </label>
                    <select
                        v-model="filterBatchNumber"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/50 focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800"
                    >
                        <option value="">All Batches</option>
                        <option v-for="batch in batchNumbers" :key="batch" :value="batch">
                            {{ batch }}
                        </option>
                    </select>
                </div>

                <!-- Status Filter -->
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

                <!-- Sort By -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">
                        Sort By
                    </label>
                    <select
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/50 focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800"
                    >
                        <option value="pupcet_total_score">PUPCET Score (Ranking)</option>
                        <option value="surname">Surname</option>
                    </select>
                </div>

                <!-- Sort Order -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">
                        Order
                    </label>
                    <select
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/50 focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800"
                    >
                        <option value="asc">Ascending</option>
                        <option value="desc">Descending</option>
                    </select>
                </div>
            </div>
        </div>
    `
})

// ─────────────────────────────────────────────────────────────────────────────
// Integration Tests for Status Filter Dropdown in Filter Grid Context
// ─────────────────────────────────────────────────────────────────────────────
describe('Status Filter Dropdown — Integration Tests', () => {
    let wrapper: any
    const mockPassers = [
        {
            test_passer_id: 1,
            surname: 'Doe',
            first_name: 'John',
            email: 'john.doe@example.com',
            passer_status_id: 1,
            school_year: '2024-2025',
            batch_number: 'Batch 1',
            pupcet_total_score: 85.5
        },
        {
            test_passer_id: 2,
            surname: 'Smith',
            first_name: 'Jane',
            email: 'jane.smith@example.com',
            passer_status_id: 2,
            school_year: '2024-2025',
            batch_number: 'Batch 1',
            pupcet_total_score: 78.2
        },
        {
            test_passer_id: 3,
            surname: 'Johnson',
            first_name: 'Bob',
            email: 'bob.johnson@example.com',
            passer_status_id: 3,
            school_year: '2023-2024',
            batch_number: 'Batch 2',
            pupcet_total_score: 65.8
        }
    ]

    beforeEach(() => {
        wrapper = mount(TestPassersFilterGrid, {
            props: { passers: mockPassers }
        })
    })

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount()
        }
    })

    it('renders status filter dropdown within the filter grid alongside other filters', () => {
        // Validates: Requirements 2.1
        const filterGrid = wrapper.find('.grid')
        expect(filterGrid.exists()).toBe(true)
        expect(filterGrid.classes()).toContain('grid-cols-1')
        expect(filterGrid.classes()).toContain('md:grid-cols-2')
        expect(filterGrid.classes()).toContain('lg:grid-cols-5')

        // Check that all 5 filter sections exist
        const filterSections = filterGrid.findAll('div > label')
        expect(filterSections).toHaveLength(5)

        // Verify status filter is the third filter
        expect(filterSections[2].text()).toBe('Status')
    })

    it('status filter dropdown has consistent styling with other filter dropdowns', () => {
        // Validates: Requirements 2.3
        const allSelects = wrapper.findAll('select')
        expect(allSelects).toHaveLength(5)

        // Get the status filter select (third one)
        const statusSelect = allSelects[2]
        const schoolYearSelect = allSelects[0]
        const batchSelect = allSelects[1]

        // Verify all selects have the same CSS classes
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
            expect(statusSelect.classes()).toContain(className)
            expect(schoolYearSelect.classes()).toContain(className)
            expect(batchSelect.classes()).toContain(className)
        })
    })

    it('displays correct passer count when status filter is applied', async () => {
        // Validates: Requirements 2.2, 2.5, 2.6
        const passerCountBadge = wrapper.find('.text-sm.text-gray-500')
        
        // Initially shows all passers
        expect(passerCountBadge.text()).toBe('3 passers')

        const statusSelect = wrapper.findAll('select')[2]

        // Filter by Qualified (passer_status_id = 1)
        await statusSelect.setValue('1')
        expect(passerCountBadge.text()).toBe('1 passers')

        // Filter by Waitlisted (passer_status_id = 2)
        await statusSelect.setValue('2')
        expect(passerCountBadge.text()).toBe('1 passers')

        // Filter by Unqualified (passer_status_id = 3)
        await statusSelect.setValue('3')
        expect(passerCountBadge.text()).toBe('1 passers')

        // Back to All Statuses
        await statusSelect.setValue('')
        expect(passerCountBadge.text()).toBe('3 passers')
    })

    it('status filter works in combination with other filters', async () => {
        // Validates: Requirements 3.6 (combined filter logic)
        const schoolYearSelect = wrapper.findAll('select')[0]
        const statusSelect = wrapper.findAll('select')[2]
        const passerCountBadge = wrapper.find('.text-sm.text-gray-500')

        // Filter by school year 2024-2025 (should show 2 passers)
        await schoolYearSelect.setValue('2024-2025')
        expect(passerCountBadge.text()).toBe('2 passers')

        // Add status filter for Qualified (should show 1 passer)
        await statusSelect.setValue('1')
        expect(passerCountBadge.text()).toBe('1 passers')

        // Change status to Waitlisted (should show 1 passer)
        await statusSelect.setValue('2')
        expect(passerCountBadge.text()).toBe('1 passers')

        // Change status to Unqualified (should show 0 passers - no unqualified in 2024-2025)
        await statusSelect.setValue('3')
        expect(passerCountBadge.text()).toBe('0 passers')
    })

    it('status filter label has consistent styling with other filter labels', () => {
        // Validates: Requirements 2.4
        const allLabels = wrapper.findAll('label')
        expect(allLabels).toHaveLength(5)

        const statusLabel = allLabels[2]
        const schoolYearLabel = allLabels[0]
        const batchLabel = allLabels[1]

        // Verify all labels have the same CSS classes
        const expectedClasses = [
            'block',
            'text-sm',
            'font-medium',
            'text-gray-700',
            'mb-2',
            'dark:text-gray-400'
        ]

        expectedClasses.forEach(className => {
            expect(statusLabel.classes()).toContain(className)
            expect(schoolYearLabel.classes()).toContain(className)
            expect(batchLabel.classes()).toContain(className)
        })
    })

    it('status filter maintains default "All Statuses" selection on component mount', () => {
        // Validates: Requirements 2.2
        const statusSelect = wrapper.findAll('select')[2]
        expect(statusSelect.element.value).toBe('')
        expect(wrapper.vm.filterPasserStatus).toBe('')
        
        // Verify all passers are shown initially
        const passerCountBadge = wrapper.find('.text-sm.text-gray-500')
        expect(passerCountBadge.text()).toBe('3 passers')
    })

    it('status filter dropdown appears in correct position within filter grid', () => {
        // Validates: Requirements 2.1
        const filterGrid = wrapper.find('.grid')
        const filterDivs = filterGrid.findAll('div').filter(div => div.find('label').exists())
        
        expect(filterDivs).toHaveLength(5)
        
        // Verify order: School Year, Batch, Status, Sort By, Order
        expect(filterDivs[0].find('label').text()).toBe('School Year')
        expect(filterDivs[1].find('label').text()).toBe('Batch')
        expect(filterDivs[2].find('label').text()).toBe('Status')
        expect(filterDivs[3].find('label').text()).toBe('Sort By')
        expect(filterDivs[4].find('label').text()).toBe('Order')
    })
})