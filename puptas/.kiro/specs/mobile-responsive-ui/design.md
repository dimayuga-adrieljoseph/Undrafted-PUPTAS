# Design Document: Mobile-Responsive UI

## Overview

This design makes the PUP Admissions Portal fully usable across mobile (320px–480px), tablet (481px–1024px), and desktop (1025px+) viewports. The implementation uses Tailwind CSS responsive utilities exclusively, applied consistently across Vue 3 components (Inertia.js) and Laravel Blade templates. No new CSS frameworks are introduced and no existing functionality is removed.

The primary structural change is converting the sidebar from a desktop-only hover-to-expand panel into a dual-mode component: an overlay drawer on mobile and the existing hover-to-expand panel on desktop. All other changes are additive Tailwind class adjustments.

---

## Architecture

### Responsive Strategy

Tailwind's mobile-first breakpoint system is the sole adaptation mechanism:

| Prefix | Min-width | Usage |
|--------|-----------|-------|
| (none) | 0px       | Mobile base styles |
| `sm`   | 640px     | Small tablet adjustments |
| `md`   | 768px     | Tablet — sidebar switches to desktop mode |
| `lg`   | 1024px    | Desktop — multi-column layouts unlock |
| `xl`   | 1280px    | Wide desktop |

### Adaptation Layers

```
┌─────────────────────────────────────────────────────┐
│  Blade Templates (app.blade.php, sar/, emails/)     │
│  → Responsive containers, overflow-x-auto wrappers  │
├─────────────────────────────────────────────────────┤
│  Vue Layouts (AppLayout, ApplicantLayout, etc.)     │
│  → Hamburger toggle, sidebar overlay, margin reset  │
├─────────────────────────────────────────────────────┤
│  Vue Components (Sidebar, Modal, ProgramRequirements│
│  ScheduleForm, AddUser, EditUser)                   │
│  → Touch targets, overflow-y-auto modals            │
├─────────────────────────────────────────────────────┤
│  Vue Pages (Dashboard, Grade Input pages)           │
│  → Responsive grids, card layouts, chart sizing     │
└─────────────────────────────────────────────────────┘
```

### State Management for Mobile Navigation

Mobile sidebar state is managed with a `ref` in each layout component and passed down to `Sidebar.vue` via props/emits. No global state store is needed — the sidebar open/close state is local to the layout.

```
AppLayout.vue
  isMobileSidebarOpen: ref(false)  ← new
  → passed as prop to Sidebar.vue
  → hamburger button toggles it
  → Sidebar emits 'close' on nav-link click or backdrop tap
```

---

## Components and Interfaces

### AppLayout.vue / ApplicantLayout.vue (and other role layouts)

Current state: `margin-left: var(--sidebar-width, 5rem)` is applied unconditionally via inline style, causing the main content to be offset on mobile.

Changes:
- Add `isMobileSidebarOpen` ref
- Add hamburger button (`md:hidden`) in the header
- Remove inline `margin-left` style; replace with Tailwind class `md:ml-20` (collapsed) toggled to `md:ml-72` (expanded) via the existing CSS variable approach, but with `ml-0` as the mobile base
- Pass `isMobileSidebarOpen` and a `@close` handler to `Sidebar`
- Add `overflow-hidden` to `<body>` when sidebar is open on mobile (via `watchEffect`)

```vue
<!-- Header hamburger button (new) -->
<button
  @click="isMobileSidebarOpen = true"
  class="md:hidden min-h-[44px] min-w-[44px] flex items-center justify-center rounded-lg
         bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 transition"
  aria-label="Open navigation menu"
>
  <!-- hamburger icon SVG -->
</button>

<!-- Main area: replace inline style with responsive class -->
<div class="flex-1 flex flex-col md:ml-[var(--sidebar-width,5rem)]">
```

### Sidebar.vue

Current state: Fixed left panel, always visible, hover-to-expand on desktop.

Changes:
- Accept `isMobileOpen` prop (Boolean)
- On mobile (`< md`): `fixed inset-y-0 left-0 z-[9999] translate-x-[-100%]` by default; `translate-x-0` when `isMobileOpen` is true
- On desktop (`md+`): existing hover-to-expand behavior unchanged
- Add semi-transparent backdrop div (sibling, `md:hidden`) that appears when open
- Emit `close` event when a nav link is clicked on mobile or backdrop is tapped
- All nav items already have sufficient padding; add explicit `min-h-[44px]` to `.nav-item`

```vue
<!-- Backdrop (mobile only) -->
<Transition name="fade">
  <div
    v-if="isMobileOpen"
    class="fixed inset-0 z-[9998] bg-black/50 md:hidden"
    @click="$emit('close')"
  />
</Transition>

<!-- Sidebar root: add transition classes -->
<div
  :class="[
    'sidebar fixed left-0 top-0 h-screen z-[9999] ...',
    'transition-transform duration-300',
    isMobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
    sidebarWidthClass
  ]"
>
```

### Grade Input Pages (ICTGradeInput.vue, STEMGradeInput.vue, etc.)

Current state: Already uses `grid-cols-1 md:grid-cols-2` for subject grids and `w-full` on inputs. The summary cards use `grid-cols-1 md:grid-cols-4`.

Changes needed:
- Summary cards: change to `grid-cols-2 md:grid-cols-4` (two per row on mobile)
- Progress step indicator: add `flex-wrap` and truncate/abbreviate labels below 480px using `text-xs sm:text-sm` and `hidden xs:inline` for long labels
- Validation error containers: add `break-words` to error message elements

### Dashboard Pages (Admin.vue, Applicant.vue, etc.)

Current state: Stats grid already uses `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`. Main content grid uses `grid-cols-1 lg:grid-cols-3`. Chart container uses `h-80`.

Changes needed:
- Chart container: add `w-full` and ensure `maintainAspectRatio: false` is set (already present in Admin.vue)
- Chart height: change to `h-64 md:h-80` for responsive height
- Search input: add `w-full` class
- Applicant list items: add `flex-wrap` to the status badge row so badges wrap on 320px

### Forms (ScheduleForm.vue, AddUser.vue, EditUser.vue)

Changes:
- Multi-field rows: `flex flex-col md:flex-row gap-4`
- All inputs/selects/textareas: `w-full`
- Labels: `block` class (most already have this)

### Modal Components (ApplicationReviewModal.vue, ConfirmationModal.vue, etc.)

Changes:
- Modal content container: add `overflow-y-auto max-h-[90vh]`
- Modal wrapper: ensure `p-4` padding on mobile so modal doesn't touch screen edges

### ProgramRequirements.vue

Changes:
- Wrap any tables in `overflow-x-auto`
- Apply `w-full` to the component root

### Sienna Widget (partials/sienna-widget.blade.php)

Changes (CSS overrides only, no structural removal):
- Add `max-width: 100vw; overflow-x: hidden;` to `.asw-menu`
- On viewports < 480px, reposition trigger button away from content overlap using `bottom: 80px` override
- Wrap the `<script>` and `<style>` block in a `<div class="overflow-x-auto">` container

### Blade Templates

**app.blade.php**: Viewport meta tag already present — preserve as-is.

**sar/template.blade.php**: Wrap the `.page` div in `<div style="overflow-x: auto;">`.

**emails/*.blade.php**: Add `style="max-width: 600px; width: 100%; margin: 0 auto;"` to outermost container div.

---

## Data Models

No new data models are introduced. This feature is purely presentational. The only state additions are:

```typescript
// Per-layout component (AppLayout, ApplicantLayout, etc.)
const isMobileSidebarOpen = ref<boolean>(false)

// Sidebar.vue new prop
defineProps<{
  variant?: string        // existing
  isMobileOpen?: boolean  // new
}>()

defineEmits<{
  close: []
}>()
```

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Main content has no fixed pixel width on mobile

*For any* layout component rendered at a viewport width below 1024px, the main content area element shall have no inline `width` style with a fixed pixel value and shall carry the `w-full` Tailwind class.

**Validates: Requirements 1.1**

---

### Property 2: Main content left margin is zero on mobile

*For any* layout component rendered at a viewport width below 768px, the main content area element shall have a computed `margin-left` of `0px` (not the sidebar CSS variable value).

**Validates: Requirements 1.2**

---

### Property 3: Sidebar is an overlay on mobile

*For any* layout rendered at a viewport width below 768px, the Sidebar element shall have `position: fixed` and a `z-index` greater than the main content area's `z-index`, confirming it renders as an overlay rather than a side-by-side panel.

**Validates: Requirements 1.5, 3.3**

---

### Property 4: Hamburger button visibility is breakpoint-gated

*For any* layout component, the hamburger toggle button shall be visible when the viewport is below 768px and hidden when the viewport is 768px or wider.

**Validates: Requirements 2.1, 2.6**

---

### Property 5: Sidebar open/close round trip

*For any* layout in mobile viewport, opening the sidebar (via hamburger tap) and then closing it (via backdrop tap or nav-link click) shall return the sidebar to its hidden state (`translate-x-[-100%]` or equivalent hidden class).

**Validates: Requirements 2.2, 2.3, 2.4, 3.1**

---

### Property 6: Body scroll is locked when sidebar is open on mobile

*For any* layout rendered at a viewport below 768px, when the mobile sidebar is open, the `<body>` element shall have `overflow-hidden` applied; when the sidebar is closed, `overflow-hidden` shall be absent.

**Validates: Requirements 3.4**

---

### Property 7: Hamburger button meets touch target size

*For any* rendered hamburger button, its computed height and width shall each be at least 44px.

**Validates: Requirements 2.5**

---

### Property 8: Grade input grids are single-column on mobile

*For any* Grade_Input_Page rendered at a viewport below 768px, all subject input grid containers shall have an effective column count of 1, and the summary cards grid shall have an effective column count of 2.

**Validates: Requirements 4.1, 4.2, 4.5**

---

### Property 9: Grade input fields fill container width

*For any* Grade_Input_Page, all `<input>` elements within subject grids shall carry the `w-full` class.

**Validates: Requirements 4.4**

---

### Property 10: Step indicator does not overflow on small viewports

*For any* Grade_Input_Page rendered at a viewport width of 480px or below, the progress step indicator container shall not have a `scrollWidth` greater than its `clientWidth`.

**Validates: Requirements 4.3**

---

### Property 11: Dashboard stats grid uses responsive column classes

*For any* Dashboard page, the statistics grid container shall carry the classes `grid-cols-1`, `sm:grid-cols-2`, and `lg:grid-cols-4`.

**Validates: Requirements 5.1**

---

### Property 12: Dashboard main grid stacks below 1024px

*For any* Dashboard page rendered at a viewport below 1024px, the main content grid (chart + recent applications) shall have an effective column count of 1.

**Validates: Requirements 5.2**

---

### Property 13: Form inputs fill container width

*For any* `<input>`, `<select>`, or `<textarea>` element inside a Vue_Component form or Blade_Template form, the element shall carry the `w-full` class.

**Validates: Requirements 5.5, 6.2**

---

### Property 14: Multi-field form rows stack on mobile

*For any* form container in Vue_Components that holds multiple fields side by side on desktop, the container shall carry `flex-col` as its base flex direction class and `md:flex-row` for desktop.

**Validates: Requirements 6.1, 6.3**

---

### Property 15: All interactive elements meet 44px touch target height

*For any* button, nav link, or dropdown trigger element in Vue_Components, the element shall have a computed height of at least 44px.

**Validates: Requirements 7.1, 7.3, 7.5**

---

### Property 16: Touch targets are spaced at least 8px apart on mobile

*For any* two adjacent Touch_Target elements rendered at a viewport below 768px, the gap between their bounding boxes shall be at least 8px.

**Validates: Requirements 7.4**

---

### Property 17: Headings use responsive text scale classes

*For any* `<h1>` element in Vue_Components, the element shall carry responsive text size classes (e.g., `text-xl md:text-2xl lg:text-3xl` or equivalent scale).

**Validates: Requirements 8.1, 8.2**

---

### Property 18: Body text minimum size on mobile

*For any* body text or label element in Vue_Components rendered at a viewport below 768px, the computed `font-size` shall be at least 14px.

**Validates: Requirements 8.3**

---

### Property 19: No fixed pixel font sizes in inline styles

*For any* Vue_Component or Blade_Template file, no element shall have an inline `style` attribute containing `font-size` with a pixel value.

**Validates: Requirements 8.4**

---

### Property 20: Long strings do not overflow on small viewports

*For any* element containing potentially long strings (email addresses, names) rendered at a viewport below 480px, the element shall carry `break-words` or an equivalent `overflow-wrap` class, and its `scrollWidth` shall not exceed its `clientWidth`.

**Validates: Requirements 8.5**

---

### Property 21: Sienna widget panel does not exceed viewport width

*For any* viewport size, the `.asw-menu` element's computed `width` shall not exceed `window.innerWidth`.

**Validates: Requirements 9.1, 9.2**

---

### Property 22: Blade template tables are wrapped in overflow-x-auto

*For any* `<table>` element in a Blade_Template or Vue_Component, the immediate or ancestor wrapper element shall have the `overflow-x-auto` class or equivalent CSS.

**Validates: Requirements 10.3, 11.2**

---

### Property 23: Multi-step workflows render all steps on mobile

*For any* Grade_Input_Page rendered at a viewport below 768px, all step indicator elements shall be present in the DOM (not removed or hidden with `display: none`).

**Validates: Requirements 11.3**

---

### Property 24: Modal components are scrollable on mobile

*For any* modal component's content container, the element shall carry `overflow-y-auto` and `max-h-[90vh]` classes.

**Validates: Requirements 11.4**

---

### Property 25: Sticky elements have no overflow-hidden ancestors

*For any* element with `position: sticky` in Vue_Components, none of its ancestor elements shall have `overflow: hidden` applied (which would break sticky positioning in iOS Safari).

**Validates: Requirements 12.3**

---

### Property 26: backdrop-blur elements have solid fallback background

*For any* element using a `backdrop-blur` Tailwind class, the element shall also carry a solid background color class (e.g., `bg-white/80` or `bg-gray-900/80`) to ensure graceful degradation.

**Validates: Requirements 12.4**

---

## Error Handling

**Sidebar state errors**: If the `isMobileOpen` prop is not passed (e.g., a layout forgets to wire it), the sidebar defaults to `false` (hidden), which is the safe fallback — navigation is still accessible via desktop mode.

**Sienna widget load failure**: The widget is loaded with `defer` and wrapped in `@if (config('app.sienna_widget.enabled'))`. If the CDN is unavailable, the page renders normally without the widget. The overflow containment CSS is inert when the widget is absent.

**Chart rendering on mobile**: `maintainAspectRatio: false` with an explicit height class (`h-64 md:h-80`) ensures the chart container has a defined height. If Chart.js fails to render, the container collapses gracefully without breaking the page layout.

**Validation errors on mobile**: Error messages use `break-words` to prevent long validation strings from overflowing. The `flex flex-col` form layout ensures errors stack below their inputs without horizontal overflow.

---

## Testing Strategy

### Dual Testing Approach

Both unit tests and property-based tests are required. Unit tests cover specific examples and integration points; property-based tests verify universal layout invariants across generated inputs.

### Unit Tests

Focus areas:
- Sidebar open/close state transitions (specific click sequences)
- Hamburger button visibility at exact breakpoint boundaries (767px vs 768px)
- Body scroll lock applied and removed correctly
- Sienna widget CSS override presence in the rendered HTML
- Email template outermost container inline styles
- SAR template overflow-x-auto wrapper presence
- Viewport meta tag presence in app.blade.php

### Property-Based Tests

Use **fast-check** (TypeScript/JavaScript) for Vue component tests and **Pest** with a property-based plugin for PHP/Blade tests.

Each property test runs a minimum of **100 iterations**.

Tag format for each test:
```
// Feature: mobile-responsive-ui, Property N: <property_text>
```

**Property test examples:**

```typescript
// Feature: mobile-responsive-ui, Property 8: Grade input grids are single-column on mobile
fc.assert(
  fc.property(
    fc.constantFrom(...gradeInputPages),
    fc.integer({ min: 320, max: 767 }),
    (PageComponent, viewportWidth) => {
      const wrapper = mount(PageComponent, { /* viewport: viewportWidth */ })
      const grids = wrapper.findAll('[class*="grid-cols-1"]')
      return grids.length > 0
    }
  ),
  { numRuns: 100 }
)

// Feature: mobile-responsive-ui, Property 13: Form inputs fill container width
fc.assert(
  fc.property(
    fc.constantFrom(...formComponents),
    (FormComponent) => {
      const wrapper = mount(FormComponent)
      const inputs = wrapper.findAll('input, select, textarea')
      return inputs.every(el => el.classes().includes('w-full'))
    }
  ),
  { numRuns: 100 }
)

// Feature: mobile-responsive-ui, Property 15: All interactive elements meet 44px touch target height
fc.assert(
  fc.property(
    fc.constantFrom(...allVueComponents),
    (Component) => {
      const wrapper = mount(Component)
      const buttons = wrapper.findAll('button')
      return buttons.every(btn => {
        const classes = btn.classes()
        return classes.some(c => c.includes('min-h-[44px]') || c.includes('h-11') || c.includes('h-12'))
      })
    }
  ),
  { numRuns: 100 }
)
```

### Cross-Browser Testing

Manual verification required on:
- Chrome for Android (version 100+) — primary Android browser
- Safari for iOS (version 15+) — primary iOS browser
- Focus areas: sticky header behavior, backdrop-blur fallback, pointer events on Sidebar

### Accessibility

- All hamburger buttons include `aria-label="Open navigation menu"`
- Sidebar overlay includes `role="dialog"` and `aria-modal="true"` when open
- Focus is trapped within the open sidebar on mobile (using a focus trap utility or manual `tabindex` management)
- Close button in sidebar overlay includes `aria-label="Close navigation menu"`
