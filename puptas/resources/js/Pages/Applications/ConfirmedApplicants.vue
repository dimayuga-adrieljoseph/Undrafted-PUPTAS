<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
const axios = window.axios;
import { Head } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import { QuillEditor } from "@vueup/vue-quill";
import "@vueup/vue-quill/dist/vue-quill.snow.css";

// ── State ──────────────────────────────────────────────────────────────────────
const applicants = ref([]);
const loading = ref(false);
const fetchError = ref(null);
const searchQuery = ref("");
const filterProgram = ref("");
const filterBatch = ref("");
const filterPasserStatus = ref([]);
const showStatusDropdown = ref(false);
const filterSarStatus = ref("");
const selectedIds = ref([]);

// Template / action mode: 'sar' | 'custom'
const templateMode = ref("sar");
const sarEnrollmentDate = ref(new Date().toISOString().split("T")[0]);
const sarEnrollmentTime = ref("09:00");
const emailTemplate = ref("");

// Send state
const sending = ref(false);
const sendResult = ref(null);

// Snackbar
const snackbar = ref({ show: false, message: "", type: "success" });
const showSnack = (msg, type = "success") => {
    snackbar.value = { show: true, message: msg, type };
    setTimeout(() => {
        snackbar.value.show = false;
    }, 4000);
};

// ── Fetch ──────────────────────────────────────────────────────────────────────
const fetchApplicants = async () => {
    loading.value = true;
    fetchError.value = null;
    try {
        const res = await axios.get("/confirmed-applicants/list");
        applicants.value = res.data;
    } catch (e) {
        fetchError.value = e.response?.data?.message || e.message;
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchApplicants();
    document.addEventListener('click', handleStatusClickOutside);
});
onUnmounted(() => {
    document.removeEventListener('click', handleStatusClickOutside);
});

// Close status dropdown on click outside
const handleStatusClickOutside = (e) => {
    if (showStatusDropdown.value && !e.target.closest('.relative.flex-1')) {
        showStatusDropdown.value = false;
    }
};

// ── Computed ───────────────────────────────────────────────────────────────────
const programs = computed(() => {
    const codes = new Set(
        applicants.value.map((a) => a.program?.code).filter(Boolean),
    );
    return Array.from(codes).sort();
});

const batches = computed(() => {
    const batchSet = new Set(
        applicants.value.map((a) => a.batch_number).filter(Boolean),
    );
    return Array.from(batchSet).sort();
});

const passerStatuses = computed(() => {
    const statusSet = new Set(
        applicants.value.map((a) => a.passer_status_name).filter(Boolean),
    );
    return Array.from(statusSet).sort();
});

const filtered = computed(() => {
    let list = applicants.value;
    const q = searchQuery.value.trim().toLowerCase();
    if (q)
        list = list.filter(
            (a) =>
                `${a.firstname} ${a.lastname}`.toLowerCase().includes(q) ||
                (a.email || "").toLowerCase().includes(q) ||
                (a.reference_number || "").toLowerCase().includes(q),
        );
    if (filterProgram.value)
        list = list.filter((a) => a.program?.code === filterProgram.value);
    if (filterBatch.value)
        list = list.filter((a) => a.batch_number === filterBatch.value);
    if (filterPasserStatus.value.length > 0)
        list = list.filter(
            (a) => filterPasserStatus.value.includes(a.passer_status_name),
        );
    if (filterSarStatus.value === "sent") list = list.filter((a) => a.sar_sent);
    if (filterSarStatus.value === "pending")
        list = list.filter((a) => !a.sar_sent);
    return list;
});

const allSelected = computed(
    () =>
        filtered.value.length > 0 &&
        filtered.value.every((a) => selectedIds.value.includes(a.id)),
);

const toggleAll = () => {
    if (allSelected.value) {
        const ids = new Set(filtered.value.map((a) => a.id));
        selectedIds.value = selectedIds.value.filter((id) => !ids.has(id));
    } else {
        const ids = filtered.value.map((a) => a.id);
        selectedIds.value = [...new Set([...selectedIds.value, ...ids])];
    }
};

const toggle = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) selectedIds.value.push(id);
    else selectedIds.value.splice(idx, 1);
};

const getStatusClass = (status) => {
    const s = (status || "").toLowerCase();
    if (s === "qualified")
        return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "waitlisted")
        return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300";
    return "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400";
};

// ── Pagination ─────────────────────────────────────────────────────────────────
const currentPage = ref(1);
const itemsPerPage = 10;

watch(
    [
        searchQuery,
        filterProgram,
        filterBatch,
        filterPasserStatus,
        filterSarStatus,
    ],
    () => {
        currentPage.value = 1;
    },
    { deep: true },
);

const totalPages = computed(
    () => Math.ceil(filtered.value.length / itemsPerPage) || 1,
);

const visiblePages = computed(() => {
    const pages = [];
    const maxVisible = 5;

    if (totalPages.value <= maxVisible) {
        for (let i = 1; i <= totalPages.value; i++) {
            pages.push(i);
        }
    } else {
        let start = Math.max(1, currentPage.value - 2);
        let end = Math.min(totalPages.value, start + maxVisible - 1);

        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }

        for (let i = start; i <= end; i++) {
            pages.push(i);
        }
    }
    return pages;
});

const paginatedApplicants = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filtered.value.slice(start, start + itemsPerPage);
});

function getGlobalRank(pageIndex) {
    const globalIndex = (currentPage.value - 1) * itemsPerPage + pageIndex;
    return globalIndex + 1;
}

const areAllSelectedOnCurrentPage = computed(() => {
    if (!paginatedApplicants.value.length) return false;
    return paginatedApplicants.value.every((a) =>
        selectedIds.value.includes(a.id),
    );
});

const toggleSelectAll = (checked) => {
    if (checked) {
        paginatedApplicants.value.forEach((a) => {
            if (!selectedIds.value.includes(a.id)) {
                selectedIds.value.push(a.id);
            }
        });
    } else {
        const idsToRemove = paginatedApplicants.value.map((a) => a.id);
        selectedIds.value = selectedIds.value.filter(
            (id) => !idsToRemove.includes(id),
        );
    }
};

// ── Send ───────────────────────────────────────────────────────────────────────
const send = async () => {
    if (!selectedIds.value.length) {
        showSnack("Select at least one applicant.", "error");
        return;
    }

    if (templateMode.value === "sar") {
        if (!sarEnrollmentDate.value || !sarEnrollmentTime.value) {
            showSnack("Set enrollment date and time.", "error");
            return;
        }
    } else {
        const quill = document.querySelector(".ql-editor");
        if (!quill?.innerHTML?.trim() && !emailTemplate.value) {
            showSnack("Email body is required.", "error");
            return;
        }
    }

    sending.value = true;
    sendResult.value = null;

    try {
        if (templateMode.value === "sar") {
            const res = await axios.post("/confirmed-applicants/send-sar", {
                applicant_ids: selectedIds.value,
                enrollment_date: sarEnrollmentDate.value,
                enrollment_time: sarEnrollmentTime.value,
            });
            sendResult.value = res.data;
            showSnack(
                res.data.message,
                res.data.failed_count ? "error" : "success",
            );
            if (!res.data.failed_count) {
                selectedIds.value = [];
                await loadSarHistory(1);
            }
            await fetchApplicants();
        } else {
            const quill = document.querySelector(".ql-editor");
            const html = quill ? quill.innerHTML : emailTemplate.value;
            const res = await axios.post("/confirmed-applicants/send-email", {
                applicant_ids: selectedIds.value,
                message_template: html,
            });
            sendResult.value = res.data;
            showSnack(res.data.message, "success");
            selectedIds.value = [];
        }
    } catch (e) {
        showSnack(e.response?.data?.message || "Failed to send.", "error");
    } finally {
        sending.value = false;
    }
};

// ── SAR Previews ───────────────────────────────────────────────────────────────

// SAR Email Template Preview
const showSarEmailPreview = ref(false);
const sarEmailPreviewHtml = ref("");
const loadingEmailPreview = ref(false);

const previewSarEmailTemplate = async () => {
    if (selectedIds.value.length === 0) {
        showSnack("Please select at least one applicant to preview", "error");
        return;
    }

    const selectedApplicant = filtered.value.find(
        (a) => a.id === selectedIds.value[0],
    );
    if (!selectedApplicant || !selectedApplicant.test_passer_id) {
        showSnack(
            "Selected applicant has no test passer record linked",
            "error",
        );
        return;
    }

    loadingEmailPreview.value = true;
    showSarEmailPreview.value = true;

    try {
        const response = await axios.post("/admin/sar/preview-email-template", {
            passer_id: selectedApplicant.test_passer_id,
        });
        sarEmailPreviewHtml.value = response.data;
    } catch (error) {
        console.error("Failed to preview email template:", error);
        showSnack("Failed to load email preview", "error");
        closeSarEmailPreview();
    } finally {
        loadingEmailPreview.value = false;
    }
};

const closeSarEmailPreview = () => {
    showSarEmailPreview.value = false;
    sarEmailPreviewHtml.value = "";
};

// SAR PDF Form Preview
const showSarPdfPreview = ref(false);
const sarPdfPreviewUrl = ref("");
const loadingPdfPreview = ref(false);

const previewSarPdfForm = async () => {
    if (selectedIds.value.length === 0) {
        showSnack("Please select at least one applicant to preview", "error");
        return;
    }

    if (!sarEnrollmentDate.value || !sarEnrollmentTime.value) {
        showSnack("Please set enrollment date and time", "error");
        return;
    }

    const selectedApplicant = filtered.value.find(
        (a) => a.id === selectedIds.value[0],
    );
    if (!selectedApplicant || !selectedApplicant.test_passer_id) {
        showSnack(
            "Selected applicant has no test passer record linked",
            "error",
        );
        return;
    }

    loadingPdfPreview.value = true;
    showSarPdfPreview.value = true;

    try {
        if (sarPdfPreviewUrl.value) {
            URL.revokeObjectURL(sarPdfPreviewUrl.value);
            sarPdfPreviewUrl.value = "";
        }

        const formData = new FormData();
        formData.append("passer_id", selectedApplicant.test_passer_id);
        formData.append("enrollment_date", sarEnrollmentDate.value);
        formData.append("enrollment_time", sarEnrollmentTime.value);

        const response = await axios.post(
            "/admin/sar/preview-pdf-template",
            formData,
            {
                responseType: "blob",
            },
        );

        const blob = new Blob([response.data], { type: "application/pdf" });
        sarPdfPreviewUrl.value = URL.createObjectURL(blob);
    } catch (error) {
        console.error("Failed to preview SAR PDF:", error);
        showSnack("Failed to generate SAR PDF preview", "error");
        closeSarPdfPreview();
    } finally {
        loadingPdfPreview.value = false;
    }
};

const closeSarPdfPreview = () => {
    if (sarPdfPreviewUrl.value) {
        URL.revokeObjectURL(sarPdfPreviewUrl.value);
    }
    showSarPdfPreview.value = false;
    sarPdfPreviewUrl.value = "";
};

const fmt = (v) =>
    v === null || v === undefined ? "—" : parseFloat(v).toFixed(2);

// ── SAR History ────────────────────────────────────────────────────────────────
const sarHistory = ref([]);
const loadingSarHistory = ref(false);
const showSarPreview = ref(false);
const sarPreviewUrl = ref("");

const sarCurrentPage = ref(1);
const sarTotalPages = ref(1);
const sarTotalResults = ref(0);
const sarItemsPerPage = 10;

const loadSarHistory = async (page = 1) => {
    if (typeof page !== "number") {
        page = 1;
    }
    loadingSarHistory.value = true;
    sarCurrentPage.value = page;
    try {
        const params = { 
            search: searchQuery.value,
            page: page,
            limit: sarItemsPerPage
        };
        const response = await axios.get("/admin/sar-generations", { params });
        sarHistory.value = response.data.data || [];
        sarTotalPages.value = response.data.last_page || 1;
        sarTotalResults.value = response.data.total || 0;
    } catch (error) {
        console.error("Failed to load SAR history:", error);
        if (error.response && error.response.status !== 404) {
            showSnack("Failed to load SAR history", "error");
        }
    } finally {
        loadingSarHistory.value = false;
    }
};

const sarVisiblePages = computed(() => {
    const pages = [];
    const maxVisible = 3;

    if (sarTotalPages.value <= maxVisible) {
        for (let i = 1; i <= sarTotalPages.value; i++) {
            pages.push(i);
        }
    } else {
        let start = Math.max(1, sarCurrentPage.value - 1);
        let end = Math.min(sarTotalPages.value, start + maxVisible - 1);

        if (end - start + 1 < maxVisible) {
            start = Math.max(1, end - maxVisible + 1);
        }

        for (let i = start; i <= end; i++) {
            pages.push(i);
        }
    }
    return pages;
});

const previewSar = (id) => {
    sarPreviewUrl.value = `/admin/sar/${id}/preview`;
    showSarPreview.value = true;
};

const closeSarPreview = () => {
    showSarPreview.value = false;
    sarPreviewUrl.value = "";
};

const downloadSar = (id) => {
    window.open(`/admin/sar/${id}/download`, "_blank");
};

const formatDate = (dateString) => {
    if (!dateString) return "";
    const date = new Date(dateString);
    return date.toLocaleString();
};

watch(searchQuery, () => {
    loadSarHistory(1);
});

onMounted(() => {
    loadSarHistory(1);
});
</script>

<template>
    <Head title="Confirmed Applicants" />
    <AppLayout>
        <!-- Page Header -->
        <div class="mb-5">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Confirmed Applicants
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                Applicants with
                <span class="font-semibold text-yellow-600 dark:text-yellow-400"
                    >For Evaluation</span
                >
                status. Send SAR Forms or custom emails.
            </p>
        </div>

        <!-- Two-column layout -->
        <div class="flex gap-6 items-start">
            <!-- ── LEFT: Applicant List ─────────────────────────────── -->
            <div class="flex-1 min-w-0">
                <!-- Filters & Controls card -->
                <div
                    class="bg-white rounded-2xl shadow-lg p-6 mb-6 dark:bg-gray-800"
                >
                    <div class="flex items-center justify-between mb-6">
                        <h2
                            class="text-xl font-semibold text-gray-900 dark:text-gray-200"
                        >
                            Filters &amp; Controls
                        </h2>
                        <span
                            class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-sm font-medium dark:bg-gray-700 dark:text-gray-300"
                        >
                            {{ filtered.length }} applicants
                        </span>
                    </div>

                    <!-- Search -->
                    <div class="relative mb-3">
                        <svg
                            class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"
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
                            placeholder="Search by name, email, or reference no…"
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent placeholder-gray-400"
                        />
                    </div>

                    <!-- Filter row -->
                    <div class="flex gap-3 flex-wrap">
                        <select
                            v-model="filterProgram"
                            class="flex-1 min-w-[140px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-900 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#9E122C]"
                        >
                            <option value="">All Programs</option>
                            <option v-for="p in programs" :key="p" :value="p">
                                {{ p }}
                            </option>
                        </select>
                        <select
                            v-model="filterBatch"
                            class="flex-1 min-w-[140px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-900 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#9E122C]"
                        >
                            <option value="">All Batches</option>
                            <option v-for="b in batches" :key="b" :value="b">
                                {{ b }}
                            </option>
                        </select>
                        <div class="relative flex-1 min-w-[140px]">
                            <button @click="showStatusDropdown = !showStatusDropdown" type="button"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-900 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#9E122C] text-left flex items-center justify-between">
                                <span>{{ filterPasserStatus.length === 0 ? 'All Statuses' : filterPasserStatus.length + ' selected' }}</span>
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div v-if="showStatusDropdown" class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl shadow-lg py-1">
                                <label class="flex items-center px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" :checked="filterPasserStatus.length === 0" @change="filterPasserStatus = []" class="mr-2 rounded border-gray-300 text-[#9E122C] focus:ring-[#9E122C]" />
                                    All Statuses
                                </label>
                                <label v-for="s in passerStatuses" :key="s" class="flex items-center px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" :value="s" v-model="filterPasserStatus" class="mr-2 rounded border-gray-300 text-[#9E122C] focus:ring-[#9E122C]" />
                                    {{ s }}
                                </label>
                            </div>
                        </div>
                        <select
                            v-model="filterSarStatus"
                            class="flex-1 min-w-[140px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-900 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#9E122C]"
                        >
                            <option value="">All SAR STATUS</option>
                            <option value="sent">SAR Sent</option>
                            <option value="pending">SAR Pending</option>
                        </select>
                        <button
                            @click="fetchApplicants"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                        >
                            ↻ Refresh
                        </button>
                    </div>

                    <!-- Select All + count -->
                    <div
                        class="flex items-center gap-3 mt-3 pt-3 border-t border-gray-100 dark:border-gray-700"
                    >
                        <label
                            class="flex items-center gap-2 cursor-pointer text-sm text-gray-600 dark:text-gray-400"
                        >
                            <input
                                type="checkbox"
                                :checked="allSelected"
                                @change="toggleAll"
                                class="h-4 w-4 rounded text-[#9E122C] border-gray-300 focus:ring-[#9E122C]"
                            />
                            Select All ({{ filtered.length }})
                        </label>
                        <span
                            v-if="selectedIds.length"
                            class="text-xs text-[#9E122C] font-medium"
                        >
                            {{ selectedIds.length }} selected
                        </span>
                    </div>
                </div>

                <!-- Loading / Error -->
                <div
                    v-if="loading"
                    class="py-12 text-center text-gray-500 dark:text-gray-400"
                >
                    Loading…
                </div>
                <div
                    v-else-if="fetchError"
                    class="py-8 text-center text-red-600 dark:text-red-400"
                >
                    {{ fetchError }}
                </div>

                <!-- Passers Table Card -->
                <div
                    v-else
                    class="bg-white rounded-2xl shadow-lg overflow-hidden dark:bg-gray-800"
                >
                    <div
                        class="px-6 py-4 border-b border-gray-200 dark:border-gray-700"
                    >
                        <div class="flex items-center justify-between">
                            <h2
                                class="text-xl font-semibold text-gray-900 dark:text-gray-200"
                            >
                                Selected Passers
                            </h2>
                            <div
                                class="text-sm text-gray-600 dark:text-gray-400"
                            >
                                Page {{ currentPage }} of {{ totalPages }} •
                                Showing {{ paginatedApplicants.length }} items
                            </div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table
                            class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-6 py-4 text-left">
                                        <input
                                            type="checkbox"
                                            :checked="
                                                areAllSelectedOnCurrentPage
                                            "
                                            @change="
                                                toggleSelectAll(
                                                    $event.target.checked,
                                                )
                                            "
                                            class="h-5 w-5 text-[#9E122C] border-gray-300 rounded focus:ring-[#9E122C] dark:text-white dark:border-gray-600"
                                        />
                                    </th>
                                    <th
                                        class="px-3 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400 w-16"
                                    >
                                        Rank
                                    </th>
                                    <th
                                        class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400"
                                    >
                                        Name
                                    </th>
                                    <th
                                        class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400"
                                    >
                                        Contact
                                    </th>
                                    <th
                                        class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400"
                                    >
                                        Program
                                    </th>
                                    <th
                                        class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400"
                                    >
                                        Passer Status
                                    </th>
                                    <th
                                        class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400"
                                    >
                                        SAR STATUS
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700"
                            >
                                <!-- Empty State Row -->
                                <tr
                                    v-if="filtered.length === 0"
                                    class="bg-gray-50 dark:bg-gray-900"
                                >
                                    <td
                                        colspan="6"
                                        class="px-6 py-12 text-center"
                                    >
                                        <div
                                            class="flex flex-col items-center justify-center space-y-3"
                                        >
                                            <svg
                                                class="h-12 w-12 text-gray-400 dark:text-gray-500"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="1"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                                />
                                            </svg>
                                            <div
                                                class="text-lg font-medium text-gray-900 dark:text-gray-200"
                                            >
                                                No applicants match your current
                                                filters
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Regular Rows -->
                                <tr
                                    v-for="(
                                        a, pageIndex
                                    ) in paginatedApplicants"
                                    :key="a.id"
                                    class="hover:bg-gray-50 transition dark:hover:bg-gray-900 cursor-pointer"
                                    @click="toggle(a.id)"
                                    v-else
                                >
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input
                                            type="checkbox"
                                            :checked="
                                                selectedIds.includes(a.id)
                                            "
                                            class="h-5 w-5 text-[#9E122C] border-gray-300 rounded focus:ring-[#9E122C] dark:text-white dark:border-gray-600 pointer-events-none"
                                        />
                                    </td>
                                    <td
                                        class="px-3 py-4 whitespace-nowrap text-center text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        {{ getGlobalRank(pageIndex) }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div>
                                            <div
                                                class="font-medium text-gray-900 dark:text-gray-200"
                                            >
                                                {{ a.lastname }},
                                                {{ a.firstname }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div
                                            class="text-gray-900 dark:text-gray-200 text-sm"
                                        >
                                            {{ a.email }}
                                        </div>
                                        <div
                                            v-if="a.reference_number"
                                            class="text-sm text-gray-500 dark:text-gray-300"
                                        >
                                            Ref: {{ a.reference_number }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-300"
                                        >
                                            {{ a.program?.code || "—" }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            :class="
                                                getStatusClass(
                                                    a.passer_status_name,
                                                )
                                            "
                                            class="px-2.5 py-1 rounded-full text-xs font-medium capitalize"
                                        >
                                            {{ a.passer_status_name }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-6 py-4 whitespace-nowrap text-sm font-medium"
                                    >
                                        <span
                                            v-if="a.sar_sent"
                                            class="px-3 py-1 bg-green-100 text-green-800 rounded-full dark:bg-green-900 dark:text-green-200 text-xs"
                                        >
                                            Sent
                                        </span>
                                        <span
                                            v-else
                                            class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full dark:bg-yellow-900 dark:text-yellow-200 text-xs"
                                        >
                                            Pending
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div
                        class="px-6 py-4 border-t border-gray-200 dark:border-gray-700"
                    >
                        <div class="flex items-center justify-between">
                            <div
                                class="text-sm text-gray-700 dark:text-gray-400"
                            >
                                <span v-if="filtered.length === 0">
                                    Showing 0 to 0 of 0 results
                                </span>
                                <span v-else>
                                    Showing
                                    {{
                                        Math.min(
                                            (currentPage - 1) * itemsPerPage +
                                                1,
                                            filtered.length,
                                        )
                                    }}
                                    to
                                    {{
                                        Math.min(
                                            currentPage * itemsPerPage,
                                            filtered.length,
                                        )
                                    }}
                                    of {{ filtered.length }} results
                                </span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button
                                    :disabled="currentPage === 1"
                                    @click.prevent="currentPage--"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                                >
                                    <svg
                                        class="h-5 w-5 mr-1"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M15 19l-7-7 7-7"
                                        />
                                    </svg>
                                    Previous
                                </button>
                                <div class="flex items-center space-x-1">
                                    <button
                                        v-for="page in visiblePages"
                                        :key="page"
                                        @click.prevent="currentPage = page"
                                        :class="[
                                            'px-3 py-1 rounded-lg text-sm font-medium transition',
                                            currentPage === page
                                                ? 'bg-[#9E122C] text-white'
                                                : 'text-gray-700 hover:bg-gray-100',
                                        ]"
                                    >
                                        {{ page }}
                                    </button>
                                    <span
                                        v-if="
                                            totalPages > 5 &&
                                            currentPage < totalPages - 2
                                        "
                                        class="px-2 text-gray-500 dark:text-gray-300"
                                    >
                                        ...
                                    </span>
                                </div>
                                <button
                                    :disabled="currentPage === totalPages"
                                    @click.prevent="currentPage++"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                                >
                                    Next
                                    <svg
                                        class="h-5 w-5 ml-1"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 5l7 7-7 7"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── RIGHT: Action Panel ──────────────────────────────── -->
            <div class="w-[550px] flex-shrink-0 sticky top-6 space-y-6">
                <div
                    class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5"
                >
                    <h2
                        class="text-sm font-semibold text-gray-900 dark:text-gray-200 mb-4"
                    >
                        Email Template
                    </h2>

                    <!-- Template Type Selector -->
                    <p
                        class="text-xs font-medium text-gray-700 dark:text-gray-400 mb-2"
                    >
                        Select Template Type
                    </p>
                    <div class="grid grid-cols-2 gap-2 mb-5">
                        <button
                            @click="templateMode = 'sar'"
                            :class="[
                                'py-2.5 rounded-xl text-sm font-medium transition-all border',
                                templateMode === 'sar'
                                    ? 'bg-[#9E122C] text-white border-[#9E122C] shadow'
                                    : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-[#9E122C]',
                            ]"
                        >
                            SAR Form
                        </button>
                        <button
                            @click="templateMode = 'custom'"
                            :class="[
                                'py-2.5 rounded-xl text-sm font-medium transition-all border',
                                templateMode === 'custom'
                                    ? 'bg-[#9E122C] text-white border-[#9E122C] shadow'
                                    : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-[#9E122C]',
                            ]"
                        >
                            Custom Email
                        </button>
                    </div>

                    <!-- ── SAR Form options ── -->
                    <div
                        v-if="templateMode === 'sar'"
                        class="mt-2 p-4 rounded-xl bg-blue-50 border border-blue-200 dark:bg-blue-900/20 dark:border-blue-700"
                    >
                        <div class="flex items-start gap-3 mb-4">
                            <div
                                class="p-2 bg-blue-100 rounded-lg dark:bg-blue-800"
                            >
                                <svg
                                    class="h-6 w-6 text-blue-600 dark:text-blue-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <h4
                                    class="font-semibold text-blue-900 mb-1 text-sm dark:text-blue-200"
                                >
                                    SAR Form Settings
                                </h4>
                                <p
                                    class="text-xs text-blue-800 dark:text-blue-300"
                                >
                                    Personalized PDF will be generated for each
                                    selected passer
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-700 mb-1 dark:text-gray-400"
                                >
                                    Enrollment Date
                                </label>
                                <input
                                    type="date"
                                    v-model="sarEnrollmentDate"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm dark:bg-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C]"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-medium text-gray-700 mb-1 dark:text-gray-400"
                                >
                                    Enrollment Time
                                </label>
                                <input
                                    type="time"
                                    v-model="sarEnrollmentTime"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm dark:bg-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C]"
                                />
                            </div>
                        </div>

                        <!-- Preview Email Template Button -->
                        <div class="mt-4 space-y-2">
                            <button
                                @click="previewSarEmailTemplate"
                                :disabled="selectedIds.length === 0"
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm rounded-xl hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition dark:text-gray-900"
                            >
                                <svg
                                    class="h-4 w-4 mr-2"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                                    />
                                </svg>
                                Preview Email Template
                            </button>

                            <button
                                @click="previewSarPdfForm"
                                :disabled="
                                    selectedIds.length === 0 ||
                                    !sarEnrollmentDate ||
                                    !sarEnrollmentTime
                                "
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-green-600 text-white text-sm rounded-xl hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition dark:text-gray-900"
                            >
                                <svg
                                    class="h-4 w-4 mr-2"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                    />
                                </svg>
                                Preview SAR PDF Form
                            </button>

                            <p
                                class="text-xs text-gray-500 text-center dark:text-gray-400"
                            >
                                Preview the actual SAR form and email before
                                sending
                            </p>
                        </div>
                    </div>

                    <!-- ── Custom Email ── -->
                    <div v-else class="space-y-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Use
                            <code
                                class="bg-gray-100 dark:bg-gray-700 px-1 rounded"
                                >&#123;&#123;firstname&#125;&#125;</code
                            >,
                            <code
                                class="bg-gray-100 dark:bg-gray-700 px-1 rounded"
                                >&#123;&#123;surname&#125;&#125;</code
                            >,
                            <code
                                class="bg-gray-100 dark:bg-gray-700 px-1 rounded"
                                >&#123;&#123;reference_no&#125;&#125;</code
                            >
                            as placeholders.
                        </p>
                        <div
                            class="border border-gray-300 dark:border-gray-600 rounded-xl overflow-hidden text-sm"
                        >
                            <QuillEditor
                                v-model="emailTemplate"
                                style="min-height: 300px"
                                theme="snow"
                                toolbar="full"
                                placeholder="Write your email…"
                            />
                        </div>
                    </div>

                    <!-- Selection info -->
                    <div
                        class="mt-4 text-xs text-gray-500 dark:text-gray-400 text-center"
                    >
                        <span
                            class="font-semibold text-gray-800 dark:text-white"
                            >{{ selectedIds.length }}</span
                        >
                        applicant(s) selected
                    </div>

                    <!-- Send button -->
                    <button
                        @click="send"
                        :disabled="sending || !selectedIds.length"
                        :class="[
                            'w-full mt-3 py-3 rounded-xl font-semibold text-sm text-white transition-all flex items-center justify-center gap-2',
                            selectedIds.length && !sending
                                ? 'bg-[#9E122C] hover:bg-[#800918] shadow-lg'
                                : 'bg-gray-300 dark:bg-gray-700 cursor-not-allowed',
                        ]"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                            />
                        </svg>
                        {{
                            sending
                                ? "Sending…"
                                : templateMode === "sar"
                                  ? `Send SAR to ${selectedIds.length}`
                                  : `Send Email to ${selectedIds.length}`
                        }}
                    </button>

                    <!-- Result -->
                    <div
                        v-if="sendResult"
                        class="mt-4 p-3 rounded-xl border text-xs"
                        :class="
                            sendResult.failed_count
                                ? 'border-yellow-300 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-300'
                                : 'border-green-300 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-300'
                        "
                    >
                        <p class="font-semibold mb-1">
                            {{ sendResult.message }}
                        </p>
                        <ul
                            v-if="sendResult.errors?.length"
                            class="space-y-0.5 text-red-600 dark:text-red-400"
                        >
                            <li
                                v-for="err in sendResult.errors"
                                :key="err.email || err.applicant"
                            >
                                • {{ err.applicant }}: {{ err.error }}
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- SAR History Card -->
                <div
                    class="bg-white rounded-2xl shadow-lg p-6 dark:bg-gray-800"
                >
                    <div class="flex items-center justify-between mb-4">
                        <h2
                            class="text-xl font-semibold text-gray-900 dark:text-gray-200"
                        >
                            SAR History
                        </h2>
                        <button
                            @click="loadSarHistory(sarCurrentPage)"
                            class="text-gray-500 hover:text-[#9E122C] transition dark:text-gray-400 dark:hover:text-red-400"
                            title="Refresh History"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                        </button>
                    </div>

                    <div v-if="loadingSarHistory" class="text-center py-8">
                        <div
                            class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[#9E122C]"
                        ></div>
                        <p class="text-gray-600 mt-2 dark:text-gray-400">
                            Loading SAR history...
                        </p>
                    </div>

                    <div
                        v-else-if="sarHistory.length === 0"
                        class="text-center py-8 text-gray-500 dark:text-gray-300"
                    >
                        No SAR forms have been generated yet.
                    </div>

                    <div v-else class="space-y-3 max-h-96 overflow-y-auto">
                        <div
                            v-for="sar in sarHistory"
                            :key="sar.id"
                            class="p-3 border border-gray-200 rounded-xl hover:bg-gray-50 transition dark:border-gray-700 dark:hover:bg-gray-900"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div
                                        class="font-medium text-gray-900 text-sm dark:text-gray-200"
                                    >
                                        {{ sar.test_passer?.surname }},
                                        {{ sar.test_passer?.first_name }}
                                    </div>
                                    <div
                                        class="text-xs text-gray-600 mt-1 dark:text-gray-400"
                                    >
                                        Ref:
                                        {{ sar.test_passer?.reference_number }}
                                    </div>
                                    <div
                                        class="text-xs text-gray-500 mt-1 dark:text-gray-300"
                                    >
                                        {{ formatDate(sar.sent_at) }}
                                    </div>
                                </div>
                                <div class="flex gap-1 ml-2">
                                    <button
                                        @click="previewSar(sar.id)"
                                        class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition dark:text-blue-400 dark:hover:bg-blue-900"
                                        title="Preview"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                            />
                                        </svg>
                                    </button>
                                    <button
                                        @click="downloadSar(sar.id)"
                                        class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg transition dark:text-green-400 dark:hover:bg-green-900"
                                        title="Download"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SAR History Pagination -->
                    <div
                        v-if="sarTotalResults > 0"
                        class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700"
                    >
                        <div class="flex flex-col space-y-2 items-center justify-between text-xs text-gray-700 dark:text-gray-400">
                            <div>
                                Showing
                                <span class="font-semibold">{{ Math.min((sarCurrentPage - 1) * sarItemsPerPage + 1, sarTotalResults) }}</span>
                                to
                                <span class="font-semibold">{{ Math.min(sarCurrentPage * sarItemsPerPage, sarTotalResults) }}</span>
                                of
                                <span class="font-semibold">{{ sarTotalResults }}</span>
                                results
                            </div>
                            <div class="flex items-center space-x-1.5 mt-1">
                                <button
                                    :disabled="sarCurrentPage === 1"
                                    @click.prevent="loadSarHistory(sarCurrentPage - 1)"
                                    class="inline-flex items-center px-2 py-1 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                                >
                                    <svg class="h-3 w-3 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                    </svg>
                                    Prev
                                </button>
                                <button
                                    v-for="page in sarVisiblePages"
                                    :key="page"
                                    @click.prevent="loadSarHistory(page)"
                                    :class="[
                                        'px-2 py-1 rounded-lg font-medium transition',
                                        sarCurrentPage === page
                                            ? 'bg-[#9E122C] text-white'
                                            : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
                                    ]"
                                >
                                    {{ page }}
                                </button>
                                <button
                                    :disabled="sarCurrentPage === sarTotalPages"
                                    @click.prevent="loadSarHistory(sarCurrentPage + 1)"
                                    class="inline-flex items-center px-2 py-1 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                                >
                                    Next
                                    <svg class="h-3 w-3 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Snackbar -->
        <div
            v-if="snackbar.show"
            :class="[
                'fixed top-4 right-4 z-50 px-5 py-3 rounded-xl shadow-lg text-white font-medium text-sm',
                snackbar.type === 'success' ? 'bg-green-600' : 'bg-red-600',
            ]"
        >
            {{ snackbar.message }}
        </div>
        <!-- SAR PDF Form Preview Modal -->
        <div
            v-if="showSarPdfPreview"
            class="fixed inset-0 bg-black bg-opacity-75 flex justify-center items-center p-4 z-50 dark:bg-white"
            @click.self="closeSarPdfPreview"
        >
            <div
                class="bg-white rounded-2xl max-w-6xl w-full h-[90vh] flex flex-col shadow-2xl dark:bg-gray-800"
            >
                <div
                    class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700"
                >
                    <h2
                        class="text-xl font-bold text-gray-900 dark:text-gray-200"
                    >
                        SAR PDF Form Preview
                    </h2>
                    <button
                        @click="closeSarPdfPreview"
                        class="p-2 hover:bg-gray-100 rounded-lg transition dark:hover:bg-gray-800"
                    >
                        <svg
                            class="h-6 w-6 text-gray-500 dark:text-gray-300"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
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
                <div class="flex-1 overflow-hidden">
                    <div
                        v-if="loadingPdfPreview"
                        class="flex items-center justify-center h-full"
                    >
                        <div class="text-center">
                            <div
                                class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-[#9E122C]"
                            ></div>
                            <p class="text-gray-600 mt-4 dark:text-gray-400">
                                Generating SAR PDF preview...
                            </p>
                        </div>
                    </div>
                    <iframe
                        v-else-if="sarPdfPreviewUrl"
                        :src="sarPdfPreviewUrl"
                        class="w-full h-full border-0"
                    ></iframe>
                </div>
            </div>
        </div>

        <!-- SAR Email Template Preview Modal -->
        <div
            v-if="showSarEmailPreview"
            class="fixed inset-0 bg-black bg-opacity-75 flex justify-center items-center p-4 z-50 dark:bg-white"
            @click.self="closeSarEmailPreview"
        >
            <div
                class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl dark:bg-gray-800"
            >
                <div
                    class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700"
                >
                    <h2
                        class="text-xl font-bold text-gray-900 dark:text-gray-200"
                    >
                        SAR Email Template Preview
                    </h2>
                    <button
                        @click="closeSarEmailPreview"
                        class="p-2 hover:bg-gray-100 rounded-lg transition dark:hover:bg-gray-800"
                    >
                        <svg
                            class="h-6 w-6 text-gray-500 dark:text-gray-300"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
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
                <div
                    class="flex-1 overflow-auto p-6 bg-gray-50 dark:bg-gray-900"
                >
                    <div
                        v-if="loadingEmailPreview"
                        class="flex items-center justify-center h-full"
                    >
                        <div class="text-center">
                            <div
                                class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-[#9E122C]"
                            ></div>
                            <p class="text-gray-600 mt-4 dark:text-gray-400">
                                Loading preview...
                            </p>
                        </div>
                    </div>
                    <iframe
                        v-else-if="sarEmailPreviewHtml"
                        :srcdoc="sarEmailPreviewHtml"
                        class="w-full h-full border-0 bg-white rounded-lg shadow-sm dark:bg-gray-800"
                        style="min-height: 600px"
                    ></iframe>
                </div>
            </div>
        </div>
        <!-- SAR Preview Modal -->
        <div
            v-if="showSarPreview"
            class="fixed inset-0 bg-black bg-opacity-75 flex justify-center items-center p-4 z-50 dark:bg-white"
            @click.self="closeSarPreview"
        >
            <div
                class="bg-white rounded-2xl max-w-6xl w-full h-[90vh] flex flex-col shadow-2xl dark:bg-gray-800"
            >
                <div
                    class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700"
                >
                    <h2
                        class="text-xl font-bold text-gray-900 dark:text-gray-200"
                    >
                        SAR Form Preview
                    </h2>
                    <button
                        @click="closeSarPreview"
                        class="p-2 hover:bg-gray-100 rounded-lg transition dark:hover:bg-gray-800"
                    >
                        <svg
                            class="h-6 w-6 text-gray-500 dark:text-gray-300"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
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
                <div class="flex-1 overflow-hidden">
                    <iframe
                        v-if="sarPreviewUrl"
                        :src="sarPreviewUrl"
                        class="w-full h-full border-0"
                    ></iframe>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
