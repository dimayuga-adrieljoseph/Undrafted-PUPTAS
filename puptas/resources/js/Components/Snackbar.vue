<template>
    <transition name="slide-fade">
        <div
            v-if="show"
            :class="[
                'fixed z-50 px-6 py-4 rounded-xl shadow-lg text-white ' +
                    'font-semibold flex items-center',
                position === 'top-right'
                    ? 'top-4 right-4'
                    : 'bottom-4 right-4',
                type === 'success'
                    ? 'bg-green-600'
                    : type === 'error'
                        ? 'bg-red-600'
                        : type === 'info'
                            ? 'bg-blue-600'
                            : 'bg-gray-600'
            ]"
        >
            <!-- Icon -->
            <svg
                v-if="type === 'success'"
                class="h-6 w-6 mr-2 flex-shrink-0"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            <svg
                v-else-if="type === 'error'"
                class="h-6 w-6 mr-2 flex-shrink-0"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            <svg
                v-else-if="type === 'info'"
                class="h-6 w-6 mr-2 flex-shrink-0"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            <svg
                v-else
                class="h-6 w-6 mr-2 flex-shrink-0"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                />
            </svg>

            <!-- Message -->
            <span>{{ message }}</span>
        </div>
    </transition>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
    show: Boolean,
    type: {
        type: String,
        default: "success",
        validator: (value) =>
            ['success', 'error', 'info', 'warning'].includes(value)
    },
    message: String,
    position: {
        type: String,
        default: "top-right",
        validator: (value) =>
            ['top-right', 'bottom-right'].includes(value)
    }
});
</script>

<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
    transition: all 0.3s ease;
}

.slide-fade-enter-from {
    opacity: 0;
    transform: translateY(-20px);
}

.slide-fade-leave-to {
    opacity: 0;
    transform: translateY(20px);
}

/* Animation for top-right position */
.top-4.right-4.slide-fade-enter-from {
    transform: translateX(100%);
}

.top-4.right-4.slide-fade-leave-to {
    transform: translateX(100%);
}
</style>
