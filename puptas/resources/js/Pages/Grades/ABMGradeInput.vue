<template>
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
                    <div class="flex items-center w-full">
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

            <form @submit.prevent="submitForm">
                <!-- Grade 11 Section -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden mb-8">
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Grade 11 Subjects</h2>
                    </div>
                    
                    <div class="p-6">
                        <!-- Math Subjects -->
                        <div class="mb-8">
                            <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                <span class="w-1 h-5 bg-[#9E122C] rounded-full mr-2 dark:bg-gray-900"></span>
                                Math-Related Subjects
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">General Mathematics</label>
                                    <div class="relative">
                                        <input required
                                            v-model.number="form.g11_general_mathematics"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                            placeholder="Enter grade (0-100)"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Business Mathematics</label>
                                    <input required
                                        v-model.number="form.g11_business_mathematics"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        placeholder="Enter grade (0-100)"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Statistics and Probability</label>
                                    <input required
                                        v-model.number="form.g11_statistics_probability"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        placeholder="Enter grade (0-100)"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- English Subjects -->
                        <div class="mb-8">
                            <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                <span class="w-1 h-5 bg-[#9E122C] rounded-full mr-2 dark:bg-gray-900"></span>
                                English-Related Subjects
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Oral Communication</label>
                                    <input required
                                        v-model.number="form.g11_oral_communication"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        placeholder="Enter grade (0-100)"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">English for Academic Purposes</label>
                                    <input required
                                        v-model.number="form.g11_academic_professional"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        placeholder="Enter grade (0-100)"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Reading and Writing</label>
                                    <input required
                                        v-model.number="form.g11_reading_writing"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        placeholder="Enter grade (0-100)"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Science Subjects -->
                        <div class="mb-4">
                            <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                <span class="w-1 h-5 bg-[#9E122C] rounded-full mr-2 dark:bg-gray-900"></span>
                                Science-Related Subjects
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Earth and Life Science</label>
                                    <input required
                                        v-model.number="form.g11_earth_life_science"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        placeholder="Enter grade (0-100)"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Physical Science</label>
                                    <input required
                                        v-model.number="form.g11_physical_science"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        placeholder="Enter grade (0-100)"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grade 12 Section -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden mb-8">
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Grade 12 Subjects</h2>
                    </div>
                    
                    <div class="p-6">
                        <!-- Math Subjects -->
                        <div class="mb-8">
                            <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                <span class="w-1 h-5 bg-[#9E122C] rounded-full mr-2 dark:bg-gray-900"></span>
                                Math Subjects (3 subjects)
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject 1</label>
                                    <input required
                                        v-model="form.g12_math_subject_1"
                                        type="text"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent mb-3"
                                        placeholder="Subject name"
                                    />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grade</label>
                                    <input required
                                        v-model.number="form.g12_math_grade_1"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        placeholder="0-100"
                                    />
                                </div>
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject 2</label>
                                    <input required
                                        v-model="form.g12_math_subject_2"
                                        type="text"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent mb-3"
                                        placeholder="Subject name"
                                    />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grade</label>
                                    <input required
                                        v-model.number="form.g12_math_grade_2"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        placeholder="0-100"
                                    />
                                </div>
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject 3</label>
                                    <input required
                                        v-model="form.g12_math_subject_3"
                                        type="text"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent mb-3"
                                        placeholder="Subject name"
                                    />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grade</label>
                                    <input required
                                        v-model.number="form.g12_math_grade_3"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        placeholder="0-100"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Science Subjects -->
                        <div class="mb-8">
                            <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                <span class="w-1 h-5 bg-[#9E122C] rounded-full mr-2 dark:bg-gray-900"></span>
                                Science Subjects (2 subjects)
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject 1</label>
                                    <input required
                                        v-model="form.g12_science_subject_1"
                                        type="text"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent mb-3"
                                        placeholder="Subject name"
                                    />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grade</label>
                                    <input required
                                        v-model.number="form.g12_science_grade_1"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        placeholder="0-100"
                                    />
                                </div>
                                <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject 2</label>
                                    <input required
                                        v-model="form.g12_science_subject_2"
                                        type="text"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent mb-3"
                                        placeholder="Subject name"
                                    />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grade</label>
                                    <input required
                                        v-model.number="form.g12_science_grade_2"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        placeholder="0-100"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- English Subjects -->
                        <div class="mb-8">
                            <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                <span class="w-1 h-5 bg-[#9E122C] rounded-full mr-2 dark:bg-gray-900"></span>
                                English Subjects (3 subjects)
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div v-for="i in 3" :key="`eng_${i}`" class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subject {{ i }}</label>
                                    <input required
                                        v-model="form[`g12_english_subject_${i}`]"
                                        type="text"
                                        :readonly="i === 1"
                                        :class="{'cursor-not-allowed bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-400': i === 1, 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white': i !== 1}"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#9E122C] focus:border-transparent mb-3"
                                        :placeholder="`Subject ${i} name`"
                                    />
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grade</label>
                                    <input required
                                        v-model.number="form[`g12_english_grade_${i}`]"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        placeholder="0-100"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Semester GWA -->
                        <div class="mb-4">
                            <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200 mb-4 flex items-center">
                                <span class="w-1 h-5 bg-[#9E122C] rounded-full mr-2 dark:bg-gray-900"></span>
                                Semester GWA
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">1st Semester</label>
                                    <input required
                                        v-model.number="form.g12_first_sem_gwa"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        placeholder="0-100"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">2nd Semester</label>
                                    <input required
                                        v-model.number="form.g12_second_sem_gwa"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                        placeholder="0-100"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grade Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
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
                                    <p v-if="errors.first_choice_program" class="text-red-500 text-xs mt-1 dark:text-red-300">
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
                                    <p v-if="errors.second_choice_program" class="text-red-500 text-xs mt-1 dark:text-red-300">
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

                        <p v-if="errors.programs" class="text-red-500 text-xs mt-2 dark:text-red-300">
                            {{ errors.programs }}
                        </p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex gap-4">
                    <button
                        type="submit"
                        :disabled="loading"
                        class="flex-1 px-6 py-3 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center dark:bg-gray-900 dark:text-gray-900 dark:hover:bg-gray-800"
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
                        class="flex-1 px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition font-medium"
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
        </div>
    </ApplicantLayout>
</template>

<script setup>
import { ref, reactive, computed } from "vue";
import { usePage, router } from "@inertiajs/vue3";
import ApplicantLayout from "@/Layouts/ApplicantLayout.vue";

const page = usePage();
const props = defineProps({
    grade: Object,
    user: Object,
    programs: Array,
    strand: String,
});

const loading = ref(false);
const successMessage = ref("");
const errors = ref({});

const form = reactive({
    g11_general_mathematics: null,
    g11_business_mathematics: null,
    g11_statistics_probability: null,
    g11_oral_communication: null,
    g11_academic_professional: null,
    g11_reading_writing: null,
    g11_earth_life_science: null,
    g11_physical_science: null,
    // Grade 12 Math subjects
    g12_math_subject_1: "",
    g12_math_grade_1: null,
    g12_math_subject_2: "",
    g12_math_grade_2: null,
    g12_math_subject_3: "",
    g12_math_grade_3: null,
    // Grade 12 Science subjects
    g12_science_subject_1: "",
    g12_science_grade_1: null,
    g12_science_subject_2: "",
    g12_science_grade_2: null,
    // Grade 12 English subjects
    g12_english_subject_1: "21st Century Literature from the Philippines and the World",
    g12_english_grade_1: null,
    g12_english_subject_2: "",
    g12_english_grade_2: null,
    g12_english_subject_3: "",
    g12_english_grade_3: null,
    // Grade 12 Semester GWA
    g12_first_sem_gwa: null,
    g12_second_sem_gwa: null,
    // Program choices
    first_choice_program: "",
    second_choice_program: "",
});

// Computed properties for averages
const isValidGrade = (val) => val !== null && val !== "" && !isNaN(val) && isFinite(val);

const mathAverage = computed(() => {
    const requiredGrades = [
        form.g11_general_mathematics,
        form.g11_business_mathematics,
        form.g11_statistics_probability,
        form.g12_math_grade_1,
        form.g12_math_grade_2,
        form.g12_math_grade_3,
    ];
    
    if (requiredGrades.some((g) => !isValidGrade(g))) {
        return null;
    }

    return (requiredGrades.reduce((a, b) => a + parseFloat(b), 0) / requiredGrades.length).toFixed(2);
});

const englishAverage = computed(() => {
    const requiredGrades = [
        form.g11_oral_communication,
        form.g11_academic_professional,
        form.g11_reading_writing,
        form.g12_english_grade_1,
        form.g12_english_grade_2,
        form.g12_english_grade_3,
    ];

    if (requiredGrades.some((g) => !isValidGrade(g))) {
        return null;
    }
    
    return (requiredGrades.reduce((a, b) => a + parseFloat(b), 0) / requiredGrades.length).toFixed(2);
});

const scienceAverage = computed(() => {
    const requiredGrades = [
        form.g11_earth_life_science,
        form.g11_physical_science,
        form.g12_science_grade_1,
        form.g12_science_grade_2,
    ];

    if (requiredGrades.some((g) => !isValidGrade(g))) {
        return null;
    }
    
    return (requiredGrades.reduce((a, b) => a + parseFloat(b), 0) / requiredGrades.length).toFixed(2);
});

const g12GWA = computed(() => {
    const requiredGrades = [form.g12_first_sem_gwa, form.g12_second_sem_gwa];

    if (requiredGrades.some((g) => !isValidGrade(g))) {
        return null;
    }

    return (requiredGrades.reduce((a, b) => a + parseFloat(b), 0) / requiredGrades.length).toFixed(2);
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

const currentStrand = computed(() => (props.strand || "ABM").toUpperCase());

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

    // Double check that selected programs are still in qualifiedPrograms list (client-side validation)
    const isFirstChoiceQualified = qualifiedPrograms.value.some(p => p.id === form.first_choice_program);
    const isSecondChoiceQualified = qualifiedPrograms.value.some(p => p.id === form.second_choice_program);

    if (!isFirstChoiceQualified || !isSecondChoiceQualified) {
        errors.value = {
            programs: "One or more selected programs are no longer qualified based on your updated grades. Please review your choices.",
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

    router.post("/grades/abm", payload, {
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