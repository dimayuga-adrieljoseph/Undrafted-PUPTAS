<template>
  <Head title="Add Program" />
  <AppLayout>
    <div class="w-1/3 bg-gray-100 p-6 rounded-lg shadow-md h-[600px]">
      <h3 class="text-lg font-semibold text-[#9E122C] mb-4">Add New Program</h3>
  
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="text-sm font-medium text-[#9E122C]">Program Code</label>
          <input v-model="newProgram.code" class="border p-2 rounded w-full mb-2" />
  
          <label class="text-sm font-medium text-[#9E122C]">Program Name</label>
          <input v-model="newProgram.name" class="border p-2 rounded w-full mb-2" />
  
          <label class="text-sm font-medium text-[#9E122C]">Strand</label>
          <input v-model="newProgram.strand" class="border p-2 rounded w-full mb-2" />
        </div>
  
        <div>
          <label class="text-sm font-medium text-[#9E122C]">Math Requirement (0-100)</label>
          <input v-model.number="newProgram.math" type="number" step="0.01" min="0" max="100" class="border p-2 rounded w-full mb-2" />
  
          <label class="text-sm font-medium text-[#9E122C]">Science Requirement (0-100)</label>
          <input v-model.number="newProgram.science" type="number" step="0.01" min="0" max="100" class="border p-2 rounded w-full mb-2" />
  
          <label class="text-sm font-medium text-[#9E122C]">English Requirement (0-100)</label>
          <input v-model.number="newProgram.english" type="number" step="0.01" min="0" max="100" class="border p-2 rounded w-full mb-2" />
        </div>
      </div>
  
      <label class="text-sm font-medium text-[#9E122C]">GWA Requirement (1.00 - 100.00)</label>
      <input v-model.number="newProgram.gwa" type="number" step="0.01" min="1" max="100" class="border p-2 rounded w-full mb-2" />

      <label class="text-sm font-medium text-[#9E122C]">PUPCET Score (0+)</label>
      <input v-model.number="newProgram.pupcet" type="number" step="0.01" min="0" class="border p-2 rounded w-full mb-2" />
  
      <label class="text-sm font-medium text-[#9E122C]">Available Slots</label>
      <input v-model.number="newProgram.slots" type="number" step="1" min="1" class="border p-2 rounded w-full mb-2" />
  
      <button @click="addProgram" class="bg-[#9E122C] text-white px-4 py-2 rounded-lg hover:bg-[#EE6A43] w-full">
        Add Program
      </button>
    </div>
    </AppLayout>
  </template>
  
  <script setup>
  import { ref } from "vue";
  const axios = window.axios;
  import AppLayout from "@/Layouts/AppLayout.vue";
  import { Head, router } from '@inertiajs/vue3';
  
  const emit = defineEmits(["programAdded"]);
  
  const newProgram = ref({
    code: "",
    name: "",
    strand: "",
    math: 0,
    science: 0,
    english: 0,
    gwa: 1,
    pupcet: 0,
    slots: 1,
  });
  
  // Get CSRF Token from Laravel
  const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute("content");
  
  // ✅ Function to Add Program
  const addProgram = async () => {
  try {
    const response = await axios.post("/programs/store", newProgram.value);
   
    // Reset form
    newProgram.value = { code: "", name: "", strand: "", math: 0, science: 0, english: 0, gwa: 1, pupcet: 0, slots: 1 };
    router.get('/programs');

  } catch (error) {
    console.error("Error creating program:", error);
  }
};

 
  </script>
  