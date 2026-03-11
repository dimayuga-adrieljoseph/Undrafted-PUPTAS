<template>
    <Head title="All Interviewer Applications" />
    <EvaluatorLayout>
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
                                    evaluationStatusFilter = '';
                                    showStatusDropdown = false;
                                "
                            >
                                All Applications
                            </button>
                            <button
                                class="block px-4 py-2 w-full text-left hover:bg-gray-100"
                                @click="
                                    evaluationStatusFilter = 'pending';
                                    showStatusDropdown = false;
                                "
                            >
                                Pending Review
                            </button>
                            <button
                                class="block px-4 py-2 w-full text-left hover:bg-gray-100"
                                @click="
                                    evaluationStatusFilter = 'completed';
                                    showStatusDropdown = false;
                                "
                            >
                                Already Evaluated
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
                            :class="[
                                'cursor-pointer hover:bg-white dark:bg-gray-800/10 backdrop-blur-sm transition',
                                user.is_evaluation_completed ? 'opacity-60' : ''
                            ]"
                        >
                            <td class="py-2 text-black dark:text-white font-medium">
                                <div class="flex items-center gap-2">
                                    <span>{{ user.firstname }} {{ user.lastname }}</span>
                                    <span 
                                        v-if="user.is_evaluation_completed"
                                        class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 text-xs rounded-full font-semibold"
                                    >
                                        Evaluated
                                    </span>
                                </div>
                            </td>
                            <td class="py-2 text-black dark:text-white">
                                {{ user.program.name || "—" }}
                            </td>
                            <td class="py-2">
                                <span
                                    :class="getEvaluationStatusClass(user)"
                                    class="px-2 py-1 rounded text-sm font-semibold"
                                >
                                    {{ getEvaluationStatusText(user) }}
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

                <p
                    v-if="paginatedUsers.length === 0"
                    class="text-center text-gray-400 mt-4"
                >
                    No results found.
                </p>
            </div>
        </div>

        <!-- User Info Modal -->

        <!-- User Info Modal -->
        <transition name="slide-fade">
            <div
                v-if="selectedUser"
                class="fixed top-0 right-0 w-full md:w-1/3 h-full bg-white dark:bg-gray-800 dark:bg-gray-900 p-6 z-50 shadow-xl shadow-red-200 transition duration-300 ease-in-out overflow-y-auto"
            >
                <button
                    class="mt-6 px-4 py-2 rounded bg-[#9E122C] text-white hover:bg-[#EE6A43] transition"
                    @click="closeUserCard"
                >
                    Close
                </button>
                
                <!-- Evaluation Complete Badge -->
                <div 
                    v-if="isEvaluationCompleted"
                    class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-500 rounded-lg"
                >
                    <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">
                        ✓ Evaluation Completed
                    </p>
                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                        You have already evaluated this application. Actions are no longer available.
                    </p>
                </div>
                
                <!-- Evaluate & Cancel buttons - Only show if not completed -->
                <div v-if="!isEvaluationCompleted" class="mt-3 flex space-x-2 justify-end">
                    <button
                        v-if="!isEvaluating"
                        @click="startEvaluation"
                        class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700"
                    >
                        Return Documents
                    </button>
                    <button
                        v-if="!isEvaluating"
                        @click="submitPass"
                        class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700"
                    >
                        Pass Application
                    </button>
                    <button
                        v-if="isEvaluating"
                        @click="cancelEvaluation"
                        class="px-3 py-1 bg-gray-400 text-black text-sm rounded hover:bg-gray-500"
                    >
                        Cancel
                    </button>
                </div>

                <!-- Return note textarea -->
                <div v-if="isEvaluating && !isEvaluationCompleted" class="mt-2">
                    <label
                        for="returnNote"
                        class="block text-xs font-semibold mb-1"
                    >
                        Return Reason <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="returnNote"
                        v-model="returnNote"
                        rows="2"
                        class="w-full border rounded p-1 text-xs"
                        placeholder="Explain what the applicant needs to fix or resubmit..."
                    ></textarea>
                </div>

                <!-- Submit button -->
                <div v-if="isEvaluating && !isEvaluationCompleted" class="mt-2 flex justify-end">
                    <button
                        @click="submitReturn"
                        class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700"
                    >
                        Confirm Return
                    </button>
                </div>

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
                <!-- <p class="text-gray-700 dark:text-gray-400">
                        Username: {{ selectedUser.username }}
                    </p>
                    <p class="text-gray-700 dark:text-gray-400">
                        Phone: {{ selectedUser.phone }}
                    </p> -->

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
    </EvaluatorLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import EvaluatorLayout from "@/Layouts/EvaluatorLayout.vue";

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
const sortKey = ref("lastname"); // default to lastname
const evaluationStatusFilter = ref(""); // Filter for evaluation completion status
const sortAsc = ref(true);
const showStatusDropdown = ref(false);

const page = usePage();
const users = ref(page.props.users || []);

//const users = ref([]);
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

const chartData = {
    labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
    datasets: [
        {
            label: "Submitted",
            data: [5, 20, 35, 50, 70, 90],
            borderColor: "#2563EB",
            backgroundColor: "rgba(37, 99, 235, 0.2)",
            tension: 0.4,
        },
        {
            label: "Accepted",
            data: [2, 10, 15, 25, 40, 60],
            borderColor: "#10B981",
            backgroundColor: "rgba(16, 185, 129, 0.2)",
            tension: 0.4,
        },
    ],
};

const getStatusClass = (status) => {
    const s = (status || "").toLowerCase();
    if (s === "accepted") return "bg-green-100 text-green-700";
    if (s === "pending") return "bg-yellow-100 text-yellow-700";
    if (s === "rejected") return "bg-red-100 text-red-700";
    return "bg-gray-100 text-gray-600";
};

// Get evaluation-specific status text
const getEvaluationStatusText = (user) => {
    if (user.is_evaluation_completed) {
        // Show what action was taken
        if (user.process_action === 'passed') return "Completed - Passed";
        if (user.process_action === 'returned') return "Completed - Returned";
        return "Completed";
    }
    if (user.process_status === 'returned') return "Needs Revision";
    if (user.process_status === 'in_progress') return "Pending Review";
    return "Unknown";
};

// Get evaluation-specific status styling
const getEvaluationStatusClass = (user) => {
    if (user.is_evaluation_completed) {
        if (user.process_action === 'passed') return "bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300";
        if (user.process_action === 'returned') return "bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300";
        return "bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300";
    }
    if (user.process_status === 'returned') return "bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300";
    if (user.process_status === 'in_progress') return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300";
    return "bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400";
};

const fetchUsers = async () => {
    try {
        const response = await fetch("/evaluator-dashboard/applicants", {
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
            const matchesEvaluationStatus = evaluationStatusFilter.value
                ? (evaluationStatusFilter.value === 'completed' ? u.is_evaluation_completed : !u.is_evaluation_completed)
                : true;
            return matchesSearch && matchesEvaluationStatus;
        })
        .sort((a, b) => {
            const aVal = (a[sortKey.value] || "").toString().toLowerCase();
            const bVal = (b[sortKey.value] || "").toString().toLowerCase();
            return sortAsc.value
                ? aVal.localeCompare(bVal)
                : bVal.localeCompare(aVal);
        });
});

const displayedUsers = computed(() => {
    if (searchQuery.value.trim()) return filteredUsers.value;
    return users.value.slice(0, 4);
});

const selectUser = async (user) => {
    try {
        const response = await axios.get(`/dashboard/user-files/${user.id}`);

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

        // ✅ Add this line to load programs into the dropdown
        await fetchPrograms();
    } catch (error) {
        console.error("Failed to fetch user data:", error);
        selectedUserFiles.value = {};
        selectedUser.value = null;
    }
};

// Check if the current user's evaluator process is completed
const isEvaluationCompleted = computed(() => {
    if (!selectedUser.value || !selectedUser.value.application?.processes) {
        return false;
    }
    const evaluatorProcess = selectedUser.value.application.processes.find(
        p => p.stage === 'evaluator'
    );
    return evaluatorProcess && evaluatorProcess.status === 'completed';
});

const closeUserCard = () => {
    selectedUser.value = null;
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

const isEvaluating = ref(false);
const filesToReturn = ref({});

const returnNote = ref("");

const startEvaluation = () => {
    isEvaluating.value = true;
    filesToReturn.value = [];
    returnNote.value = "";
};

const cancelEvaluation = () => {
    isEvaluating.value = false;
    filesToReturn.value = [];
    returnNote.value = "";
};

const submitReturn = async () => {
    const selected = Object.keys(filesToReturn.value).filter(
        (k) => filesToReturn.value[k]
    );
    const note = returnNote.value.trim();
    if (selected.length === 0) {
        alert("Please select at least one file to return.");
        return;
    }
    if (note.length < 3) {
        alert("Please enter a return reason before submitting.");
        return;
    }

    try {
        const currentUserId = selectedUser.value.id;
        
        await axios.post(`/dashboard/return-files/${currentUserId}`, {
            files: selected,
            note,
        });

        alert("Files returned successfully.");

        isEvaluating.value = false;
        filesToReturn.value = {};
        returnNote.value = "";

        // ✅ Refetch updated user list & status counts
        await fetchUsers();
        
        // Find and select the updated user from the fresh list
        const updatedUser = users.value.find(u => u.id === currentUserId);
        if (updatedUser) {
            await selectUser(updatedUser);
        }
    } catch (error) {
        console.error(error);
        const msg = error.response?.data?.message || error.response?.data?.errors?.note?.[0];
        alert(msg || "Return failed. Please try again.");
    }
};

const capitalize = (str) =>
    typeof str === "string" ? str.charAt(0).toUpperCase() + str.slice(1) : "";

const formatDate = (date) => {
    const d = new Date(date);
    return d.toLocaleString(); // or .toLocaleDateString() if you prefer
};

const submitPass = async () => {
    if (!confirm("Pass this application to the interviewer stage? This cannot be undone.")) return;
    try {
        const currentUserId = selectedUser.value.id;
        
        await axios.post(
            `/evaluator/pass-application/${currentUserId}`,
            {
                note: "",
            }
        );

        alert("Application successfully passed to the next step.");

        // reset states
        isEvaluating.value = false;
        filesToReturn.value = {};
        returnNote.value = "";

        // refresh UI
        await fetchUsers();
        
        // Find and select the updated user from the fresh list
        const updatedUser = users.value.find(u => u.id === currentUserId);
        if (updatedUser) {
            await selectUser(updatedUser);
        }
    } catch (error) {
        console.error("Error passing application:", error);
        alert("Failed to pass application.");
    }
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
    try {
        await axios.post(
            `/interviewer-dashboard/transfer/${selectedUser.value.id}`,
            {
                program_id: selectedProgramId.value,
            }
        );
        alert("Applicant transferred successfully!");
        selectedUser.value = null;
        selectedProgramId.value = "";
        fetchUsers();
    } catch (e) {
        console.error("Transfer failed", e);

        // ✅ Fix: use `e` not `error`
        if (e.response?.data?.message) {
            showSnackbar(e.response.data.message); // ✅ show server message
        } else {
            showSnackbar("Transfer failed due to an unexpected error.");
        }
    }
};

const availablePrograms = ref([]);

const fetchPrograms = async () => {
    try {
        const response = await axios.get("/interviewer-dashboard/programs");
        availablePrograms.value = response.data.programs;
    } catch (e) {
        console.error("Failed to load programs", e);
    }
};

onMounted(fetchUsers);

const totalPages = computed(() =>
    Math.ceil(filteredUsers.value.length / itemsPerPage)
);

const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredUsers.value.slice(start, start + itemsPerPage);
});

// Reset page when filters/search change
watch([searchQuery, evaluationStatusFilter, sortKey, sortAsc], () => {
    currentPage.value = 1;
});

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
    evaluationStatusFilter.value = "";
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
</style>
