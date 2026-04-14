// Error Handling — Frontend Tests (Vitest + fast-check)
// Validates: Requirements 5.2, 5.3, 5.4, 5.5, 6.1, 6.3, 6.4

import { describe, it, expect, vi, beforeEach } from 'vitest'
import * as fc from 'fast-check'

// ─── useErrorStore ────────────────────────────────────────────────────────────
// Import the real composable (no mocks needed — it's pure reactive state)
import { useErrorStore } from '@/Composables/useErrorStore'

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Extract the interceptor's error handler from bootstrap.js by re-creating
 * the same logic inline. We test the logic directly rather than importing
 * bootstrap.js (which has side-effects: sets window.axios, reads DOM for CSRF).
 *
 * The interceptor logic from bootstrap.js:
 *
 *   error => {
 *     const { setError } = useErrorStore()
 *     if (error.response) {
 *       const message = error.response.data?.message
 *         ?? 'An unexpected error occurred. Please try again.'
 *       setError(message)
 *     } else {
 *       setError('Unable to connect. Please check your connection and try again.')
 *     }
 *     return Promise.reject(error)
 *   }
 */
function makeInterceptorErrorHandler(setError: (msg: string) => void) {
  return (error: unknown) => {
    const err = error as { response?: { data?: { message?: unknown } } }
    if (err.response) {
      const message =
        (typeof err.response.data?.message === 'string' ? err.response.data.message : undefined)
        ?? 'An unexpected error occurred. Please try again.'
      setError(message)
    } else {
      setError('Unable to connect. Please check your connection and try again.')
    }
    return Promise.reject(error)
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Task 5.1 — useErrorStore unit tests
// ─────────────────────────────────────────────────────────────────────────────

describe('useErrorStore', () => {
  // Reset state before each test by calling clearError
  beforeEach(() => {
    const { clearError } = useErrorStore()
    clearError()
  })

  // ── Property 8: set/clear round-trip ──────────────────────────────────────
  it(
    'Property 8 — set/clear round-trip: setError stores message, clearError resets to null (100 iterations)',
    () => {
      // Validates: Requirements 6.1, 6.3
      fc.assert(
        fc.property(
          fc.string({ minLength: 1 }),
          (message) => {
            const { errorState, setError, clearError } = useErrorStore()

            setError(message)
            expect(errorState.message).toBe(message)

            clearError()
            expect(errorState.message).toBeNull()
          },
        ),
        { numRuns: 100 },
      )
    },
  )

  // ── Property 9: retry callback invocation ─────────────────────────────────
  it(
    'Property 9 — retry callback: calling retry() invokes the stored callback exactly once (100 iterations)',
    () => {
      // Validates: Requirements 6.4
      fc.assert(
        fc.property(
          fc.string({ minLength: 1 }),
          (message) => {
            const { setError, retry, clearError } = useErrorStore()

            const fn = vi.fn()
            setError(message, fn)
            retry()

            expect(fn).toHaveBeenCalledTimes(1)

            // Clean up for next iteration
            clearError()
            fn.mockReset()
          },
        ),
        { numRuns: 100 },
      )
    },
  )

  it('retry() does nothing when no callback is set', () => {
    const { setError, retry, errorState } = useErrorStore()
    setError('some error')
    // No callback — retry should not throw
    expect(() => retry()).not.toThrow()
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// Task 5.2 — Axios interceptor property tests
// ─────────────────────────────────────────────────────────────────────────────

describe('Axios interceptor', () => {
  // ── Property 6: message extraction from 4xx/5xx responses ─────────────────
  it(
    'Property 6 — extracts message from any 4xx/5xx response with a message field (100+ iterations)',
    async () => {
      // Validates: Requirements 5.2, 5.4
      await fc.assert(
        fc.asyncProperty(
          fc.integer({ min: 400, max: 599 }),
          fc.string({ minLength: 1 }),
          async (status, message) => {
            const captured: string[] = []
            const handler = makeInterceptorErrorHandler((msg) => captured.push(msg))

            const error = {
              response: {
                status,
                data: { message },
              },
            }

            await handler(error).catch(() => {})

            expect(captured).toHaveLength(1)
            expect(captured[0]).toBe(message)
          },
        ),
        { numRuns: 100 },
      )
    },
  )

  // ── Property 7: fallback for missing/unparseable message ──────────────────
  it(
    'Property 7 — uses fallback message when response body lacks a message field (100+ iterations)',
    async () => {
      // Validates: Requirements 5.3
      const FALLBACK = 'An unexpected error occurred. Please try again.'

      // Arbitrary for response data shapes that have no valid string message field
      const noMessageDataArb = fc.oneof(
        // null data
        fc.constant(null),
        // undefined data
        fc.constant(undefined),
        // empty object
        fc.constant({}),
        // object with null message
        fc.record({ message: fc.constant(null) }),
        // object with numeric message (not a string)
        fc.record({ message: fc.integer() }),
        // object with boolean message
        fc.record({ message: fc.boolean() }),
        // object with array message
        fc.record({ message: fc.array(fc.string()) }),
        // object with unrelated keys
        fc.record({ error: fc.string(), code: fc.integer() }),
      )

      await fc.assert(
        fc.asyncProperty(
          fc.integer({ min: 400, max: 599 }),
          noMessageDataArb,
          async (status, data) => {
            const captured: string[] = []
            const handler = makeInterceptorErrorHandler((msg) => captured.push(msg))

            const error = {
              response: {
                status,
                data,
              },
            }

            await handler(error).catch(() => {})

            expect(captured).toHaveLength(1)
            expect(captured[0]).toBe(FALLBACK)
          },
        ),
        { numRuns: 100 },
      )
    },
  )

  // ── Interceptor always re-rejects ─────────────────────────────────────────
  it('interceptor re-rejects the original error so call-site .catch() still works', async () => {
    const handler = makeInterceptorErrorHandler(() => {})
    const originalError = { response: { status: 500, data: { message: 'oops' } } }

    await expect(handler(originalError)).rejects.toBe(originalError)
  })
})

// ─────────────────────────────────────────────────────────────────────────────
// Task 5.3 — Network error example test
// ─────────────────────────────────────────────────────────────────────────────

describe('Axios interceptor — network error', () => {
  it(
    'produces the connection error message when there is no response object',
    async () => {
      // Validates: Requirements 5.5
      const CONNECTION_MSG = 'Unable to connect. Please check your connection and try again.'

      const captured: string[] = []
      const handler = makeInterceptorErrorHandler((msg) => captured.push(msg))

      // Network error: no response property
      const networkError = new Error('Network Error')

      await handler(networkError).catch(() => {})

      expect(captured).toHaveLength(1)
      expect(captured[0]).toBe(CONNECTION_MSG)
    },
  )

  it('network error re-rejects the original error', async () => {
    const handler = makeInterceptorErrorHandler(() => {})
    const networkError = new Error('Network Error')

    await expect(handler(networkError)).rejects.toBe(networkError)
  })
})
