<template>
    <!-- Modal Background -->
    <div v-if="show" class="fixed inset-0 z-[9999] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Background Overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

        <!-- Modal Container -->
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <!-- Modal Panel -->
            <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl">
                <!-- Header -->
                <div class="bg-gradient-to-r from-[#9E122C] to-[#7a0f24] px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center mr-3 dark:bg-gray-900/20">
                                <svg class="w-6 h-6 text-white dark:text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white dark:text-gray-900" id="modal-title">
                                    Terms and Conditions
                                </h3>
                                <p class="text-sm text-white/80">Privacy Policy and Data Consent</p>
                            </div>
                        </div>
                        <button v-if="canClose" @click="cancel" class="text-white/80 hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="px-6 py-4 max-h-[50vh] overflow-y-auto">
                    <!-- Consent Statement -->
                    <div class="bg-[#9E122C]/5 dark:bg-[#9E122C]/10 rounded-lg p-4 mb-4">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-[#9E122C] mt-0.5 mr-2 flex-shrink-0 dark:text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                By clicking "I Agree", you consent to the collection, use, and processing of your personal data for legitimate purposes related to this service.
                            </p>
                        </div>
                    </div>
                    
                    <div class="bg-[#9E122C]/5 dark:bg-[#9E122C]/10 rounded-lg p-4 mb-4">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-[#9E122C] mt-0.5 mr-2 flex-shrink-0 dark:text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                Your information will be handled in accordance with our Privacy Policy and in compliance with the Data Privacy Act of 2012.
                            </p>
                        </div>
                    </div>

                    <!-- Privacy Policy Reference -->
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        <section class="mb-4">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Privacy Policy</h2>
                            <p class="text-gray-600 dark:text-gray-300 text-sm">
                                The Polytechnic University of the Philippines - Taguig (PUPT) is committed to protecting your privacy and personal data. This Privacy Policy explains how we collect, use, disclose, and protect your personal information in accordance with the Data Privacy Act of 2012.
                            </p>
                        </section>

                        <section class="mb-4">
                            <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-2 text-sm">Information We Collect:</h3>
                            <ul class="text-gray-600 dark:text-gray-300 text-sm list-disc pl-5 space-y-1">
                                <li>Full name, birthdate, gender, contact information</li>
                                <li>Address and contact details</li>
                                <li>Email address and account credentials</li>
                                <li>Academic background and school information</li>
                            </ul>
                        </section>

                        <section class="mb-4">
                            <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-2 text-sm">How We Use Your Information:</h3>
                            <ul class="text-gray-600 dark:text-gray-300 text-sm list-disc pl-5 space-y-1">
                                <li>To process your admission application and evaluate your eligibility</li>
                                <li>To communicate with you regarding your application status</li>
                                <li>To maintain accurate student records and statistics</li>
                                <li>To comply with legal and regulatory requirements</li>
                            </ul>
                        </section>
                    </div>

                    <!-- Consent Checkbox -->
                    <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <label class="flex items-start cursor-pointer">
                            <input 
                                type="checkbox" 
                                v-model="isChecked"
                                class="w-5 h-5 rounded border-gray-300 text-[#9E122C] focus:ring-[#9E122C] mt-0.5 mr-3 dark:border-gray-600 dark:text-white"
                            >
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                I Agree and acknowledge the Terms and Conditions
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 dark:bg-gray-800/50 px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-center">
                        <button 
                            @click="cancel" 
                            class="px-6 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 font-medium rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                        >
                            Cancel
                        </button>
                        <button 
                            @click="accept" 
                            :disabled="!isChecked"
                            :class="[
                                'px-6 py-2 font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:ring-offset-2',
                                isChecked 
                                    ? 'bg-[#9E122C] text-white hover:bg-[#7a0f24]' 
                                    : 'bg-gray-300 text-gray-500 cursor-not-allowed dark:bg-gray-600 dark:text-gray-400'
                            ]"
                        >
                            Continue
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { defineProps, defineEmits, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    canClose: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['close', 'accept', 'cancel']);

const page = usePage();
const isChecked = ref(false);

// Reset checkbox when modal closes
watch(() => props.show, (newVal) => {
    if (!newVal) {
        isChecked.value = false;
    }
});

const accept = () => {
    if (isChecked.value) {
        emit('accept');
    }
};

const cancel = () => {
    emit('cancel');
};

// Close modal on ESC key
const handleEscKey = (event) => {
    if (event.key === 'Escape' && props.canClose) {
        cancel();
    }
};

// Add event listener for ESC key
watch(() => props.show, (newVal) => {
    if (newVal) {
        document.addEventListener('keydown', handleEscKey);
    } else {
        document.removeEventListener('keydown', handleEscKey);
    }
});
</script>

<style scoped>
/* Custom scrollbar for modal content */
::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: #555;
}

.dark ::-webkit-scrollbar-track {
    background: #374151;
}

.dark ::-webkit-scrollbar-thumb {
    background: #6b7280;
}

.dark ::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}
</style>
