<script setup>
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import CreateClientModal from '@/Pages/SuperAdmin/Partials/CreateClientModal.vue'
import SecretRevealModal from '@/Pages/SuperAdmin/Partials/SecretRevealModal.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faShieldAlt, faPlus, faTrash, faSync, faKey,
    faCheckCircle, faBan, faInfoCircle, faNetworkWired,
} from '@fortawesome/free-solid-svg-icons'
library.add(faShieldAlt, faPlus, faTrash, faSync, faKey, faCheckCircle, faBan, faInfoCircle, faNetworkWired)

const page = usePage()

const clients = computed(() => page.props.clients ?? [])
const availableScopes = computed(() => page.props.available_scopes ?? [])

// Show new client if just created/regenerated
const newClient = computed(() => page.props.flash?.new_client ?? null)

// Modals
const showCreateModal = ref(false)
const showSecretModal = ref(false)
const revealedClient = ref(null)

// Watch for the flash message containing the new client secret
import { watch } from 'vue'
watch(() => page.props.flash?.new_client, (val) => {
    if (val) {
        revealedClient.value = val
        showSecretModal.value = true
    }
}, { immediate: true })

const handleCreated = () => {
    showCreateModal.value = false
}

const closeSecret = () => {
    showSecretModal.value = false
    revealedClient.value = null
}

// Revoke
const revokeClient = (client) => {
    if (!confirm(`Are you sure you want to revoke "${client.name}"? All active tokens will be invalidated immediately.`)) return
    router.delete(route('api-clients.destroy', { id: client.id }), {
        preserveScroll: true,
    })
}

// Regenerate secret
const regenerateSecret = (client) => {
    if (!confirm(`Regenerate secret for "${client.name}"? The old secret will stop working immediately.`)) return
    router.post(route('api-clients.regenerate', { id: client.id }), {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (page.props.flash?.new_client) {
                revealedClient.value = page.props.flash.new_client
                showSecretModal.value = true
            }
        },
    })
}

// Scope display
const scopeColors = {
    'medical-read':  { bg: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-700', dot: 'bg-blue-500' },
    'medical-write': { bg: 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 border-orange-200 dark:border-orange-700', dot: 'bg-orange-500' },
    'student-read':  { bg: 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-700', dot: 'bg-indigo-500' },
    'program-read':  { bg: 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 border-green-200 dark:border-green-700', dot: 'bg-green-500' },
}
const getScopeClasses = (s) => scopeColors[s] ?? { bg: 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-600', dot: 'bg-gray-400' }

const formatDate = (d) => d ? new Date(d).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'N/A'

const activeCount = computed(() => clients.value.filter(c => !c.revoked).length)
const totalScopes = computed(() => {
    const set = new Set()
    clients.value.forEach(c => (c.scopes ?? []).forEach(s => set.add(s)))
    return set.size
})
</script>

<template>
    <SuperAdminLayout title="API Client Management">
        <div class="px-4 md:px-8 py-8 w-full">

            <!-- Header -->
            <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-[#9E122C]/10 rounded-xl">
                        <FontAwesomeIcon icon="shield-alt" class="h-6 w-6 text-[#9E122C]" />
                    </div>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">API Client Management</h1>
                        <p class="text-gray-600 dark:text-gray-400 mt-1 text-sm">OAuth 2.0 Machine-to-Machine (Client Credentials) clients</p>
                    </div>
                </div>
                <button
                    id="open-create-client-btn"
                    @click="showCreateModal = true"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#9E122C] hover:bg-[#800000] text-white rounded-xl font-medium text-sm transition shadow-sm shrink-0"
                >
                    <FontAwesomeIcon icon="plus" class="w-4 h-4" />
                    New Client
                </button>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">Total Clients</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ clients.length }}</p>
                        </div>
                        <div class="p-3 rounded-lg bg-[#9E122C]/10 text-[#9E122C]">
                            <FontAwesomeIcon icon="network-wired" class="w-6 h-6" />
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">Active Clients</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ activeCount }}</p>
                        </div>
                        <div class="p-3 rounded-lg bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-300">
                            <FontAwesomeIcon icon="check-circle" class="w-6 h-6" />
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400 text-sm font-medium mb-2">Scope Types Used</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ totalScopes }}</p>
                        </div>
                        <div class="p-3 rounded-lg bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-300">
                            <FontAwesomeIcon icon="key" class="w-6 h-6" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scope Legend -->
            <div class="mb-6 flex flex-wrap gap-3 items-center">
                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Scope Legend:</span>
                <span
                    v-for="s in availableScopes"
                    :key="s.id"
                    :class="['inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium border', getScopeClasses(s.id).bg]"
                >
                    <span :class="['w-1.5 h-1.5 rounded-full', getScopeClasses(s.id).dot]" />
                    {{ s.id }}
                </span>
            </div>

            <!-- Empty State -->
            <div v-if="clients.length === 0" class="text-center py-20 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="inline-flex p-4 rounded-full bg-gray-100 dark:bg-gray-700 mb-4">
                    <FontAwesomeIcon icon="shield-alt" class="w-10 h-10 text-gray-400 dark:text-gray-500" />
                </div>
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">No API clients yet</h3>
                <p class="text-gray-500 dark:text-gray-400 text-sm mb-6 max-w-sm mx-auto">
                    Create your first M2M client to enable secure access for Medical, Guidance, or Program external systems.
                </p>
                <button
                    @click="showCreateModal = true"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#9E122C] hover:bg-[#800000] text-white rounded-xl font-medium text-sm transition"
                >
                    <FontAwesomeIcon icon="plus" class="w-4 h-4" />
                    Create First Client
                </button>
            </div>

            <!-- Client Cards -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                <div
                    v-for="client in clients"
                    :key="client.id"
                    :class="[
                        'bg-white dark:bg-gray-800 rounded-xl shadow-sm border transition-all duration-300',
                        client.revoked
                            ? 'border-red-200 dark:border-red-900/50 opacity-70'
                            : 'border-gray-200 dark:border-gray-700 hover:shadow-lg hover:-translate-y-0.5'
                    ]"
                >
                    <!-- Card Header -->
                    <div class="px-5 pt-5 pb-4 border-b border-gray-100 dark:border-gray-700">
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <div :class="['p-2 rounded-lg shrink-0', client.revoked ? 'bg-red-100 dark:bg-red-900/30' : 'bg-[#9E122C]/10']">
                                    <FontAwesomeIcon :icon="client.revoked ? 'ban' : 'shield-alt'" :class="['w-4 h-4', client.revoked ? 'text-red-500' : 'text-[#9E122C]']" />
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-gray-900 dark:text-white text-sm truncate" :title="client.name">{{ client.name }}</h3>
                                    <code class="text-xs text-gray-400 dark:text-gray-500 font-mono truncate block" :title="client.id">ID: {{ client.id }}</code>
                                </div>
                            </div>
                            <span :class="[
                                'shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium',
                                client.revoked
                                    ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400'
                                    : 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300'
                            ]">
                                <span :class="['w-1.5 h-1.5 rounded-full', client.revoked ? 'bg-red-500' : 'bg-green-500']" />
                                {{ client.revoked ? 'Revoked' : 'Active' }}
                            </span>
                        </div>

                        <!-- Scopes -->
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="scope in (client.scopes ?? [])"
                                :key="scope"
                                :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-mono font-medium border', getScopeClasses(scope).bg]"
                            >
                                <span :class="['w-1.5 h-1.5 rounded-full', getScopeClasses(scope).dot]" />
                                {{ scope }}
                            </span>
                            <span v-if="!client.scopes?.length" class="text-xs text-gray-400 dark:text-gray-500 italic">No scopes assigned</span>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="px-5 py-3.5 flex items-center justify-between gap-2">
                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            Created {{ formatDate(client.created_at) }}
                        </p>
                        <div v-if="!client.revoked" class="flex items-center gap-2">
                            <button
                                @click="regenerateSecret(client)"
                                class="p-1.5 rounded-lg text-gray-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition"
                                title="Regenerate Secret"
                            >
                                <FontAwesomeIcon icon="sync" class="w-3.5 h-3.5" />
                            </button>
                            <button
                                @click="revokeClient(client)"
                                class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition"
                                title="Revoke Client"
                            >
                                <FontAwesomeIcon icon="trash" class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- How to use -->
            <div class="mt-8 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center gap-2 mb-3">
                    <FontAwesomeIcon icon="info-circle" class="w-4 h-4 text-gray-400 dark:text-gray-500" />
                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">How External Systems Authenticate</h4>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">External systems exchange their <code class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded font-mono">client_id</code> + <code class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded font-mono">client_secret</code> for a short-lived access token:</p>
                <pre class="text-xs bg-gray-900 dark:bg-gray-950 text-green-400 rounded-lg p-4 overflow-x-auto"><code>POST {{ $page.props.ziggy?.url ?? '' }}/oauth/token
Content-Type: application/json

{
  "grant_type": "client_credentials",
  "client_id": "&lt;your-client-id&gt;",
  "client_secret": "&lt;your-client-secret&gt;",
  "scope": "medical-read"
}</code></pre>
            </div>
        </div>

        <!-- Modals -->
        <CreateClientModal
            :show="showCreateModal"
            :available-scopes="availableScopes"
            @close="showCreateModal = false"
            @created="handleCreated"
        />

        <SecretRevealModal
            :show="showSecretModal"
            :client="revealedClient"
            @close="closeSecret"
        />
    </SuperAdminLayout>
</template>
