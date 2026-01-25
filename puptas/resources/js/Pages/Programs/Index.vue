<script setup>
import { ref, onMounted, computed, watch } from "vue";
const axios = window.axios;
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteModal from "@/Components/DeleteModal.vue";
import { Head, Link } from '@inertiajs/vue3';

// State
const programs = ref([]);
const deleteModalOpen = ref(false);
const programToDelete = ref(null);

const searchQuery = ref("");
const filterStrand = ref("");
const sortKey = ref("name");
const sortAsc = ref(true);

const currentPage = ref(1);
const itemsPerPage = 10;

const editingProgram = ref(null);
const isEditing = ref(false);

// Fetch programs
const fetchPrograms = async () => {
  try {
    const response = await axios.get("/programs/list");
    programs.value = response.data;
  } catch (error) {
    console.error("Error fetching programs:", error);
  }
};

onMounted(fetchPrograms);

// Computed filtered & sorted programs
const filteredPrograms = computed(() => {
  let filtered = programs.value.filter(p => {
    const search = searchQuery.value.toLowerCase();
    const matchesSearch = p.name.toLowerCase().includes(search) || p.code.toLowerCase().includes(search) || (p.strand && p.strand.toLowerCase().includes(search));
    const matchesStrand = filterStrand.value ? p.strand === filterStrand.value : true;
    return matchesSearch && matchesStrand;
  });

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

// Watch filters
watch([searchQuery, filterStrand, sortKey, sortAsc], () => {
  currentPage.value = 1;
});

// Editing
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
    const index = programs.value.findIndex(p => p.id === editingProgram.value.id);
    if (index !== -1) programs.value[index] = { ...editingProgram.value };
    isEditing.value = false;
    editingProgram.value = null;
    await fetchPrograms();
  } catch (error) {
    console.error(error);
    alert(error.response?.data?.message || "Failed to update program");
  }
};

const cancelEdit = () => {
  editingProgram.value = null;
  isEditing.value = false;
};

// Delete
const confirmDeleteProgram = async () => {
  if (!programToDelete.value) return;
  try {
    await axios.delete(`/programs/delete/${programToDelete.value}`);
    programs.value = programs.value.filter(p => p.id !== programToDelete.value);
    closeDeleteModal();
  } catch (error) {
    alert(error.response?.data?.message || "Failed to delete program");
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

// Filters
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
    <div class="max-w-7xl mx-auto p-6 space-y-6">
      <h2 class="text-3xl font-bold text-[#9E122C]">Programs List</h2>

      <!-- Floating Add Button -->
      <Link
        href="/addindex"
        class="fixed bottom-6 right-6 bg-[#9E122C] hover:bg-[#EE6A43] text-white rounded-full w-14 h-14 flex items-center justify-center shadow-lg transition-all group"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
      </Link>

      <!-- Filters -->
      <div class="flex flex-wrap items-center gap-4">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search programs..."
          class="flex-grow max-w-xs p-3 border border-[#EE6A43] rounded-lg focus:ring-[#9E122C] focus:border-[#9E122C]"
        />
        <select
          v-model="filterStrand"
          class="p-3 border border-[#EE6A43] rounded-lg w-40 md:w-50 lg:w-70 focus:outline-none focus:ring-2 focus:ring-[#9E122C] transition"
        >
          <option value="">All Strands</option>
          <option
            v-for="strand in [...new Set(programs.map(p => p.strand).filter(Boolean))]"
            :key="strand"
            :value="strand"
          >
            {{ strand }}
          </option>
        </select>
        <select v-model="sortKey" class="p-3 border border-[#EE6A43] rounded-lg">
          <option value="name">Name</option>
          <option value="code">Code</option>
          <option value="strand">Strand</option>
          <option value="math">Math</option>
          <option value="science">Science</option>
          <option value="english">English</option>
          <option value="gwa">GWA</option>
          <option value="pupcet">PUPCET</option>
          <option value="slots">Slots</option>
        </select>
        <button @click="toggleSortOrder" class="px-4 py-2 border border-[#9E122C] rounded-lg hover:bg-[#FDE8EA] transition">{{ sortAsc ? 'Asc ↑' : 'Desc ↓' }}</button>
        <button @click="clearFilters" class="px-4 py-2 border border-[#9E122C] rounded-lg hover:bg-[#FDE8EA] transition">Clear</button>
      </div>

      <!-- Cards Grid -->
      <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div v-for="program in paginatedPrograms" :key="program.id" class="bg-white rounded-lg shadow-md p-4 hover:shadow-lg transition relative">
          
          <template v-if="editingProgram && editingProgram.id === program.id">
            <div class="space-y-2">
              <input v-model="editingProgram.code" placeholder="Code" class="border p-2 w-full rounded" />
              <input v-model="editingProgram.name" placeholder="Name" class="border p-2 w-full rounded" />
              <input v-model="editingProgram.strand" placeholder="Strand" class="border p-2 w-full rounded" />
              <div class="grid grid-cols-3 gap-2">
                <input v-model="editingProgram.math" type="number" placeholder="Math" class="border p-2 w-full rounded" />
                <input v-model="editingProgram.science" type="number" placeholder="Science" class="border p-2 w-full rounded" />
                <input v-model="editingProgram.english" type="number" placeholder="English" class="border p-2 w-full rounded" />
                <input v-model="editingProgram.gwa" type="number" step="0.01" min="1" max="100" placeholder="GWA (1-100)" class="border p-2 w-full rounded" />
                <input v-model="editingProgram.pupcet" type="number" step="0.01" min="0" placeholder="PUPCET" class="border p-2 w-full rounded" />
                <input v-model="editingProgram.slots" type="number" placeholder="Slots" class="border p-2 w-full rounded" />
              </div>
              <div class="flex gap-2 justify-end">
                <button @click="saveEdit" class="bg-[#9E122C] text-white px-3 py-1 rounded">Save</button>
                <button @click="cancelEdit" class="bg-gray-400 text-white px-3 py-1 rounded">Cancel</button>
              </div>
            </div>
          </template>

          <template v-else>
            <div class="space-y-1">
              <h3 class="text-lg font-semibold text-[#9E122C]">{{ program.name }}</h3>
              <p class="text-gray-600">Code: {{ program.code }}</p>
              <p class="text-gray-600">Strand: {{ program.strand || 'N/A' }}</p>
              <div class="grid grid-cols-3 gap-2 mt-2 text-sm text-gray-700">
                <div>Math: {{ program.math }}</div>
                <div>Science: {{ program.science }}</div>
                <div>English: {{ program.english }}</div>
                <div>GWA: {{ program.gwa }}</div>
                <div>PUPCET: {{ program.pupcet }}</div>
                <div>Slots: {{ program.slots }}</div>
              </div>
            </div>
            <div class="flex justify-end gap-2 mt-3">
              <button @click="startEdit(program)" class="bg-[#FBBF77] hover:bg-[#FBCB77] text-white px-3 py-1 rounded shadow">Edit</button>
              <button @click="openDeleteModal(program.id)" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded shadow">Delete</button>
            </div>
          </template>

        </div>
      </div>

      <!-- Pagination -->
      <div class="flex justify-between items-center mt-6 text-sm text-gray-700">
        <div>Showing {{ paginatedPrograms.length }} of {{ filteredPrograms.length }} programs</div>
        <div class="flex gap-2 items-center">
          <button @click="currentPage--" :disabled="currentPage === 1" class="px-3 py-1 border rounded disabled:opacity-50">Previous</button>
          <span>Page {{ currentPage }} of {{ totalPages }}</span>
          <button @click="currentPage++" :disabled="currentPage === totalPages" class="px-3 py-1 border rounded disabled:opacity-50">Next</button>
        </div>
      </div>

      <DeleteModal :isOpen="deleteModalOpen" @confirm="confirmDeleteProgram" @close="closeDeleteModal" />
    </div>
  </AppLayout>
</template>

<style>
/* Scrollbar */
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: #FBCB77; border-radius: 5px; }
::-webkit-scrollbar-thumb { background: #9E122C; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #EE6A43; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter, .fade-leave-to { opacity: 0; }
</style>
