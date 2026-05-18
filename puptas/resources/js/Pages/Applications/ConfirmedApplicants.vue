<script setup>
import { ref, computed, onMounted } from 'vue';
const axios = window.axios;
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';

// ── Data ───────────────────────────────────────────────────────────────────────
const applicants    = ref([]);
const loading       = ref(false);
const fetchError    = ref(null);
const searchQuery   = ref('');
const filterProgram = ref('');
const filterSar     = ref('');
const selectedIds   = ref([]);

// Action panel
const mode              = ref('sar'); // 'sar' | 'custom'
const sarDate           = ref(new Date().toISOString().split('T')[0]);
const sarTime           = ref('09:00');
const customHtml        = ref('');
const sending           = ref(false);
const sendResult        = ref(null);

// Grade sync
const syncingId  = ref(null);
const syncRef    = ref({});

// Snackbar
const snack = ref({ show: false, msg: '', type: 'success' });
const toast = (msg, type = 'success') => {
    snack.value = { show: true, msg, type };
    setTimeout(() => { snack.value.show = false; }, 4000);
};

// ── Fetch ──────────────────────────────────────────────────────────────────────
const load = async () => {
    loading.value = true;
    fetchError.value = null;
    try {
        const r = await axios.get('/confirmed-applicants/list');
        applicants.value = r.data;
    } catch (e) {
        fetchError.value = e.response?.data?.message || e.message;
    } finally {
        loading.value = false;
    }
};
onMounted(load);

// ── Computed ───────────────────────────────────────────────────────────────────
const programCodes = computed(() => {
    const s = new Set(applicants.value.map(a => a.program?.code).filter(Boolean));
    return [...s].sort();
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
    if (filterSar.value === 'sent')    list = list.filter(a => a.sar_sent);
    if (filterSar.value === 'pending') list = list.filter(a => !a.sar_sent);
    return list;
});

const allSelected = computed(() =>
    filtered.value.length > 0 && filtered.value.every(a => selectedIds.value.includes(a.id))
);

const toggle = (id) => {
    const i = selectedIds.value.indexOf(id);
    if (i === -1) selectedIds.value.push(id);
    else selectedIds.value.splice(i, 1);
};

const toggleAll = () => {
    if (allSelected.value) {
        const ids = new Set(filtered.value.map(a => a.id));
        selectedIds.value = selectedIds.value.filter(id => !ids.has(id));
    } else {
        selectedIds.value = [...new Set([...selectedIds.value, ...filtered.value.map(a => a.id)])];
    }
};

// ── Send ───────────────────────────────────────────────────────────────────────
const send = async () => {
    if (!selectedIds.value.length) { toast('Select at least one applicant.', 'error'); return; }
    if (mode.value === 'sar' && (!sarDate.value || !sarTime.value)) {
        toast('Set enrollment date and time.', 'error'); return;
    }
    sending.value  = true;
    sendResult.value = null;
    try {
        if (mode.value === 'sar') {
            const r = await axios.post('/confirmed-applicants/send-sar', {
                applicant_ids: selectedIds.value,
                enrollment_date: sarDate.value,
                enrollment_time: sarTime.value,
            });
            sendResult.value = r.data;
            toast(r.data.message, r.data.failed_count ? 'error' : 'success');
            if (!r.data.failed_count) selectedIds.value = [];
            await load();
        } else {
            const quill = document.querySelector('.ql-editor');
            const html  = quill ? quill.innerHTML : customHtml.value;
            const r     = await axios.post('/confirmed-applicants/send-email', {
                applicant_ids: selectedIds.value,
                message_template: html,
            });
            sendResult.value = r.data;
            toast(r.data.message, 'success');
            selectedIds.value = [];
        }
    } catch (e) {
        toast(e.response?.data?.message || 'Failed to send.', 'error');
    } finally {
        sending.value = false;
    }
};

// ── Grade Sync ─────────────────────────────────────────────────────────────────
const syncGrades = async (userId) => {
    syncingId.value = userId;
    try {
        const r = await axios.post(`/confirmed-applicants/${userId}/sync-grades`, {
            reference_number: syncRef.value[userId] || undefined,
        });
        toast(r.data.message);
        await load();
    } catch (e) {
        toast(e.response?.data?.message || 'Sync failed.', 'error');
    } finally {
        syncingId.value = null;
    }
};
</script>

<template>
    <Head title="Confirmed Applicants" />
    <AppLayout>
        <!-- Header -->
        <div class="mb-5 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">Confirmed Applicants</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    Applicants with
                    <span class="font-semibold text-yellow-600 dark:text-yellow-400">For Evaluation</span>
                    status — send SAR Forms or custom emails.
                </p>
            </div>
        </div>

        <!-- Two-column split -->
        <div class="flex gap-6 items-start">

            <!-- LEFT: list ───────────────────────────────────────────── -->
            <div class="flex-1 min-w-0">

                <!-- Filters card -->
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-5 mb-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Filters &amp; Controls</span>
                        <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-1 rounded-full">
                            {{ applicants.length }} applicants
                        </span>
                    </div>

                    <!-- Search -->
                    <div class="relative mb-3">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input v-model="searchQuery" type="text"
                            placeholder="Search by name, email, or reference no…"
                            class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent" />
                    </div>

                    <!-- Dropdowns -->
                    <div class="flex gap-3 flex-wrap">
                        <select v-model="filterProgram"
                            class="flex-1 min-w-[130px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#9E122C]">
                            <option value="">All Programs</option>
                            <option v-for="p in programCodes" :key="p" :value="p">{{ p }}</option>
                        </select>
                        <select v-model="filterSar"
                            class="flex-1 min-w-[130px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#9E122C]">
                            <option value="">All SAR Statuses</option>
                            <option value="sent">SAR Sent</option>
                            <option value="pending">Pending</option>
                        </select>
                        <button @click="load"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            ↻ Refresh
                        </button>
                    </div>

                    <!-- Select All -->
                    <div class="flex items-center gap-3 mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600 dark:text-gray-400">
                            <input type="checkbox" :checked="allSelected" @change="toggleAll"
                                class="h-4 w-4 rounded text-[#9E122C] border-gray-300 focus:ring-[#9E122C]" />
                            Select All ({{ filtered.length }})
                        </label>
                        <span v-if="selectedIds.length" class="text-xs font-medium text-[#9E122C]">
                            {{ selectedIds.length }} selected
                        </span>
                    </div>
                </div>

                <!-- Loading / empty -->
                <div v-if="loading" class="py-12 text-center text-gray-500 dark:text-gray-400 text-sm">Loading…</div>
                <div v-else-if="fetchError" class="py-8 text-center text-red-600 dark:text-red-400 text-sm">{{ fetchError }}</div>
                <div v-else-if="!filtered.length" class="py-12 text-center text-gray-500 dark:text-gray-400 text-sm">No confirmed applicants found.</div>

                <!-- Rows -->
                <div v-else class="space-y-2">
                    <div v-for="a in filtered" :key="a.id"
                        @click="toggle(a.id)"
                        :class="[
                            'flex items-center gap-4 bg-white dark:bg-gray-800 rounded-xl border px-4 py-3.5 cursor-pointer transition-all',
                            selectedIds.includes(a.id)
                                ? 'border-[#9E122C] ring-1 ring-[#9E122C]/20 bg-[#9E122C]/5 dark:bg-[#9E122C]/10'
                                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                        ]">
                        <!-- Checkbox -->
                        <input type="checkbox" :checked="selectedIds.includes(a.id)"
                            class="h-4 w-4 rounded text-[#9E122C] border-gray-300 focus:ring-[#9E122C] pointer-events-none flex-shrink-0" />

                        <!-- Name & email -->
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm text-gray-900 dark:text-white truncate">
                                {{ a.lastname }}, {{ a.firstname }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ a.email }}</div>
                            <div v-if="a.reference_number" class="text-xs font-mono text-gray-400 dark:text-gray-500 mt-0.5">
                                Ref: {{ a.reference_number }}
                            </div>
                        </div>

                        <!-- Program badge -->
                        <span class="hidden sm:inline-block flex-shrink-0 text-xs font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-lg">
                            {{ a.program?.code || '—' }}
                        </span>

                        <!-- SAR badge -->
                        <span :class="a.sar_sent
                            ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'
                            : 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400'"
                            class="flex-shrink-0 text-xs font-medium px-2 py-1 rounded-full">
                            {{ a.sar_sent ? 'SAR Sent' : 'Pending' }}
                        </span>

                        <!-- Sync -->
                        <div class="flex-shrink-0 flex items-center gap-1.5" @click.stop>
                            <input v-if="!a.has_test_passer" v-model="syncRef[a.id]"
                                type="text" placeholder="Ref #"
                                class="w-20 px-2 py-1 text-xs border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-1 focus:ring-[#9E122C]" />
                            <button @click="syncGrades(a.id)" :disabled="syncingId === a.id"
                                class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs rounded-lg transition whitespace-nowrap">
                                {{ syncingId === a.id ? '…' : (a.has_test_passer ? '↻ Sync' : '↻ Link') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: action panel ──────────────────────────────────── -->
            <div class="w-80 flex-shrink-0 sticky top-6 space-y-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-5">

                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4">Action Panel</h2>

                    <!-- Mode buttons -->
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Select Action Type</p>
                    <div class="grid grid-cols-2 gap-2 mb-5">
                        <button @click="mode = 'sar'; sendResult = null"
                            :class="[
                                'py-2.5 rounded-xl text-sm font-medium border transition-all',
                                mode === 'sar'
                                    ? 'bg-[#9E122C] text-white border-[#9E122C] shadow'
                                    : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-[#9E122C]'
                            ]">
                            SAR Form
                        </button>
                        <button @click="mode = 'custom'; sendResult = null"
                            :class="[
                                'py-2.5 rounded-xl text-sm font-medium border transition-all',
                                mode === 'custom'
                                    ? 'bg-[#9E122C] text-white border-[#9E122C] shadow'
                                    : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-[#9E122C]'
                            ]">
                            Custom Email
                        </button>
                    </div>

                    <!-- SAR fields -->
                    <div v-if="mode === 'sar'" class="space-y-3">
                        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-xl text-xs text-blue-800 dark:text-blue-300">
                            Only confirmed applicants (For Evaluation) can receive SAR forms.
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Enrollment Date</label>
                            <input type="date" v-model="sarDate"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm dark:bg-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C]" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Enrollment Time</label>
                            <input type="time" v-model="sarTime"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm dark:bg-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C]" />
                        </div>
                    </div>

                    <!-- Custom email editor -->
                    <div v-else class="space-y-3">
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Placeholders:
                            <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">&#123;&#123;firstname&#125;&#125;</code>
                            <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded ml-1">&#123;&#123;surname&#125;&#125;</code>
                            <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded ml-1">&#123;&#123;reference_no&#125;&#125;</code>
                        </p>
                        <div class="border border-gray-300 dark:border-gray-600 rounded-xl overflow-hidden">
                            <QuillEditor
                                v-model="customHtml"
                                style="min-height:200px;max-height:320px"
                                theme="snow"
                                :toolbar="['bold','italic','underline','link','clean']"
                                placeholder="Write your email message…"
                            />
                        </div>
                    </div>

                    <!-- Selected count -->
                    <div class="mt-4 text-center text-xs text-gray-500 dark:text-gray-400">
                        <span class="font-semibold text-gray-800 dark:text-white">{{ selectedIds.length }}</span> applicant(s) selected
                    </div>

                    <!-- Send button -->
                    <button @click="send" :disabled="sending || !selectedIds.length"
                        :class="[
                            'mt-3 w-full py-3 rounded-xl text-sm font-semibold text-white flex items-center justify-center gap-2 transition-all',
                            selectedIds.length && !sending
                                ? 'bg-[#9E122C] hover:bg-[#800918] shadow-lg'
                                : 'bg-gray-300 dark:bg-gray-700 cursor-not-allowed'
                        ]">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <span>{{ sending ? 'Sending…' : (mode === 'sar' ? `Send SAR to ${selectedIds.length}` : `Send Email to ${selectedIds.length}`) }}</span>
                    </button>

                    <!-- Result feedback -->
                    <div v-if="sendResult" class="mt-4 p-3 rounded-xl border text-xs"
                        :class="sendResult.failed_count
                            ? 'border-yellow-300 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-300'
                            : 'border-green-300 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-300'">
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
        <transition name="slide-fade">
            <div v-if="snack.show"
                :class="['fixed top-4 right-4 z-50 px-5 py-3 rounded-xl shadow-lg text-white text-sm font-medium',
                    snack.type === 'success' ? 'bg-green-600' : 'bg-red-600']">
                {{ snack.msg }}
            </div>
        </transition>
    </AppLayout>
</template>

<style scoped>
.slide-fade-enter-active { transition: all .25s ease; }
.slide-fade-leave-active { transition: all .2s ease; }
.slide-fade-enter-from, .slide-fade-leave-to { transform: translateY(-8px); opacity: 0; }
</style>
