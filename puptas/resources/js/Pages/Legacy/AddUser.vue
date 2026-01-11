<template>
  <Head title="Add User" />
  <AppLayout>
    <div class="flex flex-col min-h-screen">
      <div class="user-types-section w-full max-w-4xl mx-auto p-8">
        <div class="text-center">
          <h2 class="text-2xl font-bold mb-4 text-maroon">User Types</h2>
          <div class="grid grid-cols-3 gap-4">
            <div class="user-type-info">
              <div class="user-type-icon"><i class="fas fa-users"></i></div>
              <div class="user-type-text">Total Users</div>
              <div class="user-type-count">{{ totalUsers }}</div>
            </div>
            <div class="user-type-info">
              <div class="user-type-icon"><i class="fas fa-user"></i></div>
              <div class="user-type-text">Applicants</div>
              <div class="user-type-count">{{ userCountsByRole[1] || 0 }}</div>
            </div>
            <div class="user-type-info">
              <div class="user-type-icon"><i class="fas fa-tools"></i></div>
              <div class="user-type-text">Admins</div>
              <div class="user-type-count">{{ userCountsByRole[2] || 0 }}</div>
            </div>
            <div class="user-type-info">
              <div class="user-type-icon"><i class="fas fa-check"></i></div>
              <div class="user-type-text">Evaluator</div>
              <div class="user-type-count">{{ userCountsByRole[3] || 0 }}</div>
            </div>
            <div class="user-type-info">
              <div class="user-type-icon"><i class="fas fa-edit"></i></div>
              <div class="user-type-text">Interviewer</div>
              <div class="user-type-count">{{ userCountsByRole[4] || 0 }}</div>
            </div>
            <div class="user-type-info">
              <div class="user-type-icon"><i class="fa-solid fa-suitcase-medical"></i></div>
              <div class="user-type-text">Medical Staff</div>
              <div class="user-type-count">{{ userCountsByRole[5] || 0 }}</div>
            </div>
            <div class="user-type-info">
              <div class="user-type-icon"><i class="fas fa-user"></i></div>
              <div class="user-type-text">Registrar</div>
              <div class="user-type-count">{{ userCountsByRole[6] || 0 }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="form-wrapper">
        <div class="form-box">
          <h2 class="form-title">Add New User</h2>
          <form @submit.prevent="submitForm">
            <div class="form-row">
              <div class="form-group half-width">
                <label for="firstname">First Name <span class="required">*</span></label>
                <input 
                  id="firstname" 
                  class="form-input" 
                  type="text" 
                  v-model="form.firstname" 
                  required 
                  autocomplete="firstname" 
                  @blur="validateField('firstname')"
                  :class="{ 'input-error': errors.firstname }"
                />
                <span class="form-error">{{ errors.firstname }}</span>
              </div>

              <div class="form-group half-width">
                <label for="lastname">Last Name <span class="required">*</span></label>
                <input 
                  id="lastname" 
                  class="form-input" 
                  type="text" 
                  v-model="form.lastname" 
                  required 
                  autocomplete="lastname" 
                  @blur="validateField('lastname')"
                  :class="{ 'input-error': errors.lastname }"
                />
                <span class="form-error">{{ errors.lastname }}</span>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group half-width">
                <label for="middlename">Middle Name</label>
                <input 
                  id="middlename" 
                  class="form-input" 
                  type="text" 
                  v-model="form.middlename" 
                  autocomplete="middlename" 
                />
              </div>

              <div class="form-group half-width">
                <label for="extension_name">Extension Name</label>
                <input 
                  id="extension_name" 
                  class="form-input" 
                  type="text" 
                  v-model="form.extension_name" 
                  autocomplete="extension_name" 
                />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group half-width">
                <label for="email">Email <span class="required">*</span></label>
                <input 
                  id="email" 
                  class="form-input" 
                  type="email" 
                  v-model="form.email" 
                  required 
                  autocomplete="email" 
                  pattern="[a-z0-9._%+\-]+@gmail\.com$" 
                  title="Must be a valid email format like example@gmail.com"
                  @blur="validateField('email')"
                  :class="{ 'input-error': errors.email }"
                />
                <span class="form-error">{{ errors.email }}</span>
              </div>

              <div class="form-group half-width">
                <label for="contactnumber">Contact Number <span class="required">*</span></label>
                <div class="form-input-group">
                  <div class="input-prefix">+63</div>
                  <input 
                    id="contactnumber" 
                    class="form-input" 
                    type="text" 
                    v-model="form.contactnumber" 
                    required 
                    autocomplete="contactnumber"
                    maxlength="10" 
                    pattern="\d{10}" 
                    title="Invalid contact number. Must be exactly 10 digits."
                    @input="validateContactNumber"
                    @blur="validateField('contactnumber')"
                    :class="{ 'input-error': errors.contactnumber }"
                  />
                </div>
                <span class="form-error">{{ errors.contactnumber }}</span>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group half-width">
                <label for="password">Password <span class="required">*</span></label>
                <input 
                  id="password" 
                  class="form-input" 
                  type="password" 
                  v-model="form.password" 
                  required 
                  autocomplete="new-password" 
                  pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]{8,}"
                  @blur="validateField('password')"
                  :class="{ 'input-error': errors.password }"
                />
                <span class="form-error">{{ errors.password }}</span>
              </div>

              <div class="form-group half-width">
                <label for="password_confirmation">Confirm Password <span class="required">*</span></label>
                <input 
                  id="password_confirmation" 
                  class="form-input" 
                  type="password" 
                  v-model="form.password_confirmation" 
                  required 
                  autocomplete="new-password"
                  @blur="validateField('password_confirmation')"
                  :class="{ 'input-error': errors.password_confirmation }"
                />
                <span class="form-error">{{ errors.password_confirmation }}</span>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group full-width">
                <label for="role_id">Select Your User Type <span class="required">*</span></label>
                <select 
                  id="role_id" 
                  v-model="form.role_id" 
                  class="form-select" 
                  required
                  @change="onRoleChange"
                  @blur="validateField('role_id')"
                  :class="{ 'input-error': errors.role_id }"
                >
                  <option value="" disabled selected>---- e.g. Applicant, Admin, Evaluator, Interviewer  ----</option>
                  <option value="1">Applicant</option>
                  <option value="2">Admin</option>
                  <option value="3">Evaluator</option>
                  <option value="4">Interviewer</option>
                  <option value="5">Medical Staff</option>
                  <option value="6">Registrar</option>
                </select>
                <span class="form-error">{{ errors.role_id }}</span>
              </div>    
            </div>

            <div class="form-row" v-show="showProgramField">
              <div class="form-group full-width" id="program-group">
                <label for="program">Program <span class="required">*</span></label>
                <select 
                  id="program" 
                  v-model="form.program" 
                  class="form-select"
                  :required="showProgramField"
                >
                  <option value="" disabled selected>---- Select Program ----</option>
                  <option v-for="program in programs" :key="program.code" :value="program.code">
                    {{ program.name }}
                  </option>
                </select>
              </div>
            </div>

            <div class="form-group mt-4 flex items-center justify-end">
              <button type="submit" class="form-button ms-4" :disabled="submitting">
                <i class="fa-solid fa-user-plus"></i> {{ submitting ? 'Adding...' : 'Add User' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Success Dialog -->
    <div v-if="showSuccessDialog" class="dialog-overlay" @click="closeDialog">
      <div class="dialog-box" @click.stop>
        <h2 class="dialog-title">Success</h2>
        <div class="dialog-content">
          User added successfully.
        </div>
        <footer class="dialog-actions">
          <button type="button" class="dialog-button" @click="closeDialog">
            OK
          </button>
        </footer>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

// Load Font Awesome
onMounted(() => {
  if (!document.querySelector('link[href*="font-awesome"]')) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css';
    document.head.appendChild(link);
  }
});

const props = defineProps({
  userCountsByRole: Object,
  roles: Object,
  totalUsers: Number,
  programs: Array,
});

const form = reactive({
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
});

const errors = reactive({
  firstname: '',
  lastname: '',
  email: '',
  contactnumber: '',
  password: '',
  password_confirmation: '',
  role_id: '',
});

const submitting = ref(false);
const showSuccessDialog = ref(false);

const showProgramField = computed(() => {
  return form.role_id === '1';
});

const validateField = (fieldName) => {
  if (fieldName === 'firstname' && !form.firstname.trim()) {
    errors.firstname = 'This field is required';
  } else if (fieldName === 'firstname') {
    errors.firstname = '';
  }

  if (fieldName === 'lastname' && !form.lastname.trim()) {
    errors.lastname = 'This field is required';
  } else if (fieldName === 'lastname') {
    errors.lastname = '';
  }

  if (fieldName === 'email' && !form.email.trim()) {
    errors.email = 'This field is required';
  } else if (fieldName === 'email') {
    errors.email = '';
  }

  if (fieldName === 'contactnumber') {
    if (!form.contactnumber.trim()) {
      errors.contactnumber = 'This field is required';
    } else if (!/^\d{10}$/.test(form.contactnumber)) {
      errors.contactnumber = 'Invalid contact number. Must be exactly 10 digits.';
    } else {
      errors.contactnumber = '';
    }
  }

  if (fieldName === 'password' && !form.password.trim()) {
    errors.password = 'This field is required';
  } else if (fieldName === 'password') {
    errors.password = '';
  }

  if (fieldName === 'password_confirmation') {
    if (!form.password_confirmation.trim()) {
      errors.password_confirmation = 'This field is required';
    } else if (form.password !== form.password_confirmation) {
      errors.password_confirmation = "Password didn't match.";
    } else {
      errors.password_confirmation = '';
    }
  }

  if (fieldName === 'role_id' && !form.role_id) {
    errors.role_id = 'This field is required';
  } else if (fieldName === 'role_id') {
    errors.role_id = '';
  }
};

const validateContactNumber = () => {
  if (form.contactnumber.length > 10) {
    form.contactnumber = form.contactnumber.slice(0, 10);
  }
  
  if (!/^\d{10}$/.test(form.contactnumber) && form.contactnumber) {
    errors.contactnumber = 'Invalid contact number. Must be exactly 10 digits.';
  } else {
    errors.contactnumber = '';
  }
};

const onRoleChange = () => {
  if (!showProgramField.value) {
    form.program = '';
  }
};

const submitForm = () => {
  // Validate all fields
  Object.keys(errors).forEach(field => validateField(field));
  
  // Check if there are any errors
  const hasErrors = Object.values(errors).some(error => error !== '');
  if (hasErrors) {
    return;
  }

  if (form.password !== form.password_confirmation) {
    errors.password_confirmation = "Password didn't match.";
    return;
  }

  submitting.value = true;

  router.post(route('add_user.store'), form, {
    preserveScroll: true,
    onSuccess: () => {
      showSuccessDialog.value = true;
      // Reset form
      Object.keys(form).forEach(key => form[key] = '');
      form.role_id = '';
      submitting.value = false;
    },
    onError: (errors) => {
      submitting.value = false;
      if (errors.email) {
        errors.email = 'Email is already used.';
      }
    },
  });
};

const closeDialog = () => {
  showSuccessDialog.value = false;
  router.visit(route('users.index'));
};
</script>

<style scoped>
body {
  font-family: 'Inter', sans-serif;
  margin: 0;
  background: #f4f6f8;
  color: #333;
}

.text-maroon {
  color: #9E122C;
}

/* User Types Section */
.user-types-section {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.user-type-info {
  background: #fff;
  padding: 1.5rem;
  border-radius: 12px;
  border: 1px solid #e0e0e0;
  text-align: center;
  transition: transform 0.3s, box-shadow 0.3s;
}

.user-type-info:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
}

.user-type-icon i {
  font-size: 2rem;
  color: #9E122C;
  margin-bottom: 0.5rem;
}

.user-type-text {
  font-size: 0.9rem;
  color: #666;
  margin-bottom: 0.25rem;
}

.user-type-count {
  font-size: 1.5rem;
  font-weight: bold;
  color: #333;
}

/* Form Wrapper */
.form-wrapper {
  display: flex;
  justify-content: center;
  padding: 2rem;
}

.form-box {
  background: #fff;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
  max-width: 800px;
  width: 100%;
}

.form-title {
  text-align: center;
  color: #9E122C;
  margin-bottom: 1.5rem;
  font-size: 1.5rem;
  font-weight: bold;
}

.form-row {
  display: flex;
  gap: 1rem;
  margin-bottom: 1rem;
}

.form-group {
  flex: 1;
}

.form-group.half-width {
  width: 48%;
}

.form-group.full-width {
  width: 100%;
}

label {
  display: block;
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.required {
  color: #f44336;
}

.form-input,
.form-select {
  width: 100%;
  padding: 0.65rem 0.75rem;
  border-radius: 8px;
  border: 1px solid #ccc;
  font-size: 1rem;
  transition: border 0.3s, box-shadow 0.3s;
}

.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: #9E122C;
  box-shadow: 0 0 0 2px rgba(158, 18, 44, 0.2);
}

.input-error {
  border-color: #f44336 !important;
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

.form-input-group .form-input {
  border-radius: 0 8px 8px 0;
  border-left: none;
}

.form-error {
  color: #f44336;
  font-size: 0.875rem;
  margin-top: 0.25rem;
  display: block;
  min-height: 1.25rem;
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
  background: #ccc;
  cursor: not-allowed;
}

/* Dialog */
.dialog-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.dialog-box {
  background: #fff;
  padding: 2rem;
  border-radius: 12px;
  max-width: 400px;
  width: 90%;
  text-align: center;
}

.dialog-title {
  color: #9E122C;
  margin-bottom: 1rem;
  font-size: 1.5rem;
}

.dialog-content {
  margin-bottom: 1.5rem;
  color: #333;
}

.dialog-actions {
  display: flex;
  justify-content: center;
}

.dialog-button {
  background: #9E122C;
  color: #fff;
  padding: 0.5rem 1.5rem;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: bold;
}

.dialog-button:hover {
  background: #7a0f24;
}

/* Responsive */
@media (max-width: 768px) {
  .form-row {
    flex-direction: column;
  }

  .form-group.half-width {
    width: 100%;
  }
}
</style>
