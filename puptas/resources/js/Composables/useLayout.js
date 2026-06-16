/**
 * useLayout
 *
 * Shared logic extracted from all layout components:
 *   - Dark mode (localStorage-persisted)
 *   - Privacy consent modal
 *   - Mobile sidebar open state + body scroll lock
 *   - Global loading indicator
 *
 * Eliminates the ~60 lines of duplicated code in each of the 6 layout files.
 */
import { ref, computed, watch, watchEffect, onMounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { useGlobalLoading } from '@/Composables/useGlobalLoading'

export function useLayout() {
    const page = usePage()
    const { isLoading } = useGlobalLoading()

    // ─── User ─────────────────────────────────────────────────────────────────
    const user = computed(() => page.props.auth?.user ?? null)

    // ─── Dark mode ────────────────────────────────────────────────────────────
    const isDarkMode = ref(false)

    onMounted(() => {
        try {
            const saved = typeof localStorage !== 'undefined' && localStorage.getItem('darkMode') === 'true'
            isDarkMode.value = saved
            document.documentElement.classList.toggle('dark', saved)
        } catch { /* noop */ }
    })

    const toggleDarkMode = () => {
        isDarkMode.value = !isDarkMode.value
        document.documentElement.classList.toggle('dark', isDarkMode.value)
        try { if (typeof localStorage !== 'undefined') localStorage.setItem('darkMode', String(isDarkMode.value)) }
        catch { /* noop */ }
    }

    // ─── Privacy consent modal ────────────────────────────────────────────────
    const privacyConsent = computed(() => page.props.privacy_consent ?? { required: false })
    const showPrivacyModal = ref(false)

    watch(
        () => [page.props.auth?.user, privacyConsent.value],
        ([u, consent]) => {
            showPrivacyModal.value = !!(u && consent?.required)
        },
        { immediate: true },
    )

    const handlePrivacyAccept = () => {
        window.axios.post('/privacy-consent/accept')
            .then(() => {
                showPrivacyModal.value = false
                router.reload({ only: ['privacy_consent'] })
            })
            .catch((err) => console.error('Failed to accept privacy consent:', err))
    }

    const handlePrivacyCancel = () => {
        router.post(route('idp.logout'), {}, {
            onSuccess: () => { showPrivacyModal.value = false },
            onError: (err) => console.error('Failed to log out:', err),
        })
    }

    // ─── Mobile sidebar ───────────────────────────────────────────────────────
    // Single v-model:open ref — works with the new Sidebar API.
    const sidebarOpen = ref(false)

    // Prevent background scroll when drawer is open
    watchEffect(() => {
        document.body.classList.toggle('overflow-hidden', sidebarOpen.value)
    })

    return {
        user,
        isLoading,
        isDarkMode,
        toggleDarkMode,
        showPrivacyModal,
        handlePrivacyAccept,
        handlePrivacyCancel,
        sidebarOpen,
    }
}
