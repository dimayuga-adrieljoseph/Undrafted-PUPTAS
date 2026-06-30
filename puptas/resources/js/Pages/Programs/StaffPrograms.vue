<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import EvaluatorLayout from '@/Layouts/EvaluatorLayout.vue';
import InterviewerLayout from '@/Layouts/InterviewerLayout.vue';
import RecordStaffLayout from '@/Layouts/RecordStaffLayout.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);

const layoutComponent = computed(() => {
    const variant = page.props.variant;
    if (variant === 'interviewer') return InterviewerLayout;
    if (variant === 'record') return RecordStaffLayout;
    if (variant === 'evaluator') return EvaluatorLayout;

    // Fallback to role-based if variant is missing
    const roleId = user.value?.role_id;
    if (roleId === 4) return InterviewerLayout;
    if (roleId === 6) return RecordStaffLayout;
    if ([2, 3, 7, 8].includes(roleId)) return EvaluatorLayout;
    return AppLayout;
});

const axios = window.axios;

const loading = ref(false);
const error = ref("");
const programs = ref([]);
const lastUpdated = ref(null);
let pollInterval = null;

const fetchPrograms = async (silent = false) => {
    if (!silent) loading.value = true;
    error.value = "";
    try {
        const { data } = await axios.get("/api/staff/programs/slots");
        programs.value = data.programs || [];
        lastUpdated.value = new Date();
    } catch (err) {
        if (!silent) {
            error.value = err.response?.data?.message || "Failed to load program slots.";
        }
    } finally {
        if (!silent) loading.value = false;
    }
};

const timeAgo = computed(() => {
    if (!lastUpdated.value) return "";
    const seconds = Math.floor((new Date() - lastUpdated.value) / 1000);
    if (seconds < 5) return "Just now";
    if (seconds < 60) return `${seconds}s ago`;
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    return `${hours}h ago`;
});

const hasData = computed(() => programs.value.length > 0);

const totalSlots = computed(() => programs.value.reduce((acc, p) => acc + (p.slots || 0), 0));

onMounted(() => {
    fetchPrograms();
    pollInterval = setInterval(() => fetchPrograms(true), 30000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <Head title="Programs & Slots" />
    <component :is="layoutComponent" title="Programs & Slots">
        <template #header>
            <h2 class="font-bold text-2xl text-gray-900 dark:text-gray-100">
                Programs & Slots Available
            </h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Stats Bar -->
                <div v-if="!loading && hasData" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <div class="flex items-center gap-6">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ programs.length }} Programs
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-green-500"></div>
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ totalSlots }} Total Slots Available
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-400 dark:text-gray-500">
                                Updated {{ timeAgo }}
                            </span>
                            <button
                                @click="fetchPrograms(true)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition min-h-[36px]"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Loading State (Skeleton) -->
                <div v-if="loading" class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 animate-pulse">
                        <div class="flex items-center gap-6">
                            <div class="h-4 w-28 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            <div class="h-4 w-28 bg-gray-200 dark:bg-gray-700 rounded"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="n in 6" :key="n" class="p-5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl animate-pulse">
                            <div class="h-5 w-24 bg-gray-200 dark:bg-gray-700 rounded mb-2"></div>
                            <div class="h-4 w-40 bg-gray-200 dark:bg-gray-700 rounded mb-4"></div>
                            <div class="h-2 w-full bg-gray-200 dark:bg-gray-700 rounded mb-3"></div>
                            <div class="grid grid-cols-4 gap-2">
                                <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-8 text-center">
                    <svg class="w-14 h-14 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                    <p class="text-red-600 dark:text-red-400 font-medium text-lg mb-2">Something went wrong</p>
                    <p class="text-red-500 dark:text-red-400/80 text-sm mb-5">{{ error }}</p>
                    <button
                        @click="fetchPrograms()"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg transition font-medium text-sm min-h-[44px]"
                    >
                        Try Again
                    </button>
                </div>

                <!-- Programs Grid -->
                <div v-else-if="hasData" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div
                        v-for="program in programs"
                        :key="program.id"
                        class="group p-5 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-blue-300 dark:hover:border-blue-700 hover:shadow-lg transition-all duration-200 flex flex-col"
                    >
                        <!-- Header -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-lg font-bold text-gray-900 dark:text-white truncate">{{ program.code }}</span>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 leading-snug line-clamp-2" :title="program.name">{{ program.name }}</p>
                                <p class="text-xs text-blue-600 dark:text-blue-400 mt-2 truncate" :title="program.strand_names">
                                    <span class="font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide text-[10px]">Strands:</span> 
                                    {{ program.strand_names || 'Open to All' }}
                                </p>
                            </div>
                        </div>

                        <!-- Slots -->
                        <div class="mb-5 p-3 bg-gray-50 dark:bg-gray-700/30 rounded-lg mt-auto">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Available Slots</span>
                                <span class="text-xl font-bold" :class="program.slots > 10 ? 'text-green-600 dark:text-green-400' : (program.slots > 0 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400')">
                                    {{ program.slots }}
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2 overflow-hidden">
                                <div
                                    class="h-2 rounded-full transition-all duration-500 ease-out"
                                    :class="program.slots > 10 ? 'bg-green-500' : (program.slots > 0 ? 'bg-yellow-500' : 'bg-red-500')"
                                    :style="{ width: Math.min(Math.max(program.slots * 3, 2), 100) + '%' }"
                                ></div>
                            </div>
                        </div>

                        <!-- Requirements -->
                        <div class="border-t border-gray-100 dark:border-gray-700/50 pt-4">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Minimum Grade Requirements</p>
                            <div class="grid grid-cols-4 gap-2">
                                <div class="text-center p-2 bg-white dark:bg-gray-800 rounded border border-gray-100 dark:border-gray-700">
                                    <p class="text-[9px] font-semibold uppercase tracking-wide text-gray-400 mb-1">Math</p>
                                    <p class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ program.requirements.math || '-' }}</p>
                                </div>
                                <div class="text-center p-2 bg-white dark:bg-gray-800 rounded border border-gray-100 dark:border-gray-700">
                                    <p class="text-[9px] font-semibold uppercase tracking-wide text-gray-400 mb-1">Sci</p>
                                    <p class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ program.requirements.science || '-' }}</p>
                                </div>
                                <div class="text-center p-2 bg-white dark:bg-gray-800 rounded border border-gray-100 dark:border-gray-700">
                                    <p class="text-[9px] font-semibold uppercase tracking-wide text-gray-400 mb-1">Eng</p>
                                    <p class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ program.requirements.english || '-' }}</p>
                                </div>
                                <div class="text-center p-2 bg-white dark:bg-gray-800 rounded border border-gray-100 dark:border-gray-700">
                                    <p class="text-[9px] font-semibold uppercase tracking-wide text-gray-400 mb-1">GWA</p>
                                    <p class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ program.requirements.gwa || '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </component>
</template>
