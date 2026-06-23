<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faKey, faCheckCircle, faArrowRight } from '@fortawesome/free-solid-svg-icons'

library.add(faKey, faCheckCircle, faArrowRight)

const props = defineProps({
    email: {
        type: String,
        required: true,
    }
})

const page = usePage()
const flash = computed(() => page.props.flash ?? {})

const form = useForm({
    otp: '',
})

const submit = () => {
    form.post(route('emergency.verify'))
}
</script>

<template>
    <Head title="Verify OTP - PUPT Admission Portal" />

    <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-4">
        <!-- Main Container -->
        <div class="relative w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-200">
            <!-- Header Section -->
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
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 mb-3">
                        <FontAwesomeIcon icon="key" class="w-5 h-5 text-green-600" />
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">Verify Your Identity</h2>
                    <p class="text-sm text-gray-500 mt-2">
                        We've sent a 6-digit verification code to <span class="font-semibold text-gray-800">{{ email }}</span>
                    </p>
                </div>
                
                <div v-if="flash.success" class="p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800 font-medium text-center flex items-center justify-center gap-2">
                    <FontAwesomeIcon icon="check-circle" />
                    {{ flash.success }}
                </div>

                <div v-if="form.errors.otp || form.errors.email" class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 font-medium text-center">
                    {{ form.errors.otp || form.errors.email }}
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label for="otp" class="block text-sm font-medium text-gray-700 mb-2 text-center">Enter 6-Digit OTP</label>
                        <input
                            id="otp"
                            v-model="form.otp"
                            type="text"
                            required
                            maxlength="6"
                            class="w-full text-center text-2xl tracking-[0.5em] font-mono px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C] outline-none transition"
                            placeholder="000000"
                            autocomplete="one-time-code"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing || form.otp.length !== 6"
                        class="w-full inline-flex justify-center items-center gap-2 px-6 py-3 border border-transparent text-base font-medium rounded-xl text-white bg-[#9E122C] hover:bg-[#800000] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#9E122C] transition-colors shadow-sm disabled:opacity-70 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? 'Verifying...' : 'Verify & Login' }}
                    </button>
                </form>

                <div class="text-center pt-2 space-y-2">
                    <p class="text-sm text-gray-500">
                        Didn't receive the code? 
                        <Link :href="route('emergency.login')" class="font-medium text-[#9E122C] hover:underline">
                            Try again
                        </Link>
                    </p>
                    <Link href="/" class="block text-sm font-medium text-gray-500 hover:text-gray-900 transition-colors">
                        Cancel
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
