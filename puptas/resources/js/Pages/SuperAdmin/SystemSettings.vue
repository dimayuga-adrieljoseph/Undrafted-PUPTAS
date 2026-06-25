<script setup>
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    }
})

const form = useForm({
    enable_qualified_programs_view: props.settings.enable_qualified_programs_view,
})

const submit = () => {
    form.post(route('system-settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // Success is handled by global flash component if it exists
        }
    })
}
</script>

<template>
    <Head title="System Settings" />

    <AdminLayout title="System Settings">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                System Settings
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">General System Settings</h3>

                    <form @submit.prevent="submit" class="space-y-6">
                        
                        <!-- Qualified Programs View Toggle -->
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input
                                    id="enable_qualified_programs_view"
                                    type="checkbox"
                                    v-model="form.enable_qualified_programs_view"
                                    class="w-4 h-4 bg-gray-50 rounded border border-gray-300 focus:ring-3 focus:ring-blue-300 dark:bg-gray-700 dark:border-gray-600 dark:focus:ring-blue-600 dark:ring-offset-gray-800"
                                >
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="enable_qualified_programs_view" class="font-medium text-gray-900 dark:text-gray-300">
                                    Enable "Qualified Programs" View for Applicants
                                </label>
                                <p class="text-gray-500 dark:text-gray-400">
                                    When disabled, applicants will not be able to view the Qualified Programs page or see available slots until you enable it again.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 mt-6">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150"
                            >
                                Save Settings
                            </button>

                            <Transition
                                enter-active-class="transition ease-in-out"
                                enter-from-class="opacity-0"
                                leave-active-class="transition ease-in-out"
                                leave-to-class="opacity-0"
                            >
                                <p v-if="form.recentlySuccessful" class="text-sm text-gray-600 dark:text-gray-400">Saved.</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
