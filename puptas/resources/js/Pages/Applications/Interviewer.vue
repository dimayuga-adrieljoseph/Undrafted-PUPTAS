<template>
    <Head title="All Interviewer Applications" />
    <InterviewerLayout>
        <div
            class="max-w-7xl mx-auto py-2 px-2 sm:px-4 md:px-6 lg:px-8 overflow-x-hidden overflow-y-auto"
        >
            <div
                class="flex flex-col md:flex-row justify-between md:items-center mb-2 gap-2"
            >
                <h2 class="text-2xl font-bold text-[#9E122C]">
                    All Applications
                </h2>
                <div
                    class="flex flex-wrap justify-end gap-2 items-center w-full md:w-auto"
                >
                    <div
                        class="flex items-center border-4 border-red-400 rounded-full px-2 py-1.5 bg-white dark:bg-gray-800 w-full sm:w-auto"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 text-black dark:text-white mr-2"
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
                            class="bg-transparent border-none outline-none focus:ring-0 focus:outline-none text-sm text-black dark:text-white placeholder-gray-500 w-full"
                        />
                    </div>
                </div>

                    <button
                        @click="clearFilters"
                        class="text-sm text-black dark:text-white border border-[#9E122C] rounded px-3 py-1.5 hover:bg-[#FDE8EA] transition"
                    >
                        Clear Filters
                    </button>

                    <div class="relative">
                        <button
                            @click="showStatusDropdown = !showStatusDropdown"
                            class="text-black dark:text-white p-2 border border-[#9E122C] rounded-full"
                            title="Filter"
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
                        </button>
                        <div
                            v-if="showStatusDropdown"
                            class="absolute top-full mt-2 right-0 bg-white dark:bg-gray-800 shadow-md border border-gray-200 rounded z-50 text-sm"
                        >
                            <button
                                class="block px-4 py-2 w-full text-left hover:bg-gray-100"
                                @click="
                                    statusFilter = '';
                                    showStatusDropdown = false;
                                "
                            >
                                All
                            </button>
                            <button
                                class="block px-4 py-2 w-full text-left hover:bg-gray-100"
                                @click="
                                    statusFilter = 'accepted';
                                    showStatusDropdown = false;
                                "
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
                                @click="sortAsc = !sortAsc" 
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

                    <button
                        @click="sortAsc = !sortAsc"
                        class="text-black dark:text-white p-2 border border-[#9E122C] rounded-full"
                        title="Sort"
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
                                :d="
                                    sortAsc ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'
                                "
                            />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800/20 rounded-xl shadow p-2 overflow-x-auto">
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    Showing {{ paginatedUsers.length }} of
                    {{ filteredUsers.length }} users
                </div>
                <table class="min-w-full text-base">
                    <thead>
                        <tr class="text-left font-semibold text-black dark:text-white">
                            <th
                                class="pb-2 cursor-pointer"
                                @click="sortBy('lastname')"
                            >
                                Name
                            </th>
                            <th class="pb-2">Course</th>
                            <th class="pb-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-600">
                        <tr
                            v-for="user in paginatedUsers"
                            :key="user.id"
                            @click="selectUser(user)"
                            class="cursor-pointer hover:bg-white dark:bg-gray-800/10 backdrop-blur-sm transition"
                        >
                            <td class="py-2 text-black dark:text-white font-medium">
                                {{ user.firstname }} {{ user.lastname }}
                            </td>
                            <td class="py-2 text-white">
                                {{ user.application?.program?.name || "—" }}
                            </td>
                            <td class="py-2">
                                <span
                                    :class="getStatusClass(user.status)"
                                    class="px-2.5 py-1 rounded-full text-xs font-medium"
                                >
                                    {{ user.status || "Unknown" }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="flex justify-end items-center space-x-4 mt-4">
                    <button
                        @click="currentPage--"
                        :disabled="currentPage === 1"
                        class="text-sm text-black dark:text-white disabled:text-gray-900"
                    >
                        Previous
                    </button>
                    <span class="text-sm"
                        >Page {{ currentPage }} of {{ totalPages }}</span
                    >
                    <button
                        @click="currentPage++"
                        :disabled="currentPage === totalPages"
                        class="text-sm text-black dark:text-white disabled:text-gray-900"
                    >
                        Next
                    </button>
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
                class="fixed top-0 right-0 w-full md:w-1/3 h-full bg-white dark:bg-gray-800 dark:bg-gray-900 p-6 z-50 shadow-xl shadow-red-200 transition duration-300 ease-in-out overflow-y-auto"
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
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ selectedUser?.application?.program?.slots }} slots available
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

                <!-- Transfer Section -->
                <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Transfer to Program</h4>
                    <select
                        v-model="selectedProgramId"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent text-sm mb-3"
                    >
                        <option value="">-- Select Program --</option>
                        <option
                            v-for="p in availablePrograms"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.code }} - {{ p.name }} ({{ p.slots }} slots)
                        </option>
                    </select>
                    
                    <div class="flex gap-3">
                        <button
                            @click="acceptApplication"
                            class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium text-sm"
                        >
                            Accept
                        </button>
                        <button
                            @click="transferApplication"
                            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm"
                            :disabled="!selectedProgramId"
                        >
                            Transfer
                        </button>
                    </div>
                </div>

                <!-- Documents Section -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Uploaded Documents</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div
                            v-for="(src, key) in selectedUserFiles"
                            :key="key"
                            class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg"
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

        <!-- Snackbar -->
        <transition name="fade">
            <div
                v-if="snackbar.visible"
                class="fixed bottom-4 right-4 bg-gray-900 text-white px-4 py-2 rounded-lg shadow-lg z-50"
            >
                {{ snackbar.message }}
            </div>
        </transition>
    </InterviewerLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import InterviewerLayout from "@/Layouts/InterviewerLayout.vue";
import axios from "axios";

import {
    Chart as ChartJS,
    LineController,
    LineElement,
    CategoryScale,
    LinearScale,
    PointElement,
    Tooltip,
    Title,
    Legend,
} from "chart.js";

ChartJS.register(
    LineController,
    LineElement,
    CategoryScale,
    LinearScale,
    PointElement,
    Tooltip,
    Title,
    Legend
);

import { usePage } from "@inertiajs/vue3";

const currentPage = ref(1);
const itemsPerPage = 10;
const sortKey = ref("lastname");
const statusFilter = ref("");
const sortAsc = ref(true);
const showStatusDropdown = ref(false);

const page = usePage();
const users = ref(page.props.users || []);

const selectedUser = ref(null);
const isLoading = ref(true);
const errorMessage = ref("");
const searchQuery = ref("");
const selectedUserFiles = ref({});
const selectedProgramId = ref("");
const snackbar = ref({
    visible: false,
    message: "",
});

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
    if (s === "pending") return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300";
    if (s === "rejected") return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300";
    return "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400";
};

const fetchUsers = async () => {
    try {
        const response = await fetch("/interviewer-dashboard/applicants", {
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

const fetchPrograms = async () => {
    try {
        const response = await axios.get("/interviewer-dashboard/programs");
        availablePrograms.value = response.data.programs;
    } catch (e) {
        console.error("Failed to load programs", e);
    }
};

onMounted(() => {
    fetchUsers();
    fetchPrograms();
});

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

const selectUser = async (user) => {
    try {
        const response = await axios.get(
            `/interviewer-dashboard/application/${user.id}`
        );

        selectedUser.value = {
            ...user,
            application: {
                ...response.data.user.application,
                processes: response.data.user.application?.processes || [],
                program: response.data.user.application?.program || null,
            },
            grades: response.data.user.grades || null,
        };

        selectedUserFiles.value = response.data.uploadedFiles || {};
        
        await fetchPrograms();
    } catch (error) {
        console.error("Failed to fetch user data:", error);
        selectedUserFiles.value = {};
        selectedUser.value = null;
    }
};

const closeUserCard = () => {
    selectedUser.value = null;
    selectedProgramId.value = "";
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

const previewImage = ref(null);
const showImageModal = ref(false);

const openImageModal = (src) => {
    previewImage.value = src;
    showImageModal.value = true;
};

const closeImageModal = () => {
    showImageModal.value = false;
};

const capitalize = (str) =>
    typeof str === "string" ? str.charAt(0).toUpperCase() + str.slice(1) : "";

const formatDate = (date) => {
    const d = new Date(date);
    return d.toLocaleString();
};

const acceptApplication = async () => {
    try {
        await axios.post(
            `/interviewer-dashboard/accept/${selectedUser.value.id}`
        );
        showSnackbar("Application accepted.");
        selectedUser.value = null;
        await fetchUsers();
    } catch (e) {
        console.error("Accept failed:", e);
        const msg =
            e.response?.data?.message ||
            "Failed to accept application due to an unexpected error.";
        showSnackbar(msg);
    }
};

const transferApplication = async () => {
    if (!selectedProgramId.value) {
        showSnackbar("Please select a program to transfer to.");
        return;
    }
    
    try {
        await axios.post(
            `/interviewer-dashboard/transfer/${selectedUser.value.id}`,
            {
                program_id: selectedProgramId.value,
            }
        );
        showSnackbar("Applicant transferred successfully!");
        selectedUser.value = null;
        selectedProgramId.value = "";
        fetchUsers();
    } catch (e) {
        console.error("Transfer failed", e);
        if (e.response?.data?.message) {
            showSnackbar(e.response.data.message);
        } else {
            showSnackbar("Transfer failed due to an unexpected error.");
        }
    }
};

const availablePrograms = ref([]);

const clearFilters = () => {
    searchQuery.value = "";
    statusFilter.value = "";
    sortKey.value = "lastname";
    sortAsc.value = true;
    currentPage.value = 1;
    showStatusDropdown.value = false;
};
</script>

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