<script setup>
import { ref, onMounted, computed, watch } from "vue";
const axios = window.axios;
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteModal from "@/Components/DeleteModal.vue";
import { Head, Link } from '@inertiajs/vue3';

const programs = ref([]);
const deleteModalOpen = ref(false);
const programToDelete = ref(null);

const searchQuery = ref("");
const filterStrand = ref("");
const sortKey = ref("name");  // default sort by name
const sortAsc = ref(true);

const currentPage = ref(1);
const itemsPerPage = 10;

const editingProgram = ref(null);
const isEditing = ref(false);

const fetchPrograms = async () => {
  try {
    const response = await axios.get("/programs/list");
    programs.value = response.data;
  } catch (error) {
    console.error("Error fetching programs:", error);
  }
};
// };


onMounted(fetchPrograms);

// Filter programs by search and strand
const filteredPrograms = computed(() => {
  let filtered = programs.value.filter(p => {
    const search = searchQuery.value.toLowerCase();
    const matchesSearch = p.name.toLowerCase().includes(search) || p.code.toLowerCase().includes(search) || (p.strand && p.strand.toLowerCase().includes(search));
    const matchesStrand = filterStrand.value ? p.strand === filterStrand.value : true;
    return matchesSearch && matchesStrand;
  });
  
  // Sort filtered list
  filtered.sort((a, b) => {
    const aVal = (a[sortKey.value] || "").toString().toLowerCase();
    const bVal = (b[sortKey.value] || "").toString().toLowerCase();
    return sortAsc.value ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
  });

  return filtered;
});

const totalPages = computed(() => Math.ceil(filteredPrograms.value.length / itemsPerPage));

const paginatedPrograms = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage;
  return filteredPrograms.value.slice(start, start + itemsPerPage);
});

// Reset page when filters/search changes
watch([searchQuery, filterStrand, sortKey, sortAsc], () => {
  currentPage.value = 1;
});

const startEdit = (program) => {
  editingProgram.value = { ...program };
  isEditing.value = true;
};

const saveEdit = async () => {
  try {
    const response = await axios.put(
      `/programs/update/${editingProgram.value.id}`, 
      editingProgram.value
    );

    // Update the local programs array with the saved data
    const index = programs.value.findIndex(p => p.id === editingProgram.value.id);
    if (index !== -1) {
      programs.value[index] = { ...editingProgram.value };
    }
    
    isEditing.value = false;
    editingProgram.value = null;
    
    // Refresh programs list to ensure data is in sync
    await fetchPrograms();
    
    console.log('Program updated successfully:', response.data);
  } catch (error) {
    console.error("Error updating program:", error);
    if (error.response) {
      console.error("Server response:", error.response.data);
      alert(`Failed to update program: ${error.response.data.message || 'Unknown error'}`);
    }
  }
};

const cancelEdit = () => {
  editingProgram.value = null;
  isEditing.value = false;
};

const confirmDeleteProgram = async () => {
  if (!programToDelete.value) return;
  try {
    await axios.delete(`/programs/delete/${programToDelete.value}`, getAxiosConfig());
    programs.value = programs.value.filter(p => p.id !== programToDelete.value);
    closeDeleteModal();
  } catch (error) {
    console.error("Error deleting program:", error);
    if (error.response) {
      alert(`Failed to delete program: ${error.response.data.message || 'Unknown error'}`);
    }
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
</script>

<template>
  <AppLayout title="Programs">
    <div class="max-w-7xl mx-auto p-6">
      <h2 class="text-2xl font-bold text-[#9E122C] mb-4">Programs List</h2>
      <Link
        href="/addindex"
        class="fixed bottom-6 right-6 bg-[#9E122C] hover:bg-[#EE6A43] text-white rounded-full w-14 h-14 flex items-center justify-center shadow-lg transition-all group"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        <span class="absolute bottom-16 right-1/2 translate-x-1/2 bg-gray-900 text-white text-sm rounded-lg px-3 py-1 opacity-0 group-hover:opacity-100 transition-opacity shadow-lg">
          Add Program
        </span>
      </Link>

      <!-- Filters Row -->
      <div class="flex flex-wrap items-center gap-4 mb-4">
        <!-- Search -->
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search programs..."
          class="flex-grow max-w-xs p-2 border border-[#EE6A43] rounded-lg text-gray-900 placeholder-gray-700 focus:ring-[#9E122C] focus:border-[#9E122C]"
        />

        <!-- Filter Strand -->
        <select
          v-model="filterStrand"
          class="p-2 border border-[#EE6A43] rounded-lg text-gray-900"
        >
          <option value="">All Strands</option>
          <option
            v-for="strand in [...new Set(programs.map(p => p.strand).filter(Boolean))]"
            :key="strand"
            :value="strand"
          >{{ strand }}</option>
        </select>

        <!-- Sort Key -->
        <select
          v-model="sortKey"
          class="p-2 border border-[#EE6A43] rounded-lg text-gray-900"
        >
          <option value="name">Sort by Name</option>
          <option value="code">Sort by Code</option>
          <option value="strand">Sort by Strand</option>
          <option value="math">Sort by Math</option>
          <option value="science">Sort by Science</option>
          <option value="english">Sort by English</option>
          <option value="gwa">Sort by GWA</option>
          <option value="pupcet">Sort by PUPCET</option>
          <option value="slots">Sort by Slots</option>
        </select>

        <!-- Sort Order Toggle -->
        <button
          @click="toggleSortOrder"
          class="px-3 py-2 border border-[#9E122C] rounded-lg text-[#9E122C] hover:bg-[#FDE8EA] transition"
          :title="sortAsc ? 'Ascending' : 'Descending'"
        >
          {{ sortAsc ? 'Asc ↑' : 'Desc ↓' }}
        </button>

        <!-- Clear Filters -->
        <button
          @click="clearFilters"
          class="px-3 py-2 border border-[#9E122C] rounded-lg text-[#9E122C] hover:bg-[#FDE8EA] transition"
        >
          Clear Filters
        </button>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="w-full border-collapse border border-gray-300">
          <thead>
            <tr class="bg-[#FBCB77]">
              <th class="border border-gray-300 p-2">Code</th>
              <th class="border border-gray-300 p-2">Name</th>
              <th class="border border-gray-300 p-2">Strand</th>
              <th class="border border-gray-300 p-2">Math</th>
              <th class="border border-gray-300 p-2">Science</th>
              <th class="border border-gray-300 p-2">English</th>
              <th class="border border-gray-300 p-2">GWA</th>
              <th class="border border-gray-300 p-2">PUPCET</th>
              <th class="border border-gray-300 p-2">Slots</th>
              <th class="border border-gray-300 p-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="program in paginatedPrograms"
              :key="program.id"
              class="hover:bg-gray-200"
            >
              <template v-if="editingProgram && editingProgram.id === program.id">
                <td class="border p-2"><input v-model="editingProgram.code" class="border p-1 w-full" /></td>
                <td class="border p-2"><input v-model="editingProgram.name" class="border p-1 w-full" /></td>
                <td class="border p-2"><input v-model="editingProgram.strand" class="border p-1 w-full" /></td>
                <td class="border p-2"><input v-model="editingProgram.math" type="number" class="border p-1 w-full" /></td>
                <td class="border p-2"><input v-model="editingProgram.science" type="number" class="border p-1 w-full" /></td>
                <td class="border p-2"><input v-model="editingProgram.english" type="number" class="border p-1 w-full" /></td>
                <td class="border p-2"><input v-model="editingProgram.gwa" type="number" class="border p-1 w-full" /></td>
                <td class="border p-2"><input v-model="editingProgram.pupcet" type="number" class="border p-1 w-full" /></td>
                <td class="border p-2"><input v-model="editingProgram.slots" type="number" class="border p-1 w-full" /></td>
                <td class="border p-2 space-x-2">
                  <button @click="saveEdit" class="bg-[#9E122C] text-white px-2 py-1 rounded">Save</button>
                  <button @click="cancelEdit" class="bg-gray-400 text-white px-2 py-1 rounded">Cancel</button>
                </td>
              </template>
              <template v-else>
                <td class="border p-2">{{ program.code }}</td>
                <td class="border p-2">{{ program.name }}</td>
                <td class="border p-2">{{ program.strand || 'N/A' }}</td>
                <td class="border p-2">{{ program.math }}</td>
                <td class="border p-2">{{ program.science }}</td>
                <td class="border p-2">{{ program.english }}</td>
                <td class="border p-2">{{ program.gwa }}</td>
                <td class="border p-2">{{ program.pupcet }}</td>
                <td class="border p-2">{{ program.slots }}</td>
                <td class="border p-2">
                  <div class="flex gap-2 justify-center">
                    <button @click="startEdit(program)" class="bg-[#FFFFFF] hover:bg-[#A9A9A9] text-white p-2 rounded-full shadow-md transition-all relative group">
                      ✏️
                      <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:flex bg-gray-800 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">Edit</span>
                    </button>
                    <button @click="openDeleteModal(program.id)" class="bg-red-500 hover:bg-red-600 text-white p-2 rounded-full shadow-md transition-all relative group">
                      🗑️
                      <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:flex bg-gray-800 text-white text-xs rounded px-2 py-1 whitespace-nowrap z-10">Delete</span>
                    </button>
                  </div>
                </td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Controls -->
      <div class="flex justify-between items-center mt-4 text-sm text-gray-700">
        <div>
          Showing {{ paginatedPrograms.length }} of {{ filteredPrograms.length }} programs
        </div>
        <div class="flex gap-2 items-center">
          <button
            @click="currentPage--"
            :disabled="currentPage === 1"
            class="px-3 py-1 border rounded disabled:opacity-50"
          >
            Previous
          </button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button
            @click="currentPage++"
            :disabled="currentPage === totalPages"
            class="px-3 py-1 border rounded disabled:opacity-50"
          >
            Next
          </button>
        </div>
      </div>

      <DeleteModal :isOpen="deleteModalOpen" @confirm="confirmDeleteProgram" @close="closeDeleteModal" />
    </div>
  </AppLayout>
</template>

<style>
/* Custom Scrollbar Styling */
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

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter,
.fade-leave-to {
  opacity: 0;
}
</style>
