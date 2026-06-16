<script setup>
import Sidebar from '@/Components/Sidebar.vue'
import Footer from '@/Components/Footer.vue'
import TermsandConditionsModal from '@/Pages/Modal/TermsandConditionsModal.vue'
import { useLayout } from '@/Composables/useLayout'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faMoon, faSun } from '@fortawesome/free-solid-svg-icons'
import { ref } from 'vue'

library.add(faMoon, faSun)

const {
    user,
    isLoading,
    isDarkMode,
    toggleDarkMode,
    showPrivacyModal,
    handlePrivacyAccept,
    handlePrivacyCancel,
    sidebarOpen,
} = useLayout()

// FAQ
const showFaqModal = ref(false)
const openFaqItems = ref([])

const faqItems = [
  {
    question: "What happens after I submit my application? What is the next step?",
    answer: "Kindly wait for your SAR (Student Admission Record) Form, which will be sent to your registered email. The SAR Form will include your interview schedule and other important instructions regarding the admission process. Please follow the instructions indicated in the email carefully and prepare all required documents for your scheduled interview.",
  },
  {
    question: "Can we change our registered name (and other necessary information) due to errors made during the PUPCET application through PUP iApply?",
    answer: "Once you pass the interview and secure a confirmed slot in any of the programs, submit a notarized Affidavit of Discrepancy explaining the erroneous entry/application and proceed to the Office of the Campus Registrar to obtain the list of supporting documents required for the request to correct your name entry in the Student Information System (SIS).",
  },
  {
    question: "Our graduation comes after the date of the interview, so the Grade 12 report card is not yet available.",
    answer: null,
    answerItems: [
      "Submit the following requirements:",
      "Certification from your school principal/registrar (with school dry seal and authorized signatures) about the date of the graduation and that you belong to the graduating batch/class.",
      "Certificate of Grades (Grade 12) with school dry seal and printed name and signature of the school principal/registrar or any authorized school personnel.",
    ],
  },
  {
    question: "The portal does not reflect the programs that I am qualified for.",
    answer: "Please ensure that you uploaded the required initial documents in the portal and you encoded the complete Senior High School English, Mathematics, and Science subjects. If no program offering was reflected after making sure of this step, please contact us or visit our campus for further checking.",
  },
  {
    question: "Can I change my email address for future communications, announcements, etc.?",
    answer: "For your Identity Provider portal account, you may seek assistance from the Chat Support upon login. Once you are officially enrolled and already have a PUP SIS account, proceed to the Office of the Campus Registrar to obtain the list of supporting documents required for the request to update your information in the Student Information System (SIS).",
  },
  {
    question: "My high school registrar advised that PUP-Taguig must send a formal request for the issuance of my report cards.",
    answer: "PUP-Taguig Campus will only request the F137-A with \"Copy for Polytechnic University of the Philippines-Taguig Campus\" once the applicant is officially accepted/enrolled in our university. The applicant should write their high school a formal request letter of the F137 or other grade records (for evaluation purposes only) personally if needed. You may attach a copy of your PUPCET evaluation result and the list of admission requirements as proof.",
  },
  {
    question: "What if I just ordered my PSA-authenticated birth certificate online and it won't be delivered before the interview date?",
    answer: "PSA birth certificate delivery lead times depend on your location, taking 1-2 working days for processing plus the courier's transit time. Deliveries typically take next day delivery for Metro Manila addresses and 3-8 working days for provincial areas. Bring your receipt as proof that you have already requested for the document.",
  },
  {
    question: "Can I replace or re-upload documents if I uploaded the wrong file?",
    answer: "Yes. Applicants may replace or re-upload documents through the Document Upload section as long as the application review process is not yet completed.",
  },
  {
    question: "I did not receive my SAR (Student Admission Record) Form in my email. What should I do?",
    answer: "First, check your spam or junk folder. If it is still not found, verify that you used the correct registered email during application. If the issue continues, contact admissions support for verification and request assistance for re-sending your SAR Form.",
  },
  {
    question: "I accidentally encoded incorrect grades in my application. Can I still correct them?",
    answer: "If you have not yet submitted your application, you may still update or correct your encoded information by clicking \"Input Grades\" again. If the application has already been submitted and locked, you may seek assistance from Chat Support.",
  },
  {
    question: "How should I properly encode my grades in the system?",
    answer: "Enter grades exactly as they appear on your report card, including decimal grades if applicable. Accurate encoding is required for proper evaluation.",
  },
  {
    question: "What should I do if a subject is not available in the system?",
    answer: "If a required subject is not listed, click the \"Add Subject\" button and enter the closest equivalent subject based on your curriculum. Ensure that the subject entered accurately reflects your official record or its nearest equivalent.",
  },
  {
    question: "What should I do if I cannot upload my documents?",
    answer: "Check the file format and file size first to ensure they meet system requirements. You may also try refreshing the page, switching browsers, or using a different device if the issue persists.",
  },
]

const toggleFaq = (index) => {
  const pos = openFaqItems.value.indexOf(index)
  if (pos === -1) {
    openFaqItems.value.push(index)
  } else {
    openFaqItems.value.splice(pos, 1)
  }
}
</script>

<template>
    <div class="min-h-screen flex bg-gradient-to-br from-orange-50 to-[#faf6f2] dark:from-gray-950 dark:to-gray-900">

        <Sidebar variant="applicant" v-model:open="sidebarOpen" />

        <div class="flex-1 flex flex-col ml-0 md:ml-[var(--sidebar-width,5rem)]">

            <header class="sticky top-0 z-40 h-16 px-3 sm:px-6 flex items-center justify-between bg-white/80 backdrop-blur border-b border-gray-200 dark:bg-gray-900/80 dark:border-gray-800">
                <div class="flex items-center gap-4">
                    <button
                        class="md:hidden min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 transition"
                        aria-label="Open navigation menu"
                        @click="sidebarOpen = true"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <slot name="title">
                        <h1 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-100">Applicant Portal</h1>
                    </slot>
                </div>

                <div class="flex items-center gap-4">
                    <!-- Per-page header actions slot (kept for optional page-specific additions) -->
                    <slot name="header-actions" />

                    <!-- FAQ button — visible on all applicant pages -->
                    <button
                        @click="showFaqModal = true"
                        class="w-9 h-9 rounded-lg flex items-center justify-center bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 transition min-h-[44px] min-w-[44px]"
                        title="Frequently Asked Questions"
                        aria-label="Open FAQ"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-700 dark:text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </button>

                    <button
                        class="w-9 h-9 rounded-lg flex items-center justify-center bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 transition min-h-[44px] min-w-[44px]"
                        aria-label="Toggle dark mode"
                        @click="toggleDarkMode"
                    >
                        <FontAwesomeIcon :icon="['fas', isDarkMode ? 'moon' : 'sun']" class="text-gray-700 dark:text-gray-200" aria-hidden="true" />
                    </button>

                    <div class="flex items-center gap-3 px-3 py-1.5 rounded-full bg-white border border-gray-200 shadow-sm dark:bg-gray-900 dark:border-gray-700">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center bg-[#9E122C]/10 text-[#9E122C] font-semibold dark:text-white" aria-hidden="true">
                            {{ user?.firstname?.charAt(0) }}{{ user?.lastname?.charAt(0) }}
                        </div>
                        <div class="hidden sm:block leading-tight">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ user?.firstname }} {{ user?.lastname }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Applicant</p>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-3 sm:p-6 overflow-y-auto">
                <div class="w-full rounded-2xl p-4 sm:p-6 bg-white min-h-[calc(100vh-12rem)] shadow-sm border border-gray-200 dark:bg-gray-900 dark:border-gray-800">
                    <slot />
                </div>
            </main>

            <Footer />
        </div>

        <div
            v-if="isLoading"
            class="fixed inset-0 z-[999] bg-black/40 backdrop-blur-sm flex items-center justify-center"
            aria-live="polite"
            aria-label="Loading"
        >
            <div class="px-6 py-4 rounded-xl bg-white shadow-lg dark:bg-gray-900 flex flex-col items-center gap-3">
                <svg class="animate-spin h-8 w-8 text-[#9E122C] dark:text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-50" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <span class="font-medium text-gray-800 dark:text-gray-100">Loading, please wait...</span>
            </div>
        </div>

        <TermsandConditionsModal
            :show="showPrivacyModal"
            :can-close="false"
            @accept="handlePrivacyAccept"
            @cancel="handlePrivacyCancel"
        />

        <!-- FAQ Modal -->
        <transition name="modal-fade">
            <div
                v-if="showFaqModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="faq-modal-title"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showFaqModal = false"></div>

                <!-- Modal Panel -->
                <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden mx-2 sm:mx-4">

                    <!-- Header -->
                    <div class="flex items-center justify-between px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-200 dark:border-gray-700 flex-shrink-0" style="background-color:#9E122C;">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h2 id="faq-modal-title" class="text-base sm:text-lg font-bold text-white leading-tight truncate">Frequently Asked Questions</h2>
                                <p class="text-xs text-red-100 mt-0.5">Applicant Portal FAQs</p>
                            </div>
                        </div>
                        <button
                            @click="showFaqModal = false"
                            class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 text-white transition-colors min-h-[44px] min-w-[44px]"
                            aria-label="Close FAQ"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Scrollable Content -->
                    <div class="overflow-y-auto flex-1 px-4 sm:px-6 py-5 space-y-2 scrollbar-hide">

                        <!-- FAQ Items -->
                        <div
                            v-for="(faq, index) in faqItems"
                            :key="index"
                            class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden transition-all"
                        >
                            <button
                                type="button"
                                class="w-full flex items-center justify-between gap-3 px-4 py-3.5 text-left bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#9E122C]"
                                :aria-expanded="openFaqItems.includes(index)"
                                @click="toggleFaq(index)"
                            >
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-100 leading-snug pr-2">{{ faq.question }}</span>
                                <svg
                                    class="w-4 h-4 flex-shrink-0 text-gray-400 dark:text-gray-400 transition-transform duration-200"
                                    :class="openFaqItems.includes(index) ? 'rotate-180' : ''"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <transition
                                enter-active-class="transition-all duration-200 ease-out"
                                leave-active-class="transition-all duration-150 ease-in"
                                enter-from-class="opacity-0 max-h-0"
                                enter-to-class="opacity-100 max-h-96"
                                leave-from-class="opacity-100 max-h-96"
                                leave-to-class="opacity-0 max-h-0"
                            >
                                <div v-show="openFaqItems.includes(index)" class="px-4 py-3 bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700">
                                    <p v-if="faq.answer" class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ faq.answer }}</p>
                                    <div v-else-if="faq.answerItems" class="space-y-2">
                                        <p v-for="(item, idx) in faq.answerItems" :key="idx" class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed" :class="{ 'font-medium': idx === 0, 'pl-4': idx > 0 }">
                                            <span v-if="idx > 0" class="mr-2">•</span>{{ item }}
                                        </p>
                                    </div>
                                </div>
                            </transition>
                        </div>

                        <!-- Important Reminder -->
                        <div class="mt-4 rounded-xl border-2 border-amber-300 dark:border-amber-600 bg-amber-50 dark:bg-amber-900/20 p-4">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-amber-400 dark:bg-amber-600 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-amber-800 dark:text-amber-200 mb-1">Important Reminder</p>
                                    <p class="text-sm text-amber-700 dark:text-amber-300 leading-relaxed">
                                        Before clicking <strong>Submit Application</strong>, ensure that all information, program choices, grades, and uploaded documents are complete and correct. Once submitted, your application will be treated as final.
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Footer -->
                    <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex-shrink-0">
                        <div class="flex justify-end">
                            <button
                                @click="showFaqModal = false"
                                class="px-6 py-2.5 text-white rounded-lg transition font-medium min-h-[44px]"
                                style="background-color:#9E122C;"
                                onmouseover="this.style.backgroundColor='#7a0e22'"
                                onmouseout="this.style.backgroundColor='#9E122C'"
                            >
                                Close
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </transition>
    </div>
</template>
