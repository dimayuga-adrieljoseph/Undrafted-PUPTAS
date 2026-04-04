# Requirements Document

## Introduction

This feature ensures the entire PUP Admissions Portal — including Vue 3 pages, Laravel Blade templates, and embedded third-party widgets (Sienna accessibility widget) — is fully usable, readable, and functional across mobile phones (320px–480px), tablets (481px–1024px), and desktop (1025px+). The implementation uses Tailwind CSS responsive utilities exclusively, applied consistently across both Vue components and Blade templates, without introducing new frameworks or removing any existing functionality.

## Glossary

- **System**: The PUP Admissions Portal application as a whole
- **Vue_Component**: A `.vue` single-file component rendered via Inertia.js in `resources/js/`
- **Blade_Template**: A Laravel `.blade.php` template in `resources/views/`
- **Layout**: A Vue layout component (e.g., `AppLayout.vue`, `ApplicantLayout.vue`) that wraps page content
- **Sidebar**: The `Sidebar.vue` component providing primary navigation, currently a fixed hover-to-expand panel
- **Hamburger_Menu**: A mobile navigation toggle button that shows/hides the Sidebar on small screens
- **Sienna_Widget**: The third-party accessibility widget loaded via CDN in `partials/sienna-widget.blade.php`
- **Responsive_Container**: A wrapper element using Tailwind classes such as `w-full`, `max-w-*`, `overflow-x-auto` to constrain and adapt content width
- **Breakpoint**: A Tailwind CSS screen size prefix — `sm` (640px), `md` (768px), `lg` (1024px), `xl` (1280px)
- **Touch_Target**: An interactive element (button, link, input) sized to meet a minimum 44×44px tap area
- **Grade_Input_Page**: Any of the strand-specific grade entry pages (e.g., `ICTGradeInput.vue`, `STEMGradeInput.vue`)
- **Dashboard**: A role-specific summary page (e.g., `Admin.vue`, `Applicant.vue`) displaying statistics and data tables
- **GWA**: General Weighted Average, a computed numeric value displayed on Grade_Input_Pages

---

## Requirements

### Requirement 1: Responsive Layout Foundation

**User Story:** As a user on any device, I want the application shell to adapt to my screen size, so that I can navigate and use the portal without horizontal scrolling or zoomed-out content.

#### Acceptance Criteria

1. THE Layout SHALL set `width: 100%` and remove any fixed pixel widths on the main content area for all viewport sizes below 1024px.
2. WHEN the viewport width is below 768px, THE Layout SHALL set the main content left margin to `0` instead of the sidebar width CSS variable (`--sidebar-width`).
3. THE Blade_Template `app.blade.php` SHALL include `<meta name="viewport" content="width=device-width, initial-scale=1">` (already present; SHALL be preserved in all future edits).
4. THE System SHALL apply Tailwind CSS responsive utilities as the sole mechanism for layout adaptation, without introducing additional CSS frameworks.
5. WHILE the viewport is below 768px, THE Layout SHALL render the Sidebar as an overlay drawer above the main content rather than a side-by-side panel.

---

### Requirement 2: Mobile Navigation — Hamburger Menu

**User Story:** As a mobile user, I want a hamburger menu toggle, so that I can access navigation links without the sidebar permanently occupying screen space.

#### Acceptance Criteria

1. WHEN the viewport width is below 768px, THE Layout SHALL display a hamburger toggle button (`md:hidden`) in the top navigation bar.
2. WHEN the hamburger button is tapped, THE Sidebar SHALL become visible as a full-height overlay drawer with a semi-transparent backdrop.
3. WHEN the backdrop is tapped, THE Sidebar SHALL close and return to a hidden state.
4. WHEN a navigation link inside the Sidebar is activated on mobile, THE Sidebar SHALL close automatically.
5. THE hamburger toggle button SHALL meet the Touch_Target minimum size of 44×44px.
6. WHILE the viewport is 768px or wider, THE Layout SHALL hide the hamburger button (`hidden md:block`) and display the Sidebar in its standard collapsed/expanded desktop mode.

---

### Requirement 3: Sidebar Responsive Behavior

**User Story:** As a user, I want the sidebar to behave appropriately for my device type, so that navigation is accessible on mobile and efficient on desktop.

#### Acceptance Criteria

1. WHILE the viewport is below 768px, THE Sidebar SHALL be hidden by default (`translate-x-[-100%]` or `hidden`) and only visible when toggled.
2. WHILE the viewport is 768px or wider, THE Sidebar SHALL retain its existing hover-to-expand and pin behavior.
3. THE Sidebar SHALL use a `z-index` value that places it above all page content when open on mobile.
4. WHEN the Sidebar is open on mobile, THE System SHALL prevent the background page from scrolling (`overflow-hidden` on `body`).
5. THE Sidebar overlay on mobile SHALL include a visible close affordance (close button or backdrop tap).

---

### Requirement 4: Grade Input Pages — Card-Based Mobile Layout

**User Story:** As an applicant on a mobile device, I want grade input forms to be readable and usable without zooming, so that I can enter my grades accurately on a small screen.

#### Acceptance Criteria

1. WHEN the viewport is below 768px, THE Grade_Input_Page SHALL render subject input groups as single-column stacked cards instead of multi-column grids.
2. THE Grade_Input_Page SHALL use `grid-cols-1 md:grid-cols-2` for all subject input grids, ensuring single-column layout on mobile.
3. THE Grade_Input_Page progress step indicator SHALL wrap or abbreviate step labels on viewports below 480px to prevent horizontal overflow.
4. ALL grade input fields on Grade_Input_Page SHALL use `w-full` to fill their container width on all screen sizes.
5. THE Grade_Input_Page summary cards (Math Average, English Average, Science Average, GWA) SHALL use `grid-cols-2 md:grid-cols-4` to display two cards per row on mobile.
6. IF a user submits the grade form with invalid data, THEN THE Grade_Input_Page SHALL display validation error messages in a layout that does not cause horizontal overflow on mobile.

---

### Requirement 5: Dashboard Responsive Grid

**User Story:** As an admin or staff member on a tablet or mobile device, I want the dashboard to reflow its statistics and charts, so that I can review application data without horizontal scrolling.

#### Acceptance Criteria

1. THE Dashboard statistics grid SHALL use `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4` so that cards stack on mobile, show two columns on tablet, and four columns on desktop.
2. WHEN the viewport is below 1024px, THE Dashboard main content grid SHALL stack the chart panel and the recent applications panel vertically (`grid-cols-1 lg:grid-cols-3`).
3. THE Dashboard applicant list items SHALL remain fully readable at 320px width, with status badges wrapping below the applicant name if necessary.
4. THE Dashboard chart container SHALL use `w-full` and `maintainAspectRatio: false` with a responsive height class (e.g., `h-64 md:h-80`) to prevent overflow on small screens.
5. WHEN the viewport is below 768px, THE Dashboard search input SHALL use `w-full` to span the full container width.

---

### Requirement 6: Forms — Responsive Stacking

**User Story:** As a user filling out any form on a mobile device, I want form fields to stack vertically and fill the screen width, so that I can interact with them comfortably.

#### Acceptance Criteria

1. THE System SHALL apply `flex flex-col gap-4` to all multi-field form containers in Vue_Components and Blade_Templates.
2. ALL form input elements (`<input>`, `<select>`, `<textarea>`) SHALL use `w-full` to fill their parent container on all screen sizes.
3. WHEN a form row contains multiple fields side by side on desktop, THE System SHALL apply `flex-col md:flex-row` or equivalent grid classes so fields stack vertically on mobile.
4. ALL form labels SHALL be rendered as block elements above their associated inputs on all screen sizes.
5. THE `ScheduleForm.vue` component SHALL apply responsive stacking to its date, time, and program fields.
6. THE `AddUser.vue` and `EditUser.vue` pages SHALL apply `w-full` to all inputs and stack multi-column rows on mobile.

---

### Requirement 7: Touch Optimization

**User Story:** As a mobile user, I want all interactive elements to be large enough to tap accurately, so that I can use the portal without accidentally activating the wrong control.

#### Acceptance Criteria

1. ALL button elements in Vue_Components SHALL have a minimum height of 44px (`min-h-[44px]`).
2. WHEN rendered on a viewport below 768px, ALL primary action buttons SHALL use `w-full` to span the full container width.
3. ALL navigation links in the Sidebar SHALL have a minimum height of 44px and sufficient horizontal padding to meet the Touch_Target requirement.
4. THE System SHALL ensure that no two Touch_Target elements are closer than 8px apart on mobile viewports.
5. ALL dropdown trigger elements in the Sidebar SHALL meet the 44px minimum Touch_Target height.

---

### Requirement 8: Typography Responsiveness

**User Story:** As a user on any device, I want text to be legible without zooming, so that I can read all content comfortably.

#### Acceptance Criteria

1. THE System SHALL apply responsive text sizing using Tailwind classes (e.g., `text-sm md:text-base lg:text-lg`) to all heading and body text elements in Vue_Components.
2. ALL page-level headings (`<h1>`) SHALL use `text-xl md:text-2xl lg:text-3xl` or equivalent responsive scale.
3. ALL body and label text SHALL use a minimum of `text-sm` (14px) on mobile viewports.
4. THE System SHALL not use fixed pixel font sizes in inline styles for any text rendered in Vue_Components or Blade_Templates.
5. WHILE the viewport is below 480px, THE System SHALL ensure line lengths do not exceed the viewport width by applying `break-words` or `overflow-wrap: break-word` where long strings (e.g., email addresses) may appear.

---

### Requirement 9: Sienna Widget — Overflow Prevention

**User Story:** As a user on a mobile device, I want the Sienna accessibility widget to not break the page layout, so that I can still use all page content without horizontal scrolling.

#### Acceptance Criteria

1. THE Sienna_Widget trigger button SHALL be positioned so that it does not overlap critical page content on viewports below 480px.
2. THE Sienna_Widget panel SHALL use `max-w-full` and SHALL not extend beyond the viewport width on any screen size.
3. IF the Sienna_Widget renders content wider than the viewport, THEN THE Blade_Template SHALL wrap the widget in a container with `overflow-x-auto` to contain horizontal overflow.
4. THE Sienna_Widget CSS overrides in `sienna-widget.blade.php` SHALL include `max-width: 100vw` on the `.asw-menu` panel to prevent viewport overflow.
5. THE System SHALL not remove or replace the Sienna_Widget; only containment and overflow CSS overrides are permitted.

---

### Requirement 10: Blade Template Responsiveness

**User Story:** As a user accessing Blade-rendered pages on a mobile device, I want those pages to be as responsive as the Vue pages, so that the experience is consistent throughout the portal.

#### Acceptance Criteria

1. ALL Blade_Templates that render visible UI SHALL wrap their content in a `Responsive_Container` using at minimum `w-full max-w-screen-xl mx-auto px-4`.
2. THE `sar/template.blade.php` SHALL wrap its content in `overflow-x-auto` to allow horizontal scrolling of wide SAR table content without breaking the page layout.
3. WHEN a Blade_Template renders a table, THE System SHALL wrap the table in `<div class="overflow-x-auto">` to prevent horizontal page overflow.
4. THE email Blade_Templates (`emails/*.blade.php`) SHALL use inline styles with `max-width: 600px; width: 100%` on their outermost container for email client compatibility.
5. ALL Blade_Templates SHALL preserve existing backend logic, route references, and CSRF tokens without modification.

---

### Requirement 11: Feature Parity on Mobile

**User Story:** As a mobile user, I want access to all the same features available on desktop, so that I am not blocked from completing any task on a small screen.

#### Acceptance Criteria

1. THE System SHALL not disable, hide, or remove any functional feature (form submission, file upload, application review, grade input) on mobile viewports.
2. WHERE screen space is insufficient to display a data table inline, THE System SHALL provide a horizontally scrollable container (`overflow-x-auto`) rather than hiding the table.
3. WHERE a multi-step workflow (e.g., grade input steps) is present, THE System SHALL render all steps accessible on mobile, adapting layout rather than removing steps.
4. THE `ApplicationReviewModal.vue` and other modal components SHALL be scrollable on mobile (`overflow-y-auto max-h-[90vh]`) to ensure all modal content is reachable.
5. THE `ProgramRequirements.vue` component SHALL remain fully functional and readable on mobile, using responsive layout classes.

---

### Requirement 12: Cross-Browser and Cross-Platform Compatibility

**User Story:** As a user on an Android or iOS device, I want the portal to render and function correctly in the default browser, so that I can use the portal regardless of my device.

#### Acceptance Criteria

1. THE System SHALL use only Tailwind CSS utility classes and standard CSS properties that are supported in Chrome for Android (version 100+) and Safari for iOS (version 15+).
2. THE System SHALL not use CSS features that require vendor prefixes not handled by the PostCSS/Autoprefixer pipeline already configured in the project.
3. WHEN a `position: sticky` element is used (e.g., the top navigation bar), THE System SHALL ensure it functions correctly in iOS Safari by avoiding `overflow: hidden` on ancestor elements.
4. THE System SHALL ensure that `backdrop-filter: blur` used on the top navigation bar degrades gracefully on browsers that do not support it, maintaining a solid fallback background color.
5. THE touch event handling in the Sidebar (pointer events) SHALL function correctly on both iOS Safari and Android Chrome without requiring additional polyfills.
