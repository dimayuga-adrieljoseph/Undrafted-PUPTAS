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
          >
        </div>

        <table v-if="filteredUsers.length > 0" id="usersTable">
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
                <span v-if="user.middlename">{{ user.middlename }}</span> 
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
              <td>{{ user.programs?.map(p => p.name).join(', ') || 'N/A' }}</td>
              <td>{{ formatDate(user.created_at) }}</td>
              <td>
                <div class="action-buttons">
                  <Link :href="route('users.edit', user.id)" class="btn-edit">
                    <i class="fas fa-edit"></i> Edit
                  </Link>
                  <button @click="confirmDelete(user.id)" class="btn-delete">
                    <i class="fas fa-trash"></i> Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-else class="no-users">
          <i class="fas fa-user-slash" style="font-size: 3rem; margin-bottom: 1rem;"></i>
          <p>No users found. <Link :href="route('legacy.add_user')">Add your first user</Link></p>
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
  
  const term = searchTerm.value.toLowerCase();
  return props.users.filter(user => {
    const fullName = `${user.firstname} ${user.middlename || ''} ${user.lastname} ${user.extension_name || ''}`.toLowerCase();
    const email = user.email.toLowerCase();
    const role = (props.roles[user.role_id] || '').toLowerCase();
    
    return fullName.includes(term) || email.includes(term) || role.includes(term);
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

const formatDate = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' });
};

const confirmDelete = (userId) => {
  if (confirm('Are you sure you want to delete this user?')) {
    router.delete(route('users.destroy', userId));
  }
};
</script>

<style scoped>
/* Reset & Typography */
.container {
  max-width: 1400px;
  margin: 2rem auto;
  padding: 0 1rem;
  font-family: 'Inter', sans-serif;
}

a {
  text-decoration: none;
}

h1, h2 {
  margin: 0;
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
  transform: translateY(-5px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.stat-card i {
  font-size: 2rem;
  color: #9E122C;
  margin-bottom: 0.5rem;
}

.stat-card span {
  font-size: 0.9rem;
  color: #666;
  margin-bottom: 0.5rem;
}

.stat-card .count {
  font-size: 2rem;
  font-weight: bold;
  color: #333;
}

/* Alerts */
.alert {
  padding: 1rem;
  margin-bottom: 1.5rem;
  border-radius: 8px;
  display: flex;
  align-items: center;
  gap: 0.5rem;
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

/* Table */
.table-wrapper {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  background: linear-gradient(135deg, #9E122C, #c41933);
  color: #fff;
}

.table-header h2 {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-add {
  background: #fff;
  color: #9E122C;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-weight: bold;
  transition: all 0.3s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-add:hover {
  background: #f0f0f0;
  transform: translateY(-2px);
}

/* Search Box */
.search-box {
  padding: 1.5rem;
}

.search-input {
  width: 100%;
  padding: 0.75rem 1rem;
  border: 2px solid #ddd;
  border-radius: 8px;
  font-size: 1rem;
  transition: border-color 0.3s;
}

.search-input:focus {
  outline: none;
  border-color: #9E122C;
}

/* Table Styles */
table {
  width: 100%;
  border-collapse: collapse;
}

thead {
  background: #f8f9fa;
}

th {
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: #555;
  border-bottom: 2px solid #ddd;
}

td {
  padding: 1rem;
  border-bottom: 1px solid #eee;
}

tbody tr:hover {
  background: #f9f9f9;
}

/* Role Badges */
.role-badge {
  padding: 0.35rem 0.75rem;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 600;
  display: inline-block;
}

.role-applicant { background: #e3f2fd; color: #1976d2; }
.role-admin { background: #fff3e0; color: #e65100; }
.role-evaluator { background: #f3e5f5; color: #7b1fa2; }
.role-interviewer { background: #e8f5e9; color: #2e7d32; }
.role-medical { background: #fce4ec; color: #c2185b; }
.role-registrar { background: #fff9c4; color: #f57f17; }

/* Action Buttons */
.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.btn-edit, .btn-delete {
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
}

.btn-edit {
  background: #2196F3;
  color: #fff;
}

.btn-edit:hover {
  background: #1976D2;
}

.btn-delete {
  background: #f44336;
  color: #fff;
}

.btn-delete:hover {
  background: #d32f2f;
}

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
