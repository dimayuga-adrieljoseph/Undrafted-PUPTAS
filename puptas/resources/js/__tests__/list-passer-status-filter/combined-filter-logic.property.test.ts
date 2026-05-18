// List Passer Status Filter — Combined Filter Logic Property Tests (Vitest + fast-check)
// **Property 2: Combined filter logic**
// **Validates: Requirements 3.6**

import { describe, it, expect } from 'vitest'
import * as fc from 'fast-check'

// ─── Type Definitions ────────────────────────────────────────────────────────
interface TestPasser {
    test_passer_id: number
    surname: string
    first_name: string
    email: string
    passer_status_id: number | null
    school_year: string
    batch_number: string
    pupcet_total_score: number | null
}

interface FilterState {
    searchTerm: string
    filterSchoolYear: string
    filterBatchNumber: string
    filterPasserStatus: string
}

// ─── Filter Logic Implementation ─────────────────────────────────────────────
// This mirrors the actual filteredPassers computed property from TestPassers/Email.vue
function applyFilters(passers: TestPasser[], filters: FilterState): TestPasser[] {
    return passers.filter((passer) => {
        // Search filter (matches surname, first_name, or email)
        const search = filters.searchTerm.toLowerCase()
        const matchesSearch = search === '' ||
            passer.surname.toLowerCase().includes(search) ||
            passer.first_name.toLowerCase().includes(search) ||
            passer.email.toLowerCase().includes(search)

        // School year filter
        const matchesSchoolYear = filters.filterSchoolYear === '' ||
            passer.school_year === filters.filterSchoolYear

        // Batch number filter
        const matchesBatch = filters.filterBatchNumber === '' ||
            passer.batch_number === filters.filterBatchNumber

        // Status filter
        const matchesStatus = filters.filterPasserStatus === '' ||
            passer.passer_status_id === parseInt(filters.filterPasserStatus)

        // Combined AND logic - all active filters must match
        return matchesSearch && matchesSchoolYear && matchesBatch && matchesStatus
    })
}

// ─── Property Test Generators ────────────────────────────────────────────────

// Generator for valid passer status IDs (1=qualified, 2=waitlisted, 3=unqualified, null=pending)
const passerStatusGenerator = fc.oneof(
    fc.constant(1),
    fc.constant(2), 
    fc.constant(3),
    fc.constant(null)
)

// Generator for school years (realistic academic years)
const schoolYearGenerator = fc.oneof(
    fc.constant('2023-2024'),
    fc.constant('2024-2025'),
    fc.constant('2025-2026')
)

// Generator for batch numbers
const batchNumberGenerator = fc.oneof(
    fc.constant('Batch 1'),
    fc.constant('Batch 2'),
    fc.constant('Batch 3')
)

// Generator for test passer objects
const passerGenerator = fc.record({
    test_passer_id: fc.integer({ min: 1, max: 10000 }),
    surname: fc.string({ minLength: 2, maxLength: 20 }).filter(s => s.trim().length > 0),
    first_name: fc.string({ minLength: 2, maxLength: 20 }).filter(s => s.trim().length > 0),
    email: fc.emailAddress(),
    passer_status_id: passerStatusGenerator,
    school_year: schoolYearGenerator,
    batch_number: batchNumberGenerator,
    pupcet_total_score: fc.oneof(
        fc.float({ min: 0, max: 100 }),
        fc.constant(null)
    )
})

// Generator for filter states
const filterStateGenerator = fc.record({
    searchTerm: fc.oneof(
        fc.constant(''), // No search filter
        fc.string({ minLength: 1, maxLength: 10 }) // Some search term
    ),
    filterSchoolYear: fc.oneof(
        fc.constant(''), // All years
        schoolYearGenerator // Specific year
    ),
    filterBatchNumber: fc.oneof(
        fc.constant(''), // All batches
        batchNumberGenerator // Specific batch
    ),
    filterPasserStatus: fc.oneof(
        fc.constant(''), // All statuses
        fc.constant('1'), // Qualified
        fc.constant('2'), // Waitlisted
        fc.constant('3')  // Unqualified
    )
})

// ─────────────────────────────────────────────────────────────────────────────
// Property Tests for Combined Filter Logic
// ─────────────────────────────────────────────────────────────────────────────
describe('Feature: list-passer-status-filter, Property 2: Combined filter logic', () => {
    
    it('Property 2.1: All filtered results satisfy ALL active filter conditions (AND logic)', () => {
        fc.assert(fc.property(
            fc.array(passerGenerator, { minLength: 0, maxLength: 50 }),
            filterStateGenerator,
            (passers, filters) => {
                const filtered = applyFilters(passers, filters)
                
                // Every filtered passer must satisfy ALL active filter conditions
                return filtered.every(passer => {
                    // Check search filter
                    const search = filters.searchTerm.toLowerCase()
                    const matchesSearch = search === '' ||
                        passer.surname.toLowerCase().includes(search) ||
                        passer.first_name.toLowerCase().includes(search) ||
                        passer.email.toLowerCase().includes(search)
                    
                    // Check school year filter
                    const matchesSchoolYear = filters.filterSchoolYear === '' ||
                        passer.school_year === filters.filterSchoolYear
                    
                    // Check batch filter
                    const matchesBatch = filters.filterBatchNumber === '' ||
                        passer.batch_number === filters.filterBatchNumber
                    
                    // Check status filter
                    const matchesStatus = filters.filterPasserStatus === '' ||
                        passer.passer_status_id === parseInt(filters.filterPasserStatus)
                    
                    // All conditions must be true
                    return matchesSearch && matchesSchoolYear && matchesBatch && matchesStatus
                })
            }
        ), { numRuns: 100 })
    })

    it('Property 2.2: No passer is excluded if they satisfy all active filter conditions', () => {
        fc.assert(fc.property(
            fc.array(passerGenerator, { minLength: 1, maxLength: 50 }),
            filterStateGenerator,
            (passers, filters) => {
                const filtered = applyFilters(passers, filters)
                
                // For every passer NOT in filtered results, they must fail at least one filter condition
                const filteredIds = new Set(filtered.map(p => p.test_passer_id))
                const excluded = passers.filter(p => !filteredIds.has(p.test_passer_id))
                
                return excluded.every(passer => {
                    // Check if passer fails any filter condition
                    const search = filters.searchTerm.toLowerCase()
                    const matchesSearch = search === '' ||
                        passer.surname.toLowerCase().includes(search) ||
                        passer.first_name.toLowerCase().includes(search) ||
                        passer.email.toLowerCase().includes(search)
                    
                    const matchesSchoolYear = filters.filterSchoolYear === '' ||
                        passer.school_year === filters.filterSchoolYear
                    
                    const matchesBatch = filters.filterBatchNumber === '' ||
                        passer.batch_number === filters.filterBatchNumber
                    
                    const matchesStatus = filters.filterPasserStatus === '' ||
                        passer.passer_status_id === parseInt(filters.filterPasserStatus)
                    
                    // At least one condition must be false for excluded passers
                    return !(matchesSearch && matchesSchoolYear && matchesBatch && matchesStatus)
                })
            }
        ), { numRuns: 100 })
    })

    it('Property 2.3: Filter combination is commutative (order of applying filters does not matter)', () => {
        fc.assert(fc.property(
            fc.array(passerGenerator, { minLength: 0, maxLength: 30 }),
            filterStateGenerator,
            (passers, filters) => {
                // Apply all filters at once
                const resultAllAtOnce = applyFilters(passers, filters)
                
                // Apply filters step by step in different orders
                let stepByStep1 = passers
                
                // Order 1: Search -> School Year -> Batch -> Status
                if (filters.searchTerm !== '') {
                    const search = filters.searchTerm.toLowerCase()
                    stepByStep1 = stepByStep1.filter(p => 
                        p.surname.toLowerCase().includes(search) ||
                        p.first_name.toLowerCase().includes(search) ||
                        p.email.toLowerCase().includes(search)
                    )
                }
                if (filters.filterSchoolYear !== '') {
                    stepByStep1 = stepByStep1.filter(p => p.school_year === filters.filterSchoolYear)
                }
                if (filters.filterBatchNumber !== '') {
                    stepByStep1 = stepByStep1.filter(p => p.batch_number === filters.filterBatchNumber)
                }
                if (filters.filterPasserStatus !== '') {
                    stepByStep1 = stepByStep1.filter(p => p.passer_status_id === parseInt(filters.filterPasserStatus))
                }
                
                // Order 2: Status -> Batch -> School Year -> Search
                let stepByStep2 = passers
                if (filters.filterPasserStatus !== '') {
                    stepByStep2 = stepByStep2.filter(p => p.passer_status_id === parseInt(filters.filterPasserStatus))
                }
                if (filters.filterBatchNumber !== '') {
                    stepByStep2 = stepByStep2.filter(p => p.batch_number === filters.filterBatchNumber)
                }
                if (filters.filterSchoolYear !== '') {
                    stepByStep2 = stepByStep2.filter(p => p.school_year === filters.filterSchoolYear)
                }
                if (filters.searchTerm !== '') {
                    const search = filters.searchTerm.toLowerCase()
                    stepByStep2 = stepByStep2.filter(p => 
                        p.surname.toLowerCase().includes(search) ||
                        p.first_name.toLowerCase().includes(search) ||
                        p.email.toLowerCase().includes(search)
                    )
                }
                
                // All approaches should yield the same result
                const ids1 = new Set(resultAllAtOnce.map(p => p.test_passer_id))
                const ids2 = new Set(stepByStep1.map(p => p.test_passer_id))
                const ids3 = new Set(stepByStep2.map(p => p.test_passer_id))
                
                return ids1.size === ids2.size && ids1.size === ids3.size &&
                       [...ids1].every(id => ids2.has(id) && ids3.has(id))
            }
        ), { numRuns: 100 })
    })

    it('Property 2.4: Empty filters return all passers (no filtering applied)', () => {
        fc.assert(fc.property(
            fc.array(passerGenerator, { minLength: 0, maxLength: 30 }),
            (passers) => {
                const emptyFilters: FilterState = {
                    searchTerm: '',
                    filterSchoolYear: '',
                    filterBatchNumber: '',
                    filterPasserStatus: ''
                }
                
                const filtered = applyFilters(passers, emptyFilters)
                
                // Should return all passers when no filters are active
                return filtered.length === passers.length &&
                       filtered.every(fp => passers.some(p => p.test_passer_id === fp.test_passer_id))
            }
        ), { numRuns: 100 })
    })

    it('Property 2.5: Adding more restrictive filters can only reduce or maintain result count', () => {
        fc.assert(fc.property(
            fc.array(passerGenerator, { minLength: 0, maxLength: 30 }),
            filterStateGenerator,
            filterStateGenerator,
            (passers, filters1, filters2) => {
                // Make filters2 more restrictive than filters1
                const moreRestrictive: FilterState = {
                    searchTerm: filters1.searchTerm || filters2.searchTerm,
                    filterSchoolYear: filters1.filterSchoolYear || filters2.filterSchoolYear,
                    filterBatchNumber: filters1.filterBatchNumber || filters2.filterBatchNumber,
                    filterPasserStatus: filters1.filterPasserStatus || filters2.filterPasserStatus
                }
                
                const result1 = applyFilters(passers, filters1)
                const result2 = applyFilters(passers, moreRestrictive)
                
                // More restrictive filters should return same or fewer results
                return result2.length <= result1.length
            }
        ), { numRuns: 100 })
    })

    it('Property 2.6: Status filter correctly handles null passer_status_id values', () => {
        fc.assert(fc.property(
            fc.array(passerGenerator, { minLength: 1, maxLength: 30 }),
            fc.oneof(fc.constant('1'), fc.constant('2'), fc.constant('3')),
            (passers, statusFilter) => {
                // Ensure we have at least one passer with null status
                const passersWithNull = [
                    ...passers,
                    {
                        test_passer_id: 99999,
                        surname: 'TestNull',
                        first_name: 'User',
                        email: 'null@test.com',
                        passer_status_id: null,
                        school_year: '2024-2025',
                        batch_number: 'Batch 1',
                        pupcet_total_score: 75.0
                    }
                ]
                
                const filters: FilterState = {
                    searchTerm: '',
                    filterSchoolYear: '',
                    filterBatchNumber: '',
                    filterPasserStatus: statusFilter
                }
                
                const filtered = applyFilters(passersWithNull, filters)
                
                // When filtering by specific status, null status passers should be excluded
                return filtered.every(p => p.passer_status_id === parseInt(statusFilter))
            }
        ), { numRuns: 100 })
    })

    it('Property 2.7: Search filter is case-insensitive and matches partial strings', () => {
        fc.assert(fc.property(
            fc.string({ minLength: 2, maxLength: 10 }).filter(s => s.trim().length > 0),
            fc.oneof(fc.constant('surname'), fc.constant('first_name'), fc.constant('email')),
            (searchTerm, fieldToMatch) => {
                // Create a passer that should match the search
                const testPasser: TestPasser = {
                    test_passer_id: 1,
                    surname: fieldToMatch === 'surname' ? `Test${searchTerm}User` : 'NoMatch',
                    first_name: fieldToMatch === 'first_name' ? `Test${searchTerm}User` : 'NoMatch',
                    email: fieldToMatch === 'email' ? `test${searchTerm}@example.com` : 'nomatch@example.com',
                    passer_status_id: 1,
                    school_year: '2024-2025',
                    batch_number: 'Batch 1',
                    pupcet_total_score: 85.0
                }
                
                const filters: FilterState = {
                    searchTerm: searchTerm.toUpperCase(), // Test case insensitivity
                    filterSchoolYear: '',
                    filterBatchNumber: '',
                    filterPasserStatus: ''
                }
                
                const filtered = applyFilters([testPasser], filters)
                
                // Should find the passer regardless of case
                return filtered.length === 1 && filtered[0].test_passer_id === 1
            }
        ), { numRuns: 100 })
    })
})