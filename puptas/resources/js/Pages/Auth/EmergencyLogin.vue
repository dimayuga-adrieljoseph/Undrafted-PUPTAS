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
                Emergency <span class="text-[#9E122C]">Access</span>
            </h1>
        </div>

        <!-- Main Card -->
        <div class="relative z-10 w-full max-w-md bg-[#FEFEFA] rounded-[2.5rem] rounded-tl-[1rem] shadow-[0_20px_60px_-15px_rgba(93,112,82,0.15)] border border-[#DED8CF]/60 overflow-hidden">
            <!-- Content -->
            <div class="p-8 sm:p-10 space-y-7">
                <div class="text-center">
                    <p class="text-[15px] text-[#78786C] leading-relaxed">
                        The primary identity provider is currently unavailable. Enter your registered email to receive a secure One-Time Password.
                    </p>
                </div>
                
                <div v-if="form.errors.email" class="p-4 bg-red-50/80 border border-red-200/60 rounded-[1.25rem] text-sm text-[#9E122C] font-medium text-center">
                    {{ form.errors.email }}
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label for="email" class="block text-sm font-bold text-[#4A4A40] mb-2 pl-1">Email Address</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors duration-300">
                                <FontAwesomeIcon icon="envelope" class="text-[#A5A59B] group-focus-within:text-[#9E122C]" />
                            </div>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                class="pl-12 w-full px-4 py-3.5 rounded-[1.25rem] border border-[#DED8CF] bg-white focus:ring-4 focus:ring-[#9E122C]/10 focus:border-[#9E122C] outline-none transition-all text-[#2C2C24] shadow-sm font-medium"
                                placeholder="Enter your email"
                            />
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full inline-flex justify-center items-center gap-2.5 px-6 py-4 border border-transparent text-base font-bold rounded-[1.25rem] text-white bg-[#9E122C] shadow-[0_8px_25px_-5px_rgba(158,18,44,0.35)] hover:shadow-[0_12px_35px_-5px_rgba(158,18,44,0.45)] hover:-translate-y-0.5 active:translate-y-0 active:shadow-none transition-all duration-300 disabled:opacity-70 disabled:cursor-not-allowed disabled:transform-none"
                    >
                        {{ form.processing ? 'Sending Code...' : 'Send Magic Code' }}
                        <FontAwesomeIcon icon="arrow-right" v-if="!form.processing" class="text-sm" />
                    </button>
                </form>

                <div class="text-center pt-3 border-t border-[#DED8CF]/40">
                    <Link href="/" class="text-sm font-semibold text-[#78786C] hover:text-[#9E122C] transition-colors">
                        Return to Landing Page
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
