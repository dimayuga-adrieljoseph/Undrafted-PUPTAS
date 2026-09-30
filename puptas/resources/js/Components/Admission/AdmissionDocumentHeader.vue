<!--
  AdmissionDocumentHeader.vue
  ───────────────────────────
  The institutional masthead shared by every admission-result state.

  Single source of truth for the PUP&ndash;Taguig identity block so the header
  can never drift between the qualified, waitlisted, below-cutoff and
  not-qualified states. Uses the repository logo asset
  (`/assets/images/pup_taguig_logo.png`) exactly as stored — the seal is
  never redrawn or recreated.

  This owns the page's single <h1> ("Admission Result"). It is only ever
  rendered in the result state, so the lookup state keeps its own heading.
-->
<template>
    <header class="text-center">
        <img
            src="/assets/images/pup_taguig_logo.png"
            alt="Polytechnic University of the Philippines &ndash; Taguig Campus"
            class="mx-auto h-20 w-20 object-contain drop-shadow-sm sm:h-24 sm:w-24"
            width="96"
            height="96"
        />

        <p class="mt-4 text-[10px] font-semibold uppercase tracking-[0.34em] text-[#8d6a1f] sm:text-[11px]">
            PUP&ndash;Taguig
        </p>

        <p
            class="mx-auto mt-1.5 max-w-[34ch] text-[10px] font-semibold uppercase leading-relaxed tracking-[0.14em] text-[#4b4636] sm:text-[11px]"
        >
            Polytechnic University of the Philippines &middot; Taguig
        </p>

        <!-- Document title + gold rule -->
        <div class="mt-5 flex items-center justify-center gap-3" aria-hidden="true">
            <span class="h-px w-8 bg-[#FFD700] sm:w-10"></span>
            <h1 class="text-[11px] font-bold uppercase tracking-[0.3em] text-[#800000] sm:text-xs">
                Admission Result
            </h1>
            <span class="h-px w-8 bg-[#FFD700] sm:w-10"></span>
        </div>
    </header>
</template>
