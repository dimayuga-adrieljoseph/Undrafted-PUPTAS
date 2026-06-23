<script setup>
import { Head, useForm, Link, usePage } from '@inertiajs/vue3'
import { computed, watch, ref, onMounted, onUnmounted } from 'vue'
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

watch(() => form.otp, (newVal) => {
    if (newVal && newVal.length === 6 && !form.processing) {
        submit()
    }
})

const resendForm = useForm({
    email: props.email
})

const cooldown = ref(180) // 3 minutes
let timerInterval = null

const startTimer = () => {
    cooldown.value = 180
    if (timerInterval) clearInterval(timerInterval)
    timerInterval = setInterval(() => {
        if (cooldown.value > 0) {
            cooldown.value--
        } else {
            clearInterval(timerInterval)
        }
    }, 1000)
}

onMounted(() => {
    startTimer()
})

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval)
})

const formattedCooldown = computed(() => {
    const minutes = Math.floor(cooldown.value / 60)
    const seconds = cooldown.value % 60
    return `${minutes}:${seconds.toString().padStart(2, '0')}`
})

const resendOtp = () => {
    resendForm.post('/emergency-login', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('otp')
            startTimer()
        }
    })
}
</script>

<template>
    <Head title="Verify OTP - PUPT Admission Portal" />

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
                        <h1 class="text-2xl font-bold text-gray-900">Secure Verification</h1>
                        <p class="text-sm text-gray-600 mt-2">
                            We've sent a 6-digit authentication code to <br/>
                            <span class="font-bold text-gray-900">{{ email }}</span>
                        </p>
                    </div>

                    <div class="px-6 sm:px-8 pb-8">
                        <div v-if="flash.success" class="mb-5 p-3 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm text-center font-medium flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            {{ flash.success }}
                        </div>

                        <div v-if="form.errors.otp || form.errors.email" class="mb-5 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm text-center font-medium">
                            {{ form.errors.otp || form.errors.email }}
                        </div>

                        <form @submit.prevent="submit" class="space-y-5" novalidate>
                            <div>
                                <label for="otp" class="block text-sm font-medium text-gray-700 mb-1 text-center">
                                    Enter 6-Digit Code
                                </label>
                                <input
                                    id="otp"
                                    v-model="form.otp"
                                    type="text"
                                    required
                                    maxlength="6"
                                    placeholder="000000"
                                    autocomplete="one-time-code"
                                    :disabled="form.processing"
                                    :class="[
                                        'block w-full rounded-lg border px-3 py-3 text-3xl tracking-[0.5em] font-mono text-center shadow-sm transition-colors',
                                        'focus:outline-none focus:ring-2 focus:ring-[#800000] focus:border-[#800000]',
                                        'disabled:bg-gray-100 disabled:cursor-not-allowed',
                                        form.errors.otp ? 'border-red-500 bg-red-50' : 'border-gray-300 bg-white/80'
                                    ]"
                                />
                            </div>

                            <!-- Auto-verifying state indicator instead of button -->
                            <div class="h-10 flex items-center justify-center">
                                <span v-if="form.processing" class="flex items-center gap-2 text-sm font-semibold text-[#800000]">
                                    <svg class="animate-spin w-4 h-4 text-[#800000]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Verifying Authentication Code...
                                </span>
                                <span v-else class="text-sm text-gray-400">
                                    Enter the 6-digit code to auto-verify
                                </span>
                            </div>
                        </form>

                        <div class="text-center mt-2 pt-5 border-t border-gray-200/50 space-y-3">
                            <div v-if="resendForm.errors.email" class="mb-2 p-2 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs text-center font-medium">
                                {{ resendForm.errors.email }}
                            </div>
                            
                            <p class="text-sm text-gray-600 flex flex-col items-center gap-2">
                                Didn't receive the code? 
                                <button 
                                    @click="resendOtp" 
                                    :disabled="resendForm.processing || cooldown > 0"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-semibold text-[#800000] bg-red-50 border border-red-100 rounded-lg hover:bg-red-100 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <svg v-if="resendForm.processing" class="animate-spin w-4 h-4 text-[#800000]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span v-if="cooldown > 0">Resend in {{ formattedCooldown }}</span>
                                    <span v-else>{{ resendForm.processing ? 'Resending...' : 'Resend OTP' }}</span>
                                </button>
                            </p>
                            <Link href="/" class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-[#800000] transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Cancel & Return
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
