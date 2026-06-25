/**
 * useSidebarNavigation
 *
 * Centralized navigation configuration composable.
 * Mirrors the Nuxt UI Sidebar pattern of rendering from a config array
 * rather than hardcoded links, adapted for Inertia.js + Ziggy routing.
 *
 * Each navigation item shape:
 * {
 *   label: string,
 *   icon: string,          // FontAwesome icon name
 *   route: string,         // Ziggy named route (single items)
 *   activeRoutes: string[] // all route names that mark this item active
 *   children: [...],       // optional sub-items (dropdown group)
 *   roleGuard: (user) => bool // optional role check
 * }
 */

import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useSidebarNavigation() {
    const page = usePage()
    const user = computed(() => page.props.auth?.user ?? null)
    const showQualifiedProgramsNav = computed(() => page.props.showQualifiedProgramsNav ?? false)
    const isQualifiedProgramsViewEnabled = computed(() => page.props.system_settings?.qualified_programs_enabled !== false)

    // ─── Role helpers ────────────────────────────────────────────────────────
    const isSuperAdmin = computed(() => user.value?.role_id === 7)
    const isAdminOrSuperAdmin = computed(() => user.value?.role_id === 2 || user.value?.role_id === 7)

    // ─── Active route helper ─────────────────────────────────────────────────
    const isRouteActive = (routeNames = []) =>
        routeNames.some((name) => {
            try { return route().current(name) } catch { return false }
        })

    // ─── ADMIN / SUPERADMIN navigation ──────────────────────────────────────
    const adminNav = computed(() => [
        {
            key: 'dashboard',
            label: 'Dashboard',
            icon: 'tachometer-alt',
            route: 'dashboard',
            activeRoutes: ['dashboard'],
        },
        {
            key: 'passers',
            label: 'Passers',
            icon: 'users',
            activeRoutes: ['upload.form', 'lists', 'confirmed-applicants.index'],
            children: [
                {
                    key: 'upload-passer',
                    label: 'Upload Passer',
                    icon: 'upload',
                    route: 'upload.form',
                    activeRoutes: ['upload.form'],
                },
                {
                    key: 'list-passers',
                    label: 'List Passers',
                    icon: 'list',
                    route: 'lists',
                    activeRoutes: ['lists'],
                },
                {
                    key: 'confirmed-applicants',
                    label: 'Confirmed Applicants',
                    icon: 'calendar-check',
                    route: 'confirmed-applicants.index',
                    activeRoutes: ['confirmed-applicants.index'],
                },
            ],
        },
        {
            key: 'applications',
            label: 'Applications',
            icon: 'envelope-open-text',
            route: 'applications',
            activeRoutes: ['applications', 'recordstaff.applications', 'interviewer.applications', 'evaluator.applications'],
        },
        {
            key: 'evaluate-grades',
            label: 'Evaluate Grades',
            icon: 'clipboard-list',
            route: 'evaluator.dashboard',
            activeRoutes: ['evaluator.dashboard'],
        },
        {
            key: 'programs',
            label: 'Programs',
            icon: 'graduation-cap',
            route: 'programs.index',
            activeRoutes: ['programs.index'],
        },
        {
            key: 'reports',
            label: 'Reports',
            icon: 'chart-pie',
            activeRoutes: ['reports.index', 'reports.logbook.index', 'reports.control-list.index', 'email-tracking.index'],
            children: computed(() => [
                {
                    key: 'applicant-reports',
                    label: 'Applicant Reports',
                    icon: 'chart-line',
                    route: 'reports.index',
                    activeRoutes: ['reports.index'],
                },
                {
                    key: 'logbook',
                    label: 'Official Logbook',
                    icon: 'file-alt',
                    route: 'reports.logbook.index',
                    activeRoutes: ['reports.logbook.index'],
                },
                {
                    key: 'control-list',
                    label: 'Control List',
                    icon: 'clipboard-list',
                    route: 'reports.control-list.index',
                    activeRoutes: ['reports.control-list.index'],
                },
                // Role-gated: Admin or Superadmin only
                ...(isAdminOrSuperAdmin.value ? [{
                    key: 'email-tracking',
                    label: 'Email Tracking',
                    icon: 'envelope',
                    route: 'email-tracking.index',
                    activeRoutes: ['email-tracking.index'],
                }] : []),
            ]),
        },
        {
            key: 'manage-users',
            label: 'Manage Users',
            icon: 'user-shield',
            route: 'users.index',
            activeRoutes: ['users.index'],
        },
        // Superadmin-only items
        ...(isSuperAdmin.value ? [
            {
                key: 'system-settings-group',
                label: 'System Settings',
                icon: 'cogs',
                activeRoutes: ['audit-logs.index', 'api-clients.index', 'cutoff-settings.index'],
                children: computed(() => [
                    {
                        key: 'audit-logs',
                        label: 'Audit Logs',
                        icon: 'history',
                        route: 'audit-logs.index',
                        activeRoutes: ['audit-logs.index'],
                    },
                    {
                        key: 'api-clients',
                        label: 'API Clients',
                        icon: 'network-wired',
                        route: 'api-clients.index',
                        activeRoutes: ['api-clients.index'],
                    },
                    {
                        key: 'cutoff-settings',
                        label: 'Cutoff Settings',
                        icon: 'clock',
                        route: 'cutoff-settings.index',
                        activeRoutes: ['cutoff-settings.index'],
                    },
                ]),
            },
        ] : []),
    ])

    // ─── STAFF navigation (record / interviewer / evaluator) ─────────────────
    const staffNav = (variant) => computed(() => [
        {
            key: 'dashboard',
            label: 'Dashboard',
            icon: 'tachometer-alt',
            route: `${variant}.dashboard`,
            activeRoutes: [`${variant}.dashboard`, 'record.dashboard', 'interviewer.dashboard', 'evaluator.dashboard'],
        },
        {
            key: 'applications',
            label: 'Applications',
            icon: 'envelope-open-text',
            route: `${variant}.applications`,
            activeRoutes: [`${variant}.applications`, 'recordstaff.applications', 'interviewer.applications', 'evaluator.applications'],
        },
        {
            key: 'programs',
            label: 'Programs',
            icon: 'clipboard-list',
            route: `${variant}.programs`,
            activeRoutes: [`${variant}.programs`, 'evaluator.programs', 'interviewer.programs', 'record.programs'],
        },
    ])

    // ─── APPLICANT navigation ────────────────────────────────────────────────
    const applicantNav = computed(() => [
        {
            key: 'dashboard',
            label: 'Dashboard',
            icon: 'home',
            route: 'applicant.dashboard',
            activeRoutes: ['applicant.dashboard'],
        },
        // Conditionally shown based on server-side prop and system settings
        ...(showQualifiedProgramsNav.value && isQualifiedProgramsViewEnabled.value ? [{
            key: 'qualified-programs',
            label: 'Qualified Programs',
            icon: 'graduation-cap',
            route: 'applicant.qualified-programs.page',
            activeRoutes: ['applicant.qualified-programs.page'],
        }] : []),
        {
            key: 'profile',
            label: 'Profile',
            icon: 'user-shield',
            route: 'applicant.profile',
            activeRoutes: ['applicant.profile'],
        },
    ])

    /**
     * Get the navigation config for a given sidebar variant.
     * @param {'default'|'superadmin'|'record'|'interviewer'|'evaluator'|'applicant'} variant
     */
    const getNavigation = (variant) => {
        if (variant === 'applicant') return applicantNav
        if (['record', 'interviewer', 'evaluator'].includes(variant)) return staffNav(variant)
        return adminNav // default, superadmin
    }

    return {
        user,
        isSuperAdmin,
        isAdminOrSuperAdmin,
        isRouteActive,
        getNavigation,
    }
}
