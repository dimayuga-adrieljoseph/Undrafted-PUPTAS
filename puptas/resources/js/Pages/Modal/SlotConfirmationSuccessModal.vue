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
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Slot confirmation</h2>
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
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Your Journey to Official Enrollment:</h3>
                    <div class="max-h-40 overflow-y-auto pr-2 custom-scrollbar">
                        <ol class="space-y-3 relative border-l border-gray-200 dark:border-gray-700 ml-2">
                            <li class="pl-4 relative">
                                <div class="absolute w-2.5 h-2.5 bg-gray-300 dark:bg-gray-600 rounded-full -left-[5.5px] top-1.5 border-2 border-gray-50 dark:border-gray-800"></div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">1. Register an account in IDP</p>
                            </li>
                            <li class="pl-4 relative">
                                <div class="absolute w-2.5 h-2.5 bg-gray-300 dark:bg-gray-600 rounded-full -left-[5.5px] top-1.5 border-2 border-gray-50 dark:border-gray-800"></div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">2. Submit grades and required documents</p>
                            </li>
                            <li class="pl-4 relative">
                                <div class="absolute w-2.5 h-2.5 bg-gray-300 dark:bg-gray-600 rounded-full -left-[5.5px] top-1.5 border-2 border-gray-50 dark:border-gray-800"></div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">3. Choose your preferred program</p>
                            </li>
                            <li class="pl-4 relative">
                                <div class="absolute w-2.5 h-2.5 bg-gray-300 dark:bg-gray-600 rounded-full -left-[5.5px] top-1.5 border-2 border-gray-50 dark:border-gray-800"></div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">4. Submit application for evaluation</p>
                            </li>
                            <li class="pl-4 relative">
                                <div class="absolute w-2.5 h-2.5 bg-gray-300 dark:bg-gray-600 rounded-full -left-[5.5px] top-1.5 border-2 border-gray-50 dark:border-gray-800"></div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">5. Attend and pass the interview</p>
                            </li>
                            <li class="pl-4 relative">
                                <div class="absolute w-2.5 h-2.5 bg-gray-300 dark:bg-gray-600 rounded-full -left-[5.5px] top-1.5 border-2 border-gray-50 dark:border-gray-800"></div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">6. Undergo medical assessment</p>
                            </li>
                            <li class="pl-4 relative">
                                <div class="absolute w-2.5 h-2.5 bg-[#9E122C] rounded-full -left-[5.5px] top-1.5 border-2 border-gray-50 dark:border-gray-800 shadow-sm"></div>
                                <p class="text-sm font-semibold text-[#9E122C] dark:text-[#E84E69]">7. Finalize official enrollment</p>
                            </li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Footer area with subtle note and CTA -->
            <div class="px-6 py-5 bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 space-y-4">
                <p class="text-xs text-center text-gray-500 dark:text-gray-400 leading-relaxed italic">
                    A confirmation email with your SAR-Form 1, interview schedule, and enrollment documents will be sent on the first week of June 2026.
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

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 10px;
}
:deep(.dark) .custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #475569;
}
</style>
