<!--
  Sidebar.vue
  ============
  Refactored to follow the Nuxt UI Sidebar API patterns while staying on
  the existing Laravel + Inertia.js + Vue 3 + Vite stack (no @nuxt/ui needed).

  API surface mirrors USidebar:
    - v-model:open  → on desktop: expanded/collapsed; on mobile: menu open/closed
    - collapsible   → "icon" (shrink to icons, hover/pin to expand) | "none"
    - variant       → "default" | "superadmin" | "record" | "interviewer" | "evaluator" | "applicant"
    - Slots: #header, #default ({ state }), #footer

  Behavior preserved:
    - Desktop: icon-only collapsed (w-20) → hover/click to expand (w-72)
    - Desktop: pin toggle on click keeps expanded
    - Mobile: off-canvas drawer with backdrop, close on item click / Escape / outside click
    - Body scroll lock managed by parent layouts
    - Active route highlighting via Ziggy route().current()
    - Dropdown groups (Passers, Reports) auto-open on active child route
    - Role-based menu items (superadmin, admin-or-superadmin)
    - PUP brand colors + gold active state
    - Dark mode support
    - --sidebar-width CSS variable updated for layout offset
-->
<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faTachometerAlt, faUsers, faCaretDown, faCaretRight,
    faGraduationCap, faEnvelopeOpenText, faEnvelope,
    faCalendarCheck, faSignOutAlt, faUpload, faList,
    faUserShield, faHome, faHistory, faNetworkWired,
    faChartPie, faChartLine, faFileAlt, faClock,
    faClipboardList, faCogs, faTags, faFileSignature, faComments
} from '@fortawesome/free-solid-svg-icons'

import NavLink from '@/Components/NavLink.vue'
import ApplicationMark from '@/Components/ApplicationMark.vue'
import { useSidebarNavigation } from '@/Composables/useSidebarNavigation'

library.add(
    faTachometerAlt, faUsers, faCaretDown, faCaretRight,
    faGraduationCap, faEnvelopeOpenText, faEnvelope,
    faCalendarCheck, faSignOutAlt, faUpload, faList,
    faUserShield, faHome, faHistory, faNetworkWired,
    faChartPie, faChartLine, faFileAlt, faClock,
    faClipboardList, faCogs, faTags, faFileSignature, faComments
)

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
    /**
     * Sidebar variant — controls which navigation set is rendered.
     */
    variant: {
        type: String,
        default: 'default',
        validator: (v) => ['default', 'superadmin', 'record', 'interviewer', 'evaluator', 'document_evaluator', 'applicant'].includes(v),
    },
    /**
     * Collapse behavior.
     * - "icon"  : shrinks to icon-only on desktop (hover or click to expand)
     * - "none"  : always fully visible, no collapse
     * Maps to Nuxt UI's `collapsible` prop.
     */
    collapsible: {
        type: String,
        default: 'icon',
        validator: (v) => ['icon', 'none'].includes(v),
    },
    /**
     * v-model:open — viewport-aware open state.
     * - Desktop: controls expanded (true) / collapsed (false) state
     * - Mobile:  controls drawer open (true) / closed (false) state
     * Maps to Nuxt UI's `v-model:open`.
     */
    open: {
        type: Boolean,
        default: undefined,
    },
})

const emit = defineEmits([
    'update:open',
    'close', // backwards-compat with existing layouts
])

// ─── Navigation ───────────────────────────────────────────────────────────────
const { isRouteActive, getNavigation } = useSidebarNavigation()
const navigation = computed(() => getNavigation(props.variant).value)

// ─── Viewport awareness ───────────────────────────────────────────────────────
// Initialize synchronously so computed values are correct from the start
// (important in test environments where onMounted may not fire before assertions).
const isMobile = ref(typeof window !== 'undefined' ? window.innerWidth < 768 : false)
const updateIsMobile = () => { isMobile.value = window.innerWidth < 768 }

// ─── State ────────────────────────────────────────────────────────────────────
// localStorage helpers — guarded for SSR / jsdom environments
const _getSavedOpen = () => {
    try { return typeof localStorage !== 'undefined' ? localStorage.getItem('sidebar-open') !== 'false' : true }
    catch { return true }
}
const _setSavedOpen = (val) => {
    try { if (typeof localStorage !== 'undefined') localStorage.setItem('sidebar-open', String(val)) }
    catch { /* noop */ }
}

// Internal desktop state — also writable via v-model on desktop
const _desktopExpanded = ref(props.open !== undefined ? props.open : _getSavedOpen())

// Internal mobile open state — used when parent does NOT use v-model
const _mobileOpen = ref(false)

// Resolved open states (writable, synced with v-model if provided)
const desktopExpanded = computed({
    get: () => (props.open !== undefined && !isMobile.value) ? props.open : _desktopExpanded.value,
    set: (val) => {
        _desktopExpanded.value = val
        _setSavedOpen(val)
        if (!isMobile.value) emit('update:open', val)
    },
})

const mobileOpen = computed({
    get: () => (props.open !== undefined && isMobile.value) ? props.open : _mobileOpen.value,
    set: (val) => {
        _mobileOpen.value = val
        if (isMobile.value) emit('update:open', val)
        if (!val) emit('close') // backwards-compat
    },
})

// Hover-expand state (desktop only)
const isHovered = ref(false)

// Pin state: click sidebar to keep it expanded regardless of hover
const isSidebarPinned = ref(desktopExpanded.value)

// The sidebar "state" — matches Nuxt UI's slot prop `{ state }`
// "expanded" | "collapsed"
const state = computed(() =>
    (desktopExpanded.value || isHovered.value || isSidebarPinned.value || mobileOpen.value)
        ? 'expanded'
        : 'collapsed',
)

// Whether labels/full content are visible
const isExpanded = computed(() => state.value === 'expanded')

// CSS width class
const sidebarWidthClass = computed(() =>
    isExpanded.value ? 'w-72 px-6 py-8' : 'w-20 px-4 py-8',
)

// ─── Dropdown group state ─────────────────────────────────────────────────────
const openGroups = ref({})

const toggleGroup = (key) => {
    openGroups.value[key] = !openGroups.value[key]
    // Expanding a group on desktop should also expand the sidebar
    if (!mobileOpen.value) {
        desktopExpanded.value = true
        isSidebarPinned.value = true
    }
}

const isGroupOpen = (item) => {
    const children = typeof item.children === 'object' && 'value' in item.children
        ? item.children.value
        : (item.children ?? [])
    const childActive = children.some((c) => isRouteActive(c.activeRoutes ?? []))
    return openGroups.value[item.key] ?? childActive
}

// ─── Route active helper ──────────────────────────────────────────────────────
const isItemActive = (item) => isRouteActive(item.activeRoutes ?? [item.route].filter(Boolean))

// ─── Desktop hover/pin interactions ──────────────────────────────────────────
const sidebarRef = ref(null)

const onSidebarEnter = () => {
    if (props.collapsible === 'icon') isHovered.value = true
}

const onSidebarLeave = () => {
    if (!isSidebarPinned.value) isHovered.value = false
}

const onSidebarClick = () => {
    if (props.collapsible === 'none') return
    isSidebarPinned.value = !isSidebarPinned.value
    if (isSidebarPinned.value) {
        desktopExpanded.value = true
    } else {
        desktopExpanded.value = false
        isHovered.value = false
    }
}

// ─── Mobile close handlers ────────────────────────────────────────────────────
const closeMobile = () => { mobileOpen.value = false }

const onNavItemClick = () => {
    if (mobileOpen.value) closeMobile()
}

// Escape key closes mobile drawer
const onKeydown = (e) => {
    if (e.key === 'Escape' && mobileOpen.value) closeMobile()
}

// Click outside closes desktop hover if not pinned
const onClickOutside = (event) => {
    if (!sidebarRef.value || isSidebarPinned.value) return
    if (!sidebarRef.value.contains(event.target)) {
        isHovered.value = false
    }
}

// ─── CSS variable (main content offset) ──────────────────────────────────────
const updateSidebarWidthVar = () => {
    if (typeof document !== 'undefined') {
        document.documentElement.style.setProperty(
            '--sidebar-width',
            isExpanded.value ? '18rem' : '5rem',
        )
    }
}

watch(isExpanded, updateSidebarWidthVar)

// Watch external v-model changes
watch(() => props.open, (val) => {
    if (val === undefined) return
    if (isMobile.value) {
        _mobileOpen.value = val
    } else {
        _desktopExpanded.value = val
        isSidebarPinned.value = val
    }
})

// Auto-close dropdowns when sidebar collapses to icon-only
watch(isExpanded, (expanded) => {
    if (!expanded) {
        Object.keys(openGroups.value).forEach((k) => { openGroups.value[k] = false })
    }
})

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
    updateIsMobile()
    updateSidebarWidthVar()

    window.addEventListener('resize', updateIsMobile)
    document.addEventListener('pointerdown', onClickOutside)
    document.addEventListener('keydown', onKeydown)

    try {
        const savedDark = typeof localStorage !== 'undefined' && localStorage.getItem('darkMode') === 'true'
        document.documentElement.classList.toggle('dark', savedDark)
    } catch { /* noop */ }
})

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', updateIsMobile)
        document.removeEventListener('pointerdown', onClickOutside)
        document.removeEventListener('keydown', onKeydown)
    }
})

// ─── Logout ───────────────────────────────────────────────────────────────────
const logout = () => { router.post(route('idp.logout')) }

// ─── Child resolution helper ──────────────────────────────────────────────────
const resolveChildren = (item) => {
    if (!item.children) return []
    if (typeof item.children === 'object' && 'value' in item.children) return item.children.value
    return item.children
}
</script>

<template>
    <!-- ── Mobile Backdrop ─────────────────────────────────────────────────── -->
    <Transition name="sidebar-fade">
        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-[9998] bg-black/50 md:hidden"
            aria-hidden="true"
            @click="closeMobile"
        />
    </Transition>

    <!-- ── Sidebar Container ───────────────────────────────────────────────── -->
    <!--
        Desktop: always visible, icon-only or expanded.
        Mobile:  off-canvas drawer, slides in from left.
        data-[state] attribute mirrors Nuxt UI's Sidebar state for CSS targeting.
    -->
    <aside
        ref="sidebarRef"
        :data-state="state"
        :data-collapsible="collapsible"
        :data-variant="variant"
        class="sidebar fixed left-0 top-0 h-screen z-[9999] flex flex-col text-white shadow-2xl dark:text-gray-900"
        :class="[
            sidebarWidthClass,
            'transition-transform duration-300',
            mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
            !mobileOpen ? 'max-md:invisible' : 'max-md:visible',
        ]"
        :role="mobileOpen ? 'dialog' : undefined"
        :aria-modal="mobileOpen ? 'true' : undefined"
        aria-label="Main navigation"
        @pointerenter="onSidebarEnter"
        @pointerleave="onSidebarLeave"
        @click.self.stop="onSidebarClick"
    >
        <!-- ── #header slot ──────────────────────────────────────────────── -->
        <div class="sidebar-header mb-8 flex items-center justify-between">
            <slot name="header">
                <!-- Default header: logo + title -->
                <div class="flex items-center gap-3 min-w-0">
                    <div class="sidebar-logo-container flex-shrink-0">
                        <NavLink :href="route('dashboard')" class="block" @click="onNavItemClick">
                            <ApplicationMark v-if="isExpanded" class="h-8" />
                            <div
                                v-else
                                class="w-8 h-8 rounded-full bg-gradient-to-br from-[#FFD700] to-[#FBCB77] flex items-center justify-center"
                            >
                                <span class="text-[#9E122C] font-bold text-sm dark:text-white">PUP</span>
                            </div>
                        </NavLink>
                    </div>
                    <Transition name="sidebar-label">
                        <div v-if="isExpanded" class="flex-1 min-w-0">
                            <h1 class="text-lg font-bold text-white">PUP Portal</h1>
                            <p class="text-xs text-gray-300 mt-0.5">Management System</p>
                        </div>
                    </Transition>
                </div>
            </slot>

            <!-- Mobile close button -->
            <button
                class="md:hidden flex-shrink-0 min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg bg-white/10 hover:bg-white/20 transition"
                aria-label="Close navigation menu"
                @click="closeMobile"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- ── #default slot with { state } ─────────────────────────────── -->
        <!--
            Slot prop `state` mirrors Nuxt UI: "expanded" | "collapsed"
        -->
        <div class="sidebar-content" role="navigation">
            <slot :state="state">
                <!-- Default: render navigation from config array -->
                <nav aria-label="Sidebar navigation">
                    <ul class="space-y-1" role="list">
                        <li
                            v-for="item in navigation"
                            :key="item.key"
                            role="none"
                        >
                            <!-- ── Group item (has children / dropdown) ── -->
                            <template v-if="item.children">
                                <button
                                    type="button"
                                    class="nav-item group w-full text-left cursor-pointer"
                                    :class="{ 'nav-item-active': isItemActive(item) || isGroupOpen(item) }"
                                    :aria-expanded="isGroupOpen(item)"
                                    :aria-controls="`nav-group-${item.key}`"
                                    @click.stop="toggleGroup(item.key)"
                                >
                                    <div class="nav-icon" :class="{ 'text-[#9E122C]': isItemActive(item) || isGroupOpen(item) }">
                                        <FontAwesomeIcon :icon="item.icon" class="text-lg" aria-hidden="true" />
                                    </div>
                                    <Transition name="sidebar-label">
                                        <span v-if="isExpanded" class="nav-label">{{ item.label }}</span>
                                    </Transition>
                                    <Transition name="sidebar-label">
                                        <FontAwesomeIcon
                                            v-if="isExpanded"
                                            :icon="isGroupOpen(item) ? 'caret-down' : 'caret-right'"
                                            class="text-xs text-gray-400 transition-transform duration-200 dark:text-gray-200 ml-auto"
                                            :class="{ 'rotate-90': isGroupOpen(item) }"
                                            aria-hidden="true"
                                        />
                                    </Transition>
                                </button>

                                <!-- Dropdown children -->
                                <Transition
                                    enter-active-class="transition-all duration-200 ease-out"
                                    leave-active-class="transition-all duration-150 ease-in"
                                    enter-from-class="opacity-0 max-h-0"
                                    enter-to-class="opacity-100 max-h-60"
                                    leave-from-class="opacity-100 max-h-60"
                                    leave-to-class="opacity-0 max-h-0"
                                >
                                    <ul
                                        v-show="isGroupOpen(item) && isExpanded"
                                        :id="`nav-group-${item.key}`"
                                        class="dropdown-content ml-10 mt-1 space-y-1"
                                        role="list"
                                    >
                                        <li
                                            v-for="child in resolveChildren(item)"
                                            :key="child.key"
                                            role="none"
                                        >
                                            <Link
                                                :href="route(child.route)"
                                                class="dropdown-item"
                                                :class="{ 'dropdown-item-active': isRouteActive(child.activeRoutes ?? [child.route]) }"
                                                :aria-current="isRouteActive(child.activeRoutes ?? [child.route]) ? 'page' : undefined"
                                                @click="onNavItemClick"
                                            >
                                                <FontAwesomeIcon :icon="child.icon" class="text-xs mr-2" aria-hidden="true" />
                                                {{ child.label }}
                                            </Link>
                                        </li>
                                    </ul>
                                </Transition>
                            </template>

                            <!-- ── Single nav item ── -->
                            <template v-else>
                                <NavLink
                                    :href="route(item.route)"
                                    :active="isItemActive(item)"
                                    class="nav-item group"
                                    :class="{ 'nav-item-active': isItemActive(item) }"
                                    :aria-current="isItemActive(item) ? 'page' : undefined"
                                    @click="onNavItemClick"
                                >
                                    <div class="nav-icon" :class="{ 'text-[#9E122C]': isItemActive(item) }">
                                        <FontAwesomeIcon :icon="item.icon" class="text-lg" aria-hidden="true" />
                                    </div>
                                    <Transition name="sidebar-label">
                                        <span v-if="isExpanded" class="nav-label">{{ item.label }}</span>
                                    </Transition>
                                </NavLink>
                            </template>
                        </li>
                    </ul>
                </nav>
            </slot>
        </div>

        <!-- ── #footer slot ──────────────────────────────────────────────── -->
        <div class="sidebar-footer mt-auto pt-6 border-t border-white/10">
            <slot name="footer">
                <!-- Default footer: logout button -->
                <form @submit.prevent="logout">
                    <button
                        type="submit"
                        class="nav-item group w-full text-left cursor-pointer hover:bg-red-600/20 transition-colors duration-200"
                        aria-label="Logout"
                    >
                        <div class="nav-icon text-red-300">
                            <FontAwesomeIcon icon="sign-out-alt" class="text-lg" aria-hidden="true" />
                        </div>
                        <Transition name="sidebar-label">
                            <span v-if="isExpanded" class="nav-label text-red-300">Logout</span>
                        </Transition>
                    </button>
                </form>
            </slot>
        </div>
    </aside>
</template>

<style scoped lang="postcss">
/* ── Base sidebar ──────────────────────────────────────────────────────────── */
.sidebar {
    background: linear-gradient(180deg, #9e122c 0%, #800000 100%);
    display: flex;
    flex-direction: column;
    /* Width + padding animate on expand/collapse */
    transition-property: width, padding;
    transition-duration: 300ms;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}

/* ── Navigation items ─────────────────────────────────────────────────────── */
.nav-item {
    @apply flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200
        relative overflow-hidden min-h-[44px];
}

.nav-item:not(.nav-item-active):hover {
    background: linear-gradient(90deg, rgba(255, 215, 0, 0.1) 0%, rgba(251, 203, 119, 0.1) 100%);
    transform: translateX(4px);
}

/* Active state: gold gradient, crimson text */
.nav-item-active {
    background: linear-gradient(90deg, #ffd700 0%, #fbcb77 100%);
    box-shadow: 0 4px 12px rgba(255, 215, 0, 0.25);
}

.nav-item-active .nav-label {
    @apply text-[#9E122C] font-semibold;
}

.nav-item-active .nav-icon {
    @apply text-[#9E122C];
}

/* ── Icons ────────────────────────────────────────────────────────────────── */
.nav-icon {
    @apply w-6 h-6 flex items-center justify-center text-white flex-shrink-0
        transition-colors duration-200;
}

/* ── Labels ───────────────────────────────────────────────────────────────── */
.nav-label {
    @apply flex-1 text-sm font-medium text-white transition-all duration-200
        whitespace-nowrap overflow-hidden;
}

/* ── Collapsed icon-only adjustments ─────────────────────────────────────── */
.sidebar:not(.w-72) .nav-item {
    @apply px-3 justify-center;
}

/* ── Dropdown / groups ────────────────────────────────────────────────────── */
.dropdown-content {
    @apply overflow-hidden;
}

.dropdown-item {
    @apply flex items-center px-3 py-2 text-xs rounded-lg transition-all duration-150
        text-gray-300 hover:text-white hover:bg-white/10 min-h-[36px];
}

.dropdown-item-active {
    @apply text-white bg-white/20 font-medium;
}

/* ── Scrollable nav area ──────────────────────────────────────────────────── */
.sidebar-content {
    @apply flex-1 overflow-y-auto overflow-x-hidden;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
}

.sidebar-content::-webkit-scrollbar {
    width: 4px;
}
.sidebar-content::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar-content::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 4px;
}
.sidebar-content::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

/* ── Short-screen adjustments ────────────────────────────────────────────── */
@media (max-height: 640px) {
    .sidebar {
        padding-top: 1rem;
        padding-bottom: 1rem;
    }
    .nav-item {
        @apply py-2;
    }
}

/* ── Dark mode ────────────────────────────────────────────────────────────── */
.dark .sidebar {
    background: linear-gradient(180deg, #7a0e23 0%, #600000 100%);
}

/* ── Transitions ──────────────────────────────────────────────────────────── */

/* Backdrop fade */
.sidebar-fade-enter-active,
.sidebar-fade-leave-active {
    transition: opacity 0.3s ease;
}
.sidebar-fade-enter-from,
.sidebar-fade-leave-to {
    opacity: 0;
}

/* Label slide-fade */
.sidebar-label-enter-active {
    transition: opacity 150ms ease 100ms, transform 200ms ease 100ms;
}
.sidebar-label-leave-active {
    transition: opacity 100ms ease, transform 150ms ease;
}
.sidebar-label-enter-from,
.sidebar-label-leave-to {
    opacity: 0;
    transform: translateX(-6px);
}
</style>
