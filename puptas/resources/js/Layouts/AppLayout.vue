<script setup>
// Imports
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { library } from "@fortawesome/fontawesome-svg-core";
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
} from "@fortawesome/free-solid-svg-icons";

import DropdownLink from "@/Components/DropdownLink.vue";
import NavLink from "@/Components/NavLink.vue";
import { useGlobalLoading } from "@/Composables/useGlobalLoading";
import { faMoon, faSun } from '@fortawesome/free-solid-svg-icons'
import ApplicationMark from "@/Components/ApplicationMark.vue";

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
    faUserGroup
);

// Composables
const page = usePage();
const { isLoading, start, finish } = useGlobalLoading();

// State
const isSidebarOpen = ref(false);
const isSidebarPinned = ref(false);
const sidebarRef = ref(null);

const isUserMenuOpen = ref(false);
const isPasserDropdownOpen = ref(false);
const isMaintenanceDropdownOpen = ref(false);
const isDarkMode = ref(false);

// Computed
const user = computed(() => page.props.auth.user);

const isActiveRoute = (name) => route().current(name);

const isAnyDropdownOpen = computed(() =>
    isPasserDropdownOpen.value || isMaintenanceDropdownOpen.value || isUserMenuOpen.value
);

const isUserSettingsActive = computed(() => isActiveRoute('profile.show'));

const isDashboardActive = computed(() => isActiveRoute("dashboard"));
const isApplicationsActive = computed(() => isActiveRoute("applications"));
const isScheduleActive = computed(() => isActiveRoute("schedules.index"));
const isUploadFormActive = computed(() => isActiveRoute("upload.form"));
const isListPassersActive = computed(() => isActiveRoute("lists"));
const isProgramsActive = computed(() => isActiveRoute("programs.index"));
const isManageActive = computed(() => isActiveRoute("add_user_vue"));
const isAssignActive = computed(() => isActiveRoute("assign_user_vue"));

const sidebarWidthClass = computed(() =>
    isSidebarOpen.value ? "w-72 p-6" : "w-20 p-4"
);

const contentMarginClass = computed(() => "");

// Methods
const onSidebarEnter = () => {
    isSidebarOpen.value = true;
};

const onSidebarLeave = () => {
    if (!isAnyDropdownOpen.value && !isSidebarPinned.value) {
        isSidebarOpen.value = false;
    }
};

const pinSidebar = () => {
    // toggle pinned state
    isSidebarPinned.value = !isSidebarPinned.value;
    if (isSidebarPinned.value) {
        isSidebarOpen.value = true;
    }
};

const onClickOutside = (event) => {
    if (sidebarRef.value && !sidebarRef.value.contains(event.target) && !isSidebarPinned.value) {
        // Close all dropdowns
        isPasserDropdownOpen.value = false;
        isMaintenanceDropdownOpen.value = false;
        isUserMenuOpen.value = false;

        // Collapse sidebar if not hovered
        isSidebarOpen.value = false;
    }
};

const isSidebarInteractable = computed(() => isSidebarOpen.value);

const togglePasserMenu = () => {
    isPasserDropdownOpen.value = !isPasserDropdownOpen.value;
    if (isPasserDropdownOpen.value) isSidebarOpen.value = true; // always expand when opening dropdown
};

const toggleMaintenanceMenu = () => {
    isMaintenanceDropdownOpen.value = !isMaintenanceDropdownOpen.value;
    if (isMaintenanceDropdownOpen.value) isSidebarOpen.value = true;
};

const toggleUserMenu = () => {
    isUserMenuOpen.value = !isUserMenuOpen.value;
    if (isUserMenuOpen.value) isSidebarOpen.value = true;
};

const toggleDarkMode = () => {
    isDarkMode.value = !isDarkMode.value;
    document.documentElement.classList.toggle("dark", isDarkMode.value);
    localStorage.setItem("darkMode", String(isDarkMode.value));
};

const logout = () => {
    router.post(route("logout"));
};

// Watchers
watch(
    () => page.props.url,
    () => {
        isPasserDropdownOpen.value =
            isUploadFormActive.value || isListPassersActive.value;

        isMaintenanceDropdownOpen.value =
            isManageActive.value || isAssignActive.value;
    },
    { immediate: true }
);

// Lifecycle
onMounted(() => {
    document.addEventListener("click", onClickOutside);

    router.on("start", start);
    router.on("finish", finish);
    router.on("error", finish);

    const savedDarkMode = localStorage.getItem("darkMode") === "true";
    isDarkMode.value = savedDarkMode;
    document.documentElement.classList.toggle("dark", savedDarkMode);
});

onUnmounted(() => {
    document.removeEventListener("click", onClickOutside);
});
</script>

<template>
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div
            ref="sidebarRef"
            class="sidebar fixed left-0 top-0 h-screen z-[9999] pointer-events-auto overflow-hidden text-white shadow-md transition-all duration-300"
            style="z-index: 9999;"
            :class="sidebarWidthClass"
            @mouseenter="onSidebarEnter"
            @mouseleave="onSidebarLeave"
            @click.self.stop="pinSidebar"
        >

            <div class="mb-10 flex items-center justify-center">
                <NavLink :href="route('dashboard')">
                    <ApplicationMark
                        v-if="isSidebarOpen"
                        class="block h-10 w-auto"
                    />
                </NavLink>
            </div>

            <nav>
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
                            <span v-if="isSidebarOpen" class="whitespace-nowrap"
                                >Dashboard</span
                            >
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
                                <span
                                    v-if="isSidebarOpen"
                                    class="whitespace-nowrap"
                                    >Upload Passer</span
                                >
                            </div>
                            <FontAwesomeIcon
                                v-if="isSidebarOpen"
                                :icon="
                                    isPasserDropdownOpen
                                        ? 'caret-down'
                                        : 'caret-right'
                                "
                            />
                        </div>

                        <transition name="slide-fade">
                            <div
                                v-show="isPasserDropdownOpen && isSidebarOpen"
                                class="ml-6 space-y-2 bg-[#EE6A43] rounded-lg mt-2"
                            >
                                <NavLink
                                    :href="route('upload.form')"
                                    class="block w-full rounded-lg px-4 py-2 transition hover:bg-[#FFD700]"
                                    :class="{
                                        'active-link': isUploadFormActive,
                                    }"
                                >
                                    Upload Passer
                                </NavLink>

                                <NavLink
                                    :href="route('lists')"
                                    class="block w-full rounded-lg px-4 py-2 transition hover:bg-[#FFD700]"
                                    :class="{
                                        'active-link': isListPassersActive,
                                    }"
                                >
                                    List Passers
                                </NavLink>
                            </div>
                        </transition>
                    </li>

                    <li>
                        <NavLink
                            :href="route('applications')"
                            :active="isApplicationsActive"
                            class="block w-full rounded-lg transition hover:bg-[#FFD700]"
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
                        <NavLink
                            :href="route('schedules.index')"
                            :active="isScheduleActive"
                            class="block w-full rounded-lg transition hover:bg-[#FFD700]"
                            :class="{
                                'active-link': isScheduleActive,
                                'flex items-center space-x-3 py-3 px-4 text-lg font-semibold': true,
                            }"
                        >
                            <div class="w-6 flex justify-center">
                                <FontAwesomeIcon
                                    icon="calendar-check"
                                    class="text-xl"
                                />
                            </div>
                            <span v-if="isSidebarOpen" class="whitespace-nowrap"
                                >Schedules</span
                            >
                        </NavLink>
                    </li>

                    <li>
                        <NavLink
                            :href="route('programs.index')"
                            :active="isProgramsActive"
                            class="block w-full rounded-lg transition hover:bg-[#FFD700]"
                            :class="{
                                'active-link': isProgramsActive,
                                'flex items-center space-x-3 py-3 px-4 text-lg font-semibold': true,
                            }"
                        >
                            <div class="w-6 flex justify-center">
                                <FontAwesomeIcon
                                    icon="graduation-cap"
                                    class="text-xl"
                                />
                            </div>
                            <span v-if="isSidebarOpen" class="whitespace-nowrap"
                                >Programs</span
                            >
                        </NavLink>
                    </li>

                    <li>
                        <div
                            @click="toggleMaintenanceMenu"
                            class="block cursor-pointer rounded-lg transition hover:bg-[#FFD700] hover:text-[#9E122C]"
                            :class="{
                                'active-link':
                                    isMaintenanceDropdownOpen ||
                                    isManageActive ||
                                    isAssignActive,
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
                                <span
                                    v-if="isSidebarOpen"
                                    class="whitespace-nowrap"
                                    >Maintenance</span
                                >
                            </div>
                            <FontAwesomeIcon
                                v-if="isSidebarOpen"
                                :icon="
                                    isMaintenanceDropdownOpen
                                        ? 'caret-down'
                                        : 'caret-right'
                                "
                            />
                        </div>

                        <transition name="slide-fade">
                            <div
                                v-show="
                                    isMaintenanceDropdownOpen && isSidebarOpen
                                "
                                class="ml-6 space-y-2 bg-[#EE6A43] rounded-lg mt-2"
                            >
                                <NavLink
                                    :href="route('add_user_vue')"
                                    class="block w-full rounded-lg px-4 py-2 transition hover:bg-[#FFD700]"
                                    :class="{
                                        'active-link': isManageActive,
                                    }"
                                >
                                    Manage Users
                                </NavLink>

                                <NavLink
                                    :href="route('assign_user_vue')"
                                    class="block w-full rounded-lg px-4 py-2 transition hover:bg-[#FFD700]"
                                    :class="{
                                        'active-link': isAssignActive,
                                    }"
                                >
                                    Assign Program
                                </NavLink>
                            </div>
                        </transition>
                    </li>

                    <li>
                        <div
                            @click="toggleUserMenu"
                            class="block cursor-pointer rounded-lg transition hover:bg-[#FFD700] hover:text-[#9E122C]"
                            :class="{
                                'active-link':
                                    isUserMenuOpen || isUserSettingsActive,
                                'flex items-center justify-between py-3 px-4 text-lg font-semibold': true,
                            }"
                        >
                            <div class="flex items-center space-x-3">
                                <div class="w-6 flex justify-center">
                                    <FontAwesomeIcon
                                        icon="cog"
                                        class="text-xl"
                                    />
                                </div>
                                <span
                                    v-if="isSidebarOpen"
                                    class="whitespace-nowrap"
                                    >User Settings</span
                                >
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
                                    class="block w-full rounded-lg px-4 py-2 transition hover:bg-[#FFD700]"
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
                                    class="flex w-full items-center justify-between rounded-lg py-3 px-4 text-lg font-semibold transition focus:outline-none
                                        hover:bg-[#FFD700] hover:text-[#9E122C]"
                            >
                                <div class="flex items-center space-x-3">
                                    <div class="w-6 flex justify-center">
                                        <FontAwesomeIcon icon="sign-out-alt" class="text-xl" />
                                    </div>
                                    <span v-if="isSidebarOpen" class="whitespace-nowrap">
                                        Log Out
                                    </span>
                                </div>
                            </button>
                        </form>
                    </li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div
        class="flex-1 bg-[#faf6f2] dark:bg-gray-900 p-6 transition-all duration-300"
        :class="contentMarginClass"
        >
            <main>
                <div
                    class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 sm:gap-2 mb-1"
                >
                    <h2
                        class="font-semibold text-xl text-[#9E122C] dark:text-gray-200 leading-tight"
                    ></h2>
                    
                    <div class="flex items-center space-x-3 mb-4">
                        <!-- Profile Avatar -->
                        <div
                            class="flex items-center space-x-3 bg-white border-4 border-red-400 dark:bg-gray-900 px-2 py-2 rounded-full shadow"
                        >
                            <svg
                                class="w-8 h-8 stroke-[#9E122C]"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 14c3.866 0 7 3.134 7 7H5c0-3.866 3.134-7 7-7z"
                                />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                            <div class="text-sm">
                                <p class="font-sm text-[#9E122C]">
                                    {{ user.lastname }}, {{ user.firstname }}
                                </p>
                                <p class="text-gray-600 dark:text-gray-300">
                                    Administrator
                                </p>
                            </div>
                        </div>

                        <!-- Dark Mode Button -->
                        <button
                            @click="toggleDarkMode"
                            class="text-[#9E122C] dark:text-white transition"
                            title="Toggle Dark Mode"
                        >
                            <FontAwesomeIcon :icon="isDarkMode ? faMoon : faSun" class="text-xl" />
                        </button>
                    </div>
                </div>

                <!-- Loading Overlay -->
                <div
                    v-if="isLoading"
                    class="fixed inset-0 z-[999] bg-black bg-opacity-25 flex flex-col items-center justify-center"
                >
                    <svg
                        class="animate-spin h-16 w-16 text-[#9E122C] mb-4"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>
                        <path
                            class="opacity-50"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                        ></path>
                    </svg>
                    <p class="text-lg font-semibold text-[#9E122C]">
                        Loading, please wait...
                    </p>
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
    transition: transform 0.1s ease, opacity 0.1s ease;
}

.slide-fade-enter,
.slide-fade-leave-to {
    transform: translateY(-10px);
    opacity: 0;
}

.active-link {
    background-color: #FFD700 !important;
    color: #9e122c;
    width: 100%;
    display: flex;
    align-items: center;
    border-radius: 0.5rem;
    box-sizing: border-box;
}

/* Sidebar styles */
.sidebar {
    background-color: #8B0000;
    position: relative;
}

.sidebar > * {
    position: relative;
    z-index: 1;
}
</style>
