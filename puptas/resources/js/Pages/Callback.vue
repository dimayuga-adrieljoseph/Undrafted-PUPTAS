<script setup>
import { ref, onMounted } from "vue";
import { Head } from "@inertiajs/vue3";

const props = defineProps({
    // API endpoint to call - will be passed from the server
    apiEndpoint: {
        type: String,
        default: "/api/callback",
    },
    // Optional: redirect URL after successful callback
    redirectTo: {
        type: String,
        default: "/dashboard",
    },
    // Optional: error redirect URL
    errorRedirect: {
        type: String,
        default: "/login",
    },
});

const status = ref("loading"); // loading, success, error
const message = ref("Processing your request...");
const progress = ref(0);

// Validate that a URL is an internal path (not an external redirect)
const isInternalPath = (url) => {
    if (!url || typeof url !== 'string') {
        return false;
    }
    
    // Reject absolute URLs with schemes
    if (/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//.test(url)) {
        return false;
    }
    
    // Reject protocol-relative URLs
    if (url.startsWith('//')) {
        return false;
    }
    
    // Reject URLs with @ symbol
    if (url.includes('@')) {
        return false;
    }
    
    // Must start with / for internal path
    return url.startsWith('/');
};

// Simulate loading progress
const simulateProgress = () => {
    const interval = setInterval(() => {
        if (progress.value < 90) {
            progress.value += Math.random() * 15;
        }
    }, 200);

    return interval;
};

onMounted(async () => {
    // Validate redirect URLs as additional client-side defense
    if (!isInternalPath(props.redirectTo)) {
        console.error('Invalid redirect URL detected, using default');
        window.location.href = '/dashboard';
        return;
    }
    
    if (!isInternalPath(props.errorRedirect)) {
        console.error('Invalid error redirect URL detected, using default');
        window.location.href = '/dashboard';
        return;
    }
    
    // Validate API endpoint is internal
    if (!isInternalPath(props.apiEndpoint)) {
        console.error('Invalid API endpoint detected, redirecting to error page');
        window.location.href = '/login';
        return;
    }

    const progressInterval = simulateProgress();

    try {
        // Make API call to the callback endpoint
        // This will be connected to an actual API soon
        const response = await fetch(props.apiEndpoint, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content,
            },
            // Pass any query parameters from the URL
            body: JSON.stringify({
                url_params: Object.fromEntries(new URLSearchParams(window.location.search)),
            }),
        });

        clearInterval(progressInterval);
        progress.value = 100;

        if (response.ok) {
            status.value = "success";
            message.value = "Request processed successfully! Redirecting...";
            
            // Redirect after short delay (already validated above)
            setTimeout(() => {
                window.location.href = props.redirectTo;
            }, 1500);
        } else {
            throw new Error("API request failed");
        }
    } catch (error) {
        clearInterval(progressInterval);
        status.value = "error";
        message.value = "An error occurred. Please try again.";
        
        // Auto-redirect to error page after delay (already validated above)
        setTimeout(() => {
            window.location.href = props.errorRedirect;
        }, 3000);
    }
});
</script>

<template>
    <Head title="Processing..." />
    
    <div class="min-h-screen bg-white-500 flex flex-col items-center justify-center p-4">
        <!-- Main Container -->
        <div class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden border border-gray-200 dark:border-gray-700 p-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-gradient-to-br from-red-100 to-red-50 dark:from-red-900/30 dark:to-red-800/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-4xl font-bold text-red-800 dark:text-red-300">P</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">PUP-T Admission</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">Processing your request</p>
            </div>

            <!-- Loading Status -->
            <div class="space-y-6">
                <!-- Progress Bar -->
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 overflow-hidden">
                    <div 
                        class="bg-gradient-to-r from-red-600 to-yellow-500 h-2.5 rounded-full transition-all duration-300 ease-out"
                        :style="{ width: `${Math.min(progress, 100)}%` }"
                    ></div>
                </div>

                <!-- Status Message -->
                <div class="text-center">
                    <!-- Loading Spinner -->
                    <div v-if="status === 'loading'" class="flex justify-center mb-4">
                        <svg class="animate-spin h-10 w-10 text-red-600 dark:text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>

                    <!-- Success Icon -->
                    <div v-else-if="status === 'success'" class="flex justify-center mb-4">
                        <div class="w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center">
                            <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Error Icon -->
                    <div v-else-if="status === 'error'" class="flex justify-center mb-4">
                        <div class="w-16 h-16 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center">
                            <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </div>
                    </div>

                    <p 
                        class="text-lg font-medium"
                        :class="{
                            'text-gray-700 dark:text-gray-300': status === 'loading',
                            'text-green-600 dark:text-green-400': status === 'success',
                            'text-red-600 dark:text-red-400': status === 'error'
                        }"
                    >
                        {{ message }}
                    </p>
                </div>

                <!-- Loading Dots Animation -->
                <div v-if="status === 'loading'" class="flex justify-center gap-1">
                    <div class="w-2 h-2 bg-red-600 rounded-full animate-bounce" style="animation-delay: 0ms;"></div>
                    <div class="w-2 h-2 bg-red-600 rounded-full animate-bounce" style="animation-delay: 150ms;"></div>
                    <div class="w-2 h-2 bg-red-600 rounded-full animate-bounce" style="animation-delay: 300ms;"></div>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-8 text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Please do not close this window
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes bounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}

.animate-bounce {
    animation: bounce 1s infinite;
}
</style>
