<script setup>
/**
 * ImageViewer — reusable document/image lightbox.
 *
 * Replaces the ad-hoc `<img>` + close-button overlays that were duplicated
 * across the PUPTAS pages so uploaded documents (report cards, certificates,
 * IDs, etc.) can actually be inspected:
 *
 *  - fits the whole image inside the viewport on open (aspect ratio preserved)
 *  - zoom in / out / reset, with zoom anchored to the cursor or touch point
 *  - drag to pan while zoomed (mouse, pen and touch) + two-finger pinch
 *  - Escape / + / - / 0 keyboard shortcuts, focus management
 *  - loading and broken-image states
 *
 * Pure Vue + Tailwind, no new dependencies.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    /** Absolute image URL. Never modified by the viewer. */
    src: { type: String, default: '' },
    alt: { type: String, default: 'Document preview' },
    /** Multiplicative factor applied per zoom step. */
    zoomStep: { type: Number, default: 1.25 },
    /** How many times larger than the fit scale the user may zoom in. */
    maxZoom: { type: Number, default: 8 },
    /** Allow dismissing by clicking the dimmed backdrop. */
    closeOnBackdrop: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const ZOOM_WHEEL_SENSITIVITY = 0.002;
const CLICK_SLOP_PX = 4;
const LOAD_TIMEOUT_MS = 20000;

const rootEl = ref(null);
const canvasEl = ref(null);
const imageEl = ref(null);
const closeButtonEl = ref(null);

const status = ref('idle'); // idle | loading | loaded | error
const naturalWidth = ref(0);
const naturalHeight = ref(0);
const scale = ref(1);
const fitScale = ref(1);
const maxScale = ref(1);
const offsetX = ref(0);
const offsetY = ref(0);
const isPanning = ref(false);

const isZoomed = computed(() => scale.value > fitScale.value + 0.0001);
const zoomPercent = computed(() =>
    fitScale.value ? Math.round((scale.value / fitScale.value) * 100) : 100
);
const isReady = computed(() => status.value === 'loaded');
const documentLabel = computed(() => props.alt || 'Document preview');

/**
 * The image box is anchored on the centre of the canvas (`left-1/2 top-1/2`),
 * so `-50%,-50%` first recentres the *whole box* on that anchor point. The pan
 * offsets are therefore always measured from the canvas centre — exactly what
 * the clamp, hit-test and zoom-anchor maths assume. With `offsetX/offsetY = 0`
 * the fit-sized image sits dead centre, fully visible.
 */
const imageTransform = computed(
    () =>
        'translate(-50%, -50%) translate(' +
        offsetX.value +
        'px,' +
        offsetY.value +
        'px) scale(' +
        scale.value +
        ')'
);

const announcement = ref('');

// ── Pointer / pinch bookkeeping ───────────────────────────────────────────
const pointers = new Map();
let panStart = null;
let pinchStart = null;
let dragDistance = 0;

// ── Lifecycle bookkeeping ─────────────────────────────────────────────────
let previousBodyOverflow = null;
let previouslyFocused = null;
let loadTimer = null;

const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

const announce = () => {
    announcement.value = `Zoom ${zoomPercent.value} percent`;
};

/* ------------------------------------------------------------------ *
 * Fit-to-viewport
 * ------------------------------------------------------------------ */
/**
 * @param {number|null} relativeScale keep this multiple of the fit scale
 *        (used so a viewport resize does not lose the user's zoom level).
 */
const measureFit = (relativeScale = null) => {
    const canvas = canvasEl.value;
    const width = naturalWidth.value;
    const height = naturalHeight.value;

    if (!canvas || !width || !height) return;

    // clientWidth/clientHeight already exclude the canvas margins, so the
    // whole image is guaranteed to stay inside the visible area on open.
    const availableWidth = canvas.clientWidth || window.innerWidth;
    const availableHeight = canvas.clientHeight || window.innerHeight;

    // Never upscale on open: small images stay at their natural size.
    fitScale.value = Math.min(availableWidth / width, availableHeight / height, 1);
    // Bound the zoom to `maxZoom` times the fit size so a huge scan cannot be
    // scaled without limit. The floor keeps max >= fit for a sane clamp.
    maxScale.value = Math.max(fitScale.value * props.maxZoom, fitScale.value);

    const target = relativeScale === null ? fitScale.value : relativeScale * fitScale.value;
    scale.value = clamp(target, fitScale.value, maxScale.value);
    offsetX.value = 0;
    offsetY.value = 0;
};

const clampOffsets = () => {
    const canvas = canvasEl.value;
    if (!canvas) return;

    // Never let the image drift further out than its own edges.
    const limitX = Math.max(0, (naturalWidth.value * scale.value - canvas.clientWidth) / 2);
    const limitY = Math.max(0, (naturalHeight.value * scale.value - canvas.clientHeight) / 2);

    offsetX.value = clamp(offsetX.value, -limitX, limitX);
    offsetY.value = clamp(offsetY.value, -limitY, limitY);
};

/**
 * @param {number} nextScale target scale
 * @param {{x:number,y:number}|null} anchor point relative to the canvas
 *        centre that must stay visually fixed; null zooms from the centre.
 */
const applyScale = (nextScale, anchor = null) => {
    if (status.value !== 'loaded') return;

    const target = clamp(nextScale, fitScale.value, maxScale.value);
    const ratio = target / scale.value;
    if (ratio === 1) return;

    // Keep the anchor point stationary while the scale changes.
    const ax = anchor ? anchor.x : 0;
    const ay = anchor ? anchor.y : 0;
    offsetX.value = ax - (ax - offsetX.value) * ratio;
    offsetY.value = ay - (ay - offsetY.value) * ratio;

    scale.value = target;
    clampOffsets();
    announce();
};

const zoomIn = (anchor = null) => applyScale(scale.value * props.zoomStep, anchor);
const zoomOut = (anchor = null) => applyScale(scale.value / props.zoomStep, anchor);
const resetZoom = () => applyScale(fitScale.value, null);

/* ------------------------------------------------------------------ *
 * Pointer interaction: drag-to-pan, pinch-to-zoom, cursor-anchored zoom
 * ------------------------------------------------------------------ */
const toCanvasCentreCoords = (clientX, clientY) => {
    const rect = canvasEl.value?.getBoundingClientRect();
    if (!rect) return { x: 0, y: 0 };
    return {
        x: clientX - (rect.left + rect.width / 2),
        y: clientY - (rect.top + rect.height / 2),
    };
};

const isOverImage = (clientX, clientY) => {
    if (status.value !== 'loaded') return false;
    const point = toCanvasCentreCoords(clientX, clientY);
    return (
        Math.abs(point.x - offsetX.value) <= (naturalWidth.value * scale.value) / 2 &&
        Math.abs(point.y - offsetY.value) <= (naturalHeight.value * scale.value) / 2
    );
};

const pointerSpread = () => {
    const [a, b] = [...pointers.values()];
    return Math.hypot(a.x - b.x, a.y - b.y);
};

const pinchAnchor = () => {
    const [a, b] = [...pointers.values()];
    return toCanvasCentreCoords((a.x + b.x) / 2, (a.y + b.y) / 2);
};

const capturePointer = (target, pointerId) => {
    try {
        target?.setPointerCapture?.(pointerId);
    } catch {
        /* Pointer capture is a progressive enhancement only. */
    }
};

const releasePointer = (target, pointerId) => {
    try {
        if (target?.hasPointerCapture?.(pointerId)) target.releasePointerCapture(pointerId);
    } catch {
        /* ignore */
    }
};

const onPointerDown = (event) => {
    if (status.value !== 'loaded' || event.button > 0) return;
    if (!isOverImage(event.clientX, event.clientY)) return;

    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    capturePointer(event.currentTarget, event.pointerId);

    if (pointers.size === 2) {
        pinchStart = { spread: pointerSpread(), scale: scale.value };
        panStart = null;
        isPanning.value = false;
        return;
    }

    panStart = {
        x: event.clientX,
        y: event.clientY,
        offsetX: offsetX.value,
        offsetY: offsetY.value,
    };
    dragDistance = 0;
    isPanning.value = true;
};

const onPointerMove = (event) => {
    if (!pointers.has(event.pointerId)) return;
    pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });

    if (pinchStart && pointers.size === 2) {
        const spread = pointerSpread();
        if (pinchStart.spread > 0 && spread > 0) {
            applyScale(pinchStart.scale * (spread / pinchStart.spread), pinchAnchor());
        }
        return;
    }

    if (!panStart) return;

    const dx = event.clientX - panStart.x;
    const dy = event.clientY - panStart.y;
    dragDistance = Math.max(dragDistance, Math.hypot(dx, dy));
    offsetX.value = panStart.offsetX + dx;
    offsetY.value = panStart.offsetY + dy;
    clampOffsets();
};

const onPointerUp = (event) => {
    pointers.delete(event.pointerId);
    releasePointer(event.currentTarget, event.pointerId);

    if (pointers.size < 2) pinchStart = null;
    if (pointers.size === 0) {
        panStart = null;
        isPanning.value = false;
    }
};

const onCanvasClick = (event) => {
    // A drag must never dismiss the viewer.
    if (dragDistance > CLICK_SLOP_PX) {
        dragDistance = 0;
        return;
    }
    dragDistance = 0;
    if (isOverImage(event.clientX, event.clientY)) return;
    if (props.closeOnBackdrop) close();
};

const onWheel = (event) => {
    if (status.value !== 'loaded' || !event.deltaY) return;
    const factor = 1 - event.deltaY * ZOOM_WHEEL_SENSITIVITY;
    applyScale(scale.value * factor, toCanvasCentreCoords(event.clientX, event.clientY));
};

const onDoubleClick = (event) => {
    if (status.value !== 'loaded' || !isOverImage(event.clientX, event.clientY)) return;
    if (isZoomed.value) {
        resetZoom();
    } else {
        applyScale(
            Math.max(fitScale.value * 2, 1),
            toCanvasCentreCoords(event.clientX, event.clientY)
        );
    }
};

/* ------------------------------------------------------------------ *
 * Loading / error states
 * ------------------------------------------------------------------ */
const clearLoadTimer = () => {
    if (loadTimer) {
        clearTimeout(loadTimer);
        loadTimer = null;
    }
};

const onImageLoad = (event) => {
    clearLoadTimer();
    const element = event?.target || imageEl.value;
    if (!element) return;

    naturalWidth.value = element.naturalWidth || 0;
    naturalHeight.value = element.naturalHeight || 0;

    if (!naturalWidth.value || !naturalHeight.value) {
        status.value = 'error';
        return;
    }

    status.value = 'loaded';
    measureFit();
    announce();
};

const onImageError = () => {
    clearLoadTimer();
    status.value = 'error';
};

const startLoading = () => {
    clearLoadTimer();
    naturalWidth.value = 0;
    naturalHeight.value = 0;
    scale.value = 1;
    fitScale.value = 1;
    maxScale.value = 1;
    offsetX.value = 0;
    offsetY.value = 0;
    isPanning.value = false;
    pointers.clear();
    panStart = null;
    pinchStart = null;
    status.value = props.src ? 'loading' : 'error';

    if (!props.src) return;

    // Safety net so a hung request cannot leave a spinner spinning forever.
    loadTimer = setTimeout(() => {
        if (status.value === 'loading') status.value = 'error';
    }, LOAD_TIMEOUT_MS);
};


/* ------------------------------------------------------------------ *
 * Keyboard shortcuts, focus management and lifecycle
 * ------------------------------------------------------------------ */
const close = () => emit('close');

const focusableControls = () => {
    if (!rootEl.value) return [];
    return Array.from(rootEl.value.querySelectorAll('button:not([disabled])'));
};

const onKeydown = (event) => {
    if (!props.show) return;

    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        close();
        return;
    }

    if (event.key === 'Tab') {
        const controls = focusableControls();
        if (controls.length === 0) return;
        const first = controls[0];
        const last = controls[controls.length - 1];
        const active = document.activeElement;
        if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        } else if (event.shiftKey && active === first) {
            event.preventDefault();
            last.focus();
        }
        return;
    }

    // Leave browser zoom / devtools shortcuts untouched.
    if (event.ctrlKey || event.metaKey || event.altKey) return;

    if (event.key === '+' || event.key === '=' || event.key === 'Add') {
        event.preventDefault();
        zoomIn();
    } else if (event.key === '-' || event.key === '_' || event.key === 'Subtract') {
        event.preventDefault();
        zoomOut();
    } else if (event.key === '0') {
        event.preventDefault();
        resetZoom();
    }
};

const onResize = () => {
    if (status.value !== 'loaded') return;
    // Preserve the current relative zoom across viewport changes.
    measureFit(fitScale.value ? scale.value / fitScale.value : 1);
};

const lockBodyScroll = () => {
    if (previousBodyOverflow === null) {
        previousBodyOverflow = document.body.style.overflow;
    }
    document.body.style.overflow = 'hidden';
};

const unlockBodyScroll = () => {
    if (previousBodyOverflow === null) return;
    document.body.style.overflow = previousBodyOverflow;
    previousBodyOverflow = null;
};

const teardown = () => {
    document.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', onResize);
    clearLoadTimer();
    unlockBodyScroll();

    pointers.clear();
    panStart = null;
    pinchStart = null;
    isPanning.value = false;

    // Never strand focus in a dialog that is no longer on screen.
    if (
        previouslyFocused &&
        typeof previouslyFocused.focus === 'function' &&
        previouslyFocused.isConnected
    ) {
        previouslyFocused.focus();
    }
    previouslyFocused = null;
};

watch(
    () => props.show,
    async (isOpen) => {
        if (isOpen) {
            previouslyFocused = document.activeElement;
            startLoading();

            document.addEventListener('keydown', onKeydown);
            window.addEventListener('resize', onResize);
            lockBodyScroll();

            await nextTick();
            closeButtonEl.value?.focus();

            // Cached images can already be complete before Vue attaches @load.
            const element = imageEl.value;
            if (element?.complete) {
                if (element.naturalWidth > 0) onImageLoad({ target: element });
                else onImageError();
            }
        } else {
            teardown();
        }
    },
    // `immediate` so the viewer still initialises when it is mounted already open.
    { immediate: true }
);

// Same viewer, different document.
watch(
    () => props.src,
    () => {
        if (props.show) startLoading();
    }
);

onBeforeUnmount(teardown);
</script>


<template>
    <Teleport to="body">
        <transition name="image-viewer">
            <div
                v-if="show"
                ref="rootEl"
                class="fixed inset-0 z-[9999] flex flex-col bg-black/90"
                role="dialog"
                aria-modal="true"
                :aria-label="alt || 'Document image viewer'"
                @contextmenu.prevent
            >
                <!--
                    Header — mirrors the PUPTAS modal header (maroon bar, white
                    title, red-100 subtitle, translucent close button) so the
                    viewer reads as a native PUPTAS surface.
                -->
                <header
                    class="shrink-0 flex items-center justify-between gap-3 px-3 py-2 sm:px-5 sm:py-3 bg-[#9E122C]"
                >
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div
                            class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0"
                            aria-hidden="true"
                        >
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-sm sm:text-base font-bold text-white leading-tight truncate">
                                {{ documentLabel }}
                            </h2>
                            <p class="hidden sm:block text-xs text-red-100 leading-tight truncate">
                                Uploaded document preview
                            </p>
                        </div>
                    </div>

                    <button
                        ref="closeButtonEl"
                        type="button"
                        class="flex-shrink-0 flex items-center justify-center w-9 h-9 min-w-[44px] min-h-[44px] rounded-full bg-white/20 text-white hover:bg-white/30 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FBCB77] transition-colors"
                        aria-label="Close image viewer"
                        data-test="image-viewer-close"
                        @click="close"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </header>

                <!-- Canvas: the image is fit and centred inside this area -->
                <div
                    ref="canvasEl"
                    class="relative flex-1 min-h-0 m-2 sm:m-4 overflow-hidden"
                    :class="isPanning ? 'cursor-grabbing' : isZoomed ? 'cursor-grab' : 'cursor-default'"
                    :style="{ touchAction: 'none' }"
                    data-test="image-viewer-canvas"
                    @pointerdown="onPointerDown"
                    @pointermove="onPointerMove"
                    @pointerup="onPointerUp"
                    @pointercancel="onPointerUp"
                    @pointerleave="onPointerUp"
                    @click="onCanvasClick"
                    @wheel.prevent="onWheel"
                    @dblclick="onDoubleClick"
                >
                    <!-- Image -->
                    <img
                        v-if="status !== 'error' && src"
                        ref="imageEl"
                        :src="src"
                        :alt="alt"
                        :style="[
                            naturalWidth ? { width: naturalWidth + 'px', height: naturalHeight + 'px' } : {},
                            {
                                opacity: status === 'loaded' ? 1 : 0,
                                transform: imageTransform,
                                transition: isPanning ? 'none' : 'transform 160ms ease-out',
                            },
                        ]"
                        class="absolute left-1/2 top-1/2 max-w-none select-none rounded-sm shadow-2xl"
                        draggable="false"
                        data-test="image-viewer-image"
                        @load="onImageLoad"
                        @error="onImageError"
                    />

                    <!-- Loading state — matches the PUPTAS global loading card -->
                    <div
                        v-if="status === 'loading'"
                        class="absolute inset-0 flex items-center justify-center px-6"
                        data-test="image-viewer-loading"
                    >
                        <div
                            class="flex flex-col items-center gap-3 rounded-2xl border border-gray-200 bg-white px-6 py-5 shadow-2xl dark:border-gray-800 dark:bg-gray-900"
                        >
                            <svg
                                class="h-8 w-8 animate-spin text-[#9E122C] dark:text-white"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-50" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            <p class="text-xs font-medium text-gray-700 dark:text-gray-200">Loading document…</p>
                        </div>
                    </div>

                    <!-- Error state — PUPTAS card + red accent chip -->
                    <div
                        v-if="status === 'error'"
                        class="absolute inset-0 flex items-center justify-center px-4"
                        data-test="image-viewer-error"
                    >
                        <div
                            class="w-full max-w-sm rounded-2xl border border-gray-200 bg-white px-6 py-5 text-center shadow-2xl dark:border-gray-700 dark:bg-gray-800"
                        >
                            <div
                                class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400"
                                aria-hidden="true"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-2.5L13.73 4c-.77-.83-2.69-.83-3.46 0L3.34 16.5c-.77.83.19 2.5 1.73 2.5z"
                                    />
                                </svg>
                            </div>
                            <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Image unavailable
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                This document could not be displayed. It may have been removed or the link may
                                have expired.
                            </p>
                        </div>
                    </div>
                </div>

                <!--
                    Toolbar — a PUPTAS surface (white / gray-800 card, gray-200
                    border, shadow-2xl) so the controls read like the rest of the
                    app rather than third-party viewer chrome.
                -->
                <div class="shrink-0 flex flex-col items-center gap-2 px-3 pb-3 sm:pb-5">
                    <div
                        class="flex items-center gap-2 rounded-full border border-gray-200 bg-white p-1 shadow-2xl dark:border-gray-700 dark:bg-gray-800 sm:p-1.5"
                        role="group"
                        aria-label="Zoom controls"
                        data-test="image-viewer-toolbar"
                    >
                        <button
                            type="button"
                            class="flex h-9 w-9 min-w-[44px] min-h-[44px] items-center justify-center rounded-full text-gray-600 transition-colors hover:bg-gray-100 hover:text-[#9E122C] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#9E122C] disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white dark:focus-visible:ring-[#FBCB77] dark:disabled:hover:bg-transparent dark:disabled:hover:text-gray-300"
                            :disabled="!isReady || !isZoomed"
                            aria-label="Zoom out"
                            data-test="image-viewer-zoom-out"
                            @click="zoomOut()"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14" />
                            </svg>
                        </button>

                        <span
                            class="min-w-[3.5rem] text-center text-xs font-semibold tabular-nums text-gray-900 dark:text-gray-100"
                            data-test="image-viewer-zoom-level"
                        >{{ zoomPercent }}%</span>

                        <button
                            type="button"
                            class="flex h-9 w-9 min-w-[44px] min-h-[44px] items-center justify-center rounded-full text-gray-600 transition-colors hover:bg-gray-100 hover:text-[#9E122C] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#9E122C] disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white dark:focus-visible:ring-[#FBCB77] dark:disabled:hover:bg-transparent dark:disabled:hover:text-gray-300"
                            :disabled="!isReady"
                            aria-label="Zoom in"
                            data-test="image-viewer-zoom-in"
                            @click="zoomIn()"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14M5 12h14" />
                            </svg>
                        </button>

                        <span class="w-px h-6 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>

                        <button
                            type="button"
                            class="flex h-9 min-w-[44px] min-h-[44px] items-center justify-center gap-1.5 rounded-full px-2.5 text-[#9E122C] transition-colors hover:bg-[#9E122C]/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#9E122C] disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:text-[#FCECDF] dark:hover:bg-white/10 dark:focus-visible:ring-[#FBCB77] dark:disabled:hover:bg-transparent sm:px-3"
                            :disabled="!isReady"
                            aria-label="Reset zoom"
                            data-test="image-viewer-reset"
                            @click="resetZoom"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 4v6h6M20 20v-6h-6M20 9A8 8 0 006.34 5.34L4 8m16 8l-2.34 2.34A8 8 0 014 15"
                                />
                            </svg>
                            <span class="hidden sm:inline text-xs font-semibold uppercase tracking-widest">Reset</span>
                        </button>
                    </div>

                    <p class="hidden sm:block text-xs text-white/50 text-center" data-test="image-viewer-hint">
                        Drag to pan · Scroll to zoom · Double-click to zoom · Esc to close
                    </p>
                </div>

                <!-- Screen-reader announcements for zoom changes -->
                <p class="sr-only" role="status" aria-live="polite" data-test="image-viewer-status">
                    {{ announcement }}
                </p>
            </div>
        </transition>
    </Teleport>
</template>

<style scoped>
.image-viewer-enter-active,
.image-viewer-leave-active {
    transition: opacity 0.15s ease;
}

.image-viewer-enter-from,
.image-viewer-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .image-viewer-enter-active,
    .image-viewer-leave-active {
        transition: none;
    }
}
</style>

