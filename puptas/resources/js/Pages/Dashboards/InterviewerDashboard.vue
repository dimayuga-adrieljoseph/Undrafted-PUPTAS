<template>
    <Head title="Interviewer Dashboard" />
    <InterviewerLayout>
        <!-- Summary Boxes -->
        <div
            class="flex flex-wrap justify-center gap-[6rem] px-4 py-4 max-w-full overflow-hidden"
        >
            <div
                class="w-[180px] h-[180px] bg-red-200 border-4 border-white rounded-2xl shadow-xl flex flex-col items-center justify-center text-center hover:scale-105 transition-transform duration-300"
            >
                <div class="bg-[#9E122C] p-4 rounded-full mb-3">
                    <svg
                        class="w-8 h-8 text-white"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M3 7h18M3 12h18M3 17h18"
                        />
                    </svg>
                </div>
                <p class="text-gray-700 text-base font-medium">
                    Total Applications
                </p>
                <p class="text-3xl font-bold text-gray-900">
                    {{ props.summary?.total ?? 0 }}
                </p>
            </div>

            <div
                class="w-[180px] h-[180px] bg-red-200 border-4 border-white rounded-2xl shadow-xl flex flex-col items-center justify-center text-center hover:scale-105 transition-transform duration-300"
            >
                <div class="bg-[#9E122C] p-4 rounded-full mb-3">
                    <svg
                        class="w-8 h-8 text-white"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>
                </div>
                <p class="text-gray-700 text-base font-medium">Accepted</p>
                <p class="text-3xl font-bold text-gray-900">
                    {{ props.summary?.accepted ?? 0 }}
                </p>
            </div>

            <div
                class="w-[180px] h-[180px] bg-red-200 border-4 border-white rounded-2xl shadow-xl flex flex-col items-center justify-center text-center hover:scale-105 transition-transform duration-300"
            >
                <div class="bg-[#9E122C] p-4 rounded-full mb-3">
                    <svg
                        class="w-8 h-8 text-white"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 8v4l3 3"
                        />
                    </svg>
                </div>
                <p class="text-gray-700 text-base font-medium">Pending</p>
                <p class="text-3xl font-bold text-gray-900">
                    {{ props.summary?.pending ?? 0 }}
                </p>
            </div>

            <div
                class="w-[180px] h-[180px] bg-red-200 border-4 border-white rounded-2xl shadow-xl flex flex-col items-center justify-center text-center hover:scale-105 transition-transform duration-300"
            >
                <div class="bg-[#9E122C] p-4 rounded-full mb-3">
                    <svg
                        class="w-8 h-8 text-white"
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
                </div>
                <p class="text-gray-700 text-base font-medium">Returned</p>
                <p class="text-3xl font-bold text-gray-900">
                    {{ props.summary?.returned ?? 0 }}
                </p>
            </div>
        </div>

        <!-- Main Content -->
        <div class="py-8 px-4 md:px-6 lg:px-8 max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row gap-6">
                <!-- Chart Section -->
                <div
                    class="flex-1 bg-white dark:bg-gray-800 p-6 rounded-xl shadow border-2 border-red-400"
                >
                    <h3
                        class="text-lg font-semibold text-gray-900 dark:text-white mb-4"
                    >
                        Applications Overview
                    </h3>
                    <LineChart :chart-data="chartData" class="h-60 w-full" />
                </div>

                <!-- Users Table -->
                <div
                    class="flex-1 bg-white dark:bg-gray-800 p-6 rounded-xl shadow border-2 border-red-400"
                >
                    <div class="flex items-center justify-between mb-4">
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            Recent Applications
                        </h3>
                        <Link
                            href="/interviewer-applications"
                            class="text-sm text-[#9E122C] hover:underline hover:text-[#b51834] transition"
                        >
                            See all
                        </Link>
                    </div>

                    <table class="min-w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left text-gray-500 pb-2">
                                    Last Name
                                </th>
                                <th class="text-left text-gray-500 pb-2">
                                    First Name
                                </th>
                                <th class="text-left text-gray-500 pb-2">
                                    Course
                                </th>
                                <th class="text-left text-gray-500 pb-2">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr
                                v-for="user in displayedUsers"
                                :key="user.id"
                                @click="selectUser(user)"
                                class="cursor-pointer hover:bg-[#FBCB77]"
                            >
                                <td
                                    class="py-2 text-gray-800 dark:text-gray-100"
                                >
                                    {{ user.lastname }}
                                </td>
                                <td
                                    class="py-2 text-gray-800 dark:text-gray-100"
                                >
                                    {{ user.firstname }}
                                </td>
                                <td
                                    class="py-2 text-gray-600 dark:text-gray-300"
                                >
                                    {{ user.program.code || "—" }}
                                </td>
                                <td class="py-2">
                                    <span
                                        :class="getStatusClass(user.status)"
                                        class="px-2 py-1 rounded text-xs font-semibold"
                                    >
                                        {{ user.status || "Unknown" }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
                            ({{ selectedUser?.application?.program?.slots }}
                            slots left)
                        </p>
                    </div>

                    <div class="mt-4">
                        <label class="block mb-1 text-sm font-semibold"
                            >Transfer to Program</label
                        >
                        <select
                            v-model="selectedProgramId"
                            class="w-full border rounded p-2"
                        >
                            <option disabled value="">
                                -- Select Program --
                            </option>
                            <option
                                v-for="p in availablePrograms"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.code }} - {{ p.name }} ({{ p.slots }}
                                slots left)
                            </option>
                        </select>
                        <!-- Accept and Transfer buttons -->
                        <div class="mt-4 flex justify-end space-x-2">
                            <button
                                @click="acceptApplication"
                                class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                            >
                                Accept
                            </button>
                            <button
                                @click="transferApplication"
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                            >
                                Transfer
                            </button>
                        </div>
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
                            <ul
                                class="border-l-2 border-red-400 pl-3 space-y-2"
                            >
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
                                                    process.status ===
                                                    'completed',
                                                'text-yellow-600':
                                                    process.status ===
                                                    'in_progress',
                                                'text-red-600':
                                                    process.status ===
                                                    'returned',
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
        </div>
    </InterviewerLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { LineChart } from "vue-chart-3";
import { Head, Link } from "@inertiajs/vue3";
import InterviewerLayout from "@/Layouts/InterviewerLayout.vue";
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

const page = usePage();
const users = ref(page.props.users || []);

const props = defineProps({
    user: Object,
    allUsers: Array,
    summary: {
        type: Object,
        default: () => ({
            total: 0,
            accepted: 0,
            pending: 0,
            returned: 0,
        }),
    },
});

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

// const users = ref([]);
// const isLoading = ref(true);
// const errorMessage = ref("");

const fetchUsers = async () => {
    try {
        const response = await fetch("/interviewer-dashboard/applicants", {
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });
        if (!response.ok) throw new Error("Failed to fetch users");
        users.value = await response.json(); // same as Evaluator
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isLoading.value = false;
    }
};

onMounted(fetchUsers);

onMounted(() => {
    fetchUsers();
    fetchPrograms();
});

const filteredUsers = computed(() => {
    if (!searchQuery.value.trim()) return users.value;
    return users.value.filter((user) => {
        if (!user.name) return false;
        return user.name
            .toLowerCase()
            .includes(searchQuery.value.toLowerCase());
    });
});

const displayedUsers = computed(() => {
    if (searchQuery.value.trim()) return filteredUsers.value;
    return users.value.slice(0, 4);
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
        file10Front: "Grade 10 Front",
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
        await axios.post(`/dashboard/return-files/${selectedUser.value.id}`, {
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

/* Smaller placeholder styling */
.placeholder {
    background-color: #f3f4f6;
    min-height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #d1d5db;
    color: #9ca3af;
    font-style: italic;
    font-size: 0.75rem;
    border-radius: 0.375rem;
    text-align: center;
    padding: 0 4px;
}

.image-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.image-wrapper img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 0.375rem;
    border: 1px solid #d1d5db;
    background-color: #fff;
    cursor: pointer;
}

@media (max-width: 768px) {
    .image-wrapper img {
        width: 80px;
        height: 80px;
    }
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.5s;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
