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

    <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-4">
        <!-- Main Container -->
        <div class="relative w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-200">
            <!-- Header Section with School Colors -->
            <div class="bg-[#9E122C] p-6 flex flex-col items-center">
                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center shadow-lg mb-4">
                    <span class="text-3xl font-bold text-[#9E122C]">P</span>
                </div>
                <h1 class="text-2xl font-bold text-white tracking-tight text-center">
                    PUP-T Admission
                </h1>
            </div>

            <!-- Content -->
            <div class="p-8 space-y-6">
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-amber-100 mb-3">
                        <FontAwesomeIcon icon="exclamation-triangle" class="w-5 h-5 text-amber-600" />
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">Emergency Login</h2>
                    <p class="text-sm text-gray-500 mt-2">
                        The primary identity provider is currently unavailable. Please enter your email address to receive a One-Time Password (OTP) for access.
                    </p>
                </div>
                
                <div v-if="form.errors.email" class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium text-center">
                    {{ form.errors.email }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <FontAwesomeIcon icon="envelope" class="text-gray-400" />
                            </div>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                class="pl-10 w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C] outline-none transition"
                                placeholder="Enter your registered email"
                            />
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full inline-flex justify-center items-center gap-2 px-6 py-3 border border-transparent text-base font-medium rounded-xl text-white bg-[#9E122C] hover:bg-[#800000] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#9E122C] transition-colors shadow-sm disabled:opacity-70 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? 'Sending...' : 'Send OTP' }}
                        <FontAwesomeIcon icon="arrow-right" v-if="!form.processing" class="text-sm" />
                    </button>
                </form>

                <div class="text-center pt-2">
                    <Link href="/" class="text-sm font-medium text-gray-500 hover:text-gray-900 transition-colors">
                        Return to Home
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
