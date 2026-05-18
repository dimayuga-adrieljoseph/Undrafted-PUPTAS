<script setup>
import { ref, computed, onMounted } from 'vue';
const axios = window.axios;
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';

// ── State ──────────────────────────────────────────────────────────────────────
const applicants      = ref([]);
const loading         = ref(false);
const fetchError      = ref(null);
const searchQuery     = ref('');
const filterProgram   = ref('');
const filterSarStatus = ref('');
const selectedIds     = ref([]);

// Template / action mode: 'sar' | 'custom'
const templateMode      = ref('sar');
const sarEnrollmentDate = ref(new Date().toISOString().split('T')[0]);
const sarEnrollmentTime = ref('09:00');
const emailTemplate     = ref('');

// Send state
const sending    = ref(false);
const sendResult = ref(null);


// Snackbar
const snackbar = ref({ show: false, message: '', type: 'success' });
const showSnack = (msg, type = 'success') => {
    snackbar.value = { show: true, message: msg, type };
    setTimeout(() => { snackbar.value.show = false; }, 4000);
};

// ── Fetch ──────────────────────────────────────────────────────────────────────
const fetchApplicants = async () => {
    loading.value    = true;
    fetchError.value = null;
    try {
        const res = await axios.get('/confirmed-applicants/list');
        applicants.value = res.data;
    } catch (e) {
        fetchError.value = e.response?.data?.message || e.message;
    } finally {
        loading.value = false;
    }
};

onMounted(fetchApplicants);

// ── Computed ───────────────────────────────────────────────────────────────────
const programs = computed(() => {
    const codes = new Set(applicants.value.map(a => a.program?.code).filter(Boolean));
    return Array.from(codes).sort();
});

const filtered = computed(() => {
    let list = applicants.value;
    const q = searchQuery.value.trim().toLowerCase();
    if (q) list = list.filter(a =>
        `${a.firstname} ${a.lastname}`.toLowerCase().includes(q) ||
        (a.email || '').toLowerCase().includes(q) ||
        (a.reference_number || '').toLowerCase().includes(q)
    );
    if (filterProgram.value) list = list.filter(a => a.program?.code === filterProgram.value);
    if (filterSarStatus.value === 'sent')    list = list.filter(a => a.sar_sent);
    if (filterSarStatus.value === 'pending') list = list.filter(a => !a.sar_sent);
    return list;
});

const allSelected = computed(() =>
    filtered.value.length > 0 &&
    filtered.value.every(a => selectedIds.value.includes(a.id))
);

const toggleAll = () => {
    if (allSelected.value) {
        const ids = new Set(filtered.value.map(a => a.id));
        selectedIds.value = selectedIds.value.filter(id => !ids.has(id));
    } else {
        const ids = filtered.value.map(a => a.id);
        selectedIds.value = [...new Set([...selectedIds.value, ...ids])];
    }
};

const toggle = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) selectedIds.value.push(id);
    else selectedIds.value.splice(idx, 1);
};

// ── Send ───────────────────────────────────────────────────────────────────────
const send = async () => {
    if (!selectedIds.value.length) { showSnack('Select at least one applicant.', 'error'); return; }

    if (templateMode.value === 'sar') {
        if (!sarEnrollmentDate.value || !sarEnrollmentTime.value) {
            showSnack('Set enrollment date and time.', 'error'); return;
        }
    } else {
        const quill = document.querySelector('.ql-editor');
        if (!quill?.innerHTML?.trim() && !emailTemplate.value) {
            showSnack('Email body is required.', 'error'); return;
        }
    }

    sending.value    = true;
    sendResult.value = null;

    try {
        if (templateMode.value === 'sar') {
            const res = await axios.post('/confirmed-applicants/send-sar', {
                applicant_ids:   selectedIds.value,
                enrollment_date: sarEnrollmentDate.value,
                enrollment_time: sarEnrollmentTime.value,
            });
            sendResult.value = res.data;
            showSnack(res.data.message, res.data.failed_count ? 'error' : 'success');
            if (!res.data.failed_count) selectedIds.value = [];
            await fetchApplicants();
        } else {
            const quill = document.querySelector('.ql-editor');
            const html  = quill ? quill.innerHTML : emailTemplate.value;
            const res   = await axios.post('/confirmed-applicants/send-email', {
                applicant_ids:    selectedIds.value,
                message_template: html,
            });
            sendResult.value = res.data;
            showSnack(res.data.message, 'success');
            selectedIds.value = [];
        }
    } catch (e) {
        showSnack(e.response?.data?.message || 'Failed to send.', 'error');
    } finally {
        sending.value = false;
    }
};

// ── SAR Previews ───────────────────────────────────────────────────────────────

// SAR Email Template Preview
const showSarEmailPreview = ref(false);
const sarEmailPreviewHtml = ref('');
const loadingEmailPreview = ref(false);

const previewSarEmailTemplate = async () => {
    if (selectedIds.value.length === 0) {
        showSnack('Please select at least one applicant to preview', 'error');
        return;
    }

    const selectedApplicant = filtered.value.find(a => a.id === selectedIds.value[0]);
    if (!selectedApplicant || !selectedApplicant.test_passer_id) {
        showSnack('Selected applicant has no test passer record linked', 'error');
        return;
    }

    loadingEmailPreview.value = true;
    showSarEmailPreview.value = true;
    
    try {
        const response = await axios.post('/admin/sar/preview-email-template', {
            passer_id: selectedApplicant.test_passer_id
        });
        sarEmailPreviewHtml.value = response.data;
    } catch (error) {
        console.error('Failed to preview email template:', error);
        showSnack('Failed to load email preview', 'error');
        closeSarEmailPreview();
    } finally {
        loadingEmailPreview.value = false;
    }
};

const closeSarEmailPreview = () => {
    showSarEmailPreview.value = false;
    sarEmailPreviewHtml.value = '';
};

// SAR PDF Form Preview
const showSarPdfPreview = ref(false);
const sarPdfPreviewUrl = ref('');
const loadingPdfPreview = ref(false);

const previewSarPdfForm = async () => {
    if (selectedIds.value.length === 0) {
        showSnack('Please select at least one applicant to preview', 'error');
        return;
    }

    if (!sarEnrollmentDate.value || !sarEnrollmentTime.value) {
        showSnack('Please set enrollment date and time', 'error');
        return;
    }

    const selectedApplicant = filtered.value.find(a => a.id === selectedIds.value[0]);
    if (!selectedApplicant || !selectedApplicant.test_passer_id) {
        showSnack('Selected applicant has no test passer record linked', 'error');
        return;
    }

    loadingPdfPreview.value = true;
    showSarPdfPreview.value = true;
    
    try {
        if (sarPdfPreviewUrl.value) {
            URL.revokeObjectURL(sarPdfPreviewUrl.value);
            sarPdfPreviewUrl.value = '';
        }
        
        const formData = new FormData();
        formData.append('passer_id', selectedApplicant.test_passer_id);
        formData.append('enrollment_date', sarEnrollmentDate.value);
        formData.append('enrollment_time', sarEnrollmentTime.value);

        const response = await axios.post('/admin/sar/preview-pdf-template', formData, {
            responseType: 'blob'
        });

        const blob = new Blob([response.data], { type: 'application/pdf' });
        sarPdfPreviewUrl.value = URL.createObjectURL(blob);
    } catch (error) {
        console.error('Failed to preview SAR PDF:', error);
        showSnack('Failed to generate SAR PDF preview', 'error');
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
    sarPdfPreviewUrl.value = '';
};

const fmt = (v) => (v === null || v === undefined ? '—' : parseFloat(v).toFixed(2));
</script>

<template>
    <Head title="Confirmed Applicants" />
    <AppLayout>
        <!-- Page Header -->
        <div class="mb-5">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Confirmed Applicants</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                Applicants with <span class="font-semibold text-yellow-600 dark:text-yellow-400">For Evaluation</span> status.
                Send SAR Forms or custom emails.
            </p>
        </div>

        <!-- Two-column layout -->
        <div class="flex gap-6 items-start">

            <!-- ── LEFT: Applicant List ─────────────────────────────── -->
            <div class="flex-1 min-w-0">
                <!-- Filters & Controls card -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 mb-4">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Filters &amp; Controls
                        </h2>
                        <span class="text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-full">
                            {{ applicants.length }} applicants
                        </span>
                    </div>

                    <!-- Search -->
                    <div class="relative mb-3">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input v-model="searchQuery" type="text"
                            placeholder="Search by name, email, or reference no…"
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-900 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent placeholder-gray-400" />
                    </div>

                    <!-- Filter row -->
                    <div class="flex gap-3 flex-wrap">
                        <select v-model="filterProgram"
                            class="flex-1 min-w-[140px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-900 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#9E122C]">
                            <option value="">All Programs</option>
                            <option v-for="p in programs" :key="p" :value="p">{{ p }}</option>
                        </select>
                        <select v-model="filterSarStatus"
                            class="flex-1 min-w-[140px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-900 text-sm text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#9E122C]">
                            <option value="">All SAR Statuses</option>
                            <option value="sent">SAR Sent</option>
                            <option value="pending">SAR Pending</option>
                        </select>
                        <button @click="fetchApplicants"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            ↻ Refresh
                        </button>
                    </div>

                    <!-- Select All + count -->
                    <div class="flex items-center gap-3 mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600 dark:text-gray-400">
                            <input type="checkbox" :checked="allSelected" @change="toggleAll"
                                class="h-4 w-4 rounded text-[#9E122C] border-gray-300 focus:ring-[#9E122C]" />
                            Select All ({{ filtered.length }})
                        </label>
                        <span v-if="selectedIds.length" class="text-xs text-[#9E122C] font-medium">
                            {{ selectedIds.length }} selected
                        </span>
                    </div>
                </div>

                <!-- Loading / Error -->
                <div v-if="loading" class="py-12 text-center text-gray-500 dark:text-gray-400">Loading…</div>
                <div v-else-if="fetchError" class="py-8 text-center text-red-600 dark:text-red-400">{{ fetchError }}</div>
                <div v-else-if="!filtered.length" class="py-12 text-center text-gray-500 dark:text-gray-400">
                    No confirmed applicants found.
                </div>

                <!-- Applicant rows -->
                <div v-else class="space-y-2">
                    <div
                        v-for="a in filtered"
                        :key="a.id"
                        @click="toggle(a.id)"
                        :class="[
                            'flex items-center gap-4 bg-white dark:bg-gray-800 rounded-xl border px-4 py-3 cursor-pointer transition-all',
                            selectedIds.includes(a.id)
                                ? 'border-[#9E122C] ring-1 ring-[#9E122C]/30'
                                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                        ]"
                    >
                        <input type="checkbox" :checked="selectedIds.includes(a.id)"
                            class="h-4 w-4 rounded text-[#9E122C] border-gray-300 focus:ring-[#9E122C] pointer-events-none flex-shrink-0" />

                        <!-- Name & email -->
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-gray-900 dark:text-white text-sm truncate">
                                {{ a.lastname }}, {{ a.firstname }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ a.email }}</div>
                            <div v-if="a.reference_number" class="text-xs font-mono text-gray-500 dark:text-gray-400">
                                Ref: {{ a.reference_number }}
                            </div>
                        </div>

                        <!-- Program -->
                        <div class="hidden sm:block text-xs text-gray-600 dark:text-gray-400 text-center flex-shrink-0 w-16">
                            <span class="font-semibold">{{ a.program?.code || '—' }}</span>
                        </div>

                        <!-- SAR badge -->
                        <div class="flex-shrink-0">
                            <span v-if="a.sar_sent"
                                class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs rounded-full font-medium">
                                SAR Sent
                            </span>
                            <span v-else
                                class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 text-xs rounded-full font-medium">
                                Pending
                            </span>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ── RIGHT: Action Panel ──────────────────────────────── -->
            <div class="w-[550px] flex-shrink-0 sticky top-6">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Action Panel</h2>

                    <!-- Template Type Selector -->
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Select Action Type</p>
                    <div class="grid grid-cols-2 gap-2 mb-5">
                        <button
                            @click="templateMode = 'sar'"
                            :class="[
                                'py-2.5 rounded-xl text-sm font-medium transition-all border',
                                templateMode === 'sar'
                                    ? 'bg-[#9E122C] text-white border-[#9E122C] shadow'
                                    : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-[#9E122C]'
                            ]">
                            SAR Form
                        </button>
                        <button
                            @click="templateMode = 'custom'"
                            :class="[
                                'py-2.5 rounded-xl text-sm font-medium transition-all border',
                                templateMode === 'custom'
                                    ? 'bg-[#9E122C] text-white border-[#9E122C] shadow'
                                    : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-[#9E122C]'
                            ]">
                            Custom Email
                        </button>
                    </div>

                    <!-- ── SAR Form options ── -->
                    <div v-if="templateMode === 'sar'" class="mt-2 p-4 rounded-xl bg-blue-50 border border-blue-200 dark:bg-blue-900/20 dark:border-blue-700">
                        <div class="flex items-start gap-3 mb-4">
                            <div class="p-2 bg-blue-100 rounded-lg dark:bg-blue-800">
                                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-semibold text-blue-900 mb-1 text-sm dark:text-blue-200">
                                    SAR Form Settings
                                </h4>
                                <p class="text-xs text-blue-800 dark:text-blue-300">
                                    Personalized PDF will be generated for each selected passer
                                </p>
                            </div>
                        </div>
                        
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1 dark:text-gray-400">
                                    Enrollment Date
                                </label>
                                <input type="date" v-model="sarEnrollmentDate"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm dark:bg-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C]" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1 dark:text-gray-400">
                                    Enrollment Time
                                </label>
                                <input type="time" v-model="sarEnrollmentTime"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm dark:bg-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C]" />
                            </div>
                        </div>

                        <!-- Preview Email Template Button -->
                        <div class="mt-4 space-y-2">
                            <button
                                @click="previewSarEmailTemplate"
                                :disabled="selectedIds.length === 0"
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm rounded-xl hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition dark:text-gray-900"
                            >
                                <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                Preview Email Template
                            </button>
                            
                            <button
                                @click="previewSarPdfForm"
                                :disabled="selectedIds.length === 0 || !sarEnrollmentDate || !sarEnrollmentTime"
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-green-600 text-white text-sm rounded-xl hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition dark:text-gray-900"
                            >
                                <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Preview SAR PDF Form
                            </button>
                            
                            <p class="text-xs text-gray-500 text-center dark:text-gray-400">
                                Preview the actual SAR form and email before sending
                            </p>
                        </div>
                    </div>

                    <!-- ── Custom Email ── -->
                    <div v-else class="space-y-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Use <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">&#123;&#123;firstname&#125;&#125;</code>,
                            <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">&#123;&#123;surname&#125;&#125;</code>,
                            <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">&#123;&#123;reference_no&#125;&#125;</code>
                            as placeholders.
                        </p>
                        <div class="border border-gray-300 dark:border-gray-600 rounded-xl overflow-hidden text-sm">
                            <QuillEditor
                                v-model="emailTemplate"
                                style="min-height: 300px;"
                                theme="snow"
                                toolbar="full"
                                placeholder="Write your email…"
                            />
                        </div>
                    </div>

                    <!-- Selection info -->
                    <div class="mt-4 text-xs text-gray-500 dark:text-gray-400 text-center">
                        <span class="font-semibold text-gray-800 dark:text-white">{{ selectedIds.length }}</span>
                        applicant(s) selected
                    </div>

                    <!-- Send button -->
                    <button @click="send" :disabled="sending || !selectedIds.length"
                        :class="[
                            'w-full mt-3 py-3 rounded-xl font-semibold text-sm text-white transition-all flex items-center justify-center gap-2',
                            selectedIds.length && !sending
                                ? 'bg-[#9E122C] hover:bg-[#800918] shadow-lg'
                                : 'bg-gray-300 dark:bg-gray-700 cursor-not-allowed'
                        ]">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        {{ sending ? 'Sending…' : (templateMode === 'sar' ? `Send SAR to ${selectedIds.length}` : `Send Email to ${selectedIds.length}`) }}
                    </button>

                    <!-- Result -->
                    <div v-if="sendResult" class="mt-4 p-3 rounded-xl border text-xs"
                        :class="sendResult.failed_count ? 'border-yellow-300 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-300' : 'border-green-300 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-300'">
                        <p class="font-semibold mb-1">{{ sendResult.message }}</p>
                        <ul v-if="sendResult.errors?.length" class="space-y-0.5 text-red-600 dark:text-red-400">
                            <li v-for="err in sendResult.errors" :key="err.email || err.applicant">
                                • {{ err.applicant }}: {{ err.error }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Snackbar -->
        <div v-if="snackbar.show"
            :class="['fixed top-4 right-4 z-50 px-5 py-3 rounded-xl shadow-lg text-white font-medium text-sm',
                snackbar.type === 'success' ? 'bg-green-600' : 'bg-red-600']">
            {{ snackbar.message }}
        </div>
        <!-- SAR PDF Form Preview Modal -->
        <div
            v-if="showSarPdfPreview"
            class="fixed inset-0 bg-black bg-opacity-75 flex justify-center items-center p-4 z-50 dark:bg-white"
            @click.self="closeSarPdfPreview"
        >
            <div class="bg-white rounded-2xl max-w-6xl w-full h-[90vh] flex flex-col shadow-2xl dark:bg-gray-800">
                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200">
                        SAR PDF Form Preview
                    </h2>
                    <button
                        @click="closeSarPdfPreview"
                        class="p-2 hover:bg-gray-100 rounded-lg transition dark:hover:bg-gray-800"
                    >
                        <svg class="h-6 w-6 text-gray-500 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-hidden">
                    <div v-if="loadingPdfPreview" class="flex items-center justify-center h-full">
                        <div class="text-center">
                            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-[#9E122C]"></div>
                            <p class="text-gray-600 mt-4 dark:text-gray-400">Generating SAR PDF preview...</p>
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
            <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl dark:bg-gray-800">
                <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200">
                        SAR Email Template Preview
                    </h2>
                    <button
                        @click="closeSarEmailPreview"
                        class="p-2 hover:bg-gray-100 rounded-lg transition dark:hover:bg-gray-800"
                    >
                        <svg class="h-6 w-6 text-gray-500 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-auto p-6 bg-gray-50 dark:bg-gray-900">
                    <div v-if="loadingEmailPreview" class="flex items-center justify-center h-full">
                        <div class="text-center">
                            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-[#9E122C]"></div>
                            <p class="text-gray-600 mt-4 dark:text-gray-400">Loading preview...</p>
                        </div>
                    </div>
                    <iframe
                        v-else-if="sarEmailPreviewHtml"
                        :srcdoc="sarEmailPreviewHtml"
                        class="w-full h-full border-0 bg-white rounded-lg shadow-sm dark:bg-gray-800"
                        style="min-height: 600px;"
                    ></iframe>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
