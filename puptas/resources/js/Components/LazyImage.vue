<template>
    <div ref="containerRef" class="lazy-image-container">
        <!-- Loading State -->
        <div
            v-if="!imageLoaded && !error"
            class="w-full h-full flex items-center justify-center bg-gray-100 dark:bg-gray-800"
        >
            <div class="text-center">
                <svg
                    v-if="loading"
                    class="animate-spin h-8 w-8 text-gray-400 dark:text-gray-500 mx-auto mb-2"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    ></circle>
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    ></path>
                </svg>
                <svg
                    v-else
                    class="h-8 w-8 text-gray-400 dark:text-gray-500 mx-auto mb-2"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                </svg>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ loading ? 'Loading...' : 'Image' }}
                </p>
            </div>
        </div>

        <!-- Error State -->
        <div
            v-else-if="error"
            class="w-full h-full flex items-center justify-center bg-red-50 dark:bg-red-900/20"
        >
            <div class="text-center">
                <svg
                    class="h-8 w-8 text-red-400 dark:text-red-500 mx-auto mb-2"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <p class="text-xs text-red-600 dark:text-red-400">Failed to load</p>
            </div>
        </div>

        <!-- Loaded Image -->
        <img
            v-show="imageLoaded && !error"
            :src="imageSrc"
            :alt="alt"
            :class="imageClass"
            @load="onImageLoad"
            @error="onImageError"
        />
    </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    src: {
        type: String,
        default: null,
    },
    alt: {
        type: String,
        default: 'Image',
    },
    imageClass: {
        type: String,
        default: 'w-full h-full object-cover',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    eager: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['load', 'error']);

const containerRef = ref(null);
const imageSrc = ref(null);
const imageLoaded = ref(false);
const error = ref(false);
const observer = ref(null);

const onImageLoad = () => {
    imageLoaded.value = true;
    error.value = false;
    emit('load');
};

const onImageError = () => {
    error.value = true;
    imageLoaded.value = false;
    emit('error');
};

const loadImage = () => {
    if (props.src && !imageSrc.value) {
        imageSrc.value = props.src;
    }
};

const setupObserver = () => {
    if (props.eager || !containerRef.value) {
        loadImage();
        return;
    }

    observer.value = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    loadImage();
                    if (observer.value) {
                        observer.value.disconnect();
                    }
                }
            });
        },
        {
            rootMargin: '50px',
            threshold: 0.01,
        }
    );

    observer.value.observe(containerRef.value);
};

watch(() => props.src, (newSrc) => {
    if (newSrc && !imageSrc.value) {
        if (props.eager) {
            loadImage();
        }
    }
});

onMounted(() => {
    setupObserver();
});

onUnmounted(() => {
    if (observer.value) {
        observer.value.disconnect();
    }
});
</script>

<style scoped>
.lazy-image-container {
    position: relative;
    width: 100%;
    height: 100%;
    min-height: 100px;
}
</style>
