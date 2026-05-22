<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    bulkOperationId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['dismissed']);

const progress = ref(null);
const isLoading = ref(true);
const consecutiveFailures = ref(0);
const showConnectionError = ref(false);
let pollInterval = null;

const percentage = computed(() => {
    if (!progress.value || progress.value.total_count === 0) return 0;
    return Math.round(
        ((progress.value.sent_count + progress.value.failed_count) / progress.value.total_count) * 100
    );
});

const isTerminal = computed(() => {
    if (!progress.value) return false;
    return ['completed', 'completed_with_failures'].includes(progress.value.status);
});

const pendingCount = computed(() => {
    if (!progress.value) return 0;
    return progress.value.pending_count;
});

async function fetchProgress() {
    try {
        const response = await axios.get(`/admin/email-tracking/${props.bulkOperationId}/progress`);
        progress.value = response.data;
        isLoading.value = false;
        consecutiveFailures.value = 0;
        showConnectionError.value = false;

        if (isTerminal.value) {
            stopPolling();
        }
    } catch (error) {
        consecutiveFailures.value++;
        if (consecutiveFailures.value >= 3) {
            showConnectionError.value = true;
        }
        isLoading.value = false;
    }
}

function startPolling() {
    fetchProgress();
    pollInterval = setInterval(fetchProgress, 3000);
}

function stopPolling() {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

function dismiss() {
    stopPolling();
    emit('dismissed');
}

onMounted(() => {
    startPolling();
});

onUnmounted(() => {
    stopPolling();
});
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <!-- Loading state -->
        <div v-if="isLoading && !progress" class="flex items-center space-x-2">
            <svg class="h-5 w-5 animate-spin text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span class="text-sm text-gray-500 dark:text-gray-400">Loading progress...</span>
        </div>

        <!-- Progress content -->
        <div v-else-if="progress">
            <!-- Header with dismiss button -->
            <div class="mb-3 flex items-center justify-between">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Email Progress
                    <span v-if="isTerminal" class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="{
                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': progress.status === 'completed',
                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': progress.status === 'completed_with_failures',
                        }"
                    >
                        {{ progress.status === 'completed' ? 'Completed' : 'Completed with failures' }}
                    </span>
                </h4>
                <button
                    v-if="isTerminal"
                    type="button"
                    class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                    aria-label="Dismiss"
                    @click="dismiss"
                >
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Progress bar -->
            <div class="mb-2 h-4 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div
                    class="flex h-full items-center justify-center rounded-full text-xs font-medium text-white transition-all duration-500 ease-out"
                    :class="{
                        'bg-green-500': progress.failed_count === 0,
                        'bg-yellow-500': progress.failed_count > 0 && progress.sent_count > 0,
                        'bg-red-500': progress.sent_count === 0 && progress.failed_count > 0,
                        'bg-indigo-500': !isTerminal && progress.failed_count === 0,
                    }"
                    :style="{ width: percentage + '%' }"
                >
                    <span v-if="percentage > 10">{{ percentage }}%</span>
                </div>
            </div>
            <div v-if="percentage <= 10" class="mb-2 text-center text-xs font-medium text-gray-600 dark:text-gray-400">
                {{ percentage }}%
            </div>

            <!-- Color-coded counts -->
            <div class="flex items-center space-x-4 text-sm">
                <span class="flex items-center space-x-1">
                    <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
                    <span class="text-green-700 dark:text-green-400">Sent: {{ progress.sent_count }}</span>
                </span>
                <span class="flex items-center space-x-1">
                    <span class="inline-block h-2 w-2 rounded-full bg-red-500"></span>
                    <span class="text-red-700 dark:text-red-400">Failed: {{ progress.failed_count }}</span>
                </span>
                <span class="flex items-center space-x-1">
                    <span class="inline-block h-2 w-2 rounded-full bg-gray-400"></span>
                    <span class="text-gray-600 dark:text-gray-400">Pending: {{ pendingCount }}</span>
                </span>
            </div>

            <!-- Completion summary -->
            <div v-if="isTerminal" class="mt-3 rounded-md border border-gray-100 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-700">
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    <span class="font-medium">Summary:</span>
                    {{ progress.sent_count }} of {{ progress.total_count }} emails sent successfully.
                    <span v-if="progress.failed_count > 0" class="text-red-600 dark:text-red-400">
                        {{ progress.failed_count }} failed.
                    </span>
                </p>
                <p v-if="progress.completed_at" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Completed at: {{ new Date(progress.completed_at).toLocaleString() }}
                </p>
            </div>

            <!-- Connection error -->
            <div v-if="showConnectionError" class="mt-3 rounded-md border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                <div class="flex items-center space-x-2">
                    <svg class="h-4 w-4 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    <span class="text-sm text-red-700 dark:text-red-400">
                        Connection error. Retrying...
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
