<template>
    <Head title="All Medical Applications" />
    <MedicalLayout>
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
                <div class="text-sm text-[#4B5563] mb-2">
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
                            <td class="py-2 text-white">
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

                <div
                    class="mt-3 p-2 border rounded bg-gray-100 dark:bg-gray-800"
                >
                    <h4
                        class="text-sm font-bold text-gray-700 dark:text-white mb-1"
                    >
                        Current Program:
                    </h4>
                    <p class="text-sm text-gray-800 dark:text-gray-300">
                        {{ selectedUser?.application?.program?.code }} -
                        {{ selectedUser?.application?.program?.name }}
                    </p>
                </div>

                <!-- Evaluate & Cancel buttons -->
                <div class="mt-3 flex space-x-2 justify-end">
                    <button
                        v-if="!isEvaluating"
                        @click="startEvaluation"
                        class="px-3 py-1 text-gray-600 text-l rounded hover:text-yellow-700"
                    >
                        <font-awesome-icon :icon="faBolt" /> Actions
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
                <div v-if="isEvaluating" class="mt-2">
                    <label
                        for="returnNote"
                        class="block text-xs font-semibold mb-1"
                    >
                        Return Reason / Note
                    </label>
                    <textarea
                        id="returnNote"
                        v-model="returnNote"
                        rows="2"
                        class="w-full border rounded p-1 text-xs"
                        placeholder="Reason for returning..."
                    ></textarea>
                </div>

                <!-- Submit button -->
                <div v-if="isEvaluating" class="mt-2 flex justify-end">
                    <button
                        @click="submitReturn"
                        class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700"
                    >
                        Return Selected Files
                    </button>
                    <button
                        @click="acceptApplication"
                        class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                    >
                        Clear Medical
                    </button>
                </div>

                <!-- <div class="mt-4">
                        Accept and Transfer buttons
                        <div class="mt-4 flex justify-end space-x-2">
                            <button
                                @click="acceptApplication"
                                class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                            >
                                Clear Medical
                            </button>
                        </div>
                    </div> -->

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
                                    Note: {{ process.notes }} By:
                                    {{ process.performed_by.firstname }}
                                    {{ process.performed_by.lastname }}
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
        <!-- Snackbar -->
        <transition name="fade">
            <div
                v-if="snackbar.visible"
                class="fixed bottom-4 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white px-4 py-2 rounded shadow-lg z-50"
            >
                {{ snackbar.message }}
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
    </MedicalLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import MedicalLayout from "@/Layouts/MedicalLayout.vue";

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
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faBolt } from "@fortawesome/free-solid-svg-icons";

const currentPage = ref(1);
const itemsPerPage = 10;
const sortKey = ref("lastname"); // default to lastname
const statusFilter = ref("");
const sortAsc = ref(true);

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

const fetchUsers = async () => {
    try {
        const response = await fetch("/medical-dashboard/applicants", {
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

const displayedUsers = computed(() => {
    if (searchQuery.value.trim()) return filteredUsers.value;
    return users.value.slice(0, 4);
});

const selectUser = async (user) => {
    try {
        const response = await axios.get(
            `/medical-dashboard/application/${user.id}`
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
        console.log("Full user data:", response.data.user);
        console.log("Grades check:", response.data.user.grades);

        // ✅ Add this line to load programs into the dropdown
        await fetchPrograms();

        console.log(
            "User & files:",
            selectedUser.value,
            selectedUserFiles.value
        );
    } catch (error) {
        console.error("Failed to fetch user data:", error);
        selectedUserFiles.value = {};
        selectedUser.value = null;
    }
};

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
    if (selected.length === 0 || !returnNote.value.trim()) {
        alert("Please select files and enter a return note.");
        return;
    }

    try {
        await axios.post(`/medical/return-files/${selectedUser.value.id}`, {
            files: selected,
            note: returnNote.value.trim(),
        });

        alert("Files returned and application status logged.");

        isEvaluating.value = false;
        filesToReturn.value = {};
        returnNote.value = "";

        // ✅ Refetch updated user list & status counts
        await fetchUsers();
        await selectUser(selectedUser.value);
    } catch (error) {
        console.error(error);
        alert("Return failed.");
    }
};

const capitalize = (str) =>
    typeof str === "string" ? str.charAt(0).toUpperCase() + str.slice(1) : "";

const formatDate = (date) => {
    const d = new Date(date);
    return d.toLocaleString(); // or .toLocaleDateString() if you prefer
};

const acceptApplication = async () => {
    try {
        await axios.post(`/medical-dashboard/accept/${selectedUser.value.id}`);
        showSnackbar("Application cleared.");
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
watch([searchQuery, statusFilter, sortKey, sortAsc], () => {
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
</style>
