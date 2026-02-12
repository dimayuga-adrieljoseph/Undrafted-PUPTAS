<script setup>
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

import TermsOfServiceModal from '@/Pages/Modal/TermsofServiceModal.vue'
import PrivacyPolicyModal from '@/Pages/Modal/PrivacyPolicyModal.vue'

// Modal control variables
const showTermsModal = ref(false);
const showPrivacyModal = ref(false);

const form = useForm({
    lastname: "",
    firstname: "",
    middlename: "",
    birthday: "",
    sex: "",
    contactnumber: "",
    address: "",
    school: "",
    schoolAdd: "",
    schoolyear: "",
    dateGrad: "",
    strand: "",
    track: "",
    email: "",
    password: "",
    password_confirmation: "",
    terms: false,
});

// Modal control functions
const openTermsModal = () => {
    showTermsModal.value = true;
};

const openPrivacyModal = () => {
    showPrivacyModal.value = true;
};

const closeTermsModal = () => {
    showTermsModal.value = false;
};

const closePrivacyModal = () => {
    showPrivacyModal.value = false;
};

// Submit function
const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Register - PUPT Admission Portal" />
    
    <!-- Use imported modal components -->
    <TermsOfServiceModal :show="showTermsModal" @close="closeTermsModal" />
    <PrivacyPolicyModal :show="showPrivacyModal" @close="closePrivacyModal" />
    
    <div class="min-h-screen bg-white-500 flex flex-col items-center justify-center p-4">
        <!-- Main Container -->
        <div class="relative w-full max-w-6xl bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden border border-gray-200 dark:border-gray-700">
            <!-- Header Section with School Colors -->
            <div class="bg-red-800 p-8">
                <div class="flex flex-col md:flex-row items-center justify-between">
                    <div class="text-center md:text-left">
                        <div class="flex items-center justify-center md:justify-start space-x-3">
                            <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-lg">
                                <span class="text-2xl font-bold text-red-800">P</span>
                            </div>
                            <div>
                                <h1 class="text-3xl md:text-4xl font-bold text-white tracking-tight">PUP-T Admission</h1>
                                <p class="text-yellow-200 font-medium">Polytechnic University of the Philippines - Taguig</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <div class="bg-white/10 backdrop-blur-sm rounded-lg px-4 py-2 text-center">
                            <p class="text-yellow-200 font-semibold">Registration</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registration Form -->
            <div class="p-8 md:p-10">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left Side - Form Sections -->
                    <div class="lg:col-span-2">
                        <form @submit.prevent="submit" class="space-y-8">
                            <!-- Section 1: Personal Information -->
                            <div class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-6 border border-gray-200 dark:border-gray-700 shadow-sm">
                                <div class="flex items-center mb-6">
                                    <div class="w-10 h-10 bg-gradient-to-br from-red-100 to-red-50 dark:from-red-900/30 dark:to-red-800/20 rounded-lg flex items-center justify-center mr-4">
                                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Personal Information</h2>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Your basic personal details</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            Last Name <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="form.lastname"
                                            type="text"
                                            required
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                            placeholder="Enter your last name"
                                        >
                                        <div v-if="form.errors.lastname" class="text-red-500 text-sm mt-1 flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                            </svg>
                                            {{ form.errors.lastname }}
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            First Name <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="form.firstname"
                                            type="text"
                                            required
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                            placeholder="Enter your first name"
                                        >
                                        <div v-if="form.errors.firstname" class="text-red-500 text-sm mt-1 flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                            </svg>
                                            {{ form.errors.firstname }}
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Middle Name</label>
                                        <input
                                            v-model="form.middlename"
                                            type="text"
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                            placeholder="Enter your middle name"
                                        >
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            Birthday <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="form.birthday"
                                            type="date"
                                            required
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                        >
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            Gender <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            v-model="form.sex"
                                            required
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                        >
                                            <option value="" disabled>Select Gender</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                            <option value="prefer-not-to-say">Prefer not to say</option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            Contact Number <span class="text-red-500">*</span>
                                        </label>
                                        <div class="flex">
                                            <span class="inline-flex items-center px-4 bg-gray-100 dark:bg-gray-700 border border-r-0 border-gray-300 dark:border-gray-600 rounded-l-lg text-gray-700 dark:text-gray-300">
                                                +63
                                            </span>
                                            <input
                                                v-model="form.contactnumber"
                                                type="tel"
                                                required
                                                placeholder="912 345 6789"
                                                class="flex-1 rounded-r-lg border border-gray-300 dark:border-gray-600 px-4 py-3 focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                            >
                                        </div>
                                    </div>

                                    <div class="md:col-span-2 space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            Complete Address <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="form.address"
                                            type="text"
                                            required
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                            placeholder="House No., Street, Barangay, City, Province"
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Academic Information -->
                            <div class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-6 border border-gray-200 dark:border-gray-700 shadow-sm">
                                <div class="flex items-center mb-6">
                                    <div class="w-10 h-10 bg-gradient-to-br from-yellow-100 to-yellow-50 dark:from-yellow-900/30 dark:to-yellow-800/20 rounded-lg flex items-center justify-center mr-4">
                                        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" opacity="0.5"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14v6l9-5M12 20l-9-5"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Academic Information</h2>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Your Senior High School background</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            School Name <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="form.school"
                                            type="text"
                                            required
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                            placeholder="Name of your senior high school"
                                        >
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            School Address <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="form.schoolAdd"
                                            type="text"
                                            required
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                            placeholder="Complete school address"
                                        >
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            School Year <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="form.schoolyear"
                                            type="text"
                                            required
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                            placeholder="2023-2024"
                                        >
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            Expected Graduation <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="form.dateGrad"
                                            type="date"
                                            required
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                        >
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            Strand <span class="text-red-500">*</span>
                                        </label>
                                        <select
                                            v-model="form.strand"
                                            required
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                        >
                                            <option value="" disabled>Select Strand</option>
                                            <option value="STEM">STEM (Science, Technology, Engineering & Mathematics)</option>
                                            <option value="HUMSS">HUMSS (Humanities & Social Sciences)</option>
                                            <option value="ABM">ABM (Accountancy, Business & Management)</option>
                                            <option value="TVL">TVL (Technical-Vocational-Livelihood)</option>
                                            <option value="ICT">ICT (Information and Communications Technology)</option>
                                            <option value="GAS">GAS (General Academic Strand)</option>
                                            <option value="SPORTS">Sports Track</option>
                                            <option value="ARTS">Arts & Design Track</option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Specialization/Track</label>
                                        <input
                                            v-model="form.track"
                                            type="text"
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                            placeholder="e.g., ICT Programming, Cookery, Animation"
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Section 3: Account Credentials -->
                            <div class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-6 border border-gray-200 dark:border-gray-700 shadow-sm">
                                <div class="flex items-center mb-6">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-100 to-blue-50 dark:from-blue-900/30 dark:to-blue-800/20 rounded-lg flex items-center justify-center mr-4">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-800 dark:text-white">Account Credentials</h2>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Create your login credentials</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="md:col-span-2 space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            Email Address <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="form.email"
                                            type="email"
                                            required
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                            placeholder="your.email@example.com"
                                        >
                                        <div v-if="form.errors.email" class="text-red-500 text-sm mt-1 flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                            </svg>
                                            {{ form.errors.email }}
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            This will be your username for login and official communication
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            Password <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="form.password"
                                            type="password"
                                            required
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                            placeholder="Create a strong password"
                                        >
                                        <div v-if="form.errors.password" class="text-red-500 text-sm mt-1 flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                            </svg>
                                            {{ form.errors.password }}
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Minimum 8 characters with letters and numbers
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">
                                            Confirm Password <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            v-model="form.password_confirmation"
                                            type="password"
                                            required
                                            class="w-full px-4 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 dark:focus:ring-red-400 dark:focus:border-red-400 transition-all duration-200"
                                            placeholder="Re-enter your password"
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800/50 dark:to-gray-900/50 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                                <div class="flex items-start space-x-3">
                                    <input
                                        v-model="form.terms"
                                        type="checkbox"
                                        required
                                        id="terms"
                                        class="mt-1 w-5 h-5 text-red-600 bg-gray-100 border-gray-300 rounded focus:ring-red-500 dark:focus:ring-red-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                    >
                                    <div>
                                        <label for="terms" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            I agree to the 
                                            <button 
                                                type="button"
                                                @click="openTermsModal"
                                                class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 font-semibold underline focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 rounded"
                                            >
                                                Terms of Service
                                            </button>, 
                                            <button 
                                                type="button"
                                                @click="openPrivacyModal"
                                                class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 font-semibold underline focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 rounded"
                                            >
                                                Privacy Policy
                                            </button>, and understand that:
                                        </label>
                                        <ul class="mt-2 text-sm text-gray-600 dark:text-gray-400 space-y-1 pl-4">
                                            <li class="flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                                All information provided is accurate and truthful
                                            </li>
                                            <li class="flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                                I will provide required documents in the applicant dashboard
                                            </li>
                                            <li class="flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                </svg>
                                                I understand the admission process and requirements
                                            </li>
                                        </ul>
                                        <div v-if="form.errors.terms" class="text-red-500 text-sm mt-2 flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                            </svg>
                                            {{ form.errors.terms }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4">
                                <Link
                                    :href="route('login')"
                                    class="flex items-center text-gray-600 dark:text-gray-400 hover:text-red-700 dark:hover:text-red-400 font-medium transition-colors duration-200"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                    </svg>
                                    Back to Login
                                </Link>

                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="w-full sm:w-auto px-8 py-3.5 bg-gradient-to-r from-red-700 via-red-600 to-yellow-600 hover:from-red-800 hover:via-red-700 hover:to-yellow-700 text-white font-bold rounded-lg shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                                >
                                    <div class="flex items-center justify-center">
                                        <svg v-if="form.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>{{ form.processing ? 'Creating Account...' : 'Complete Registration' }}</span>
                                    </div>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Right Side - Info Panel -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-8 space-y-6">
                            <!-- Quick Help Card -->
                            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-6 border border-blue-200 dark:border-blue-800">
                                <div class="flex items-center mb-4">
                                    <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center mr-3">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">Need Help?</h3>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                    Having trouble with registration? Here's what you need to know:
                                </p>
                                <ul class="space-y-3">
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Ensure all required fields marked with * are filled</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Use a valid email you have access to</span>
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="text-sm text-gray-700 dark:text-gray-300">Check your email for verification after registration</span>
                                    </li>
                                </ul>
                                <div class="mt-6">
                                    <a href="mailto:admissions@pupt.edu.ph" class="inline-flex items-center text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                        Contact Admissions Office
                                    </a>
                                </div>
                            </div>

                            <!-- Next Steps Card -->
                            <div class="bg-gradient-to-br from-red-50 to-orange-50 dark:from-red-900/20 dark:to-orange-900/20 rounded-xl p-6 border border-red-200 dark:border-red-800">
                                <div class="flex items-center mb-4">
                                    <div class="w-10 h-10 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center mr-3">
                                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">After Registration</h3>
                                </div>
                                <div class="space-y-4">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 w-8 h-8 bg-white dark:bg-gray-800 rounded-full border-2 border-red-200 dark:border-red-700 flex items-center justify-center mr-3">
                                            <span class="text-sm font-bold text-red-600 dark:text-red-400">1</span>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-800 dark:text-white text-sm">Verify Email</h4>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Check your inbox for verification link</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 w-8 h-8 bg-white dark:bg-gray-800 rounded-full border-2 border-red-200 dark:border-red-700 flex items-center justify-center mr-3">
                                            <span class="text-sm font-bold text-red-600 dark:text-red-400">2</span>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-800 dark:text-white text-sm">Complete Profile</h4>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Upload required documents in your dashboard</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 w-8 h-8 bg-white dark:bg-gray-800 rounded-full border-2 border-red-200 dark:border-red-700 flex items-center justify-center mr-3">
                                            <span class="text-sm font-bold text-red-600 dark:text-red-400">3</span>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-gray-800 dark:text-white text-sm">Track Application</h4>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Monitor your admission status online</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Security Notice -->
                            <div class="bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800/50 dark:to-gray-900/50 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
                                <div class="flex items-center mb-3">
                                    <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <h4 class="font-bold text-gray-800 dark:text-white">Secure & Confidential</h4>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                    Your information is protected with 256-bit SSL encryption. We never share your data with third parties.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 p-6">
                <div class="flex flex-col md:flex-row items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                    <div class="mb-4 md:mb-0">
                        <p>© 2024 Polytechnic University of the Philippines - Taguig. All rights reserved.</p>
                    </div>
                    <div class="flex items-center space-x-6">
                        <button 
                            @click="openPrivacyModal"
                            class="hover:text-red-600 dark:hover:text-red-400 transition-colors focus:outline-none"
                        >
                            Privacy Policy
                        </button>
                        <button 
                            @click="openTermsModal"
                            class="hover:text-red-600 dark:hover:text-red-400 transition-colors focus:outline-none"
                        >
                            Terms of Service
                        </button>
                        <a href="#" class="hover:text-red-600 dark:hover:text-red-400 transition-colors">Help Center</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Login Link at Bottom -->
        <div class="mt-8 text-center">
            <p class="text-gray-600 dark:text-gray-400">
                Already have an account?
                <Link :href="route('login')" class="font-semibold text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 ml-1 underline">
                    Sign in here
                </Link>
            </p>
        </div>
    </div>
</template>

<style>
.animation-delay-2000 {
    animation-delay: 2s;
}

.animation-delay-4000 {
    animation-delay: 4s;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Dark mode scrollbar */
.dark ::-webkit-scrollbar-track {
    background: #374151;
}

.dark ::-webkit-scrollbar-thumb {
    background: #6b7280;
}

.dark ::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

/* Form input focus effects */
input:focus, select:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.dark input:focus, .dark select:focus {
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
}

/* Smooth transitions */
* {
    transition: background-color 0.3s ease, border-color 0.3s ease;
}

/* Modal animation */
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>