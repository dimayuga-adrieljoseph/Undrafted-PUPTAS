<!--
  AdmissionResultDocument.vue
  ───────────────────────────
  The "digital admission document" chrome shared by every public
  admission-result state (qualified, waitlisted, below-cutoff waitlist and
  not-qualified).

  Responsibilities:
    • warm paper surface, restrained radius, soft shadow
    • institutional top rule (gold hairline over a maroon band)
    • a very faint PUP watermark, clipped inside the document
    • an optional soft gold highlight behind the hero (qualified only)
    • an understated document footer, so a screenshot still reads as an
      official artefact when separated from the website

  Deliberately NOT glassmorphism: this must read as a document, not as a
  dashboard card. Per-state content is supplied through slots by
  `Pages/Public/CheckStatus.vue`.

  Slots
  ─────
  header  optional institutional masthead (usually AdmissionDocumentHeader)
  default the per-state body
  footer  overrides the default footer line
-->
<script setup>
defineProps({
    /**
     * Decorative watermark drawn behind the content.
     * Extremely low opacity, blurred and aria-hidden — it carries no
     * information, it only lends the surface an official feel.
     */
    watermark: {
        type: String,
        default: '/assets/images/pup_taguig_logo.png',
    },
    /**
     * Soft gold radial highlight behind the hero area.
     * Used only by the qualified state, where a little warmth is warranted.
     */
    glow: {
        type: Boolean,
        default: false,
    },
})
</script>

<template>
    <section
        class="relative isolate overflow-hidden rounded-lg border border-[#e8dfcd]
               bg-gradient-to-b from-[#fffdf7] via-white to-[#fbf8f1]
               shadow-[0_24px_60px_-32px_rgba(80,16,16,0.45),0_2px_10px_-6px_rgba(31,26,18,0.3)]"
    >
        <!-- Institutional top rule: gold hairline over a maroon band -->
        <div class="relative z-10 h-1.5 w-full bg-[#800000]" aria-hidden="true">
            <div class="absolute inset-x-0 top-0 h-px w-full bg-[#FFD700]"></div>
        </div>

        <!-- Faint PUP watermark, clipped inside the document -->
        <img
            v-if="watermark"
            :src="watermark"
            alt=""
            aria-hidden="true"
            class="pointer-events-none absolute left-1/2 top-[38%] w-[46%] max-w-[340px]
                   -translate-x-1/2 -translate-y-1/2 select-none opacity-[0.05]
                   blur-[1px] mix-blend-multiply"
        />

        <!-- Soft gold highlight (qualified only) -->
        <div
            v-if="glow"
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-72
                   bg-[radial-gradient(ellipse_60%_50%_at_50%_0%,rgba(255,215,0,0.22),transparent_72%)]"
        ></div>

        <!-- Content -->
        <div class="relative z-10 px-5 py-8 sm:px-9 sm:py-10">
            <slot name="header" />
            <slot />
        </div>

        <!-- Understated footer — keeps a detached screenshot self-explanatory -->
        <footer
            class="relative z-10 border-t border-[#efe7d6] bg-[#faf6ec]/70 px-5 py-4
                   text-center sm:px-9"
        >
            <slot name="footer">
                <p
                    class="text-[10px] font-semibold uppercase tracking-[0.26em] text-[#8a7a4b] sm:text-[11px]"
                >
                    Admission and Registration Office
                </p>
                <p
                    class="mt-1.5 text-[9px] font-medium uppercase tracking-[0.2em] text-[#a9a08a] sm:text-[10px]"
                >
                    PUP&ndash;Taguig Admission Results &middot; SY 2026&ndash;2027
                </p>
            </slot>
        </footer>
    </section>
</template>
