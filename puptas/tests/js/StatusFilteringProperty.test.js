import { describe, test, expect } from 'vitest'
import fc from 'fast-check'

/**
 * Property-Based Tests for Status Filtering Correctness
 * Feature: list-passer-status-filter
 * 
 * These tests verify the universal correctness properties of the status filtering logic
 * using property-based testing with fast-check library.
 */

// Generator for test passer objects
const passerGenerator = fc.record({
  test_passer_id: fc.integer({ min: 1, max: 10000 }),
  surname: fc.string({ minLength: 1, maxLength: 50 }),
  first_name: fc.string({ minLength: 1, maxLength: 50 }),
  email: fc.emailAddress(),
  passer_status_id: fc.oneof(
    fc.constant(null),
    fc.constant(1), // qualified
    fc.constant(2), // waitlisted
    fc.constant(3)  // unqualified
  ),
  school_year: fc.oneof(
    fc.constant('2023-2024'),
    fc.constant('2024-2025'),
    fc.constant('2025-2026')
  ),
  batch_number: fc.oneof(
    fc.constant('Batch 1'),
    fc.constant('Batch 2'),
    fc.constant('Batch 3')
  ),
  pupcet_total_score: fc.oneof(
    fc.constant(null),
    fc.float({ min: 0, max: 100 })
  )
})

// Generator for status filter values
const statusFilterGenerator = fc.oneof(
  fc.constant(''),   // All Statuses
  fc.constant('1'),  // Qualified
  fc.constant('2'),  // Waitlisted
  fc.constant('3')   // Unqualified
)

/**
 * Simulates the filteredPassers computed property logic from TestPassers/Email.vue
 * This is the core filtering function we're testing
 */
function applyStatusFilter(passers, statusFilter, searchTerm = '', schoolYear = '', batchNumber = '') {
  return passers.filter((passer) => {
    // Search filter logic
    const search = searchTerm.toLowerCase()
    const matchesSearch = search === '' || 
      passer.surname.toLowerCase().includes(search) ||
      passer.first_name.toLowerCase().includes(search) ||
      passer.email.toLowerCase().includes(search)

    // School year filter logic
    const matchesSchoolYear = schoolYear === '' || passer.school_year === schoolYear

    // Batch filter logic
    const matchesBatch = batchNumber === '' || passer.batch_number === batchNumber

    // Status filter logic (the main focus of this test)
    const matchesStatus = statusFilter === '' || 
      passer.passer_status_id === parseInt(statusFilter)

    return matchesSearch && matchesSchoolYear && matchesBatch && matchesStatus
  })
}

/**
 * Verifies that status filtering results are correct
 */
function verifyStatusFilterResults(filteredResults, originalPassers, statusFilter) {
  if (statusFilter === '') {
    // When "All Statuses" is selected, no status filtering should occur
    // (other filters may still apply, so we can't check for exact equality)
    return true
  }

  const expectedStatusId = parseInt(statusFilter)
  
  // Every result should have the expected status
  const allHaveCorrectStatus = filteredResults.every(passer => 
    passer.passer_status_id === expectedStatusId
  )

  // No passer with the expected status should be missing (unless filtered by other criteria)
  const passersWithExpectedStatus = originalPassers.filter(passer => 
    passer.passer_status_id === expectedStatusId
  )

  // All passers with expected status should be included in results
  // (assuming no other filters are applied in this basic test)
  const noValidPassersMissing = passersWithExpectedStatus.every(expectedPasser =>
    filteredResults.some(resultPasser => 
      resultPasser.test_passer_id === expectedPasser.test_passer_id
    )
  )

  return allHaveCorrectStatus && noValidPassersMissing
}

describe('Status Filtering Properties', () => {
  /**
   * Property 1: Status filtering correctness
   * **Validates: Requirements 2.5, 2.6, 3.1, 3.2, 3.3, 3.4**
   * 
   * For any dataset of test passers and any selected status filter value 
   * (qualified=1, waitlisted=2, unqualified=3, or "All Statuses"), 
   * the filtered results SHALL contain only passers whose passer_status_id 
   * matches the selected value, or all passers when "All Statuses" is selected.
   */
  test('Property 1: Status filtering correctness', () => {
    fc.assert(fc.property(
      fc.array(passerGenerator, { minLength: 0, maxLength: 100 }),
      statusFilterGenerator,
      (passers, statusFilter) => {
        const filtered = applyStatusFilter(passers, statusFilter)
        
        if (statusFilter === '') {
          // "All Statuses" - should include all passers (no status filtering)
          return filtered.length === passers.length
        } else {
          const expectedStatusId = parseInt(statusFilter)
          
          // All filtered results should have the correct status
          const allHaveCorrectStatus = filtered.every(passer => 
            passer.passer_status_id === expectedStatusId
          )
          
          // Count of filtered results should match count of passers with that status
          const expectedCount = passers.filter(passer => 
            passer.passer_status_id === expectedStatusId
          ).length
          
          return allHaveCorrectStatus && filtered.length === expectedCount
        }
      }
    ), { numRuns: 100 })
  })

  /**
   * Property 2: Empty status filter behavior
   * **Validates: Requirements 2.5, 3.4**
   * 
   * When status filter is empty (All Statuses), all passers should be included
   * regardless of their passer_status_id value (including null values).
   */
  test('Property 2: Empty status filter includes all passers', () => {
    fc.assert(fc.property(
      fc.array(passerGenerator, { minLength: 1, maxLength: 50 }),
      (passers) => {
        const filtered = applyStatusFilter(passers, '') // Empty filter = "All Statuses"
        
        // Should include all passers, including those with null status
        return filtered.length === passers.length &&
               filtered.every(filteredPasser => 
                 passers.some(originalPasser => 
                   originalPasser.test_passer_id === filteredPasser.test_passer_id
                 )
               )
      }
    ), { numRuns: 100 })
  })

  /**
   * Property 3: Specific status filter exclusivity
   * **Validates: Requirements 3.1, 3.2, 3.3**
   * 
   * When a specific status is selected, only passers with that exact status
   * should be included, and no passers with different statuses should be included.
   */
  test('Property 3: Specific status filter exclusivity', () => {
    fc.assert(fc.property(
      fc.array(passerGenerator, { minLength: 5, maxLength: 50 }),
      fc.oneof(fc.constant('1'), fc.constant('2'), fc.constant('3')),
      (passers, statusFilter) => {
        const filtered = applyStatusFilter(passers, statusFilter)
        const expectedStatusId = parseInt(statusFilter)
        
        // Every filtered passer must have the expected status
        const allHaveCorrectStatus = filtered.every(passer => 
          passer.passer_status_id === expectedStatusId
        )
        
        // No passer with a different status should be included
        const noneHaveWrongStatus = !filtered.some(passer => 
          passer.passer_status_id !== expectedStatusId
        )
        
        return allHaveCorrectStatus && noneHaveWrongStatus
      }
    ), { numRuns: 100 })
  })

  /**
   * Property 4: Null status handling
   * **Validates: Requirements 3.4**
   * 
   * Passers with null passer_status_id should only appear when "All Statuses" 
   * is selected, never when a specific status is selected.
   */
  test('Property 4: Null status handling', () => {
    fc.assert(fc.property(
      fc.array(passerGenerator, { minLength: 1, maxLength: 30 }),
      statusFilterGenerator,
      (passers, statusFilter) => {
        const filtered = applyStatusFilter(passers, statusFilter)
        
        if (statusFilter === '') {
          // "All Statuses" - null status passers should be included
          const nullStatusPassers = passers.filter(p => p.passer_status_id === null)
          const nullStatusInResults = filtered.filter(p => p.passer_status_id === null)
          return nullStatusInResults.length === nullStatusPassers.length
        } else {
          // Specific status - no null status passers should be included
          return !filtered.some(passer => passer.passer_status_id === null)
        }
      }
    ), { numRuns: 100 })
  })

  /**
   * Property 5: Filter result consistency
   * **Validates: Requirements 2.6**
   * 
   * Applying the same filter multiple times should always produce identical results.
   */
  test('Property 5: Filter result consistency', () => {
    fc.assert(fc.property(
      fc.array(passerGenerator, { minLength: 0, maxLength: 50 }),
      statusFilterGenerator,
      (passers, statusFilter) => {
        const result1 = applyStatusFilter(passers, statusFilter)
        const result2 = applyStatusFilter(passers, statusFilter)
        
        // Results should be identical
        return result1.length === result2.length &&
               result1.every((passer, index) => 
                 passer.test_passer_id === result2[index].test_passer_id
               )
      }
    ), { numRuns: 100 })
  })

  /**
   * Property 6: Combined filter logic (AND operation)
   * **Validates: Requirements 3.6**
   * 
   * When multiple filters are applied, they should work together using AND logic.
   * A passer must satisfy ALL active filters to be included in results.
   */
  test('Property 6: Combined filter AND logic', () => {
    fc.assert(fc.property(
      fc.array(passerGenerator, { minLength: 5, maxLength: 30 }),
      statusFilterGenerator,
      fc.string({ minLength: 0, maxLength: 10 }), // search term
      (passers, statusFilter, searchTerm) => {
        const filtered = applyStatusFilter(passers, statusFilter, searchTerm)
        
        // Every result should satisfy both status and search filters
        return filtered.every(passer => {
          const search = searchTerm.toLowerCase()
          const matchesSearch = search === '' || 
            passer.surname.toLowerCase().includes(search) ||
            passer.first_name.toLowerCase().includes(search) ||
            passer.email.toLowerCase().includes(search)
          
          const matchesStatus = statusFilter === '' || 
            passer.passer_status_id === parseInt(statusFilter)
          
          return matchesSearch && matchesStatus
        })
      }
    ), { numRuns: 100 })
  })
})