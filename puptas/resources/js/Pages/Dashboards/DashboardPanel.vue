<template>
  <Head title="Dashboard" />
  <AppLayout>
    <div class="flex justify-between items-center mb-6">
  <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-200 leading-tight">
    Dashboard
  </h2>
  <!-- Profile Avatar with Name -->
  <div class="flex items-center space-x-3 bg-white dark:bg-gray-900 px-4 py-2 rounded-full shadow">
    <svg class="w-8 h-8 stroke-[#9E122C]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
      stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M12 14c3.866 0 7 3.134 7 7H5c0-3.866 3.134-7 7-7z" />
      <circle cx="12" cy="7" r="4" />
    </svg>
    <div class="text-sm">
      <p class="font-medium text-[#9E122C]">John Doe</p>
      <p class="text-gray-600 dark:text-gray-300">Administrator</p>
    </div>
  </div>
</div>

<!-- Professional Summary Boxes Section -->
<div class="flex flex-wrap justify-center gap-[6rem] px-4 py-6">
  <div class="w-[180px] h-[180px] bg-[#FCECDF] rounded-2xl shadow-md shadow-red-500 flex flex-col items-center justify-center text-center hover:scale-105 transition-transform duration-300">
    <div class="bg-[#9E122C] p-4 rounded-full mb-3">
      <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18" />
      </svg>
    </div>
    <p class="text-gray-700 text-base font-medium">Total Applications</p>
    <p class="text-3xl font-bold text-gray-900">120</p>
  </div>

  <div class="w-[180px] h-[180px] bg-[#FCECDF] rounded-2xl shadow-md shadow-red-500 flex flex-col items-center justify-center text-center hover:scale-105 transition-transform duration-300">
    <div class="bg-[#9E122C] p-4 rounded-full mb-3">
      <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
      </svg>
    </div>
    <p class="text-gray-700 text-base font-medium">Accepted</p>
    <p class="text-3xl font-bold text-gray-900">80</p>
  </div>

  <div class="w-[180px] h-[180px] bg-[#FCECDF] rounded-2xl shadow-md shadow-red-500 flex flex-col items-center justify-center text-center hover:scale-105 transition-transform duration-300">
    <div class="bg-[#9E122C] p-4 rounded-full mb-3">
      <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3" />
      </svg>
    </div>
    <p class="text-gray-700 text-base font-medium">Pending</p>
    <p class="text-3xl font-bold text-gray-900">30</p>
  </div>

  <div class="w-[180px] h-[180px] bg-[#FCECDF] rounded-2xl shadow-md shadow-red-500 flex flex-col items-center justify-center text-center hover:scale-105 transition-transform duration-300">
    <div class="bg-[#9E122C] p-4 rounded-full mb-3">
      <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </div>
    <p class="text-gray-700 text-base font-medium">Rejected</p>
    <p class="text-3xl font-bold text-gray-900">10</p>
  </div>
</div>



    <div class="py-8 px-4 md:px-6 lg:px-8 max-w-7xl mx-auto">
      


      <div class="flex flex-col md:flex-row gap-6">

        <!-- Applications Overview Chart -->
        <div class="flex-1 bg-white dark:bg-gray-800 p-6 rounded-xl shadow">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Applications Overview</h3>
        <LineChart :chart-data="chartData" class="h-60 w-full" />
        </div>

        <!-- Recent Applications Table -->
        <div class="flex-1 bg-white dark:bg-gray-800 p-6 rounded-xl shadow">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Applications</h3>
          <table class="min-w-full text-sm">
            <thead>
              <tr>
                <th class="text-left text-gray-500 pb-2">Name</th>
                <th class="text-left text-gray-500 pb-2">Course</th>
                <th class="text-left text-gray-500 pb-2">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="user in displayedUsers" :key="user.id" @click="selectUser(user)" class="cursor-pointer hover:bg-[#FBCB77]">
                <td class="py-2 text-gray-800 dark:text-gray-100">{{ getFullName(user) }}</td>
                <td class="py-2 text-gray-600 dark:text-gray-300">{{ user.course || '—' }}</td>
                <td class="py-2">
                  <span :class="getStatusClass(user.status)" class="px-2 py-1 rounded text-xs font-semibold">
                    {{ user.status || 'Unknown' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal Panel for User Info -->
      <transition name="slide-fade">
        <div
          v-if="selectedUser"
          class="fixed top-0 right-0 w-full md:w-1/3 h-full bg-[#FBCB77] dark:bg-gray-900 p-6 z-50 shadow-xl transition duration-300 ease-in-out overflow-y-auto"
        >
          <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">User Information</h3>
          <p class="text-gray-800 dark:text-gray-200 font-medium">Name: {{ getFullName(selectedUser) }}</p>
          <p class="text-gray-700 dark:text-gray-400">Email: {{ selectedUser.email }}</p>
          <p class="text-gray-700 dark:text-gray-400">Username: {{ selectedUser.username }}</p>
          <p class="text-gray-700 dark:text-gray-400">Phone: {{ selectedUser.phone }}</p>
          <p class="text-gray-700 dark:text-gray-400">Company: {{ selectedUser.company?.name }}</p>
          <button
            class="mt-6 px-4 py-2 rounded bg-[#9E122C] text-white hover:bg-[#EE6A43] transition"
            @click="closeUserCard"
          >Close</button>
        </div>
      </transition>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { LineChart } from "vue-chart-3";
import { Head } from '@inertiajs/vue3';
import AppLayout from "@/Layouts/AppLayout.vue";
import {
  Chart as ChartJS,
  LineController,
  LineElement,
  CategoryScale,
  LinearScale,
  PointElement,
  Tooltip,
  Title,
  Legend
} from 'chart.js';

ChartJS.register(
  LineController,
  LineElement,
  CategoryScale,
  LinearScale,
  PointElement,
  Tooltip,
  Title,
  Legend
);

const users = ref([]);
const selectedUser = ref(null);
const isLoading = ref(true);
const errorMessage = ref("");
const searchQuery = ref("");

const getFullName = (user) => `${user.firstname || ''} ${user.lastname || ''}`.trim();


const chartData = {
  labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
  datasets: [
    {
      label: 'Submitted',
      data: [5, 20, 35, 50, 70, 90],
      borderColor: '#2563EB',
      backgroundColor: 'rgba(37, 99, 235, 0.2)',
      tension: 0.4,
    },
    {
      label: 'Accepted',
      data: [2, 10, 15, 25, 40, 60],
      borderColor: '#10B981',
      backgroundColor: 'rgba(16, 185, 129, 0.2)',
      tension: 0.4,
    }
  ]
};

const getStatusClass = (status) => {
  const s = (status || '').toLowerCase();
  if (s === 'accepted') return 'bg-green-100 text-green-700';
  if (s === 'pending') return 'bg-yellow-100 text-yellow-700';
  if (s === 'rejected') return 'bg-red-100 text-red-700';
  return 'bg-gray-100 text-gray-600';
};

const fetchUsers = async () => {
  try {
    const response = await fetch("/dashboard/users", {
      headers: {
        "Accept": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });
    if (!response.ok) throw new Error("Failed to fetch users");
    users.value = await response.json();
  } catch (error) {
    errorMessage.value = error.message;
  } finally {
    isLoading.value = false;
  }
};

onMounted(fetchUsers);

const filteredUsers = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();
  if (!query) return users.value;

  return users.value.filter(user => {
    // Combine first and last name into fullName
    const fullName = `${user.firstname || ''} ${user.lastname || ''}`.toLowerCase();
    return fullName.includes(query);
  });
});


const displayedUsers = computed(() => {
  if (searchQuery.value.trim()) return filteredUsers.value;
  return users.value.slice(0, 4);
});

const selectUser = (user) => {
  selectedUser.value = user;
};

const closeUserCard = () => {
  selectedUser.value = null;
};
</script>

<style scoped>
.slide-fade-enter-active,
.slide-fade-leave-active {
  transition: all 0.3s ease;
}
.slide-fade-enter-from,
.slide-fade-leave-to {
  transform: translateX(100%);
  opacity: 0;
}
</style>