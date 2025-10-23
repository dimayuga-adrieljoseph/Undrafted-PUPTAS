<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import { ref } from 'vue';

defineProps({
  canLogin: Boolean,
  canRegister: Boolean,
});

// Feature icons for the sidebar
const featureItems = [
  { title: 'Requirements', icon: 'fa-graduation-cap', content: 'Here are the admission requirements...' },
  { title: 'Campus', icon: 'fa-university', content: 'Details about the campus and facilities...' },
  { title: 'Vision', icon: 'fa-eye', content: 'Our vision is to provide quality education...' },
  { title: 'Mission', icon: 'fa-bullhorn', content: 'Our mission is to...' },
  { title: 'Programs', icon: 'fa-cogs', content: 'We offer a variety of programs such as...' },
  { title: 'Process', icon: 'fa-spinner', content: 'The admission process is simple...' },
  { title: 'How To', icon: 'fa-question-circle', content: 'Here are the steps to apply for admission...' }
];

const hoveredItem = ref(null);
const isHoveringIcon = ref(false);
</script>

<template>
  <Head title="Welcome" />
  
  <!-- Gradient overlay with image -->
  <div class="min-h-screen overflow-x-hidden flex flex-col items-center justify-center text-[#9E122C] dark:text-[#FCECDF] relative background-overlay">
  
    <!-- Logo and Heading -->
    <div class="flex items-center space-x-4 absolute top-12 left-1/2 transform -translate-x-1/2 flex items-center space-x-8 mb-16">
        <ApplicationLogo />
        <h1 class="text-5xl font-black bg-gradient-to-r from-[rgba(128,0,0,0.7)] via-black to-orange-500 bg-clip-text text-transparent tracking-wide gradient-flowing-text custom-text-shadow">
  PUPT ADMISSION SYSTEM
</h1>

      </div>


    <!-- Login/Register Section (Right Aligned) -->
    <div class="absolute right-10 top-1/4 flex flex-col items-center justify-center space-y-4 w-[250px] h-[450px] px-8 py-10 shadow-lg rounded-2xl border-1 border-[#9E122C]">
      <!-- Registration Section -->
      <div class="text-center">
        <h2 class="text-xl font-semibold text-gray-700">Create your account</h2>
        <p class="text-gray-600 mt-2 text-sm">Start your application by creating an account.</p>
      </div>

      <div v-if="canRegister" class="w-full flex justify-center">
        <Link 
          :href="route('register')" 
          class="px-3 py-1 bg-[#9E122C] text-white font-semibold rounded-full shadow-md hover:bg-gray-500 hover:text-gray-700 transition duration-200 max-w-xs"
        >
          Register
        </Link>
      </div>

      <div class="text-center mt-4">
        <p class="text-sm text-gray-600">Already have an account?</p>
      </div>

      <!-- Login Section -->
      <div class="mt-10 text-center">
        <h2 class="text-xl font-semibold text-gray-700">Log in your account</h2>
      </div>

      <div v-if="canLogin" class="w-full flex justify-center">
        <Link 
          :href="route('login')" 
          class="px-3 py-1 border-2 border-[#9E122C] text-gray-700 font-semibold rounded-full shadow-md hover:bg-[#9E122C] hover:text-white transition duration-200 max-w-xs"
        >
          Log In
        </Link>
      </div>
    </div>

    <!-- Sidebar Icons -->
    <div class="fixed left-10 top-1/4 space-y-8 z-30">
      <div class="flex flex-col items-center space-y-6">
        <div 
          v-for="(item, index) in featureItems" 
          :key="index" 
          class="group relative" 
          @mouseenter="hoveredItem = item; isHoveringIcon = true"
          @mouseleave="hoveredItem = null; isHoveringIcon = false"
        >
          <!-- Trailing Background Effect -->
          <div class="bg-color-effect absolute top-0 left-0 bg-[#9E122C] opacity-0 transition-all duration-300 ease-in-out transform scale-0 origin-left"></div>

          <!-- Icon -->
          <div class="icon-btn flex justify-center items-center bg-gray-400 hover:bg-[#9E122C] p-4 rounded-full shadow-md text-white text-2xl cursor-pointer relative z-10 transition-all duration-300 ease-in-out">
            <i :class="['fa', item.icon]"></i>
          </div>

          <!-- Tooltip -->
          <div class="absolute left-full ml-3 top-1/2 -translate-y-1/2 whitespace-nowrap bg-yellow-500 text-white text-sm font-semibold px-3 py-1 rounded shadow-md opacity-0 group-hover:opacity-100 transition duration-300 z-20">
            {{ item.title }}
          </div>
        </div>
      </div>
    </div>

    <!-- Centered Card on Hover -->
    <div v-if="hoveredItem" class="fixed inset-0 flex items-center justify-center z-40 pointer-events-none">
      <div class="bg-gray-100 text-gray-700 p-6 rounded-lg shadow-lg max-w-md w-full transition-all duration-300 pointer-events-auto">
        <h2 class="text-2xl font-bold mb-2">{{ hoveredItem.title }}</h2>
        <p>{{ hoveredItem.content }}</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.background-overlay {
  background: radial-gradient(circle, rgba(168, 167, 167, 0.6), rgba(223, 223, 223, 0.6), rgba(255, 254, 254, 0.6)), url('../../../public/assets/images/4.jpg');
  background-size: cover;
  background-position: center;
}

/* Other styles */
.bg-gradient-to-r {
  background-image: linear-gradient(to right, #9E122C, #F99D90);
}

.icon-btn {
  position: relative;
  transition: transform 0.3s ease-in-out;
}

.icon-btn:hover {
  transform: translateX(60px);
}

.bg-color-effect {
  opacity: 0;
  width: 200%;
  height: 100%;
  transition: opacity 0.3s ease, transform 0.3s ease;
  position: absolute;
  z-index: -1;
  border-radius: 20px;
  transform: scale(0);
}

.group:hover .bg-color-effect {
  opacity: 1;
  transform: scale(1);
}

.group:hover .icon-btn {
  transform: translateX(60px);
}

.gradient-flowing-text {
  background: linear-gradient(90deg, rgba(128, 0, 0, 0.8), rgba(211, 0, 0, 0.7));
  background-size: 200% 100%;
  background-clip: text;
  color: transparent;
  -webkit-background-clip: text;
  animation: gradientFlow 5s ease-in-out infinite;
}

@keyframes gradientFlow {
  0% {
    background-position: 100% 0;
  }
  25% {
    background-position: 0% 50%;
  }
  50% {
    background-position: 100% 100%;
  }
  75% {
    background-position: 0% 50%;
  }
  100% {
    background-position: 100% 0;
  }
}

.custom-text-shadow {
  filter: drop-shadow(2px 2px 3px rgba(0, 0, 0, 0.6));
}

</style>
