<!--
  PageContainer.vue
  ─────────────────
  Shared page content container for every authenticated PUPTAS page.

  This is the single source of truth for the application's content width.
  The strategy is the one established by the System Logs / Audit Logs page
  (Pages/SuperAdmin/Logs.vue), which is the reference implementation:

      w-full        → use the horizontal space the layout shell already offers
      px-4 md:px-8  → a horizontal gutter, never a hard cap
      py-8          → vertical rhythm

  AuthenticatedLayout already supplies the outer padding (`main` = p-3 sm:p-6)
  and a full-width page card (p-4 sm:p-6), so the shell itself is fluid. The
  narrow appearance some pages had came entirely from page-level wrappers that
  added `max-w-5xl` / `max-w-7xl mx-auto` and squeezed tables, filters and
  forms into a narrow centred column on wide monitors. Those caps are gone.

  Usage
  ─────
  <PageContainer>                          fluid content, standard padding
  <PageContainer class="space-y-6">        spacing utilities merge through
  <PageContainer :vertical-padding="false"> when a parent already adds py-*

  Guidelines
  ──────────
  • Do not add max-w-* / mx-auto here — content should grow with the shell.
  • Pages that genuinely need a narrow column (auth screens, modals, small
    informational content, single-purpose data-entry forms) should constrain
    their own inner card instead of the page container.
-->
<script setup>
defineProps({
    /**
     * Applies `py-8` for pages that sit directly inside the layout.
     * Set to false when an ancestor already provides the vertical rhythm.
     */
    verticalPadding: {
        type: Boolean,
        default: true,
    },
})
</script>

<template>
    <div
        :class="[
            'w-full px-4 md:px-8',
            verticalPadding ? 'py-8' : '',
        ]"
    >
        <slot />
    </div>
</template>
