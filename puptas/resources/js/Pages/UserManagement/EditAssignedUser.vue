<script setup>
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    user: Object,
    programs: Array,
    assignedPrograms: Array
});

const form = useForm({
    salutation: props.user.salutation || '',
    lastname: props.user.lastname || '',
    firstname: props.user.firstname || '',
    contactnumber: props.user.contactnumber || '',
    email: props.user.email || '',
    role: props.user.role_id || '',
    programs: props.assignedPrograms || []
});

const submitForm = () => {
    form.post(route('admin.users.update', props.user.id), {
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
    <AppLayout title="Edit Assigned User">
        <div class="page-container">
            <!-- Error Messages -->
            <div v-if="$page.props.flash?.error" class="error-message">
                {{ $page.props.flash.error }}
            </div>

            <div v-if="form.errors && Object.keys(form.errors).length > 0" class="error-message">
                <ul>
                    <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                </ul>
            </div>

            <h1>Edit Assigned User</h1>

            <div class="form-card">
                <form @submit.prevent="submitForm">
                    <!-- Salutation -->
                    <div class="form-group">
                        <label for="salutation">Salutation</label>
                        <select id="salutation" v-model="form.salutation" required>
                            <option value="Mr.">Mr.</option>
                            <option value="Ms.">Ms.</option>
                            <option value="Mrs.">Mrs.</option>
                            <option value="Sr.">Sr.</option>
                            <option value="Mx.">Mx.</option>
                            <option value="Prof.">Prof.</option>
                            <option value="Dr.">Dr.</option>
                        </select>
                    </div>

                    <!-- Last Name -->
                    <div class="form-group">
                        <label for="lastname">Last Name</label>
                        <input type="text" id="lastname" v-model="form.lastname" required>
                    </div>

                    <!-- First Name -->
                    <div class="form-group">
                        <label for="firstname">First Name</label>
                        <input type="text" id="firstname" v-model="form.firstname" required>
                    </div>

                    <!-- Contact Number -->
                    <div class="form-group">
                        <label for="contactnumber">Phone</label>
                        <input type="text" id="contactnumber" v-model="form.contactnumber" required>
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" v-model="form.email" required>
                    </div>

                    <!-- Role Selection -->
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" v-model="form.role" required>
                            <option value="3">Evaluator</option>
                            <option value="4">Interviewer</option>
                        </select>
                    </div>

                    <!-- Program Assignment -->
                    <div class="form-group">
                        <label for="programs">Assign to Programs</label>
                        <small>Hold Ctrl (Windows) or Cmd (Mac) to select multiple programs</small>
                        <select id="programs" v-model="form.programs" multiple required>
                            <option v-for="program in programs" :key="program.id" :value="program.id">
                                {{ program.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Submit Button -->
                    <div class="form-group">
                        <button type="submit" :disabled="form.processing">
                            <i class="fas fa-save"></i> Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.page-container {
    font-family: 'Inter', sans-serif;
    background: #f4f6f8;
    padding: 2rem;
    min-height: 100vh;
}

h1 {
    text-align: center;
    color: #9E122C;
    margin-bottom: 2rem;
}

.form-card {
    background: #fff;
    max-width: 700px;
    margin: 0 auto;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
}

.form-group {
    margin-bottom: 1.5rem;
}

label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

input[type="text"],
input[type="email"],
select {
    width: 100%;
    padding: 0.65rem 0.75rem;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 1rem;
    transition: border 0.3s;
}

input[type="text"]:focus,
input[type="email"]:focus,
select:focus {
    outline: none;
    border-color: #9E122C;
    box-shadow: 0 0 0 2px rgba(158, 18, 44, 0.2);
}

select[multiple] {
    min-height: 120px;
}

small {
    font-size: 0.85rem;
    color: #666;
    display: block;
    margin-bottom: 0.5rem;
}

button {
    background: #9E122C;
    color: #fff;
    padding: 0.65rem 1.25rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: bold;
    transition: background 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

button:hover {
    background: #7a0f24;
}

button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.error-message {
    max-width: 700px;
    margin: 0 auto 1rem auto;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    font-size: 0.95rem;
}

.error-message ul {
    padding-left: 1.25rem;
    margin: 0;
}

@media (max-width: 600px) {
    .form-card {
        padding: 1rem;
    }
}
</style>
