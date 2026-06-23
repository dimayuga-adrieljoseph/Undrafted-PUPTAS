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

    <div class="min-h-screen bg-[#FDFCF8] font-sans text-[#2C2C24] relative overflow-hidden flex flex-col items-center justify-center p-4">
        <!-- Global Grain Texture -->
        <div class="pointer-events-none fixed inset-0 z-[100] opacity-[0.035] mix-blend-multiply"
            style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 256 256%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noise%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.9%22 numOctaves=%224%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noise)%22/%3E%3C/svg%3E'); background-size: 200px 200px;"></div>

        <!-- Ambient Blobs -->
        <div class="absolute top-0 right-0 w-[500px] h-[500px] opacity-15 pointer-events-none"
            style="background: radial-gradient(circle, #9E122C 0%, transparent 70%); border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%; filter: blur(60px); transform: translate(30%, -30%);"></div>
        <div class="absolute bottom-0 left-0 w-[400px] h-[400px] opacity-10 pointer-events-none"
            style="background: radial-gradient(circle, #C18C5D 0%, transparent 70%); border-radius: 40% 60% 70% 30% / 40% 70% 30% 60%; filter: blur(50px); transform: translate(-30%, 30%);"></div>

        <!-- Logo Section Outside -->
        <div class="relative z-10 flex flex-col items-center mb-8">
            <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-[0_8px_30px_-4px_rgba(158,18,44,0.2)] mb-4 border border-[#DED8CF]/50">
                <img src="/assets/images/pup_logo.png" alt="PUP Logo" class="h-14 w-14 object-contain" />
            </div>
            <h1 class="text-3xl font-bold text-[#2C2C24] tracking-tight text-center leading-tight">
                Secure <span class="text-[#9E122C]">Verification</span>
            </h1>
        </div>

        <!-- Main Card -->
        <div class="relative z-10 w-full max-w-md bg-[#FEFEFA] rounded-[2.5rem] rounded-tl-[1rem] shadow-[0_20px_60px_-15px_rgba(93,112,82,0.15)] border border-[#DED8CF]/60 overflow-hidden">
            <!-- Content -->
            <div class="p-8 sm:p-10 space-y-7">
                <div class="text-center">
                    <p class="text-[15px] text-[#78786C] leading-relaxed">
                        We've sent a 6-digit magic code to <br/>
                        <span class="font-bold text-[#4A4A40]">{{ email }}</span>
                    </p>
                </div>
                
                <div v-if="flash.success" class="p-4 bg-green-50/80 border border-green-200/60 rounded-[1.25rem] text-sm text-green-800 font-medium text-center flex items-center justify-center gap-2">
                    <FontAwesomeIcon icon="check-circle" />
                    {{ flash.success }}
                </div>

                <div v-if="form.errors.otp || form.errors.email" class="p-4 bg-red-50/80 border border-red-200/60 rounded-[1.25rem] text-sm text-[#9E122C] font-medium text-center">
                    {{ form.errors.otp || form.errors.email }}
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label for="otp" class="block text-sm font-bold text-[#4A4A40] mb-2 pl-1 text-center">Enter 6-Digit Code</label>
                        <input
                            id="otp"
                            v-model="form.otp"
                            type="text"
                            required
                            maxlength="6"
                            class="w-full text-center text-3xl tracking-[0.5em] font-mono px-4 py-4 rounded-[1.25rem] border border-[#DED8CF] bg-white focus:ring-4 focus:ring-[#9E122C]/10 focus:border-[#9E122C] outline-none transition-all text-[#2C2C24] shadow-sm font-bold placeholder-gray-300"
                            placeholder="000000"
                            autocomplete="one-time-code"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing || form.otp.length !== 6"
                        class="w-full inline-flex justify-center items-center gap-2.5 px-6 py-4 border border-transparent text-base font-bold rounded-[1.25rem] text-white bg-[#9E122C] shadow-[0_8px_25px_-5px_rgba(158,18,44,0.35)] hover:shadow-[0_12px_35px_-5px_rgba(158,18,44,0.45)] hover:-translate-y-0.5 active:translate-y-0 active:shadow-none transition-all duration-300 disabled:opacity-70 disabled:cursor-not-allowed disabled:transform-none"
                    >
                        {{ form.processing ? 'Verifying Identity...' : 'Verify & Sign In' }}
                    </button>
                </form>

                <div class="text-center pt-4 border-t border-[#DED8CF]/40 space-y-3">
                    <p class="text-sm text-[#78786C]">
                        Didn't receive the code? 
                        <Link :href="route('emergency.login')" class="font-bold text-[#9E122C] hover:text-[#800000] hover:underline transition-colors">
                            Try again
                        </Link>
                    </p>
                    <Link href="/" class="block text-sm font-semibold text-[#78786C] hover:text-[#4A4A40] transition-colors">
                        Cancel & Return
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
