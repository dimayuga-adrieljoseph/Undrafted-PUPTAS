<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from "vue";
import { Head } from "@inertiajs/vue3";
import SlotConfirmationSuccessModal from "@/Pages/Modal/SlotConfirmationSuccessModal.vue";
import AdmissionResultDocument from "@/Components/Admission/AdmissionResultDocument.vue";
import AdmissionDocumentHeader from "@/Components/Admission/AdmissionDocumentHeader.vue";
import AdmissionNextSteps from "@/Components/Admission/AdmissionNextSteps.vue";

/** Academic year reported by this public result page. */
const ACADEMIC_YEAR = "SY 2026–2027";

/** Controls visibility of the IDP redirect reminder modal */
const showIdpModal = ref(false);

/** Panel element, used to move focus into the dialog when it opens */
const idpModalPanel = ref(null);

/**
 * Move focus into the dialog when it opens.
 *
 * This is also what makes the Escape-to-close handler reachable: `@keydown.esc`
 * lives on the dialog subtree, so the keypress is only delivered to it once
 * focus is inside. Without this, focus stays on <body> and Escape does nothing.
 */
watch(showIdpModal, async (open) => {
    if (!open) return;
    await nextTick();
    idpModalPanel.value?.focus();
});

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

// ── Lookup form fields ────────────────────────────────────────────────────────

/**
 * Single source of truth for the three lookup inputs. Rendering every field
 * from one config keeps spacing, validation and error wiring identical across
 * all three, while `digitsOnly` preserves the reference-number restriction
 * (digits and hyphens only).
 */
const fields = computed(() => [
    {
        id: "referenceNumber",
        label: "Reference Number",
        placeholder: "e.g. 2026-XXX-XXX",
        autocomplete: "off",
        model: referenceNumber,
        digitsOnly: true,
    },
    {
        id: "firstName",
        label: "First Name",
        placeholder: "e.g. Juan",
        autocomplete: "given-name",
        model: firstName,
        digitsOnly: false,
    },
    {
        id: "lastName",
        label: "Last Name",
        placeholder: "e.g. Dela Cruz",
        autocomplete: "family-name",
        model: lastName,
        digitsOnly: false,
    },
]);

/** Keep the reference number free of anything but digits and hyphens. */
function onFieldInput(field, event) {
    const raw = event.target.value;
    field.model.value = field.digitsOnly ? raw.replace(/[^\d-]/g, "") : raw;
    // Re-sync the DOM when the sanitiser shortened the value.
    if (field.digitsOnly && event.target.value !== field.model.value) {
        event.target.value = field.model.value;
    }
}

/** Block disallowed reference-number characters at the keypress level. */
function onFieldKeypress(field, event) {
    if (field.digitsOnly && !/[\d-]/.test(event.key)) {
        event.preventDefault();
    }
}

// ── Celebration ───────────────────────────────────────────────────────────────

/**
 * Horizontal placement of the one-shot gold particles shown behind the
 * qualified status. Kept as data so the markup stays a single v-for and the
 * whole decorative layer is trivially removable.
 */
const sparkPositions = ["8%", "17%", "27%", "38%", "49%", "60%", "71%", "82%", "91%"];

// ── Result state helpers ──────────────────────────────────────────────────────

/**
 * Stable key for the currently rendered result state. Drives the result
 * transition and keeps the non-qualified states visually distinct from one
 * another.
 */
const resultKey = computed(() => {
    if (!result.value) return "none";
    if (result.value.qualified === true) return "qualified";
    // Below-cutoff is checked before the standard waitlist so the two
    // waitlist states can never collapse into one another.
    if (result.value.waitlisted_below_cutoff === true) return "waitlisted-below-cutoff";
    if (result.value.waitlisted === true) return "waitlisted";
    if (result.value.not_qualified === true) return "not-qualified";
    return "no-record";
});

/** Applicant name for the result document; falls back to the submitted input. */
const applicantName = computed(() => {
    if (!result.value) return "";
    if (result.value.full_name) return result.value.full_name;
    return [result.value.first_name, result.value.last_name].filter(Boolean).join(" ");
});


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

/** Stop the rate-limit countdown if the page is torn down mid-wait. */
onBeforeUnmount(() => {
    if (countdownInterval) clearInterval(countdownInterval);
});
</script>

<template>
    <Head title="Admission Result" />

    <!--
        Page background. The existing PUPTAS campus photo is retained, but it is
        deliberately made quieter in the result state (dimmed + stronger overlay)
        so it never competes with the admission document.
    -->
    <div class="relative min-h-screen font-sans">
        <div
            aria-hidden="true"
            class="absolute inset-0 bg-cover bg-center bg-[url('/assets/images/2.jpg')]
                   transition-opacity duration-500"
            :class="result ? 'opacity-25' : 'opacity-100'"
        ></div>
        <div
            aria-hidden="true"
            class="absolute inset-0 transition-colors duration-500"
            :class="result
                ? 'bg-white/80 backdrop-blur-md'
                : 'bg-white/60 backdrop-blur-[6px]'"
        ></div>

        <!--
            min-h-screen + justify-center means the lookup card is optically
            centred on tall screens, while a tall result simply grows the page
            instead of being clipped.
        -->
        <main class="relative z-10 mx-auto flex min-h-screen w-full max-w-[860px] flex-col justify-center px-4 py-8 sm:px-6 sm:py-10 lg:py-14">
            <!-- Lookup: ~480px. Result: ~760px. The two states are never the same width. -->
            <div class="mx-auto w-full transition-[max-width] duration-500" :class="result ? 'max-w-[760px]' : 'max-w-[480px]'">

                <!-- ══ STATE A — LOOKUP ══════════════════════════════════════ -->
                <div v-if="!result" class="w-full">

                    <!-- Presentation header -->
                    <div class="text-center">
                        <img
                            src="/assets/images/pup_taguig_logo.png"
                            alt="Polytechnic University of the Philippines – Taguig Campus"
                            class="mx-auto w-20 h-20 object-contain drop-shadow-sm sm:w-24 sm:h-24"
                        />
                        <p class="mt-3 text-[10px] font-semibold uppercase tracking-[0.34em] text-[#8d6a1f] sm:text-[11px]">
                            PUP&ndash;Taguig
                        </p>
                        <h1 class="mt-1.5 text-xl font-bold tracking-tight text-[#1f1a12] sm:text-2xl">
                            Admission Results
                        </h1>
                        <p class="mt-2 text-sm leading-relaxed text-[#5b5445]">
                            Check your admission result for
                            <span class="font-semibold text-[#800000]">{{ ACADEMIC_YEAR }}</span>
                        </p>
                    </div>

                    <!-- Lookup card — a light touch of glass, the result state drops it entirely -->
                    <div class="mt-7 overflow-hidden rounded-xl border border-white/70 bg-white/80 shadow-[0_18px_45px_-28px_rgba(80,16,16,0.5),0_2px_8px_-4px_rgba(31,26,18,0.2)] backdrop-blur-md">
                        <div class="h-1 w-full bg-gradient-to-r from-[#800000] via-[#FFD700] to-[#800000]" aria-hidden="true"></div>

                        <form class="space-y-5 px-5 py-6 sm:px-7 sm:py-7" @submit.prevent="submit" novalidate>

                            <!-- Rate-limit notice — also carries the server's operational message -->
                            <div
                                v-if="rateLimited"
                                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-center"
                                role="status"
                                aria-live="polite"
                            >
                                <p class="text-sm font-semibold text-amber-800">Too many attempts.</p>
                                <p class="mt-0.5 text-sm text-amber-700">
                                    Please try again in <span class="font-semibold tabular-nums">{{ rateLimitCountdown }} seconds</span>.
                                </p>
                                <p v-if="genericError" class="mt-1 text-xs text-amber-700/90">{{ genericError }}</p>
                            </div>

                            <!-- Generic API / network error banner -->
                            <div
                                v-else-if="genericError"
                                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
                                role="alert"
                            >
                                {{ genericError }}
                            </div>


                            <!-- Lookup fields — driven by one config so spacing and
                                 validation wiring stay identical across all three -->
                            <div v-for="field in fields" :key="field.id">
                                <label
                                    :for="field.id"
                                    class="block text-[11px] font-semibold uppercase tracking-[0.16em] text-[#6b6455]"
                                >
                                    {{ field.label }}
                                </label>
                                <input
                                    :id="field.id"
                                    :value="field.model.value"
                                    @input="onFieldInput(field, $event)"
                                    @keypress="onFieldKeypress(field, $event)"
                                    type="text"
                                    :autocomplete="field.autocomplete"
                                    :placeholder="field.placeholder"
                                    maxlength="55"
                                    :disabled="loading || rateLimited"
                                    :class="[
                                        'block w-full rounded-lg border px-3.5 py-2.5 text-sm shadow-sm transition-colors',
                                        'placeholder:text-[#b3ab98]',
                                        'focus:outline-none focus:ring-2 focus:ring-[#800000]/30 focus:border-[#800000]',
                                        'disabled:bg-[#f3efe4] disabled:cursor-not-allowed disabled:text-[#9a9384]',
                                        errors[field.id]
                                            ? 'border-red-500 bg-red-50'
                                            : 'border-[#e2d9c6] bg-white',
                                    ]"
                                    :aria-invalid="errors[field.id] ? 'true' : 'false'"
                                    :aria-describedby="`${field.id}-error`"
                                />
                                <p
                                    v-if="errors[field.id]"
                                    :id="`${field.id}-error`"
                                    class="mt-1.5 text-xs text-red-600"
                                    role="alert"
                                >
                                    {{ errors[field.id][0] }}
                                </p>
                            </div>

                            <!-- Primary action -->
                            <button
                                type="submit"
                                :disabled="loading || rateLimited"
                                class="w-full rounded-lg bg-gradient-to-r from-[#800000] to-[#9d0000] px-4 py-3
                                       text-sm font-semibold text-white shadow-md transition-all duration-200
                                       hover:from-[#6b0000] hover:to-[#800000]
                                       focus:outline-none focus:ring-2 focus:ring-[#800000] focus:ring-offset-2
                                       disabled:cursor-not-allowed disabled:opacity-50"
                                aria-busy="loading"
                            >
                                <span v-if="loading">Checking...</span>
                                <span v-else-if="rateLimited">Try again in {{ rateLimitCountdown }}s</span>
                                <span v-else>Check My Result</span>
                            </button>

                            <!-- Progress state between "submitting" and "result" -->
                            <p
                                v-if="loading"
                                class="flex items-center justify-center gap-2 text-xs text-[#6b6455]"
                                role="status"
                                aria-live="polite"
                            >
                                <svg class="h-3.5 w-3.5 animate-spin text-[#800000]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Checking your admission result&hellip;
                            </p>
                        </form>
                    </div>

                    <!-- Privacy note -->
                    <p class="mt-5 text-center text-xs leading-relaxed text-[#6b6455]">
                        Your information is used to retrieve your admission result.
                    </p>
                </div>


                <!-- ══ STATE B — RESULT ══════════════════════════════════════ -->
                <!--
                    A single role="status" region wraps every result state so screen
                    readers announce the outcome once, and so the keyed <Transition>
                    can swap between states without re-announcing.
                -->
                <Transition name="result-rise" mode="out-in">
                    <div v-if="result" :key="resultKey" role="status" aria-live="polite" class="w-full">

                        <!-- ✓ QUALIFIED (status 1) -->
                        <AdmissionResultDocument v-if="result.qualified === true" glow>
                            <template #header>
                                <AdmissionDocumentHeader />
                            </template>

                            <!-- One-shot gold particles. Decorative, aria-hidden, painted
                                 behind the status, and never re-runs. -->
                            <div
                                aria-hidden="true"
                                class="pointer-events-none absolute inset-0 -z-10 overflow-hidden"
                            >
                                <span
                                    v-for="(left, i) in sparkPositions"
                                    :key="left"
                                    class="spark"
                                    :style="{ left, animationDelay: `${i * 90}ms` }"
                                ></span>
                            </div>

                            <!-- Hero: outcome first, then the applicant's name -->
                            <div class="mt-8 text-center sm:mt-10">
                                <p class="text-[11px] font-bold uppercase tracking-[0.36em] text-[#8d6a1f] sm:text-xs">
                                    Congratulations
                                </p>

                                <p class="mx-auto mt-3 max-w-[20ch] break-words text-3xl font-extrabold leading-tight tracking-tight text-[#1b1710] sm:text-5xl">
                                    {{ applicantName }}
                                </p>

                                <div class="mx-auto mt-5 h-px w-24 bg-[#FFD700]" aria-hidden="true"></div>

                                <p class="mt-5 text-2xl font-extrabold uppercase leading-tight tracking-[0.1em] text-[#800000] sm:text-4xl">
                                    You&rsquo;re Qualified
                                </p>

                                <p class="mt-2.5 text-sm leading-relaxed text-[#5b5445]">
                                    for admission to
                                    <span class="font-semibold text-[#4b4636]">PUP&ndash;Taguig</span>
                                    &middot;
                                    <span class="font-semibold text-[#4b4636]">{{ ACADEMIC_YEAR }}</span>
                                </p>
                            </div>

                            <!-- Applicant information -->
                            <div class="mt-8 rounded-lg border border-[#e8dfcd] bg-white/85 px-5 py-5 text-center shadow-[0_1px_3px_rgba(31,26,18,0.05)] sm:px-6">
                                <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-[#9a8a63] sm:text-[11px]">
                                    Application Reference
                                </p>
                                <p class="mt-1.5 break-all text-xl font-bold tracking-tight text-[#800000] sm:text-2xl">
                                    {{ result.reference_number }}
                                </p>
                            </div>


                            <p class="mt-6 text-sm leading-relaxed text-[#4b4636]">
                                We are pleased to inform you that you qualify to be admitted to
                                <strong class="font-semibold text-[#800000]">PUP&ndash;Taguig Campus</strong>
                                for the First Semester of the Academic Year 2026&ndash;2027.
                            </p>
                            <p class="mt-3 text-sm leading-relaxed text-[#5b5445]">
                                You may choose a curricular program you intend to enroll in, subject to
                                fulfillment of college requirements and the availability of slots.
                            </p>

                            <AdmissionNextSteps />

                            <!-- Next action -->
                            <div class="mt-7 rounded-lg border border-[#e8dfcd] bg-white/85 px-5 py-6 text-center sm:px-6">
                                <p class="text-sm font-semibold text-[#1f1a12]">
                                    Your admission journey continues with the next step.
                                </p>
                                <p class="mt-1.5 text-xs leading-relaxed text-[#6b6455]">
                                    Confirming your interview slot does not mean that you are officially enrolled.
                                </p>

                                <button
                                    type="button"
                                    @click="confirmSlot"
                                    :disabled="confirmingSlot"
                                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg
                                           bg-gradient-to-r from-[#800000] to-[#9d0000] px-4 py-3
                                           text-sm font-semibold text-white shadow-md transition-all duration-200
                                           hover:from-[#6b0000] hover:to-[#800000]
                                           focus:outline-none focus:ring-2 focus:ring-[#800000] focus:ring-offset-2
                                           disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <svg v-if="confirmingSlot" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2z"/>
                                    </svg>
                                    {{ confirmingSlot ? 'Confirming...' : 'Confirm Interview Slot' }}
                                </button>
                            </div>
                        </AdmissionResultDocument>


                        <!-- ⏳ WAITLISTED (status 2)
                             Deliberately never reuses the qualified layout: no
                             congratulatory language, no "Qualified" status, no green
                             success styling and no interview-slot CTA. -->
                        <AdmissionResultDocument v-else-if="result.waitlisted === true">
                            <template #header>
                                <AdmissionDocumentHeader />
                            </template>

                            <div class="mt-8 text-center sm:mt-10">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border border-[#ddd5c4] bg-[#f7f5ef]" aria-hidden="true">
                                    <svg class="h-7 w-7 text-[#7d7560]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>

                                <p class="mx-auto mt-2 max-w-[22ch] break-words text-2xl font-bold leading-tight text-[#1b1710] sm:text-3xl">
                                    {{ applicantName }}
                                </p>

                                <p class="mt-5 text-2xl font-extrabold uppercase leading-tight tracking-[0.08em] text-[#4b4636] sm:text-4xl">
                                    Your Application<br class="sm:hidden" /> Is Waitlisted
                                </p>
                                <p class="mt-2.5 text-sm leading-relaxed text-[#5b5445]">
                                    <span class="font-semibold uppercase tracking-wider text-[#4b4636]">Waitlisted</span>
                                    &middot; PUP&ndash;Taguig
                                    <span class="whitespace-nowrap">&middot; {{ ACADEMIC_YEAR }}</span>
                                </p>
                            </div>

                            <!-- Applicant information -->
                            <div class="mt-8 rounded-lg border border-[#e8dfcd] bg-white/85 px-5 py-5 text-center sm:px-6">
                                <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-[#9a8a63] sm:text-[11px]">
                                    Application Reference
                                </p>
                                <p class="mt-1.5 break-all text-xl font-bold tracking-tight text-[#4b4636] sm:text-2xl">
                                    {{ result.reference_number }}
                                </p>
                            </div>

                            <p class="mt-6 text-sm leading-relaxed text-[#4b4636]">
                                Thank you for considering PUP for your higher education.
                            </p>
                            <p class="mt-3 text-sm leading-relaxed text-[#4b4636]">
                                Your application did not qualify for admission at this time and has been
                                placed on the <strong class="font-semibold">PUP&ndash;Taguig waitlist</strong>.
                                The waitlist is an official record that your application was received and
                                evaluated successfully.
                            </p>
                            <p class="mt-3 text-sm leading-relaxed text-[#5b5445]">
                                If a slot becomes available, the Admission and Registration Office will
                                notify you through the contact details you provided during
                                registration. No action is required from you at this time.
                            </p>
                        </AdmissionResultDocument>


                        <!-- 🟠 WAITLISTED — BELOW CUT-OFF (status 4)
                             Kept visually and verbally distinct from the standard
                             waitlist above: amber accent, a "Below Cut-off" qualifier,
                             and the full existing backend copy. -->
                        <AdmissionResultDocument v-else-if="result.waitlisted_below_cutoff === true">
                            <template #header>
                                <AdmissionDocumentHeader />
                            </template>

                            <div class="mt-8 text-center sm:mt-10">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border border-[#e8d6ad] bg-[#fdf8ec]" aria-hidden="true">
                                    <svg class="h-7 w-7 text-[#9a7b25]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                    </svg>
                                </div>

                                <p class="mt-5 inline-flex items-center rounded-full border border-[#e8d6ad] bg-[#fdf8ec] px-3 py-1 text-[10px] font-bold uppercase tracking-[0.2em] text-[#8a6a1c] sm:text-[11px]">
                                    Below Cut-off
                                </p>
                                <p class="mx-auto mt-3 max-w-[22ch] break-words text-2xl font-bold leading-tight text-[#1b1710] sm:text-3xl">
                                    {{ applicantName }}
                                </p>

                                <p class="mt-5 text-2xl font-extrabold uppercase leading-tight tracking-[0.08em] text-[#8a6a1c] sm:text-4xl">
                                    Your Application<br class="sm:hidden" /> Is Waitlisted
                                </p>
                                <p class="mt-2.5 text-sm leading-relaxed text-[#5b5445]">
                                    <span class="font-semibold uppercase tracking-wider text-[#8a6a1c]">Waitlisted &middot; Below Cut-off</span>
                                    <span class="whitespace-nowrap">&middot; {{ ACADEMIC_YEAR }}</span>
                                </p>
                            </div>

                            <div class="mt-8 rounded-lg border border-[#e8dfcd] bg-white/85 px-5 py-5 text-center sm:px-6">
                                <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-[#9a8a63] sm:text-[11px]">
                                    Application Reference
                                </p>
                                <p class="mt-1.5 break-all text-xl font-bold tracking-tight text-[#8a6a1c] sm:text-2xl">
                                    {{ result.reference_number }}
                                </p>
                            </div>

                            <p class="mt-6 text-sm leading-relaxed text-[#4b4636]">
                                Based on evaluation, we regret to inform you that your score in the
                                PUP College Entrance Test did not place you in the
                                <strong class="font-semibold">Top 500 requirement</strong> of the Campus.
                            </p>
                            <p class="mt-3 text-sm leading-relaxed text-[#5b5445]">
                                Nevertheless, you might still be notified (via email) of the possible
                                remaining slots, based on your evaluated rank. Admission to these
                                slots, however, shall be on a first-come, first-served basis, and
                                subject to specific academic program admission requirements.
                            </p>
                            <p class="mt-3 text-sm leading-relaxed text-[#5b5445]">
                                Since we cannot give you an assurance that a slot will be made available
                                to you, we recommend you to still consider your admission options in
                                other higher education institutions.
                            </p>
                            <p class="mt-3 text-sm leading-relaxed text-[#5b5445]">
                                We hope that you will still be able to pursue your career plans and be
                                successful in your academic endeavor.
                            </p>
                        </AdmissionResultDocument>


                        <!-- ✖ NOT QUALIFIED (status 3)
                             A formal, restrained result — not an error page. No red
                             interface, no celebratory language. -->
                        <AdmissionResultDocument v-else-if="result.not_qualified === true">
                            <template #header>
                                <AdmissionDocumentHeader />
                            </template>

                            <div class="mt-8 text-center sm:mt-10">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border border-[#e5ddd0] bg-[#f8f6f1]" aria-hidden="true">
                                    <svg class="h-7 w-7 text-[#6b6455]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 9v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>

                                <p class="mx-auto mt-5 max-w-[22ch] break-words text-2xl font-bold leading-tight text-[#1b1710] sm:text-3xl">
                                    {{ applicantName }}
                                </p>

                                <div class="mx-auto mt-5 h-px w-24 bg-[#e0d7c4]" aria-hidden="true"></div>

                                <p class="mt-5 text-2xl font-extrabold uppercase leading-tight tracking-[0.1em] text-[#4b4636] sm:text-4xl">
                                    Not Qualified
                                </p>
                                <p class="mt-2.5 text-sm leading-relaxed text-[#5b5445]">
                                    PUP&ndash;Taguig &middot; {{ ACADEMIC_YEAR }}
                                </p>
                            </div>

                            <div class="mt-8 rounded-lg border border-[#e8dfcd] bg-white/85 px-5 py-5 text-center sm:px-6">
                                <p class="text-[10px] font-bold uppercase tracking-[0.28em] text-[#9a8a63] sm:text-[11px]">
                                    Application Reference
                                </p>
                                <p class="mt-1.5 break-all text-xl font-bold tracking-tight text-[#4b4636] sm:text-2xl">
                                    {{ result.reference_number }}
                                </p>
                            </div>

                            <p class="mt-6 text-sm leading-relaxed text-[#4b4636]">
                                Thank you for considering PUP for your higher education.
                            </p>
                            <p class="mt-3 text-sm leading-relaxed text-[#5b5445]">
                                We regret to inform you that your score in the PUP College Entrance Test
                                for Taguig Campus did not meet the qualifying threshold.
                            </p>
                            <p class="mt-3 text-sm leading-relaxed text-[#5b5445]">
                                We hope that you will still be able to pursue your career plans and be
                                successful in your academic endeavor.
                            </p>
                        </AdmissionResultDocument>


                        <!-- 🔍 NO RECORD FOUND
                             Intentionally NOT rendered as an admission document: it uses a
                             plain surface with no masthead, watermark or gold rule, so it
                             can never be mistaken for an official result. -->
                        <div v-else class="overflow-hidden rounded-lg border border-[#e4e0d5] bg-white/90 shadow-[0_18px_45px_-32px_rgba(80,16,16,0.4)]">
                            <div class="h-1 w-full bg-[#800000]" aria-hidden="true"></div>

                            <div class="px-5 py-8 text-center sm:px-8 sm:py-10">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border border-gray-200 bg-gray-50" aria-hidden="true">
                                    <svg class="h-7 w-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>

                                <h2 class="mt-5 text-xl font-extrabold uppercase tracking-[0.06em] text-[#1f1a12] sm:text-2xl">
                                    We Couldn&rsquo;t Find Your Record
                                </h2>

                                <p
                                    v-if="applicantName"
                                    class="mt-2 text-sm font-semibold text-[#800000]"
                                >
                                    {{ applicantName }}
                                </p>

                                <p class="mt-4 text-sm leading-relaxed text-[#4b4636]">
                                    We could not find any record matching the information you provided.
                                    This may be because:
                                </p>
                                <ul class="mx-auto mt-3 max-w-md space-y-1.5 text-left text-sm text-[#5b5445]">
                                    <li class="flex gap-2.5">
                                        <span class="mt-[7px] h-1 w-1 shrink-0 rounded-full bg-[#c9a227]" aria-hidden="true"></span>
                                        The reference number or name was entered incorrectly
                                    </li>
                                    <li class="flex gap-2.5">
                                        <span class="mt-[7px] h-1 w-1 shrink-0 rounded-full bg-[#c9a227]" aria-hidden="true"></span>
                                        Your results have not yet been uploaded to the system
                                    </li>
                                    <li class="flex gap-2.5">
                                        <span class="mt-[7px] h-1 w-1 shrink-0 rounded-full bg-[#c9a227]" aria-hidden="true"></span>
                                        The reference number does not exist in our records
                                    </li>
                                </ul>

                                <p class="mx-auto mt-5 max-w-md text-sm leading-relaxed text-[#5b5445]">
                                    Please double-check your reference number and name, then try again.
                                    If the issue persists, contact the
                                    <strong class="font-semibold text-[#4b4636]">Admission and Registration Office</strong>
                                    for assistance.
                                </p>

                                <p class="mt-6 border-t border-gray-100 pt-4 text-[10px] font-semibold uppercase tracking-[0.2em] text-gray-400">
                                    PUP&ndash;Taguig Campus Admission and Registration Office
                                </p>
                            </div>
                        </div>
                    </div>
                </Transition>

                <!-- Secondary action: return to the lookup state without a page reload -->
                <div v-if="result" class="mt-6 text-center">
                    <button
                        type="button"
                        @click="reset"
                        class="inline-flex items-center gap-2 rounded-lg border border-[#cfc4a8] bg-white/70 px-5 py-2.5
                               text-sm font-semibold text-[#800000] transition-colors duration-200
                               hover:border-[#800000] hover:bg-white
                               focus:outline-none focus:ring-2 focus:ring-[#800000] focus:ring-offset-2"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 4v6h6M20 20v-6h-6M20 9A8.5 8.5 0 005.6 6M4 15a8.5 8.5 0 0014.4 3"/>
                        </svg>
                        Check Another Result
                    </button>
                </div>
            </div>

            <!-- Page footer -->
            <p class="mt-8 text-center text-xs leading-relaxed text-[#6b6455]">
                PUP&ndash;Taguig Admission System &mdash; For inquiries, please contact the Admission and Registration Office at
                <a href="mailto:taguig@pup.edu.ph" class="underline hover:text-[#800000]">taguig@pup.edu.ph</a>
                /
                <a href="mailto:puptadmission@gmail.com" class="underline hover:text-[#800000]">puptadmission@gmail.com</a>
            </p>
        </main>


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

                <!-- Panel. tabindex="-1" lets the dialog receive focus so that
                     Escape (handled by the parent @keydown.esc) is delivered. -->
                <div
                    ref="idpModalPanel"
                    tabindex="-1"
                    class="relative z-10 w-full max-w-sm bg-white rounded-2xl shadow-2xl overflow-hidden focus:outline-none"
                >

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

<style scoped>
/* ── Result transition ──────────────────────────────────────────────────────
   Submitting -> Checking -> Result. A short fade + slight rise + a whisper of
   scale. Deliberately restrained: no bouncing, no long easing. */
.result-rise-enter-active {
    transition: opacity 320ms ease-out, transform 320ms ease-out;
}
.result-rise-leave-active {
    transition: opacity 150ms ease-in;
}
.result-rise-enter-from {
    opacity: 0;
    transform: translateY(12px) scale(0.985);
}
.result-rise-enter-to {
    opacity: 1;
    transform: translateY(0) scale(1);
}
.result-rise-leave-from {
    opacity: 1;
}
.result-rise-leave-to {
    opacity: 0;
}

/* ── Celebration ─────────────────────────────────────────────────────────────
   A restrained, one-shot scatter of small gold particles behind the qualified
   status. Absolutely positioned (no layout shift), aria-hidden, and it never
   re-runs — it fires once when the result appears. */
.spark {
    position: absolute;
    bottom: 14%;
    width: 5px;
    height: 5px;
    border-radius: 9999px;
    background: #FFD700;
    box-shadow: 0 0 8px 1px rgba(255, 215, 0, 0.65);
    animation: puptas-spark 1500ms ease-out forwards;
}

@keyframes puptas-spark {
    0% {
        opacity: 0;
        transform: translateY(0) scale(0.4);
    }
    25% {
        opacity: 0.9;
    }
    100% {
        opacity: 0;
        transform: translateY(-90px) scale(1);
    }
}

/* Respect reduced-motion: kill the transition and the particles entirely. */
@media (prefers-reduced-motion: reduce) {
    .result-rise-enter-active,
    .result-rise-leave-active {
        transition-duration: 1ms;
    }
    .spark {
        animation: none;
        opacity: 0;
    }
}
</style>
