<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import { router, usePage, Link } from "@inertiajs/vue3";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { library } from "@fortawesome/fontawesome-svg-core";
import {
    faTachometerAlt,
    faUsers,
    faCaretDown,
    faCaretRight,
    faGraduationCap,
    faEnvelopeOpenText,
    faEnvelope,
    faCalendarCheck,
    faSignOutAlt,
    faUpload,
    faList,
    faUserShield,
    faHome,
    faHistory,
    faNetworkWired,
    faChartPie,
    faChartLine,
    faFileAlt,
    faClock,
    faClipboardList,
} from "@fortawesome/free-solid-svg-icons";

import NavLink from "@/Components/NavLink.vue";
import ApplicationMark from "@/Components/ApplicationMark.vue";

library.add(
    faTachometerAlt,
    faUsers,
    faCaretDown,
    faCaretRight,
    faGraduationCap,
    faEnvelopeOpenText,
    faEnvelope,
    faCalendarCheck,
    faSignOutAlt,
    faUpload,
    faList,
    faUserShield,
    faHome,
    faHistory,
    faNetworkWired,
    faChartPie,
    faChartLine,
    faFileAlt,
    faClock,
    faClipboardList
);

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const showQualifiedProgramsNav = computed(() => page.props.showQualifiedProgramsNav ?? false);

const props = defineProps({
    variant: {
        type: String,
        default: "default",
    },
    isMobileOpen: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);

/* ---------------- STATE ---------------- */
const isSidebarOpen = ref(false);
const isSidebarPinned = ref(false);
const sidebarRef = ref(null);

const isPasserDropdownOpen = ref(false);
const isReportsDropdownOpen = ref(false);

/* ---------------- HELPERS ---------------- */
const isActiveRoute = (name) => route().current(name);

const isAnyDropdownOpen = computed(
    () =>
        isPasserDropdownOpen.value ||
        isReportsDropdownOpen.value
);

const sidebarWidthClass = computed(() =>
    (isSidebarOpen.value || props.isMobileOpen) ? "w-72 px-6 py-8" : "w-20 px-4 py-8"
);

// True whenever the sidebar should show labels/full content —
// either expanded by hover/pin on desktop, or opened by the hamburger on mobile.
const isExpanded = computed(() => isSidebarOpen.value || props.isMobileOpen);

/* ---------------- ROUTE ACTIVE ---------------- */
const isActiveRouteFor = (routeNames = []) =>
    computed(() => routeNames.some((name) => isActiveRoute(name)));

const isDashboardActive = isActiveRouteFor([
    "dashboard",
    "record.dashboard",
    "interviewer.dashboard",
    "evaluator.dashboard",
    "applicant.dashboard",
]);

const isQualifiedProgramsActive = isActiveRouteFor([
    "applicant.qualified-programs.page",
]);

const isProfileActive = isActiveRouteFor([
    "applicant.profile",
]);

const isApplicationsActive = isActiveRouteFor([
    "applications",
    "recordstaff.applications",
    "interviewer.applications",
    "evaluator.applications",
]);

const isStaffProgramsActive = isActiveRouteFor([
    "evaluator.programs",
    "interviewer.programs",
    "record.programs",
]);

const isUploadFormActive = isActiveRouteFor(["upload.form"]);
const isListPassersActive = isActiveRouteFor(["lists"]);
const isProgramsActive = isActiveRouteFor(["programs.index"]);
const isReportsActive = isActiveRouteFor(["reports.index"]);
const isTestPasserReportsActive = isActiveRouteFor(["reports.test-passers.index"]);
const isMasterlistReportsActive = isActiveRouteFor(["reports.masterlist.index"]);
const isLogbookReportsActive = isActiveRouteFor(["reports.logbook.index"]);
const isControlListActive = isActiveRouteFor(["reports.control-list.index"]);
const isConfirmedApplicantsActive = isActiveRouteFor(["confirmed-applicants.index"]);
const isManageActive = isActiveRouteFor(["users.index"]);

const isSuperAdmin = computed(() => {
    return user.value && user.value.role_id === 7;
});

const isAdminOrSuperAdmin = computed(() => {
    return user.value && (user.value.role_id === 2 || user.value.role_id === 7);
});

const isAuditLogsActive = isActiveRouteFor(["audit-logs.index"]);
const isApiClientsActive = isActiveRouteFor(["api-clients.index"]);
const isEmailTrackingActive = isActiveRouteFor(["email-tracking.index"]);
const isCutoffSettingsActive = isActiveRouteFor(["cutoff-settings.index"]);

/* ---------------- INTERACTION ---------------- */
const onSidebarEnter = () => (isSidebarOpen.value = true);

const onSidebarLeave = () => {
    if (!isAnyDropdownOpen.value && !isSidebarPinned.value) {
        isSidebarOpen.value = false;
    }
};

const pinSidebar = () => {
    isSidebarPinned.value = !isSidebarPinned.value;
    if (isSidebarPinned.value) isSidebarOpen.value = true;
};

const togglePasserMenu = () => {
    isPasserDropdownOpen.value = !isPasserDropdownOpen.value;
    isSidebarOpen.value = true;
};

const toggleReportsMenu = () => {
    isReportsDropdownOpen.value = !isReportsDropdownOpen.value;
    isSidebarOpen.value = true;
};

const logout = () => {
    router.post(route("idp.logout"));
};

/* ---------------- CLICK OUTSIDE ---------------- */
const onClickOutside = (event) => {
    if (!sidebarRef.value || isSidebarPinned.value) return;
    if (!sidebarRef.value.contains(event.target)) {
        isSidebarOpen.value = false;
        isPasserDropdownOpen.value = false;
        isReportsDropdownOpen.value = false;
    }
};

/* ---------------- WATCH ---------------- */
watch(
    () => page.props.url,
    () => {
        isPasserDropdownOpen.value =
            isUploadFormActive.value || isListPassersActive.value || isConfirmedApplicantsActive.value;
        isReportsDropdownOpen.value = 
            isReportsActive.value || isTestPasserReportsActive.value || isMasterlistReportsActive.value || isLogbookReportsActive.value || isControlListActive.value || isEmailTrackingActive.value;
    },
    { immediate: true }
);

/* ---------------- LIFECYCLE ---------------- */
onMounted(() => {
    document.addEventListener("pointerdown", onClickOutside);

    const savedDark = localStorage.getItem("darkMode") === "true";
    document.documentElement.classList.toggle("dark", savedDark);

    document.documentElement.style.setProperty(
        "--sidebar-width",
        isSidebarOpen.value ? "18rem" : "5rem"
    );
});

onUnmounted(() => {
    document.removeEventListener("pointerdown", onClickOutside);
});

watch(isSidebarOpen, (val) => {
    document.documentElement.style.setProperty(
        "--sidebar-width",
        val ? "18rem" : "5rem"
    );
});
</script>

<template>
    <!-- Backdrop (mobile only) -->
    <Transition name="fade">
        <div
            v-if="props.isMobileOpen"
            class="fixed inset-0 z-[9998] bg-black/50 md:hidden"
            @click="emit('close')"
        />
    </Transition>

    <div
        ref="sidebarRef"
        class="sidebar fixed left-0 top-0 h-screen z-[9999] flex flex-col text-white shadow-2xl transition-all duration-300 ease-out dark:text-gray-900"
        :class="[
            sidebarWidthClass,
            'transition-transform duration-300',
            props.isMobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
            !props.isMobileOpen ? 'max-md:invisible' : 'max-md:visible',
        ]"
        :role="props.isMobileOpen ? 'dialog' : undefined"
        :aria-modal="props.isMobileOpen ? 'true' : undefined"
        @pointerenter="onSidebarEnter"
        @pointerleave="onSidebarLeave"
        @click.self.stop="pinSidebar"
    >
        <!-- Header Section -->
        <div class="sidebar-header mb-8 flex items-center justify-between">
            <!-- Logo + Title (always shown when expanded) -->
            <div class="flex items-center gap-3 min-w-0">
                <div class="sidebar-logo-container flex-shrink-0">
                    <NavLink :href="route('dashboard')" class="block">
                        <ApplicationMark v-if="isExpanded" class="h-8" />
                        <div
                            v-else
                            class="w-8 h-8 rounded-full bg-gradient-to-br from-[#FFD700] to-[#FBCB77] flex items-center justify-center"
                        >
                            <span class="text-[#9E122C] font-bold text-sm dark:text-white">
                                PUP
                            </span>
                        </div>
                    </NavLink>
                </div>
                <div v-if="isExpanded" class="flex-1 min-w-0">
                    <h1 class="text-lg font-bold text-white">PUP Portal</h1>
                    <p class="text-xs text-gray-300 mt-0.5">
                        Management System
                    </p>
                </div>
            </div>

            <!-- Close button (mobile only) -->
            <button
                @click="emit('close')"
                class="md:hidden flex-shrink-0 min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg bg-white/10 hover:bg-white/20 transition"
                aria-label="Close navigation menu"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Navigation Content -->
        <div class="sidebar-content">
            <!-- ================= ADMIN VARIANT ================= -->
            <nav
                v-if="
                    ![
                        'record',
                        'interviewer',
                        'evaluator',
                        'applicant',
                    ].includes(props.variant)
                "
            >
                <ul class="space-y-2">
                    <!-- Dashboard -->
                    <li>
                        <NavLink
                            :href="route('dashboard')"
                            :active="isDashboardActive"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isDashboardActive }"
                            @click="emit('close')"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="tachometer-alt"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isExpanded" class="nav-label">
                                Dashboard
                            </span>
                            <div
                                v-if="isExpanded"
                                class="nav-indicator"
                                :class="{ active: isDashboardActive }"
                            ></div>
                        </NavLink>
                    </li>

                    <!-- Passer Management Dropdown -->
                    <li>
                        <div
                            @click.stop="togglePasserMenu"
                            class="nav-item group cursor-pointer"
                            :class="{
                                'nav-item-active':
                                    isPasserDropdownOpen ||
                                    isUploadFormActive ||
                                    isListPassersActive ||
                                    isConfirmedApplicantsActive,
                            }"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon icon="users" class="text-lg" />
                            </div>
                            <span v-if="isExpanded" class="nav-label">
                                Passers
                            </span>
                            <div class="flex items-center gap-2">

                                <FontAwesomeIcon
                                    v-if="isExpanded"
                                    :icon="
                                        isPasserDropdownOpen
                                            ? 'caret-down'
                                            : 'caret-right'
                                    "
                                    class="text-xs text-gray-400 transition-transform duration-200 dark:text-gray-200"
                                    :class="{
                                        'rotate-90': isPasserDropdownOpen,
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
                                v-show="isPasserDropdownOpen && isExpanded"
                                class="dropdown-content ml-10 mt-1 space-y-1"
                            >
                                <Link
                                    :href="route('upload.form')"
                                    class="dropdown-item"
                                    :class="{
                                        'dropdown-item-active':
                                            isUploadFormActive,
                                    }"
                                    @click="emit('close')"
                                >
                                    <FontAwesomeIcon
                                        icon="upload"
                                        class="text-xs mr-2"
                                    />
                                    Upload Passer
                                </Link>
                                <Link
                                    :href="route('lists')"
                                    class="dropdown-item"
                                    :class="{
                                        'dropdown-item-active':
                                            isListPassersActive,
                                    }"
                                    @click="emit('close')"
                                >
                                    <FontAwesomeIcon
                                        icon="list"
                                        class="text-xs mr-2"
                                    />
                                    List Passers
                                </Link>
                                <Link
                                    :href="route('confirmed-applicants.index')"
                                    class="dropdown-item"
                                    :class="{
                                        'dropdown-item-active':
                                            isConfirmedApplicantsActive,
                                    }"
                                    @click="emit('close')"
                                >
                                    <FontAwesomeIcon
                                        icon="calendar-check"
                                        class="text-xs mr-2"
                                    />
                                    Confirmed Applicants
                                </Link>
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
                            @click="emit('close')"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="envelope-open-text"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isExpanded" class="nav-label">
                                Applications
                            </span>
                            <div
                                v-if="isExpanded"
                                class="nav-indicator"
                                :class="{ active: isApplicationsActive }"
                            ></div>
                        </NavLink>
                    </li>

                    <!-- Evaluate Grades (Admin) -->
                    <li>
                        <NavLink
                            :href="route('evaluator.dashboard')"
                            :active="isActiveRoute('evaluator.dashboard')"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isActiveRoute('evaluator.dashboard') }"
                            @click="emit('close')"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="clipboard-list"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isExpanded" class="nav-label">
                                Evaluate Grades
                            </span>
                            <div
                                v-if="isExpanded"
                                class="nav-indicator"
                                :class="{ active: isActiveRoute('evaluator.dashboard') }"
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
                            @click="emit('close')"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="graduation-cap"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isExpanded" class="nav-label">
                                Programs
                            </span>
                            <div
                                v-if="isExpanded"
                                class="nav-indicator"
                                :class="{ active: isProgramsActive }"
                            ></div>
                        </NavLink>
                    </li>

                    <!-- Reports Dropdown -->
                    <li>
                        <div
                            class="nav-item group cursor-pointer"
                            @click="toggleReportsMenu"
                            :class="{
                                'nav-item-active':
                                    isReportsDropdownOpen ||
                                    isReportsActive ||
                                    isTestPasserReportsActive ||
                                    isMasterlistReportsActive ||
                                    isControlListActive ||
                                    isEmailTrackingActive,
                            }"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon icon="chart-pie" class="text-lg" />
                            </div>
                            <span v-if="isExpanded" class="nav-label">
                                Reports
                            </span>
                            <div class="flex items-center gap-2">
                                <FontAwesomeIcon
                                    v-if="isExpanded"
                                    :icon="
                                        isReportsDropdownOpen
                                            ? 'caret-down'
                                            : 'caret-right'
                                    "
                                    class="text-xs text-gray-400 transition-transform duration-200 dark:text-gray-200"
                                    :class="{
                                        'rotate-90': isReportsDropdownOpen,
                                    }"
                                />
                            </div>
                        </div>

                        <!-- Dropdown Content -->
                        <transition
                            enter-active-class="transition-all duration-200 ease-out"
                            leave-active-class="transition-all duration-150 ease-in"
                            enter-from-class="opacity-0 max-h-0"
                            enter-to-class="opacity-100 max-h-60"
                            leave-from-class="opacity-100 max-h-60"
                            leave-to-class="opacity-0 max-h-0"
                        >
                            <div
                                v-show="isReportsDropdownOpen && isExpanded"
                                class="dropdown-content ml-10 mt-1 space-y-1"
                            >
                                <Link
                                    :href="route('reports.index')"
                                    class="dropdown-item"
                                    :class="{
                                        'dropdown-item-active':
                                            isReportsActive,
                                    }"
                                    @click="emit('close')"
                                >
                                    <FontAwesomeIcon
                                        icon="chart-line"
                                        class="text-xs mr-2"
                                    />
                                    Applicant Reports
                                </Link>

                                <Link
                                    :href="route('reports.logbook.index')"
                                    class="dropdown-item"
                                    :class="{
                                        'dropdown-item-active':
                                            isLogbookReportsActive,
                                    }"
                                    @click="emit('close')"
                                >
                                    <FontAwesomeIcon
                                        icon="file-alt"
                                        class="text-xs mr-2"
                                    />
                                    Official Logbook
                                </Link>
                                <Link
                                    :href="route('reports.control-list.index')"
                                    class="dropdown-item"
                                    :class="{
                                        'dropdown-item-active':
                                            isControlListActive,
                                    }"
                                    @click="emit('close')"
                                >
                                    <FontAwesomeIcon
                                        icon="clipboard-list"
                                        class="text-xs mr-2"
                                    />
                                    Control List
                                </Link>
                                <Link
                                    v-if="isAdminOrSuperAdmin"
                                    :href="route('email-tracking.index')"
                                    class="dropdown-item"
                                    :class="{
                                        'dropdown-item-active':
                                            isEmailTrackingActive,
                                    }"
                                    @click="emit('close')"
                                >
                                    <FontAwesomeIcon
                                        icon="envelope"
                                        class="text-xs mr-2"
                                    />
                                    Email Tracking
                                </Link>
                            </div>
                        </transition>
                    </li>

                    <!-- Manage Users -->
                    <li>
                        <NavLink
                            :href="route('users.index')"
                            :active="isManageActive"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isManageActive }"
                            @click="emit('close')"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="user-shield"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isExpanded" class="nav-label">
                                Manage Users
                            </span>
                            <div
                                v-if="isExpanded"
                                class="nav-indicator"
                                :class="{ active: isManageActive }"
                            ></div>
                        </NavLink>
                    </li>



                    <!-- Audit Logs (Superadmin Only) -->
                    <li v-if="isSuperAdmin">
                        <NavLink
                            :href="route('audit-logs.index')"
                            :active="isAuditLogsActive"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isAuditLogsActive }"
                            @click="emit('close')"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="history"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isExpanded" class="nav-label">
                                Audit Logs
                            </span>
                            <div
                                v-if="isExpanded"
                                class="nav-indicator"
                                :class="{ active: isAuditLogsActive }"
                            ></div>
                        </NavLink>
                    </li>

                    <!-- API Clients (Superadmin Only) -->
                    <li v-if="isSuperAdmin">
                        <NavLink
                            :href="route('api-clients.index')"
                            :active="isApiClientsActive"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isApiClientsActive }"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="network-wired"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isExpanded" class="nav-label">
                                API Clients
                            </span>
                            <div
                                v-if="isExpanded"
                                class="nav-indicator"
                                :class="{ active: isApiClientsActive }"
                            ></div>
                        </NavLink>
                    </li>

                    <!-- Cutoff Settings (Superadmin Only) -->
                    <li v-if="isSuperAdmin">
                        <NavLink
                            :href="route('cutoff-settings.index')"
                            :active="isCutoffSettingsActive"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isCutoffSettingsActive }"
                            @click="emit('close')"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="clock"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isExpanded" class="nav-label">
                                Cutoff Settings
                            </span>
                            <div
                                v-if="isExpanded"
                                class="nav-indicator"
                                :class="{ active: isCutoffSettingsActive }"
                            ></div>
                        </NavLink>
                    </li>
                </ul>
            </nav>

            <!-- ================= STAFF VARIANTS ================= -->
            <nav
                v-else-if="
                    ['record', 'interviewer', 'evaluator'].includes(
                        props.variant
                    )
                "
            >
                <ul class="space-y-2">
                    <!-- Dashboard -->
                    <li>
                        <NavLink
                            :href="route(props.variant + '.dashboard')"
                            :active="isDashboardActive"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isDashboardActive }"
                            @click="emit('close')"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="tachometer-alt"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isExpanded" class="nav-label">
                                Dashboard
                            </span>
                            <div
                                v-if="isExpanded"
                                class="nav-indicator"
                                :class="{ active: isDashboardActive }"
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
                            @click="emit('close')"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="envelope-open-text"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isExpanded" class="nav-label">
                                Applications
                            </span>
                            <div
                                v-if="isExpanded"
                                class="nav-indicator"
                                :class="{ active: isApplicationsActive }"
                            ></div>
                        </NavLink>
                    </li>

                    <!-- Programs -->
                    <li>
                        <NavLink
                            :href="route(props.variant + '.programs')"
                            :active="isStaffProgramsActive"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isStaffProgramsActive }"
                            @click="emit('close')"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon
                                    icon="clipboard-list"
                                    class="text-lg"
                                />
                            </div>
                            <span v-if="isExpanded" class="nav-label">
                                Programs
                            </span>
                            <div
                                v-if="isExpanded"
                                class="nav-indicator"
                                :class="{ active: isStaffProgramsActive }"
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
                            @click="emit('close')"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon icon="home" class="text-lg" />
                            </div>
                            <span v-if="isExpanded" class="nav-label">
                                Dashboard
                            </span>
                            <div
                                v-if="isExpanded"
                                class="nav-indicator"
                                :class="{ active: isDashboardActive }"
                            ></div>
                        </NavLink>
                    </li>
                    <li v-if="showQualifiedProgramsNav">
                        <NavLink
                            :href="route('applicant.qualified-programs.page')"
                            :active="isQualifiedProgramsActive"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isQualifiedProgramsActive }"
                            @click="emit('close')"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon icon="graduation-cap" class="text-lg" />
                            </div>
                            <span v-if="isExpanded" class="nav-label">
                                Qualified Programs
                            </span>
                            <div
                                v-if="isExpanded"
                                class="nav-indicator"
                                :class="{ active: isQualifiedProgramsActive }"
                            ></div>
                        </NavLink>
                    </li>
                    <li>
                        <NavLink
                            :href="route('applicant.profile')"
                            :active="isProfileActive"
                            class="nav-item group"
                            :class="{ 'nav-item-active': isProfileActive }"
                            @click="emit('close')"
                        >
                            <div class="nav-icon">
                                <FontAwesomeIcon icon="user-shield" class="text-lg" />
                            </div>
                            <span v-if="isExpanded" class="nav-label">
                                Profile
                            </span>
                            <div
                                v-if="isExpanded"
                                class="nav-indicator"
                                :class="{ active: isProfileActive }"
                            ></div>
                        </NavLink>
                    </li>
                </ul>
            </nav>
        </div>

        <!-- Bottom Section -->
        <div class="sidebar-footer mt-auto pt-8 border-t border-white/10">
            <ul class="space-y-2">
                <!-- Logout -->
                <li>
                    <form @submit.prevent="logout">
                        <button
                            type="submit"
                            class="nav-item group w-full text-left cursor-pointer hover:bg-red-600/20 transition-colors duration-200"
                        >
                            <div class="nav-icon text-red-300">
                                <FontAwesomeIcon
                                    icon="sign-out-alt"
                                    class="text-lg"
                                />
                            </div>
                            <span
                                v-if="isExpanded"
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

<style scoped lang="postcss">
.sidebar {
    background: linear-gradient(180deg, #9e122c 0%, #800000 100%);
    display: flex;
    flex-direction: column;
    transition-property: width, padding;
    transition-duration: 300ms;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}

/* Navigation Items */
.nav-item {
    @apply flex items-center gap-3 px-4 py-3 rounded-xl transition-all
        duration-200 relative overflow-hidden min-h-[44px];
}

.nav-item:not(.nav-item-active):hover {
    background: linear-gradient(
        90deg,
        rgba(255, 215, 0, 0.1) 0%,
        rgba(251, 203, 119, 0.1) 100%
    );
    transform: translateX(4px);
}

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

.nav-icon {
    @apply w-6 h-6 flex items-center justify-center text-white
        transition-colors duration-200;
}

.nav-label {
    @apply flex-1 text-sm font-medium text-white transition-all duration-200
        whitespace-nowrap overflow-hidden;
}

.nav-indicator {
    @apply hidden;
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

/* Fade transition for backdrop */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
