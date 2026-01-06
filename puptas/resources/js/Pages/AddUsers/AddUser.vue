<script setup>
import { computed, watch } from "vue"
import { Link, useForm } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowLeft, faSave, faTimes } from "@fortawesome/free-solid-svg-icons"

/* ---------------- PROPS ---------------- */
const props = defineProps({
    user: Object,
    roles: Object,
    programs: Array,
    errors: Object,
})

/* ---------------- FORM ---------------- */
const form = useForm({
    firstname: props.user.firstname ?? "",
    lastname: props.user.lastname ?? "",
    middlename: props.user.middlename ?? "",
    email: props.user.email ?? "",
    contactnumber: props.user.contactnumber ?? "",
    password: "",
    password_confirmation: "",
    role_id: props.user.role_id,
    program: props.user.programs?.[0]?.id ?? "",
})

/* ---------------- PROGRAM VISIBILITY ---------------- */
const showProgram = computed(() => form.role_id == 1)

watch(showProgram, (visible) => {
    if (!visible) {
        form.program = ""
    }
})

/* ---------------- SUBMIT ---------------- */
const submit = () => {
    form.put(route("users.update", props.user.id))
}
</script>

<template>
    <div class="page-header max-w-[800px] mx-auto mt-8 px-8">
        <Link :href="route('users.index')" class="back-button">
            <FontAwesomeIcon :icon="faArrowLeft" /> Back to Manage Users
        </Link>
    </div>

    <div class="flex flex-col min-h-screen">
        <div class="form-wrapper">
            <div class="form-box">
                <h2 class="form-title">Edit User</h2>

                <!-- Errors -->
                <div
                    v-if="Object.keys(errors).length"
                    class="bg-red-100 text-red-700 p-4 rounded mb-4"
                >
                    <ul class="list-disc ml-5">
                        <li v-for="(error, key) in errors" :key="key">
                            {{ error }}
                        </li>
                    </ul>
                </div>

                <form @submit.prevent="submit">
                    <!-- NAME -->
                    <div class="form-row">
                        <div class="form-group half-width">
                            <label>First Name *</label>
                            <input v-model="form.firstname" class="form-input" required />
                        </div>

                        <div class="form-group half-width">
                            <label>Last Name *</label>
                            <input v-model="form.lastname" class="form-input" required />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group half-width">
                            <label>Middle Name</label>
                            <input v-model="form.middlename" class="form-input" />
                        </div>
                    </div>

                    <!-- EMAIL & CONTACT -->
                    <div class="form-row">
                        <div class="form-group half-width">
                            <label>Email *</label>
                            <input
                                v-model="form.email"
                                type="email"
                                class="form-input"
                                required
                                pattern="[a-z0-9._%+\-]+@gmail\.com$"
                            />
                        </div>

                        <div class="form-group half-width">
                            <label>Contact Number *</label>
                            <div class="form-input-group">
                                <div class="input-prefix">+63</div>
                                <input
                                    v-model="form.contactnumber"
                                    class="form-input"
                                    maxlength="10"
                                    pattern="\d{10}"
                                    required
                                />
                            </div>
                        </div>
                    </div>

                    <!-- PASSWORD -->
                    <div class="form-row">
                        <div class="form-group half-width">
                            <label>New Password</label>
                            <input
                                v-model="form.password"
                                type="password"
                                class="form-input"
                                pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[@$!%*?&.]).{8,}"
                            />
                        </div>

                        <div class="form-group half-width">
                            <label>Confirm Password</label>
                            <input
                                v-model="form.password_confirmation"
                                type="password"
                                class="form-input"
                            />
                        </div>
                    </div>

                    <!-- ROLE -->
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label>User Type *</label>
                            <select v-model="form.role_id" class="form-select" required>
                                <option
                                    v-for="(name, id) in roles"
                                    :key="id"
                                    :value="id"
                                >
                                    {{ name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- PROGRAM -->
                    <div v-if="showProgram" class="form-row">
                        <div class="form-group full-width">
                            <label>Program *</label>
                            <select v-model="form.program" class="form-select" required>
                                <option disabled value="">---- Select Program ----</option>
                                <option
                                    v-for="program in programs"
                                    :key="program.id"
                                    :value="program.id"
                                >
                                    {{ program.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- ACTIONS -->
                    <div class="form-group mt-6 flex justify-end gap-3">
                        <Link
                            :href="route('users.index')"
                            class="form-button bg-gray-500"
                        >
                            <FontAwesomeIcon :icon="faTimes" /> Cancel
                        </Link>

                        <button type="submit" class="form-button">
                            <FontAwesomeIcon :icon="faSave" /> Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
