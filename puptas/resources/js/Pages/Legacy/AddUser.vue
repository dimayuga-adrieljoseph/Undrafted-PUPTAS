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
                  v-model="form.firstname" 
                  @blur="validateField('firstname')"
                  class="form-input" 
                  type="text" 
                  required 
                  autocomplete="firstname" 
                />
                <span v-if="errors.firstname" class="form-error">{{ errors.firstname }}</span>
              </div>

              <div class="form-group half-width">
                <label for="lastname">Last Name <span class="required">*</span></label>
                <input 
                  id="lastname" 
                  v-model="form.lastname" 
                  @blur="validateField('lastname')"
                  class="form-input" 
                  type="text" 
                  required 
                  autocomplete="lastname" 
                />
                <span v-if="errors.lastname" class="form-error">{{ errors.lastname }}</span>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group half-width">
                <label for="middlename">Middle Name</label>
                <input 
                  id="middlename" 
                  v-model="form.middlename" 
                  class="form-input" 
                  type="text" 
                  autocomplete="middlename" 
                />
              </div>

              <div class="form-group half-width">
                <label for="extension_name">Extension Name</label>
                <input 
                  id="extension_name" 
                  v-model="form.extension_name" 
                  class="form-input" 
                  type="text" 
                  autocomplete="extension_name" 
                />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group half-width">
                <label for="email">Email <span class="required">*</span></label>
                <input 
                  id="email" 
                  v-model="form.email" 
                  @blur="validateField('email')"
                  class="form-input" 
                  type="email" 
                  required 
                  autocomplete="email"
                  pattern="[a-z0-9._%+\-]+@gmail\.com$"
                  title="Must be a valid email format like example@gmail.com"
                />
                <span v-if="errors.email" class="form-error">{{ errors.email }}</span>
              </div>

              <div class="form-group half-width">
                <label for="contactnumber">Contact Number <span class="required">*</span></label>
                <div class="form-input-group">
                  <div class="input-prefix">+63</div>
                  <input 
                    id="contactnumber" 
                    v-model="form.contactnumber" 
                    @blur="validateField('contactnumber')"
                    class="form-input" 
                    type="text" 
                    required 
                    autocomplete="contactnumber"
                    maxlength="10"
                    pattern="\d{10}"
                    title="Invalid contact number. Must be exactly 10 digits."
                  />
                </div>
                <span v-if="errors.contactnumber" class="form-error">{{ errors.contactnumber }}</span>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group half-width">
                <label for="password">Password <span class="required">*</span></label>
                <input 
                  id="password" 
                  v-model="form.password" 
                  @blur="validateField('password')"
                  class="form-input" 
                  type="password" 
                  required 
                  autocomplete="new-password"
                  pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]{8,}"
                />
                <span v-if="errors.password" class="form-error">{{ errors.password }}</span>
              </div>

              <div class="form-group half-width">
                <label for="password_confirmation">Confirm Password <span class="required">*</span></label>
                <input 
                  id="password_confirmation" 
                  v-model="form.password_confirmation" 
                  @blur="validateField('password_confirmation')"
                  class="form-input" 
                  type="password" 
                  required 
                  autocomplete="new-password"
                />
                <span v-if="errors.password_confirmation" class="form-error">{{ errors.password_confirmation }}</span>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group full-width">
                <label for="role_id">Select Your User Type <span class="required">*</span></label>
                <select 
                  id="role_id" 
                  v-model="form.role_id" 
                  @change="onRoleChange"
                  class="form-select" 
                  required
                >
                  <option value="" disabled>---- e.g. Applicant, Admin, Evaluator, Interviewer ----</option>
                  <option value="1">Applicant</option>
                  <option value="2">Admin</option>
                  <option value="3">Evaluator</option>
                  <option value="4">Interviewer</option>
                  <option value="5">Medical Staff</option>
                  <option value="6">Registrar</option>
                </select>
                <span v-if="errors.role_id" class="form-error">{{ errors.role_id }}</span>
              </div>
            </div>

            <div v-if="showProgramField" class="form-row">
              <div class="form-group full-width">
                <label for="program">Program <span class="required">*</span></label>
                <select 
                  id="program" 
                  v-model="form.program" 
                  class="form-select"
                  :required="showProgramField"
                >
                  <option value="" disabled>---- Select Program ----</option>
                  <option v-for="program in programs" :key="program.code" :value="program.code">
                    {{ program.name }}
                  </option>
                </select>
                <span v-if="errors.program" class="form-error">{{ errors.program }}</span>
              </div>
            </div>

            <div class="form-group mt-4 flex items-center justify-end">
              <button type="submit" class="form-button ms-4">
                <i class="fa-solid fa-user-plus"></i> Add User
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
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

onMounted(() => {
  if (!document.querySelector('link[href*="font-awesome"]')) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css';
    document.head.appendChild(link);
  }
});

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
});

const errors = ref({});

const showProgramField = computed(() => {
  return form.value.role_id === '1';
});

const onRoleChange = () => {
  if (!showProgramField.value) {
    form.value.program = '';
  }
};

const validateField = (fieldName) => {
  errors.value[fieldName] = '';

  if (fieldName === 'email' && form.value.email) {
    const emailPattern = /^[a-z0-9._%+\-]+@gmail\.com$/;
    if (!emailPattern.test(form.value.email)) {
      errors.value.email = 'Must be a valid Gmail address';
    }
  }

  if (fieldName === 'contactnumber' && form.value.contactnumber) {
    if (!/^\d{10}$/.test(form.value.contactnumber)) {
      errors.value.contactnumber = 'Must be exactly 10 digits';
    }
  }

  if (fieldName === 'password' && form.value.password) {
    const passwordPattern = /(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]{8,}/;
    if (!passwordPattern.test(form.value.password)) {
      errors.value.password = 'Password must be at least 8 characters with uppercase, lowercase, number and special character';
    }
  }

  if (fieldName === 'password_confirmation' && form.value.password_confirmation) {
    if (form.value.password !== form.value.password_confirmation) {
      errors.value.password_confirmation = 'Passwords do not match';
    }
  }
};

const submitForm = () => {
  Object.keys(form.value).forEach(key => validateField(key));

  const hasErrors = Object.values(errors.value).some(error => error !== '');
  if (hasErrors) return;

  router.post(route('add_user.store'), form.value, {
    onSuccess: () => {
      alert('User added successfully!');
      Object.keys(form.value).forEach(key => {
        form.value[key] = '';
      });
    },
    onError: (serverErrors) => {
      errors.value = serverErrors;
    }
  });
};
</script>

<style scoped>
.text-maroon {
  color: #800000;
}

.user-types-section {
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
  margin-top: 10px;
  margin-bottom: 10px;
}

.user-type-info {
  background-color: #800000;
  color: white;
  padding: 15px;
  border-radius: 10px;
  text-align: center;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
  transition: transform 0.3s;
}

.user-type-info:hover {
  transform: translateY(-5px);
}

.user-type-icon {
  font-size: 1.75em;
}

.user-type-text {
  font-size: 1.125em;
  margin-top: 8px;
}

.user-type-count {
  font-size: 1.25em;
  margin-top: 4px;
}

.form-wrapper {
  display: flex;
  justify-content: center;
  padding: 2rem;
}

.form-box {
  background: white;
  padding: 20px 30px;
  border-radius: 8px;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
  width: 85%;
  max-width: 800px;
  margin-bottom: 20px;
}

.form-title {
  text-align: center;
  font-size: 24px;
  margin-bottom: 20px;
  color: #800000;
  font-weight: bold;
}

.form-row {
  display: flex;
  gap: 15px;
  margin-bottom: 15px;
  flex-wrap: wrap;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.half-width {
  flex: 1 1 45%;
}

.full-width {
  flex: 1 1 100%;
}

label {
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #333;
}

.required {
  color: #800000;
}

.form-input, .form-select {
  width: 100%;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 4px;
  font-size: 16px;
  transition: border-color 0.3s;
}

.form-input:focus, .form-select:focus {
  outline: none;
  border-color: #800000;
  box-shadow: 0 0 8px rgba(217, 83, 79, 0.1);
}

.form-input-group {
  display: flex;
  align-items: center;
}

.input-prefix {
  padding: 10px;
  background-color: #eee;
  border: 1px solid #ccc;
  border-right: none;
  border-radius: 4px 0 0 4px;
  color: #555;
}

.form-input-group .form-input {
  border-radius: 0 4px 4px 0;
  flex: 1;
}

.form-error {
  color: #800000;
  font-size: 0.875rem;
  margin-top: 0.25rem;
}

.form-button {
  background-color: #800000;
  color: white;
  padding: 10px 20px;
  border: none;
  border-radius: 35px;
  cursor: pointer;
  font-size: 16px;
  font-weight: bold;
  transition: background-color 0.3s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.form-button:hover {
  background-color: #c9302c;
}

@media (max-width: 767px) {
  .user-types-section {
    width: 100%;
    padding: 10px;
  }

  .user-types-section .grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
  }

  .form-box {
    width: 100%;
    padding: 15px;
  }

  .form-row {
    flex-direction: column;
  }

  .half-width {
    flex: 1 1 100%;
  }

  .form-button {
    width: 100%;
    padding: 12px;
  }
}
</style>
