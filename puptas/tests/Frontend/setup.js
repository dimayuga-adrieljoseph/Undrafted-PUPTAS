/**
 * Frontend Test Setup
 * ====================
 * Global Vitest configuration for Vue component testing
 * 
 * Provides:
 * - Vue Test Utils integration
 * - Global component stubs (Inertia Head, Link)
 * - Router mocks
 * - JSDOM environment setup
 */

import { config } from '@vue/test-utils'
import { vi } from 'vitest'

// Mock Inertia Head Manager
const mockHeadManager = {
    forceUpdate: vi.fn(),
    update: vi.fn(),
    createProvider: () => ({
        update: vi.fn(),
        disconnect: vi.fn(),
        install(app) {
            app.config.globalProperties.$headManager = mockHeadManager
        },
    }),
}

global.headManager = mockHeadManager

// Mock Inertia.js global components
config.global.stubs = {
    Head: {
        template: '<div data-test="head-stub"><slot /></div>',
    },
    Link: {
        template: '<a data-test="link-stub"><slot /></a>',
        props: ['href'],
    },
}

// Mock Inertia.js router
config.global.mocks = {
    $headManager: mockHeadManager,
    route: (name, params) => {
        const routes = {
            'dashboard': '/dashboard',
            'idp.logout': '/logout',
            'applicant.dashboard': '/applicant-dashboard',
            'applicant.profile': '/applicant-profile',
        }
        return routes[name] || `/${name}`
    },
    $page: {
        props: {
            auth: {
                user: {
                    id: 1,
                    firstname: 'Test',
                    lastname: 'User',
                    email: 'test@example.com',
                    name: 'Test User',
                    role_id: 1,
                    role: {
                        id: 1,
                        name: 'Admin'
                    }
                },
            },
            // Common props used across role-based pages
            users: [],
            filters: {},
            flash: {},
            programs: [],
            strands: [],
            logTypes: ['SYSTEM', 'AUDIT', 'SECURITY'],
            logCounts: { SYSTEM: 0, AUDIT: 0, SECURITY: 0 },
            // For layouts/composables
            stage: 'document_evaluator',
        },
    },
}

// Mock route() helper (Ziggy)
global.route = vi.fn((name, params) => {
    const routes = {
        'dashboard': '/dashboard',
        'idp.logout': '/logout',
        'applicant.dashboard': '/applicant-dashboard',
        'applicant.profile': '/applicant-profile',
    }
    return routes[name] || `/${name}`
})

// Mock window.matchMedia for responsive tests
Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation(query => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
    })),
})

// Mock localStorage
const localStorageMock = {
    getItem: vi.fn(),
    setItem: vi.fn(),
    removeItem: vi.fn(),
    clear: vi.fn(),
}
global.localStorage = localStorageMock

// Mock document.querySelector for meta tags
document.querySelector = vi.fn((selector) => {
    if (selector === 'meta[name="csrf-token"]') {
        return { content: 'mock-csrf-token' }
    }
    return null
})

// Ensure body element exists for Teleport components (modals)
if (!document.body) {
    document.body = document.createElement('body')
}

// Mock IntersectionObserver for components that use viewport detection
global.IntersectionObserver = class IntersectionObserver {
    constructor(callback) {
        this.callback = callback
    }
    observe() {
        // Immediately trigger callback with isIntersecting: true
        this.callback([{ isIntersecting: true }])
    }
    unobserve() {}
    disconnect() {}
}

// Mock ResizeObserver for responsive components
global.ResizeObserver = class ResizeObserver {
    constructor(callback) {
        this.callback = callback
    }
    observe() {
        // Trigger callback immediately with mock entry
        this.callback([{ contentRect: { width: 1024, height: 768 } }])
    }
    unobserve() {}
    disconnect() {}
}

// Mock asset imports at the module level
vi.mock('/assets/images/pup_taguig_logo.png', () => ({ default: '/mock-pup-taguig-logo.png' }))
vi.mock('/assets/images/pup_logo.png', () => ({ default: '/mock-pup-logo.png' }))
vi.mock('*.png', () => ({ default: '/mock-image.png' }), { virtual: true })
vi.mock('*.jpg', () => ({ default: '/mock-image.jpg' }), { virtual: true })
vi.mock('*.svg', () => ({ default: '/mock-image.svg' }), { virtual: true })
