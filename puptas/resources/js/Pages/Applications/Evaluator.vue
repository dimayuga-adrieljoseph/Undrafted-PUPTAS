<template>
    <Head title="All Evaluator Applications" />
    <EvaluatorLayout>
        <div class="max-w-9xl mx-auto p-6 px-2 sm:px-4 md:px-6 lg:px-8 overflow-x-hidden overflow-y-auto">
            <!-- Filters and Controls -->
            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 mb-6">
                <!-- Search Input -->
                <div class="flex-1 relative">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2 dark:text-gray-200"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                        />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by name..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                    />
                </div>

                <!-- Status Filter Dropdown -->
                <div class="relative">
                    <button
                        @click="showStatusDropdown = !showStatusDropdown"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium flex items-center space-x-2"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L15 12.414V19a1 1 0 01-1.447.894l-4-2A1 1 0 019 17v-4.586L3.293 6.707A1 1 0 013 6V4z"
                            />
                        </svg>
                        <span>{{ evaluationStatusFilter ? getEvaluationStatusText({ pipeline_status: evaluationStatusFilter }) : 'All Status' }}</span>
                    </button>
                    <div
                        v-if="showStatusDropdown"
                        class="absolute top-full mt-2 right-0 bg-white dark:bg-gray-800 shadow-md border border-gray-200 rounded z-50 text-sm min-w-[200px] dark:border-gray-700"
                    >
                        <button
                            class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="evaluationStatusFilter = ''; showStatusDropdown = false;"
                        >All</button>
                        <button class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="evaluationStatusFilter = 'for_evaluation'; showStatusDropdown = false;">
                            For Evaluation
                        </button>
                        <button class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="evaluationStatusFilter = 'evaluation_returned'; showStatusDropdown = false;">
                            Returned for Revision
                        </button>
                        <button class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="evaluationStatusFilter = 'evaluation_passed'; showStatusDropdown = false;">
                            Evaluation Passed
                        </button>
                        <button class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="evaluationStatusFilter = 'for_interview'; showStatusDropdown = false;">
                            For Interview
                        </button>
                        <button class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="evaluationStatusFilter = 'for_medical'; showStatusDropdown = false;">
                            For Medical
                        </button>
                        <button class="block px-4 py-2 w-full text-left hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="evaluationStatusFilter = 'officially_enrolled'; showStatusDropdown = false;">
                            Officially Enrolled
                        </button>
                    </div>
                </div>

                <!-- Sort By -->
                <select v-model="sortKey" class="px-7 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent">
                    <option value="lastname">Last Name</option>
                    <option value="firstname">First Name</option>
                    <option value="program.name">Course</option>
                </select>

                <!-- Sort Order -->
                <button 
                    @click="sortAsc = !sortAsc" 
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium flex items-center space-x-2"
                >
                    <span>{{ sortAsc ? 'Ascending' : 'Descending' }}</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path v-if="sortAsc" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4 4m0 0l4-4m-4 4V4" />
                    </svg>
                </button>

                <!-- Clear Filters -->
                <button 
                    @click="clearFilters" 
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium"
                >
                    Clear
                </button>
            </div>

            <!-- Users Table -->
            <div v-if="isLoading" class="text-center text-gray-500 py-8 dark:text-gray-300">Loading applicants…</div>
            <div v-else-if="errorMessage" class="text-center text-red-500 py-8 dark:text-red-300">Error: {{ errorMessage }}</div>

            <div v-else class="bg-white dark:bg-gray-800/20 rounded-xl shadow p-2 overflow-x-auto">
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    Showing {{ paginatedUsers.length }} of {{ filteredUsers.length }} users
                </div>
                
                <table class="min-w-full text-base">
                    <thead>
                        <tr class="text-left font-semibold text-black dark:text-white">
                            <th class="pb-2 cursor-pointer hover:text-[#9E122C] dark:hover:text-white" @click="sortBy('lastname')">
                                Name
                                <span v-if="sortKey === 'lastname'" class="ml-1">{{ sortAsc ? '↑' : '↓' }}</span>
                            </th>
                            <th class="pb-2 cursor-pointer hover:text-[#9E122C] dark:hover:text-white" @click="sortBy('program.name')">
                                Course
                                <span v-if="sortKey === 'program.name'" class="ml-1">{{ sortAsc ? '↑' : '↓' }}</span>
                            </th>
                            <th class="pb-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr
                            v-for="user in paginatedUsers"
                            :key="user.id"
                            @click="selectUser(user)"
                            :class="[
                                'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/30 transition',
                                user.is_evaluation_completed ? 'opacity-60' : ''
                            ]"
                        >
                            <td class="py-3 text-gray-900 dark:text-white font-medium">
                                <div class="flex items-center gap-2">
                                    <span>{{ user.firstname }} {{ user.lastname }}</span>
                                    <span 
                                        v-if="user.is_evaluation_completed"
                                        class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 text-xs rounded-full font-semibold"
                                    >
                                        Evaluated
                                    </span>
                                </div>
                            </td>
                            <td class="py-3 text-gray-700 dark:text-gray-300">{{ user.program?.name || "—" }}</td>
                            <td class="py-3">
                                <span :class="getEvaluationStatusClass(user)" class="px-2.5 py-1 rounded-full text-xs font-medium">
                                    {{ getEvaluationStatusText(user) }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="paginatedUsers.length === 0">
                            <td colspan="3" class="py-8 text-center text-gray-500 dark:text-gray-400">No applicants found matching your criteria.</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700 dark:text-gray-400">
                            <span v-if="!filteredUsers.length || filteredUsers.length === 0">
                                Showing 0 to 0 of 0 results
                            </span>
                            <span v-else>
                                Showing {{ (currentPage - 1) * itemsPerPage + 1 }} 
                                to {{ Math.min(currentPage * itemsPerPage, filteredUsers.length) }} 
                                of {{ filteredUsers.length }} results
                            </span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button :disabled="currentPage === 1" @click.prevent="currentPage--"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900">
                                <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                Previous
                            </button>
                            <div class="flex items-center space-x-2 mx-2 text-sm text-gray-700 dark:text-gray-300">
                                <span>Page</span>
                                <input type="number" :value="currentPage" min="1" :max="totalPages || 1"
                                    @change="currentPage = Math.max(1, Math.min($event.target.value, totalPages || 1))"
                                    class="w-16 px-2 py-1 text-center border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent font-medium text-sm" />
                                <span>of <span class="font-semibold">{{ totalPages || 1 }}</span></span>
                            </div>
                            <button :disabled="currentPage === totalPages || totalPages === 0" @click.prevent="currentPage++"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900">
                                Next
                                <svg class="h-5 w-5 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applicant Details Modal -->
        <div
            v-if="selectedUser"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 backdrop-blur-sm p-4 sm:p-6 overflow-y-auto"
            @click.self="closeUserCard"
        >
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-7xl my-4 sm:my-8 flex flex-col max-h-[calc(100vh-2rem)] sm:max-h-[calc(100vh-4rem)] overflow-hidden">
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[#9E122C] text-white flex items-center justify-center text-lg font-bold shrink-0">
                            {{ (selectedUser.firstname || selectedUser.email || '?').charAt(0).toUpperCase() }}{{ (selectedUser.lastname || '').charAt(0).toUpperCase() }}
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ [selectedUser.firstname, selectedUser.middlename, selectedUser.lastname].filter(Boolean).join(' ') }}
                            </h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                App #{{ selectedUser.application?.id || 'N/A' }} · {{ selectedUser.reference_number || 'No ref' }} · {{ selectedUser.email }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span :class="getStatusBadgeClass(selectedUser)" class="px-3 py-1 rounded-full text-xs font-semibold">
                            {{ getEvaluationStatusText(selectedUser) }}
                        </span>
                        <button @click="closeUserCard"
                            class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition" aria-label="Close">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal Body: 2-column layout -->
                <div class="flex-1 overflow-y-auto px-6 py-5">
                    <!-- Evaluation Completed Badge -->
                    <div v-if="isEvaluationCompleted"
                        class="flex items-start gap-3 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-xl mb-5">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">Evaluation Completed</p>
                            <p class="text-xs text-blue-600 dark:text-blue-400 mt-0.5">You have already evaluated this application. Actions are no longer available.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        <!-- Left Column: Info & Grades -->
                        <div class="lg:col-span-7 space-y-5">

                            <!-- Promissory Note Tag -->
                            <div v-if="selectedUser?.application?.requires_promissory_note" class="p-3 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-xl flex items-center gap-2">
                                <svg class="w-5 h-5 text-orange-600 dark:text-orange-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                <span class="text-sm font-medium text-orange-800 dark:text-orange-300">This applicant requires a Promissory Note.</span>
                            </div>

                            <!-- Personal & Educational Info -->
                            <div>
                                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Personal Information</h4>
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Sex</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white capitalize">{{ selectedUser.sex || '—' }}</p>
                                    </div>
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
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Date Graduated</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDateOnly(selectedUser.date_graduated) }}</p>
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
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">1st Choice</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ selectedUser.application?.program?.name || "—" }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ selectedUser.application?.program?.code || "" }} · {{ selectedUser.application?.program?.slots ?? 0 }} slots</p>
                                    </div>
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">2nd Choice</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ selectedUser.application?.second_choice?.name || "—" }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ selectedUser.application?.second_choice?.code || "" }} · {{ selectedUser.application?.second_choice?.slots ?? 0 }} slots</p>
                                    </div>
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">3rd Choice</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ selectedUser.application?.third_choice?.name || "—" }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ selectedUser.application?.third_choice?.code || "" }} · {{ selectedUser.application?.third_choice?.slots ?? 0 }} slots</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Subject Grades -->
                            <div v-if="hasIndividualSubjects">
                                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Subject Grades</h4>
                                
                                <!-- Mathematics -->
                                <div v-if="mathSubjects.length" class="mb-4">
                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Mathematics</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div v-for="subj in mathSubjects" :key="subj.key" class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                            <span class="text-xs text-gray-600 dark:text-gray-400">{{ subj.label }}</span>
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ subj.value ?? '—' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Science -->
                                <div v-if="scienceSubjects.length" class="mb-4">
                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Science</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div v-for="subj in scienceSubjects" :key="subj.key" class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                            <span class="text-xs text-gray-600 dark:text-gray-400">{{ subj.label }}</span>
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ subj.value ?? '—' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- English -->
                                <div v-if="englishSubjects.length" class="mb-4">
                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">English</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div v-for="subj in englishSubjects" :key="subj.key" class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                            <span class="text-xs text-gray-600 dark:text-gray-400">{{ subj.label }}</span>
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ subj.value ?? '—' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Dynamic Subjects -->
                                <div v-if="dynamicSubjectList.length" class="mb-4">
                                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Additional Subjects</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div v-for="(subj, idx) in dynamicSubjectList" :key="'dyn-'+idx" class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                            <span class="text-xs text-gray-600 dark:text-gray-400 truncate">{{ subj.label }}</span>
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ subj.value ?? '—' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Category Averages -->
                                <div class="grid grid-cols-3 gap-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-center">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Math Avg</p>
                                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ selectedUser.grades?.mathematics ?? '—' }}</p>
                                    </div>
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-center">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Science Avg</p>
                                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ selectedUser.grades?.science ?? '—' }}</p>
                                    </div>
                                    <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-center">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">English Avg</p>
                                        <p class="text-lg font-bold text-gray-900 dark:text-white">{{ selectedUser.grades?.english ?? '—' }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Fallback: averages only -->
                            <div v-else-if="selectedUser?.grades">
                                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Academic Grades</h4>
                                <div class="grid grid-cols-3 gap-3">
                                    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-center">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Mathematics</p>
                                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ selectedUser.grades?.mathematics ?? '—' }}</p>
                                    </div>
                                    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-center">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Science</p>
                                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ selectedUser.grades?.science ?? '—' }}</p>
                                    </div>
                                    <div class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl text-center">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">English</p>
                                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ selectedUser.grades?.english ?? '—' }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Application History -->
                            <div v-if="selectedUser?.application?.processes?.length">
                                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Application History</h4>
                                <div class="space-y-2">
                                    <div v-for="(process, index) in selectedUser.application.processes" :key="index"
                                        class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
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
                        <div class="lg:col-span-5 space-y-5">

                            <!-- Evaluation Actions -->
                            <div v-if="!isEvaluationCompleted" class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Evaluation Actions</h4>
                                
                                <div v-if="!hasStartedReview" class="mb-4">
                                    <button
                                        @click="startReview"
                                        :disabled="isStartingReview"
                                        class="w-full px-4 py-2 bg-[#9E122C] hover:bg-[#800918] text-white rounded-lg transition font-medium min-h-[44px] flex justify-center items-center disabled:opacity-50"
                                    >
                                        <svg v-if="isStartingReview" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>{{ isStartingReview ? 'Starting...' : 'Begin Review' }}</span>
                                    </button>
                                </div>
                                
                                <div v-else class="space-y-3">
                                    <div class="flex gap-2">
                                        <button v-if="!isEvaluating" @click="showPassModal = true"
                                            class="flex-1 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-xl transition flex items-center justify-center gap-2 shadow-sm">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Pass
                                        </button>
                                        <button v-if="!isEvaluating" @click="startEvaluation"
                                            class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl transition flex items-center justify-center gap-2 shadow-sm">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                            </svg>
                                            {{ $page.props.auth?.user?.role_id !== 3 ? 'Go to Admission Office' : 'Go to Guidance Office' }}
                                        </button>
                                        <button v-if="isEvaluating" @click="cancelEvaluation"
                                            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg transition">
                                            Cancel
                                        </button>
                                    </div>



                                    <div v-if="isEvaluating" class="p-3 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-lg space-y-2">
                                        <div v-if="evaluationError" class="p-2 mb-2 bg-red-100 border border-red-400 text-red-700 text-xs rounded relative" role="alert">
                                            <span class="block sm:inline">{{ evaluationError }}</span>
                                        </div>
                                        <label for="returnNote" class="block text-xs font-semibold text-red-700 dark:text-red-400">
                                            Reason <span class="text-red-500">*</span>
                                        </label>
                                        <textarea id="returnNote" name="returnNote" v-model="returnNote" rows="3"
                                            maxlength="400"
                                            :class="[
                                                'w-full border rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:border-transparent resize-none',
                                                returnNoteCharCount > 400 ? 'border-red-500 focus:ring-red-500' : 'border-red-200 dark:border-red-700 focus:ring-red-500'
                                            ]"
                                            placeholder="Explain what the applicant needs to fix or resubmit..."></textarea>
                                        <div class="text-right mt-1">
                                            <span :class="{'text-red-500': returnNoteCharCount > 400, 'text-gray-500': returnNoteCharCount <= 400}" class="text-xs">
                                                {{ returnNoteCharCount }} / 400 characters
                                            </span>
                                        </div>
                                        <div class="flex gap-2">
                                            <button @click="promptFlag"
                                                class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition shadow-sm">
                                                Confirm Action
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Uploaded Documents -->
                            <div>
                                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Uploaded Documents</h4>
                                <div class="grid grid-cols-2 gap-3">
                                    <div v-for="(file, key) in selectedUserFiles" :key="key"
                                        class="p-2 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                                        <div class="flex items-center gap-1.5 mb-1.5 min-w-0">
                                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate">{{ formatFileKey(key) }}</span>
                                        </div>
                                        <img v-if="hasImagePreview(file)" :src="getFileUrl(file)" alt="Document"
                                            class="w-full aspect-[4/3] object-cover rounded-lg cursor-pointer hover:opacity-80 transition"
                                            @click="openImageModal(file)" />
                                        <div v-else class="w-full aspect-[4/3] flex items-center justify-center text-xs text-gray-400 dark:text-gray-500 bg-gray-200 dark:bg-gray-700 rounded-lg">
                                            No file
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Image Preview Modal -->
        <div v-if="showImageModal"
            class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-[60]"
            @click.self="closeImageModal">
            <img :src="previewImage" alt="Preview" class="max-w-full max-h-full rounded shadow-lg" />
            <button @click="closeImageModal"
                class="absolute top-5 right-5 text-white text-4xl font-bold hover:text-gray-300" aria-label="Close preview">
                &times;
            </button>
        </div>

        <ChangesConfirmationModal
            :show="showPassModal"
            title="Pass Application"
            subtitle="Pass this application to the next stage? This cannot be undone."
            confirmText="Confirm Pass"
            confirmButtonClass="bg-green-600 hover:bg-green-700 text-white"
            :hideTable="true"
            :loading="isSubmitting"
            @confirm="submitPass"
            @cancel="showPassModal = false"
        />

        <ChangesConfirmationModal
            :show="showFlagModal"
            title="Flag Application"
            subtitle="Are you sure you want to require this applicant to go to the assigned office?"
            confirmText="Confirm"
            confirmButtonClass="bg-red-600 hover:bg-red-700 text-white"
            :hideTable="true"
            :loading="isSubmitting"
            @confirm="submitFlag"
            @cancel="showFlagModal = false"
        />

        <!-- Success Toast Notification -->
        <transition enter-active-class="transition ease-out duration-300" enter-from-class="transform opacity-0 translate-y-[-1rem]" enter-to-class="transform opacity-100 translate-y-0" leave-active-class="transition ease-in duration-200" leave-from-class="transform opacity-100 translate-y-0" leave-to-class="transform opacity-0 translate-y-[-1rem]">
            <div v-if="toastVisible" class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 w-full max-w-md px-4">
                <div :class="['rounded-lg shadow-lg overflow-hidden border', toastType === 'success' ? 'bg-green-50 dark:bg-green-900/20 border-green-500 dark:border-green-400' : 'bg-red-50 dark:bg-red-900/20 border-red-500 dark:border-red-400']">
                    <div class="p-4 flex items-start">
                        <svg v-if="toastType === 'success'" class="w-5 h-5 text-green-500 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <svg v-else class="w-5 h-5 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="ml-3 w-0 flex-1 pt-0.5">
                            <p :class="['text-sm font-medium', toastType === 'success' ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200']">{{ toastMessage }}</p>
                        </div>
                        <div class="ml-4 flex-shrink-0 flex">
                            <button @click="toastVisible = false" :class="['rounded-md inline-flex focus:outline-none', toastType === 'success' ? 'text-green-500 hover:text-green-600' : 'text-red-500 hover:text-red-600']">
                                <span class="sr-only">Close</span>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </EvaluatorLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import EvaluatorLayout from "@/Layouts/EvaluatorLayout.vue";
import ChangesConfirmationModal from "@/Components/ChangesConfirmationModal.vue";

import {
    Chart as ChartJS,
    LineController,
    LineElement,
    CategoryScale,
    LinearScale,
    PointElement,
    Tooltip,
    Title,
    Legend,
} from "chart.js";

ChartJS.register(
    LineController,
    LineElement,
    CategoryScale,
    LinearScale,
    PointElement,
    Tooltip,
    Title,
    Legend
);

import { usePage } from "@inertiajs/vue3";

const props = defineProps({
    selectedUserId: {
        type: [Number, String],
        default: null
    }
});

const currentPage = ref(1);
const itemsPerPage = 10;
const sortKey = ref("lastname");
const evaluationStatusFilter = ref("");
const sortAsc = ref(true);
const showStatusDropdown = ref(false);
const filterDropdownRef = ref(null);
const autoRefreshTimer = ref(null);
const POLL_INTERVAL_MS = 10000;

const page = usePage();
const users = ref(page.props.users || []);
const hasAutoSelected = ref(false);

const selectedUser = ref(null);
const isLoading = ref(true);
const errorMessage = ref("");
const searchQuery = ref("");
const selectedUserFiles = ref({});
const selectedProgramId = ref("");
const snackbar = ref({ visible: false, message: "" });

const showSnackbar = (msg, duration = 3000) => {
    snackbar.value.message = msg;
    snackbar.value.visible = true;
    setTimeout(() => { snackbar.value.visible = false; }, duration);
};

// Subject label mapping
const SUBJECT_LABELS = {
    g11_general_mathematics: 'G11 General Mathematics',
    g11_statistics_probability: 'G11 Statistics & Probability',
    g11_business_mathematics: 'G11 Business Mathematics',
    g11_pre_calculus: 'G11 Pre-Calculus',
    g11_basic_calculus: 'G11 Basic Calculus',
    g11_earth_life_science: 'G11 Earth & Life Science',
    g11_physical_science: 'G11 Physical Science',
    g11_earth_science: 'G11 Earth Science',
    g11_general_chemistry_1: 'G11 General Chemistry 1',
    g12_general_physics_1: 'G12 General Physics 1',
    g12_general_biology_1: 'G12 General Biology 1',
    g12_general_physics_2: 'G12 General Physics 2',
    g12_general_biology_2: 'G12 General Biology 2',
    g12_general_chemistry_2: 'G12 General Chemistry 2',
    g12_earth_life_science: 'G12 Earth & Life Science',
    g12_physical_science: 'G12 Physical Science',
    g11_oral_communication: 'G11 Oral Communication',
    'g11_21st_century_lit': 'G11 21st Century Literature',
    g11_academic_professional: 'G11 Academic & Professional',
    g11_reading_writing: 'G11 Reading & Writing',
    'g12_21st_century_lit': 'G12 21st Century Literature',
    g12_academic_professional: 'G12 Academic & Professional',
};

const MATH_KEYS = ['g11_general_mathematics', 'g11_statistics_probability', 'g11_business_mathematics', 'g11_pre_calculus', 'g11_basic_calculus'];
const SCIENCE_KEYS = ['g11_earth_life_science', 'g11_physical_science', 'g11_earth_science', 'g11_general_chemistry_1', 'g12_general_physics_1', 'g12_general_biology_1', 'g12_general_physics_2', 'g12_general_biology_2', 'g12_general_chemistry_2', 'g12_earth_life_science', 'g12_physical_science'];
const ENGLISH_KEYS = ['g11_oral_communication', 'g11_21st_century_lit', 'g11_academic_professional', 'g11_reading_writing', 'g12_21st_century_lit', 'g12_academic_professional'];

function buildSubjectList(keys, grades) {
    return keys.filter(key => grades?.[key] != null && grades[key] !== '').map(key => ({ key, label: SUBJECT_LABELS[key] || key, value: grades[key] }));
}

const mathSubjects = computed(() => buildSubjectList(MATH_KEYS, selectedUser.value?.grades));
const scienceSubjects = computed(() => buildSubjectList(SCIENCE_KEYS, selectedUser.value?.grades));
const englishSubjects = computed(() => buildSubjectList(ENGLISH_KEYS, selectedUser.value?.grades));

const dynamicSubjectList = computed(() => {
    const dyn = selectedUser.value?.grades?.dynamic_subjects;
    if (!dyn || !Array.isArray(dyn)) return [];
    return dyn.map(s => ({ label: s.name || 'Dynamic Subject', value: s.grade }));
});

const hasIndividualSubjects = computed(() => mathSubjects.value.length > 0 || scienceSubjects.value.length > 0 || englishSubjects.value.length > 0);

const getStatusBadgeClass = (user) => {
    switch (user.pipeline_status) {
        case 'for_evaluation': return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300';
        case 'evaluation_returned': return 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';
        case 'evaluation_passed': return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300';
        default: return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400';
    }
};

const getEvaluationStatusText = (user) => {
    switch (user.pipeline_status) {
        case 'for_evaluation': return 'For Evaluation';
        case 'evaluation_returned': return 'Returned for Revision';
        case 'evaluation_passed': return 'Evaluation Passed';
        case 'for_interview': return 'For Interview';
        case 'interview_returned': return 'Returned for Revision';
        case 'interview_passed': return 'Interview Passed';
        case 'interview_transferred': return 'Course Transferred';
        case 'for_medical': return 'For Medical';
        case 'medical_cleared': return 'Medical Cleared';
        case 'medical_rejected': return 'Medical Rejected';
        case 'for_records': return 'For Records';
        case 'officially_enrolled': return 'Officially Enrolled';
        case 'rejected': return 'Rejected';
        default: return 'Unknown';
    }
};

const getEvaluationStatusClass = (user) => {
    switch (user.pipeline_status) {
        case 'for_evaluation': return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300';
        case 'evaluation_returned': return 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';
        case 'evaluation_passed': return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300';
        case 'for_interview': return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300';
        case 'interview_returned': return 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';
        case 'interview_passed': return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300';
        case 'interview_transferred': return 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300';
        case 'for_medical': return 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300';
        case 'medical_cleared': return 'bg-teal-100 text-teal-700 dark:bg-teal-900 dark:text-teal-300';
        case 'medical_rejected': return 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';
        case 'for_records': return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300';
        case 'officially_enrolled': return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 font-semibold';
        case 'rejected': return 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';
        default: return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400';
    }
};

const fetchUsers = async () => {
    try {
        const response = await fetch("/evaluator-dashboard/applicants", {
            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        });
        if (!response.ok) throw new Error("Failed to fetch users");
        users.value = await response.json();

        // Auto-select user if selectedUserId prop was provided
        if (props.selectedUserId && !selectedUser.value && !hasAutoSelected.value) {
            const user = users.value.find(u => u.id == props.selectedUserId);
            if (user) {
                hasAutoSelected.value = true;
                await selectUser(user);
            }
        }
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isLoading.value = false;
    }
};

const handleOutsideClick = (e) => {
    if (filterDropdownRef.value && !filterDropdownRef.value.contains(e.target)) {
        showStatusDropdown.value = false;
    }
};

const refreshApplicants = async () => {
    await fetchUsers();
    if (!selectedUser.value) return;
    const existsInQueue = users.value.some((u) => String(u.id) === String(selectedUser.value.id));
    if (!existsInQueue) closeUserCard();
};

onMounted(() => {
    fetchUsers();
    document.addEventListener('click', handleOutsideClick);
    document.addEventListener('click', handleOutsideClick);
    autoRefreshTimer.value = setInterval(refreshApplicants, POLL_INTERVAL_MS);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleOutsideClick);
    if (autoRefreshTimer.value) { clearInterval(autoRefreshTimer.value); autoRefreshTimer.value = null; }
});

const filteredUsers = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return users.value
        .filter((u) => {
            const fullName = `${u.firstname} ${u.lastname}`.toLowerCase();
            const matchesSearch = fullName.includes(q);
            const matchesEvaluationStatus = evaluationStatusFilter.value ? u.pipeline_status === evaluationStatusFilter.value : true;
            return matchesSearch && matchesEvaluationStatus;
        })
        .sort((a, b) => {
            let aVal, bVal;
            if (sortKey.value === 'program.name') {
                aVal = (a.program?.name || "").toString().toLowerCase();
                bVal = (b.program?.name || "").toString().toLowerCase();
            } else {
                aVal = (a[sortKey.value] || "").toString().toLowerCase();
                bVal = (b[sortKey.value] || "").toString().toLowerCase();
            }
            return sortAsc.value ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
        });
});

const selectUser = async (user) => {
    try {
        const response = await axios.get(`/dashboard/user-files/${user.id}`);
        selectedUser.value = {
            ...user,
            ...response.data.user,
            application: {
                ...response.data.user.application,
                processes: response.data.user.application?.processes || [],
                program: response.data.user.application?.program || null,
                second_choice: response.data.user.application?.second_choice || null,
                third_choice: response.data.user.application?.third_choice || null,
            },
            grades: response.data.user.grades || null,
        };
        selectedUserFiles.value = response.data.uploadedFiles || {};
    } catch (error) {
        console.error("Failed to fetch user data:", error);
        selectedUserFiles.value = {};
        selectedUser.value = null;
    }
};

const isEvaluationCompleted = computed(() => {
    if (!selectedUser.value || !selectedUser.value.application?.processes) return false;
    const targetStage = page.props.auth?.user?.role_id === 3 ? 'document_evaluator' : 'grade_evaluator';
    const evaluatorProcess = selectedUser.value.application.processes.find(p => p.stage === targetStage);
    return evaluatorProcess && evaluatorProcess.status === 'completed';
});

const hasStartedReview = computed(() => {
    if (!selectedUser.value || !selectedUser.value.application?.processes) return false;
    const targetStage = page.props.auth?.user?.role_id === 3 ? 'document_evaluator' : 'grade_evaluator';
    const evaluatorProcess = selectedUser.value.application.processes.find(p => p.stage === targetStage);
    return evaluatorProcess && !!evaluatorProcess.started_at;
});

const isStartingReview = ref(false);

const startReview = async () => {
    const targetStage = page.props.auth?.user?.role_id === 3 ? 'document_evaluator' : 'grade_evaluator';
    const processes = selectedUser.value.application.processes;
    const processIndex = processes.findIndex(p => p.stage === targetStage);

    if (processIndex === -1) {
        showToast("Error finding application process.", "error");
        return;
    }

    const evaluatorProcess = processes[processIndex];
    isStartingReview.value = true;
    try {
        const response = await axios.post(`/evaluator/start-review/${evaluatorProcess.id}`);
        // Rebuild selectedUser.value to trigger Vue reactivity
        selectedUser.value = {
            ...selectedUser.value,
            application: {
                ...selectedUser.value.application,
                processes: processes.map((p, i) =>
                    i === processIndex ? { ...p, started_at: response.data.started_at } : p
                ),
            },
        };
        showToast("Review started successfully.", "success");
    } catch (error) {
        if (error.response?.status === 409) {
            // Already started — re-fetch user to sync UI with DB state
            try {
                const refetch = await axios.get(`/dashboard/user-files/${selectedUser.value.id}`);
                const userData = refetch.data.user;
                selectedUser.value = {
                    ...selectedUser.value,
                    ...userData,
                    application: {
                        ...userData.application,
                        processes: userData.application?.processes || [],
                        program: userData.application?.program || null,
                        second_choice: userData.application?.second_choice || null,
                        third_choice: userData.application?.third_choice || null,
                    },
                    grades: userData.grades || null,
                };
                showToast("Review was already started.", "success");
            } catch (refetchErr) {
                console.error("Refetch failed:", refetchErr);
            }
        } else {
            console.error("Error starting review:", error);
            showToast(error.response?.data?.message || "Failed to start review.", "error");
        }
    } finally {
        isStartingReview.value = false;
    }
};

const closeUserCard = () => { selectedUser.value = null; };

const formatFileKey = (key) => {
    const map = {
        file10Front: 'Grade 10 Report Front', file10: 'Grade 10 Report Back',
        file11Front: "Grade 11 Report Front", file11: "Grade 11 Report Back",
        file12Front: "Grade 12 Report Front", file12: "Grade 12 Report Back",
        schoolId: "School ID", nonEnrollCert: "Certificate of Non-Enrollment",
        psa: "PSA Birth Certificate", goodMoral: "Good Moral Certificate",
        underOath: "Under Oath Document", photo2x2: "2x2 Photo",
    };
    return map[key] || key;
};

const getFileUrl = (file) => (typeof file === "string" ? file : file?.url || "");
const hasImagePreview = (file) => Boolean(getFileUrl(file)) && (typeof file === "string" || file?.isImage !== false);

const previewImage = ref(null);
const showImageModal = ref(false);

const openImageModal = (file) => {
    const src = getFileUrl(file);
    if (!src || !hasImagePreview(file)) return;
    previewImage.value = src;
    showImageModal.value = true;
};

const closeImageModal = () => { showImageModal.value = false; };

const isEvaluating = ref(false);
const evaluationError = ref("");
const filesToReturn = ref({});
const returnNote = ref("");
const returnNoteCharCount = computed(() => {
    return returnNote.value.length;
});
const requiresPromissoryNote = ref(false);

const startEvaluation = () => {
    isEvaluating.value = true;
    evaluationError.value = "";
    filesToReturn.value = {};
    returnNote.value = "";
};
const cancelEvaluation = () => { isEvaluating.value = false; filesToReturn.value = {}; returnNote.value = ""; };

const promptFlag = () => {
    if (!returnNote.value.trim()) {
        evaluationError.value = "Please provide a reason.";
        showToast("Please provide a reason.", "error");
        return;
    }
    showFlagModal.value = true;
};

const isSubmitting = ref(false);

const submitFlag = async () => {
    evaluationError.value = "";
    const note = returnNote.value.trim();

    if (returnNoteCharCount.value > 400) {
        evaluationError.value = "The reason cannot exceed 400 characters.";
        showFlagModal.value = false;
        return;
    }

    isSubmitting.value = true;
    try {
        const currentUserId = selectedUser.value.id;
        const isGradeEvaluator = page.props.auth?.user?.role_id !== 3;

        await axios.post(`/evaluator/flag-application/${currentUserId}`, {
            note,
            requires_guidance_office: !isGradeEvaluator,
            requires_admission_office: isGradeEvaluator
        });

        showToast("Application flagged successfully!");

        showFlagModal.value = false;
        isEvaluating.value = false; filesToReturn.value = {}; returnNote.value = "";
        closeUserCard();
        await fetchUsers();
    } catch (error) {
        console.error(error);
        showFlagModal.value = false;
        const msg = error.response?.data?.message || error.response?.data?.errors?.note?.[0];
        evaluationError.value = msg || "Action failed. Please try again.";
    } finally {
        isSubmitting.value = false;
    }
};

const capitalize = (str) => typeof str === "string" ? str.charAt(0).toUpperCase() + str.slice(1) : "";
const formatStage = (stage) => {
    const map = {
        'evaluator': 'DE, GE',
        'interviewer': 'Interviewer',
        'medical': 'Medical',
        'record_staff': 'Record Staff'
    };
    return map[stage] || (stage ? stage.charAt(0).toUpperCase() + stage.slice(1).replace(/_/g, " ") : "");
};

const formatDate = (date) => { const d = new Date(date); return d.toLocaleString(); };

const formatDateOnly = (date) => {
    if (!date) return '—';
    const d = new Date(date);
    return d.toLocaleDateString('en-GB'); // DD/MM/YYYY
};

const submitPass = async () => {
    isSubmitting.value = true;
    try {
        const currentUserId = selectedUser.value.id;
        await axios.post(`/evaluator/pass-application/${currentUserId}`, {
            note: ""
        });
        showPassModal.value = false;
        isEvaluating.value = false;
        filesToReturn.value = {};
        returnNote.value = "";
        requiresPromissoryNote.value = false;
        showPassModal.value = false;
        closeUserCard();
        showToast("Applicant passed successfully!");
        await fetchUsers();
    } catch (error) {
        console.error("Error passing application:", error);
        showPassModal.value = false;
        evaluationError.value = error.response?.data?.message || "Failed to pass application.";
    } finally {
        isSubmitting.value = false;
    }
};

const showPassModal = ref(false);
const showFlagModal = ref(false);

// Toast notification state
const toastMessage = ref('');
const toastType = ref('success');
const toastVisible = ref(false);
let toastTimeout = null;

const showToast = (message, type = 'success') => {
    if (toastTimeout) clearTimeout(toastTimeout);
    toastMessage.value = message;
    toastType.value = type;
    toastVisible.value = true;
    toastTimeout = setTimeout(() => {
        toastVisible.value = false;
    }, 3000);
};

const totalPages = computed(() => Math.ceil(filteredUsers.value.length / itemsPerPage));

const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredUsers.value.slice(start, start + itemsPerPage);
});

watch([searchQuery, evaluationStatusFilter, sortKey, sortAsc], () => { currentPage.value = 1; });

const sortBy = (key) => {
    if (sortKey.value === key) { sortAsc.value = !sortAsc.value; }
    else { sortKey.value = key; sortAsc.value = true; }
};

const clearFilters = () => {
    searchQuery.value = ""; evaluationStatusFilter.value = "";
    sortKey.value = "lastname"; sortAsc.value = true;
    currentPage.value = 1; showStatusDropdown.value = false;
};
</script>