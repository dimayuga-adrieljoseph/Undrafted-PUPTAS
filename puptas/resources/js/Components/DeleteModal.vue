<template>
    <transition name="fade">
        <div
            v-if="isOpen"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 dark:bg-white"
        >
            <div class="bg-white rounded-lg shadow-lg w-96 p-6 text-center dark:bg-gray-800">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-200">
                    Confirm Deletion
                </h3>
                <p class="text-[#000103] mt-2">
                    Are you sure you want to delete this program?
                </p>

                <div class="mt-4 flex justify-center space-x-3">
                    <button
                        @click="confirmDelete"
                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg dark:text-gray-900"
                    >
                        Delete
                    </button>
                    <button
                        @click="closeModal"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-900 px-4 py-2 rounded-lg dark:bg-gray-600 dark:hover:bg-gray-500 dark:text-gray-200"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';

const props = defineProps({
    isOpen: Boolean,
});

const emit = defineEmits(["confirm", "close"]);

const confirmDelete = () => {
    emit("confirm");
};

const closeModal = () => {
    emit("close");
};
</script>

<style>
/* Fade transition for modal */
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.3s ease;
}
.fade-enter, .fade-leave-to {
    opacity: 0;
}
</style>
