<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faTachometerAlt,
    faUsers,
    faCaretDown,
    faCaretRight,
    faCog,
    faGraduationCap,
    faPencilAlt,
    faEnvelopeOpenText,
    faCalendarCheck,
    faUserGroup,
    faMoon,
    faSun,
    faSignOutAlt,
    faUpload,
    faList,
    faWrench,
    faUserShield,
    faHome,
    faUserCircle,
} from '@fortawesome/free-solid-svg-icons'

import NavLink from '@/Components/NavLink.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import ApplicationMark from '@/Components/ApplicationMark.vue'

library.add(
    faTachometerAlt,
    faUsers,
    faCaretDown,
    faCaretRight,
    faCog,
    faGraduationCap,
    faPencilAlt,
    faEnvelopeOpenText,
    faCalendarCheck,
    faUserGroup,
    faMoon,
    faSun,
    faSignOutAlt,
    faUpload,
    faList,
    faWrench,
    faUserShield,
    faHome,
    faUserCircle
)

const page = usePage()
const user = computed(() => page.props.auth?.user ?? null)

const props = defineProps({
    variant: {
        type: String,
        default: 'default',
    },
})

/* ---------------- STATE ---------------- */
const isSidebarOpen = ref(false)
const isSidebarPinned = ref(false)
const sidebarRef = ref(null)

const isUserMenuOpen = ref(false)
const isPasserDropdownOpen = ref(false)
const isMaintenanceDropdownOpen = ref(false)
const isDarkMode = ref(false)

/* ---------------- HELPERS ---------------- */
const isActiveRoute = (name) => route().current(name)

const isAnyDropdownOpen = computed(() =>
    isUserMenuOpen.value ||
    isPasserDropdownOpen.value ||
    isMaintenanceDropdownOpen.value
)

const sidebarWidthClass = computed(() =>
    isSidebarOpen.value ? 'w-72 px-6 py-8' : 'w-20 px-4 py-8'
)

/* ---------------- ROUTE ACTIVE ---------------- */
const isActiveRouteFor = (routeNames = []) =>
    computed(() => routeNames.some(name => isActiveRoute(name)))

const isDashboardActive = isActiveRouteFor([
    'dashboard',
    'record.dashboard',
    'medical.dashboard',
    'interviewer.dashboard',
    'evaluator.dashboard',
    'applicant.dashboard',
])

const isApplicationsActive = isActiveRouteFor([
    'applications',
    'recordstaff.applications',
    'medical.applications',
    'interviewer.applications',
    'evaluator.applications',
])

const isScheduleActive = isActiveRouteFor(['schedules.index'])
const isUploadFormActive = isActiveRouteFor(['upload.form'])
const isListPassersActive = isActiveRouteFor(['lists'])
const isProgramsActive = isActiveRouteFor(['programs.index'])
const isManageActive = isActiveRouteFor(['users.index'])
const isAssignActive = isActiveRouteFor(['admin.users.create'])
const isUserSettingsActive = isActiveRouteFor([
    'profile.show',
    'api-tokens.index'
])

/* ---------------- INTERACTION ---------------- */
const onSidebarEnter = () => (isSidebarOpen.value = true)

const onSidebarLeave = () => {
    if (!isAnyDropdownOpen.value && !isSidebarPinned.value) {
        isSidebarOpen.value = false
    }
}

const pinSidebar = () => {
    isSidebarPinned.value = !isSidebarPinned.value
    if (isSidebarPinned.value) isSidebarOpen.value = true
}

const togglePasserMenu = () => {
    isPasserDropdownOpen.value = !isPasserDropdownOpen.value
    isSidebarOpen.value = true
}

const toggleMaintenanceMenu = () => {
    isMaintenanceDropdownOpen.value = !isMaintenanceDropdownOpen.value
    isSidebarOpen.value = true
}

const toggleUserMenu = () => {
    isUserMenuOpen.value = !isUserMenuOpen.value
    isSidebarOpen.value = true
}

const toggleDarkMode = () => {
    isDarkMode.value = !isDarkMode.value
    document.documentElement.classList.toggle('dark', isDarkMode.value)
    localStorage.setItem('darkMode', isDarkMode.value)
}

const logout = () => {
    router.post(route('logout'))
}

/* ---------------- CLICK OUTSIDE ---------------- */
const onClickOutside = (event) => {
    if (!sidebarRef.value || isSidebarPinned.value) return
    if (!sidebarRef.value.contains(event.target)) {
        isSidebarOpen.value = false
        isUserMenuOpen.value = false
        isPasserDropdownOpen.value = false
        isMaintenanceDropdownOpen.value = false
    }
}

/* ---------------- WATCH ---------------- */
watch(
    () => page.props.url,
    () => {
        isPasserDropdownOpen.value =
            isUploadFormActive.value || isListPassersActive.value
        isMaintenanceDropdownOpen.value =
            isManageActive.value || isAssignActive.value
        isUserMenuOpen.value = isUserSettingsActive.value
    },
    { immediate: true }
)

/* ---------------- LIFECYCLE ---------------- */
onMounted(() => {
    document.addEventListener('pointerdown', onClickOutside)

    const savedDark = localStorage.getItem('darkMode') === 'true'
    isDarkMode.value = savedDark
    document.documentElement.classList.toggle('dark', savedDark)

    document.documentElement.style.setProperty(
        '--sidebar-width',
        isSidebarOpen.value ? '18rem' : '5rem'
    )
})

onUnmounted(() => {
    document.removeEventListener('pointerdown', onClickOutside)
})

watch(isSidebarOpen, (val) => {
    document.documentElement.style.setProperty(
        '--sidebar-width',
        val ? '18rem' : '5rem'
    )
})
</script>

<template>
    <div
        ref="sidebarRef"
        class="sidebar fixed left-0 top-0 h-screen z-[9999]
            overflow-hidden text-white shadow-2xl transition-all
            duration-300 ease-out"
        :class="sidebarWidthClass"
        @pointerenter="onSidebarEnter"
        @pointerleave="onSidebarLeave"
        @click.self.stop="pinSidebar"
    >
        <!-- Header Section -->
        <div class="sidebar-header mb-8 flex items-center">
            <div class="flex items-center gap-3">
                <div class="sidebar-logo-container">
                    <NavLink :href="route('dashboard')" class="block">
                        <ApplicationMark
                            v-if="isSidebarOpen"
                            class="h-8"
                        />
                        <div
                            v-else
                            class="w-8 h-8 rounded-full bg-gradient-to-br
                                from-[#FFD700] to-[#FBCB77] flex items-center
                                justify-center"
                        >
                            <span class="text-[#9E122C] font-bold text-sm">
                                PUP
                            </span>
                        </div>
                    </NavLink>
                </div>
                <div v-if="isSidebarOpen" class="flex-1">
                    <h1 class="text-lg font-bold text-white">PUP Portal</h1>
                    <p class="text-xs text-gray-300 mt-0.5">
                        Management System
                    </p>
                </div>
            </div>
        </div>

        <!-- Navigation Content -->
        <div class="sidebar-content">
            <!-- ================= ADMIN VARIANT ================= -->
            <nav
                v-if="!['record', 'medical', 'interviewer', 'evaluator',
                    'applicant'].includes(props.variant)"
            >
                <ul class="space-y-2">
                    <!-- Dashboard -->
                    <li>
                        <NavLink
                            :href="route('dashboard')"
                            :active="isDashboardActive"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isDashboardActive }"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="tachometer-alt"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isSidebarOpen" class="nav-label">
                                Dashboard
                            </span>
                            <div
                                v-if="isSidebarOpen"
                                class="nav-indicator"
                                :class="{ 'active': isDashboardActive }"
                            ></div>
                        </NavLink>
                    </li>

                    <!-- Passer Management Dropdown -->
                    <li>
                        <div
                            @click.stop="togglePasserMenu"
                            class="nav-item group cursor-pointer"
                            :class="{
                                'nav-item-active': isPasserDropdownOpen ||
                                    isUploadFormActive || isListPassersActive
                            }"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon icon="users" class="text-lg" />
                            </div>
                            <span v-if="isSidebarOpen" class="nav-label">
                                Passers
                            </span>
                            <div class="flex items-center gap-2">
                                <div
                                    v-if="isSidebarOpen"
                                    class="nav-indicator"
                                    :class="{
                                        'active': isPasserDropdownOpen ||
                                            isUploadFormActive ||
                                            isListPassersActive
                                    }"
                                ></div>
                                <FontAwesomeIcon
                                    v-if="isSidebarOpen"
                                    :icon="isPasserDropdownOpen
                                        ? 'caret-down'
                                        : 'caret-right'"
                                    class="text-xs text-gray-400
                                        transition-transform duration-200"
                                    :class="{ 'rotate-90': isPasserDropdownOpen }"
                                />
                            </div>
                        </div>

                        <!-- Dropdown Content -->
                        <transition
                            enter-active-class="transition-all duration-200
                                ease-out"
                            leave-active-class="transition-all duration-150
                                ease-in"
                            enter-from-class="opacity-0 max-h-0"
                            enter-to-class="opacity-100 max-h-40"
                            leave-from-class="opacity-100 max-h-40"
                            leave-to-class="opacity-0 max-h-0"
                        >
                            <div
                                v-show="isPasserDropdownOpen && isSidebarOpen"
                                class="dropdown-content ml-10 mt-1 space-y-1"
                            >
                                <NavLink
                                    :href="route('upload.form')"
                                    class="dropdown-item"
                                    :class="{
                                        'dropdown-item-active': isUploadFormActive
                                    }"
                                >
                                    <FontAwesomeIcon
                                        icon="upload"
                                        class="text-xs mr-2"
                                    />
                                    Upload Passer
                                </NavLink>
                                <NavLink
                                    :href="route('lists')"
                                    class="dropdown-item"
                                    :class="{
                                        'dropdown-item-active': isListPassersActive
                                    }"
                                >
                                    <FontAwesomeIcon
                                        icon="list"
                                        class="text-xs mr-2"
                                    />
                                    List Passers
                                </NavLink>
                            </div>
                        </transition>
                    </li>

                    <!-- Applications -->
                    <li>
                        <NavLink
                            :href="route('applications')"
                            :active="isApplicationsActive"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isApplicationsActive }"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="envelope-open-text"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isSidebarOpen" class="nav-label">
                                Applications
                            </span>
                            <div
                                v-if="isSidebarOpen"
                                class="nav-indicator"
                                :class="{ 'active': isApplicationsActive }"
                            ></div>
                        </NavLink>
                    </li>

                    <!-- Schedules -->
                    <li>
                        <NavLink
                            :href="route('schedules.index')"
                            :active="isScheduleActive"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isScheduleActive }"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="calendar-check"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isSidebarOpen" class="nav-label">
                                Schedules
                            </span>
                            <div
                                v-if="isSidebarOpen"
                                class="nav-indicator"
                                :class="{ 'active': isScheduleActive }"
                            ></div>
                        </NavLink>
                    </li>

                    <!-- Programs -->
                    <li>
                        <NavLink
                            :href="route('programs.index')"
                            :active="isProgramsActive"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isProgramsActive }"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="graduation-cap"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isSidebarOpen" class="nav-label">
                                Programs
                            </span>
                            <div
                                v-if="isSidebarOpen"
                                class="nav-indicator"
                                :class="{ 'active': isProgramsActive }"
                            ></div>
                        </NavLink>
                    </li>

                    <!-- Maintenance Dropdown -->
                    <li>
                        <div
                            @click.stop="toggleMaintenanceMenu"
                            class="nav-item group cursor-pointer"
                            :class="{
                                'nav-item-active': isMaintenanceDropdownOpen ||
                                    isManageActive || isAssignActive
                            }"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon icon="wrench" class="text-lg" />
                            </div>
                            <span v-if="isSidebarOpen" class="nav-label">
                                Maintenance
                            </span>
                            <div class="flex items-center gap-2">
                                <div
                                    v-if="isSidebarOpen"
                                    class="nav-indicator"
                                    :class="{
                                        'active': isMaintenanceDropdownOpen ||
                                            isManageActive || isAssignActive
                                    }"
                                ></div>
                                <FontAwesomeIcon
                                    v-if="isSidebarOpen"
                                    :icon="isMaintenanceDropdownOpen
                                        ? 'caret-down'
                                        : 'caret-right'"
                                    class="text-xs text-gray-400
                                        transition-transform duration-200"
                                    :class="{
                                        'rotate-90': isMaintenanceDropdownOpen
                                    }"
                                />
                            </div>
                        </div>

                        <!-- Dropdown Content -->
                        <transition
                            enter-active-class="transition-all duration-200
                                ease-out"
                            leave-active-class="transition-all duration-150
                                ease-in"
                            enter-from-class="opacity-0 max-h-0"
                            enter-to-class="opacity-100 max-h-40"
                            leave-from-class="opacity-100 max-h-40"
                            leave-to-class="opacity-0 max-h-0"
                        >
                            <div
                                v-show="isMaintenanceDropdownOpen &&
                                    isSidebarOpen"
                                class="dropdown-content ml-10 mt-1 space-y-1"
                            >
                                <NavLink
                                    :href="route('users.index')"
                                    class="dropdown-item"
                                    :class="{
                                        'dropdown-item-active': isManageActive
                                    }"
                                >
                                    <FontAwesomeIcon
                                        icon="user-shield"
                                        class="text-xs mr-2"
                                    />
                                    Manage Users
                                </NavLink>
                                <NavLink
                                    :href="route('admin.users.create')"
                                    class="dropdown-item"
                                    :class="{
                                        'dropdown-item-active': isAssignActive
                                    }"
                                >
                                    <FontAwesomeIcon
                                        icon="user-group"
                                        class="text-xs mr-2"
                                    />
                                    Assign Program
                                </NavLink>
                            </div>
                        </transition>
                    </li>
                </ul>
            </nav>

            <!-- ================= STAFF VARIANTS ================= -->
            <nav
                v-else-if="['record', 'medical', 'interviewer', 'evaluator']
                    .includes(props.variant)"
            >
                <ul class="space-y-2">
                    <!-- Dashboard -->
                    <li>
                        <NavLink
                            :href="route(props.variant + '.dashboard')"
                            :active="isDashboardActive"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isDashboardActive }"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="tachometer-alt"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isSidebarOpen" class="nav-label">
                                Dashboard
                            </span>
                            <div
                                v-if="isSidebarOpen"
                                class="nav-indicator"
                                :class="{ 'active': isDashboardActive }"
                            ></div>
                        </NavLink>
                    </li>

                    <!-- Applications -->
                    <li>
                        <NavLink
                            :href="route(props.variant + '.applications')"
                            :active="isApplicationsActive"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isApplicationsActive }"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="envelope-open-text"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isSidebarOpen" class="nav-label">
                                Applications
                            </span>
                            <div
                                v-if="isSidebarOpen"
                                class="nav-indicator"
                                :class="{ 'active': isApplicationsActive }"
                            ></div>
                        </NavLink>
                    </li>
                </ul>
            </nav>

            <!-- ================= APPLICANT VARIANT ================= -->
            <nav v-else-if="props.variant === 'applicant'">
                <ul class="space-y-2">
                    <li>
                        <NavLink
                            :href="route('applicant.dashboard')"
                            :active="isDashboardActive"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isDashboardActive }"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon icon="home" class="text-lg" />
                            </div>
                            <span v-if="isSidebarOpen" class="nav-label">
                                Dashboard
                            </span>
                            <div
                                v-if="isSidebarOpen"
                                class="nav-indicator"
                                :class="{ 'active': isDashboardActive }"
                            ></div>
                        </NavLink>
                    </li>
                </ul>
            </nav>
        </div>

        <!-- Bottom Section -->
        <div class="sidebar-footer mt-auto pt-8 border-t border-white/10">
            <ul class="space-y-2">
                <!-- User Settings -->
                <li>
                    <div
                        @click.stop="toggleUserMenu"
                        class="nav-item group cursor-pointer"
                        :class="{
                            'nav-item-active': isUserMenuOpen ||
                                isUserSettingsActive
                        }"
                    >
                        <div class="nav-icon">
                            <FontAwesomeIcon icon="cog" class="text-lg" />
                        </div>
                        <span v-if="isSidebarOpen" class="nav-label">
                            Settings
                        </span>
                        <div class="flex items-center gap-2">
                            <div
                                v-if="isSidebarOpen"
                                class="nav-indicator"
                                :class="{
                                    'active': isUserMenuOpen ||
                                        isUserSettingsActive
                                }"
                            ></div>
                            <FontAwesomeIcon
                                v-if="isSidebarOpen"
                                :icon="isUserMenuOpen
                                    ? 'caret-down'
                                    : 'caret-right'"
                                class="text-xs text-gray-400
                                    transition-transform duration-200"
                                :class="{ 'rotate-90': isUserMenuOpen }"
                            />
                        </div>
                    </div>

                    <!-- Dropdown Content -->
                    <transition
                        enter-active-class="transition-all duration-200
                            ease-out"
                        leave-active-class="transition-all duration-150 ease-in"
                        enter-from-class="opacity-0 max-h-0"
                        enter-to-class="opacity-100 max-h-32"
                        leave-from-class="opacity-100 max-h-32"
                        leave-to-class="opacity-0 max-h-0"
                    >
                        <div
                            v-show="isUserMenuOpen && isSidebarOpen"
                            class="dropdown-content ml-10 mt-1 space-y-1"
                        >
                            <DropdownLink
                                :href="route('profile.show')"
                                class="dropdown-item"
                                :class="{
                                    'dropdown-item-active':
                                        isActiveRoute('profile.show')
                                }"
                            >
                                <FontAwesomeIcon
                                    icon="user-circle"
                                    class="text-xs mr-2"
                                />
                                Profile
                            </DropdownLink>
                            <button
                                @click="toggleDarkMode"
                                class="dropdown-item w-full text-left"
                            >
                                <FontAwesomeIcon
                                    :icon="isDarkMode ? 'sun' : 'moon'"
                                    class="text-xs mr-2"
                                />
                                {{ isDarkMode ? 'Light Mode' : 'Dark Mode' }}
                            </button>
                        </div>
                    </transition>
                </li>

                <!-- Logout -->
                <li>
                    <form @submit.prevent="logout">
                        <button
                            type="submit"
                            class="nav-item group w-full text-left
                                cursor-pointer hover:bg-red-600/20
                                transition-colors duration-200"
                        >
                            <div class="nav-icon text-red-300">
                                <FontAwesomeIcon
                                    icon="sign-out-alt"
                                    class="text-lg"
                                />
                            </div>
                            <span
                                v-if="isSidebarOpen"
                                class="nav-label text-red-300"
                            >
                                Logout
                            </span>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped>
.sidebar {
    background: linear-gradient(180deg, #9E122C 0%, #800000 100%);
    display: flex;
    flex-direction: column;
    transition-property: width, padding;
    transition-duration: 300ms;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}

/* Navigation Items */
.nav-item {
    @apply flex items-center gap-3 px-4 py-3 rounded-xl transition-all
        duration-200 relative overflow-hidden;
}

.nav-item:not(.nav-item-active):hover {
    background: linear-gradient(90deg, rgba(255, 215, 0, 0.1) 0%,
        rgba(251, 203, 119, 0.1) 100%);
    transform: translateX(4px);
}

.nav-item-active {
    background: linear-gradient(90deg, #FFD700 0%, #FBCB77 100%);
    box-shadow: 0 4px 12px rgba(255, 215, 0, 0.25);
}

.nav-item-active .nav-label {
    @apply text-[#9E122C] font-semibold;
}

.nav-item-active .nav-icon {
    @apply text-[#9E122C];
}

.nav-icon {
    @apply w-6 h-6 flex items-center justify-center text-white
        transition-colors duration-200;
}

.nav-label {
    @apply flex-1 text-sm font-medium text-white transition-all duration-200
        whitespace-nowrap overflow-hidden;
}

.nav-indicator {
    @apply w-1.5 h-1.5 rounded-full bg-white/50 transition-all duration-200;
}

.nav-indicator.active {
    @apply bg-[#9E122C] scale-125;
}

/* Dropdown Styles */
.dropdown-content {
    @apply overflow-hidden;
}

.dropdown-item {
    @apply flex items-center px-3 py-2 text-xs rounded-lg transition-all
        duration-150 text-gray-300 hover:text-white hover:bg-white/10;
}

.dropdown-item-active {
    @apply text-white bg-white/20 font-medium;
}

/* Sidebar Header */
.sidebar-header {
    @apply transition-all duration-300;
}

/* Animations for collapsed state */
.sidebar:not(.w-72) .nav-label {
    @apply opacity-0 w-0;
}

.sidebar:not(.w-72) .nav-indicator {
    @apply opacity-0;
}

.sidebar:not(.w-72) .nav-item {
    @apply px-3 justify-center;
}

/* Scrollbar Styling */
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

/* Responsive Adjustments */
@media (max-height: 640px) {
    .sidebar {
        padding-top: 1rem;
        padding-bottom: 1rem;
    }

    .nav-item {
        @apply py-2;
    }
}

/* Dark Mode Support */
.dark .sidebar {
    background: linear-gradient(180deg, #7a0e23 0%, #600000 100%);
}
</style>
