<template>
    <Head title="All Interviewer Applications" />
    <InterviewerLayout>
        <div class="max-w-9xl mx-auto p-6 px-2 sm:px-4 md:px-6 lg:px-8 overflow-x-hidden overflow-y-auto">
            <!-- Filters and Controls -->
            <div class="flex flex-col gap-3 mb-6">
                <!-- Search Input (full width always) -->
                <div class="relative w-full">
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
                        id="searchApplications"
                        name="searchApplications"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search by name..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                    />
                </div>

                <!-- Filter buttons row — wrap on mobile -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Status Filter Dropdown -->
                    <div class="relative">
                        <button
                            @click="showStatusDropdown = !showStatusDropdown"
                            class="px-4 py-2 min-h-[40px] border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium flex items-center space-x-2 whitespace-nowrap"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L15 12.414V19a1 1 0 01-1.447.894l-4-2A1 1 0 019 17v-4.586L3.293 6.707A1 1 0 013 6V4z" />
                            </svg>
                            <span>{{ evaluationStatusFilter ? getEvaluationStatusText({ pipeline_status: evaluationStatusFilter }) : 'All Status' }}</span>
                        </button>
                        <div
                            v-if="showStatusDropdown"
                            class="absolute top-full mt-2 left-0 bg-white dark:bg-gray-800 shadow-md border border-gray-200 rounded z-50 text-sm min-w-[200px] dark:border-gray-700"
                        >
                            <button class="block px-4 py-2 w-full text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                                @click="evaluationStatusFilter = ''; showStatusDropdown = false;">All</button>
                            <button class="block px-4 py-2 w-full text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                                @click="evaluationStatusFilter = 'for_interview'; showStatusDropdown = false;">For Interview</button>
                            <button class="block px-4 py-2 w-full text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                                @click="evaluationStatusFilter = 'interview_returned'; showStatusDropdown = false;">Returned for Revision</button>
                            <button class="block px-4 py-2 w-full text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                                @click="evaluationStatusFilter = 'interview_passed'; showStatusDropdown = false;">Interview Passed</button>
                            <button class="block px-4 py-2 w-full text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                                @click="evaluationStatusFilter = 'for_medical'; showStatusDropdown = false;">For Medical</button>
                            <button class="block px-4 py-2 w-full text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                                @click="evaluationStatusFilter = 'officially_enrolled'; showStatusDropdown = false;">Officially Enrolled</button>
                        </div>
                    </div>

                    <!-- Sort By -->
                    <div class="relative">
                        <button
                            @click="showSortDropdown = !showSortDropdown"
                            class="px-4 py-2 min-h-[40px] border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition font-medium flex items-center space-x-2 whitespace-nowrap text-sm"
                        >
                            <span>{{ sortKey === 'lastname' ? 'Last Name' : sortKey === 'firstname' ? 'First Name' : 'Course' }}</span>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div
                            v-if="showSortDropdown"
                            class="absolute top-full mt-2 left-0 bg-white dark:bg-gray-800 shadow-md border border-gray-200 dark:border-gray-700 rounded z-50 text-sm min-w-[160px]"
                        >
                            <button class="block px-4 py-2 w-full text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                                @click="sortKey = 'lastname'; showSortDropdown = false;">Last Name</button>
                            <button class="block px-4 py-2 w-full text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                                @click="sortKey = 'firstname'; showSortDropdown = false;">First Name</button>
                            <button class="block px-4 py-2 w-full text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                                @click="sortKey = 'program.name'; showSortDropdown = false;">Course</button>
                        </div>
                    </div>

                    <!-- Sort Order -->
                    <button
                        @click="sortAsc = !sortAsc"
                        class="px-4 py-2 min-h-[40px] border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium flex items-center space-x-2 whitespace-nowrap"
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
                        class="px-4 py-2 min-h-[40px] border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium whitespace-nowrap"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <!-- Users Table -->
            <div v-if="isLoading" class="text-center text-gray-500 py-8 dark:text-gray-300">Loading applicants…</div>
            <div v-else-if="errorMessage" class="text-center text-red-500 py-8 dark:text-red-300">Error: {{ errorMessage }}</div>

            <div v-else class="bg-white dark:bg-gray-800/20 rounded-xl shadow p-2 overflow-x-auto">
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    Showing {{ paginatedUsers.length }} of {{ filteredUsers.length }} users
                </div>
                
                <table class="w-full text-base table-fixed">
                    <thead>
                        <tr class="text-left font-semibold text-black dark:text-white">
                            <th class="pb-2 w-2/5 cursor-pointer hover:text-[#9E122C] dark:hover:text-white" @click="sortBy('lastname')">
                                Name
                                <span v-if="sortKey === 'lastname'" class="ml-1">{{ sortAsc ? '↑' : '↓' }}</span>
                            </th>
                            <th class="pb-2 w-2/5 cursor-pointer hover:text-[#9E122C] dark:hover:text-white" @click="sortBy('program.name')">
                                Course
                                <span v-if="sortKey === 'program.name'" class="ml-1">{{ sortAsc ? '↑' : '↓' }}</span>
                            </th>
                            <th class="pb-2 w-1/5">Status</th>
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
                                <div class="flex flex-col gap-1">
                                    <span class="break-words">{{ user.firstname }} {{ user.lastname }}</span>
                                    <span
                                        v-if="user.is_evaluation_completed"
                                        class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 text-xs rounded-full font-semibold self-start"
                                    >Evaluated</span>
                                </div>
                            </td>
                            <td class="py-3 text-gray-700 dark:text-gray-300 text-sm">
                                <span class="block break-words leading-snug">{{ user.program?.name || "—" }}</span>
                            </td>
                            <td class="py-3">
                                <span :class="getEvaluationStatusClass(user)" class="px-2 py-1 rounded-full text-xs font-medium inline-block text-center">
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
                <div class="px-4 py-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div class="text-sm text-gray-700 dark:text-gray-400">
                            <span v-if="!filteredUsers.length || filteredUsers.length === 0">Showing 0 to 0 of 0 results</span>
                            <span v-else>
                                Showing {{ (currentPage - 1) * itemsPerPage + 1 }}
                                to {{ Math.min(currentPage * itemsPerPage, filteredUsers.length) }}
                                of {{ filteredUsers.length }} results
                            </span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button :disabled="currentPage === 1" @click.prevent="currentPage--"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900 min-h-[40px]">
                                <svg class="h-5 w-5 sm:mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                                <span class="hidden sm:inline">Previous</span>
                            </button>
                            <div class="flex items-center space-x-2 mx-1 text-sm text-gray-700 dark:text-gray-300">
                                <span class="hidden sm:inline">Page</span>
                                <input type="number" id="pageNumberInput" name="pageNumberInput" :value="currentPage" min="1" :max="totalPages || 1"
                                    @change="currentPage = Math.max(1, Math.min($event.target.value, totalPages || 1))"
                                    class="w-14 px-2 py-1 text-center border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent font-medium text-sm" />
                                <span>of <span class="font-semibold">{{ totalPages || 1 }}</span></span>
                            </div>
                            <button :disabled="currentPage === totalPages || totalPages === 0" @click.prevent="currentPage++"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900 min-h-[40px]">
                                <span class="hidden sm:inline">Next</span>
                                <svg class="h-5 w-5 sm:ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applicant Details Modal -->
        <transition name="fade">
            <div v-if="selectedUser" class="fixed inset-0 z-50">
                <div class="fixed inset-0 bg-black/50" @click="closeUserCard"></div>
                <div class="relative min-h-screen flex items-center justify-center p-2 sm:p-4">
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
                                        App #{{ selectedUser.application?.id || 'N/A' }} · {{ selectedUser.reference_number || 'No ref' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <span :class="getStatusBadgeClass(selectedUser)" class="hidden sm:inline px-3 py-1 rounded-full text-xs font-semibold">
                                    {{ getEvaluationStatusText(selectedUser) }}
                                </span>
                                <button @click="closeUserCard"
                                    class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition min-h-[44px] min-w-[44px]" aria-label="Close">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Modal Body: 2-column layout -->
                        <div class="flex-1 overflow-hidden px-4 sm:px-6 py-5 flex flex-col">
                            <!-- Interview Completed Badge -->
                            <div v-if="isEvaluationCompleted"
                                class="shrink-0 flex items-start gap-3 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-xl mb-5">
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">Interview Completed</p>
                                    <p class="text-xs text-blue-600 dark:text-blue-400 mt-0.5">
                                        This interview has been completed
                                        <span v-if="getInterviewerName() !== '—'"> by {{ getInterviewerName() }}</span>.
                                        Actions are no longer available.
                                    </p>
                                </div>
                            </div>

                            <div class="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-12 gap-6">
                                <!-- Left Column: Info & Grades -->
                                <div class="lg:col-span-7 space-y-5 overflow-y-auto pr-2 pb-4 min-h-0">

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
                                            <div v-if="selectedUser.application?.requires_promissory_note" class="p-3 bg-orange-50 dark:bg-orange-900/20 rounded-xl border border-orange-200 dark:border-orange-700">
                                                <p class="text-xs text-orange-600 dark:text-orange-400 mb-0.5">Special Requirements</p>
                                                <p class="text-sm font-medium text-orange-700 dark:text-orange-300">📝 Promissory Note Required</p>
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

                                    <!-- Qualified Programs -->
                                    <div v-if="selectedUser.qualified_programs && selectedUser.qualified_programs.length > 0" class="mt-6">
                                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Qualified Programs</h4>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div v-for="prog in selectedUser.qualified_programs" :key="prog.id" class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-green-200 dark:border-green-800/60">
                                                <div class="flex items-start justify-between mb-3">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2 mb-0.5">
                                                            <span class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ prog.code }}</span>
                                                            <span class="px-1.5 py-0.5 text-[10px] font-semibold uppercase rounded-full bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300">Qualified</span>
                                                        </div>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ prog.name }}</p>
                                                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                            <span class="font-semibold">Strands:</span> {{ prog.strand_names || 'Open to All' }}
                                                        </p>
                                                        <div class="mt-1.5">
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded-lg bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                                Meets all requirements
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center flex-shrink-0 ml-2">
                                                        <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                    </div>
                                                </div>
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1.5">Requirements vs Grades</p>
                                                <div class="grid grid-cols-3 gap-1.5">
                                                    <div class="text-center p-2 rounded-lg border bg-green-50 dark:bg-green-900/10 border-green-100 dark:border-green-900/20">
                                                        <p class="text-[9px] font-semibold uppercase tracking-wide text-gray-400 mb-0.5">Math</p>
                                                        <p class="text-sm font-bold text-green-600 dark:text-green-400">{{ prog.your_grades.math }}</p>
                                                        <p class="text-[9px] text-gray-400">≥ {{ prog.requirements.math }}</p>
                                                    </div>
                                                    <div class="text-center p-2 rounded-lg border bg-green-50 dark:bg-green-900/10 border-green-100 dark:border-green-900/20">
                                                        <p class="text-[9px] font-semibold uppercase tracking-wide text-gray-400 mb-0.5">Science</p>
                                                        <p class="text-sm font-bold text-green-600 dark:text-green-400">{{ prog.your_grades.science }}</p>
                                                        <p class="text-[9px] text-gray-400">≥ {{ prog.requirements.science }}</p>
                                                    </div>
                                                    <div class="text-center p-2 rounded-lg border bg-green-50 dark:bg-green-900/10 border-green-100 dark:border-green-900/20">
                                                        <p class="text-[9px] font-semibold uppercase tracking-wide text-gray-400 mb-0.5">English</p>
                                                        <p class="text-sm font-bold text-green-600 dark:text-green-400">{{ prog.your_grades.english }}</p>
                                                        <p class="text-[9px] text-gray-400">≥ {{ prog.requirements.english }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Unqualified Programs -->
                                    <div v-if="selectedUser.unqualified_programs && selectedUser.unqualified_programs.length > 0" class="mt-6">
                                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Not Qualified Programs</h4>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div v-for="prog in selectedUser.unqualified_programs" :key="prog.id" class="p-4 bg-white dark:bg-gray-800 rounded-xl border border-red-200 dark:border-red-800/60 opacity-90">
                                                <div class="flex items-start justify-between mb-3">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-2 mb-0.5">
                                                            <span class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ prog.code }}</span>
                                                            <span class="px-1.5 py-0.5 text-[10px] font-semibold uppercase rounded-full bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300">Not Qualified</span>
                                                        </div>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ prog.name }}</p>
                                                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                            <span class="font-semibold">Strands:</span> {{ prog.strand_names || 'Open to All' }}
                                                        </p>
                                                        <div class="mt-1.5">
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded-lg bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300">
                                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                                <span v-if="!prog.meets_strand && !prog.meets_grades">Strand mismatch &amp; grades too low</span>
                                                                <span v-else-if="!prog.meets_strand">Strand mismatch</span>
                                                                <span v-else-if="!prog.meets_grades">Did not meet grade requirements</span>
                                                                <span v-else>Not qualified</span>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center flex-shrink-0 ml-2">
                                                        <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                    </div>
                                                </div>
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1.5">Requirements vs Grades</p>
                                                <div class="grid grid-cols-3 gap-1.5">
                                                    <div class="text-center p-2 rounded-lg border" :class="prog.your_grades.math >= prog.requirements.math ? 'bg-green-50 dark:bg-green-900/10 border-green-100 dark:border-green-900/20' : 'bg-red-50 dark:bg-red-900/10 border-red-100 dark:border-red-900/20'">
                                                        <p class="text-[9px] font-semibold uppercase tracking-wide text-gray-400 mb-0.5">Math</p>
                                                        <p class="text-sm font-bold" :class="prog.your_grades.math >= prog.requirements.math ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ prog.your_grades.math }}</p>
                                                        <p class="text-[9px] text-gray-400">≥ {{ prog.requirements.math }}</p>
                                                    </div>
                                                    <div class="text-center p-2 rounded-lg border" :class="prog.your_grades.science >= prog.requirements.science ? 'bg-green-50 dark:bg-green-900/10 border-green-100 dark:border-green-900/20' : 'bg-red-50 dark:bg-red-900/10 border-red-100 dark:border-red-900/20'">
                                                        <p class="text-[9px] font-semibold uppercase tracking-wide text-gray-400 mb-0.5">Science</p>
                                                        <p class="text-sm font-bold" :class="prog.your_grades.science >= prog.requirements.science ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ prog.your_grades.science }}</p>
                                                        <p class="text-[9px] text-gray-400">≥ {{ prog.requirements.science }}</p>
                                                    </div>
                                                    <div class="text-center p-2 rounded-lg border" :class="prog.your_grades.english >= prog.requirements.english ? 'bg-green-50 dark:bg-green-900/10 border-green-100 dark:border-green-900/20' : 'bg-red-50 dark:bg-red-900/10 border-red-100 dark:border-red-900/20'">
                                                        <p class="text-[9px] font-semibold uppercase tracking-wide text-gray-400 mb-0.5">English</p>
                                                        <p class="text-sm font-bold" :class="prog.your_grades.english >= prog.requirements.english ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ prog.your_grades.english }}</p>
                                                        <p class="text-[9px] text-gray-400">≥ {{ prog.requirements.english }}</p>
                                                    </div>
                                                </div>
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
                                <div class="lg:col-span-5 space-y-5 overflow-y-auto pr-2 pb-4 min-h-0">

                                    <!-- Interviewer Actions -->
                                    <div v-if="!isEvaluationCompleted" class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Interview Actions</h4>
                                        <div class="space-y-3">
                                            <div>
                                                <label for="programSelect" class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                                                    Select Program for Interview
                                                </label>
                                                <select
                                                    id="programSelect"
                                                    v-model="selectedProgramId"
                                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2.5 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                                >
                                                    <option disabled value="">Select Program</option>
                                                    <option
                                                        v-for="p in props.assignedPrograms"
                                                        :key="p.id"
                                                        :value="p.id"
                                                    >
                                                        {{ p.code }} - {{ p.name }}
                                                    </option>
                                                </select>
                                            </div>

                                            <div v-if="interviewStartTime">
                                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                                                    Comments/Notes (Optional)
                                                </label>
                                                <textarea
                                                    v-model="interviewNotes"
                                                    rows="3"
                                                    placeholder="Add any additional notes or comments here..."
                                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2.5 text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent resize-none"
                                                ></textarea>
                                            </div>

                                            <div v-if="!interviewStartTime">
                                                <button
                                                    @click="beginInterview"
                                                    class="w-full px-4 py-2.5 rounded-lg font-medium transition flex items-center justify-center gap-2 bg-[#9E122C] text-white hover:bg-[#b51834]"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                    Begin Interview
                                                </button>
                                            </div>

                                            <div v-else class="space-y-3">
                                                <div class="p-3 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 rounded-lg text-sm flex items-center justify-between border border-blue-200 dark:border-blue-800">
                                                    <div class="flex items-center gap-2">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                        Interview in progress since {{ new Date(interviewStartTime).toLocaleTimeString() }}
                                                    </div>
                                                    <button @click="cancelInterview" :disabled="isCancellingInterview" class="text-xs font-semibold hover:underline text-red-600 dark:text-red-400 disabled:opacity-50">
                                                        {{ isCancellingInterview ? 'Cancelling...' : 'Cancel' }}
                                                    </button>
                                                </div>
                                                <div class="flex gap-2 pt-1">
                                                <button
                                                    @click="promptAccept"
                                                    :class="[getButtonClass('success'), 'flex-1 px-4 py-2.5 rounded-lg transition font-medium flex items-center justify-center gap-2']"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    Accept
                                                </button>
                                                <button
                                                    @click="promptReject"
                                                    :class="[getButtonClass('danger'), 'flex-1 px-4 py-2.5 rounded-lg transition font-medium flex items-center justify-center gap-2']"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                    Reject
                                                </button>
                                            </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Interview Completed Summary (when already evaluated) -->
                                    <div v-else class="p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                                        <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Interview Result</h4>
                                        <div class="space-y-2 text-sm">
                                            <div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Program</p>
                                                <p class="font-medium text-gray-900 dark:text-white">{{ selectedUser.application?.program?.code || "—" }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Interviewer</p>
                                                <p class="font-medium text-gray-900 dark:text-white">{{ getInterviewerName() }}</p>
                                            </div>
                                            <div v-if="selectedUser.application?.requires_promissory_note" class="pt-2 border-t border-gray-200 dark:border-gray-700">
                                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300">
                                                    📝 Promissory Note Required
                                                </span>
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
                                                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate" :title="formatFileKey(key)">{{ formatFileKey(key) }}</span>
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
            </div>
        </transition>

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

        <!-- Snackbar Notification -->
        <transition name="fade">
            <div
                v-if="snackbar.visible"
                data-testid="snackbar"
                :class="[
                    'fixed bottom-8 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-lg shadow-lg text-white font-medium z-50',
                    snackbar.type === 'success' ? 'bg-green-600' : '',
                    snackbar.type === 'error' ? 'bg-red-600' : '',
                    snackbar.type === 'info' ? 'bg-blue-600' : ''
                ]"
            >
                {{ snackbar.message }}
            </div>
        </transition>
        <!-- Confirmation Modals -->
        <ChangesConfirmationModal
            :show="showAcceptModal"
            :loading="isSubmitting"
            title="Accept Application"
            subtitle="Accept this application to the selected program? This cannot be undone."
            confirm-text="Confirm Accept"
            confirm-button-class="bg-green-600 hover:bg-green-700 text-white"
            :hide-table="true"
            @cancel="showAcceptModal = false"
            @confirm="acceptApplication"
        />

        <ChangesConfirmationModal
            :show="showRejectModal"
            :loading="isSubmitting"
            title="Reject Application"
            subtitle="Reject this application for the selected program? This cannot be undone."
            confirm-text="Confirm Reject"
            confirm-button-class="bg-red-600 hover:bg-red-700 text-white"
            :hide-table="true"
            @cancel="showRejectModal = false"
            @confirm="rejectApplication"
        />

    </InterviewerLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import InterviewerLayout from "@/Layouts/InterviewerLayout.vue";
import ChangesConfirmationModal from '@/Components/ChangesConfirmationModal.vue';

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
    user: Object,
    assignedPrograms: Array,
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
const showSortDropdown = ref(false);
const filterDropdownRef = ref(null);

const page = usePage();
const users = ref(page.props.users || []);

const selectedUser = ref(null);
const isLoading = ref(true);
const errorMessage = ref("");
const searchQuery = ref("");
const selectedUserFiles = ref({});
const showAcceptModal = ref(false);
const showRejectModal = ref(false);
const isSubmitting = ref(false);
const interviewNotes = ref("");
const interviewStartTime = ref(null);
const snackbar = ref({
    visible: false,
    message: "",
    type: "success",
});

const showSnackbar = (msg, type = "success", duration = 3000) => {
    snackbar.value.message = msg;
    snackbar.value.type = type;
    snackbar.value.visible = true;
    setTimeout(() => {
        snackbar.value.visible = false;
    }, duration);
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
        case 'for_interview': return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300';
        case 'interview_returned': return 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';
        case 'interview_passed': return 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300';
        default: return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400';
    }
};

const getStatusClass = (status) => {
    const s = (status || "").toLowerCase();
    if (s === "accepted") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "cleared_for_enrollment" || s === "officially_enrolled") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "submitted" || s === "pending") return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300";
    if (s === "rejected") return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300";
    if (s === "returned") return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300";
    return "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300";
};

const getButtonClass = (type) => {
    const classes = {
        primary: 'bg-[#9E122C] text-white hover:bg-[#b51834]',
        success: 'bg-green-600 text-white hover:bg-green-700',
        danger: 'bg-red-600 text-white hover:bg-red-700',
        secondary: 'border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
    };
    return classes[type] || classes.secondary;
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
        const response = await fetch("/interviewer-dashboard/applicants", {
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });
        if (!response.ok) throw new Error("Failed to fetch users");
        users.value = await response.json();

        // Auto-select user if selectedUserId prop was provided
        if (props.selectedUserId && !selectedUser.value) {
            const user = users.value.find(u => u.id == props.selectedUserId);
            if (user) {
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

onMounted(() => {
    fetchUsers();
    // fetchPrograms(); removed since assignedPrograms comes from props
    document.addEventListener('click', handleOutsideClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleOutsideClick);
});

const filteredUsers = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return users.value
        .filter((u) => {
            const fullName = `${u.firstname} ${u.lastname}`.toLowerCase();
            const matchesSearch = fullName.includes(q);
            const matchesEvaluationStatus = evaluationStatusFilter.value
                ? u.pipeline_status === evaluationStatusFilter.value
                : true;
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
            
            return sortAsc.value
                ? aVal.localeCompare(bVal)
                : bVal.localeCompare(aVal);
        });
});

const selectUser = async (user) => {
    try {
        // Open panel immediately with basic user data
        selectedUser.value = {
            ...user,
            grades: null,
            application: user.application || null,
        };
        
        // Show loading state for files
        selectedUserFiles.value = { loading: true };

        // Fetch full data in background
        const response = await axios.get(
            `/interviewer-dashboard/application/${user.id}`
        );

        // Update with full data
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

        // Check if there is an interview already in progress
        const interviewerInProgress = selectedUser.value.application?.processes?.find(
            p => p.stage === 'interviewer' && p.status === 'in_progress'
        );
        if (interviewerInProgress && interviewerInProgress.started_at) {
            interviewStartTime.value = interviewerInProgress.started_at.endsWith('Z') 
                ? interviewerInProgress.started_at 
                : interviewerInProgress.started_at + 'Z';
        } else {
            interviewStartTime.value = null;
        }

    } catch (error) {
        console.error("Failed to fetch user data:", error);
        
        if (error.response && error.response.status === 403) {
            showSnackbar("Unauthorized access. Application is not at the interviewer stage.", "error");
        } else {
            showSnackbar("Failed to load applicant data. Please try again.", "error");
        }
        
        selectedUserFiles.value = {};
        selectedUser.value = null;
    }
};

const closeUserCard = () => {
    selectedUser.value = null;
    interviewNotes.value = "";
    interviewStartTime.value = null;
};
const isCancellingInterview = ref(false);

const beginInterview = async () => {
    try {
        const response = await axios.post(`/interviewer-dashboard/start/${selectedUser.value.id}`);
        const startedAt = response.data.started_at;
        interviewStartTime.value = startedAt.endsWith('Z') ? startedAt : startedAt + 'Z';
    } catch (e) {
        console.error("Failed to start interview:", e);
        const msg = e.response?.data?.message || "Failed to start interview";
        showSnackbar(msg, "error");
    }
};

const cancelInterview = async () => {
    if (!confirm("Are you sure you want to cancel your current interview? Your progress will not be saved.")) return;

    isCancellingInterview.value = true;
    try {
        await axios.post(`/interviewer-dashboard/cancel/${selectedUser.value.id}`);
        // Update local state directly
        interviewStartTime.value = null;
        if (selectedUser.value && selectedUser.value.application) {
            const processes = selectedUser.value.application.processes;
            const idx = processes.findIndex(p => p.stage === 'interviewer');
            if (idx !== -1) {
                processes[idx].started_at = null;
                processes[idx].performed_by = null;
            }
        }
        showSnackbar("Interview cancelled.", "info");
    } catch (e) {
        console.error("Failed to cancel interview:", e);
        const msg = e.response?.data?.message || "Failed to cancel interview";
        showSnackbar(msg, "error");
    } finally {
        isCancellingInterview.value = false;
    }
};

// Check if the current user's interviewer process is completed
const isEvaluationCompleted = computed(() => {
    if (!selectedUser.value || !selectedUser.value.application?.processes) {
        return false;
    }
    const interviewerProcess = selectedUser.value.application.processes.find(
        p => p.stage === 'interviewer'
    );
    return interviewerProcess && interviewerProcess.status === 'completed';
});

const formatFileKey = (key) => {
    const map = {
        file10Front: 'Grade 10 Report Front',
        file10: 'Grade 10 Report Back',
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

const capitalize = (str) =>
    typeof str === "string" ? str.charAt(0).toUpperCase() + str.slice(1) : "";

const formatStage = (stage) => {
    const map = {
        'evaluator': 'DE, GE',
        'interviewer': 'Interviewer',
        'medical': 'Medical',
        'record_staff': 'Record Staff'
    };
    return map[stage] || (stage ? stage.charAt(0).toUpperCase() + stage.slice(1).replace(/_/g, " ") : "");
};

const getInterviewerName = () => {
    const interviewerProcess = selectedUser.value?.application?.processes?.find(
        p => p.stage === 'interviewer' && p.status === 'completed' && p.action === 'passed'
    );
    
    if (!interviewerProcess) {
        return '—';
    }
    
    if (typeof interviewerProcess.performed_by === 'object' && interviewerProcess.performed_by !== null) {
        if (interviewerProcess.performed_by.firstname && interviewerProcess.performed_by.lastname) {
            return `${interviewerProcess.performed_by.firstname} ${interviewerProcess.performed_by.lastname}`;
        }
    }
    
    if (interviewerProcess.performedBy?.firstname && interviewerProcess.performedBy?.lastname) {
        return `${interviewerProcess.performedBy.firstname} ${interviewerProcess.performedBy.lastname}`;
    }
    
    if (interviewerProcess.performed_by_user?.firstname && interviewerProcess.performed_by_user?.lastname) {
        return `${interviewerProcess.performed_by_user.firstname} ${interviewerProcess.performed_by_user.lastname}`;
    }
    
    if (typeof interviewerProcess.performed_by === 'number') {
        return `User ID: ${interviewerProcess.performed_by}`;
    }
    
    return '—';
};

const formatDate = (date) => {
    const d = new Date(date);
    return d.toLocaleString();
};

const formatDateOnly = (date) => {
    if (!date) return '—';
    const d = new Date(date);
    return d.toLocaleDateString('en-GB'); // DD/MM/YYYY
};

const selectedProgramId = ref("");
const requiresPromissoryNote = ref(false);

const promptAccept = () => {
    if (!selectedProgramId.value) {
        showSnackbar("Please select a program to accept the applicant into", "error");
        return;
    }
    showAcceptModal.value = true;
};

const acceptApplication = async () => {
    isSubmitting.value = true;
    const currentUserId = selectedUser.value.id;
    try {
        await axios.post(
            `/interviewer-dashboard/accept/${currentUserId}`,
            {
                program_id: selectedProgramId.value,
                requires_promissory_note: requiresPromissoryNote.value,
                notes: interviewNotes.value,
                start_time: interviewStartTime.value,
            }
        );
        showSnackbar("Application accepted successfully", "success");
        selectedProgramId.value = "";
        requiresPromissoryNote.value = false;
        showAcceptModal.value = false;
        
        await fetchUsers();
        
        const updatedUser = users.value.find(u => u.id === currentUserId);
        if (updatedUser) {
            await selectUser(updatedUser);
        } else {
            selectedUser.value = null;
        }
    } catch (e) {
        console.error("Accept failed:", e);
        const msg =
            e.response?.data?.message ||
            "Failed to accept application due to an unexpected error.";
        showSnackbar(msg, "error");
    } finally {
        isSubmitting.value = false;
    }
};

const promptReject = () => {
    if (!selectedProgramId.value) {
        showSnackbar("Please select a program to reject the applicant from", "error");
        return;
    }
    showRejectModal.value = true;
};

const rejectApplication = async () => {
    isSubmitting.value = true;
    const currentUserId = selectedUser.value.id;
    try {
        await axios.post(
            `/interviewer-dashboard/reject/${currentUserId}`,
            {
                program_id: selectedProgramId.value,
                notes: interviewNotes.value,
                start_time: interviewStartTime.value,
            }
        );
        showSnackbar("Application rejected successfully", "success");
        selectedProgramId.value = "";
        showRejectModal.value = false;
        
        await fetchUsers();
        
        const updatedUser = users.value.find(u => u.id === currentUserId);
        if (updatedUser) {
            await selectUser(updatedUser);
        } else {
            selectedUser.value = null;
        }
    } catch (e) {
        console.error("Reject failed:", e);
        const msg =
            e.response?.data?.message ||
            "Failed to reject application due to an unexpected error.";
        showSnackbar(msg, "error");
    } finally {
        isSubmitting.value = false;
    }
};



const totalPages = computed(() =>
    Math.ceil(filteredUsers.value.length / itemsPerPage)
);

const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredUsers.value.slice(start, start + itemsPerPage);
});

watch([searchQuery, evaluationStatusFilter, sortKey, sortAsc], () => {
    currentPage.value = 1;
});

const sortBy = (key) => {
    if (sortKey.value === key) {
        sortAsc.value = !sortAsc.value;
    } else {
        sortKey.value = key;
        sortAsc.value = true;
    }
};

const clearFilters = () => {
    searchQuery.value = "";
    evaluationStatusFilter.value = "";
    sortKey.value = "lastname";
    sortAsc.value = true;
    currentPage.value = 1;
    showStatusDropdown.value = false;
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
</style>