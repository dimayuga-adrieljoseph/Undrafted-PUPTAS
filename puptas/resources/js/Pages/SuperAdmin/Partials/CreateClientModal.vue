<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faTimes, faPlus, faShieldAlt, faSpinner } from '@fortawesome/free-solid-svg-icons'
library.add(faTimes, faPlus, faShieldAlt, faSpinner)

const props = defineProps({
    show: Boolean,
    availableScopes: Array, // [{ id, description }]
})

const emit = defineEmits(['close', 'created'])

const form = useForm({
    name: '',
    scopes: [],
})

const scopeColors = {
    'medical-read':  'blue',
    'medical-write': 'orange',
    'student-read':  'indigo',
    'program-read':  'green',
}

const colorClasses = {
    blue:   { border: 'border-blue-400 dark:border-blue-500', bg: 'bg-blue-50 dark:bg-blue-900/20', badge: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300' },
    orange: { border: 'border-orange-400 dark:border-orange-500', bg: 'bg-orange-50 dark:bg-orange-900/20', badge: 'bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300' },
    indigo: { border: 'border-indigo-400 dark:border-indigo-500', bg: 'bg-indigo-50 dark:bg-indigo-900/20', badge: 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300' },
    green:  { border: 'border-green-400 dark:border-green-500', bg: 'bg-green-50 dark:bg-green-900/20', badge: 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300' },
    purple: { border: 'border-purple-400 dark:border-purple-500', bg: 'bg-purple-50 dark:bg-purple-900/20', badge: 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300' },
}

const getColor = (scopeId) => colorClasses[scopeColors[scopeId] ?? 'purple']

const toggleScope = (scopeId) => {
    const idx = form.scopes.indexOf(scopeId)
    if (idx === -1) form.scopes.push(scopeId)
    else form.scopes.splice(idx, 1)
}

const isSelected = (scopeId) => form.scopes.includes(scopeId)

const submit = () => {
    form.post(route('api-clients.store'), {
        onSuccess: () => {
            emit('created')
            form.reset()
        },
    })
}

const close = () => {
    form.reset()
    emit('close')
}
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="fixed inset-0 z-[9998] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="close" />

                <div class="relative w-full max-w-md bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-200 dark:border-gray-700">
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-[#9E122C]/10">
                                <FontAwesomeIcon icon="shield-alt" class="w-5 h-5 text-[#9E122C]" />
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-gray-900 dark:text-white">New M2M Client</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Client Credentials Grant</p>
                            </div>
                        </div>
                        <button @click="close" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                            <FontAwesomeIcon icon="times" class="w-4 h-4" />
                        </button>
                    </div>

                    <!-- Body -->
                    <form @submit.prevent="submit" class="px-6 py-5 space-y-5">
                        <!-- System Name -->
                        <div>
                            <label for="client-name" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                                System Name <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="client-name"
                                v-model="form.name"
                                type="text"
                                placeholder="e.g. Medical Clinic System"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-[#9E122C]/40 focus:border-[#9E122C] outline-none transition placeholder-gray-400 dark:placeholder-gray-500"
                                :class="{ 'border-red-400': form.errors.name }"
                                required
                            />
                            <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                        </div>

                        <!-- Scope Selection -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                Grant Scopes <span class="text-red-500">*</span>
                            </label>
                            <p v-if="form.errors.scopes" class="text-xs text-red-500 mb-2">{{ form.errors.scopes }}</p>
                            <div class="grid grid-cols-1 gap-2.5">
                                <button
                                    v-for="scope in availableScopes"
                                    :key="scope.id"
                                    type="button"
                                    @click="toggleScope(scope.id)"
                                    :class="[
                                        'flex items-center gap-3 px-4 py-3 rounded-xl border-2 text-left transition-all duration-150',
                                        isSelected(scope.id)
                                            ? `${getColor(scope.id).border} ${getColor(scope.id).bg}`
                                            : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-800'
                                    ]"
                                >
                                    <div :class="[
                                        'w-4 h-4 rounded border-2 flex items-center justify-center shrink-0 transition-all',
                                        isSelected(scope.id)
                                            ? `${getColor(scope.id).border} bg-current`
                                            : 'border-gray-300 dark:border-gray-600'
                                    ]">
                                        <svg v-if="isSelected(scope.id)" class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span :class="['inline-flex px-2 py-0.5 rounded-full text-xs font-mono font-semibold', getColor(scope.id).badge]">
                                            {{ scope.id }}
                                        </span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ scope.description }}</p>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center justify-end gap-3 pt-2">
                            <button type="button" @click="close"
                                class="px-4 py-2 rounded-xl border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                Cancel
                            </button>
                            <button
                                id="create-client-submit-btn"
                                type="submit"
                                :disabled="form.processing || !form.name || !form.scopes.length"
                                class="flex items-center gap-2 px-5 py-2 rounded-xl bg-[#9E122C] hover:bg-[#800000] disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-medium transition shadow-sm"
                            >
                                <FontAwesomeIcon v-if="form.processing" icon="spinner" class="w-4 h-4 animate-spin" />
                                <FontAwesomeIcon v-else icon="plus" class="w-4 h-4" />
                                Create Client
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
