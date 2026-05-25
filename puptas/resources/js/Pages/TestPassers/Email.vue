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

            <!-- Email Progress Bar -->
            <EmailProgressBar
                v-if="activeBulkOperationId"
                :bulkOperationId="activeBulkOperationId"
                @dismissed="activeBulkOperationId = null"
            />

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Panel: Controls & Filters -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Filters card -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm p-5">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Filters &amp; Controls</span>
                            <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-1 rounded-full">
                                {{ passers?.total || 0 }} passers
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
                                placeholder="Search by name, surname, or email..."
                                class="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                            />
                        </div>

                        <!-- Dropdowns -->
                        <div class="flex gap-3 flex-wrap">
                            <select v-model="filterSchoolYear"
                                class="flex-1 min-w-[130px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#9E122C]">
                                <option value="all">All Years</option>
                                <option v-for="year in schoolYears" :key="year" :value="year">{{ year }}</option>
                            </select>
                            <select v-model="filterBatchNumber"
                                class="flex-1 min-w-[130px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#9E122C]">
                                <option value="all">All Batches</option>
                                <option v-for="batch in batchNumbers" :key="batch" :value="batch">{{ batch }}</option>
                            </select>
                            <select v-model="sortKey"
                                class="flex-1 min-w-[130px] px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-xl text-sm bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:ring-2 focus:ring-[#9E122C]">
                                <option value="pupcet_total_score">Sort: PUPCET Score</option>
                                <option value="surname">Sort: Surname</option>
                                <option value="first_name">Sort: First Name</option>
                                <option value="email">Sort: Email</option>
                                <option value="school_year">Sort: School Year</option>
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
                                Select All ({{ selectedPassers.length }}/{{ totalFilteredCount }})
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
                                    &bull; Showing {{ paginatedPassers.length }} of {{ passers?.total || 0 }} items
                                </div>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full divide-y divide-gray-200 dark:divide-gray-700 table-fixed">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                     <tr>
                                         <th class="px-3 py-3 text-left w-10">
                                             <input
                                                 type="checkbox"
                                                 :checked="areAllSelected"
                                                 @change="toggleSelectAll($event.target.checked)"
                                                 class="h-4 w-4 text-[#9E122C] border-gray-300 rounded focus:ring-[#9E122C] dark:text-white dark:border-gray-600"
                                             />
                                         </th>
                                         <th class="px-2 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400 w-12">
                                             Rank
                                         </th>
                                         <th class="px-3 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400 w-[18%]">
                                             Name
                                         </th>
                                         <th class="px-3 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400 w-[20%]">
                                             Contact
                                         </th>
                                         <th class="px-3 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400 w-[10%]">
                                             Score
                                         </th>
                                         <th class="px-3 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400 w-[16%]">
                                             Details
                                         </th>
                                         <th class="px-3 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400 w-[12%]">
                                             Status
                                         </th>
                                         <th class="px-3 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider dark:text-gray-400 w-24 sticky right-0 bg-gray-50 dark:bg-gray-900">
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
                                         <td class="px-3 py-3 whitespace-nowrap">
                                             <input
                                                 type="checkbox"
                                                 :value="passer.test_passer_id"
                                                 v-model="selectedPassers"
                                                 class="h-4 w-4 text-[#9E122C] border-gray-300 rounded focus:ring-[#9E122C] dark:text-white dark:border-gray-600"
                                             />
                                         </td>
                                         <!-- Rank cell: global rank across all filtered passers -->
                                         <td class="px-2 py-3 whitespace-nowrap text-center text-sm text-gray-600 dark:text-gray-400">
                                             {{ getGlobalRank(passer, pageIndex) }}
                                         </td>
                                         <td class="px-3 py-3">
                                             <div class="truncate">
                                                 <div class="font-medium text-sm text-gray-900 dark:text-gray-200 truncate">
                                                     {{ passer.surname }}, {{ passer.first_name }}
                                                 </div>
                                                 <div v-if="passer.middle_name" class="text-xs text-gray-500 dark:text-gray-300 truncate">
                                                     {{ passer.middle_name }}
                                                 </div>
                                             </div>
                                         </td>
                                         <td class="px-3 py-3">
                                             <div class="truncate">
                                                 <div class="text-sm text-gray-900 dark:text-gray-200 truncate">{{ passer.email }}</div>
                                                 <div v-if="passer.reference_number" class="text-xs text-gray-500 dark:text-gray-300 truncate">
                                                     Ref: {{ passer.reference_number }}
                                                 </div>
                                             </div>
                                         </td>
                                         <!-- PUPCET Score cell -->
                                         <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                             <span v-if="passer.pupcet_total_score !== null && passer.pupcet_total_score !== undefined">
                                                 {{ Number(passer.pupcet_total_score).toFixed(2) }}
                                             </span>
                                             <span v-else class="text-gray-400 dark:text-gray-500">-</span>
                                         </td>
                                         <td class="px-3 py-3">
                                             <div class="flex flex-wrap gap-1">
                                                 <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-300">
                                                     {{ passer.school_year }}
                                                 </span>
                                                 <span v-if="passer.batch_number" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-300">
                                                     {{ passer.batch_number }}
                                                 </span>
                                             </div>
                                         </td>
                                         <!-- Status Column -->
                                         <td class="px-3 py-3 whitespace-nowrap text-xs font-medium">
                                             <span v-if="passer.passer_status_id === 1" class="px-2 py-1 bg-green-100 text-green-800 rounded-full dark:bg-green-900 dark:text-green-200">
                                                 Qualified
                                             </span>
                                             <span v-else-if="passer.passer_status_id === 2" class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full dark:bg-yellow-900 dark:text-yellow-200">
                                                 Waitlisted
                                             </span>
                                             <span v-else-if="passer.passer_status_id === 4" class="px-2 py-1 bg-orange-100 text-orange-800 rounded-full dark:bg-orange-900 dark:text-orange-200">
                                                 Waitlisted Below Cut Off
                                             </span>
                                             <span v-else-if="passer.passer_status_id === 3" class="px-2 py-1 bg-red-100 text-red-800 rounded-full dark:bg-red-900 dark:text-red-200">
                                                 Unqualified
                                             </span>
                                             <span v-else class="px-2 py-1 bg-gray-200 text-gray-500 rounded-full dark:bg-gray-700 dark:text-gray-400">
                                                 Pending
                                             </span>
                                         </td>
                                         <td class="px-3 py-3 whitespace-nowrap sticky right-0 bg-white dark:bg-gray-800">
                                             <div class="flex items-center gap-1">
                                                 <button
                                                     @click.prevent="openEditModal(passer)"
                                                     class="inline-flex items-center p-1.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200 transition dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-900"
                                                     title="Edit Passer"
                                                 >
                                                     <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                     </svg>
                                                 </button>
                                                 <button
                                                     @click.prevent="confirmDelete(passer)"
                                                     class="inline-flex items-center p-1.5 border border-red-300 rounded-lg text-red-600 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-200 transition dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20"
                                                     title="Delete Passer"
                                                 >
                                                     <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                                    <span v-if="!passers || passers.total === 0">
                                        Showing 0 to 0 of 0 results
                                    </span>
                                    <span v-else>
                                        Showing {{ passers.from }} 
                                        to {{ passers.to }} 
                                        of {{ passers.total }} results
                                    </span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button
                                        :disabled="currentPage === 1"
                                        @click.prevent="goToPage(currentPage - 1)"
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
                                            @click.prevent="goToPage(page)"
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
                                        @click.prevent="goToPage(currentPage + 1)"
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

                        <!-- Custom Template Editor -->
                        <div v-if="templateType === 'custom'" class="mt-4">
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

                        <!-- Waitlisted Cut-off Template Preview -->
                        <div v-else-if="templateType === 'waitlisted-cutoff'" class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-3 dark:text-gray-400">
                                Waitlisted (Below Cut-off) Template Preview
                            </label>
                            <div class="border border-gray-200 rounded-xl p-4 bg-gray-50 max-h-[300px] overflow-y-auto dark:border-gray-700 dark:bg-gray-900">
                                <div v-html="formattedWaitlistedCutoffTemplatePreview"></div>
                            </div>
                        </div>

                        <!-- Default Template Preview -->
                        <div v-else class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-3 dark:text-gray-400">
                                Default Template Preview
                            </label>
                            <div class="border border-gray-200 rounded-xl p-4 bg-gray-50 max-h-[300px] overflow-y-auto dark:border-gray-700 dark:bg-gray-900">
                                <div v-html="formattedDefaultTemplatePreview"></div>
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
                                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-200">{{ passers?.total || 0 }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
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
                        <div class="col-span-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Email *</label>
                                <input
                                    type="email"
                                    v-model="editingPasser.email"
                                    required
                                    :readonly="$page.props.auth.user.role_id !== 7"
                                    :class="[
                                        'w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none transition dark:border-gray-600',
                                        $page.props.auth.user.role_id !== 7 
                                            ? 'bg-gray-100 text-gray-600 cursor-not-allowed dark:bg-gray-700 dark:text-gray-400' 
                                            : 'focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] dark:bg-gray-800 dark:text-white'
                                    ]"
                                />
                            </div>
                        </div>

                        <!-- High School Attended -->
                        <div class="col-span-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">High School Attended</label>
                                <input
                                    type="text"
                                    v-model="editingPasser.shs_school"
                                    placeholder="High School Name"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                />
                            </div>
                        </div>

                        <div class="col-span-2 grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Strand</label>
                                <select
                                    v-model="editingPasser.strand"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800"
                                >
                                    <option value="" disabled>Select Strand</option>
                                    <option value="STEM">STEM</option>
                                    <option value="HUMSS">HUMSS</option>
                                    <option value="ABM">ABM</option>
                                    <option value="TVL">TVL</option>
                                    <option value="ICT">ICT</option>
                                    <option value="GAS">GAS</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Year Graduated</label>
                                <select
                                    v-model="editingPasser.year_graduated"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800"
                                >
                                    <option value="" disabled>Select an option</option>
                                    <option value="Senior High School of A.Y. 2025-2026">Senior High School A.Y. 2025-2026</option>
                                    <option value="Senior High School of Past School Years">Senior High School of Past School Years</option>
                                </select>
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
                                <select
                                    v-model="editingPasser.school_year"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800"
                                >
                                    <option value="" disabled>Select School Year</option>
                                    <option v-for="sy in academicYearOptions" :key="sy" :value="sy">{{ sy }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">
                                    Batch Number<span v-if="editingPasser.passer_status_id !== '3' && editingPasser.passer_status_id !== '4' && editingPasser.passer_status_id !== 3 && editingPasser.passer_status_id !== 4"> *</span>
                                </label>
                                <select
                                    v-model="editingPasser.batch_number"
                                    :required="editingPasser.passer_status_id !== '3' && editingPasser.passer_status_id !== '4' && editingPasser.passer_status_id !== 3 && editingPasser.passer_status_id !== 4"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800"
                                >
                                    <option value="">Select Batch</option>
                                    <option value="Batch 1">Batch 1</option>
                                    <option value="Batch 2">Batch 2</option>
                                    <option value="Batch 3">Batch 3</option>
                                    <option value="Batch 4">Batch 4</option>
                                </select>
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
                                    readonly
                                    class="w-full px-4 py-3 bg-gray-100 text-gray-600 border border-gray-300 rounded-xl cursor-not-allowed focus:outline-none transition dark:bg-gray-700 dark:border-gray-600 dark:text-gray-400"
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
                                    <option value="4">Waitlisted Below Cut Off</option>
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
                        <div class="col-span-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Email *</label>
                                <input
                                    type="email"
                                    v-model="newPasserData.email"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600"
                                />
                            </div>
                        </div>

                        <!-- High School Attended -->
                        <div class="col-span-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">High School Attended</label>
                                <input
                                    type="text"
                                    v-model="newPasserData.shs_school"
                                    placeholder="High School Name"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                />
                            </div>
                        </div>

                        <div class="col-span-2 grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Strand</label>
                                <select
                                    v-model="newPasserData.strand"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800"
                                >
                                    <option value="" disabled>Select Strand</option>
                                    <option value="STEM">STEM</option>
                                    <option value="HUMSS">HUMSS</option>
                                    <option value="ABM">ABM</option>
                                    <option value="TVL">TVL</option>
                                    <option value="ICT">ICT</option>
                                    <option value="GAS">GAS</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">Year Graduated</label>
                                <select
                                    v-model="newPasserData.year_graduated"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800"
                                >
                                    <option value="" disabled>Select an option</option>
                                    <option value="Senior High School of A.Y. 2025-2026">Senior High School A.Y. 2025-2026</option>
                                    <option value="Senior High School of Past School Years">Senior High School of Past School Years</option>
                                </select>
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
                                <select
                                    v-model="newPasserData.school_year"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800"
                                >
                                    <option value="" disabled>Select School Year</option>
                                    <option v-for="sy in academicYearOptions" :key="sy" :value="sy">{{ sy }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2 dark:text-gray-400">
                                    Batch Number<span v-if="newPasserData.passer_status_id !== '3' && newPasserData.passer_status_id !== '4' && newPasserData.passer_status_id !== 3 && newPasserData.passer_status_id !== 4"> *</span>
                                </label>
                                <select
                                    v-model="newPasserData.batch_number"
                                    :required="newPasserData.passer_status_id !== '3' && newPasserData.passer_status_id !== '4' && newPasserData.passer_status_id !== 3 && newPasserData.passer_status_id !== 4"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition dark:border-gray-600 dark:bg-gray-800"
                                >
                                    <option value="">Select Batch</option>
                                    <option value="Batch 1">Batch 1</option>
                                    <option value="Batch 2">Batch 2</option>
                                    <option value="Batch 3">Batch 3</option>
                                    <option value="Batch 4">Batch 4</option>
                                </select>
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
                                    <option value="4">Waitlisted Below Cut Off</option>
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
import { Head, router } from "@inertiajs/vue3";
import { QuillEditor } from "@vueup/vue-quill";
import "@vueup/vue-quill/dist/vue-quill.snow.css";
import { useGlobalLoading } from "@/Composables/useGlobalLoading";
import { useSnackbar } from "@/Composables/useSnackbar";
import EmailProgressBar from "@/Components/EmailProgressBar.vue";

// Scroll functionality (unchanged)
const scrollWrapper = ref(null);
const scrollAmount = 200;
const showScrollUp = ref(false);
const showScrollDown = ref(true);

// Email progress tracking
const activeBulkOperationId = ref(null);

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

import { onMounted, onUnmounted } from "vue";

onMounted(() => {
    handleScroll();
});

// Template types for selection
const templateTypes = [
    { label: 'Default', value: 'default' },
    { label: 'Custom', value: 'custom' },
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
    passers: Object,
    filterOptions: Object,
    filters: Object,
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
        If you have any questions or need help, please contact the Admission and Registration Office at
        <a href="mailto:taguig@pup.edu.ph" style="color:#9E122C;">taguig@pup.edu.ph</a> /
        <a href="mailto:puptadmission@gmail.com" style="color:#9E122C;">puptadmission@gmail.com</a>.
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
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">All PUPCET Passers and Waitlisted Applicants are also invited to attend the <strong>Career Orientation for the Incoming First-Year Students</strong> on June 8, 2026 (Monday), 2:00PM, via Facebook Live (<a href="https://www.facebook.com/PUPTOFFICIAL" target="_blank" rel="noopener noreferrer" style="color:#1155cc;">PUP - Taguig Facebook page</a>).</p>
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">Your enrollment will only be considered official when you bring the original documents with two photocopies on <strong>June 24, 2026 (Wednesday)</strong> and pass the interview. Incomplete requirements will not be entertained, so please ensure that you have all the necessary documents.</p>
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;font-style:italic;">Kindly disregard this email if you already have confirmed your interview slot.</p>
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
    <p style="margin:0 0 12px 0;font-size:14px;color:#222;line-height:1.6;">All PUPCET Passers and Waitlisted Applicants are also invited to attend the <strong>Career Orientation for the Incoming First-Year Students</strong> on June 8, 2026 (Monday), 2:00PM, via Facebook Live (<a href="https://www.facebook.com/PUPTOFFICIAL" target="_blank" rel="noopener noreferrer" style="color:#1155cc;">PUP - Taguig Facebook page</a>).</p>
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

const formattedDefaultTemplatePreview = computed(() => {
    return defaultTemplatePreview
        .replace(/\{\{firstname\}\} \{\{surname\}\}/g, '<span style="color:#cc0000;font-weight:bold;">John Doe</span>')
        .replace(/\{\{firstname\}\}/g, 'John')
        .replace(/\{\{surname\}\}/g, 'Doe');
});

const formattedWaitlistedTemplatePreview = computed(() => {
    return waitlistedTemplatePreview
        .replace(/\{\{firstname\}\} \{\{surname\}\}/g, '<span style="color:#cc0000;font-weight:bold;">John Doe</span>')
        .replace(/\{\{firstname\}\}/g, 'John')
        .replace(/\{\{surname\}\}/g, 'Doe');
});

const formattedWaitlistedCutoffTemplatePreview = computed(() => {
    return waitlistedCutoffTemplatePreview
        .replace(/\{\{firstname\}\} \{\{surname\}\}/g, '<span style="color:#9E122C;font-weight:bold;">John Doe</span>')
        .replace(/\{\{firstname\}\}/g, 'John')
        .replace(/\{\{surname\}\}/g, 'Doe');
});

const flatPassers = ref([]);

watch(
    () => [props.groupedPassers, props.passers],
    ([groupedVal, paginatedVal]) => {
        const result = [];

        // New paginated format: passers is a Laravel LengthAwarePaginator object
        if (paginatedVal && paginatedVal.data) {
            paginatedVal.data.forEach((passer) => {
                result.push({
                    ...passer,
                    schoolYear: passer.school_year,
                    batchNumber: passer.batch_number,
                });
            });
        }
        // Legacy grouped format: groupedPassers is { school_year: { batch_number: [...] } }
        else if (groupedVal) {
            for (const schoolYear in groupedVal) {
                for (const batchNumber in groupedVal[schoolYear]) {
                    groupedVal[schoolYear][batchNumber].forEach((passer) => {
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

const searchTerm = ref(props.filters?.search || "");
const debouncedSearchTerm = ref(props.filters?.search || "");
const filterSchoolYear = ref(props.filters?.school_year || "all");
const filterBatchNumber = ref(props.filters?.batch_number || "all");
const sortKey = ref(props.filters?.sort_key || "pupcet_total_score");
const sortOrder = ref(props.filters?.sort_order || "desc");

// Server-side pagination: read from the paginator metadata
const currentPage = computed(() => props.passers?.current_page || 1);
const totalPages = computed(() => props.passers?.last_page || 1);
const itemsPerPage = computed(() => props.passers?.per_page || 15);

// Central function to make server requests with all current params
function buildServerParams(overrides = {}) {
    const params = {
        school_year: filterSchoolYear.value || 'all',
        batch_number: filterBatchNumber.value || 'all',
        search: debouncedSearchTerm.value || undefined,
        sort_key: sortKey.value || undefined,
        sort_order: sortOrder.value || undefined,
        per_page: itemsPerPage.value,
        page: 1,
        ...overrides,
    };
    // Remove undefined values
    Object.keys(params).forEach(key => params[key] === undefined && delete params[key]);
    return params;
}

// Navigate to a different page via Inertia (server-side pagination)
function goToPage(page) {
    if (page < 1 || page > totalPages.value || page === currentPage.value) return;
    router.get('/test-passers', buildServerParams({ page }), {
        preserveState: true,
        preserveScroll: true,
    });
}

// Reload from server with current filters (resets to page 1)
function applyServerFilters() {
    router.get('/test-passers', buildServerParams({ page: 1 }), {
        preserveState: true,
        preserveScroll: true,
    });
}

const onSearchInput = debounce(() => {
    debouncedSearchTerm.value = searchTerm.value.toLowerCase();
    applyServerFilters();
}, 300);

// When server-side filters change, reload from server
watch([filterSchoolYear, filterBatchNumber], () => {
    applyServerFilters();
}, { deep: true });

// When sort changes, reload from server (keeps current page 1)
watch([sortKey, sortOrder], () => {
    applyServerFilters();
});

const schoolYears = computed(() => {
    // Use server-provided filter options if available
    if (props.filterOptions && props.filterOptions.schoolYears && props.filterOptions.schoolYears.length > 0) {
        return props.filterOptions.schoolYears;
    }
    // Fallback to client-side derivation
    const years = new Set(flatPassers.value.map((p) => p.schoolYear));
    return Array.from(years).sort();
});

const academicYearOptions = computed(() => {
    const currentYear = new Date().getFullYear();
    return [
        `${currentYear}-${currentYear + 1}`,
        `${currentYear - 1}-${currentYear}`,
        `${currentYear - 2}-${currentYear - 1}`,
        `${currentYear - 3}-${currentYear - 2}`,
    ];
});

const batchNumbers = computed(() => {
    // Use server-provided filter options if available
    if (props.filterOptions && props.filterOptions.batchNumbers && props.filterOptions.batchNumbers.length > 0) {
        return props.filterOptions.batchNumbers;
    }
    // Fallback to client-side derivation
    const batches = new Set(
        flatPassers.value
            .map((p) => p.batchNumber)
            .filter((b) => b !== null && b !== undefined && b !== '')
    );
    return Array.from(batches).sort();
});

const filteredPassers = computed(() => {
    // Filtering is now done server-side; just return all records from the current page
    return flatPassers.value;
});

const sortedPassers = computed(() => {
    // Sorting is now done server-side; data arrives pre-sorted
    return filteredPassers.value;
});

/**
 * Returns the 1-based global rank of a passer within the full result set.
 * Uses the server paginator's 'from' value for correct offset across pages.
 */
function getGlobalRank(passer, pageIndex) {
    const from = props.passers?.from || 1;
    return from + pageIndex;
}

// visiblePages for pagination UI (uses server-side totalPages)

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
    // Data is already paginated by the server; just apply client-side search/sort
    return sortedPassers.value;
});

const selectedPassers = ref([]);
const allFilteredSelected = ref(false);
const totalFilteredCount = computed(() => props.passers?.total || 0);

// Check if all filtered passers are selected (uses the allFilteredSelected flag)
const areAllSelected = computed(() => {
    if (totalFilteredCount.value === 0) return false;
    return allFilteredSelected.value;
});

const areAllSelectedOnCurrentPage = computed(() => {
    if (!paginatedPassers.value.length) return false;
    return paginatedPassers.value.every((p) =>
        selectedPassers.value.includes(p.test_passer_id)
    );
});

const toggleSelectAll = async (isSelected) => {
    if (isSelected) {
        // Fetch ALL IDs matching current filters from the server
        try {
            const params = {};
            if (filterSchoolYear.value) params.school_year = filterSchoolYear.value;
            if (filterBatchNumber.value) params.batch_number = filterBatchNumber.value;
            if (debouncedSearchTerm.value) params.search = debouncedSearchTerm.value;

            const response = await axios.get('/test-passers/select-all-ids', { params });
            const allIds = response.data.ids;

            // Merge all IDs into selectedPassers (avoid duplicates)
            const currentSet = new Set(selectedPassers.value);
            allIds.forEach(id => currentSet.add(id));
            selectedPassers.value = Array.from(currentSet);
            allFilteredSelected.value = true;
        } catch (error) {
            console.error('Failed to fetch all IDs:', error);
            // Fallback: select only current page
            const pageIds = paginatedPassers.value.map(p => p.test_passer_id);
            const currentSet = new Set(selectedPassers.value);
            pageIds.forEach(id => currentSet.add(id));
            selectedPassers.value = Array.from(currentSet);
        }
    } else {
        // Deselect all — clear everything
        selectedPassers.value = [];
        allFilteredSelected.value = false;
    }
};

// Reset allFilteredSelected when filters change
watch([filterSchoolYear, filterBatchNumber], () => {
    allFilteredSelected.value = false;
}, { deep: true });

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
        const response = await axios.post("/test-passers/send-emails", {
            passer_ids: selectedPassers.value,
            message_template: messageHtml,
            template_type: templateType.value,
            enrollment_date: sarEnrollmentDate.value,
            enrollment_time: sarEnrollmentTime.value,
        });

        // Extract bulk_operation_id and show progress bar
        if (response.data && response.data.bulk_operation_id) {
            activeBulkOperationId.value = response.data.bulk_operation_id;
        }

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
    let yearGradVal = '';
    if (passer.year_graduated != null) {
        const yearInt = parseInt(passer.year_graduated, 10);
        if (yearInt >= 2026) {
            yearGradVal = 'Senior High School of A.Y. 2025-2026';
        } else {
            yearGradVal = 'Senior High School of Past School Years';
        }
    }

    editingPasser.value = { 
        ...passer,
        // Ensure passer_status_id is a string for the <select> v-model binding
        passer_status_id: passer.passer_status_id != null ? String(passer.passer_status_id) : '',
        pupcet_total_score: passer.pupcet_total_score != null ? passer.pupcet_total_score : '',
        year_graduated: yearGradVal,
        shs_school: passer.shs_school || '',
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
        const dataToSend = { ...editingPasser.value };
        if (dataToSend.year_graduated === 'Senior High School of A.Y. 2025-2026') {
            dataToSend.year_graduated = 2026;
        } else if (dataToSend.year_graduated === 'Senior High School of Past School Years') {
            dataToSend.year_graduated = 2025;
        } else if (dataToSend.year_graduated === "" || dataToSend.year_graduated === null || dataToSend.year_graduated === undefined) {
            dataToSend.year_graduated = null;
        } else {
            dataToSend.year_graduated = parseInt(dataToSend.year_graduated, 10);
        }

        const response = await axios.put(
            `/test-passers/${editingPasser.value.test_passer_id}`,
            dataToSend
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
                (!filterSchoolYear.value || filterSchoolYear.value === 'all' || updatedPasser.schoolYear === filterSchoolYear.value) &&
                (!filterBatchNumber.value || filterBatchNumber.value === 'all' || updatedPasser.batchNumber === filterBatchNumber.value)
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

function getStatusAndBatchFromScore(score) {
    if (score === null || score === undefined || score === "") {
        return { passer_status_id: "", batch_number: "" };
    }
    const numScore = parseFloat(score);
    if (isNaN(numScore)) {
        return { passer_status_id: "", batch_number: "" };
    }

    if (numScore >= 85.00) {
        return { passer_status_id: "1", batch_number: "Batch 1" };
    } else if (numScore >= 79.00) {
        return { passer_status_id: "1", batch_number: "Batch 2" };
    } else if (numScore >= 75.00) {
        return { passer_status_id: "2", batch_number: "Batch 3" };
    } else if (numScore >= 55.00) {
        return { passer_status_id: "2", batch_number: "Batch 4" };
    } else {
        return { passer_status_id: "3", batch_number: "" };
    }
}

watch(
    () => newPasserData.value?.pupcet_total_score,
    (newScore) => {
        if (!newPasserData.value) return;
        const { passer_status_id, batch_number } = getStatusAndBatchFromScore(newScore);
        if (passer_status_id !== "") {
            newPasserData.value.passer_status_id = passer_status_id;
        }
        newPasserData.value.batch_number = batch_number;
    }
);

watch(
    () => editingPasser.value?.pupcet_total_score,
    (newScore) => {
        if (!editingPasser.value) return;
        const { passer_status_id, batch_number } = getStatusAndBatchFromScore(newScore);
        if (passer_status_id !== "") {
            editingPasser.value.passer_status_id = passer_status_id;
        }
        editingPasser.value.batch_number = batch_number;
    }
);

function openAddModal() {
    newPasserData.value = {
        surname: "",
        first_name: "",
        middle_name: "",
        strand: "",
        shs_school: "",
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
    // Check for duplicate email
    const duplicateEmail = flatPassers.value.find(
        (p) => p.email && p.email.toLowerCase() === newPasserData.value.email?.toLowerCase()
    );
    if (duplicateEmail) {
        show("A passer with this email already exists.", "error");
        return;
    }

    // Check for duplicate reference number
    if (newPasserData.value.reference_number) {
        const duplicateRef = flatPassers.value.find(
            (p) => p.reference_number && p.reference_number === newPasserData.value.reference_number
        );
        if (duplicateRef) {
            show("A passer with this reference number already exists.", "error");
            return;
        }
    }

    saving.value = true;
    start();
    try {
        const dataToSend = { ...newPasserData.value };
        if (dataToSend.year_graduated === 'Senior High School of A.Y. 2025-2026') {
            dataToSend.year_graduated = 2026;
        } else if (dataToSend.year_graduated === 'Senior High School of Past School Years') {
            dataToSend.year_graduated = 2025;
        } else if (dataToSend.year_graduated === "" || dataToSend.year_graduated === null || dataToSend.year_graduated === undefined) {
            dataToSend.year_graduated = null;
        } else {
            dataToSend.year_graduated = parseInt(dataToSend.year_graduated, 10);
        }

        const response = await axios.post(
            "/test-passers-store",
            dataToSend
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
        if (error.response?.status === 422) {
            const errors = error.response.data?.errors;
            if (errors?.email) {
                show(errors.email[0], "error");
            } else if (errors?.reference_number) {
                show(errors.reference_number[0], "error");
            } else {
                show("Validation failed. Please check your input.", "error");
            }
        } else {
            show("Failed to add passer.", "error");
        }
        console.error(error);
    } finally {
        finish();
        saving.value = false;
    }
}

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


// â”€â”€ Bulk Enroll â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

