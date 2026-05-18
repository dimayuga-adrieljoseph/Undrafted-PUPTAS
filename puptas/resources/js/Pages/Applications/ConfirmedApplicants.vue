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
const selectedIds     = ref([]);
const activeTab       = ref('list'); // 'list' | 'sar' | 'email'

// SAR state
const sarEnrollmentDate = ref(new Date().toISOString().split('T')[0]);
const sarEnrollmentTime = ref('09:00');
const sendingSar        = ref(false);
const sarResult         = ref(null);

// Custom email state
const emailTemplate  = ref('');
const sendingEmail   = ref(false);
const emailResult    = ref(null);

// Grade sync state
const syncingUserId  = ref(null);
const syncRefInput   = ref({});
const syncResult     = ref({});

// Snackbar
const snackbar = ref({ show: false, message: '', type: 'success' });
const showSnack = (msg, type = 'success') => {
    snackbar.value = { show: true, message: msg, type };
    setTimeout(() => { snackbar.value.show = false; }, 4000);
};

// ── Fetch ──────────────────────────────────────────────────────────────────────
const fetchApplicants = async () => {
    loading.value  = true;
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
const filtered = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return applicants.value;
    return applicants.value.filter(a =>
        `${a.firstname} ${a.lastname}`.toLowerCase().includes(q) ||
        (a.email || '').toLowerCase().includes(q) ||
        (a.reference_number || '').toLowerCase().includes(q)
    );
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

// ── SAR Send ───────────────────────────────────────────────────────────────────
const sendSar = async () => {
    if (!selectedIds.value.length) { showSnack('Select at least one applicant.', 'error'); return; }
    if (!sarEnrollmentDate.value || !sarEnrollmentTime.value) { showSnack('Set enrollment date and time.', 'error'); return; }

    sendingSar.value = true;
    sarResult.value  = null;
    try {
        const res = await axios.post('/confirmed-applicants/send-sar', {
            applicant_ids:   selectedIds.value,
            enrollment_date: sarEnrollmentDate.value,
            enrollment_time: sarEnrollmentTime.value,
        });
        sarResult.value = res.data;
        showSnack(`SAR sent: ${res.data.success_count} ok, ${res.data.failed_count} failed.`,
            res.data.failed_count ? 'error' : 'success');
        if (!res.data.failed_count) selectedIds.value = [];
        await fetchApplicants();
    } catch (e) {
        showSnack(e.response?.data?.message || 'Failed to send SAR.', 'error');
    } finally {
        sendingSar.value = false;
    }
};

// ── Custom Email ───────────────────────────────────────────────────────────────
const sendCustomEmail = async () => {
    if (!selectedIds.value.length) { showSnack('Select at least one applicant.', 'error'); return; }
    if (!emailTemplate.value) { showSnack('Email template is required.', 'error'); return; }

    sendingEmail.value = true;
    emailResult.value  = null;
    try {
        const quill = document.querySelector('.ql-editor');
        const html  = quill ? quill.innerHTML : emailTemplate.value;
        const res   = await axios.post('/confirmed-applicants/send-email', {
            applicant_ids:    selectedIds.value,
            message_template: html,
        });
        emailResult.value = res.data;
        showSnack(res.data.message, 'success');
        selectedIds.value = [];
    } catch (e) {
        showSnack(e.response?.data?.message || 'Failed to send email.', 'error');
    } finally {
        sendingEmail.value = false;
    }
};

// ── Grade Sync ─────────────────────────────────────────────────────────────────
const syncGrades = async (userId) => {
    syncingUserId.value = userId;
    syncResult.value[userId] = null;
    try {
        const res = await axios.post(`/confirmed-applicants/${userId}/sync-grades`, {
            reference_number: syncRefInput.value[userId] || undefined,
        });
        syncResult.value[userId] = { success: true, message: res.data.message };
        showSnack(res.data.message, 'success');
        await fetchApplicants();
    } catch (e) {
        const msg = e.response?.data?.message || 'Grade sync failed.';
        syncResult.value[userId] = { success: false, message: msg };
        showSnack(msg, 'error');
    } finally {
        syncingUserId.value = null;
    }
};

// ── Helpers ────────────────────────────────────────────────────────────────────
const fmt = (v) => (v === null || v === undefined ? '—' : parseFloat(v).toFixed(2));
</script>

<template>
    <Head title="Confirmed Applicants" />
    <AppLayout>
        <!-- Header -->
        <div class="mb-6 flex items-center gap-4">
            <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-2xl">
                <svg class="h-7 w-7 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Confirmed Applicants</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Applicants with <span class="font-semibold text-yellow-600">For Evaluation</span> status —
                    SAR Forms, custom emails, and grade sync available.
                </p>
            </div>
            <div class="ml-auto">
                <button @click="fetchApplicants"
                    class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 mb-6 bg-gray-100 dark:bg-gray-800 p-1 rounded-xl w-fit">
            <button v-for="tab in [{id:'list',label:'Applicants'},{id:'sar',label:'Send SAR Form'},{id:'email',label:'Custom Email'}]"
                :key="tab.id"
                @click="activeTab = tab.id"
                :class="[
                    'px-4 py-2 rounded-lg text-sm font-medium transition-all',
                    activeTab === tab.id
                        ? 'bg-white dark:bg-gray-700 text-[#9E122C] dark:text-white shadow'
                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
                ]">
                {{ tab.label }}
                <span v-if="tab.id === 'list'" class="ml-1.5 px-1.5 py-0.5 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 text-xs rounded-full">
                    {{ applicants.length }}
                </span>
                <span v-if="tab.id !== 'list' && selectedIds.length" class="ml-1.5 px-1.5 py-0.5 bg-[#9E122C]/10 text-[#9E122C] text-xs rounded-full">
                    {{ selectedIds.length }} selected
                </span>
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="py-16 text-center text-gray-500 dark:text-gray-400">Loading confirmed applicants…</div>
        <div v-else-if="fetchError" class="py-8 text-center text-red-600 dark:text-red-400">{{ fetchError }}</div>

        <template v-else>
            <!-- ── TAB: Applicants List ────────────────────────────────── -->
            <div v-show="activeTab === 'list'">
                <!-- Search + Selection -->
                <div class="flex flex-col sm:flex-row gap-3 mb-4">
                    <div class="relative flex-1">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input v-model="searchQuery" type="text" placeholder="Search by name, email, or reference…"
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent" />
                    </div>
                    <button @click="toggleAll"
                        class="px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                        {{ allSelected ? 'Deselect All' : 'Select All' }} ({{ selectedIds.length }})
                    </button>
                </div>

                <div v-if="!filtered.length" class="py-12 text-center text-gray-500 dark:text-gray-400">
                    No confirmed applicants found.
                </div>

                <!-- Table -->
                <div v-else class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left">
                                    <input type="checkbox" :checked="allSelected" @change="toggleAll"
                                        class="h-4 w-4 rounded text-[#9E122C] border-gray-300 focus:ring-[#9E122C]" />
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Applicant</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Program</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Reference No.</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Grades</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">SAR Sent</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Sync Grades</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="a in filtered" :key="a.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                <td class="px-4 py-3">
                                    <input type="checkbox" :value="a.id" v-model="selectedIds"
                                        class="h-4 w-4 rounded text-[#9E122C] border-gray-300 focus:ring-[#9E122C]" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ a.lastname }}, {{ a.firstname }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ a.email }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ a.program?.code || '—' }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="a.reference_number" class="font-mono text-xs text-gray-700 dark:text-gray-300">{{ a.reference_number }}</span>
                                    <span v-else class="text-gray-400 text-xs italic">No ref #</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="a.grades" class="text-xs space-y-0.5">
                                        <div>Eng: <span class="font-medium">{{ fmt(a.grades.english) }}</span></div>
                                        <div>Math: <span class="font-medium">{{ fmt(a.grades.mathematics) }}</span></div>
                                        <div>Sci: <span class="font-medium">{{ fmt(a.grades.science) }}</span></div>
                                    </div>
                                    <span v-else class="text-xs text-gray-400 italic">No grades</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="a.sar_sent" class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs rounded-full">Sent</span>
                                    <span v-else class="px-2 py-1 bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-xs rounded-full">Pending</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <input v-if="!a.has_test_passer" v-model="syncRefInput[a.id]"
                                            type="text" placeholder="Ref #"
                                            class="w-24 px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-800 dark:text-white focus:ring-1 focus:ring-[#9E122C]" />
                                        <button @click="syncGrades(a.id)"
                                            :disabled="syncingUserId === a.id"
                                            class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap">
                                            {{ syncingUserId === a.id ? 'Syncing…' : (a.has_test_passer ? '↻ Sync' : '↻ Link & Sync') }}
                                        </button>
                                    </div>
                                    <div v-if="syncResult[a.id]"
                                        :class="syncResult[a.id].success ? 'text-green-600' : 'text-red-500'"
                                        class="text-xs mt-1">
                                        {{ syncResult[a.id].message }}
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── TAB: Send SAR Form ──────────────────────────────────── -->
            <div v-show="activeTab === 'sar'" class="max-w-2xl">
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-xl p-4 mb-6">
                    <div class="flex items-center gap-3 mb-2">
                        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h3 class="font-semibold text-blue-900 dark:text-blue-200">SAR Form Email</h3>
                    </div>
                    <p class="text-sm text-blue-800 dark:text-blue-300">
                        SAR Forms are restricted to <strong>confirmed applicants</strong> (For Evaluation status) only.
                        Select applicants in the Applicants tab first.
                    </p>
                </div>

                <div class="space-y-4 bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Enrollment Date</label>
                        <input type="date" v-model="sarEnrollmentDate"
                            class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl text-sm dark:bg-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C]" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Enrollment Time</label>
                        <input type="time" v-model="sarEnrollmentTime"
                            class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl text-sm dark:bg-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C]" />
                    </div>

                    <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-medium">{{ selectedIds.length }}</span> applicant(s) selected.
                        <button class="ml-2 text-[#9E122C] hover:underline text-xs" @click="activeTab = 'list'">
                            ← Go to Applicants tab to select
                        </button>
                    </div>

                    <button @click="sendSar" :disabled="sendingSar || !selectedIds.length"
                        :class="[
                            'w-full py-3 rounded-xl font-semibold text-white transition-all',
                            selectedIds.length && !sendingSar
                                ? 'bg-[#9E122C] hover:bg-[#800918] shadow-lg'
                                : 'bg-gray-300 dark:bg-gray-700 cursor-not-allowed'
                        ]">
                        {{ sendingSar ? 'Sending SAR Forms…' : `Send SAR to ${selectedIds.length} Applicant(s)` }}
                    </button>

                    <!-- SAR Result -->
                    <div v-if="sarResult" class="rounded-xl p-4 border"
                        :class="sarResult.failed_count ? 'border-yellow-300 bg-yellow-50 dark:bg-yellow-900/20' : 'border-green-300 bg-green-50 dark:bg-green-900/20'">
                        <p class="font-semibold text-sm mb-2"
                            :class="sarResult.failed_count ? 'text-yellow-800 dark:text-yellow-300' : 'text-green-800 dark:text-green-300'">
                            {{ sarResult.message }}
                        </p>
                        <div v-if="sarResult.errors?.length" class="space-y-1">
                            <p class="text-xs font-medium text-red-600 dark:text-red-400">Failed:</p>
                            <div v-for="err in sarResult.errors" :key="err.email" class="text-xs text-red-700 dark:text-red-300">
                                • {{ err.applicant }} ({{ err.email }}): {{ err.error }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── TAB: Custom Email ───────────────────────────────────── -->
            <div v-show="activeTab === 'email'" class="max-w-3xl">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700 space-y-4">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                            <span class="font-medium">{{ selectedIds.length }}</span> applicant(s) selected.
                            Use <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">&#123;&#123;firstname&#125;&#125;</code>,
                            <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">&#123;&#123;surname&#125;&#125;</code>,
                            <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">&#123;&#123;reference_no&#125;&#125;</code> as placeholders.
                        </p>
                    </div>

                    <div class="border border-gray-300 dark:border-gray-600 rounded-xl overflow-hidden">
                        <QuillEditor
                            v-model="emailTemplate"
                            style="min-height: 280px"
                            theme="snow"
                            toolbar="full"
                            placeholder="Write your custom email here…"
                        />
                    </div>

                    <button @click="sendCustomEmail" :disabled="sendingEmail || !selectedIds.length || !emailTemplate"
                        :class="[
                            'w-full py-3 rounded-xl font-semibold text-white transition-all',
                            selectedIds.length && emailTemplate && !sendingEmail
                                ? 'bg-[#9E122C] hover:bg-[#800918] shadow-lg'
                                : 'bg-gray-300 dark:bg-gray-700 cursor-not-allowed'
                        ]">
                        {{ sendingEmail ? 'Sending…' : `Send Email to ${selectedIds.length} Applicant(s)` }}
                    </button>

                    <div v-if="emailResult" class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-300 dark:border-green-700 rounded-xl text-sm text-green-800 dark:text-green-300">
                        {{ emailResult.message }}
                    </div>
                </div>
            </div>
        </template>

        <!-- Snackbar -->
        <div v-if="snackbar.show"
            :class="['fixed top-4 right-4 z-50 px-5 py-3 rounded-xl shadow-lg text-white font-medium text-sm transition-all',
                snackbar.type === 'success' ? 'bg-green-600' : 'bg-red-600']">
            {{ snackbar.message }}
        </div>
    </AppLayout>
</template>
