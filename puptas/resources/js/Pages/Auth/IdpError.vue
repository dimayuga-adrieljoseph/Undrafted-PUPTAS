<script setup>
import { Head, Link } from "@inertiajs/vue3";
import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

const page = usePage();
// Retrieve the flashed 'idp' error from the session via Inertia props
const error = computed(() => {
    return page.props.errors?.idp || "An unknown authentication error occurred.";
});
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
                
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium">
                    {{ error }}
                </div>

                <div class="pt-4">
                    <a href="/auth/idp/redirect" class="w-full inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-red-700 hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors shadow-sm hover:shadow">
                        Return to IDP Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
