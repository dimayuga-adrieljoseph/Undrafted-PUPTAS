/**
 * Sidebar Component Tests
 * ========================
 * Comprehensive test suite for the navigation Sidebar component
 * 
 * Tests cover:
 * - Sidebar rendering with navigation items
 * - Mobile responsive behavior (drawer open/close)
 * - Desktop collapse/expand functionality
 * - Active route highlighting
 * - Dropdown group interactions
 * - Hover and pin behavior
 * - Logout functionality
 * - Role-based menu visibility
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Sidebar from '@/Components/Sidebar.vue'

// Mock useSidebarNavigation composable
vi.mock('@/Composables/useSidebarNavigation', () => ({
    useSidebarNavigation: () => ({
        isRouteActive: vi.fn((routes) => routes.includes('dashboard')),
        getNavigation: vi.fn(() => ({
            value: [
                {
                    key: 'dashboard',
                    label: 'Dashboard',
                    icon: 'tachometer-alt',
                    route: 'dashboard',
                    activeRoutes: ['dashboard'],
                },
                {
                    key: 'applicants',
                    label: 'Applicants',
                    icon: 'users',
                    route: 'applicants.index',
                    activeRoutes: ['applicants.index', 'applicants.show'],
                },
                {
                    key: 'passers',
                    label: 'Test Passers',
                    icon: 'graduation-cap',
                    children: [
                        {
                            key: 'passers-upload',
                            label: 'Upload',
                            icon: 'upload',
                            route: 'passers.upload',
                            activeRoutes: ['passers.upload'],
                        },
                        {
                            key: 'passers-list',
                            label: 'List',
                            icon: 'list',
                            route: 'passers.list',
                            activeRoutes: ['passers.list'],
                        },
                    ],
                },
            ],
        })),
    }),
}))

// Mock Inertia router
vi.mock('@inertiajs/vue3', () => ({
    router: {
        post: vi.fn(),
    },
    usePage: () => ({
        props: {
            value: {
                auth: { user: { id: 1, name: 'Test User' } },
            },
        },
    }),
    Link: {
        template: '<a><slot /></a>',
        props: ['href'],
    },
}))

describe('Sidebar Component', () => {
    let wrapper

    // Helper to create mount config with common mocks and stubs
    const createMountConfig = (overrides = {}) => ({
        global: {
            mocks: {
                route: (name) => `/${name}`,
            },
            stubs: {
                NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                ApplicationMark: { template: '<div>Logo</div>' },
                FontAwesomeIcon: { template: '<i />', props: ['icon'] },
            },
        },
        ...overrides,
    })

    beforeEach(() => {
        // Reset window size to desktop
        Object.defineProperty(window, 'innerWidth', {
            writable: true,
            configurable: true,
            value: 1024,
        })

        vi.clearAllMocks()
    })

    afterEach(() => {
        if (wrapper) {
            wrapper.unmount()
        }
    })

    describe('Basic Rendering', () => {
        it('renders the sidebar component', () => {
            wrapper = mount(Sidebar, createMountConfig({
                props: { variant: 'default' },
            }))

            expect(wrapper.find('aside').exists()).toBe(true)
            expect(wrapper.find('[role="navigation"]').exists()).toBe(true)
        })

        it('renders navigation items from config', () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default' },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            expect(wrapper.text()).toContain('Dashboard')
            expect(wrapper.text()).toContain('Applicants')
            expect(wrapper.text()).toContain('Test Passers')
        })

        it('renders default header with logo and title', () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default' },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>PUP Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            expect(wrapper.text()).toContain('PUP Portal')
            expect(wrapper.text()).toContain('Management System')
        })

        it('renders logout button in footer', () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default' },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            expect(wrapper.text()).toContain('Logout')
            expect(wrapper.find('button[type="submit"]').exists()).toBe(true)
        })
    })

    describe('Desktop Behavior', () => {
        beforeEach(() => {
            // Ensure desktop viewport
            Object.defineProperty(window, 'innerWidth', {
                writable: true,
                configurable: true,
                value: 1024,
            })
        })

        it('starts in collapsed state when collapsible="icon"', () => {
            // Clear localStorage to ensure we start unpinned
            if (typeof localStorage !== 'undefined') {
                localStorage.setItem('sidebar-open', 'false')
            }
            
            wrapper = mount(Sidebar, {
                props: { variant: 'default', collapsible: 'icon', open: false },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            const sidebar = wrapper.find('aside')
            expect(sidebar.attributes('data-state')).toBe('collapsed')
        })

        it('expands on hover when collapsible="icon"', async () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default', collapsible: 'icon' },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            const sidebar = wrapper.find('aside')
            await sidebar.trigger('pointerenter')
            await wrapper.vm.$nextTick()

            expect(sidebar.attributes('data-state')).toBe('expanded')
        })

        it('collapses on mouse leave when not pinned', async () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default', collapsible: 'icon', open: false },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            const sidebar = wrapper.find('aside')
            
            // Hover to expand
            await sidebar.trigger('pointerenter')
            await wrapper.vm.$nextTick()
            expect(sidebar.attributes('data-state')).toBe('expanded')

            // Leave to collapse (should collapse since not pinned)
            await sidebar.trigger('pointerleave')
            await wrapper.vm.$nextTick()
            expect(sidebar.attributes('data-state')).toBe('collapsed')
        })

        it('toggles pin state on click', async () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default', collapsible: 'icon', open: false },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            const sidebar = wrapper.find('aside')
            
            // Start collapsed
            expect(sidebar.attributes('data-state')).toBe('collapsed')

            // Click to pin (expand)
            await sidebar.trigger('click')
            await wrapper.vm.$nextTick()
            expect(sidebar.attributes('data-state')).toBe('expanded')

            // Click again to unpin (collapse)
            await sidebar.trigger('click')
            await wrapper.vm.$nextTick()
            expect(sidebar.attributes('data-state')).toBe('collapsed')
        })

        it('stays expanded when pinned even on mouse leave', async () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default', collapsible: 'icon', open: false },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            const sidebar = wrapper.find('aside')
            
            // Click to pin (expand)
            await sidebar.trigger('click')
            await wrapper.vm.$nextTick()
            expect(sidebar.attributes('data-state')).toBe('expanded')

            // Try to leave - should stay expanded because it's pinned
            await sidebar.trigger('pointerleave')
            await wrapper.vm.$nextTick()
            expect(sidebar.attributes('data-state')).toBe('expanded')
        })

        it('always stays expanded when collapsible="none"', async () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default', collapsible: 'none' },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            const sidebar = wrapper.find('aside')
            expect(sidebar.attributes('data-state')).toBe('expanded')

            // Hover/leave should not change state
            await sidebar.trigger('pointerenter')
            await sidebar.trigger('pointerleave')
            await wrapper.vm.$nextTick()
            expect(sidebar.attributes('data-state')).toBe('expanded')
        })
    })

    describe('Mobile Behavior', () => {
        beforeEach(() => {
            // Set mobile viewport
            Object.defineProperty(window, 'innerWidth', {
                writable: true,
                configurable: true,
                value: 600,
            })
        })

        it('starts closed on mobile', () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default', open: false },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            const sidebar = wrapper.find('aside')
            expect(sidebar.classes()).toContain('-translate-x-full')
        })

        it('opens when open prop is true on mobile', async () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default', open: true },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            await wrapper.vm.$nextTick()

            const sidebar = wrapper.find('aside')
            expect(sidebar.classes()).toContain('translate-x-0')
        })

        it('shows backdrop when open on mobile', async () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default', open: true },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            await wrapper.vm.$nextTick()

            // Backdrop should be visible
            const backdrop = wrapper.find('[aria-hidden="true"]')
            expect(backdrop.exists()).toBe(true)
        })

        it('shows close button on mobile', async () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default', open: true },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            await wrapper.vm.$nextTick()

            const closeButton = wrapper.find('button[aria-label="Close navigation menu"]')
            expect(closeButton.exists()).toBe(true)
        })

        it('emits update:open when close button clicked', async () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default', open: true },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            await wrapper.vm.$nextTick()

            const closeButton = wrapper.find('button[aria-label="Close navigation menu"]')
            await closeButton.trigger('click')

            expect(wrapper.emitted('update:open')).toBeTruthy()
            expect(wrapper.emitted('update:open')[0]).toEqual([false])
        })

        it('closes when backdrop is clicked', async () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default', open: true },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            await wrapper.vm.$nextTick()

            const backdrop = wrapper.find('[aria-hidden="true"]')
            await backdrop.trigger('click')

            expect(wrapper.emitted('update:open')).toBeTruthy()
            expect(wrapper.emitted('update:open')[0]).toEqual([false])
        })
    })

    describe('Navigation Groups (Dropdowns)', () => {
        it('renders group items with expand icon', () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default' },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            // Test Passers is a group item
            expect(wrapper.text()).toContain('Test Passers')
        })

        it('expands group on click', async () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default', collapsible: 'none' }, // Ensure expanded
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            // Find the group button
            const groupButtons = wrapper.findAll('button[aria-expanded]')
            expect(groupButtons.length).toBeGreaterThan(0)

            const passersButton = groupButtons[0]
            await passersButton.trigger('click')
            await wrapper.vm.$nextTick()

            // Check if dropdown children are visible
            expect(wrapper.text()).toContain('Upload')
            expect(wrapper.text()).toContain('List')
        })

        it('collapses group when sidebar collapses', async () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default', collapsible: 'icon' },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            const sidebar = wrapper.find('aside')

            // Expand sidebar and open group
            await sidebar.trigger('click') // Pin
            await wrapper.vm.$nextTick()

            const groupButtons = wrapper.findAll('button[aria-expanded]')
            if (groupButtons.length > 0) {
                await groupButtons[0].trigger('click')
                await wrapper.vm.$nextTick()
            }

            // Collapse sidebar
            await sidebar.trigger('click') // Unpin
            await wrapper.vm.$nextTick()

            // Groups should be closed when collapsed
            expect(sidebar.attributes('data-state')).toBe('collapsed')
        })
    })

    describe('Active Route Highlighting', () => {
        it('highlights active navigation item', () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default' },
                global: {
                    stubs: {
                        NavLink: { template: '<a :class="{ active }"><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            // Dashboard should be active (mocked in isRouteActive)
            const navLinks = wrapper.findAll('a')
            const activeLinks = navLinks.filter(link => link.classes('active'))
            expect(activeLinks.length).toBeGreaterThan(0)
        })
    })

    describe('Logout Functionality', () => {
        it('calls router.post with logout route on submit', async () => {
            const { router } = await import('@inertiajs/vue3')
            
            wrapper = mount(Sidebar, {
                props: { variant: 'default' },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            const logoutForm = wrapper.find('form')
            await logoutForm.trigger('submit')

            expect(router.post).toHaveBeenCalledWith(route('idp.logout'))
        })
    })

    describe('Variant Support', () => {
        it('accepts different variant props', () => {
            const variants = ['default', 'superadmin', 'record', 'interviewer', 'evaluator', 'applicant']

            variants.forEach(variant => {
                wrapper = mount(Sidebar, {
                    props: { variant },
                    global: {
                        stubs: {
                            NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                            ApplicationMark: { template: '<div>Logo</div>' },
                            FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                        },
                    },
                })

                const sidebar = wrapper.find('aside')
                expect(sidebar.attributes('data-variant')).toBe(variant)

                wrapper.unmount()
            })
        })
    })

    describe('Accessibility', () => {
        it('has proper ARIA attributes', () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default' },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            const sidebar = wrapper.find('aside')
            expect(sidebar.attributes('aria-label')).toBe('Main navigation')

            const nav = wrapper.find('[role="navigation"]')
            expect(nav.exists()).toBe(true)
        })

        it('sets aria-modal when open on mobile', async () => {
            Object.defineProperty(window, 'innerWidth', {
                writable: true,
                configurable: true,
                value: 600,
            })

            wrapper = mount(Sidebar, {
                props: { variant: 'default', open: true },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            await wrapper.vm.$nextTick()

            const sidebar = wrapper.find('aside')
            expect(sidebar.attributes('role')).toBe('dialog')
            expect(sidebar.attributes('aria-modal')).toBe('true')
        })

        it('has accessible logout button label', () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default' },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            const logoutButton = wrapper.find('button[type="submit"]')
            expect(logoutButton.attributes('aria-label')).toBe('Logout')
        })

        it('has proper aria-expanded for dropdown groups', async () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default', collapsible: 'none' },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            const groupButtons = wrapper.findAll('button[aria-expanded]')
            expect(groupButtons.length).toBeGreaterThan(0)

            groupButtons.forEach(button => {
                expect(['true', 'false']).toContain(button.attributes('aria-expanded'))
            })
        })
    })

    describe('Slots', () => {
        it('supports custom header slot', () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default' },
                slots: {
                    header: '<div class="custom-header">Custom Header</div>',
                },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            expect(wrapper.text()).toContain('Custom Header')
        })

        it('supports custom footer slot', () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default' },
                slots: {
                    footer: '<div class="custom-footer">Custom Footer</div>',
                },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            expect(wrapper.text()).toContain('Custom Footer')
        })

        it('provides state to default slot', () => {
            wrapper = mount(Sidebar, {
                props: { variant: 'default', collapsible: 'icon' },
                slots: {
                    default: '<div>State: {{ state }}</div>',
                },
                global: {
                    stubs: {
                        NavLink: { template: '<a><slot /></a>', props: ['href', 'active'] },
                        ApplicationMark: { template: '<div>Logo</div>' },
                        FontAwesomeIcon: { template: '<i />', props: ['icon'] },
                    },
                },
            })

            expect(wrapper.text()).toContain('State:')
        })
    })
})
