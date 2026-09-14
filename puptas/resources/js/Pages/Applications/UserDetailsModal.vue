<script setup>
const props = defineProps({
    selectedUser: {
        type: Object,
        default: null,
    },
    selectedUserFiles: {
        type: Object,
        default: () => ({}),
    },
    currentUser: {
        type: Object,
        default: null,
    },
    availablePrograms: {
        type: Array,
        default: () => [],
    },
    isChangingCourse: {
        type: Boolean,
        default: false,
    },
    courseChangeMessage: {
        type: String,
        default: "",
    },
    changeCourseSelectedId: {
        type: [String, Number],
        default: "",
    },
});

const emit = defineEmits([
    "close",
    "open-image",
    "change-course",
    "update:changeCourseSelectedId",
]);

const getStatusClass = (status) => {
    const s = (status || "").toLowerCase();
    if (s === "accepted") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "cleared_for_enrollment" || s === "officially_enrolled") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "submitted" || s === "pending") return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300";
    if (s === "rejected") return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300";
    if (s === "returned") return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300";
    return "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400";
};

const stageLabels = {
    document_evaluator: "Document Evaluation",
    grade_evaluator: "Grade Evaluation",
    interviewer: "For Interview",
    medical: "For Medical",
    records: "For Records",
    enrollment: "Enrolled",
};

const stageColorMap = {
    document_evaluator: "bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300",
    grade_evaluator: "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300",
    interviewer: "bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300",
    medical: "bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300",
    records: "bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300",
    enrollment: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300",
};

const getStageLabel = (stage) => stageLabels[stage] || null;

const getStatusLabel = (user) => {
    const label = getStageLabel(user?.stage);
    return label || (user?.status ? user.status.charAt(0).toUpperCase() + user.status.slice(1) : "Unknown");
};

const getStageClass = (user) => {
    if (user?.stage && stageColorMap[user.stage]) return stageColorMap[user.stage];
    return getStatusClass(user?.status);
};

const formatFileKey = (key) => {
    const map = {
        file10Front: "Grade 10 Report Front",
        file10: "Grade 10 Report Back",
        file11Front: "Grade 11 Report Front",
        file11: "Grade 11 Report Back",
        file12Front: "Grade 12 Report Front",
        file12: "Grade 12 Report Back",
        schoolId: "School ID",
        nonEnrollCert: "Certificate of Non-Enrollment",
        psa: "PSA Birth Certificate",
        goodMoral: "Good Moral Certificate",
        underOath: "Under Oath Document",
        photo2x2: "2x2 Photo",
    };
    return map[key] || key;
};

const getFileUrl = (file) => (typeof file === "string" ? file : file?.url || "");

const hasImagePreview = (file) =>
    Boolean(getFileUrl(file)) && (typeof file === "string" || file?.isImage !== false);

const capitalize = (str) =>
    typeof str === "string" ? str.charAt(0).toUpperCase() + str.slice(1) : "";

const formatDate = (date) => {
    const d = new Date(date);
    return d.toLocaleString();
};

const formatStage = (stage) => {
    if (!stage) return "Unknown Stage";
    return stage.split("_").map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(" ");
};

const formatGrade = (value) => {
    if (value === null || value === undefined) return "—";
    const num = parseFloat(value);
    return isNaN(num) ? "—" : num.toFixed(2);
};
</script>

<template>
    <transition name="fade">
        <div
            v-if="selectedUser"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 backdrop-blur-sm p-4 sm:p-6 overflow-y-auto"
            @click.self="emit('close')"
        >
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-7xl my-4 sm:my-8 flex flex-col max-h-[calc(100vh-2rem)] sm:max-h-[calc(100vh-4rem)] overflow-hidden">

                <!-- Modal Header -->
                <div class="flex items-center justify-between px-4 sm:px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-full bg-[#9E122C] text-white flex items-center justify-center text-lg font-bold shrink-0">
                            {{ (selectedUser.firstname || selectedUser.email || '?').charAt(0).toUpperCase() }}{{ (selectedUser.lastname || '').charAt(0).toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white truncate">
                                {{ [selectedUser.firstname, selectedUser.middlename, selectedUser.lastname].filter(Boolean).join(' ') }}
                            </h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                App #{{ selectedUser.application?.id || 'N/A' }} · {{ selectedUser.reference_number || 'No ref' }} · {{ selectedUser.email }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span :class="getStageClass(selectedUser)" class="hidden sm:inline px-3 py-1 rounded-full text-xs font-semibold">
                            {{ getStatusLabel(selectedUser) }}
                        </span>
                        <button
                            @click="emit('close')"
                            class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition min-h-[44px] min-w-[44px]"
                            aria-label="Close"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal Body: 2-column layout -->
                <div class="flex-1 overflow-hidden px-4 sm:px-6 py-5 flex flex-col">
                    <div class="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-12 gap-6">

                        <!-- Left Column: Info & Grades -->
                        <div class="lg:col-span-7 space-y-5 overflow-y-auto pr-2 pb-4 min-h-0">

                            <!-- Personal Info -->
                            <div>
                                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Personal Information</h4>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">School</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ selectedUser.school || '—' }}</p>
                                    </div>
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Strand</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedUser.strand || '—' }}</p>
                                    </div>
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Track</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedUser.track || '—' }}</p>
                                    </div>
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">G12 1st Sem</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ selectedUser.grades?.g12_first_sem ?? '—' }}</p>
                                    </div>
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">G12 2nd Sem</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ selectedUser.grades?.g12_second_sem ?? '—' }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Program Choices -->
                            <div>
                                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Program Choices</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div class="p-3 rounded-xl border border-[#9E122C]/30 bg-[#9E122C]/5 dark:bg-[#9E122C]/10">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="w-5 h-5 rounded-full bg-[#9E122C] text-white text-xs font-bold flex items-center justify-center flex-shrink-0">1</span>
                                            <p class="text-xs font-semibold text-[#9E122C] dark:text-red-400 uppercase tracking-wide">1st Choice</p>
                                        </div>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white leading-snug">{{ selectedUser.application?.program?.name || "—" }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ selectedUser.application?.program?.code || "" }} · {{ selectedUser.application?.program?.slots ?? 0 }} slots</p>
                                    </div>
                                    <div class="p-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50" :class="{ 'opacity-40': !selectedUser.application?.second_choice }">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="w-5 h-5 rounded-full bg-gray-400 dark:bg-gray-500 text-white text-xs font-bold flex items-center justify-center flex-shrink-0">2</span>
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">2nd Choice</p>
                                        </div>
                                        <template v-if="selectedUser.application?.second_choice">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white leading-snug">{{ selectedUser.application.second_choice.name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ selectedUser.application.second_choice.code }} · {{ selectedUser.application.second_choice.slots ?? 0 }} slots</p>
                                        </template>
                                        <p v-else class="text-sm text-gray-400 dark:text-gray-500">Not specified</p>
                                    </div>
                                    <div class="p-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50" :class="{ 'opacity-40': !selectedUser.application?.third_choice }">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="w-5 h-5 rounded-full bg-gray-400 dark:bg-gray-500 text-white text-xs font-bold flex items-center justify-center flex-shrink-0">3</span>
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">3rd Choice</p>
                                        </div>
                                        <template v-if="selectedUser.application?.third_choice">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white leading-snug">{{ selectedUser.application.third_choice.name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ selectedUser.application.third_choice.code }} · {{ selectedUser.application.third_choice.slots ?? 0 }} slots</p>
                                        </template>
                                        <p v-else class="text-sm text-gray-400 dark:text-gray-500">Not specified</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Academic Grades -->
                            <div v-if="selectedUser?.grades">
                                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Academic Grades</h4>
                                <div class="grid grid-cols-3 gap-3">
                                    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-center">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Mathematics</p>
                                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatGrade(selectedUser.grades?.mathematics) }}</p>
                                    </div>
                                    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-center">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Science</p>
                                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatGrade(selectedUser.grades?.science) }}</p>
                                    </div>
                                    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-center">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">English</p>
                                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatGrade(selectedUser.grades?.english) }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Application History -->
                            <div v-if="selectedUser?.application?.processes?.length">
                                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Application History</h4>
                                <div class="space-y-2">
                                    <div
                                        v-for="(process, index) in selectedUser.application.processes"
                                        :key="index"
                                        class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl"
                                    >
                                        <div :class="[
                                            'w-2.5 h-2.5 rounded-full mt-1.5 shrink-0',
                                            process.action === 'rejected' ? 'bg-red-500' :
                                            process.status === 'completed' ? 'bg-green-500' :
                                            process.status === 'returned' ? 'bg-red-500' : 'bg-yellow-500'
                                        ]"></div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex justify-between items-start">
                                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ formatStage(process.stage) }}</p>
                                                <span :class="[
                                                    'px-2 py-0.5 rounded-full text-xs font-medium',
                                                    process.action === 'rejected' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' :
                                                    process.status === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' :
                                                    process.status === 'returned' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300' :
                                                    'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300'
                                                ]">{{ process.action === 'rejected' ? 'Rejected' : capitalize(process.status) }}</span>
                                            </div>
                                            <p v-if="process.reviewer_notes" class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic">{{ process.reviewer_notes }}</p>
                                            <p v-else-if="process.notes" class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic">{{ process.notes }}</p>
                                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ formatDate(process.created_at) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Actions & Documents -->
                        <div class="lg:col-span-5 space-y-5 overflow-y-auto pr-2 pb-4 min-h-0">

                            <!-- Optional slot for caller-specific action panels (e.g. admin enrollment actions) -->
                            <slot name="top-actions" />

                            <!-- Change Course — only visible for admins, interviewers, superadmins, and officially enrolled applicants -->
                            <div
                                v-if="(currentUser?.role_id === 2 || currentUser?.role_id === 4 || currentUser?.role_id === 7) && selectedUser?.application?.enrollment_status === 'officially_enrolled'"
                                class="p-4 border border-yellow-300 rounded-xl bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-700"
                            >
                                <h4 class="text-xs font-semibold text-yellow-800 dark:text-yellow-200 uppercase tracking-wide mb-2">⚠️ Change Course</h4>
                                <p class="text-xs text-yellow-700 dark:text-yellow-300 mb-3">
                                    Changing the course of an enrolled applicant will be logged in the audit trail.
                                </p>
                                <div v-if="courseChangeMessage" class="mb-3 px-3 py-2 text-sm rounded bg-white text-gray-800 border dark:bg-gray-800 dark:text-gray-300">
                                    {{ courseChangeMessage }}
                                </div>
                                <select
                                    :value="changeCourseSelectedId"
                                    @change="emit('update:changeCourseSelectedId', $event.target.value)"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-yellow-500 focus:border-transparent mb-3"
                                >
                                    <option value="" disabled>Select new program…</option>
                                    <option
                                        v-for="prog in availablePrograms"
                                        :key="prog.id"
                                        :value="prog.id"
                                        :disabled="prog.id === selectedUser?.application?.program?.id"
                                    >
                                        {{ prog.code }} - {{ prog.name }} [Slots: {{ prog.slots }}]
                                        <template v-if="prog.id === selectedUser?.application?.program?.id"> (current)</template>
                                    </option>
                                </select>
                                <button
                                    @click="emit('change-course')"
                                    :disabled="!changeCourseSelectedId || changeCourseSelectedId === selectedUser?.application?.program?.id || isChangingCourse"
                                    class="w-full px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 disabled:opacity-50 disabled:cursor-not-allowed transition text-sm font-medium"
                                >
                                    <span v-if="isChangingCourse">Applying…</span>
                                    <span v-else>Apply Changes</span>
                                </button>
                            </div>

                            <!-- Uploaded Documents -->
                            <div>
                                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Uploaded Documents</h4>
                                <div class="grid grid-cols-2 gap-3">
                                    <div
                                        v-for="(file, key) in selectedUserFiles"
                                        :key="key"
                                        class="p-2 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden"
                                    >
                                        <div class="flex items-center gap-1.5 mb-1.5 min-w-0">
                                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate">{{ formatFileKey(key) }}</span>
                                        </div>
                                        <img
                                            v-if="hasImagePreview(file)"
                                            :src="getFileUrl(file)"
                                            alt="Document"
                                            class="w-full aspect-[4/3] object-cover rounded-lg cursor-pointer hover:opacity-80 transition"
                                            @click="emit('open-image', file)"
                                        />
                                        <div v-else class="w-full aspect-[4/3] flex items-center justify-center text-xs text-gray-400 dark:text-gray-500 bg-gray-200 dark:bg-gray-700 rounded-lg">
                                            No file
                                        </div>
                                    </div>
                                    <div v-if="!Object.keys(selectedUserFiles).length" class="col-span-2 text-center text-xs text-gray-400 dark:text-gray-500 py-4">
                                        No documents uploaded.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
