<template>
    <!-- Modal Backdrop -->
    <div
        v-if="show"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4 transition-opacity"
    >
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md flex flex-col overflow-hidden transform transition-all">
            
            <!-- Header with Icon -->
            <div class="px-6 pt-8 pb-4 text-center border-b border-gray-100 dark:border-gray-800">
                <div class="mx-auto w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Slot confirmation submitted</h2>
            </div>

            <!-- Body -->
            <div class="p-6 space-y-5 bg-gray-50 dark:bg-gray-800/20">
                
                <!-- Important notice box -->
                <div class="p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700/50 rounded-xl flex gap-3">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <p class="text-sm text-yellow-800 dark:text-yellow-200 leading-relaxed">
                        Confirming your slot <strong>does not</strong> mean you are officially enrolled. This only reserves your place in the admissions process.
                    </p>
                </div>

                <!-- Next Steps List -->
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">To complete official enrollment, you must:</h3>
                    <ul class="space-y-2.5">
                        <li class="flex items-start gap-2.5">
                            <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                            <span class="text-sm text-gray-700 dark:text-gray-300">Attend your scheduled interview at PUP-Taguig Campus</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-sm text-gray-700 dark:text-gray-300">Submit all original documents with two photocopies on enrollment day</span>
                        </li>
                        <li class="flex items-start gap-2.5">
                            <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span class="text-sm text-gray-700 dark:text-gray-300">Pass the interview — incomplete requirements will not be entertained</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Footer area with subtle note and CTA -->
            <div class="px-6 py-5 bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 space-y-4">
                <p class="text-xs text-center text-gray-500 dark:text-gray-400 leading-relaxed italic">
                    A confirmation email with your SAR-Form 1, interview schedule, and enrollment documents will be sent within three working days.
                </p>
                <button
                    @click="closeModal"
                    class="w-full flex items-center justify-center py-3 px-4 rounded-xl text-sm font-semibold text-white bg-[#9E122C] hover:bg-[#7a0e22] shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                >
                    I understand, continue
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    show: {
        type: Boolean,
        required: true,
    }
});

const emit = defineEmits(["close"]);

const closeModal = () => {
    emit("close");
};
</script>
