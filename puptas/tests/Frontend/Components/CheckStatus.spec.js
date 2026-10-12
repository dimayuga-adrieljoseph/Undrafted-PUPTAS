/**
 * CheckStatus Component Tests
 * =============================
 * Comprehensive test suite for the Public Status Checker component
 *
 * Tests cover:
 * - Form rendering and field validation
 * - API request handling (success, validation errors, rate limiting)
 * - Result display for every status returned by the public endpoint
 *   (qualified, waitlisted, waitlisted-below-cutoff, not qualified, no record)
 * - User interactions (form submission, reset, slot confirmation)
 * - Error handling and accessibility semantics
 *
 * NOTE ON STATUS COPY
 * The waitlisted states must never reuse the qualified layout. A previous
 * regression made the status-2 waitlist render "CONGRATULATIONS" and
 * "Status: Qualified"; the suite below asserts the opposite explicitly so
 * the bug cannot come back.
 *
 * NOTE ON CASING
 * Headings are uppercased with CSS (`text-transform: uppercase`) rather than
 * by hard-coding the markup, so assertions that care about a heading compare
 * against the uppercased text.
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import CheckStatus from '@/Pages/Public/CheckStatus.vue'
import SlotConfirmationSuccessModal from '@/Pages/Modal/SlotConfirmationSuccessModal.vue'

/**
 * Mock fetch handle. Declared at module scope so the `mountWithResult`
 * helper below can reach the mock installed by each test's beforeEach.
 */
let mockFetch

/** Mount the page and drive it through a successful lookup. */
async function mountWithResult(payload) {
    const wrapper = mount(CheckStatus)
    mockFetch.mockResolvedValueOnce({
        status: 200,
        json: async () => payload,
    })
    await wrapper.find('form').trigger('submit')
    await flushPromises()
    return wrapper
}

/** Uppercased text, for comparing against CSS-uppercased headings. */
const upper = (text) => text.toUpperCase()

describe('CheckStatus Component', () => {
    let wrapper

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

            expect(wrapper.find('h1').text()).toBe('Admission Results')
            expect(wrapper.find('input#referenceNumber').exists()).toBe(true)
            expect(wrapper.find('input#firstName').exists()).toBe(true)
            expect(wrapper.find('input#lastName').exists()).toBe(true)
            expect(wrapper.find('button[type="submit"]').exists()).toBe(true)
        })

        it('displays form instructions when no result is shown', () => {
            wrapper = mount(CheckStatus)

            expect(wrapper.text()).toContain('Check your admission result for')
            expect(wrapper.text()).toContain('SY 2026–2027')
            expect(wrapper.text()).toContain(
                'Your information is used to retrieve your admission result.'
            )
        })

        it('uses the primary action label', () => {
            wrapper = mount(CheckStatus)

            expect(wrapper.find('button[type="submit"]').text()).toBe('Check My Result')
        })

        it('has proper input labels and accessibility attributes', () => {
            wrapper = mount(CheckStatus)

            const refLabel = wrapper.find('label[for="referenceNumber"]')
            const firstLabel = wrapper.find('label[for="firstName"]')
            const lastLabel = wrapper.find('label[for="lastName"]')

            expect(refLabel.text()).toBe('Reference Number')
            expect(firstLabel.text()).toBe('First Name')
            expect(lastLabel.text()).toBe('Last Name')

            // aria-describedby must be bound per field, not emitted literally
            const refInput = wrapper.find('input#referenceNumber')
            expect(refInput.attributes('aria-describedby')).toBe('referenceNumber-error')
            expect(wrapper.find('input#firstName').attributes('aria-describedby'))
                .toBe('firstName-error')
            expect(wrapper.find('input#lastName').attributes('aria-describedby'))
                .toBe('lastName-error')
        })

        it('exposes the PUP-Taguig logo with descriptive alt text', () => {
            wrapper = mount(CheckStatus)

            const logo = wrapper.find('img[alt*="Polytechnic University"]')
            expect(logo.exists()).toBe(true)
            // The test harness rewrites image imports, so only assert the
            // asset is wired up and that it carries descriptive alt text.
            expect(logo.attributes('src')).toBeTruthy()
            expect(logo.attributes('alt')).toContain('Polytechnic University of the Philippines')
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
            wrapper = await mountWithResult({
                qualified: true,
                full_name: 'Juan Dela Cruz',
                reference_number: '2026-001-123',
            })

            expect(upper(wrapper.text())).toContain('CONGRATULATIONS')
            expect(wrapper.text()).toContain('Juan Dela Cruz')
            expect(wrapper.text()).toContain('2026-001-123')
            expect(wrapper.text()).toContain('You’re Qualified')
        })

        it('shows the status as readable text, not colour alone', async () => {
            wrapper = await mountWithResult({
                qualified: true,
                full_name: 'Juan Dela Cruz',
                reference_number: '2026-001-123',
            })

            // The status must be spelled out in the document body.
            const text = upper(wrapper.text())
            expect(text).toContain('YOU’RE QUALIFIED')
            expect(text).toContain('ADMISSION RESULT')
        })

        it('renders the institutional document header and screenshot footer', async () => {
            wrapper = await mountWithResult({
                qualified: true,
                full_name: 'Juan Dela Cruz',
                reference_number: '2026-001-123',
            })

            const text = wrapper.text()
            expect(upper(text)).toContain('POLYTECHNIC UNIVERSITY')
            expect(upper(text)).toContain('PUP–TAGUIG')
            // Undersigned footer so a detached screenshot still reads correctly.
            expect(text).toContain('PUP–Taguig Admission Results · SY 2026–2027')
            expect(text).toContain('Admission and Registration Office')
        })

        it('shows the next-steps section for qualified applicants', async () => {
            wrapper = await mountWithResult({
                qualified: true,
                full_name: 'Juan Dela Cruz',
                reference_number: '2026-001-123',
            })

            const text = wrapper.text()
            expect(upper(text)).toContain('WHAT HAPPENS NEXT')
            expect(text).toContain('Confirm your interview slot')
            expect(text).toContain('Complete your interview')
            expect(text).toContain('Proceed with enrollment')
        })

        it('shows slot confirmation button for qualified applicants', async () => {
            wrapper = await mountWithResult({
                qualified: true,
                full_name: 'Test User',
                reference_number: '2026-001',
            })

            const confirmButton = wrapper.find('button:not([type="submit"])')
            expect(confirmButton.exists()).toBe(true)
            expect(confirmButton.text()).toContain('Confirm Interview Slot')
        })

        it('keeps the "not officially enrolled" warning next to the CTA', async () => {
            wrapper = await mountWithResult({
                qualified: true,
                full_name: 'Test User',
                reference_number: '2026-001',
            })

            expect(wrapper.text()).toContain(
                'Confirming your interview slot does not mean that you are officially enrolled.'
            )
        })

        it('hides form when result is displayed', async () => {
            wrapper = await mountWithResult({
                qualified: true,
                full_name: 'Test User',
                reference_number: '2026-001',
            })

            expect(wrapper.find('form').exists()).toBe(false)
        })
    })

    describe('Result Display - Not Qualified Status', () => {
        it('displays not qualified result with regret message', async () => {
            wrapper = await mountWithResult({
                not_qualified: true,
                full_name: 'Pedro Reyes',
                reference_number: '2026-500-999',
            })

            expect(wrapper.text()).toContain('Pedro Reyes')
            expect(wrapper.text()).toContain('regret to inform')
            // Official approved wording. Note this is deliberately NOT the
            // "Top 500 requirement" language used by the below-cut-off state.
            expect(wrapper.text()).toContain('did not meet the qualifying threshold')
            expect(wrapper.text()).not.toContain('top 500')
            expect(upper(wrapper.text())).toContain('NOT QUALIFIED')
            expect(upper(wrapper.text())).not.toContain('CONGRATULATIONS')
        })

        it('does not show confirmation button for not qualified applicants', async () => {
            wrapper = await mountWithResult({
                not_qualified: true,
                full_name: 'Test User',
                reference_number: '2026-999',
            })

            // No button in this branch may offer the interview-slot CTA.
            wrapper.findAll('button').forEach((button) => {
                expect(button.text()).not.toContain('Confirm Interview Slot')
            })
        })

        it('keeps the formal office signature', async () => {
            wrapper = await mountWithResult({
                not_qualified: true,
                full_name: 'Pedro Reyes',
                reference_number: '2026-500-999',
            })

            expect(wrapper.text()).toContain('Admission and Registration Office')
        })
    })

    describe('Result Display - Waitlisted Status', () => {
        it('displays the waitlisted result without any qualified copy', async () => {
            wrapper = await mountWithResult({
                waitlisted: true,
                full_name: 'Maria Santos',
                reference_number: '2026-300-456',
            })

            const text = wrapper.text()
            const upperText = upper(text)

            // Regression guard: status 2 previously rendered the qualified
            // layout, including "CONGRATULATIONS" and "Status: Qualified".
            expect(upperText).toContain('WAITLISTED')
            expect(upperText).toContain('YOUR APPLICATION')
            expect(upperText).not.toContain('CONGRATULATIONS')
            expect(upperText).not.toContain('QUALIFIED')
            expect(text).not.toContain('You’re Qualified')
        })

        it('shows the applicant name and reference number', async () => {
            wrapper = await mountWithResult({
                waitlisted: true,
                full_name: 'Maria Santos',
                reference_number: '2026-300-456',
            })

            expect(wrapper.text()).toContain('Maria Santos')
            expect(wrapper.text()).toContain('2026-300-456')
        })

        it('does not offer the interview-slot confirmation flow', async () => {
            wrapper = await mountWithResult({
                waitlisted: true,
                full_name: 'Maria Santos',
                reference_number: '2026-300-456',
            })

            wrapper.findAll('button').forEach((button) => {
                expect(button.text()).not.toContain('Confirm Interview Slot')
            })
        })

        it('does not show the qualified next-steps section', async () => {
            wrapper = await mountWithResult({
                waitlisted: true,
                full_name: 'Maria Santos',
                reference_number: '2026-300-456',
            })

            expect(upper(wrapper.text())).not.toContain('WHAT HAPPENS NEXT')
        })
    })

    describe('Result Display - Waitlisted Below Cut-off Status', () => {
        it('is distinguishable from the standard waitlist', async () => {
            wrapper = await mountWithResult({
                waitlisted_below_cutoff: true,
                full_name: 'Ana Reyes',
                reference_number: '2026-400-111',
            })

            const text = wrapper.text()
            const upperText = upper(text)

            expect(upperText).toContain('WAITLISTED')
            // The qualifier that separates this state from the standard waitlist.
            expect(upperText).toContain('BELOW CUT-OFF')
            expect(text).toContain('Top 500 requirement')
            expect(upperText).not.toContain('CONGRATULATIONS')
            expect(upperText).not.toContain('QUALIFIED')
        })

        it('keeps the full existing backend copy for this status', async () => {
            wrapper = await mountWithResult({
                waitlisted_below_cutoff: true,
                full_name: 'Ana Reyes',
                reference_number: '2026-400-111',
            })

            const text = wrapper.text()
            expect(text).toContain('first-come, first-served')
            expect(text).toContain('still consider your admission options')
        })

        it('does not offer the interview-slot confirmation flow', async () => {
            wrapper = await mountWithResult({
                waitlisted_below_cutoff: true,
                full_name: 'Ana Reyes',
                reference_number: '2026-400-111',
            })

            wrapper.findAll('button').forEach((button) => {
                expect(button.text()).not.toContain('Confirm Interview Slot')
            })
        })
    })

    describe('Result Display - No Record Found', () => {
        it('renders the no-record state instead of an admission result', async () => {
            wrapper = await mountWithResult({
                found: false,
                qualified: false,
                first_name: 'Juan',
                last_name: 'Dela Cruz',
                message: 'no_record',
            })

            const text = wrapper.text()
            expect(text).toContain('We Couldn’t Find Your Record')
            expect(upper(text)).not.toContain('ADMISSION RESULT')
            expect(upper(text)).not.toContain('WAITLISTED')
            expect(upper(text)).not.toContain('QUALIFIED')
        })

        it('provides the existing troubleshooting guidance', async () => {
            wrapper = await mountWithResult({
                found: false,
                qualified: false,
                first_name: 'Juan',
                last_name: 'Dela Cruz',
                message: 'no_record',
            })

            const text = wrapper.text()
            expect(text).toContain('The reference number or name was entered incorrectly')
            expect(text).toContain('Your results have not yet been uploaded to the system')
            expect(text).toContain('Admission and Registration Office')
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
            expect(submitButton.text()).toBe('Check My Result')
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

            // "Check Another Result" returns to the lookup state and clears inputs
            const resetButton = wrapper
                .findAll('button')
                .find((b) => b.text().includes('Check Another Result'))
            expect(resetButton).toBeTruthy()

            await resetButton.trigger('click')
            await wrapper.vm.$nextTick()

            expect(wrapper.find('form').exists()).toBe(true)
            expect(wrapper.find('input#referenceNumber').element.value).toBe('')
            expect(wrapper.find('input#firstName').element.value).toBe('')
            expect(wrapper.find('input#lastName').element.value).toBe('')
            expect(wrapper.text()).not.toContain('Juan')
        })

        it('clears validation errors when starting a new lookup', async () => {
            wrapper = mount(CheckStatus)

            mockFetch.mockResolvedValueOnce({
                status: 422,
                json: async () => ({ errors: { referenceNumber: ['Required'] } }),
            })
            await wrapper.find('form').trigger('submit')
            await flushPromises()

            expect(wrapper.find('#referenceNumber-error').exists()).toBe(true)

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

            // A new submission drops the stale validation errors
            expect(wrapper.find('#referenceNumber-error').exists()).toBe(false)
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

    describe('IDP Reminder Modal', () => {
        /**
         * Drive the page all the way to the IDP reminder modal.
         *
         * Real timers are used on purpose: the modal and the success modal are
         * wrapped in <Transition>, and under fake timers the leave transition
         * never settles, so the nodes linger in the DOM and assertions about
         * "closed" would be meaningless.
         */
        async function openIdpReminder() {
            const w = mount(CheckStatus, { attachTo: document.body })
            mockFetch.mockResolvedValueOnce({
                status: 200,
                json: async () => ({
                    qualified: true,
                    full_name: 'Test User',
                    reference_number: '2026-001',
                }),
            })
            await w.find('form').trigger('submit')
            await flushPromises()

            await w.find('button:not([type="submit"])').trigger('click')
            await new Promise((resolve) => setTimeout(resolve, 900)) // confirmation delay

            // Closing the success modal surfaces the IDP reminder
            w.findComponent(SlotConfirmationSuccessModal).vm.$emit('close')
            await w.vm.$nextTick()
            return w
        }

        const DIALOG = '[aria-labelledby="idp-modal-title"]'

        it('exposes proper dialog semantics', async () => {
            wrapper = await openIdpReminder()

            const dialog = wrapper.find(DIALOG)
            expect(dialog.exists()).toBe(true)
            expect(dialog.attributes('role')).toBe('dialog')
            expect(dialog.attributes('aria-modal')).toBe('true')
            expect(wrapper.find('#idp-modal-title').text()).toBe('Before You Proceed')
            expect(wrapper.find('#idp-modal-desc').exists()).toBe(true)
        })

        it('moves focus into the dialog so Escape is reachable', async () => {
            wrapper = await openIdpReminder()

            // The Escape handler lives on the dialog subtree, so the keypress
            // only reaches it once focus is inside.
            expect(
                wrapper.find(DIALOG).element.contains(document.activeElement)
            ).toBe(true)
        })

        it('closes the reminder on Escape', async () => {
            wrapper = await openIdpReminder()
            expect(wrapper.find(DIALOG).exists()).toBe(true)

            await wrapper.find(DIALOG).trigger('keydown', { key: 'Escape' })
            await new Promise((resolve) => setTimeout(resolve, 250)) // leave transition

            expect(wrapper.find(DIALOG).exists()).toBe(false)
        })

        it('closes the reminder when the backdrop is clicked', async () => {
            wrapper = await openIdpReminder()

            await wrapper.find(`${DIALOG} > div`).trigger('click')
            await new Promise((resolve) => setTimeout(resolve, 250)) // leave transition

            expect(wrapper.find(DIALOG).exists()).toBe(false)
        })

        it('closes the reminder via the Cancel button', async () => {
            wrapper = await openIdpReminder()

            const cancel = wrapper.findAll('button').find((b) => b.text() === 'Cancel')
            expect(cancel).toBeTruthy()
            await cancel.trigger('click')
            await new Promise((resolve) => setTimeout(resolve, 250)) // leave transition

            expect(wrapper.find(DIALOG).exists()).toBe(false)
        })
    })

    describe('Slot Confirmation Flow', () => {
        it('shows loading state on the confirm button while confirming', async () => {
            wrapper = await mountWithResult({
                qualified: true,
                full_name: 'Test User',
                reference_number: '2026-001',
            })

            const confirmButton = wrapper.find('button:not([type="submit"])')
            await confirmButton.trigger('click')
            await wrapper.vm.$nextTick()

            // Check for loading state during confirmation
            expect(confirmButton.text()).toContain('Confirming')
            expect(confirmButton.attributes('disabled')).toBeDefined()
        })

        it('opens the success modal once the confirmation step completes', async () => {
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

            // Fake timers are enabled only around the confirmation delay, so the
            // fetch above still resolves against the real microtask queue.
            vi.useFakeTimers()
            try {
                const confirmButton = wrapper.find('button:not([type="submit"])')
                await confirmButton.trigger('click')

                expect(wrapper.findComponent(SlotConfirmationSuccessModal).props('show')).toBe(false)

                await vi.advanceTimersByTimeAsync(800)

                expect(wrapper.findComponent(SlotConfirmationSuccessModal).props('show')).toBe(true)
            } finally {
                vi.useRealTimers()
            }
        })
    })
})
