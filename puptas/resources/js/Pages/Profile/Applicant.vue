<script setup>
import { computed, ref, reactive } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
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

const appStatusBadge = computed(() => {
  const status = props.application?.status;
  const map = {
    pending: { label: 'Pending', cls: 'status-pending' },
    submitted: { label: 'Submitted', cls: 'status-submitted' },
    approved: { label: 'Approved', cls: 'status-approved' },
    rejected: { label: 'Rejected', cls: 'status-rejected' },
    waitlisted: { label: 'Waitlisted', cls: 'status-waitlisted' },
  };
  return map[status] ?? { label: status ?? 'N/A', cls: 'status-default' };
});

const enrollmentBadge = computed(() => {
  const status = props.application?.enrollment_status;
  const map = {
    not_enrolled: { label: 'Not Enrolled', cls: 'status-default' },
    officially_enrolled: { label: 'Officially Enrolled', cls: 'status-approved' },
    waitlisted: { label: 'Waitlisted', cls: 'status-waitlisted' },
  };
  return map[status] ?? { label: status ?? 'N/A', cls: 'status-default' };
});

const docTypeLabels = {
  file10_front: 'Grade 10 Report Card (Front)', file10_back: 'Grade 10 Report Card (Back)',
  file11_front: 'Grade 11 Report Card (Front)', file11_back: 'Grade 11 Report Card (Back)',
  file12_front: 'Grade 12 Report Card (Front)', file12_back: 'Grade 12 Report Card (Back)',
  school_id: 'School ID', non_enroll_cert: 'Non-Enrollment Certificate',
  psa: 'PSA Birth Certificate', good_moral: 'Good Moral Certificate',
  under_oath: 'Under Oath Statement', photo_2x2: '2x2 Photo',
};

const docStatusBadge = (status) => {
  const map = {
    pending: { label: 'Pending', cls: 'status-pending' },
    approved: { label: 'Approved', cls: 'status-approved' },
    returned: { label: 'Returned', cls: 'status-rejected' },
    failed: { label: 'Failed', cls: 'status-rejected' },
    uploading: { label: 'Uploading', cls: 'status-submitted' },
  };
  return map[status] ?? { label: status, cls: 'status-default' };
};

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

  // G11 english fields vary by strand — STEM uses G12 versions instead of G11
  const g11EnglishFields = strand === 'STEM'
    ? [
        { key: 'g11_oral_communication', label: 'Oral Communication' },
        { key: 'g11_reading_writing', label: 'Reading & Writing' },
      ]
    : [
        { key: 'g11_oral_communication', label: 'Oral Communication' },
        { key: 'g11_21st_century_lit', label: '21st Century Literature' },
        { key: 'g11_academic_professional', label: 'Academic & Professional Literacy' },
        { key: 'g11_reading_writing', label: 'Reading & Writing' },
      ];

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
  if (isNaN(n)) return 'grade-neutral';
  if (n >= 90) return 'grade-high';
  if (n >= 80) return 'grade-mid';
  if (n >= 75) return 'grade-pass';
  return 'grade-low';
};

const activeTab = ref('profile');
const tabs = [
  { id: 'profile', label: 'Profile', icon: 'M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z' },
  { id: 'application', label: 'Application', icon: 'M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z' },
  { id: 'grades', label: 'Grades', icon: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z' },
];

const formatGrade = (val) => {
  if (val === '' || val == null) return '—';
  const num = parseFloat(val);
  return isNaN(num) ? '—' : Number(num).toFixed(2);
};

// ── Academic Background + Former School form ────────────────────────────────
const academicForm = useForm({
  school:                  props.formerSchool?.school               ?? props.applicantProfile?.school ?? '',
  former_school_address:   props.formerSchool?.former_school_address    ?? '',
  former_school_principal: props.formerSchool?.former_school_principal  ?? '',
});

const academicSaved   = ref(false);
const academicEditing = ref(false);

const formerSchoolComplete = computed(() =>
  academicForm.school.trim() !== '' &&
  academicForm.former_school_address.trim() !== ''
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
  academicForm.school                  = props.formerSchool?.school               ?? props.applicantProfile?.school ?? '';
  academicForm.former_school_address   = props.formerSchool?.former_school_address   ?? '';
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
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Hero Card -->
        <div class="hero-card">
          <div class="hero-body">
            <div class="hero-avatar-row">
              <div class="hero-avatar">{{ initials }}</div>
              <div class="hero-meta">
                <h1 class="hero-name">{{ fullName }}</h1>
                <div class="hero-tags">
                  <span class="role-badge role-applicant">Applicant</span>
                  <!-- Reference Number -->
                  <span v-if="user.test_passer?.reference_number" class="meta-chip">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                    Ref: {{ user.test_passer.reference_number }}
                  </span>
                </div>
              </div>
            </div>
            <div class="hero-contact">
              <span class="contact-item">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                {{ user.email }}
              </span>
            </div>
          </div>
        </div>

        <!-- Tab Bar -->
        <div class="tab-bar">
          <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id" :class="['tab-btn', activeTab === tab.id ? 'tab-btn--active' : '']">
            <svg class="shrink-0" viewBox="0 0 24 24" fill="currentColor"><path :d="tab.icon"/></svg>
            <span class="hidden sm:inline">{{ tab.label }}</span>
          </button>
        </div>

        <!-- Profile Tab -->
        <div v-show="activeTab === 'profile'" class="tab-panel">
          <div class="two-col-layout">
            <!-- Main Profile Details -->
            <div class="card">
              <div class="card-header">
                <div class="card-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></div>
                <div><h2 class="card-title">Personal Information</h2></div>
              </div>
              <dl class="info-list">
                <div class="info-row"><dt>First Name</dt><dd>{{ user.firstname || '—' }}</dd></div>
                <div class="info-row"><dt>Last Name</dt><dd>{{ user.lastname || '—' }}</dd></div>
                <div class="info-row"><dt>Middle Name</dt><dd>{{ user.middlename || '—' }}</dd></div>
                <div class="info-row"><dt>Extension Name</dt><dd>{{ applicantProfile?.extension_name || '—' }}</dd></div>
                <div class="info-row"><dt>Email</dt><dd>{{ user.email || '—' }}</dd></div>
              </dl>
            </div>

            <!-- Sidebar -->
            <aside class="sidebar">
              <!-- Academic Background (editable) -->
              <div class="card">
                <div class="card-header">
                  <div class="card-icon">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
                  </div>
                  <div style="flex:1"><h3 class="card-title">Academic Background</h3></div>
                  <template v-if="!academicEditing">
                    <button @click="academicEditing = true" class="edit-btn" title="Edit Former School Information">
                      <svg viewBox="0 0 24 24" fill="currentColor" style="width:13px;height:13px;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                      Edit
                    </button>
                  </template>
                </div>

                <!-- ── View mode ── -->
                <template v-if="!academicEditing">
                  <dl class="info-list">
                    <div class="info-row"><dt>Strand</dt><dd>{{ applicantProfile?.strand || '—' }}</dd></div>
                    <div class="info-row"><dt>Date Graduated</dt><dd>{{ formatDateDisplay(applicantProfile?.date_graduated) }}</dd></div>
                    <div class="info-row" style="padding:.4rem 0 .1rem;">
                      <dt style="font-size:.7rem;font-weight:700;color:#9E122C;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap;">For F137 Request Letter</dt>
                      <dd></dd>
                    </div>
                    <div class="info-row"><dt>School (SHS)</dt><dd>{{ academicForm.school || '—' }}</dd></div>
                    <div class="info-row"><dt>School Address</dt><dd>{{ academicForm.former_school_address || '—' }}</dd></div>
                    <div class="info-row"><dt>Principal / Registrar</dt><dd>{{ academicForm.former_school_principal || '—' }}</dd></div>
                  </dl>
                  <Transition name="fade">
                    <div v-if="academicSaved" style="padding:.5rem 1.25rem;border-top:1px solid #f3f4f6;display:flex;align-items:center;gap:.4rem;font-size:.78rem;color:#15803d;">
                      <svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;flex-shrink:0;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                      <span v-if="formerSchoolComplete">
                        Academic information saved. You can now download your F137 Request Letter in the dashboard.
                      </span>
                      <span v-else>Saved successfully</span>
                    </div>
                  </Transition>
                </template>

                <!-- ── Edit mode ── -->
                <form v-else @submit.prevent="submitAcademic" style="padding:.75rem 1.25rem;display:flex;flex-direction:column;gap:.85rem;">

                  <!-- For F137 divider -->
                  <div style="display:flex;align-items:center;gap:.5rem;">
                    <div style="flex:1;height:1px;background:#e5e7eb;"></div>
                    <span style="font-size:.7rem;font-weight:700;color:#9E122C;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap;">For F137 Request Letter</span>
                    <div style="flex:1;height:1px;background:#e5e7eb;"></div>
                  </div>

                  <!-- School (SHS) -->
                  <div>
                    <label class="field-label">School (SHS) <span class="req">*</span></label>
                    <input
                      v-model="academicForm.school"
                      type="text"
                      required
                      placeholder="e.g. PUP Taguig SHS"
                      class="field-input"
                    />
                    <p v-if="academicForm.errors.school" class="field-error">{{ academicForm.errors.school }}</p>
                  </div>

                  <!-- School Address -->
                  <div>
                    <label class="field-label">School Address <span class="req">*</span></label>
                    <input
                      v-model="academicForm.former_school_address"
                      type="text"
                      required
                      placeholder="e.g. General Santos Avenue, Lower Bicutan, Taguig City, Metro Manila"
                      class="field-input"
                    />
                    <p v-if="academicForm.errors.former_school_address" class="field-error">{{ academicForm.errors.former_school_address }}</p>
                  </div>

                  <!-- Principal / Registrar -->
                  <div>
                    <label class="field-label">Name of Principal / Registrar <span class="opt">(optional)</span></label>
                    <input
                      v-model="academicForm.former_school_principal"
                      type="text"
                      placeholder="e.g. Maria Santos"
                      class="field-input"
                    />
                    <p class="field-hint">If left blank, the letter will be addressed to "THE PRINCIPAL/REGISTRAR".</p>
                  </div>

                  <!-- Actions -->
                  <div style="display:flex;gap:.5rem;">
                    <button type="submit" :disabled="academicForm.processing" class="btn-save">
                      {{ academicForm.processing ? 'Saving…' : 'Save' }}
                    </button>
                    <button type="button" @click="cancelAcademic" :disabled="academicForm.processing" class="btn-cancel">
                      Cancel
                    </button>
                  </div>
                </form>
              </div>
              <!-- Program Choices -->
              <div class="card">
                <div class="card-header"><div class="card-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg></div><div><h3 class="card-title">Program Choices</h3></div></div>
                <div class="program-list">
                  <div v-if="application?.program" class="program-item program-item--first"><span class="program-rank program-rank--first">1</span><div><p class="program-name">{{ application.program.name }}</p><p class="program-code">{{ application.program.code }}</p></div></div>
                  <div v-if="application?.second_choice" class="program-item"><span class="program-rank program-rank--second">2</span><div><p class="program-name">{{ application.second_choice.name }}</p><p class="program-code">{{ application.second_choice.code }}</p></div></div>
                  <div v-if="application?.third_choice" class="program-item"><span class="program-rank program-rank--third">3</span><div><p class="program-name">{{ application.third_choice.name }}</p><p class="program-code">{{ application.third_choice.code }}</p></div></div>
                  <p v-if="!application?.program && !application?.second_choice && !application?.third_choice" class="empty-note">No program choices recorded</p>
                </div>
              </div>

            </aside>
          </div>
        </div>

        <!-- Application Tab -->
        <div v-show="activeTab === 'application'" class="tab-panel">
          <div class="two-col-layout">
            <div class="card">
              <div class="card-header"><div class="card-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg></div><div><h2 class="card-title">Application Status</h2></div></div>
              <template v-if="application">
                <dl class="info-list">
                  <div class="info-row"><dt>Status</dt><dd><span :class="['status-badge', appStatusBadge.cls]">{{ appStatusBadge.label }}</span></dd></div>
                  <div class="info-row"><dt>Enrollment</dt><dd><span :class="['status-badge', enrollmentBadge.cls]">{{ enrollmentBadge.label }}</span></dd></div>
                  <div v-if="application.enrollment_position" class="info-row"><dt>Position</dt><dd class="font-semibold">#{{ application.enrollment_position }}</dd></div>
                  <div v-if="application.submitted_at" class="info-row"><dt>Submitted</dt><dd>{{ new Date(application.submitted_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) }}</dd></div>
                </dl>
              </template>
              <div v-else class="empty-state"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg><p>No application found</p></div>
            </div>
            <div v-if="application?.processes?.length" class="card card--wide">
              <div class="card-header"><div class="card-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M13 2.05V4.05C17.39 4.59 20.5 8.58 19.96 12.97C19.5 16.61 16.64 19.5 13 19.93V21.93C18.5 21.38 22.5 16.5 21.95 11C21.5 6.25 17.73 2.5 13 2.05M11 2.06C9.05 2.25 7.19 3 5.67 4.26L7.1 5.74C8.22 4.84 9.57 4.26 11 4.06V2.06M4.26 5.67C3 7.19 2.25 9.04 2.05 11H4.05C4.24 9.58 4.8 8.23 5.69 7.1L4.26 5.67M2.06 13C2.26 14.96 3.03 16.81 4.27 18.33L5.69 16.9C4.81 15.77 4.24 14.42 4.06 13H2.06M7.1 18.37L5.67 19.74C7.18 21 9.04 21.79 11 22V20C9.58 19.82 8.23 19.25 7.1 18.37M12 7L9.5 11.5H11.5V17L14.5 12.5H12.5L12 7Z"/></svg></div><div><h2 class="card-title">Application Process Timeline</h2></div></div>
              <div class="timeline">
                <div v-for="(process, idx) in application.processes" :key="process.id" class="timeline-item">
                  <div :class="['timeline-dot', process.status === 'completed' ? 'timeline-dot--done' : process.status === 'in_progress' ? 'timeline-dot--active' : 'timeline-dot--pending']"></div>
                  <div class="timeline-content">
                    <div class="timeline-header">
                      <p class="timeline-name">{{ process.name ?? process.type ?? `Step ${idx + 1}` }}</p>
                      <span :class="['status-badge', process.status === 'completed' ? 'status-approved' : process.status === 'in_progress' ? 'status-submitted' : 'status-default']">{{ process.status?.replace(/_/g, ' ') ?? 'Pending' }}</span>
                    </div>
                    <p v-if="process.updated_at" class="timeline-date">{{ new Date(process.updated_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}</p>
                    <p v-if="process.remarks" class="timeline-remark">{{ process.remarks }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Grades Tab -->
        <div v-show="activeTab === 'grades'" class="tab-panel">
          <div v-if="!grades" class="empty-card">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
            <p class="empty-card-title">No grades recorded</p>
            <p class="empty-card-sub">Grades will appear here once you've encoded them.</p>
          </div>
          <div v-else class="grade-groups">
            <div v-for="group in gradeGroups" :key="group.title" class="card">
              <div class="card-header"><div class="card-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg></div><h2 class="card-title">{{ group.title }}</h2></div>
              <dl class="info-list">
                <div v-for="subject in group.subjects" :key="subject.label" class="info-row">
                  <dt>{{ subject.label }}</dt>
                  <dd :class="['font-semibold', gradeColor(subject.value)]">{{ formatGrade(subject.value) }}</dd>
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
:root { --brand: #9E122C; --brand-light: #c81e3d; --brand-pale: #fdf2f4; --brand-dim: rgba(158,18,44,.08); }
.hero-card { background:linear-gradient(135deg,#9E122C 0%,#c81e3d 100%); border-radius:20px; border:none; overflow:hidden; margin-bottom:1.25rem; box-shadow:0 4px 20px rgba(158,18,44,.35); position:relative; }
.hero-body { padding:1.5rem 1.5rem 1.25rem; position:relative; z-index:1; }
.hero-avatar-row { display:flex; flex-wrap:wrap; align-items:center; gap:1rem; }
.hero-avatar { width:72px; height:72px; border-radius:16px; background:rgba(255,255,255,.15); border:2px solid rgba(255,255,255,.4); box-shadow:0 4px 16px rgba(0,0,0,.2); display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:700; color:#fff; flex-shrink:0; backdrop-filter:blur(4px); }
.hero-meta { padding-bottom:2px; }
.hero-name { font-size:1.15rem; font-weight:700; color:#fff; margin:0 0 .35rem; }
.hero-tags { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
.meta-chip { display:inline-flex; align-items:center; gap:.3rem; font-size:.7rem; color:rgba(255,255,255,.9); background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.25); border-radius:20px; padding:2px 8px; }
.meta-chip svg { width:12px; height:12px; }
.hero-contact { display:flex; flex-wrap:wrap; gap:.75rem 1.25rem; margin-top:.75rem; padding-top:.75rem; border-top:1px solid rgba(255,255,255,.2); }
.contact-item { display:flex; align-items:center; gap:.4rem; font-size:.8rem; color:rgba(255,255,255,.85); }
.contact-item svg { width:14px; height:14px; color:rgba(255,255,255,.6); }
.role-badge { font-size:.68rem; font-weight:700; letter-spacing:.03em; text-transform:uppercase; padding:3px 10px; border-radius:20px; }
.role-applicant { background:rgba(255,255,255,.2); color:#fff; border:1px solid rgba(255,255,255,.3); }
.tab-bar { display:flex; overflow-x:auto; background:#fff; border-radius:16px; border:1px solid #e5e7eb; margin-bottom:1.25rem; box-shadow:0 1px 4px rgba(0,0,0,.06); scrollbar-width:none; }
.tab-bar::-webkit-scrollbar { display:none; }
.tab-btn { display:flex; align-items:center; gap:.5rem; padding:.85rem 1.25rem; font-size:.82rem; font-weight:500; color:#6b7280; border:none; background:transparent; cursor:pointer; border-bottom:2px solid transparent; white-space:nowrap; transition:color .15s,border-color .15s,background .15s; }
.tab-btn svg { width:15px; height:15px; }
.tab-btn:hover { color:#374151; background:#f9fafb; }
.tab-btn--active { color:#9E122C; border-bottom-color:#9E122C; background:#fdf2f4; }
.tab-panel { animation:fadeIn .18s ease; }
@keyframes fadeIn { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:none; } }
.two-col-layout { display:grid; grid-template-columns:1fr; gap:1.25rem; }
@media (min-width:1024px) { .two-col-layout { grid-template-columns:1fr 340px; } }
.sidebar { display:flex; flex-direction:column; gap:1.25rem; }
.grade-groups { display:flex; flex-direction:column; gap:1.25rem; }
.card { background:#fff; border-radius:16px; border:1px solid #e5e7eb; box-shadow:0 1px 4px rgba(0,0,0,.05); overflow:hidden; }
.card--wide { grid-column:1 / -1; }
.card-header { display:flex; align-items:center; gap:.75rem; padding:1rem 1.25rem; border-bottom:1px solid #f3f4f6; }
.card-icon { width:34px; height:34px; border-radius:10px; background:#fdf2f4; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.card-icon svg { width:16px; height:16px; fill:#9E122C; }
.card-title { font-size:.9rem; font-weight:700; color:#111827; margin:0; }
.info-list { padding:.75rem 1.25rem; display:flex; flex-direction:column; gap:0; }
.info-row { display:flex; justify-content:space-between; align-items:baseline; gap:1rem; padding:.6rem 0; border-bottom:1px solid #f3f4f6; font-size:.82rem; }
.info-row:last-child { border-bottom:none; }
.info-row dt { color:#6b7280; flex-shrink:0; }
.info-row dd { font-weight:600; color:#111827; text-align:right; }
.score-value { font-size:1.1rem; font-weight:800; color:#fff; }
.status-badge { font-size:.68rem; font-weight:700; padding:3px 9px; border-radius:20px; white-space:nowrap; text-transform:capitalize; }
.status-pending { background:#fef9c3; color:#92400e; }
.status-submitted { background:#dbeafe; color:#1e40af; }
.status-approved { background:#dcfce7; color:#15803d; }
.status-rejected { background:#fee2e2; color:#b91c1c; }
.status-waitlisted { background:#ffedd5; color:#c2410c; }
.status-default { background:#f3f4f6; color:#374151; }
.empty-note { font-size:.82rem; color:#9ca3af; padding:.25rem 0; }
.empty-state { text-align:center; padding:2rem; color:#9ca3af; }
.empty-state svg { width:40px; height:40px; margin:0 auto .75rem; fill:#d1d5db; }
.empty-card { text-align:center; padding:3rem 1.5rem; color:#9ca3af; }
.empty-card svg { width:48px; height:48px; margin:0 auto .75rem; fill:#d1d5db; }
.empty-card-title { font-size:.9rem; font-weight:600; color:#6b7280; margin-bottom:.25rem; }
.empty-card-sub { font-size:.78rem; }
.program-list { padding:.75rem 1.25rem; display:flex; flex-direction:column; gap:.6rem; }
.program-item { display:flex; align-items:center; gap:.75rem; padding:.7rem .9rem; border-radius:12px; background:#f9fafb; border:1px solid #f3f4f6; }
.program-item--first { background:#fdf2f4; border-color:rgba(158,18,44,.15); }
.program-rank { width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.72rem; font-weight:800; color:#fff; flex-shrink:0; }
.program-rank--first { background:#9E122C; }
.program-rank--second { background:#9ca3af; }
.program-rank--third { background:#d1d5db; color:#374151; }
.program-name { font-size:.83rem; font-weight:700; color:#111827; margin:0 0 2px; }
.program-code { font-size:.72rem; color:#6b7280; margin:0; }
.timeline { padding:.75rem 1.25rem 1rem; position:relative; }
.timeline::before { content:''; position:absolute; left:calc(1.25rem + 7px); top:1.25rem; bottom:1.25rem; width:2px; background:#f3f4f6; }
.timeline-item { display:flex; gap:1rem; position:relative; margin-bottom:1rem; }
.timeline-item:last-child { margin-bottom:0; }
.timeline-dot { width:16px; height:16px; border-radius:50%; flex-shrink:0; margin-top:3px; border:2px solid #fff; box-shadow:0 0 0 2px currentColor; z-index:1; position:relative; }
.timeline-dot--done { background:#22c55e; color:#22c55e; }
.timeline-dot--active { background:#3b82f6; color:#3b82f6; }
.timeline-dot--pending { background:#d1d5db; color:#d1d5db; }
.timeline-content { flex:1; background:#f9fafb; border:1px solid #f3f4f6; border-radius:12px; padding:.65rem .9rem; }
.timeline-header { display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap; }
.timeline-name { font-size:.83rem; font-weight:700; color:#111827; margin:0; }
.timeline-date { font-size:.72rem; color:#9ca3af; margin:.25rem 0 0; }
.timeline-remark { font-size:.76rem; color:#4b5563; margin:.3rem 0 0; font-style:italic; }
.grade-high { color:#22c55e; }
.grade-mid { color:#3b82f6; }
.grade-pass { color:#eab308; }
.grade-low { color:#ef4444; }
.grade-neutral { color:#6b7280; }
.fade-enter-active, .fade-leave-active { transition:opacity .3s; }
.fade-enter-from, .fade-leave-to { opacity:0; }
.edit-btn { display:inline-flex;align-items:center;gap:.3rem;font-size:.75rem;font-weight:600;padding:.3rem .7rem;border-radius:8px;border:1px solid #e5e7eb;background:#f9fafb;color:#374151;cursor:pointer;transition:background .15s; }
.edit-btn:hover { background:#f3f4f6; }
.edit-btn svg { fill:#6b7280; }
.field-label { display:block;font-size:.78rem;font-weight:600;color:#374151;margin-bottom:.3rem; }
.req { color:#9E122C; }
.opt { font-size:.7rem;font-weight:400;color:#9ca3af; }
.field-input { width:100%;padding:.5rem .75rem;border:1px solid #e5e7eb;border-radius:8px;font-size:.82rem;color:#111827;outline:none;box-sizing:border-box;transition:border-color .15s; }
.field-input:focus { border-color:#9E122C; }
.field-error { font-size:.72rem;color:#b91c1c;margin-top:.25rem; }
.field-hint { font-size:.7rem;color:#9ca3af;margin-top:.25rem; }
.btn-save { font-size:.78rem;font-weight:700;padding:.45rem 1rem;border-radius:8px;border:none;background:#9E122C;color:#fff;cursor:pointer;min-height:36px;transition:background .15s; }
.btn-save:hover:not(:disabled) { background:#7a0e22; }
.btn-save:disabled { opacity:.6;cursor:not-allowed; }
.btn-cancel { font-size:.78rem;font-weight:600;padding:.45rem .9rem;border-radius:8px;border:1px solid #e5e7eb;background:#f9fafb;color:#374151;cursor:pointer;transition:background .15s; }
.btn-cancel:hover:not(:disabled) { background:#f3f4f6; }
.btn-cancel:disabled { opacity:.6;cursor:not-allowed; }
.doc-grid { display:grid; grid-template-columns:1fr; gap:1rem; }
@media (min-width:640px) { .doc-grid { grid-template-columns:1fr 1fr; } }
@media (min-width:1024px) { .doc-grid { grid-template-columns:1fr 1fr 1fr; } }
.doc-card { background:#fff; border-radius:14px; border:1px solid #e5e7eb; padding:1rem; }
.doc-card-top { display:flex; align-items:center; gap:.75rem; }
.doc-icon { width:36px; height:36px; border-radius:10px; background:#fdf2f4; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.doc-icon svg { width:16px; height:16px; fill:#9E122C; }
.doc-meta { flex:1; min-width:0; }
.doc-type { font-size:.8rem; font-weight:700; color:#111827; margin:0 0 2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.doc-filename { font-size:.7rem; color:#9ca3af; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.doc-remark { display:flex; align-items:flex-start; gap:.35rem; margin-top:.5rem; padding:.45rem .55rem; background:#fff5f5; border:1px solid #fecaca; border-radius:8px; font-size:.72rem; color:#b91c1c; }
.doc-remark svg { width:12px; height:12px; fill:#ef4444; flex-shrink:0; margin-top:1px; }
.doc-date { font-size:.7rem; color:#d1d5db; margin-top:.5rem; }
.summary-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:0; padding:1rem 1.25rem; }
.summary-stat { text-align:center; display:flex; flex-direction:column; gap:.25rem; }
.summary-num { font-size:1.3rem; font-weight:800; }
.summary-label { font-size:.7rem; color:#9ca3af; }
.summary-stat--neutral .summary-num { color:#6b7280; }
.summary-stat--green .summary-num { color:#22c55e; }
.summary-stat--yellow .summary-num { color:#eab308; }
.summary-stat--red .summary-num { color:#ef4444; }
</style>