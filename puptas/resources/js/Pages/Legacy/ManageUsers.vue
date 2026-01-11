<template>
  <Head title="Manage Users" />
  <AppLayout>
    <div class="container">
      <!-- User Statistics -->
      <h1 class="mb-4 text-center text-maroon">User Statistics</h1>
      <div class="stats-grid">
        <div class="stat-card">
          <i class="fas fa-users"></i>
          <span>Total Users</span>
          <div class="count">{{ totalUsers }}</div>
        </div>
        <div class="stat-card">
          <i class="fas fa-user"></i>
          <span>Applicants</span>
          <div class="count">{{ userCountsByRole[1] || 0 }}</div>
        </div>
        <div class="stat-card">
          <i class="fas fa-tools"></i>
          <span>Admins</span>
          <div class="count">{{ userCountsByRole[2] || 0 }}</div>
        </div>
        <div class="stat-card">
          <i class="fas fa-check"></i>
          <span>Evaluator</span>
          <div class="count">{{ userCountsByRole[3] || 0 }}</div>
        </div>
        <div class="stat-card">
          <i class="fas fa-edit"></i>
          <span>Interviewer</span>
          <div class="count">{{ userCountsByRole[4] || 0 }}</div>
        </div>
        <div class="stat-card">
          <i class="fa-solid fa-suitcase-medical"></i>
          <span>Medical Staff</span>
          <div class="count">{{ userCountsByRole[5] || 0 }}</div>
        </div>
        <div class="stat-card">
          <i class="fas fa-user"></i>
          <span>Registrar</span>
          <div class="count">{{ userCountsByRole[6] || 0 }}</div>
        </div>
      </div>

      <!-- Alerts -->
      <div v-if="$page.props.flash.status" class="alert alert-success">
        <i class="fas fa-check-circle"></i> {{ $page.props.flash.status }}
      </div>

      <div v-if="$page.props.flash.error" class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> {{ $page.props.flash.error }}
      </div>

      <!-- Users Table -->
      <div class="table-wrapper">
        <div class="table-header">
          <h2><i class="fas fa-users"></i> Manage Users</h2>
          <Link :href="route('legacy.add_user')" class="btn-add">
            <i class="fas fa-user-plus"></i> Add User
          </Link>
        </div>

        <div class="search-box">
          <input 
            type="text" 
            v-model="searchTerm" 
            class="search-input" 
            placeholder="Search by name, email, or role..."
          />
        </div>

        <table v-if="users.length > 0" id="usersTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Contact</th>
              <th>Role</th>
              <th>Program</th>
              <th>Created At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in filteredUsers" :key="user.id">
              <td>{{ user.id }}</td>
              <td>
                {{ user.firstname }} 
                <span v-if="user.middlename">{{ user.middlename }} </span>
                {{ user.lastname }} 
                <span v-if="user.extension_name">{{ user.extension_name }}</span>
              </td>
              <td>{{ user.email }}</td>
              <td>{{ user.contactnumber }}</td>
              <td>
                <span :class="['role-badge', getRoleClass(user.role_id)]">
                  {{ roles[user.role_id] || 'Unknown' }}
                </span>
              </td>
              <td>{{ getProgramNames(user.programs) }}</td>
              <td>{{ formatDate(user.created_at) }}</td>
              <td>
                <div class="action-buttons">
                  <Link :href="route('users.edit', user.id)" class="btn-edit">
                    <i class="fas fa-edit"></i> Edit
                  </Link>
                  <button 
                    @click="confirmDelete(user.id)" 
                    class="btn-delete"
                  >
                    <i class="fas fa-trash"></i> Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-else class="no-users">
          <i class="fas fa-user-slash" style="font-size: 3rem; margin-bottom: 1rem;"></i>
          <p>No users found. <Link :href="route('legacy.add_user')" style="color: #9E122C; font-weight: bold;">Add your first user</Link></p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
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
  users: Array,
  userCountsByRole: Object,
  roles: Object,
  totalUsers: Number,
});

const searchTerm = ref('');

const filteredUsers = computed(() => {
  if (!searchTerm.value) return props.users;
  
  const search = searchTerm.value.toLowerCase();
  return props.users.filter(user => {
    const name = `${user.firstname} ${user.middlename || ''} ${user.lastname} ${user.extension_name || ''}`.toLowerCase();
    const email = user.email.toLowerCase();
    const role = props.roles[user.role_id]?.toLowerCase() || '';
    return name.includes(search) || email.includes(search) || role.includes(search);
  });
});

const getRoleClass = (roleId) => {
  const roleClasses = {
    1: 'role-applicant',
    2: 'role-admin',
    3: 'role-evaluator',
    4: 'role-interviewer',
    5: 'role-medical',
    6: 'role-registrar',
  };
  return roleClasses[roleId] || '';
};

const getProgramNames = (programs) => {
  if (!programs || programs.length === 0) return 'N/A';
  return programs.map(p => p.name).join(', ');
};

const formatDate = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const confirmDelete = (userId) => {
  if (confirm('Are you sure you want to delete this user?')) {
    router.delete(route('users.destroy', userId));
  }
};
</script>

<style scoped>
/* Reset & Typography */
body {
  font-family: 'Inter', sans-serif;
  margin: 0;
  background: #f4f6f8;
  color: #333;
}

a {
  text-decoration: none;
}

h1, h2 {
  margin: 0;
}

/* Container */
.container {
  max-width: 1400px;
  margin: 2rem auto;
  padding: 0 1rem;
}

.text-maroon {
  color: #9E122C;
}

/* User Statistics */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: #fff;
  padding: 1.5rem;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  transition: transform 0.3s, box-shadow 0.3s;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.stat-card i {
  font-size: 2rem;
  margin-bottom: 0.5rem;
  color: #9E122C;
}

.stat-card .count {
  font-size: 1.5rem;
  font-weight: bold;
  margin-top: 0.25rem;
}

/* Alerts */
.alert {
  max-width: 1400px;
  margin: 1rem auto;
  padding: 1rem 1.5rem;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
}

.alert-success {
  background: #d4edda;
  color: #155724;
  border: 1px solid #c3e6cb;
}

.alert-error {
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

/* Table Section */
.table-wrapper {
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
}

.table-header {
  background: #9E122C;
  color: #fff;
  padding: 1rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.btn-add {
  background: #FBCB77;
  color: #9E122C;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-weight: bold;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.3s;
}

.btn-add:hover {
  background: #EE6A43;
  color: #fff;
}

.search-box {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid #eee;
}

.search-input {
  width: 100%;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  border: 1px solid #ddd;
  font-size: 1rem;
}

table {
  width: 100%;
  border-collapse: collapse;
}

thead {
  background: #f9f9f9;
}

th, td {
  padding: 1rem 0.75rem;
  text-align: left;
  font-size: 0.95rem;
}

th {
  font-weight: 600;
  color: #555;
}

tbody tr:hover {
  background: #f1f5f9;
}

/* Role Badges */
.role-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 500;
  display: inline-block;
}

.role-applicant { background: #e3f2fd; color: #1976d2; }
.role-admin { background: #fce4ec; color: #c2185b; }
.role-evaluator { background: #e8f5e9; color: #388e3c; }
.role-interviewer { background: #fff3e0; color: #f57c00; }
.role-medical { background: #f3e5f5; color: #7b1fa2; }
.role-registrar { background: #e0f2f1; color: #00796b; }

/* Action Buttons */
.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.btn-edit, .btn-delete {
  padding: 0.375rem 0.75rem;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  transition: all 0.3s;
}

.btn-edit { background: #2196f3; color: #fff; }
.btn-edit:hover { background: #1976d2; }

.btn-delete { background: #f44336; color: #fff; }
.btn-delete:hover { background: #d32f2f; }

/* No Users */
.no-users {
  text-align: center;
  padding: 3rem;
  color: #888;
}

.no-users a {
  color: #9E122C;
  font-weight: bold;
}
</style>
