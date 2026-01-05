<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";

const userCounts = ref({});
const programs = ref([]);
const message = ref("");
const error = ref("");

const form = ref({
    salutation: "",
    firstname: "",
    lastname: "",
    middlename: "",
    extension_name: "",
    email: "",
    contact_number: "",
    password: "",
    password_confirmation: "",
    role_id: "",
    program: "",
});

const salutations = ["Mr.", "Ms.", "Mrs.", "Sr.", "Mx.", "Prof.", "Dr."];
const roles = [
    { id: 1, name: "Applicant" },
    { id: 2, name: "Admin" },
    { id: 3, name: "Evaluator" },
    { id: 4, name: "Interviewer" },
    { id: 5, name: "Medical Staff" },
    { id: 6, name: "Registrar" },
];
const icons = {
    "Total Users": "fas fa-users",
    Applicants: "fas fa-user",
    Admins: "fas fa-tools",
    Evaluator: "fas fa-check",
    Interviewer: "fas fa-edit",
    "Medical Staff": "fa-solid fa-suitcase-medical",
    Registrar: "fas fa-user",
};

const fetchUserStats = async () => {
    const res = await axios.get("/api/user-stats");
    userCounts.value = res.data;
};

const fetchPrograms = async () => {
    const res = await axios.get("/api/programs");
    programs.value = res.data;
};

const submitForm = async () => {
    message.value = "";
    error.value = "";
    try {
        await axios.post("/api/add-user", form.value);
        message.value = "User added successfully.";
        Object.keys(form.value).forEach((k) => (form.value[k] = ""));
        await fetchUserStats();
    } catch (e) {
        error.value = e.response?.data?.message || "Error adding user.";
    }
};

onMounted(() => {
    fetchUserStats();
    fetchPrograms();
});
</script>

<style scoped>
@import url("https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css");
</style>

<template>
    <div class="flex flex-col min-h-screen">
        <!-- User Types -->
        <section class="user-types-section w-full max-w-4xl mx-auto p-8">
            <div class="text-center">
                <h2 class="text-2xl font-bold mb-4 text-maroon">User Types</h2>
                <div class="grid grid-cols-3 gap-4">
                    <div
                        v-for="(count, role) in userCounts"
                        :key="role"
                        class="user-type-info"
                    >
                        <div class="user-type-icon">
                            <i :class="icons[role]"></i>
                        </div>
                        <div class="user-type-text">{{ role }}</div>
                        <div class="user-type-count">{{ count }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Add User Form -->
        <section class="form-wrapper">
            <div class="form-box">
                <h2 class="form-title">Add New User</h2>

                <form @submit.prevent="submitForm">
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="salutation"
                                >Salutation
                                <span class="required">*</span></label
                            >
                            <select
                                v-model="form.salutation"
                                required
                                class="form-select"
                            >
                                <option disabled value="">
                                    ---- Select Salutation ----
                                </option>
                                <option
                                    v-for="s in salutations"
                                    :key="s"
                                    :value="s"
                                >
                                    {{ s }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group half-width">
                            <label
                                >First Name
                                <span class="required">*</span></label
                            >
                            <input
                                v-model="form.firstname"
                                type="text"
                                class="form-input"
                                required
                            />
                        </div>

                        <div class="form-group half-width">
                            <label
                                >Last Name
                                <span class="required">*</span></label
                            >
                            <input
                                v-model="form.lastname"
                                type="text"
                                class="form-input"
                                required
                            />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group half-width">
                            <label>Middle Name</label>
                            <input
                                v-model="form.middlename"
                                type="text"
                                class="form-input"
                            />
                        </div>
                        <div class="form-group half-width">
                            <label>Extension Name</label>
                            <input
                                v-model="form.extension_name"
                                type="text"
                                class="form-input"
                            />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group half-width">
                            <label>Email <span class="required">*</span></label>
                            <input
                                v-model="form.email"
                                type="email"
                                class="form-input"
                                required
                                pattern="[a-z0-9._%+-]+@gmail\.com$"
                            />
                        </div>
                        <div class="form-group half-width">
                            <label
                                >Contact Number
                                <span class="required">*</span></label
                            >
                            <div class="form-input-group">
                                <div class="input-prefix">+63</div>
                                <input
                                    v-model="form.contact_number"
                                    type="text"
                                    class="form-input"
                                    maxlength="10"
                                    pattern="\d{10}"
                                    required
                                />
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group half-width">
                            <label
                                >Password <span class="required">*</span></label
                            >
                            <input
                                v-model="form.password"
                                type="password"
                                class="form-input"
                                required
                            />
                        </div>

                        <div class="form-group half-width">
                            <label
                                >Confirm Password
                                <span class="required">*</span></label
                            >
                            <input
                                v-model="form.password_confirmation"
                                type="password"
                                class="form-input"
                                required
                            />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label
                                >User Type
                                <span class="required">*</span></label
                            >
                            <select
                                v-model="form.role_id"
                                class="form-select"
                                required
                            >
                                <option disabled value="">
                                    ---- Select ----
                                </option>
                                <option
                                    v-for="role in roles"
                                    :key="role.id"
                                    :value="role.id"
                                >
                                    {{ role.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label
                                >Program <span class="required">*</span></label
                            >
                            <select v-model="form.program" class="form-select">
                                <option disabled value="">
                                    ---- Select Program ----
                                </option>
                                <option
                                    v-for="p in programs"
                                    :key="p.code"
                                    :value="p.code"
                                >
                                    {{ p.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group mt-4 flex items-center justify-end">
                        <button type="submit" class="form-button ms-4">
                            <i class="fa-solid fa-user-plus"></i> Add User
                        </button>
                    </div>
                </form>

                <div v-if="message" class="mt-4 text-green-600 font-bold">
                    {{ message }}
                </div>
                <div v-if="error" class="mt-4 text-red-600 font-bold">
                    {{ error }}
                </div>
            </div>
        </section>
    </div>
</template>
