<script setup>

// Vue core imports
import { ref, computed } from "vue";

// Inertia.js imports for routing and form handling
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";

// Custom components for authentication UI
import AuthenticationCardLogo from "@/Components/AuthenticationCardLogo.vue";
import Checkbox from "@/Components/Checkbox.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import '@fortawesome/fontawesome-free/css/all.css'

/**
 * Reactive state for toggling password visibility
 * @type {import('vue').Ref<boolean>}
 */
const showPassword = ref(false);

/**
 * Access to page props for error handling and flash messages
 * @type {import('@inertiajs/vue3').UsePageReturn}
 */
const page = usePage();

/**
 * Props accepted by the Login component
 * @property {boolean} canResetPassword - Whether to show the password reset link
 * @property {string} [status] - Status message from the server (e.g., verification sent)
 */
defineProps({
    canResetPassword: Boolean,
    status: String,
});

/**
 * Form data for login credentials
 * Uses Inertia's useForm for automatic error handling and validation
 * @type {import('@inertiajs/vue3').UseForm<{email: string, password: string, remember: boolean}>}
 */
const form = useForm({
    email: "",
    password: "",
    remember: false,
});

/**
 * Computed property to check if there's an authentication error
 * Checks for general authentication errors from the server
 */
const hasAuthError = computed(() => {
    // Check if there's a general error in the page props
    if (page.props.errors?.error) {
        return true;
    }
    // Check if there are any form errors (excluding field-specific errors we want to show inline)
    if (form.errors.email || form.errors.password) {
        return true;
    }
    // Check for authentication failed message
    if (form.errors.auth) {
        return true;
    }
    return false;
});

/**
 * Get the authentication error message
 */
const authErrorMessage = computed(() => {
    if (page.props.errors?.error) {
        return page.props.errors.error;
    }
    if (form.errors.auth) {
        return form.errors.auth;
    }
    if (form.errors.email && !form.errors.email.includes('email')) {
        return form.errors.email;
    }
    return "Invalid credentials. Please check your email and password and try again.";
});

/**
 * Handles form submission for traditional email/password login
 * Transforms the remember boolean to 'on' string as expected by Laravel
 * Resets password field after submission for security
 * @returns {void}
 */
const submit = () => {
    form.transform((data) => ({
        ...data,
        remember: form.remember ? "on" : "",
    })).post(route("login"), {
        onFinish: () => form.reset("password"),
        onError: (errors) => {
            // This ensures errors are properly captured
            console.log('Login errors:', errors);
        },
    });
};

/**
 * Force a full browser navigation to the IDP, bypassing Inertia's XHR interception.
 */
const navigateToIdp = () => {
    window.location.href = route('idp.redirect');
};
</script>

<template>
    <!-- Page title for browser tab and SEO -->
    <Head title="Log in" />

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

        <!-- Login Container -->
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
                        - Logo and welcome text
                    -->
                    <div class="relative">
                        <!-- Accent bar with brand colors -->
                        <div class="h-1 bg-gradient-to-r from-[#800000] via-[#FFD700] to-[#800000]"></div>
                        
                        <div class="px-6 sm:px-8 pt-8 pb-6 text-center">
                            <div class="flex justify-center mb-4">
                                <AuthenticationCardLogo />
                            </div>
                            <h2 class="text-2xl font-bold text-gray-900 mb-1">
                                PUP-T Admission
                            </h2>
                            <p class="text-sm text-gray-600">
                                Sign in to your account
                            </p>
                        </div>
                    </div>

                    <!-- Form Section -->
                    <div class="px-6 sm:px-8 pb-8">
                        <!-- Status Message (e.g., verification sent) -->
                        <div
                            v-if="status"
                            class="mb-4 p-3 rounded-lg bg-green-50/90 backdrop-blur-sm border border-green-200 text-green-700 text-sm"
                        >
                            <i class="fas fa-check-circle mr-2"></i>
                            {{ status }}
                        </div>

                        <!-- IDP Error Display -->
                        <div
                            v-if="page.props.errors?.idp"
                            class="mb-4 p-3 rounded-lg bg-red-50/90 backdrop-blur-sm border border-red-200 text-red-700 text-sm"
                        >
                            <div class="flex items-start gap-2">
                                <i class="fas fa-exclamation-circle mt-0.5"></i>
                                <div>
                                    <p class="font-medium">IDP Authentication Failed</p>
                                    <p class="text-sm mt-1">{{ page.props.errors.idp }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Login Error Display -->
                        <div
                            v-if="hasAuthError && !page.props.errors?.idp"
                            class="mb-4 p-3 rounded-lg bg-red-50/90 backdrop-blur-sm border border-red-200 text-red-700 text-sm"
                        >
                            <div class="flex items-start gap-2">
                                <i class="fas fa-exclamation-triangle mt-0.5"></i>
                                <div>
                                    <p class="font-medium">Authentication Failed</p>
                                    <p class="text-sm mt-1">{{ authErrorMessage }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Login Form -->
                        <form @submit.prevent="submit" class="space-y-5 relative">
                            <!-- Loading Overlay -->
                            <div
                                v-if="form.processing"
                                class="absolute inset-0 bg-white/90 backdrop-blur-md flex flex-col items-center justify-center rounded-xl z-20 transition-all duration-300"
                                aria-live="polite"
                            >
                                <div class="relative">
                                    <div class="w-12 h-12 rounded-full border-4 border-gray-200"></div>
                                    <div class="absolute top-0 left-0 w-12 h-12 rounded-full border-4 border-t-[#800000] border-r-[#FFD700] border-b-transparent border-l-transparent animate-spin"></div>
                                </div>
                                <p class="mt-4 text-sm font-medium text-gray-700">
                                    <i class="fas fa-spinner fa-spin mr-2"></i>
                                    Signing in...
                                </p>
                            </div>

                            <!-- Email Field -->
                            <div>
                                <InputLabel
                                    for="email"
                                    value="Email Address"
                                    class="text-gray-700 text-sm font-medium mb-1 block"
                                />
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-envelope text-gray-400 text-sm"></i>
                                    </div>
                                    <TextInput
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        class="mt-1 block w-full bg-white/80 border-gray-300 rounded-lg focus:ring-[#800000] focus:border-[#800000] transition-all duration-200 backdrop-blur-sm pl-10"
                                        :class="{
                                            'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.email && !hasAuthError
                                        }"
                                        required
                                        autofocus
                                        autocomplete="username"
                                        placeholder="you@example.com"
                                        :disabled="form.processing"
                                    />
                                </div>
                                <InputError
                                    class="mt-1 text-sm text-red-600"
                                    :message="form.errors.email"
                                />
                            </div>

                            <!-- Password Field -->
                            <div>
                                <div class="flex justify-between items-center mb-1">
                                    <InputLabel
                                        for="password"
                                        value="Password"
                                        class="text-gray-700 text-sm font-medium block"
                                    />
                                    <Link
                                        v-if="canResetPassword"
                                        :href="route('password.request')"
                                        class="text-xs text-[#800000] hover:text-[#600000] font-medium transition-colors"
                                    >
                                        <i class="fas fa-key mr-1"></i>
                                        Forgot password?
                                    </Link>
                                </div>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-lock text-gray-400 text-sm"></i>
                                    </div>
                                    <TextInput
                                        id="password"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        class="mt-1 block w-full bg-white/80 border-gray-300 rounded-lg focus:ring-[#800000] focus:border-[#800000] transition-all duration-200 pl-10 pr-10 backdrop-blur-sm"
                                        :class="{
                                            'border-red-500 focus:ring-red-500 focus:border-red-500': form.errors.password && !hasAuthError
                                        }"
                                        required
                                        autocomplete="current-password"
                                        placeholder="Enter your password"
                                        :disabled="form.processing"
                                    />
                                    <!-- Password Visibility Toggle with Font Awesome -->
                                    <button
                                        type="button"
                                        @click="showPassword = !showPassword"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-[#800000] focus:ring-offset-2 rounded-full p-1 transition-colors"
                                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                        :aria-pressed="showPassword"
                                        tabindex="-1"
                                    >
                                        <i 
                                            :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"
                                            class="text-sm"
                                        ></i>
                                    </button>
                                </div>
                                <InputError
                                    class="mt-1 text-sm text-red-600"
                                    :message="form.errors.password"
                                />
                            </div>

                            <!-- Remember Me Checkbox -->
                            <div class="flex items-center">
                                <Checkbox
                                    v-model:checked="form.remember"
                                    name="remember"
                                    class="border-gray-300 rounded focus:ring-[#800000] text-[#800000]"
                                    :disabled="form.processing"
                                />
                                <span class="ml-2 text-sm text-gray-700">
                                    <i class="fas fa-check-circle mr-1 text-gray-400"></i>
                                    Remember me
                                </span>
                            </div>

                            <!-- Submit Button -->
                            <PrimaryButton
                                class="w-full py-2.5 bg-gradient-to-r from-[#800000] to-[#9d0000] text-white font-semibold rounded-lg hover:from-[#600000] hover:to-[#800000] transition-all duration-200 shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                                :disabled="form.processing"
                            >
                                <i class="fas fa-sign-in-alt"></i>
                                <span v-if="!form.processing">Sign In</span>
                                <span v-else>Signing In...</span>
                            </PrimaryButton>

                            <!-- IDP Login Button - uses window.location for full browser redirect, bypassing Inertia XHR interception -->
                            <button
                                type="button"
                                @click="navigateToIdp"
                                class="w-full flex items-center justify-center gap-3 py-2.5 px-4 border-2 border-gray-200/80 rounded-lg text-gray-700 font-medium hover:border-[#800000] hover:text-[#800000] hover:bg-white/50 transition-all duration-200 group backdrop-blur-sm"
                            >
                                <i class="fas fa-university group-hover:scale-110 transition-transform"></i>
                                Login with School IDP
                            </button>

                            <!-- Register Button -->
                            <div class="text-center text-sm text-gray-600">
                                Don't have an account?
                                <Link
                                    :href="route('register')"
                                    class="text-[#800000] hover:text-[#600000] font-medium transition-colors ml-1"
                                >
                                    <i class="fas fa-user-plus mr-1"></i>
                                    Register
                                </Link>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom animations for loading spinner */
@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

/* Optional: Add smooth fade-in animation for error messages */
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

.mb-4.p-3 {
    animation: fadeIn 0.3s ease-out;
}
</style>