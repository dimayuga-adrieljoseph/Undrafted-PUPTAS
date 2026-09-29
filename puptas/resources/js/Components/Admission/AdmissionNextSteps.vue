<!--
  AdmissionNextSteps.vue
  ─────────────────────
  Secondary "What Happens Next" section shown to qualified applicants only.

  Every step below is already part of the PUPTAS workflow described in
  `Pages/Public/Landing.vue` and `Pages/Modal/SlotConfirmationSuccessModal.vue`
  (interview slot -> interview -> medical assessment -> official enrollment).
  No new admission requirement is introduced here.

  The section is intentionally visually secondary to the admission outcome:
  no background fill, muted type, and a number-led timeline.
-->
<script setup>
defineProps({
    /** Section heading. */
    title: {
        type: String,
        default: 'What Happens Next',
    },
    /** Overridable step list; defaults to the PUPTAS admission workflow. */
    steps: {
        type: Array,
        default: () => [
            { label: 'Confirm your interview slot' },
            { label: 'Complete your interview' },
            { label: 'Complete your medical requirements' },
            { label: 'Proceed with enrollment' },
        ],
    },
})
</script>

<template>
    <section class="mt-8" aria-labelledby="next-steps-title">
        <h2
            id="next-steps-title"
            class="text-center text-[10px] font-bold uppercase tracking-[0.28em] text-[#9a8a63] sm:text-[11px]"
        >
            {{ title }}
        </h2>

        <ol class="mt-5 space-y-0">
            <li
                v-for="(step, index) in steps"
                :key="step.label"
                class="relative flex gap-4 pb-4 last:pb-0"
            >
                <!-- Connector rail between step markers -->
                <span
                    v-if="index < steps.length - 1"
                    aria-hidden="true"
                    class="absolute left-[15px] top-8 h-[calc(100%-1rem)] w-px bg-[#e6dcc4]"
                ></span>

                <span
                    class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center
                           rounded-full border border-[#e3d6ae] bg-[#fdf8ea]
                           text-[10px] font-bold tracking-wider text-[#8d6a1f]"
                >
                    {{ String(index + 1).padStart(2, '0') }}
                </span>

                <p class="pt-1.5 text-sm leading-relaxed text-[#4f4939]">
                    {{ step.label }}
                </p>
            </li>
        </ol>
    </section>
</template>
