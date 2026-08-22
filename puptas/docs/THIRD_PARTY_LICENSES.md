# Third-Party Licenses & IP Clarification

## PUPT Admission System (PUPTAS) — Open Source Software Attribution

> **Compliance Target:** Pre-Oral Defense Checklist §3 — *"Open-source software (OSS) components and commercial assets properly documented and licensed."*
>
> **Project License:** MIT (see `/LICENSE` in repository root)
>
> **Last Updated:** August 23, 2026

---

## 1. Project License

The PUPTAS project is released under the **MIT License**, which is compatible with all dependencies listed below. The full license text is located at the root of the repository in the `LICENSE` file.

---

## 2. Backend Dependencies (PHP / Composer)

| Package | Version | License | Purpose |
|---|---|---|---|
| `laravel/framework` | ^11.31 | MIT | Core PHP web framework |
| `laravel/jetstream` | ^5.3 | MIT | Authentication scaffolding (Teams, 2FA) |
| `laravel/passport` | ^12.0 | MIT | OAuth 2.0 server for API authentication |
| `laravel/sanctum` | ^4.0 | MIT | SPA / token authentication |
| `laravel/tinker` | ^2.9 | MIT | REPL for debugging |
| `laravel/pint` | ^1.13 | MIT | Code style fixer |
| `laravel/sail` | ^1.26 | MIT | Docker dev environment |
| `laravel/pail` | ^1.1 | MIT | Log tailing |
| `inertiajs/inertia-laravel` | ^1.0 | MIT | Server-side Inertia.js adapter |
| `barryvdh/laravel-dompdf` | ^3.1 | MIT | PDF generation (SAR, F137 letters) |
| `intervention/image` | ^3.11 | MIT | Image processing and compression |
| `knuckleswtf/scribe` | ^5.11 | MIT | API documentation generator |
| `maatwebsite/excel` | ^3.1 | MIT | Excel import/export |
| `league/flysystem-aws-s3-v3` | ^3.32 | MIT | AWS S3 file storage driver |
| `predis/predis` | ^3.4 | MIT | Redis client for caching/queues |
| `resend/resend-laravel` | ^1.3 | MIT | Email delivery service |
| `setasign/fpdi` | ^2.6 | MIT | PDF template filling |
| `tecnickcom/tcpdf` | ^6.10 | LGPL-3.0 | PDF generation library |
| `tightenco/ziggy` | ^2.0 | MIT | Laravel route sharing with JS |
| `fakerphp/faker` | ^1.23 | MIT | Test data generation (dev only) |
| `mockery/mockery` | ^1.6 | BSD-3 | Mock objects for testing (dev only) |
| `nunomaduro/collision` | ^8.1 | MIT | Error reporting (dev only) |
| `pestphp/pest` | ^3.7 | MIT | Testing framework (dev only) |

---

## 3. Frontend Dependencies (JavaScript / npm)

| Package | Version | License | Purpose |
|---|---|---|---|
| `vue` | ^3.3.13 | MIT | Frontend reactive framework |
| `@inertiajs/vue3` | ^1.0.14 | MIT | Client-side Inertia.js adapter |
| `@vitejs/plugin-vue` | ^5.0.0 | MIT | Vite plugin for Vue SFC compilation |
| `vite` | ^6.4.1 | MIT | Frontend build tool and dev server |
| `laravel-vite-plugin` | ^1.2.0 | MIT | Laravel ↔ Vite integration |
| `tailwindcss` | ^3.4.0 | MIT | Utility-first CSS framework |
| `@tailwindcss/forms` | ^0.5.7 | MIT | Tailwind form styling plugin |
| `@tailwindcss/typography` | ^0.5.10 | MIT | Tailwind prose typography plugin |
| `autoprefixer` | ^10.4.16 | MIT | CSS vendor prefix tool |
| `postcss` | ^8.4.32 | MIT | CSS post-processor |
| `axios` | ^1.7.4 | MIT | HTTP client for API calls |
| `chart.js` | ^3.9.1 | MIT | Data visualization charts |
| `vue-chart-3` | ^3.1.8 | MIT | Vue 3 wrapper for Chart.js |
| `@vueup/vue-quill` | ^1.2.0 | MIT | Rich text editor (announcements) |
| `marked` | ^18.0.5 | MIT | Markdown parser |
| `compressorjs` | ^1.3.0 | MIT | Client-side image compression |
| `vue-the-mask` | ^0.11.1 | MIT | Input masking |
| `ziggy-js` | ^2.5.0 | MIT | Laravel named routes in JavaScript |
| `@fortawesome/fontawesome-free` | ^7.2.0 | CC BY 4.0 / MIT | Icon library |
| `@fortawesome/fontawesome-svg-core` | ^7.2.0 | MIT | SVG icon framework |
| `@fortawesome/free-solid-svg-icons` | ^6.7.2 | CC BY 4.0 / MIT | Solid icon set |
| `@fortawesome/vue-fontawesome` | ^3.1.3 | MIT | Vue integration for FontAwesome |
| `eslint` | 9.28.0 | MIT | Code linting (dev only) |
| `eslint-plugin-vue` | 9.33.0 | MIT | Vue-specific lint rules (dev only) |
| `vitest` | ^4.1.2 | MIT | Unit testing framework (dev only) |
| `@vue/test-utils` | ^2.4.6 | MIT | Vue component testing (dev only) |
| `jsdom` | ^29.0.1 | MIT | DOM simulation for tests (dev only) |
| `fast-check` | ^4.6.0 | MIT | Property-based testing (dev only) |
| `concurrently` | ^9.2.1 | MIT | Parallel script runner (dev only) |

---

## 4. Infrastructure & Services

| Service/Tool | License/Terms | Purpose |
|---|---|---|
| Docker | Apache 2.0 | Containerized deployment |
| PostgreSQL / MySQL | PostgreSQL License / GPL-2.0 | Database |
| Redis | BSD-3 | Caching and queue management |
| AWS S3 | Commercial (AWS) | File storage |
| Railway | Commercial (Railway) | Cloud hosting platform |
| Resend | Commercial (Resend) | Transactional email delivery |
| PUP IDP | Institutional | OAuth 2.0 identity provider |

---

## 5. Commercial Assets

| Asset | Source | License |
|---|---|---|
| PUP Logo | Polytechnic University of the Philippines | Institutional use — authorized for official PUP systems |
| Google Fonts (Bunny CDN) | Google / Bunny.net | Open Font License (OFL) |

---

## 6. License Compatibility Summary

All dependencies use licenses compatible with the project's MIT license:

- **MIT** — Fully permissive, compatible ✅
- **BSD-3** — Permissive, compatible ✅
- **Apache 2.0** — Permissive, compatible ✅
- **LGPL-3.0** (TCPDF) — Compatible when used as a library (not modified) ✅
- **CC BY 4.0** (FontAwesome icons) — Attribution provided ✅
- **OFL** (Fonts) — Permissive for use ✅

> **No GPL-contaminated or proprietary-only dependencies** are included in the production build.

---

**Document Author:** Undrafted Capstone Team  
**Review Status:** Ready for Pre-Oral Defense Panel Audit
