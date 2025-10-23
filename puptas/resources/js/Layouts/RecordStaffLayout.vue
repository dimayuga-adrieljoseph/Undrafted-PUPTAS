<script setup>
import { ref, watchEffect, onMounted } from "vue";
import { Link, router } from "@inertiajs/vue3";
import ApplicationMark from "@/Components/ApplicationMark.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import NavLink from "@/Components/NavLink.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import {
    faArrowLeft,
    faBars,
    faTimes,
    faTachometerAlt,
    faUsers,
    faCaretDown,
    faCaretRight,
    faCog,
    faSun,
    faMoon,
    faEnvelopeOpenText,
} from "@fortawesome/free-solid-svg-icons";
import {
    faGraduationCap,
    faRightFromBracket,
} from "@fortawesome/free-solid-svg-icons";

import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

const page = usePage();
const user = computed(() => page.props.user);

const isDarkMode = ref(false);

onMounted(() => {
    const saved = localStorage.getItem("darkMode") === "true";
    isDarkMode.value = saved;
    document.documentElement.classList.toggle("dark", saved);
});

function toggleDarkMode() {
    isDarkMode.value = !isDarkMode.value;
    document.documentElement.classList.toggle("dark", isDarkMode.value);
    localStorage.setItem("darkMode", String(isDarkMode.value));
}

// Register icons globally
import { library } from "@fortawesome/fontawesome-svg-core";
library.add(
    faGraduationCap,
    faArrowLeft,
    faBars,
    faTimes,
    faTachometerAlt,
    faUsers,
    faCaretDown,
    faCaretRight,
    faCog,
    faRightFromBracket,
    faSun,
    faMoon,
    faEnvelopeOpenText
);

defineProps({
    title: String,
});

const isTeamMenuOpen = ref(false);
const isUserMenuOpen = ref(false);
const isSidebarOpen = ref(true);

const isLoading = ref(false);

const start = () => {
    isLoading.value = true;
};
const finish = () => {
    isLoading.value = false;
};

onMounted(() => {
    router.on("start", () => {
        isLoading.value = true;
    });
    router.on("finish", () => {
        isLoading.value = false;
    });
    router.on("error", () => {
        isLoading.value = false;
    });
});

const toggleSidebar = () => {
    isSidebarOpen.value = !isSidebarOpen.value;
};

// Check if the current route matches the section, to persist dropdown state
const isActiveRoute = (routeName) => {
    return route().current(routeName);
};

const isApplicationsActive = computed(() =>
    isActiveRoute("recordstaff.applications")
);
const isDashboardActive = computed(() => isActiveRoute("record.dashboard"));
// Toggle dropdown menu logic
const toggleMenu = (menu) => {
    if (menu === "team") {
        isTeamMenuOpen.value = !isTeamMenuOpen.value;
        // Close the user settings menu if it's open
        if (isUserMenuOpen.value) {
            isUserMenuOpen.value = false;
        }
    } else if (menu === "user") {
        isUserMenuOpen.value = !isUserMenuOpen.value;
        // Close the team menu if it's open
        if (isTeamMenuOpen.value) {
            isTeamMenuOpen.value = false;
        }
    }
};

// Watch for route changes to control dropdown visibility
watchEffect(() => {
    if (
        isActiveRoute("teams.show") ||
        isActiveRoute("teams.create") ||
        isActiveRoute("teams.index")
    ) {
        isTeamMenuOpen.value = true;
    } else {
        isTeamMenuOpen.value = false;
    }

    if (isActiveRoute("profile.show") || isActiveRoute("api-tokens.index")) {
        isUserMenuOpen.value = true;
    } else {
        isUserMenuOpen.value = false;
    }
});

const logout = () => {
    router.post(route("logout"));
};
</script>

<template>
    <div
        class="flex min-h-screen bg-orange-100 text-black dark:bg-gray-900 dark:text-white"
    >
        <!-- Sidebar -->
        <div
            :class="[
                'sidebar relative overflow-hidden text-white shadow-md transition-all duration-300 ease-in-out',
                'bg-[#9E122C] dark:bg-gray-800',
                isSidebarOpen ? 'w-72 p-6' : 'w-20 p-4',
            ]"
        >
            <div class="mb-10 flex items-center justify-center">
                <Link :href="route('record.dashboard')">
                    <ApplicationMark
                        v-if="isSidebarOpen"
                        class="block h-10 w-auto"
                    />
                </Link>
                <button
                    @click="toggleSidebar"
                    class="text-white focus:outline-none"
                >
                    <FontAwesomeIcon
                        :icon="isSidebarOpen ? 'arrow-left' : 'bars'"
                        class="text-2xl p-4 transform transition-transform duration-300 ease-in-out"
                    />
                </button>
            </div>

            <nav>
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
                            @click="toggleMenu('user')"
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
        </div>

        <!-- Main Content -->
        <div
            :class="[
                'flex-1 p-6 transition-all',
                'bg-gradient-to-br from-orange-200 to-red-400',
                'dark:from-gray-100 dark:to-gray-900',
                isSidebarOpen ? 'ml-0' : 'ml-16',
            ]"
        >
            <main>
                <div
                    class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 sm:gap-2 mb-1"
                >
                    <h2
                        class="font-semibold text-xl text-[#9E122C] dark:text-gray-200 leading-tight"
                    ></h2>
                    <!-- Profile Avatar with Name -->
                    <button
                        @click="toggleDarkMode"
                        class="text-[#9E122C] dark:text-white transition"
                        title="Toggle Dark Mode"
                    >
                        <FontAwesomeIcon
                            :icon="isDarkMode ? 'moon' : 'sun'"
                            class="text-xl"
                        />
                    </button>

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
                                {{ user?.lastname }}, {{ user?.firstname }}
                            </p>
                            <p class="text-gray-600 dark:text-gray-300">
                                Record Staff
                            </p>
                        </div>
                    </div>
                </div>
                <div
                    v-if="isLoading"
                    class="fixed inset-0 z-50 bg-white bg-opacity-25 flex flex-col items-center justify-center"
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
    background-color: #fbcb77 !important;
    color: #9e122c !important;
    padding-top: 0.75rem; /* py-3 */
    padding-bottom: 0.75rem;
    padding-left: 1rem; /* px-4 */
    padding-right: 1rem;
    margin: 0;
    border-radius: 0.5rem; /* rounded-lg */
    display: flex;
    align-items: center;
    gap: 0.75rem; /* space-x-3 */
}

/* Nav item base styles for both NavLink and div buttons */
.nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem; /* space-x-3 equivalent */
    padding: 0.75rem 1rem; /* py-3 px-4 */
    border-radius: 0.5rem; /* rounded-lg */
    cursor: pointer;
    transition: background-color 0.2s ease, color 0.2s ease;
}

/* Dropdown container removed left margin for alignment */
.dropdown-container {
    background-color: #ee6a43;
    border-radius: 0.5rem;
    margin-top: 0.5rem;
    /* Removed ml-6 to align dropdown with main nav links */
    padding: 0.25rem 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* Dropdown link styles */
.dropdown-item {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    color: #fcedcf;
    transition: background-color 0.2s ease;
    display: flex;
    align-items: center;
    cursor: pointer;
}

.dropdown-item:hover {
    background-color: #fbcb77;
    color: #9e122c;
}

/* Sidebar background image overlay */
.sidebar {
    position: relative;
}

.sidebar::before {
    content: "";
    position: absolute;
    inset: 0;
    background-image: url("/assets/images/2.jpg");
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center;
    opacity: 0.2;
    z-index: 0;
}

.sidebar > * {
    position: relative;
    z-index: 1;
}
</style>
