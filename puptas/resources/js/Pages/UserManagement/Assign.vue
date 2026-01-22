<script setup>
import { ref, onMounted } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    programs: Array,
    assignedUsers: Array
});

const form = useForm({
    salutation: '',
    lastname: '',
    firstname: '',
    contactnumber: '',
    email: '',
    role: '',
    programs: []
});

const deleteModal = ref(false);
const deleteUserId = ref(null);
const deleteForm = useForm({});

const submitForm = () => {
    form.post(route('admin.users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        }
    });
};

const confirmDeleteUser = (userId) => {
    deleteUserId.value = userId;
    deleteModal.value = true;
};

const cancelDelete = () => {
    deleteModal.value = false;
    deleteUserId.value = null;
};

const deleteUser = () => {
    if (deleteUserId.value) {
        deleteForm.delete(route('admin.users.delete', deleteUserId.value), {
            preserveScroll: true,
            onSuccess: () => {
                deleteModal.value = false;
                deleteUserId.value = null;
            }
        });
    }
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
    <AppLayout title="Create & Assign Users">
        <div class="page-container">
            <!-- Form Card -->
            <div class="form-card">
                <h1>Create User & Assign Programs</h1>

                <!-- Error Messages -->
                <div v-if="form.errors && Object.keys(form.errors).length > 0" class="error-message">
                    <ul>
                        <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                    </ul>
                </div>

                <form @submit.prevent="submitForm">
                    <!-- Salutation -->
                    <div class="form-group">
                        <label for="salutation">Salutation <span class="required">*</span></label>
                        <select id="salutation" v-model="form.salutation" required>
                            <option value="" disabled>Select Salutation</option>
                            <option value="Mr.">Mr.</option>
                            <option value="Ms.">Ms.</option>
                            <option value="Mrs.">Mrs.</option>
                            <option value="Sr.">Sr.</option>
                            <option value="Mx.">Mx.</option>
                            <option value="Prof.">Prof.</option>
                            <option value="Dr.">Dr.</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group half-width">
                            <label for="lastname">Last Name <span class="required">*</span></label>
                            <input type="text" id="lastname" v-model="form.lastname" required>
                        </div>

                        <div class="form-group half-width">
                            <label for="firstname">First Name <span class="required">*</span></label>
                            <input type="text" id="firstname" v-model="form.firstname" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group half-width">
                            <label for="contactnumber">Phone <span class="required">*</span></label>
                            <input type="text" id="contactnumber" v-model="form.contactnumber" required>
                        </div>

                        <div class="form-group half-width">
                            <label for="email">Email <span class="required">*</span></label>
                            <input type="email" id="email" v-model="form.email" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="role">Role <span class="required">*</span></label>
                        <select id="role" v-model="form.role" required>
                            <option value="" disabled>Select Role</option>
                            <option value="3">Evaluator</option>
                            <option value="4">Interviewer</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="programs">Assign to Programs <span class="required">*</span></label>
                        <small>Hold Ctrl (Windows) or Cmd (Mac) to select multiple programs</small>
                        <select id="programs" v-model="form.programs" multiple required size="5">
                            <option v-for="program in programs" :key="program.id" :value="program.id">
                                {{ program.name }}
                            </option>
                        </select>
                    </div>

                    <div style="text-align: right; margin-top: 1.5rem;">
                        <button type="submit" class="form-button" :disabled="form.processing">
                            <i class="fas fa-user-plus"></i> CREATE
                        </button>
                    </div>
                </form>
            </div>

            <!-- Assigned Users Table -->
            <div class="form-card">
                <h2>Assigned Evaluators & Interviewers</h2>
                <p v-if="!assignedUsers || assignedUsers.length === 0" style="text-align:center; color:#666;">
                    No evaluators or interviewers assigned yet.
                </p>
                <table v-else>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Assigned Programs</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in assignedUsers" :key="user.id">
                            <td>{{ user.salutation }} {{ user.firstname }} {{ user.lastname }}</td>
                            <td>{{ user.email }}</td>
                            <td>{{ user.role_id == 3 ? 'Evaluator' : 'Interviewer' }}</td>
                            <td>
                                <span v-if="!user.programs || user.programs.length === 0">Not assigned</span>
                                <span v-else>{{ user.programs.map(p => p.name).join(', ') }}</span>
                            </td>
                            <td>
                                <div class="action-container">
                                    <Link :href="route('admin.users.edit', user.id)" class="action-button edit-button">
                                        <i class="fas fa-edit"></i>
                                        <span class="tooltiptext">Edit</span>
                                    </Link>
                                    <button @click="confirmDeleteUser(user.id)" class="action-button delete-button">
                                        <i class="fas fa-trash-alt"></i>
                                        <span class="tooltiptext">Delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Delete Confirmation Modal -->
            <div v-if="deleteModal" class="modal" @click.self="cancelDelete">
                <div class="modal-content">
                    <p>Are you sure you want to delete this user?</p>
                    <div class="modal-buttons">
                        <button @click="cancelDelete" class="cancel-button">Cancel</button>
                        <button @click="deleteUser" class="delete-button" :disabled="deleteForm.processing">Delete</button>
                    </div>
                </div>
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

h1, h2 {
    text-align: center;
    color: #9E122C;
    margin-bottom: 1.5rem;
}

.form-card {
    max-width: 900px;
    margin: 0 auto 2rem auto;
    background: #fff;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
}

.form-group {
    margin-bottom: 1.25rem;
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
select:focus {
    outline: none;
    border-color: #9E122C;
    box-shadow: 0 0 0 2px rgba(158, 18, 44, 0.2);
}

.form-row {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.half-width {
    width: 48%;
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
}

.form-button:hover {
    background: #7a0f24;
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
    font-size: 0.85rem;
    color: #666;
    display: block;
    margin-bottom: 0.5rem;
}

/* Table Styles */
table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    margin-top: 2rem;
}

th, td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e0e0e0;
    text-align: left;
}

th {
    background: #f5f5f5;
    font-weight: 600;
}

tbody tr:hover {
    background: #f9f9f9;
}

.action-container {
    display: flex;
    gap: 0.5rem;
}

.action-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.35rem 0.65rem;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: background 0.3s;
    position: relative;
    text-decoration: none;
}

.edit-button {
    background: #2196f3;
    color: #fff;
}

.edit-button:hover {
    background: #1976d2;
}

.delete-button {
    background: #f44336;
    color: #fff;
}

.delete-button:hover {
    background: #d32f2f;
}

.tooltiptext {
    visibility: hidden;
    width: 80px;
    background-color: #333;
    color: #fff;
    text-align: center;
    border-radius: 4px;
    padding: 3px 0;
    position: absolute;
    z-index: 1;
    bottom: 125%;
    left: 50%;
    transform: translateX(-50%);
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 0.75rem;
}

.action-button:hover .tooltiptext {
    visibility: visible;
    opacity: 1;
}

/* Modal Styles */
.modal {
    display: flex;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.4);
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: #fff;
    padding: 2rem;
    border-radius: 12px;
    text-align: center;
    max-width: 400px;
    width: 90%;
}

.modal-buttons {
    margin-top: 1.5rem;
    display: flex;
    justify-content: space-around;
}

.modal-buttons button {
    padding: 0.65rem 1.25rem;
    border-radius: 8px;
    border: none;
    font-weight: bold;
    cursor: pointer;
}

.modal-buttons .cancel-button {
    background: #6c757d;
    color: #fff;
}

.modal-buttons .cancel-button:hover {
    background: #5a6268;
}

.modal-buttons .delete-button {
    background: #f44336;
    color: #fff;
}

.modal-buttons .delete-button:hover {
    background: #d32f2f;
}

.modal-buttons button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Responsive */
@media (max-width: 600px) {
    .half-width {
        width: 100%;
    }

    .form-row {
        flex-direction: column;
    }
}
</style>

