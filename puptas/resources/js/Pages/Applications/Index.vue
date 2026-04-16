<script setup>
import { ref, computed, onMounted, watch } from "vue";
const axios = window.axios;
import { Head, usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import EvaluatorLayout from "@/Layouts/EvaluatorLayout.vue";
import InterviewerLayout from "@/Layouts/InterviewerLayout.vue";

const page = usePage();
const currentUser = computed(() => page.props.auth?.user);
const currentLayout = computed(() => {
    const roleId = currentUser.value?.role_id;
    if (roleId === 3) return EvaluatorLayout;
    if (roleId === 4) return InterviewerLayout;
    return AppLayout; // Default to admin layout for role 2 or others
});

const props = defineProps({
    selectedUserId: {
        type: [Number, String],
        default: null
    }
});

// State
const users = ref([]);
const loading = ref(false);
const fetchError = ref(null);
const userNotFoundError = ref(null);
const searchQuery = ref("");
const statusFilter = ref("");
const selectedUser = ref(null);
const currentPage = ref(1);
const itemsPerPage = 10;
const sortKey = ref("lastname");
const sortAsc = ref(true);
const showStatusDropdown = ref(false);
const selectedUserFiles = ref({});
const showImageModal = ref(false);
const previewImage = ref("");

// Change Course State
const availablePrograms = ref([]);
const changeCourseSelectedId = ref("");
const isChangingCourse = ref(false);
const courseChangeMessage = ref("");

// Fetch programs for Admin/Registrar
const fetchPrograms = async () => {
    try {
        const response = await axios.get("/record-dashboard/programs");
        availablePrograms.value = response.data.programs || [];
    } catch (err) {
        console.error("Failed to fetch programs:", err);
    }
};

// Fetch users
const fetchUsers = async () => {
    loading.value = true;
    fetchError.value = null;
    try {
        const response = await axios.get("/dashboard/users");
        if (!Array.isArray(response.data)) {
            fetchError.value = typeof response.data === 'string' ? response.data : JSON.stringify(response.data);
            users.value = [];
        } else {
            users.value = response.data;
            
            if (props.selectedUserId) {
                const user = users.value.find(u => u.id == props.selectedUserId);
                if (user) {
                    await selectUser(user);
                } else {
                    userNotFoundError.value = `Applicant with ID ${props.selectedUserId} not found. They may have been deleted or you may not have permission to view this record.`;
                }
            }
        }
    } catch (err) {
        console.error("Failed to fetch users:", err);
        fetchError.value = err.response?.data || err.message || String(err);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchUsers();
    if (currentUser.value?.role_id === 2 || currentUser.value?.role_id === 4 || currentUser.value?.role_id === 7) {
        fetchPrograms();
    }
});

// Filtered & sorted users
const filteredUsers = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return users.value
        .filter((u) => {
            const fullName = `${u.firstname} ${u.lastname}`.toLowerCase();
            const matchesSearch = fullName.includes(q);
            const matchesStatus = statusFilter.value
                ? u.status?.toLowerCase() === statusFilter.value
                : true;
            return matchesSearch && matchesStatus;
        })
        .sort((a, b) => {
            let aVal, bVal;
            
            if (sortKey.value === 'program.name') {
                aVal = (a.program?.name || "").toString().toLowerCase();
                bVal = (b.program?.name || "").toString().toLowerCase();
            } else {
                aVal = (a[sortKey.value] || "").toString().toLowerCase();
                bVal = (b[sortKey.value] || "").toString().toLowerCase();
            }
            
            return sortAsc.value
                ? aVal.localeCompare(bVal)
                : bVal.localeCompare(aVal);
        });
});

const totalPages = computed(() =>
    Math.ceil(filteredUsers.value.length / itemsPerPage)
);

const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredUsers.value.slice(start, start + itemsPerPage);
});

// Reset page when filters/search change
watch([searchQuery, statusFilter, sortKey, sortAsc], () => {
    currentPage.value = 1;
});

// Helper functions
const getStatusClass = (status) => {
    const s = (status || "").toLowerCase();
    if (s === "accepted") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "cleared_for_enrollment" || s === "officially_enrolled") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "submitted" || s === "pending") return "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300";
    if (s === "rejected") return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300";
    if (s === "returned") return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300";
    return "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400";
};

const getUserFilesEndpoint = (userId) => {
    const roleId = currentUser.value?.role_id;
    if (roleId === 3) return `/dashboard/user-files/${userId}`; // Evaluator
    if (roleId === 4) return `/interviewer-dashboard/application/${userId}`; // Interviewer
    return `/admin-dashboard/user-files/${userId}`; // Admin (default)
};

const selectUser = async (user) => {
    try {
        const response = await axios.get(
            getUserFilesEndpoint(user.id)
        );

        selectedUser.value = {
            ...user,
            ...response.data.user,
            application: {
                ...response.data.user.application,
                processes: response.data.user.application?.processes || [],
            },
            grades: response.data.user.grades || null,
        };

        selectedUserFiles.value = response.data.uploadedFiles || {};
    } catch (error) {
        console.error("Failed to fetch user data:", error);
        selectedUserFiles.value = {};
        selectedUser.value = null;
    }
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
        nonEnrollCert: "Certificate of Non-Enrollment",
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

const capitalize = (str) =>
    typeof str === "string" ? str.charAt(0).toUpperCase() + str.slice(1) : "";

const formatDate = (date) => {
    const d = new Date(date);
    return d.toLocaleString();
};

const formatGrade = (value) => {
    if (value === null || value === undefined) return "—";
    const num = parseFloat(value);
    return isNaN(num) ? "—" : num.toFixed(2);
};

const closeUserCard = () => {
    selectedUser.value = null;
};

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
    previewImage.value = "";
};

const changeCourse = async () => {
    if (!changeCourseSelectedId.value) {
        courseChangeMessage.value = "Please select a program first.";
        return;
    }

    const selectedProg = availablePrograms.value.find(
        (p) => p.id === changeCourseSelectedId.value
    );
    const confirmMsg = selectedProg
        ? `Change course to "${selectedProg.code} - ${selectedProg.name}"? This action will be logged.`
        : "Change course? This action will be logged.";

    if (!confirm(confirmMsg)) return;

    isChangingCourse.value = true;
    courseChangeMessage.value = "";
    
    try {
        const res = await axios.post(
            `/record-dashboard/change-course/${selectedUser.value.id}`,
            { program_id: changeCourseSelectedId.value }
        );
        courseChangeMessage.value = res.data?.message ?? "Course updated successfully.";
        changeCourseSelectedId.value = "";

        // Refresh user details
        await fetchUsers();
        await fetchPrograms(); // Update slot counters
        const refreshedUser = users.value.find((u) => u.id === selectedUser.value.id);
        if (refreshedUser) {
            await selectUser(refreshedUser);
        } else {
            selectedUser.value = null;
        }
    } catch (e) {
        console.error("Course change failed:", e);
        courseChangeMessage.value =
            e.response?.data?.message ??
            e.response?.data?.errors?.program_id?.[0] ??
            "Failed to change course.";
    } finally {
        isChangingCourse.value = false;
        setTimeout(() => { courseChangeMessage.value = ""; }, 5000);
    }
};

watch(
    () => selectedUser.value?.id,
    () => {
        changeCourseSelectedId.value = "";
        courseChangeMessage.value = "";
    }
);

const sortBy = (key) => {
    if (sortKey.value === key) {
        sortAsc.value = !sortAsc.value;
    } else {
        sortKey.value = key;
        sortAsc.value = true;
    }
};

const toggleSortOrder = () => {
    sortAsc.value = !sortAsc.value;
};

const clearFilters = () => {
    searchQuery.value = "";
    statusFilter.value = "";
    sortKey.value = "lastname";
    sortAsc.value = true;
    currentPage.value = 1;
    showStatusDropdown.value = false;
    userNotFoundError.value = null;
};
</script>

<template>
    <Head title="All Applications" />
    <component :is="currentLayout">
        <div class="max-w-9xl mx-auto p-6 px-2 sm:px-4 md:px-6 lg:px-8 overflow-x-hidden overflow-y-auto">
            <!-- User Not Found Error Message -->
            <div v-if="userNotFoundError" class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg flex items-start dark:bg-red-800 dark:border-red-500 dark:text-red-400">
                <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1">
                    <p class="font-semibold">Applicant Not Found</p>
                    <p class="text-sm">{{ userNotFoundError }}</p>
                </div>
                <button @click="userNotFoundError = null" class="ml-4 text-red-700 hover:text-red-900 dark:text-red-400 dark:hover:text-red-200">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>

            <!-- Filters and Controls -->
            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 mb-6">
                <!-- Search Input -->
                <div class="flex-1 relative">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2 dark:text-gray-200"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                        />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by name..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                    />
                </div>

                <!-- Status Filter Dropdown -->
                <div class="relative">
                    <button
                        @click="showStatusDropdown = !showStatusDropdown"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium flex items-center space-x-2"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L15 12.414V19a1 1 0 01-1.447.894l-4-2A1 1 0 019 17v-4.586L3.293 6.707A1 1 0 013 6V4z"
                            />
                        </svg>
                        <span>{{ statusFilter ? statusFilter.charAt(0).toUpperCase() + statusFilter.slice(1) : 'All Status' }}</span>
                    </button>
                    <div
                        v-if="showStatusDropdown"
                        class="absolute top-full mt-2 right-0 bg-white dark:bg-gray-800 shadow-md border border-gray-200 rounded z-50 text-sm min-w-[150px] dark:border-gray-700"
                    >
                        <button
                            class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="
                                statusFilter = '';
                                showStatusDropdown = false;
                            "
                        >
                            All
                        </button>
                        <button
                            class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="
                                statusFilter = 'accepted';
                                showStatusDropdown = false;
                            "
                        >
                            Accepted
                        </button>
                        <button
                            class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="
                                statusFilter = 'cleared_for_enrollment';
                                showStatusDropdown = false;
                            "
                        >
                            Cleared for Enrollment
                        </button>
                        <button
                            class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="
                                statusFilter = 'submitted';
                                showStatusDropdown = false;
                            "
                        >
                            Pending
                        </button>
                        <button
                            class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="
                                statusFilter = 'rejected';
                                showStatusDropdown = false;
                            "
                        >
                            Rejected
                        </button>
                    </div>
                </div>

                <!-- Sort By -->
                <select v-model="sortKey" class="px-7 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent">
                    <option value="lastname">Last Name</option>
                    <option value="firstname">First Name</option>
                    <option value="email">Email</option>
                    <option value="program.name">Course</option>
                    <option value="status">Status</option>
                </select>

                <!-- Sort Order -->
                <button 
                    @click="toggleSortOrder" 
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium flex items-center space-x-2"
                >
                    <span>{{ sortAsc ? 'Ascending' : 'Descending' }}</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path v-if="sortAsc" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4 4m0 0l4-4m-4 4V4" />
                    </svg>
                </button>

                <!-- Clear Filters -->
                <button 
                    @click="clearFilters" 
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium"
                >
                    Clear
                </button>
            </div>

            <!-- Loading and Error States -->
            <div v-if="loading" class="text-center text-gray-500 py-8 dark:text-gray-300">Loading applicants…</div>
            <div v-else-if="fetchError" class="text-center text-red-500 py-8 dark:text-red-300">Error: {{ fetchError }}</div>

            <!-- Users Table -->
            <div v-else class="bg-white dark:bg-gray-800/20 rounded-xl shadow p-2 overflow-x-auto">
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    Showing {{ paginatedUsers.length }} of {{ filteredUsers.length }} users
                </div>
                
                <table class="min-w-full text-base">
                    <thead>
                        <tr class="text-left font-semibold text-black dark:text-white">
                            <th class="pb-2 cursor-pointer hover:text-[#9E122C] dark:hover:text-white" @click="sortBy('lastname')">
                                Name
                                <span v-if="sortKey === 'lastname'" class="ml-1">
                                    {{ sortAsc ? '↑' : '↓' }}
                                </span>
                            </th>
                            <th class="pb-2 cursor-pointer hover:text-[#9E122C] dark:hover:text-white" @click="sortBy('program.name')">
                                Course
                                <span v-if="sortKey === 'program.name'" class="ml-1">
                                    {{ sortAsc ? '↑' : '↓' }}
                                </span>
                            </th>
                            <th class="pb-2 cursor-pointer hover:text-[#9E122C] dark:hover:text-white" @click="sortBy('status')">
                                Status
                                <span v-if="sortKey === 'status'" class="ml-1">
                                    {{ sortAsc ? '↑' : '↓' }}
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr
                            v-for="user in paginatedUsers"
                            :key="user.id"
                            @click="selectUser(user)"
                            class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/30 transition"
                        >
                            <td class="py-3 text-gray-900 dark:text-white font-medium">
                                {{ user.firstname || user.email || '—' }} {{ user.lastname || '' }}
                            </td>
                            <td class="py-3 text-gray-700 dark:text-gray-300">
                                {{ user.program?.name || "—" }}
                            </td>
                            <td class="py-3">
                                <span
                                    :class="getStatusClass(user.status)"
                                    class="px-2.5 py-1 rounded-full text-xs font-medium"
                                >
                                    {{ user.status || "Unknown" }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="paginatedUsers.length === 0">
                            <td colspan="3" class="py-8 text-center text-gray-500 dark:text-gray-400">
                                No applicants found matching your criteria.
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="mt-8 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Showing <span class="font-medium">{{ paginatedUsers.length }}</span> of 
                        <span class="font-medium">{{ filteredUsers.length }}</span> applicants
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <button 
                            @click="currentPage--" 
                            :disabled="currentPage === 1"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                        >
                            Previous
                        </button>
                        
                        <div class="flex items-center space-x-2">
                            <span class="px-4 py-2 bg-[#9E122C] text-white rounded-lg font-medium dark:bg-gray-900 dark:text-gray-900">{{ currentPage }}</span>
                            <span class="text-gray-500 dark:text-gray-400">of</span>
                            <span class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 font-medium">{{ totalPages || 1 }}</span>
                        </div>
                        
                        <button 
                            @click="currentPage++" 
                            :disabled="currentPage === totalPages || totalPages === 0"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Details Modal -->
        <transition name="slide-fade">
            <div
                v-if="selectedUser"
                class="fixed top-0 right-0 w-full md:w-1/3 h-full bg-white dark:bg-gray-900 p-6 z-50 shadow-xl transition duration-300 ease-in-out overflow-y-auto"
            >
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Applicant Details</h3>
                    <button 
                        @click="closeUserCard"
                        class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 dark:text-gray-200"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Profile Section -->
                <div class="flex items-center gap-4 mb-6 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                    <div class="w-16 h-16 rounded-full bg-[#9E122C] text-white flex items-center justify-center text-2xl font-semibold dark:bg-gray-900 dark:text-gray-900">
                        {{ (selectedUser.firstname || selectedUser.email || '?').charAt(0).toUpperCase() }}{{ (selectedUser.lastname || '').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ selectedUser.lastname ? `${selectedUser.lastname}, ${selectedUser.firstname}` : (selectedUser.email || '—') }}
                        </h4>
                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-0.5">Student No: {{ selectedUser.student_number || 'N/A' }}</p>
                        <p class="text-gray-600 dark:text-gray-400">{{ selectedUser.email }}</p>
                    </div>
                </div>

                <!-- Program Info -->
                <div class="mb-6 p-4 bg-[#9E122C]/5 dark:bg-[#9E122C]/10 rounded-xl border border-[#9E122C]/20">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Applied Program</h4>
                    <p class="text-lg font-medium text-gray-900 dark:text-white">
                        {{ selectedUser?.application?.program?.code }} - {{ selectedUser?.application?.program?.name }}
                    </p>
                </div>

                <!-- Change Course — only visible for admins, interviewers, superadmins, and officially enrolled applicants -->
                <div
                    v-if="(currentUser?.role_id === 2 || currentUser?.role_id === 4 || currentUser?.role_id === 7) && selectedUser?.application?.enrollment_status === 'officially_enrolled'"
                    class="mb-6 p-3 border border-yellow-300 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-700"
                >
                    <h5 class="text-sm font-semibold text-yellow-800 dark:text-yellow-200 mb-2">
                        ⚠️ Change Course
                    </h5>
                    <p class="text-xs text-yellow-700 dark:text-yellow-300 mb-3">
                        Changing the course of an officially enrolled applicant will be logged in the audit trail.
                    </p>
                    <div v-if="courseChangeMessage" class="mb-3 px-3 py-2 text-sm rounded bg-white text-gray-800 border dark:bg-gray-800 dark:text-gray-300">
                        {{ courseChangeMessage }}
                    </div>
                    <select
                        v-model="changeCourseSelectedId"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-yellow-500 focus:border-transparent mb-3"
                    >
                        <option value="" disabled>Select new program…</option>
                        <option
                            v-for="prog in availablePrograms"
                            :key="prog.id"
                            :value="prog.id"
                            :disabled="prog.id === selectedUser?.application?.program?.id"
                        >
                            {{ prog.code }} - {{ prog.name }} [Slots: {{ prog.slots }}]
                            <template v-if="prog.id === selectedUser?.application?.program?.id"> (current)</template>
                        </option>
                    </select>
                    <button
                        @click="changeCourse"
                        :disabled="!changeCourseSelectedId || changeCourseSelectedId === selectedUser?.application?.program?.id || isChangingCourse"
                        class="w-full px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 disabled:opacity-50 disabled:cursor-not-allowed transition text-sm font-medium dark:text-gray-900"
                    >
                        <span v-if="isChangingCourse">Applying…</span>
                        <span v-else>Apply Changes</span>
                    </button>
                </div>

                <!-- Grades Section -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Academic Grades</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                        <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">English</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatGrade(selectedUser?.grades?.english) }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Mathematics</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatGrade(selectedUser?.grades?.mathematics) }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Science</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatGrade(selectedUser?.grades?.science) }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Grade 12 – 1st Semester</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatGrade(selectedUser?.grades?.g12_first_sem) }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Grade 12 – 2nd Semester</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ formatGrade(selectedUser?.grades?.g12_second_sem) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Documents Section -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Uploaded Documents</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div
                            v-for="(file, key) in selectedUserFiles"
                            :key="key"
                            class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg"
                        >
                            <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2 truncate">
                                {{ formatFileKey(key) }}
                            </p>
                            <img
                                v-if="hasImagePreview(file)"
                                :src="getFileUrl(file)"
                                :alt="formatFileKey(key)"
                                class="w-full h-24 object-cover rounded-lg cursor-pointer hover:opacity-80 transition"
                                @click="openImageModal(file)"
                            />
                            <div
                                v-else
                                class="w-full h-24 flex items-center justify-center text-xs text-gray-400 bg-gray-200 dark:bg-gray-700 rounded-lg dark:text-gray-200"
                            >
                                No Image
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Application History -->
                <div v-if="selectedUser?.application?.processes?.length">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Application History</h4>
                    <div class="space-y-3">
                        <div
                            v-for="(process, index) in selectedUser.application.processes"
                            :key="index"
                            class="relative pl-6 pb-3 border-l-2 border-[#9E122C] last:border-0"
                        >
                            <div class="absolute -left-[9px] top-0 w-4 h-4 rounded-full bg-[#9E122C] border-2 border-white dark:border-gray-900 dark:bg-gray-900"></div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ capitalize(process.stage) }}
                                <span :class="{
                                    'text-green-600 dark:text-green-400': process.status === 'completed',
                                    'text-yellow-600 dark:text-yellow-400': process.status === 'in_progress',
                                    'text-red-600 dark:text-red-400': process.status === 'returned',
                                }">
                                    • {{ capitalize(process.status) }}
                                </span>
                            </p>
                            <p v-if="process.notes" class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic">
                                {{ process.notes }}
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                {{ formatDate(process.created_at) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Image Preview Modal -->
        <div
            v-if="showImageModal"
            class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-[60] p-4 dark:bg-white"
            @click.self="closeImageModal"
        >
            <div class="relative max-w-4xl w-full">
                <img
                    :src="previewImage"
                    alt="Preview"
                    class="w-full h-auto rounded-lg shadow-2xl"
                />
                <button
                    @click="closeImageModal"
                    class="absolute top-4 right-4 text-white bg-black bg-opacity-50 rounded-full p-2 hover:bg-opacity-70 transition dark:text-gray-900 dark:bg-white"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </component>
</template>

<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
    transition: all 0.3s ease;
}
.slide-fade-enter-from,
.slide-fade-leave-to {
    transform: translateX(100%);
    opacity: 0;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 5px;
}

::-webkit-scrollbar-track {
    background: #FBCB77;
    border-radius: 5px;
}

::-webkit-scrollbar-thumb {
    background: #9E122C;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: #EE6A43;
}
</style>