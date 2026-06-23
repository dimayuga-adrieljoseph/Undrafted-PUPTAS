<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faEnvelope, faArrowRight, faExclamationTriangle } from '@fortawesome/free-solid-svg-icons'

library.add(faEnvelope, faArrowRight, faExclamationTriangle)

const form = useForm({
    email: '',
})

const submit = () => {
    form.post(route('emergency.send-otp'))
}
</script>

<template>
    <Head title="Emergency Login - PUPT Admission Portal" />

    <!-- Full-page centered layout matching CheckStatus.vue -->
    <div class="relative min-h-screen bg-cover bg-center bg-[url('/assets/images/2.jpg')] font-sans">
        <!-- Frosted overlay consistent with the rest of the app -->
        <div class="absolute inset-0 bg-white/40 backdrop-blur-[10px] saturate-[168%]"></div>

        <div class="relative z-10 min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8">
            <div class="w-full max-w-md animate-fade-in-up">

                <!-- Card -->
                <div class="bg-white/70 backdrop-blur-sm rounded-2xl shadow-2xl overflow-hidden border border-white/50">

                    <!-- Brand accent bar -->
                    <div class="h-1 bg-gradient-to-r from-[#800000] via-[#FFD700] to-[#800000]"></div>

                    <!-- Header -->
                    <div class="px-6 sm:px-8 pt-8 pb-6 text-center">
                        <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm border border-gray-100">
                            <img src="/assets/images/pup_logo.png" alt="PUP Logo" class="h-10 w-10 object-contain" />
                        </div>
                        <h1 class="text-2xl font-bold text-gray-900">Emergency Access</h1>
                        <p class="text-sm text-gray-600 mt-2">
                            The primary identity provider is currently unavailable. Enter your registered email to receive a secure authentication code.
                        </p>
                    </div>

                    <div class="px-6 sm:px-8 pb-8">
                        <div v-if="form.errors.email" class="mb-5 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm text-center font-medium" role="alert">
                            {{ form.errors.email }}
                        </div>

                        <form @submit.prevent="submit" class="space-y-5" novalidate>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                    Email Address
                                </label>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    placeholder="Enter your registered email"
                                    :disabled="form.processing"
                                    :class="[
                                        'block w-full rounded-lg border px-3 py-2.5 text-sm shadow-sm transition-colors',
                                        'focus:outline-none focus:ring-2 focus:ring-[#800000] focus:border-[#800000]',
                                        'disabled:bg-gray-100 disabled:cursor-not-allowed',
                                        form.errors.email ? 'border-red-500 bg-red-50' : 'border-gray-300 bg-white/80'
                                    ]"
                                />
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="w-full flex items-center justify-center gap-2 py-2.5 rounded-lg font-semibold text-sm text-white shadow-md transition-all duration-200
                                       bg-gradient-to-r from-[#800000] to-[#9d0000]
                                       hover:from-[#600000] hover:to-[#800000]
                                       disabled:opacity-50 disabled:cursor-not-allowed
                                       focus:outline-none focus:ring-2 focus:ring-[#800000] focus:ring-offset-2"
                            >
                                <span v-if="form.processing" class="flex items-center gap-2">
                                    <svg class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Sending Code...
                                </span>
                                <span v-else>Send Authentication Code</span>
                            </button>
                        </form>

                        <div class="text-center mt-6 pt-5 border-t border-gray-200/50">
                            <Link href="/" class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-[#800000] transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Return to Landing Page
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
.animate-fade-in-up {
  animation: fadeInUp 0.4s ease-out forwards;
}
</style>
