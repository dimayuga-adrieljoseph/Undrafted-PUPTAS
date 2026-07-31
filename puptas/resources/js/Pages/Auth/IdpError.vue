<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

const props = defineProps({
    referenceCode: {
        type: [Number, String, null],
        default: null,
    },
});

const page = usePage();
const rawError = computed(() => page.props.errors?.idp || null);

// Map internal error keys to user-facing messages.
// idp_uuid_conflict / email_collision require manual registrar intervention —
// the message must be clear enough that the user knows not to retry on their own.
const errorMessage = computed(() => {
    if (rawError.value === "idp_uuid_conflict") {
        return "We could not verify your identity because your account information has a conflict that needs to be resolved manually. Please contact the Admission Office for assistance.";
    }
    if (rawError.value === "email_collision") {
        return "Your new email address is already linked to another account in our system. This needs to be resolved manually before you can log in. Please contact the Admission Office for assistance.";
    }
    return rawError.value || "An unknown authentication error occurred.";
});

// Show the registrar contact block and reference code only for the two
// conflict/collision cases where manual intervention is required.
const requiresManualResolution = computed(() =>
    rawError.value === "idp_uuid_conflict" || rawError.value === "email_collision"
);
</script>

<template>
    <Head title="Authentication Failed - PUPT Admission Portal" />

    <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-4">
        <!-- Main Container -->
        <div class="relative w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-200">
            <!-- Header Section with School Colors -->
            <div class="bg-red-800 p-6 flex flex-col items-center">
                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center shadow-lg mb-4">
                    <span class="text-3xl font-bold text-red-800">P</span>
                </div>
                <h1 class="text-2xl font-bold text-white tracking-tight text-center">
                    PUP-T Admission
                </h1>
            </div>

            <!-- Error Content -->
            <div class="p-8 text-center space-y-6">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 mb-2">
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <h2 class="text-xl font-bold text-gray-900">Authentication Failed</h2>

                <div class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium text-left">
                    {{ errorMessage }}
                </div>

                <!-- Registrar contact block — only shown for conflict/collision errors -->
                <div v-if="requiresManualResolution" class="p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800 text-left space-y-2">
                    <p class="font-semibold">Action required:</p>
                    <p>Please bring a valid ID and visit or email the <strong>Admission Office / Registrar</strong> to resolve this issue.</p>
                    <p v-if="referenceCode" class="mt-2 text-xs text-amber-700">
                        Reference code for support: <span class="font-mono font-bold">{{ referenceCode }}</span>
                    </p>
                </div>

                <div class="pt-4">
                    <a
                        v-if="!requiresManualResolution"
                        href="/auth/idp/redirect"
                        class="w-full inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-red-700 hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors shadow-sm hover:shadow"
                    >
                        Return to IDP Login
                    </a>
                    <a
                        v-else
                        href="/"
                        class="w-full inline-flex justify-center items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 transition-colors shadow-sm"
                    >
                        Return to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
