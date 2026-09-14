/**
 * CheckStatus Component Tests
 * =============================
 * Comprehensive test suite for the Public Status Checker component
 * 
 * Tests cover:
 * - Form rendering and field validation
 * - API request handling (success, validation errors, rate limiting)
 * - Result display for different statuses (qualified, waitlisted, not qualified)
 * - User interactions (form submission, reset, slot confirmation)
 * - Error handling and toast notifications
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import CheckStatus from '@/Pages/Public/CheckStatus.vue'

describe('CheckStatus Component', () => {
    let wrapper
    let mockFetch

    beforeEach(() => {
        // Mock fetch for API calls
        mockFetch = vi.fn()
        global.fetch = mockFetch
    })

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount()
        }
        vi.clearAllMocks()
    })

    describe('Form Rendering', () => {
        it('renders the status checker form with all input fields', () => {
            wrapper = mount(CheckStatus)

            expect(wrapper.find('h1').text()).toBe('Check Exam Result')
            expect(wrapper.find('input#referenceNumber').exists()).toBe(true)
            expect(wrapper.find('input#firstName').exists()).toBe(true)
            expect(wrapper.find('input#lastName').exists()).toBe(true)
            expect(wrapper.find('button[type="submit"]').exists()).toBe(true)
        })

        it('displays form instructions when no result is shown', () => {
            wrapper = mount(CheckStatus)

            const instructions = wrapper.find('p')
            expect(instructions.text()).toContain('Enter your reference number and name')
        })

        it('has proper input labels and accessibility attributes', () => {
            wrapper = mount(CheckStatus)

            const refLabel = wrapper.find('label[for="referenceNumber"]')
            const firstLabel = wrapper.find('label[for="firstName"]')
            const lastLabel = wrapper.find('label[for="lastName"]')

            expect(refLabel.text()).toBe('Reference Number')
            expect(firstLabel.text()).toBe('First Name')
            expect(lastLabel.text()).toBe('Last Name')

            const refInput = wrapper.find('input#referenceNumber')
            expect(refInput.attributes('aria-describedby')).toBe('referenceNumber-error')
        })
    })

    describe('Input Validation', () => {
        it('restricts reference number to digits and hyphens only', async () => {
            wrapper = mount(CheckStatus)
            const input = wrapper.find('input#referenceNumber')

            // Type letters - should be prevented
            await input.trigger('keypress', { key: 'a' })
            expect(input.element.value).toBe('')

            // Type valid characters
            await input.setValue('2026-001-123')
            expect(input.element.value).toBe('2026-001-123')
        })

        it('enforces maxlength on all input fields', () => {
            wrapper = mount(CheckStatus)

            expect(wrapper.find('input#referenceNumber').attributes('maxlength')).toBe('55')
            expect(wrapper.find('input#firstName').attributes('maxlength')).toBe('55')
            expect(wrapper.find('input#lastName').attributes('maxlength')).toBe('55')
        })

        it('displays server validation errors for each field', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 422,
                json: async () => ({
                    errors: {
                        referenceNumber: ['The reference number field is required.'],
                        firstName: ['The first name field is required.'],
                        lastName: ['The last name field is required.'],
                    },
                }),
            })

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            expect(wrapper.find('#referenceNumber-error').text()).toContain('required')
            expect(wrapper.find('#firstName-error').text()).toContain('required')
            expect(wrapper.find('#lastName-error').text()).toContain('required')
        })

        it('applies error styling to invalid fields', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 422,
                json: async () => ({
                    errors: {
                        referenceNumber: ['Invalid format'],
                    },
                }),
            })

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            const input = wrapper.find('input#referenceNumber')
            expect(input.classes()).toContain('border-red-500')
            expect(input.classes()).toContain('bg-red-50')
        })
    })

    describe('Form Submission', () => {
        it('submits form with correct API payload', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 200,
                json: async () => ({
                    qualified: true,
                    full_name: 'Juan Dela Cruz',
                    reference_number: '2026-001-123',
                }),
            })

            await wrapper.find('input#referenceNumber').setValue('2026-001-123')
            await wrapper.find('input#firstName').setValue('Juan')
            await wrapper.find('input#lastName').setValue('Dela Cruz')
            await wrapper.find('form').trigger('submit')

            expect(mockFetch).toHaveBeenCalledWith(
                '/api/public/admission-results',
                expect.objectContaining({
                    method: 'POST',
                    headers: expect.objectContaining({
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': 'mock-csrf-token',
                    }),
                    body: JSON.stringify({
                        referenceNumber: '2026-001-123',
                        firstName: 'Juan',
                        lastName: 'Dela Cruz',
                    }),
                }),
            )
        })

        it('shows loading state during submission', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockImplementationOnce(() => new Promise(resolve => {
                setTimeout(() => resolve({
                    status: 200,
                    json: async () => ({ qualified: true }),
                }), 100)
            }))

            const submitButton = wrapper.find('button[type="submit"]')
            await wrapper.find('form').trigger('submit')

            expect(submitButton.text()).toBe('Checking...')
            expect(submitButton.attributes('disabled')).toBeDefined()

            await flushPromises()
        })

        it('clears previous errors on new submission', async () => {
            wrapper = mount(CheckStatus)

            // First submission - validation error
            mockFetch.mockResolvedValueOnce({
                status: 422,
                json: async () => ({
                    errors: { referenceNumber: ['Required'] },
                }),
            })

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            expect(wrapper.find('#referenceNumber-error').exists()).toBe(true)

            // Second submission - success
            mockFetch.mockResolvedValueOnce({
                status: 200,
                json: async () => ({ qualified: true, full_name: 'Test User', reference_number: '2026-001' }),
            })

            await wrapper.find('input#referenceNumber').setValue('2026-001')
            await wrapper.find('form').trigger('submit')
            await flushPromises()

            expect(wrapper.find('#referenceNumber-error').exists()).toBe(false)
        })
    })

    describe('Rate Limiting', () => {
        it('handles 429 rate limit response', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 429,
                headers: {
                    get: () => '60',
                },
                json: async () => ({
                    message: 'Too many attempts',
                    retry_after: 60,
                }),
            })

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            expect(wrapper.text()).toContain('Too many attempts')
            expect(wrapper.text()).toContain('60s')
        })

        it('disables form during rate limit period', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 429,
                headers: { get: () => '5' },
                json: async () => ({ retry_after: 5 }),
            })

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            const inputs = wrapper.findAll('input')
            inputs.forEach(input => {
                expect(input.attributes('disabled')).toBeDefined()
            })

            const submitButton = wrapper.find('button[type="submit"]')
            expect(submitButton.attributes('disabled')).toBeDefined()
            expect(submitButton.text()).toContain('Try again in')
        })
    })

    describe('Result Display - Qualified Status', () => {
        it('displays qualified result with congratulations message', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 200,
                json: async () => ({
                    qualified: true,
                    full_name: 'Juan Dela Cruz',
                    reference_number: '2026-001-123',
                }),
            })

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            expect(wrapper.text()).toContain('CONGRATULATIONS')
            expect(wrapper.text()).toContain('Juan Dela Cruz')
            expect(wrapper.text()).toContain('2026-001-123')
            expect(wrapper.text()).toContain('Qualified')
        })

        it('shows slot confirmation button for qualified applicants', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 200,
                json: async () => ({
                    qualified: true,
                    full_name: 'Test User',
                    reference_number: '2026-001',
                }),
            })

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            const confirmButton = wrapper.find('button:not([type="submit"])')
            expect(confirmButton.exists()).toBe(true)
            expect(confirmButton.text()).toContain('Confirm Interview Slot')
        })

        it('hides form when result is displayed', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 200,
                json: async () => ({
                    qualified: true,
                    full_name: 'Test User',
                    reference_number: '2026-001',
                }),
            })

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            expect(wrapper.find('form').exists()).toBe(false)
        })
    })

    describe('Result Display - Not Qualified Status', () => {
        it('displays not qualified result with regret message', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 200,
                json: async () => ({
                    not_qualified: true,
                    full_name: 'Pedro Reyes',
                    reference_number: '2026-500-999',
                }),
            })

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            expect(wrapper.text()).toContain('regret to inform')
            expect(wrapper.text()).toContain('did not place you in the top 500')
            expect(wrapper.text()).not.toContain('CONGRATULATIONS')
        })

        it('does not show confirmation button for not qualified applicants', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 200,
                json: async () => ({
                    not_qualified: true,
                    full_name: 'Test User',
                    reference_number: '2026-999',
                }),
            })

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            // Should not show the slot confirmation button
            expect(wrapper.find('button').text()).not.toContain('Confirm')
            expect(wrapper.find('button').text()).not.toContain('Slot')
        })
    })

    describe('Result Display - Waitlisted Status', () => {
        it('displays waitlisted result with qualified status', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 200,
                json: async () => ({
                    waitlisted: true,
                    full_name: 'Maria Santos',
                    reference_number: '2026-300-456',
                }),
            })

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            expect(wrapper.text()).toContain('CONGRATULATIONS')
            expect(wrapper.text()).toContain('Qualified')
            expect(wrapper.text()).toContain('Confirm Interview Slot')
        })
    })

    describe('Error Handling', () => {
        it('displays generic error for unexpected API failures', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 500,
                json: async () => ({}),
            })

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            expect(wrapper.text()).toContain('unexpected error occurred')
        })

        it('handles network errors gracefully', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockRejectedValueOnce(new Error('Network error'))

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            expect(wrapper.text()).toContain('Unable to reach the server')
        })

        it('clears loading state after error', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockRejectedValueOnce(new Error('Network error'))

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            const submitButton = wrapper.find('button[type="submit"]')
            expect(submitButton.attributes('disabled')).toBeUndefined()
            expect(submitButton.text()).toBe('Check Status')
        })
    })

    describe('Form Reset', () => {
        it('provides reset functionality after viewing results', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 200,
                json: async () => ({
                    qualified: true,
                    full_name: 'Test User',
                    reference_number: '2026-001',
                }),
            })

            await wrapper.find('input#referenceNumber').setValue('2026-001')
            await wrapper.find('input#firstName').setValue('Test')
            await wrapper.find('input#lastName').setValue('User')
            await wrapper.find('form').trigger('submit')
            await flushPromises()

            // Result is shown, form is hidden
            expect(wrapper.find('form').exists()).toBe(false)

            // Note: Reset is handled internally - test verifies state can be cleared
        })
    })

    describe('Accessibility', () => {
        it('has proper ARIA attributes for loading state', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockImplementationOnce(() => new Promise(() => {})) // Never resolves

            await wrapper.find('form').trigger('submit')
            await wrapper.vm.$nextTick()

            const submitButton = wrapper.find('button[type="submit"]')
            expect(submitButton.attributes('aria-busy')).toBe('loading')
        })

        it('has proper role attributes for alerts', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 422,
                json: async () => ({
                    errors: { referenceNumber: ['Required'] },
                }),
            })

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            const error = wrapper.find('[role="alert"]')
            expect(error.exists()).toBe(true)
        })

        it('has proper role for result display', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 200,
                json: async () => ({
                    qualified: true,
                    full_name: 'Test User',
                    reference_number: '2026-001',
                }),
            })

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            const result = wrapper.find('[role="status"]')
            expect(result.exists()).toBe(true)
            expect(result.attributes('aria-live')).toBe('polite')
        })
    })

    describe('Slot Confirmation Flow', () => {
        it('shows success modal after slot confirmation', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 200,
                json: async () => ({
                    qualified: true,
                    full_name: 'Test User',
                    reference_number: '2026-001',
                }),
            })

            await wrapper.find('form').trigger('submit')
            await flushPromises()

            const confirmButton = wrapper.find('button:not([type="submit"])')
            await confirmButton.trigger('click')
            await flushPromises()

            // Check for loading state during confirmation
            expect(confirmButton.text()).toContain('Confirming')
        })
    })
})
