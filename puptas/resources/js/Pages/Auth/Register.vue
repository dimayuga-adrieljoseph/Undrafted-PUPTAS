<script setup>
import { ref, onMounted } from "vue";
import { Head, Link, useForm, usePage, router } from "@inertiajs/vue3";
import { computed } from "vue";
import TermsandConditionsModal from "@/Pages/Modal/TermsandConditionsModal.vue";

const page = usePage();
const flashError = computed(() => page.props.flash?.error);

// IDP pending registration data - set when user came from IDP login
const pendingReg = computed(() => page.props.pending_registration);
const idpEmail = computed(() => pendingReg.value?.email ?? null);

// Redirect away if there's no pending IDP session
onMounted(() => {
    if (!pendingReg.value) {
        router.visit('/auth/idp/redirect');
        return;
    }

    const testPasser = page.props.test_passer_data;
    if (testPasser) {
        const hasScoreOverride = page.props.cutoff?.has_score_override;

        if (!hasScoreOverride && (testPasser.passer_status_id === 3 || testPasser.passer_status_id === 4)) {
            showTermsModal.value = false;
            showBlockedModal.value = true;
            if (testPasser.passer_status_id === 3) {
                blockedMessage.value = 'Registration is not available for Unqualified applicants.';
            } else {
                blockedMessage.value = 'Registration is currently unavailable at this time. Please wait for further announcements regarding open slots.';
            }
        }
        form.reference_number = testPasser.reference_number || '';
        form.firstname = testPasser.first_name || '';
        form.lastname = testPasser.surname || '';
        form.middlename = testPasser.middle_name || '';
        form.school = testPasser.shs_school || '';
    }
});

// Modal control variables
const showTermsModal = ref(true);
const showBlockedModal = ref(false);
const blockedMessage = ref('');

const form = useForm({
    lastname: "",
    firstname: "",
    middlename: "",
    sex: "",
    reference_number: "",
    school: "",
    schoolyear: "",
    dateGrad: "",
    strand: "",
    track: "",
});

// Allow only letters, spaces, hyphens, and apostrophes (for names)
const onlyLetters = (e) => {
    if (!/^[a-zA-ZÀ-ÿ\s'\-.]$/.test(e.key) && !['Backspace','Delete','ArrowLeft','ArrowRight','Tab','Home','End'].includes(e.key)) {
        e.preventDefault();
    }
};

// Allow only digits
const onlyDigits = (e) => {
    if (!/^\d$/.test(e.key) && !['Backspace','Delete','ArrowLeft','ArrowRight','Tab','Home','End'].includes(e.key)) {
        e.preventDefault();
    }
};

// Handle form submission - validate reference number first, then show modal
const handleSubmit = async () => {
    // Clear any previous reference number error
    form.clearErrors('reference_number');

    // Pre-validate the reference number before opening the modal
    try {
        const response = await fetch('/check-reference-number', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ reference_number: form.reference_number }),
        });

        const data = await response.json();

        if (!data.valid) {
            form.setError('reference_number', 'The reference number you entered is not recognized. Only admitted test passers are allowed to create an account. Please verify your reference number and try again.');
            return;
        }
    } catch {
        form.setError('reference_number', 'Unable to verify your reference number. Please check your connection and try again.');
        return;
    }

    // Reference number is valid — submit form directly since they already agreed
    form.post(route("register"), {
        onError: () => {
            // Errors display inline on the form
        },
    });
};

// Handle modal acceptance
const handleTermsAccept = () => {
    showTermsModal.value = false;
};

// Handle modal cancellation
const handleTermsCancel = () => {
    window.location.href = '/auth/idp/cancel-registration';
};
</script>

<template>
    <Head title="Register - PUPT Admission Portal" />

    <div
        class="min-h-screen bg-white-500 flex flex-col items-center justify-center p-4"
    >
        <!-- Main Container -->
        <div
            class="relative w-full max-w-6xl bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-clip border border-gray-200 dark:border-gray-700"
        >
            <!-- Header Section with School Colors -->
            <div class="bg-red-800 p-8">
                <div
                    class="flex flex-col md:flex-row items-center justify-between"
                >
                    <div class="text-center md:text-left">
                        <div
                            class="flex items-center justify-center md:justify-start space-x-3"
                        >
                            <div
                                class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-lg dark:bg-gray-800"
                            >
                                <span class="text-2xl font-bold text-red-800 dark:text-red-300"
                                    >P</span
                                >
                            </div>
                            <div>
                                <h1
                                    class="text-3xl md:text-4xl font-bold text-white tracking-tight dark:text-gray-900"
                                >
                                    PUP-T Admission
                                </h1>
                                <p class="text-yellow-200 font-medium">
                                    Polytechnic University of the Philippines -
                                    Taguig
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <div
                            class="bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2 text-center dark:bg-gray-900/10"
                        >
                            <p class="text-yellow-200 font-semibold">
                                Registration
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registration Form -->
            <div class="p-8 md:p-10">
                <!-- Cutoff Banner -->
                <div
                    v-if="$page.props.cutoff?.is_passed"
                    class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 rounded-lg flex items-start gap-3"
                >
                    <svg class="w-6 h-6 text-red-500 dark:text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="text-sm font-bold text-red-800 dark:text-red-300">Registration is closed.</p>
                        <p class="text-sm text-red-700 dark:text-red-400 mt-0.5">
                            The deadline for admissions ({{ $page.props.cutoff?.display }}) has already passed. You can no longer create a new account.
                        </p>
                    </div>
                </div>

                <!-- Server error banner -->
                <div
                    v-if="flashError"
                    class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 rounded-lg text-red-700 dark:text-red-400 text-sm"
                >
                    {{ flashError }}
                </div>

                <!-- IDP Account Notice -->
                <div
                    v-if="idpEmail"
                    class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg flex items-start gap-3"
                >
                    <svg class="w-5 h-5 text-blue-500 dark:text-blue-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-blue-800 dark:text-blue-300">Completing registration for your IDP account</p>
                        <p class="text-sm text-blue-700 dark:text-blue-400 mt-0.5">
                            Registering as <span class="font-mono font-semibold">{{ idpEmail }}</span>. Once done, you can log in with this account.
                        </p>
                    </div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left Side - Form Sections -->
                    <div class="lg:col-span-2">
                        <form @submit.prevent="handleSubmit" class="space-y-8">
                            <!-- Section 1: Personal Information -->
                            <div
                                class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-6 border border-gray-200 dark:border-gray-700 shadow-sm"
                            >
                                <div class="flex items-center mb-6">
                                    <div
                                        class="w-10 h-10 bg-gradient-to-br from-red-100 to-red-50 dark:from-red-900/30 dark:to-red-800/20 rounded-lg flex items-center justify-center mr-4"
                                    >
                                        <svg
                                            class="w-5 h-5 text-red-600 dark:text-red-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                            ></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2
                                            class="text-xl font-bold text-gray-800 dark:text-white"
                                        >
                                            Personal Information
                                        </h2>
                                        <p
                                            class="text-sm text-gray-600 dark:text-gray-400"
                                        >
                                            Your basic personal details
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="grid grid-cols-1 md:grid-cols-2 gap-6"
                                >
                                    <!-- Email - display only, sourced from IDP session -->
                                    <div class="md:col-span-2 space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            Email Address
                                        </label>
                                        <div class="w-full px-4 py-3 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-300 text-sm">
                                            {{ idpEmail }}
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            This is your IDP account email. Your local account will be created with this email.
                                        </p>
                                    </div>

                                    <!-- Reference Number - required, only test passers may register -->
                                    <div class="md:col-span-2 space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            Reference Number
                                            <span class="text-red-500 dark:text-red-300">*</span>
                                        </label>
                                        <input
                                            v-model="form.reference_number"
                                            type="text"
                                            required
                                            readonly
                                            autocomplete="off"
                                            class="w-full px-4 py-3 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-300 cursor-not-allowed focus:outline-none transition-all duration-200"
                                            placeholder="e.g., 2026-XXXX-001"
                                        />
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Enter the reference number from your admission test result. Only test passers are allowed to create an account.
                                        </p>
                                        <div
                                            v-if="form.errors.reference_number"
                                            class="text-red-500 text-sm mt-1 flex items-center dark:text-red-300"
                                        >
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                            </svg>
                                            {{ form.errors.reference_number }}
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <label
                                            class="block text-sm font-semibold text-gray-700 dark:text-gray-300"
                                        >
                                            Last Name
                                            <span class="text-red-500 dark:text-red-300">*</span>
                                        </label>
                                        <input
                                            v-model="form.lastname"
                                            type="text"
                                            required
                                            readonly
                                            autocomplete="family-name"
                                            class="w-full px-4 py-3 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-300 cursor-not-allowed focus:outline-none transition-all duration-200"
                                            placeholder="Enter your last name"
                                        />
                                        <div
                                            v-if="form.errors.lastname"
                                            class="text-red-500 text-sm mt-1 flex items-center dark:text-red-300"
                                        >
                                            <svg
                                                class="w-4 h-4 mr-1"
                                                fill="currentColor"
                                                viewBox="0 0 20 20"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                            {{ form.errors.lastname }}
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <label
                                            class="block text-sm font-semibold text-gray-700 dark:text-gray-300"
                                        >
                                            First Name
                                            <span class="text-red-500 dark:text-red-300">*</span>
                                        </label>
                                        <input
                                            v-model="form.firstname"
                                            type="text"
                                            required
                                            readonly
                                            autocomplete="given-name"
                                            class="w-full px-4 py-3 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-300 cursor-not-allowed focus:outline-none transition-all duration-200"
                                            placeholder="Enter your first name"
                                        />
                                        <div
                                            v-if="form.errors.firstname"
                                            class="text-red-500 text-sm mt-1 flex items-center dark:text-red-300"
                                        >
                                            <svg
                                                class="w-4 h-4 mr-1"
                                                fill="currentColor"
                                                viewBox="0 0 20 20"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                            {{ form.errors.firstname }}
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <label
                                            class="block text-sm font-semibold text-gray-700 dark:text-gray-300"
                                            >Middle Name</label
                                        >
                                        <input
                                            v-model="form.middlename"
                                            type="text"
                                            readonly
                                            autocomplete="additional-name"
                                            class="w-full px-4 py-3 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-300 cursor-not-allowed focus:outline-none transition-all duration-200"
                                            placeholder="Enter your middle name"
                                        />
                                    </div>

                                    <div class="space-y-2">

                                    </div>


                                </div>
                            </div>

                            <!-- Section 2: Academic Information -->
                            <div
                                class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-6 border border-gray-200 dark:border-gray-700 shadow-sm"
                            >
                                <div class="flex items-center mb-6">
                                    <div
                                        class="w-10 h-10 bg-gradient-to-br from-yellow-100 to-yellow-50 dark:from-yellow-900/30 dark:to-yellow-800/20 rounded-lg flex items-center justify-center mr-4"
                                    >
                                        <svg
                                            class="w-5 h-5 text-yellow-600 dark:text-yellow-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 14l9-5-9-5-9 5 9 5z"
                                            ></path>
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 14l9-5-9-5-9 5 9 5z"
                                                opacity="0.5"
                                            ></path>
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 14v6l9-5M12 20l-9-5"
                                            ></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2
                                            class="text-xl font-bold text-gray-800 dark:text-white"
                                        >
                                            Academic Information
                                        </h2>
                                        <p
                                            class="text-sm text-gray-600 dark:text-gray-400"
                                        >
                                            Your Senior High School background
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="grid grid-cols-1 md:grid-cols-2 gap-6"
                                >
                                    <div class="md:col-span-2 space-y-2">
                                        <label
                                            class="block text-sm font-semibold text-gray-700 dark:text-gray-300"
                                        >
                                            School
                                            <span class="text-red-500 dark:text-red-300">*</span>
                                        </label>
                                        <input
                                            v-model="form.school"
                                            type="text"
                                            required
                                            readonly
                                            class="w-full px-4 py-3 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-300 cursor-not-allowed focus:outline-none transition-all duration-200"
                                            placeholder="High School Name"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <label
                                            class="block text-sm font-semibold text-gray-700 dark:text-gray-300"
                                        >
                                            Graduate of:
                                            <span class="text-red-500 dark:text-red-300">*</span>
                                        </label>
                                        <select
                                            v-model="form.schoolyear"
                                            required
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                        >
                                            <option value="" disabled>Select an option</option>
                                            <option value="Senior High School of A.Y. 2025-2026">Senior High School A.Y. 2025-2026</option>
                                            <option value="Senior High School of Past School Years">Senior High School of Past School Years</option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label
                                            class="block text-sm font-semibold text-gray-700 dark:text-gray-300"
                                        >
                                            Graduation Date
                                            <span class="text-red-500 dark:text-red-300">*</span>
                                        </label>
                                        <input
                                            v-model="form.dateGrad"
                                            type="date"
                                            required
                                            autocomplete="off"
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <label
                                            class="block text-sm font-semibold text-gray-700 dark:text-gray-300"
                                        >
                                            Strand
                                            <span class="text-red-500 dark:text-red-300">*</span>
                                        </label>
                                        <select
                                            v-model="form.strand"
                                            required
                                            autocomplete="off"
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                        >
                                            <option value="" disabled>
                                                Select Strand
                                            </option>
                                            <option value="STEM">
                                                STEM (Science, Technology,
                                                Engineering & Mathematics)
                                            </option>
                                            <option value="HUMSS">
                                                HUMSS (Humanities & Social
                                                Sciences)
                                            </option>
                                            <option value="ABM">
                                                ABM (Accountancy, Business &
                                                Management)
                                            </option>
                                            <option value="TVL">
                                                TVL
                                                (Technical-Vocational-Livelihood)
                                            </option>
                                            <option value="ICT">
                                                ICT (Information and
                                                Communications Technology)
                                            </option>
                                            <option value="GAS">
                                                GAS (General Academic Strand)
                                            </option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label
                                            class="block text-sm font-semibold text-gray-700 dark:text-gray-300"
                                            >Specialization/Track</label
                                        >
                                        <input
                                            v-model="form.track"
                                            type="text"
                                            autocomplete="off"
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                            placeholder="e.g., ICT Programming, Cookery, Animation"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Hidden Terms Field for Fortify -->
                            <input type="hidden" name="terms" value="on" />

                            <!-- Submit Button -->
                            <div
                                class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4"
                            >
                                <a
                                    href="/auth/idp/cancel-registration"
                                    class="flex items-center text-gray-600 dark:text-gray-400 hover:text-red-700 dark:hover:text-red-400 font-medium transition-colors duration-200"
                                >
                                    <svg
                                        class="w-4 h-4 mr-2"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M10 19l-7-7m0 0l7-7m-7 7h18"
                                        ></path>
                                    </svg>
                                    Back to Login
                                </a>

                                <button
                                    type="submit"
                                    :disabled="form.processing || $page.props.cutoff?.is_passed"
                                    class="w-full sm:w-auto px-8 py-3.5 bg-gradient-to-r from-red-700 via-red-600 to-yellow-600 hover:from-red-800 hover:via-red-700 hover:to-yellow-700 text-white font-bold rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none dark:text-gray-900"
                                >
                                    <div
                                        class="flex items-center justify-center"
                                    >
                                        <svg
                                            v-if="form.processing"
                                            class="animate-spin -ml-1 mr-3 h-5 w-5 text-white dark:text-gray-900"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle
                                                class="opacity-25"
                                                cx="12"
                                                cy="12"
                                                r="10"
                                                stroke="currentColor"
                                                stroke-width="4"
                                            ></circle>
                                            <path
                                                class="opacity-75"
                                                fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                            ></path>
                                        </svg>
                                        <span>{{
                                            $page.props.cutoff?.is_passed
                                                ? "Registration Closed"
                                                : (form.processing ? "Creating Account..." : "Complete Registration")
                                        }}</span>
                                    </div>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Right Side - Info Panel -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-8 space-y-6">

                            <!-- Welcome Card -->
                            <div
                                class="bg-white rounded-xl p-6 border border-blue-200 shadow-sm"
                            >
                                <div class="flex items-start gap-4">
                                    <div
                                        class="w-12 h-12 rounded-full bg-blue-50 border border-blue-100 flex items-center justify-center shrink-0"
                                    >
                                        <svg
                                            class="w-6 h-6 text-blue-600"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"
                                            ></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3
                                            class="text-xl font-bold text-slate-800"
                                        >
                                            Welcome to PUP Taguig Admissions!
                                        </h3>
                                        <p class="mt-2 text-sm text-slate-600">
                                            Start your journey with the
                                            Polytechnic University of the
                                            Philippines - Taguig. Complete your
                                            registration to apply for your
                                            desired program.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Important Reminders -->
                            <div
                                class="bg-white rounded-xl p-6 border border-red-200 shadow-sm"
                            >
                                <div class="flex items-center mb-4">
                                    <div
                                        class="w-10 h-10 bg-red-50 rounded-lg border border-red-100 flex items-center justify-center mr-3"
                                    >
                                        <svg
                                            class="w-5 h-5 text-red-600"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M13 16h-1v-4h-1m1-4h.01M12 9a9 9 0 100 18 9 9 0 000-18z"
                                            ></path>
                                        </svg>
                                    </div>
                                    <h4 class="text-lg font-bold text-slate-800">
                                        Important Reminders
                                    </h4>
                                </div>

                                <ul class="space-y-3 text-sm text-slate-600">
                                    <li class="flex items-start gap-3">
                                        <span class="mt-1 inline-flex h-5 w-5 items-center justify-center rounded-full border border-red-200 text-xs font-bold text-red-600">1</span>
                                        <span>Ensure all details match your official documents</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <span class="mt-1 inline-flex h-5 w-5 items-center justify-center rounded-full border border-red-200 text-xs font-bold text-red-600">2</span>
                                        <span>Review your information carefully before submitting</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <span class="mt-1 inline-flex h-5 w-5 items-center justify-center rounded-full border border-red-200 text-xs font-bold text-red-600">3</span>
                                        <span>Double-check for typographical errors (name, birthday, email, etc.)</span>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <span class="mt-1 inline-flex h-5 w-5 items-center justify-center rounded-full border border-red-200 text-xs font-bold text-red-600">4</span>
                                        <span>Incomplete or incorrect information may delay your application</span>
                                    </li>
                                </ul>
                            </div>

                            <!-- After Registration -->
                            <div
                                class="bg-white rounded-xl p-6 border border-yellow-200 shadow-sm"
                            >
                                <div class="flex items-center mb-4">
                                    <div
                                        class="w-10 h-10 bg-yellow-50 rounded-lg border border-yellow-100 flex items-center justify-center mr-3"
                                    >
                                        <svg
                                            class="w-5 h-5 text-yellow-600"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                            ></path>
                                        </svg>
                                    </div>
                                    <h4 class="text-lg font-bold text-slate-800">
                                        After Registration
                                    </h4>
                                </div>

                                <div class="space-y-4">
                                    <div class="flex items-start">
                                        <div
                                            class="flex-shrink-0 w-8 h-8 bg-white rounded-full border-2 border-yellow-200 flex items-center justify-center mr-3"
                                        >
                                            <span class="text-sm font-bold text-yellow-600">1</span>
                                        </div>
                                        <div>
                                            <h5 class="font-semibold text-slate-800 text-sm">
                                                Complete Profile
                                            </h5>
                                            <p class="text-xs text-slate-600 mt-1">
                                                Upload required documents in your dashboard
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <div
                                            class="flex-shrink-0 w-8 h-8 bg-white rounded-full border-2 border-yellow-200 flex items-center justify-center mr-3"
                                        >
                                            <span class="text-sm font-bold text-yellow-600">2</span>
                                        </div>
                                        <div>
                                            <h5 class="font-semibold text-slate-800 text-sm">
                                                Follow Application Process
                                            </h5>
                                            <p class="text-xs text-slate-600 mt-1">
                                                The admission system will guide you as to what you need to do
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <div
                                            class="flex-shrink-0 w-8 h-8 bg-white rounded-full border-2 border-yellow-200 flex items-center justify-center mr-3"
                                        >
                                            <span class="text-sm font-bold text-yellow-600">3</span>
                                        </div>
                                        <div>
                                            <h5 class="font-semibold text-slate-800 text-sm">
                                                Track Application
                                            </h5>
                                            <p class="text-xs text-slate-600 mt-1">
                                                Monitor your admission status online
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Security Notice -->
                            <div
                                class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800/50 dark:to-gray-900/50 rounded-xl p-6 border border-gray-200 dark:border-gray-700"
                            >
                                <div class="flex items-center mb-3">
                                    <svg
                                        class="w-5 h-5 text-green-500 mr-2 dark:text-green-300"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                    <h4
                                        class="font-bold text-gray-800 dark:text-white"
                                    >
                                        Secure & Confidential
                                    </h4>
                                </div>
                                <p
                                    class="text-xs text-gray-600 dark:text-gray-400"
                                >
                                    Your information is protected with 256-bit
                                    SSL encryption. We never share your data
                                    with third parties.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Terms and Conditions Modal -->
        <TermsandConditionsModal
            :show="showTermsModal"
            :can-close="true"
            @accept="handleTermsAccept"
            @cancel="handleTermsCancel"
        />

        <!-- Blocked Applicant Modal -->
        <div v-if="showBlockedModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75 backdrop-blur-sm"></div>
            
            <div class="relative w-full max-w-md overflow-hidden bg-white rounded-2xl shadow-2xl transform transition-all dark:bg-gray-800 border border-gray-200 dark:border-gray-700 animate-[slideUp_0.3s_ease-out]">
                <div class="p-6 sm:p-8 flex flex-col items-center text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 mb-6 dark:bg-red-900/30">
                        <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    
                    <h3 class="mb-2 text-2xl font-bold text-gray-900 dark:text-white">Registration Closed</h3>
                    
                    <p class="mb-8 text-gray-600 dark:text-gray-300">
                        {{ blockedMessage }}
                    </p>
                    
                    <a href="/auth/idp/cancel-registration" class="w-full inline-flex justify-center items-center px-6 py-3.5 border border-transparent text-base font-medium rounded-xl text-white bg-red-700 hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors shadow-lg hover:shadow-xl dark:focus:ring-offset-gray-800">
                        Back to Login
                    </a>
                </div>
            </div>
        </div>

    </div>
</template>

<style>
.animation-delay-2000 {
    animation-delay: 2s;
}

.animation-delay-4000 {
    animation-delay: 4s;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Dark mode scrollbar */
.dark ::-webkit-scrollbar-track {
    background: #374151;
}

.dark ::-webkit-scrollbar-thumb {
    background: #6b7280;
}

.dark ::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

/* Form input focus effects */
input:focus,
select:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.dark input:focus,
.dark select:focus {
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
}

/* Smooth transitions */
* {
    transition: background-color 0.3s ease, border-color 0.3s ease;
}

/* Modal animation */
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
