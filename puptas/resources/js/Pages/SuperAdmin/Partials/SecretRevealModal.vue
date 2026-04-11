<script setup>
import { ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faKey, faCopy, faCheckCircle, faTimes, faExclamationTriangle } from '@fortawesome/free-solid-svg-icons'
library.add(faKey, faCopy, faCheckCircle, faTimes, faExclamationTriangle)

const props = defineProps({
    show: Boolean,
    client: Object, // { id, name, secret, scopes }
})

const emit = defineEmits(['close'])

const copiedId = ref(false)
const copiedSecret = ref(false)

const copyToClipboard = async (text, type) => {
    await navigator.clipboard.writeText(text)
    if (type === 'id') {
        copiedId.value = true
        setTimeout(() => (copiedId.value = false), 2500)
    } else {
        copiedSecret.value = true
        setTimeout(() => (copiedSecret.value = false), 2500)
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" />

                <!-- Modal -->
                <div class="relative w-full max-w-lg bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">

                    <!-- Warning Banner -->
                    <div class="bg-amber-50 dark:bg-amber-900/30 border-b border-amber-200 dark:border-amber-700 px-6 py-4 flex items-start gap-3">
                        <FontAwesomeIcon icon="exclamation-triangle" class="w-5 h-5 text-amber-500 mt-0.5 shrink-0" />
                        <div>
                            <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Save these credentials now</p>
                            <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">
                                The client secret will <strong>never be shown again</strong>. Copy it and store it securely.
                            </p>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="px-6 py-6 space-y-5">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <span class="p-2 rounded-lg bg-purple-100 dark:bg-purple-900/40">
                                    <FontAwesomeIcon icon="key" class="w-4 h-4 text-purple-600 dark:text-purple-300" />
                                </span>
                                Client Created: {{ client?.name }}
                            </h2>
                        </div>

                        <!-- Client ID -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Client ID</label>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 block bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-sm font-mono px-4 py-2.5 rounded-lg border border-gray-200 dark:border-gray-700 break-all">
                                    {{ client?.id }}
                                </code>
                                <button
                                    @click="copyToClipboard(client?.id, 'id')"
                                    class="p-2.5 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-purple-100 dark:hover:bg-purple-900/40 text-gray-500 dark:text-gray-400 hover:text-purple-600 dark:hover:text-purple-300 transition border border-gray-200 dark:border-gray-700"
                                    title="Copy Client ID"
                                >
                                    <FontAwesomeIcon :icon="copiedId ? 'check-circle' : 'copy'" class="w-4 h-4" :class="copiedId ? 'text-green-500' : ''" />
                                </button>
                            </div>
                        </div>

                        <!-- Client Secret -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Client Secret</label>
                            <div class="flex items-center gap-2">
                                <code class="flex-1 block bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 text-sm font-mono px-4 py-2.5 rounded-lg border border-amber-300 dark:border-amber-600 break-all">
                                    {{ client?.secret }}
                                </code>
                                <button
                                    @click="copyToClipboard(client?.secret, 'secret')"
                                    class="p-2.5 rounded-lg bg-amber-50 dark:bg-amber-900/30 hover:bg-amber-100 dark:hover:bg-amber-900/50 text-amber-600 dark:text-amber-300 transition border border-amber-200 dark:border-amber-700"
                                    title="Copy Secret"
                                >
                                    <FontAwesomeIcon :icon="copiedSecret ? 'check-circle' : 'copy'" class="w-4 h-4" :class="copiedSecret ? 'text-green-500' : ''" />
                                </button>
                            </div>
                        </div>

                        <!-- Granted Scopes -->
                        <div v-if="client?.scopes?.length">
                            <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Granted Scopes</label>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="scope in client.scopes"
                                    :key="scope"
                                    class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-700"
                                >
                                    {{ scope }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 pb-6 flex justify-end">
                        <button
                            id="secret-modal-close-btn"
                            @click="emit('close')"
                            class="px-5 py-2.5 bg-[#9E122C] hover:bg-[#800000] text-white rounded-xl font-medium text-sm transition shadow-sm"
                        >
                            I've saved the credentials
                        </button>
                    </div>

                    <!-- Close X -->
                    <button
                        @click="emit('close')"
                        class="absolute top-3 right-3 p-1.5 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                    >
                        <FontAwesomeIcon icon="times" class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
