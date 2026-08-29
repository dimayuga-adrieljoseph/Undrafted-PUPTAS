<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import RecordStaffLayout from "@/Layouts/RecordStaffLayout.vue";
import BlurText from "@/Components/BlurText.vue";
import UserDetailsModal from "@/Pages/Applications/UserDetailsModal.vue";

import { usePage } from "@inertiajs/vue3";

const page = usePage();
const users = ref(page.props.users || []);
const programs = ref(page.props.programs || []);
const summary = ref(
    page.props.summary || { total: 0, accepted: 0, pending: 0, returned: 0 }
);

const props = defineProps({
    user: Object,
    allUsers: Array,
    // Admin/superadmin pass '/admin/records'; registrar gets the default
    baseUrl: {
        type: String,
        default: '/record-dashboard',
    },
});

// Summary items with icons and percentages
// Summary items — 2 cards: in-queue for records + officially enrolled
const summaryItems = computed(() => [
    {
        label: "In Queue",
        value: summary.value?.in_progress ?? 0,
        icon: {
            template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>',
        },
        percentage: null,
        color: "blue",
    },
    {
        label: "Enrolled",
        value: summary.value?.processed ?? 0,
        icon: {
            template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
        },
        percentage: null,
        color: "green",
    },
]);

const selectedUser = ref(null);
const isLoading = ref(true);
const errorMessage = ref("");
const searchQuery = ref("");
const selectedUserFiles = ref({});
const snackbar = ref({
    visible: false,
    message: "",
});
const autoRefreshTimer = ref(null);
const POLL_INTERVAL_MS = 10000;

const showSnackbar = (msg, duration = 3000) => {
    snackbar.value.message = msg;
    snackbar.value.visible = true;
    setTimeout(() => {
        snackbar.value.visible = false;
    }, duration);
};

const getStatusClass = (status) => {
    const s = (status || "").toLowerCase();
    if (s === "accepted") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "cleared_for_enrollment" || s === "officially_enrolled") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "submitted" || s === "pending") return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300";
    if (s === "returned")
        return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300";
    return "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300";
};

const fetchUsers = async () => {
    try {
        const response = await fetch(`${props.baseUrl}/applicants`, {
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });
        if (!response.ok) throw new Error("Failed to fetch users");
        users.value = await response.json();
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isLoading.value = false;
    }
};

const fetchStats = async () => {
    try {
        const response = await axios.get(`${props.baseUrl}/stats`);
        summary.value = response.data.summary || { total: 0, accepted: 0, pending: 0, returned: 0 };
        programs.value = response.data.programs || [];
    } catch (error) {
        console.error("Failed to fetch stats:", error);
    }
};

const recentListContainer = ref(null);
const dynamicPageSize = ref(2);

const updateDynamicPageSize = () => {
    if (!recentListContainer.value) return;
    const availableHeight = recentListContainer.value.clientHeight;
    // Each applicant card is ~115px (including padding, text, borders) with gap-3 (12px) = ~127px per item
    const calculated = Math.floor((availableHeight + 12) / 127);
    dynamicPageSize.value = Math.max(2, calculated);
};

let resizeObserver = null;
onMounted(() => {
    fetchUsers();
    fetchStats();
    if (recentListContainer.value && typeof ResizeObserver !== "undefined") {
        resizeObserver = new ResizeObserver(() => {
            updateDynamicPageSize();
        });
        resizeObserver.observe(recentListContainer.value);
    }
    updateDynamicPageSize();

    autoRefreshTimer.value = setInterval(async () => {
        await fetchUsers();
        await fetchStats();

        if (!selectedUser.value) {
            return;
        }

        const existsInQueue = users.value.some((u) => String(u.id) === String(selectedUser.value.id));
        if (!existsInQueue) {
            closeUserCard();
        }
    }, POLL_INTERVAL_MS);
});

onBeforeUnmount(() => {
    if (resizeObserver) {
        resizeObserver.disconnect();
        resizeObserver = null;
    }
    if (autoRefreshTimer.value) {
        clearInterval(autoRefreshTimer.value);
        autoRefreshTimer.value = null;
    }
});

const filteredUsers = computed(() => {
    if (!searchQuery.value.trim()) return users.value;
    const query = searchQuery.value.toLowerCase();
    return users.value.filter((user) => {
        return (
            user.firstname?.toLowerCase().includes(query) ||
            user.lastname?.toLowerCase().includes(query) ||
            user.email?.toLowerCase().includes(query)
        );
    });
});

const currentPage = ref(1);

const totalRecordPages = computed(() => {
    const visible = users.value.filter(
        u => u.pipeline_status === 'for_records' || u.pipeline_status === 'officially_enrolled' ||
             u.enrollment_status === 'officially_enrolled'
    );
    const filtered = searchQuery.value.trim()
        ? visible.filter(u => {
            const q = searchQuery.value.toLowerCase();
            return u.firstname?.toLowerCase().includes(q) ||
                   u.lastname?.toLowerCase().includes(q) ||
                   u.email?.toLowerCase().includes(q);
          })
        : visible;
    return Math.max(1, Math.ceil(filtered.length / dynamicPageSize.value));
});

const displayedUsers = computed(() => {
    // Show only in-progress (for_records) and officially enrolled records
    const visible = users.value.filter(
        u => u.pipeline_status === 'for_records' || u.pipeline_status === 'officially_enrolled' ||
             u.enrollment_status === 'officially_enrolled'
    );
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        const filtered = visible.filter(u =>
            u.firstname?.toLowerCase().includes(q) ||
            u.lastname?.toLowerCase().includes(q) ||
            u.email?.toLowerCase().includes(q)
        );
        const start = (currentPage.value - 1) * dynamicPageSize.value;
        return filtered.slice(start, start + dynamicPageSize.value);
    }
    const start = (currentPage.value - 1) * dynamicPageSize.value;
    return visible.slice(start, start + dynamicPageSize.value);
});

watch([searchQuery, dynamicPageSize], () => {
    if (currentPage.value > totalRecordPages.value) {
        currentPage.value = Math.max(1, totalRecordPages.value);
    }
});

watch(searchQuery, () => { currentPage.value = 1; });

const selectUser = async (user) => {
    try {
        const response = await axios.get(
            `${props.baseUrl}/application/${user.id}`
        );

        selectedUser.value = {
            ...user,
            application: {
                ...response.data.user.application,
                processes: response.data.user.application?.processes || [],
                program: response.data.user.application?.program || null,
            },
        };

        selectedUserFiles.value = response.data.uploadedFiles || {};
    } catch (error) {
        console.error("Failed to fetch user data:", error);
        selectedUserFiles.value = {};
        selectedUser.value = null;
        showSnackbar("Failed to load applicant data");
    }
};

const closeUserCard = () => {
    selectedUser.value = null;
};

const formatFileKey = (key) => {
    const map = {
        file10Front: 'Grade 10 Report Front',
        file10: 'Grade 10 Report Back',
        file11Front: "Grade 11 Report Front",
        file11: "Grade 11 Report Back",
        file12Front: "Grade 12 Report Front",
        file12: "Grade 12 Report Back",
        schoolId: "School ID",
        nonEnrollCert: "Non-Enrollment Cert",
        psa: "PSA Birth Certificate",
        goodMoral: "Good Moral Certificate",
        underOath: "Under Oath Document",
        photo2x2: "2x2 Photo",
    };
    return map[key] || key;
};

const getFileUrl = (file) => (typeof file === "string" ? file : file?.url || "");

const hasImagePreview = (file) =>
    Boolean(getFileUrl(file)) && (typeof file === "string" || file?.isImage !== false);

const previewImage = ref(null);
const showImageModal = ref(false);

const openImageModal = (file) => {
    const src = getFileUrl(file);
    if (!src || !hasImagePreview(file)) {
        return;
    }

    previewImage.value = src;
    showImageModal.value = true;
};

const closeImageModal = () => {
    showImageModal.value = false;
};

const capitalize = (str) =>
    typeof str === "string"
        ? str.charAt(0).toUpperCase() + str.slice(1).replace("_", " ")
        : "";

const formatDate = (date) => {
    if (!date) return "—";
    return new Date(date).toLocaleDateString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const acceptApplication = async () => {
    try {
        const taggedId = selectedUser.value.id;
        await axios.post(`${props.baseUrl}/tag/${taggedId}`);
        showSnackbar("Tagged as Enrolled");

        // Immediately remove from the list so UI updates without waiting for refetch
        users.value = users.value.filter(u => u.id !== taggedId);
        selectedUser.value = null;

        await fetchUsers();
        await fetchStats();
    } catch (e) {
        console.error("Tag failed:", e);
        const msg = e.response?.data?.message || "Failed to tag application";
        showSnackbar(msg);
    }
};

const untagApplication = async () => {
    try {
        await axios.post(`${props.baseUrl}/untag/${selectedUser.value.id}`);
        showSnackbar("Reverted to temporary enrolled");
        selectedUser.value = null;
        await fetchUsers();
        await fetchStats();
    } catch (e) {
        console.error("Untag failed:", e);
        const msg = e.response?.data?.message || "Failed to untag application";
        showSnackbar(msg);
    }
};
</script>

<template>
    <Head title="Record Staff Dashboard" />
    <RecordStaffLayout>
        <div class="dash-shell">
        <!-- Header & Stats Section -->
        <div class="px-4 md:px-8 mb-6 shrink-0 flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-6">
            <!-- Header Left -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 flex-1">
                <div>
                    <BlurText
                        text="Records Dashboard"
                        :delay="100"
                        animate-by="words"
                        direction="top"
                        class-name="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white"
                    />
                    <BlurText
                        text="Manage enrollment records and program applications."
                        :delay="60"
                        animate-by="words"
                        direction="top"
                        :step-duration="0.3"
                        class-name="text-gray-600 dark:text-gray-400 mt-2"
                    />
                </div>
                <div class="relative w-full sm:w-64 shrink-0">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search applicants..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                    />
                    <svg
                        class="w-5 h-5 text-gray-400 absolute left-3 top-2.5 dark:text-gray-200"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                        />
                    </svg>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 shrink-0 xl:min-w-[420px]">
                <div
                    v-for="(item, index) in summaryItems"
                    :key="index"
                    class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700 hover:shadow-md transition-all duration-300 flex items-center justify-between gap-4"
                >
                    <div>
                        <p class="text-gray-600 dark:text-gray-400 text-xs font-medium mb-1">{{ item.label }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white leading-none">{{ item.value.toLocaleString() }}</p>
                    </div>
                    <div :class="[
                        'p-2.5 rounded-lg shrink-0',
                        item.color === 'blue' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300' :
                        item.color === 'green' ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-300' :
                        item.color === 'yellow' ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-300' :
                        'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-300'
                    ]">
                        <component :is="item.icon" class="w-5 h-5" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-3 gap-6 px-4 md:px-8">
            <!-- Left Column: Programs Overview -->
            <div class="lg:col-span-2 flex flex-col min-h-0">
                <div
                    class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 h-full flex flex-col min-h-0"
                >
                    <div class="mb-4 shrink-0">
                        <h3
                            class="text-xl font-semibold text-gray-900 dark:text-white mb-1"
                        >
                            Programs Overview
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">
                            Application counts by program
                        </p>
                    </div>

                    <div
                        class="flex-1 min-h-0 overflow-y-auto pr-1"
                    >
                        <div
                            class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3"
                        >
                            <div
                                v-for="program in programs"
                                :key="program.id"
                                class="group"
                            >
                                <div
                                    class="bg-gradient-to-br from-pink-50 to-pink-100 dark:from-pink-900/20 dark:to-pink-900/30 rounded-xl p-3.5 text-center border-2 border-pink-200 dark:border-pink-800 transition-all duration-300 hover:shadow-sm"
                                >
                                    <div
                                        class="w-10 h-10 mx-auto mb-2 bg-[#9E122C] rounded-full flex items-center justify-center text-white font-bold text-base dark:bg-gray-900 dark:text-white"
                                    >
                                        {{ program.code ? program.code.charAt(0) : '?' }}
                                    </div>
                                    <p
                                        class="font-semibold text-gray-900 dark:text-white text-xs mb-1 truncate"
                                        :title="program.code || 'N/A'"
                                    >
                                        {{ program.code || 'N/A' }}
                                    </p>
                                    <p class="text-xl font-bold text-[#9E122C] dark:text-white">
                                        {{ program.applications_count || 0 }}
                                    </p>
                                    <p
                                        class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5"
                                    >
                                        applications
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Program Statistics -->
                    <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700 grid grid-cols-2 gap-4 shrink-0">
                        <div
                            class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                        >
                            <p
                                class="text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >
                                Total Programs
                            </p>
                            <p
                                class="text-xl font-bold text-gray-900 dark:text-white"
                            >
                                {{ programs.length }}
                            </p>
                        </div>
                        <div
                            class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
                        >
                            <p
                                class="text-xs text-gray-500 dark:text-gray-400 mb-1"
                            >
                                Total Applications
                            </p>
                            <p
                                class="text-xl font-bold text-gray-900 dark:text-white"
                            >
                                {{ summary?.total || 0 }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Recent Applications -->
            <div class="h-full min-h-0">
                <div
                    class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 h-full flex flex-col"
                >
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3
                                class="text-xl font-semibold text-gray-900 dark:text-white mb-1"
                            >
                                Recent Applications
                            </h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm">
                                Latest applicant records
                            </p>
                        </div>
                        <Link
                            href="/recordstaff-applications"
                            class="text-sm text-[#9E122C] hover:text-[#b51834] font-medium transition dark:text-white dark:hover:text-white"
                        >
                            View All
                        </Link>
                    </div>

                    <div ref="recentListContainer" class="space-y-3 flex-1 overflow-y-auto min-h-0">
                        <div
                            v-for="applicant in displayedUsers"
                            :key="applicant.id"
                            @click="selectUser(applicant)"
                            class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition cursor-pointer"
                        >
                            <div class="flex items-center justify-between mb-3 gap-2">
                                <div class="flex items-center space-x-3 min-w-0">
                                    <div
                                        class="w-10 h-10 bg-[#9E122C] rounded-full flex items-center justify-center text-white font-semibold dark:bg-gray-900 dark:text-gray-900 shrink-0"
                                    >
                                        {{ applicant.firstname?.[0] || ""
                                        }}{{ applicant.lastname?.[0] || "" }}
                                    </div>
                                    <div class="min-w-0">
                                        <h4
                                            class="font-semibold text-gray-900 dark:text-white truncate"
                                        >
                                            {{ applicant.firstname }}
                                            {{ applicant.lastname }}
                                        </h4>
                                        <p
                                            class="text-gray-600 dark:text-gray-400 text-sm truncate"
                                        >
                                            {{ applicant.email }}
                                        </p>
                                    </div>
                                </div>
                                <span
                                    :class="getStatusClass(applicant.status)"
                                    class="px-3 py-1 rounded-full text-xs font-semibold shrink-0"
                                >
                                    {{ applicant.status || "Pending" }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400">
                                        Program
                                    </p>
                                    <p
                                        class="text-gray-900 dark:text-white font-medium"
                                    >
                                        {{
                                            applicant.application?.program
                                                ?.code || "—"
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400">
                                        Applied
                                    </p>
                                    <p
                                        class="text-gray-900 dark:text-white font-medium"
                                    >
                                        {{
                                            formatDate(
                                                applicant.application
                                                    ?.created_at
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div
                            v-if="displayedUsers.length === 0"
                            class="text-center py-8"
                        >
                            <svg
                                class="w-12 h-12 text-gray-400 mx-auto mb-3 dark:text-gray-200"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                            <p class="text-gray-500 dark:text-gray-400">
                                No recent applications
                            </p>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Page {{ currentPage }} of {{ totalRecordPages }}
                        </p>
                        <div class="flex items-center gap-2">
                            <button
                                @click="currentPage--"
                                :disabled="currentPage === 1"
                                class="p-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition"
                                aria-label="Previous page"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <button
                                @click="currentPage++"
                                :disabled="currentPage === totalRecordPages"
                                class="p-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition"
                                aria-label="Next page"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        </div>

        <!-- Applicant Detail Modal -->
        <UserDetailsModal
            :selected-user="selectedUser"
            :selected-user-files="selectedUserFiles"
            :current-user="null"
            :available-programs="[]"
            :is-changing-course="false"
            :course-change-message="''"
            :change-course-selected-id="''"
            @close="closeUserCard"
            @open-image="openImageModal"
        >
            <template #top-actions>
                <!-- Enrollment Actions -->
                <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                    <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Enrollment Status</h4>
                    <div class="space-y-3">
                        <button
                            v-if="selectedUser?.application?.enrollment_status !== 'officially_enrolled'"
                            @click="acceptApplication"
                            class="w-full px-4 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium flex items-center justify-center gap-2 text-sm min-h-[44px]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Tag: Enrolled
                        </button>
                        <button
                            @click="untagApplication"
                            class="w-full px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium flex items-center justify-center gap-2 text-sm min-h-[44px]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Untag / Revert
                        </button>
                    </div>
                </div>
            </template>
        </UserDetailsModal>

        <!-- Image Preview Modal -->
        <transition name="fade">
            <div
                v-if="showImageModal"
                class="fixed inset-0 z-[100] flex items-center justify-center p-4"
            >
                <div
                    class="fixed inset-0 bg-black/80"
                    @click="closeImageModal"
                ></div>
                <div class="relative z-10 max-w-4xl max-h-[90vh]">
                    <img
                        :src="previewImage"
                        alt="Document Preview"
                        class="max-w-full max-h-[80vh] rounded-lg shadow-2xl"
                    />
                    <button
                        @click="closeImageModal"
                        class="absolute top-4 right-4 p-2 bg-white/10 backdrop-blur-sm rounded-full hover:bg-white/20 transition dark:bg-gray-900/10 dark:hover:bg-gray-900/20 min-h-[44px] min-w-[44px]"
                    >
                        <svg
                            class="w-6 h-6 text-white dark:text-gray-900"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </transition>

        <!-- Snackbar Notification -->
        <transition name="fade">
            <div
                v-if="snackbar.visible"
                class="fixed bottom-4 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white px-6 py-3 rounded-lg shadow-lg z-50 dark:text-gray-900"
            >
                {{ snackbar.message }}
            </div>
        </transition>
    </RecordStaffLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
