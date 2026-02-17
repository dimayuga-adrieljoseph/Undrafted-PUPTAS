<script setup>
import { ref, computed, onMounted, watch } from "vue";
const axios = window.axios;
import { Head } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";

const users = ref([]);
const loading = ref(false);
const fetchError = ref(null);
const searchQuery = ref("");
const statusFilter = ref("");
const selectedUser = ref(null);
const currentPage = ref(1);
const itemsPerPage = 10;
const sortKey = ref("lastname");
const sortAsc = ref(true);
const showStatusDropdown = ref(false);
const selectedUserFiles = ref({});

const fetchUsers = async () => {
    loading.value = true;
    fetchError.value = null;
    try {
        const response = await axios.get("/dashboard/users");
        // Ensure we received an array; if not, surface helpful error
        if (!Array.isArray(response.data)) {
            fetchError.value = typeof response.data === 'string' ? response.data : JSON.stringify(response.data);
            users.value = [];
        } else {
            users.value = response.data;
        }
    } catch (err) {
        console.error("Failed to fetch users:", err);
        fetchError.value = err.response?.data || err.message || String(err);
    } finally {
        loading.value = false;
    }
};

onMounted(fetchUsers);

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

// Reset page when filters/search change
watch([searchQuery, statusFilter, sortKey, sortAsc], () => {
    currentPage.value = 1;
});

const getStatusClass = (status) => {
    const s = (status || "").toLowerCase();
    if (s === "accepted") return "bg-green-100 text-green-700";
    if (s === "pending") return "bg-yellow-100 text-yellow-700";
    if (s === "rejected") return "bg-red-100 text-red-700";
    return "bg-gray-100 text-gray-600";
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
        console.log(
            "User & files:",
            selectedUser.value,
            selectedUserFiles.value
        );
        console.log("processes:", selectedUser.processes);

        console.log("Processes:", selectedUser.value.application?.processes);
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
    return d.toLocaleString(); // or .toLocaleDateString() if you prefer
};

const closeUserCard = () => {
    selectedUser.value = null;
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
    showStatusDropdown.value = false;
};
</script>

<template>
    <Head title="All Applications" />
    <AppLayout>
        <div
            class="max-w-7xl mx-auto p-6 px-2 sm:px-4 md:px-6 lg:px-8 overflow-x-hidden overflow-y-auto"
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
                        class="flex items-center border-4 border-red-400 rounded-full px-2 py-1.5 bg-white w-full sm:w-auto"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 text-[#9E122C] mr-2"
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
                            class="bg-transparent border-none outline-none focus:ring-0 focus:outline-none text-sm text-[#9E122C] placeholder-gray-500 w-full"
                        />
                    </div>

                    <button
                        @click="clearFilters"
                        class="text-sm text-[#9E122C] border border-[#9E122C] rounded px-3 py-1.5 hover:bg-[#FDE8EA] transition"
                    >
                        Clear Filters
                    </button>

                    <div class="relative">
                        <button
                            @click="showStatusDropdown = !showStatusDropdown"
                            class="text-[#9E122C] p-2 border border-[#9E122C] rounded-full"
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
                            class="absolute top-full mt-2 right-0 bg-white shadow-md border border-gray-200 rounded z-50 text-sm"
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
                                Accepted
                            </button>
                            <button
                                class="block px-4 py-2 w-full text-left hover:bg-gray-100"
                                @click="
                                    statusFilter = 'pending';
                                    showStatusDropdown = false;
                                "
                            >
                                Pending
                            </button>
                            <button
                                class="block px-4 py-2 w-full text-left hover:bg-gray-100"
                                @click="
                                    statusFilter = 'rejected';
                                    showStatusDropdown = false;
                                "
                            >
                                Rejected
                            </button>
                        </div>
                    </div>

                    <button
                        @click="sortAsc = !sortAsc"
                        class="text-[#9E122C] p-2 border border-[#9E122C] rounded-full"
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

            <div class="bg-white/20 rounded-xl shadow p-2 overflow-x-auto">
                <div v-if="loading" class="text-center text-gray-500 py-4">Loading applicants…</div>
                <div v-else-if="fetchError" class="text-center text-red-500 py-4">Error: {{ fetchError }}</div>
                <div v-if="!loading && !fetchError" class="text-sm text-[#4B5563] mb-2">
                    Showing {{ paginatedUsers.length }} of
                    {{ filteredUsers.length }} users
                </div>
                <table class="min-w-full text-base">
                    <thead>
                        <tr class="text-left text-white font-semibold">
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
                            class="cursor-pointer hover:bg-white/10 backdrop-blur-sm transition"
                        >
                            <td class="py-2 text-[#111827] font-medium">
                                {{ user.firstname }} {{ user.lastname }}
                            </td>
                            <td class="py-2 text-[#111827]">
                                {{ user.program.name || "—" }}
                            </td>
                            <td class="py-2">
                                <span
                                    :class="getStatusClass(user.status)"
                                    class="px-2 py-1 rounded text-sm font-semibold"
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
                        class="text-sm text-[#9E122C] disabled:text-gray-900"
                    >
                        Previous
                    </button>
                    <span class="text-sm"
                        >Page {{ currentPage }} of {{ totalPages }}</span
                    >
                    <button
                        @click="currentPage++"
                        :disabled="currentPage === totalPages"
                        class="text-sm text-[#9E122C] disabled:text-gray-900"
                    >
                        Next
                    </button>
                </div>

                <p
                    v-if="paginatedUsers.length === 0"
                    class="text-center text-gray-400 mt-4"
                >
                    No results found.
                </p>
            </div>
        </div>

        <!-- User Info Modal -->
        <transition name="slide-fade">
            <div
                v-if="selectedUser"
                class="fixed top-0 right-0 w-full md:w-1/3 h-full bg-white dark:bg-gray-900 p-6 z-50 shadow-xl shadow-red-200 transition duration-300 ease-in-out overflow-y-auto"
            >
                <button
                    class="mt-6 px-4 py-2 rounded bg-[#9E122C] text-white hover:bg-[#EE6A43] transition"
                    @click="closeUserCard"
                >
                    Close
                </button>

                <h3
                    class="text-xl font-semibold text-gray-900 dark:text-white mb-2"
                >
                    User Information
                </h3>
                <p class="text-gray-800 dark:text-gray-200 font-medium">
                    Name: {{ selectedUser.lastname }},
                    {{ selectedUser.firstname }}
                </p>
                <p class="text-gray-700 dark:text-gray-400">
                    Email: {{ selectedUser.email }}
                </p>
                <h4
                    class="text-sm font-bold text-gray-700 dark:text-white mb-1"
                >
                    Grades
                </h4>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Math: {{ selectedUser?.grades?.mathematics ?? "—" }}
                </p>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Science: {{ selectedUser?.grades?.science ?? "—" }}
                </p>
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    English: {{ selectedUser?.grades?.english ?? "—" }}
                </p>

                <div
                    class="mt-3 p-2 border rounded bg-gray-100 dark:bg-gray-800"
                >
                    <h4
                        class="text-sm font-bold text-gray-700 dark:text-white mb-1"
                    >
                        Current Program for Acceptance:
                    </h4>
                    <p class="text-sm text-gray-800 dark:text-gray-300">
                        {{ selectedUser?.application?.program?.code }} -
                        {{ selectedUser?.application?.program?.name }}
                    </p>
                </div>

                <section class="mt-3 text-sm">
                    <h4 class="font-semibold mb-1 text-base">
                        Uploaded Documents
                    </h4>
                    <div class="grid grid-cols-3 gap-2">
                        <div
                            v-for="(src, key) in selectedUserFiles"
                            :key="key"
                            class="flex flex-col items-start space-y-1"
                        >
                            <div class="flex items-center space-x-2 w-full">
                                <input
                                    v-if="isEvaluating"
                                    type="checkbox"
                                    :id="key"
                                    v-model="filesToReturn[key]"
                                    class="h-4 w-4 mt-1"
                                />
                                <label
                                    :for="key"
                                    class="text-xs font-medium truncate w-full"
                                >
                                    {{ formatFileKey(key) }}
                                </label>
                            </div>
                            <div class="w-full">
                                <img
                                    v-if="src"
                                    :src="src"
                                    alt="Uploaded Document"
                                    class="h-16 w-full object-contain border rounded cursor-pointer"
                                    @click="openImageModal(src)"
                                />
                                <div
                                    v-else
                                    class="h-16 flex items-center justify-center text-[10px] italic text-gray-400 border rounded"
                                >
                                    No Image
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Application History -->
                    <section
                        v-if="selectedUser?.application?.processes?.length"
                        class="mt-4"
                    >
                        <h4
                            class="font-semibold mb-2 text-base text-gray-800 dark:text-gray-200"
                        >
                            Application History
                        </h4>
                        <ul class="border-l-2 border-red-400 pl-3 space-y-2">
                            <li
                                v-for="(process, index) in selectedUser
                                    .application.processes"
                                :key="index"
                                class="relative"
                            >
                                <div
                                    class="absolute -left-[10px] top-1 w-3 h-3 bg-red-600 rounded-full border-2 border-white"
                                ></div>
                                <p
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ capitalize(process.stage) }} -
                                    <span
                                        :class="{
                                            'text-green-600':
                                                process.status === 'completed',
                                            'text-yellow-600':
                                                process.status ===
                                                'in_progress',
                                            'text-red-600':
                                                process.status === 'returned',
                                        }"
                                    >
                                        {{ capitalize(process.status) }}
                                    </span>
                                </p>
                                <p
                                    v-if="process.notes"
                                    class="text-xs text-gray-500 italic"
                                >
                                    Note: {{ process.notes }}
                                </p>
                                <p class="text-xs text-gray-400">
                                    {{ formatDate(process.created_at) }}
                                </p>
                            </li>
                        </ul>
                    </section>
                </section>
            </div>
        </transition>
        <!-- Image Preview Modal -->
        <div
            v-if="showImageModal"
            class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-50"
            @click.self="closeImageModal"
        >
            <img
                :src="previewImage"
                alt="Preview"
                class="max-w-full max-h-full rounded shadow-lg"
            />
            <button
                @click="closeImageModal"
                class="absolute top-5 right-5 text-white text-4xl font-bold hover:text-gray-300"
                aria-label="Close preview"
            >
                &times;
            </button>
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
</style>
