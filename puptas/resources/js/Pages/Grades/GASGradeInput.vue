<template>
    <Head title="GAS Grade Input" />
    <ApplicantLayout>
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header Section -->
            <div class="mb-8">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">GAS Strand Grade Input</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">
                    Enter your academic grades for Grade 11 and Grade 12 to determine program eligibility
                </p>
            </div>

            <!-- Progress Steps -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div class="flex flex-wrap items-center w-full">
                        <div class="flex items-center relative">
                            <div class="w-8 h-8 bg-[#9E122C] text-white rounded-full flex items-center justify-center font-semibold text-sm dark:bg-gray-900 dark:text-gray-900">1</div>
                            <div class="ml-2 text-sm font-medium text-gray-900 dark:text-white">Grade 11</div>
                        </div>
                        <div class="flex-1 h-0.5 mx-4 bg-[#9E122C] dark:bg-gray-900"></div>
                        <div class="flex items-center relative">
                            <div class="w-8 h-8 bg-[#9E122C] text-white rounded-full flex items-center justify-center font-semibold text-sm dark:bg-gray-900 dark:text-gray-900">2</div>
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
                <!-- Manual Input Notice (shown when document extraction was unavailable) -->
                <div v-if="!extractionResult" class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg flex items-center gap-2">
                    <i class="fas fa-pencil-alt text-yellow-600 dark:text-yellow-400"></i>
                    <p class="text-sm text-yellow-700 dark:text-yellow-300">
                        Document extraction was unavailable. Please enter your grades manually below.
                    </p>
                </div>
                <!-- Core Subjects Card -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden mb-8">
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Core Subjects</h2>
                    </div>

                    <div class="p-6">
                        <!-- Math Sub-section -->
                        <div class="mb-8">
                            <!-- G11 Math Group -->
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
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        :class="['w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent', isLowConfidence('general mathematics') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]']"
                                        placeholder="Enter grade (0-100)"
                                    />
                                    <p v-if="isLowConfidence('general mathematics')" class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Low confidence result. Please verify.</p>
                                    <span v-if="getConfidence('general mathematics') !== null" class="text-xs text-gray-500 mt-1 block">Extraction confidence: {{ Math.round(getConfidence('general mathematics') * 100) }}%</span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Statistics and Probability</label>
                                    <input
                                        v-model.number="form.g11_statistics_probability"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        :class="['w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent', isLowConfidence('statistics and probability') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]']"
                                        placeholder="Enter grade (0-100)"
                                    />
                                    <p v-if="isLowConfidence('statistics and probability')" class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Low confidence result. Please verify.</p>
                                    <span v-if="getConfidence('statistics and probability') !== null" class="text-xs text-gray-500 mt-1 block">Extraction confidence: {{ Math.round(getConfidence('statistics and probability') * 100) }}%</span>
                                </div>
                            </div>
                            <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg inline-block">
                                <p class="text-sm font-medium text-blue-700 dark:text-blue-300">
                                    Math Average: <span class="font-bold">{{ mathAverage || "—" }}</span>
                                </p>
                            </div>

                            <!-- G12 Math Group -->
                            <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mt-6 mb-4 flex items-center">
                                <span class="w-1 h-5 bg-[#9E122C] rounded-full mr-2 dark:bg-gray-900"></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-500 text-white mr-2">G12</span>
                                Math Subjects (2 subjects)
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject 1</label>
                                    <input
                                        v-model="form.g12_math_subject_1"
                                        type="text"
                                        :disabled="isLocked"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent mb-3"
                                        placeholder="Subject name"
                                    />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grade</label>
                                    <input
                                        v-model.number="form.g12_math_grade_1"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        :class="['w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent', isLowConfidence(form.g12_math_subject_1?.toLowerCase()?.trim() || '') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]']"
                                        placeholder="0-100"
                                    />
                                    <p v-if="isLowConfidence(form.g12_math_subject_1?.toLowerCase()?.trim() || '')" class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Low confidence result. Please verify.</p>
                                    <span v-if="getConfidence(form.g12_math_subject_1?.toLowerCase()?.trim() || '') !== null" class="text-xs text-gray-500 mt-1 block">Extraction confidence: {{ Math.round(getConfidence(form.g12_math_subject_1?.toLowerCase()?.trim() || '') * 100) }}%</span>
                                </div>
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject 2</label>
                                    <input
                                        v-model="form.g12_math_subject_2"
                                        type="text"
                                        :disabled="isLocked"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent mb-3"
                                        placeholder="Subject name"
                                    />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grade</label>
                                    <input
                                        v-model.number="form.g12_math_grade_2"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        :class="['w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent', isLowConfidence(form.g12_math_subject_2?.toLowerCase()?.trim() || '') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]']"
                                        placeholder="0-100"
                                    />
                                    <p v-if="isLowConfidence(form.g12_math_subject_2?.toLowerCase()?.trim() || '')" class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Low confidence result. Please verify.</p>
                                    <span v-if="getConfidence(form.g12_math_subject_2?.toLowerCase()?.trim() || '') !== null" class="text-xs text-gray-500 mt-1 block">Extraction confidence: {{ Math.round(getConfidence(form.g12_math_subject_2?.toLowerCase()?.trim() || '') * 100) }}%</span>
                                </div>
                            </div>
                        </div>

                        <!-- English Sub-section -->
                        <div class="mb-8">
                            <!-- G11 English Group -->
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
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        :class="['w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent', isLowConfidence('oral communication') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]']"
                                        placeholder="Enter grade (0-100)"
                                    />
                                    <p v-if="isLowConfidence('oral communication')" class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Low confidence result. Please verify.</p>
                                    <span v-if="getConfidence('oral communication') !== null" class="text-xs text-gray-500 mt-1 block">Extraction confidence: {{ Math.round(getConfidence('oral communication') * 100) }}%</span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">21st Century Literature</label>
                                    <input
                                        v-model.number="form.g11_21st_century_lit"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        :class="['w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent', isLowConfidence('21st century literature') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]']"
                                        placeholder="Enter grade (0-100)"
                                    />
                                    <p v-if="isLowConfidence('21st century literature')" class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Low confidence result. Please verify.</p>
                                    <span v-if="getConfidence('21st century literature') !== null" class="text-xs text-gray-500 mt-1 block">Extraction confidence: {{ Math.round(getConfidence('21st century literature') * 100) }}%</span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">English for Academic Purposes</label>
                                    <input
                                        v-model.number="form.g11_academic_professional"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        :class="['w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent', isLowConfidence('english for academic purposes') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]']"
                                        placeholder="Enter grade (0-100)"
                                    />
                                    <p v-if="isLowConfidence('english for academic purposes')" class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Low confidence result. Please verify.</p>
                                    <span v-if="getConfidence('english for academic purposes') !== null" class="text-xs text-gray-500 mt-1 block">Extraction confidence: {{ Math.round(getConfidence('english for academic purposes') * 100) }}%</span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reading and Writing</label>
                                    <input
                                        v-model.number="form.g11_reading_writing"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        :class="['w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent', isLowConfidence('reading and writing') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]']"
                                        placeholder="Enter grade (0-100)"
                                    />
                                    <p v-if="isLowConfidence('reading and writing')" class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Low confidence result. Please verify.</p>
                                    <span v-if="getConfidence('reading and writing') !== null" class="text-xs text-gray-500 mt-1 block">Extraction confidence: {{ Math.round(getConfidence('reading and writing') * 100) }}%</span>
                                </div>
                            </div>
                            <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg inline-block">
                                <p class="text-sm font-medium text-blue-700 dark:text-blue-300">
                                    English Average: <span class="font-bold">{{ englishAverage || "—" }}</span>
                                </p>
                            </div>

                            <!-- G12 English Group -->
                            <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mt-6 mb-4 flex items-center">
                                <span class="w-1 h-5 bg-[#9E122C] rounded-full mr-2 dark:bg-gray-900"></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-500 text-white mr-2">G12</span>
                                English Subjects (4 subjects)
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-for="i in 4" :key="i" class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject {{ i }}</label>
                                    <input
                                        v-model="form[`g12_english_subject_${i}`]"
                                        type="text"
                                        :disabled="isLocked"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent mb-3"
                                        :placeholder="`Subject ${i} name`"
                                    />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grade</label>
                                    <input
                                        v-model.number="form[`g12_english_grade_${i}`]"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        :class="['w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent', isLowConfidence(form[`g12_english_subject_${i}`]?.toLowerCase()?.trim() || '') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]']"
                                        placeholder="0-100"
                                    />
                                    <p v-if="isLowConfidence(form[`g12_english_subject_${i}`]?.toLowerCase()?.trim() || '')" class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Low confidence result. Please verify.</p>
                                    <span v-if="getConfidence(form[`g12_english_subject_${i}`]?.toLowerCase()?.trim() || '') !== null" class="text-xs text-gray-500 mt-1 block">Extraction confidence: {{ Math.round(getConfidence(form[`g12_english_subject_${i}`]?.toLowerCase()?.trim() || '') * 100) }}%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Science Sub-section -->
                        <div class="mb-8">
                            <!-- G11 Science Group -->
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
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        :class="['w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent', isLowConfidence('earth and life science') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]']"
                                        placeholder="Enter grade (0-100)"
                                    />
                                    <p v-if="isLowConfidence('earth and life science')" class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Low confidence result. Please verify.</p>
                                    <span v-if="getConfidence('earth and life science') !== null" class="text-xs text-gray-500 mt-1 block">Extraction confidence: {{ Math.round(getConfidence('earth and life science') * 100) }}%</span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Physical Science</label>
                                    <input
                                        v-model.number="form.g11_physical_science"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        :class="['w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent', isLowConfidence('physical science') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]']"
                                        placeholder="Enter grade (0-100)"
                                    />
                                    <p v-if="isLowConfidence('physical science')" class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Low confidence result. Please verify.</p>
                                    <span v-if="getConfidence('physical science') !== null" class="text-xs text-gray-500 mt-1 block">Extraction confidence: {{ Math.round(getConfidence('physical science') * 100) }}%</span>
                                </div>
                            </div>
                            <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg inline-block">
                                <p class="text-sm font-medium text-blue-700 dark:text-blue-300">
                                    Science Average: <span class="font-bold">{{ scienceAverage || "—" }}</span>
                                </p>
                            </div>

                            <!-- G12 Science Group -->
                            <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mt-6 mb-4 flex items-center">
                                <span class="w-1 h-5 bg-[#9E122C] rounded-full mr-2 dark:bg-gray-900"></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-500 text-white mr-2">G12</span>
                                Science Subjects (2 subjects)
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject 1</label>
                                    <input
                                        v-model="form.g12_science_subject_1"
                                        type="text"
                                        :disabled="isLocked"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent mb-3"
                                        placeholder="Subject name"
                                    />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grade</label>
                                    <input
                                        v-model.number="form.g12_science_grade_1"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        :class="['w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent', isLowConfidence(form.g12_science_subject_1?.toLowerCase()?.trim() || '') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]']"
                                        placeholder="0-100"
                                    />
                                    <p v-if="isLowConfidence(form.g12_science_subject_1?.toLowerCase()?.trim() || '')" class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Low confidence result. Please verify.</p>
                                    <span v-if="getConfidence(form.g12_science_subject_1?.toLowerCase()?.trim() || '') !== null" class="text-xs text-gray-500 mt-1 block">Extraction confidence: {{ Math.round(getConfidence(form.g12_science_subject_1?.toLowerCase()?.trim() || '') * 100) }}%</span>
                                </div>
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject 2</label>
                                    <input
                                        v-model="form.g12_science_subject_2"
                                        type="text"
                                        :disabled="isLocked"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent mb-3"
                                        placeholder="Subject name"
                                    />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grade</label>
                                    <input
                                        v-model.number="form.g12_science_grade_2"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        :class="['w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent', isLowConfidence(form.g12_science_subject_2?.toLowerCase()?.trim() || '') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]']"
                                        placeholder="0-100"
                                    />
                                    <p v-if="isLowConfidence(form.g12_science_subject_2?.toLowerCase()?.trim() || '')" class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Low confidence result. Please verify.</p>
                                    <span v-if="getConfidence(form.g12_science_subject_2?.toLowerCase()?.trim() || '') !== null" class="text-xs text-gray-500 mt-1 block">Extraction confidence: {{ Math.round(getConfidence(form.g12_science_subject_2?.toLowerCase()?.trim() || '') * 100) }}%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Semester GWA -->
                        <div class="mb-4">
                            <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                <span class="w-1 h-5 bg-[#9E122C] rounded-full mr-2 dark:bg-gray-900"></span>
                                Semester GWA (Optional - at least one required)
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">1st Semester</label>
                                    <input
                                        v-model.number="form.g12_first_sem_gwa"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        :class="['w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent', isLowConfidence('1st semester') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]']"
                                        placeholder="0-100"
                                    />
                                    <p v-if="isLowConfidence('1st semester')" class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Low confidence result. Please verify.</p>
                                    <span v-if="getConfidence('1st semester') !== null" class="text-xs text-gray-500 mt-1 block">Extraction confidence: {{ Math.round(getConfidence('1st semester') * 100) }}%</span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">2nd Semester</label>
                                    <input
                                        v-model.number="form.g12_second_sem_gwa"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        :disabled="isLocked"
                                        :class="['w-full px-4 py-2 border rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:border-transparent', isLowConfidence('2nd semester') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 dark:border-gray-600 focus:ring-[#9E122C]']"
                                        placeholder="0-100"
                                    />
                                    <p v-if="isLowConfidence('2nd semester')" class="text-xs text-red-500 mt-1"><i class="fas fa-exclamation-triangle mr-1"></i>Low confidence result. Please verify.</p>
                                    <span v-if="getConfidence('2nd semester') !== null" class="text-xs text-gray-500 mt-1 block">Extraction confidence: {{ Math.round(getConfidence('2nd semester') * 100) }}%</span>
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
                        <div v-if="qualifiedPrograms.length > 0">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        First Choice Program <span class="text-red-500 dark:text-red-300">*</span>
                                    </label>
                                    <select
                                        v-model="form.first_choice_program"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        required
                                    >
                                        <option value="">-- Select First Choice --</option>
                                        <option
                                            v-for="program in qualifiedPrograms"
                                            :key="program.id"
                                            :value="program.id"
                                            :disabled="program.id === form.second_choice_program"
                                        >
                                            {{ program.code }} - {{ program.name }}
                                        </option>
                                    </select>
                                    <p v-if="errors.first_choice_program" class="text-red-500 text-xs mt-1 dark:text-red-300 break-words">
                                        {{ errors.first_choice_program }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Second Choice Program <span class="text-red-500 dark:text-red-300">*</span>
                                    </label>
                                    <select
                                        v-model="form.second_choice_program"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        required
                                    >
                                        <option value="">-- Select Second Choice --</option>
                                        <option
                                            v-for="program in qualifiedPrograms"
                                            :key="program.id"
                                            :value="program.id"
                                            :disabled="program.id === form.first_choice_program"
                                        >
                                            {{ program.code }} - {{ program.name }}
                                        </option>
                                    </select>
                                    <p v-if="errors.second_choice_program" class="text-red-500 text-xs mt-1 dark:text-red-300 break-words">
                                        {{ errors.second_choice_program }}
                                    </p>
                                </div>
                            </div>

                            <!-- Selected Programs Display -->
                            <div v-if="form.first_choice_program || form.second_choice_program" 
                                 class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                <p class="font-semibold text-sm text-gray-900 dark:text-white mb-2">Your Selected Programs:</p>
                                <p v-if="form.first_choice_program" class="text-sm text-gray-700 dark:text-gray-300">
                                    1st Choice: <strong>{{ getSelectedProgramName(form.first_choice_program) }}</strong>
                                </p>
                                <p v-if="form.second_choice_program" class="text-sm text-gray-700 dark:text-gray-300">
                                    2nd Choice: <strong>{{ getSelectedProgramName(form.second_choice_program) }}</strong>
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
                        class="flex-1 px-6 py-3 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center dark:bg-gray-900 dark:text-gray-900 dark:hover:bg-gray-800 min-h-[44px]"
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
                :form-data="form"
                @close="closeReviewModal"
                @confirm="confirmSaveGrades"
            />
        </div>
    </ApplicantLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import { Head } from "@inertiajs/vue3";
import ApplicantLayout from "@/Layouts/ApplicantLayout.vue";
import GradesReviewModal from "@/Components/GradesReviewModal.vue";

const page = usePage();
const props = defineProps({
    grade: Object,
    user: Object,
    programs: Array,
    strand: String,
    profile: Object,
    extractionResult: { type: Object, default: null },
    isLocked: { type: Boolean, default: false },
});

const loading = ref(false);
const successMessage = ref("");
const errors = ref({});
const confidenceMap = ref({});
const bannerDismissed = ref(false);
const showReviewModal = ref(false);

const form = reactive({
    g11_general_mathematics: null,
    g11_statistics_probability: null,
    g11_oral_communication: null,
    g11_21st_century_lit: null,
    g11_academic_professional: null,
    g11_reading_writing: null,
    g11_earth_life_science: null,
    g11_physical_science: null,
    // Grade 12 Math subjects
    g12_math_subject_1: "",
    g12_math_grade_1: null,
    g12_math_subject_2: "",
    g12_math_grade_2: null,
    // Grade 12 Science subjects
    g12_science_subject_1: "",
    g12_science_grade_1: null,
    g12_science_subject_2: "",
    g12_science_grade_2: null,
    // Grade 12 English subjects
    g12_english_subject_1: "",
    g12_english_grade_1: null,
    g12_english_subject_2: "",
    g12_english_grade_2: null,
    g12_english_subject_3: "",
    g12_english_grade_3: null,
    g12_english_subject_4: "",
    g12_english_grade_4: null,
    // Grade 12 Semester GWA
    g12_first_sem_gwa: null,
    g12_second_sem_gwa: null,
    // Program choices
    first_choice_program: "",
    second_choice_program: "",
});

// Computed properties for averages
const mathAverage = computed(() => {
    const grades = [
        form.g11_general_mathematics,
        form.g11_statistics_probability,
        form.g12_math_grade_1,
        form.g12_math_grade_2,
    ].filter((g) => g !== null && g !== "");
    return grades.length > 0
        ? (grades.reduce((a, b) => a + b, 0) / grades.length).toFixed(2)
        : null;
});

const englishAverage = computed(() => {
    const grades = [
        form.g11_oral_communication,
        form.g11_21st_century_lit,
        form.g11_academic_professional,
        form.g11_reading_writing,
        form.g12_english_grade_1,
        form.g12_english_grade_2,
        form.g12_english_grade_3,
        form.g12_english_grade_4,
    ].filter((g) => g !== null && g !== "");
    return grades.length > 0
        ? (grades.reduce((a, b) => a + b, 0) / grades.length).toFixed(2)
        : null;
});

const scienceAverage = computed(() => {
    const grades = [
        form.g11_earth_life_science,
        form.g11_physical_science,
        form.g12_science_grade_1,
        form.g12_science_grade_2,
    ].filter((g) => g !== null && g !== "");
    return grades.length > 0
        ? (grades.reduce((a, b) => a + b, 0) / grades.length).toFixed(2)
        : null;
});

const g12GWA = computed(() => {
    const semGrades = [form.g12_first_sem_gwa, form.g12_second_sem_gwa].filter(
        (g) => g !== null && g !== "" && !isNaN(g) && isFinite(g)
    );

    if (semGrades.length === 0) {
        return null;
    }

    const average = semGrades.reduce((a, b) => parseFloat(a) + parseFloat(b), 0) / semGrades.length;
    return isNaN(average) || !isFinite(average) ? null : average.toFixed(2);
});

const meetsRequirement = (studentValue, requiredValue) => {
    if (requiredValue === null || requiredValue === undefined || requiredValue === "") {
        return true;
    }

    const required = parseFloat(requiredValue);
    if (Number.isNaN(required)) {
        return true;
    }

    return parseFloat(studentValue) >= required;
};

// Program qualification logic
const qualifiedPrograms = computed(() => {
    if (
        !props.programs ||
        !mathAverage.value ||
        !englishAverage.value ||
        !scienceAverage.value ||
        !g12GWA.value
    ) {
        return [];
    }

    return props.programs.filter((program) => {
        if (!isStrandAllowed(program)) {
            return false;
        }
        const meetsMath = meetsRequirement(mathAverage.value, program.math);
        const meetsEnglish = meetsRequirement(englishAverage.value, program.english);
        const meetsScience = meetsRequirement(scienceAverage.value, program.science);
        const meetsGWA = meetsRequirement(g12GWA.value, program.gwa);

        return meetsMath && meetsEnglish && meetsScience && meetsGWA;
    });
});

const notQualifiedPrograms = computed(() => {
    if (
        !props.programs ||
        !mathAverage.value ||
        !englishAverage.value ||
        !scienceAverage.value ||
        !g12GWA.value
    ) {
        return props.programs || [];
    }

    return props.programs.filter((program) => {
        if (!isStrandAllowed(program)) {
            return true;
        }
        const meetsMath = meetsRequirement(mathAverage.value, program.math);
        const meetsEnglish = meetsRequirement(englishAverage.value, program.english);
        const meetsScience = meetsRequirement(scienceAverage.value, program.science);
        const meetsGWA = meetsRequirement(g12GWA.value, program.gwa);

        return !(meetsMath && meetsEnglish && meetsScience && meetsGWA);
    });
});

const currentStrand = computed(() => (props.strand || "GAS").toUpperCase());

const isStrandAllowed = (program) => {
    const strandValue = (program.strand_names || "").toString().toUpperCase();
    if (!strandValue || strandValue.includes("OPEN TO ALL")) {
        return true;
    }

    if (strandValue.includes("OTHER WITH BRIDGING")) {
        return true;
    }

    const allowed = strandValue
        .split(",")
        .map((s) => s.trim())
        .filter(Boolean);
    return allowed.includes(currentStrand.value);
};

// Helper function to get selected program name
const getSelectedProgramName = (programId) => {
    const program = props.programs?.find((p) => p.id === programId);
    return program ? `${program.code} - ${program.name}` : "";
};

const getConfidence = (fieldKey) => {
    const normalizedKey = fieldKey.toLowerCase().trim();
    return confidenceMap.value[normalizedKey] ?? null;
};

const isLowConfidence = (fieldKey) => {
    const c = getConfidence(fieldKey);
    return c !== null && c < 0.80;
};

const G11_MAP = {
    'general mathematics': 'g11_general_mathematics',
    'statistics and probability': 'g11_statistics_probability',
    'oral communication': 'g11_oral_communication',
    'oral communication in context': 'g11_oral_communication',
    '21st century literature': 'g11_21st_century_lit',
    '21st century literature from the philippines and the world': 'g11_21st_century_lit',
    'english for academic and professional purposes': 'g11_academic_professional',
    'english for academic purposes': 'g11_academic_professional',
    'eapp': 'g11_academic_professional',
    'reading and writing': 'g11_reading_writing',
    'reading and writing skills': 'g11_reading_writing',
    'earth and life science': 'g11_earth_life_science',
    'physical science': 'g11_physical_science',
};

const applyAutofill = (result) => {
    if (!result || !result.subjects) return;
    const newConfidenceMap = {};
    
    let mathIdx = 1;
    let scienceIdx = 1;
    let englishIdx = 1;
    
    for (const group of ['math', 'science', 'english', 'others']) {
        if (!result.subjects[group]) continue;
        for (const [subjectKey, gradeVal] of Object.entries(result.subjects[group])) {
            const normalizedKey = subjectKey.toLowerCase().trim();
            newConfidenceMap[normalizedKey] = 1.0; 
            const numericGrade = parseFloat(gradeVal);
            if (isNaN(numericGrade)) continue;

            let matched = false;

            const g11FormKey = G11_MAP[normalizedKey];
            if (g11FormKey && g11FormKey in form) {
                form[g11FormKey] = numericGrade;
                matched = true;
            }
            
            if (!matched) {
                if (group === 'math') {
                    while(`g12_math_subject_${mathIdx}` in form && form[`g12_math_subject_${mathIdx}`] && form[`g12_math_subject_${mathIdx}`].toLowerCase() !== subjectKey.toLowerCase()) {
                        mathIdx++;
                    }
                    if (`g12_math_subject_${mathIdx}` in form) {
                        form[`g12_math_subject_${mathIdx}`] = subjectKey;
                        form[`g12_math_grade_${mathIdx}`] = numericGrade;
                        mathIdx++;
                    }
                } else if (group === 'science') {
                    while(`g12_science_subject_${scienceIdx}` in form && form[`g12_science_subject_${scienceIdx}`] && form[`g12_science_subject_${scienceIdx}`].toLowerCase() !== subjectKey.toLowerCase()) {
                        scienceIdx++;
                    }
                    if (`g12_science_subject_${scienceIdx}` in form) {
                        form[`g12_science_subject_${scienceIdx}`] = subjectKey;
                        form[`g12_science_grade_${scienceIdx}`] = numericGrade;
                        scienceIdx++;
                    }
                } else if (group === 'english') {
                    if (normalizedKey.includes('21st century') && 'g12_english_grade_1' in form) {
                         form['g12_english_grade_1'] = numericGrade;
                    } else {
                        while(`g12_english_subject_${englishIdx}` in form && (`g12_english_subject_${englishIdx}` === 'g12_english_subject_1' || (form[`g12_english_subject_${englishIdx}`] && form[`g12_english_subject_${englishIdx}`].toLowerCase() !== subjectKey.toLowerCase()))) {
                            englishIdx++;
                        }
                        if (`g12_english_subject_${englishIdx}` in form) {
                            form[`g12_english_subject_${englishIdx}`] = subjectKey;
                            form[`g12_english_grade_${englishIdx}`] = numericGrade;
                            englishIdx++;
                        }
                    }
                }
            }
        }
    }
    confidenceMap.value = newConfidenceMap;
};

onMounted(() => {
    // Load saved program choices from profile
    if (props.profile) {
        if (props.profile.first_choice_program) {
            form.first_choice_program = props.profile.first_choice_program;
        }
        if (props.profile.second_choice_program) {
            form.second_choice_program = props.profile.second_choice_program;
        }
    }
    
    // Load saved semester GWAs from grade
    if (props.grade && props.grade.id) {
        if (props.grade.g12_first_sem) {
            form.g12_first_sem_gwa = props.grade.g12_first_sem;
        }
        if (props.grade.g12_second_sem) {
            form.g12_second_sem_gwa = props.grade.g12_second_sem;
        }
    }
    
    // Apply autofill from extraction if available (takes precedence)
    if (props.extractionResult) {
        applyAutofill(props.extractionResult);
    }
});

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

const submitForm = async () => {
    loading.value = true;
    errors.value = {};

    // Validate required grades
    if (
        !mathAverage.value ||
        !englishAverage.value ||
        !scienceAverage.value ||
        !g12GWA.value
    ) {
        errors.value = {
            programs:
                "Please complete all subject grades and semester GWAs before submitting",
        };
        loading.value = false;
        return;
    }

    // Validate that program choices are selected
    if (!form.first_choice_program || !form.second_choice_program) {
        errors.value = {
            programs: "Please select both first and second choice programs",
        };
        loading.value = false;
        return;
    }

    // Validate that choices are different
    if (form.first_choice_program === form.second_choice_program) {
        errors.value = {
            programs: "First and second choice programs must be different",
        };
        loading.value = false;
        return;
    }

    // Prepare data with only computed averages
    const payload = {
        mathematics: parseFloat(mathAverage.value),
        english: parseFloat(englishAverage.value),
        science: parseFloat(scienceAverage.value),
        g12_first_sem: parseFloat(form.g12_first_sem_gwa),
        g12_second_sem: parseFloat(form.g12_second_sem_gwa),
        first_choice_program: form.first_choice_program,
        second_choice_program: form.second_choice_program,
    };

    router.post("/grades/gas", payload, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: (response) => {
            successMessage.value =
                "Grades and program choices saved successfully!";
            alert("✅ Grades saved successfully! Redirecting to dashboard...");
            setTimeout(() => {
                router.visit("/applicant-dashboard");
            }, 1000);
            loading.value = false;
        },
        onError: (errorResponse) => {
            errors.value = errorResponse;
            const firstError = Object.values(errorResponse)[0];
            alert(
                "❌ " +
                    (firstError ||
                        "Failed to save grades. Please check the form.")
            );
            loading.value = false;
        },
        onFinish: () => {
            loading.value = false;
        },
    });
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