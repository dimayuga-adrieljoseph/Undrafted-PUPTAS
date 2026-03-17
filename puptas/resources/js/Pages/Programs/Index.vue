<script setup>
import { ref, onMounted, computed, watch } from "vue";
const axios = window.axios;
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteModal from "@/Components/DeleteModal.vue";
import { Head, Link } from '@inertiajs/vue3';

// State
const programs = ref([]);
const availableStrands = ref([]);
const deleteModalOpen = ref(false);
const programToDelete = ref(null);

const searchQuery = ref("");
const filterStrand = ref("");
const sortKey = ref("name");
const sortAsc = ref(true);

const currentPage = ref(1);
const itemsPerPage = 10;

const editingProgram = ref(null);
const selectedPrograms = ref([]);
const selectAll = ref(false);

// Fetch programs
const fetchPrograms = async () => {
  try {
    const response = await axios.get("/programs/list");
    programs.value = response.data;
  } catch (error) {
    console.error("Error fetching programs:", error);
  }
};

// Fetch available strands
const fetchStrands = async () => {
  try {
    const response = await axios.get("/programs/strands");
    availableStrands.value = response.data;
  } catch (error) {
    console.error("Error fetching strands:", error);
  }
};

onMounted(() => {
  fetchPrograms();
  fetchStrands();
});

// Get unique strands from all programs
const uniqueStrands = computed(() => {
  const strandSet = new Set();
  programs.value.forEach(p => {
    if (p.strands && Array.isArray(p.strands)) {
      p.strands.forEach(s => strandSet.add(s.code));
    }
  });
  return [...strandSet];
});

// Helper to get strand display text
const getStrandDisplay = (program) => {
  if (program.strands && program.strands.length > 0) {
    return program.strands.map(s => s.code).join(', ');
  }
  return 'No Strand';
};

// Summary items for stats cards - Only Total Programs and Total Slots
const summaryItems = computed(() => [
  { 
    label: "Total Programs", 
    value: programs.value.length, 
    icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>' },
    color: 'blue'
  },
  { 
    label: "Total Slots", 
    value: programs.value.reduce((acc, p) => acc + (p.slots || 0), 0), 
    icon: { template: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>' },
    color: 'yellow'
  }
]);

// Filtered & sorted programs
const filteredPrograms = computed(() => {
  let filtered = programs.value.filter(p => {
    const search = searchQuery.value.toLowerCase();
    const strandCodes = p.strands ? p.strands.map(s => s.code.toLowerCase()).join(' ') : '';
    return search === "" || 
      p.name.toLowerCase().includes(search) || 
      p.code.toLowerCase().includes(search) ||
      strandCodes.includes(search);
  });

  if (filterStrand.value) {
    filtered = filtered.filter(p => {
      return p.strands && p.strands.some(s => s.code === filterStrand.value);
    });
  }

  filtered.sort((a, b) => {
    let aVal = a[sortKey.value];
    let bVal = b[sortKey.value];
    
    if (['math', 'science', 'english', 'gwa', 'slots'].includes(sortKey.value)) {
      return sortAsc.value ? (aVal || 0) - (bVal || 0) : (bVal || 0) - (aVal || 0);
    }
    
    aVal = (aVal || "").toString().toLowerCase();
    bVal = (bVal || "").toString().toLowerCase();
    return sortAsc.value ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
  });

  return filtered;
});

const totalPages = computed(() => Math.ceil(filteredPrograms.value.length / itemsPerPage));

const paginatedPrograms = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  return filteredPrograms.value.slice(start, start + itemsPerPage);
});

// Select all functionality
watch(selectAll, (value) => {
  if (value) {
    selectedPrograms.value = paginatedPrograms.value.map(p => p.id);
  } else {
    selectedPrograms.value = [];
  }
});

watch(paginatedPrograms, () => {
  selectAll.value = paginatedPrograms.value.length > 0 && 
    paginatedPrograms.value.every(p => selectedPrograms.value.includes(p.id));
});

// Filters
watch([searchQuery, filterStrand, sortKey, sortAsc], () => {
  currentPage.value = 1;
});

// Edit
const startEdit = (program) => {
  // Create a copy with strand_ids for editing
  editingProgram.value = { 
    ...program,
    strand_ids: program.strands ? program.strands.map(s => s.id) : []
  };
};

const saveEdit = async () => {
  try {
    const payload = {
      name: editingProgram.value.name,
      code: editingProgram.value.code,
      slots: editingProgram.value.slots === "" ? null : Number(editingProgram.value.slots),
      math: editingProgram.value.math === "" ? null : Number(editingProgram.value.math),
      science: editingProgram.value.science === "" ? null : Number(editingProgram.value.science),
      english: editingProgram.value.english === "" ? null : Number(editingProgram.value.english),
      gwa: editingProgram.value.gwa === "" ? null : Number(editingProgram.value.gwa),
      strand_ids: editingProgram.value.strand_ids,
    };

    const response = await axios.put(
      `/programs/${editingProgram.value.id}`,
      payload
    );
    const index = programs.value.findIndex(p => p.id === editingProgram.value.id);
    if (index !== -1) programs.value[index] = { ...programs.value[index], ...response.data.program };
    editingProgram.value = null;
    await fetchPrograms();
  } catch (error) {
    const validationErrors = error.response?.data?.errors;
    if (validationErrors) {
      const firstError = Object.values(validationErrors)[0]?.[0];
      alert(firstError || "Update failed");
      return;
    }

    alert(error.response?.data?.message || "Update failed");
  }
};

const cancelEdit = () => {
  editingProgram.value = null;
};

// Get strand name by ID
const getStrandName = (strandId) => {
  const strand = availableStrands.value.find(s => s.id === strandId);
  return strand ? strand.code : strandId;
};

// Toggle strand selection in edit mode
const toggleStrand = (strandId) => {
  const index = editingProgram.value.strand_ids.indexOf(strandId);
  if (index === -1) {
    editingProgram.value.strand_ids.push(strandId);
  } else {
    editingProgram.value.strand_ids.splice(index, 1);
  }
};

// Delete
const confirmDeleteProgram = async () => {
  if (!programToDelete.value) return;
  try {
    await axios.delete(`/programs/${programToDelete.value}`);
    programs.value = programs.value.filter(p => p.id !== programToDelete.value);
    closeDeleteModal();
  } catch (error) {
    alert(error.response?.data?.message || "Delete failed");
  }
};

const openDeleteModal = (id) => {
  programToDelete.value = id;
  deleteModalOpen.value = true;
};

const closeDeleteModal = () => {
  deleteModalOpen.value = false;
  programToDelete.value = null;
};

const clearFilters = () => {
  searchQuery.value = "";
  filterStrand.value = "";
  sortKey.value = "name";
  sortAsc.value = true;
  currentPage.value = 1;
};

const toggleSortOrder = () => {
  sortAsc.value = !sortAsc.value;
};

// Helper to check if program has requirements
const hasRequirements = (program) => {
  return program.math || program.science || program.english || program.gwa;
};
</script>

<template>
  <AppLayout title="Programs">
    <Head title="Programs Management" />
    
    <!-- Header Section -->
    <div class="px-4 md:px-8 mb-8">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Programs Management</h1>
          <p class="text-gray-600 dark:text-gray-400 mt-2">Manage academic programs and slot allocations</p>
        </div>
        <Link
          href="/addindex"
          class="px-4 py-2 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium flex items-center space-x-2"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          <span>Add Program</span>
        </Link>
      </div>
    </div>

    <!-- Stats Cards - Only 2 cards now -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 px-4 md:px-8 mb-8 max-w-3xl">
      <div
        v-for="(item, index) in summaryItems"
        :key="index"
        class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300"
      >
        <div class="flex items-start justify-between">
          <div>
            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">{{ item.label }}</p>
            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ item.value.toLocaleString() }}</p>
          </div>
          <div :class="[
            'p-3 rounded-lg',
            item.color === 'blue' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300' :
            'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-300'
          ]">
            <component :is="item.icon" class="w-6 h-6" />
          </div>
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="px-4 md:px-8 mb-8">
      <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700">
        <div class="flex flex-col lg:flex-row gap-4">
          <!-- Search -->
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search Programs</label>
            <div class="relative">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search by name, code, or strand..."
                class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
              />
              <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
          </div>

          <!-- Strand Filter -->
          <div class="w-full lg:w-48">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Strand</label>
            <select
              v-model="filterStrand"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
            >
              <option value="">All Strands</option>
              <option
                v-for="strand in uniqueStrands"
                :key="strand"
                :value="strand"
              >
                {{ strand }}
              </option>
            </select>
          </div>

          <!-- Sort By -->
          <div class="w-full lg:w-48">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sort By</label>
            <select v-model="sortKey" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent">
              <option value="name">Program Name</option>
              <option value="code">Program Code</option>
              <option value="strand">Strand</option>
              <option value="slots">Available Slots</option>
            </select>
          </div>

          <!-- Sort Order -->
          <div class="flex items-end space-x-2">
            <button 
              @click="toggleSortOrder" 
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

    <!-- Programs List -->
    <div class="px-4 md:px-8">
      <!-- Bulk Actions Bar -->
      <div v-if="selectedPrograms.length > 0" class="mb-4 bg-[#9E122C]/5 border border-[#9E122C]/20 rounded-lg p-3 flex items-center justify-between">
        <span class="text-sm text-gray-700 dark:text-gray-300">
          <span class="font-medium">{{ selectedPrograms.length }}</span> programs selected
        </span>
        <button class="px-3 py-1 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition">
          Bulk Delete
        </button>
      </div>

      <!-- Table - Requirements column removed -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <!-- Table Header - Removed Requirements column -->
        <div class="grid grid-cols-10 gap-4 px-6 py-4 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
          <div class="col-span-4 flex items-center gap-3">
            <input 
              type="checkbox" 
              v-model="selectAll"
              class="rounded border-gray-300 text-[#9E122C] focus:ring-[#9E122C]"
            >
            <span>Program</span>
          </div>
          <div class="col-span-2">Code</div>
          <div class="col-span-2">Strand</div>
          <div class="col-span-1 text-center">Slots</div>
          <div class="col-span-1 text-right">Actions</div>
        </div>

        <!-- Table Body - Removed Requirements display -->
        <div v-for="program in paginatedPrograms" :key="program.id" 
             class="group hover:bg-gray-50 dark:hover:bg-gray-700/50 transition border-b border-gray-100 dark:border-gray-700 last:border-0">
          
          <!-- Edit Mode -->
          <div v-if="editingProgram?.id === program.id" class="p-6 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Editing: {{ program.name }}</h3>
              <div class="flex gap-2">
                <button @click="saveEdit" class="px-3 py-1.5 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                  Save Changes
                </button>
                <button @click="cancelEdit" class="px-3 py-1.5 bg-gray-500 text-white text-sm rounded-lg hover:bg-gray-600 transition flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                  Cancel
                </button>
              </div>
            </div>

            <!-- Two-column edit layout -->
            <div class="grid grid-cols-2 gap-6">
              <!-- Left Column - Basic Info -->
              <div class="space-y-4">
                <div>
                  <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Program Name</label>
                  <input v-model="editingProgram.name" 
                         class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Program Code</label>
                  <input v-model="editingProgram.code" 
                         class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent" />
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Eligible Strands</label>
                  <div class="flex flex-wrap gap-2">
                    <button v-for="strand in availableStrands" :key="strand.id"
                            type="button"
                            @click="toggleStrand(strand.id)"
                            :class="[
                              'px-3 py-1 text-xs rounded-full transition',
                              editingProgram.strand_ids.includes(strand.id)
                                ? 'bg-[#9E122C] text-white'
                                : 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-500'
                            ]">
                      {{ strand.code }}
                    </button>
                  </div>
                  <p class="text-xs text-gray-400 mt-1">Click to toggle strand selection</p>
                </div>
                <div>
                  <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Available Slots</label>
                  <input v-model="editingProgram.slots" type="number" 
                         class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent" />
                </div>
              </div>

              <!-- Right Column - Requirements (Still in edit mode for data entry) -->
              <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-3">Minimum Requirements</label>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Mathematics</label>
                    <input v-model="editingProgram.math" type="number" 
                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent" 
                           placeholder="Enter score" />
                  </div>
                  <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Science</label>
                    <input v-model="editingProgram.science" type="number" 
                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent" 
                           placeholder="Enter score" />
                  </div>
                  <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">English</label>
                    <input v-model="editingProgram.english" type="number" 
                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent" 
                           placeholder="Enter score" />
                  </div>
                  <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">GWA</label>
                    <input v-model="editingProgram.gwa" type="number" step="0.01" 
                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 focus:ring-2 focus:ring-[#9E122C] focus:border-transparent" 
                           placeholder="Enter GWA" />
                  </div>
                  <div class="col-span-2">
                    <p class="text-xs text-gray-400">Passing PUPCET score is implied.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- View Mode - Removed requirements column -->
          <div v-else class="px-6 py-4 grid grid-cols-10 gap-4 items-center text-sm">
            <div class="col-span-4 flex items-center gap-3">
              <input 
                type="checkbox" 
                v-model="selectedPrograms" 
                :value="program.id"
                class="rounded border-gray-300 text-[#9E122C] focus:ring-[#9E122C]"
              >
              <div>
                <p class="font-semibold text-gray-900 dark:text-white">{{ program.name }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ program.code }}</p>
              </div>
            </div>
            <div class="col-span-2 text-gray-600 dark:text-gray-300">{{ program.code }}</div>
            <div class="col-span-2">
              <div class="flex flex-wrap gap-1">
                <span v-if="program.strands && program.strands.length > 0"
                      v-for="strand in program.strands" :key="strand.id"
                      class="px-2 py-0.5 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded-full text-xs font-medium">
                  {{ strand.code }}
                </span>
                <span v-else class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-full text-xs font-medium">
                  No Strand
                </span>
              </div>
            </div>
            <div class="col-span-1 text-center">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                {{ program.slots || 0 }}
              </span>
            </div>

            <!-- Actions -->
            <div class="col-span-1 text-right">
              <div class="flex justify-end gap-1">
                <button @click="startEdit(program)" class="p-2 text-gray-400 hover:text-[#9E122C] dark:hover:text-[#9E122C] transition rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700" title="Edit">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                <button @click="openDeleteModal(program.id)" class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-500 transition rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700" title="Delete">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="paginatedPrograms.length === 0" class="text-center py-16">
          <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
          </svg>
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No programs found</h3>
          <p class="text-gray-500 dark:text-gray-400 mb-4">Try adjusting your search or filter criteria</p>
          <Link href="/addindex" class="inline-flex items-center px-4 py-2 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add New Program
          </Link>
        </div>
      </div>

      <!-- Pagination -->
      <div class="mt-8 flex flex-col sm:flex-row justify-between items-center gap-4">
        <div class="text-sm text-gray-600 dark:text-gray-400">
          Showing <span class="font-medium">{{ paginatedPrograms.length }}</span> of 
          <span class="font-medium">{{ filteredPrograms.length }}</span> programs
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

    <!-- Floating Add Button (Mobile) -->
    <Link
      href="/addindex"
      class="lg:hidden fixed bottom-6 right-6 bg-[#9E122C] text-white rounded-full w-14 h-14 flex items-center justify-center shadow-lg hover:bg-[#b51834] transition hover:scale-110 z-40"
      title="Add New Program"
    >
      <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
      </svg>
    </Link>

    <!-- Delete Modal -->
    <DeleteModal 
      :isOpen="deleteModalOpen" 
      @confirm="confirmDeleteProgram" 
      @close="closeDeleteModal" 
    />
  </AppLayout>
</template>

<style scoped>
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

/* Transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>