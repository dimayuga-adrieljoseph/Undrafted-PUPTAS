<template>
    <Head title="All Medical Applications" />
    <MedicalLayout>
        <div class="px-4 md:px-8 mb-8">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Medical Applications</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Review and clear medical requirements</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">Total Applicants</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ users.length }}</p>
                        </div>
                        <div class="p-3 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">Cleared</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ users.filter(u => u.status?.toLowerCase() === 'accepted').length }}</p>
                        </div>
                        <div class="p-3 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">Pending</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ users.filter(u => u.status?.toLowerCase() === 'pending').length }}</p>
                        </div>
                        <div class="p-3 rounded-lg bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">Rejected</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ users.filter(u => u.status?.toLowerCase() === 'rejected').length }}</p>
                        </div>
                        <div class="p-3 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
                    <div class="flex flex-col lg:flex-row gap-4">
                        <!-- Search -->
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search Applicants</label>
                            <div class="relative">
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search by name..."
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                                />
                                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="w-full lg:w-48">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                            <select
                                v-model="statusFilter"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                            >
                                <option value="">All Statuses</option>
                                <option value="accepted">Cleared</option>
                                <option value="pending">Pending</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>

                        <!-- Sort By -->
                        <div class="w-full lg:w-48">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sort By</label>
                            <select v-model="sortKey" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent">
                                <option value="lastname">Last Name</option>
                                <option value="firstname">First Name</option>
                                <option value="email">Email</option>
                                <option value="status">Status</option>
                            </select>
                        </div>

                        <!-- Sort Order -->
                        <div class="flex items-end space-x-2">
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
                            <button 
                                @click="clearFilters" 
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition font-medium"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Applications Table -->
            <div>
                <!-- Loading State -->
                <div v-if="isLoading" class="text-center py-16">
                    <svg class="animate-spin h-10 w-10 text-[#9E122C] mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-gray-600 dark:text-gray-400">Loading applicants...</p>
                </div>

                <!-- Error State -->
                <div v-else-if="errorMessage" class="text-center py-16">
                    <svg class="w-16 h-16 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Error Loading Data</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-4">{{ errorMessage }}</p>
                    <button @click="fetchUsers" class="px-4 py-2 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium">
                        Try Again
                    </button>
                </div>

                <!-- Table -->
                <div v-else class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <!-- Table Header -->
                    <div class="grid grid-cols-10 gap-4 px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        <div class="col-span-4">Name</div>
                        <div class="col-span-3">Course</div>
                        <div class="col-span-2">Status</div>
                        <div class="col-span-1 text-right">Actions</div>
                    </div>

                    <!-- Table Body -->
                    <div v-for="user in paginatedUsers" :key="user.id" 
                         class="group hover:bg-gray-50 dark:hover:bg-gray-700/50 transition border-b border-gray-100 dark:border-gray-700 last:border-0">
                        <div class="px-6 py-4 grid grid-cols-10 gap-4 items-center text-sm">
                            <div class="col-span-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-[#9E122C]/10 text-[#9E122C] flex items-center justify-center font-semibold">
                                        {{ user.firstname?.charAt(0) }}{{ user.lastname?.charAt(0) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ user.firstname }} {{ user.lastname }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ user.email }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-span-3 text-gray-600 dark:text-gray-300">
                                {{ user.program?.name || "—" }}
                            </div>
                            <div class="col-span-2">
                                <span
                                    :class="getStatusClass(user.status)"
                                    class="px-2.5 py-1 rounded-full text-xs font-medium"
                                >
                                    {{ user.status || "Unknown" }}
                                </span>
                            </div>
                            <div class="col-span-1 text-right">
                                <button 
                                    @click="selectUser(user)" 
                                    class="p-2 text-gray-400 hover:text-[#9E122C] dark:hover:text-[#9E122C] transition rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                    title="View Details"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="paginatedUsers.length === 0" class="text-center py-16">
                        <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No applicants found</h3>
                        <p class="text-gray-500 dark:text-gray-400">Try adjusting your search or filter criteria</p>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="mt-8 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Showing <span class="font-medium">{{ paginatedUsers.length }}</span> of 
                        <span class="font-medium">{{ filteredUsers.length }}</span> applicants
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <button 
                            @click="currentPage--" 
                            :disabled="currentPage === 1"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                        >
                            Previous
                        </button>
                        
                        <div class="flex items-center space-x-2">
                            <span class="px-4 py-2 bg-[#9E122C] text-white rounded-lg font-medium">{{ currentPage }}</span>
                            <span class="text-gray-500 dark:text-gray-400">of</span>
                            <span class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 font-medium">{{ totalPages || 1 }}</span>
                        </div>
                        
                        <button 
                            @click="currentPage++" 
                            :disabled="currentPage === totalPages || totalPages === 0"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Details Modal -->
        <transition name="slide-fade">
            <div
                v-if="selectedUser"
                class="fixed top-0 right-0 w-full md:w-2/5 h-full bg-white dark:bg-gray-900 p-6 z-50 shadow-xl overflow-y-auto"
            >
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Medical Review</h3>
                    <button 
                        @click="closeUserCard"
                        class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Profile Section -->
                <div class="flex items-center gap-4 mb-6 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                    <div class="w-16 h-16 rounded-full bg-[#9E122C] text-white flex items-center justify-center text-2xl font-semibold">
                        {{ selectedUser.firstname?.charAt(0) }}{{ selectedUser.lastname?.charAt(0) }}
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ selectedUser.lastname }}, {{ selectedUser.firstname }}
                        </h4>
                        <p class="text-gray-600 dark:text-gray-400">{{ selectedUser.email }}</p>
                    </div>
                </div>

                <!-- Program Info -->
                <div class="mb-6 p-4 bg-[#9E122C]/5 dark:bg-[#9E122C]/10 rounded-xl border border-[#9E122C]/20">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Applied Program</h4>
                    <p class="text-lg font-medium text-gray-900 dark:text-white">
                        {{ selectedUser?.application?.program?.code }} - {{ selectedUser?.application?.program?.name }}
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="mb-6 flex flex-wrap gap-2">
                    <button
                        v-if="!isEvaluating"
                        @click="startEvaluation"
                        class="flex-1 px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition font-medium text-sm flex items-center justify-center gap-2"
                    >
                        <font-awesome-icon :icon="faBolt" class="w-4 h-4" />
                        Actions
                    </button>
                    <button
                        @click="acceptApplication"
                        class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium text-sm"
                    >
                        Clear Medical
                    </button>
                    <button
                        v-if="isEvaluating"
                        @click="cancelEvaluation"
                        class="flex-1 px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition font-medium text-sm"
                    >
                        Cancel
                    </button>
                </div>

                <!-- Return Form -->
                <div v-if="isEvaluating" class="mb-6 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Return Reason / Note
                    </label>
                    <textarea
                        v-model="returnNote"
                        rows="3"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent text-sm"
                        placeholder="Reason for returning documents..."
                    ></textarea>
                    
                    <div class="mt-4 flex justify-end">
                        <button
                            @click="submitReturn"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium text-sm"
                        >
                            Return Selected Files
                        </button>
                    </div>
                </div>

                <!-- Documents Section -->
                <div class="mb-6">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Uploaded Documents</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div
                            v-for="(src, key) in selectedUserFiles"
                            :key="key"
                            class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg"
                        >
                            <div class="flex items-start gap-2 mb-2">
                                <input
                                    v-if="isEvaluating"
                                    type="checkbox"
                                    :id="key"
                                    v-model="filesToReturn[key]"
                                    class="mt-1 rounded border-gray-300 text-[#9E122C] focus:ring-[#9E122C]"
                                />
                                <label :for="key" class="text-xs font-medium text-gray-700 dark:text-gray-300">
                                    {{ formatFileKey(key) }}
                                </label>
                            </div>
                            <img
                                v-if="src"
                                :src="src"
                                :alt="formatFileKey(key)"
                                class="w-full h-24 object-cover rounded-lg cursor-pointer hover:opacity-80 transition"
                                @click="openImageModal(src)"
                            />
                            <div
                                v-else
                                class="w-full h-24 flex items-center justify-center text-xs text-gray-400 bg-gray-200 dark:bg-gray-700 rounded-lg"
                            >
                                No Image
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Application History -->
                <div v-if="selectedUser?.application?.processes?.length">
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Application History</h4>
                    <div class="space-y-3">
                        <div
                            v-for="(process, index) in selectedUser.application.processes"
                            :key="index"
                            class="relative pl-6 pb-3 border-l-2 border-[#9E122C] last:border-0"
                        >
                            <div class="absolute -left-[9px] top-0 w-4 h-4 rounded-full bg-[#9E122C] border-2 border-white dark:border-gray-900"></div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ capitalize(process.stage) }}
                                <span :class="{
                                    'text-green-600 dark:text-green-400': process.status === 'completed',
                                    'text-yellow-600 dark:text-yellow-400': process.status === 'in_progress',
                                    'text-red-600 dark:text-red-400': process.status === 'returned',
                                }">
                                    • {{ capitalize(process.status) }}
                                </span>
                            </p>
                            <p v-if="process.notes" class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic">
                                Note: {{ process.notes }}
                                <span v-if="process.performed_by">
                                    by {{ process.performed_by.firstname }} {{ process.performed_by.lastname }}
                                </span>
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                {{ formatDate(process.created_at) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Image Preview Modal -->
        <div
            v-if="showImageModal"
            class="fixed inset-0 bg-black bg-opacity-80 flex items-center justify-center z-[60] p-4"
            @click.self="closeImageModal"
        >
            <div class="relative max-w-4xl w-full">
                <img
                    :src="previewImage"
                    alt="Preview"
                    class="w-full h-auto rounded-lg shadow-2xl"
                />
                <button
                    @click="closeImageModal"
                    class="absolute top-4 right-4 text-white bg-black bg-opacity-50 rounded-full p-2 hover:bg-opacity-70 transition"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Snackbar -->
        <transition name="fade">
            <div
                v-if="snackbar.visible"
                class="fixed bottom-4 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white px-6 py-3 rounded-lg shadow-lg z-50 text-sm"
            >
                {{ snackbar.message }}
            </div>
        </transition>
    </MedicalLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import MedicalLayout from "@/Layouts/MedicalLayout.vue";
import axios from "axios";

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
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faBolt } from "@fortawesome/free-solid-svg-icons";

const currentPage = ref(1);
const itemsPerPage = 10;
const sortKey = ref("lastname");
const statusFilter = ref("");
const sortAsc = ref(true);
const showStatusDropdown = ref(false);

const page = usePage();
const users = ref(page.props.users || []);

const selectedUser = ref(null);
const isLoading = ref(true);
const errorMessage = ref("");
const searchQuery = ref("");
const selectedUserFiles = ref({});
const selectedProgramId = ref("");
const snackbar = ref({
    visible: false,
    message: "",
});

const showSnackbar = (msg, duration = 3000) => {
    snackbar.value.message = msg;
    snackbar.value.visible = true;
    setTimeout(() => {
        snackbar.value.visible = false;
    }, duration);
};

const getStatusClass = (status) => {
    const s = (status || "").toLowerCase();
    if (s === "accepted") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300";
    if (s === "pending") return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300";
    if (s === "rejected") return "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300";
    return "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400";
};

const fetchUsers = async () => {
    try {
        const response = await fetch("/medical-dashboard/applicants", {
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });
        if (!response.ok) throw new Error("Failed to fetch users");
        users.value = await response.json();
    } catch (error) {
        errorMessage.value = error.message;
    } finally {
        isLoading.value = false;
    }
};

const fetchPrograms = async () => {
    try {
        const response = await axios.get("/interviewer-dashboard/programs");
        availablePrograms.value = response.data.programs;
    } catch (e) {
        console.error("Failed to load programs", e);
    }
};

onMounted(() => {
    fetchUsers();
    fetchPrograms();
});

const filteredUsers = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return users.value
        .filter((u) => {
            const fullName = `${u.firstname} ${u.lastname}`.toLowerCase();
            const matchesSearch = fullName.includes(q);
            const matchesStatus = statusFilter.value
                ? u.status?.toLowerCase() === statusFilter.value
                : true;
            return matchesSearch && matchesStatus;
        })
        .sort((a, b) => {
            const aVal = (a[sortKey.value] || "").toString().toLowerCase();
            const bVal = (b[sortKey.value] || "").toString().toLowerCase();
            return sortAsc.value
                ? aVal.localeCompare(bVal)
                : bVal.localeCompare(aVal);
        });
});

const totalPages = computed(() =>
    Math.ceil(filteredUsers.value.length / itemsPerPage)
);

const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredUsers.value.slice(start, start + itemsPerPage);
});

watch([searchQuery, statusFilter, sortKey, sortAsc], () => {
    currentPage.value = 1;
});

const selectUser = async (user) => {
    try {
        const response = await axios.get(
            `/medical-dashboard/application/${user.id}`
        );

        selectedUser.value = {
            ...user,
            application: {
                ...response.data.user.application,
                processes: response.data.user.application?.processes || [],
                program: response.data.user.application?.program || null,
            },
            grades: response.data.user.grades || null,
        };

        selectedUserFiles.value = response.data.uploadedFiles || {};
        
        await fetchPrograms();
    } catch (error) {
        console.error("Failed to fetch user data:", error);
        selectedUserFiles.value = {};
        selectedUser.value = null;
    }
};

const closeUserCard = () => {
    selectedUser.value = null;
    isEvaluating.value = false;
    filesToReturn.value = {};
    returnNote.value = "";
};

const formatFileKey = (key) => {
    const map = {
        file11: "Grade 11 Report",
        file12: "Grade 12 Report",
        schoolId: "School ID",
        nonEnrollCert: "Certificate of Non-Enrollment",
        psa: "PSA Birth Certificate",
        goodMoral: "Good Moral Certificate",
        underOath: "Under Oath Document",
        photo2x2: "2x2 Photo",
    };
    return map[key] || key;
};

const previewImage = ref(null);
const showImageModal = ref(false);

const openImageModal = (src) => {
    previewImage.value = src;
    showImageModal.value = true;
};

const closeImageModal = () => {
    showImageModal.value = false;
};

const isEvaluating = ref(false);
const filesToReturn = ref({});
const returnNote = ref("");

const startEvaluation = () => {
    isEvaluating.value = true;
    filesToReturn.value = {};
    returnNote.value = "";
};

const cancelEvaluation = () => {
    isEvaluating.value = false;
    filesToReturn.value = {};
    returnNote.value = "";
};

const submitReturn = async () => {
    const selected = Object.keys(filesToReturn.value).filter(
        (k) => filesToReturn.value[k]
    );
    if (selected.length === 0) {
        alert("Please select at least one file to return.");
        return;
    }
    if (!returnNote.value.trim()) {
        alert("Please enter a return reason.");
        return;
    }

    try {
        await axios.post(`/medical/return-files/${selectedUser.value.id}`, {
            files: selected,
            note: returnNote.value.trim(),
        });

        alert("Files returned successfully.");

        isEvaluating.value = false;
        filesToReturn.value = {};
        returnNote.value = "";

        await fetchUsers();
        await selectUser(selectedUser.value);
    } catch (error) {
        console.error(error);
        alert("Return failed. Please try again.");
    }
};

const capitalize = (str) =>
    typeof str === "string" ? str.charAt(0).toUpperCase() + str.slice(1) : "";

const formatDate = (date) => {
    const d = new Date(date);
    return d.toLocaleString();
};

const acceptApplication = async () => {
    try {
        await axios.post(`/medical-dashboard/accept/${selectedUser.value.id}`);
        showSnackbar("Medical requirements cleared successfully.");
        selectedUser.value = null;
        await fetchUsers();
    } catch (e) {
        console.error("Clear failed:", e);
        const msg =
            e.response?.data?.message ||
            "Failed to clear medical requirements.";
        showSnackbar(msg);
    }
};

const availablePrograms = ref([]);

const clearFilters = () => {
    searchQuery.value = "";
    statusFilter.value = "";
    sortKey.value = "lastname";
    sortAsc.value = true;
    currentPage.value = 1;
    showStatusDropdown.value = false;
};
</script>

<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
    transition: all 0.3s ease;
}
.slide-fade-enter-from,
.slide-fade-leave-to {
    transform: translateX(100%);
    opacity: 0;
}

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