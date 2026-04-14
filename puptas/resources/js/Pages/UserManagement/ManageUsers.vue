<template>
  <Head title="Manage Users" />
  <AppLayout>
    <!-- Header -->
    <div class="px-4 md:px-8 mb-8">
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">User Management</h1>
          <p class="text-gray-600 dark:text-gray-400 mt-2">Manage user accounts, roles, and permissions</p>
        </div>
        <Link
          :href="route('users.create')"
          class="hidden md:inline-flex items-center gap-2 px-4 py-2 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          Add New User
        </Link>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 px-4 md:px-8 mb-8">
      <div class="bg-[#9E122C] text-white rounded-xl p-5 shadow-lg col-span-2 sm:col-span-1 flex items-center gap-4">
        <div class="p-2 bg-white/20 rounded-lg">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
            <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
          </svg>
        </div>
        <div>
          <p class="text-white/80 text-xs font-medium">Total Users</p>
          <p class="text-2xl font-bold">{{ totalUsers }}</p>
        </div>
      </div>

      <div
        v-for="(stat, i) in roleStats"
        :key="i"
        class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-lg border border-gray-200 dark:border-gray-700 flex items-center gap-4 hover:shadow-xl transition-all duration-300"
      >
        <div :class="['p-2 rounded-lg', stat.bg]">
          <svg class="w-5 h-5" :class="stat.color" fill="currentColor" viewBox="0 0 24 24">
            <path :d="stat.icon"/>
          </svg>
        </div>
        <div>
          <p class="text-gray-500 dark:text-gray-400 text-xs font-medium">{{ stat.label }}</p>
          <p class="text-xl font-bold text-gray-900 dark:text-white">{{ userCountsByRole[stat.roleId] || 0 }}</p>
        </div>
      </div>
    </div>

    <!-- Flash Messages -->
    <div class="px-4 md:px-8 mb-6">
      <div v-if="page.props.flash.status" class="flex items-start gap-3 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-800 dark:text-green-300">
        <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
        </svg>
        <p class="text-sm font-medium">{{ page.props.flash.status }}</p>
      </div>
      <div v-if="page.props.flash.error" class="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-red-800 dark:text-red-300">
        <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
        </svg>
        <p class="text-sm font-medium">{{ page.props.flash.error }}</p>
      </div>
    </div>

    <!-- Table Card -->
    <div class="px-4 md:px-8">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">

        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <div class="relative flex-1 max-w-sm">
            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
              v-model="searchTerm"
              type="text"
              placeholder="Search by name, email, or role..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
            />
          </div>
          <p class="text-sm text-gray-500 dark:text-gray-400 self-center whitespace-nowrap">
            {{ filteredUsers.length }} of {{ users.length }} users
          </p>
        </div>

        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">User</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Contact</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Role</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Program</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Joined</th>
                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr
                v-for="user in filteredUsers"
                :key="user.id"
                class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
              >
                <td class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-[#9E122C] text-white flex items-center justify-center text-xs font-bold flex-shrink-0">
                      {{ getInitials(user.firstname, user.lastname) }}
                    </div>
                    <div>
                      <p class="font-semibold text-gray-900 dark:text-white">
                        {{ user.firstname }}
                        <span v-if="user.middlename">{{ user.middlename[0] }}.</span>
                        {{ user.lastname }}
                        <span v-if="user.extension_name">{{ user.extension_name }}</span>
                      </p>
                      <p class="text-xs text-gray-400">#{{ user.id }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ user.email }}</td>
                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ user.contactnumber || '—' }}</td>
                <td class="px-6 py-4">
                  <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', getRoleBadgeClass(user.role_id)]">
                    {{ roles?.[user.role_id] || 'Unknown' }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-md text-xs font-medium">
                    {{ getProgramName(user) }}
                  </span>
                </td>
                <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs whitespace-nowrap">{{ formatDate(user.created_at) }}</td>
                <td class="px-6 py-4">
                  <div class="flex justify-end gap-1">
                    <Link
                      :href="route('users.edit', user.id)"
                      class="p-2 text-gray-400 hover:text-[#9E122C] dark:text-gray-400 dark:hover:text-[#9E122C] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                      title="Edit user"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                    </Link>
                    <button
                      @click="confirmDelete(user.id)"
                      class="p-2 text-gray-400 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-500 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                      title="Delete user"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Cards -->
        <div class="md:hidden divide-y divide-gray-100 dark:divide-gray-700">
          <div
            v-for="user in filteredUsers"
            :key="user.id"
            class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-lg bg-[#9E122C] text-white flex items-center justify-center text-sm font-bold flex-shrink-0">
                  {{ getInitials(user.firstname, user.lastname) }}
                </div>
                <div class="min-w-0">
                  <p class="font-semibold text-gray-900 dark:text-white truncate">
                    {{ user.firstname }}
                    <span v-if="user.middlename">{{ user.middlename[0] }}.</span>
                    {{ user.lastname }}
                  </p>
                  <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ user.email }}</p>
                </div>
              </div>
              <div class="flex gap-1 flex-shrink-0">
                <Link
                  :href="route('users.edit', user.id)"
                  class="p-2 text-gray-400 hover:text-[#9E122C] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                </Link>
                <button
                  @click="confirmDelete(user.id)"
                  class="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
              <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', getRoleBadgeClass(user.role_id)]">
                {{ roles?.[user.role_id] || 'Unknown' }}
              </span>
              <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-full text-xs font-medium">
                {{ getProgramName(user) }}
              </span>
              <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-full text-xs">
                Joined {{ formatDate(user.created_at) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="!filteredUsers.length" class="text-center py-16">
          <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
          </svg>
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No users found</h3>
          <p class="text-gray-500 dark:text-gray-400 mb-4">
            <span v-if="searchTerm">No users match your search. Try a different term.</span>
            <span v-else>Get started by adding your first user.</span>
          </p>
          <Link
            :href="route('users.create')"
            class="inline-flex items-center gap-2 px-4 py-2 bg-[#9E122C] text-white rounded-lg hover:bg-[#b51834] transition font-medium"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <line x1="12" y1="5" x2="12" y2="19"></line>
              <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add New User
          </Link>
        </div>
      </div>
    </div>

    <!-- Floating Add Button (Mobile) -->
    <Link
      :href="route('users.create')"
      class="md:hidden fixed bottom-6 right-6 bg-[#9E122C] text-white rounded-full w-14 h-14 flex items-center justify-center shadow-lg hover:bg-[#b51834] transition hover:scale-110 z-40"
      title="Add New User"
    >
      <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
      </svg>
    </Link>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  users: Array,
  userCountsByRole: Object,
  roles: Object,
  totalUsers: Number,
});

const page = usePage();
const searchTerm = ref('');

const roleStats = [
  {
    label: 'Applicants', roleId: 1,
    icon: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM7.07 18.28c.43-.9 3.05-1.78 4.93-1.78s4.51.88 4.93 1.78C15.57 19.36 13.86 20 12 20s-3.57-.64-4.93-1.72zm11.29-1.45c-1.43-1.74-4.9-2.33-6.36-2.33s-4.93.59-6.36 2.33C4.62 15.49 4 13.82 4 12c0-4.41 3.59-8 8-8s8 3.59 8 8c0 1.82-.62 3.49-1.64 4.83zM12 6c-1.94 0-3.5 1.56-3.5 3.5S10.06 13 12 13s3.5-1.56 3.5-3.5S13.94 6 12 6zm0 5c-.83 0-1.5-.67-1.5-1.5S11.17 8 12 8s1.5.67 1.5 1.5S12.83 11 12 11z',
    bg: 'bg-blue-100 dark:bg-blue-900/30', color: 'text-blue-600 dark:text-blue-300',
  },
  {
    label: 'Admins', roleId: 2,
    icon: 'M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm0 4c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm6 12H6v-1.4c0-2 4-3.1 6-3.1s6 1.1 6 3.1V19z',
    bg: 'bg-yellow-100 dark:bg-yellow-900/30', color: 'text-yellow-600 dark:text-yellow-300',
  },
  {
    label: 'Evaluators', roleId: 3,
    icon: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z',
    bg: 'bg-purple-100 dark:bg-purple-900/30', color: 'text-purple-600 dark:text-purple-300',
  },
  {
    label: 'Interviewers', roleId: 4,
    icon: 'M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z',
    bg: 'bg-green-100 dark:bg-green-900/30', color: 'text-green-600 dark:text-green-300',
  },
];

const filteredUsers = computed(() => {
  if (!searchTerm.value) return props.users;
  const term = searchTerm.value.toLowerCase();
  return props.users.filter(user => {
    const fullName = `${user.firstname} ${user.middlename || ''} ${user.lastname} ${user.extension_name || ''}`.toLowerCase();
    return fullName.includes(term) || user.email.toLowerCase().includes(term) || (props.roles?.[user.role_id] || '').toLowerCase().includes(term);
  });
});

const getRoleBadgeClass = (roleId) => {
  const map = {
    1: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
    2: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300',
    3: 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300',
    4: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
    5: 'bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-300',
    6: 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300',
  };
  return map[roleId] || 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300';
};

const getProgramName = (user) => {
  if (user.role_id === 1) {
    return user.officially_enrolled_application?.program?.name
      || user.current_application?.program?.name
      || user.applicant_profile?.first_choice_program?.name
      || '—';
  }
  return user.programs?.[0]?.name || '—';
};

const getInitials = (firstName, lastName) =>
  `${firstName?.[0] || ''}${lastName?.[0] || ''}`.toUpperCase();

const formatDate = (dateString) =>
  new Date(dateString).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

const confirmDelete = (userId) => {
  if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
    router.delete(route('users.destroy', userId));
  }
};
</script>

<style scoped>
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: #FBCB77; border-radius: 5px; }
::-webkit-scrollbar-thumb { background: #9E122C; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #EE6A43; }
</style>
