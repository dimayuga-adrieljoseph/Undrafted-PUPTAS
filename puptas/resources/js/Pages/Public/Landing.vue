<!-- PUPTAS Landing Page — Editorial Institutional Design -->
<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head } from '@inertiajs/vue3'

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({
  isDevMode:      { type: Boolean, default: false },
  appDebug:       { type: Boolean, default: false },
  isEmergencyMode:{ type: Boolean, default: false },
})

// ─── Utilities ────────────────────────────────────────────────────────────────
const currentYear = computed(() => new Date().getFullYear())

// ─── Dark mode ────────────────────────────────────────────────────────────────
// Mirrors the same localStorage key used by useLayout across the rest of the system.
const isDarkMode = ref(false)

const toggleDarkMode = () => {
  isDarkMode.value = !isDarkMode.value
  document.documentElement.classList.toggle('dark', isDarkMode.value)
  try { localStorage.setItem('darkMode', String(isDarkMode.value)) } catch { /* noop */ }
}

// ─── Navigation ───────────────────────────────────────────────────────────────
const mobileMenuOpen = ref(false)
const navScrolled    = ref(false)

const navLinks = [
  { label: 'Admissions',  href: '#admissions'  },
  { label: 'How to Apply',href: '#process'     },
  { label: 'Programs',    href: '#programs'    },
  { label: 'FAQ',         href: '#faq'         },
]

const scrollTo = (href) => {
  mobileMenuOpen.value = false
  const el = document.querySelector(href)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const handleNavScroll = () => {
  navScrolled.value = window.scrollY > 32
}

// ─── Login routing ────────────────────────────────────────────────────────────
const goToLogin = () => {
  if (props.isEmergencyMode) {
    window.location.href = '/emergency-login'
  } else if (props.appDebug || props.isDevMode) {
    window.location.href = '/dev-login'
  } else {
    window.location.href = '/auth/idp/redirect'
  }
}

// ─── Scroll-reveal animation ──────────────────────────────────────────────────
// Elements marked with data-reveal are animated when they enter the viewport.
// We store a single observer and clean it up on unmount.
let revealObserver = null

const initReveal = () => {
  revealObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-revealed')
          revealObserver.unobserve(entry.target)
        }
      })
    },
    { threshold: 0.12, rootMargin: '0px 0px -48px 0px' }
  )
  document.querySelectorAll('[data-reveal]').forEach((el) => {
    revealObserver.observe(el)
  })
}

// ─── Hero counter animation ───────────────────────────────────────────────────
// Simple animated stat counters for the hero panel.
const stats = ref([
  { label: 'Degree Programs',    value: 0, target: 11,    suffix: ''  },
  { label: 'Years of Excellence',value: 0, target: 36,    suffix: ''  },
])

let counterTimer = null

const animateCounters = () => {
  const duration = 1600 // ms
  const steps    = 60
  const interval = duration / steps

  let step = 0
  counterTimer = setInterval(() => {
    step++
    const progress = step / steps
    // ease-out cubic
    const eased = 1 - Math.pow(1 - progress, 3)

    stats.value = stats.value.map((s) => ({
      ...s,
      value: Math.round(s.target * eased),
    }))

    if (step >= steps) clearInterval(counterTimer)
  }, interval)
}

// ─── Admissions process steps ────────────────────────────────────────────────
const steps = [
  {
    number: '01',
    phase:  'Results',
    title:  'Receive Your PUPCET Result',
    body:   'After the PUP College Entrance Test, qualified applicants receive an email with their result and a Student Admission Receipt (SAR). Check your inbox — including spam.',
    icon:   'mail',
  },
  {
    number: '02',
    phase:  'Account',
    title:  'Register via PUP IDP',
    body:   'Create your PUP Identity Provider account using the registration link in your PUPCET results email. This single account is your key to the portal.',
    icon:   'key',
  },
  {
    number: '03',
    phase:  'Profile',
    title:  'Complete Your Profile',
    body:   'On first login, fill in your personal information: full name, sex, SHS strand, school attended, date graduated, and your top three program choices.',
    icon:   'user',
  },
  {
    number: '04',
    phase:  'Grades',
    title:  'Enter Your SHS Grades',
    body:   'Input your Grade 11 and 12 subject grades using the form for your strand (ABM, ICT, HUMSS, GAS, STEM, or TVL). The system computes your GWA instantly.',
    icon:   'chart',
  },
  {
    number: '05',
    phase:  'Documents',
    title:  'Upload Required Documents',
    body:   'Upload your report cards and supporting documents directly to secure cloud storage. The system tells you exactly which files are needed.',
    icon:   'folder',
  },
  {
    number: '06',
    phase:  'Evaluation',
    title:  'Evaluation & Interview',
    body:   "Staff will verify your documents and grades. Once cleared, you'll be scheduled for an interview. You'll receive email updates at every stage.",
    icon:   'chat',
  },
  {
    number: '07',
    phase:  'Medical',
    title:  'Medical Examination',
    body:   'After passing the interview, you will be scheduled for a medical examination as a final requirement before enrollment.',
    icon:   'heart',
  },
  {
    number: '08',
    phase:  'Enrollment',
    title:  'Official Enrollment',
    body:   'Receive your enrollment confirmation and complete the final enrollment requirements at PUP Taguig Campus.',
    icon:   'graduation',
  },
]

const activeStep = ref(0)

// ─── FAQ ─────────────────────────────────────────────────────────────────────
const faqs = [
  {
    q: 'Who can use PUPTAS?',
    a: 'PUPTAS is for PUPCET passers applying for admission to PUP Taguig Campus. You must have received a result email with a Student Admission Receipt (SAR) to be eligible.',
  },
  {
    q: 'Do I need a separate account to log in?',
    a: 'No. You log in using your PUP Identity Provider (IDP) account — the same account used for all PUP online services. If you do not have one yet, register using the link in your PUPCET results email.',
  },
  {
    q: 'What documents do I need to prepare?',
    a: 'Typically: Grade 10 Report Card, Grade 11 Report Card (front and back), Grade 12 Report Card (front and back), and your PSA Birth Certificate. The system will confirm the exact requirements based on your graduate type.',
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
]

const openFaq = ref(null)
const toggleFaq = (i) => { openFaq.value = openFaq.value === i ? null : i }

// ─── Programs directory ───────────────────────────────────────────────────────
const programs = [
  { code: 'BSBA-HRM',    name: 'BS Business Administration – Human Resource Management' },
  { code: 'BSBA-MM',     name: 'BS Business Administration – Marketing Management' },
  { code: 'BSOA',        name: 'BS Office Administration' },
  { code: 'BSECE',       name: 'BS Electronics and Communications Engineering' },
  { code: 'BSME',        name: 'BS Mechanical Engineering' },
  { code: 'BSIT',        name: 'BS Information Technology' },
  { code: 'BSED-ENGLISH',name: 'BS Education – English' },
  { code: 'BSED-MATH',   name: 'BS Education – Mathematics' },
  { code: 'BSPSYCH',     name: 'BS Psychology' },
  { code: 'DIT',         name: 'Diploma in Information Technology' },
  { code: 'DOMT',        name: 'Diploma in Office Management and Technology' },
]

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(() => {
  window.addEventListener('scroll', handleNavScroll, { passive: true })
  initReveal()

  // Sync dark mode with the rest of the system (same localStorage key)
  try {
    const saved = localStorage.getItem('darkMode') === 'true'
    isDarkMode.value = saved
    document.documentElement.classList.toggle('dark', saved)
  } catch { /* noop */ }

  // Kick off counters after a brief paint delay
  setTimeout(animateCounters, 600)
})

onUnmounted(() => {
  window.removeEventListener('scroll', handleNavScroll)
  revealObserver?.disconnect()
  clearInterval(counterTimer)
})
</script>

<template>
  <Head>
    <title>PUPTAS — PUP Taguig Admission System</title>
    <meta name="description" content="The official online admission portal for Polytechnic University of the Philippines – Taguig Campus. Apply for admission, track your application, and begin your PUP journey." />
    <meta property="og:title" content="PUPTAS — PUP Taguig Admission System" />
    <meta property="og:description" content="The official digital admissions platform for PUP Taguig Campus. Complete your application online." />
    <meta name="theme-color" content="#800000" />
  </Head>

  <div class="min-h-screen bg-white dark:bg-slate-950 text-[#151515] dark:text-gray-100 font-sans antialiased overflow-x-hidden">

    <!-- ═══════════════════════════════════════════════════════════════
         NAVIGATION
    ═══════════════════════════════════════════════════════════════ -->
    <header
      class="fixed top-0 left-0 right-0 z-50 transition-all duration-300"
      :class="navScrolled
        ? 'bg-white/95 dark:bg-gray-900/80 backdrop-blur-md shadow-[0_1px_0_0_rgba(0,0,0,0.08)] dark:shadow-[0_1px_0_0_rgba(0,0,0,0.4)]'
        : 'bg-transparent'"
    >
      <div class="max-w-screen-xl mx-auto px-6 lg:px-10 h-16 flex items-center justify-between gap-6">

        <!-- Logo -->
        <a href="/" class="flex items-center gap-3 flex-shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#800000] focus-visible:ring-offset-2 rounded" aria-label="PUPTAS — Home">
          <img src="/assets/images/pup_logo.png" alt="PUP Taguig Logo" class="h-9 w-9 object-contain" />
          <div class="leading-tight">
            <span class="block text-sm font-extrabold tracking-tight text-[#800000]">PUPTAS</span>
            <span
              class="block text-[10px] font-semibold uppercase tracking-[0.12em] transition-colors duration-300"
              :class="navScrolled ? 'text-[#555]' : 'text-[#800000]/70'"
            >PUP Taguig</span>
          </div>
        </a>

        <!-- Desktop nav -->
        <nav class="hidden md:flex items-center gap-0.5" aria-label="Main navigation">
          <button
            v-for="link in navLinks"
            :key="link.label"
            @click="scrollTo(link.href)"
            class="px-4 py-2 text-sm font-semibold rounded transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#800000] focus-visible:ring-offset-2"
            :class="navScrolled
              ? 'text-[#333] dark:text-gray-200 hover:text-[#800000] hover:bg-[#800000]/5'
              : 'text-[#151515] dark:text-gray-200 hover:text-[#800000] hover:bg-[#800000]/5'"
          >
            {{ link.label }}
          </button>
          <a
            href="/admission-results"
            class="px-4 py-2 text-sm font-semibold rounded transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#800000] focus-visible:ring-offset-2"
            :class="navScrolled ? 'text-[#333] dark:text-gray-200 hover:text-[#800000] hover:bg-[#800000]/5' : 'text-[#151515] dark:text-gray-200 hover:text-[#800000] hover:bg-[#800000]/5'"
          >
            Check Status
          </a>
        </nav>

        <!-- Right side: dark mode + CTA + mobile hamburger -->
        <div class="flex items-center gap-3">
          <!-- Dark mode toggle -->
          <button
            @click="toggleDarkMode"
            class="flex items-center justify-center w-9 h-9 rounded transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#800000] focus-visible:ring-offset-2"
            :class="navScrolled
              ? 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800'
              : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800'"
            :aria-label="isDarkMode ? 'Switch to light mode' : 'Switch to dark mode'"
            :title="isDarkMode ? 'Switch to light mode' : 'Switch to dark mode'"
          >
            <!-- Moon (shown in light mode) -->
            <svg v-if="!isDarkMode" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
            <!-- Sun (shown in dark mode) -->
            <svg v-else class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="5"/>
              <line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/>
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/>
              <line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/>
              <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>
            </svg>
          </button>

          <button
            @click="goToLogin"
            class="hidden sm:inline-flex items-center gap-2 px-5 py-2 rounded text-sm font-bold text-white bg-[#800000] hover:bg-[#6a0000] active:scale-95 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#800000] focus-visible:ring-offset-2 shadow-sm"
          >
            Log In to Apply
            <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M3 8h10M9 4l4 4-4 4"/>
            </svg>
          </button>

          <!-- Hamburger -->
          <button
            @click="mobileMenuOpen = !mobileMenuOpen"
            class="md:hidden flex items-center justify-center w-9 h-9 rounded text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#800000] focus-visible:ring-offset-2"
            :aria-expanded="mobileMenuOpen"
            :aria-label="mobileMenuOpen ? 'Close menu' : 'Open menu'"
          >
            <svg v-if="!mobileMenuOpen" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
              <line x1="3" y1="7"  x2="21" y2="7"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="17" x2="21" y2="17"/>
            </svg>
            <svg v-else class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
        </div>
      </div>

      <!-- Mobile menu -->
      <transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition-all duration-150 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
      >
        <div
          v-if="mobileMenuOpen"
          class="md:hidden bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 shadow-lg"
          role="navigation"
          aria-label="Mobile navigation"
        >
          <div class="max-w-screen-xl mx-auto px-6 py-4 flex flex-col gap-1">
            <button
              v-for="link in navLinks"
              :key="link.label"
              @click="scrollTo(link.href)"
              class="w-full text-left px-4 py-3 text-sm font-semibold text-[#333] dark:text-gray-200 hover:text-[#800000] hover:bg-[#800000]/5 dark:hover:bg-gray-800 rounded transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#800000]"
            >
              {{ link.label }}
            </button>
            <a
              href="/admission-results"
              @click="mobileMenuOpen = false"
              class="px-4 py-3 text-sm font-semibold text-[#333] dark:text-gray-200 hover:text-[#800000] hover:bg-[#800000]/5 dark:hover:bg-gray-800 rounded transition-colors duration-200"
            >
              Check Status
            </a>
            <div class="pt-3 mt-1 border-t border-gray-100 dark:border-gray-800">
              <button
                @click="goToLogin"
                class="w-full px-5 py-3 rounded text-sm font-bold text-white bg-[#800000] hover:bg-[#6a0000] transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#800000]"
              >
                Log In to Apply
              </button>
            </div>
          </div>
        </div>
      </transition>
    </header>

    <!-- ═══════════════════════════════════════════════════════════════
         HERO
    ═══════════════════════════════════════════════════════════════ -->
    <section class="relative min-h-screen flex flex-col" aria-label="Hero">

      <!-- Gold top bar — institutional accent -->
      <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-[#800000] via-[#FFD700] to-[#800000] z-40"></div>

      <!-- Split layout: left text column + right image column -->
      <div class="flex flex-col lg:flex-row min-h-screen pt-16">

        <!-- Left: Text panel -->
        <div class="relative z-10 flex flex-col justify-center px-6 sm:px-10 lg:px-16 xl:px-24 pt-16 pb-16 lg:py-0 lg:w-[52%] bg-white dark:bg-slate-950">

          <!-- Eyebrow -->
          <div class="hero-eyebrow flex items-center gap-3 mb-8">
            <div class="h-px w-12 bg-[#FFD700]"></div>
            <span class="text-xs font-bold uppercase tracking-[0.2em] text-[#800000]">
              Official Admissions Portal
            </span>
          </div>

          <!-- Headline -->
          <h1 class="hero-headline font-extrabold text-[#151515] dark:text-white leading-[1.05] mb-6">
            <span class="block text-[clamp(2.5rem,5vw,4rem)]">Your PUP</span>
            <span class="block text-[clamp(2.5rem,5vw,4rem)]">Journey</span>
            <span class="block text-[clamp(2.5rem,5vw,4rem)] text-[#800000]">Starts Here.</span>
          </h1>

          <!-- Sub -->
          <p class="hero-sub text-base sm:text-lg text-[#555] dark:text-gray-400 leading-relaxed max-w-[44ch] mb-10">
            PUPTAS is the official digital admissions platform for
            <strong class="text-[#151515] font-semibold">PUP Taguig Campus</strong>.
            Complete your application, submit documents, and track every stage of your admission — entirely online.
          </p>

          <!-- CTAs -->
          <div class="hero-ctas flex flex-col sm:flex-row gap-3 mb-14">
            <button
              @click="goToLogin"
              class="group inline-flex items-center justify-center gap-2.5 px-8 py-3.5 rounded font-bold text-sm text-white bg-[#800000] hover:bg-[#6a0000] active:scale-95 transition-all duration-200 shadow-md hover:shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#800000] focus-visible:ring-offset-2"
            >
              Start Your Application
              <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform duration-200" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                <path d="M3 8h10M9 4l4 4-4 4"/>
              </svg>
            </button>
            <a
              href="/admission-results"
              class="inline-flex items-center justify-center gap-2.5 px-8 py-3.5 rounded font-bold text-sm text-[#800000] border-2 border-[#800000]/25 hover:border-[#800000] hover:bg-[#800000]/5 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#800000] focus-visible:ring-offset-2"
            >
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
              </svg>
              Check Admission Status
            </a>
          </div>

          <!-- Stats strip -->
          <div class="hero-stats grid grid-cols-2 gap-4 sm:gap-8 pt-8 border-t border-gray-100 dark:border-gray-800">
            <div v-for="stat in stats" :key="stat.label">
              <div class="text-2xl sm:text-3xl font-extrabold text-[#800000] tabular-nums">
                {{ stat.value.toLocaleString() }}{{ stat.suffix }}
              </div>
              <div class="text-xs text-[#888] dark:text-gray-500 font-semibold mt-0.5 leading-tight">{{ stat.label }}</div>
            </div>
          </div>
        </div>

        <!-- Right: Image panel -->
        <div class="relative lg:w-[48%] h-72 sm:h-96 lg:h-auto overflow-hidden bg-[#800000]">
          <!-- Primary campus photo -->
          <img
            src="/assets/images/1.jpg"
            alt="PUP Taguig Campus"
            class="absolute inset-0 w-full h-full object-cover object-center"
            fetchpriority="high"
          />
          <!-- Maroon overlay for brand cohesion -->
          <div class="absolute inset-0 bg-[#800000]/40 mix-blend-multiply"></div>
          <!-- Bottom gradient fade on mobile -->
          <div class="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-white to-transparent lg:hidden"></div>

          <!-- Floating credential chip -->
          <div class="absolute top-6 right-6 bg-white/95 dark:bg-gray-800/95 backdrop-blur rounded shadow-lg px-4 py-3 max-w-[180px]">
            <div class="flex items-center gap-2.5">
              <img src="/assets/images/pup_logo.png" alt="" class="w-8 h-8 object-contain flex-shrink-0" aria-hidden="true" />
              <div>
                <div class="text-[11px] font-extrabold text-[#800000] leading-tight">PUP Taguig</div>
                <div class="text-[10px] text-[#555] dark:text-gray-400 leading-tight">Campus Est. 1990</div>
              </div>
            </div>
          </div>

          <!-- Gold accent bar — vertical, desktop only -->
          <div class="hidden lg:block absolute left-0 top-1/4 bottom-1/4 w-1 bg-[#FFD700] rounded-r"></div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════
         MAROON BAND — ADMISSIONS OVERVIEW
    ═══════════════════════════════════════════════════════════════ -->
    <section id="admissions" class="bg-[#800000] py-16 sm:py-20 overflow-hidden" aria-label="Admissions overview">
      <div class="max-w-screen-xl mx-auto px-6 lg:px-10">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-10">

          <div data-reveal class="reveal-left lg:max-w-xl">
            <p class="text-[#FFD700] text-xs font-bold uppercase tracking-[0.2em] mb-4">Admissions 2024–2025</p>
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white leading-[1.1] mb-6">
              PUP Taguig is Now<br>Accepting Applications.
            </h2>
            <p class="text-white/75 text-base leading-relaxed">
              Qualified PUPCET passers are invited to complete their online application through PUPTAS. The portal manages every step from document submission to enrollment confirmation.
            </p>
          </div>

          <div data-reveal class="reveal-right grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-1 gap-4 lg:min-w-[280px]">
            <!-- Feature chips -->
            <div
              v-for="(item, i) in [
                { label: 'Secure document upload',       icon: 'lock'   },
                { label: 'Real-time status tracking',    icon: 'eye'    },
                { label: 'Online interview scheduling',  icon: 'cal'    },
              ]"
              :key="i"
              class="flex items-center gap-3 bg-white/10 rounded px-4 py-3.5"
            >
              <!-- Icon -->
              <div class="flex-shrink-0 w-8 h-8 rounded bg-[#FFD700]/15 flex items-center justify-center">
                <svg v-if="item.icon === 'lock'" class="w-4 h-4 text-[#FFD700]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <svg v-else-if="item.icon === 'eye'" class="w-4 h-4 text-[#FFD700]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else-if="item.icon === 'cal'" class="w-4 h-4 text-[#FFD700]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              </div>
              <span class="text-sm font-semibold text-white">{{ item.label }}</span>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════
         ADMISSIONS PROCESS
    ═══════════════════════════════════════════════════════════════ -->
    <section id="process" class="py-20 sm:py-28 bg-[#FAFAFA] dark:bg-gray-800" aria-label="Admissions process">
      <div class="max-w-screen-xl mx-auto px-6 lg:px-10">

        <!-- Section header -->
        <div data-reveal class="reveal-up mb-14 max-w-2xl">
          <p class="text-[#800000] text-xs font-bold uppercase tracking-[0.2em] mb-3">How to Apply</p>
          <h2 class="text-3xl sm:text-4xl font-extrabold text-[#151515] dark:text-white leading-tight mb-4">
            Eight Steps to<br>Your PUP Admission.
          </h2>
          <p class="text-[#666] dark:text-gray-400 text-base leading-relaxed">
            From receiving your PUPCET results to official enrollment — follow this pathway through the PUPTAS admission process.
          </p>
        </div>

        <!-- Process grid: 4 columns desktop, 2 tablet, 1 mobile -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-px bg-gray-200 dark:bg-gray-700 rounded overflow-hidden shadow-sm">
          <div
            v-for="(step, i) in steps"
            :key="step.number"
            data-reveal
            :style="{ '--reveal-delay': `${i * 60}ms` }"
            class="reveal-up bg-white dark:bg-gray-900 p-6 sm:p-8 flex flex-col gap-4 hover:bg-[#800000]/[0.02] dark:hover:bg-maroon-900/20 transition-colors duration-200 group cursor-default"
          >
            <!-- Step number + phase -->
            <div class="flex items-start justify-between gap-2">
              <span class="text-[2.5rem] font-extrabold text-[#800000]/10 group-hover:text-[#800000]/20 leading-none transition-colors duration-200 select-none">
                {{ step.number }}
              </span>
              <span class="inline-block text-[10px] font-bold uppercase tracking-widest text-[#800000] bg-[#800000]/8 px-2 py-0.5 rounded mt-1 flex-shrink-0">
                {{ step.phase }}
              </span>
            </div>

            <!-- Icon -->
            <div class="w-10 h-10 rounded bg-[#800000]/8 group-hover:bg-[#800000] flex items-center justify-center transition-colors duration-200 flex-shrink-0">
              <svg v-if="step.icon === 'mail'"       class="w-5 h-5 text-[#800000] group-hover:text-white transition-colors duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 7L2 7"/></svg>
              <svg v-else-if="step.icon === 'key'"   class="w-5 h-5 text-[#800000] group-hover:text-white transition-colors duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/></svg>
              <svg v-else-if="step.icon === 'user'"  class="w-5 h-5 text-[#800000] group-hover:text-white transition-colors duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <svg v-else-if="step.icon === 'chart'" class="w-5 h-5 text-[#800000] group-hover:text-white transition-colors duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
              <svg v-else-if="step.icon === 'folder'"class="w-5 h-5 text-[#800000] group-hover:text-white transition-colors duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
              <svg v-else-if="step.icon === 'chat'"  class="w-5 h-5 text-[#800000] group-hover:text-white transition-colors duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              <svg v-else-if="step.icon === 'heart'" class="w-5 h-5 text-[#800000] group-hover:text-white transition-colors duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
              <svg v-else-if="step.icon === 'graduation'" class="w-5 h-5 text-[#800000] group-hover:text-white transition-colors duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            </div>

            <!-- Text -->
            <div>
              <h3 class="text-sm font-bold text-[#151515] dark:text-white mb-1.5 leading-snug">{{ step.title }}</h3>
              <p class="text-xs text-[#777] dark:text-gray-400 leading-relaxed">{{ step.body }}</p>
            </div>
          </div>
        </div>

        <!-- Inline CTA -->
        <div data-reveal class="reveal-up mt-10 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded">
          <div>
            <p class="text-sm font-bold text-[#151515] dark:text-white mb-0.5">Ready to begin?</p>
            <p class="text-sm text-[#777] dark:text-gray-400">Log in to PUPTAS to start your application.</p>
          </div>
          <button
            @click="goToLogin"
            class="flex-shrink-0 inline-flex items-center gap-2 px-6 py-2.5 rounded text-sm font-bold text-white bg-[#800000] hover:bg-[#6a0000] active:scale-95 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#800000] focus-visible:ring-offset-2"
          >
            Log In to Apply
            <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
          </button>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════
         CAMPUS PHOTO STRIP
    ═══════════════════════════════════════════════════════════════ -->
    <section class="relative h-72 sm:h-96 overflow-hidden" aria-hidden="true">
      <img
        src="/assets/images/3.jpg"
        alt=""
        class="absolute inset-0 w-full h-full object-cover object-center"
        loading="lazy"
      />
      <!-- Maroon wash -->
      <div class="absolute inset-0 bg-[#800000]/55 mix-blend-multiply"></div>
      <!-- Content overlay -->
      <div class="relative z-10 h-full flex items-center justify-center px-6">
        <div class="text-center">
          <p class="text-[#FFD700] text-xs font-bold uppercase tracking-[0.25em] mb-3">PUP Taguig Campus</p>
          <p class="text-white text-2xl sm:text-3xl font-extrabold leading-tight max-w-xl mx-auto">
            Delivering Quality Education<br class="hidden sm:block">Since 1990.
          </p>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════
         PROGRAMS DIRECTORY
    ═══════════════════════════════════════════════════════════════ -->
    <section id="programs" class="py-20 sm:py-28 bg-white dark:bg-slate-950" aria-label="Academic programs">
      <div class="max-w-screen-xl mx-auto px-6 lg:px-10">

        <div class="flex flex-col lg:flex-row lg:gap-20">

          <!-- Left: sticky label -->
          <div class="lg:w-80 lg:flex-shrink-0 mb-12 lg:mb-0">
            <div data-reveal class="reveal-left lg:sticky lg:top-24">
              <p class="text-[#800000] text-xs font-bold uppercase tracking-[0.2em] mb-3">Academic Programs</p>
              <h2 class="text-3xl sm:text-4xl font-extrabold text-[#151515] dark:text-white leading-tight mb-5">
                Programs<br>Offered at<br>PUP Taguig.
              </h2>
              <p class="text-[#666] dark:text-gray-400 text-sm leading-relaxed mb-6">
                PUP Taguig offers undergraduate programs across engineering, technology, business, and the sciences. Admission eligibility is determined by your PUPCET result, SHS strand, and grade average.
              </p>
              <a
                href="/admission-results"
                class="inline-flex items-center gap-2 text-sm font-bold text-[#800000] hover:text-[#6a0000] underline-offset-4 hover:underline transition-colors duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#800000] rounded"
              >
                Check your eligible programs
                <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
              </a>
            </div>
          </div>

          <!-- Right: programs list -->
          <div class="flex-1" data-reveal>
            <ul class="reveal-up space-y-3" aria-label="Programs offered at PUP Taguig">
              <li
                v-for="program in programs"
                :key="program.code"
                class="flex items-center gap-3 text-sm text-[#444] dark:text-gray-300"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-[#FFD700] flex-shrink-0" aria-hidden="true"></span>
                {{ program.name }}
              </li>
            </ul>
          </div>

        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════
         FAQ
    ═══════════════════════════════════════════════════════════════ -->
    <section id="faq" class="py-20 sm:py-28 bg-[#FAFAFA] dark:bg-gray-800" aria-label="Frequently asked questions">
      <div class="max-w-screen-xl mx-auto px-6 lg:px-10">

        <div class="max-w-screen-md mx-auto">
          <!-- Header -->
          <div data-reveal class="reveal-up text-center mb-14">
            <p class="text-[#800000] text-xs font-bold uppercase tracking-[0.2em] mb-3">Common Questions</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-[#151515] dark:text-white leading-tight">
              Frequently Asked Questions
            </h2>
          </div>

          <!-- Accordion -->
          <div class="space-y-2">
            <div
              v-for="(faq, i) in faqs"
              :key="i"
              data-reveal
              :style="{ '--reveal-delay': `${i * 50}ms` }"
              class="reveal-up bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded overflow-hidden"
            >
              <button
                type="button"
                class="w-full flex items-center justify-between gap-4 px-6 py-5 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#800000] group"
                :aria-expanded="openFaq === i"
                @click="toggleFaq(i)"
              >
                <span class="text-sm font-semibold text-[#151515] dark:text-gray-200 group-hover:text-[#800000] transition-colors duration-200 pr-2">{{ faq.q }}</span>
                <span
                  class="flex-shrink-0 w-6 h-6 rounded-full border border-gray-200 dark:border-gray-700 group-hover:border-[#800000] group-hover:bg-[#800000] flex items-center justify-center transition-all duration-200"
                  :class="openFaq === i ? 'bg-[#800000] border-[#800000]' : ''"
                  aria-hidden="true"
                >
                  <svg
                    class="w-3 h-3 transition-transform duration-200"
                    :class="openFaq === i ? 'rotate-180 text-white' : 'text-[#800000] group-hover:text-white'"
                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                  >
                    <polyline points="6 9 12 15 18 9"/>
                  </svg>
                </span>
              </button>

              <transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
              >
                <div v-show="openFaq === i" class="px-6 pb-5 border-t border-gray-100 dark:border-gray-800">
                  <p class="text-sm text-[#555] dark:text-gray-400 leading-relaxed pt-4">{{ faq.a }}</p>
                </div>
              </transition>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════
         FINAL CTA
    ═══════════════════════════════════════════════════════════════ -->
    <section class="relative overflow-hidden bg-[#800000] py-20 sm:py-28" aria-label="Call to action">

      <!-- Geometric accent shapes -->
      <div class="absolute top-0 right-0 w-72 h-72 bg-[#FFD700]/10 rounded-full translate-x-1/2 -translate-y-1/2 pointer-events-none" aria-hidden="true"></div>
      <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/5 rounded-full -translate-x-1/2 translate-y-1/2 pointer-events-none" aria-hidden="true"></div>
      <!-- Gold horizontal rule -->
      <div class="absolute top-0 left-0 right-0 h-1 bg-[#FFD700]/40" aria-hidden="true"></div>

      <div class="relative max-w-screen-xl mx-auto px-6 lg:px-10">
        <div class="max-w-3xl">
          <p data-reveal class="reveal-up text-[#FFD700] text-xs font-bold uppercase tracking-[0.2em] mb-5">Begin Your Application</p>
          <h2 data-reveal class="reveal-up text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-[1.05] mb-8">
            Ready to<br>Pursue Your<br class="hidden sm:block">PUP Dream?
          </h2>
          <p data-reveal class="reveal-up text-white/70 text-base sm:text-lg leading-relaxed mb-10 max-w-[48ch]">
            Log in to PUPTAS using your PUP Identity Provider account. No account yet? Register using the link in your PUPCET results email.
          </p>
          <div data-reveal class="reveal-up flex flex-col sm:flex-row gap-4">
            <button
              @click="goToLogin"
              class="group inline-flex items-center justify-center gap-2.5 px-8 py-4 rounded font-bold text-sm text-[#800000] bg-white hover:bg-[#FFFBEA] active:scale-95 transition-all duration-200 shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#800000]"
            >
              Log In to PUPTAS
              <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform duration-200" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                <path d="M3 8h10M9 4l4 4-4 4"/>
              </svg>
            </button>
            <a
              href="/admission-results"
              class="inline-flex items-center justify-center gap-2.5 px-8 py-4 rounded font-bold text-sm text-white border-2 border-white/30 hover:border-white/60 hover:bg-white/10 transition-all duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#800000]"
            >
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
              </svg>
              Check Admission Status
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- ═══════════════════════════════════════════════════════════════
         FOOTER
    ═══════════════════════════════════════════════════════════════ -->
    <footer class="bg-[#151515] text-white/60" role="contentinfo">
      <div class="max-w-screen-xl mx-auto px-6 lg:px-10 py-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">

        <!-- Brand -->
        <div class="flex items-center gap-3">
          <img src="/assets/images/pup_logo.png" alt="PUP Taguig Logo" class="h-8 w-8 object-contain opacity-70" />
          <div>
            <div class="text-sm font-bold text-white/80">PUPTAS</div>
            <div class="text-xs">PUP Taguig Admission System</div>
          </div>
        </div>

        <!-- Links -->
        <nav class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-medium" aria-label="Footer navigation">
          <a href="https://www.pup.edu.ph/terms/" target="_blank" rel="noopener noreferrer"
            class="hover:text-white transition-colors duration-200 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-white rounded">
            Terms of Use
          </a>
          <a href="https://www.pup.edu.ph/privacy/" target="_blank" rel="noopener noreferrer"
            class="hover:text-white transition-colors duration-200 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-white rounded">
            Privacy Statement
          </a>
          <a href="/admission-results"
            class="hover:text-white transition-colors duration-200 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-white rounded">
            Check Status
          </a>
        </nav>

        <!-- Copyright -->
        <p class="text-xs flex-shrink-0">
          © 1990–{{ currentYear }} PUP Taguig. All rights reserved.
        </p>
      </div>
    </footer>

  </div>
</template>

<style scoped>
/* ── Scroll-reveal system ─────────────────────────────────────────────────── */
[data-reveal] {
  opacity: 0;
  transition:
    opacity 0.55s cubic-bezier(0.22, 1, 0.36, 1),
    transform 0.55s cubic-bezier(0.22, 1, 0.36, 1);
  transition-delay: var(--reveal-delay, 0ms);
}

.reveal-up     { transform: translateY(28px); }
.reveal-left   { transform: translateX(-28px); }
.reveal-right  { transform: translateX(28px); }

[data-reveal].is-revealed {
  opacity: 1;
  transform: none;
}

/* ── Hero entrance sequence ───────────────────────────────────────────────── */
.hero-eyebrow {
  opacity: 0;
  transform: translateY(16px);
  animation: heroIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) 0.15s forwards;
}
.hero-headline {
  opacity: 0;
  transform: translateY(20px);
  animation: heroIn 0.65s cubic-bezier(0.22, 1, 0.36, 1) 0.3s forwards;
}
.hero-sub {
  opacity: 0;
  transform: translateY(16px);
  animation: heroIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) 0.45s forwards;
}
.hero-ctas {
  opacity: 0;
  transform: translateY(14px);
  animation: heroIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) 0.6s forwards;
}
.hero-stats {
  opacity: 0;
  animation: heroFade 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.8s forwards;
}

@keyframes heroIn {
  to { opacity: 1; transform: none; }
}
@keyframes heroFade {
  to { opacity: 1; }
}

/* ── Reduced-motion overrides ─────────────────────────────────────────────── */
@media (prefers-reduced-motion: reduce) {
  [data-reveal],
  .hero-eyebrow,
  .hero-headline,
  .hero-sub,
  .hero-ctas,
  .hero-stats {
    opacity: 1 !important;
    transform: none !important;
    animation: none !important;
    transition: none !important;
  }
}

/* ── Global transition baseline ──────────────────────────────────────────── */
*, *::before, *::after {
  transition-property: color, background-color, border-color, box-shadow, transform, opacity, text-decoration-color;
  transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
  transition-duration: 200ms;
}

/* Prevent transition conflict with reveal animations */
[data-reveal]:not(.is-revealed) * {
  transition-duration: 0ms;
}

button:active, a:active {
  transition-duration: 0ms;
}
</style>
