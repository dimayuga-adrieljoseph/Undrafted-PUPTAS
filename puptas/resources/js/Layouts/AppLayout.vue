<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
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
    faGraduationCap,
    faRightFromBracket,
    faPencilAlt,
    faEnvelopeOpenText,
    faCalendarCheck,
    faUserGroup,
} from "@fortawesome/free-solid-svg-icons";
import { library } from "@fortawesome/fontawesome-svg-core";
import { useGlobalLoading } from "@/Composables/useGlobalLoading";

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
    faPencilAlt,
    faEnvelopeOpenText,
    faCalendarCheck,
    faUserGroup
);

const page = usePage();
const { isLoading, start, finish } = useGlobalLoading();

const user = computed(() => page.props.auth.user);
// const jetstream = computed(() => page.props.jetstream);
// const currentTeam = computed(() => user.value.current_team);
// const allTeams = computed(() => user.value.all_teams || []);

const isTeamMenuOpen = ref(false);
const isUserMenuOpen = ref(false);
const isPasserDropdownOpen = ref(false);
const isScheduleMenuOpen = ref(false);
const isMaintenanceDropdownOpen = ref(false);

const isSidebarOpen = ref(false);
const isSidebarPinned = ref(false);

const onSidebarEnter = () => {
    isSidebarOpen.value = true;
};

const onSidebarLeave = () => {
    if (!isSidebarPinned.value) {
        isSidebarOpen.value = false;
    }
};

const sidebarRef = ref(null);
const onSidebarClick = () => {
    isSidebarPinned.value = true;
    isSidebarOpen.value = true;
};
const onClickOutside = (event) => {
    if (!isSidebarPinned.value) return;

    if (sidebarRef.value && !sidebarRef.value.contains(event.target)) {
        isSidebarPinned.value = false;
        isSidebarOpen.value = false;
    }
};


const isActiveRoute = (routeName) => route().current(routeName);
const isListPassersActive = computed(() => isActiveRoute("lists"));
const isApplicationsActive = computed(() => isActiveRoute("applications"));
const isScheduleActive = computed(() => isActiveRoute("schedules.index"));

const isDashboardActive = computed(() => isActiveRoute("dashboard"));
const isUploadFormActive = computed(() => isActiveRoute("upload.form"));
const isTeamsActive = computed(() =>
    ["teams.show", "teams.create", "teams.index"].some(isActiveRoute)
);
const isUserSettingsActive = computed(() =>
    ["profile.show", "api-tokens.index"].some(isActiveRoute)
);
const isProgramsActive = computed(() => isActiveRoute("programs.index"));
const isManageActive = computed(() => isActiveRoute("add_user_vue"));
const isAssignActive = computed(() => isActiveRoute("assign_user_vue"));

watch(
    () => page.props.url,
    () => {
        isTeamMenuOpen.value = isTeamsActive.value;
        isUserMenuOpen.value = isUserSettingsActive.value;
    },
    { immediate: true }
);

const toggleMenu = (menu) => {
    if (menu === "team") {
        isTeamMenuOpen.value = !isTeamMenuOpen.value;
        if (isUserMenuOpen.value) isUserMenuOpen.value = false;
    } else if (menu === "user") {
        isUserMenuOpen.value = !isUserMenuOpen.value;
        if (isTeamMenuOpen.value) isTeamMenuOpen.value = false;
    }
};

// const switchToTeam = (team) => {
//     router
//         .put(
//             route("current-team.update"),
//             { team_id: team.id },
//             { preserveState: false }
//         )
//         .then(() => {
//             isTeamMenuOpen.value = true;
//         });
// };

watch(
    () => page.props.url,
    () => {
        isTeamMenuOpen.value = isTeamsActive.value;
        isUserMenuOpen.value = isUserSettingsActive.value;

        if (isUploadFormActive.value || isListPassersActive.value) {
            isPasserDropdownOpen.value = true;
        } else {
            isPasserDropdownOpen.value = false;
        }

        if (isManageActive.value || isAssignActive.value) {
            isMaintenanceDropdownOpen.value = true;
        } else {
            isMaintenanceDropdownOpen.value = false;
        }
    },
    { immediate: true }
);

const logout = () => {
    router.post(route("logout"));
};

let listenersRegistered = false;

onMounted(() => {
    document.addEventListener("click", onClickOutside);
    if (!listenersRegistered) {
        router.on("start", start);
        router.on("finish", finish);
        router.on("error", finish);
        listenersRegistered = true;
    }

    const saved = localStorage.getItem("darkMode") === "true";
    isDarkMode.value = saved;
    document.documentElement.classList.toggle("dark", saved);
});

onUnmounted(() => {
    document.removeEventListener("click", onClickOutside);
});

function toggleDarkMode() {
    isDarkMode.value = !isDarkMode.value;
    document.documentElement.classList.toggle("dark", isDarkMode.value);
    localStorage.setItem("darkMode", String(isDarkMode.value));
}

const isDarkMode = ref(false);

onMounted(() => {
    const isDark = localStorage.getItem("darkMode") === "true";
    document.documentElement.classList.toggle("dark", isDark);
});
</script>

<template>
    <div class="flex min-h-screen bg-[#FCECDF]">
        <!-- Sidebar -->
        <div
            ref="sidebarRef"
            @mouseenter="onSidebarEnter"
            @mouseleave="onSidebarLeave"
            @click.stop="onSidebarClick"
            :class="[
                'sidebar relative overflow-hidden text-white shadow-md transition-all duration-300 ease-in-out',
                isSidebarOpen ? 'w-72 p-6' : 'w-20 p-4',
            ]"
        >

            <div class="mb-10 flex items-center justify-center">
                <Link :href="route('dashboard')">
                    <ApplicationMark
                        v-if="isSidebarOpen"
                        class="block h-10 w-auto"
                    />
                </Link>
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
                            @click="isSidebarOpen && (isPasserDropdownOpen = !isPasserDropdownOpen)"
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
                        <NavLink
                            :href="route('add_user_vue')"
                            :active="isManageActive"
                            class="block w-full rounded-lg transition hover:bg-[#FFD700]"
                            :class="{
                                'active-link': isManageActive,
                                'flex items-center space-x-3 py-3 px-4 text-lg font-semibold': true,
                            }"
                        >
                            <div class="w-6 flex justify-center">
                                <FontAwesomeIcon
                                    icon="user-group"
                                    class="text-xl"
                                />
                            </div>
                            <span v-if="isSidebarOpen" class="whitespace-nowrap"
                                >Manage Users</span
                            >
                        </NavLink>
                    </li>
                    <li>
                        <div
                            @click="isSidebarOpen && (isMaintenanceDropdownOpen = !isMaintenanceDropdownOpen)"
                            class="block cursor-pointer rounded-lg transition hover:bg-[#FFD700] hover:text-[#9E122C]"
                            :class="{
                                'active-link':
                                    isMaintenanceOpen ||
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
                            @click="isSidebarOpen && toggleMenu('user')"
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
                                class="cursor-pointer flex w-full items-center justify-between rounded-lg py-3 px-4 text-lg font-semibold hover:bg-[#FFD700] hover:text-[#9E122C] transition focus:outline-none"
                            >
                                <div class="flex items-center space-x-3">
                                    <div class="w-6 flex justify-center">
                                        <FontAwesomeIcon
                                            icon="sign-out-alt"
                                            class="text-xl"
                                        />
                                    </div>
                                    <span
                                        v-if="isSidebarOpen"
                                        class="whitespace-nowrap"
                                        >Log Out</span
                                    >
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
                'flex-1 bg-gradient-to-br from-orange-200 to-red-400 p-6 transition-all duration-300',
                isSidebarOpen ? 'ml-72' : 'ml-20',
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
                                {{ user.lastname }}, {{ user.firstname }}
                            </p>
                            <p class="text-gray-600 dark:text-gray-300">
                                Administrator
                            </p>
                        </div>
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
