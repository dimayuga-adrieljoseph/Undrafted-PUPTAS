<script setup>
import { ref, onMounted, computed, watch } from "vue";
const axios = window.axios;
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteModal from "@/Components/DeleteModal.vue";
import { Head, Link } from '@inertiajs/vue3';

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

const fetchPrograms = async () => {
  try {
    const response = await axios.get("/programs/list");
    programs.value = response.data;
  } catch (error) {
    console.error("Error fetching programs:", error);
  }
};

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

const uniqueStrands = computed(() => {
  const strandSet = new Set();
  programs.value.forEach(p => {
    if (p.strands && Array.isArray(p.strands)) {
      p.strands.forEach(s => strandSet.add(s.code));
    }
  });
  return [...strandSet];
});

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
    filtered = filtered.filter(p => p.strands && p.strands.some(s => s.code === filterStrand.value));
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

watch(selectAll, (value) => {
  selectedPrograms.value = value ? paginatedPrograms.value.map(p => p.id) : [];
});

watch(paginatedPrograms, () => {
  selectAll.value = paginatedPrograms.value.length > 0 &&
    paginatedPrograms.value.every(p => selectedPrograms.value.includes(p.id));
});

watch([searchQuery, filterStrand, sortKey, sortAsc], () => {
  currentPage.value = 1;
});

const startEdit = (program) => {
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
    const response = await axios.put(`/programs/${editingProgram.value.id}`, payload);
    const index = programs.value.findIndex(p => p.id === editingProgram.value.id);
    if (index !== -1) programs.value[index] = { ...programs.value[index], ...response.data.program };
    editingProgram.value = null;
    await fetchPrograms();
  } catch (error) {
    const validationErrors = error.response?.data?.errors;
    if (validationErrors) {
      alert(Object.values(validationErrors)[0]?.[0] || "Update failed");
      return;
    }
    alert(error.response?.data?.message || "Update failed");
  }
};

const cancelEdit = () => { editingProgram.value = null; };

const toggleStrand = (strandId) => {
  const index = editingProgram.value.strand_ids.indexOf(strandId);
  if (index === -1) editingProgram.value.strand_ids.push(strandId);
  else editingProgram.value.strand_ids.splice(index, 1);
};

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

const openDeleteModal = (id) => { programToDelete.value = id; deleteModalOpen.value = true; };
const closeDeleteModal = () => { deleteModalOpen.value = false; programToDelete.value = null; };

const clearFilters = () => {
  searchQuery.value = "";
  filterStrand.value = "";
  sortKey.value = "name";
  sortAsc.value = true;
  currentPage.value = 1;
};

const toggleSortOrder = () => { sortAsc.value = !sortAsc.value; };
</script>

<template>
  <Head title="Programs Management" />
  <AppLayout>

    <!-- Header -->
    <div class="px-4 md:px-8 mb-8">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Programs Management</h1>
          <p class="text-gray-600 dark:text-gray-400 mt-2">Manage academic programs and slot allocations</p>
        </div>
        <Link
          href="/addindex"
          class="hidden md:inline-flex items-center gap-2 px-4 py-2 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          Add Program
        </Link>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 gap-4 px-4 md:px-8 mb-8">
      <div
        v-for="(item, index) in summaryItems"
        :key="index"
        class="bg-white dark:bg-gray-800 rounded-xl p-4 md:p-5 shadow-lg border border-gray-200 dark:border-gray-700 flex items-center gap-3"
      >
        <div :class="[
          'p-2 md:p-3 rounded-lg flex-shrink-0',
          item.color === 'blue' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300' :
          'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-300'
        ]">
          <component :is="item.icon" class="w-5 h-5 md:w-6 md:h-6" />
        </div>
        <div class="min-w-0">
          <p class="text-gray-500 dark:text-gray-400 text-xs font-medium truncate">{{ item.label }}</p>
          <p class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">{{ item.value.toLocaleString() }}</p>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="px-4 md:px-8 mb-6">
      <div class="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-lg border border-gray-200 dark:border-gray-700">
        <div class="relative mb-3">
          <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5 dark:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search by name, code, or strand..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
          />
        </div>
        <div class="flex flex-wrap gap-2">
          <select
            v-model="filterStrand"
            class="flex-1 min-w-[120px] px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C]"
          >
            <option value="">All Strands</option>
            <option v-for="strand in uniqueStrands" :key="strand" :value="strand">{{ strand }}</option>
          </select>
          <select
            v-model="sortKey"
            class="flex-1 min-w-[100px] px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C]"
          >
            <option value="name">Name</option>
            <option value="code">Code</option>
            <option value="slots">Slots</option>
          </select>
          <button @click="toggleSortOrder" class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path v-if="sortAsc" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
              <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4 4m0 0l4-4m-4 4V4" />
            </svg>
            {{ sortAsc ? 'Asc' : 'Desc' }}
          </button>
          <button @click="clearFilters" class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
            Clear
          </button>
        </div>
      </div>
    </div>

    <!-- Programs List -->
    <div class="px-4 md:px-8">

      <!-- Bulk Actions -->
      <div v-if="selectedPrograms.length > 0" class="mb-4 bg-[#9E122C]/5 border border-[#9E122C]/20 rounded-lg p-3 flex items-center justify-between">
        <span class="text-sm text-gray-700 dark:text-gray-300">
          <span class="font-medium">{{ selectedPrograms.length }}</span> selected
        </span>
        <button class="px-3 py-1 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition">
          Bulk Delete
        </button>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">

        <!-- Edit Panel (shared, shown above table/cards when editing) -->
        <div v-if="editingProgram" class="p-4 md:p-6 bg-blue-50 dark:bg-blue-900/10 border-b border-blue-200 dark:border-blue-800">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 truncate">Editing: {{ editingProgram.name }}</h3>
            <div class="flex gap-2 flex-shrink-0">
              <button @click="saveEdit" class="flex-1 sm:flex-none px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition flex items-center justify-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Save
              </button>
              <button @click="cancelEdit" class="flex-1 sm:flex-none px-3 py-2 bg-gray-500 text-white text-sm rounded-lg hover:bg-gray-600 transition flex items-center justify-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Cancel
              </button>
            </div>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-3">
              <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Program Name</label>
                <input v-model="editingProgram.name" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Program Code</label>
                <input v-model="editingProgram.code" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Available Slots</label>
                <input v-model="editingProgram.slots" type="number" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Eligible Strands</label>
                <div class="flex flex-wrap gap-2 max-h-24 overflow-y-auto">
                  <button v-for="strand in availableStrands" :key="strand.id" type="button" @click="toggleStrand(strand.id)"
                    :class="['px-3 py-1 text-xs rounded-full transition flex-shrink-0', editingProgram.strand_ids.includes(strand.id) ? 'bg-[#9E122C] text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-500']">
                    {{ strand.code }}
                  </button>
                </div>
              </div>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Minimum Requirements</label>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Mathematics</label>
                  <input v-model="editingProgram.math" type="number" placeholder="Score" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent" />
                </div>
                <div>
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Science</label>
                  <input v-model="editingProgram.science" type="number" placeholder="Score" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent" />
                </div>
                <div>
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">English</label>
                  <input v-model="editingProgram.english" type="number" placeholder="Score" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent" />
                </div>
                <div>
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">GWA</label>
                  <input v-model="editingProgram.gwa" type="number" step="0.01" placeholder="GWA" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C] focus:border-transparent" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
              <tr>
                <th class="px-6 py-3 text-left">
                  <input type="checkbox" v-model="selectAll" class="rounded border-gray-300 text-[#9E122C] focus:ring-[#9E122C] dark:border-gray-600" />
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Program</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Code</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Strands</th>
                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Slots</th>
                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="program in paginatedPrograms" :key="program.id"
                :class="['transition', editingProgram?.id === program.id ? 'bg-blue-50/50 dark:bg-blue-900/5' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50']">
                <td class="px-6 py-4">
                  <input type="checkbox" v-model="selectedPrograms" :value="program.id" class="rounded border-gray-300 text-[#9E122C] focus:ring-[#9E122C] dark:border-gray-600" />
                </td>
                <td class="px-6 py-4">
                  <p class="font-semibold text-gray-900 dark:text-white">{{ program.name }}</p>
                </td>
                <td class="px-6 py-4 font-mono text-[#9E122C] dark:text-red-400 font-semibold">{{ program.code }}</td>
                <td class="px-6 py-4">
                  <div class="flex flex-wrap gap-1">
                    <span v-for="strand in program.strands" :key="strand.id"
                      class="px-2 py-0.5 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded-full text-xs font-medium">
                      {{ strand.code }}
                    </span>
                    <span v-if="!program.strands || program.strands.length === 0"
                      class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-full text-xs">
                      No Strand
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4 text-center">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                    {{ program.slots || 0 }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex justify-end gap-1">
                    <button @click="startEdit(program)" class="p-2 text-gray-400 hover:text-[#9E122C] dark:text-gray-400 dark:hover:text-[#9E122C] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition" title="Edit">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>
                    <button @click="openDeleteModal(program.id)" class="p-2 text-gray-400 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-500 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition" title="Delete">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Cards -->
        <div class="md:hidden divide-y divide-gray-100 dark:divide-gray-700">
          <div v-for="program in paginatedPrograms" :key="program.id"
            class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <input type="checkbox" v-model="selectedPrograms" :value="program.id" class="rounded border-gray-300 text-[#9E122C] focus:ring-[#9E122C] dark:border-gray-600 flex-shrink-0" />
                <div class="min-w-0">
                  <p class="font-semibold text-gray-900 dark:text-white truncate">{{ program.name }}</p>
                  <p class="text-xs font-mono text-[#9E122C] dark:text-red-400">{{ program.code }}</p>
                </div>
              </div>
              <div class="flex gap-1 flex-shrink-0">
                <button @click="startEdit(program)" class="p-2 text-gray-400 hover:text-[#9E122C] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                <button @click="openDeleteModal(program.id)" class="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                {{ program.slots || 0 }} slots
              </span>
              <span v-for="strand in program.strands" :key="strand.id"
                class="px-2 py-0.5 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded-full text-xs font-medium">
                {{ strand.code }}
              </span>
              <span v-if="!program.strands || program.strands.length === 0"
                class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-full text-xs">
                No Strand
              </span>
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
          <Link href="/addindex" class="inline-flex items-center gap-2 px-4 py-2 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add New Program
          </Link>
        </div>
      </div>

      <!-- Pagination -->
      <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-3">
        <div class="text-sm text-gray-600 dark:text-gray-400 order-2 sm:order-1">
          Showing <span class="font-medium">{{ paginatedPrograms.length }}</span> of
          <span class="font-medium">{{ filteredPrograms.length }}</span> programs
        </div>
        <div class="flex items-center gap-2 order-1 sm:order-2">
          <button @click="currentPage--" :disabled="currentPage === 1"
            class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium">
            Previous
          </button>
          <div class="flex items-center gap-1 text-sm">
            <span class="px-3 py-2 bg-[#9E122C] text-white rounded-lg font-medium">{{ currentPage }}</span>
            <span class="text-gray-500 dark:text-gray-400 px-1">of</span>
            <span class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 font-medium">{{ totalPages || 1 }}</span>
          </div>
          <button @click="currentPage++" :disabled="currentPage === totalPages || totalPages === 0"
            class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition disabled:opacity-50 disabled:cursor-not-allowed text-sm font-medium">
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Floating Add Button (Mobile) -->
    <Link
      href="/addindex"
      class="md:hidden fixed bottom-6 right-6 bg-[#9E122C] text-white rounded-full w-14 h-14 flex items-center justify-center shadow-lg hover:bg-[#b51834] transition hover:scale-110 z-40"
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
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: #FBCB77; border-radius: 5px; }
::-webkit-scrollbar-thumb { background: #9E122C; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #EE6A43; }
</style>
