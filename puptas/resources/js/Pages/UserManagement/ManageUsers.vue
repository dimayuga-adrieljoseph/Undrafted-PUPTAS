<template>
  <Head title="Manage Users" />
  <AppLayout>
    <div class="page-container">
      <!-- Header Section -->
      <div class="header-section">
        <div class="header-content">
          <div class="header-left">
            <h1 class="page-title">
              <span class="title-icon">
                <svg class="icon" viewBox="0 0 24 24">
                  <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
              </span>
              User Management
            </h1>
            <p class="page-subtitle">Manage user accounts, roles, and permissions</p>
          </div>
          <div class="header-right">
            <Link 
              :href="route('legacy.add_user')" 
              class="add-user-btn"
            >
              <span class="btn-icon">
                <svg viewBox="0 0 24 24">
                  <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                </svg>
              </span>
              Add New User
            </Link>
          </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="quick-stats">
          <div class="stat-item main-stat">
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
          
          <div class="stat-item">
            <div class="stat-icon">
              <svg viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM7.07 18.28c.43-.9 3.05-1.78 4.93-1.78s4.51.88 4.93 1.78C15.57 19.36 13.86 20 12 20s-3.57-.64-4.93-1.72zm11.29-1.45c-1.43-1.74-4.9-2.33-6.36-2.33s-4.93.59-6.36 2.33C4.62 15.49 4 13.82 4 12c0-4.41 3.59-8 8-8s8 3.59 8 8c0 1.82-.62 3.49-1.64 4.83zM12 6c-1.94 0-3.5 1.56-3.5 3.5S10.06 13 12 13s3.5-1.56 3.5-3.5S13.94 6 12 6zm0 5c-.83 0-1.5-.67-1.5-1.5S11.17 8 12 8s1.5.67 1.5 1.5S12.83 11 12 11z"/>
              </svg>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ userCountsByRole[1] || 0 }}</div>
              <div class="stat-label">Applicants</div>
            </div>
          </div>
          
          <div class="stat-item">
            <div class="stat-icon">
              <svg viewBox="0 0 24 24">
                <path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm0 4c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm6 12H6v-1.4c0-2 4-3.1 6-3.1s6 1.1 6 3.1V19z"/>
              </svg>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ userCountsByRole[2] || 0 }}</div>
              <div class="stat-label">Admins</div>
            </div>
          </div>
          
          <div class="stat-item">
            <div class="stat-icon">
              <svg viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
              </svg>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ userCountsByRole[3] || 0 }}</div>
              <div class="stat-label">Evaluators</div>
            </div>
          </div>
          
          <div class="stat-item">
            <div class="stat-icon">
              <svg viewBox="0 0 24 24">
                <path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/>
              </svg>
            </div>
            <div class="stat-content">
              <div class="stat-value">{{ userCountsByRole[4] || 0 }}</div>
              <div class="stat-label">Interviewers</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Alerts -->
      <div v-if="$page.props.flash.status" class="alert-card success">
        <div class="alert-icon">
          <svg viewBox="0 0 24 24">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
          </svg>
        </div>
        <div class="alert-content">
          <div class="alert-title">Success</div>
          <p>{{ $page.props.flash.status }}</p>
        </div>
      </div>

      <div v-if="$page.props.flash.error" class="alert-card error">
        <div class="alert-icon">
          <svg viewBox="0 0 24 24">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
          </svg>
        </div>
        <div class="alert-content">
          <div class="alert-title">Error</div>
          <p>{{ $page.props.flash.error }}</p>
        </div>
      </div>

      <!-- Main Content Card -->
      <div class="main-card">
        <!-- Toolbar -->
        <div class="toolbar">
          <div class="search-box">
            <div class="search-icon">
              <svg viewBox="0 0 24 24">
                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
              </svg>
            </div>
            <input
              v-model="searchTerm"
              type="text"
              placeholder="Search by name, email, or role..."
              class="search-input"
            />
          </div>
          
          <div class="results-info">
            Showing {{ filteredUsers.length }} of {{ users.length }} users
          </div>
        </div>

        <!-- Users Table -->
        <div class="table-wrapper" v-if="filteredUsers.length">
          <table class="users-table">
            <thead>
              <tr>
                <th class="col-id">ID</th>
                <th class="col-user">User</th>
                <th class="col-email">Email</th>
                <th class="col-contact">Contact</th>
                <th class="col-role">Role</th>
                <th class="col-program">Program</th>
                <th class="col-created">Created</th>
                <th class="col-actions">Actions</th>
              </tr>
            </thead>
            
            <tbody>
              <tr v-for="user in filteredUsers" :key="user.id">
                <td class="col-id">
                  <span class="user-id">#{{ user.id }}</span>
                </td>
                
                <td class="col-user">
                  <div class="user-info">
                    <div class="user-avatar">
                      {{ getInitials(user.firstname, user.lastname) }}
                    </div>
                    <div class="user-details">
                      <div class="user-name">
                        {{ user.firstname }}
                        <span v-if="user.middlename">{{ user.middlename[0] }}.</span>
                        {{ user.lastname }}
                        <span v-if="user.extension_name">{{ user.extension_name }}</span>
                      </div>
                    </div>
                  </div>
                </td>
                
                <td class="col-email">
                  <div class="email-cell">
                    <div class="email-text">{{ user.email }}</div>
                  </div>
                </td>
                
                <td class="col-contact">
                  <div class="contact-cell">
                    {{ user.contactnumber || '—' }}
                  </div>
                </td>
                
                <td class="col-role">
                  <span :class="['role-badge', getRoleClass(user.role_id)]">
                    {{ roles[user.role_id] || 'Unknown' }}
                  </span>
                </td>
                
                <td class="col-program">
                  <div class="program-tags">
                    <!-- Show first choice program for applicants (role_id = 1) -->
                    <span v-if="user.role_id === 1 && user.applicant_profile?.first_choice_program" class="program-tag">
                      {{ user.applicant_profile.first_choice_program.name }}
                    </span>
                    <!-- Show programs for other roles -->
                    <span v-else-if="user.programs?.length" class="program-tag">
                      {{ user.programs[0].name }}
                      <span v-if="user.programs.length > 1" class="tag-count">
                        +{{ user.programs.length - 1 }}
                      </span>
                    </span>
                    <span v-else class="program-tag empty">—</span>
                  </div>
                </td>
                
                <td class="col-created">
                  <div class="date-cell">
                    <div class="date-text">{{ formatDate(user.created_at) }}</div>
                    <div class="date-subtext">Joined</div>
                  </div>
                </td>
                
                <td class="col-actions">
                  <div class="action-buttons">
                    <Link
                      :href="route('users.edit', user.id)"
                      class="action-btn edit-btn"
                      title="Edit user"
                    >
                      <svg viewBox="0 0 24 24">
                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                      </svg>
                    </Link>
                    
                    <button
                      @click="confirmDelete(user.id)"
                      class="action-btn delete-btn"
                      title="Delete user"
                    >
                      <svg viewBox="0 0 24 24">
                        <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <div v-else class="empty-state">
          <div class="empty-icon">
            <svg viewBox="0 0 24 24">
              <path d="M12 5.9c1.16 0 2.1.94 2.1 2.1s-.94 2.1-2.1 2.1S9.9 9.16 9.9 8s.94-2.1 2.1-2.1m0 9c2.97 0 6.1 1.46 6.1 2.1v1.1H5.9V17c0-.64 3.13-2.1 6.1-2.1M12 4C9.79 4 8 5.79 8 8s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0 9c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4z"/>
            </svg>
          </div>
          <h3>No users found</h3>
          <p v-if="searchTerm">No users match your search criteria. Try a different search term.</p>
          <p v-else>Get started by adding your first user to the system.</p>
          <Link 
            :href="route('legacy.add_user')" 
            class="empty-action-btn"
          >
            <svg viewBox="0 0 24 24">
              <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
            </svg>
            Add New User
          </Link>
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

const getInitials = (firstName, lastName) => {
  return `${firstName?.[0] || ''}${lastName?.[0] || ''}`.toUpperCase();
};

const formatDate = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', { 
    month: 'short', 
    day: 'numeric',
    year: 'numeric'
  });
};

const confirmDelete = (userId) => {
  if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
    router.delete(route('users.destroy', userId));
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

.header-left .page-title {
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

.add-user-btn {
  background: linear-gradient(135deg, #9e122c 0%, #c81e3d 100%);
  color: white;
  padding: 0.75rem 1.5rem;
  border-radius: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(158, 18, 44, 0.2);
}

.add-user-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(158, 18, 44, 0.3);
}

.btn-icon svg {
  width: 20px;
  height: 20px;
  fill: currentColor;
}

/* Quick Stats */
.quick-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
}

.stat-item {
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

.stat-item.main-stat {
  background: linear-gradient(135deg, #9e122c 0%, #c81e3d 100%);
  color: white;
}

.stat-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.stat-item.main-stat:hover {
  box-shadow: 0 4px 12px rgba(158, 18, 44, 0.3);
}

.stat-icon svg {
  width: 32px;
  height: 32px;
}

.stat-item.main-stat .stat-icon svg {
  fill: white;
}

.stat-item:not(.main-stat) .stat-icon svg {
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

.stat-item.main-stat .stat-label {
  opacity: 0.95;
}

/* Alerts */
.alert-card {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1rem 1.25rem;
  border-radius: 12px;
  margin-bottom: 1.5rem;
  animation: slideIn 0.3s ease;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.alert-card.success {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #166534;
}

.alert-card.error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
}

.alert-icon svg {
  width: 24px;
  height: 24px;
  flex-shrink: 0;
}

.alert-card.success .alert-icon svg {
  fill: #166534;
}

.alert-card.error .alert-icon svg {
  fill: #991b1b;
}

.alert-content {
  flex: 1;
}

.alert-title {
  font-weight: 600;
  margin-bottom: 0.25rem;
}

.alert-content p {
  margin: 0;
  font-size: 0.95rem;
  opacity: 0.95;
}

/* Main Card */
.main-card {
  background: white;
  border-radius: 20px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
  overflow: hidden;
  border: 1px solid #e2e8f0;
}

/* Toolbar */
.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem 1.5rem 1rem;
  border-bottom: 1px solid #f1f5f9;
}

.search-box {
  flex: 1;
  max-width: 400px;
  position: relative;
}

.search-icon {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
}

.search-icon svg {
  width: 20px;
  height: 20px;
  fill: #94a3b8;
}

.search-input {
  width: 100%;
  padding: 0.875rem 1rem 0.875rem 3rem;
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  background: #f8fafc;
}

.search-input:focus {
  outline: none;
  border-color: #9e122c;
  background: white;
  box-shadow: 0 0 0 3px rgba(158, 18, 44, 0.1);
}

.results-info {
  font-size: 0.875rem;
  color: #64748b;
  font-weight: 500;
}

/* Table */
.table-wrapper {
  overflow-x: auto;
  padding: 0 1.5rem;
}

.users-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}

.users-table thead {
  background: #f8fafc;
}

.users-table th {
  padding: 1rem 0.75rem;
  text-align: left;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #64748b;
  border-bottom: 2px solid #e2e8f0;
  white-space: nowrap;
}

.users-table tbody tr {
  transition: all 0.2s ease;
}

.users-table tbody tr:hover {
  background: #f8fafc;
}

.users-table td {
  padding: 1rem 0.75rem;
  border-bottom: 1px solid #f1f5f9;
}

/* Column-specific styles */
.col-id {
  width: 80px;
}

.user-id {
  font-family: 'SF Mono', 'Monaco', 'Inconsolata', monospace;
  color: #64748b;
  font-size: 0.875rem;
  font-weight: 600;
}

.col-user {
  min-width: 250px;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 0.875rem;
}

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, #9e122c 0%, #c81e3d 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.875rem;
  flex-shrink: 0;
}

.user-name {
  font-weight: 600;
  color: #1a202c;
}

.col-email .email-cell {
  min-width: 200px;
}

.email-text {
  color: #4a5568;
  font-size: 0.95rem;
}

.col-contact .contact-cell {
  color: #64748b;
  font-size: 0.95rem;
}

.col-role {
  white-space: nowrap;
}

.role-badge {
  display: inline-block;
  padding: 0.375rem 0.75rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.role-applicant { background: #dbeafe; color: #1d4ed8; }
.role-admin { background: #fef3c7; color: #d97706; }
.role-evaluator { background: #ede9fe; color: #7c3aed; }
.role-interviewer { background: #dcfce7; color: #15803d; }
.role-medical { background: #fce7f3; color: #be185d; }
.role-registrar { background: #fef9c3; color: #a16207; }

.col-program {
  min-width: 150px;
}

.program-tags {
  display: flex;
  gap: 0.25rem;
}

.program-tag {
  background: #f1f5f9;
  color: #475569;
  padding: 0.375rem 0.75rem;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.program-tag.empty {
  opacity: 0.5;
}

.tag-count {
  background: #e2e8f0;
  color: #475569;
  font-size: 0.75rem;
  padding: 0.125rem 0.375rem;
  border-radius: 999px;
  margin-left: 0.25rem;
}

.col-created {
  white-space: nowrap;
}

.date-cell {
  display: flex;
  flex-direction: column;
}

.date-text {
  font-weight: 500;
  color: #1a202c;
  font-size: 0.95rem;
}

.date-subtext {
  font-size: 0.75rem;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.col-actions {
  width: 120px;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
}

.action-btn {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: none;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
}

.action-btn svg {
  width: 18px;
  height: 18px;
}

.edit-btn {
  color: #3b82f6;
  background: #eff6ff;
}

.edit-btn:hover {
  background: #dbeafe;
  color: #1d4ed8;
}

.delete-btn {
  color: #ef4444;
  background: #fef2f2;
}

.delete-btn:hover {
  background: #fee2e2;
  color: #dc2626;
}

/* Empty State */
.empty-state {
  padding: 4rem 2rem;
  text-align: center;
}

.empty-icon svg {
  width: 64px;
  height: 64px;
  fill: #cbd5e1;
  margin-bottom: 1.5rem;
}

.empty-state h3 {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1a202c;
  margin: 0 0 0.75rem 0;
}

.empty-state p {
  color: #64748b;
  max-width: 400px;
  margin: 0 auto 2rem;
  line-height: 1.5;
}

.empty-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: linear-gradient(135deg, #9e122c 0%, #c81e3d 100%);
  color: white;
  padding: 0.75rem 1.5rem;
  border-radius: 12px;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(158, 18, 44, 0.2);
}

.empty-action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(158, 18, 44, 0.3);
}

.empty-action-btn svg {
  width: 20px;
  height: 20px;
  fill: currentColor;
}

/* Responsive */
@media (max-width: 1024px) {
  .quick-stats {
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
  
  .add-user-btn {
    width: 100%;
    justify-content: center;
  }
  
  .toolbar {
    flex-direction: column;
    gap: 1rem;
    align-items: stretch;
  }
  
  .search-box {
    max-width: 100%;
  }
  
  .results-info {
    text-align: right;
  }
}

@media (max-width: 640px) {
  .quick-stats {
    grid-template-columns: 1fr;
  }
  
  .users-table {
    min-width: 800px;
  }
  
  .table-wrapper {
    margin: 0 -1.5rem;
    padding: 0;
  }
}
</style>