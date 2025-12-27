<script setup>
import { ref, watch } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import axios from "axios";
import AuthenticationCard from "@/Components/AuthenticationCard.vue";
import AuthenticationCardLogo from "@/Components/AuthenticationCardLogo.vue";
import InputError from "@/Components/InputError.vue";
import InputLabel from "@/Components/InputLabel.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import TextInput from "@/Components/TextInput.vue";
import "../../../css/step1-form.css";
import { router } from "@inertiajs/vue3";

const emailError = ref("");
const checkingEmail = ref(false);
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const checkEmailAvailability = async () => {
    emailError.value = "";
    if (!form.email) return;

    checkingEmail.value = true;

    try {
        const response = await axios.post("/check-email", {
            email: form.email,
        });
        if (response.data.taken) {
            emailError.value = "This email is already taken.";
        }
    } catch (error) {
        console.error("Error checking email:", error);
        emailError.value = "Unable to validate email.";
    } finally {
        checkingEmail.value = false;
    }
};

const isProcessing = ref(false); // Spinner state

// Form for user registration
const form = useForm({
    lastname: "",
    firstname: "",
    middlename: "",
    birthday: "",
    sex: "",
    contactnumber: "",
    address: "",
    email: "",
    password: "",
    password_confirmation: "",
});

const enforcePHFormat = (event) => {
    let value = event.target.value;

    // Remove all non-numeric characters
    value = value.replace(/\D/g, "");

    // Limit to 10 digits
    if (value.length > 10) {
        value = value.slice(0, 10);
    }

    // Update the form field
    form.contactnumber = value;

    // Force the input to update visually
    event.target.value = value;
};

// Form Submission
const submit = () => {
    form.post(route("register"), {
        onSuccess: () => {
            alert("Registration successful!");
            form.reset("password", "password_confirmation");
            router.visit("/applicant-dashboard");
        },
        onError: (errors) => {
            console.error("Registration error:", errors);
        },
    });
};

</script>

<template>
    <Head title="Register" />

    <AuthenticationCard wide>
        <template #logo>
            <div class="flex items-center space-x-4">
            <AuthenticationCardLogo />
            <h1
                class="text-5xl font-black text-[#8B0000]"
            >
                PUPT ADMISSION SYSTEM
            </h1>
            </div>
        </template>

        <!-- User Information -->
        <form @submit.prevent="submit">
            <div class="mb-4 text-xl font-semibold text-white text-center">
                Personal Information
            </div>
            <div class="two-column-form">
                <div>
                    <InputLabel for="lastname" value="Lastname" />
                    <TextInput
                        id="lastname"
                        v-model="form.lastname"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="lastname"
                        placeholder="e.g. DELA CRUZ"
                    />
                    <InputError class="mt-2" :message="form.errors.lastname" />
                </div>

                <div>
                    <InputLabel for="firstname" value="Firstname" />
                    <TextInput
                        id="firstname"
                        v-model="form.firstname"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="firstname"
                        placeholder="e.g. JUAN"
                    />
                    <InputError class="mt-2" :message="form.errors.firstname" />
                </div>

                <div>
                    <InputLabel for="middlename" value="Middlename" />
                    <TextInput
                        id="middlename"
                        v-model="form.middlename"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="middlename"
                        placeholder="e.g. SANTOS"
                    />
                    <InputError
                        class="mt-2"
                        :message="form.errors.middlename"
                    />
                </div>

                <div>
                    <InputLabel for="birthday" value="Birthday" />
                    <TextInput
                        id="birthday"
                        v-model="form.birthday"
                        type="date"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="bday"
                    />
                    <InputError class="mt-2" :message="form.errors.birthday" />
                </div>

                <div>
                    <InputLabel for="sex" value="Sex/Gender" />
                    <select
                        id="sex"
                        v-model="form.sex"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500"
                        required
                        autocomplete="sex"
                    >
                        <option value="" disabled>Select your gender</option>
                        <option value="female">♀ Female</option>
                        <option value="male">♂ Male</option>
                        <option value="non-binary">⚧ Non-Binary</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.sex" />
                </div>

                <div class="relative">
                    <InputLabel for="contactnumber" value="Contact number" />

                    <!-- Visual prefix -->
                    <span
                        class="absolute left-3 top-[38px] text-gray-500 text-sm pointer-events-none"
                        >+63</span
                    >

                    <!-- Input field -->
                    <TextInput
                        id="contactnumber"
                        :value="form.contactnumber"
                        type="tel"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        class="mt-1 block w-full pl-12"
                        required
                        autocomplete="tel-national"
                        @input="enforcePHFormat"
                        placeholder="9123456789"
                    />

                    <InputError
                        class="mt-2"
                        :message="form.errors.contactnumber"
                    />
                </div>
                <div>
                    <InputLabel for="address" value="Home Address" />
                    <TextInput
                        id="address"
                        v-model="form.address"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="address"
                        placeholder="e.g. Blk 123 Lot 456 Lower Bicutan, Taguig City"
                    />
                    <InputError class="mt-2" :message="form.errors.address" />
                </div>

                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                        autocomplete="username"
                        placeholder="e.g. example@gmail.com"
                        @blur="checkEmailAvailability"
                    />
                    <InputError
                        class="mt-2"
                        :message="emailError || form.errors.email"
                    />
                </div>

                <div>
                    <InputLabel for="password" value="Password" />

                    <!-- relative wrapper -->
                    <div class="relative">
                        <TextInput
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            class="mt-1 block w-full pr-10"
                            required
                            autocomplete="new-password"
                        />

                        <!-- eye toggle button -->
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute top-1/2 right-3 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none"
                            tabindex="-1"
                            aria-label="Toggle password visibility"
                        >
                            <!-- Eye open -->
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

                            <!-- Eye slash -->
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
                    </div>

                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div>
                    <InputLabel
                        for="password_confirmation"
                        value="Confirm Password"
                    />

                    <!-- wrapper for input + icon -->
                    <div class="relative">
                        <TextInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            :type="
                                showPasswordConfirmation ? 'text' : 'password'
                            "
                            class="mt-1 block w-full pr-10"
                            required
                            autocomplete="new-password"
                        />

                        <!-- eye toggle icon -->
                        <button
                            type="button"
                            @click="
                                showPasswordConfirmation =
                                    !showPasswordConfirmation
                            "
                            class="absolute top-1/2 right-3 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none"
                            tabindex="-1"
                            aria-label="Toggle password confirmation visibility"
                        >
                            <!-- eye open -->
                            <svg
                                v-if="!showPasswordConfirmation"
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

                            <!-- eye slash -->
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
                    </div>

                    <InputError
                        class="mt-2"
                        :message="form.errors.password_confirmation"
                    />
                </div>

                <div class="col-span-2 mt-6">
                    <PrimaryButton
                        class="mx-auto block px-10 py-3 text-center
                            bg-[#FFD700] text-white font-bold rounded-md
                            hover:bg-[#FFC31B] transition"
                        :disabled="!!emailError || checkingEmail"
                    >
                        Register
                    </PrimaryButton>
                </div>
                
            </div>
        </form>
    </AuthenticationCard>
</template>
