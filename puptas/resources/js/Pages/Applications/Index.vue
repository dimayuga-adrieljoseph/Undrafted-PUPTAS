<script setup>
import { ref, computed, onMounted, watch } from "vue";
const axios = window.axios;
import { Head } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";

const props = defineProps({
    selectedUserId: {
        type: [Number, String],
        default: null
    }
});

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
const showImageModal = ref(false);
const previewImage = ref("");
const selectedUserFiles = ref({});
const isEvaluating = ref(false);
const filesToReturn = ref({});

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
                    userNotFoundError.value = `Applicant with ID ${props.selectedUserId} not found.`;
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

onMounted(fetchUsers);

// Summary stats
const summaryItems = computed(() => [
    { 
        label: "Total Applicants", 
        value: users.value.length, 
        icon: {
            template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>'
        },
        color: 'blue'
    },
    { 
        label: "Accepted", 
        value: users.value.filter(u => u.status?.toLowerCase() === 'accepted').length, 
        icon: {
            template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
        },
        color: 'green'
    },
    { 
        label: "Pending", 
        value: users.value.filter(u => u.status?.toLowerCase() === 'pending').length, 
        icon: {
            template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
        },
        color: 'yellow'
    },
    { 
        label: "Rejected", 
        value: users.value.filter(u => u.status?.toLowerCase() === 'rejected').length, 
        icon: {
            template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
        },
        color: 'red'
    }
]);

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
            const aVal = (a[sortKey.value] || "").toString().toLowerCase();
            const bVal = (b[sortKey.value] || "").toString().toLowerCase();
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

watch([searchQuery, statusFilter, sortKey, sortAsc], () => {
    currentPage.value = 1;
});

const getStatusClass = (status) => {
    const s = (status || "").toLowerCase();
    if (s === "accepted") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "pending") return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300";
    if (s === "rejected") return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300";
    return "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400";
};

const selectUser = async (user) => {
    try {
        const response = await axios.get(
            `/admin-dashboard/user-files/${user.id}`
        );

        selectedUser.value = {
            ...user,
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
        file11: "Grade 11 Report",
        file12: "Grade 12 Report",
        schoolId: "School ID",
        nonEnrollCert: "Certificate of Non-Enrollment",
        psa: "PSA Birth Certificate",
        goodMoral: "Good Moral Certificate",
        underOath: "Under Oath Document",
        photo2x2: "2x2 Photo",
    };
    return map[key] || key;
};

const capitalize = (str) =>
    typeof str === "string" ? str.charAt(0).toUpperCase() + str.slice(1) : "";

const formatDate = (date) => {
    const d = new Date(date);
    return d.toLocaleString();
};

const closeUserCard = () => {
    selectedUser.value = null;
    filesToReturn.value = {};
};

const openImageModal = (src) => {
    previewImage.value = src;
    showImageModal.value = true;
};

const closeImageModal = () => {
    showImageModal.value = false;
    previewImage.value = "";
};

const sortBy = (key) => {
    if (sortKey.value === key) {
        sortAsc.value = !sortAsc.value;
    } else {
        sortKey.value = key;
        sortAsc.value = true;
    }
};

const clearFilters = () => {
    searchQuery.value = "";
    statusFilter.value = "";
    sortKey.value = "lastname";
    sortAsc.value = true;
    currentPage.value = 1;
    userNotFoundError.value = null;
};

const toggleSortOrder = () => {
    sortAsc.value = !sortAsc.value;
};
</script>

<template>
    <Head title="All Applications" />
    <AppLayout>
        <div class="px-4 md:px-8 mb-8">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">All Applications</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Manage and review applicant submissions</p>
                </div>
            </div>

            <!-- User Not Found Error -->
            <div v-if="userNotFoundError" class="mb-4 p-4 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-300 rounded-lg flex items-start">
                <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1">
                    <p class="font-semibold">Applicant Not Found</p>
                    <p class="text-sm">{{ userNotFoundError }}</p>
                </div>
                <button @click="userNotFoundError = null" class="ml-4 text-red-700 dark:text-red-300 hover:text-red-900 dark:hover:text-red-100">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div
                    v-for="(item, index) in summaryItems"
                    :key="index"
                    class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">{{ item.label }}</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ item.value.toLocaleString() }}</p>
                        </div>
                        <div :class="[
                            'p-3 rounded-lg',
                            item.color === 'blue' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300' :
                            item.color === 'green' ? 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-300' :
                            item.color === 'yellow' ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-300' :
                            'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-300'
                        ]">
                            <component :is="item.icon" class="w-6 h-6" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col lg:flex-row gap-4">
                        <!-- Search -->
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search Applicants</label>
                            <div class="relative">
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search by name..."
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                />
                                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="w-full lg:w-48">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                            <select
                                v-model="statusFilter"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                            >
                                <option value="">All Statuses</option>
                                <option value="accepted">Accepted</option>
                                <option value="pending">Pending</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>

                        <!-- Sort By -->
                        <div class="w-full lg:w-48">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sort By</label>
                            <select v-model="sortKey" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent">
                                <option value="lastname">Last Name</option>
                                <option value="firstname">First Name</option>
                                <option value="email">Email</option>
                                <option value="status">Status</option>
                            </select>
                        </div>

                        <!-- Sort Order -->
                        <div class="flex items-end space-x-2">
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
                            <button 
                                @click="clearFilters" 
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Applications Table -->
            <div>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <!-- Loading State -->
                    <div v-if="loading" class="text-center py-16">
                        <svg class="animate-spin h-10 w-10 text-[#9E122C] mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="text-gray-600 dark:text-gray-400">Loading applicants...</p>
                    </div>

                    <!-- Error State -->
                    <div v-else-if="fetchError" class="text-center py-16">
                        <svg class="w-16 h-16 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Error Loading Data</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-4">{{ fetchError }}</p>
                        <button @click="fetchUsers" class="px-4 py-2 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium">
                            Try Again
                        </button>
                    </div>

                    <!-- Table Header -->
                    <div v-else class="grid grid-cols-10 gap-4 px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        <div class="col-span-4">Name</div>
                        <div class="col-span-3">Course</div>
                        <div class="col-span-2">Status</div>
                        <div class="col-span-1 text-right">Actions</div>
                    </div>

                    <!-- Table Body -->
                    <div v-for="user in paginatedUsers" :key="user.id" 
                         class="group hover:bg-gray-50 dark:hover:bg-gray-700/50 transition border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <div class="px-6 py-4 grid grid-cols-10 gap-4 items-center text-sm">
                            <div class="col-span-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-[#9E122C]/10 text-[#9E122C] flex items-center justify-center font-semibold">
                                        {{ user.firstname?.charAt(0) }}{{ user.lastname?.charAt(0) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ user.firstname }} {{ user.lastname }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-span-3 text-gray-600 dark:text-gray-300">
                                {{ user.program?.name || "—" }}
                            </div>
                            <div class="col-span-2">
                                <span
                                    :class="getStatusClass(user.status)"
                                    class="px-2.5 py-1 rounded-full text-xs font-medium"
                                >
                                    {{ user.status || "Unknown" }}
                                </span>
                            </div>
                            <div class="col-span-1 text-right">
                                <button 
                                    @click="selectUser(user)" 
                                    class="p-2 text-gray-400 hover:text-[#9E122C] dark:hover:text-[#9E122C] transition rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                    title="View Details"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="!loading && !fetchError && paginatedUsers.length === 0" class="text-center py-16">
                        <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No applicants found</h3>
                        <p class="text-gray-500 dark:text-gray-400">Try adjusting your search or filter criteria</p>
                    </div>
                </div>

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
                            <span class="px-4 py-2 bg-[#9E122C] text-white rounded-lg font-medium">{{ currentPage }}</span>
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
                class="fixed top-0 right-0 w-full md:w-2/5 h-full bg-white dark:bg-gray-900 p-6 z-50 shadow-xl overflow-y-auto"
            >
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Applicant Details</h3>
                    <button 
                        @click="closeUserCard"
                        class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Profile Section -->
                <div class="flex items-center gap-4 mb-6 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                    <div class="w-16 h-16 rounded-full bg-[#9E122C] text-white flex items-center justify-center text-2xl font-semibold">
                        {{ selectedUser.firstname?.charAt(0) }}{{ selectedUser.lastname?.charAt(0) }}
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ selectedUser.lastname }}, {{ selectedUser.firstname }}
                        </h4>
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

                <!-- Grades Section -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Academic Grades</h4>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Math</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ selectedUser?.grades?.mathematics ?? "—" }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Science</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ selectedUser?.grades?.science ?? "—" }}</p>
                        </div>
                        <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg text-center">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">English</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ selectedUser?.grades?.english ?? "—" }}</p>
                        </div>
                    </div>
                </div>

                <!-- Documents Section -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Uploaded Documents</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div
                            v-for="(src, key) in selectedUserFiles"
                            :key="key"
                            class="p-2 bg-gray-50 dark:bg-gray-800/50 rounded-lg"
                        >
                            <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2 truncate">
                                {{ formatFileKey(key) }}
                            </p>
                            <img
                                v-if="src"
                                :src="src"
                                :alt="formatFileKey(key)"
                                class="w-full h-24 object-cover rounded-lg cursor-pointer hover:opacity-80 transition"
                                @click="openImageModal(src)"
                            />
                            <div
                                v-else
                                class="w-full h-24 flex items-center justify-center text-xs text-gray-400 bg-gray-200 dark:bg-gray-700 rounded-lg"
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
                            <div class="absolute -left-[9px] top-0 w-4 h-4 rounded-full bg-[#9E122C] border-2 border-white dark:border-gray-900"></div>
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

                <!-- Action Buttons -->
                <div class="mt-6 flex gap-3">
                    <button class="flex-1 px-4 py-2 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium">
                        Update Status
                    </button>
                    <button class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium">
                        Message
                    </button>
                </div>
            </div>
        </transition>

        <!-- Image Preview Modal -->
        <div
            v-if="showImageModal"
            class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-[60] p-4"
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
                    class="absolute top-4 right-4 text-white bg-black bg-opacity-50 rounded-full p-2 hover:bg-opacity-70 transition"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </AppLayout>
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