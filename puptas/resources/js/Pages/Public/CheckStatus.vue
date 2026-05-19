<script setup>
import { ref } from "vue";
import { Head } from "@inertiajs/vue3";
import SlotConfirmationSuccessModal from "@/Pages/Modal/SlotConfirmationSuccessModal.vue";

/** Controls visibility of the IDP redirect reminder modal */
const showIdpModal = ref(false);

/** Close the reminder modal without redirecting */
function closeIdpModal() {
    showIdpModal.value = false;
}

/** Proceed to IDP registration after the user acknowledges the reminder */
function proceedToIdp() {
    showIdpModal.value = false;
    window.open(result.value.confirmation_url, '_blank', 'noopener,noreferrer');
}

const confirmingSlot = ref(false);
const showSuccessModal = ref(false);

async function confirmSlot() {
    confirmingSlot.value = true;
    try {
        await new Promise(resolve => setTimeout(resolve, 800));
        showSuccessModal.value = true;
    } finally {
        confirmingSlot.value = false;
    }
}

function handleSuccessModalClose() {
    showSuccessModal.value = false;
    showIdpModal.value = true;
}

// ── Reactive state ────────────────────────────────────────────────────────────

/** Reference number input value */
const referenceNumber = ref("");

/** First name input value */
const firstName = ref("");

/** Last name input value */
const lastName = ref("");

/** True while the API request is in-flight */
const loading = ref(false);

/** API response body on success, null otherwise */
const result = ref(null);

/** Field-level validation errors from a 422 response */
const errors = ref({});

/** True when the user has hit the rate limit (429) */
const rateLimited = ref(false);

/** Seconds remaining before the submit button re-enables after a 429 */
const rateLimitCountdown = ref(0);

/** Generic error message for unexpected failures */
const genericError = ref("");

// ── Countdown timer handle ────────────────────────────────────────────────────

let countdownInterval = null;

// ── Actions ───────────────────────────────────────────────────────────────────

/**
 * Submit the form to POST /api/public/admission-results.
 * Handles 200, 422, 429, and unexpected errors explicitly.
 */
async function submit() {
    loading.value = true;
    errors.value = {};
    result.value = null;
    genericError.value = "";

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        const response = await fetch("/api/public/admission-results", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-TOKEN": csrfToken ?? "",
            },
            body: JSON.stringify({
                referenceNumber: referenceNumber.value,
                firstName: firstName.value,
                lastName: lastName.value,
            }),
        });

        if (response.status === 200) {
            result.value = await response.json();
        } else if (response.status === 422) {
            const body = await response.json();
            errors.value = body.errors ?? {};
        } else if (response.status === 429) {
            const body = await response.json().catch(() => ({}));
            // Use retry_after from the response body, fall back to the Retry-After header, then 60s
            const retryAfterHeader = parseInt(response.headers.get("Retry-After") ?? "60", 10);
            const retryAfter = body.retry_after ?? retryAfterHeader;

            rateLimited.value = true;
            rateLimitCountdown.value = retryAfter;
            genericError.value = body.message ?? "Too many attempts. Please wait before trying again.";

            // Clear any existing interval before starting a new one
            if (countdownInterval) clearInterval(countdownInterval);

            countdownInterval = setInterval(() => {
                rateLimitCountdown.value -= 1;
                if (rateLimitCountdown.value <= 0) {
                    clearInterval(countdownInterval);
                    countdownInterval = null;
                    rateLimited.value = false;
                    rateLimitCountdown.value = 0;
                    genericError.value = "";
                }
            }, 1000);
        } else {
            genericError.value = "An unexpected error occurred. Please try again later.";
        }
    } catch {
        genericError.value = "Unable to reach the server. Please check your connection and try again.";
    } finally {
        loading.value = false;
    }
}

/**
 * Reset the form back to its initial state so the user can perform another lookup.
 */
function reset() {
    result.value = null;
    errors.value = {};
    referenceNumber.value = "";
    firstName.value = "";
    lastName.value = "";
    genericError.value = "";
}
</script>

<template>
    <Head title="Check Exam Result" />

    <!-- Full-page centered layout -->
    <div class="relative min-h-screen bg-cover bg-center bg-[url('/assets/images/2.jpg')]">
        <!-- Frosted overlay consistent with the rest of the app -->
        <div class="absolute inset-0 bg-white/40 backdrop-blur-[10px] saturate-[168%]"></div>

        <div class="relative z-10 min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8">
            <div class="w-full max-w-md">

                <!-- Card -->
                <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-2xl overflow-hidden">

                    <!-- Brand accent bar -->
                    <div class="h-1 bg-gradient-to-r from-[#800000] via-[#FFD700] to-[#800000]"></div>

                    <!-- Header -->
                    <div class="px-6 sm:px-8 pt-8 pb-6 text-center">
                        <div class="w-16 h-16 bg-gradient-to-br from-red-100 to-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="text-3xl font-bold text-red-800">P</span>
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900">Check Exam Result</h1>
                        <p v-if="!result" class="text-sm text-gray-600 mt-1">
                            Enter your reference number and name to check if you passed the entrance exam.
                        </p>
                    </div>

                    <div class="px-6 sm:px-8 pb-8">

                        <!-- ── Form (hidden once a result is shown) ──────────────── -->
                        <form v-if="!result" @submit.prevent="submit" class="space-y-5" novalidate>

                            <!-- Generic error banner -->
                            <div
                                v-if="genericError"
                                class="p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"
                                role="alert"
                            >
                                {{ genericError }}
                            </div>

                            <!-- Reference Number -->
                            <div>
                                <label
                                    for="referenceNumber"
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                >
                                    Reference Number
                                </label>
                                <input
                                    id="referenceNumber"
                                    v-model="referenceNumber"
                                    type="text"
                                    autocomplete="off"
                                    placeholder="e.g. 2026-XXX-XXX"
                                    maxlength="55"
                                    @keypress="(e) => { if (!/[\d\-]/.test(e.key)) e.preventDefault() }"
                                    @input="referenceNumber = referenceNumber.replace(/[^\d\-]/g, '')"
                                    :disabled="loading || rateLimited"
                                    :class="[
                                        'block w-full rounded-lg border px-3 py-2 text-sm shadow-sm transition-colors',
                                        'focus:outline-none focus:ring-2 focus:ring-[#800000] focus:border-[#800000]',
                                        'disabled:bg-gray-100 disabled:cursor-not-allowed',
                                        errors.referenceNumber
                                            ? 'border-red-500 bg-red-50'
                                            : 'border-gray-300 bg-white/80',
                                    ]"
                                    aria-describedby="referenceNumber-error"
                                />
                                <p
                                    v-if="errors.referenceNumber"
                                    id="referenceNumber-error"
                                    class="mt-1 text-xs text-red-600"
                                    role="alert"
                                >
                                    {{ errors.referenceNumber[0] }}
                                </p>
                            </div>

                            <!-- First Name -->
                            <div>
                                <label
                                    for="firstName"
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                >
                                    First Name
                                </label>
                                <input
                                    id="firstName"
                                    v-model="firstName"
                                    type="text"
                                    autocomplete="given-name"
                                    placeholder="e.g. Juan"
                                    maxlength="55"
                                    @keypress="(e) => { if (!/[a-zA-ZÀ-ÖØ-öø-ÿ\s\-']/.test(e.key)) e.preventDefault() }"
                                    @input="firstName = firstName.replace(/[^a-zA-ZÀ-ÖØ-öø-ÿ\s\-']/g, '')"
                                    :disabled="loading || rateLimited"
                                    :class="[
                                        'block w-full rounded-lg border px-3 py-2 text-sm shadow-sm transition-colors',
                                        'focus:outline-none focus:ring-2 focus:ring-[#800000] focus:border-[#800000]',
                                        'disabled:bg-gray-100 disabled:cursor-not-allowed',
                                        errors.firstName
                                            ? 'border-red-500 bg-red-50'
                                            : 'border-gray-300 bg-white/80',
                                    ]"
                                    aria-describedby="firstName-error"
                                />
                                <p
                                    v-if="errors.firstName"
                                    id="firstName-error"
                                    class="mt-1 text-xs text-red-600"
                                    role="alert"
                                >
                                    {{ errors.firstName[0] }}
                                </p>
                            </div>

                            <!-- Last Name -->
                            <div>
                                <label
                                    for="lastName"
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                >
                                    Last Name
                                </label>
                                <input
                                    id="lastName"
                                    v-model="lastName"
                                    type="text"
                                    autocomplete="family-name"
                                    placeholder="e.g. Dela Cruz"
                                    maxlength="55"
                                    @keypress="(e) => { if (!/[a-zA-ZÀ-ÖØ-öø-ÿ\s\-']/.test(e.key)) e.preventDefault() }"
                                    @input="lastName = lastName.replace(/[^a-zA-ZÀ-ÖØ-öø-ÿ\s\-']/g, '')"
                                    :disabled="loading || rateLimited"
                                    :class="[
                                        'block w-full rounded-lg border px-3 py-2 text-sm shadow-sm transition-colors',
                                        'focus:outline-none focus:ring-2 focus:ring-[#800000] focus:border-[#800000]',
                                        'disabled:bg-gray-100 disabled:cursor-not-allowed',
                                        errors.lastName
                                            ? 'border-red-500 bg-red-50'
                                            : 'border-gray-300 bg-white/80',
                                    ]"
                                    aria-describedby="lastName-error"
                                />
                                <p
                                    v-if="errors.lastName"
                                    id="lastName-error"
                                    class="mt-1 text-xs text-red-600"
                                    role="alert"
                                >
                                    {{ errors.lastName[0] }}
                                </p>
                            </div>

                            <!-- Rate-limit notice -->
                            <p
                                v-if="rateLimited"
                                class="text-sm text-red-600 text-center"
                                role="status"
                                aria-live="polite"
                            >
                                Too many attempts. Please try again in {{ rateLimitCountdown }}s.
                            </p>

                            <!-- Submit button -->
                            <button
                                type="submit"
                                :disabled="loading || rateLimited"
                                class="w-full py-2.5 rounded-lg font-semibold text-sm text-white shadow-md transition-all duration-200
                                       bg-gradient-to-r from-[#800000] to-[#9d0000]
                                       hover:from-[#600000] hover:to-[#800000]
                                       disabled:opacity-50 disabled:cursor-not-allowed
                                       focus:outline-none focus:ring-2 focus:ring-[#800000] focus:ring-offset-2"
                                aria-busy="loading"
                            >
                                <span v-if="loading">Checking...</span>
                                <span v-else-if="rateLimited">Try again in {{ rateLimitCountdown }}s</span>
                                <span v-else>Check Status</span>
                            </button>
                        </form>

                        <!-- ── Result card ─────────────────────────────────────── -->
                        <div v-if="result" class="space-y-4">

                            <!-- ✅ Qualified (status 1) -->
                            <div
                                v-if="result.qualified === true"
                                class="rounded-2xl overflow-hidden shadow-md border border-gray-200 bg-white"
                                role="status"
                                aria-live="polite"
                            >
                                <!-- Logo -->
                                <div class="pt-7 pb-2 flex justify-center">
                                    <img
                                        src="/assets/images/pup_taguig_logo.png"
                                        alt="PUP Taguig Logo"
                                        class="w-16 h-16 object-contain"
                                    />
                                </div>

                                <!-- Body -->
                                <div class="px-8 pb-7 pt-4 space-y-4">
                                    <p class="text-sm text-gray-800 leading-relaxed">
                                        Dear <strong class="text-[#800000]">{{ result.full_name }}</strong>,
                                    </p>

                                    <p class="text-sm text-gray-800 leading-relaxed font-semibold">
                                         <span class="text-[#800000]">CONGRATULATIONS!</span> 🎉
                                    </p>

                                    <p class="text-sm text-gray-700 leading-relaxed">
                                        We are pleased to inform you that you qualify to be admitted to <strong>PUP-Taguig Campus</strong> for the First Semester of the Academic Year 2026-2027.
                                    </p>

                                    <!-- Details box -->
                                    <div class="rounded-xl bg-gray-50 border border-gray-200 overflow-hidden text-sm divide-y divide-gray-200">
                                        <div class="flex items-center justify-between px-4 py-3">
                                            <span class="text-gray-400 text-xs font-semibold uppercase tracking-wider">Applicant Name</span>
                                            <span class="text-gray-900 font-semibold">{{ result.full_name }}</span>
                                        </div>
                                        <div class="flex items-center justify-between px-4 py-3">
                                            <span class="text-gray-400 text-xs font-semibold uppercase tracking-wider">Reference No.</span>
                                            <span class="text-gray-900 font-semibold">{{ result.reference_number }}</span>
                                        </div>
                                        <div class="flex items-center justify-between px-4 py-3">
                                            <span class="text-gray-400 text-xs font-semibold uppercase tracking-wider">Status</span>
                                            <span class="text-green-700 font-semibold">Qualified</span>
                                        </div>
                                    </div>

                                    <p class="text-sm text-gray-700 leading-relaxed">
                                        You may choose a curricular program you intend to enroll in, subject to fulfillment of college requirements and the availability of slots.
                                    </p>
                                    <p class="text-sm text-gray-700 leading-relaxed">
                                        Once again, congratulations on this remarkable achievement, and we look forward to meeting you at PUP-Taguig Campus!
                                    </p>
                                    <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide pt-2 border-t border-gray-100">
                                        PUP-Taguig Campus Admission and Registration Office
                                    </p>

                                    <!-- CTA — opens slot confirmation modal -->
                                    <button
                                        type="button"
                                        @click="confirmSlot"
                                        :disabled="confirmingSlot"
                                        class="flex items-center justify-center gap-2 w-full py-2.5 rounded-lg font-semibold text-sm text-white shadow-md
                                               bg-gradient-to-r from-[#800000] to-[#9d0000]
                                               hover:from-[#600000] hover:to-[#800000] transition-all duration-200
                                               disabled:opacity-50 disabled:cursor-not-allowed
                                               focus:outline-none focus:ring-2 focus:ring-[#800000] focus:ring-offset-2"
                                    >
                                        <svg v-if="confirmingSlot" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2z"/>
                                        </svg>
                                        {{ confirmingSlot ? 'Confirming...' : 'Click to Confirm Interview Slot' }}
                                    </button>
                                </div>
                            </div>

                            <!-- ⏳ Waitlisted (status 2) -->
                            <div
                                v-else-if="result.waitlisted === true"
                                class="rounded-2xl overflow-hidden shadow-md border border-gray-200 bg-white"
                                role="status"
                                aria-live="polite"
                            >
                                <!-- Logo -->
                                <div class="pt-7 pb-2 flex justify-center">
                                    <img
                                        src="/assets/images/pup_taguig_logo.png"
                                        alt="PUP Taguig Logo"
                                        class="w-16 h-16 object-contain"
                                    />
                                </div>

                                <!-- Body -->
                                <div class="px-8 pb-7 pt-4 space-y-4">
                                    <p class="text-sm text-gray-800 leading-relaxed">
                                        Dear <strong class="text-[#800000]">{{ result.full_name }}</strong>,
                                    </p>
                                    <p class="text-sm text-gray-700 leading-relaxed">
                                        Thank you for considering <strong>PUP-Taguig Campus</strong> for your higher education.
                                    </p>
                                    <p class="text-sm text-gray-700 leading-relaxed">
                                        Based on evaluation, we regret to inform you that your score in the PUP College Entrance Test did not place you in the Top 500 requirement of the Campus.
                                    </p>
                                    <p class="text-sm text-gray-700 leading-relaxed">
                                        Nevertheless, you might still be notified (via email) of the possible remaining slots, based on your evaluated rank. Admission to these slots, however, shall be on a first-come, first-served basis, and subject to specific academic program admission requirements.
                                    </p>
                                    <p class="text-sm text-gray-700 leading-relaxed">
                                        Since we cannot give you an assurance that a slot will be made available to you, we recommend you to still consider your admission options in other higher education institutions.
                                    </p>
                                    <p class="text-sm text-gray-700 leading-relaxed">
                                        We hope that you will still be able to pursue your career plans and be successful in your academic endeavor.
                                    </p>
                                    <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide pt-2 border-t border-gray-100">
                                        PUP-Taguig Campus Admission and Registration Office
                                    </p>
                                </div>
                            </div>

                            <!-- ❌ Not Qualified (status 3) -->
                            <div
                                v-else-if="result.not_qualified === true"
                                class="rounded-2xl overflow-hidden shadow-md border border-gray-200 bg-white"
                                role="status"
                                aria-live="polite"
                            >
                                <!-- Logo -->
                                <div class="pt-7 pb-2 flex justify-center">
                                    <img
                                        src="/assets/images/pup_taguig_logo.png"
                                        alt="PUP Taguig Logo"
                                        class="w-16 h-16 object-contain"
                                    />
                                </div>

                                <!-- Body -->
                                <div class="px-8 pb-7 pt-4 space-y-4">
                                    <p class="text-sm text-gray-800 leading-relaxed">
                                        Dear <strong class="text-[#800000]">{{ result.full_name }}</strong>,
                                    </p>
                                    <p class="text-sm text-gray-700 leading-relaxed">
                                        Thank you for considering Polytechnic University of the Philippines for your higher education.
                                    </p>

                                    <!-- Details box -->
                                    <div class="rounded-xl bg-gray-50 border border-gray-200 overflow-hidden text-sm divide-y divide-gray-200">
                                        <div class="flex items-center justify-between px-4 py-3">
                                            <span class="text-gray-400 text-xs font-semibold uppercase tracking-wider">Applicant Name</span>
                                            <span class="text-gray-900 font-semibold">{{ result.full_name }}</span>
                                        </div>
                                        <div class="flex items-center justify-between px-4 py-3">
                                            <span class="text-gray-400 text-xs font-semibold uppercase tracking-wider">Reference No.</span>
                                            <span class="text-gray-900 font-semibold">{{ result.reference_number }}</span>
                                        </div>
                                        <div class="flex items-center justify-between px-4 py-3">
                                            <span class="text-gray-400 text-xs font-semibold uppercase tracking-wider">Status</span>
                                            <span class="text-red-600 font-semibold">Not Qualified</span>
                                        </div>
                                    </div>

                                    <p class="text-sm text-gray-700 leading-relaxed">
                                        We regret to inform you that your score in the PUP College Entrance Test for Taguig Campus did not meet the qualifying threshold. We hope that you will still be able to pursue your career plans and be successful in your academic endeavor.
                                    </p>
                                    <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide pt-2 border-t border-gray-100">
                                        PUP-Taguig Campus Admission and Registration Office
                                    </p>
                                </div>
                            </div>

                            <!-- 🟠 Waitlisted Below Cut Off (status 4) -->
                            <div
                                v-else-if="result.waitlisted_below_cutoff === true"
                                class="rounded-2xl overflow-hidden shadow-md border border-orange-300 bg-white"
                                role="status"
                                aria-live="polite"
                            >
                                <!-- Logo -->
                                <div class="pt-7 pb-2 flex justify-center">
                                    <img
                                        src="/assets/images/pup_taguig_logo.png"
                                        alt="PUP Taguig Logo"
                                        class="w-16 h-16 object-contain"
                                    />
                                </div>

                                <!-- Body -->
                                <div class="px-8 pb-7 pt-4 space-y-4">
                                    <p class="text-sm text-gray-800 leading-relaxed">
                                        Dear <strong class="text-[#800000]">{{ result.full_name }}</strong>,
                                    </p>
                                    <p class="text-sm text-gray-700 leading-relaxed">
                                        Thank you for considering <strong>PUP-Taguig Campus</strong> for your higher education.
                                    </p>

                                    <!-- Details box -->
                                    <div class="rounded-xl bg-orange-50 border border-orange-200 overflow-hidden text-sm divide-y divide-orange-200">
                                        <div class="flex items-center justify-between px-4 py-3">
                                            <span class="text-gray-400 text-xs font-semibold uppercase tracking-wider">Applicant Name</span>
                                            <span class="text-gray-900 font-semibold">{{ result.full_name }}</span>
                                        </div>
                                        <div class="flex items-center justify-between px-4 py-3">
                                            <span class="text-gray-400 text-xs font-semibold uppercase tracking-wider">Reference No.</span>
                                            <span class="text-gray-900 font-semibold">{{ result.reference_number }}</span>
                                        </div>
                                        <div class="flex items-center justify-between px-4 py-3">
                                            <span class="text-gray-400 text-xs font-semibold uppercase tracking-wider">Status</span>
                                            <span class="text-orange-700 font-semibold">Waitlisted Below Cut Off</span>
                                        </div>
                                    </div>

                                    <p class="text-sm text-gray-700 leading-relaxed">
                                        We regret to inform you that your score in the PUP College Entrance Test did not meet the cut-off threshold for admission to PUP-Taguig Campus. The available slots for qualified and waitlisted applicants have been filled.
                                    </p>
                                    <p class="text-sm text-gray-700 leading-relaxed">
                                        We encourage you to explore other academic opportunities and wish you success in your future endeavors.
                                    </p>
                                    <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide pt-2 border-t border-gray-100">
                                        PUP-Taguig Campus Admission and Registration Office
                                    </p>
                                </div>
                            </div>

                            <!-- 🔍 No Record Found -->
                            <div
                                v-else
                                class="rounded-2xl overflow-hidden shadow-md border border-gray-200 bg-white"
                                role="status"
                                aria-live="polite"
                            >
                                <!-- Logo -->
                                <div class="pt-7 pb-2 flex justify-center">
                                    <img
                                        src="/assets/images/pup_taguig_logo.png"
                                        alt="PUP Taguig Logo"
                                        class="w-16 h-16 object-contain"
                                    />
                                </div>

                                <!-- Body -->
                                <div class="px-8 pb-7 pt-4 space-y-4">
                                    <div class="flex justify-center">
                                        <div class="w-12 h-12 rounded-full bg-gray-100 border border-gray-200 flex items-center justify-center">
                                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                            </svg>
                                        </div>
                                    </div>

                                    <p class="text-sm text-gray-800 leading-relaxed font-semibold text-center">
                                        No Record Found
                                    </p>

                                    <p class="text-sm text-gray-700 leading-relaxed">
                                        We could not find any record matching the information you provided. This may be because:
                                    </p>
                                    <ul class="text-sm text-gray-600 list-disc pl-5 space-y-1">
                                        <li>The reference number or name was entered incorrectly</li>
                                        <li>Your results have not yet been uploaded to the system</li>
                                        <li>The reference number does not exist in our records</li>
                                    </ul>

                                    <p class="text-sm text-gray-700 leading-relaxed">
                                        Please double-check your reference number and name, then try again. If the issue persists, contact the <strong>Admission and Registration Office</strong> for assistance.
                                    </p>
                                    <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide pt-2 border-t border-gray-100">
                                        PUP-Taguig Campus Admission and Registration Office
                                    </p>
                                </div>
                            </div>

                            <!-- Check another button -->
                            <button
                                type="button"
                                @click="reset"
                                class="w-full py-2.5 rounded-lg font-semibold text-sm text-[#800000] border-2 border-[#800000]
                                       hover:bg-[#800000] hover:text-white transition-all duration-200
                                       focus:outline-none focus:ring-2 focus:ring-[#800000] focus:ring-offset-2"
                            >
                                Check another
                            </button>
                        </div>

                    </div>
                </div>

                <!-- Footer note -->
                <p class="mt-4 text-center text-xs text-gray-500">
                    PUP-T Admission System &mdash; For inquiries, contact the Registrar's Office.
                </p>

            </div>
        </div>

        <!-- ── IDP Redirect Reminder Modal ──────────────────────────────────── -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="showIdpModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
                aria-labelledby="idp-modal-title"
                aria-describedby="idp-modal-desc"
                @keydown.esc="closeIdpModal"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                    @click="closeIdpModal"
                    aria-hidden="true"
                ></div>

                <!-- Panel -->
                <div class="relative z-10 w-full max-w-sm bg-white rounded-2xl shadow-2xl overflow-hidden">

                    <!-- Accent bar -->
                    <div class="h-1 bg-gradient-to-r from-[#800000] via-[#FFD700] to-[#800000]"></div>

                    <div class="px-6 pt-6 pb-7 space-y-5">

                        <!-- Icon + title -->
                        <div class="flex flex-col items-center text-center gap-3">
                            <div class="w-14 h-14 rounded-full bg-yellow-50 border border-yellow-200 flex items-center justify-center">
                                <!-- Warning / info icon -->
                                <svg class="w-7 h-7 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                </svg>
                            </div>
                            <h2 id="idp-modal-title" class="text-lg font-bold text-gray-900">
                                Before You Proceed
                            </h2>
                        </div>

                        <!-- Reminder message -->
                        <p id="idp-modal-desc" class="text-sm text-gray-700 leading-relaxed text-center">
                            Make sure that you will be using the
                            <strong class="text-[#800000]">same email on iApply</strong>
                            when logging in to IDP.
                        </p>

                        <!-- Actions -->
                        <div class="flex flex-col gap-3 pt-1">
                            <button
                                type="button"
                                @click="proceedToIdp"
                                class="w-full py-2.5 rounded-lg font-semibold text-sm text-white shadow-md
                                       bg-gradient-to-r from-[#800000] to-[#9d0000]
                                       hover:from-[#600000] hover:to-[#800000] transition-all duration-200
                                       focus:outline-none focus:ring-2 focus:ring-[#800000] focus:ring-offset-2"
                            >
                                I Understand, Proceed to IDP
                            </button>
                            <button
                                type="button"
                                @click="closeIdpModal"
                                class="w-full py-2.5 rounded-lg font-semibold text-sm text-gray-600 border border-gray-300
                                       hover:bg-gray-50 transition-all duration-200
                                       focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <SlotConfirmationSuccessModal
            :show="showSuccessModal"
            @close="handleSuccessModalClose"
        />

    </div>
</template>
