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
          <label class="text-sm font-medium text-[#9E122C]">Math</label>
          <input v-model="newProgram.math" type="number" class="border p-2 rounded w-full mb-2" />
  
          <label class="text-sm font-medium text-[#9E122C]">Science</label>
          <input v-model="newProgram.science" type="number" class="border p-2 rounded w-full mb-2" />
  
          <label class="text-sm font-medium text-[#9E122C]">English</label>
          <input v-model="newProgram.english" type="number" class="border p-2 rounded w-full mb-2" />
        </div>
      </div>
  
      <label class="text-sm font-medium text-[#9E122C]">GWA Requirement</label>
      <input v-model="newProgram.gwa" type="number" class="border p-2 rounded w-full mb-2" />
  
      <label class="text-sm font-medium text-[#9E122C]">PUPCET Score</label>
      <input v-model="newProgram.pupcet" type="number" class="border p-2 rounded w-full mb-2" />
  
      <label class="text-sm font-medium text-[#9E122C]">Slots</label>
      <input v-model="newProgram.slots" type="number" class="border p-2 rounded w-full mb-2" />
  
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
    gwa: 0,
    pupcet: 0,
    slots: 0,
  });
  
  // Get CSRF Token from Laravel
  const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute("content");
  
  // ✅ Function to Add Program
  const addProgram = async () => {
  try {
    const response = await axios.post("/programs/store", newProgram.value);
   
    // Reset form
    newProgram.value = { code: "", name: "", strand: "", math: 0, science: 0, english: 0, gwa: 0, pupcet: 0, slots: 0 };
    router.get('/programs');

  } catch (error) {
    console.error("Error creating program:", error);
  }
};

 
  </script>
  