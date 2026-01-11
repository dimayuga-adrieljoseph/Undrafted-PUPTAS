<template>
  <div
    class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center p-4 overflow-auto z-50"
  >
    <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] p-6 relative shadow-lg overflow-y-auto">
      <h2 class="text-xl font-semibold mb-6">Edit Passer Details</h2>

      <form @submit.prevent="$emit('save')" class="grid grid-cols-2 gap-x-6 gap-y-4">
        <div>
          <label class="block font-medium mb-1">Surname</label>
          <input v-model="passer.surname" class="border rounded px-3 py-1 w-full" required />
        </div>
        <div>
          <label class="block font-medium mb-1">First Name</label>
          <input v-model="passer.first_name" class="border rounded px-3 py-1 w-full" required />
        </div>
        <div>
          <label class="block font-medium mb-1">Middle Name</label>
          <input v-model="passer.middle_name" class="border rounded px-3 py-1 w-full" />
        </div>
        <div>
          <label class="block font-medium mb-1">Date of Birth</label>
          <input type="date" v-model="passer.date_of_birth" class="border rounded px-3 py-1 w-full" />
        </div>

        <div>
          <label class="block font-medium mb-1">Address</label>
          <input v-model="passer.address" class="border rounded px-3 py-1 w-full" />
        </div>
        <div>
          <label class="block font-medium mb-1">School Address</label>
          <input v-model="passer.school_address" class="border rounded px-3 py-1 w-full" />
        </div>
        <div>
          <label class="block font-medium mb-1">SHS School</label>
          <input v-model="passer.shs_school" class="border rounded px-3 py-1 w-full" />
        </div>
        <div>
          <label class="block font-medium mb-1">Strand</label>
          <input v-model="passer.strand" class="border rounded px-3 py-1 w-full" />
        </div>
        <div>
          <label class="block font-medium mb-1">Year Graduated</label>
          <input type="number" v-model="passer.year_graduated" min="1900" max="2099" class="border rounded px-3 py-1 w-full" />
        </div>
        <div>
          <label class="block font-medium mb-1">Email</label>
          <input v-model="passer.email" type="email" class="border rounded px-3 py-1 w-full" required />
        </div>
        <div>
          <label class="block font-medium mb-1">Reference Number</label>
          <input v-model="passer.reference_number" class="border rounded px-3 py-1 w-full" />
        </div>
        <div>
          <label class="block font-medium mb-1">Batch Number</label>
          <input v-model="passer.batch_number" class="border rounded px-3 py-1 w-full" />
        </div>
        <div>
          <label class="block font-medium mb-1">School Year</label>
          <input v-model="passer.school_year" class="border rounded px-3 py-1 w-full" />
        </div>

        <!-- Buttons -->
        <div class="col-span-2 flex justify-end gap-3 mt-6">
          <button type="button" @click="$emit('close')" class="px-5 py-2 rounded border">Cancel</button>
          <button type="submit" class="bg-red-800 text-white px-5 py-2 rounded hover:bg-red-900">Save</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  passer: Object
})
</script>
