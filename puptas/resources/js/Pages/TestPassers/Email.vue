<template>
    <Head title="PUPCET Passers Email" />
    <div
        ref="scrollWrapper"
        class="scroll-wrapper"
        tabindex="0"
        @scroll="handleScroll"
    >
        <AppLayout>
            <!-- Header -->
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">PUPCET Passers Email System</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                        Send personalized emails to successful PUPCET applicants
                    </p>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Panel: Controls & Filters -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Filters card -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-5">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Filters &amp; Controls</span>
                            <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-1 rounded-full">
                                {{ filteredPassers.length }} passers
                            </span>
                        </div>

                        <!-- Search -->
                        <div class="relative mb-3">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input
                                type="text"
                                v-model="searchTerm"
                                @input="onSearchInput"
                                placeholder="Search by name, surname, or email…"
                                class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                            />
                        </div>

                        <!-- Dropdowns -->
                        <div class="flex gap-3 flex-wrap">
                            <select v-model="filterSchoolYear"
                                class="flex-1 min-w-[130px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#9E122C]">
                                <option value="">All Years</option>
                                <option v-for="year in schoolYears" :key="year" :value="year">{{ year }}</option>
                            </select>
                            <select v-model="filterBatchNumber"
                                class="flex-1 min-w-[130px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#9E122C]">
                                <option value="">All Batches</option>
                                <option v-for="batch in batchNumbers" :key="batch" :value="batch">{{ batch }}</option>
                            </select>
                            <select v-model="filterPasserStatus"
                                class="flex-1 min-w-[130px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#9E122C]">
                                <option value="">All Statuses</option>
                                <option value="1">Qualified</option>
                                <option value="2">Waitlisted</option>
                                <option value="3">Unqualified</option>
                            </select>
                            <select v-model="sortKey"
                                class="flex-1 min-w-[130px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#9E122C]">
                                <option value="pupcet_total_score">Sort: PUPCET Score</option>
                                <option value="surname">Sort: Surname</option>
                                <option value="first_name">Sort: First Name</option>
                                <option value="email">Sort: Email</option>
                                <option value="schoolYear">Sort: School Year</option>
                                <option value="batchNumber">Sort: Batch</option>
                            </select>
                            <select v-model="sortOrder"
                                class="flex-1 min-w-[100px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#9E122C]">
                                <option value="asc">Ascending</option>
                                <option value="desc">Descending</option>
                            </select>
                        </div>

                        <!-- Select All & Actions -->
                        <div class="flex items-center gap-3 mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                            <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600 dark:text-gray-400">
                                <input type="checkbox" :checked="areAllSelected" @change="toggleSelectAll($event.target.checked)"
                                    class="h-4 w-4 rounded text-[#9E122C] border-gray-300 focus:ring-[#9E122C]" />
                                Select All ({{ selectedPassers.length }})
                            </label>
                            <button
                                @click.prevent="openAddModal"
                                class="px-4 py-2 bg-[#9E122C] text-white rounded-xl text-sm hover:bg-[#800918] transition">
                                + Add Passer
                            </button>
                            <button
                                v-if="selectedPassers.length > 0"
                                @click.prevent="confirmBulkDelete"
                                class="px-4 py-2 bg-red-600 text-white rounded-xl text-sm hover:bg-red-700 transition">
                                Delete ({{ selectedPassers.length }})
                            </button>
                        </div>
                    </div>

                    <!-- Passers Table Card -->
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden dark:bg-gray-800">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-200">
                                    Selected Passers
                                </h2>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    Page {{ currentPage }} of {{ totalPages }}
                                    • Showing {{ paginatedPassers.length }} items
                                </div>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                     <tr>
                                         <th class="px-6 py-4 text-left">
                                             <input
                                                 type="checkbox"
                                                 :checked="areAllSelected"
                                                 @change="toggleSelectAll($event.target.checked)"
                                                 class="h-5 w-5 text-[#9E122C] border-gray-300 rounded focus:ring-[#9E122C] dark:text-white dark:border-gray-600"
                                             />
                                         </th>
                                         <th class="px-3 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400 w-16">
                                             Rank
                                         </th>
                                         <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400">
                                             Name
                                         </th>
                                         <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400">
                                             Contact
                                         </th>
                                         <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400">
                                             PUPCET Score
                                         </th>
                                         <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400">
                                             Details
                                         </th>
                                         <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400">
                                             Status
                                         </th>
                                         <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400">
                                             Actions
                                         </th>
                                     </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                     <!-- Empty State Row -->
                                     <tr v-if="filteredPassers.length === 0" class="bg-gray-50 dark:bg-gray-900">
                                         <td colspan="8" class="px-6 py-12 text-center">
                                             <div class="flex flex-col items-center justify-center space-y-3">
                                                 <svg class="h-12 w-12 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                 </svg>
                                                 <div class="text-lg font-medium text-gray-900 dark:text-gray-200">
                                                     No passers match your current filters
                                                 </div>
                                                 <div class="text-sm text-gray-500 dark:text-gray-400">
                                                     Try adjusting your search term, school year, batch, or status filter to see more results.
                                                 </div>
                                             </div>
                                         </td>
                                     </tr>
                                     <!-- Regular Passer Rows -->
                                     <tr 
                                         v-for="(passer, pageIndex) in paginatedPassers" 
                                         :key="passer.test_passer_id"
                                         class="hover:bg-gray-50 transition dark:hover:bg-gray-900"
                                         v-else
                                     >
                                         <td class="px-6 py-4 whitespace-nowrap">
                                             <input
                                                 type="checkbox"
                                                 :value="passer.test_passer_id"
                                                 v-model="selectedPassers"
                                                 class="h-5 w-5 text-[#9E122C] border-gray-300 rounded focus:ring-[#9E122C] dark:text-white dark:border-gray-600"
                                             />
                                         </td>
                                         <!-- Rank cell: global rank across all filtered passers -->
                                         <td class="px-3 py-4 whitespace-nowrap text-center text-sm text-gray-600 dark:text-gray-400">
                                             {{ getGlobalRank(passer, pageIndex) }}
                                         </td>
                                         <td class="px-6 py-4">
                                             <div>
                                                 <div class="font-medium text-gray-900 dark:text-gray-200">
                                                     {{ passer.surname }}, {{ passer.first_name }}
                                                 </div>
                                                 <div v-if="passer.middle_name" class="text-sm text-gray-500 dark:text-gray-300">
                                                     {{ passer.middle_name }}
                                                 </div>
                                             </div>
                                         </td>
                                         <td class="px-6 py-4">
                                             <div class="text-gray-900 dark:text-gray-200">{{ passer.email }}</div>
                                             <div v-if="passer.reference_number" class="text-sm text-gray-500 dark:text-gray-300">
                                                 Ref: {{ passer.reference_number }}
                                             </div>
                                         </td>
                                         <!-- PUPCET Score cell -->
                                         <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                             <span v-if="passer.pupcet_total_score !== null && passer.pupcet_total_score !== undefined">
                                                 {{ Number(passer.pupcet_total_score).toFixed(2) }}
                                             </span>
                                             <span v-else class="text-gray-400 dark:text-gray-500">—</span>
                                         </td>
                                         <td class="px-6 py-4">
                                             <div class="flex flex-wrap gap-2">
                                                 <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-300">
                                                     SY: {{ passer.school_year }}
                                                 </span>
                                                 <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-300">
                                                     {{ passer.batch_number }}
                                                 </span>
                                             </div>
                                         </td>
                                         <!-- Status Column -->
                                         <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                             <span v-if="passer.passer_status_id === 1" class="px-3 py-1 bg-green-100 text-green-800 rounded-full dark:bg-green-900 dark:text-green-200">
                                                 Qualified
                                             </span>
                                             <span v-else-if="passer.passer_status_id === 2" class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full dark:bg-yellow-900 dark:text-yellow-200">
                                                 Waitlisted
                                             </span>
                                             <span v-else-if="passer.passer_status_id === 3" class="px-3 py-1 bg-red-100 text-red-800 rounded-full dark:bg-red-900 dark:text-red-200">
                                                 Unqualified
                                             </span>
                                             <span v-else class="px-3 py-1 bg-gray-200 text-gray-500 rounded-full dark:bg-gray-700 dark:text-gray-400">
                                                 Pending
                                             </span>
                                         </td>
                                         <td class="px-6 py-4 whitespace-nowrap">
                                             <div class="flex items-center gap-2">
                                                 <button
                                                     @click.prevent="openEditModal(passer)"
                                                     class="inline-flex items-center p-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200 transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                                                     title="Edit Passer"
                                                 >
                                                     <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                     </svg>
                                                 </button>
                                                 <button
                                                     @click.prevent="confirmDelete(passer)"
                                                     class="inline-flex items-center p-2 border border-red-300 rounded-lg text-red-600 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-200 transition dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20"
                                                     title="Delete Passer"
                                                 >
                                                     <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                     </svg>
                                                 </button>
                                             </div>
                                         </td>
                                     </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700 dark:text-gray-400">
                                    <span v-if="filteredPassers.length === 0">
                                        Showing 0 to 0 of 0 results
                                    </span>
                                    <span v-else>
                                        Showing {{ Math.min((currentPage - 1) * itemsPerPage + 1, filteredPassers.length) }} 
                                        to {{ Math.min(currentPage * itemsPerPage, filteredPassers.length) }} 
                                        of {{ filteredPassers.length }} results
                                    </span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button
                                        :disabled="currentPage === 1"
                                        @click.prevent="currentPage--"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                                    >
                                        <svg class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                        </svg>
                                        Previous
                                    </button>
                                    <div class="flex items-center space-x-1">
                                        <button
                                            v-for="page in visiblePages"
                                            :key="page"
                                            @click.prevent="currentPage = page"
                                            :class="[
                                                'px-3 py-1 rounded-lg text-sm font-medium transition',
                                                currentPage === page 
                                                    ? 'bg-[#9E122C] text-white' 
                                                    : 'text-gray-700 hover:bg-gray-100'
                                            ]"
                                        >
                                            {{ page }}
                                        </button>
                                        <span v-if="totalPages > 5 && currentPage < totalPages - 2" class="px-2 text-gray-500 dark:text-gray-300">
                                            ...
                                        </span>
                                    </div>
                                    <button
                                        :disabled="currentPage === totalPages"
                                        @click.prevent="currentPage++"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                                    >
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

                <!-- Right Panel: Email Template -->
                <div class="space-y-6">
                    <!-- Template Selection Card -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 dark:bg-gray-800">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4 dark:text-gray-200">
                            Email Template
                        </h2>
                        
                        <!-- Template Type Selector -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3 dark:text-gray-400">
                                Select Template Type
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="type in templateTypes"
                                    :key="type.value"
                                    @click="templateType = type.value"
                                    :class="[
                                        'py-3 px-4 rounded-xl text-sm font-medium transition-all duration-200',
                                        templateType === type.value
                                            ? 'bg-[#9E122C] text-white shadow-md'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                    ]"
                                >
                                    {{ type.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Waitlisted Template Editor -->
                        <div v-if="templateType === 'waitlisted'" class="mt-4">
                            <!-- Waitlisted Template Preview -->
                            <label class="block text-sm font-medium text-gray-700 mb-3 dark:text-gray-400">
                                Waitlisted Template Preview
                            </label>
                            <div class="border border-gray-200 rounded-xl p-4 bg-gray-50 max-h-[300px] overflow-y-auto dark:border-gray-700 dark:bg-gray-900">
                                <div v-html="waitlistedTemplatePreview"></div>
                            </div>

                            <label class="block text-sm font-medium text-gray-700 mt-4 mb-3 dark:text-gray-400">
                                Custom Message (Optional)
                            </label>
                            <div class="border border-gray-300 rounded-xl overflow-hidden dark:border-gray-600">
                                <QuillEditor
                                    v-model="emailTemplate"
                                    style="min-height: 300px"
                                    theme="snow"
                                    toolbar="full"
                                    placeholder="Enter additional message for waitlisted applicants (content will be provided by Ma'am Dianne)..."
                                />
                            </div>
                        </div>

                        <!-- Waitlisted Cut-off Template Preview -->
                        <div v-else-if="templateType === 'waitlisted-cutoff'" class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-3 dark:text-gray-400">
                                Waitlisted (Below Cut-off) Template Preview
                            </label>
                            <div class="border border-gray-200 rounded-xl p-4 bg-gray-50 max-h-[300px] overflow-y-auto dark:border-gray-700 dark:bg-gray-900">
                                <div v-html="waitlistedCutoffTemplatePreview"></div>
                            </div>
                        </div>

                        <!-- Custom Template Editor -->
                        <div v-else-if="templateType === 'custom'" class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-3 dark:text-gray-400">
                                Custom Email Template
                            </label>
                            <div class="border border-gray-300 rounded-xl overflow-hidden dark:border-gray-600">
                                <QuillEditor
                                    v-model="emailTemplate"
                                    style="min-height: 300px"
                                    theme="snow"
                                    toolbar="full"
                                />
                            </div>
                        </div>

                        <!-- Default Template Preview -->
                        <div v-else class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-3 dark:text-gray-400">
                                Default Template Preview
                            </label>
                            <div class="border border-gray-200 rounded-xl p-4 bg-gray-50 max-h-[300px] overflow-y-auto dark:border-gray-700 dark:bg-gray-900">
                                <div v-html="defaultTemplatePreview"></div>
                            </div>
                        </div>

                        <!-- Send Button -->
                        <button
                            type="button"
                            @click="sendEmails"
                            :disabled="!selectedPassers.length || !emailTemplate"
                            :class="[
                                'w-full mt-6 py-4 rounded-xl font-semibold text-lg transition-all duration-200',
                                selectedPassers.length && emailTemplate
                                    ? 'bg-[#9E122C] hover:bg-[#800918] text-white shadow-lg hover:shadow-xl transform hover:-translate-y-0.5'
                                    : 'bg-gray-200 text-gray-500 cursor-not-allowed'
                            ]"
                        >
                            <div class="flex items-center justify-center">
                                <svg class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                Send Emails to {{ selectedPassers.length }} Passer{{ selectedPassers.length !== 1 ? 's' : '' }}
                            </div>
                        </button>
                    </div>

                    <!-- Statistics Card -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 dark:bg-gray-800">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4 dark:text-gray-200">
                            Statistics
                        </h2>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl dark:bg-gray-900">
                                <div class="flex items-center">
                                    <div class="p-2 bg-blue-100 rounded-lg mr-3 dark:bg-blue-800">
                                        <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Total Passers</div>
                                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-200">{{ flatPassers.length }}</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl dark:bg-gray-900">
                                <div class="flex items-center">
                                    <div class="p-2 bg-green-100 rounded-lg mr-3 dark:bg-green-800">
                                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Selected</div>
                                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-200">{{ selectedPassers.length }}</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl dark:bg-gray-900">
                                <div class="flex items-center">
                                    <div class="p-2 bg-purple-100 rounded-lg mr-3 dark:bg-purple-800">
                                        <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">Filtered</div>
                                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-200">{{ filteredPassers.length }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SAR History Card -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 dark:bg-gray-800">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-200">
                                SAR History
                            </h2>
                            <button
                                @click="loadSarHistory"
                                class="inline-flex items-center px-3 py-1.5 text-sm text-[#9E122C] hover:bg-[#9E122C]/10 rounded-lg transition dark:text-white"
                            >
                                <svg class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Refresh
                            </button>
                        </div>
                        
                        <div v-if="loadingSarHistory" class="text-center py-8">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[#9E122C]"></div>
                            <p class="text-gray-600 mt-2 dark:text-gray-400">Loading SAR history...</p>
                        </div>
                        
                        <div v-else-if="sarHistory.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-300">
                            <svg class="h-12 w-12 mx-auto text-gray-400 mb-2 dark:text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p>No SAR forms generated yet</p>
                        </div>
                        
                        <div v-else class="space-y-3 max-h-96 overflow-y-auto">
                            <div
                                v-for="sar in sarHistory"
                                :key="sar.id"
                                class="p-3 border border-gray-200 rounded-xl hover:bg-gray-50 transition dark:border-gray-700 dark:hover:bg-gray-900"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900 text-sm dark:text-gray-200">
                                            {{ sar.test_passer?.surname }}, {{ sar.test_passer?.first_name }}
                                        </div>
                                        <div class="text-xs text-gray-600 mt-1 dark:text-gray-400">
                                            Ref: {{ sar.test_passer?.reference_number }}
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1 dark:text-gray-300">
                                            {{ formatDate(sar.sent_at) }}
                                        </div>
                                    </div>
                                    <div class="flex gap-1 ml-2">
                                        <button
                                            @click="previewSar(sar.id)"
                                            class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition dark:text-blue-400 dark:hover:bg-blue-900"
                                            title="Preview"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="downloadSar(sar.id)"
                                            class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg transition dark:text-green-400 dark:hover:bg-green-900"
                                            title="Download"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SAR Preview Modal -->
            <div
                v-if="showSarPreview"
                class="fixed inset-0 bg-black bg-opacity-75 flex justify-center items-center p-4 z-50 dark:bg-white"
                @click.self="closeSarPreview"
            >
                <div class="bg-white rounded-2xl max-w-6xl w-full h-[90vh] flex flex-col shadow-2xl dark:bg-gray-800">
                    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200">
                            SAR Form Preview
                        </h2>
                        <button
                            @click="closeSarPreview"
                            class="p-2 hover:bg-gray-100 rounded-lg transition dark:hover:bg-gray-800"
                        >
                            <svg class="h-6 w-6 text-gray-500 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-hidden">
                        <iframe
                            v-if="sarPreviewUrl"
                            :src="sarPreviewUrl"
                            class="w-full h-full border-0"
                        ></iframe>
                    </div>
                </div>
            </div>



            <!-- Bulk Enroll Result Modal -->
            <div
                v-if="showBulkEnrollResult"
                class="fixed inset-0 bg-black bg-opacity-60 flex justify-center items-center p-4 z-50"
                @click.self="showBulkEnrollResult = false"
            >
                <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-2xl w-full max-h-[85vh] flex flex-col shadow-2xl">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl">
                                <svg class="h-6 w-6 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Bulk Enrollment Results</h2>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ bulkEnrollResult.message }}</p>
                            </div>
                        </div>
                        <button @click="showBulkEnrollResult = false" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition">
                            <svg class="h-5 w-5 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="overflow-y-auto flex-1 p-5 space-y-4">
                        <!-- Success list -->
                        <div v-if="bulkEnrollResult.enrolled?.length">
                            <h3 class="text-sm font-semibold text-emerald-700 dark:text-emerald-400 mb-2 flex items-center gap-1">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                Successfully Enrolled ({{ bulkEnrollResult.enrolled.length }})
                            </h3>
                            <div class="space-y-1.5">
                                <div
                                    v-for="r in bulkEnrollResult.enrolled"
                                    :key="r.passer_id"
                                    class="flex items-center justify-between px-3 py-2 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg text-sm"
                                >
                                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ r.name }}</span>
                                    <span class="font-mono text-xs text-emerald-700 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/40 px-2 py-0.5 rounded">{{ r.student_number }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Error list -->
                        <div v-if="bulkEnrollResult.errors?.length">
                            <h3 class="text-sm font-semibold text-red-600 dark:text-red-400 mb-2 flex items-center gap-1">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                Failed ({{ bulkEnrollResult.errors.length }})
                            </h3>
                            <div class="space-y-1.5">
                                <div
                                    v-for="e in bulkEnrollResult.errors"
                                    :key="e.passer_id"
                                    class="px-3 py-2 bg-red-50 dark:bg-red-900/20 rounded-lg text-sm"
                                >
                                    <div class="font-medium text-gray-800 dark:text-gray-200">{{ e.name }} <span class="text-gray-500">({{ e.email }})</span></div>
                                    <div class="text-xs text-red-600 dark:text-red-400 mt-0.5">{{ e.reason }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-5 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                        <button
                            @click="showBulkEnrollResult = false"
                            class="px-5 py-2 bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-800 rounded-xl text-sm font-medium hover:opacity-90 transition"
                        >Close</button>
                    </div>
                </div>
            </div>

            <!-- Waitlisted Email Template Preview Modal -->
            <div
                v-if="showWaitlistedEmailPreview"
                class="fixed inset-0 bg-black bg-opacity-75 flex justify-center items-center p-4 z-50 dark:bg-white"
                @click.self="closeWaitlistedEmailPreview"
            >
                <div class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl dark:bg-gray-800">
                    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-200">
                            Waitlisted Email Template Preview
                        </h2>
                        <button
                            @click="closeWaitlistedEmailPreview"
                            class="p-2 hover:bg-gray-100 rounded-lg transition dark:hover:bg-gray-800"
                        >
                            <svg class="h-6 w-6 text-gray-500 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-auto p-6 bg-gray-50 dark:bg-gray-900">
                        <div v-if="loadingWaitlistedPreview" class="flex items-center justify-center h-full">
                            <div class="text-center">
                                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-yellow-600"></div>
                                <p class="text-gray-600 mt-4 dark:text-gray-400">Loading preview...</p>
                            </div>
                        </div>
                        <iframe
                            v-else-if="waitlistedEmailPreviewHtml"
                            :srcdoc="waitlistedEmailPreviewHtml"
                            class="w-full h-full border-0 bg-white rounded-lg shadow-sm dark:bg-gray-800"
                            style="min-height: 600px;"
                        ></iframe>
                    </div>
                </div>
            </div>

            <!-- Modals (Remain the same) -->
            <!-- Edit Modal -->
            <div
                v-if="showEditModal"
                class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center p-4 overflow-auto z-50 dark:bg-white"
            >
                <div
                    class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] p-6 relative shadow-2xl overflow-y-auto dark:bg-gray-800"
                >
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-200">
                            Edit Passer Details
                        </h2>
                        <button
                            @click="closeEditModal"
                            class="p-2 hover:bg-gray-100 rounded-lg transition dark:hover:bg-gray-800"
                        >
                            <svg class="h-6 w-6 text-gray-500 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form
                        @submit.prevent="savePasser"
                        class="grid grid-cols-2 gap-x-6 gap-y-4"
                    >
                        <!-- Personal Information -->
                        <div class="col-span-2 grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Surname *</label>
                                <input
                                    type="text"
                                    v-model="editingPasser.surname"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">First Name *</label>
                                <input
                                    type="text"
                                    v-model="editingPasser.first_name"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Middle Name</label>
                                <input
                                    type="text"
                                    v-model="editingPasser.middle_name"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="col-span-2 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Email *</label>
                                <input
                                    type="email"
                                    v-model="editingPasser.email"
                                    required
                                    readonly
                                    class="w-full px-4 py-3 bg-gray-100 text-gray-600 border border-gray-300 rounded-xl cursor-not-allowed focus:outline-none transition dark:bg-gray-700 dark:border-gray-600 dark:text-gray-400"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Date of Birth</label>
                                <input
                                    type="date"
                                    v-model="editingPasser.date_of_birth"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Address</label>
                            <input
                                type="text"
                                v-model="editingPasser.address"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                            />
                        </div>

                        <!-- School Information -->
                        <div class="col-span-2 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">SHS School</label>
                                <input
                                    type="text"
                                    v-model="editingPasser.shs_school"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">School Address</label>
                                <input
                                    type="text"
                                    v-model="editingPasser.school_address"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                        </div>

                        <div class="col-span-2 grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Strand</label>
                                <input
                                    type="text"
                                    v-model="editingPasser.strand"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Year Graduated</label>
                                <input
                                    type="text"
                                    v-model="editingPasser.year_graduated"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Reference Number</label>
                                <input
                                    type="text"
                                    v-model="editingPasser.reference_number"
                                    readonly
                                    class="w-full px-4 py-3 bg-gray-100 text-gray-600 border border-gray-300 rounded-xl cursor-not-allowed focus:outline-none transition dark:bg-gray-700 dark:border-gray-600 dark:text-gray-400"
                                />
                            </div>
                        </div>

                        <!-- Batch Information -->
                        <div class="col-span-2 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">School Year *</label>
                                <input
                                    type="text"
                                    v-model="editingPasser.school_year"
                                    required
                                    placeholder="e.g., 2023-2024"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Batch Number *</label>
                                <input
                                    type="text"
                                    v-model="editingPasser.batch_number"
                                    required
                                    placeholder="e.g., 1"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                        </div>

                        <!-- PUPCET Total Score and Status -->
                        <div class="col-span-2 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">PUPCET Total Score</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="999.99"
                                    v-model="editingPasser.pupcet_total_score"
                                    placeholder="e.g., 75.50"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                                <p class="text-xs text-gray-500 mt-1 dark:text-gray-400">Used to rank applicants from highest to lowest score.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Status *</label>
                                <select
                                    v-model="editingPasser.passer_status_id"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800"
                                >
                                    <option value="">Select Status</option>
                                    <option value="1">Qualified</option>
                                    <option value="2">Waitlisted</option>
                                    <option value="3">Unqualified</option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1 dark:text-gray-400">Current qualification status of the applicant.</p>
                            </div>
                        </div>

                        <!-- Modal Actions -->
                        <div class="col-span-2 flex justify-end space-x-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <button
                                type="button"
                                @click="closeEditModal"
                                class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200 transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="saving"
                                class="px-6 py-3 bg-[#9E122C] text-white rounded-xl hover:bg-[#800918] focus:outline-none focus:ring-2 focus:ring-[#9E122C]/50 transition disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-900 dark:text-gray-900"
                            >
                                <span v-if="saving">Saving...</span>
                                <span v-else>Save Changes</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Modal -->
            <div
                v-if="showAddModal"
                class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center p-4 overflow-auto z-50 dark:bg-white"
            >
                <div
                    class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] p-6 relative shadow-2xl overflow-y-auto dark:bg-gray-800"
                >
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-200">
                            Add New Passer
                        </h2>
                        <button
                            @click="closeAddModal"
                            class="p-2 hover:bg-gray-100 rounded-lg transition dark:hover:bg-gray-800"
                        >
                            <svg class="h-6 w-6 text-gray-500 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form
                        @submit.prevent="saveNewPasser"
                        class="grid grid-cols-2 gap-x-6 gap-y-4"
                    >
                        <!-- Personal Information -->
                        <div class="col-span-2 grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Surname *</label>
                                <input
                                    type="text"
                                    v-model="newPasserData.surname"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">First Name *</label>
                                <input
                                    type="text"
                                    v-model="newPasserData.first_name"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Middle Name</label>
                                <input
                                    type="text"
                                    v-model="newPasserData.middle_name"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="col-span-2 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Email *</label>
                                <input
                                    type="email"
                                    v-model="newPasserData.email"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Date of Birth</label>
                                <input
                                    type="date"
                                    v-model="newPasserData.date_of_birth"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Address</label>
                            <input
                                type="text"
                                v-model="newPasserData.address"
                                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                            />
                        </div>

                        <!-- School Information -->
                        <div class="col-span-2 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">SHS School</label>
                                <input
                                    type="text"
                                    v-model="newPasserData.shs_school"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">School Address</label>
                                <input
                                    type="text"
                                    v-model="newPasserData.school_address"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                        </div>

                        <div class="col-span-2 grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Strand</label>
                                <input
                                    type="text"
                                    v-model="newPasserData.strand"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Year Graduated</label>
                                <input
                                    type="text"
                                    v-model="newPasserData.year_graduated"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Reference Number</label>
                                <input
                                    type="text"
                                    v-model="newPasserData.reference_number"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                        </div>

                        <!-- Batch Information -->
                        <div class="col-span-2 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">School Year *</label>
                                <input
                                    type="text"
                                    v-model="newPasserData.school_year"
                                    required
                                    placeholder="e.g., 2023-2024"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Batch Number *</label>
                                <input
                                    type="text"
                                    v-model="newPasserData.batch_number"
                                    required
                                    placeholder="e.g., 1"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                        </div>

                        <!-- PUPCET Total Score and Status -->
                        <div class="col-span-2 grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">PUPCET Total Score</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="999.99"
                                    v-model="newPasserData.pupcet_total_score"
                                    placeholder="e.g., 75.50"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                                <p class="text-xs text-gray-500 mt-1 dark:text-gray-400">Used to rank applicants from highest to lowest score.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Status *</label>
                                <select
                                    v-model="newPasserData.passer_status_id"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800"
                                >
                                    <option value="">Select Status</option>
                                    <option value="1">Qualified</option>
                                    <option value="2">Waitlisted</option>
                                    <option value="3">Unqualified</option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1 dark:text-gray-400">Current qualification status of the applicant.</p>
                            </div>
                        </div>

                        <!-- Modal Actions -->
                        <div class="col-span-2 flex justify-end space-x-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <button
                                type="button"
                                @click="closeAddModal"
                                class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200 transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="saving"
                                class="px-6 py-3 bg-[#9E122C] text-white rounded-xl hover:bg-[#800918] focus:outline-none focus:ring-2 focus:ring-[#9E122C]/50 transition disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-900 dark:text-gray-900"
                            >
                                <span v-if="saving">Saving...</span>
                                <span v-else>Add Passer</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div
                v-if="showDeleteConfirm"
                class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center p-4 z-50"
                @click.self="showDeleteConfirm = false"
            >
                <div class="bg-white dark:bg-gray-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-xl">
                            <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Confirm Delete</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400" v-if="deleteTarget">
                                Delete <strong>{{ deleteTarget.first_name }} {{ deleteTarget.surname }}</strong>?
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400" v-else>
                                Delete <strong>{{ selectedPassers.length }}</strong> selected passer(s)?
                            </p>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">This action cannot be undone. Associated SAR records will also be removed.</p>
                    <div class="flex justify-end gap-3">
                        <button
                            @click="showDeleteConfirm = false"
                            class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700 transition"
                        >
                            Cancel
                        </button>
                        <button
                            @click="executeDelete"
                            :disabled="deleting"
                            class="px-5 py-2.5 bg-red-600 text-white rounded-xl hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        >
                            {{ deleting ? 'Deleting...' : 'Yes, Delete' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Snackbar -->
            <div
                v-if="snackbar.show"
                :class="[
                    'fixed z-50 top-4 right-4 px-6 py-4 rounded-xl shadow-lg text-white font-semibold animate-slide-in',
                    snackbar.type === 'success' ? 'bg-green-600' : 'bg-red-600'
                ]"
            >
                <div class="flex items-center">
                    <svg v-if="snackbar.type === 'success'" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg v-else class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ snackbar.message }}
                </div>
            </div>
        </AppLayout>
    </div>

    <!-- Scroll Navigation -->
    <div class="fixed right-4 top-1/2 -translate-y-1/2 space-y-2 z-30">
        <button
            v-show="showScrollUp"
            @click="scrollUp"
            class="bg-white hover:bg-gray-50 text-gray-700 p-3 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 flex items-center justify-center dark:bg-gray-800 dark:hover:bg-gray-900 dark:text-gray-400"
            aria-label="Scroll Up"
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
            </svg>
        </button>
        <button
            v-show="showScrollDown"
            @click="scrollDown"
            class="bg-white hover:bg-gray-50 text-gray-700 p-3 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 flex items-center justify-center dark:bg-gray-800 dark:hover:bg-gray-900 dark:text-gray-400"
            aria-label="Scroll Down"
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
        </button>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick} from "vue";
const axios = window.axios;
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head } from "@inertiajs/vue3";
import { QuillEditor } from "@vueup/vue-quill";
import "@vueup/vue-quill/dist/vue-quill.snow.css";
import { useGlobalLoading } from "@/Composables/useGlobalLoading";
import { useSnackbar } from "@/Composables/useSnackbar";

// Scroll functionality (unchanged)
const scrollWrapper = ref(null);
const scrollAmount = 200;
const showScrollUp = ref(false);
const showScrollDown = ref(true);

const scrollUp = () => {
    if (scrollWrapper.value) {
        scrollWrapper.value.scrollBy({
            top: -scrollAmount,
            behavior: "smooth",
        });
    }
};

const scrollDown = () => {
    if (scrollWrapper.value) {
        scrollWrapper.value.scrollBy({
            top: scrollAmount,
            behavior: "smooth"
        });
    }
};

const handleScroll = () => {
    if (!scrollWrapper.value) return;
    
    const scrollTop = scrollWrapper.value.scrollTop;
    const scrollHeight = scrollWrapper.value.scrollHeight;
    const clientHeight = scrollWrapper.value.clientHeight;

    showScrollUp.value = scrollTop > 10;
    showScrollDown.value = scrollTop < 10;

    if (scrollHeight <= clientHeight) {
        showScrollUp.value = false;
        showScrollDown.value = false;
    }
};

import { onMounted } from "vue";
onMounted(() => {
    handleScroll();
});

// Template types for selection
const templateTypes = [
    { label: 'Default', value: 'default' },
    { label: 'Custom', value: 'custom' },
    { label: 'Waitlisted', value: 'waitlisted' },
    { label: 'Waitlisted (Below Cut-off)', value: 'waitlisted-cutoff' }
];

// All existing functionality remains exactly the same
const { snackbar, show } = useSnackbar();

function debounce(fn, delay) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), delay);
    };
}

const { start, finish } = useGlobalLoading();

const props = defineProps({
    groupedPassers: Object,
    registrationUrl: String,
});

// Email template and settings (unchanged)
const emailTemplate = ref(
    `
  <div style="font-family: Arial, sans-serif; background-color: #f7f7f7; padding: 40px;">
    <div style="max-width: 600px; margin: auto; background-color: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);">
      <h1 style="color: #cc0000; text-align: center;">🎉 Congratulations, {{firstname}} {{surname}}!</h1>
      <p style="font-size: 16px; color: #333; text-align: center;">
        You have successfully passed the <strong>PUPCET</strong>! We are thrilled to welcome you as part of our growing community at Polytechnic University of the Philippines - Taguig Branch.
      </p>

      <div style="text-align: center; margin: 30px 0;">
        <a href="${props.registrationUrl}" 
           style="background-color: #cc0000; color: #FFD700; text-decoration: none; padding: 15px 25px; font-weight: bold; border-radius: 8px; display: inline-block;">
           Complete Your Registration
        </a>
      </div>

      <p style="font-size: 14px; color: #555; text-align: center;">
        If you have any questions or need help, please contact our admissions office.
      </p>
    </div>
  </div>
`.trim()
);

const templateType = ref("default");
const sarEnrollmentDate = ref(new Date().toISOString().split('T')[0]);
const sarEnrollmentTime = ref('09:00');
const defaultTemplatePreview = `
<div style="background:#f3f4f6;padding:40px 20px;font-family:Arial,Helvetica,sans-serif;">
  <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;padding:36px 40px;box-shadow:0 4px 16px rgba(0,0,0,0.08);">
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">Dear <strong><span style="color:#cc0000;font-weight:bold;">{{firstname}} {{surname}}</span></strong>,</p>    
    <p style="margin:0 0 12px 0;font-size:14px;color:#cc0000;font-weight:bold;line-height:1.6;">Congratulations! 🎉</p>
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">We are pleased to inform you that you qualify to be admitted to <strong>PUP-Taguig Campus</strong> for the First Semester of the Academic Year 2026-2027.</p>
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">You may choose a curricular program you intend to enroll in, subject to fulfillment of college requirements and the availability of slots.</p>
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">You may view the Admission Requirements here: <a href="https://drive.google.com/file/d/153oJlLhvU9UDjJ5JzFgA04aWurQ_PBbE/view" target="_blank" rel="noopener noreferrer" style="color:#1155cc;">2026 PUP-Taguig Campus Admission Criteria</a></p>
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">Please confirm your <strong>slot until June 10, 2026 (Wednesday)</strong>. You will receive an email within three working days containing the SAR-Form 1, your interview schedule; and other essential enrollment documents. Please print in long bond paper, sign, and bring them on the day of the interview. We encourage you to come on this date as enrollment is on a first-come, first-served basis. However, you will not be accommodated for enrollment if you come earlier than this date.</p>
    <div style="text-align:center;margin:24px 0;">
    <a 
        href="${props.registrationUrl}" 
        style="
        display:inline-block;
        padding:12px 24px;
        background:#9E122C;
        color:#ffffff;
        text-decoration:none;
        border-radius:6px;
        font-weight:bold;
        font-size:14px;
        "
    >
        CLICK TO CONFIRM YOUR INTERVIEW SLOT
    </a>
    </div>    
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">All PUPCET Passers and Waitlisted Applicants are also invited to attend the <strong>Career Orientation for the Incoming First-Year Students</strong> on June 8, 2026 (Monday), 2:00PM, via Facebook Live (<a href="https://www.facebook.com/PUPTOFFICIAL" target="_blank" rel="noopener noreferrer" style="color:#1155cc;">PUP – Taguig Facebook page</a>).</p>
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">Your enrollment will only be considered official when you bring the original documents with two photocopies on <strong>June 24, 2026 (Wednesday)</strong> and pass the interview. Incomplete requirements will not be entertained, so please ensure that you have all the necessary documents.</p>
    <p style="margin:0 0 24px 0;font-size:14px;color:#222;line-height:1.6;">Once again, congratulations on this remarkable achievement, and we look forward to meeting you at PUP-Taguig Campus!</p>
    <p style="margin:0 0 4px 0;font-size:14px;color:#222;">Regards,</p>
    <p style="margin:0;font-size:14px;font-weight:bold;color:#222;">PUP-Taguig Admission and Registration Office</p>
  </div>
</div>`.trim();

const waitlistedTemplatePreview = `
<div style="background:#f3f4f6;padding:40px 20px;font-family:Arial,Helvetica,sans-serif;">
  <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;padding:36px 40px;box-shadow:0 4px 16px rgba(0,0,0,0.08);">
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">Dear <strong><span style="color:#cc0000;font-weight:bold;">{{firstname}} {{surname}}</span></strong>,</p>    
    <p style="margin:0 0 12px 0;font-size:14px;color:#cc0000;font-weight:bold;line-height:1.6;">Congratulations! 🎉</p>
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">We are pleased to inform you that you qualify to be admitted to <strong>PUP-Taguig Campus</strong> for the First Semester of the Academic Year 2026-2027.</p>
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">You may choose a curricular program you intend to enroll in, subject to fulfillment of college requirements and the availability of slots.</p>
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">You may view the Admission Requirements here: <a href="https://drive.google.com/file/d/153oJlLhvU9UDjJ5JzFgA04aWurQ_PBbE/view" target="_blank" rel="noopener noreferrer" style="color:#1155cc;">2026 PUP-Taguig Campus Admission Criteria</a></p>
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">Please confirm your <strong>slot until June 10, 2026 (Wednesday)</strong>. You will receive an email within three working days containing the SAR-Form 1, your interview schedule; and other essential enrollment documents. Please print in long bond paper, sign, and bring them on the day of the interview. We encourage you to come on this date as enrollment is on a first-come, first-served basis. However, you will not be accommodated for enrollment if you come earlier than this date.</p>
    <div style="text-align:center;margin:24px 0;">
    <a 
        href="${props.registrationUrl}" 
        style="
        display:inline-block;
        padding:12px 24px;
        background:#9E122C;
        color:#ffffff;
        text-decoration:none;
        border-radius:6px;
        font-weight:bold;
        font-size:14px;
        "
    >
        CLICK TO CONFIRM YOUR INTERVIEW SLOT
    </a>
    </div>    
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">All PUPCET Passers and Waitlisted Applicants are also invited to attend the <strong>Career Orientation for the Incoming First-Year Students</strong> on June 8, 2026 (Monday), 2:00PM, via Facebook Live (<a href="https://www.facebook.com/PUPTOFFICIAL" target="_blank" rel="noopener noreferrer" style="color:#1155cc;">PUP – Taguig Facebook page</a>).</p>
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">Your enrollment will only be considered official when you bring the original documents with two photocopies on <strong>June 24, 2026 (Wednesday)</strong> and pass the interview. Incomplete requirements will not be entertained, so please ensure that you have all the necessary documents.</p>
    <p style="margin:0 0 24px 0;font-size:14px;color:#222;line-height:1.6;">Once again, congratulations on this remarkable achievement, and we look forward to meeting you at PUP-Taguig Campus!</p>
    <p style="margin:0 0 4px 0;font-size:14px;color:#222;">Regards,</p>
    <p style="margin:0;font-size:14px;font-weight:bold;color:#222;">PUP-Taguig Admission and Registration Office</p>
  </div>
</div>`.trim();

const waitlistedCutoffTemplatePreview = `
<div style="background:#f3f4f6;padding:40px 20px;font-family:Arial,Helvetica,sans-serif;">
  <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;padding:36px 40px;box-shadow:0 4px 16px rgba(0,0,0,0.08);">
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">Dear <strong><span style="color:#9E122C;font-weight:bold;">{{firstname}} {{surname}}</span></strong>,</p>
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">Thank you for considering <strong>PUP-Taguig Campus</strong> for your higher education.</p>
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">Based on evaluation, we regret to inform you that your score in the PUP College Entrance Test did not place you in the Top 500 requirement of the Campus.</p>
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">Nevertheless, you might still be notified (via email) of the possible remaining slots, based on your evaluated rank. Admission to these slots, however, shall be on a first-come, first-served basis, and subject to specific academic program admission requirements.</p>
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">Since we cannot give you an assurance that a slot will be made available to you, we recommend you to still consider your admission options in other higher education institutions.</p>
    <p style="margin:0 0 24px 0;font-size:14px;color:#222;line-height:1.6;">We hope that you will still be able to pursue your career plans and be successful in your academic endeavor.</p>
    <p style="margin:0 0 4px 0;font-size:14px;color:#222;">Regards,</p>
    <p style="margin:0;font-size:14px;font-weight:bold;color:#222;">PUP-Taguig Campus Admission and Registration Office</p>
  </div>
</div>`.trim();

const flatPassers = ref([]);

watch(
    () => props.groupedPassers,
    (newVal) => {
        const result = [];
        if (newVal) {
            for (const schoolYear in newVal) {
                for (const batchNumber in newVal[schoolYear]) {
                    newVal[schoolYear][batchNumber].forEach((passer) => {
                        result.push({
                            ...passer,
                            schoolYear,
                            batchNumber,
                        });
                    });
                }
            }
        }
        flatPassers.value = result;
    },
    { immediate: true }
);

const searchTerm = ref("");
const debouncedSearchTerm = ref("");
const filterSchoolYear = ref("");
const filterBatchNumber = ref("");
const filterPasserStatus = ref("");
const sortKey = ref("pupcet_total_score");
const sortOrder = ref("desc");
const currentPage = ref(1);
const itemsPerPage = 10;

const onSearchInput = debounce(() => {
    debouncedSearchTerm.value = searchTerm.value.toLowerCase();
    currentPage.value = 1;
}, 300);

watch([filterSchoolYear, filterBatchNumber, filterPasserStatus, sortKey, sortOrder], () => {
    currentPage.value = 1;
});

const schoolYears = computed(() => {
    const years = new Set(flatPassers.value.map((p) => p.schoolYear));
    return Array.from(years).sort();
});

const batchNumbers = computed(() => {
    const batches = new Set(flatPassers.value.map((p) => p.batchNumber));
    return Array.from(batches).sort();
});

const filteredPassers = computed(() => {
    return flatPassers.value.filter((passer) => {
        const search = debouncedSearchTerm.value;
        const matchesSearch =
            passer.surname.toLowerCase().includes(search) ||
            passer.first_name.toLowerCase().includes(search) ||
            passer.email.toLowerCase().includes(search);

        const matchesSchoolYear = filterSchoolYear.value
            ? passer.schoolYear === filterSchoolYear.value
            : true;

        const matchesBatch = filterBatchNumber.value
            ? passer.batchNumber === filterBatchNumber.value
            : true;

        const matchesStatus = filterPasserStatus.value
            ? passer.passer_status_id === parseInt(filterPasserStatus.value)
            : true;

        return matchesSearch && matchesSchoolYear && matchesBatch && matchesStatus;
    });
});

const sortedPassers = computed(() => {
    return filteredPassers.value.slice().sort((a, b) => {
        const key = sortKey.value;
        const valA = a[key];
        const valB = b[key];

        // Numeric sort for pupcet_total_score (nulls always last)
        if (key === 'pupcet_total_score') {
            const numA = valA === null || valA === undefined ? -Infinity : parseFloat(valA);
            const numB = valB === null || valB === undefined ? -Infinity : parseFloat(valB);
            return sortOrder.value === 'desc' ? numB - numA : numA - numB;
        }

        // String sort for everything else
        const strA = (valA ?? '').toString().toLowerCase();
        const strB = (valB ?? '').toString().toLowerCase();
        if (strA < strB) return sortOrder.value === 'asc' ? -1 : 1;
        if (strA > strB) return sortOrder.value === 'asc' ? 1 : -1;
        return 0;
    });
});

/**
 * Returns the 1-based global rank of a passer within the sortedPassers list.
 * Relies on sortedPassers being ordered by score DESC so rank 1 = highest score.
 */
function getGlobalRank(passer, pageIndex) {
    const globalIndex = (currentPage.value - 1) * itemsPerPage + pageIndex;
    return globalIndex + 1;
}

const totalPages = computed(() =>
    Math.ceil(sortedPassers.value.length / itemsPerPage)
);

// Pagination range for display
const visiblePages = computed(() => {
    const pages = [];
    const maxVisible = 5;
    
    if (totalPages.value <= maxVisible) {
        for (let i = 1; i <= totalPages.value; i++) {
            pages.push(i);
        }
    } else {
        let start = Math.max(1, currentPage.value - 2);
        let end = Math.min(totalPages.value, start + maxVisible - 1);
        
        if (end - start + 1 < maxVisible) {
            start = end - maxVisible + 1;
        }
        
        for (let i = start; i <= end; i++) {
            pages.push(i);
        }
    }
    
    return pages;
});

const paginatedPassers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return sortedPassers.value.slice(start, start + itemsPerPage);
});

const selectedPassers = ref([]);

// Fix: Check if ALL filtered passers are selected (not just current page)
const areAllSelected = computed(() => {
    if (!filteredPassers.value.length) return false;
    
    // Get all IDs from filtered passers
    const allFilteredIds = filteredPassers.value.map(p => p.test_passer_id);
    
    // Check if ALL filtered IDs are in selectedPassers
    return allFilteredIds.every(id => selectedPassers.value.includes(id));
});

const areAllSelectedOnCurrentPage = computed(() => {
    if (!paginatedPassers.value.length) return false;
    return paginatedPassers.value.every((p) =>
        selectedPassers.value.includes(p.test_passer_id)
    );
});

const toggleSelectAll = (isSelected) => {
    if (isSelected) {
        // Select ALL filtered passers (not just current page)
        const allFilteredIds = filteredPassers.value.map(p => p.test_passer_id);
        const newIds = allFilteredIds.filter(id => !selectedPassers.value.includes(id));
        selectedPassers.value.push(...newIds);
    } else {
        // Deselect ALL filtered passers
        const filteredIdsSet = new Set(filteredPassers.value.map(p => p.test_passer_id));
        selectedPassers.value = selectedPassers.value.filter(id => !filteredIdsSet.has(id));
    }
};

const sendEmails = async () => {
    let messageHtml;

    if (templateType.value === 'default') {
        messageHtml = defaultTemplatePreview;
    } else if (templateType.value === 'waitlisted') {
        messageHtml = waitlistedTemplatePreview;
    } else if (templateType.value === 'waitlisted-cutoff') {
        messageHtml = waitlistedCutoffTemplatePreview;
    } else {
        const quillContainer = document.querySelector(".ql-editor");
        messageHtml = quillContainer
            ? quillContainer.innerHTML
            : emailTemplate.value;
    }

    if (templateType.value === 'sar') {
        if (!sarEnrollmentDate.value || !sarEnrollmentTime.value) {
            show("Please set enrollment date and time for SAR forms.", "error");
            return;
        }
    }

    start();
    try {
        await axios.post("/test-passers/send-emails", {
            passer_ids: selectedPassers.value,
            message_template: messageHtml,
            template_type: templateType.value,
            enrollment_date: sarEnrollmentDate.value,
            enrollment_time: sarEnrollmentTime.value,
        });
        const successMsg = templateType.value === 'sar' 
            ? 'SAR PDFs generated and emails sent successfully!' 
            : 'Emails sent successfully!';
        show(successMsg, "success");
        selectedPassers.value = [];
    } catch (error) {
        show("Failed to send emails.", "error");
        console.error(error);
    } finally {
        finish();
    }
};

// Modal state (unchanged)
const showEditModal = ref(false);
const editingPasser = ref(null);
const saving = ref(false);

// Delete state
const showDeleteConfirm = ref(false);
const deleteTarget = ref(null);   // null = bulk, passer object = single
const deleting = ref(false);

function confirmDelete(passer) {
    deleteTarget.value = passer;
    showDeleteConfirm.value = true;
}

function confirmBulkDelete() {
    deleteTarget.value = null;
    showDeleteConfirm.value = true;
}

async function executeDelete() {
    deleting.value = true;
    start();
    try {
        if (deleteTarget.value) {
            // Single delete
            const id = deleteTarget.value.test_passer_id;
            await axios.delete(`/test-passers/${id}`);
            flatPassers.value = flatPassers.value.filter(p => p.test_passer_id !== id);
            selectedPassers.value = selectedPassers.value.filter(sid => sid !== id);
            show('Passer deleted successfully.', 'success');
        } else {
            // Bulk delete
            await axios.post('/test-passers/bulk-destroy', { passer_ids: selectedPassers.value });
            const deletedSet = new Set(selectedPassers.value);
            flatPassers.value = flatPassers.value.filter(p => !deletedSet.has(p.test_passer_id));
            show(`${selectedPassers.value.length} passer(s) deleted.`, 'success');
            selectedPassers.value = [];
        }
        showDeleteConfirm.value = false;
        deleteTarget.value = null;
    } catch (error) {
        show('Failed to delete passer(s).', 'error');
        console.error(error);
    } finally {
        finish();
        deleting.value = false;
    }
}

function openEditModal(passer) {
    editingPasser.value = { 
        ...passer,
        // Ensure passer_status_id is a string for the <select> v-model binding
        passer_status_id: passer.passer_status_id != null ? String(passer.passer_status_id) : '',
        pupcet_total_score: passer.pupcet_total_score != null ? passer.pupcet_total_score : '',
    };
    showEditModal.value = true;
}

function closeEditModal() {
    showEditModal.value = false;
    editingPasser.value = null;
}

async function savePasser() {
    saving.value = true;
    start();
    try {
        const response = await axios.put(
            `/test-passers/${editingPasser.value.test_passer_id}`,
            editingPasser.value
        );
        show("Passer updated successfully!", "success");

        // Normalize passer_status_id to integer for consistent comparisons
        const updatedData = {
            ...editingPasser.value,
            passer_status_id: editingPasser.value.passer_status_id 
                ? parseInt(editingPasser.value.passer_status_id) 
                : null,
            pupcet_total_score: editingPasser.value.pupcet_total_score !== '' && editingPasser.value.pupcet_total_score !== null
                ? parseFloat(editingPasser.value.pupcet_total_score)
                : null,
        };

        // Update the local data to reflect changes immediately
        const index = flatPassers.value.findIndex(
            (p) => p.test_passer_id === editingPasser.value.test_passer_id
        );
        if (index !== -1) {
            // Use Vue's reactivity by replacing the entire object
            flatPassers.value.splice(index, 1, { 
                ...flatPassers.value[index], 
                ...updatedData,
                // Ensure computed properties are updated
                schoolYear: updatedData.school_year,
                batchNumber: updatedData.batch_number
            });
        }

        // Clear selected passers if the updated passer no longer matches current filters
        // This ensures the UI stays consistent
        const updatedPasser = flatPassers.value[index];
        if (updatedPasser) {
            const matchesCurrentFilters = (
                (!filterSchoolYear.value || updatedPasser.schoolYear === filterSchoolYear.value) &&
                (!filterBatchNumber.value || updatedPasser.batchNumber === filterBatchNumber.value) &&
                (!filterPasserStatus.value || updatedPasser.passer_status_id === parseInt(filterPasserStatus.value))
            );
            
            if (!matchesCurrentFilters) {
                // Remove from selected if it no longer matches filters
                selectedPassers.value = selectedPassers.value.filter(
                    id => id !== editingPasser.value.test_passer_id
                );
            }
        }

        closeEditModal();
        
        // Ensure the UI updates immediately
        await nextTick();
    } catch (error) {
        show("Failed to update passer.", "error");
        console.error(error);
    } finally {
        finish();
        saving.value = false;
    }
}

const showAddModal = ref(false);
const newPasserData = ref({});

function openAddModal() {
    newPasserData.value = {
        surname: "",
        first_name: "",
        middle_name: "",
        date_of_birth: "",
        address: "",
        school_address: "",
        shs_school: "",
        strand: "",
        year_graduated: "",
        email: "",
        reference_number: "",
        batch_number: "",
        school_year: "",
        pupcet_total_score: "",
        passer_status_id: "",
    };
    showAddModal.value = true;
}

function closeAddModal() {
    showAddModal.value = false;
}

async function saveNewPasser() {
    saving.value = true;
    start();
    try {
        const response = await axios.post(
            "/test-passers-store",
            newPasserData.value
        );
        show("Passer added successfully!", "success");

        // Add the new passer with computed properties
        const newPasser = {
            ...response.data,
            schoolYear: response.data.school_year,
            batchNumber: response.data.batch_number
        };
        flatPassers.value.unshift(newPasser);

        closeAddModal();
        
        // Ensure the UI updates immediately
        await nextTick();
    } catch (error) {
        show("Failed to add passer.", "error");
        console.error(error);
    } finally {
        finish();
        saving.value = false;
    }
}

// SAR History Management
const sarHistory = ref([]);
const loadingSarHistory = ref(false);
const showSarPreview = ref(false);
const sarPreviewUrl = ref('');

const loadSarHistory = async () => {
    loadingSarHistory.value = true;
    try {
        const params = {};
        if (filterSchoolYear.value) params.school_year = filterSchoolYear.value;
        if (filterBatchNumber.value) params.batch_number = filterBatchNumber.value;
        if (debouncedSearchTerm.value) params.search = debouncedSearchTerm.value;

        const response = await axios.get('/admin/sar-generations', { params });
        sarHistory.value = response.data.data || [];
    } catch (error) {
        console.error('Failed to load SAR history:', error);
        // Don't show error on initial load if no data yet
        if (error.response && error.response.status !== 404) {
            show('Failed to load SAR history', 'error');
        }
    } finally {
        loadingSarHistory.value = false;
    }
};

const previewSar = (id) => {
    sarPreviewUrl.value = `/admin/sar/${id}/preview`;
    showSarPreview.value = true;
};

const closeSarPreview = () => {
    showSarPreview.value = false;
    sarPreviewUrl.value = '';
};

const downloadSar = (id) => {
    window.open(`/admin/sar/${id}/download`, '_blank');
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};



// Waitlisted Email Template Preview
const showWaitlistedEmailPreview = ref(false);
const waitlistedEmailPreviewHtml = ref('');
const loadingWaitlistedPreview = ref(false);

const previewWaitlistedEmailTemplate = async () => {
    if (selectedPassers.value.length === 0) {
        show('Please select at least one passer to preview', 'error');
        return;
    }

    loadingWaitlistedPreview.value = true;
    showWaitlistedEmailPreview.value = true;
    
    try {
        const response = await axios.post('/admin/waitlisted/preview-email-template', {
            passer_id: selectedPassers.value[0],
            message_template: emailTemplate.value
        });
        waitlistedEmailPreviewHtml.value = response.data;
    } catch (error) {
        console.error('Failed to preview waitlisted email template:', error);
        show('Failed to load email preview', 'error');
        closeWaitlistedEmailPreview();
    } finally {
        loadingWaitlistedPreview.value = false;
    }
};

const closeWaitlistedEmailPreview = () => {
    showWaitlistedEmailPreview.value = false;
    waitlistedEmailPreviewHtml.value = '';
};

// Watch filters to reload SAR history
watch([filterSchoolYear, filterBatchNumber, filterPasserStatus, debouncedSearchTerm], () => {
    if (sarHistory.value.length > 0 || filterSchoolYear.value || filterBatchNumber.value || filterPasserStatus.value || debouncedSearchTerm.value) {
        loadSarHistory();
    }
});

// Load SAR history on mount
onMounted(() => {
    loadSarHistory();
});

// ── Bulk Enroll ───────────────────────────────────────────────────────────────
const bulkEnrollRunning    = ref(false);
const showBulkEnrollResult = ref(false);
const bulkEnrollResult     = ref({ message: '', enrolled: [], errors: [] });

const runBulkEnroll = async () => {
    if (selectedPassers.value.length === 0) {
        show('Please select at least one passer first.', 'error');
        return;
    }

    if (!confirm(
        `Auto-enroll ${selectedPassers.value.length} selected passer(s) as Officially Enrolled?\n\n` +
        `This will create their accounts, profiles, and complete all admission stages automatically.\n` +
        `The operation is safe to run multiple times.`
    )) return;

    bulkEnrollRunning.value = true;

    try {
        const response = await axios.post('/test-passers/bulk-enroll', {
            passer_ids: selectedPassers.value,
        });

        bulkEnrollResult.value = {
            message:  response.data.message ?? 'Enrollment complete.',
            enrolled: response.data.enrolled ?? [],
            errors:   response.data.errors ?? [],
        };

        showBulkEnrollResult.value = true;

        if (response.data.enrolled?.length > 0) {
            show(`${response.data.enrolled.length} passer(s) successfully enrolled!`, 'success');
        }
    } catch (error) {
        const msg = error.response?.data?.error
            ?? error.response?.data?.message
            ?? 'Enrollment failed. Please try again.';
        show(msg, 'error');
    } finally {
        bulkEnrollRunning.value = false;
    }
};
</script>

<style>
.scroll-wrapper {
    height: 100vh;
    overflow-y: auto;
    padding-right: 1px;
    box-sizing: content-box;
}

.scroll-wrapper::-webkit-scrollbar {
    width: 8px;
}

.scroll-wrapper::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.scroll-wrapper::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.scroll-wrapper::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}

/* Animation for snackbar */
@keyframes slide-in {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.animate-slide-in {
    animation: slide-in 0.3s ease-out;
}

/* Custom Quill Editor Styling */
.ql-toolbar {
    border-top-left-radius: 0.75rem !important;
    border-top-right-radius: 0.75rem !important;
    border-color: #d1d5db !important;
}

.ql-container {
    border-bottom-left-radius: 0.75rem !important;
    border-bottom-right-radius: 0.75rem !important;
    border-color: #d1d5db !important;
    min-height: 250px;
}

.ql-editor {
    min-height: 250px;
}
</style>
