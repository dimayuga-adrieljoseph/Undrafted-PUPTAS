<template>
    <div
        ref="scrollWrapper"
        class="scroll-wrapper"
        tabindex="0"
        @scroll="handleScroll"
    >
        <AppLayout>
            <!-- Header Section -->
            <div class="mb-8">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-[#9E122C]/10 rounded-2xl">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-[#9E122C]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">
                                PUPCET Passers Email System
                            </h1>
                            <p class="text-gray-600 mt-1">
                                Send personalized emails to successful PUPCET applicants
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Panel: Controls & Filters -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Control Cards -->
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-gray-900">
                                Filters & Controls
                            </h2>
                            <span class="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-full">
                                {{ filteredPassers.length }} passers
                            </span>
                        </div>

                        <!-- Search Bar -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Search Passers
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input
                                    type="text"
                                    v-model="searchTerm"
                                    @input="onSearchInput"
                                    placeholder="Search by name, surname, or email..."
                                    class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#9E122C]/50 focus:border-[#9E122C] transition"
                                />
                            </div>
                        </div>

                        <!-- Filter Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            <!-- School Year Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    School Year
                                </label>
                                <div class="relative">
                                    <select
                                        v-model="filterSchoolYear"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/50 focus:border-[#9E122C] appearance-none transition pr-10"
                                    >
                                        <option value="">All Years</option>
                                        <option v-for="year in schoolYears" :key="year" :value="year">
                                            {{ year }}
                                        </option>
                                    </select>
                                    <div class="absolute inset-y-0 right-5 flex items-center pr-3 pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Batch Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Batch
                                </label>
                                <div class="relative">
                                    <select
                                        v-model="filterBatchNumber"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/50 focus:border-[#9E122C] appearance-none transition pr-10"
                                    >
                                        <option value="">All Batches</option>
                                        <option v-for="batch in batchNumbers" :key="batch" :value="batch">
                                            Batch {{ batch }}
                                        </option>
                                    </select>
                                    <div class="absolute inset-y-0 right-5 flex items-center pr-3 pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Sort By -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Sort By
                                </label>
                                <div class="relative">
                                    <select
                                        v-model="sortKey"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/50 focus:border-[#9E122C] appearance-none transition pr-10"
                                    >
                                        <option value="surname">Surname</option>
                                        <option value="first_name">First Name</option>
                                        <option value="email">Email</option>
                                        <option value="schoolYear">School Year</option>
                                        <option value="batchNumber">Batch</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-5 flex items-center pr-3 pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Sort Order -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Order
                                </label>
                                <div class="relative">
                                    <select
                                        v-model="sortOrder"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/50 focus:border-[#9E122C] appearance-none transition pr-10"
                                    >
                                        <option value="asc">Ascending</option>
                                        <option value="desc">Descending</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-5 flex items-center pr-3 pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-wrap gap-3">
                            <button
                                @click.prevent="openAddModal"
                                class="inline-flex items-center px-4 py-3 bg-[#9E122C] text-white rounded-xl hover:bg-[#800918] focus:outline-none focus:ring-2 focus:ring-[#9E122C]/50 transition"
                            >
                                <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                Add New Passer
                            </button>
                            
                            <button
                                @click.prevent="toggleSelectAll(!areAllSelected)"
                                class="inline-flex items-center px-4 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200 transition"
                            >
                                <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ areAllSelected ? 'Deselect All' : 'Select All' }} ({{ selectedPassers.length }})
                            </button>
                        </div>
                    </div>

                    <!-- Passers Table Card -->
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <h2 class="text-xl font-semibold text-gray-900">
                                    Selected Passers
                                </h2>
                                <div class="text-sm text-gray-600">
                                    Page {{ currentPage }} of {{ totalPages }}
                                    • Showing {{ paginatedPassers.length }} items
                                </div>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left">
                                            <input
                                                type="checkbox"
                                                :checked="areAllSelected"
                                                @change="toggleSelectAll($event.target.checked)"
                                                class="h-5 w-5 text-[#9E122C] border-gray-300 rounded focus:ring-[#9E122C]"
                                            />
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Name
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Contact
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Details
                                        </th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr 
                                        v-for="passer in paginatedPassers" 
                                        :key="passer.test_passer_id"
                                        class="hover:bg-gray-50 transition"
                                    >
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input
                                                type="checkbox"
                                                :value="passer.test_passer_id"
                                                v-model="selectedPassers"
                                                class="h-5 w-5 text-[#9E122C] border-gray-300 rounded focus:ring-[#9E122C]"
                                            />
                                        </td>
                                        <td class="px-6 py-4">
                                            <div>
                                                <div class="font-medium text-gray-900">
                                                    {{ passer.surname }}, {{ passer.first_name }}
                                                </div>
                                                <div v-if="passer.middle_name" class="text-sm text-gray-500">
                                                    {{ passer.middle_name }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-gray-900">{{ passer.email }}</div>
                                            <div v-if="passer.reference_number" class="text-sm text-gray-500">
                                                Ref: {{ passer.reference_number }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-wrap gap-2">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    SY: {{ passer.schoolYear }}
                                                </span>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Batch: {{ passer.batchNumber }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <button
                                                @click.prevent="openEditModal(passer)"
                                                class="inline-flex items-center p-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200 transition"
                                                title="Edit Passer"
                                            >
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="px-6 py-4 border-t border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    Showing {{ Math.min((currentPage - 1) * itemsPerPage + 1, filteredPassers.length) }} 
                                    to {{ Math.min(currentPage * itemsPerPage, filteredPassers.length) }} 
                                    of {{ filteredPassers.length }} results
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button
                                        :disabled="currentPage === 1"
                                        @click.prevent="currentPage--"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition"
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
                                        <span v-if="totalPages > 5 && currentPage < totalPages - 2" class="px-2 text-gray-500">
                                            ...
                                        </span>
                                    </div>
                                    <button
                                        :disabled="currentPage === totalPages"
                                        @click.prevent="currentPage++"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition"
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
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">
                            Email Template
                        </h2>
                        
                        <!-- Template Type Selector -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                Select Template Type
                            </label>
                            <div class="grid grid-cols-3 gap-2">
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

                        <!-- SAR Form Settings -->
                        <div v-if="templateType === 'sar'" class="mt-4 p-4 rounded-xl bg-blue-50 border border-blue-200">
                            <div class="flex items-start gap-3 mb-4">
                                <div class="p-2 bg-blue-100 rounded-lg">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-blue-900 mb-1">
                                        SAR Form Settings
                                    </h4>
                                    <p class="text-sm text-blue-800">
                                        Personalized PDF will be generated for each selected passer
                                    </p>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Enrollment Date
                                    </label>
                                    <input 
                                        type="date" 
                                        v-model="sarEnrollmentDate"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        required
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Enrollment Time
                                    </label>
                                    <input 
                                        type="time" 
                                        v-model="sarEnrollmentTime"
                                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                                        required
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Custom Template Editor -->
                        <div v-else-if="templateType === 'custom'" class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                Custom Email Template
                            </label>
                            <div class="border border-gray-300 rounded-xl overflow-hidden">
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
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                Default Template Preview
                            </label>
                            <div class="border border-gray-200 rounded-xl p-4 bg-gray-50 max-h-[300px] overflow-y-auto">
                                <div v-html="defaultTemplatePreview"></div>
                            </div>
                        </div>

                        <!-- Send Button -->
                        <button
                            type="button"
                            @click="sendEmails"
                            :disabled="!selectedPassers.length || !emailTemplate || (templateType === 'sar' && (!sarEnrollmentDate || !sarEnrollmentTime))"
                            :class="[
                                'w-full mt-6 py-4 rounded-xl font-semibold text-lg transition-all duration-200',
                                selectedPassers.length && emailTemplate && (templateType !== 'sar' || (sarEnrollmentDate && sarEnrollmentTime))
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
                    <div class="bg-white rounded-2xl shadow-lg p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">
                            Statistics
                        </h2>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                <div class="flex items-center">
                                    <div class="p-2 bg-blue-100 rounded-lg mr-3">
                                        <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-600">Total Passers</div>
                                        <div class="text-2xl font-bold text-gray-900">{{ flatPassers.length }}</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                <div class="flex items-center">
                                    <div class="p-2 bg-green-100 rounded-lg mr-3">
                                        <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-600">Selected</div>
                                        <div class="text-2xl font-bold text-gray-900">{{ selectedPassers.length }}</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                <div class="flex items-center">
                                    <div class="p-2 bg-purple-100 rounded-lg mr-3">
                                        <svg class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-600">Filtered</div>
                                        <div class="text-2xl font-bold text-gray-900">{{ filteredPassers.length }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modals -->
            <EditPasserModal
                :show="showEditModal"
                :passer="editingPasser"
                :saving="saving"
                @close="closeEditModal"
                @save="savePasser"
            />

            <AddPasserModal
                :show="showAddModal"
                :saving="saving"
                @close="closeAddModal"
                @save="saveNewPasser"
            />

            <Snackbar
                :show="snackbar.show"
                :message="snackbar.message"
                :type="snackbar.type"
            />
        </AppLayout>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";
const axios = window.axios;
import AppLayout from "@/Layouts/AppLayout.vue";
import { QuillEditor } from "@vueup/vue-quill";
import "@vueup/vue-quill/dist/vue-quill.snow.css";
import { useGlobalLoading } from "@/Composables/useGlobalLoading";
import { useSnackbar } from "@/Composables/useSnackbar";

// Import modals
import EditPasserModal from "../Modal/EditPasserModal.vue";
import AddPasserModal from "../Modal/AddPasserModal.vue";
import Snackbar from "../../Components/Snackbar.vue";

// Scroll functionality
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

onMounted(() => {
    handleScroll();
});

// Template types for selection
const templateTypes = [
    { label: 'Default', value: 'default' },
    { label: 'Custom', value: 'custom' },
    { label: 'SAR Form', value: 'sar' }
];

// Snackbar functionality
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

// Email template and settings
const emailTemplate = ref(`
    <div style="font-family: Arial, sans-serif; background-color: #f7f7f7; padding: 40px;">
        <div style="max-width: 600px; margin: auto; background-color: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);">
            <h1 style="color: #800000; text-align: center;">🎉 Congratulations, {{firstname}} {{surname}}!</h1>
            <p style="font-size: 16px; color: #333; text-align: center;">
                You have successfully passed the <strong>PUPCET</strong>! We are thrilled to welcome you as part of our growing community at Polytechnic University of the Philippines - Taguig Branch.
            </p>
            <div style="text-align: center; margin: 30px 0;">
                <a href="${props.registrationUrl}" 
                style="background-color: #800000; color: #FFD700; text-decoration: none; padding: 15px 25px; font-weight: bold; border-radius: 8px; display: inline-block;">
                Complete Your Registration
                </a>
            </div>
            <p style="font-size: 14px; color: #555; text-align: center;">
                If you have any questions or need help, please contact our admissions office.
            </p>
        </div>
    </div>
`.trim());

const templateType = ref("default");
const sarEnrollmentDate = ref(new Date().toISOString().split('T')[0]);
const sarEnrollmentTime = ref('09:00');
const defaultTemplatePreview = `
    <div style="font-family: Arial, sans-serif; background-color: #f7f7f7; padding: 40px;">
        <div style="max-width: 600px; margin: auto; background-color: #fff; border-radius: 12px; padding: 30px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);">
            <h1 style="color: #800000; text-align: center;">🎉 Congratulations, {{firstname}} {{surname}}!</h1>
            <p style="font-size: 16px; color: #333; text-align: center;">
                You have successfully passed the <strong>PUPCET</strong>! We are thrilled to welcome you as part of our growing community at Polytechnic University of the Philippines - Taguig Branch.
            </p>
            <div style="text-align: center; margin: 30px 0;">
                <a href="${props.registrationUrl}" 
                style="background-color: #800000; color: #FFD700; text-decoration: none; padding: 15px 25px; font-weight: bold; border-radius: 8px; display: inline-block;">
                Complete Your Registration
                </a>
            </div>
            <p style="font-size: 14px; color: #555; text-align: center;">
                If you have any questions or need help, please contact our admissions office.
            </p>
        </div>
    </div>
`.trim();

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
const sortKey = ref("surname");
const sortOrder = ref("asc");
const currentPage = ref(1);
const itemsPerPage = 10;

const onSearchInput = debounce(() => {
    debouncedSearchTerm.value = searchTerm.value.toLowerCase();
    currentPage.value = 1;
}, 300);

watch([filterSchoolYear, filterBatchNumber, sortKey, sortOrder], () => {
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

        return matchesSearch && matchesSchoolYear && matchesBatch;
    });
});

const sortedPassers = computed(() => {
    return filteredPassers.value.slice().sort((a, b) => {
        const key = sortKey.value;
        let valA = a[key]?.toString().toLowerCase() || "";
        let valB = b[key]?.toString().toLowerCase() || "";

        if (valA < valB) return sortOrder.value === "asc" ? -1 : 1;
        if (valA > valB) return sortOrder.value === "asc" ? 1 : -1;
        return 0;
    });
});

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
    const quillContainer = document.querySelector(".ql-editor");
    const messageHtml = quillContainer
        ? quillContainer.innerHTML
        : emailTemplate.value;

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

// Modal state
const showEditModal = ref(false);
const editingPasser = ref(null);
const saving = ref(false);

function openEditModal(passer) {
    editingPasser.value = { ...passer };
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
        await axios.put(
            `/test-passers/${editingPasser.value.test_passer_id}`,
            editingPasser.value
        );
        show("Passer updated successfully!", "success");

        const index = flatPassers.value.findIndex(
            (p) => p.test_passer_id === editingPasser.value.test_passer_id
        );
        if (index !== -1) {
            flatPassers.value[index] = { ...editingPasser.value };
        }

        closeEditModal();
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
    };
    showAddModal.value = true;
}

function closeAddModal() {
    showAddModal.value = false;
}

async function saveNewPasser(passerData) {
    saving.value = true;
    start();
    try {
        const response = await axios.post(
            "/test-passers-store",
            passerData
        );
        show("Passer added successfully!", "success");

        flatPassers.value.unshift(response.data);

        closeAddModal();
    } catch (error) {
        show("Failed to add passer.", "error");
        console.error(error);
    } finally {
        finish();
        saving.value = false;
    }
}
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