<template>
    <!-- Modal Background -->
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Background Overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeModal"></div>

        <!-- Modal Container -->
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <!-- Modal Panel -->
            <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl">
                <!-- Header -->
                <div class="bg-gradient-to-r from-red-700 to-red-800 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white" id="modal-title">
                                    Terms of Service
                                </h3>
                                <p class="text-sm text-red-100">Polytechnic University of the Philippines - Taguig</p>
                            </div>
                        </div>
                        <button @click="closeModal" class="text-white hover:text-red-200 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="px-6 py-4 max-h-[60vh] overflow-y-auto">
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        <!-- Last Updated -->
                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-6">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-blue-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-sm font-medium text-blue-700 dark:text-blue-300">Last Updated: January 2024</span>
                            </div>
                        </div>

                        <!-- Introduction -->
                        <section class="mb-8">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">1. Introduction</h2>
                            <p class="text-gray-600 dark:text-gray-300 mb-3">
                                Welcome to the Polytechnic University of the Philippines - Taguig (PUPT) Admission Portal. By accessing and using this portal, you agree to comply with and be bound by the following terms and conditions.
                            </p>
                            <p class="text-gray-600 dark:text-gray-300">
                                These Terms of Service govern your use of the admission portal and all related services provided by PUPT.
                            </p>
                        </section>

                        <!-- Eligibility -->
                        <section class="mb-8">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">2. Eligibility</h2>
                            <ul class="space-y-2 text-gray-600 dark:text-gray-300">
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-red-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    You must be a senior high school graduate or graduating student
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-red-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    You must provide accurate and complete information during registration
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-red-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    You must meet all admission requirements set by PUPT
                                </li>
                            </ul>
                        </section>

                        <!-- Account Responsibilities -->
                        <section class="mb-8">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">3. Account Responsibilities</h2>
                            <p class="text-gray-600 dark:text-gray-300 mb-3">
                                As a registered user of the PUPT Admission Portal, you agree to:
                            </p>
                            <ul class="space-y-2 text-gray-600 dark:text-gray-300 list-disc pl-5">
                                <li>Maintain the confidentiality of your account credentials</li>
                                <li>Notify PUPT immediately of any unauthorized use of your account</li>
                                <li>Provide truthful and accurate information in all application materials</li>
                                <li>Accept responsibility for all activities that occur under your account</li>
                            </ul>
                        </section>

                        <!-- Application Process -->
                        <section class="mb-8">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">4. Application Process</h2>
                            <p class="text-gray-600 dark:text-gray-300 mb-3">
                                The admission process involves the following stages:
                            </p>
                            <ol class="space-y-2 text-gray-600 dark:text-gray-300 list-decimal pl-5">
                                <li>Online Registration and Profile Completion</li>
                                <li>Document Submission and Verification</li>
                                <li>Application Review by Admissions Committee</li>
                                <li>Notification of Admission Decision</li>
                                <li>Enrollment Confirmation (if admitted)</li>
                            </ol>
                        </section>

                        <!-- Fees and Payments -->
                        <section class="mb-8">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">5. Fees and Payments</h2>
                            <p class="text-gray-600 dark:text-gray-300">
                                PUPT reserves the right to charge application fees as determined by the university administration. All fees are non-refundable unless otherwise specified.
                            </p>
                        </section>

                        <!-- Data Accuracy -->
                        <section class="mb-8">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">6. Data Accuracy and Verification</h2>
                            <p class="text-gray-600 dark:text-gray-300 mb-3">
                                You are responsible for ensuring all information provided is accurate and complete. PUPT reserves the right to:
                            </p>
                            <ul class="space-y-2 text-gray-600 dark:text-gray-300 list-disc pl-5">
                                <li>Verify all submitted information and documents</li>
                                <li>Request additional documentation if needed</li>
                                <li>Disqualify applicants who provide false information</li>
                                <li>Take legal action against fraudulent applications</li>
                            </ul>
                        </section>

                        <!-- Intellectual Property -->
                        <section class="mb-8">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">7. Intellectual Property</h2>
                            <p class="text-gray-600 dark:text-gray-300">
                                All content, features, and functionality of the PUPT Admission Portal are owned by Polytechnic University of the Philippines and are protected by copyright, trademark, and other intellectual property laws.
                            </p>
                        </section>

                        <!-- Limitation of Liability -->
                        <section class="mb-8">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">8. Limitation of Liability</h2>
                            <p class="text-gray-600 dark:text-gray-300 mb-3">
                                PUPT shall not be liable for:
                            </p>
                            <ul class="space-y-2 text-gray-600 dark:text-gray-300 list-disc pl-5">
                                <li>Technical issues or system downtime beyond our control</li>
                                <li>Loss of data due to user error or system failure</li>
                                <li>Decisions made regarding admission applications</li>
                                <li>Any indirect, incidental, or consequential damages</li>
                            </ul>
                        </section>

                        <!-- Changes to Terms -->
                        <section class="mb-8">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">9. Changes to Terms</h2>
                            <p class="text-gray-600 dark:text-gray-300">
                                PUPT reserves the right to modify these Terms of Service at any time. Changes will be effective immediately upon posting on the admission portal. Your continued use of the portal constitutes acceptance of the modified terms.
                            </p>
                        </section>

                        <!-- Governing Law -->
                        <section class="mb-8">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">10. Governing Law</h2>
                            <p class="text-gray-600 dark:text-gray-300">
                                These Terms of Service shall be governed by and construed in accordance with the laws of the Republic of the Philippines. Any disputes shall be resolved in the courts of Taguig City.
                            </p>
                        </section>

                        <!-- Contact Information -->
                        <section>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">11. Contact Information</h2>
                            <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
                                <p class="text-gray-600 dark:text-gray-300 mb-2">
                                    For questions or concerns regarding these Terms of Service, please contact:
                                </p>
                                <div class="space-y-2">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                        <span class="text-gray-600 dark:text-gray-300">admissions@pupt.edu.ph</span>
                                    </div>
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                        </svg>
                                        <span class="text-gray-600 dark:text-gray-300">(02) 1234-5678</span>
                                    </div>
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <span class="text-gray-600 dark:text-gray-300">Admissions Office, Polytechnic University of the Philippines - Taguig</span>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 dark:bg-gray-800/50 px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm text-gray-600 dark:text-gray-400">Protected by Philippine Data Privacy Act of 2012</span>
                        </div>
                        <button @click="closeModal" class="px-6 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            I Understand
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close']);

const closeModal = () => {
    emit('close');
};

// Close modal on ESC key
const handleEscKey = (event) => {
    if (event.key === 'Escape') {
        closeModal();
    }
};

// Add event listener for ESC key
if (props.show) {
    document.addEventListener('keydown', handleEscKey);
} else {
    document.removeEventListener('keydown', handleEscKey);
}
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

/* Modal animation */
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>