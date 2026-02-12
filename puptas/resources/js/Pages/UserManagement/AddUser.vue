<template>
  <Head title="Add User" />
  <AppLayout>
    <div class="page-container">
      <!-- Header Section -->
      <div class="header-section">
        <div class="header-content">
          <div class="header-left">
            <div class="breadcrumb">
              <Link :href="route('users.index')" class="breadcrumb-link">
                <svg class="breadcrumb-icon" viewBox="0 0 24 24">
                  <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                </svg>
                Manage Users
              </Link>
              <span class="breadcrumb-separator">/</span>
              <span class="breadcrumb-current">Add User</span>
            </div>
            <h1 class="page-title">
              <span class="title-icon">
                <svg class="icon" viewBox="0 0 24 24">
                  <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                </svg>
              </span>
              Add New User
            </h1>
            <p class="page-subtitle">Create new user accounts and assign roles & permissions</p>
          </div>
          
          <div class="header-actions">
            <Link 
              :href="route('users.index')" 
              class="back-btn"
            >
              <svg viewBox="0 0 24 24">
                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
              </svg>
              Back to Users
            </Link>
          </div>
        </div>

        <!-- User Stats Cards -->
        <div class="stats-grid">
          <div class="stat-card total">
            <div class="stat-icon">
              <svg viewBox="0 0 24 24">
                <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
              </svg>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ totalUsers }}</div>
              <div class="stat-label">Total Users</div>
            </div>
          </div>

          <div class="stat-card role-card" v-for="(count, roleId) in userCountsByRole" :key="roleId">
            <div class="stat-icon">
              <svg :class="`role-icon-${roleId}`" viewBox="0 0 24 24">
                <!-- Applicant Icon -->
                <path v-if="roleId == 1" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM7.07 18.28c.43-.9 3.05-1.78 4.93-1.78s4.51.88 4.93 1.78C15.57 19.36 13.86 20 12 20s-3.57-.64-4.93-1.72zm11.29-1.45c-1.43-1.74-4.9-2.33-6.36-2.33s-4.93.59-6.36 2.33C4.62 15.49 4 13.82 4 12c0-4.41 3.59-8 8-8s8 3.59 8 8c0 1.82-.62 3.49-1.64 4.83zM12 6c-1.94 0-3.5 1.56-3.5 3.5S10.06 13 12 13s3.5-1.56 3.5-3.5S13.94 6 12 6zm0 5c-.83 0-1.5-.67-1.5-1.5S11.17 8 12 8s1.5.67 1.5 1.5S12.83 11 12 11z"/>
                
                <!-- Admin Icon -->
                <path v-if="roleId == 2" d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm0 4c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm6 12H6v-1.4c0-2 4-3.1 6-3.1s6 1.1 6 3.1V19z"/>
                
                <!-- Evaluator Icon -->
                <path v-if="roleId == 3" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                
                <!-- Interviewer Icon -->
                <path v-if="roleId == 4" d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/>
                
                <!-- Medical Staff Icon -->
                <g v-if="roleId == 5">
                  <path d="M20 8l-8 5-8-5v10h16zm0-2H4l8 4.99z" opacity=".3"/>
                  <path d="M4 20h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2zM20 6l-8 4.99L4 6h16zM4 8l8 5 8-5v10H4V8z"/>
                </g>
                
                <!-- Registrar Icon (default) -->
                <path v-if="roleId == 6" d="M14 6v15H3v-2h2V3h9v1h5v15h2v2h-4V6h-3zm-4 5v2h2v-2h-2z"/>
              </svg>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ count }}</div>
              <div class="stat-label">{{ roles[roleId] || `Role ${roleId}` }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Form Card -->
      <div class="form-container">
        <div class="form-card">
          <!-- Form Header -->
          <div class="form-header">
            <div class="form-header-icon">
              <svg viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM7.07 18.28c.43-.9 3.05-1.78 4.93-1.78s4.51.88 4.93 1.78C15.57 19.36 13.86 20 12 20s-3.57-.64-4.93-1.72zm11.29-1.45c-1.43-1.74-4.9-2.33-6.36-2.33s-4.93.59-6.36 2.33C4.62 15.49 4 13.82 4 12c0-4.41 3.59-8 8-8s8 3.59 8 8c0 1.82-.62 3.49-1.64 4.83zM12 6c-1.94 0-3.5 1.56-3.5 3.5S10.06 13 12 13s3.5-1.56 3.5-3.5S13.94 6 12 6zm0 5c-.83 0-1.5-.67-1.5-1.5S11.17 8 12 8s1.5.67 1.5 1.5S12.83 11 12 11z"/>
              </svg>
            </div>
            <div>
              <h2 class="form-title">User Information</h2>
              <p class="form-subtitle">Fill in the details to create a new user account</p>
            </div>
          </div>

          <form @submit.prevent="submitForm" class="form-content">
            <!-- Name Section -->
            <div class="form-section">
              <h3 class="section-title">Personal Information</h3>
              
              <div class="form-grid">
                <div class="form-group">
                  <label for="firstname" class="form-label">
                    First Name
                    <span class="required">*</span>
                  </label>
                  <input 
                    id="firstname" 
                    v-model="form.firstname" 
                    @blur="validateField('firstname')"
                    :class="['form-input', { 'error': errors.firstname }]"
                    type="text" 
                    required 
                    placeholder="Enter first name"
                  />
                  <div v-if="errors.firstname" class="form-error">
                    <svg class="error-icon" viewBox="0 0 24 24">
                      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    {{ errors.firstname }}
                  </div>
                </div>

                <div class="form-group">
                  <label for="lastname" class="form-label">
                    Last Name
                    <span class="required">*</span>
                  </label>
                  <input 
                    id="lastname" 
                    v-model="form.lastname" 
                    @blur="validateField('lastname')"
                    :class="['form-input', { 'error': errors.lastname }]"
                    type="text" 
                    required 
                    placeholder="Enter last name"
                  />
                  <div v-if="errors.lastname" class="form-error">
                    <svg class="error-icon" viewBox="0 0 24 24">
                      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    {{ errors.lastname }}
                  </div>
                </div>

                <div class="form-group">
                  <label for="middlename" class="form-label">Middle Name</label>
                  <input 
                    id="middlename" 
                    v-model="form.middlename" 
                    class="form-input" 
                    type="text" 
                    placeholder="Enter middle name"
                  />
                </div>

                <div class="form-group">
                  <label for="extension_name" class="form-label">Extension Name</label>
                  <select 
                    id="extension_name" 
                    v-model="form.extension_name" 
                    class="form-input"
                  >
                    <option value="">None</option>
                    <option value="Jr.">Jr.</option>
                    <option value="Sr.">Sr.</option>
                    <option value="II">II</option>
                    <option value="III">III</option>
                    <option value="IV">IV</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Contact Section -->
            <div class="form-section">
              <h3 class="section-title">Contact Information</h3>
              
              <div class="form-grid">
                <div class="form-group">
                  <label for="email" class="form-label">
                    Email Address
                    <span class="required">*</span>
                  </label>
                  <div class="input-with-hint">
                    <input 
                      id="email" 
                      v-model="form.email" 
                      @blur="validateField('email')"
                      :class="['form-input', { 'error': errors.email }]"
                      type="email" 
                      required 
                      placeholder="user@gmail.com"
                    />
                    <div class="input-hint">Must be a Gmail address</div>
                  </div>
                  <div v-if="errors.email" class="form-error">
                    <svg class="error-icon" viewBox="0 0 24 24">
                      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    {{ errors.email }}
                  </div>
                </div>

                <div class="form-group">
                  <label for="contactnumber" class="form-label">
                    Contact Number
                    <span class="required">*</span>
                  </label>
                  <div class="phone-input">
                    <div class="country-code">+63</div>
                    <input 
                      id="contactnumber" 
                      v-model="form.contactnumber" 
                      @blur="validateField('contactnumber')"
                      :class="['form-input', { 'error': errors.contactnumber }]"
                      type="text" 
                      required 
                      placeholder="912 345 6789"
                      maxlength="10"
                    />
                  </div>
                  <div v-if="errors.contactnumber" class="form-error">
                    <svg class="error-icon" viewBox="0 0 24 24">
                      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    {{ errors.contactnumber }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Security Section -->
            <div class="form-section">
              <h3 class="section-title">Security</h3>
              
              <div class="form-grid">
                <div class="form-group">
                  <label for="password" class="form-label">
                    Password
                    <span class="required">*</span>
                  </label>
                  <div class="password-input">
                    <input 
                      id="password" 
                      v-model="form.password" 
                      @blur="validateField('password')"
                      :class="['form-input', { 'error': errors.password }]"
                      :type="showPassword ? 'text' : 'password'"
                      required 
                      placeholder="Create a strong password"
                    />
                    <button 
                      type="button" 
                      class="password-toggle"
                      @click="showPassword = !showPassword"
                    >
                      <svg v-if="showPassword" viewBox="0 0 24 24">
                        <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>
                      </svg>
                      <svg v-else viewBox="0 0 24 24">
                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                      </svg>
                    </button>
                  </div>
                  <div v-if="errors.password" class="form-error">
                    <svg class="error-icon" viewBox="0 0 24 24">
                      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    {{ errors.password }}
                  </div>
                  <div class="password-requirements">
                    <div :class="['requirement', { 'met': /[A-Z]/.test(form.password) }]">
                      <svg viewBox="0 0 24 24">
                        <path v-if="/[A-Z]/.test(form.password)" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        <circle v-else cx="12" cy="12" r="10"/>
                      </svg>
                      Uppercase letter
                    </div>
                    <div :class="['requirement', { 'met': /[a-z]/.test(form.password) }]">
                      <svg viewBox="0 0 24 24">
                        <path v-if="/[a-z]/.test(form.password)" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        <circle v-else cx="12" cy="12" r="10"/>
                      </svg>
                      Lowercase letter
                    </div>
                    <div :class="['requirement', { 'met': /\d/.test(form.password) }]">
                      <svg viewBox="0 0 24 24">
                        <path v-if="/\d/.test(form.password)" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        <circle v-else cx="12" cy="12" r="10"/>
                      </svg>
                      Number
                    </div>
                    <div :class="['requirement', { 'met': /[@$!%*?&.]/.test(form.password) }]">
                      <svg viewBox="0 0 24 24">
                        <path v-if="/[@$!%*?&.]/.test(form.password)" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        <circle v-else cx="12" cy="12" r="10"/>
                      </svg>
                      Special character
                    </div>
                    <div :class="['requirement', { 'met': form.password.length >= 8 }]">
                      <svg viewBox="0 0 24 24">
                        <path v-if="form.password.length >= 8" d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        <circle v-else cx="12" cy="12" r="10"/>
                      </svg>
                      8+ characters
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <label for="password_confirmation" class="form-label">
                    Confirm Password
                    <span class="required">*</span>
                  </label>
                  <input 
                    id="password_confirmation" 
                    v-model="form.password_confirmation" 
                    @blur="validateField('password_confirmation')"
                    :class="['form-input', { 'error': errors.password_confirmation }]"
                    :type="showConfirmPassword ? 'text' : 'password'"
                    required 
                    placeholder="Confirm your password"
                  />
                  <div v-if="errors.password_confirmation" class="form-error">
                    <svg class="error-icon" viewBox="0 0 24 24">
                      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    {{ errors.password_confirmation }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Role & Program Section -->
            <div class="form-section">
              <h3 class="section-title">Role & Assignment</h3>
              
              <div class="form-grid">
                <div class="form-group">
                  <label for="role_id" class="form-label">
                    User Role
                    <span class="required">*</span>
                  </label>
                  <div class="role-select">
                    <select 
                      id="role_id" 
                      v-model="form.role_id" 
                      @change="onRoleChange"
                      :class="['form-input', { 'error': errors.role_id }]"
                      required
                    >
                      <option value="" disabled>Select a role</option>
                      <option value="1">Applicant</option>
                      <option value="2">Admin</option>
                      <option value="3">Evaluator</option>
                      <option value="4">Interviewer</option>
                      <option value="5">Medical Staff</option>
                      <option value="6">Registrar</option>
                    </select>
                    <div class="role-hint">Determines user permissions and access</div>
                  </div>
                  <div v-if="errors.role_id" class="form-error">
                    <svg class="error-icon" viewBox="0 0 24 24">
                      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    {{ errors.role_id }}
                  </div>
                </div>

                <!-- Program Assignment for Evaluators and Interviewers -->
                <div v-if="showProgramAssignment" class="form-group">
                  <label for="program" class="form-label">
                    Program Assignment
                    <span class="required">*</span>
                  </label>
                  <div class="program-select">
                    <select 
                      id="program" 
                      v-model="form.program" 
                      :class="['form-input', { 'error': errors.program }]"
                      :required="showProgramAssignment"
                    >
                      <option value="" disabled>Select assigned program</option>
                      <option v-for="program in programs" :key="program.code" :value="program.code">
                        {{ program.name }} ({{ program.code }})
                      </option>
                    </select>
                    <div class="program-hint">
                      Evaluators and Interviewers must be assigned to a program
                    </div>
                  </div>
                  <div v-if="errors.program" class="form-error">
                    <svg class="error-icon" viewBox="0 0 24 24">
                      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    {{ errors.program }}
                  </div>
                </div>

                <!-- Program Assignment for Applicants -->
                <div v-if="showApplicantProgram" class="form-group">
                  <label for="applicant_program" class="form-label">
                    Program Selection
                    <span class="required">*</span>
                  </label>
                  <select 
                    id="applicant_program" 
                    v-model="form.applicant_program" 
                    :class="['form-input', { 'error': errors.applicant_program }]"
                    :required="showApplicantProgram"
                  >
                    <option value="" disabled>Select program to apply for</option>
                    <option v-for="program in programs" :key="program.code" :value="program.code">
                      {{ program.name }}
                    </option>
                  </select>
                  <div v-if="errors.applicant_program" class="form-error">
                    <svg class="error-icon" viewBox="0 0 24 24">
                      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    {{ errors.applicant_program }}
                  </div>
                </div>
              </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
              <Link 
                :href="route('users.index')" 
                class="cancel-btn"
              >
                Cancel
              </Link>
              <button 
                type="submit" 
                class="submit-btn"
                :disabled="isSubmitting"
              >
                <span v-if="isSubmitting" class="spinner"></span>
                <svg v-else class="submit-icon" viewBox="0 0 24 24">
                  <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                </svg>
                {{ isSubmitting ? 'Creating User...' : 'Create User' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  programs: Array,
  totalUsers: Number,
  userCountsByRole: Object,
  roles: Object,
});

const form = ref({
  firstname: '',
  lastname: '',
  middlename: '',
  extension_name: '',
  email: '',
  contactnumber: '',
  password: '',
  password_confirmation: '',
  role_id: '',
  program: '',
  applicant_program: '',
});

const errors = ref({});
const showPassword = ref(false);
const showConfirmPassword = ref(false);
const isSubmitting = ref(false);

const showProgramAssignment = computed(() => {
  return ['3', '4'].includes(form.value.role_id); // Evaluator (3) and Interviewer (4)
});

const showApplicantProgram = computed(() => {
  return form.value.role_id === '1'; // Applicant
});

const onRoleChange = () => {
  // Clear program selections when role changes
  if (!showProgramAssignment.value) {
    form.value.program = '';
  }
  if (!showApplicantProgram.value) {
    form.value.applicant_program = '';
  }
};

const validateField = (fieldName) => {
  errors.value[fieldName] = '';

  if (fieldName === 'email' && form.value.email) {
    const emailPattern = /^[a-z0-9._%+\-]+@gmail\.com$/;
    if (!emailPattern.test(form.value.email)) {
      errors.value.email = 'Must be a valid Gmail address (e.g., user@gmail.com)';
    }
  }

  if (fieldName === 'contactnumber' && form.value.contactnumber) {
    if (!/^\d{10}$/.test(form.value.contactnumber)) {
      errors.value.contactnumber = 'Must be exactly 10 digits';
    }
  }

  if (fieldName === 'password' && form.value.password) {
    const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]{8,}$/;
    if (!passwordPattern.test(form.value.password)) {
      errors.value.password = 'Password must meet all requirements';
    }
  }

  if (fieldName === 'password_confirmation' && form.value.password_confirmation) {
    if (form.value.password !== form.value.password_confirmation) {
      errors.value.password_confirmation = 'Passwords do not match';
    }
  }
};

const submitForm = async () => {
  // Validate all fields
  Object.keys(form.value).forEach(key => validateField(key));

  // Check for errors
  const hasErrors = Object.values(errors.value).some(error => error !== '');
  if (hasErrors) return;

  isSubmitting.value = true;

  try {
    await router.post(route('add_user.store'), form.value, {
      onSuccess: () => {
        // Reset form
        Object.keys(form.value).forEach(key => {
          form.value[key] = '';
        });
        isSubmitting.value = false;
        
        // Show success message (you could add a toast notification here)
        alert('User created successfully!');
      },
      onError: (serverErrors) => {
        errors.value = serverErrors;
        isSubmitting.value = false;
      }
    });
  } catch (error) {
    isSubmitting.value = false;
    console.error('Error submitting form:', error);
  }
};
</script>

<style scoped>
.page-container {
  min-height: 100vh;
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  padding: 1.5rem;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* Header Section */
.header-section {
  margin-bottom: 2rem;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
}

.header-left {
  flex: 1;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
  font-size: 0.875rem;
  color: #64748b;
}

.breadcrumb-link {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  color: #64748b;
  text-decoration: none;
  transition: color 0.2s;
}

.breadcrumb-link:hover {
  color: #9e122c;
}

.breadcrumb-icon {
  width: 16px;
  height: 16px;
  fill: currentColor;
}

.breadcrumb-separator {
  color: #cbd5e1;
}

.breadcrumb-current {
  color: #1a202c;
  font-weight: 500;
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: #1a202c;
  margin: 0 0 0.5rem 0;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.title-icon .icon {
  width: 32px;
  height: 32px;
  fill: #9e122c;
}

.page-subtitle {
  color: #64748b;
  font-size: 0.95rem;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 1rem;
}

.back-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.25rem;
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  color: #64748b;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.3s ease;
}

.back-btn:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.back-btn svg {
  width: 20px;
  height: 20px;
  fill: currentColor;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.stat-card {
  background: white;
  border-radius: 16px;
  padding: 1.25rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  transition: all 0.3s ease;
  border: 1px solid #e2e8f0;
}

.stat-card.total {
  background: linear-gradient(135deg, #9e122c 0%, #c81e3d 100%);
  color: white;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.stat-card.total:hover {
  box-shadow: 0 4px 12px rgba(158, 18, 44, 0.3);
}

.stat-icon svg {
  width: 32px;
  height: 32px;
}

.stat-card.total .stat-icon svg {
  fill: white;
}

.role-card .stat-icon svg {
  fill: #9e122c;
}

.stat-content {
  flex: 1;
}

.stat-value {
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1;
  margin-bottom: 0.25rem;
}

.stat-label {
  font-size: 0.875rem;
  opacity: 0.9;
  font-weight: 500;
}

.stat-card.total .stat-label {
  opacity: 0.95;
}

/* Form Container */
.form-container {
  max-width: 1000px;
  margin: 0 auto;
}

.form-card {
  background: white;
  border-radius: 20px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
  overflow: hidden;
  border: 1px solid #e2e8f0;
}

.form-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 2rem 2rem 1.5rem;
  border-bottom: 1px solid #f1f5f9;
}

.form-header-icon svg {
  width: 48px;
  height: 48px;
  fill: #9e122c;
}

.form-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1a202c;
  margin: 0 0 0.25rem 0;
}

.form-subtitle {
  color: #64748b;
  font-size: 0.95rem;
  margin: 0;
}

/* Form Content */
.form-content {
  padding: 2rem;
}

.form-section {
  margin-bottom: 2.5rem;
}

.section-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1a202c;
  margin: 0 0 1.5rem 0;
  padding-bottom: 0.75rem;
  border-bottom: 2px solid #f1f5f9;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.5rem;
}

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
}

.form-group {
  margin-bottom: 1rem;
}

.form-label {
  display: block;
  font-weight: 500;
  color: #374151;
  margin-bottom: 0.5rem;
  font-size: 0.95rem;
}

.required {
  color: #ef4444;
}

.form-input {
  width: 100%;
  padding: 0.875rem 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  background: #f8fafc;
  color: #1a202c;
}

.form-input:focus {
  outline: none;
  border-color: #9e122c;
  background: white;
  box-shadow: 0 0 0 3px rgba(158, 18, 44, 0.1);
}

.form-input.error {
  border-color: #ef4444;
  background: #fef2f2;
}

.form-input.error:focus {
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

/* Input Groups */
.input-with-hint {
  position: relative;
}

.input-hint {
  font-size: 0.75rem;
  color: #64748b;
  margin-top: 0.375rem;
}

.phone-input {
  display: flex;
}

.country-code {
  padding: 0.875rem 0.75rem;
  background: #e2e8f0;
  border: 2px solid #e2e8f0;
  border-right: none;
  border-radius: 12px 0 0 12px;
  font-weight: 500;
  color: #64748b;
}

.phone-input .form-input {
  border-radius: 0 12px 12px 0;
  flex: 1;
}

.password-input {
  position: relative;
}

.password-toggle {
  position: absolute;
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: #64748b;
  cursor: pointer;
  padding: 0.25rem;
}

.password-toggle svg {
  width: 20px;
  height: 20px;
  fill: currentColor;
}

.password-toggle:hover {
  color: #4a5568;
}

/* Password Requirements */
.password-requirements {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.5rem;
  margin-top: 0.75rem;
}

@media (max-width: 480px) {
  .password-requirements {
    grid-template-columns: 1fr;
  }
}

.requirement {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.75rem;
  color: #64748b;
}

.requirement.met {
  color: #10b981;
}

.requirement svg {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}

.requirement.met svg {
  fill: #10b981;
}

.requirement:not(.met) svg {
  fill: #cbd5e1;
}

/* Error Messages */
.form-error {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #ef4444;
  font-size: 0.875rem;
  margin-top: 0.375rem;
}

.error-icon {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  fill: #ef4444;
}

/* Role & Program Select */
.role-select,
.program-select {
  position: relative;
}

.role-hint,
.program-hint {
  font-size: 0.75rem;
  color: #64748b;
  margin-top: 0.375rem;
}

/* Form Actions */
.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding-top: 2rem;
  border-top: 1px solid #f1f5f9;
  margin-top: 2rem;
}

.cancel-btn {
  padding: 0.875rem 1.75rem;
  background: white;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  color: #64748b;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.3s ease;
}

.cancel-btn:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.submit-btn {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 2rem;
  background: linear-gradient(135deg, #9e122c 0%, #c81e3d 100%);
  border: none;
  border-radius: 12px;
  color: white;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(158, 18, 44, 0.2);
}

.submit-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(158, 18, 44, 0.3);
}

.submit-btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.submit-icon {
  width: 20px;
  height: 20px;
  fill: currentColor;
}

.spinner {
  width: 20px;
  height: 20px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top-color: white;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Select Styling */
select.form-input {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 1rem center;
  background-size: 1rem;
  padding-right: 3rem;
}

/* Responsive */
@media (max-width: 1024px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 768px) {
  .page-container {
    padding: 1rem;
  }
  
  .header-content {
    flex-direction: column;
    gap: 1rem;
  }
  
  .form-header {
    padding: 1.5rem;
  }
  
  .form-content {
    padding: 1.5rem;
  }
}

@media (max-width: 640px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .cancel-btn,
  .submit-btn {
    width: 100%;
    justify-content: center;
  }
}
</style>