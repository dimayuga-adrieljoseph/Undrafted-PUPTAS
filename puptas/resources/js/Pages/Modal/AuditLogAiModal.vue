<script setup>
import { ref, watch, onMounted } from 'vue';
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import axios from 'axios';
import { marked } from 'marked';

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(['close']);

const activeTab = ref('new'); // 'new' or 'history'
const startDate = ref('');
const endDate = ref('');
const isAnalyzing = ref(false);
const summary = ref('');
const error = ref('');

const historyList = ref([]);
const isLoadingHistory = ref(false);

watch(() => props.show, (newVal) => {
    if (newVal) {
        startDate.value = '';
        endDate.value = '';
        summary.value = '';
        error.value = '';
        activeTab.value = 'new';
        
        const today = new Date().toISOString().split('T')[0];
        startDate.value = today;
        endDate.value = today;

        fetchHistory();
    }
});

const fetchHistory = async () => {
    isLoadingHistory.value = true;
    try {
        const response = await axios.get(route('audit-logs.history'));
        historyList.value = response.data.history;
    } catch (err) {
        console.error('Failed to fetch history', err);
    } finally {
        isLoadingHistory.value = false;
    }
};

const close = () => {
    emit('close');
};

const analyzeLogs = async () => {
    if (!startDate.value || !endDate.value) {
        error.value = 'Please select both start and end dates.';
        return;
    }
    
    const start = new Date(startDate.value);
    const end = new Date(endDate.value);

    if (end < start) {
        error.value = 'End date cannot be before start date.';
        return;
    }

    const diffTime = Math.abs(end - start);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)); 
    if (diffDays > 31) {
        error.value = 'Date range cannot exceed 31 days to ensure system availability.';
        return;
    }

    isAnalyzing.value = true;
    error.value = '';
    summary.value = '';

    try {
        const response = await axios.post(route('audit-logs.analyze'), {
            start_date: startDate.value,
            end_date: endDate.value
        });
        
        summary.value = marked(response.data.summary || 'No summary returned.');
        fetchHistory(); // Refresh history
    } catch (err) {
        console.error('AI Analysis Error:', err);
        error.value = err.response?.data?.summary || 'An error occurred while analyzing logs.';
    } finally {
        isAnalyzing.value = false;
    }
};

const viewHistoryItem = (item) => {
    summary.value = marked(item.summary);
    startDate.value = item.start_date;
    endDate.value = item.end_date;
    activeTab.value = 'new'; // Switch to view mode effectively
};

const deleteHistoryItem = async (id) => {
    if (!confirm("Are you sure you want to delete this analytics history?")) return;
    try {
        await axios.delete(route('audit-logs.history.delete', id));
        fetchHistory();
    } catch (err) {
        console.error('Failed to delete history', err);
    }
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString();
};
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            
            <transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="show" class="fixed inset-0 bg-gray-500 dark:bg-gray-900 bg-opacity-75 dark:bg-opacity-80 transition-opacity" @click="close" aria-hidden="true"></div>
            </transition>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
                <div v-if="show" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full border border-gray-200 dark:border-gray-700">
                    
                    <div class="bg-[#9E122C] px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <FontAwesomeIcon icon="code" class="text-white h-5 w-5" />
                                <h3 class="text-lg font-bold text-white">AI Audit Log Analytics</h3>
                            </div>
                            <button @click="close" class="text-white/80 hover:text-white transition-colors focus:outline-none">
                                <FontAwesomeIcon icon="times" class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="flex -mb-px px-6" aria-label="Tabs">
                            <button 
                                @click="activeTab = 'new'; summary=''" 
                                :class="[
                                    activeTab === 'new' ? 'border-[#9E122C] text-[#9E122C]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300',
                                    'w-1/2 py-3 px-1 text-center border-b-2 font-medium text-sm transition-colors'
                                ]"
                            >
                                New Analysis
                            </button>
                            <button 
                                @click="activeTab = 'history'" 
                                :class="[
                                    activeTab === 'history' ? 'border-[#9E122C] text-[#9E122C]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300',
                                    'w-1/2 py-3 px-1 text-center border-b-2 font-medium text-sm transition-colors'
                                ]"
                            >
                                Analytics History
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
                        <template v-if="activeTab === 'new'">
                            <div class="bg-red-50 dark:bg-red-900/10 rounded-xl p-4 mb-6 border border-red-100 dark:border-red-900/30">
                                <h4 class="text-sm font-semibold text-[#9E122C] dark:text-red-400 mb-3 flex items-center gap-2">
                                    <FontAwesomeIcon icon="filter" class="w-4 h-4" /> Date Range Filter
                                </h4>
                                <div class="flex flex-col sm:flex-row gap-4">
                                    <div class="flex-1">
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">From Date</label>
                                        <input type="date" v-model="startDate" class="w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-[#9E122C] focus:border-[#9E122C] sm:text-sm">
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">To Date</label>
                                        <input type="date" v-model="endDate" class="w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-[#9E122C] focus:border-[#9E122C] sm:text-sm">
                                    </div>
                                    <div class="flex items-end">
                                        <button 
                                            @click="analyzeLogs" 
                                            :disabled="isAnalyzing"
                                            class="w-full sm:w-auto px-6 py-2 bg-[#9E122C] hover:bg-red-800 text-white font-medium rounded-lg shadow-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                                        >
                                            <FontAwesomeIcon v-if="isAnalyzing" icon="circle-notch" class="animate-spin w-4 h-4" />
                                            <FontAwesomeIcon v-else icon="bolt" class="w-4 h-4" />
                                            {{ isAnalyzing ? 'Analyzing...' : 'Analyze Logs' }}
                                        </button>
                                    </div>
                                </div>
                                <div v-if="error" class="mt-3 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 p-2 rounded-md">
                                    {{ error }}
                                </div>
                                <div class="mt-3 text-xs text-[#9E122C]/70 dark:text-red-400/70 flex items-start gap-1">
                                    <FontAwesomeIcon icon="shield-alt" class="mt-0.5" />
                                    <p><strong>Integrity & Confidentiality Notice:</strong> Sensitive PII (emails, IPs) are automatically redacted before analysis. AI summaries may occasionally hallucinate. Always verify critical incidents in raw system logs.</p>
                                </div>
                            </div>

                            <!-- Results Area -->
                            <div class="relative min-h-[200px] border border-gray-200 dark:border-gray-700 rounded-xl bg-gray-50 dark:bg-gray-900 overflow-hidden">
                                <!-- Loading State -->
                                <div v-if="isAnalyzing" class="absolute inset-0 bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm flex flex-col items-center justify-center z-10">
                                    <div class="relative w-16 h-16">
                                        <div class="absolute inset-0 rounded-full border-t-2 border-[#9E122C] animate-spin"></div>
                                        <div class="absolute inset-2 rounded-full border-r-2 border-red-500 animate-spin" style="animation-direction: reverse; animation-duration: 1.5s;"></div>
                                    </div>
                                    <p class="mt-4 text-sm font-medium text-[#9E122C] dark:text-red-400 animate-pulse">DeepSeek AI is analyzing logs...</p>
                                </div>

                                <!-- Content -->
                                <div v-if="summary" class="p-6 prose prose-red dark:prose-invert max-w-none max-h-[500px] overflow-y-auto simple-scrollbar">
                                    <div v-html="summary"></div>
                                </div>
                                
                                <div v-else-if="!isAnalyzing" class="h-full min-h-[200px] flex flex-col items-center justify-center text-gray-400 dark:text-gray-500 p-6 text-center">
                                    <FontAwesomeIcon icon="network-wired" class="w-12 h-12 mb-3 opacity-50" />
                                    <p>Select a date range and click Analyze to generate AI insights.</p>
                                </div>
                            </div>
                        </template>

                        <!-- History Tab -->
                        <template v-if="activeTab === 'history'">
                            <div class="max-h-[500px] overflow-y-auto simple-scrollbar pr-2">
                                <div v-if="isLoadingHistory" class="text-center py-8 text-gray-500">
                                    <FontAwesomeIcon icon="circle-notch" class="animate-spin w-6 h-6 mb-2" />
                                    <p>Loading history...</p>
                                </div>
                                <div v-else-if="historyList.length === 0" class="text-center py-8 text-gray-500">
                                    <p>No analytics history found.</p>
                                </div>
                                <div v-else class="space-y-4">
                                    <div v-for="item in historyList" :key="item.id" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm hover:shadow transition-shadow">
                                        <div class="flex justify-between items-start mb-2">
                                            <div>
                                                <h5 class="font-semibold text-gray-900 dark:text-white">Analysis: {{ formatDate(item.start_date) }} to {{ formatDate(item.end_date) }}</h5>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Run on {{ new Date(item.created_at).toLocaleString() }} by {{ item.user ? (item.user.firstname + ' ' + item.user.lastname).trim() || item.user.email : 'System' }}</p>
                                            </div>
                                            <div class="flex gap-2">
                                                <button @click="viewHistoryItem(item)" class="text-[#9E122C] hover:text-red-800 text-sm font-medium px-2 py-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                                    View
                                                </button>
                                                <button @click="deleteHistoryItem(item.id)" class="text-red-600 hover:text-red-800 text-sm font-medium px-2 py-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-800/80 px-6 py-4 flex justify-end rounded-b-2xl border-t border-gray-200 dark:border-gray-700">
                        <button type="button" class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors focus:outline-none" @click="close">
                            Close
                        </button>
                    </div>
                </div>
            </transition>
        </div>
    </div>
</template>

<style scoped>
.simple-scrollbar::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.simple-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.simple-scrollbar::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 10px;
}
.dark .simple-scrollbar::-webkit-scrollbar-thumb {
    background: #4b5563;
}
.simple-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

/* Base markdown styles to ensure tables and lists look good */
:deep(.prose) {
    font-size: 0.95rem;
    line-height: 1.6;
}
:deep(.prose h3), :deep(.prose h4) {
    margin-top: 1.5em;
    margin-bottom: 0.5em;
}
:deep(.prose ul) {
    margin-top: 0.5em;
    margin-bottom: 1em;
}
:deep(.prose li) {
    margin-top: 0.25em;
    margin-bottom: 0.25em;
}
</style>
