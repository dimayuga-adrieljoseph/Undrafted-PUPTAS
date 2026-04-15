<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AuthenticationCardLogo from "@/Components/AuthenticationCardLogo.vue";
import '@fortawesome/fontawesome-free/css/all.css'

/**
 * Force a full browser navigation to the IDP, bypassing Inertia's XHR interception.
 */
const navigateToIdp = () => {
    window.location.href = route('idp.redirect');
};
</script>

<template>
    <!-- Page title for browser tab and SEO -->
    <Head title="Logged Out" />

    <!--
        Main Container
        - Full screen background image from assets
        - Relative positioning for overlay placement
    -->
    <div
        class="relative w-screen min-h-screen bg-cover bg-center bg-[url('/assets/images/2.jpg')]"
    >
        <!--
            Background Overlay with Frosted Effect
            - Applies blur effect (10px) and saturation (168%) to background image
            - White tint with 40% opacity for better contrast
        -->
        <div
            class="absolute inset-0 bg-white/40 backdrop-blur-[10px] saturate-[168%]"
        ></div>

        <!-- Logged Out Container -->
        <div class="relative min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8 z-10">
            <div class="w-full max-w-md">
                <!--
                    Modern Glassmorphism Card
                    - Frosted glass effect that complements the background
                    - Clean white card with subtle transparency
                    - Rounded corners with modern aesthetics
                -->
                <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-2xl overflow-hidden transition-all duration-300 hover:shadow-3xl">
                    <!--
                        Header Section with Branding
                        - Gradient accent bar at top
                        - Logo and message
                    -->
                    <div class="relative">
                        <!-- Accent bar with brand colors -->
                        <div class="h-1 bg-gradient-to-r from-[#800000] via-[#FFD700] to-[#800000]"></div>
                        
                        <div class="px-6 sm:px-8 pt-8 pb-6 text-center">
                            <div class="flex justify-center mb-4">
                                <AuthenticationCardLogo />
                            </div>
                            
                            <!-- Success Icon -->
                            <div class="flex justify-center mb-4">
                                <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center">
                                    <i class="fas fa-check-circle text-green-600 text-3xl"></i>
                                </div>
                            </div>
                            
                            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                                Successfully Logged Out
                            </h2>
                            <p class="text-sm text-gray-600">
                                You have been securely logged out of your account.
                            </p>
                        </div>
                    </div>

                    <!-- Content Section -->
                    <div class="px-6 sm:px-8 pb-8">
                        <!-- Information Box -->
                        <div class="mb-6 p-4 rounded-lg bg-blue-50/90 backdrop-blur-sm border border-blue-200">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
                                <div class="text-sm text-blue-800">
                                    <p class="font-medium mb-1">Session Cleared</p>
                                    <p>Your local session and tokens have been removed. For complete security, you may also want to close your browser.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="space-y-3">
                            <!-- Login with IDP Button -->
                            <button
                                type="button"
                                @click="navigateToIdp"
                                class="w-full flex items-center justify-center gap-3 py-3 px-4 bg-gradient-to-r from-[#800000] to-[#9d0000] text-white font-semibold rounded-lg hover:from-[#600000] hover:to-[#800000] transition-all duration-200 shadow-md hover:shadow-lg group"
                            >
                                <i class="fas fa-sign-in-alt group-hover:scale-110 transition-transform"></i>
                                Sign In Again
                            </button>

                            <!-- Alternative: Go to Login Page -->
                            <Link
                                :href="route('login')"
                                class="w-full flex items-center justify-center gap-3 py-3 px-4 border-2 border-gray-200/80 rounded-lg text-gray-700 font-medium hover:border-[#800000] hover:text-[#800000] hover:bg-white/50 transition-all duration-200 group backdrop-blur-sm"
                            >
                                <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i>
                                Back to Login Page
                            </Link>
                        </div>

                        <!-- Additional Info -->
                        <div class="mt-6 text-center">
                            <p class="text-xs text-gray-500">
                                <i class="fas fa-shield-alt mr-1"></i>
                                Your data is protected and secure
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Optional: Add smooth fade-in animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.bg-white\/70 {
    animation: fadeIn 0.5s ease-out;
}
</style>
