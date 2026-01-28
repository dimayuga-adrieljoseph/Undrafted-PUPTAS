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
                <div class="bg-gradient-to-r from-blue-700 to-blue-800 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold text-white" id="modal-title">
                                    Privacy Policy
                                </h3>
                                <p class="text-sm text-blue-100">Data Protection and Privacy Statement</p>
                            </div>
                        </div>
                        <button @click="closeModal" class="text-white hover:text-blue-200 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Content -->
                <div class="px-6 py-4 max-h-[60vh] overflow-y-auto">
                    <div class="prose prose-sm dark:prose-invert max-w-none">
                        <!-- Introduction -->
                        <section class="mb-8">
                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-4">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-blue-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-sm font-medium text-blue-700 dark:text-blue-300">Compliant with Republic Act No. 10173 (Data Privacy Act of 2012)</span>
                                </div>
                            </div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">1. Introduction</h2>
                            <p class="text-gray-600 dark:text-gray-300">
                                The Polytechnic University of the Philippines - Taguig (PUPT) is committed to protecting your privacy and personal data. This Privacy Policy explains how we collect, use, disclose, and protect your personal information in accordance with the Data Privacy Act of 2012.
                            </p>
                        </section>

                        <!-- Data Collection -->
                        <section class="mb-8">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">2. Information We Collect</h2>
                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Personal Information:</h3>
                                    <ul class="space-y-2 text-gray-600 dark:text-gray-300 list-disc pl-5">
                                        <li>Full name, birthdate, gender, contact information</li>
                                        <li>Address and contact details</li>
                                        <li>Email address and account credentials</li>
                                        <li>Academic background and school information</li>
                                    </ul>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Academic Information:</h3>
                                    <ul class="space-y-2 text-gray-600 dark:text-gray-300 list-disc pl-5">
                                        <li>Senior high school details and records</li>
                                        <li>Academic grades and performance data</li>
                                        <li>Extracurricular activities and achievements</li>
                                    </ul>
                                </div>
                            </div>
                        </section>

                        <!-- How We Use Information -->
                        <section class="mb-8">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">3. How We Use Your Information</h2>
                            <p class="text-gray-600 dark:text-gray-300 mb-3">Your personal data is used for the following purposes:</p>
                            <ul class="space-y-3 text-gray-600 dark:text-gray-300">
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>To process your admission application and evaluate your eligibility</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>To communicate with you regarding your application status</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>To maintain accurate student records and statistics</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>To comply with legal and regulatory requirements</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>To improve our admission processes and services</span>
                                </li>
                            </ul>
                        </section>

                        <!-- Data Sharing -->
                        <section class="mb-8">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">4. Data Sharing and Disclosure</h2>
                            <p class="text-gray-600 dark:text-gray-300 mb-3">
                                We do not sell or rent your personal information. We may share your data only in the following circumstances:
                            </p>
                            <ul class="space-y-2 text-gray-600 dark:text-gray-300 list-disc pl-5">
                                <li>With authorized university personnel for admission processing</li>
                                <li>When required by law or legal process</li>
                                <li>To protect the rights, property, or safety of PUPT</li>
                                <li>With your explicit consent for specific purposes</li>
                            </ul>
                        </section>

                        <!-- Data Security -->
                        <section class="mb-8">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">5. Data Security Measures</h2>
                            <p class="text-gray-600 dark:text-gray-300 mb-3">
                                PUPT implements appropriate technical and organizational security measures to protect your personal data:
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-gray-50 dark:bg-gray-800/50 p-4 rounded-lg">
                                    <div class="flex items-center mb-2">
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                        </svg>
                                        <h4 class="font-semibold text-gray-800 dark:text-white">Access Control</h4>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Role-based access controls and authentication mechanisms</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800/50 p-4 rounded-lg">
                                    <div class="flex items-center mb-2">
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
                                        </svg>
                                        <h4 class="font-semibold text-gray-800 dark:text-white">Encryption</h4>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">256-bit SSL encryption for data transmission</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800/50 p-4 rounded-lg">
                                    <div class="flex items-center mb-2">
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <h4 class="font-semibold text-gray-800 dark:text-white">Regular Audits</h4>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Regular security assessments and vulnerability testing</p>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-800/50 p-4 rounded-lg">
                                    <div class="flex items-center mb-2">
                                        <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z" clip-rule="evenodd"/>
                                        </svg>
                                        <h4 class="font-semibold text-gray-800 dark:text-white">Firewall Protection</h4>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Advanced firewall systems and intrusion detection</p>
                                </div>
                            </div>
                        </section>

                        <!-- Your Rights -->
                        <section class="mb-8">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">6. Your Data Privacy Rights</h2>
                            <p class="text-gray-600 dark:text-gray-300 mb-4">
                                Under the Data Privacy Act of 2012, you have the following rights:
                            </p>
                            <div class="space-y-3">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-xs font-bold text-blue-600 dark:text-blue-400">1</span>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-800 dark:text-white">Right to Access</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Request access to your personal data</p>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-xs font-bold text-blue-600 dark:text-blue-400">2</span>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-800 dark:text-white">Right to Rectification</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Correct inaccurate or incomplete data</p>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-xs font-bold text-blue-600 dark:text-blue-400">3</span>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-800 dark:text-white">Right to Erasure</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Request deletion of your personal data</p>
                                    </div>
                                </div>
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center mr-3">
                                        <span class="text-xs font-bold text-blue-600 dark:text-blue-400">4</span>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-800 dark:text-white">Right to Object</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Object to processing of your personal data</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Data Retention -->
                        <section class="mb-8">
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">7. Data Retention Period</h2>
                            <p class="text-gray-600 dark:text-gray-300">
                                We retain your personal data only for as long as necessary to fulfill the purposes for which it was collected, or as required by law. Unsuccessful application data is retained for two (2) years for statistical purposes, after which it is securely disposed of.
                            </p>
                        </section>

                        <!-- Contact Information -->
                        <section>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-3">8. Contact Our Data Protection Officer</h2>
                            <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
                                <p class="text-gray-600 dark:text-gray-300 mb-3">
                                    For data privacy concerns, requests, or complaints, please contact our Data Protection Officer:
                                </p>
                                <div class="space-y-2">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                        <span class="text-gray-600 dark:text-gray-300">dpo@pupt.edu.ph</span>
                                    </div>
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                        </svg>
                                        <span class="text-gray-600 dark:text-gray-300">(02) 9876-5432</span>
                                    </div>
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <span class="text-gray-600 dark:text-gray-300">Data Protection Office, Administration Building, PUPT Campus</span>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <!-- Footer -->
                <div class="bg-gray-50 dark:bg-gray-800/50 px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                <span class="font-semibold">Reference:</span> Republic Act No. 10173
                            </p>
                        </div>
                        <button @click="closeModal" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
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
</style>