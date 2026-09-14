<script setup>
import { defineProps, ref, computed, onMounted } from "vue";
import { router } from "@inertiajs/vue3";
import { Head } from "@inertiajs/vue3";
import Compressor from "compressorjs";
const axios = window.axios;
import ApplicantLayout from "@/Layouts/ApplicantLayout.vue";
import ApplicationReviewModal from "@/Pages/Modal/ApplicationReviewModal.vue";
import BlurText from "@/Components/BlurText.vue";

const props = defineProps({ user: Object, gradeUrl: String, canDownloadSlip: Boolean, canDownloadF137: Boolean });

const showModal = ref(false);
const showSuccessNotification = ref(false);
const loading = ref(false);
const error = ref("");
const fileStatuses = ref({});
const applicationStatus = ref("");
const enrollmentStatus = ref("");
const applicationProcesses = ref([]);
const requiresPromissoryNote = ref(false);
const requiresGuidanceOffice = ref(false);
const requiresAdmissionOffice = ref(false);
const hasGrades = ref(false);

// Reactively derived from live applicationStatus — updates immediately after the user
// submits without requiring a page reload.
// Mirrors the server-side canDownloadSlip logic exactly:
//   - application submitted (status !== 'draft')
//   - grades exist (gradeUrl is set — the controller only sets it when grades are present)
// Using both conditions prevents showing a button that would 403 on the server.
const canDownloadSlipReactive = computed(() => {
  // If the server already confirmed eligibility at page load, keep it true
  if (props.canDownloadSlip) return true;
  // After in-session submission: require both a submitted status AND grades to exist
  // (gradeUrl is only non-null when the applicant has grades for their strand)
  return Boolean(
    applicationStatus.value &&
    applicationStatus.value !== 'draft' &&
    props.gradeUrl !== null &&
    props.gradeUrl !== undefined
  );
});

const enrollmentInfo = computed(() => {
  const s = enrollmentStatus.value;
  const appS = applicationStatus.value;
  const procs = applicationProcesses.value;

  // ── Determine the current pipeline stage label ──────────────────────────
  const stageLabels = {
    document_evaluator: 'Document Evaluator',
    grade_evaluator:    'Grade Evaluator',
    evaluator:          'Document Evaluator', // legacy key
    interviewer:        'Interviewer',
    medical:            'Medical',
    records:            'Registrar',
  };

  let currentStageLabel = null;
  let currentStageKey = null;
  if (procs.length) {
    const byStage = {};
    for (const p of procs) {
      const key = p.stage === 'evaluator' ? 'document_evaluator' : p.stage;
      if (!byStage[key] || new Date(p.created_at) > new Date(byStage[key].created_at)) {
        byStage[key] = p;
      }
    }
    
    const stageKeys = ['document_evaluator', 'grade_evaluator', 'interviewer', 'medical', 'records'];
    
    for (let i = stageKeys.length - 1; i >= 0; i--) {
      const key = stageKeys[i];
      const proc = byStage[key];
      if (proc && (['in_progress', 'returned', 'rejected'].includes(proc.status) || proc.action === 'rejected')) {
        currentStageKey = key;
        break;
      }
    }
    
    if (!currentStageKey) {
      for (let i = stageKeys.length - 1; i >= 0; i--) {
        const key = stageKeys[i];
        const proc = byStage[key];
        if (proc && proc.status === 'completed') {
          currentStageKey = key;
          break;
        }
      }
    }
    
    if (currentStageKey) {
      currentStageLabel = stageLabels[currentStageKey] ?? null;
    }

    if (!byStage['records'] && byStage['medical']?.status === 'completed') {
      currentStageLabel = 'Registrar';
      currentStageKey = 'records';
    }
  }

  // ── Color and Style Theme Presets ────────────────────────────────────────
  const themeEmerald = {
    type: 'success',
    color: 'text-emerald-950 dark:text-emerald-100',
    accentColor: 'text-emerald-700 dark:text-emerald-400',
    cardBg: 'bg-gradient-to-br from-emerald-500/[0.08] via-teal-500/[0.04] to-emerald-500/[0.02] dark:from-emerald-950/40 dark:via-gray-900/80 dark:to-teal-950/20',
    border: 'border-emerald-200/80 dark:border-emerald-800/60',
    glow: 'bg-emerald-400/20 dark:bg-emerald-500/10',
    ring: 'ring-1 ring-emerald-300/50 dark:ring-emerald-500/30',
    badge: 'bg-emerald-100/90 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-700/60',
    badgeDot: 'bg-emerald-500',
    iconBg: 'bg-gradient-to-br from-emerald-500 to-teal-600 shadow-lg shadow-emerald-500/25',
    chipBg: 'bg-white/70 dark:bg-gray-800/70 border-emerald-100 dark:border-emerald-800/40 text-emerald-900 dark:text-emerald-200',
    icon: 'academic-cap',
  };

  const themeBlue = {
    type: 'info',
    color: 'text-blue-950 dark:text-blue-100',
    accentColor: 'text-blue-700 dark:text-blue-400',
    cardBg: 'bg-gradient-to-br from-blue-500/[0.08] via-indigo-500/[0.04] to-blue-500/[0.02] dark:from-blue-950/40 dark:via-gray-900/80 dark:to-indigo-950/20',
    border: 'border-blue-200/80 dark:border-blue-800/60',
    glow: 'bg-blue-400/20 dark:bg-blue-500/10',
    ring: 'ring-1 ring-blue-300/50 dark:ring-blue-500/30',
    badge: 'bg-blue-100/90 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300 border border-blue-200 dark:border-blue-700/60',
    badgeDot: 'bg-blue-500 animate-ping',
    iconBg: 'bg-gradient-to-br from-blue-600 to-indigo-600 shadow-lg shadow-blue-500/25',
    chipBg: 'bg-white/70 dark:bg-gray-800/70 border-blue-100 dark:border-blue-800/40 text-blue-900 dark:text-blue-200',
    icon: 'clock',
  };

  const themeAmber = {
    type: 'warning',
    color: 'text-amber-950 dark:text-amber-100',
    accentColor: 'text-amber-700 dark:text-amber-400',
    cardBg: 'bg-gradient-to-br from-amber-500/[0.09] via-orange-500/[0.05] to-amber-500/[0.02] dark:from-amber-950/40 dark:via-gray-900/80 dark:to-orange-950/20',
    border: 'border-amber-300/80 dark:border-amber-700/60',
    glow: 'bg-amber-400/20 dark:bg-amber-500/10',
    ring: 'ring-1 ring-amber-300/60 dark:ring-amber-500/30',
    badge: 'bg-amber-100/90 text-amber-900 dark:bg-amber-900/60 dark:text-amber-300 border border-amber-300 dark:border-amber-700/60',
    badgeDot: 'bg-amber-500 animate-ping',
    iconBg: 'bg-gradient-to-br from-amber-500 to-orange-600 shadow-lg shadow-amber-500/25',
    chipBg: 'bg-white/70 dark:bg-gray-800/70 border-amber-200 dark:border-amber-800/40 text-amber-900 dark:text-amber-200',
    icon: 'warning',
  };

  const themePurple = {
    type: 'waitlisted',
    color: 'text-purple-950 dark:text-purple-100',
    accentColor: 'text-purple-700 dark:text-purple-400',
    cardBg: 'bg-gradient-to-br from-purple-500/[0.08] via-violet-500/[0.04] to-purple-500/[0.02] dark:from-purple-950/40 dark:via-gray-900/80 dark:to-violet-950/20',
    border: 'border-purple-200/80 dark:border-purple-800/60',
    glow: 'bg-purple-400/20 dark:bg-purple-500/10',
    ring: 'ring-1 ring-purple-300/50 dark:ring-purple-500/30',
    badge: 'bg-purple-100/90 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300 border border-purple-200 dark:border-purple-700/60',
    badgeDot: 'bg-purple-500',
    iconBg: 'bg-gradient-to-br from-purple-600 to-indigo-600 shadow-lg shadow-purple-500/25',
    chipBg: 'bg-white/70 dark:bg-gray-800/70 border-purple-100 dark:border-purple-800/40 text-purple-900 dark:text-purple-200',
    icon: 'queue',
  };

  const themeRose = {
    type: 'error',
    color: 'text-rose-950 dark:text-rose-100',
    accentColor: 'text-rose-700 dark:text-rose-400',
    cardBg: 'bg-gradient-to-br from-rose-500/[0.09] via-red-500/[0.05] to-rose-500/[0.02] dark:from-rose-950/40 dark:via-gray-900/80 dark:to-red-950/20',
    border: 'border-rose-300/80 dark:border-rose-700/60',
    glow: 'bg-rose-400/20 dark:bg-rose-500/10',
    ring: 'ring-1 ring-rose-300/60 dark:ring-rose-500/30',
    badge: 'bg-rose-100/90 text-rose-900 dark:bg-rose-900/60 dark:text-rose-300 border border-rose-300 dark:border-rose-700/60',
    badgeDot: 'bg-rose-500',
    iconBg: 'bg-gradient-to-br from-rose-600 to-red-600 shadow-lg shadow-rose-500/25',
    chipBg: 'bg-white/70 dark:bg-gray-800/70 border-rose-200 dark:border-rose-800/40 text-rose-900 dark:text-rose-200',
    icon: 'x-circle',
  };

  const themeSlate = {
    type: 'draft',
    color: 'text-gray-900 dark:text-gray-100',
    accentColor: 'text-gray-600 dark:text-gray-400',
    cardBg: 'bg-gradient-to-br from-slate-100 via-gray-50/80 to-slate-100/50 dark:from-gray-800/80 dark:via-gray-900/90 dark:to-gray-800/40',
    border: 'border-gray-200 dark:border-gray-700',
    glow: 'bg-gray-400/10 dark:bg-gray-600/10',
    ring: 'ring-1 ring-gray-300/60 dark:ring-gray-600/30',
    badge: 'bg-gray-100 text-gray-700 dark:bg-gray-700/80 dark:text-gray-300 border border-gray-200 dark:border-gray-600',
    badgeDot: 'bg-gray-400',
    iconBg: 'bg-gradient-to-br from-slate-600 to-gray-700 shadow-lg shadow-gray-500/20',
    chipBg: 'bg-white/80 dark:bg-gray-800/80 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300',
    icon: 'document-text',
  };

  // ── Terminal enrollment states ───────────────────────────────────────────
  if (s === 'officially_enrolled') {
    return {
      ...themeEmerald,
      badgeLabel: 'Officially Enrolled',
      statusTag: 'Enrollment Confirmed',
      label: 'Officially Enrolled Iskolar',
      description: 'Congratulations! You are officially enrolled in the Polytechnic University of the Philippines. Welcome to PUP, Iskolar ng Bayan!',
      nextStep: 'Prepare for Class Opening',
      stageLabel: 'Completed',
      actionKey: 'download_slip',
    };
  }

  if (s === 'temporary') {
    return {
      ...themeBlue,
      badgeLabel: 'Temporarily Enrolled',
      statusTag: 'Conditional Enrollment',
      label: 'Temporarily Enrolled',
      description: 'Your enrollment has been conditionally accepted. Please ensure any pending requirements (such as COR or promissory documents) are completed.',
      nextStep: 'Complete Pending Requirements',
      stageLabel: currentStageLabel || 'Registrar',
      actionKey: 'upload_cor',
    };
  }

  if (s === 'waitlisted') {
    return {
      ...themePurple,
      badgeLabel: 'Waitlisted',
      statusTag: 'Queue Position Active',
      label: 'Application on Waitlist',
      description: "You have been placed on the official waitlist. As slots open up during enrollment finalization, notifications will be issued.",
      nextStep: 'Monitor Application Updates',
      stageLabel: currentStageLabel || 'Evaluation Complete',
      actionKey: 'view_programs',
    };
  }

  if (s === 'not_enrolled') {
    return {
      ...themeRose,
      badgeLabel: 'Not Enrolled',
      statusTag: 'Admission Closed',
      label: 'Not Enrolled for this Cycle',
      description: 'Your application has not led to enrollment for the current admission period.',
      nextStep: 'Admission Cycle Concluded',
      stageLabel: 'Archived',
      actionKey: null,
    };
  }

  // ── Derive from application status ───────────────────────────────────────
  if (appS === 'approved') {
    return {
      ...themeEmerald,
      badgeLabel: 'Approved',
      statusTag: 'Evaluation Complete',
      label: 'Application Approved',
      description: 'Your application has been approved by the admissions committee! Monitor instructions for your final enrollment clearance.',
      nextStep: 'Await Enrollment Schedule',
      stageLabel: currentStageLabel || 'Admissions',
      actionKey: 'review_app',
    };
  }

  if (appS === 'cleared_for_enrollment') {
    return {
      ...themeEmerald,
      badgeLabel: 'Cleared for Enrollment',
      statusTag: 'Ready for Final Registration',
      label: 'Cleared for Enrollment',
      description: 'You have cleared all evaluation and medical verification steps! Upload your Certificate of Registration (COR) below to finalize enrollment.',
      nextStep: 'Upload Certificate of Registration (COR)',
      stageLabel: 'COR Submission',
      actionKey: 'upload_cor',
    };
  }

  if (appS === 'accepted') {
    return {
      ...themeEmerald,
      badgeLabel: 'Accepted',
      statusTag: 'Application Accepted',
      label: 'Application Accepted',
      description: 'Your application has been accepted into your designated program. Proceed with the remaining admission stages.',
      nextStep: 'Proceed with Pipeline Stages',
      stageLabel: currentStageLabel || 'Accepted',
      actionKey: 'review_app',
    };
  }

  if (appS === 'returned') {
    return {
      ...themeAmber,
      badgeLabel: 'Action Required',
      statusTag: 'Documents Returned',
      label: 'Documents Returned for Correction',
      description: 'The evaluator has requested updates to some of your submitted files. Please review the reviewer comments and re-upload the required documents below.',
      nextStep: 'Re-upload Returned Documents',
      stageLabel: currentStageLabel || 'Document Evaluator',
      actionKey: 'documents',
    };
  }

  if (appS === 'rejected') {
    return {
      ...themeRose,
      badgeLabel: 'Rejected',
      statusTag: 'Application Unsuccessful',
      label: 'Application Not Accepted',
      description: 'We regret to inform you that your application was not accepted for this admission cycle. Review evaluator feedback below.',
      nextStep: 'Review Evaluator Remarks',
      stageLabel: currentStageLabel || 'Evaluator',
      actionKey: null,
    };
  }

  if (appS === 'submitted') {
    return {
      ...themeBlue,
      badgeLabel: 'Under Review',
      statusTag: currentStageLabel ? `${currentStageLabel} Stage` : 'Under Evaluation',
      label: currentStageLabel ? `${currentStageLabel} Stage in Progress` : 'Application Under Active Review',
      description: 'Your application has been submitted and is currently being evaluated by University admissions staff.',
      nextStep: 'Staff Reviewing Application',
      stageLabel: currentStageLabel || 'Under Review',
      actionKey: 'review_app',
    };
  }

  if (!appS || appS === 'draft') {
    return {
      ...themeSlate,
      badgeLabel: 'Application Draft',
      statusTag: 'Not Yet Submitted',
      label: 'Application in Progress (Draft)',
      description: 'Upload all required report card documents and submit your academic grades to officially file your application.',
      nextStep: 'Complete Documents & Submit Application',
      stageLabel: 'Pre-Submission',
      actionKey: 'documents',
    };
  }

  return {
    ...themeBlue,
    badgeLabel: capitalize((s || appS || 'Processing').replace(/_/g, ' ')),
    statusTag: currentStageLabel || 'Processing',
    label: currentStageLabel ? `${currentStageLabel} Stage` : capitalize((s || appS || 'In Progress').replace(/_/g, ' ')),
    description: 'Your application is currently being processed by the admissions team.',
    nextStep: 'Processing Application',
    stageLabel: currentStageLabel || 'Active',
    actionKey: 'review_app',
  };
});

const canEditGrades = computed(() => {
    
    if (applicationStatus.value === 'returned') {
        const returnedProcesses = applicationProcesses.value.filter(p => p.status === 'returned');
        if (!returnedProcesses.length) return false;
        const latestReturned = returnedProcesses[returnedProcesses.length - 1];
        return latestReturned.stage !== 'document_evaluator';
    }
    
    if (applicationStatus.value === 'rejected') {
        const rejectedProcesses = applicationProcesses.value.filter(p => p.status === 'completed' && p.action === 'rejected');
        if (!rejectedProcesses.length) return false;
        const latestRejected = rejectedProcesses[rejectedProcesses.length - 1];
        return latestRejected.stage === 'grade_evaluator';
    }
    
    return false;
});

const showImageModal = ref(false);
const previewSrc = ref("");
const showMedicalRedirect = ref(false);
const showF137Button = ref(false);
const activeUploadKey = ref("");
const activeUploadFile = ref(null);
const activeUploadDropActive = ref(false);
const activeUploadUploading = ref(false);
const activeUploadProgress = ref(0);
const activeUploadLoaded = ref(0);
const activeUploadTotal = ref(0);
const activeUploadError = ref("");
const activeUploadSuccess = ref(false);
// Grade Verification Slip download state
const downloadingSlip = ref(false);
const slipDownloadError = ref('');
const showSchedule = ref(false);
const showCorUpload = ref(false);

// COR keys are post-medical requirements — excluded from pre-submission progress checks
const COR_KEYS = ['fileCorFront', 'fileCorBack'];

const needsCorUpload = computed(() => {
  if (applicationStatus.value !== 'cleared_for_enrollment') return false;
  if (enrollmentStatus.value === 'officially_enrolled') return false;
  
  const front = fileStatuses.value['fileCorFront'];
  const back = fileStatuses.value['fileCorBack'];
  
  const frontNeedsUpload = !front?.url || front?.status === 'returned' || front?.status === 'rejected';
  const backNeedsUpload = !back?.url || back?.status === 'returned' || back?.status === 'rejected';
  
  return frontNeedsUpload || backNeedsUpload;
});

// checks if all pre-submission documents have been uploaded
const allDocumentsUploaded = computed(() => {
  const entries = Object.entries(fileStatuses.value).filter(([key]) => !COR_KEYS.includes(key));
  const values = entries.map(([, v]) => v);
  return values.length > 0 && values.every(f => f?.url != null && f?.status !== 'uploading' && f?.status !== 'failed');
});

// "Review and Submit Application" only appears once all documents are uploaded AND grades have been entered
const canReviewAndSubmit = computed(() => allDocumentsUploaded.value && hasGrades.value);

// Sidebar is visible when the actions card or downloads card has content (or while loading)
const hasSidebarContent = computed(() =>
  (loading.value && !applicationStatus.value && !stepKeys.value.length) ||
  canReviewAndSubmit.value ||
  allDocumentsUploaded.value ||
  showF137Button.value
);

// (grades extraction removed) documents are simply uploaded

// File statuses come directly from the backend
const stepKeys = computed(() => Object.keys(fileStatuses.value));

// Pre-submission document keys (excludes COR)
const preSubmissionKeys = computed(() => stepKeys.value.filter(k => !COR_KEYS.includes(k)));

const uploadedCount = computed(() => {
  return preSubmissionKeys.value.filter(k => {
    const f = fileStatuses.value[k];
    return f?.url && f?.status !== 'uploading' && f?.status !== 'failed';
  }).length;
});

const uploadProgressPercentage = computed(() => {
  if (!preSubmissionKeys.value.length) return 0;
  return (uploadedCount.value / preSubmissionKeys.value.length) * 100;
});

const formatKey = (key) => {
  const labels = {
    file10Front: "Grade 10 Report Card (Front)",
    file10: "Grade 10 Report Card (Back)",
    file11Front: "Grade 11 Report Card (Front)",
    file11: "Grade 11 Report Card (Back)",
    file12Front: "Grade 12 Report Card (Front)",
    file12: "Grade 12 Report Card (Back)",
    fileCorFront: "Certificate of Registration (Front)",
    fileCorBack: "Certificate of Registration (Back)",
  };

  return labels[key] || key.replace(/([A-Z])/g, " $1").replace(/^./, (s) => s.toUpperCase());
};
const formatStage = (stage) => {
    const map = {
        'evaluator':          'DE, GE',
        'document_evaluator': 'Document Evaluator',
        'grade_evaluator':    'Grade Evaluator',
        'interviewer':        'Interviewer',
        'medical':            'Medical',
        'record_staff':       'Registrar',
    };
    return map[stage] || (stage ? stage.charAt(0).toUpperCase() + stage.slice(1).replace(/_/g, " ") : "");
};

// All pipeline stages in order — always shown regardless of progress
const PIPELINE_STAGES = [
  { key: 'document_evaluator', label: 'Document Evaluator' },
  { key: 'grade_evaluator',    label: 'Grade Evaluator' },
  { key: 'interviewer',        label: 'Interviewer' },
  { key: 'medical',            label: 'Medical' },
  { key: 'records',            label: 'Registrar' },
];

// Merge fixed pipeline with live process data.
// Each step gets: key, label, status (completed/in_progress/returned/rejected/pending),
// action, created_at, reviewer_notes, isCurrent (highlighted), isPast, isFuture.
const timelineSteps = computed(() => {
  const procs = applicationProcesses.value;

  // Build a lookup: stage key → latest process entry
  // Also map legacy 'evaluator' key → 'document_evaluator' so old records still display
  const byStage = {};
  for (const p of procs) {
    const key = p.stage === 'evaluator' ? 'document_evaluator' : p.stage;
    if (!byStage[key] || new Date(p.created_at) > new Date(byStage[key].created_at)) {
      byStage[key] = p;
    }
  }

  let currentKey = null;
  const stageKeys = PIPELINE_STAGES.map(s => s.key);
  
  for (let i = stageKeys.length - 1; i >= 0; i--) {
    const key = stageKeys[i];
    const proc = byStage[key];
    if (proc && (['in_progress', 'returned', 'rejected'].includes(proc.status) || proc.action === 'rejected')) {
      currentKey = key;
      break;
    }
  }
  
  if (!currentKey) {
    for (let i = stageKeys.length - 1; i >= 0; i--) {
      const key = stageKeys[i];
      const proc = byStage[key];
      if (proc && proc.status === 'completed') {
        currentKey = key;
        break;
      }
    }
  }

  // If medical is completed and no records process exists yet, synthesize an
  // in_progress records entry so the Registrar step shows "In Progress" instead
  // of "Upcoming" — the applicant is now actively waiting for the registrar.
  const medicalDone = byStage['medical']?.status === 'completed';
  const hasRecordsProcess = !!byStage['records'];
  if (medicalDone && !hasRecordsProcess) {
    byStage['records'] = {
      stage: 'records',
      status: 'in_progress',
      action: null,
      created_at: null,
      reviewer_notes: null,
    };
  }

  // Determine how far the pipeline has progressed
  const reachedKeys = new Set(procs.map(p => p.stage === 'evaluator' ? 'document_evaluator' : p.stage));
  // Also count the synthetic records entry as reached when applicable
  if (byStage['records']) reachedKeys.add('records');

  return PIPELINE_STAGES.map((stage) => {
    const proc = byStage[stage.key] || null;
    const reached = reachedKeys.has(stage.key);
    const status = proc?.status ?? (reached ? 'pending' : 'pending');
    const isCurrent = stage.key === currentKey;
    const reachedIdx = stageKeys.indexOf(stage.key);
    const currentIdx = stageKeys.indexOf(currentKey ?? '');
    const isPast = reached && !isCurrent && (proc?.status === 'completed');
    const isFuture = !reached;

    return {
      key: stage.key,
      label: stage.label,
      status: proc?.status ?? 'pending',
      action: proc?.action ?? null,
      created_at: proc?.created_at ?? null,
      reviewer_notes: proc?.reviewer_notes ?? null,
      isCurrent,
      isPast,
      isFuture,
    };
  });
});

const returnedDocumentsCount = computed(() => {
  let count = 0;
  for (const [, file] of Object.entries(fileStatuses.value || {})) {
    if (file && (file.status === 'returned' || file.status === 'rejected')) {
      count++;
    }
  }
  return count;
});

const currentStageNote = computed(() => {
  const currentStep = timelineSteps.value.find(s => s.isCurrent && s.reviewer_notes);
  if (currentStep?.reviewer_notes) return currentStep.reviewer_notes;
  const returnedStep = timelineSteps.value.find(s => s.status === 'returned' && s.reviewer_notes);
  return returnedStep?.reviewer_notes || null;
});

const stageProgressInfo = computed(() => {
  const completed = timelineSteps.value.filter(s => s.status === 'completed').length;
  const total = timelineSteps.value.length;
  const currentIdx = timelineSteps.value.findIndex(s => s.isCurrent);
  const currentStepNum = currentIdx >= 0 ? currentIdx + 1 : (completed === total ? total : 1);
  return {
    completed,
    total,
    currentStepNum,
    percent: Math.round((completed / total) * 100),
  };
});

const scrollToDocuments = () => {
  const el = document.getElementById('documents-section');
  if (el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
};

const capitalize = (s) => s.charAt(0).toUpperCase() + s.slice(1);
const formatTimestamp = (ts) => ts ? new Date(ts).toLocaleString(undefined, { year:"numeric", month:"short", day:"numeric", hour:"2-digit", minute:"2-digit" }) : "";
const getFileUrl = (file) => file?.url || "";
const hasImagePreview = (file) => Boolean(getFileUrl(file)) && file?.isImage !== false;
const formatFileSize = (bytes) => {
  if (!Number.isFinite(bytes) || bytes <= 0) return "";
  const units = ["B", "KB", "MB", "GB"];
  const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
  const value = bytes / Math.pow(1024, index);
  return `${value.toFixed(value >= 10 || index === 0 ? 0 : 1)} ${units[index]}`;
};

const getStatusShort = (status) => {
  switch ((status || "").toLowerCase()) {
    case "approved": case "completed": return "OK";
    case "pending": case "submitted": return "PD";
    case "in_progress": case "processing": case "uploading": return "IP";
    case "rejected": case "returned": return "RJ";
    case "failed": return "FL";
    case "draft": return "DR";
    default: return "PD";
  }
};

const getStatusIconBg = (status) => {
  switch ((status || "").toLowerCase()) {
    case "approved": case "completed": return "bg-green-600";
    case "pending": case "submitted": return "bg-blue-600";
    case "in_progress": case "processing": case "uploading": return "bg-yellow-600";
    case "rejected": case "returned": case "failed": return "bg-red-600";
    case "draft": return "bg-gray-600";
    default: return "bg-gray-600";
  }
};

const getEnrollmentIconBg = (status) => {
  switch ((status || "").toLowerCase()) {
    case "pending": return "bg-yellow-600";
    case "temporary": return "bg-blue-600";
    case "officially_enrolled": return "bg-green-600";
    default: return "bg-gray-600";
  }
};

const getBadgeClass = (status) => {
  switch ((status || "").toLowerCase()) {
    case "approved": case "completed": return "bg-green-500";
    case "pending": case "submitted": return "bg-blue-500";
    case "in_progress": case "processing": case "uploading": return "bg-yellow-500";
    case "rejected": case "returned": case "failed": return "bg-red-500";
    case "draft": return "bg-gray-400";
    default: return "bg-gray-500";
  }
};

const getEnrollmentBadgeClass = (status) => {
  switch ((status || "").toLowerCase()) {
    case "pending": return "bg-yellow-500";
    case "temporary": return "bg-yellow-500";
    case "officially_enrolled": return "bg-green-500";
    default: return "bg-gray-500";
  }
};

const fetchData = async () => {
  loading.value = true;
  error.value = "";
  try {
    const { data } = await axios.get("/user/application");
    fileStatuses.value = data.uploadedFiles || {};
    applicationStatus.value = data.status || "";
    enrollmentStatus.value = data.enrollment_status || "";
    applicationProcesses.value = data.processes || [];
    showMedicalRedirect.value = data.show_medical_redirect || false;
    showF137Button.value = data.show_f137_button || false;
    showCorUpload.value = data.show_cor_upload || false;
    requiresPromissoryNote.value = data.requires_promissory_note || false;
    requiresGuidanceOffice.value = data.requires_guidance_office || false;
    requiresAdmissionOffice.value = data.requires_admission_office || false;
    hasGrades.value = data.has_grades || false;
  } catch {
    error.value = "Could not load application data.";
  } finally {
    loading.value = false;
  }
};

const triggerFileInput = (key) => {
  const input = document.getElementById('file-input-' + key);
  if (input) {
    input.value = '';
    input.click();
  }
};

// Wrapper to open inline uploader and trigger file picker.
// Prevents opening another uploader while an upload is in progress.
const handleOpenUpload = (key) => {
  if (activeUploadUploading.value) return;
  // Backend-authoritative check: don't allow opening uploader if backend says uploading
  if (fileStatuses.value[key]?.status === 'uploading') return;
  openInlineUpload(key);
  triggerFileInput(key);
};

const reuploadFile = async (e, key) => {
  const file = e.target.files[0];
  if (!file) return;

  // Validate and set in inline uploader
  selectInlineFile(file);
  // Auto-upload if validation passed (no error)
  if (!activeUploadError.value) {
    uploadInlineFile();
  }
};

const openInlineUpload = (key) => {
  if (activeUploadUploading.value) return;
  activeUploadKey.value = key;
  activeUploadFile.value = null;
  activeUploadDropActive.value = false;
  activeUploadProgress.value = 0;
  activeUploadLoaded.value = 0;
  activeUploadTotal.value = 0;
  activeUploadError.value = "";
  activeUploadSuccess.value = false;
};

const closeInlineUpload = () => {
  if (activeUploadUploading.value) return;
  activeUploadKey.value = "";
  activeUploadFile.value = null;
  activeUploadDropActive.value = false;
  activeUploadProgress.value = 0;
  activeUploadLoaded.value = 0;
  activeUploadTotal.value = 0;
  activeUploadError.value = "";
  activeUploadSuccess.value = false;
};

const onInlineFileChange = (e) => {
  const file = e.target.files?.[0];
  if (!file) return;
  selectInlineFile(file);
  // Auto-upload if validation passed (no error)
  if (!activeUploadError.value) {
    uploadInlineFile();
  }
};

const selectInlineFile = (file) => {
  activeUploadSuccess.value = false;
  
  // Use relaxed limits for local testing environment
  let maxSize = 5 * 1024 * 1024; // 5MB default
  let validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
  
  // In local environment, allow more file types and larger sizes
  if (import.meta.env.VITE_APP_ENV === 'local') {
    maxSize = 50 * 1024 * 1024; // 50MB for testing
    validTypes = [
      'image/jpeg', 'image/png', 'image/webp', 'image/gif',
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'text/plain'
    ];
  }
  
  if (file.size > maxSize) {
    activeUploadError.value = `File size must not exceed ${maxSize / (1024 * 1024)}MB.`;
    return;
  }
  if (!validTypes.includes(file.type)) {
    activeUploadError.value = 'Please upload JPG, PNG, WebP, GIF, PDF, Word, or text files only.';
    return;
  }
  activeUploadFile.value = file;
  activeUploadError.value = '';
};

const onInlineDragEnter = () => (activeUploadDropActive.value = true);
const onInlineDragOver = () => (activeUploadDropActive.value = true);
const onInlineDragLeave = () => (activeUploadDropActive.value = false);
const onInlineDrop = (event) => {
  activeUploadDropActive.value = false;
  const file = event.dataTransfer?.files?.[0];
  if (file) {
    selectInlineFile(file);
    // Auto-upload if validation passed (no error)
    if (!activeUploadError.value) {
      uploadInlineFile();
    }
  }
};

const clearInlineSelection = () => {
  activeUploadFile.value = null;
  activeUploadProgress.value = 0;
  activeUploadError.value = '';
  const input = document.getElementById('inline-file-input-' + activeUploadKey.value);
  if (input) input.value = '';
};

// Backend-authoritative upload state check.
// The backend sets status='uploading' before storage begins, so we can
// check the file payload from the last fetchData() call instead of localStorage.

// Active upload state tracking
let waitingForServer = false;

const uploadInlineFile = async () => {
  const key = activeUploadKey.value;
  const originalFile = activeUploadFile.value;
  if (!key || !originalFile) return;

  // Backend-authoritative guard: check if the backend already reports this file as uploading.
  // This replaces the old localStorage-based duplicate upload prevention.
  const backendFile = fileStatuses.value[key];
  if (backendFile?.status === 'uploading' && !activeUploadUploading.value) {
    activeUploadError.value = 'An upload for this document is already in progress. Please wait.';
    return;
  }

  activeUploadUploading.value = true;
  activeUploadProgress.value = 0;
  activeUploadLoaded.value = 0;
  activeUploadTotal.value = 0;
  activeUploadError.value = '';
  activeUploadSuccess.value = false;

  // Track whether client→server transfer is done (waiting for server→S3)
  let waitingForServer = false;
  // Set up abort controller for timeout
  const abortController = new AbortController();
  let serverTimeout = null;

  const doUpload = async (fileToUpload) => {
    const filename = fileToUpload.name || originalFile.name;
    try {
      const form = new FormData();
      form.append('file', fileToUpload, filename);
      form.append('field', key);

      const { data } = await axios.post('/user/application/reupload', form, {
        signal: abortController.signal,
        onUploadProgress: (progressEvent) => {
          if (progressEvent.total) {
            activeUploadLoaded.value = progressEvent.loaded;
            activeUploadTotal.value = progressEvent.total;
            const pct = Math.round((progressEvent.loaded * 100) / progressEvent.total);

            if (progressEvent.loaded >= progressEvent.total && !waitingForServer) {
              waitingForServer = true;
              activeUploadProgress.value = 95;
              // Start a 180-second timeout for server processing (S3 uploads can take a while)
              serverTimeout = setTimeout(() => {
                abortController.abort();
              }, 180000);
            } else if (!waitingForServer) {
              activeUploadProgress.value = Math.min(90, pct);
            }
          }
        }
      });

      if (serverTimeout) clearTimeout(serverTimeout);

      fileStatuses.value[key] = data.file;
      activeUploadProgress.value = 100;
      activeUploadSuccess.value = true;
      await fetchData();
    } catch (err) {
      if (serverTimeout) clearTimeout(serverTimeout);
      if (err.name === 'CanceledError' || err.code === 'ERR_CANCELED' || axios.isCancel(err)) {
        activeUploadError.value = 'Upload is taking longer than expected. It may still be processing in the background. Please refresh the page in a few minutes.';
      } else {
        activeUploadError.value = err.response?.data?.message || 'Failed to upload file. Please try again.';
      }
      activeUploadSuccess.value = false;
      await fetchData();
    } finally {
      if (serverTimeout) clearTimeout(serverTimeout);
      activeUploadUploading.value = false;
    }
  };

  // Non-image files (e.g. PDF) cannot be processed by Compressor — upload directly.
  const isImage = originalFile.type.startsWith('image/');
  if (!isImage) {
    await doUpload(originalFile);
    return;
  }

  new Compressor(originalFile, {
    quality: 0.6,
    maxWidth: 1200,
    convertSize: 500000, // Convert to JPEG if over 500KB
    mimeType: 'image/webp', // WebP is 30-50% smaller than JPEG
    success: (compressedFile) => { doUpload(compressedFile); },
    error(err) {
      activeUploadError.value = err.message || 'Image compression failed.';
      activeUploadUploading.value = false;
      activeUploadSuccess.value = false;
    },
  });
};

const openImageModal = (file) => { 
  const src = getFileUrl(file);
  if(!src || !hasImagePreview(file)) return; 
  previewSrc.value = src; 
  showImageModal.value = true; 
  document.body.style.overflow = 'hidden';
};

const closeImageModal = () => { 
  showImageModal.value = false; 
  previewSrc.value = ""; 
  document.body.style.overflow = '';
};

const closeModal = () => (showModal.value = false);

// grades extraction removed — documents are uploaded and stored as usual
const goToGrades = () => {
  router.visit(props.gradeUrl || '/grades/abm');
};

const goToQualifiedPrograms = () => {
  router.visit(route('applicant.qualified-programs.page'));
};

/**
 * Download the Grade Verification Slip by triggering a direct browser download.
 * The route is authenticated — the server uses the session to identify the applicant.
 * No applicant ID or reference number is passed as a URL parameter to prevent
 * IDOR (Insecure Direct Object Reference) attacks.
 */
const welcomeText = computed(() => `Welcome back, ${props.user?.firstname || 'Applicant'}!`);

const downloadGradeVerificationSlip = () => {
  // Navigate directly to the authenticated download route.
  // The server responds with Content-Disposition: attachment so the browser
  // saves the file without navigating away from the page.
  //
  // This replaces the previous axios blob approach, which lost the browser's
  // user-gesture context after `await` and was silently blocked on mobile
  // (Chrome Android, Safari iOS) and desktops with strict download settings.
  slipDownloadError.value = '';
  window.location.href = '/applicant-dashboard/grade-verification-slip';
};

/**
 * Download the F137 Request Letter.
 * Fresh Philippine date is applied server-side on every download.
 * Only available when school and former_school_address are set in the applicant's profile.
 */
const f137DownloadError = ref('');
const downloadF137RequestLetter = () => {
  f137DownloadError.value = '';
  window.location.href = '/applicant-dashboard/f137-request-letter';
};

onMounted(() => { 
  // Clear all upload state locks so a refresh allows the user to retry
  try {
    for (let i = 0; i < localStorage.length; i++) {
      const k = localStorage.key(i);
      if (k && k.startsWith('puptas_upload_')) {
        localStorage.removeItem(k);
        i--;
      }
    }
  } catch (e) { /* ignore */ }

  fetchData();
  
  // Check for success query parameter
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('success') === 'grades_saved') {
    showSuccessNotification.value = true;
    
    // Remove query parameter from URL
    window.history.replaceState({}, document.title, window.location.pathname);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
      showSuccessNotification.value = false;
    }, 5000);
  }
});
</script>

<template>
  <Head title="Applicant Dashboard" />
  <ApplicantLayout title="Applicant Dashboard">

    <template #header>
      <BlurText
        text="Applicant Dashboard"
        :delay="100"
        animate-by="words"
        direction="top"
        class-name="font-bold text-2xl text-gray-900 dark:text-gray-100"
      />
    </template>

    <div class="w-full">
      <div class="w-full max-w-full space-y-4 sm:space-y-6 overflow-x-hidden">
        
        <!-- Success Notification -->
        <Transition name="slide-down">
          <div v-if="showSuccessNotification" class="p-4 bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 dark:border-green-400 rounded-lg shadow-lg flex items-center gap-3">
            <div class="flex-shrink-0">
              <svg class="w-6 h-6 text-green-500 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="flex-1">
              <p class="text-sm font-semibold text-green-800 dark:text-green-200">Success!</p>
              <p class="text-sm text-green-700 dark:text-green-300">Grades saved successfully!</p>
            </div>
            <button @click="showSuccessNotification = false" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-200 text-xl leading-none">&times;</button>
          </div>
        </Transition>
        
        <!-- Welcome Header -->
        <div class="flex justify-between items-center">
          <div>
            <BlurText
              :text="welcomeText"
              :delay="100"
              animate-by="words"
              direction="top"
              class-name="text-xl md:text-2xl lg:text-3xl font-bold text-gray-900 dark:text-white"
            />
            <BlurText
              text="Manage your application and track your progress"
              :delay="60"
              animate-by="words"
              direction="top"
              :step-duration="0.3"
              class-name="text-gray-600 dark:text-gray-400 mt-1"
            />
          </div>
        </div>

        <!-- Status Card (Always on Top) -->
        <div :class="[
          'relative overflow-hidden rounded-2xl transition-all duration-300 border backdrop-blur-xs shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:shadow-[0_8px_30px_rgb(0,0,0,0.2)]',
          enrollmentInfo.cardBg, enrollmentInfo.border, enrollmentInfo.ring
        ]">
          <!-- Decorative ambient background glow -->
          <div class="absolute -top-16 -right-16 w-56 h-56 rounded-full blur-3xl pointer-events-none transition-transform duration-700"
               :class="enrollmentInfo.glow"></div>
          <div class="absolute -bottom-16 -left-16 w-48 h-48 rounded-full blur-3xl pointer-events-none transition-transform duration-700"
               :class="enrollmentInfo.glow"></div>

          <!-- Skeleton Loading -->
          <div v-if="loading && !applicationStatus && !enrollmentStatus" class="p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between">
              <div class="h-6 w-36 bg-gray-200 dark:bg-gray-700 rounded-full animate-pulse"></div>
              <div class="h-5 w-24 bg-gray-200 dark:bg-gray-700 rounded-md animate-pulse"></div>
            </div>
            <div class="flex items-start gap-4">
              <div class="w-14 h-14 rounded-2xl bg-gray-200 dark:bg-gray-700 animate-pulse shrink-0"></div>
              <div class="flex-1 space-y-2.5">
                <div class="h-6 w-52 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                <div class="h-4 w-full max-w-md bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
              </div>
            </div>
          </div>

          <!-- Main Status Card Content -->
          <div v-else class="relative p-5 sm:p-6 lg:p-7 flex flex-col gap-4 sm:gap-5">
            
            <!-- Top Bar: Status Category Badge & Meta Information -->
            <div class="flex items-center justify-between flex-wrap gap-2.5 pb-3 border-b border-gray-200/50 dark:border-gray-700/40">
              <div class="flex items-center gap-2">
                <span :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold tracking-wide shadow-xs', enrollmentInfo.badge]">
                  <span class="relative flex h-2 w-2">
                    <span v-if="enrollmentInfo.type === 'info' || enrollmentInfo.type === 'warning'"
                          :class="['absolute inline-flex h-full w-full rounded-full opacity-75', enrollmentInfo.badgeDot]"></span>
                    <span :class="['relative inline-flex rounded-full h-2 w-2', enrollmentInfo.badgeDot.replace(' animate-ping', '')]"></span>
                  </span>
                  <span>{{ enrollmentInfo.badgeLabel }}</span>
                </span>
                <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400 hidden sm:inline">
                  • {{ enrollmentInfo.statusTag }}
                </span>
              </div>

              <!-- Quick Progress & Target Meta -->
              <div class="flex items-center gap-2 text-xs">
                <span v-if="props.user?.program?.code" class="px-2.5 py-0.5 rounded-md font-mono font-bold bg-white/80 dark:bg-gray-800/80 text-gray-800 dark:text-gray-200 border border-gray-200/60 dark:border-gray-700/60 shadow-2xs">
                  {{ props.user.program.code }}
                </span>
                <span v-else-if="props.user?.strand" class="px-2.5 py-0.5 rounded-md font-mono font-bold bg-white/80 dark:bg-gray-800/80 text-gray-800 dark:text-gray-200 border border-gray-200/60 dark:border-gray-700/60 shadow-2xs">
                  {{ props.user.strand.toUpperCase() }}
                </span>
                <span class="text-[11px] font-semibold text-gray-500 dark:text-gray-400">
                  Stage {{ stageProgressInfo.currentStepNum }} of {{ stageProgressInfo.total }}
                </span>
              </div>
            </div>

            <!-- Middle Row: Icon, Headline & Action -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 sm:gap-5">
              <div class="flex items-start gap-4 sm:gap-5 min-w-0">
                <!-- Status Icon -->
                <div :class="['w-13 h-13 sm:w-15 sm:h-15 rounded-2xl flex items-center justify-center shrink-0 text-white transition-transform duration-300 hover:scale-105', enrollmentInfo.iconBg]">
                  <!-- Academic Cap / Enrolled -->
                  <svg v-if="enrollmentInfo.icon === 'academic-cap'" class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                  </svg>
                  <!-- Clock / In Progress / Under Review -->
                  <svg v-else-if="enrollmentInfo.icon === 'clock'" class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <!-- Warning / Returned / Action Required -->
                  <svg v-else-if="enrollmentInfo.icon === 'warning'" class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                  </svg>
                  <!-- Queue / Waitlisted -->
                  <svg v-else-if="enrollmentInfo.icon === 'queue'" class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <!-- X Circle / Rejected / Not Enrolled -->
                  <svg v-else-if="enrollmentInfo.icon === 'x-circle'" class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <!-- Document / Application Form (Draft) -->
                  <svg v-else-if="enrollmentInfo.icon === 'document-text' || enrollmentInfo.icon === 'draft'" class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                  </svg>
                  <!-- Document Edit / Pencil-Square (Fallback 1) -->
                  <svg v-else-if="enrollmentInfo.icon === 'document-edit'" class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125l-2.638-2.638" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                  </svg>
                  <!-- Fallback: Default Document -->
                  <svg v-else class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                  </svg>
                </div>

                <!-- Text block -->
                <div class="min-w-0 flex-1">
                  <h2 :class="['text-xl sm:text-2xl font-extrabold tracking-tight leading-tight', enrollmentInfo.color]">
                    {{ enrollmentInfo.label }}
                  </h2>
                  <p class="mt-1 text-xs sm:text-sm text-gray-600 dark:text-gray-300 leading-relaxed max-w-2xl">
                    {{ enrollmentInfo.description }}
                  </p>
                </div>
              </div>

              <!-- Contextual Action Button -->
              <div v-if="enrollmentInfo.actionKey" class="flex md:flex-col items-stretch sm:items-end justify-start gap-2 shrink-0 pt-1 md:pt-0">
                <button
                  v-if="enrollmentInfo.actionKey === 'documents' || enrollmentInfo.actionKey === 'upload_cor'"
                  @click="scrollToDocuments"
                  class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-[#9E122C] hover:bg-[#7a0e22] shadow-md shadow-red-950/20 transition-all duration-200 hover:-translate-y-0.5 cursor-pointer"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                  </svg>
                  <span>{{ enrollmentInfo.actionKey === 'upload_cor' ? 'Upload COR Files' : 'Review Documents' }}</span>
                </button>
                <button
                  v-else-if="enrollmentInfo.actionKey === 'review_app'"
                  @click="showModal = true"
                  class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-[#9E122C] hover:bg-[#7a0e22] shadow-md shadow-red-950/20 transition-all duration-200 hover:-translate-y-0.5 cursor-pointer"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span>View Details</span>
                </button>
                <button
                  v-else-if="enrollmentInfo.actionKey === 'view_programs'"
                  @click="goToQualifiedPrograms"
                  class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-purple-600 hover:bg-purple-700 shadow-md shadow-purple-600/25 transition-all duration-200 hover:-translate-y-0.5 cursor-pointer"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                  </svg>
                  <span>Qualified Programs</span>
                </button>
              </div>
            </div>

            <!-- Evaluator Remarks Alert inside Status Card (if applicable) -->
            <div v-if="currentStageNote" class="rounded-xl p-3.5 bg-amber-500/10 dark:bg-amber-950/40 border border-amber-300/80 dark:border-amber-700/60 flex items-start gap-3">
              <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
              </svg>
              <div class="text-xs text-amber-900 dark:text-amber-200 leading-relaxed">
                <span class="font-bold uppercase tracking-wider text-[11px] text-amber-800 dark:text-amber-300 mr-1.5">Evaluator Note:</span>
                <span class="italic font-medium">"{{ currentStageNote }}"</span>
              </div>
            </div>

          </div>
        </div>

        <!-- COR Upload Alert -->
        <div v-if="needsCorUpload" class="bg-blue-50 dark:bg-blue-900/20 rounded-xl shadow-md border-l-4 border-blue-500 p-4 sm:p-6 mb-6">
          <div class="flex items-start gap-3 sm:gap-4">
            <div class="flex-shrink-0 mt-0.5">
              <svg class="w-6 h-6 sm:w-8 sm:h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-base sm:text-xl font-bold text-blue-900 dark:text-blue-100 mb-1 sm:mb-2">
                Action Required: Upload Certificate of Registration (COR)
              </h3>
              <p class="text-sm text-blue-800 dark:text-blue-200">
                You have been cleared for enrollment! To complete your final registration, please scroll down to the documents section and upload clear photos of the <strong>Front and Back of your Certificate of Registration (COR)</strong>.
              </p>
            </div>
          </div>
        </div>

        <!-- Promissory Note Alert -->
        <div v-if="requiresPromissoryNote" class="bg-orange-50 dark:bg-orange-900/20 rounded-xl shadow-md border-l-4 border-orange-500 p-4 sm:p-6">
          <div class="flex items-start gap-3 sm:gap-4">
            <div class="flex-shrink-0 mt-0.5">
              <svg class="w-6 h-6 sm:w-8 sm:h-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-base sm:text-xl font-bold text-orange-900 dark:text-orange-100 mb-1 sm:mb-2">
                Action Required: Promissory Note
              </h3>
              <p class="text-sm text-orange-800 dark:text-orange-200">
                The evaluator has indicated that you need to submit a <strong>Promissory Note</strong>. 
                Please prepare this document as it is required to proceed with your enrollment process.
              </p>
            </div>
          </div>
        </div>

        <!-- Guidance Office Alert -->
        <div v-if="requiresGuidanceOffice" class="bg-orange-50 dark:bg-orange-900/20 rounded-xl shadow-md border-l-4 border-orange-500 p-4 sm:p-6">
          <div class="flex items-start gap-3 sm:gap-4">
            <div class="flex-shrink-0 mt-0.5">
              <svg class="w-6 h-6 sm:w-8 sm:h-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-base sm:text-xl font-bold text-orange-900 dark:text-orange-100 mb-1 sm:mb-2">
                Action Required: Go to Guidance Office
              </h3>
              <p class="text-sm text-orange-800 dark:text-orange-200">
                The evaluator has indicated that you need to go to the <strong>Guidance Office</strong>. 
                Please proceed to the Guidance Office for further instructions regarding your application.
              </p>
            </div>
          </div>
        </div>

        <!-- Admissions Office Alert -->
        <div v-if="requiresAdmissionOffice" class="bg-blue-50 dark:bg-blue-900/20 rounded-xl shadow-md border-l-4 border-blue-500 p-4 sm:p-6">
          <div class="flex items-start gap-3 sm:gap-4">
            <div class="flex-shrink-0 mt-0.5">
              <svg class="w-6 h-6 sm:w-8 sm:h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="text-base sm:text-xl font-bold text-blue-900 dark:text-blue-100 mb-1 sm:mb-2">
                Action Required: Go to Admissions Office
              </h3>
              <p class="text-sm text-blue-800 dark:text-blue-200">
                The evaluator has indicated that you need to go to the <strong>Admissions Office</strong>. 
                Please proceed to the Admissions Office for further instructions regarding your application.
              </p>
            </div>
          </div>
        </div>

        <!-- Medical System Redirect Card -->
        <div v-if="showMedicalRedirect" class="relative overflow-hidden bg-gradient-to-br from-emerald-50 via-green-50/50 to-teal-50 dark:from-emerald-950/40 dark:via-gray-900 dark:to-teal-950/40 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:shadow-[0_8px_30px_rgba(16,185,129,0.1)] border border-emerald-200/60 dark:border-emerald-800/60 p-6 sm:p-8 transition-all duration-500 hover:shadow-[0_8px_30px_rgba(16,185,129,0.15)] group/card">
          <!-- Decorative background blur -->
          <div class="absolute -top-24 -right-24 w-64 h-64 bg-emerald-400/20 dark:bg-emerald-600/10 rounded-full blur-3xl pointer-events-none transition-transform duration-700 group-hover/card:scale-110"></div>
          <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-teal-400/20 dark:bg-teal-600/10 rounded-full blur-3xl pointer-events-none transition-transform duration-700 group-hover/card:scale-110"></div>

          <div class="relative flex flex-col items-start gap-6">
            <div class="flex-1 w-full">
              <div class="flex items-center gap-3 mb-3">
                <div class="relative w-8 h-8 sm:w-10 sm:h-10 flex-shrink-0">
                  <!-- Pulsing outer ring -->
                  <div class="absolute inset-0 bg-emerald-400 dark:bg-emerald-500 rounded-full animate-ping opacity-25"></div>
                  <!-- Inner circle -->
                  <div class="relative w-full h-full bg-gradient-to-tr from-emerald-600 to-green-500 rounded-full flex items-center justify-center shadow-md shadow-emerald-500/30 border border-white/20">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white drop-shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                  </div>
                </div>
                <h3 class="text-2xl sm:text-3xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-emerald-700 to-teal-600 dark:from-emerald-400 dark:to-teal-300 tracking-tight">
                  Evaluation & Interview Complete!
                </h3>
              </div>
              <p class="text-gray-700 dark:text-gray-300 mb-6 text-base leading-relaxed">
                <span class="font-semibold text-emerald-700 dark:text-emerald-400">Congratulations!</span> You've successfully completed the evaluation and interview stages. 
                Your next step is to create a Health Record in the Medical System.
              </p>
              
              <!-- Stepper Instructions (Full Width) -->
              <div class="bg-white/80 dark:bg-gray-800/80 backdrop-blur-xl rounded-2xl p-4 sm:p-6 border border-emerald-100/50 dark:border-emerald-800/30 shadow-[0_8px_30px_rgb(0,0,0,0.04)] mb-6 transition-all hover:shadow-[0_8px_30px_rgba(16,185,129,0.1)] group/stepper">
                <div class="flex items-center gap-2 mb-6 opacity-80">
                  <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                  <p class="text-sm font-black text-gray-500 dark:text-gray-400 uppercase tracking-widest">INSTRUCTION</p>
                </div>
                
                <div class="relative w-full max-w-4xl mx-auto">
                  <!-- Connecting Line (Desktop) -->
                  <div class="hidden sm:block absolute top-[19px] left-[10%] right-[10%] h-[2px] bg-gradient-to-r from-emerald-100 via-emerald-300 to-emerald-100 dark:from-gray-700 dark:via-emerald-600/50 dark:to-gray-700 rounded-full">
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-emerald-400 to-transparent w-full h-full opacity-0 group-hover/stepper:opacity-100 group-hover/stepper:animate-[shimmer_2s_infinite]"></div>
                  </div>
                  
                  <!-- Connecting Line (Mobile) -->
                  <div class="sm:hidden absolute top-[19px] bottom-[19px] left-[20px] w-[2px] -translate-x-1/2 bg-gradient-to-b from-emerald-100 via-emerald-300 to-emerald-100 dark:from-gray-700 dark:via-emerald-600/50 dark:to-gray-700 rounded-full">
                    <div class="absolute inset-0 bg-gradient-to-b from-transparent via-emerald-400 to-transparent w-full h-full opacity-0 group-hover/stepper:opacity-100 group-hover/stepper:animate-[shimmer_2s_infinite]"></div>
                  </div>
                  
                  <div class="flex flex-col sm:flex-row gap-6 sm:gap-0 justify-between relative z-10">
                    
                    <!-- Step 1 -->
                    <div class="flex flex-row sm:flex-col items-center sm:items-center flex-1 group/step w-full">
                      <div class="w-10 h-10 flex-shrink-0 rounded-full bg-white dark:bg-gray-800 border-[2px] border-emerald-100 dark:border-gray-700 flex items-center justify-center shadow-sm sm:mb-3 transition-all duration-300 group-hover/step:-translate-y-1 group-hover/step:border-emerald-300 dark:group-hover/step:border-emerald-600 relative overflow-hidden group-hover/step:shadow-emerald-500/20 z-10">
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-400/10 to-teal-500/10 opacity-0 group-hover/step:opacity-100 transition-opacity"></div>
                        <span class="text-base font-bold text-emerald-700 dark:text-emerald-400">1</span>
                      </div>
                      <div class="text-left sm:text-center ml-4 sm:ml-0 px-0 sm:px-2 flex-1">
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium leading-relaxed transition-colors group-hover/step:text-gray-900 dark:group-hover/step:text-gray-200">
                          Click the button below to go to the <span class="font-bold text-gray-800 dark:text-gray-200 border-b border-dashed border-gray-300 dark:border-gray-600">Medical System</span>
                        </p>
                      </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="flex flex-row sm:flex-col items-center sm:items-center flex-1 group/step w-full">
                      <div class="w-10 h-10 flex-shrink-0 rounded-full bg-white dark:bg-gray-800 border-[2px] border-emerald-100 dark:border-gray-700 flex items-center justify-center shadow-sm sm:mb-3 transition-all duration-300 group-hover/step:-translate-y-1 group-hover/step:border-emerald-300 dark:group-hover/step:border-emerald-600 relative overflow-hidden group-hover/step:shadow-emerald-500/20 z-10">
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-400/10 to-teal-500/10 opacity-0 group-hover/step:opacity-100 transition-opacity"></div>
                        <span class="text-base font-bold text-emerald-700 dark:text-emerald-400">2</span>
                      </div>
                      <div class="text-left sm:text-center ml-4 sm:ml-0 px-0 sm:px-2 flex-1">
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium leading-relaxed transition-colors group-hover/step:text-gray-900 dark:group-hover/step:text-gray-200">
                          Click <span class="font-bold text-gray-800 dark:text-gray-200">Log In Via One Portal</span>
                        </p>
                      </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="flex flex-row sm:flex-col items-center sm:items-center flex-1 group/step w-full">
                      <div class="w-10 h-10 flex-shrink-0 rounded-full bg-white dark:bg-gray-800 border-[2px] border-emerald-100 dark:border-gray-700 flex items-center justify-center shadow-sm sm:mb-3 transition-all duration-300 group-hover/step:-translate-y-1 group-hover/step:border-emerald-300 dark:group-hover/step:border-emerald-600 relative overflow-hidden group-hover/step:shadow-emerald-500/20 z-10">
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-400/10 to-teal-500/10 opacity-0 group-hover/step:opacity-100 transition-opacity"></div>
                        <span class="text-base font-bold text-emerald-700 dark:text-emerald-400">3</span>
                      </div>
                      <div class="text-left sm:text-center ml-4 sm:ml-0 px-0 sm:px-2 flex-1">
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium leading-relaxed transition-colors group-hover/step:text-gray-900 dark:group-hover/step:text-gray-200">
                          Log in with your <span class="font-bold text-gray-800 dark:text-gray-200">credentials</span>
                        </p>
                      </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="flex flex-row sm:flex-col items-center sm:items-center flex-1 group/step w-full">
                      <div class="w-10 h-10 flex-shrink-0 rounded-full bg-white dark:bg-gray-800 border-[2px] border-emerald-100 dark:border-gray-700 flex items-center justify-center shadow-sm sm:mb-3 transition-all duration-300 group-hover/step:-translate-y-1 group-hover/step:border-emerald-300 dark:group-hover/step:border-emerald-600 relative overflow-hidden group-hover/step:shadow-emerald-500/20 z-10">
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-400/10 to-teal-500/10 opacity-0 group-hover/step:opacity-100 transition-opacity"></div>
                        <span class="text-base font-bold text-emerald-700 dark:text-emerald-400">4</span>
                      </div>
                      <div class="text-left sm:text-center ml-4 sm:ml-0 px-0 sm:px-2 flex-1 flex flex-row sm:flex-col items-center sm:items-center justify-start sm:justify-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium leading-relaxed transition-colors group-hover/step:text-gray-900 dark:group-hover/step:text-gray-200 mr-2 sm:mr-0 sm:mb-1.5">
                          Click 
                        </p>
                        <span class="px-2 py-0.5 bg-white dark:bg-gray-700 text-emerald-600 dark:text-emerald-400 rounded border border-gray-200 dark:border-gray-600 shadow-sm font-mono text-[10px] font-bold tracking-tight uppercase whitespace-nowrap group-hover/step:border-emerald-300 dark:group-hover/step:border-emerald-500 transition-colors">Fill Up Form</span>
                      </div>
                    </div>

                    <!-- Step 5 -->
                    <div class="flex flex-row sm:flex-col items-center sm:items-center flex-1 group/step w-full">
                      <div class="w-10 h-10 flex-shrink-0 rounded-full bg-white dark:bg-gray-800 border-[2px] border-emerald-100 dark:border-gray-700 flex items-center justify-center shadow-sm sm:mb-3 transition-all duration-300 group-hover/step:-translate-y-1 group-hover/step:border-emerald-300 dark:group-hover/step:border-emerald-600 relative overflow-hidden group-hover/step:shadow-emerald-500/20 z-10">
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-400/10 to-teal-500/10 opacity-0 group-hover/step:opacity-100 transition-opacity"></div>
                        <span class="text-base font-bold text-emerald-700 dark:text-emerald-400">5</span>
                      </div>
                      <div class="text-left sm:text-center ml-4 sm:ml-0 px-0 sm:px-2 flex-1 flex flex-row sm:flex-col items-center sm:items-center justify-start sm:justify-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-medium leading-relaxed transition-colors group-hover/step:text-gray-900 dark:group-hover/step:text-gray-200 sm:mb-1.5">
                          Fill up the information sheet completely
                        </p>
                      </div>
                    </div>
                    
                  </div>
                </div>
              </div>

              <!-- Schedule Panel (Now Full Width below Stepper) -->
              <div class="bg-white/70 dark:bg-gray-800/70 backdrop-blur-md rounded-xl border border-emerald-100 dark:border-emerald-800/50 shadow-sm overflow-hidden flex flex-col transition-all duration-300 hover:shadow-md hover:bg-white/90 dark:hover:bg-gray-800/90 hover:-translate-y-0.5 mb-8">
                <div class="p-4 sm:p-5 pb-4 flex items-center justify-between border-b border-gray-100 dark:border-gray-700/50 cursor-pointer group" @click="showSchedule = !showSchedule">
                  <div class="flex items-center gap-3 pr-2">
                    <div class="p-2 flex-shrink-0 bg-blue-100 dark:bg-blue-900/50 rounded-lg shadow-sm group-hover:bg-blue-200 dark:group-hover:bg-blue-800/60 transition-colors">
                      <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <p class="text-[15px] sm:text-lg leading-tight font-bold text-gray-900 dark:text-white transition-colors group-hover:text-emerald-700 dark:group-hover:text-emerald-400">Schedule of Issuance of medical clearance</p>
                  </div>
                  <button type="button" class="flex-shrink-0 text-gray-500 hover:text-emerald-600 dark:text-gray-400 dark:hover:text-emerald-400 focus:outline-none transition-transform duration-300" :class="{ 'rotate-180': showSchedule }">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                  </button>
                </div>
                <div v-show="showSchedule" class="transition-all duration-500 origin-top">
                  <div class="overflow-x-auto flex-1 custom-scrollbar">
                    <table class="min-w-full text-xs sm:text-sm text-left text-gray-700 dark:text-gray-300 h-full">
                    <thead class="text-[11px] sm:text-xs text-gray-500 dark:text-gray-400 uppercase bg-gray-50/80 dark:bg-gray-800/50">
                      <tr>
                        <th scope="col" class="px-4 sm:px-5 py-3 font-semibold whitespace-nowrap min-w-[100px]">Date</th>
                        <th scope="col" class="px-4 sm:px-5 py-3 font-semibold whitespace-nowrap min-w-[140px]">Time</th>
                        <th scope="col" class="px-4 sm:px-5 py-3 font-semibold whitespace-nowrap min-w-[150px]">Program</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100/50 dark:divide-gray-700/50 hover:bg-emerald-50/60 dark:hover:bg-emerald-900/20 transition-colors group cursor-default">
                      <tr>
                        <td class="px-5 py-3 font-medium align-top group-hover:text-emerald-900 dark:group-hover:text-emerald-300 transition-colors" rowspan="3">July 1, 2026<br><span class="text-[10px] sm:text-xs text-emerald-600 dark:text-emerald-400 font-medium">Wednesday</span></td>
                        <td class="px-5 py-3 whitespace-nowrap">8:00AM - 11:00AM</td>
                        <td class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">BSBA-MM</td>
                      </tr>
                      <tr>
                        <td class="px-5 py-3 whitespace-nowrap">12:00PM - 3:00PM</td>
                        <td class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">BSIT</td>
                      </tr>
                      <tr>
                        <td class="px-5 py-3 whitespace-nowrap">3:30PM - 6:30PM</td>
                        <td class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">BS PSYCHOLOGY</td>
                      </tr>
                    </tbody>
                    <tbody class="divide-y divide-gray-100/50 dark:divide-gray-700/50 hover:bg-emerald-50/60 dark:hover:bg-emerald-900/20 transition-colors border-t border-gray-100 dark:border-gray-700/50 bg-gray-50/40 dark:bg-gray-800/20 group cursor-default">
                      <tr>
                        <td class="px-5 py-3 font-medium align-top group-hover:text-emerald-900 dark:group-hover:text-emerald-300 transition-colors" rowspan="3">July 2, 2026<br><span class="text-[10px] sm:text-xs text-emerald-600 dark:text-emerald-400 font-medium">Thursday</span></td>
                        <td class="px-5 py-3 whitespace-nowrap">8:00AM - 11:00AM</td>
                        <td class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">BSBA-HRM</td>
                      </tr>
                      <tr>
                        <td class="px-5 py-3 whitespace-nowrap">12:00PM - 3:00PM</td>
                        <td class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">BSOA</td>
                      </tr>
                      <tr>
                        <td class="px-5 py-3 whitespace-nowrap">3:30PM - 6:30PM</td>
                        <td class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">BSED-MATH</td>
                      </tr>
                    </tbody>
                    <tbody class="divide-y divide-gray-100/50 dark:divide-gray-700/50 hover:bg-emerald-50/60 dark:hover:bg-emerald-900/20 transition-colors border-t border-gray-100 dark:border-gray-700/50 group cursor-default">
                      <tr>
                        <td class="px-5 py-3 font-medium align-top group-hover:text-emerald-900 dark:group-hover:text-emerald-300 transition-colors" rowspan="3">July 3, 2026<br><span class="text-[10px] sm:text-xs text-emerald-600 dark:text-emerald-400 font-medium">Friday</span></td>
                        <td class="px-5 py-3 whitespace-nowrap">8:00AM - 11:00AM</td>
                        <td class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">BSECE</td>
                      </tr>
                      <tr>
                        <td class="px-5 py-3 whitespace-nowrap">12:00PM - 3:00PM</td>
                        <td class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">BSED-ENGLISH</td>
                      </tr>
                      <tr>
                        <td class="px-5 py-3 whitespace-nowrap">3:30PM - 6:30PM</td>
                        <td class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">BSME</td>
                      </tr>
                    </tbody>
                    <tbody class="divide-y divide-gray-100/50 dark:divide-gray-700/50 hover:bg-emerald-50/60 dark:hover:bg-emerald-900/20 transition-colors border-t border-gray-100 dark:border-gray-700/50 bg-gray-50/40 dark:bg-gray-800/20 group cursor-default">
                      <tr>
                        <td class="px-5 py-3 font-medium align-top group-hover:text-emerald-900 dark:group-hover:text-emerald-300 transition-colors" rowspan="2">July 8, 2026<br><span class="text-[10px] sm:text-xs text-emerald-600 dark:text-emerald-400 font-medium">Wednesday</span></td>
                        <td class="px-5 py-3 whitespace-nowrap">9:00AM - 12:00PM</td>
                        <td class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">DIT</td>
                      </tr>
                      <tr>
                        <td class="px-5 py-3 whitespace-nowrap">1:00PM - 4:00PM</td>
                        <td class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-200">DOMT-LOM</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                </div>
              </div>

              <div class="flex items-center pt-2">
                <a
                  href="https://clinic-ms.inaebsit2027.com"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="group inline-flex items-center justify-center gap-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white px-8 py-4 rounded-xl shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 transition-all duration-300 hover:-translate-y-1 font-bold text-base ring-2 ring-transparent hover:ring-emerald-300/50"
                >
                  <svg class="w-5 h-5 transition-transform duration-300 group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                  </svg>
                  <span>Go to Medical System</span>
                  <svg class="w-5 h-5 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                  </svg>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Horizontal Application Timeline ── -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">

          <!-- Header -->
          <div class="flex items-center gap-2 px-5 py-3 border-b border-gray-100 dark:border-gray-700/80 bg-gray-50/60 dark:bg-gray-800/60">
            <svg class="w-3.5 h-3.5 shrink-0" style="color:#9E122C" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Application Timeline</h3>
            <span class="ml-auto text-[10px] font-bold tabular-nums bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 px-2 py-0.5 rounded-full">
              {{ timelineSteps.filter(s => !s.isFuture).length }} / {{ timelineSteps.length }} stages
            </span>
          </div>

          <!-- Horizontal stepper — fixed width on all screens, no scroll -->
          <div class="px-2 py-5 sm:px-4">
            <ol class="flex items-start w-full">
              <li v-for="(step, idx) in timelineSteps" :key="step.key" class="flex items-start flex-1 min-w-0">

                <!-- Step node -->
                <div :class="[
                  'flex flex-col items-center w-full px-0.5 sm:px-1 py-2.5 rounded-xl transition-all',
                  step.isCurrent
                    ? (step.action === 'rejected' || step.status === 'rejected'
                        ? 'bg-red-50 dark:bg-red-400/10 ring-1 ring-red-200 dark:ring-red-400/30'
                        : step.status === 'returned'
                          ? 'bg-orange-50 dark:bg-orange-400/10 ring-1 ring-orange-200 dark:ring-orange-400/30'
                          : 'bg-blue-50 dark:bg-blue-400/10 ring-1 ring-blue-200 dark:ring-blue-400/30')
                    : ''
                ]">
                  <!-- Dot -->
                  <span :class="[
                    'w-7 h-7 sm:w-8 sm:h-8 rounded-full border-2 flex items-center justify-center shrink-0 transition-all',
                    step.action === 'rejected' || step.status === 'rejected'
                      ? 'bg-red-500 border-red-500 text-white'
                      : step.status === 'completed'
                        ? 'bg-green-500 border-green-500 text-white'
                        : step.isCurrent && step.status === 'in_progress'
                          ? 'bg-blue-500 border-blue-500 text-white ring-4 ring-blue-500/20'
                          : step.status === 'returned'
                            ? 'bg-orange-400 border-orange-400 text-white'
                            : step.isFuture
                              ? 'bg-gray-100 dark:bg-gray-700 border-gray-200 dark:border-gray-600 text-gray-400 dark:text-gray-500'
                              : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-400'
                  ]">
                    <svg v-if="step.action === 'rejected' || step.status === 'rejected'" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    <svg v-else-if="step.status === 'completed'" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    <svg v-else-if="step.isCurrent && step.status === 'in_progress'" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                    <svg v-else-if="step.status === 'returned'" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.707 3.293a1 1 0 010 1.414L5.414 7H11a7 7 0 017 7v2a1 1 0 11-2 0v-2a5 5 0 00-5-5H5.414l2.293 2.293a1 1 0 11-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    <span v-else class="text-[10px] font-bold">{{ idx + 1 }}</span>
                  </span>

                  <!-- Label -->
                  <p :class="[
                    'mt-1.5 text-[0.55rem] sm:text-[0.65rem] text-center leading-snug px-0.5 transition-colors break-words w-full',
                    step.isCurrent
                      ? 'font-bold text-gray-900 dark:text-gray-100'
                      : step.isPast
                        ? 'font-semibold text-gray-600 dark:text-gray-400'
                        : 'font-medium text-gray-400 dark:text-gray-500'
                  ]">{{ step.label }}</p>

                  <!-- Status badge -->
                  <span :class="[
                    'mt-1 inline-flex items-center text-[0.5rem] sm:text-[0.6rem] font-bold px-1 sm:px-2 py-0.5 rounded-full capitalize tracking-wide',
                    step.action === 'rejected'
                      ? 'bg-red-100 text-red-700 dark:bg-red-400/15 dark:text-red-300'
                      : step.status === 'completed'
                        ? 'bg-green-100 text-green-700 dark:bg-green-400/15 dark:text-green-300'
                        : step.status === 'in_progress'
                          ? 'bg-blue-100 text-blue-700 dark:bg-blue-400/15 dark:text-blue-300'
                          : step.status === 'returned'
                            ? 'bg-orange-100 text-orange-700 dark:bg-orange-400/15 dark:text-orange-300'
                            : step.isFuture
                              ? 'bg-gray-100 text-gray-400 dark:bg-gray-700/60 dark:text-gray-500'
                              : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
                  ]">
                    {{ step.action === 'rejected' ? 'Rejected' : step.isFuture ? 'Upcoming' : capitalize((step.status || '').replace(/_/g, ' ')) }}
                  </span>

                  <!-- Timestamp -->
                  <p v-if="step.created_at" class="mt-1 text-[0.5rem] sm:text-[0.6rem] text-gray-400 dark:text-gray-500 tabular-nums text-center leading-snug">
                    {{ formatTimestamp(step.created_at) }}
                  </p>

                  <!-- Reviewer note pill -->
                  <div v-if="step.isCurrent && step.reviewer_notes"
                       class="mt-1.5 w-full px-1 py-1 rounded-lg bg-red-50 dark:bg-red-400/10 border border-red-100 dark:border-red-400/20">
                    <p class="text-[0.5rem] text-red-700 dark:text-red-300 leading-snug line-clamp-3 text-center" :title="step.reviewer_notes">
                      {{ step.reviewer_notes }}
                    </p>
                  </div>
                </div>

                <!-- Connector between steps -->
                <div v-if="idx < timelineSteps.length - 1"
                     class="flex items-start pt-[13px] sm:pt-[15px] shrink-0 w-2 sm:w-4">
                  <div :class="[
                    'h-px w-full transition-colors',
                    step.status === 'completed' ? 'bg-green-400 dark:bg-green-600' :
                    step.isCurrent ? 'bg-blue-300 dark:bg-blue-700' :
                    'bg-gray-200 dark:bg-gray-700'
                  ]"></div>
                </div>

              </li>
            </ol>
          </div>
        </div>

        <!-- Two-column layout: sidebar (actions/downloads) + main content -->
        <div :class="hasSidebarContent ? 'grid grid-cols-1 lg:grid-cols-[280px_1fr] xl:grid-cols-[300px_1fr] gap-6 items-start' : 'block'">

          <!-- ── Sidebar ── -->
          <aside v-if="hasSidebarContent" class="flex flex-col gap-4 lg:sticky lg:top-6">

            <!-- Quick Actions Card — only shown when at least one action button is available (or while loading) -->
            <div
              v-if="(loading && !applicationStatus && !stepKeys.length) || canReviewAndSubmit || allDocumentsUploaded"
              class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5"
            >
              <div class="flex items-center gap-2 mb-4">
                <svg class="w-4 h-4 flex-shrink-0" style="color:#9E122C" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Actions</p>
              </div>
              <div v-if="loading && !applicationStatus && !stepKeys.length" class="flex flex-col gap-3">
                <div class="h-11 w-full bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
                <div class="h-11 w-full bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
              </div>
              <div v-else class="flex flex-col gap-3">
                <!-- Review and Submit Application Button -->
                <button
                  v-if="canReviewAndSubmit"
                  @click="showModal = true"
                  class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white shadow-sm transition-all duration-200 min-h-[44px]"
                  style="background-color: #9E122C;"
                  onmouseover="this.style.backgroundColor='#7a0e22'"
                  onmouseout="this.style.backgroundColor='#9E122C'"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  Review and Submit Application
                </button>
                <!-- Input / View / Edit Grades Button -->
                <button
                  v-if="allDocumentsUploaded"
                  @click="goToGrades"
                  class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white shadow-sm transition-all duration-200 min-h-[44px]"
                  style="background-color: #D97706;"
                  onmouseover="this.style.backgroundColor='#b65f06'"
                  onmouseout="this.style.backgroundColor='#D97706'"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                  {{ applicationStatus && applicationStatus !== 'draft' ? (canEditGrades ? 'Edit Grades' : 'View Grades') : 'Input Grades' }}
                </button>
              </div>
            </div>

            <!-- Downloads Card -->
            <div
              v-if="showF137Button"
              class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5"
            >
              <div class="flex items-center gap-2 mb-4">
                <svg class="w-4 h-4 flex-shrink-0 text-[#9E122C] dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Downloads</p>
              </div>
              <div class="flex flex-col gap-3">
                <!-- Download F137 Request Letter -->
                <template v-if="showF137Button">
                  <button
                    v-if="props.canDownloadF137"
                    @click="downloadF137RequestLetter"
                    class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white shadow-sm transition-all duration-200 min-h-[44px]"
                    style="background-color: #9E122C;"
                    onmouseover="this.style.backgroundColor='#7a0e22'"
                    onmouseout="this.style.backgroundColor='#9E122C'"
                    title="Download your F137 Request Letter"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Download F137 Request Letter
                  </button>
                  <!-- F137 not available — prompt to complete profile -->
                  <div
                    v-else
                    class="w-full inline-flex items-start gap-2 px-4 py-3 rounded-lg text-sm border border-dashed border-red-300 bg-red-50 dark:bg-red-950/20 text-red-700 dark:text-red-300"
                    title="Complete Former School Information in your Profile to unlock this"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0 mt-0.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.27 16A2 2 0 005.07 19z" />
                    </svg>
                    <span class="text-xs leading-snug">
                      F137 unavailable — <a :href="route('applicant.profile')" class="underline font-semibold">complete your academic info</a> in Profile to enable this.
                    </span>
                  </div>
                </template>
              </div>
              <!-- Error toasts -->
              <Transition name="slide-down">
                <div v-if="f137DownloadError" class="w-full mt-3 p-3 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 rounded-lg flex items-center gap-2 text-sm text-red-700 dark:text-red-300">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                  </svg>
                  {{ f137DownloadError }}
                </div>
              </Transition>
            </div>

          </aside>

          <!-- ── Main Content ── -->
          <div class="min-w-0">
            <div class="grid grid-cols-1 gap-6">

          <!-- Certificate of Registration Card (shown only after medical stage is completed) -->
          <div v-if="showCorUpload" class="col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-blue-200 dark:border-blue-800 p-5">
              <div class="flex items-center justify-between mb-1 flex-wrap gap-3">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                  <svg class="w-5 h-5 text-blue-500 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7 20H5a2 2 0 01-2-2V6a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2h-2M9 12l2 2 4-4" />
                  </svg>
                  Certificate of Registration (COR)
                </h3>

              </div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                Upload the front and back of your Certificate of Registration (COR). This is required after you have been cleared for enrollment.
              </p>

              <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                <div v-for="key in COR_KEYS.filter(k => fileStatuses[k] !== undefined)" :key="key" class="group">
                  <div class="bg-blue-50/60 dark:bg-blue-900/10 rounded-lg p-3 border border-blue-200 dark:border-blue-800 hover:border-blue-400 dark:hover:border-blue-500 transition-all">
                    <div class="relative mb-2">
                      <div v-if="fileStatuses[key]?.status === 'uploading' && activeUploadKey !== key" class="w-full h-20 bg-yellow-50 dark:bg-yellow-900/20 rounded-md flex flex-col items-center justify-center border border-yellow-200 dark:border-yellow-700">
                        <svg class="animate-spin h-6 w-6 text-yellow-600 dark:text-yellow-400 mb-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-[10px] text-yellow-700 dark:text-yellow-300 font-medium">Processing...</span>
                      </div>
                      <div v-else-if="fileStatuses[key]?.status === 'failed'" class="w-full h-20 bg-red-50 dark:bg-red-900/20 rounded-md flex flex-col items-center justify-center border border-red-200 dark:border-red-700">
                        <svg class="w-6 h-6 text-red-500 dark:text-red-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <span class="text-[10px] text-red-600 dark:text-red-300 font-medium">Upload Failed</span>
                      </div>
                      <div v-else-if="hasImagePreview(fileStatuses[key])" class="relative cursor-pointer" @click="openImageModal(fileStatuses[key])">
                        <img :src="getFileUrl(fileStatuses[key])" :alt="formatKey(key)" class="w-full h-20 object-cover rounded-md" />
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 rounded-md transition-all pointer-events-none"></div>
                      </div>
                      <div v-else class="w-full h-20 bg-blue-100 dark:bg-blue-900/20 rounded-md flex items-center justify-center">
                        <svg class="w-8 h-8 text-blue-300 dark:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                      </div>
                      <div v-if="['rejected', 'returned'].includes((fileStatuses[key]?.status || '').toLowerCase())" class="absolute -top-1 -right-1">
                        <span class="px-1.5 py-0.5 rounded-full text-xs text-white bg-red-600">
                          Rejected
                        </span>
                      </div>
                    </div>

                    <p class="text-xs text-center text-gray-700 dark:text-gray-300 font-medium mb-2 leading-tight break-words" :title="formatKey(key)">
                      {{ formatKey(key) }}
                    </p>

                    <p v-if="fileStatuses[key]?.status === 'returned' && fileStatuses[key]?.comment" class="text-[10px] text-red-600 dark:text-red-400 mb-2 text-center italic">
                      {{ fileStatuses[key].comment }}
                    </p>

                    <div v-if="activeUploadKey !== key">
                      <button
                        v-if="!fileStatuses[key]?.url && fileStatuses[key]?.status !== 'uploading'"
                        @click.prevent="handleOpenUpload(key)"
                        :disabled="activeUploadUploading"
                        class="w-full py-1 text-xs bg-blue-600 hover:bg-blue-700 text-white rounded transition-colors min-h-[44px] disabled:opacity-70"
                      >Upload</button>
                      <button
                        v-else-if="fileStatuses[key]?.status === 'failed'"
                        @click.prevent="handleOpenUpload(key)"
                        :disabled="activeUploadUploading"
                        class="w-full py-1 text-xs bg-red-600 hover:bg-red-700 text-white rounded transition-colors min-h-[44px] disabled:opacity-70"
                      >Retry Upload</button>
                      <button
                        v-else-if="fileStatuses[key]?.url"
                        @click.prevent="handleOpenUpload(key)"
                        :disabled="activeUploadUploading"
                        class="w-full py-1 text-xs bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded transition-colors min-h-[44px] disabled:opacity-70"
                      >Replace</button>
                      <input type="file" :id="'file-input-' + key" class="hidden"
                        accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.txt,image/*,application/*,text/*"
                        @change="reuploadFile($event, key)" />
                    </div>

                    <div v-else class="mt-3 rounded-2xl border border-gray-200 bg-gradient-to-br from-gray-50 via-white to-gray-100 p-3 shadow-sm dark:border-gray-700 dark:from-gray-950/70 dark:via-gray-900 dark:to-gray-950/80">
                      <div
                        class="group rounded-2xl border-2 border-dashed p-2 text-center transition-all duration-200"
                        :class="activeUploadDropActive ? 'border-blue-500 bg-blue-500/5 shadow-inner' : 'border-gray-300 bg-white/80 hover:border-blue-400 hover:bg-white dark:border-gray-700 dark:bg-gray-900/60 dark:hover:bg-gray-900/80'"
                        @click.stop="document.getElementById('inline-file-input-' + key)?.click()"
                        @dragenter.prevent.stop="onInlineDragEnter"
                        @dragover.prevent.stop="onInlineDragOver"
                        @dragleave.prevent.stop="onInlineDragLeave"
                        @drop.prevent.stop="onInlineDrop"
                      >
                        <div class="mx-auto flex h-8 w-8 items-center justify-center rounded-full bg-blue-500/10 text-blue-600 transition-transform group-hover:scale-105 dark:bg-white/10 dark:text-white">
                          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.902A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                          </svg>
                        </div>
                        <p class="mt-1 text-[10px] text-gray-500 dark:text-gray-400">Choose a file or drag and drop it here</p>
                        <input :id="'inline-file-input-' + key" type="file" class="hidden"
                          accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.txt,image/*,application/*,text/*"
                          @change="onInlineFileChange" />
                      </div>

                      <div v-if="activeUploadKey === key" class="mt-3 space-y-3">
                        <div v-if="activeUploadUploading" class="space-y-1">
                          <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
                            <span class="font-medium">Uploading...</span>
                            <span class="font-semibold text-blue-600">{{ activeUploadProgress }}%</span>
                          </div>
                          <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                            <div class="h-2 rounded-full bg-blue-500 transition-all duration-200" :style="{ width: activeUploadProgress + '%' }"></div>
                          </div>
                          <div v-if="activeUploadTotal > 0" class="text-[10px] text-gray-400 dark:text-gray-500 text-right">
                            {{ formatFileSize(activeUploadLoaded) }} / {{ formatFileSize(activeUploadTotal) }}
                          </div>
                        </div>
                        <div v-if="activeUploadSuccess && !activeUploadUploading" class="flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400 font-medium">
                          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                          </svg>
                          Upload complete!
                        </div>
                        <p v-if="activeUploadError && activeUploadKey === key" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-600 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                          {{ activeUploadError }}
                        </p>
                        <div class="flex gap-2">
                          <button
                            class="flex-1 rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                            :disabled="activeUploadUploading"
                            @click="closeInlineUpload"
                          >{{ activeUploadSuccess ? 'Done' : 'Cancel' }}</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Required Documents -->
          <div id="documents-section" class="col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
              <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                  <svg class="w-5 h-5 text-gray-500 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                  Required Documents
                </h3>
                <div class="flex items-center gap-2 flex-wrap">
                </div>
              </div>
               <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                 Upload files (images, PDF, Word, text). Maximum file size: 2MB.
               </p>
              
              <div v-if="loading && !stepKeys.length" class="text-center py-8">
                <div class="flex justify-center items-center space-x-2">
                  <svg class="animate-spin h-5 w-5 text-maroon-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span class="text-gray-600 dark:text-gray-400">Loading documents...</span>
                </div>
              </div>

              <div v-else-if="preSubmissionKeys.length" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                <div v-for="(key, idx) in preSubmissionKeys" :key="key" class="group">
                  <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600 hover:border-maroon-500 transition-all">
                    <!-- Document Icon/Preview -->
                    <div class="relative mb-2">
                      <!-- Show spinner overlay when backend reports uploading (e.g. from another tab/session) -->
                      <div v-if="fileStatuses[key]?.status === 'uploading' && activeUploadKey !== key" class="w-full h-20 bg-yellow-50 dark:bg-yellow-900/20 rounded-md flex flex-col items-center justify-center border border-yellow-200 dark:border-yellow-700">
                        <svg class="animate-spin h-6 w-6 text-yellow-600 dark:text-yellow-400 mb-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-[10px] text-yellow-700 dark:text-yellow-300 font-medium">Processing...</span>
                      </div>
                      <!-- Show failed state from backend -->
                      <div v-else-if="fileStatuses[key]?.status === 'failed'" class="w-full h-20 bg-red-50 dark:bg-red-900/20 rounded-md flex flex-col items-center justify-center border border-red-200 dark:border-red-700">
                        <svg class="w-6 h-6 text-red-500 dark:text-red-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <span class="text-[10px] text-red-600 dark:text-red-300 font-medium">Upload Failed</span>
                      </div>
                      <div v-else-if="hasImagePreview(fileStatuses[key])" class="relative cursor-pointer" @click="openImageModal(fileStatuses[key])">
                        <img
                          :src="getFileUrl(fileStatuses[key])"
                          :alt="formatKey(key)"
                          class="w-full h-20 object-cover rounded-md"
                        />
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 rounded-md transition-all pointer-events-none"></div>
                      </div>
                      <div v-else class="w-full h-20 bg-gray-200 dark:bg-gray-600 rounded-md flex items-center justify-center">
                        <svg class="w-8 h-8 text-gray-400 dark:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                      </div>

                      <!-- Status Badge -->
                      <div v-if="['rejected', 'returned'].includes((fileStatuses[key]?.status || '').toLowerCase())" class="absolute -top-1 -right-1">
                        <span class="px-1.5 py-0.5 rounded-full text-xs text-white bg-red-600">
                          Rejected
                        </span>
                      </div>
                    </div>

                    <!-- Document Name -->
                    <p class="text-xs text-center text-gray-700 dark:text-gray-300 font-medium mb-2 leading-tight break-words" :title="formatKey(key)">
                      {{ formatKey(key) }}
                    </p>

                    <!-- Returned comment from evaluator -->
                    <p v-if="fileStatuses[key]?.status === 'returned' && fileStatuses[key]?.comment" class="text-[10px] text-red-600 dark:text-red-400 mb-2 text-center italic">
                      {{ fileStatuses[key].comment }}
                    </p>

                    <!-- Upload / Replace button: clicking opens file picker and shows drag-drop area below -->
                    <div v-if="activeUploadKey !== key">
                      <button
                        v-if="!fileStatuses[key]?.url && fileStatuses[key]?.status !== 'uploading'"
                        @click.prevent="handleOpenUpload(key)"
                        :disabled="activeUploadUploading"
                        class="w-full py-1 text-xs bg-[#9E122C] hover:bg-[#7a0e22] text-white rounded transition-colors min-h-[44px] disabled:opacity-70"
                      >
                        Upload
                      </button>
                      <!-- Retry button for failed uploads -->
                      <button
                        v-else-if="fileStatuses[key]?.status === 'failed'"
                        @click.prevent="handleOpenUpload(key)"
                        :disabled="activeUploadUploading"
                        class="w-full py-1 text-xs bg-red-600 hover:bg-red-700 text-white rounded transition-colors min-h-[44px] disabled:opacity-70"
                      >
                        Retry Upload
                      </button>
                      <button
                        v-else-if="fileStatuses[key]?.url"
                        @click.prevent="handleOpenUpload(key)"
                        :disabled="activeUploadUploading"
                        class="w-full py-1 text-xs bg-gray-200 hover:bg-gray-300 dark:bg-gray-600 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-200 rounded transition-colors min-h-[44px] disabled:opacity-70"
                      >
                        Replace
                      </button>

                       <!-- Hidden File Input for file picker -->
                       <input
                         type="file"
                         :id="'file-input-' + key"
                         class="hidden"
                         accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.txt,image/*,application/*,text/*"
                         @change="reuploadFile($event, key)"
                       />
                    </div>

                    <!-- Drag and drop area appears below button when Upload/Replace is clicked -->
                    <div v-else class="mt-3 rounded-2xl border border-gray-200 bg-gradient-to-br from-gray-50 via-white to-gray-100 p-3 shadow-sm dark:border-gray-700 dark:from-gray-950/70 dark:via-gray-900 dark:to-gray-950/80">
                      <div
                        class="group rounded-2xl border-2 border-dashed p-2 text-center transition-all duration-200"
                        :class="activeUploadDropActive ? 'border-[#9E122C] bg-[#9E122C]/5 shadow-inner' : 'border-gray-300 bg-white/80 hover:border-[#9E122C]/70 hover:bg-white dark:border-gray-700 dark:bg-gray-900/60 dark:hover:bg-gray-900/80'"
                        @click.stop="document.getElementById('inline-file-input-' + key)?.click()"
                        @dragenter.prevent.stop="onInlineDragEnter"
                        @dragover.prevent.stop="onInlineDragOver"
                        @dragleave.prevent.stop="onInlineDragLeave"
                        @drop.prevent.stop="onInlineDrop"
                      >
                        <div class="mx-auto flex h-8 w-8 items-center justify-center rounded-full bg-[#9E122C]/10 text-[#9E122C] transition-transform group-hover:scale-105 dark:bg-white/10 dark:text-white">
                          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.902A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                          </svg>
                        </div>

                        <p class="mt-1 text-[10px] text-gray-500 dark:text-gray-400">
                          Choose a file or drag and drop it here
                        </p>

                         <input :id="'inline-file-input-' + key" type="file" class="hidden" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.txt,image/*,application/*,text/*" @change="onInlineFileChange" />
                      </div>

                      <div v-if="activeUploadKey === key" class="mt-3 space-y-3">
                        <!-- Upload Progress Bar -->
                        <div v-if="activeUploadUploading" class="space-y-1">
                          <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
                            <span class="font-medium">Uploading...</span>
                            <span class="font-semibold text-[#9E122C]">{{ activeUploadProgress }}%</span>
                          </div>
                          <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                            <div
                              class="h-2 rounded-full transition-all duration-200"
                              style="background-color: #9E122C;"
                              :style="{ width: activeUploadProgress + '%' }"
                            ></div>
                          </div>
                          <div v-if="activeUploadTotal > 0" class="text-[10px] text-gray-400 dark:text-gray-500 text-right">
                            {{ formatFileSize(activeUploadLoaded) }} / {{ formatFileSize(activeUploadTotal) }}
                          </div>
                        </div>

                        <!-- Success indicator -->
                        <div v-if="activeUploadSuccess && !activeUploadUploading" class="flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400 font-medium">
                          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                          </svg>
                          Upload complete!
                        </div>

                        <p v-if="activeUploadError && activeUploadKey === key" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-600 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                          {{ activeUploadError }}
                        </p>

                        <div class="flex gap-2">
                          <button
                            class="flex-1 rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                            :disabled="activeUploadUploading"
                            @click="closeInlineUpload"
                          >
                            {{ activeUploadSuccess ? 'Done' : 'Cancel' }}
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else class="text-center py-8">
                <p class="text-gray-500 dark:text-gray-400">No documents to display.</p>
              </div>
            </div>
          </div>
        </div>
          </div><!-- end main content -->
        </div><!-- end two-column layout grid -->

        <!-- Error Message -->
        <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <p class="text-red-600 dark:text-red-400 text-center">{{ error }}</p>
        </div>

        <!-- Modals -->
        <ApplicationReviewModal
          :show="showModal"
          :userEmail="props.user.email"
          @close="closeModal"
          @refreshDashboard="fetchData"
        />
      </div>
    </div>

    <!-- Image Preview Modal -->
    <div
      v-if="showImageModal"
      class="fixed inset-0 z-50 bg-black bg-opacity-90 flex items-center justify-center p-4 dark:bg-white"
      @click="closeImageModal"
    >
      <div class="relative max-w-[90vw] max-h-[90vh]">
        <img
          :src="previewSrc"
          alt="Preview"
          class="max-w-full max-h-full rounded-lg shadow-2xl"
          @click.stop
        />
        <button
          class="absolute top-2 right-2 text-white text-4xl hover:text-gray-300 transition-colors w-10 h-10 flex items-center justify-center rounded-full bg-black/50 hover:bg-black/70 dark:text-gray-900 min-h-[44px] min-w-[44px]"
          @click.stop="closeImageModal"
        >
          &times;
        </button>
      </div>
    </div>

  </ApplicantLayout>
</template>

<style scoped>
/* Custom colors */
.bg-maroon-600 { background-color: #800000; }
.bg-maroon-700 { background-color: #991b1b; }
.bg-maroon-800 { background-color: #660000; }
.hover\:bg-maroon-700:hover { background-color: #991b1b; }
.hover\:bg-maroon-800:hover { background-color: #660000; }
.hover\:border-maroon-500:hover { border-color: #800000; }

/* Transitions */
.transition-all {
  transition: all 0.2s ease-in-out;
}

/* Scrollbar hiding */
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

/* Thin scrollbar for the horizontal timeline */
.scrollbar-thin {
  scrollbar-width: thin;
  scrollbar-color: #d1d5db transparent;
}
.scrollbar-thin::-webkit-scrollbar {
  height: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: #d1d5db;
  border-radius: 999px;
}
:global(.dark) .scrollbar-thin {
  scrollbar-color: #4b5563 transparent;
}
:global(.dark) .scrollbar-thin::-webkit-scrollbar-thumb {
  background-color: #4b5563;
}

/* Loading animation */
@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.animate-spin {
  animation: spin 1s linear infinite;
}

/* Slide down animation for notification */
.slide-down-enter-active {
  transition: all 0.4s ease-out;
}

.slide-down-leave-active {
  transition: all 0.3s ease-in;
}

.slide-down-enter-from {
  transform: translateY(-20px);
  opacity: 0;
}

.slide-down-leave-to {
  transform: translateY(-10px);
  opacity: 0;
}

/* Modal fade animation */
.modal-fade-enter-active {
  transition: opacity 0.25s ease;
}
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>