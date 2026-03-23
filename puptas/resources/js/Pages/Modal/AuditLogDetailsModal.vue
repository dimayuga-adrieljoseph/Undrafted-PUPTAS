<script setup>
import { computed } from "vue";

const props = defineProps({
    show: {
        type: Boolean,
        required: true,
    },
    log: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(["close"]);

const closeModal = () => {
    emit("close");
};

const formatDate = (date) => {
    if (!date) return "N/A";
    return new Date(date).toLocaleString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
    });
};

const formatJson = (json) => {
    if (!json) return null;
    try {
        if (typeof json === "string") {
            return JSON.stringify(JSON.parse(json), null, 2);
        }
        return JSON.stringify(json, null, 2);
    } catch {
        return json;
    }
};

const getActionBadgeClass = (action) => {
    const classes = {
        CREATE: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400",
        UPDATE: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400",
        DELETE: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400",
        LOGIN: "bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400",
        LOGOUT: "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400",
        VIEW: "bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400",
        EXPORT: "bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400",
        IMPORT: "bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-400",
    };
    return classes[action] || classes.VIEW;
};
</script>

<template>
    <!-- Details Modal -->
    <transition name="fade">
        <div
            v-if="show"
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div
                    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity dark:bg-gray-900 dark:bg-opacity-75"
                    aria-hidden="true"
                    @click="closeModal"
                ></div>

                <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                <div
                    class="relative inline-block transform overflow-hidden rounded-xl bg-white dark:bg-gray-800 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-3xl sm:align-middle"
                >
                    <!-- Modal Header -->
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                                    <svg class="w-5 h-5 text-purple-600 dark:text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="modal-title">
                                    Audit Log Details
                                </h3>
                            </div>
                            <button
                                @click="closeModal"
                                class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-200"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Modal Content - Scrollable -->
                    <div class="px-6 py-4 max-h-[70vh] overflow-y-auto simple-scrollbar">
                        <div v-if="log" class="space-y-4">
                            <!-- Basic Info Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-1 mb-2">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Timestamp
                                    </label>
                                    <p class="text-sm text-gray-900 dark:text-white font-medium">{{ formatDate(log.created_at) }}</p>
                                </div>
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Status</label>
                                    <p class="text-sm text-green-600 dark:text-green-400 font-medium">Success</p>
                                </div>
                            </div>

                            <!-- User Info -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-1 mb-2">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                        User
                                    </label>
                                    <p class="text-sm text-gray-900 dark:text-white font-medium">{{ log.username || log.user_id || "N/A" }}</p>
                                </div>
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Role</label>
                                    <p class="text-sm text-gray-900 dark:text-white font-medium">{{ log.user_role || "N/A" }}</p>
                                </div>
                            </div>

                            <!-- Action, Module, and Type -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Module</label>
                                    <p class="text-sm text-gray-900 dark:text-white font-medium">{{ log.module_name || "N/A" }}</p>
                                </div>
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Action Type</label>
                                    <p class="mt-1">
                                        <span
                                            :class="[
                                                'inline-flex rounded-full px-3 py-1 text-xs font-medium',
                                                getActionBadgeClass(log.action_type),
                                            ]"
                                        >
                                            {{ log.action_type || "N/A" }}
                                        </span>
                                    </p>
                                </div>
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Log Type</label>
                                    <p class="text-sm text-gray-900 dark:text-white font-medium">{{ log.log_type || "N/A" }}</p>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Description</label>
                                <p class="text-sm text-gray-900 dark:text-white">{{ log.description || "N/A" }}</p>
                            </div>

                            <!-- Network Info -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-1 mb-2">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                                        </svg>
                                        IP Address
                                    </label>
                                    <p class="text-sm text-gray-900 dark:text-white font-mono">{{ log.ip_address || "N/A" }}</p>
                                </div>
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Session ID</label>
                                    <p class="text-sm text-gray-900 dark:text-white font-mono">{{ log.session_id || "N/A" }}</p>
                                </div>
                            </div>

                            <!-- Request URL -->
                            <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Request URL</label>
                                <p class="text-sm text-gray-900 dark:text-white break-all">{{ log.request_url || "N/A" }}</p>
                            </div>

                            <!-- User Agent -->
                            <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider flex items-center gap-1 mb-2">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                                    </svg>
                                    User Agent
                                </label>
                                <p class="text-sm text-gray-900 dark:text-white break-all">{{ log.user_agent || "N/A" }}</p>
                            </div>

                            <!-- Login/Logout Times -->
                            <div v-if="log.login_time || log.logout_time" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-if="log.login_time" class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Login Time</label>
                                    <p class="text-sm text-gray-900 dark:text-white">{{ formatDate(log.login_time) }}</p>
                                </div>
                                <div v-if="log.logout_time" class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Logout Time</label>
                                    <p class="text-sm text-gray-900 dark:text-white">{{ formatDate(log.logout_time) }}</p>
                                </div>
                            </div>

                            <!-- JSON Values -->
                            <div v-if="log.old_values" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 block">Old Values</label>
                                    <pre class="max-h-60 overflow-auto rounded-lg bg-white dark:bg-gray-900 p-3 text-xs text-gray-900 dark:text-white font-mono border border-gray-200 dark:border-gray-700 simple-scrollbar">{{ formatJson(log.old_values) }}</pre>
                                </div>
                                <div v-if="log.new_values" class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 block">New Values</label>
                                    <pre class="max-h-60 overflow-auto rounded-lg bg-white dark:bg-gray-900 p-3 text-xs text-gray-900 dark:text-white font-mono border border-gray-200 dark:border-gray-700 simple-scrollbar">{{ formatJson(log.new_values) }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex justify-end">
                            <button
                                @click="closeModal"
                                type="button"
                                class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition font-medium text-sm dark:text-gray-900"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
