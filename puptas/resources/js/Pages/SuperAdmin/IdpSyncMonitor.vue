<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageContainer from '@/Components/PageContainer.vue';
import axios from 'axios';

const loading = ref(true);
const error = ref(null);
const metrics = ref({
  stats: {
    total_syncs: 0,
    successful: 0,
    failed: 0,
    mfa_required: 0,
    success_rate: 0,
    synced_students: 0,
    unsynced_students: 0,
    hourly_activity: []
  },
  recent_activity: [],
  failure_breakdown: {},
  queue_status: {},
});
const selectedRange = ref('24h');
const showSyncModal = ref(false);
const syncModalError = ref(null);
const mfaCode = ref('');
const syncing = ref(false);
const retrying = ref(false);
const successMessage = ref('');

const openSyncModal = () => {
  mfaCode.value = '';
  syncModalError.value = null;
  showSyncModal.value = true;
};

const timeRanges = [
  { value: '24h', label: 'Last 24 Hours' },
  { value: '7d', label: 'Last 7 Days' },
  { value: '30d', label: 'Last 30 Days' },
  { value: 'all', label: 'All Time' },
];

const fetchMetrics = async () => {
  loading.value = true;
  error.value = null;
  
  try {
    const response = await axios.get('/api/super-admin/idp-sync-monitor/metrics', {
      params: { range: selectedRange.value }
    });
    
    if (response.data.success) {
      metrics.value = response.data.data;
    } else {
      error.value = response.data.message || 'Failed to load metrics';
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'An error occurred while fetching metrics';
    console.error('Error fetching metrics:', err);
  } finally {
    loading.value = false;
  }
};

const changeTimeRange = (range) => {
  if (range === selectedRange.value) return;
  selectedRange.value = range;
  loading.value = true;
  fetchMetrics();
};

const refreshData = () => {
  fetchMetrics();
};

const triggerSync = async () => {
  syncing.value = true;
  syncModalError.value = null;
  error.value = null;
  
  try {
    const response = await axios.post('/api/super-admin/idp-sync-monitor/trigger-sync', {
      mfa_code: mfaCode.value || null
    });
    
    if (response.data.success) {
      showSuccessMessage('Sync verified and jobs queued successfully!');
      showSyncModal.value = false;
      mfaCode.value = '';
      syncModalError.value = null;
      setTimeout(() => fetchMetrics(), 2000);
    } else {
      syncModalError.value = response.data.message || 'Failed to trigger sync';
    }
  } catch (err) {
    syncModalError.value = err.response?.data?.message || 'An error occurred while triggering sync';
    console.error('Error triggering sync:', err);
  } finally {
    syncing.value = false;
  }
};

const retryFailedJobs = async () => {
  if (!confirm('Are you sure you want to retry all failed jobs?')) {
    return;
  }

  retrying.value = true;
  error.value = null;
  
  try {
    const response = await axios.post('/api/super-admin/idp-sync-monitor/retry-failed-jobs');
    
    if (response.data.success) {
      showSuccessMessage('Failed jobs have been queued for retry!');
      setTimeout(() => fetchMetrics(), 2000);
    } else {
      error.value = response.data.message || 'Failed to retry jobs';
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'An error occurred while retrying jobs';
    console.error('Error retrying jobs:', err);
  } finally {
    retrying.value = false;
  }
};

const showSuccessMessage = (message) => {
  successMessage.value = message;
  setTimeout(() => {
    successMessage.value = '';
  }, 3000);
};

const getStatusBadgeClass = (action) => {
  const baseClass = 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full';
  
  switch (action) {
    case 'UPDATE':
      return `${baseClass} bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300`;
    case 'FAILED':
      return `${baseClass} bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300`;
    case 'ATTEMPTED':
      return `${baseClass} bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300`;
    case 'COMMAND':
      return `${baseClass} bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300`;
    default:
      return `${baseClass} bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300`;
  }
};

const formatErrorType = (type) => {
  return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

onMounted(() => {
  fetchMetrics();
});
</script>

<template>
  <Head title="IDP Sync Monitor" />

  <SuperAdminLayout>
    <PageContainer>
      <!-- Header -->
      <div class="mb-8 flex items-center gap-4">
        <div class="p-3 bg-[#9E122C]/10 rounded-xl">
          <svg class="h-6 w-6 text-[#9E122C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">IDP Sync Monitor</h1>
          <p class="text-gray-600 dark:text-gray-400 mt-1 text-sm">
            Monitor and manage IDP account type synchronization for enrolled students
          </p>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <p class="mt-2 text-gray-600">Loading metrics...</p>
      </div>

      <!-- Error State -->
      <div v-if="error && !loading" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4 mb-6">
        <div class="flex items-start">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-red-400 dark:text-red-300" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3 flex-1">
            <h3 class="text-sm font-medium text-red-800 dark:text-red-300">Error</h3>
            <p class="mt-1 text-sm text-red-700 dark:text-red-400">{{ error }}</p>
          </div>
          <button @click="error = null" class="ml-3 text-red-400 hover:text-red-600">
            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Main Content -->
      <div v-if="!loading">
        <!-- Time Range Filter -->
        <div class="mb-6 flex justify-between items-center">
          <div class="flex space-x-2">
            <button
              v-for="range in timeRanges"
              :key="range.value"
              @click="changeTimeRange(range.value)"
              :class="[
                'px-4 py-2 rounded-xl text-sm font-medium transition-all',
                selectedRange === range.value
                  ? 'bg-[#9E122C] text-white shadow-lg'
                  : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700'
              ]"
            >
              {{ range.label }}
            </button>
          </div>
          <button
            @click="refreshData"
            class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all flex items-center"
          >
            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Refresh
          </button>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <!-- Total Syncs -->
          <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300">
            <div class="flex items-start justify-between">
              <div>
                <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">Total Syncs</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ metrics.stats.total_syncs || 0 }}</p>
              </div>
              <div class="p-3 rounded-lg bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-300">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
              </div>
            </div>
          </div>

          <!-- Success Rate -->
          <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300">
            <div class="flex items-start justify-between">
              <div>
                <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">Success Rate</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ metrics.stats.success_rate || 0 }}%</p>
              </div>
              <div class="p-3 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-300">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
            </div>
          </div>

          <!-- Failed Syncs -->
          <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300">
            <div class="flex items-start justify-between">
              <div>
                <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">Failed Syncs</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ metrics.stats.failed || 0 }}</p>
              </div>
              <div class="p-3 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-300">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </div>
            </div>
          </div>

          <!-- Unsynced Students -->
          <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300">
            <div class="flex items-start justify-between">
              <div>
                <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">Unsynced Students</p>
                <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ metrics.stats.unsynced_students || 0 }}</p>
              </div>
              <div class="p-3 rounded-lg bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-300">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="mb-8 flex space-x-4">
          <button
            @click="openSyncModal"
            class="px-6 py-3 bg-[#9E122C] hover:bg-red-900 text-white rounded-xl font-medium shadow-lg transition-all transform hover:scale-105 flex items-center"
          >
            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Trigger Manual Sync
          </button>
          
          <button
            v-if="metrics.queue_status.failed_jobs > 0"
            @click="retryFailedJobs"
            :disabled="retrying"
            class="px-6 py-3 bg-amber-600 text-white rounded-xl font-medium hover:bg-amber-700 shadow-lg transition-all transform hover:scale-105 flex items-center disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            {{ retrying ? 'Retrying...' : `Retry Failed Jobs (${metrics.queue_status.failed_jobs})` }}
          </button>
        </div>

        <!-- Queue Status -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-8 border border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Queue Status</h2>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400">Connection</p>
              <p class="mt-1 text-lg font-medium text-gray-900 dark:text-white">{{ metrics.queue_status.queue_connection || 'Unknown' }}</p>
            </div>
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400">Pending Jobs</p>
              <p class="mt-1 text-lg font-medium text-gray-900 dark:text-white">
                {{ metrics.queue_status.pending_jobs !== null ? metrics.queue_status.pending_jobs : 'N/A' }}
              </p>
              <p v-if="metrics.queue_status.pending_jobs === null" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Not available for {{ metrics.queue_status.queue_connection }} driver
              </p>
            </div>
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400">Failed Jobs</p>
              <p class="mt-1 text-lg font-medium text-red-600 dark:text-red-400">{{ metrics.queue_status.failed_jobs || 0 }}</p>
            </div>
          </div>
          <div
            id="idp-sudo-status"
            :class="[
              'mt-6 rounded-xl px-4 py-3 text-sm flex items-start gap-2',
              metrics.queue_status.sudo_active
                ? 'bg-green-50 text-green-800 dark:bg-green-900/20 dark:text-green-300'
                : 'bg-amber-50 text-amber-800 dark:bg-amber-900/20 dark:text-amber-300'
            ]"
          >
            <span class="font-semibold whitespace-nowrap">
              {{ metrics.queue_status.sudo_active ? '🟢 Auto-sync active' : '🟡 Auto-sync paused' }}
            </span>
            <span v-if="metrics.queue_status.sudo_active">
              An MFA-verified admin session is cached. Enrollments tagged by Record Staff will sync to the IDP automatically.
            </span>
            <span v-else>
              No MFA-verified admin session. Click "Trigger Manual Sync" and enter your MFA code once to enable automatic syncing.
            </span>
          </div>
        </div>

        <!-- Failure Breakdown -->
        <div v-if="Object.values(metrics.failure_breakdown || {}).some(v => v > 0)" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-8 border border-gray-200 dark:border-gray-700">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Failure Breakdown</h2>
          <div class="space-y-3">
            <div v-for="(count, type) in metrics.failure_breakdown" :key="type" v-show="count > 0" class="flex items-center justify-between">
              <span class="text-sm text-gray-600 dark:text-gray-400">{{ formatErrorType(type) }}</span>
              <span class="text-sm font-medium text-gray-900 dark:text-white">{{ count }}</span>
            </div>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden border border-gray-200 dark:border-gray-700">
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Activity</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-900/50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Status
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Details
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Time
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-if="metrics.recent_activity.length === 0">
                  <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    No recent activity found
                  </td>
                </tr>
                <tr v-for="activity in metrics.recent_activity" :key="activity.id">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span :class="getStatusBadgeClass(activity.action)">
                      {{ activity.action }}
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <p class="text-sm text-gray-900 dark:text-gray-300 line-clamp-2">{{ activity.details }}</p>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    {{ activity.created_at_human }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </PageContainer>
  </SuperAdminLayout>

  <!-- Sync Modal -->
  <div v-if="showSyncModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Trigger Manual Sync</h3>

      <!-- Error Banner inside modal -->
      <div v-if="syncModalError" class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
        <div class="flex">
          <svg class="h-5 w-5 text-red-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
          <div class="text-xs text-red-700 dark:text-red-300 leading-relaxed break-words">
            <strong>Sync Failed:</strong> {{ syncModalError }}
          </div>
        </div>
      </div>
      
      <!-- Info Banner -->
      <div class="mb-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
        <div class="flex">
          <svg class="h-5 w-5 text-blue-400 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
          </svg>
          <p class="text-xs text-blue-700 dark:text-blue-300">
            <strong>Note:</strong> This requires an IDP admin access token. Make sure you're logged in via IDP or have IDP_ADMIN_ACCESS_TOKEN configured.
          </p>
        </div>
      </div>
      
      <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
        This will queue jobs to sync all officially enrolled students who haven't been synced yet to the IDP.
      </p>
      
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          MFA Code (Optional)
        </label>
        <input
          v-model="mfaCode"
          type="text"
          maxlength="8"
          placeholder="123456"
          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-blue-500 focus:border-blue-500"
        />
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
          Enter your MFA code if your IDP account requires it
        </p>
      </div>

      <div class="flex justify-end space-x-3">
        <button
          @click="showSyncModal = false"
          :disabled="syncing"
          class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50"
        >
          Cancel
        </button>
        <button
          @click="triggerSync"
          :disabled="syncing"
          class="px-4 py-2 bg-[#9E122C] text-white rounded-xl text-sm font-medium hover:bg-red-900 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ syncing ? 'Syncing...' : 'Start Sync' }}
        </button>
      </div>
    </div>
  </div>

  <!-- Success Toast -->
  <div v-if="successMessage" class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
    {{ successMessage }}
  </div>
</template>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
