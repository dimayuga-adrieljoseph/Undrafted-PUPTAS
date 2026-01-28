<template>
    <div
        v-if="show"
        class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center p-4 overflow-auto z-50"
    >
        <div
            class="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] p-6 relative shadow-2xl overflow-y-auto"
        >
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-900">
                    Add New Passer
                </h2>
                <button
                    @click="close"
                    class="p-2 hover:bg-gray-100 rounded-lg transition"
                >
                    <svg class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form
                @submit.prevent="save"
                class="grid grid-cols-2 gap-x-6 gap-y-4"
            >
                <!-- Personal Information -->
                <div class="col-span-2 grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Surname *
                        </label>
                        <input
                            type="text"
                            v-model="newPasser.surname"
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            First Name *
                        </label>
                        <input
                            type="text"
                            v-model="newPasser.first_name"
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Middle Name
                        </label>
                        <input
                            type="text"
                            v-model="newPasser.middle_name"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition"
                        />
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="col-span-2 grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Email *
                        </label>
                        <input
                            type="email"
                            v-model="newPasser.email"
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Date of Birth
                        </label>
                        <input
                            type="date"
                            v-model="newPasser.date_of_birth"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition"
                        />
                    </div>
                </div>

                <!-- Address -->
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Address
                    </label>
                    <input
                        type="text"
                        v-model="newPasser.address"
                        class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition"
                    />
                </div>

                <!-- School Information -->
                <div class="col-span-2 grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            SHS School
                        </label>
                        <input
                            type="text"
                            v-model="newPasser.shs_school"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            School Address
                        </label>
                        <input
                            type="text"
                            v-model="newPasser.school_address"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition"
                        />
                    </div>
                </div>

                <div class="col-span-2 grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Strand
                        </label>
                        <input
                            type="text"
                            v-model="newPasser.strand"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Year Graduated
                        </label>
                        <input
                            type="text"
                            v-model="newPasser.year_graduated"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Reference Number
                        </label>
                        <input
                            type="text"
                            v-model="newPasser.reference_number"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition"
                        />
                    </div>
                </div>

                <!-- Batch Information -->
                <div class="col-span-2 grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            School Year *
                        </label>
                        <input
                            type="text"
                            v-model="newPasser.school_year"
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition"
                            placeholder="e.g., 2023-2024"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Batch Number *
                        </label>
                        <input
                            type="text"
                            v-model="newPasser.batch_number"
                            required
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-[#9E122C] transition"
                            placeholder="e.g., 1"
                        />
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="col-span-2 flex justify-end space-x-3 pt-6 border-t border-gray-200">
                    <button
                        type="button"
                        @click="close"
                        class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="px-6 py-3 bg-[#9E122C] text-white rounded-xl hover:bg-[#800918] focus:outline-none focus:ring-2 focus:ring-[#9E122C]/50 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="saving">Saving...</span>
                        <span v-else>Add Passer</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";

const props = defineProps({
    show: Boolean,
    saving: Boolean,
});

const emit = defineEmits(['close', 'save']);

const newPasser = ref({
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
});

const close = () => {
    newPasser.value = {
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
    emit('close');
};

const save = () => {
    emit('save', newPasser.value);
};
</script>