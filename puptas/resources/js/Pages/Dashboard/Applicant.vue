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

const canEditGrades = computed(() => {
    if (!['returned', 'rejected'].includes(applicationStatus.value)) return false;
    
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

// checks if all documents have been uploaded (i.e. all fileStatuses have a completed status with a url)
const allDocumentsUploaded = computed(() => {
  const values = Object.values(fileStatuses.value);
  return values.length > 0 && values.every(f => f?.url != null && f?.status !== 'uploading' && f?.status !== 'failed');
});

// (grades extraction removed) documents are simply uploaded

// File statuses come directly from the backend
const stepKeys = computed(() => Object.keys(fileStatuses.value));

const uploadedCount = computed(() => {
  return Object.values(fileStatuses.value).filter(f => f?.url && f?.status !== 'uploading' && f?.status !== 'failed').length;
});

const uploadProgressPercentage = computed(() => {
  if (!stepKeys.value.length) return 0;
  return (uploadedCount.value / stepKeys.value.length) * 100;
});

const formatKey = (key) => {
  const labels = {
    file10Front: "Grade 10 Report Card (Front)",
    file10: "Grade 10 Report Card (Back)",
    file11Front: "Grade 11 Report Card (Front)",
    file11: "Grade 11 Report Card (Back)",
    file12Front: "Grade 12 Report Card (Front)",
    file12: "Grade 12 Report Card (Back)",
  };

  return labels[key] || key.replace(/([A-Z])/g, " $1").replace(/^./, (s) => s.toUpperCase());
};
const formatStage = (stage) => {
    const map = {
        'evaluator': 'DE, GE',
        'interviewer': 'Interviewer',
        'medical': 'Medical',
        'record_staff': 'Record Staff'
    };
    return map[stage] || (stage ? stage.charAt(0).toUpperCase() + stage.slice(1).replace(/_/g, " ") : "");
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
    requiresPromissoryNote.value = data.requires_promissory_note || false;
    requiresGuidanceOffice.value = data.requires_guidance_office || false;
    requiresAdmissionOffice.value = data.requires_admission_office || false;
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

    <div class="py-4 sm:py-8">
      <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 space-y-6">
        
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

        <!-- Promissory Note Alert -->
        <div v-if="requiresPromissoryNote" class="bg-orange-50 dark:bg-orange-900/20 rounded-xl shadow-md border-l-4 border-orange-500 p-6">
          <div class="flex items-start gap-4">
            <div class="flex-shrink-0 mt-1">
              <svg class="w-8 h-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
              </svg>
            </div>
            <div class="flex-1">
              <h3 class="text-xl font-bold text-orange-900 dark:text-orange-100 mb-2">
                Action Required: Promissory Note
              </h3>
              <p class="text-orange-800 dark:text-orange-200">
                The evaluator has indicated that you need to submit a <strong>Promissory Note</strong>. 
                Please prepare this document as it is required to proceed with your enrollment process.
              </p>
            </div>
          </div>
        </div>

        <!-- Guidance Office Alert -->
        <div v-if="requiresGuidanceOffice" class="bg-orange-50 dark:bg-orange-900/20 rounded-xl shadow-md border-l-4 border-orange-500 p-6 mb-6">
          <div class="flex items-start gap-4">
            <div class="flex-shrink-0 mt-1">
              <svg class="w-8 h-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
              </svg>
            </div>
            <div class="flex-1">
              <h3 class="text-xl font-bold text-orange-900 dark:text-orange-100 mb-2">
                Action Required: Go to Guidance Office
              </h3>
              <p class="text-orange-800 dark:text-orange-200">
                The evaluator has indicated that you need to go to the <strong>Guidance Office</strong>. 
                Please proceed to the Guidance Office for further instructions regarding your application.
              </p>
            </div>
          </div>
        </div>

        <!-- Admissions Office Alert -->
        <div v-if="requiresAdmissionOffice" class="bg-blue-50 dark:bg-blue-900/20 rounded-xl shadow-md border-l-4 border-blue-500 p-6 mb-6">
          <div class="flex items-start gap-4">
            <div class="flex-shrink-0 mt-1">
              <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
              </svg>
            </div>
            <div class="flex-1">
              <h3 class="text-xl font-bold text-blue-900 dark:text-blue-100 mb-2">
                Action Required: Go to Admissions Office
              </h3>
              <p class="text-blue-800 dark:text-blue-200">
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

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
          <!-- Application Status Card -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Application Status</p>
                <div v-if="loading && !applicationStatus" class="mt-2 h-8 w-32 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                <p v-else class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                  {{ applicationStatus ? capitalize(applicationStatus) : 'Not Started' }}
                </p>
              </div>
              <div :class="`w-12 h-12 rounded-full flex items-center justify-center ${getStatusIconBg(applicationStatus)}`">
                <svg class="w-6 h-6 text-white dark:text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
              </div>
            </div>
          </div>

          <!-- Enrollment Status Card -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Enrollment Status</p>
                <div v-if="loading && !enrollmentStatus" class="mt-2 h-8 w-24 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                <p v-else class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                  {{ enrollmentStatus ? capitalize(enrollmentStatus.replace(/_/g, " ")) : 'Pending' }}
                </p>
              </div>
              <div :class="`w-12 h-12 rounded-full flex items-center justify-center ${getEnrollmentIconBg(enrollmentStatus)}`">
                <svg class="w-6 h-6 text-white dark:text-gray-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
              </div>
            </div>
          </div>

          <!-- Documents Progress Card -->
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Documents Uploaded</p>
                <div v-if="loading && !stepKeys.length" class="mt-2 h-8 w-16 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                <p v-else class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                  {{ uploadedCount }}/{{ stepKeys.length }}
                </p>
              </div>
              <div class="w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                </svg>
              </div>
            </div>
            <!-- Progress Bar -->
            <div class="mt-3">
              <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-300" :style="{ width: uploadProgressPercentage + '%' }"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
          <!-- Header -->
          <div class="flex items-center gap-2 mb-4">
            <svg class="w-4 h-4 flex-shrink-0" style="color:#9E122C" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Quick Actions</p>
          </div>

          <div v-if="loading && !applicationStatus && !stepKeys.length" class="flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:justify-center">
            <div class="h-11 w-full sm:w-48 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
            <div class="h-11 w-full sm:w-40 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
          </div>
          <div v-else class="flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:justify-center">
            <!-- Review Application Button -->
            <button
              @click="showModal = true"
              class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white shadow-sm transition-all duration-200 min-h-[44px]"
              style="background-color: #9E122C;"
              onmouseover="this.style.backgroundColor='#7a0e22'"
              onmouseout="this.style.backgroundColor='#9E122C'"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Review Application
            </button>

            <!-- Input Grades Button -->
            <button
              v-if="allDocumentsUploaded"
              @click="goToGrades"
              class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white shadow-sm transition-all duration-200 min-h-[44px]"
              style="background-color: #D97706;"
              onmouseover="this.style.backgroundColor='#b65f06'"
              onmouseout="this.style.backgroundColor='#D97706'"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              {{ applicationStatus && applicationStatus !== 'draft' ? (canEditGrades ? 'Edit Grades' : 'View Grades') : 'Input Grades' }}
            </button>

            <!-- Download Grade Verification Slip -->
            <button
              v-if="canDownloadSlipReactive"
              @click="downloadGradeVerificationSlip"
              class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white shadow-sm transition-all duration-200 min-h-[44px]"
              style="background-color: #059669;"
              onmouseover="this.style.backgroundColor='#047857'"
              onmouseout="this.style.backgroundColor='#059669'"
              title="Download your Grade Verification Slip"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              Download Verification Slip
            </button>

            <!-- Download F137 Request Letter — only shown at medical stage -->
            <template v-if="showMedicalRedirect">
              <button
                v-if="props.canDownloadF137"
                @click="downloadF137RequestLetter"
                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white shadow-sm transition-all duration-200 min-h-[44px]"
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
                class="w-full sm:w-auto inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm border border-dashed border-red-300 bg-red-50 dark:bg-red-950/20 text-red-700 dark:text-red-300 min-h-[44px]"
                title="Complete Former School Information in your Profile to unlock this"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19H19a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.27 16A2 2 0 005.07 19z" />
                </svg>
                <span class="text-xs">
                  F137 Request Letter — <a :href="route('applicant.profile')" class="underline font-semibold">edit your academic information</a> in your Profile to enable this.
                </span>
              </div>
            </template>
          </div>

          <!-- Slip download error toast -->
          <Transition name="slide-down">
            <div v-if="slipDownloadError" class="w-full mt-3 p-3 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 rounded-lg flex items-center gap-2 text-sm text-red-700 dark:text-red-300">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
              {{ slipDownloadError }}
            </div>
          </Transition>
          <!-- F137 download error toast -->
          <Transition name="slide-down">
            <div v-if="f137DownloadError" class="w-full mt-3 p-3 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 rounded-lg flex items-center gap-2 text-sm text-red-700 dark:text-red-300">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
              {{ f137DownloadError }}
            </div>
          </Transition>
        </div>

        <!-- Application Process -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 px-4 py-3">

          <!-- Header -->
          <div class="flex items-center gap-2 mb-4">
            <svg class="w-4 h-4 flex-shrink-0" style="color:#9E122C" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Application Process</p>
          </div>

          <!-- Timeline -->
          <div class="relative flex flex-col sm:flex-row items-start sm:justify-between gap-0">

            <!-- Spine: vertical on mobile, horizontal on desktop -->
            <div class="
              absolute z-0
              left-[13px] top-[14px] bottom-[14px] w-px sm:w-auto
              sm:left-[14px] sm:right-[14px] sm:top-[13px] sm:bottom-auto sm:h-px
              bg-gray-200 dark:bg-gray-700
            "></div>

            <template v-for="(step, i) in [
              'Upload Grade 10, 11 &amp; 12 documents',
              'Go to <code>Input Grades</code>, enter grades &amp; pick 3 programs',
              'Review entries, then click <code>Save Grades</code>',
              'Go to <code>Review Application</code> &amp; verify info',
              'Click <code class=\'submit\'>Submit Application</code>',
              'Download your <code class=\'download\'>Grade Verification Slip</code> and bring it along with your <code>SAR Form</code> on your interview day.',
              'Download your <code class=\'f137\'>F137 Request Letter</code> and submit it to your former school to request your copy of F137.'
            ]" :key="i">

              <div class="relative z-10 flex flex-row sm:flex-col items-start sm:items-center gap-3 sm:gap-2 flex-1 py-2 sm:py-0">

                <!-- Node -->
                <div
                  class="w-7 h-7 rounded-full flex items-center justify-center text-white text-[11px] font-medium flex-shrink-0"
                  style="background-color:#9E122C"
                >
                  <template v-if="i < 4">{{ i + 1 }}</template>
                  <template v-else-if="i === 4">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                  </template>
                  <template v-else>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1" />
                    </svg>
                  </template>
                </div>

                <!-- Label -->
                <p
                  class="
                    text-[11.5px] text-gray-500 dark:text-gray-400 leading-relaxed
                    text-left sm:text-center
                    px-0 sm:px-1
                    mt-0.5 sm:mt-0
                    [&_code]:font-mono [&_code]:text-[11px]
                    [&_code]:bg-gray-100 dark:[&_code]:bg-gray-700
                    [&_code]:border [&_code]:border-gray-200 dark:[&_code]:border-gray-600
                    [&_code]:rounded [&_code]:px-1 [&_code]:py-px
                    [&_code.submit]:bg-red-50 dark:[&_code.submit]:bg-red-950/40
                    [&_code.submit]:border-red-200 dark:[&_code.submit]:border-red-900
                    [&_code.submit]:text-[#9E122C]
                    [&_code.download]:bg-emerald-50 dark:[&_code.download]:bg-emerald-950/40
                    [&_code.download]:border-emerald-200 dark:[&_code.download]:border-emerald-900
                    [&_code.download]:text-emerald-700 dark:[&_code.download]:text-emerald-400
                    [&_code.f137]:bg-red-50 dark:[&_code.f137]:bg-red-950/40
                    [&_code.f137]:border-red-200 dark:[&_code.f137]:border-red-900
                    [&_code.f137]:text-[#9E122C] dark:[&_code.f137]:text-red-400
                  "
                  v-html="step"
                ></p>

              </div>
            </template>

          </div>

          <!-- Note -->
          <div class="flex items-start gap-1.5 mt-4 px-2.5 py-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
            <svg class="w-3.5 h-3.5 flex-shrink-0 mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z" />
            </svg>
            <p class="text-[12px] text-gray-500 dark:text-gray-400 leading-relaxed">
              Your application will only be processed after successful submission.
            </p>
          </div>

        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Left Column - Timeline -->
          <div class="lg:col-span-1">
            <div v-if="applicationProcesses.length" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-500 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Application Timeline
              </h3>
              <div class="space-y-4">
                <div v-for="(proc, idx) in applicationProcesses" :key="idx" class="flex gap-3">
                  <div class="relative">
                    <div :class="`w-8 h-8 rounded-full flex items-center justify-center text-white text-xs ${getBadgeClass(proc.action === 'rejected' ? 'rejected' : proc.status)}`">
                      {{ idx + 1 }}
                    </div>
                    <div v-if="idx < applicationProcesses.length - 1" class="absolute top-8 left-1/2 w-0.5 h-8 bg-gray-200 dark:bg-gray-700 -translate-x-1/2"></div>
                  </div>
                  <div class="flex-1 pb-4 min-w-0">
                    <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-200 flex items-center gap-2 flex-wrap">
                      {{ formatStage(proc.stage) }}
                      <span :class="`text-xs px-1.5 py-0.5 rounded-full text-white ${getBadgeClass(proc.action === 'rejected' ? 'rejected' : proc.status)}`">
                        {{ proc.action === 'rejected' ? 'Rejected' : capitalize(proc.status.replace(/_/g, ' ')) }}
                      </span>
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      {{ formatTimestamp(proc.created_at) }}
                    </p>
                    <div v-if="(proc.status === 'returned' || proc.action === 'rejected') && proc.reviewer_notes"
                         class="mt-1 p-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-xs text-red-700 dark:text-red-400 break-all whitespace-pre-wrap">
                      <span class="font-semibold">{{ proc.action === 'rejected' ? 'Reject reason:' : 'Return reason:' }} </span>{{ proc.reviewer_notes }}
                    </div>
                    <p v-else-if="proc.reviewer_notes" class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic break-all whitespace-pre-wrap">
                      {{ proc.reviewer_notes }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column - Documents Grid -->
          <div class="lg:col-span-2">
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

              <div v-else-if="stepKeys.length" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <div v-for="(key, idx) in stepKeys" :key="key" class="group">
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
                      <div class="absolute -top-1 -right-1">
                        <span :class="`px-1.5 py-0.5 rounded-full text-xs text-white ${getBadgeClass(fileStatuses[key]?.status)}`">
                          {{ getStatusShort(fileStatuses[key]?.status) }}
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
                        class="w-full py-1 text-xs bg-maroon-600 hover:bg-maroon-700 text-white rounded transition-colors dark:text-gray-900 min-h-[44px] disabled:opacity-70"
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