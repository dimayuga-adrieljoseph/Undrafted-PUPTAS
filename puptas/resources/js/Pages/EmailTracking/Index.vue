<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  operations: Object,
});

/**
 * Navigate to the detail page for a bulk operation.
 */
const navigateToDetail = (id) => {
  router.visit(`/admin/email-tracking/${id}`);
};

/**
 * Format email_type for display.
 */
const formatEmailType = (type) => {
  const labels = {
    pupcet_result: 'PUPCET Result',
    sar_form: 'SAR Form',
    waitlisted: 'Waitlisted',
    congratulations: 'Congratulations',
    user_created: 'User Created',
  };
  return labels[type] || type;
};

/**
 * Get badge classes for email type.
 */
const getEmailTypeBadgeClass = (type) => {
  const classes = {
    pupcet_result: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
    sar_form: 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300',
    waitlisted: 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300',
    congratulations: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
    user_created: 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300',
  };
  return classes[type] || 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300';
};

/**
 * Format status for display.
 */
const formatStatus = (status) => {
  const labels = {
    in_progress: 'In Progress',
    completed: 'Completed',
    completed_with_failures: 'Completed with Failures',
  };
  return labels[status] || status;
};

/**
 * Get badge classes for status.
 */
const getStatusBadgeClass = (status) => {
  const classes = {
    in_progress: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300',
    completed: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
    completed_with_failures: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300',
  };
  return classes[status] || 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300';
};

/**
 * Format date for display.
 */
const formatDate = (dateString) => {
  if (!dateString) return '—';
  return new Date(dateString).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
};

/**
 * Compute visible page numbers for pagination.
 */
const visiblePages = computed(() => {
  const current = props.operations.current_page;
  const last = props.operations.last_page;

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

/**
 * Build page URL from page number.
 */
const getPageUrl = (page) => {
  const url = new URL(window.location.href);
  url.searchParams.set('page', page);
  return url.pathname + url.search;
};
</script>

<template>
  <Head title="Email Tracking" />
  <AppLayout>
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Email Tracking</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
        Monitor bulk email operations and delivery status
      </p>
    </div>

    <!-- Table Card -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
      <!-- Desktop Table -->
      <div class="overflow-x-auto">
        <table v-if="operations.data.length" class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Email Type
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Total
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Sent
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Failed
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Status
              </th>
              <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                Started At
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <tr
              v-for="operation in operations.data"
              :key="operation.id"
              @click="navigateToDetail(operation.id)"
              class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition cursor-pointer"
            >
              <td class="px-6 py-4">
                <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', getEmailTypeBadgeClass(operation.email_type)]">
                  {{ formatEmailType(operation.email_type) }}
                </span>
              </td>
              <td class="px-6 py-4 text-gray-900 dark:text-gray-200 font-medium">
                {{ operation.total_count }}
              </td>
              <td class="px-6 py-4 text-green-600 dark:text-green-400 font-medium">
                {{ operation.sent_count }}
              </td>
              <td class="px-6 py-4 text-red-600 dark:text-red-400 font-medium">
                {{ operation.failed_count }}
              </td>
              <td class="px-6 py-4">
                <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', getStatusBadgeClass(operation.status)]">
                  {{ formatStatus(operation.status) }}
                </span>
              </td>
              <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs whitespace-nowrap">
                {{ formatDate(operation.started_at) }}
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Empty State -->
        <div v-else class="text-center py-16">
          <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No email operations yet</h3>
          <p class="text-gray-500 dark:text-gray-400">
            Bulk email operations will appear here once you send emails to passers.
          </p>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="operations.last_page > 1" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
              <div class="text-sm text-gray-700 dark:text-gray-400">
                  <span v-if="!operations.total || operations.total === 0">
                      Showing 0 to 0 of 0 results
                  </span>
                  <span v-else>
                      Showing {{ operations.from || 0 }} 
                      to {{ operations.to || 0 }} 
                      of {{ operations.total }} results
                  </span>
              </div>
              <div class="flex items-center space-x-2">
                  <button
                      :disabled="operations.current_page === 1"
                      @click.prevent="router.visit(getPageUrl(operations.current_page - 1))"
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
                          :value="operations.current_page"
                          min="1"
                          :max="operations.last_page || 1"
                          @change="router.visit(getPageUrl(Math.max(1, Math.min($event.target.value, operations.last_page || 1))))"
                          class="w-16 px-2 py-1 text-center border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent font-medium text-sm"
                      />
                      <span>of <span class="font-semibold">{{ operations.last_page || 1 }}</span></span>
                  </div>
                  <button
                      :disabled="operations.current_page === operations.last_page"
                      @click.prevent="router.visit(getPageUrl(operations.current_page + 1))"
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
