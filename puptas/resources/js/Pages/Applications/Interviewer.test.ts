/**
 * Bug Condition Exploration Test for Interviewer Failed to Load Data Fix
 * 
 * **Validates: Requirements 2.1, 2.2, 2.3, 2.4**
 * 
 * This test encodes the EXPECTED behavior (error notifications should be displayed).
 * It will FAIL on unfixed code because the snackbar component and error handling don't exist yet.
 * This failure is expected and confirms the bug exists.
 * 
 * After the fix is implemented, this test should PASS, confirming the bug is fixed.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createInertiaApp } from '@inertiajs/vue3'
import axios from 'axios'
import * as fc from 'fast-check'

// Mock axios
vi.mock('axios')

// Make axios available globally for the component
globalThis.axios = axios

// Mock Inertia
vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual('@inertiajs/vue3')
    return {
        ...actual,
        Head: { template: '<div></div>' },
        usePage: () => ({
            props: {
                users: []
            }
        })
    }
})

// Mock InterviewerLayout
vi.mock('@/Layouts/InterviewerLayout.vue', () => ({
    default: {
        template: '<div><slot /></div>'
    }
}))

// Import the component after mocks are set up
import Interviewer from './Interviewer.vue'

describe('Bug Condition Exploration: Error Notification Display', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        // Mock successful fetch for initial load
        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => []
        })
    })

    /**
     * Property 1: Bug Condition - Error Notification Display
     * 
     * For 403 errors, the system SHOULD display:
     * "Unauthorized access. Application is not at the interviewer stage."
     * 
     * This test will FAIL on unfixed code because:
     * - No snackbar component exists in the template
     * - No showSnackbar function handles error display
     * - Only console.error is called
     */
    it('should display 403 error notification when getUserFiles fails with 403', async () => {
        const wrapper = mount(Interviewer, {
            global: {
                stubs: {
                    Head: true,
                    InterviewerLayout: {
                        template: '<div><slot /></div>'
                    }
                }
            }
        })

        await flushPromises()

        // Mock axios.get to return 403 error
        const mockError = {
            response: {
                status: 403,
                data: { message: 'Unauthorized' }
            }
        }
        vi.mocked(axios.get).mockRejectedValueOnce(mockError)

        // Simulate clicking on an applicant (calls selectUser)
        const mockUser = {
            id: 1,
            firstname: 'John',
            lastname: 'Doe',
            email: 'john@example.com'
        }

        // Call selectUser directly
        await wrapper.vm.selectUser(mockUser)
        await flushPromises()

        // Expected behavior: snackbar should be visible with 403 error message
        const snackbar = wrapper.vm.snackbar
        expect(snackbar.visible).toBe(true)
        expect(snackbar.message).toBe('Unauthorized access. Application is not at the interviewer stage.')
        
        // Verify snackbar is rendered in the DOM
        const snackbarElement = wrapper.find('[data-testid="snackbar"]')
        expect(snackbarElement.exists()).toBe(true)
        expect(snackbarElement.text()).toContain('Unauthorized access. Application is not at the interviewer stage.')
    })

    /**
     * Property 1: Bug Condition - Error Notification Display
     * 
     * For non-403 errors (404, 500, network errors), the system SHOULD display:
     * "Failed to load applicant data. Please try again."
     * 
     * This test will FAIL on unfixed code for the same reasons as above.
     */
    it('should display generic error notification when getUserFiles fails with 404', async () => {
        const wrapper = mount(Interviewer, {
            global: {
                stubs: {
                    Head: true,
                    InterviewerLayout: {
                        template: '<div><slot /></div>'
                    }
                }
            }
        })

        await flushPromises()

        // Mock axios.get to return 404 error
        const mockError = {
            response: {
                status: 404,
                data: { message: 'Not Found' }
            }
        }
        vi.mocked(axios.get).mockRejectedValueOnce(mockError)

        const mockUser = {
            id: 2,
            firstname: 'Jane',
            lastname: 'Smith',
            email: 'jane@example.com'
        }

        await wrapper.vm.selectUser(mockUser)
        await flushPromises()

        // Expected behavior: snackbar should be visible with generic error message
        const snackbar = wrapper.vm.snackbar
        expect(snackbar.visible).toBe(true)
        expect(snackbar.message).toBe('Failed to load applicant data. Please try again.')
        
        const snackbarElement = wrapper.find('[data-testid="snackbar"]')
        expect(snackbarElement.exists()).toBe(true)
        expect(snackbarElement.text()).toContain('Failed to load applicant data. Please try again.')
    })

    it('should display generic error notification when getUserFiles fails with 500', async () => {
        const wrapper = mount(Interviewer, {
            global: {
                stubs: {
                    Head: true,
                    InterviewerLayout: {
                        template: '<div><slot /></div>'
                    }
                }
            }
        })

        await flushPromises()

        // Mock axios.get to return 500 error
        const mockError = {
            response: {
                status: 500,
                data: { message: 'Internal Server Error' }
            }
        }
        vi.mocked(axios.get).mockRejectedValueOnce(mockError)

        const mockUser = {
            id: 3,
            firstname: 'Bob',
            lastname: 'Johnson',
            email: 'bob@example.com'
        }

        await wrapper.vm.selectUser(mockUser)
        await flushPromises()

        // Expected behavior: snackbar should be visible with generic error message
        const snackbar = wrapper.vm.snackbar
        expect(snackbar.visible).toBe(true)
        expect(snackbar.message).toBe('Failed to load applicant data. Please try again.')
        
        const snackbarElement = wrapper.find('[data-testid="snackbar"]')
        expect(snackbarElement.exists()).toBe(true)
        expect(snackbarElement.text()).toContain('Failed to load applicant data. Please try again.')
    })

    it('should display generic error notification when getUserFiles fails with network error', async () => {
        const wrapper = mount(Interviewer, {
            global: {
                stubs: {
                    Head: true,
                    InterviewerLayout: {
                        template: '<div><slot /></div>'
                    }
                }
            }
        })

        await flushPromises()

        // Mock axios.get to throw network error (no response object)
        const mockError = new Error('Network Error')
        vi.mocked(axios.get).mockRejectedValueOnce(mockError)

        const mockUser = {
            id: 4,
            firstname: 'Alice',
            lastname: 'Williams',
            email: 'alice@example.com'
        }

        await wrapper.vm.selectUser(mockUser)
        await flushPromises()

        // Expected behavior: snackbar should be visible with generic error message
        const snackbar = wrapper.vm.snackbar
        expect(snackbar.visible).toBe(true)
        expect(snackbar.message).toBe('Failed to load applicant data. Please try again.')
        
        const snackbarElement = wrapper.find('[data-testid="snackbar"]')
        expect(snackbarElement.exists()).toBe(true)
        expect(snackbarElement.text()).toContain('Failed to load applicant data. Please try again.')
    })

    /**
     * Property-Based Test: Error Notification Display for Various Error Codes
     * 
     * This property test generates random error responses and verifies that:
     * - 403 errors display the specific unauthorized message
     * - All other errors display the generic error message
     * 
     * Scoped PBT Approach: We scope the property to concrete failing cases (403, 404, 500, network)
     * to ensure reproducibility for this deterministic bug.
     */
    it('property: should display appropriate error messages for any API failure', async () => {
        await fc.assert(
            fc.asyncProperty(
                fc.oneof(
                    fc.constant(403),
                    fc.constant(404),
                    fc.constant(500),
                    fc.constant(null) // null represents network error
                ),
                async (errorCode) => {
                    const wrapper = mount(Interviewer, {
                        global: {
                            stubs: {
                                Head: true,
                                InterviewerLayout: {
                                    template: '<div><slot /></div>'
                                }
                            }
                        }
                    })

                    await flushPromises()

                    // Mock the error based on errorCode
                    if (errorCode === null) {
                        // Network error
                        vi.mocked(axios.get).mockRejectedValueOnce(new Error('Network Error'))
                    } else {
                        // HTTP error
                        vi.mocked(axios.get).mockRejectedValueOnce({
                            response: {
                                status: errorCode,
                                data: { message: 'Error' }
                            }
                        })
                    }

                    const mockUser = {
                        id: Math.floor(Math.random() * 1000),
                        firstname: 'Test',
                        lastname: 'User',
                        email: 'test@example.com'
                    }

                    await wrapper.vm.selectUser(mockUser)
                    await flushPromises()

                    // Verify snackbar is visible
                    const snackbar = wrapper.vm.snackbar
                    expect(snackbar.visible).toBe(true)

                    // Verify correct message based on error type
                    if (errorCode === 403) {
                        expect(snackbar.message).toBe('Unauthorized access. Application is not at the interviewer stage.')
                    } else {
                        expect(snackbar.message).toBe('Failed to load applicant data. Please try again.')
                    }

                    // Verify snackbar is rendered in DOM
                    const snackbarElement = wrapper.find('[data-testid="snackbar"]')
                    expect(snackbarElement.exists()).toBe(true)

                    wrapper.unmount()
                }
            ),
            { numRuns: 10 } // Run 10 times with different error codes
        )
    })
})

/**
 * Preservation Property Tests for Interviewer Failed to Load Data Fix
 * 
 * **Validates: Requirements 3.1, 3.2, 3.3, 3.4**
 * 
 * These tests verify that successful data loading behavior works correctly on UNFIXED code.
 * They establish the baseline behavior that must be preserved after the fix is implemented.
 * 
 * EXPECTED OUTCOME: These tests should PASS on unfixed code, confirming the baseline behavior.
 */

describe('Preservation: Successful Data Loading', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        // Mock successful fetch for initial load
        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => []
        })
    })

    /**
     * Property 2: Preservation - Successful Data Loading
     * 
     * When API call succeeds, the system SHOULD:
     * - Populate selectedUser with correct data
     * - Populate selectedUserFiles with uploaded files
     * - NOT display any error notification
     * 
     * This test should PASS on unfixed code, establishing the baseline.
     */
    it('should successfully load applicant data when API call succeeds', async () => {
        const wrapper = mount(Interviewer, {
            global: {
                stubs: {
                    Head: true,
                    InterviewerLayout: {
                        template: '<div><slot /></div>'
                    }
                }
            }
        })

        await flushPromises()

        // Mock successful API response
        const mockApiResponse = {
            data: {
                user: {
                    id: 1,
                    firstname: 'John',
                    lastname: 'Doe',
                    email: 'john@example.com',
                    application: {
                        id: 100,
                        program: {
                            id: 10,
                            code: 'BSCS',
                            name: 'Bachelor of Science in Computer Science',
                            slots: 30
                        },
                        processes: [
                            {
                                stage: 'interviewer',
                                status: 'in_progress',
                                notes: null,
                                created_at: '2024-01-01T00:00:00Z'
                            }
                        ]
                    },
                    grades: {
                        mathematics: 95,
                        science: 92,
                        english: 90
                    }
                },
                uploadedFiles: {
                    file10Front: 'https://example.com/file10front.jpg',
                    file10: 'https://example.com/file10.jpg',
                    psa: 'https://example.com/psa.pdf'
                }
            }
        }

        // Mock axios.get for getUserFiles
        vi.mocked(axios.get).mockResolvedValueOnce(mockApiResponse)

        // Mock fetchPrograms call
        vi.mocked(axios.get).mockResolvedValueOnce({
            data: {
                programs: [
                    { id: 10, code: 'BSCS', name: 'Bachelor of Science in Computer Science', slots: 30 },
                    { id: 11, code: 'BSIT', name: 'Bachelor of Science in Information Technology', slots: 25 }
                ]
            }
        })

        const mockUser = {
            id: 1,
            firstname: 'John',
            lastname: 'Doe',
            email: 'john@example.com'
        }

        await wrapper.vm.selectUser(mockUser)
        await flushPromises()

        // Verify selectedUser is populated correctly
        expect(wrapper.vm.selectedUser).toBeDefined()
        expect(wrapper.vm.selectedUser.id).toBe(1)
        expect(wrapper.vm.selectedUser.firstname).toBe('John')
        expect(wrapper.vm.selectedUser.lastname).toBe('Doe')
        expect(wrapper.vm.selectedUser.email).toBe('john@example.com')

        // Verify application data is populated
        expect(wrapper.vm.selectedUser.application).toBeDefined()
        expect(wrapper.vm.selectedUser.application.program).toBeDefined()
        expect(wrapper.vm.selectedUser.application.program.code).toBe('BSCS')
        expect(wrapper.vm.selectedUser.application.program.name).toBe('Bachelor of Science in Computer Science')

        // Verify processes are populated
        expect(wrapper.vm.selectedUser.application.processes).toBeDefined()
        expect(wrapper.vm.selectedUser.application.processes.length).toBe(1)
        expect(wrapper.vm.selectedUser.application.processes[0].stage).toBe('interviewer')

        // Verify grades are populated
        expect(wrapper.vm.selectedUser.grades).toBeDefined()
        expect(wrapper.vm.selectedUser.grades.mathematics).toBe(95)
        expect(wrapper.vm.selectedUser.grades.science).toBe(92)
        expect(wrapper.vm.selectedUser.grades.english).toBe(90)

        // Verify selectedUserFiles is populated
        expect(wrapper.vm.selectedUserFiles).toBeDefined()
        expect(wrapper.vm.selectedUserFiles.file10Front).toBe('https://example.com/file10front.jpg')
        expect(wrapper.vm.selectedUserFiles.file10).toBe('https://example.com/file10.jpg')
        expect(wrapper.vm.selectedUserFiles.psa).toBe('https://example.com/psa.pdf')

        // Verify NO snackbar is displayed for successful load
        expect(wrapper.vm.snackbar.visible).toBe(false)
    })

    /**
     * Property 2: Preservation - Closing Applicant Card
     * 
     * When user closes the applicant card, the system SHOULD:
     * - Reset selectedUser to null
     * 
     * This test should PASS on unfixed code.
     */
    it('should reset selectedUser when closing applicant card', async () => {
        const wrapper = mount(Interviewer, {
            global: {
                stubs: {
                    Head: true,
                    InterviewerLayout: {
                        template: '<div><slot /></div>'
                    }
                }
            }
        })

        await flushPromises()

        // First, set up a selected user
        const mockApiResponse = {
            data: {
                user: {
                    id: 1,
                    firstname: 'John',
                    lastname: 'Doe',
                    email: 'john@example.com',
                    application: {
                        program: { id: 10, code: 'BSCS', name: 'Computer Science', slots: 30 },
                        processes: []
                    },
                    grades: { mathematics: 95, science: 92, english: 90 }
                },
                uploadedFiles: {}
            }
        }

        vi.mocked(axios.get).mockResolvedValueOnce(mockApiResponse)
        vi.mocked(axios.get).mockResolvedValueOnce({ data: { programs: [] } })

        const mockUser = {
            id: 1,
            firstname: 'John',
            lastname: 'Doe',
            email: 'john@example.com'
        }

        await wrapper.vm.selectUser(mockUser)
        await flushPromises()

        // Verify user is selected
        expect(wrapper.vm.selectedUser).not.toBeNull()

        // Close the user card
        wrapper.vm.closeUserCard()

        // Verify selectedUser is reset to null
        expect(wrapper.vm.selectedUser).toBeNull()
    })

    /**
     * Property 2: Preservation - Error Handling State Cleanup
     * 
     * When API call fails, the system SHOULD:
     * - Set selectedUser to null
     * - Clear selectedUserFiles
     * 
     * This test should PASS on unfixed code (state cleanup works, just no notification).
     */
    it('should set selectedUser to null and clear selectedUserFiles on error', async () => {
        const wrapper = mount(Interviewer, {
            global: {
                stubs: {
                    Head: true,
                    InterviewerLayout: {
                        template: '<div><slot /></div>'
                    }
                }
            }
        })

        await flushPromises()

        // Mock axios.get to return error
        const mockError = {
            response: {
                status: 500,
                data: { message: 'Internal Server Error' }
            }
        }
        vi.mocked(axios.get).mockRejectedValueOnce(mockError)

        const mockUser = {
            id: 1,
            firstname: 'John',
            lastname: 'Doe',
            email: 'john@example.com'
        }

        await wrapper.vm.selectUser(mockUser)
        await flushPromises()

        // Verify state cleanup on error
        expect(wrapper.vm.selectedUser).toBeNull()
        expect(wrapper.vm.selectedUserFiles).toEqual({})
    })

    /**
     * Property 2: Preservation - fetchPrograms is Called
     * 
     * When data loads successfully, the system SHOULD:
     * - Call fetchPrograms() to populate the program dropdown
     * 
     * This test should PASS on unfixed code.
     */
    it('should call fetchPrograms after successful data load', async () => {
        const wrapper = mount(Interviewer, {
            global: {
                stubs: {
                    Head: true,
                    InterviewerLayout: {
                        template: '<div><slot /></div>'
                    }
                }
            }
        })

        await flushPromises()

        // Mock successful API response for selectUser
        const mockApiResponse = {
            data: {
                user: {
                    id: 1,
                    firstname: 'John',
                    lastname: 'Doe',
                    email: 'john@example.com',
                    application: {
                        program: { id: 10, code: 'BSCS', name: 'Computer Science', slots: 30 },
                        processes: []
                    },
                    grades: { mathematics: 95, science: 92, english: 90 }
                },
                uploadedFiles: {}
            }
        }

        // Mock successful API response for fetchPrograms
        const mockProgramsResponse = {
            data: {
                programs: [
                    { id: 10, code: 'BSCS', name: 'Bachelor of Science in Computer Science', slots: 30 },
                    { id: 11, code: 'BSIT', name: 'Bachelor of Science in Information Technology', slots: 25 }
                ]
            }
        }

        vi.mocked(axios.get).mockResolvedValueOnce(mockApiResponse)
        vi.mocked(axios.get).mockResolvedValueOnce(mockProgramsResponse)

        const mockUser = {
            id: 1,
            firstname: 'John',
            lastname: 'Doe',
            email: 'john@example.com'
        }

        await wrapper.vm.selectUser(mockUser)
        await flushPromises()

        // Verify fetchPrograms was called
        // Note: axios.get is called multiple times (user data, programs, and potentially on mount)
        expect(axios.get).toHaveBeenCalledWith('/interviewer-dashboard/programs')

        // Verify availablePrograms is populated
        expect(wrapper.vm.availablePrograms).toBeDefined()
        expect(wrapper.vm.availablePrograms.length).toBe(2)
        expect(wrapper.vm.availablePrograms[0].code).toBe('BSCS')
        expect(wrapper.vm.availablePrograms[1].code).toBe('BSIT')
    })

    /**
     * Property-Based Test: Successful Data Loading Across Various Valid Responses
     * 
     * This property test generates random valid API responses and verifies that:
     * - selectedUser is always populated correctly
     * - selectedUserFiles is always populated correctly
     * - No error notification is displayed
     * 
     * This establishes strong guarantees that successful loading works across all valid inputs.
     */
    it('property: should successfully load data for any valid API response', async () => {
        await fc.assert(
            fc.asyncProperty(
                fc.record({
                    id: fc.integer({ min: 1, max: 1000 }),
                    firstname: fc.string({ minLength: 1, maxLength: 20 }),
                    lastname: fc.string({ minLength: 1, maxLength: 20 }),
                    email: fc.emailAddress(),
                    programCode: fc.constantFrom('BSCS', 'BSIT', 'BSBA', 'BSED'),
                    programName: fc.string({ minLength: 5, maxLength: 50 }),
                    slots: fc.integer({ min: 0, max: 100 }),
                    mathGrade: fc.integer({ min: 75, max: 100 }),
                    scienceGrade: fc.integer({ min: 75, max: 100 }),
                    englishGrade: fc.integer({ min: 75, max: 100 })
                }),
                async (testData) => {
                    const wrapper = mount(Interviewer, {
                        global: {
                            stubs: {
                                Head: true,
                                InterviewerLayout: {
                                    template: '<div><slot /></div>'
                                }
                            }
                        }
                    })

                    await flushPromises()

                    // Mock successful API response with generated data
                    const mockApiResponse = {
                        data: {
                            user: {
                                id: testData.id,
                                firstname: testData.firstname,
                                lastname: testData.lastname,
                                email: testData.email,
                                application: {
                                    program: {
                                        id: testData.id,
                                        code: testData.programCode,
                                        name: testData.programName,
                                        slots: testData.slots
                                    },
                                    processes: []
                                },
                                grades: {
                                    mathematics: testData.mathGrade,
                                    science: testData.scienceGrade,
                                    english: testData.englishGrade
                                }
                            },
                            uploadedFiles: {
                                file10Front: 'https://example.com/file.jpg'
                            }
                        }
                    }

                    vi.mocked(axios.get).mockResolvedValueOnce(mockApiResponse)
                    vi.mocked(axios.get).mockResolvedValueOnce({ data: { programs: [] } })

                    const mockUser = {
                        id: testData.id,
                        firstname: testData.firstname,
                        lastname: testData.lastname,
                        email: testData.email
                    }

                    await wrapper.vm.selectUser(mockUser)
                    await flushPromises()

                    // Verify selectedUser is populated correctly
                    expect(wrapper.vm.selectedUser).toBeDefined()
                    expect(wrapper.vm.selectedUser.id).toBe(testData.id)
                    expect(wrapper.vm.selectedUser.firstname).toBe(testData.firstname)
                    expect(wrapper.vm.selectedUser.lastname).toBe(testData.lastname)
                    expect(wrapper.vm.selectedUser.email).toBe(testData.email)

                    // Verify application data
                    expect(wrapper.vm.selectedUser.application.program.code).toBe(testData.programCode)
                    expect(wrapper.vm.selectedUser.application.program.name).toBe(testData.programName)
                    expect(wrapper.vm.selectedUser.application.program.slots).toBe(testData.slots)

                    // Verify grades
                    expect(wrapper.vm.selectedUser.grades.mathematics).toBe(testData.mathGrade)
                    expect(wrapper.vm.selectedUser.grades.science).toBe(testData.scienceGrade)
                    expect(wrapper.vm.selectedUser.grades.english).toBe(testData.englishGrade)

                    // Verify selectedUserFiles is populated
                    expect(wrapper.vm.selectedUserFiles).toBeDefined()
                    expect(wrapper.vm.selectedUserFiles.file10Front).toBe('https://example.com/file.jpg')

                    // Verify NO snackbar is displayed
                    expect(wrapper.vm.snackbar.visible).toBe(false)

                    wrapper.unmount()
                }
            ),
            { numRuns: 20 } // Run 20 times with different valid data
        )
    })

    /**
     * Property 2: Preservation - UI Interactions Remain Unchanged
     * 
     * This test verifies that existing UI interactions (search, filters, pagination, sorting)
     * continue to work correctly and are not affected by the error handling changes.
     */
    it('should preserve search functionality', async () => {
        const mockUsers = [
            { id: 1, firstname: 'John', lastname: 'Doe', email: 'john@example.com', is_evaluation_completed: false, process_status: 'in_progress' },
            { id: 2, firstname: 'Jane', lastname: 'Smith', email: 'jane@example.com', is_evaluation_completed: false, process_status: 'in_progress' },
            { id: 3, firstname: 'Bob', lastname: 'Johnson', email: 'bob@example.com', is_evaluation_completed: true, process_action: 'accepted' }
        ]

        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => mockUsers
        })

        const wrapper = mount(Interviewer, {
            global: {
                stubs: {
                    Head: true,
                    InterviewerLayout: {
                        template: '<div><slot /></div>'
                    }
                }
            }
        })

        await flushPromises()

        // Verify all users are loaded
        expect(wrapper.vm.users.length).toBe(3)

        // Test search functionality
        wrapper.vm.searchQuery = 'john'
        await flushPromises()

        // Verify filtered results
        expect(wrapper.vm.filteredUsers.length).toBe(2) // John Doe and Bob Johnson
        expect(wrapper.vm.filteredUsers.some(u => u.firstname === 'John')).toBe(true)
        expect(wrapper.vm.filteredUsers.some(u => u.firstname === 'Bob')).toBe(true)
        expect(wrapper.vm.filteredUsers.some(u => u.firstname === 'Jane')).toBe(false)
    })

    it('should preserve evaluation status filter functionality', async () => {
        const mockUsers = [
            { id: 1, firstname: 'John', lastname: 'Doe', email: 'john@example.com', is_evaluation_completed: false, process_status: 'in_progress' },
            { id: 2, firstname: 'Jane', lastname: 'Smith', email: 'jane@example.com', is_evaluation_completed: false, process_status: 'in_progress' },
            { id: 3, firstname: 'Bob', lastname: 'Johnson', email: 'bob@example.com', is_evaluation_completed: true, process_action: 'accepted' }
        ]

        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => mockUsers
        })

        const wrapper = mount(Interviewer, {
            global: {
                stubs: {
                    Head: true,
                    InterviewerLayout: {
                        template: '<div><slot /></div>'
                    }
                }
            }
        })

        await flushPromises()

        // Test evaluation status filter - completed
        wrapper.vm.evaluationStatusFilter = 'completed'
        await flushPromises()

        expect(wrapper.vm.filteredUsers.length).toBe(1)
        expect(wrapper.vm.filteredUsers[0].firstname).toBe('Bob')

        // Test evaluation status filter - pending
        wrapper.vm.evaluationStatusFilter = 'pending'
        await flushPromises()

        expect(wrapper.vm.filteredUsers.length).toBe(2)
        expect(wrapper.vm.filteredUsers.some(u => u.firstname === 'John')).toBe(true)
        expect(wrapper.vm.filteredUsers.some(u => u.firstname === 'Jane')).toBe(true)
    })

    it('should preserve sorting functionality', async () => {
        const mockUsers = [
            { id: 1, firstname: 'Charlie', lastname: 'Doe', email: 'charlie@example.com', is_evaluation_completed: false, process_status: 'in_progress' },
            { id: 2, firstname: 'Alice', lastname: 'Smith', email: 'alice@example.com', is_evaluation_completed: false, process_status: 'in_progress' },
            { id: 3, firstname: 'Bob', lastname: 'Johnson', email: 'bob@example.com', is_evaluation_completed: true, process_action: 'accepted' }
        ]

        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => mockUsers
        })

        const wrapper = mount(Interviewer, {
            global: {
                stubs: {
                    Head: true,
                    InterviewerLayout: {
                        template: '<div><slot /></div>'
                    }
                }
            }
        })

        await flushPromises()

        // Test sorting by firstname ascending
        wrapper.vm.sortKey = 'firstname'
        wrapper.vm.sortAsc = true
        await flushPromises()

        const sortedUsers = wrapper.vm.filteredUsers
        expect(sortedUsers[0].firstname).toBe('Alice')
        expect(sortedUsers[1].firstname).toBe('Bob')
        expect(sortedUsers[2].firstname).toBe('Charlie')

        // Test sorting by firstname descending
        wrapper.vm.sortAsc = false
        await flushPromises()

        const sortedUsersDesc = wrapper.vm.filteredUsers
        expect(sortedUsersDesc[0].firstname).toBe('Charlie')
        expect(sortedUsersDesc[1].firstname).toBe('Bob')
        expect(sortedUsersDesc[2].firstname).toBe('Alice')
    })

    it('should preserve pagination functionality', async () => {
        // Create 25 mock users to test pagination
        const mockUsers = Array.from({ length: 25 }, (_, i) => ({
            id: i + 1,
            firstname: `User${String(i + 1).padStart(2, '0')}`, // Pad with zeros for consistent sorting
            lastname: `Last${String(i + 1).padStart(2, '0')}`,
            email: `user${i + 1}@example.com`,
            is_evaluation_completed: false,
            process_status: 'in_progress'
        }))

        global.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => mockUsers
        })

        const wrapper = mount(Interviewer, {
            global: {
                stubs: {
                    Head: true,
                    InterviewerLayout: {
                        template: '<div><slot /></div>'
                    }
                }
            }
        })

        await flushPromises()

        // Verify total pages calculation (25 users / 10 per page = 3 pages)
        expect(wrapper.vm.totalPages).toBe(3)

        // Verify first page shows 10 users
        expect(wrapper.vm.paginatedUsers.length).toBe(10)
        expect(wrapper.vm.paginatedUsers[0].id).toBe(1)

        // Navigate to page 2
        wrapper.vm.currentPage = 2
        await flushPromises()

        expect(wrapper.vm.paginatedUsers.length).toBe(10)
        expect(wrapper.vm.paginatedUsers[0].id).toBe(11)

        // Navigate to page 3
        wrapper.vm.currentPage = 3
        await flushPromises()

        expect(wrapper.vm.paginatedUsers.length).toBe(5) // Last page has 5 users
        expect(wrapper.vm.paginatedUsers[0].id).toBe(21)
    })
})
