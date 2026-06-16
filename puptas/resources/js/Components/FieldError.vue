<script setup>
defineProps({
    message: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div
        v-if="message"
        class="flex items-center gap-1.5 mt-1.5"
        role="alert"
        aria-live="polite"
    >
        <svg
            class="h-3.5 w-3.5 flex-shrink-0 text-red-500 dark:text-red-400"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
        >
            <path
                fill-rule="evenodd"
                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z"
                clip-rule="evenodd"
            />
        </svg>
        <p class="text-xs font-medium text-red-600 dark:text-red-400 break-words">
            {{ message }}
        </p>
    </div>
</template>
