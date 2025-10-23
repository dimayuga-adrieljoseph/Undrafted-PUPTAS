<template>
    <Head title="Dashboard" />
    <AppLayout>
        <!-- Professional Summary Boxes Section -->
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

        <div class="py-8 px-4 md:px-6 lg:px-8 max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row gap-6">
                <!-- Applications Overview Chart -->
                <div
                    class="flex-1 bg-white dark:bg-gray-800 p-6 rounded-xl shadow border-2 border-red-400"
                >
                    <h3
                        class="text-lg font-semibold text-gray-900 dark:text-white mb-4"
                    >
                        Applications Overview
                    </h3>
                    <LineChart
                        :chart-data="{
                            labels: props.chartData.years,
                            datasets: [
                                {
                                    label: 'Submitted',
                                    data: props.chartData.submitted,
                                    borderColor: '#2563EB',
                                    backgroundColor: 'rgba(37, 99, 235, 0.2)',
                                    tension: 0.4,
                                },
                                {
                                    label: 'Accepted',
                                    data: props.chartData.accepted,
                                    borderColor: '#10B981',
                                    backgroundColor: 'rgba(16, 185, 129, 0.2)',
                                    tension: 0.4,
                                },
                                {
                                    label: 'Returned',
                                    data: props.chartData.returned,
                                    borderColor: '#F59E0B',
                                    backgroundColor: 'rgba(245, 158, 11, 0.2)',
                                    tension: 0.4,
                                },
                            ],
                        }"
                        class="h-60 w-full"
                    />
                </div>

                <!-- Recent Applications Table -->
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
                            href="/applications"
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
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { LineChart } from "vue-chart-3";
import { Head, Link } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
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
    chartData: {
        type: Object,
        default: () => ({
            submitted: [],
            accepted: [],
            returned: [],
        }),
    },
    years: Array,
});

const users = ref([]);
const selectedUser = ref(null);
const isLoading = ref(true);
const errorMessage = ref("");
const searchQuery = ref("");
const selectedUserFiles = ref({});

const getStatusClass = (status) => {
    const s = (status || "").toLowerCase();
    if (s === "accepted") return "bg-green-100 text-green-700";
    if (s === "pending") return "bg-yellow-100 text-yellow-700";
    if (s === "returned") return "bg-red-100 text-red-700";
    return "bg-gray-100 text-gray-600";
};

const fetchUsers = async () => {
    try {
        const response = await fetch("/dashboard/users", {
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

const capitalize = (str) =>
    typeof str === "string" ? str.charAt(0).toUpperCase() + str.slice(1) : "";

const formatDate = (date) => {
    const d = new Date(date);
    return d.toLocaleString(); // or .toLocaleDateString() if you prefer
};

const closeUserCard = () => {
    selectedUser.value = null;
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
