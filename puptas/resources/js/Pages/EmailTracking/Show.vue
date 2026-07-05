<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  operation: Object,
  logs: Object,
});

// --- State ---
const selectedIds = ref([]);
const filterStatus = ref(new URL(window.location.href).searchParams.get('status') || '');
const searchQuery = ref(new URL(window.location.href).searchParams.get('search') || '');
const retryingSelected = ref(false);
const retryingAll = ref(false);
const feedbackMessage = ref('');
const feedbackType = ref('success');

let searchDebounceTimer = null;

// --- Computed ---
const hasFailedLogs = computed(() => {
  return props.logs.data.some(log => log.status === 'failed');
});

const allFailedSelected = computed(() => {
  const failedLogs = props.logs.data.filter(log => log.status === 'failed');
  if (failedLogs.length === 0) return false;
  return failedLogs.every(log => selectedIds.value.includes(log.id));
});

const visiblePages = computed(() => {
  const current = props.logs.current_page;
  const last = props.logs.last_page;

  if (last <= 7) {
    return Array.from({ length: last }, (_, i) => i + 1);
  }

  const pages = [];

  if (current <= 4) {
    for (let i = 1; i <= 5; i++) pages.push(i);
    pages.push('...');
    pages.push(last);
  } else if (current >= last - 3) {
    pages.push(1);
    pages.push('...');
    for (let i = last - 4; i <= last; i++) pages.push(i);
  } else {
    pages.push(1);
    pages.push('...');
    for (let i = current - 1; i <= current + 1; i++) pages.push(i);
    pages.push('...');
    pages.push(last);
  }

  return pages;
});

// --- Watchers ---
watch(searchQuery, (newVal) => {
  clearTimeout(searchDebounceTimer);
  searchDebounceTimer = setTimeout(() => {
    if (newVal.length === 0 || newVal.length >= 2) {
      applyFilters();
    }
  }, 300);
});

// --- Methods ---
function applyFilters() {
  const params = {};
  if (filterStatus.value) {
    params.status = filterStatus.value;
  }
  if (searchQuery.value && searchQuery.value.length >= 2) {
    params.search = searchQuery.value;
  }

  router.get(`/admin/email-tracking/${props.operation.id}`, params, {
    preserveState: true,
    preserveScroll: true,
  });
}

function toggleSelectAll(event) {
  const failedLogs = props.logs.data.filter(log => log.status === 'failed');
  if (event.target.checked) {
    const failedIds = failedLogs.map(log => log.id);
    selectedIds.value = [...new Set([...selectedIds.value, ...failedIds])];
  } else {
    const failedIds = failedLogs.map(log => log.id);
    selectedIds.value = selectedIds.value.filter(id => !failedIds.includes(id));
  }
}

async function retrySelected() {
  if (selectedIds.value.length === 0) return;
  retryingSelected.value = true;
  feedbackMessage.value = '';

  try {
    const response = await axios.post('/admin/email-tracking/retry-selected', {
      email_log_ids: selectedIds.value,
    });
    feedbackMessage.value = `Successfully queued ${response.data.retried_count} email(s) for retry.`;
    feedbackType.value = 'success';
    selectedIds.value = [];
    router.reload({ preserveScroll: true });
  } catch (error) {
    feedbackMessage.value = error.response?.data?.message || 'Failed to retry selected emails. Please try again.';
    feedbackType.value = 'error';
  } finally {
    retryingSelected.value = false;
    clearFeedbackAfterDelay();
  }
}

async function retryAllFailed() {
  retryingAll.value = true;
  feedbackMessage.value = '';

  try {
    const response = await axios.post(`/admin/email-tracking/${props.operation.id}/retry-all`);
    feedbackMessage.value = `Successfully queued ${response.data.retried_count} email(s) for retry.`;
    feedbackType.value = 'success';
    selectedIds.value = [];
    router.reload({ preserveScroll: true });
  } catch (error) {
    feedbackMessage.value = error.response?.data?.message || 'Failed to retry all failed emails. Please try again.';
    feedbackType.value = 'error';
  } finally {
    retryingAll.value = false;
    clearFeedbackAfterDelay();
  }
}

function clearFeedbackAfterDelay() {
  setTimeout(() => {
    feedbackMessage.value = '';
  }, 5000);
}

function getPageUrl(page) {
  const url = new URL(window.location.href);
  url.searchParams.set('page', page);
  return url.pathname + url.search;
}

// --- Formatting Helpers ---
function formatEmailType(type) {
  const labels = {
    pupcet_result: 'PUPCET Result',
    sar_form: 'SAR Form',
    waitlisted: 'Waitlisted',
    congratulations: 'Congratulations',
    user_created: 'User Created',
  };
  return labels[type] || type;
}

function getEmailTypeBadgeClass(type) {
  const classes = {
    pupcet_result: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
    sar_form: 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300',
    waitlisted: 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300',
    congratulations: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
    user_created: 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
  };
  return classes[type] || 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300';
}

function formatStatus(status) {
  const labels = {
    in_progress: 'In Progress',
    completed: 'Completed',
    completed_with_failures: 'Completed with Failures',
  };
  return labels[status] || status;
}

function getStatusBadgeClass(status) {
  const classes = {
    in_progress: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300',
    completed: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
    completed_with_failures: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
  };
  return classes[status] || 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300';
}

function getLogStatusBadgeClass(status) {
  const classes = {
    sent: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
    failed: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
    pending: 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
  };
  return classes[status] || 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300';
}

function capitalizeFirst(str) {
  if (!str) return '';
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function getLogTimestamp(log) {
  if (log.status === 'sent' && log.sent_at) {
    return formatDate(log.sent_at);
  }
  if (log.status === 'failed' && log.failed_at) {
    return formatDate(log.failed_at);
  }
  return formatDate(log.created_at);
}

function formatDate(dateString) {
  if (!dateString) return '—';
  return new Date(dateString).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}
</script>

<template>
  <Head :title="`Email Tracking - ${formatEmailType(operation.email_type)}`" />
  <AppLayout>
    <!-- Back Link -->
    <div class="mb-4">
      <Link
        href="/admin/email-tracking"
        class="inline-flex items-center text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition"
      >
        <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back to Email Tracking
      </Link>
    </div>

    <!-- Operation Summary Header -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 p-6 mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4">
        <div class="flex items-center space-x-3">
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Operation Details</h1>
          <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', getEmailTypeBadgeClass(operation.email_type)]">
            {{ formatEmailType(operation.email_type) }}
          </span>
          <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', getStatusBadgeClass(operation.status)]">
            {{ formatStatus(operation.status) }}
          </span>
        </div>
      </div>

      <!-- Counts & Timestamps -->
      <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4">
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
          <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Total</p>
          <p class="text-lg font-bold text-gray-900 dark:text-white">{{ operation.total_count }}</p>
        </div>
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
          <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Sent</p>
          <p class="text-lg font-bold text-green-600 dark:text-green-400">{{ operation.sent_count }}</p>
        </div>
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
          <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Failed</p>
          <p class="text-lg font-bold text-red-600 dark:text-red-400">{{ operation.failed_count }}</p>
        </div>
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
          <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Pending</p>
          <p class="text-lg font-bold text-gray-500 dark:text-gray-400">{{ operation.pending_count }}</p>
        </div>
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
          <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Started At</p>
          <p class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDate(operation.started_at) }}</p>
        </div>
        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
          <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Completed At</p>
          <p class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDate(operation.completed_at) }}</p>
        </div>
      </div>
    </div>

    <!-- Filters, Search, and Retry Actions -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <!-- Left: Filter & Search -->
          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
            <!-- Status Filter Dropdown -->
            <select
              v-model="filterStatus"
              @change="applyFilters"
              class="block w-full sm:w-auto rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm focus:ring-[#9E122C] focus:border-[#9E122C]"
            >
              <option value="">All</option>
              <option value="sent">Sent</option>
              <option value="failed">Failed</option>
              <option value="pending">Pending</option>
            </select>

            <!-- Search Input -->
            <div class="relative w-full sm:w-64">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search name or email..."
                class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm pl-9 focus:ring-[#9E122C] focus:border-[#9E122C]"
              />
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
          </div>

          <!-- Right: Retry Buttons -->
          <div class="flex items-center gap-2">
            <button
              v-if="operation.failed_count > 0"
              @click="retryAllFailed"
              :disabled="retryingAll"
              class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <svg v-if="retryingAll" class="animate-spin -ml-0.5 mr-1.5 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              Retry All Failed
            </button>
            <button
              @click="retrySelected"
              :disabled="selectedIds.length === 0 || retryingSelected"
              class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-[#9E122C] hover:bg-[#7d0e23] rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <svg v-if="retryingSelected" class="animate-spin -ml-0.5 mr-1.5 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
              </svg>
              Retry Selected ({{ selectedIds.length }})
            </button>
          </div>
        </div>
      </div>

      <!-- Success/Error Feedback -->
      <div v-if="feedbackMessage" class="px-6 py-3" :class="feedbackType === 'success' ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'">
        <p :class="feedbackType === 'success' ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'" class="text-sm font-medium">
          {{ feedbackMessage }}
        </p>
      </div>

      <!-- Email Logs Table -->
      <div class="overflow-x-auto">
        <table v-if="logs.data.length" class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
            <tr>
              <th class="px-6 py-3 text-left w-10">
                <input
                  v-if="hasFailedLogs"
                  type="checkbox"
                  :checked="allFailedSelected"
                  @change="toggleSelectAll"
                  class="rounded border-gray-300 dark:border-gray-600 text-[#9E122C] focus:ring-[#9E122C]"
                />
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Recipient Name
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Email
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Status
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Timestamp
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr
              v-for="log in logs.data"
              :key="log.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
            >
              <td class="px-6 py-4">
                <input
                  v-if="log.status === 'failed'"
                  type="checkbox"
                  :value="log.id"
                  v-model="selectedIds"
                  class="rounded border-gray-300 dark:border-gray-600 text-[#9E122C] focus:ring-[#9E122C]"
                />
              </td>
              <td class="px-6 py-4 text-gray-900 dark:text-gray-200">
                {{ log.recipient_name || '—' }}
              </td>
              <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                {{ log.recipient_email }}
              </td>
              <td class="px-6 py-4">
                <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', getLogStatusBadgeClass(log.status)]">
                  {{ capitalizeFirst(log.status) }}
                </span>
              </td>
              <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs whitespace-nowrap">
                {{ getLogTimestamp(log) }}
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Empty State -->
        <div v-else class="text-center py-16">
          <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No email logs found</h3>
          <p class="text-gray-500 dark:text-gray-400">
            No results match your current filter or search criteria.
          </p>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="logs.last_page > 1" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700 dark:text-gray-400">
                  <span v-if="!logs.total || logs.total === 0">
                      Showing 0 to 0 of 0 results
                  </span>
                  <span v-else>
                      Showing {{ logs.from || 0 }} 
                      to {{ logs.to || 0 }} 
                      of {{ logs.total }} results
                  </span>
              </div>
              <div class="flex items-center space-x-2">
                  <button
                      :disabled="logs.current_page === 1"
                      @click.prevent="router.visit(getPageUrl(logs.current_page - 1))"
                      class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                  >
                      <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                      </svg>
                      Previous
                  </button>
                  <div class="flex items-center space-x-2 mx-2 text-sm text-gray-700 dark:text-gray-300">
                      <span>Page</span>
                      <input
                          type="number"
                          :value="logs.current_page"
                          min="1"
                          :max="logs.last_page || 1"
                          @change="router.visit(getPageUrl(Math.max(1, Math.min($event.target.value, logs.last_page || 1))))"
                          class="w-16 px-2 py-1 text-center border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent font-medium text-sm"
                      />
                      <span>of <span class="font-semibold">{{ logs.last_page || 1 }}</span></span>
                  </div>
                  <button
                      :disabled="logs.current_page === logs.last_page"
                      @click.prevent="router.visit(getPageUrl(logs.current_page + 1))"
                      class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                  >
                      Next
                      <svg class="h-5 w-5 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                      </svg>
                  </button>
              </div>
          </div>
      </div>
    </div>
  </AppLayout>
</template>
