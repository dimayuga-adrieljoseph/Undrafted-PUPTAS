<template>
    <Head title="ABM Grade Input" />
    <ApplicantLayout>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header Section -->
            <div class="mb-8">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">ABM Strand Grade Input</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">
                    Enter your academic grades for Grade 11 and Grade 12 to determine program eligibility
                </p>
            </div>

            <!-- Progress Steps -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div class="flex flex-wrap items-center w-full">
                        <div class="flex items-center relative">
                            <div class="w-8 h-8 bg-[#9E122C] text-white rounded-full flex items-center justify-center font-semibold text-sm">1</div>
                            <div class="ml-2 text-sm font-medium text-gray-900 dark:text-white">Grade 11</div>
                        </div>
                        <div class="flex-1 h-0.5 mx-4 bg-[#9E122C]"></div>
                        <div class="flex items-center relative">
                            <div class="w-8 h-8 bg-[#9E122C] text-white rounded-full flex items-center justify-center font-semibold text-sm">2</div>
                            <div class="ml-2 text-sm font-medium text-gray-900 dark:text-white">Grade 12</div>
                        </div>
                        <div class="flex-1 h-0.5 mx-4 bg-gray-300 dark:bg-gray-600"></div>
                        <div class="flex items-center relative">
                            <div class="w-8 h-8 bg-gray-300 dark:bg-gray-600 text-gray-600 dark:text-gray-400 rounded-full flex items-center justify-center font-semibold text-sm">3</div>
                            <div class="ml-2 text-sm font-medium text-gray-500 dark:text-gray-400">Program Selection</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lock Notice Banner -->
            <div v-if="isLocked" class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 rounded-lg flex items-center gap-3">
                <i class="fas fa-lock text-red-600 dark:text-red-400 text-lg flex-shrink-0"></i>
                <p class="text-sm text-red-700 dark:text-red-300 font-medium">
                    Grade submission is closed. Your application has been submitted and grades can no longer be modified.
                </p>
            </div>

            <!-- Server Validation Errors Summary -->
            <div v-if="Object.keys(errors).length > 0" class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-300 dark:border-red-700 rounded-lg">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-red-700 dark:text-red-300">Please fix the following errors:</p>
                        <ul class="mt-2 space-y-1">
                            <li v-for="(message, field) in errors" :key="field" class="text-sm text-red-600 dark:text-red-400">
                                • {{ Array.isArray(message) ? message[0] : message }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Success Toast Notification - Fixed Position -->
            <Transition name="slide-down">
                <div v-if="successMessage" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 w-full max-w-md px-4">
                    <div class="p-4 bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 dark:border-green-400 rounded-lg shadow-2xl flex items-center gap-3">
                        <div class="flex-shrink-0">
                            <svg class="w-6 h-6 text-green-500 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-green-800 dark:text-green-200">Success!</p>
                            <p class="text-sm text-green-700 dark:text-green-300">{{ successMessage }}</p>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-green-500 dark:border-green-400"></div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Error/Warning Toast Notification - Fixed Position -->
            <Transition name="slide-down">
                <div v-if="toastVisible" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 w-full max-w-md px-4">
                    <div :class="[
                        'p-4 rounded-lg shadow-2xl flex items-start gap-3 border-l-4',
                        toastType === 'error' ? 'bg-red-50 dark:bg-red-900/20 border-red-500 dark:border-red-400' : 'bg-amber-50 dark:bg-amber-900/20 border-amber-500 dark:border-amber-400'
                    ]">
                        <div class="flex-shrink-0 mt-0.5">
                            <svg v-if="toastType === 'error'" class="w-5 h-5 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <svg v-else class="w-5 h-5 text-amber-500 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p :class="['text-sm font-medium', toastType === 'error' ? 'text-red-800 dark:text-red-200' : 'text-amber-800 dark:text-amber-200']">
                                {{ toastMessage }}
                            </p>
                            <button
                                v-if="showRetryOption"
                                @click="retrySubmit"
                                :class="['mt-2 text-sm font-semibold underline', toastType === 'error' ? 'text-red-700 dark:text-red-300 hover:text-red-900 dark:hover:text-red-100' : 'text-amber-700 dark:text-amber-300 hover:text-amber-900 dark:hover:text-amber-100']"
                            >
                                Retry
                            </button>
                        </div>
                        <button @click="dismissToast" class="flex-shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </Transition>

            <form @submit.prevent="openReviewModal">
                <!-- Docling Autofill Banner -->
                <div v-if="extractionResult && !bannerDismissed" class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-file-alt text-blue-600 dark:text-blue-400"></i>
                        <p class="text-sm text-blue-700 dark:text-blue-300">
                            Grades have been autofilled from your uploaded documents using Docling. Please review and verify before submitting.
                        </p>
                    </div>
                    <button @click="bannerDismissed = true" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 text-xl leading-none flex-shrink-0">&times;</button>
                </div>
                <!-- Core Subjects Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden mb-8">
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Core Subjects</h2>
                    </div>

                    <div class="p-6">
                        <!-- Math Sub-section -->
                        <div class="mb-8">
                            <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                <span class="w-1 h-5 bg-[#9E122C] rounded-full mr-2 dark:bg-gray-900"></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#9E122C] text-white mr-2">G11</span>
                                Math-Related Subjects
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">General Mathematics</label>
                                    <input
                                        v-model.number="form.g11_general_mathematics"
                                        type="number"
                                        @keydown="preventInvalidInput"
                                        @input="validateGrade"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        class="w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]"
                                        placeholder="Enter grade (0-100)"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Business Mathematics</label>
                                    <input
                                        v-model.number="form.g11_business_mathematics"
                                        type="number"
                                        @keydown="preventInvalidInput"
                                        @input="validateGrade"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        class="w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]"
                                        placeholder="Enter grade (0-100)"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Statistics and Probability</label>
                                    <input
                                        v-model.number="form.g11_statistics_probability"
                                        type="number"
                                        @keydown="preventInvalidInput"
                                        @input="validateGrade"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        class="w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]"
                                        placeholder="Enter grade (0-100)"
                                    />
                                </div>
                            </div>

                            <!-- Dynamic Math Subjects -->
                            <div v-if="dynamicSubjects.math.length > 0" class="mt-4 space-y-3">
                                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">Additional Math Subjects</h4>
                                <DynamicSubjectRow
                                    v-for="subject in dynamicSubjects.math"
                                    :key="subject.id"
                                    :subject="subject"
                                    :disabled="isLocked"
                                    category="math"
                                    @update:name="subject.name = $event"
                                    @update:grade="subject.grade = $event"
                                    @remove="removeSubject('math', subject.id)"
                                />
                            </div>

                            <!-- Add Math Subject Button -->
                            <div class="mt-3">
                                <button
                                    v-if="canAddSubject('math')"
                                    type="button"
                                    :disabled="isLocked"
                                    @click="addSubject('math')"
                                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-[#9E122C] bg-red-50 dark:bg-red-900/20 border border-[#9E122C]/30 dark:border-red-700/30 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/40 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Subject
                                </button>
                                <p v-else class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                    <i class="fas fa-info-circle mr-1"></i>Maximum of 5 additional math subjects reached.
                                </p>
                            </div>

                            <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg inline-block">
                                <p class="text-sm font-medium text-blue-700 dark:text-blue-300">
                                    Math Average: <span class="font-bold">{{ mathAverage || "—" }}</span>
                                    <span class="text-xs ml-2">({{ mathCount }} subject{{ mathCount !== 1 ? 's' : '' }})</span>
                                </p>
                            </div>
                        </div>

                        <!-- English Sub-section -->
                        <div class="mb-8">
                            <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                <span class="w-1 h-5 bg-[#9E122C] rounded-full mr-2 dark:bg-gray-900"></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#9E122C] text-white mr-2">G11</span>
                                English-Related Subjects
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Oral Communication</label>
                                    <input
                                        v-model.number="form.g11_oral_communication"
                                        type="number"
                                        @keydown="preventInvalidInput"
                                        @input="validateGrade"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        class="w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]"
                                        placeholder="Enter grade (0-100)"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">English for Academic Purposes</label>
                                    <input
                                        v-model.number="form.g11_academic_professional"
                                        type="number"
                                        @keydown="preventInvalidInput"
                                        @input="validateGrade"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        class="w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]"
                                        placeholder="Enter grade (0-100)"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reading and Writing</label>
                                    <input
                                        v-model.number="form.g11_reading_writing"
                                        type="number"
                                        @keydown="preventInvalidInput"
                                        @input="validateGrade"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        class="w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]"
                                        placeholder="Enter grade (0-100)"
                                    />
                                </div>
                            </div>

                            <!-- G12 English -->
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 mt-6 flex items-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#9E122C] text-white mr-2">G12</span>
                                Grade 12
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">21st Century Literature</label>
                                    <input
                                        v-model.number="form.g12_21st_century_lit"
                                        type="number"
                                        @keydown="preventInvalidInput"
                                        @input="validateGrade"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        class="w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]"
                                        placeholder="Enter grade (0-100)"
                                    />
                                </div>
                            </div>

                            <!-- Dynamic English Subjects -->
                            <div v-if="dynamicSubjects.english.length > 0" class="mt-4 space-y-3">
                                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">Additional English Subjects</h4>
                                <DynamicSubjectRow
                                    v-for="subject in dynamicSubjects.english"
                                    :key="subject.id"
                                    :subject="subject"
                                    :disabled="isLocked"
                                    category="english"
                                    @update:name="subject.name = $event"
                                    @update:grade="subject.grade = $event"
                                    @remove="removeSubject('english', subject.id)"
                                />
                            </div>

                            <!-- Add English Subject Button -->
                            <div class="mt-3">
                                <button
                                    v-if="canAddSubject('english')"
                                    type="button"
                                    :disabled="isLocked"
                                    @click="addSubject('english')"
                                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-[#9E122C] bg-red-50 dark:bg-red-900/20 border border-[#9E122C]/30 dark:border-red-700/30 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/40 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Subject
                                </button>
                                <p v-else class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                    <i class="fas fa-info-circle mr-1"></i>Maximum of 5 additional English subjects reached.
                                </p>
                            </div>

                            <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg inline-block">
                                <p class="text-sm font-medium text-blue-700 dark:text-blue-300">
                                    English Average: <span class="font-bold">{{ englishAverage || "—" }}</span>
                                    <span class="text-xs ml-2">({{ englishCount }} subject{{ englishCount !== 1 ? 's' : '' }})</span>
                                </p>
                            </div>
                        </div>

                        <!-- Science Sub-section -->
                        <div class="mb-4">
                            <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                <span class="w-1 h-5 bg-[#9E122C] rounded-full mr-2 dark:bg-gray-900"></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#9E122C] text-white mr-2">G11</span>
                                Science-Related Subjects
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Earth and Life Science</label>
                                    <input
                                        v-model.number="form.g11_earth_life_science"
                                        type="number"
                                        @keydown="preventInvalidInput"
                                        @input="validateGrade"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        class="w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]"
                                        placeholder="Enter grade (0-100)"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Physical Science</label>
                                    <input
                                        v-model.number="form.g11_physical_science"
                                        type="number"
                                        @keydown="preventInvalidInput"
                                        @input="validateGrade"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        class="w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]"
                                        placeholder="Enter grade (0-100)"
                                    />
                                </div>
                            </div>

                            <!-- Dynamic Science Subjects -->
                            <div v-if="dynamicSubjects.science.length > 0" class="mt-4 space-y-3">
                                <h4 class="text-sm font-medium text-gray-600 dark:text-gray-400">Additional Science Subjects</h4>
                                <DynamicSubjectRow
                                    v-for="subject in dynamicSubjects.science"
                                    :key="subject.id"
                                    :subject="subject"
                                    :disabled="isLocked"
                                    category="science"
                                    @update:name="subject.name = $event"
                                    @update:grade="subject.grade = $event"
                                    @remove="removeSubject('science', subject.id)"
                                />
                            </div>

                            <!-- Add Science Subject Button -->
                            <div class="mt-3">
                                <button
                                    v-if="canAddSubject('science')"
                                    type="button"
                                    :disabled="isLocked"
                                    @click="addSubject('science')"
                                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-[#9E122C] bg-red-50 dark:bg-red-900/20 border border-[#9E122C]/30 dark:border-red-700/30 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/40 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Subject
                                </button>
                                <p v-else class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                    <i class="fas fa-info-circle mr-1"></i>Maximum of 5 additional science subjects reached.
                                </p>
                            </div>

                            <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg inline-block">
                                <p class="text-sm font-medium text-blue-700 dark:text-blue-300">
                                    Science Average: <span class="font-bold">{{ scienceAverage || "—" }}</span>
                                    <span class="text-xs ml-2">({{ scienceCount }} subject{{ scienceCount !== 1 ? 's' : '' }})</span>
                                </p>
                            </div>
                        </div>

                        <!-- Grade 12 GWA -->
                        <div class="mb-4">
                            <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                <span class="w-1 h-5 bg-[#9E122C] rounded-full mr-2 dark:bg-gray-900"></span>
                                Grade 12 GWA
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">1st Semester</label>
                                    <input
                                        v-model.number="form.g12_first_sem_gwa"
                                        type="number"
                                        @keydown="preventInvalidInput"
                                        @input="validateGrade"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        class="w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]"
                                        placeholder="0-100"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">2nd Semester</label>
                                    <input
                                        v-model.number="form.g12_second_sem_gwa"
                                        type="number"
                                        @keydown="preventInvalidInput"
                                        @input="validateGrade"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        class="w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]"
                                        placeholder="0-100"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Grade 12 GWA Display -->
                        <div class="mt-4 p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                            <p class="text-sm font-medium text-purple-700 dark:text-purple-300">
                                Grade 12 GWA: <span class="text-2xl font-bold">{{ g12GWA || "—" }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Grade Summary Cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-lg border border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Math Average</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ mathAverage || "—" }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-lg border border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">English Average</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ englishAverage || "—" }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-lg border border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Science Average</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ scienceAverage || "—" }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-lg border border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Grade 12 GWA</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ g12GWA || "—" }}</p>
                    </div>
                </div>

                <!-- Program Qualification Section -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden mb-8">
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Program Qualification</h2>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Qualified Programs -->
                            <div>
                                <h3 class="text-md font-semibold text-green-600 dark:text-green-400 mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Qualified Programs
                                </h3>
                                <div class="space-y-3">
                                    <div v-if="qualifiedPrograms.length > 0">
                                        <div
                                            v-for="program in qualifiedPrograms"
                                            :key="program.id"
                                            class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg"
                                        >
                                            <p class="font-semibold text-sm text-gray-900 dark:text-white">
                                                {{ program.code }} - {{ program.name }}
                                            </p>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                Requirements: Math {{ program.math }}, English {{ program.english }}, 
                                                Science {{ program.science }}, GWA {{ program.gwa }}
                                            </p>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                <span class="font-medium">Strand:</span> {{ program.strand_names || 'Open to All' }}
                                            </p>
                                        </div>
                                    </div>
                                    <p v-else class="text-sm text-gray-500 dark:text-gray-400 italic">
                                        No qualified programs based on current grades
                                    </p>
                                </div>
                            </div>

                            <!-- Not Qualified Programs -->
                            <div>
                                <h3 class="text-md font-semibold text-red-600 dark:text-red-400 mb-3 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Not Qualified
                                </h3>
                                <div class="space-y-3">
                                    <div v-if="notQualifiedPrograms.length > 0">
                                        <div
                                            v-for="program in notQualifiedPrograms"
                                            :key="program.id"
                                            class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg"
                                        >
                                            <p class="font-semibold text-sm text-gray-900 dark:text-white">
                                                {{ program.code }} - {{ program.name }}
                                            </p>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                Requirements: Math {{ program.math }}, English {{ program.english }}, 
                                                Science {{ program.science }}, GWA {{ program.gwa }}
                                            </p>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                <span class="font-medium">Strand:</span> {{ program.strand_names || 'Open to All' }}
                                            </p>
                                            <div v-if="program.unmetRequirements && program.unmetRequirements.length > 0" class="mt-2">
                                                <p class="text-xs font-medium text-red-600 dark:text-red-400">Unmet Requirements:</p>
                                                <ul class="list-disc list-inside mt-1">
                                                    <li v-for="(req, idx) in program.unmetRequirements" :key="idx" class="text-xs text-red-500 dark:text-red-400">
                                                        {{ req }}
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-else class="text-sm text-gray-500 dark:text-gray-400 italic">
                                        All programs are qualified
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Program Choice Selection -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden mb-8">
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Program Choices <span class="text-red-500 dark:text-red-300">*</span></h2>
                    </div>
                    
                    <div class="p-6">
                        <!-- Approval Notice -->
                        <div class="flex items-start gap-3 rounded-xl border-2 border-[#9E122C] bg-red-50 p-4 mb-6 dark:border-red-700 dark:bg-red-900/20">
                            <div class="flex-shrink-0 mt-0.5">
                                <svg class="h-5 w-5 text-[#9E122C] dark:text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                    <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836.042-.02a.75.75 0 01.67 1.34l-.04.022c-1.147.573-2.438-.463-2.127-1.706l.71-2.836-.042.02a.75.75 0 11-.671-1.34l.041-.022zM12 9a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-[#9E122C] dark:text-red-400">Important Notice</p>
                                <p class="mt-1 text-sm text-red-800 dark:text-red-300">
                                    This is subject to approval after the interview.
                                </p>
                            </div>
                        </div>
                        <!-- Program Choice Disabled Notice -->
                        <div v-if="programChoiceDisabled" class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg flex items-center gap-2">
                            <svg class="w-5 h-5 text-amber-500 dark:text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                            <p class="text-sm text-amber-700 dark:text-amber-300">
                                Program selection is disabled. Please enter valid grades in all categories and G12 GWA to enable program choices.
                            </p>
                        </div>
                        <div v-if="qualifiedPrograms.length > 0">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        First Choice Program <span class="text-red-500 dark:text-red-300">*</span>
                                    </label>
                                    <select
                                        v-model="form.first_choice_program"
                                        :disabled="programChoiceDisabled || isLocked"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
                                        required
                                    >
                                        <option value="">-- Select First Choice --</option>
                                        <option
                                            v-for="program in qualifiedPrograms"
                                            :key="program.id"
                                            :value="program.id"
                                            :disabled="program.id === form.second_choice_program || program.id === form.third_choice_program"
                                        >
                                            {{ program.code }} - {{ program.name }}
                                        </option>
                                    </select>
                                    <p v-if="errors.first_choice_program" class="text-red-500 text-xs mt-1 dark:text-red-300 break-words">
                                        {{ errors.first_choice_program }}
                                    </p>
                                </div>

                                <div v-if="qualifiedPrograms.length >= 2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Second Choice Program <span v-if="qualifiedPrograms.length >= 2" class="text-red-500 dark:text-red-300">*</span>
                                    </label>
                                    <select
                                        v-model="form.second_choice_program"
                                        :disabled="programChoiceDisabled || isLocked"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
                                        :required="qualifiedPrograms.length >= 2"
                                    >
                                        <option value="">-- Select Second Choice --</option>
                                        <option
                                            v-for="program in qualifiedPrograms"
                                            :key="program.id"
                                            :value="program.id"
                                            :disabled="program.id === form.first_choice_program || program.id === form.third_choice_program"
                                        >
                                            {{ program.code }} - {{ program.name }}
                                        </option>
                                    </select>
                                    <p v-if="errors.second_choice_program" class="text-red-500 text-xs mt-1 dark:text-red-300 break-words">
                                        {{ errors.second_choice_program }}
                                    </p>
                                </div>

                                <div v-if="qualifiedPrograms.length >= 3">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Third Choice Program <span v-if="qualifiedPrograms.length >= 3" class="text-red-500 dark:text-red-300">*</span>
                                    </label>
                                    <select
                                        v-model="form.third_choice_program"
                                        :disabled="programChoiceDisabled || isLocked"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
                                        :required="qualifiedPrograms.length >= 3"
                                    >
                                        <option value="">-- Select Third Choice --</option>
                                        <option
                                            v-for="program in qualifiedPrograms"
                                            :key="program.id"
                                            :value="program.id"
                                            :disabled="program.id === form.first_choice_program || program.id === form.second_choice_program"
                                        >
                                            {{ program.code }} - {{ program.name }}
                                        </option>
                                    </select>
                                    <p v-if="errors.third_choice_program" class="text-red-500 text-xs mt-1 dark:text-red-300 break-words">
                                        {{ errors.third_choice_program }}
                                    </p>
                                </div>
                            </div>

                            <!-- Selected Programs Display -->
                            <div v-if="form.first_choice_program || form.second_choice_program || form.third_choice_program" 
                                 class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                <p class="font-semibold text-sm text-gray-900 dark:text-white mb-2">Your Selected Programs:</p>
                                <p v-if="form.first_choice_program" class="text-sm text-gray-700 dark:text-gray-300">
                                    1st Choice: <strong>{{ getSelectedProgramName(form.first_choice_program) }}</strong>
                                </p>
                                <p v-if="form.second_choice_program" class="text-sm text-gray-700 dark:text-gray-300">
                                    2nd Choice: <strong>{{ getSelectedProgramName(form.second_choice_program) }}</strong>
                                </p>
                                <p v-if="form.third_choice_program" class="text-sm text-gray-700 dark:text-gray-300">
                                    3rd Choice: <strong>{{ getSelectedProgramName(form.third_choice_program) }}</strong>
                                </p>
                            </div>
                        </div>

                        <div v-else class="text-center p-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                            <svg class="w-12 h-12 text-red-400 mx-auto mb-3 dark:text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="font-semibold text-red-600 dark:text-red-400">⚠️ No Qualified Programs</p>
                            <p class="text-sm text-red-500 dark:text-red-400 mt-1">
                                You need to enter all your grades to see which programs you qualify for.
                            </p>
                        </div>

                        <p v-if="errors.programs" class="text-red-500 text-xs mt-2 dark:text-red-300 break-words">
                            {{ errors.programs }}
                        </p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex gap-4">
                    <button
                        v-if="!isLocked"
                        type="submit"
                        :disabled="loading"
                        class="flex-1 px-6 py-3 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center min-h-[44px]"
                    >
                        <svg v-if="loading" class="animate-spin h-5 w-5 mr-2 text-white dark:text-gray-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ loading ? "Saving..." : "Save Grades" }}
                    </button>
                    <button
                        type="button"
                        @click="$inertia.visit('/applicant-dashboard')"
                        class="flex-1 px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition font-medium min-h-[44px]"
                    >
                        Cancel
                    </button>
                </div>

                <!-- Success Message -->
                <transition name="fade">
                    <div
                        v-if="successMessage"
                        class="mt-4 p-4 bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 rounded-lg flex items-center"
                    >
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ successMessage }}
                    </div>
                </transition>
            </form>

            <GradesReviewModal
                :show="showReviewModal"
                :loading="loading"
                :form-data="reviewData"
                :sections="reviewSections"
                @close="closeReviewModal"
                @confirm="confirmSaveGrades"
            />
        </div>
    </ApplicantLayout>
</template>

<script setup>
import { ref, computed } from "vue";
import { Head } from "@inertiajs/vue3";
import ApplicantLayout from "@/Layouts/ApplicantLayout.vue";
import GradesReviewModal from "@/Components/GradesReviewModal.vue";
import DynamicSubjectRow from "@/Components/DynamicSubjectRow.vue";
import { useGradeForm } from "@/Composables/useGradeForm.js";

const props = defineProps({
    grade: Object,
    user: Object,
    programs: Array,
    strand: String,
    profile: Object,
    extractionResult: { type: Object, default: null },
    isLocked: { type: Boolean, default: false },
});

// ABM-specific default subjects
const defaultSubjects = {
    math: [
        'g11_general_mathematics',
        'g11_business_mathematics',
        'g11_statistics_probability',
    ],
    english: [
        'g11_oral_communication',
        'g11_academic_professional',
        'g11_reading_writing',
        'g12_21st_century_lit',
    ],
    science: [
        'g11_earth_life_science',
        'g11_physical_science',
    ],
};

// Use the composable for all grade form logic
const {
    form,
    dynamicSubjects,
    mathAverage,
    englishAverage,
    scienceAverage,
    g12GWA,
    mathCount,
    englishCount,
    scienceCount,
    addSubject,
    removeSubject,
    canAddSubject,
    qualifiedPrograms,
    notQualifiedPrograms,
    programChoiceDisabled,
    validateGrade,
    preventInvalidInput,
    errors,
    submitForm,
    retrySubmit,
    loading,
    toastMessage,
    toastType,
    toastVisible,
    showRetryOption,
    dismissToast,
} = useGradeForm({
    strand: props.strand || 'ABM',
    defaultSubjects,
    grade: props.grade,
    programs: props.programs,
    profile: props.profile,
    isLocked: props.isLocked,
});

const successMessage = ref("");
const bannerDismissed = ref(false);
const showReviewModal = ref(false);

// Helper function to get selected program name
const getSelectedProgramName = (programId) => {
    const program = props.programs?.find((p) => p.id === programId);
    return program ? `${program.code} - ${program.name}` : "";
};

// Computed property for review data
const reviewData = computed(() => ({
    g11_general_mathematics: form.g11_general_mathematics,
    g11_business_mathematics: form.g11_business_mathematics,
    g11_statistics_probability: form.g11_statistics_probability,
    g11_oral_communication: form.g11_oral_communication,
    g11_academic_professional: form.g11_academic_professional,
    g11_reading_writing: form.g11_reading_writing,
    g12_21st_century_lit: form.g12_21st_century_lit,
    g11_earth_life_science: form.g11_earth_life_science,
    g11_physical_science: form.g11_physical_science,
    g12_first_sem_gwa: form.g12_first_sem_gwa,
    g12_second_sem_gwa: form.g12_second_sem_gwa,
    first_choice_program: getSelectedProgramName(form.first_choice_program),
    second_choice_program: getSelectedProgramName(form.second_choice_program),
    third_choice_program: getSelectedProgramName(form.third_choice_program),
    math_average: mathAverage.value,
    english_average: englishAverage.value,
    science_average: scienceAverage.value,
    g12_gwa: g12GWA.value,
}));

// Computed property for review modal sections
const reviewSections = computed(() => [
    {
        title: 'Grade 11 Math Subjects',
        items: [
            { label: 'General Mathematics', value: reviewData.value.g11_general_mathematics },
            { label: 'Business Mathematics', value: reviewData.value.g11_business_mathematics },
            { label: 'Statistics and Probability', value: reviewData.value.g11_statistics_probability },
        ],
    },
    {
        title: 'English Subjects',
        items: [
            { label: 'Oral Communication', value: reviewData.value.g11_oral_communication },
            { label: 'English for Academic Purposes', value: reviewData.value.g11_academic_professional },
            { label: 'Reading and Writing', value: reviewData.value.g11_reading_writing },
            { label: '21st Century Literature', value: reviewData.value.g12_21st_century_lit },
        ],
    },
    {
        title: 'Science Subjects',
        items: [
            { label: 'Earth and Life Science', value: reviewData.value.g11_earth_life_science },
            { label: 'Physical Science', value: reviewData.value.g11_physical_science },
        ],
    },
    {
        title: 'Grade 12 GWA',
        items: [
            { label: '1st Semester', value: reviewData.value.g12_first_sem_gwa },
            { label: '2nd Semester', value: reviewData.value.g12_second_sem_gwa },
        ],
    },
    {
        title: 'Additional Subjects',
        items: [
            ...dynamicSubjects.value.math.filter(s => s.name && s.grade != null).map(s => ({ label: `Math: ${s.name}`, value: s.grade })),
            ...dynamicSubjects.value.english.filter(s => s.name && s.grade != null).map(s => ({ label: `English: ${s.name}`, value: s.grade })),
            ...dynamicSubjects.value.science.filter(s => s.name && s.grade != null).map(s => ({ label: `Science: ${s.name}`, value: s.grade })),
        ],
    },
    {
        title: 'Program Choices and Averages',
        items: [
            { label: 'First Choice Program *', value: reviewData.value.first_choice_program || '—' },
            { label: 'Second Choice Program *', value: reviewData.value.second_choice_program || '—' },
            { label: 'Third Choice Program *', value: reviewData.value.third_choice_program || '—' },
            { label: 'Math Average', value: reviewData.value.math_average },
            { label: 'English Average', value: reviewData.value.english_average },
            { label: 'Science Average', value: reviewData.value.science_average },
            { label: 'G12 GWA', value: reviewData.value.g12_gwa },
        ],
    },
]);

const openReviewModal = () => {
    errors.value = {};
    showReviewModal.value = true;
};

const closeReviewModal = () => {
    if (!loading.value) {
        showReviewModal.value = false;
    }
};

const confirmSaveGrades = () => {
    showReviewModal.value = false;
    submitForm();
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

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

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 5px;
}

::-webkit-scrollbar-track {
    background: #FBCB77;
    border-radius: 5px;
}

::-webkit-scrollbar-thumb {
    background: #9E122C;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: #EE6A43;
}
</style>
