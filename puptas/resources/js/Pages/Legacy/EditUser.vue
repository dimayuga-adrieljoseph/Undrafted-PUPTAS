<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    user: Object,
    programs: Array,
    roles: Object
});

const form = useForm({
    firstname: props.user.firstname || '',
    lastname: props.user.lastname || '',
    middlename: props.user.middlename || '',
    extension_name: props.user.extension_name || '',
    email: props.user.email || '',
    contactnumber: props.user.contactnumber || '',
    password: '',
    password_confirmation: '',
    role_id: props.user.role_id || '',
    program: props.user.programs && props.user.programs.length > 0 ? props.user.programs[0].id : ''
});

const showProgramField = computed(() => {
    return form.role_id == 1; // Show for applicants
});

const onRoleChange = () => {
    if (!showProgramField.value) {
        form.program = '';
    }
};

const submitForm = () => {
    form.post(route('users.update', props.user.id), {
        preserveScroll: true
    });
};

onMounted(() => {
    // Load Font Awesome
    if (!document.querySelector('link[href*="font-awesome"]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css';
        document.head.appendChild(link);
    }
});
</script>

<template>
    <AppLayout title="Edit User">
        <div class="form-container">
            <div class="form-card">
                <Link :href="route('users.index')" class="form-button cancel back-button">
                    <i class="fas fa-arrow-left"></i> Back
                </Link>

                <h2>Edit User</h2>

                <!-- Error Messages -->
                <div v-if="form.errors && Object.keys(form.errors).length > 0" class="error-message">
                    <ul>
                        <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                    </ul>
                </div>

                <form @submit.prevent="submitForm">
                    <div class="form-row">
                        <div class="form-group half-width">
                            <label for="firstname">First Name <span class="required">*</span></label>
                            <input type="text" id="firstname" v-model="form.firstname" required>
                        </div>

                        <div class="form-group half-width">
                            <label for="lastname">Last Name <span class="required">*</span></label>
                            <input type="text" id="lastname" v-model="form.lastname" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="middlename">Middle Name</label>
                        <input type="text" id="middlename" v-model="form.middlename">
                    </div>

                    <div class="form-row">
                        <div class="form-group half-width">
                            <label for="email">Email <span class="required">*</span></label>
                            <input 
                                type="email" 
                                id="email" 
                                v-model="form.email" 
                                required
                                pattern="[a-z0-9._%+\-]+@gmail\.com$"
                                title="Must be a valid Gmail address like example@gmail.com"
                            >
                        </div>

                        <div class="form-group half-width">
                            <label for="contactnumber">Contact Number <span class="required">*</span></label>
                            <div class="form-input-group">
                                <div class="input-prefix">+63</div>
                                <input 
                                    type="text" 
                                    id="contactnumber" 
                                    v-model="form.contactnumber" 
                                    required 
                                    maxlength="10"
                                    pattern="\d{10}" 
                                    title="Must be exactly 10 digits"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group half-width">
                            <label for="password">New Password</label>
                            <input 
                                type="password" 
                                id="password" 
                                v-model="form.password"
                                pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[@$!%*?&.]).{8,}"
                            >
                            <small>
                                At least 8 characters, 1 uppercase, 1 lowercase, 1 number, 1 special character
                            </small>
                        </div>

                        <div class="form-group half-width">
                            <label for="password_confirmation">Confirm Password</label>
                            <input type="password" id="password_confirmation" v-model="form.password_confirmation">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="role_id">User Type <span class="required">*</span></label>
                        <select id="role_id" v-model="form.role_id" @change="onRoleChange" required>
                            <option v-for="(roleName, roleId) in roles" :key="roleId" :value="roleId">
                                {{ roleName }}
                            </option>
                        </select>
                    </div>

                    <div class="form-group" v-show="showProgramField">
                        <label for="program">Program <span class="required">*</span></label>
                        <select id="program" v-model="form.program" :required="showProgramField">
                            <option value="" disabled>---- Select Program ----</option>
                            <option v-for="program in programs" :key="program.id" :value="program.id">
                                {{ program.name }}
                            </option>
                        </select>
                    </div>

                    <div class="button-container">
                        <Link :href="route('users.index')" class="form-button cancel">
                            <i class="fas fa-times"></i> Cancel
                        </Link>
                        <button type="submit" class="form-button" :disabled="form.processing">
                            <i class="fas fa-save"></i> Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.form-container {
    font-family: 'Inter', sans-serif;
    background: #f4f6f8;
    padding: 2rem;
    min-height: 100vh;
}

h2 {
    color: #9E122C;
    margin-bottom: 1.5rem;
    text-align: center;
}

.form-card {
    max-width: 800px;
    margin: 0 auto;
    background: #fff;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
}

.back-button {
    margin-bottom: 1rem;
    display: inline-flex;
}

.form-group {
    margin-bottom: 1.5rem;
}

label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.required {
    color: #dc3545;
}

input[type="text"],
input[type="email"],
input[type="password"],
select {
    width: 100%;
    padding: 0.65rem 0.75rem;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 1rem;
    transition: border 0.3s, box-shadow 0.3s;
}

input[type="text"]:focus,
input[type="email"]:focus,
input[type="password"]:focus,
select:focus {
    outline: none;
    border-color: #9E122C;
    box-shadow: 0 0 0 2px rgba(158, 18, 44, 0.2);
}

.form-input-group {
    display: flex;
    align-items: center;
}

.input-prefix {
    padding: 0.65rem 0.75rem;
    background: #e9ecef;
    border: 1px solid #ccc;
    border-right: none;
    border-radius: 8px 0 0 8px;
    font-weight: 500;
}

.form-input-group input {
    border-radius: 0 8px 8px 0;
    border-left: none;
    flex: 1;
}

.half-width {
    width: 48%;
}

.form-row {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.form-button {
    background: #9E122C;
    color: #fff;
    padding: 0.65rem 1.25rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: bold;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: background 0.3s;
    text-decoration: none;
}

.form-button:hover {
    background: #7a0f24;
}

.form-button.cancel {
    background: #6c757d;
}

.form-button.cancel:hover {
    background: #5a6268;
}

.form-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.error-message ul {
    padding-left: 1.5rem;
    margin: 0;
}

small {
    color: #666;
    display: block;
    margin-top: 0.25rem;
    font-size: 0.85rem;
}

.button-container {
    text-align: right;
    margin-top: 1.5rem;
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

@media (max-width: 600px) {
    .half-width {
        width: 100%;
    }

    .form-row {
        flex-direction: column;
    }
}
</style>
