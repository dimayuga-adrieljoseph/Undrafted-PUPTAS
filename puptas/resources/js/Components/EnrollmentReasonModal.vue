<script setup>
import { ref, watch, computed } from 'vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    actionType: {
        type: String,
        default: 'tag', // 'tag' or 'untag'
    },
    applicantName: {
        type: String,
        default: 'Applicant',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    errorMessage: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'confirm']);

const reason = ref('');

watch(() => props.show, (newVal) => {
    if (newVal) {
        reason.value = '';
    }
});

const isReasonValid = computed(() => {
    return reason.value.trim().length >= 5;
});

const handleConfirm = () => {
    if (!isReasonValid.value || props.loading) return;
    emit('confirm', reason.value.trim());
};

const handleClose = () => {
    if (props.loading) return;
    emit('close');
};
</script>

<template>
    <Modal :show="show" max-width="md" @close="handleClose">
        <div class="p-6">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                {{ actionType === 'tag' ? 'Tag as Officially Enrolled' : 'Untag Enrollment' }}
            </h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                {{ actionType === 'tag'
                    ? `Are you sure you want to tag ${applicantName} as officially enrolled?`
                    : `Are you sure you want to revert ${applicantName} from officially enrolled to temporary?`
                }}
            </p>

            <div class="mt-4">
                <label for="enrollment-reason" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide mb-1">
                    Reason <span class="text-red-500">* (minimum 5 characters)</span>
                </label>
                <textarea
                    id="enrollment-reason"
                    v-model="reason"
                    rows="3"
                    maxlength="500"
                    placeholder="Enter the reason for this enrollment status change..."
                    class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 p-2.5 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent transition"
                    :disabled="loading"
                ></textarea>
                <div class="flex justify-between items-center mt-1 text-xs text-gray-500">
                    <span>{{ reason.trim().length }} / 500 characters</span>
                    <span v-if="reason.length > 0 && reason.trim().length < 5" class="text-amber-600">
                        At least 5 characters required
                    </span>
                </div>
                <p v-if="errorMessage" class="mt-1 text-xs text-red-600 font-medium">
                    {{ errorMessage }}
                </p>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    @click="handleClose"
                    :disabled="loading"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    @click="handleConfirm"
                    :disabled="!isReasonValid || loading"
                    :class="[
                        actionType === 'tag'
                            ? 'bg-green-600 hover:bg-green-700'
                            : 'bg-blue-600 hover:bg-blue-700',
                        'px-4 py-2 text-sm font-medium text-white rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2'
                    ]"
                >
                    <svg v-if="loading" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    {{ actionType === 'tag' ? 'Confirm Tag' : 'Confirm Untag' }}
                </button>
            </div>
        </div>
    </Modal>
</template>
