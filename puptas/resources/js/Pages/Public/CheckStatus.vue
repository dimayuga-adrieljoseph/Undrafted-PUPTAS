<script setup>
import { ref } from "vue";
import { Head } from "@inertiajs/vue3";

// ── Reactive state ────────────────────────────────────────────────────────────

/** Reference number input value */
const referenceNumber = ref("");

/** Email input value */
const email = ref("");

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
 * Submit the form to POST /api/public/check-status.
 * Handles 200, 422, 429, and unexpected errors explicitly.
 */
async function submit() {
    loading.value = true;
    errors.value = {};
    result.value = null;
    genericError.value = "";

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        const response = await fetch("/api/public/check-status", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json",
                "X-CSRF-TOKEN": csrfToken ?? "",
            },
            body: JSON.stringify({
                referenceNumber: referenceNumber.value,
                email: email.value,
            }),
        });

        if (response.status === 200) {
            result.value = await response.json();
        } else if (response.status === 422) {
            const body = await response.json();
            errors.value = body.errors ?? {};
        } else if (response.status === 429) {
            rateLimited.value = true;
            rateLimitCountdown.value = 60;

            // Clear any existing interval before starting a new one
            if (countdownInterval) clearInterval(countdownInterval);

            countdownInterval = setInterval(() => {
                rateLimitCountdown.value -= 1;
                if (rateLimitCountdown.value <= 0) {
                    clearInterval(countdownInterval);
                    countdownInterval = null;
                    rateLimited.value = false;
                    rateLimitCountdown.value = 0;
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
    email.value = "";
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
                        <p class="text-sm text-gray-600 mt-1">
                            Enter your reference number and email to check if you passed the entrance exam.
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
                                    placeholder="e.g. 2026-000123"
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

                            <!-- Email Address -->
                            <div>
                                <label
                                    for="email"
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                >
                                    Email Address
                                </label>
                                <input
                                    id="email"
                                    v-model="email"
                                    type="email"
                                    autocomplete="email"
                                    placeholder="you@example.com"
                                    :disabled="loading || rateLimited"
                                    :class="[
                                        'block w-full rounded-lg border px-3 py-2 text-sm shadow-sm transition-colors',
                                        'focus:outline-none focus:ring-2 focus:ring-[#800000] focus:border-[#800000]',
                                        'disabled:bg-gray-100 disabled:cursor-not-allowed',
                                        errors.email
                                            ? 'border-red-500 bg-red-50'
                                            : 'border-gray-300 bg-white/80',
                                    ]"
                                    aria-describedby="email-error"
                                />
                                <p
                                    v-if="errors.email"
                                    id="email-error"
                                    class="mt-1 text-xs text-red-600"
                                    role="alert"
                                >
                                    {{ errors.email[0] }}
                                </p>
                            </div>

                            <!-- Rate-limit notice -->
                            <p
                                v-if="rateLimited"
                                class="text-sm text-red-600 text-center"
                                role="status"
                                aria-live="polite"
                            >
                                Too many attempts. Please try again later.
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

                            <!-- Qualified -->
                            <div
                                v-if="result.qualified === true"
                                class="rounded-xl border border-green-200 bg-green-50 p-6 text-center"
                                role="status"
                                aria-live="polite"
                            >
                                <!-- Success icon -->
                                <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>

                                <p class="text-lg font-semibold text-green-800 mb-2">
                                    {{ result.message }}
                                </p>

                                <div class="inline-block mt-1 px-4 py-1.5 rounded-full bg-green-100 border border-green-300">
                                    <span class="text-sm font-medium text-green-700">
                                        {{ result.batch_number }}
                                    </span>
                                </div>
                            </div>

                            <!-- Not qualified / no match -->
                            <div
                                v-else
                                class="rounded-xl border border-gray-200 bg-gray-50 p-6 text-center"
                                role="status"
                                aria-live="polite"
                            >
                                <!-- Neutral icon -->
                                <div class="w-14 h-14 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-7 h-7 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z" />
                                    </svg>
                                </div>

                                <p class="text-base font-medium text-gray-700">
                                    {{ result.message }}
                                </p>
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
    </div>
</template>
