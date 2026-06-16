<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  text:                { type: String,   default: '' },
  delay:               { type: Number,   default: 200 },  // ms between each word/letter
  className:           { type: String,   default: '' },
  animateBy:           { type: String,   default: 'words' }, // 'words' | 'letters'
  direction:           { type: String,   default: 'top' },   // 'top' | 'bottom'
  threshold:           { type: Number,   default: 0.1 },
  rootMargin:          { type: String,   default: '0px' },
  stepDuration:        { type: Number,   default: 0.35 },  // seconds per element
  onAnimationComplete: { type: Function, default: null },
});

const containerRef = ref(null);
const inView       = ref(false);
const doneCount    = ref(0);

const elements = computed(() =>
  props.animateBy === 'words' ? props.text.split(' ') : props.text.split('')
);

// CSS values derived from direction
const translateFrom = computed(() =>
  props.direction === 'top' ? 'translateY(-20px)' : 'translateY(20px)'
);

const getStyle = (index) => ({
  display: 'inline-block',
  opacity: inView.value ? 1 : 0,
  filter: inView.value ? 'blur(0px)' : 'blur(8px)',
  transform: inView.value ? 'translateY(0)' : translateFrom.value,
  transition: inView.value
    ? `opacity ${props.stepDuration}s ease ${(index * props.delay) / 1000}s,
       filter ${props.stepDuration}s ease ${(index * props.delay) / 1000}s,
       transform ${props.stepDuration}s ease ${(index * props.delay) / 1000}s`
    : 'none',
  willChange: 'transform, filter, opacity',
});

const handleTransitionEnd = (index) => {
  if (!inView.value) return;
  doneCount.value++;
  if (doneCount.value === elements.value.length && props.onAnimationComplete) {
    props.onAnimationComplete();
  }
};

let observer = null;

onMounted(() => {
  if (!containerRef.value) return;
  observer = new IntersectionObserver(
    ([entry]) => {
      if (entry.isIntersecting) {
        inView.value = true;
        observer.unobserve(containerRef.value);
      }
    },
    { threshold: props.threshold, rootMargin: props.rootMargin }
  );
  observer.observe(containerRef.value);
});

onUnmounted(() => {
  observer?.disconnect();
});
</script>

<template>
  <p ref="containerRef" :class="className" style="display: flex; flex-wrap: wrap; margin: 0;">
    <span
      v-for="(segment, index) in elements"
      :key="index"
      :style="getStyle(index)"
      @transitionend="handleTransitionEnd(index)"
    >{{ segment === ' ' ? '\u00A0' : segment }}{{ animateBy === 'words' && index < elements.length - 1 ? '\u00A0' : '' }}</span>
  </p>
</template>
