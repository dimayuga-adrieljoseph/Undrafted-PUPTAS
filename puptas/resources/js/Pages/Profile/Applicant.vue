<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue';

const props = defineProps({
  user: Object,
  applicantProfile: Object,
  grades: Object,
  files: Array,
  application: Object,
  formerSchool: Object,
});

const p = computed(() => props.applicantProfile ?? {});
const u = computed(() => props.user ?? {});

const fullName = computed(() => {
  const parts = [
    u.value.firstname,
    u.value.middlename ? u.value.middlename[0] + '.' : null,
    u.value.lastname,
    p.value.extension_name || null,
  ].filter(Boolean);
  return parts.join(' ');
});

const initials = computed(() =>
  `${u.value.firstname?.[0] || ''}${u.value.lastname?.[0] || ''}`.toUpperCase()
);

const formatDateDisplay = (dateVal) => {
  if (!dateVal) return '—';
  const d = new Date(dateVal);
  if (isNaN(d.getTime())) return '—';
  return d.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });
};

const statusBadgeClass = (status) => {
  const map = {
    pending:    'bg-yellow-100 text-yellow-800 dark:bg-yellow-400/15 dark:text-yellow-300',
    submitted:  'bg-blue-100 text-blue-800 dark:bg-blue-400/15 dark:text-blue-300',
    approved:   'bg-green-100 text-green-800 dark:bg-green-400/15 dark:text-green-300',
    rejected:   'bg-red-100 text-red-800 dark:bg-red-400/15 dark:text-red-300',
    waitlisted: 'bg-orange-100 text-orange-800 dark:bg-orange-400/15 dark:text-orange-300',
    returned:   'bg-red-100 text-red-800 dark:bg-red-400/15 dark:text-red-300',
    failed:     'bg-red-100 text-red-800 dark:bg-red-400/15 dark:text-red-300',
    uploading:  'bg-blue-100 text-blue-800 dark:bg-blue-400/15 dark:text-blue-300',
    completed:  'bg-green-100 text-green-800 dark:bg-green-400/15 dark:text-green-300',
    in_progress:'bg-blue-100 text-blue-800 dark:bg-blue-400/15 dark:text-blue-300',
    not_enrolled:'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    officially_enrolled:'bg-green-100 text-green-800 dark:bg-green-400/15 dark:text-green-300',
  };
  return (map[status] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300')
    + ' inline-flex items-center text-[0.68rem] font-bold px-2.5 py-0.5 rounded-full whitespace-nowrap capitalize tracking-wide';
};

const appStatusLabel = computed(() => {
  const map = { pending:'Pending', submitted:'Submitted', approved:'Approved', rejected:'Rejected', waitlisted:'Waitlisted' };
  return map[props.application?.status] ?? props.application?.status ?? 'N/A';
});
const enrollmentLabel = computed(() => {
  const map = { not_enrolled:'Not Enrolled', officially_enrolled:'Officially Enrolled', waitlisted:'Waitlisted' };
  return map[props.application?.enrollment_status] ?? props.application?.enrollment_status ?? 'N/A';
});

const gradeGroups = computed(() => {
  const g = props.grades;
  if (!g) return [];
  const strand = (props.applicantProfile?.strand || '').toUpperCase();
  const groups = [];
  const core = [];
  if (g.english != null) core.push({ label: 'English (Average)', value: g.english });
  if (g.mathematics != null) core.push({ label: 'Mathematics (Average)', value: g.mathematics });
  if (g.science != null) core.push({ label: 'Science (Average)', value: g.science });
  if (g.g12_first_sem != null) core.push({ label: 'G12 First Semester Average', value: g.g12_first_sem });
  if (g.g12_second_sem != null) core.push({ label: 'G12 Second Semester Average', value: g.g12_second_sem });
  if (core.length) groups.push({ title: 'Core Averages', subjects: core });

  const g11EnglishFields = strand === 'STEM'
    ? [{ key: 'g11_oral_communication', label: 'Oral Communication' }, { key: 'g11_reading_writing', label: 'Reading & Writing' }]
    : [{ key: 'g11_oral_communication', label: 'Oral Communication' }, { key: 'g11_21st_century_lit', label: '21st Century Literature' }, { key: 'g11_academic_professional', label: 'Academic & Professional Literacy' }, { key: 'g11_reading_writing', label: 'Reading & Writing' }];

  const g11 = [
    ...g11EnglishFields,
    { key: 'g11_general_mathematics', label: 'General Mathematics' },
    { key: 'g11_statistics_probability', label: 'Statistics & Probability' },
    { key: 'g11_earth_life_science', label: 'Earth & Life Science' },
    { key: 'g11_physical_science', label: 'Physical Science' },
    { key: 'g11_business_mathematics', label: 'Business Mathematics' },
    { key: 'g11_pre_calculus', label: 'Pre-Calculus' },
    { key: 'g11_basic_calculus', label: 'Basic Calculus' },
    { key: 'g11_earth_science', label: 'Earth Science' },
    { key: 'g11_general_chemistry_1', label: 'General Chemistry 1' },
  ].filter(s => g[s.key] != null).map(s => ({ label: s.label, value: g[s.key] }));
  if (g11.length) groups.push({ title: 'Grade 11 Subjects', subjects: g11 });

  const g12 = [
    { key: 'g12_21st_century_lit', label: '21st Century Literature' },
    { key: 'g12_academic_professional', label: 'Academic & Professional Literacy' },
    { key: 'g12_general_physics_1', label: 'General Physics 1' },
    { key: 'g12_general_physics_2', label: 'General Physics 2' },
    { key: 'g12_general_biology_1', label: 'General Biology 1' },
    { key: 'g12_general_biology_2', label: 'General Biology 2' },
    { key: 'g12_general_chemistry_2', label: 'General Chemistry 2' },
    { key: 'g12_earth_life_science', label: 'Earth & Life Science' },
    { key: 'g12_physical_science', label: 'Physical Science' },
  ].filter(s => g[s.key] != null).map(s => ({ label: s.label, value: g[s.key] }));
  if (g12.length) groups.push({ title: 'Grade 12 Subjects', subjects: g12 });

  const dynamic = g.dynamic_subjects ?? [];
  if (dynamic.length) groups.push({ title: 'Additional Subjects', subjects: dynamic.map(s => ({ label: s.subject ?? s.name ?? 'Subject', value: s.grade ?? s.value })) });
  return groups;
});

const gradeColor = (val) => {
  const n = parseFloat(val);
  if (isNaN(n)) return 'text-gray-500 dark:text-gray-400';
  if (n >= 90) return 'text-green-600 dark:text-green-400';
  if (n >= 80) return 'text-blue-600 dark:text-blue-400';
  if (n >= 75) return 'text-yellow-600 dark:text-yellow-400';
  return 'text-red-600 dark:text-red-400';
};

const activeTab = ref('profile');
const tabs = [
  { id: 'profile',     label: 'Profile',      icon: 'M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z' },
  { id: 'application', label: 'Application',  icon: 'M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z' },
  { id: 'grades',      label: 'Grades',        icon: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z' },
];

const formatGrade = (val) => {
  if (val === '' || val == null) return '—';
  const num = parseFloat(val);
  return isNaN(num) ? '—' : Number(num).toFixed(2);
};

// ── Academic Background + Former School form ──────────────────────────────
const academicForm = useForm({
  school:                  props.formerSchool?.school                ?? props.applicantProfile?.school ?? '',
  former_school_address:   props.formerSchool?.former_school_address  ?? '',
  former_school_principal: props.formerSchool?.former_school_principal ?? '',
});

const academicSaved   = ref(false);
const academicEditing = ref(false);

const formerSchoolComplete = computed(() =>
  academicForm.school.trim() !== '' && academicForm.former_school_address.trim() !== ''
);

const submitAcademic = () => {
  academicForm.post(route('applicant.profile.update-former-school'), {
    preserveScroll: true,
    onSuccess: () => {
      academicSaved.value   = true;
      academicEditing.value = false;
      setTimeout(() => { academicSaved.value = false; }, 4000);
    },
  });
};

const cancelAcademic = () => {
  academicForm.school                  = props.formerSchool?.school                ?? props.applicantProfile?.school ?? '';
  academicForm.former_school_address   = props.formerSchool?.former_school_address  ?? '';
  academicForm.former_school_principal = props.formerSchool?.former_school_principal ?? '';
  academicForm.clearErrors();
  academicEditing.value = false;
};
</script>

<template>
  <Head title="My Profile" />
  <ApplicantLayout title="My Profile">
    <template #title>My Profile</template>

    <div class="py-8">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-5">

        <!-- ── Hero Card (crimson gradient) ── -->
        <div class="hero-card rounded-2xl overflow-hidden shadow-[0_4px_24px_rgba(158,18,44,.32)]">
          <div class="px-6 pt-6 pb-5 relative z-[1]">
            <div class="flex flex-wrap items-center gap-4">
              <!-- Avatar -->
              <div class="w-[68px] h-[68px] rounded-2xl flex items-center justify-center text-[1.35rem] font-extrabold text-white flex-shrink-0 border-2 border-white/40 shadow-[0_4px_18px_rgba(0,0,0,.18)]"
                   style="background:rgba(255,255,255,.18)">
                {{ initials }}
              </div>
              <!-- Name + chips -->
              <div class="min-w-0">
                <h1 class="text-[1.1rem] font-extrabold text-white leading-snug mb-2 truncate">{{ fullName }}</h1>
                <div class="flex flex-wrap gap-1.5 items-center">
                  <span class="text-[0.65rem] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full text-white border border-white/30"
                        style="background:rgba(255,255,255,.22)">Applicant</span>
                  <span v-if="user.test_passer?.reference_number"
                        class="inline-flex items-center gap-1 text-[0.68rem] font-semibold rounded-full px-2.5 py-0.5 border border-white/25"
                        style="color:rgba(255,255,255,.92);background:rgba(255,255,255,.14)">
                    <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                    Ref: {{ user.test_passer.reference_number }}
                  </span>
                </div>
              </div>
            </div>
            <!-- Contact strip -->
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-4 pt-3.5 border-t border-white/20">
              <span class="inline-flex items-center gap-1.5 text-[0.78rem] text-white/80">
                <svg class="w-3.5 h-3.5 shrink-0 text-white/55" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                {{ user.email }}
              </span>
            </div>
          </div>
        </div>

        <!-- ── Tab Bar ── -->
        <div class="flex overflow-x-auto bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm scrollbar-none p-1 gap-1">
          <button v-for="tab in tabs" :key="tab.id"
                  @click="activeTab = tab.id"
                  :class="[
                    'flex items-center gap-2 px-4 py-2.5 text-[0.8rem] font-semibold whitespace-nowrap rounded-xl transition-all duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-1',
                    activeTab === tab.id
                      ? 'bg-brand text-white shadow-sm dark:bg-red-600'
                      : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700'
                  ]">
            <svg class="w-[15px] h-[15px] shrink-0" viewBox="0 0 24 24" fill="currentColor"><path :d="tab.icon"/></svg>
            <span class="hidden sm:inline">{{ tab.label }}</span>
          </button>
        </div>


        <!-- ── Profile Tab ── -->
        <div v-show="activeTab === 'profile'" class="animate-fadeIn">
          <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-5">

            <!-- Personal Information -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
              <div class="flex items-center gap-3 px-5 py-3.5 border-b border-gray-100 dark:border-gray-700/80 bg-gray-50/60 dark:bg-gray-800/60">
                <div class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-400/10 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 icon-brand dark:fill-red-400" viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                </div>
                <h2 class="text-[0.88rem] font-bold text-gray-800 dark:text-gray-100 tracking-[-0.01em]">Personal Information</h2>
              </div>
              <dl class="divide-y divide-gray-100 dark:divide-gray-700/60">
                <div v-for="(item, i) in [
                  { label: 'First Name',      val: user.firstname },
                  { label: 'Last Name',       val: user.lastname },
                  { label: 'Middle Name',     val: user.middlename },
                  { label: 'Extension Name',  val: applicantProfile?.extension_name },
                  { label: 'Email',           val: user.email },
                ]" :key="i"
                  class="flex justify-between items-baseline gap-4 px-5 py-2.5 text-[0.82rem]">
                  <dt class="text-gray-500 dark:text-gray-400 shrink-0">{{ item.label }}</dt>
                  <dd class="font-semibold text-gray-900 dark:text-gray-100 text-right break-all">{{ item.val || '—' }}</dd>
                </div>
              </dl>
            </div>

            <!-- Sidebar -->
            <aside class="flex flex-col gap-5">

              <!-- Academic Background -->
              <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="flex items-center gap-3 px-5 py-3.5 border-b border-gray-100 dark:border-gray-700/80 bg-gray-50/60 dark:bg-gray-800/60">
                  <div class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-400/10 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 icon-brand dark:fill-red-400" viewBox="0 0 24 24"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
                  </div>
                  <h3 class="flex-1 text-[0.88rem] font-bold text-gray-800 dark:text-gray-100 tracking-[-0.01em]">Academic Background</h3>
                  <button v-if="!academicEditing" @click="academicEditing = true"
                          class="inline-flex items-center gap-1 text-[0.72rem] font-semibold px-2.5 py-1 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:border-gray-300 dark:hover:border-gray-500 hover:bg-gray-50 dark:hover:bg-gray-600 transition-all duration-150">
                    <svg class="w-3 h-3 fill-gray-400 dark:fill-gray-400" viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                    Edit
                  </button>
                </div>

                <!-- View mode -->
                <template v-if="!academicEditing">
                  <dl class="divide-y divide-gray-100 dark:divide-gray-700/60">
                    <div class="flex justify-between items-baseline gap-4 px-5 py-2.5 text-[0.82rem]">
                      <dt class="text-gray-500 dark:text-gray-400 shrink-0">Strand</dt>
                      <dd class="font-semibold text-gray-900 dark:text-gray-100 text-right">{{ applicantProfile?.strand || '—' }}</dd>
                    </div>
                    <div class="flex justify-between items-baseline gap-4 px-5 py-2.5 text-[0.82rem]">
                      <dt class="text-gray-500 dark:text-gray-400 shrink-0">Date Graduated</dt>
                      <dd class="font-semibold text-gray-900 dark:text-gray-100 text-right">{{ formatDateDisplay(applicantProfile?.date_graduated) }}</dd>
                    </div>
                    <!-- F137 sub-header -->
                    <div class="px-5 pt-3 pb-1.5">
                      <span class="inline-flex items-center gap-1.5 text-[0.65rem] font-extrabold text-brand dark:text-red-400 uppercase tracking-widest">
                        <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                        For F137 Request Letter
                      </span>
                    </div>
                    <div class="flex justify-between items-baseline gap-4 px-5 py-2.5 text-[0.82rem]">
                      <dt class="text-gray-500 dark:text-gray-400 shrink-0">School (SHS)</dt>
                      <dd class="font-semibold text-gray-900 dark:text-gray-100 text-right">{{ academicForm.school || '—' }}</dd>
                    </div>
                    <div class="flex justify-between items-baseline gap-4 px-5 py-2.5 text-[0.82rem]">
                      <dt class="text-gray-500 dark:text-gray-400 shrink-0">School Address</dt>
                      <dd class="font-semibold text-gray-900 dark:text-gray-100 text-right">{{ academicForm.former_school_address || '—' }}</dd>
                    </div>
                    <div class="flex justify-between items-baseline gap-4 px-5 py-2.5 text-[0.82rem]">
                      <dt class="text-gray-500 dark:text-gray-400 shrink-0">Principal / Registrar</dt>
                      <dd class="font-semibold text-gray-900 dark:text-gray-100 text-right">{{ academicForm.former_school_principal || '—' }}</dd>
                    </div>
                  </dl>
                  <Transition name="fade">
                    <div v-if="academicSaved"
                         class="flex items-center gap-1.5 px-5 py-2.5 text-[0.76rem] font-medium text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-400/10 border-t border-green-100 dark:border-green-400/20">
                      <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                      <span v-if="formerSchoolComplete">Saved. You can now download your F137 Request Letter from the dashboard.</span>
                      <span v-else>Saved successfully.</span>
                    </div>
                  </Transition>
                </template>

                <!-- Edit mode -->
                <form v-else @submit.prevent="submitAcademic" class="px-5 py-4 flex flex-col gap-3.5">
                  <div class="flex items-center gap-2">
                    <div class="flex-1 h-px bg-gray-200 dark:bg-gray-600"></div>
                    <span class="inline-flex items-center gap-1.5 text-[0.65rem] font-extrabold text-brand dark:text-red-400 uppercase tracking-widest whitespace-nowrap">
                      <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                      For F137 Request Letter
                    </span>
                    <div class="flex-1 h-px bg-gray-200 dark:bg-gray-600"></div>
                  </div>
                  <div>
                    <label class="block text-[0.76rem] font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                      School (SHS) <span class="text-brand dark:text-red-400">*</span>
                    </label>
                    <input v-model="academicForm.school" type="text" required placeholder="e.g. PUP Taguig SHS"
                           class="field-input w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 text-[0.82rem] text-gray-900 dark:text-gray-100 outline-none transition-colors" />
                    <p v-if="academicForm.errors.school" class="text-[0.72rem] text-red-600 dark:text-red-400 mt-1">{{ academicForm.errors.school }}</p>
                  </div>
                  <div>
                    <label class="block text-[0.76rem] font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                      School Address <span class="text-brand dark:text-red-400">*</span>
                    </label>
                    <input v-model="academicForm.former_school_address" type="text" required placeholder="e.g. General Santos Ave, Taguig City"
                           class="field-input w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 text-[0.82rem] text-gray-900 dark:text-gray-100 outline-none transition-colors" />
                    <p v-if="academicForm.errors.former_school_address" class="text-[0.72rem] text-red-600 dark:text-red-400 mt-1">{{ academicForm.errors.former_school_address }}</p>
                  </div>
                  <div>
                    <label class="block text-[0.76rem] font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                      Principal / Registrar <span class="text-[0.68rem] font-normal text-gray-400">(optional)</span>
                    </label>
                    <input v-model="academicForm.former_school_principal" type="text" placeholder="e.g. Maria Santos"
                           class="field-input w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-900 text-[0.82rem] text-gray-900 dark:text-gray-100 outline-none transition-colors" />
                    <p class="text-[0.68rem] text-gray-400 mt-1 leading-snug">If blank, the letter will address "THE PRINCIPAL/REGISTRAR".</p>
                  </div>
                  <div class="flex gap-2 pt-0.5">
                    <button type="submit" :disabled="academicForm.processing"
                            class="btn-brand text-[0.78rem] font-bold px-4 py-2 rounded-lg text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed min-h-[36px]">
                      {{ academicForm.processing ? 'Saving…' : 'Save' }}
                    </button>
                    <button type="button" @click="cancelAcademic" :disabled="academicForm.processing"
                            class="text-[0.78rem] font-semibold px-3.5 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                      Cancel
                    </button>
                  </div>
                </form>
              </div>


              <!-- Program Choices -->
              <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="flex items-center gap-3 px-5 py-3.5 border-b border-gray-100 dark:border-gray-700/80 bg-gray-50/60 dark:bg-gray-800/60">
                  <div class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-400/10 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 icon-brand dark:fill-red-400" viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h10v2zm-4 4H9v-2h6v2zm4-8H9V5h10v2z"/></svg>
                  </div>
                  <h3 class="text-[0.88rem] font-bold text-gray-800 dark:text-gray-100 tracking-[-0.01em]">Program Choices</h3>
                </div>
                <div class="px-5 py-3 flex flex-col gap-2">
                  <div v-if="application?.program"
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-100 dark:border-red-500/20 transition-colors">
                    <span class="w-6 h-6 rounded-full bg-brand flex items-center justify-center text-[0.68rem] font-extrabold text-white shrink-0">1</span>
                    <div class="min-w-0">
                      <p class="text-[0.83rem] font-bold text-gray-900 dark:text-gray-100 mb-0.5 leading-snug truncate">{{ application.program.name }}</p>
                      <p class="text-[0.7rem] font-semibold text-brand dark:text-red-400">{{ application.program.code }}</p>
                    </div>
                  </div>
                  <div v-if="application?.second_choice"
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-700/40 border border-gray-200 dark:border-gray-700 transition-colors">
                    <span class="w-6 h-6 rounded-full bg-gray-400 dark:bg-gray-500 flex items-center justify-center text-[0.68rem] font-extrabold text-white shrink-0">2</span>
                    <div class="min-w-0">
                      <p class="text-[0.83rem] font-bold text-gray-900 dark:text-gray-100 mb-0.5 leading-snug truncate">{{ application.second_choice.name }}</p>
                      <p class="text-[0.7rem] font-semibold text-gray-500 dark:text-gray-400">{{ application.second_choice.code }}</p>
                    </div>
                  </div>
                  <div v-if="application?.third_choice"
                       class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-700/40 border border-gray-200 dark:border-gray-700 transition-colors">
                    <span class="w-6 h-6 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center text-[0.68rem] font-extrabold text-gray-600 dark:text-gray-300 shrink-0">3</span>
                    <div class="min-w-0">
                      <p class="text-[0.83rem] font-bold text-gray-900 dark:text-gray-100 mb-0.5 leading-snug truncate">{{ application.third_choice.name }}</p>
                      <p class="text-[0.7rem] font-semibold text-gray-500 dark:text-gray-400">{{ application.third_choice.code }}</p>
                    </div>
                  </div>
                  <p v-if="!application?.program && !application?.second_choice && !application?.third_choice"
                     class="text-[0.81rem] text-gray-400 dark:text-gray-500 py-2 text-center">No program choices recorded.</p>
                </div>
              </div>

            </aside>
          </div>
        </div>


        <!-- ── Application Tab ── -->
        <div v-show="activeTab === 'application'" class="animate-fadeIn">
          <div class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-5">

            <!-- Application Status -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
              <div class="flex items-center gap-3 px-5 py-3.5 border-b border-gray-100 dark:border-gray-700/80 bg-gray-50/60 dark:bg-gray-800/60">
                <div class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-400/10 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 icon-brand dark:fill-red-400" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                </div>
                <h2 class="text-[0.88rem] font-bold text-gray-800 dark:text-gray-100 tracking-[-0.01em]">Application Status</h2>
              </div>
              <template v-if="application">
                <dl class="divide-y divide-gray-100 dark:divide-gray-700/60">
                  <div class="flex justify-between items-center gap-4 px-5 py-2.5 text-[0.82rem]">
                    <dt class="text-gray-500 dark:text-gray-400 shrink-0">Status</dt>
                    <dd><span :class="statusBadgeClass(application.status)">{{ appStatusLabel }}</span></dd>
                  </div>
                  <div class="flex justify-between items-center gap-4 px-5 py-2.5 text-[0.82rem]">
                    <dt class="text-gray-500 dark:text-gray-400 shrink-0">Enrollment</dt>
                    <dd><span :class="statusBadgeClass(application.enrollment_status)">{{ enrollmentLabel }}</span></dd>
                  </div>
                  <div v-if="application.enrollment_position" class="flex justify-between items-baseline gap-4 px-5 py-2.5 text-[0.82rem]">
                    <dt class="text-gray-500 dark:text-gray-400 shrink-0">Position</dt>
                    <dd class="font-bold text-gray-900 dark:text-gray-100">#{{ application.enrollment_position }}</dd>
                  </div>
                  <div v-if="application.submitted_at" class="flex justify-between items-baseline gap-4 px-5 py-2.5 text-[0.82rem]">
                    <dt class="text-gray-500 dark:text-gray-400 shrink-0">Submitted</dt>
                    <dd class="font-semibold text-gray-900 dark:text-gray-100 text-right">{{ new Date(application.submitted_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) }}</dd>
                  </div>
                </dl>
              </template>
              <div v-else class="flex flex-col items-center justify-center py-10 px-6 text-center">
                <div class="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center mb-3">
                  <svg class="w-6 h-6 fill-gray-400 dark:fill-gray-500" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                </div>
                <p class="text-[0.85rem] font-semibold text-gray-600 dark:text-gray-400 mb-0.5">No application found</p>
                <p class="text-[0.75rem] text-gray-400 dark:text-gray-500">Your application details will appear here.</p>
              </div>
            </div>

            <!-- Timeline -->
            <div v-if="application?.processes?.length"
                 class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden lg:col-span-2">
              <div class="flex items-center gap-3 px-5 py-3.5 border-b border-gray-100 dark:border-gray-700/80 bg-gray-50/60 dark:bg-gray-800/60">
                <div class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-400/10 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 icon-brand dark:fill-red-400" viewBox="0 0 24 24"><path d="M13 2.05V4.05C17.39 4.59 20.5 8.58 19.96 12.97C19.5 16.61 16.64 19.5 13 19.93V21.93C18.5 21.38 22.5 16.5 21.95 11C21.5 6.25 17.73 2.5 13 2.05M11 2.06C9.05 2.25 7.19 3 5.67 4.26L7.1 5.74C8.22 4.84 9.57 4.26 11 4.06V2.06M4.26 5.67C3 7.19 2.25 9.04 2.05 11H4.05C4.24 9.58 4.8 8.23 5.69 7.1L4.26 5.67M2.06 13C2.26 14.96 3.03 16.81 4.27 18.33L5.69 16.9C4.81 15.77 4.24 14.42 4.06 13H2.06M7.1 18.37L5.67 19.74C7.18 21 9.04 21.79 11 22V20C9.58 19.82 8.23 19.25 7.1 18.37M12 7L9.5 11.5H11.5V17L14.5 12.5H12.5L12 7Z"/></svg>
                </div>
                <h2 class="text-[0.88rem] font-bold text-gray-800 dark:text-gray-100 tracking-[-0.01em]">Application Process Timeline</h2>
              </div>
              <div class="px-5 pt-4 pb-5 relative timeline-wrapper">
                <div v-for="(process, idx) in application.processes" :key="process.id"
                     class="flex gap-3.5 relative mb-3.5 last:mb-0">
                  <!-- Step dot -->
                  <div class="flex flex-col items-center shrink-0 pt-0.5">
                    <div :class="[
                      'w-3.5 h-3.5 rounded-full z-10 ring-2 ring-white dark:ring-gray-800',
                      process.status === 'completed'  ? 'bg-green-500 ring-green-200 dark:ring-green-800' :
                      process.status === 'in_progress' ? 'bg-blue-500 ring-blue-200 dark:ring-blue-800' :
                                                         'bg-gray-300 dark:bg-gray-600 ring-gray-100 dark:ring-gray-700'
                    ]"></div>
                  </div>
                  <!-- Step body -->
                  <div :class="[
                    'flex-1 rounded-xl px-3.5 py-2.5 border transition-colors',
                    process.status === 'completed'  ? 'bg-green-50 dark:bg-green-500/10 border-green-100 dark:border-green-500/20' :
                    process.status === 'in_progress' ? 'bg-blue-50 dark:bg-blue-500/10 border-blue-100 dark:border-blue-500/20' :
                                                       'bg-gray-50 dark:bg-gray-700/40 border-gray-100 dark:border-gray-700'
                  ]">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                      <p class="text-[0.82rem] font-bold text-gray-900 dark:text-gray-100 leading-snug">{{ process.name ?? process.type ?? `Step ${idx + 1}` }}</p>
                      <span :class="statusBadgeClass(process.status)">{{ process.status?.replace(/_/g, ' ') ?? 'Pending' }}</span>
                    </div>
                    <p v-if="process.updated_at" class="text-[0.7rem] text-gray-400 dark:text-gray-500 mt-1">
                      {{ new Date(process.updated_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}
                    </p>
                    <p v-if="process.remarks" class="text-[0.75rem] text-gray-600 dark:text-gray-400 mt-1.5 italic leading-snug">{{ process.remarks }}</p>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>


        <!-- ── Grades Tab ── -->
        <div v-show="activeTab === 'grades'" class="animate-fadeIn">
          <div v-if="!grades"
               class="flex flex-col items-center justify-center py-14 px-6 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="w-14 h-14 rounded-2xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center mb-4">
              <svg class="w-7 h-7 fill-gray-400 dark:fill-gray-500" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
            </div>
            <p class="text-[0.9rem] font-bold text-gray-700 dark:text-gray-300 mb-1">No grades recorded yet</p>
            <p class="text-[0.78rem] text-gray-400 dark:text-gray-500 max-w-xs">Grades will appear here once they've been encoded in the system.</p>
          </div>
          <div v-else class="flex flex-col gap-4">
            <div v-for="group in gradeGroups" :key="group.title"
                 class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
              <div class="flex items-center gap-3 px-5 py-3.5 border-b border-gray-100 dark:border-gray-700/80 bg-gray-50/60 dark:bg-gray-800/60">
                <div class="w-8 h-8 rounded-lg bg-red-50 dark:bg-red-400/10 flex items-center justify-center shrink-0">
                  <svg class="w-4 h-4 icon-brand dark:fill-red-400" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                </div>
                <h2 class="text-[0.88rem] font-bold text-gray-800 dark:text-gray-100 tracking-[-0.01em]">{{ group.title }}</h2>
                <span class="ml-auto text-[0.68rem] font-bold text-gray-400 dark:text-gray-500 tabular-nums">{{ group.subjects.length }} subject{{ group.subjects.length !== 1 ? 's' : '' }}</span>
              </div>
              <dl class="divide-y divide-gray-100 dark:divide-gray-700/60">
                <div v-for="subject in group.subjects" :key="subject.label"
                     class="flex justify-between items-baseline gap-4 px-5 py-2.5 text-[0.82rem]">
                  <dt class="text-gray-600 dark:text-gray-400">{{ subject.label }}</dt>
                  <dd :class="['font-bold tabular-nums', gradeColor(subject.value)]">{{ formatGrade(subject.value) }}</dd>
                </div>
              </dl>
            </div>
          </div>
        </div>

      </div>
    </div>
  </ApplicantLayout>
</template>


<style scoped>
/* ── Brand color classes (scoped to avoid Tailwind purge issues) ── */
.bg-brand {
  background-color: #9E122C;
}
.hover\:bg-brand-hover:hover {
  background-color: #7a0e22;
}
.active\:bg-brand-active:active {
  background-color: #6a0b1c;
}
.text-brand {
  color: #9E122C;
}
.icon-brand {
  fill: #9E122C;
}

/* Hero card gradient */
.hero-card {
  background: linear-gradient(135deg, #9E122C 0%, #c81e3d 100%);
}

/* Brand button with hover/active states built in */
.btn-brand {
  background-color: #9E122C;
}
.btn-brand:hover {
  background-color: #7a0e22;
}
.btn-brand:active {
  background-color: #6a0b1c;
}

/* Focus ring using brand color */
.focus-visible\:ring-brand:focus-visible {
  --tw-ring-color: #9E122C;
}

/* Timeline vertical connector — needs pseudo-element, can't use Tailwind alone */
.timeline-wrapper::before {
  content: '';
  position: absolute;
  left: calc(1.25rem + 6px);
  top: 1.5rem;
  bottom: 1.5rem;
  width: 2px;
  background: #e5e7eb;
  border-radius: 999px;
}
:global(.dark) .timeline-wrapper::before {
  background: #374151;
}

/* Fade transition for save success banner */
.fade-enter-active,
.fade-leave-active { transition: opacity .25s ease, transform .25s ease; }
.fade-enter-from,
.fade-leave-to { opacity: 0; transform: translateY(-4px); }

/* Tab fade-in */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(5px); }
  to   { opacity: 1; transform: none; }
}
.animate-fadeIn { animation: fadeIn .16s ease; }

/* Hide scrollbar on tab bar */
.scrollbar-none { scrollbar-width: none; }
.scrollbar-none::-webkit-scrollbar { display: none; }

/* Focus ring for inputs */
.field-input:focus {
  border-color: #9E122C;
  box-shadow: 0 0 0 3px rgba(158, 18, 44, 0.12);
}
:global(.dark) .field-input:focus {
  border-color: #f87171;
  box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.15);
}
</style>