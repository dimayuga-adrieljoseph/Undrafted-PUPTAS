<script setup>
import { ref } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import AuthenticationCardLogo from "@/Components/AuthenticationCardLogo.vue";
import Checkbox from "@/Components/Checkbox.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";

// State to toggle password visibility
const showPassword = ref(false);

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        remember: form.remember ? "on" : "",
    })).post(route("login"), {
        onFinish: () => form.reset("password"),
    });
};
</script>

<template>
    <Head title="Log in" />

    <div
        class="relative w-screen min-h-screen bg-cover bg-center bg-[url('/assets/images/2.jpg')]"
    >
        <!-- Optional overlay -->
        <div
            class="absolute inset-0 bg-white/40 backdrop-blur-[10px] saturate-[168%]"
        ></div>

        <div
            class="absolute right-0 top-0 h-full w-full md:w-[30%] z-10 flex justify-center items-center px-6 sm:px-10 p-6 transition-all"
            style=" background: rgba(139, 0, 0, 0.7); "
        >
            <div class="w-full max-w-md">
                <!-- bg-white/25 rounded-lg shadow-lg p-8 md:p-10 -->
                <!-- Logo -->
                <div class="flex justify-center mb-6">
                    <AuthenticationCardLogo />
                </div>

                <!-- Heading -->
                <h2 class="text-2xl font-bold text-center text-[white] mb-6">
                    Log In
                </h2>

                <!-- Status Message -->
                <!-- <div
                    v-if="status"
                    class="mb-4 font-medium text-sm text-green-600"
                >
                    {{ status }}
                </div> -->

                <!-- Login Form -->
                <form @submit.prevent="submit" class="space-y-4 relative">
                    <!-- Loading spinner overlay -->
                    <div
                        v-if="form.processing"
                        class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-lg z-20"
                        aria-live="polite"
                    >
                        <svg
                            class="animate-spin h-10 w-10 text-[#800000]"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                            ></path>
                        </svg>
                    </div>

                    <!-- Email -->
                    <div>
                        <InputLabel
                            for="email"
                            value="Email"
                            class="text-[#800000]"
                        />
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="mt-1 block w-full bg-white border border-gray-300 rounded-md px-3 py-2 focus:ring-[#800000] focus:border-[#800000]"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="Enter your email"
                            :disabled="form.processing"
                        />
                        <InputError
                            class="mt-2 text-[#800000]"
                            :message="form.errors.email"
                        />
                    </div>

                    <!-- Password -->
                    <div class="relative">
                        <InputLabel
                            for="password"
                            value="Password"
                            class="text-[#800000]"
                        />
                        <TextInput
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            class="mt-1 block w-full bg-white border border-gray-300 rounded-md px-3 py-2 focus:ring-[#800000] focus:border-[#800000]"
                            required
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            :disabled="form.processing"
                        />
                        <!-- Eye icon for toggle -->
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-[38px] text-gray-500 hover:text-gray-700 focus:outline-none"
                            tabindex="-1"
                            aria-label="Toggle password visibility"
                        >
                            <svg
                                v-if="!showPassword"
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                />
                            </svg>
                            <svg
                                v-else
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.963 9.963 0 012.218-3.325m1.964-1.964A9.955 9.955 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.995 9.995 0 01-4.04 5.045M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M3 3l18 18"
                                />
                            </svg>
                        </button>
                        <InputError
                            class="mt-2 text-[#800000]"
                            :message="form.errors.password"
                        />
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center">
                        <Checkbox
                            v-model:checked="form.remember"
                            name="remember"
                            class="border-[#800000] focus:ring-[#800000]"
                            :disabled="form.processing"
                        />
                        <span class="ms-2 text-sm text-[white]"
                            >Remember me</span
                        >
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col space-y-3 mt-4">
                        <Link
                            v-if="canResetPassword"
                            :href="route('password.request')"
                            class="text-sm text-[#DC3545] font-semibold hover:text-[#800000] text-center"
                        >
                            Forgot your password?
                        </Link>

                        <PrimaryButton
                            class="w-full py-3 bg-[#FFD700] text-white font-bold rounded-md hover:bg-[#FFC31B] transition flex items-center justify-center"
                            :class="{
                                'opacity-25 cursor-not-allowed':
                                    form.processing,
                            }"
                            :disabled="form.processing"
                        >
                            LOG IN
                        </PrimaryButton>
                    </div>
                </form>

                <!-- Register Link -->
                <div class="mt-4 text-center">
                    <span class="text-sm text-[white]">No account yet?</span>
                    <Link
                        :href="route('register')"
                        class="ml-1 text-sm font-semibold text-[#FFD700] hover:text-[#FFC31B] underline"
                    >
                        Sign up now.
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
