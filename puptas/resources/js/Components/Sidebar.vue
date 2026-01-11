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
    faSignOutAlt
)

const page = usePage()
const user = computed(() => page.props.auth.user)

// Variant prop: allow different sidebar variants (e.g., 'applicant')
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
    isUserMenuOpen.value || isPasserDropdownOpen.value || isMaintenanceDropdownOpen.value
)

const sidebarWidthClass = computed(() => (isSidebarOpen.value ? 'w-72 p-6' : 'w-20 p-4'))

/* ---------------- ROUTE ACTIVE ---------------- */
// Helper function: returns a computed boolean if the current route matches any in the array
const isActiveRouteFor = (routeNames = []) =>
    computed(() => routeNames.some(name => isActiveRoute(name)))

// Example usage in sidebar
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
])

const isScheduleActive = isActiveRouteFor(['schedules.index'])

const isUploadFormActive = isActiveRouteFor(['upload.form'])
const isListPassersActive = isActiveRouteFor(['lists'])

const isProgramsActive = isActiveRouteFor(['programs.index'])

const isManageActive = isActiveRouteFor(['add_user_vue'])
const isAssignActive = isActiveRouteFor(['assign_user_vue'])

const isUserSettingsActive = isActiveRouteFor(['profile.show', 'api-tokens.index'])


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

// Local toggle used by compact variants that show a manual collapse button
const toggleLocalSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value
    // update CSS variable so layouts adjust margin automatically
    document.documentElement.style.setProperty('--sidebar-width', isSidebarOpen.value ? '18rem' : '5rem')
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
        isPasserDropdownOpen.value = isUploadFormActive.value || isListPassersActive.value

        isMaintenanceDropdownOpen.value = isManageActive.value || isAssignActive.value
        
        // Close user settings dropdown when navigating away from profile/settings pages
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
    // initialize CSS variable used by layouts to offset main content
    document.documentElement.style.setProperty('--sidebar-width', isSidebarOpen.value ? '18rem' : '5rem')
})

onUnmounted(() => {
    document.removeEventListener('pointerdown', onClickOutside)
})

// Keep the global CSS variable in sync whenever the sidebar open state changes
watch(isSidebarOpen, (val) => {
    document.documentElement.style.setProperty('--sidebar-width', val ? '18rem' : '5rem')
})
</script>

<template>
    <div
        ref="sidebarRef"
        class="sidebar fixed left-0 top-0 h-screen z-[9999] overflow-hidden text-white shadow-md transition-all duration-500 ease-in-out"
        :class="sidebarWidthClass"
        @pointerenter="onSidebarEnter"
        @pointerleave="onSidebarLeave"
        @click.self.stop="pinSidebar"
    >
        <!-- Logo -->
        <div class="mb-10 flex items-center justify-between px-4">
            <NavLink :href="route('dashboard')">
                <ApplicationMark v-if="isSidebarOpen" class="h-10" />
            </NavLink>
        </div>
        <!-- ================= RECORD VARIANT ================= -->
        <nav v-if="props.variant === 'record'">
            <ul class="space-y-6">
                <li>
                    <NavLink
                        :href="route('record.dashboard')"
                        :active="isDashboardActive"
                        class="block w-full rounded-lg transition hover:bg-[#FBCB77]"
                        :class="{
                            'active-link': isDashboardActive,
                            'flex items-center space-x-3 py-3 px-4 text-lg font-semibold': true,
                        }"
                    >
                        <div class="w-6 flex justify-center">
                            <FontAwesomeIcon
                                icon="tachometer-alt"
                                class="text-xl"
                            />
                        </div>
                        <span v-if="isSidebarOpen" class="whitespace-nowrap"
                            >My Dashboard</span
                        >
                    </NavLink>
                </li>
                <li>
                    <NavLink
                        :href="route('recordstaff.applications')"
                        :active="isApplicationsActive"
                        class="block w-full rounded-lg transition hover:bg-[#FBCB77]"
                        :class="{
                            'active-link': isApplicationsActive,
                            'flex items-center space-x-3 py-3 px-4 text-lg font-semibold': true,
                        }"
                    >
                        <div class="w-6 flex justify-center">
                            <FontAwesomeIcon
                                icon="envelope-open-text"
                                class="text-xl"
                            />
                        </div>
                        <span v-if="isSidebarOpen" class="whitespace-nowrap"
                            >Applications</span
                        >
                    </NavLink>
                </li>

                <li>
                    <div
                        @click="toggleUserMenu"
                        class="cursor-pointer flex items-center justify-between py-3 px-4 text-lg font-semibold rounded-lg hover:bg-[#FBCB77] hover:text-[#9E122C] transition"
                        :class="{
                            'active-link':
                                isUserMenuOpen ||
                                isActiveRoute('profile.show') ||
                                isActiveRoute('api-tokens.index'),
                        }"
                    >
                        <div class="flex items-center space-x-3">
                            <FontAwesomeIcon icon="cog" class="text-xl" />
                            <span v-if="isSidebarOpen">User Settings</span>
                        </div>
                        <FontAwesomeIcon
                            v-if="isSidebarOpen"
                            :icon="
                                isUserMenuOpen
                                    ? 'caret-down'
                                    : 'caret-right'
                            "
                        />
                    </div>
                    <transition name="slide-fade">
                        <div
                            v-show="isUserMenuOpen && isSidebarOpen"
                            class="ml-6 space-y-2 bg-[#EE6A43] rounded-lg mt-2"
                        >
                            <DropdownLink
                                :href="route('profile.show')"
                                class="text-[#FCECDF] py-2 px-4 block hover:bg-[#FBCB77] transition"
                                :class="{
                                    'active-link':
                                        isActiveRoute('profile.show'),
                                }"
                            >
                                Profile
                            </DropdownLink>
                            <!-- <DropdownLink 
                                v-if="$page.props.jetstream.hasApiFeatures" 
                                :href="route('api-tokens.index')" 
                                class="text-[#FCECDF] py-2 px-4 block hover:bg-[#FBCB77] transition"
                                :class="{ 'active-link': isActiveRoute('api-tokens.index') }"
                            >
                                API Tokens
                            </DropdownLink> -->
                        </div>
                    </transition>
                </li>

                <!-- <li>
                    <NavLink 
                        :href="route('programs.index')" 
                        :active="isActiveRoute('programs.index')" 
                        class="flex items-center space-x-3 py-3 px-4 text-lg font-semibold rounded-lg hover:bg-[#FBCB77] hover:text-[#9E122C] transition"
                        :class="{ 'active-link': isActiveRoute('programs.index') }"
                    >
                        <FontAwesomeIcon icon="graduation-cap" class="text-xl" /> 
                        <span v-if="isSidebarOpen">Programs</span>
                    </NavLink>
                </li> -->

                <li>
                    <form @submit.prevent="logout">
                        <button
                            type="submit"
                            class="cursor-pointer flex items-center justify-between py-3 px-4 text-lg font-semibold rounded-lg hover:bg-[#FBCB77] hover:text-[#9E122C] transition focus:outline-none w-full"
                        >
                            <div class="flex items-center space-x-3">
                                <FontAwesomeIcon
                                    icon="sign-out-alt"
                                    class="text-xl"
                                />
                                <span v-if="isSidebarOpen">Log Out</span>
                            </div>
                        </button>
                    </form>
                </li>
            </ul>
        </nav>
        <!-- ================= MEDICAL VARIANT ================= -->
        <nav v-else-if="props.variant === 'medical'">
                <ul class="space-y-6">
                    <li>
                        <NavLink
                            :href="route('medical.dashboard')"
                            :active="isDashboardActive"
                            class="block w-full rounded-lg transition hover:bg-[#FBCB77]"
                            :class="{
                                'active-link': isDashboardActive,
                                'flex items-center space-x-3 py-3 px-4 text-lg font-semibold': true,
                            }"
                        >
                            <div class="w-6 flex justify-center">
                                <FontAwesomeIcon
                                    icon="tachometer-alt"
                                    class="text-xl"
                                />
                            </div>
                            <span v-if="isSidebarOpen" class="whitespace-nowrap"
                                >My Dashboard</span
                            >
                        </NavLink>
                    </li>
                    <li>
                        <NavLink
                            :href="route('medical.applications')"
                            :active="isApplicationsActive"
                            class="block w-full rounded-lg transition hover:bg-[#FBCB77]"
                            :class="{
                                'active-link': isApplicationsActive,
                                'flex items-center space-x-3 py-3 px-4 text-lg font-semibold': true,
                            }"
                        >
                            <div class="w-6 flex justify-center">
                                <FontAwesomeIcon
                                    icon="envelope-open-text"
                                    class="text-xl"
                                />
                            </div>
                            <span v-if="isSidebarOpen" class="whitespace-nowrap"
                                >Applications</span
                            >
                        </NavLink>
                    </li>

                    <li>
                        <div
                            @click="toggleUserMenu"
                            class="cursor-pointer flex items-center justify-between py-3 px-4 text-lg font-semibold rounded-lg hover:bg-[#FBCB77] hover:text-[#9E122C] transition"
                            :class="{
                                'active-link':
                                    isUserMenuOpen ||
                                    isActiveRoute('profile.show') ||
                                    isActiveRoute('api-tokens.index'),
                            }"
                        >
                            <div class="flex items-center space-x-3">
                                <FontAwesomeIcon icon="cog" class="text-xl" />
                                <span v-if="isSidebarOpen">User Settings</span>
                            </div>
                            <FontAwesomeIcon
                                v-if="isSidebarOpen"
                                :icon="
                                    isUserMenuOpen
                                        ? 'caret-down'
                                        : 'caret-right'
                                "
                            />
                        </div>
                        <transition name="slide-fade">
                            <div
                                v-show="isUserMenuOpen && isSidebarOpen"
                                class="ml-6 space-y-2 bg-[#EE6A43] rounded-lg mt-2"
                            >
                                <DropdownLink
                                    :href="route('profile.show')"
                                    class="text-[#FCECDF] py-2 px-4 block hover:bg-[#FBCB77] transition"
                                    :class="{
                                        'active-link':
                                            isActiveRoute('profile.show'),
                                    }"
                                >
                                    Profile
                                </DropdownLink>
                            </div>
                        </transition>
                    </li>
                    <li>
                        <form @submit.prevent="logout">
                            <button
                                type="submit"
                                class="cursor-pointer flex items-center justify-between py-3 px-4 text-lg font-semibold rounded-lg hover:bg-[#FBCB77] hover:text-[#9E122C] transition focus:outline-none w-full"
                            >
                                <div class="flex items-center space-x-3">
                                    <FontAwesomeIcon
                                        icon="sign-out-alt"
                                        class="text-xl"
                                    />
                                    <span v-if="isSidebarOpen">Log Out</span>
                                </div>
                            </button>
                        </form>
                    </li>
                </ul>
        </nav>
        <!-- ================= INTERVIEWER VARIANT ================= -->
        <nav v-else-if="props.variant === 'interviewer'">
            <ul class="space-y-6">
                <li>
                    <NavLink
                        :href="route('interviewer.dashboard')"
                        :active="isDashboardActive"
                        class="block w-full rounded-lg transition hover:bg-[#FBCB77]"
                        :class="{
                            'active-link': isDashboardActive,
                            'flex items-center space-x-3 py-3 px-4 text-lg font-semibold': true,
                        }"
                    >
                        <div class="w-6 flex justify-center">
                            <FontAwesomeIcon
                                icon="tachometer-alt"
                                class="text-xl"
                            />
                        </div>
                        <span v-if="isSidebarOpen" class="whitespace-nowrap"
                            >My Dashboard</span
                        >
                    </NavLink>
                </li>
                <li>
                    <NavLink
                        :href="route('interviewer.applications')"
                        :active="isApplicationsActive"
                        class="block w-full rounded-lg transition hover:bg-[#FBCB77]"
                        :class="{
                            'active-link': isApplicationsActive,
                            'flex items-center space-x-3 py-3 px-4 text-lg font-semibold': true,
                        }"
                    >
                        <div class="w-6 flex justify-center">
                            <FontAwesomeIcon
                                icon="envelope-open-text"
                                class="text-xl"
                            />
                        </div>
                        <span v-if="isSidebarOpen" class="whitespace-nowrap"
                            >Applications</span
                        >
                    </NavLink>
                </li>

                <li>
                    <div
                        @click="toggleUserMenu"
                        class="cursor-pointer flex items-center justify-between py-3 px-4 text-lg font-semibold rounded-lg hover:bg-[#FBCB77] hover:text-[#9E122C] transition"
                        :class="{
                            'active-link':
                                isUserMenuOpen ||
                                isActiveRoute('profile.show') ||
                                isActiveRoute('api-tokens.index'),
                        }"
                    >
                        <div class="flex items-center space-x-3">
                            <FontAwesomeIcon icon="cog" class="text-xl" />
                            <span v-if="isSidebarOpen">User Settings</span>
                        </div>
                        <FontAwesomeIcon
                            v-if="isSidebarOpen"
                            :icon="
                                isUserMenuOpen
                                    ? 'caret-down'
                                    : 'caret-right'
                            "
                        />
                    </div>
                    <transition name="slide-fade">
                        <div
                            v-show="isUserMenuOpen && isSidebarOpen"
                            class="ml-6 space-y-2 bg-[#EE6A43] rounded-lg mt-2"
                        >
                            <DropdownLink
                                :href="route('profile.show')"
                                class="text-[#FCECDF] py-2 px-4 block hover:bg-[#FBCB77] transition"
                                :class="{
                                    'active-link':
                                        isActiveRoute('profile.show'),
                                }"
                            >
                                Profile
                            </DropdownLink>
                        </div>
                    </transition>
                </li>
                <li>
                    <form @submit.prevent="logout">
                        <button
                            type="submit"
                            class="cursor-pointer flex items-center justify-between py-3 px-4 text-lg font-semibold rounded-lg hover:bg-[#FBCB77] hover:text-[#9E122C] transition focus:outline-none w-full"
                        >
                            <div class="flex items-center space-x-3">
                                <FontAwesomeIcon
                                    icon="sign-out-alt"
                                    class="text-xl"
                                />
                                <span v-if="isSidebarOpen">Log Out</span>
                            </div>
                        </button>
                    </form>
                </li>
            </ul>
        </nav>
        <!-- ================= EVALUATOR VARIANT ================= -->
        <nav v-else-if="props.variant === 'evaluator'">
            <ul class="space-y-6">
                <li>
                    <NavLink
                        :href="route('evaluator.dashboard')"
                        :active="isDashboardActive"
                        class="block w-full rounded-lg transition hover:bg-[#FBCB77]"
                        :class="{
                            'active-link': isDashboardActive,
                            'flex items-center space-x-3 py-3 px-4 text-lg font-semibold': true,
                        }"
                    >
                        <div class="w-6 flex justify-center">
                            <FontAwesomeIcon
                                icon="tachometer-alt"
                                class="text-xl"
                            />
                        </div>
                        <span v-if="isSidebarOpen" class="whitespace-nowrap"
                            >My Dashboard</span
                        >
                    </NavLink>
                </li>
                <li>
                    <NavLink
                        :href="route('evaluator.applications')"
                        :active="isApplicationsActive"
                        class="block w-full rounded-lg transition hover:bg-[#FBCB77]"
                        :class="{
                            'active-link': isApplicationsActive,
                            'flex items-center space-x-3 py-3 px-4 text-lg font-semibold': true,
                        }"
                    >
                        <div class="w-6 flex justify-center">
                            <FontAwesomeIcon
                                icon="envelope-open-text"
                                class="text-xl"
                            />
                        </div>
                        <span v-if="isSidebarOpen" class="whitespace-nowrap"
                            >Applications</span
                        >
                    </NavLink>
                </li>

                <li>
                    <div
                        @click="toggleUserMenu"
                        class="cursor-pointer flex items-center justify-between py-3 px-4 text-lg font-semibold rounded-lg hover:bg-[#FBCB77] hover:text-[#9E122C] transition"
                        :class="{
                            'active-link':
                                isUserMenuOpen ||
                                isActiveRoute('profile.show') ||
                                isActiveRoute('api-tokens.index'),
                        }"
                    >
                        <div class="flex items-center space-x-3">
                            <FontAwesomeIcon icon="cog" class="text-xl" />
                            <span v-if="isSidebarOpen">User Settings</span>
                        </div>
                        <FontAwesomeIcon
                            v-if="isSidebarOpen"
                            :icon="
                                isUserMenuOpen
                                    ? 'caret-down'
                                    : 'caret-right'
                            "
                        />
                    </div>
                    <transition name="slide-fade">
                        <div
                            v-show="isUserMenuOpen && isSidebarOpen"
                            class="ml-6 space-y-2 bg-[#EE6A43] rounded-lg mt-2"
                        >
                            <DropdownLink
                                :href="route('profile.show')"
                                class="text-[#FCECDF] py-2 px-4 block hover:bg-[#FBCB77] transition"
                                :class="{
                                    'active-link':
                                        isActiveRoute('profile.show'),
                                }"
                            >
                                Profile
                            </DropdownLink>
                        </div>
                    </transition>
                </li>
                <li>
                    <form @submit.prevent="logout">
                        <button
                            type="submit"
                            class="cursor-pointer flex items-center justify-between py-3 px-4 text-lg font-semibold rounded-lg hover:bg-[#FBCB77] hover:text-[#9E122C] transition focus:outline-none w-full"
                        >
                            <div class="flex items-center space-x-3">
                                <FontAwesomeIcon
                                    icon="sign-out-alt"
                                    class="text-xl"
                                />
                                <span v-if="isSidebarOpen">Log Out</span>
                            </div>
                        </button>
                    </form>
                </li>
            </ul>
        </nav>

        <!-- ================= APPLICANT VARIANT ================= -->
        <nav v-else-if="props.variant === 'applicant'">
            <ul class="space-y-6">
                <li>
                    <NavLink
                        :href="route('applicant.dashboard')"
                        :active="isActiveRoute('applicant.dashboard')"
                        class="flex items-center space-x-3 py-3 px-4 text-lg font-semibold rounded-lg hover:bg-[#FBCB77] transition"
                        :class="{ 'active-link': isActiveRoute('applicant.dashboard') }"
                    >
                        <FontAwesomeIcon icon="tachometer-alt" class="text-xl" />
                        <span v-if="isSidebarOpen">My Dashboard</span>
                    </NavLink>
                </li>

                <li>
                    <div @click="toggleUserMenu" class="cursor-pointer flex items-center justify-between py-3 px-4 text-lg font-semibold rounded-lg hover:bg-[#FBCB77] hover:text-[#9E122C] transition" :class="{ 'active-link': isUserMenuOpen || isActiveRoute('profile.show') || isActiveRoute('api-tokens.index') }">
                        <div class="flex items-center space-x-3">
                            <FontAwesomeIcon icon="cog" class="text-xl" />
                            <span v-if="isSidebarOpen">User Settings</span>
                        </div>
                        <FontAwesomeIcon v-if="isSidebarOpen" :icon="isUserMenuOpen ? 'caret-down' : 'caret-right'" />
                    </div>

                    <transition name="slide-fade">
                        <div v-show="isUserMenuOpen && isSidebarOpen" class="ml-6 space-y-2 bg-[#EE6A43] rounded-lg mt-2">
                            <DropdownLink :href="route('profile.show')" class="text-[#FCECDF] py-2 px-4 block hover:bg-[#FBCB77] transition" :class="{ 'active-link': isActiveRoute('profile.show') }">
                                Profile
                            </DropdownLink>
                        </div>
                    </transition>
                </li>

                <li>
                    <form @submit.prevent="logout">
                        <button type="submit" class="cursor-pointer flex items-center justify-between py-3 px-4 text-lg font-semibold rounded-lg hover:bg-[#FBCB77] hover:text-[#9E122C] transition focus:outline-none w-full">
                            <div class="flex items-center space-x-3">
                                <FontAwesomeIcon icon="sign-out-alt" class="text-xl" />
                                <span v-if="isSidebarOpen">Log Out</span>
                            </div>
                        </button>
                    </form>
                </li>
            </ul>
        </nav>

<!-- ================= ADMIN VARIANT ================= -->
        <nav v-else>
            <ul class="space-y-6">
                <li>
                    <NavLink
                        :href="route('dashboard')"
                        :active="isDashboardActive"
                        class="block w-full rounded-lg transition hover:bg-[#FFD700]"
                        :class="{
                            'active-link': isDashboardActive,
                            'flex items-center space-x-3 py-3 px-4 text-lg font-semibold': true,
                        }"
                    >
                        <div class="w-6 flex justify-center">
                            <FontAwesomeIcon
                                icon="tachometer-alt"
                                class="text-xl"
                            />
                        </div>
                        <span v-if="isSidebarOpen" class="whitespace-nowrap">Dashboard</span>
                    </NavLink>
                </li>
                <li>
                    <div
                        @click="togglePasserMenu"
                        class="block cursor-pointer rounded-lg transition hover:bg-[#FFD700] hover:text-[#9E122C]"
                        :class="{
                            'active-link':
                                isPasserDropdownOpen ||
                                isUploadFormActive ||
                                isListPassersActive,
                            'flex items-center justify-between py-3 px-4 text-lg font-semibold': true,
                        }"
                    >
                        <div class="flex items-center space-x-3">
                            <div class="w-6 flex justify-center">
                                <FontAwesomeIcon
                                    icon="pencil-alt"
                                    class="text-xl"
                                />
                            </div>
                            <span v-if="isSidebarOpen" class="whitespace-nowrap">Upload Passer</span>
                        </div>
                        <FontAwesomeIcon v-if="isSidebarOpen" :icon="isPasserDropdownOpen ? 'caret-down' : 'caret-right'" />
                    </div>

                    <transition name="slide-fade">
                        <div v-show="isPasserDropdownOpen && isSidebarOpen" class="ml-6 space-y-2 bg-[#EE6A43] rounded-lg mt-2">
                            <NavLink :href="route('upload.form')" class="block w-full rounded-lg px-4 py-2 transition hover:bg-[#FFD700]" :class="{ 'active-link': isUploadFormActive }">Upload Passer</NavLink>
                            <NavLink :href="route('lists')" class="block w-full rounded-lg px-4 py-2 transition hover:bg-[#FFD700]" :class="{ 'active-link': isListPassersActive }">List Passers</NavLink>
                        </div>
                    </transition>
                </li>

                <li>
                    <NavLink :href="route('applications')" :active="isApplicationsActive" class="block w-full rounded-lg transition hover:bg-[#FFD700]" :class="{ 'active-link': isApplicationsActive, 'flex items-center space-x-3 py-3 px-4 text-lg font-semibold': true }">
                        <div class="w-6 flex justify-center"><FontAwesomeIcon icon="envelope-open-text" class="text-xl"/></div>
                        <span v-if="isSidebarOpen" class="whitespace-nowrap">Applications</span>
                    </NavLink>
                </li>

                <li>
                    <NavLink :href="route('schedules.index')" :active="isScheduleActive" class="block w-full rounded-lg transition hover:bg-[#FFD700]" :class="{ 'active-link': isScheduleActive, 'flex items-center space-x-3 py-3 px-4 text-lg font-semibold': true }">
                        <div class="w-6 flex justify-center"><FontAwesomeIcon icon="calendar-check" class="text-xl"/></div>
                        <span v-if="isSidebarOpen" class="whitespace-nowrap">Schedules</span>
                    </NavLink>
                </li>

                <li>
                    <NavLink :href="route('programs.index')" :active="isProgramsActive" class="block w-full rounded-lg transition hover:bg-[#FFD700]" :class="{ 'active-link': isProgramsActive, 'flex items-center space-x-3 py-3 px-4 text-lg font-semibold': true }">
                        <div class="w-6 flex justify-center"><FontAwesomeIcon icon="graduation-cap" class="text-xl"/></div>
                        <span v-if="isSidebarOpen" class="whitespace-nowrap">Programs</span>
                    </NavLink>
                </li>

                <li>
                    <div @click="toggleMaintenanceMenu" class="block cursor-pointer rounded-lg transition hover:bg-[#FFD700] hover:text-[#9E122C]" :class="{ 'active-link': isMaintenanceDropdownOpen || isManageActive || isAssignActive, 'flex items-center justify-between py-3 px-4 text-lg font-semibold': true }">
                        <div class="flex items-center space-x-3"><div class="w-6 flex justify-center"><FontAwesomeIcon icon="pencil-alt" class="text-xl"/></div><span v-if="isSidebarOpen" class="whitespace-nowrap">Maintenance</span></div>
                        <FontAwesomeIcon v-if="isSidebarOpen" :icon="isMaintenanceDropdownOpen ? 'caret-down' : 'caret-right'" />
                    </div>

                    <transition name="slide-fade">
                        <div v-show="isMaintenanceDropdownOpen && isSidebarOpen" class="ml-6 space-y-2 bg-[#EE6A43] rounded-lg mt-2">
                            <NavLink :href="route('add_user_vue')" class="block w-full rounded-lg px-4 py-2 transition hover:bg-[#FFD700]" :class="{ 'active-link': isManageActive }">Manage Users</NavLink>
                            <NavLink :href="route('assign_user_vue')" class="block w-full rounded-lg px-4 py-2 transition hover:bg-[#FFD700]" :class="{ 'active-link': isAssignActive }">Assign Program</NavLink>
                        </div>
                    </transition>
                </li>

                <li>
                    <div @click="toggleUserMenu" class="block cursor-pointer rounded-lg transition hover:bg-[#FFD700] hover:text-[#9E122C]" :class="{ 'active-link': isUserMenuOpen || isUserSettingsActive, 'flex items-center justify-between py-3 px-4 text-lg font-semibold': true }">
                        <div class="flex items-center space-x-3"><div class="w-6 flex justify-center"><FontAwesomeIcon icon="cog" class="text-xl"/></div><span v-if="isSidebarOpen" class="whitespace-nowrap">User Settings</span></div>
                        <FontAwesomeIcon v-if="isSidebarOpen" :icon="isUserMenuOpen ? 'caret-down' : 'caret-right'" />
                    </div>
                    <transition name="slide-fade">
                        <div v-show="isUserMenuOpen && isSidebarOpen" class="ml-6 space-y-2 bg-[#EE6A43] rounded-lg mt-2">
                            <DropdownLink :href="route('profile.show')" class="block w-full rounded-lg px-4 py-2 transition hover:bg-[#FFD700]" :class="{ 'active-link': isActiveRoute('profile.show') }">Profile</DropdownLink>
                        </div>
                    </transition>
                </li>

                <li>
                    <form @submit.prevent="logout"><button type="submit" class="flex w-full items-center justify-between rounded-lg py-3 px-4 text-lg font-semibold transition focus:outline-none hover:bg-[#FFD700] hover:text-[#9E122C]"><div class="flex items-center space-x-3"><div class="w-6 flex justify-center"><FontAwesomeIcon icon="sign-out-alt" class="text-xl"/></div><span v-if="isSidebarOpen" class="whitespace-nowrap">Log Out</span></div></button></form>
                </li>
            </ul>
        </nav>
    </div>
</template>

<style scoped>
.sidebar {
    background-color: #8b0000;
    /* Only animate width and padding for smoother layout transitions */
    transition-property: width, padding;
    transition-duration: 500ms;
    transition-timing-function: cubic-bezier(0.2, 0.8, 0.2, 1);
    will-change: width, padding;
}

/* Smoothly fade/slide labels when the sidebar changes width */
.sidebar .whitespace-nowrap {
    transition: opacity 260ms ease, transform 260ms ease;
}

/* Slight performance hint for icons and text */
.sidebar * {
    backface-visibility: hidden;
}
</style>
