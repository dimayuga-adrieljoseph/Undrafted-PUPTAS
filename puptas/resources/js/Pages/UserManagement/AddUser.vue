<template>
    <Head title="Add User" />
    <AppLayout>
        <div class="add-user-root">

            <!-- Access Denied -->
            <div v-if="!isSuperAdmin" class="card" style="max-width:600px;margin:4rem auto;">
                <div class="card-header">
                    <div class="card-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    </div>
                    <div>
                        <h2 class="card-title">Access Denied</h2>
                        <p class="card-subtitle">You do not have permission to create users.</p>
                    </div>
                </div>
                <div style="padding:1rem 1.5rem 1.5rem;">
                    <Link :href="route('users.index')" class="btn btn--primary">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                        Back to User Management
                    </Link>
                </div>
            </div>

            <template v-else>
                <!-- Breadcrumb -->
                <nav class="breadcrumb">
                    <Link :href="route('users.index')" class="breadcrumb-link">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                        Manage Users
                    </Link>
                    <span class="breadcrumb-sep">/</span>
                    <span class="breadcrumb-current">Add User</span>
                </nav>

                <!-- Form card -->
                <div class="form-wrap">
                    <div class="card">
                        <!-- Card header -->
                        <div class="card-header">
                            <div class="card-icon">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                            </div>
                            <div>
                                <h2 class="card-title">Add New User</h2>
                                <p class="card-subtitle">Create a user account and assign a role &amp; permissions</p>
                            </div>
                            <Link :href="route('users.index')" class="btn btn--ghost" style="margin-left:auto;">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                                Back
                            </Link>
                        </div>

                        <form @submit.prevent="submitForm" class="form-body" novalidate>

                            <!-- Server-side error banner -->
                            <div v-if="hasAnyError && submitted" class="error-banner">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                                <div>
                                    <p class="error-banner-title">Please fix the following errors:</p>
                                    <ul class="error-list">
                                        <li v-for="(err, key) in errors" :key="key" v-if="err">{{ err }}</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Personal Information -->
                            <fieldset class="fieldset">
                                <legend class="fieldset-legend">Personal Information</legend>
                                <div class="field-grid">
                                    <div class="field">
                                        <label for="firstname" class="field-label">First Name <span class="req">*</span></label>
                                        <input
                                            id="firstname"
                                            v-model="form.firstname"
                                            @blur="validateField('firstname')"
                                            type="text"
                                            :class="['field-input', errors.firstname ? 'field-input--error' : '']"
                                            placeholder="Juan"
                                        />
                                        <p v-if="errors.firstname" class="field-error">{{ errors.firstname }}</p>
                                    </div>

                                    <div class="field">
                                        <label for="lastname" class="field-label">Last Name <span class="req">*</span></label>
                                        <input
                                            id="lastname"
                                            v-model="form.lastname"
                                            @blur="validateField('lastname')"
                                            type="text"
                                            :class="['field-input', errors.lastname ? 'field-input--error' : '']"
                                            placeholder="Dela Cruz"
                                        />
                                        <p v-if="errors.lastname" class="field-error">{{ errors.lastname }}</p>
                                    </div>

                                    <div class="field">
                                        <label for="middlename" class="field-label">Middle Name</label>
                                        <input
                                            id="middlename"
                                            v-model="form.middlename"
                                            type="text"
                                            class="field-input"
                                            placeholder="Santos"
                                        />
                                    </div>

                                    <div class="field">
                                        <label for="extension_name" class="field-label">Extension Name</label>
                                        <select id="extension_name" v-model="form.extension_name" class="field-input">
                                            <option value="">None</option>
                                            <option value="Jr.">Jr.</option>
                                            <option value="Sr.">Sr.</option>
                                            <option value="II">II</option>
                                            <option value="III">III</option>
                                            <option value="IV">IV</option>
                                            <option value="V">V</option>
                                        </select>
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Account Details -->
                            <fieldset class="fieldset">
                                <legend class="fieldset-legend">Account Details</legend>
                                <div class="field-grid">
                                    <div class="field">
                                        <label for="email" class="field-label">Email Address <span class="req">*</span></label>
                                        <input
                                            id="email"
                                            v-model="form.email"
                                            @blur="checkEmailUnique"
                                            @input="errors.email = ''"
                                            type="email"
                                            :class="['field-input', errors.email ? 'field-input--error' : '']"
                                            placeholder="user@example.com"
                                        />
                                        <p v-if="isCheckingEmail" class="field-hint">Checking email availability…</p>
                                        <p v-else-if="errors.email" class="field-error">{{ errors.email }}</p>
                                    </div>

                                    <div class="field">
                                        <label for="role_id" class="field-label">User Role <span class="req">*</span></label>
                                        <select
                                            id="role_id"
                                            v-model="form.role_id"
                                            @change="onRoleChange"
                                            :class="['field-input', errors.role_id ? 'field-input--error' : '']"
                                        >
                                            <option value="" disabled>Select a role</option>
                                            <option value="1">Applicant</option>
                                            <option value="2">Admin</option>
                                            <option value="3">Evaluator</option>
                                            <option value="8">Grade Evaluator</option>
                                            <option value="4">Interviewer</option>
                                            <option value="5">Medical Staff</option>
                                            <option value="6">Registrar</option>
                                        </select>
                                        <p class="field-hint">Determines user permissions and access</p>
                                        <p v-if="errors.role_id" class="field-error">{{ errors.role_id }}</p>
                                    </div>
                                </div>
                            </fieldset>

                            <!-- Role-specific Assignment -->
                            <fieldset v-if="showProgramAssignment || showApplicantProgram" class="fieldset">
                                <legend class="fieldset-legend">Program Assignment</legend>
                                <div class="field-grid">

                                    <!-- Multi-select for Evaluator / Interviewer / Grade Evaluator -->
                                    <div v-if="showProgramAssignment" class="field">
                                        <label for="program" class="field-label">Program Assignment <span class="req">*</span></label>
                                        <select
                                            id="program"
                                            v-model="form.program"
                                            multiple
                                            size="4"
                                            :class="['field-input field-input--multi', errors.program ? 'field-input--error' : '']"
                                        >
                                            <option v-for="program in programs" :key="program.code" :value="program.code">
                                                {{ program.name }} ({{ program.code }})
                                            </option>
                                        </select>
                                        <p class="field-hint">Hold Ctrl / Cmd to select multiple programs.</p>
                                        <p v-if="errors.program" class="field-error">{{ errors.program }}</p>
                                    </div>

                                    <!-- Single-select for Applicant -->
                                    <div v-if="showApplicantProgram" class="field">
                                        <label for="applicant_program" class="field-label">1st Program Choice <span class="req">*</span></label>
                                        <select
                                            id="applicant_program"
                                            v-model="form.applicant_program"
                                            :class="['field-input', errors.applicant_program ? 'field-input--error' : '']"
                                        >
                                            <option value="" disabled>Select program to apply for</option>
                                            <option v-for="program in programs" :key="program.code" :value="program.code">
                                                {{ program.name }}
                                            </option>
                                        </select>
                                        <p v-if="errors.applicant_program" class="field-error">{{ errors.applicant_program }}</p>
                                    </div>

                                </div>
                            </fieldset>

                            <!-- Form Actions -->
                            <div class="form-actions">
                                <Link :href="route('users.index')" class="btn btn--ghost">Cancel</Link>
                                <button
                                    type="submit"
                                    class="btn btn--primary"
                                    :disabled="isSubmitting || isCheckingEmail"
                                >
                                    <span v-if="isSubmitting" class="spinner"></span>
                                    <svg v-else viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                                    {{ isSubmitting ? 'Creating User…' : 'Create User' }}
                                </button>
                            </div>

                        </form>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    programs:         Array,
    totalUsers:       Number,
    userCountsByRole: Object,
    roles:            Object,
    currentUserRoleId: Number,
});

const isSuperAdmin = computed(() => props.currentUserRoleId === 7);

const form = ref({
    firstname:        '',
    lastname:         '',
    middlename:       '',
    extension_name:   '',
    email:            '',
    role_id:          '',
    program:          [],
    applicant_program: '',
});

const errors      = ref({});
const isSubmitting  = ref(false);
const isCheckingEmail = ref(false);
const submitted   = ref(false);

const hasAnyError = computed(() => Object.values(errors.value).some(e => e !== ''));

const showProgramAssignment = computed(() => ['3', '4', '8'].includes(form.value.role_id));
const showApplicantProgram  = computed(() => form.value.role_id === '1');

const onRoleChange = () => {
    if (!showProgramAssignment.value) form.value.program = [];
    if (!showApplicantProgram.value)  form.value.applicant_program = '';
};

const validateField = (fieldName) => {
    if (fieldName !== 'email') errors.value[fieldName] = '';
};

const checkEmailUnique = async () => {
    if (!form.value.email) { errors.value.email = ''; return; }
    isCheckingEmail.value = true;
    try {
        const response = await axios.post('/check-email', { email: form.value.email });
        errors.value.email = response.data.taken ? 'This email has already been taken.' : '';
    } catch {
        // fall through to server-side validation
    } finally {
        isCheckingEmail.value = false;
    }
};

const submitForm = async () => {
    submitted.value = true;
    Object.keys(form.value).forEach(key => validateField(key));

    if (form.value.email && !errors.value.email) {
        await checkEmailUnique();
    }

    if (hasAnyError.value) return;

    isSubmitting.value = true;

    router.post(route('users.store'), form.value, {
        onError: (serverErrors) => {
            errors.value = serverErrors;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};
</script>

<style scoped>
/* ── Root ─────────────────────────────────────────────────── */
.add-user-root {
    min-height: 100vh;
    background: #f4f5f7;
    padding: 1.25rem 1rem 3rem;
    font-family: 'DM Sans', 'Segoe UI', system-ui, sans-serif;
}
@media (min-width: 768px) {
    .add-user-root { padding: 1.75rem 1.5rem 3rem; }
}

/* ── Breadcrumb ───────────────────────────────────────────── */
.breadcrumb {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-size: .8rem;
    color: #6b7280;
    margin-bottom: 1.25rem;
}
.breadcrumb-link {
    display: flex;
    align-items: center;
    gap: .3rem;
    color: #6b7280;
    text-decoration: none;
    transition: color .15s;
}
.breadcrumb-link:hover { color: #9e122c; }
.breadcrumb-link svg { width: 14px; height: 14px; }
.breadcrumb-sep   { color: #d1d5db; }
.breadcrumb-current { color: #111827; font-weight: 600; }

/* ── Form container ───────────────────────────────────────── */
.form-wrap {
    max-width: 780px;
    margin: 0 auto;
}

/* ── Card ─────────────────────────────────────────────────── */
.card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
    overflow: hidden;
}
.card-header {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #f3f4f6;
}
.card-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #fdf2f4;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.card-icon svg { width: 16px; height: 16px; fill: #9e122c; }
.card-title    { font-size: .9rem; font-weight: 700; color: #111827; margin: 0; }
.card-subtitle { font-size: .73rem; color: #6b7280; margin: 2px 0 0; }

/* ── Form body ────────────────────────────────────────────── */
.form-body {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* ── Fieldset ─────────────────────────────────────────────── */
.fieldset { border: none; padding: 0; margin: 0; }
.fieldset-legend {
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: #9ca3af;
    margin-bottom: .75rem;
    display: block;
}

/* ── Field grid ───────────────────────────────────────────── */
.field-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: .875rem;
}
@media (min-width: 640px) {
    .field-grid { grid-template-columns: 1fr 1fr; }
}
.field {
    display: flex;
    flex-direction: column;
    gap: .3rem;
}
.field-label {
    font-size: .8rem;
    font-weight: 600;
    color: #374151;
}
.req { color: #9e122c; }

/* ── Field inputs ─────────────────────────────────────────── */
.field-input {
    width: 100%;
    padding: .55rem .75rem;
    border-radius: 10px;
    border: 1.5px solid #e5e7eb;
    background: #f9fafb;
    font-size: .83rem;
    color: #111827;
    transition: border-color .15s, box-shadow .15s;
    outline: none;
    appearance: none;
}
.field-input:focus {
    border-color: #9e122c;
    box-shadow: 0 0 0 3px rgba(158,18,44,.08);
    background: #fff;
}
.field-input--error {
    border-color: #f87171;
    background: #fff5f5;
}
.field-input--multi { height: auto; }
.field-error { font-size: .72rem; color: #dc2626; }
.field-hint  { font-size: .72rem; color: #9ca3af; }

/* ── Error banner ─────────────────────────────────────────── */
.error-banner {
    display: flex;
    gap: .75rem;
    padding: .9rem 1rem;
    background: #fff5f5;
    border: 1px solid #fecaca;
    border-radius: 10px;
}
.error-banner svg { width: 18px; height: 18px; fill: #dc2626; flex-shrink: 0; margin-top: 1px; }
.error-banner-title { font-size: .8rem; font-weight: 700; color: #dc2626; margin-bottom: .3rem; }
.error-list { font-size: .78rem; color: #b91c1c; padding-left: 1.1rem; margin: 0; }

/* ── Form actions ─────────────────────────────────────────── */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: .75rem;
    padding-top: .5rem;
    border-top: 1px solid #f3f4f6;
}

/* ── Buttons ──────────────────────────────────────────────── */
.btn {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    padding: .55rem 1.1rem;
    border-radius: 10px;
    font-size: .83rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s;
    border: none;
    text-decoration: none;
}
.btn svg { width: 15px; height: 15px; }
.btn--ghost {
    background: transparent;
    border: 1.5px solid #e5e7eb;
    color: #374151;
}
.btn--ghost:hover { background: #f9fafb; }
.btn--primary {
    background: linear-gradient(135deg, #9e122c, #c81e3d);
    color: #fff;
    box-shadow: 0 2px 8px rgba(158,18,44,.3);
}
.btn--primary:hover:not(:disabled) {
    box-shadow: 0 4px 14px rgba(158,18,44,.4);
    transform: translateY(-1px);
}
.btn--primary:disabled { opacity: .6; transform: none; cursor: not-allowed; }

/* ── Spinner ──────────────────────────────────────────────── */
.spinner {
    width: 14px;
    height: 14px;
    border: 2px solid rgba(255,255,255,.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin .6s linear infinite;
    flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Select arrow ─────────────────────────────────────────── */
select.field-input {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right .75rem center;
    background-size: 1rem;
    padding-right: 2.5rem;
}
/* Multi-select should not show the chevron arrow */
select[multiple].field-input {
    background-image: none;
    padding-right: .75rem;
}

@media (max-width: 480px) {
    .form-actions { flex-direction: column; }
    .btn { width: 100%; justify-content: center; }
}
</style>
