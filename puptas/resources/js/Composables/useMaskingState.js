import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

// In-memory reactive state (NOT stored in localStorage - resets on refresh/session restart)
const isUnmasked = ref(false);
const showComplianceModal = ref(false);

let interceptorsRegistered = false;

export function useMaskingState() {
    // Register axios & Inertia interceptors once
    if (!interceptorsRegistered && typeof window !== 'undefined') {
        interceptorsRegistered = true;

        // Axios request interceptor: automatically attaches ?unmask=1 to GET requests when unmasked
        axios.interceptors.request.use((config) => {
            if (isUnmasked.value && config.method?.toLowerCase() === 'get') {
                config.params = config.params || {};
                if (config.params.unmask === undefined) {
                    config.params.unmask = 1;
                }
            }
            return config;
        });

        // Inertia navigation hook: propagates ?unmask=1 across page visits during unmasked session
        router.on('before', (event) => {
            if (isUnmasked.value && event.detail.visit.method === 'get') {
                const visit = event.detail.visit;
                if (visit.url instanceof URL) {
                    if (!visit.url.searchParams.has('unmask')) {
                        visit.url.searchParams.set('unmask', '1');
                    }
                } else if (typeof visit.url === 'string') {
                    try {
                        const url = new URL(visit.url, window.location.origin);
                        if (!url.searchParams.has('unmask')) {
                            url.searchParams.set('unmask', '1');
                            visit.url = url.pathname + url.search + url.hash;
                        }
                    } catch (e) {
                        // Ignore relative parsing edge cases
                    }
                }
            }
        });
    }

    const openComplianceModal = () => {
        showComplianceModal.value = true;
    };

    const closeComplianceModal = () => {
        showComplianceModal.value = false;
    };

    const confirmUnmask = () => {
        showComplianceModal.value = false;
        isUnmasked.value = true;

        // Reload current page attaching unmask=1
        const url = new URL(window.location.href);
        url.searchParams.set('unmask', '1');
        router.visit(url.pathname + url.search + url.hash, {
            preserveScroll: true,
            preserveState: false,
        });
    };

    const mask = () => {
        showComplianceModal.value = false;
        isUnmasked.value = false;

        // Reload current page removing unmask
        const url = new URL(window.location.href);
        url.searchParams.delete('unmask');
        router.visit(url.pathname + (url.search ? url.search : '') + url.hash, {
            preserveScroll: true,
            preserveState: false,
        });
    };

    const toggleMasking = () => {
        if (isUnmasked.value) {
            mask();
        } else {
            openComplianceModal();
        }
    };

    return {
        isUnmasked,
        showComplianceModal,
        openComplianceModal,
        closeComplianceModal,
        confirmUnmask,
        mask,
        toggleMasking,
    };
}
