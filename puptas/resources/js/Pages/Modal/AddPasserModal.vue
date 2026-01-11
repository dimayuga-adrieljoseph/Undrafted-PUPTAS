<template>
    <div
        ref="scrollWrapper"
        class="scroll-wrapper"
        tabindex="0"
        @scroll="handleScroll"
    >
        <AppLayout>
            <h1 class="mb-6 text-2xl font-semibold">
                Send Emails to PUPCET Passers
            </h1>

            <form v-if="flatPassers.length" @submit.prevent="sendEmails">
                <!-- Controls -->
                <div class="mb-6 flex flex-wrap gap-4 items-center">
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
                            type="text"
                            placeholder="Search by surname, first name, or email"
                            v-model="searchTerm"
                            @input="onSearchInput"
                            class="bg-transparent border-none outline-none focus:ring-0 focus:outline-none text-sm text-[#9E122C] placeholder-gray-500 w-full"
                        />
                    </div>

                    <div
                        class="flex items-center border rounded px-3 py-1 gap-2"
                    >
                        <select
                            v-model="filterSchoolYear"
                            style="outline: none; box-shadow: none"
                            class="border-none outline-none bg-transparent"
                        >
                            <option value="">All School Years</option>
                            <option
                                v-for="year in schoolYears"
                                :key="year"
                                :value="year"
                            >
                                {{ year }}
                            </option>
                        </select>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 text-[#9E122C]"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            title="Filter by School Year"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2v-7H3v7a2 2 0 002 2z"
                            />
                        </svg>
                    </div>

                    <div
                        class="flex items-center border rounded px-3 py-1 gap-2"
                    >
                        <select
                            v-model="filterBatchNumber"
                            style="outline: none; box-shadow: none"
                            class="border-none outline-none bg-transparent"
                        >
                            <option value="">All Batches</option>
                            <option
                                v-for="batch in batchNumbers"
                                :key="batch"
                                :value="batch"
                            >
                                {{ batch }}
                            </option>
                        </select>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 text-[#9E122C]"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            title="Filter by Batch Number"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M7 7h10M7 11h6m-6 4h10M5 19h14a2 2 0 002-2v-7H3v7a2 2 0 002 2z"
                            />
                        </svg>
                    </div>

                    <div
                        class="flex items-center border rounded px-3 py-1 gap-2"
                    >
                        <select
                            v-model="sortKey"
                            style="outline: none; box-shadow: none"
                            class="border-none outline-none bg-transparent"
                        >
                            <option value="surname">Sort by Surname</option>
                            <option value="first_name">
                                Sort by First Name
                            </option>
                            <option value="email">Sort by Email</option>
                            <option value="schoolYear">
                                Sort by School Year
                            </option>
                            <option value="batchNumber">Sort by Batch</option>
                        </select>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 text-[#9E122C]"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            title="Sort by"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h8m-8 6h12"
                            />
                        </svg>
                    </div>

                    <div
                        class="flex items-center border rounded px-3 py-1 gap-2"
                    >
                        <select
                            v-model="sortOrder"
                            style="outline: none; box-shadow: none"
                            class="border-none outline-none bg-transparent"
                        >
                            <option value="asc">Ascending</option>
                            <option value="desc">Descending</option>
                        </select>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 text-[#9E122C]"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            title="Sort order"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M7 11l5-5 5 5M7 13l5 5 5-5"
                            />
                        </svg>
                    </div>
                </div>

                <div class="mb-4">
                    <button
                        @click.prevent="openAddModal"
                        class="fixed bottom-8 right-6 bg-[#9E122C] hover:bg-[#EE6A43] text-white rounded-full w-14 h-14 flex items-center justify-center shadow-lg transition-all group"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-8 w-8"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        <span
                            class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 whitespace-nowrap rounded bg-gray-700 px-2 py-1 text-xs text-white opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity"
                        >
                            Manually Add Passer
                        </span>
                    </button>
                </div>

                <!-- Passers table -->
                <div class="bg-white/20 rounded-xl shadow p-2 overflow-x-auto">
                    <!-- User count info -->
                    <div class="text-sm text-[#4B5563] mb-2">
                        Showing {{ paginatedPassers.length }} of
                        {{ filteredPassers.length }} passers
                    </div>

                    <!-- Table -->
                    <table class="min-w-full text-base">
                        <thead>
                            <tr class="text-left text-gray-900 font-semibold">
                                <th class="pb-2 text-center">
                                    <input
                                        type="checkbox"
                                        :checked="areAllSelected"
                                        @change="
                                            toggleSelectAll(
                                                $event.target.checked
                                            )
                                        "
                                        class="accent-[#9E122C]"
                                    />
                                </th>
                                <th class="pb-2">Surname</th>
                                <th class="pb-2">First Name</th>
                                <th class="pb-2">Email</th>
                                <th class="pb-2 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-600">
                            <tr
                                v-for="passer in paginatedPassers"
                                :key="passer.test_passer_id"
                                class="hover:bg-white/10 backdrop-blur-sm transition cursor-pointer"
                            >
                                <td class="py-2 text-center">
                                    <input
                                        type="checkbox"
                                        :value="passer.test_passer_id"
                                        v-model="selectedPassers"
                                        class="accent-[#9E122C]"
                                    />
                                </td>
                                <td class="py-2 text-gray-900 font-medium">
                                    {{ passer.surname }}
                                </td>
                                <td class="py-2 text-gray-900">
                                    {{ passer.first_name }}
                                </td>
                                <td class="py-2 text-gray-900">
                                    {{ passer.email }}
                                </td>
                                <td class="py-2 text-center">
                                    <button
                                        @click.prevent="openEditModal(passer)"
                                        class="text-white hover:text-orange-800 relative group"
                                        aria-label="Edit"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-6 w-6"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <path d="M12 20h9" />
                                            <path
                                                d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"
                                            />
                                        </svg>
                                        <span
                                            class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 whitespace-nowrap rounded bg-gray-700 px-2 py-1 text-xs text-white opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity"
                                        >
                                            Edit
                                        </span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-4 flex justify-center items-center gap-2">
                    <button
                        :disabled="currentPage === 1"
                        @click.prevent="currentPage--"
                        class="px-3 py-1 border rounded disabled:opacity-50"
                    >
                        Prev
                    </button>
                    <span>Page {{ currentPage }} / {{ totalPages }}</span>
                    <button
                        :disabled="currentPage === totalPages"
                        @click.prevent="currentPage++"
                        class="px-3 py-1 border rounded disabled:opacity-50"
                    >
                        Next
                    </button>
                </div>

                <div class="flex items-center mt-6 gap-2">
                    <label class="block">Choose Template Type:</label>
                    <div
                        class="flex items-center border rounded px-3 py-1 w-64 gap-2"
                    >
                        <select
                            v-model="templateType"
                            class="border-none outline-none bg-transparent w-full"
                        >
                            <option value="default">Default Template</option>
                            <option value="custom">Custom Template</option>
                        </select>
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 text-[#9E122C]"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            title="Select Template Type"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h8m-8 6h12"
                            />
                        </svg>
                    </div>
                </div>

                <div v-if="templateType === 'custom'" class="mt-4">
                    <label>Email Template (Rich Text):</label>
                    <QuillEditor
                        v-model="emailTemplate"
                        style="min-height: 250px"
                        theme="snow"
                        toolbar="full"
                    />
                </div>

                <div v-else class="mt-4 p-4 border rounded bg-gray-50">
                    <div v-html="defaultTemplatePreview"></div>
                </div>

                <button
                    type="submit"
                    class="bg-red-800 text-white px-6 py-2 rounded hover:bg-red-900 mt-4"
                    :disabled="!selectedPassers.length || !emailTemplate"
                >
                    Send Emails
                </button>
            </form>

            <!-- Edit Modal -->
            <div
                v-if="showEditModal"
                class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center p-4 overflow-auto"
            >
                <div
                    class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] p-6 relative shadow-lg overflow-y-auto"
                >
                    <h2 class="text-xl font-semibold mb-6">
                        Edit Passer Details
                    </h2>

                    <form
                        @submit.prevent="savePasser"
                        class="grid grid-cols-2 gap-x-6 gap-y-4"
                    >
                        <div>
                            <label class="block font-medium mb-1"
                                >Surname</label
                            >
                            <input
                                v-model="editingPasser.surname"
                                class="border rounded px-3 py-1 w-full"
                                required
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >First Name</label
                            >
                            <input
                                v-model="editingPasser.first_name"
                                class="border rounded px-3 py-1 w-full"
                                required
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >Middle Name</label
                            >
                            <input
                                v-model="editingPasser.middle_name"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >Date of Birth</label
                            >
                            <input
                                type="date"
                                v-model="editingPasser.date_of_birth"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >Address</label
                            >
                            <input
                                v-model="editingPasser.address"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >School Address</label
                            >
                            <input
                                v-model="editingPasser.school_address"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >SHS School</label
                            >
                            <input
                                v-model="editingPasser.shs_school"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Strand</label>
                            <input
                                v-model="editingPasser.strand"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >Year Graduated</label
                            >
                            <input
                                type="number"
                                v-model="editingPasser.year_graduated"
                                min="1900"
                                max="2099"
                                step="1"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Email</label>
                            <input
                                v-model="editingPasser.email"
                                type="email"
                                class="border rounded px-3 py-1 w-full"
                                required
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >Reference Number</label
                            >
                            <input
                                v-model="editingPasser.reference_number"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >Batch Number</label
                            >
                            <input
                                v-model="editingPasser.batch_number"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >School Year</label
                            >
                            <input
                                v-model="editingPasser.school_year"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <!-- Buttons full width under inputs -->
                        <div class="col-span-2 flex justify-end gap-3 mt-6">
                            <button
                                type="button"
                                @click="closeEditModal"
                                class="px-5 py-2 rounded border"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="bg-red-800 text-white px-5 py-2 rounded hover:bg-red-900"
                                :disabled="saving"
                            >
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Passer Modal -->
            <div
                v-if="showAddModal"
                class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center p-4 overflow-auto z-50"
            >
                <div
                    class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] p-6 relative shadow-lg overflow-y-auto"
                >
                    <h2 class="text-xl font-semibold mb-6">Add New Passer</h2>

                    <form
                        @submit.prevent="saveNewPasser"
                        class="grid grid-cols-2 gap-x-6 gap-y-4"
                    >
                        <div>
                            <label class="block font-medium mb-1"
                                >Surname</label
                            >
                            <input
                                v-model="newPasser.surname"
                                class="border rounded px-3 py-1 w-full"
                                required
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >First Name</label
                            >
                            <input
                                v-model="newPasser.first_name"
                                class="border rounded px-3 py-1 w-full"
                                required
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >Middle Name</label
                            >
                            <input
                                v-model="newPasser.middle_name"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >Date of Birth</label
                            >
                            <input
                                type="date"
                                v-model="newPasser.date_of_birth"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >Address</label
                            >
                            <input
                                v-model="newPasser.address"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >School Address</label
                            >
                            <input
                                v-model="newPasser.school_address"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >SHS School</label
                            >
                            <input
                                v-model="newPasser.shs_school"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Strand</label>
                            <input
                                v-model="newPasser.strand"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >Year Graduated</label
                            >
                            <input
                                type="number"
                                v-model="newPasser.year_graduated"
                                min="1900"
                                max="2099"
                                step="1"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1">Email</label>
                            <input
                                v-model="newPasser.email"
                                type="email"
                                class="border rounded px-3 py-1 w-full"
                                required
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >Reference Number</label
                            >
                            <input
                                v-model="newPasser.reference_number"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >Batch Number</label
                            >
                            <input
                                v-model="newPasser.batch_number"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <div>
                            <label class="block font-medium mb-1"
                                >School Year</label
                            >
                            <input
                                v-model="newPasser.school_year"
                                class="border rounded px-3 py-1 w-full"
                            />
                        </div>

                        <!-- Buttons -->
                        <div class="col-span-2 flex justify-end gap-3 mt-6">
                            <button
                                type="button"
                                @click="closeAddModal"
                                class="px-5 py-2 rounded border"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="bg-green-600 text-white px-5 py-2 rounded hover:bg-green-700"
                                :disabled="saving"
                            >
                                Add
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Snackbar UI -->
            <div
                v-if="snackbar.show"
                :class="[
                    'fixed z-51 bottom-4 left-1/2 transform -translate-x-1/2 px-4 py-2 rounded shadow text-white font-semibold',
                    snackbar.type === 'success' ? 'bg-green-600' : 'bg-red-600',
                ]"
            >
                {{ snackbar.message }}
            </div>
        </AppLayout>
    </div>

    <!-- Scroll Up Button, show only when not at top -->
    <button
        v-show="showScrollUp"
        @click="scrollUp"
        class="fixed right-4 top-24 bg-[#9E122C] text-white p-2 rounded-full shadow hover:bg-[#EE6A43]"
        aria-label="Scroll Up"
    >
        ▲
    </button>

    <!-- Scroll Down Button, show only when at top -->
    <button
        v-show="showScrollDown"
        @click="scrollDown"
        class="fixed right-4 bottom-24 bg-[#9E122C] text-white p-2 rounded-full shadow hover:bg-[#EE6A43]"
        aria-label="Scroll Down"
    >
        ▼
    </button>
</template>

<script setup>
import { ref, computed, watch } from "vue";
const axios = window.axios;
import AppLayout from "@/Layouts/AppLayout.vue";
import { QuillEditor } from "@vueup/vue-quill";
import "@vueup/vue-quill/dist/vue-quill.snow.css";
import { useGlobalLoading } from "@/Composables/useGlobalLoading";
import { useSnackbar } from "@/Composables/useSnackbar";

const scrollWrapper = ref(null);
const scrollAmount = 200;

// Reactive states to show/hide arrows
const showScrollUp = ref(false);
const showScrollDown = ref(true);

const scrollUp = () => {
    if (scrollWrapper.value) {
        scrollWrapper.value.scrollBy({
            top: -scrollAmount,
            behavior: "smooth",
        });
    }
};

const scrollDown = () => {
    if (scrollWrapper.value) {
        scrollWrapper.value.scrollBy({ top: scrollAmount, behavior: "smooth" });
    }
};

// Handle scroll event to update button visibility
const handleScroll = () => {
    if (!scrollWrapper.value) return;
    const scrollTop = scrollWrapper.value.scrollTop;
    const scrollHeight = scrollWrapper.value.scrollHeight;
    const clientHeight = scrollWrapper.value.clientHeight;

    // Show up button if scrolled down (scrollTop > 10px to avoid jitter)
    showScrollUp.value = scrollTop > 10;

    // Show down button if near top (within 10px of top)
    showScrollDown.value = scrollTop < 10;

    // Optionally, hide down button if content fits inside viewport (no scroll needed)
    if (scrollHeight <= clientHeight) {
        showScrollUp.value = false;
        showScrollDown.value = false;
    }
};

// Also call handleScroll on mounted to set initial button state
import { onMounted } from "vue";
onMounted(() => {
    handleScroll();
});

const { snackbar, show } = useSnackbar();

function debounce(fn, delay) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), delay);
    };
}

const { start, finish } = useGlobalLoading();

const props = defineProps({
    groupedPassers: Object,
    registrationUrl: String,
});

const emailTemplate = ref(
    `
  <div style="font-family: Arial, sans-serif; background-color: #f7f7f7; padding: 40px;">
    <div style="max-width: 600px; margin: auto; background-color: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);">
      <h1 style="color: #800000; text-align: center;">🎉 Congratulations, {{firstname}} {{surname}}!</h1>
      <p style="font-size: 16px; color: #333; text-align: center;">
        You have successfully passed the <strong>PUPCET</strong>! We are thrilled to welcome you as part of our growing community at Polytechnic University of the Philippines - Taguig Branch.
      </p>

      <div style="text-align: center; margin: 30px 0;">
        <a href="${props.registrationUrl}" 
           style="background-color: #800000; color: #FFD700; text-decoration: none; padding: 15px 25px; font-weight: bold; border-radius: 8px; display: inline-block;">
           Complete Your Registration
        </a>
      </div>

      <p style="font-size: 14px; color: #555; text-align: center;">
        If you have any questions or need help, please contact our admissions office.
      </p>
    </div>
  </div>
`.trim()
);

const templateType = ref("default");
const defaultTemplatePreview = `
  <div style="font-family: Arial, sans-serif; background-color: #f7f7f7; padding: 40px;">
    <div style="max-width: 600px; margin: auto; background-color: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);">
      <h1 style="color: #800000; text-align: center;">🎉 Congratulations, {{firstname}} {{surname}}!</h1>
      <p style="font-size: 16px; color: #333; text-align: center;">
        You have successfully passed the <strong>PUPCET</strong>! We are thrilled to welcome you as part of our growing community at Polytechnic University of the Philippines - Taguig Branch.
      </p>

      <div style="text-align: center; margin: 30px 0;">
        <a href="${props.registrationUrl}" 
           style="background-color: #800000; color: #FFD700; text-decoration: none; padding: 15px 25px; font-weight: bold; border-radius: 8px; display: inline-block;">
           Complete Your Registration
        </a>
      </div>

      <p style="font-size: 14px; color: #555; text-align: center;">
        If you have any questions or need help, please contact our admissions office.
      </p>
    </div>
  </div>
`.trim();

const flatPassers = ref([]);

watch(
    () => props.groupedPassers,
    (newVal) => {
        const result = [];
        if (newVal) {
            for (const schoolYear in newVal) {
                for (const batchNumber in newVal[schoolYear]) {
                    newVal[schoolYear][batchNumber].forEach((passer) => {
                        result.push({
                            ...passer,
                            schoolYear,
                            batchNumber,
                        });
                    });
                }
            }
        }
        flatPassers.value = result;
    },
    { immediate: true }
);

const searchTerm = ref("");
const debouncedSearchTerm = ref("");
const filterSchoolYear = ref("");
const filterBatchNumber = ref("");
const sortKey = ref("surname");
const sortOrder = ref("asc");
const currentPage = ref(1);
const itemsPerPage = 10;

const onSearchInput = debounce(() => {
    debouncedSearchTerm.value = searchTerm.value.toLowerCase();
    currentPage.value = 1;
}, 300);

watch([filterSchoolYear, filterBatchNumber, sortKey, sortOrder], () => {
    currentPage.value = 1;
});

const schoolYears = computed(() => {
    const years = new Set(flatPassers.value.map((p) => p.schoolYear));
    return Array.from(years).sort();
});

const batchNumbers = computed(() => {
    const batches = new Set(flatPassers.value.map((p) => p.batchNumber));
    return Array.from(batches).sort();
});

const filteredPassers = computed(() => {
    return flatPassers.value.filter((passer) => {
        const search = debouncedSearchTerm.value;
        const matchesSearch =
            passer.surname.toLowerCase().includes(search) ||
            passer.first_name.toLowerCase().includes(search) ||
            passer.email.toLowerCase().includes(search);

        const matchesSchoolYear = filterSchoolYear.value
            ? passer.schoolYear === filterSchoolYear.value
            : true;

        const matchesBatch = filterBatchNumber.value
            ? passer.batchNumber === filterBatchNumber.value
            : true;

        return matchesSearch && matchesSchoolYear && matchesBatch;
    });
});

const sortedPassers = computed(() => {
    return filteredPassers.value.slice().sort((a, b) => {
        const key = sortKey.value;
        let valA = a[key]?.toString().toLowerCase() || "";
        let valB = b[key]?.toString().toLowerCase() || "";

        if (valA < valB) return sortOrder.value === "asc" ? -1 : 1;
        if (valA > valB) return sortOrder.value === "asc" ? 1 : -1;
        return 0;
    });
});

const totalPages = computed(() =>
    Math.ceil(sortedPassers.value.length / itemsPerPage)
);

const paginatedPassers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return sortedPassers.value.slice(start, start + itemsPerPage);
});

const selectedPassers = ref([]);

const areAllSelected = computed(() => {
    if (!paginatedPassers.value.length) return false;
    return paginatedPassers.value.every((p) =>
        selectedPassers.value.includes(p.test_passer_id)
    );
});

const toggleSelectAll = (isSelected) => {
    if (isSelected) {
        const idsToAdd = paginatedPassers.value
            .map((p) => p.test_passer_id)
            .filter((id) => !selectedPassers.value.includes(id));
        selectedPassers.value.push(...idsToAdd);
    } else {
        selectedPassers.value = selectedPassers.value.filter(
            (id) => !paginatedPassers.value.some((p) => p.test_passer_id === id)
        );
    }
};

const sendEmails = async () => {
    const quillContainer = document.querySelector(".ql-editor");
    const messageHtml = quillContainer
        ? quillContainer.innerHTML
        : emailTemplate.value;

    start();
    try {
        await axios.post("/test-passers/send-emails", {
            passer_ids: selectedPassers.value,
            message_template: messageHtml,
        });
        show("Emails sent successfully!", "success");
        selectedPassers.value = [];
    } catch (error) {
        show("Failed to send emails.", "error");
        console.error(error);
    } finally {
        finish();
    }
};

// Modal state
const showEditModal = ref(false);
const editingPasser = ref(null);
const saving = ref(false);

function openEditModal(passer) {
    editingPasser.value = { ...passer };
    showEditModal.value = true;
}

function closeEditModal() {
    showEditModal.value = false;
    editingPasser.value = null;
}

async function savePasser() {
    saving.value = true;
    start();
    try {
        await axios.put(
            `/test-passers/${editingPasser.value.test_passer_id}`,
            editingPasser.value
        );
        show("Passer updated successfully!", "success");

        const index = flatPassers.value.findIndex(
            (p) => p.test_passer_id === editingPasser.value.test_passer_id
        );
        if (index !== -1) {
            flatPassers.value[index] = { ...editingPasser.value };
        }

        closeEditModal();
    } catch (error) {
        show("Failed to update passer.", "error");
        console.error(error);
    } finally {
        finish();
        saving.value = false;
    }
}

const showAddModal = ref(false);
const newPasser = ref({
    surname: "",
    first_name: "",
    middle_name: "",
    date_of_birth: "",
    address: "",
    school_address: "",
    shs_school: "",
    strand: "",
    year_graduated: "",
    email: "",
    reference_number: "",
    batch_number: "",
    school_year: "",
});

function openAddModal() {
    newPasser.value = {
        surname: "",
        first_name: "",
        middle_name: "",
        date_of_birth: "",
        address: "",
        school_address: "",
        shs_school: "",
        strand: "",
        year_graduated: "",
        email: "",
        reference_number: "",
        batch_number: "",
        school_year: "",
    };
    showAddModal.value = true;
}

function closeAddModal() {
    showAddModal.value = false;
}

async function saveNewPasser() {
    saving.value = true;
    start();
    try {
        const response = await axios.post(
            "/test-passers-store",
            newPasser.value
        );
        show("Passer added successfully!", "success");

        flatPassers.value.unshift(response.data);

        closeAddModal();
    } catch (error) {
        show("Failed to add passer.", "error");
        console.error(error);
    } finally {
        finish();
        saving.value = false;
    }
}
</script>

<style>
.scroll-wrapper {
    height: 100vh; /* Full viewport height */
    overflow-y: auto;
    padding-right: 1px; /* Prevent native scrollbar overlap */
    box-sizing: content-box; /* Make padding outside scrollbar */
}

/* Hide native scrollbar for cleaner UI */
.scroll-wrapper::-webkit-scrollbar {
    width: 0;
    background: transparent;
}
.scroll-wrapper {
    -ms-overflow-style: none; /* IE and Edge */
    scrollbar-width: none; /* Firefox */
}
</style>