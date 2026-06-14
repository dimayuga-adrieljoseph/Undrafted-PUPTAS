<script setup>
import { defineProps, ref, computed, onMounted } from "vue";
import { router } from "@inertiajs/vue3";
import { Head } from "@inertiajs/vue3";
import Compressor from "compressorjs";
const axios = window.axios;
import ApplicantLayout from "@/Layouts/ApplicantLayout.vue";
import ApplicationReviewModal from "@/Pages/Modal/ApplicationReviewModal.vue";

const props = defineProps({ user: Object, gradeUrl: String, canDownloadSlip: Boolean });

const showModal = ref(false);
const showSuccessNotification = ref(false);
const loading = ref(false);
const error = ref("");
const fileStatuses = ref({});
const applicationStatus = ref("");
const enrollmentStatus = ref("");
const applicationProcesses = ref([]);
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
const openFaqItems = ref([]);
const showFaqModal = ref(false);

// Grade Verification Slip download state
const downloadingSlip = ref(false);
const slipDownloadError = ref('');

const faqItems = [
  {
    question: "What happens after I submit my application? What is the next step?",
    answer: "Kindly wait for your SAR (Student Admission Record) Form, which will be sent to your registered email. The SAR Form will include your interview schedule and other important instructions regarding the admission process. Please follow the instructions indicated in the email carefully and prepare all required documents for your scheduled interview.",
  },
  {
    question: "Can we change our registered name (and other necessary information) due to errors made during the PUPCET application through PUP iApply?",
    answer: "Once you pass the interview and secure a confirmed slot in any of the programs, submit a notarized Affidavit of Discrepancy explaining the erroneous entry/application and proceed to the Office of the Campus Registrar to obtain the list of supporting documents required for the request to correct your name entry in the Student Information System (SIS).",
  },
  {
    question: "Our graduation comes after the date of the interview, so the Grade 12 report card is not yet available.",
    answer: null,
    answerItems: [
      "Submit the following requirements:",
      "Certification from your school principal/registrar (with school dry seal and authorized signatures) about the date of the graduation and that you belong to the graduating batch/class.",
      "Certificate of Grades (Grade 12) with school dry seal and printed name and signature of the school principal/registrar or any authorized school personnel.",
    ],
  },
  {
    question: "The portal does not reflect the programs that I am qualified for.",
    answer: "Please ensure that you uploaded the required initial documents in the portal and you encoded the complete Senior High School English, Mathematics, and Science subjects. If no program offering was reflected after making sure of this step, please contact us or visit our campus for further checking.",
  },
  {
    question: "Can I change my email address for future communications, announcements, etc.?",
    answer: "For your Identity Provider portal account, you may seek assistance from the Chat Support upon login. Once you are officially enrolled and already have a PUP SIS account, proceed to the Office of the Campus Registrar to obtain the list of supporting documents required for the request to update your information in the Student Information System (SIS).",
  },
  {
    question: "My high school registrar advised that PUP-Taguig must send a formal request for the issuance of my report cards.",
    answer: "PUP-Taguig Campus will only request the F137-A with \"Copy for Polytechnic University of the Philippines-Taguig Campus\" once the applicant is officially accepted/enrolled in our university. The applicant should write their high school a formal request letter of the F137 or other grade records (for evaluation purposes only) personally if needed. You may attach a copy of your PUPCET evaluation result and the list of admission requirements as proof.",
  },
  {
    question: "What if I just ordered my PSA-authenticated birth certificate online and it won't be delivered before the interview date?",
    answer: "PSA birth certificate delivery lead times depend on your location, taking 1-2 working days for processing plus the courier's transit time. Deliveries typically take next day delivery for Metro Manila addresses and 3-8 working days for provincial areas. Bring your receipt as proof that you have already requested for the document.",
  },
  {
    question: "Can I replace or re-upload documents if I uploaded the wrong file?",
    answer: "Yes. Applicants may replace or re-upload documents through the Document Upload section as long as the application review process is not yet completed.",
  },
  {
    question: "I did not receive my SAR (Student Admission Record) Form in my email. What should I do?",
    answer: "First, check your spam or junk folder. If it is still not found, verify that you used the correct registered email during application. If the issue continues, contact admissions support for verification and request assistance for re-sending your SAR Form.",
  },
  {
    question: "I accidentally encoded incorrect grades in my application. Can I still correct them?",
    answer: "If you have not yet submitted your application, you may still update or correct your encoded information by clicking \"Input Grades\" again. If the application has already been submitted and locked, you may seek assistance from Chat Support.",
  },
  {
    question: "How should I properly encode my grades in the system?",
    answer: "Enter grades exactly as they appear on your report card, including decimal grades if applicable. Accurate encoding is required for proper evaluation.",
  },
  {
    question: "What should I do if a subject is not available in the system?",
    answer: "If a required subject is not listed, click the \"Add Subject\" button and enter the closest equivalent subject based on your curriculum. Ensure that the subject entered accurately reflects your official record or its nearest equivalent.",
  },
  {
    question: "What should I do if I cannot upload my documents?",
    answer: "Check the file format and file size first to ensure they meet system requirements. You may also try refreshing the page, switching browsers, or using a different device if the issue persists.",
  },
];

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

const toggleFaq = (index) => {
  const pos = openFaqItems.value.indexOf(index);
  if (pos === -1) {
    openFaqItems.value.push(index);
  } else {
    openFaqItems.value.splice(pos, 1);
  }
};

/**
 * Download the Grade Verification Slip by triggering a direct browser download.
 * The route is authenticated — the server uses the session to identify the applicant.
 * No applicant ID or reference number is passed as a URL parameter to prevent
 * IDOR (Insecure Direct Object Reference) attacks.
 */
const downloadGradeVerificationSlip = async () => {
  downloadingSlip.value = true;
  slipDownloadError.value = '';

  try {
    const response = await axios.get('/applicant-dashboard/grade-verification-slip', {
      responseType: 'blob',
    });

    // Derive filename from Content-Disposition header if present, otherwise fall back
    let filename = 'Grade_Verification_Slip.pdf';
    const disposition = response.headers['content-disposition'];
    if (disposition) {
      const match = disposition.match(/filename="?([^";\n]+)"?/);
      if (match && match[1]) {
        filename = match[1].trim();
      }
    }

    // response.data is already a Blob (responseType: 'blob') — use directly
    const url = window.URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
  } catch (err) {
    const message = err.response?.data?.message
      || (err.response?.status === 403 ? 'Grade Verification Slip is not yet available. Please submit your application and complete your grade input first.' : null)
      || 'Failed to download the Grade Verification Slip. Please try again.';
    slipDownloadError.value = message;

    setTimeout(() => { slipDownloadError.value = ''; }, 6000);
  } finally {
    downloadingSlip.value = false;
  }
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
    <!-- FAQ button injected into the top-bar beside the dark mode toggle -->
    <template #header-actions>
      <button
        @click="showFaqModal = true"
        class="w-9 h-9 rounded-lg flex items-center justify-center bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 transition min-h-[44px] min-w-[44px]"
        title="Frequently Asked Questions"
        aria-label="Open FAQ"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-700 dark:text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </button>
    </template>

    <template #header>
      <h2 class="font-bold text-2xl text-gray-900 dark:text-gray-100">
        Applicant Dashboard
      </h2>
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
            <h1 class="text-xl md:text-2xl lg:text-3xl font-bold text-gray-900 dark:text-white break-words">Welcome back, {{ props.user?.firstname || 'Applicant' }}!</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Manage your application and track your progress</p>
          </div>
          
         </div>

        <!-- Guidance Office Alert -->
        <div v-if="requiresGuidanceOffice" class="bg-orange-50 dark:bg-orange-900/20 rounded-xl shadow-md border-l-4 border-orange-500 p-6">
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
        <div v-if="requiresAdmissionOffice" class="bg-blue-50 dark:bg-blue-900/20 rounded-xl shadow-md border-l-4 border-blue-500 p-6">
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
                Please proceed to the Admissions Office and you may resubmit your grades.
              </p>
            </div>
          </div>
        </div>

        <!-- Medical System Redirect Card -->
        <div v-if="showMedicalRedirect" class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-xl shadow-md border-2 border-green-300 dark:border-green-700 p-4 sm:p-6">
          <div class="flex flex-col sm:flex-row items-start gap-4">
            <div class="flex-shrink-0">
              <div class="w-14 h-14 rounded-full bg-green-600 flex items-center justify-center">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
            </div>
            <div class="flex-1">
              <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">
                Evaluation & Interview Complete!
              </h3>
              <p class="text-gray-700 dark:text-gray-300 mb-4">
                Congratulations! You've successfully completed the evaluation and interview stages. 
                Your next step is to create a Health Record in the Medical System.
              </p>
              <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-4 border border-green-200 dark:border-green-700">
                <p class="text-sm font-semibold text-gray-900 dark:text-white mb-2">📋 Instructions:</p>
                <ol class="text-sm text-gray-700 dark:text-gray-300 space-y-1 list-decimal list-inside">
                  <li>Click the button below to go to the Medical System</li>
                  <li>Log in with your credentials</li>
                  <li>Go to <strong>My Account</strong> dropdown menu</li>
                  <li>Click <strong>Health Record</strong></li>
                  <li>Click <strong>Complete Form Now</strong></li>
                  <li>Fill up the information sheet completely</li>
                  <li>When you are done filling up the sheets you may now proceed to the clinic for the medical assessment</li>
                </ol>
              </div>
              <a
                href="https://clinic-ms.inaebsit2027.com"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg shadow-md transition-all hover:shadow-lg font-medium min-h-[44px]"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                </svg>
                Go to Medical System
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
              </a>
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
              :disabled="downloadingSlip"
              class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white shadow-sm transition-all duration-200 min-h-[44px] disabled:opacity-50 disabled:cursor-not-allowed"
              style="background-color: #059669;"
              onmouseover="if(!disabled) this.style.backgroundColor='#047857'"
              onmouseout="this.style.backgroundColor='#059669'"
              title="Download your Grade Verification Slip"
            >
              <svg v-if="downloadingSlip" class="animate-spin h-5 w-5 text-white flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              {{ downloadingSlip ? 'Generating...' : 'Download Verification Slip' }}
            </button>
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
              'Download your <code class=\'download\'>Grade Verification Slip</code> and bring it along with your <code>SAR Form</code> on your interview day.'
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

    <!-- FAQ Modal -->
    <transition name="modal-fade">
      <div
        v-if="showFaqModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="faq-modal-title"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showFaqModal = false"></div>

        <!-- Modal Panel -->
        <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden mx-2 sm:mx-4">

          <!-- Header -->
          <div class="flex items-center justify-between px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-200 dark:border-gray-700 flex-shrink-0" style="background-color:#9E122C;">
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <div class="min-w-0">
                <h2 id="faq-modal-title" class="text-base sm:text-lg font-bold text-white leading-tight truncate">Frequently Asked Questions</h2>
                <p class="text-xs text-red-100 mt-0.5">Applicant Dashboard FAQs</p>
              </div>
            </div>
            <button
              @click="showFaqModal = false"
              class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 text-white transition-colors min-h-[44px] min-w-[44px]"
              aria-label="Close FAQ"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Scrollable Content -->
          <div class="overflow-y-auto flex-1 px-4 sm:px-6 py-5 space-y-2 scrollbar-hide">

            <!-- FAQ Items -->
            <div
              v-for="(faq, index) in faqItems"
              :key="index"
              class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden transition-all"
            >
              <button
                type="button"
                class="w-full flex items-center justify-between gap-3 px-4 py-3.5 text-left bg-gray-50 dark:bg-gray-700/50 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#9E122C]"
                :aria-expanded="openFaqItems.includes(index)"
                @click="toggleFaq(index)"
              >
                <span class="text-sm font-medium text-gray-800 dark:text-gray-100 leading-snug pr-2">{{ faq.question }}</span>
                <svg
                  class="w-4 h-4 flex-shrink-0 text-gray-400 dark:text-gray-400 transition-transform duration-200"
                  :class="openFaqItems.includes(index) ? 'rotate-180' : ''"
                  fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>
              <transition
                enter-active-class="transition-all duration-200 ease-out"
                leave-active-class="transition-all duration-150 ease-in"
                enter-from-class="opacity-0 max-h-0"
                enter-to-class="opacity-100 max-h-96"
                leave-from-class="opacity-100 max-h-96"
                leave-to-class="opacity-0 max-h-0"
              >
                <div v-show="openFaqItems.includes(index)" class="px-4 py-3 bg-white dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700">
                  <p v-if="faq.answer" class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{{ faq.answer }}</p>
                  <div v-else-if="faq.answerItems" class="space-y-2">
                    <p v-for="(item, idx) in faq.answerItems" :key="idx" class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed" :class="{ 'font-medium': idx === 0, 'pl-4': idx > 0 }">
                      <span v-if="idx > 0" class="mr-2">•</span>{{ item }}
                    </p>
                  </div>
                </div>
              </transition>
            </div>

            <!-- Important Reminder -->
            <div class="mt-4 rounded-xl border-2 border-amber-300 dark:border-amber-600 bg-amber-50 dark:bg-amber-900/20 p-4">
              <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-amber-400 dark:bg-amber-600 flex items-center justify-center">
                  <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                  </svg>
                </div>
                <div>
                  <p class="text-sm font-bold text-amber-800 dark:text-amber-200 mb-1">Important Reminder</p>
                  <p class="text-sm text-amber-700 dark:text-amber-300 leading-relaxed">
                    Before clicking <strong>Submit Application</strong>, ensure that all information, program choices, grades, and uploaded documents are complete and correct. Once submitted, your application will be treated as final.
                  </p>
                </div>
              </div>
            </div>

          </div>

          <!-- Footer -->
          <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex-shrink-0">
            <div class="flex justify-end">
              <button
                @click="showFaqModal = false"
                class="px-6 py-2.5 text-white rounded-lg transition font-medium min-h-[44px]"
                style="background-color:#9E122C;"
                onmouseover="this.style.backgroundColor='#7a0e22'"
                onmouseout="this.style.backgroundColor='#9E122C'"
              >
                Close
              </button>
            </div>
          </div>

        </div>
      </div>
    </transition>

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