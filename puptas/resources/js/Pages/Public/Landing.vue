<!-- PUPTAS Landing Page — Organic/Natural Design System -->
<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  appEnv: { type: String, default: 'production' },
  appDebug: { type: Boolean, default: false },
  isEmergencyMode: { type: Boolean, default: false },
})

const currentYear = computed(() => new Date().getFullYear())

// On local/staging keep the dev bypass; on production go straight to IDP
const goToLogin = () => {
  if (props.appDebug || props.appEnv === 'local' || props.appEnv === 'staging') {
    window.location.href = '/dev-login'
  } else if (props.isEmergencyMode) {
    window.location.href = '/emergency-login'
  } else {
    window.location.href = '/auth/idp/redirect'
  }
}

const steps = ref([
  {
    title: 'Receive Your PUPCET Result',
    description: 'After the PUP College Entrance Test, qualified applicants receive an email with their result and a Student Admission Receipt (SAR) form. Check your inbox — including spam.',
    icon: 'mail',
  },
  {
    title: 'Register via PUP Identity Provider',
    description: 'Create your PUP account using the registration link in your results email. This single account is your key to the PUPTAS admission portal.',
    icon: 'key',
  },
  {
    title: 'Complete Your Profile',
    description: 'On first login, fill in your personal information: full name, sex, SHS strand, school attended, date graduated, and your top three program choices.',
    icon: 'user',
  },
  {
    title: 'Enter Your SHS Grades',
    description: 'Input your Grade 11 and 12 subject grades using the form for your strand (ABM, ICT, HUMSS, GAS, STEM, or TVL). Upload your report card for AI-assisted extraction.',
    icon: 'chart',
  },
  {
    title: 'Upload Required Documents',
    description: 'Upload your report cards and supporting documents directly to secure cloud storage. The system tells you exactly which files are needed based on your graduate type.',
    icon: 'folder',
  },
  {
    title: 'Submit Your Application',
    description: 'Review the programs you qualify for based on your grades and strand, confirm your choices, and officially submit your application.',
    icon: 'check-circle',
  },
  {
    title: 'Evaluation & Interview',
    description: "Staff will verify your documents and grades. Once cleared, you'll be scheduled for an interview. You'll receive email updates at every step.",
    icon: 'chat',
  },
  {
    title: 'Medical Examination',
    description: 'After passing the interview, you will be scheduled for a medical examination as a final requirement before enrollment. Instructions will be sent to your email.',
    icon: 'graduation',
  },
])

const capabilities = ref([
  {
    icon: 'user',
    title: 'Complete Your Profile',
    desc: 'Set up your applicant profile with personal info, SHS strand, school details, and program choices on your first login.',
  },
  {
    icon: 'chart',
    title: 'Submit Your Grades',
    desc: 'Enter your Grade 11 and 12 subject grades. Upload your report card and let AI extract grades automatically.',
  },
  {
    icon: 'folder',
    title: 'Upload Documents',
    desc: "Directly upload your report cards and supporting documents. The portal shows exactly what's needed.",
  },
  {
    icon: 'target',
    title: 'See Your Eligible Programs',
    desc: 'Know which college programs you qualify for based on your strand, GWA, and subject grades — instantly.',
  },
  {
    icon: 'signal',
    title: 'Track Application Status',
    desc: 'Monitor your application in real time as it moves through evaluation, interview, medical, and enrollment.',
  },
  {
    icon: 'download',
    title: 'Download Grade Verification Slip',
    desc: 'Once your application is submitted, download your official Grade Verification Slip directly from the portal.',
  },
])

const faqs = ref([
  {
    q: 'Who can use PUPTAS?',
    a: 'PUPTAS is for PUPCET passers applying for admission to PUP Taguig Campus. You must have received a result email with a Student Admission Receipt (SAR) to be eligible.',
  },
  {
    q: 'Do I need a separate account to log in?',
    a: "No. You log in using your PUP Identity Provider (IDP) account — the same account used for all PUP online services. If you don't have one, register using the link in your PUPCET results email.",
  },
  {
    q: 'What documents do I need to prepare?',
    a: "Typically: Grade 10 Report Card, Grade 11 Report Card (front and back), Grade 12 Report Card (front and back), and your PSA Birth Certificate. The system will tell you exactly what's required based on your graduate type.",
  },
  {
    q: 'Can I change my program choices after submitting?',
    a: 'Once your application is submitted and locked, you cannot change your program choices on your own. Contact the admissions office via Chat Support for assistance.',
  },
  {
    q: 'How do I know if I qualify for a program?',
    a: 'After entering your grades, the system automatically shows which programs you are eligible for based on your GWA, strand, and subject grades. No guesswork needed.',
  },
  {
    q: "What if I didn't receive my SAR form email?",
    a: "Check your spam or junk folder first. If it's still not there, verify you used the correct registered email. Contact admissions support if the issue continues.",
  },
])

const openFaq = ref(null)
const toggleFaq = (i) => { openFaq.value = openFaq.value === i ? null : i }

const mobileMenuOpen = ref(false)

const navLinks = [
  { label: 'Features', href: '#features' },
  { label: 'How It Works', href: '#how-it-works' },
  { label: 'FAQ', href: '#faq' },
]

const scrollTo = (href) => {
  mobileMenuOpen.value = false
  const el = document.querySelector(href)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

// Asymmetric border-radius patterns for feature cards
const cardRadii = [
  'rounded-tl-[3rem] rounded-tr-[1.5rem] rounded-bl-[1.5rem] rounded-br-[3rem]',
  'rounded-tl-[1.5rem] rounded-tr-[3rem] rounded-bl-[3rem] rounded-br-[1.5rem]',
  'rounded-tl-[2.5rem] rounded-tr-[2.5rem] rounded-bl-[1.5rem] rounded-br-[3rem]',
  'rounded-tl-[3rem] rounded-tr-[1.5rem] rounded-bl-[3rem] rounded-br-[1.5rem]',
  'rounded-tl-[1.5rem] rounded-tr-[3rem] rounded-bl-[1.5rem] rounded-br-[3rem]',
  'rounded-tl-[3rem] rounded-tr-[3rem] rounded-bl-[1.5rem] rounded-br-[1.5rem]',
]
</script>

<template>
  <Head title="PUP-T Admission System" />
  <div class="min-h-screen bg-[#FDFCF8] font-sans text-[#2C2C24] relative overflow-x-hidden">

    <!-- ── GLOBAL GRAIN TEXTURE ──────────────────────────────── -->
    <div class="pointer-events-none fixed inset-0 z-[100] opacity-[0.035] mix-blend-multiply"
      style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 256 256%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noise%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.9%22 numOctaves=%224%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noise)%22/%3E%3C/svg%3E');
      background-size: 200px 200px;"></div>

    <!-- ── NAVBAR ─────────────────────────────────────────────── -->
    <nav class="sticky top-4 z-50 mx-4 sm:mx-6 lg:mx-auto lg:max-w-5xl">
      <div class="bg-white/70 backdrop-blur-md border border-[#DED8CF]/50 shadow-[0_4px_20px_-2px_rgba(93,112,82,0.15)] rounded-full px-4 sm:px-6 h-16 flex items-center justify-between">
        <!-- Logo -->
        <div class="flex items-center gap-3">
          <img src="/assets/images/pup_logo.png" alt="PUP Taguig Logo" class="h-10 w-10 object-contain flex-shrink-0" />
          <div class="leading-tight hidden sm:block">
            <p class="text-sm font-bold text-[#9E122C]">PUPTAS</p>
            <p class="text-xs text-[#78786C]">Admission Portal</p>
          </div>
        </div>

        <!-- Desktop nav links (center) -->
        <div class="hidden md:flex items-center gap-1">
          <button
            v-for="link in navLinks"
            :key="link.label"
            @click="scrollTo(link.href)"
            class="text-sm text-[#4A4A40] hover:text-[#9E122C] font-medium px-4 py-2 rounded-full hover:bg-[#9E122C]/5 transition-all duration-300"
          >
            {{ link.label }}
          </button>
          <a href="/admission-results"
            class="text-sm text-[#4A4A40] hover:text-[#9E122C] font-medium px-4 py-2 rounded-full hover:bg-[#9E122C]/5 transition-all duration-300">
            Check Status
          </a>
        </div>

        <!-- Right: Log In + mobile hamburger -->
        <div class="flex items-center gap-2">
          <button @click="goToLogin"
            class="px-5 py-2.5 rounded-full text-sm font-bold text-white bg-[#9E122C] shadow-[0_4px_20px_-2px_rgba(158,18,44,0.35)] hover:shadow-[0_6px_24px_-4px_rgba(158,18,44,0.45)] hover:scale-105 active:scale-95 transition-all duration-300">
            Log In
          </button>
          <!-- Hamburger (mobile only) -->
          <button
            @click="mobileMenuOpen = !mobileMenuOpen"
            class="md:hidden flex items-center justify-center w-10 h-10 rounded-full border border-[#DED8CF]/60 bg-white/60 text-[#4A4A40] hover:bg-[#9E122C]/5 hover:text-[#9E122C] transition-all duration-300"
            :aria-label="mobileMenuOpen ? 'Close menu' : 'Open menu'"
          >
            <svg v-if="!mobileMenuOpen" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
            <svg v-else class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- Mobile dropdown menu -->
      <transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-2 scale-95"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 -translate-y-2 scale-95">
        <div v-if="mobileMenuOpen"
          class="md:hidden mt-2 bg-white/80 backdrop-blur-md border border-[#DED8CF]/50 shadow-[0_10px_40px_-10px_rgba(93,112,82,0.2)] rounded-[2rem] px-4 py-4 flex flex-col gap-1">
          <button
            v-for="link in navLinks"
            :key="link.label"
            @click="scrollTo(link.href)"
            class="w-full text-left text-sm font-semibold text-[#4A4A40] hover:text-[#9E122C] px-4 py-3 rounded-full hover:bg-[#9E122C]/5 transition-all duration-300"
          >
            {{ link.label }}
          </button>
          <a href="/admission-results"
            class="text-sm font-semibold text-[#4A4A40] hover:text-[#9E122C] px-4 py-3 rounded-full hover:bg-[#9E122C]/5 transition-all duration-300">
            Check Status
          </a>
          <div class="mt-1 pt-3 border-t border-[#DED8CF]/40">
            <button @click="goToLogin"
              class="w-full px-5 py-3 rounded-full text-sm font-bold text-white bg-[#9E122C] shadow-[0_4px_20px_-2px_rgba(158,18,44,0.35)] hover:scale-[1.02] active:scale-95 transition-all duration-300">
              Log In to PUPTAS
            </button>
          </div>
        </div>
      </transition>
    </nav>

    <!-- ── HERO ───────────────────────────────────────────────── -->
    <section class="relative overflow-hidden pt-16 pb-28 sm:pt-24 sm:pb-36">
      <!-- Ambient blobs -->
      <div class="absolute -top-32 -right-32 w-[480px] h-[480px] opacity-20 pointer-events-none"
        style="background: radial-gradient(circle, #9E122C 0%, transparent 70%); border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%; filter: blur(60px);"></div>
      <div class="absolute -bottom-24 -left-24 w-[400px] h-[400px] opacity-15 pointer-events-none"
        style="background: radial-gradient(circle, #C18C5D 0%, transparent 70%); border-radius: 40% 60% 70% 30% / 40% 70% 30% 60%; filter: blur(50px);"></div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-12">
        <div class="flex flex-col items-center text-center">

          <!-- Pill badge -->
          <div class="inline-flex items-center gap-2.5 mb-8 px-5 py-2.5 rounded-full bg-white border border-[#DED8CF]/70 shadow-[0_4px_20px_-2px_rgba(93,112,82,0.12)]">
            <span class="relative flex h-2 w-2 flex-shrink-0">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#9E122C] opacity-60"></span>
              <span class="relative inline-flex rounded-full h-2 w-2 bg-[#9E122C]"></span>
            </span>
            <span class="text-xs font-bold text-[#9E122C] uppercase tracking-widest">PUP Taguig Campus · Now Accepting</span>
          </div>

          <!-- Headline -->
          <h1 class=" text-5xl sm:text-6xl lg:text-7xl font-bold text-[#2C2C24] leading-[1.1] mb-6 max-w-4xl">
            Welcome to the
            <span class="relative inline-block text-[#9E122C]">
              PUP - T Admission System
              <svg class="absolute -bottom-2 left-0 w-full" height="8" viewBox="0 0 300 8" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                <path d="M2 6 C50 2, 100 7, 150 4 S250 1, 298 5" stroke="#C18C5D" stroke-width="2.5" stroke-linecap="round" fill="none" opacity="0.6"/>
              </svg>
            </span>
          </h1>

          <!-- Subheadline -->
          <p class="text-lg sm:text-xl text-[#78786C] leading-relaxed mb-10 max-w-2xl">
            PUPTAS is the official online admission portal for PUP Taguig Campus. Complete your application, upload documents, and track your progress in real-time — all from one secure platform.
          </p>

          <!-- CTA Buttons -->
          <div class="flex flex-col sm:flex-row gap-4 justify-center mb-16">
            <button @click="goToLogin"
              class="group inline-flex items-center justify-center gap-2.5 px-9 py-4 rounded-full text-white font-bold text-base bg-[#9E122C] shadow-[0_4px_20px_-2px_rgba(158,18,44,0.4)] hover:shadow-[0_10px_40px_-10px_rgba(158,18,44,0.5)] hover:scale-105 active:scale-95 transition-all duration-300">
              Log In to PUPTAS
              <!-- Arrow right icon -->
              <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14M12 5l7 7-7 7"/>
              </svg>
            </button>
            <a href="/admission-results"
              class="inline-flex items-center justify-center gap-2.5 px-9 py-4 rounded-full font-bold text-base border-2 border-[#DED8CF] text-[#4A4A40] bg-white/60 hover:border-[#9E122C] hover:text-[#9E122C] hover:bg-[#9E122C]/5 transition-all duration-300">
              <!-- Search icon -->
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
              </svg>
              Check My Status
            </a>
          </div>



        </div>
      </div>
    </section>

    <!-- ── WHAT YOU CAN DO ────────────────────────────────────── -->
    <section id="features" class="py-28 sm:py-32 bg-[#F0EBE5]/30">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <span class="inline-block text-xs font-bold text-[#9E122C] uppercase tracking-widest mb-3">Portal Features</span>
          <h2 class=" text-4xl sm:text-5xl font-bold text-[#2C2C24] mb-4">Everything You Need<br class="hidden sm:block"> in One Place</h2>
          <p class="text-lg text-[#78786C] max-w-2xl mx-auto">Manage your entire admission process with intuitive tools designed for applicants.</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="(item, idx) in capabilities"
            :key="item.title"
            class="group relative bg-[#FEFEFA] border border-[#DED8CF]/50 p-7 shadow-[0_4px_20px_-2px_rgba(93,112,82,0.10)] hover:-translate-y-1 hover:shadow-[0_20px_40px_-10px_rgba(93,112,82,0.15)] transition-all duration-500 cursor-default"
            :class="cardRadii[idx % cardRadii.length]"
          >
            <!-- Icon container -->
            <div class="inline-flex items-center justify-center h-14 w-14 rounded-2xl bg-[#9E122C]/10 mb-5 group-hover:bg-[#9E122C] transition-colors duration-300">
              <!-- user icon -->
              <svg v-if="item.icon === 'user'" class="w-7 h-7 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
              </svg>
              <!-- chart/grades icon -->
              <svg v-else-if="item.icon === 'chart'" class="w-7 h-7 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
              </svg>
              <!-- folder icon -->
              <svg v-else-if="item.icon === 'folder'" class="w-7 h-7 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
              </svg>
              <!-- target icon -->
              <svg v-else-if="item.icon === 'target'" class="w-7 h-7 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>
              </svg>
              <!-- signal/tracking icon -->
              <svg v-else-if="item.icon === 'signal'" class="w-7 h-7 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
              </svg>
              <!-- download icon -->
              <svg v-else-if="item.icon === 'download'" class="w-7 h-7 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
              </svg>
            </div>
            <h3 class="font-bold text-[#2C2C24] text-lg mb-2 ">{{ item.title }}</h3>
            <p class="text-[#78786C] text-sm leading-relaxed">{{ item.desc }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ── HOW IT WORKS ───────────────────────────────────────── -->
    <section id="how-it-works" class="py-28 sm:py-32 bg-[#FDFCF8]">
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <span class="inline-block text-xs font-bold text-[#9E122C] uppercase tracking-widest mb-3">Application Flow</span>
          <h2 class=" text-4xl sm:text-5xl font-bold text-[#2C2C24] mb-4">Your Admission Journey</h2>
          <p class="text-lg text-[#78786C]">From PUPCET results to official enrollment — 8 clear steps.</p>
        </div>

        <div class="relative">
          <!-- Curved dashed SVG connector (desktop only) -->
          <svg class="absolute left-1/2 top-0 -translate-x-1/2 w-1 hidden sm:block" style="height:100%;" preserveAspectRatio="none" viewBox="0 0 4 800" fill="none" xmlns="http://www.w3.org/2000/svg">
            <line x1="2" y1="0" x2="2" y2="800" stroke="#DED8CF" stroke-width="2" stroke-dasharray="8 6"/>
          </svg>

          <div class="space-y-8 sm:space-y-10">
            <div v-for="(step, i) in steps" :key="step.title" class="relative flex flex-col sm:flex-row items-start gap-4 sm:gap-0">

              <!-- Left side card (even) -->
              <div v-if="i % 2 === 0" class="sm:w-1/2 sm:pr-10 w-full ml-14 sm:ml-0">
                <div class="group bg-[#FEFEFA] border border-[#DED8CF]/50 shadow-[0_4px_20px_-2px_rgba(93,112,82,0.10)] hover:shadow-[0_20px_40px_-10px_rgba(93,112,82,0.15)] hover:-translate-y-0.5 transition-all duration-500 p-6 rounded-[2rem] rounded-tr-[0.75rem]">
                  <div class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-[#9E122C]/10 mb-4 group-hover:bg-[#9E122C] transition-colors duration-300">
                    <!-- step icons inline -->
                    <svg v-if="step.icon === 'mail'" class="w-6 h-6 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 7L2 7"/></svg>
                    <svg v-else-if="step.icon === 'user'" class="w-6 h-6 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <svg v-else-if="step.icon === 'folder'" class="w-6 h-6 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    <svg v-else-if="step.icon === 'chat'" class="w-6 h-6 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <svg v-else-if="step.icon === 'graduation'" class="w-6 h-6 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6 6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/><path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"/><circle cx="20" cy="10" r="2"/></svg>
                    <svg v-else-if="step.icon === 'key'" class="w-6 h-6 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/></svg>
                  </div>
                  <h3 class="font-bold text-[#2C2C24] text-base mb-1.5">{{ step.title }}</h3>
                  <p class="text-[#78786C] text-sm leading-relaxed">{{ step.description }}</p>
                </div>
              </div>
              <div v-else class="sm:w-1/2 sm:pl-10 w-full sm:ml-auto ml-14">
                <div class="group bg-[#FEFEFA] border border-[#DED8CF]/50 shadow-[0_4px_20px_-2px_rgba(93,112,82,0.10)] hover:shadow-[0_20px_40px_-10px_rgba(93,112,82,0.15)] hover:-translate-y-0.5 transition-all duration-500 p-6 rounded-[2rem] rounded-tl-[0.75rem]">
                  <div class="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-[#9E122C]/10 mb-4 group-hover:bg-[#9E122C] transition-colors duration-300">
                    <svg v-if="step.icon === 'mail'" class="w-6 h-6 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 7L2 7"/></svg>
                    <svg v-else-if="step.icon === 'user'" class="w-6 h-6 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <svg v-else-if="step.icon === 'folder'" class="w-6 h-6 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    <svg v-else-if="step.icon === 'chat'" class="w-6 h-6 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <svg v-else-if="step.icon === 'graduation'" class="w-6 h-6 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6 6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/><path d="M8 15v1a6 6 0 0 0 6 6v0a6 6 0 0 0 6-6v-4"/><circle cx="20" cy="10" r="2"/></svg>
                    <svg v-else-if="step.icon === 'chart'" class="w-6 h-6 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    <svg v-else-if="step.icon === 'check-circle'" class="w-6 h-6 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <svg v-else-if="step.icon === 'key'" class="w-6 h-6 text-[#9E122C] group-hover:text-white transition-colors duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/></svg>
                  </div>
                  <h3 class="font-bold text-[#2C2C24] text-base mb-1.5">{{ step.title }}</h3>
                  <p class="text-[#78786C] text-sm leading-relaxed">{{ step.description }}</p>
                </div>
              </div>

              <!-- Center step number (desktop) -->
              <div class="absolute left-1/2 -translate-x-1/2 top-6 hidden sm:flex items-center justify-center w-10 h-10 rounded-full bg-[#9E122C] text-white font-bold text-sm shadow-[0_4px_12px_rgba(158,18,44,0.35)] border-4 border-[#FDFCF8] z-10 ">
                {{ i + 1 }}
              </div>

              <!-- Mobile step number -->
              <div class="absolute left-0 top-4 sm:hidden flex items-center justify-center w-10 h-10 rounded-full bg-[#9E122C] text-white font-bold text-sm shadow-[0_4px_12px_rgba(158,18,44,0.35)] border-4 border-[#FDFCF8] z-10 ">
                {{ i + 1 }}
              </div>

            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ── FAQ ───────────────────────────────────────────────── -->
    <section id="faq" class="py-28 sm:py-32 bg-[#E6DCCD]/20">
      <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
          <span class="inline-block text-xs font-bold text-[#9E122C] uppercase tracking-widest mb-3">Common Questions</span>
          <h2 class=" text-4xl sm:text-5xl font-bold text-[#2C2C24] mb-4">Frequently Asked<br>Questions</h2>
          <p class="text-lg text-[#78786C]">Answers to the most common questions from applicants.</p>
        </div>

        <div class="space-y-3">
          <div v-for="(faq, i) in faqs" :key="i"
            class="bg-[#FEFEFA] border border-[#DED8CF]/50 shadow-[0_4px_20px_-2px_rgba(93,112,82,0.08)] overflow-hidden transition-all duration-300"
            :class="openFaq === i ? 'rounded-[1.5rem]' : 'rounded-[1.25rem]'">
            <button type="button"
              class="w-full flex items-center justify-between gap-4 px-6 py-5 text-left hover:bg-[#F0EBE5]/40 transition-colors duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#9E122C]/30 focus-visible:ring-offset-2 rounded-[1.25rem]"
              @click="toggleFaq(i)">
              <span class="text-base font-semibold text-[#2C2C24] ">{{ faq.q }}</span>
              <span class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-full bg-[#9E122C]/10 transition-all duration-300" :class="openFaq === i ? 'bg-[#9E122C]' : ''">
                <svg class="w-4 h-4 transition-all duration-300" :class="openFaq === i ? 'rotate-180 text-white' : 'text-[#9E122C]'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="6 9 12 15 18 9"/>
                </svg>
              </span>
            </button>
            <transition
              enter-active-class="transition-all duration-300 ease-out"
              enter-from-class="opacity-0 -translate-y-1"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition-all duration-200 ease-in"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 -translate-y-1">
              <div v-show="openFaq === i" class="px-6 pb-5 border-t border-[#DED8CF]/30">
                <p class="text-sm text-[#4A4A40] leading-relaxed pt-4">{{ faq.a }}</p>
              </div>
            </transition>
          </div>
        </div>
      </div>
    </section>

    <!-- ── CTA ───────────────────────────────────────────────── -->
    <section class="relative py-28 sm:py-32 overflow-hidden">
      <!-- Background: deep crimson with organic blobs -->
      <div class="absolute inset-0 bg-[#9E122C]"></div>
      <div class="absolute top-0 right-0 w-[500px] h-[500px] opacity-20 pointer-events-none"
        style="background: radial-gradient(circle, #C18C5D 0%, transparent 70%); border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%; filter: blur(60px); transform: translate(30%, -30%);"></div>
      <div class="absolute bottom-0 left-0 w-[400px] h-[400px] opacity-15 pointer-events-none"
        style="background: radial-gradient(circle, #ffffff 0%, transparent 70%); border-radius: 40% 60% 70% 30% / 40% 70% 30% 60%; filter: blur(50px); transform: translate(-30%, 30%);"></div>
      <!-- Grain overlay for CTA -->
      <div class="absolute inset-0 opacity-[0.04] mix-blend-soft-light pointer-events-none"
        style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 256 256%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noise%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.9%22 numOctaves=%224%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noise)%22/%3E%3C/svg%3E'); background-size: 200px 200px;"></div>

      <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class=" text-4xl sm:text-5xl font-bold text-white mb-6 leading-tight">
          Ready to Pursue<br class="hidden sm:block"> Your PUP Dream?
        </h2>

        <div class="flex flex-col sm:flex-row gap-4 justify-center mt-10 mb-8">
          <button @click="goToLogin"
            class="group inline-flex items-center justify-center gap-2.5 px-9 py-4 rounded-full font-bold text-base text-[#9E122C] bg-white shadow-[0_10px_40px_-10px_rgba(0,0,0,0.3)] hover:bg-[#FDFCF8] hover:scale-105 active:scale-95 transition-all duration-300">
            Log In to PUPTAS
            <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </button>
          <a href="/admission-results"
            class="inline-flex items-center justify-center gap-2.5 px-9 py-4 rounded-full font-bold text-base text-white border-2 border-white/40 hover:bg-white/10 hover:border-white/60 transition-all duration-300 w-full sm:w-auto">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
            Check Admission Status
          </a>
        </div>

        <p class="text-sm text-red-200/70">
          No account yet? <span class="text-white font-semibold">Create one using the link in your PUPCET results email.</span>
        </p>
      </div>
    </section>

    <!-- ── FOOTER ─────────────────────────────────────────────── -->
    <footer class="bg-white border-t border-gray-200 py-4 px-6">
      <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-center gap-4">
        <!-- Copyright -->
        <p class="text-sm text-gray-500">
          © 1998-{{ currentYear }} PUP Taguig. All rights reserved.
        </p>

        <!-- Links -->
        <div class="flex items-center gap-6">
          <a
            href="https://www.pup.edu.ph/terms/"
            target="_blank"
            rel="noopener noreferrer"
            class="text-sm text-gray-600 hover:text-[#9E122C] transition-colors duration-200"
          >
            Terms of Use
          </a>
          <span class="text-gray-300">|</span>
          <a
            href="https://www.pup.edu.ph/privacy/"
            target="_blank"
            rel="noopener noreferrer"
            class="text-sm text-gray-600 hover:text-[#9E122C] transition-colors duration-200"
          >
            Privacy Statement
          </a>
        </div>
      </div>
    </footer>

  </div>
</template>

<style scoped>
/* Smooth global transitions */
*, *::before, *::after {
  transition-property: color, background-color, border-color, box-shadow, transform, opacity;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 300ms;
}

button:active, a:active {
  transition-duration: 0ms;
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    transition-duration: 0ms !important;
    animation-duration: 0ms !important;
  }
  .animate-ping {
    animation: none !important;
  }
}
</style>
