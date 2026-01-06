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
import Tesseract from "tesseract.js";

const emailError = ref("");
const checkingEmail = ref(false);
const showPassword = ref(false);
const showPasswordConfirmation = ref(false);

const fileId = ref(null);

const file10Back = ref(null);

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

// Step Management
const step = ref(1);
const isProcessing = ref(false); // Spinner state
const file11 = ref(null);
const file12 = ref(null);

const file10Front = ref(null);
const file = ref(null);
const file11Front = ref(null);
const file12Front = ref(null);
const extracted10 = ref({ name: "", lrn: "" });
const extracted11 = ref({ name: "", lrn: "" });
const extracted12 = ref({ name: "", lrn: "" });

const requiredDocs = ref({
    id: { file: null, name: "" },
    nonEnroll: { file: null, name: "" },
    psa: { file: null, name: "" },
    goodMoral: { file: null, name: "" },
    underOath: { file: null, name: "" },
    photo: { file: null, name: "" },
});

const extractedNameId = ref("");
const extractedNameNonEnroll = ref("");
const extractedNamePSA = ref("");
const extractedNameGoodMoral = ref("");
const extractedNameUnderOath = ref("");
const extractedName2x2 = ref("");

const handleRequiredDocUpload = async (event, key, nameRef = null) => {
    const file = event.target.files[0];
    if (file) {
        requiredDocs.value[key].file = file;

        if (nameRef) {
            await extractNameOnly(file, nameRef);
        }
    }
};

const handleGrade10Upload = (event) => {
    file.value = event.target.files[0];
};

const handleGrade11Upload = (event) => {
    file11.value = event.target.files[0];
};

const handleGrade12Upload = (event) => {
    file12.value = event.target.files[0];
};

const handleGrade10FrontUpload = (event) => {
    file10Front.value = event.target.files[0];
    extractTextFromImage(file10Front.value, extracted10);
};

const handleGrade11FrontUpload = (event) => {
    file11Front.value = event.target.files[0];
    extractTextFromImage(file11Front.value, extracted11);
};

const handleGrade12FrontUpload = (event) => {
    file12Front.value = event.target.files[0];
    extractTextFromImage(file12Front.value, extracted12);
};

const handleIdUpload = async (event) => {
    const file = event.target.files[0];
    if (file) {
        fileId.value = file;
        await extractNameOnly(file, extractedNameId);
    }
};

const handleNameUpload = async (event, outputRef) => {
    const file = event.target.files[0];
    if (file) {
        fileId.value = file;
        await extractNameOnly(file, outputRef);
    }
};

const handleNonEnrollUpload = async (event) => {
    const file = event.target.files[0];
    if (file) {
        fileId.value = file;
        await extractNameOnly(file, extractedNameNonEnroll);
    }
};

const handlePSAUpload = async (event) => {
    const file = event.target.files[0];
    if (file) {
        fileId.value = file;
        await extractNameOnly(file, extractedNamePSA);
    }
};

const handleGoodMoralUpload = async (event) => {
    const file = event.target.files[0];
    if (file) {
        fileId.value = file;
        await extractNameOnly(file, extractedNameGoodMoral);
    }
};

const handleUnderOathUpload = async (event) => {
    const file = event.target.files[0];
    if (file) {
        fileId.value = file;
        await extractNameOnly(file, extractedNameUnderOath);
    }
};

const handle2x2Upload = async (event) => {
    const file = event.target.files[0];
    if (file) {
        fileId.value = file;
        await extractNameOnly(file, extractedName2x2);
    }
};

// Form for user registration
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
    file: null,
    english: "",
    mathematics: "",
    science: "",
    firstSem: "",
    secondSem: "",
    g12firstSem: "",
    g12secondSem: "",
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

const extractTextFromImage = async (file, outputRef) => {
    if (!file) return;

    isProcessing.value = true;

    try {
        const {
            data: { text },
        } = await Tesseract.recognize(file, "eng");

        // Basic parsing example for Name and LRN:
        const nameMatch = text.match(/Name\s*[:\-]?\s*(.+)/i);
        const lrnMatch = text.match(/LRN\s*[:\-]?\s*(\d{12})/i);

        outputRef.value.name = nameMatch ? nameMatch[1].trim() : "";
        outputRef.value.lrn = lrnMatch ? lrnMatch[1].trim() : "";
    } catch (error) {
        console.error("Tesseract error:", error);
        alert("Failed to extract text from image.");
    } finally {
        isProcessing.value = false;
    }
};

const handleFileUpload = (event) => {
    //form.file = event.target.files[0];
    //file.value = event.target.files[0];
    file10Back.value = event.target.files[0];
};

// FRONTEND OCR: Extract Grade 10 grades from uploaded file
const extractGrades = async () => {
    if (!file10Front.value) {
        alert("Please upload a grade 10 report file.");
        return;
    }

    isProcessing.value = true;

    try {
        const {
            data: { text },
        } = await Tesseract.recognize(file10Back.value, "eng");

        // Example grade parsing - adjust regex according to your actual text format:
        const englishMatch = text.match(/English\s*[:\-]?\s*(\d{1,3})/i);
        const mathMatch = text.match(/Mathematics\s*[:\-]?\s*(\d{1,3})/i);
        const scienceMatch = text.match(/Science\s*[:\-]?\s*(\d{1,3})/i);

        form.english = englishMatch ? englishMatch[1] : "";
        form.mathematics = mathMatch ? mathMatch[1] : "";
        form.science = scienceMatch ? scienceMatch[1] : "";

        step.value = 4;
    } catch (error) {
        alert("Error extracting grades. Please try again.");
    } finally {
        isProcessing.value = false;
    }
};

// FRONTEND OCR: Extract Grade 11 grades
const extractGrade11 = async () => {
    if (!file11.value) {
        alert("Please upload a grade 11 report file.");
        return;
    }
    isProcessing.value = true;

    try {
        const {
            data: { text },
        } = await Tesseract.recognize(file11.value, "eng");
        console.log("Grade 11 OCR Text:", text);

        // Regex explanation:
        // Matches 'General Average' (case insensitive), optionally with some spaces/punctuation,
        // then captures the number (integer or decimal) that follows (allowing spaces/newlines)
        // The [\s\S]{0,15}? means non-greedy capture of up to 15 characters including line breaks before the number.
        const regex = /General\s*Average[\s\S]{0,15}?(\d{1,3}(\.\d+)?)/gi;

        const matches = [];
        let match;
        while ((match = regex.exec(text)) !== null) {
            matches.push(match[1]);
        }

        form.firstSem = matches[0] || "";
        form.secondSem = matches[1] || "";

        step.value = 6;
    } catch (error) {
        alert("Error extracting Grade 11. Please try again.");
    } finally {
        isProcessing.value = false;
    }
};

// FRONTEND OCR: Extract Grade 12 grades
const extractGrade12 = async () => {
    if (!file12.value) {
        alert("Please upload a grade 12 report file.");
        return;
    }

    isProcessing.value = true;

    try {
        const {
            data: { text },
        } = await Tesseract.recognize(file12.value, "eng");

        // Example parsing:
        const g12firstSemMatch = text.match(
            /First\s*Sem\s*[:\-]?\s*(\d{1,3})/i
        );
        const g12secondSemMatch = text.match(
            /Second\s*Sem\s*[:\-]?\s*(\d{1,3})/i
        );

        form.g12firstSem = g12firstSemMatch ? g12firstSemMatch[1] : "";
        form.g12secondSem = g12secondSemMatch ? g12secondSemMatch[1] : "";

        step.value = 8;
    } catch (error) {
        alert("Error extracting Grade 12. Please try again.");
    } finally {
        isProcessing.value = false;
    }
};

// Extract name only (generic)
const extractNameOnly = async (file, outputRef) => {
    if (!file) return;

    isProcessing.value = true;

    try {
        const {
            data: { text },
        } = await Tesseract.recognize(file, "eng");
        const nameMatch = text.match(/Name\s*[:\-]?\s*(.+)/i);
        outputRef.value = nameMatch ? nameMatch[1].trim() : "";
    } catch (error) {
        console.error("OCR error:", error);
        alert("Failed to extract name from image.");
    } finally {
        isProcessing.value = false;
    }
};

// Form Submission
const submit = async () => {
    await form.post(route("register"), {
        onSuccess: async () => {
            try {
                const formData = new FormData();
                formData.append("email", form.email);

                if (file10Front.value)
                    formData.append("file10Front", file10Front.value);
                //if (form.file) formData.append("file10Back", file.value); // Grade 10 back (optional, if used in backend)
                //if (file.value) formData.append("file10Back", file.value);
                if (file10Back.value)
                    formData.append("file10Back", file10Back.value);

                // Grade 11
                if (file11Front.value)
                    formData.append("file11Front", file11Front.value);
                if (file11.value) formData.append("file11", file11.value); // Grade 11 back

                // Grade 12
                if (file12Front.value)
                    formData.append("file12Front", file12Front.value);
                if (file12.value) formData.append("file12", file12.value); // Grade 12 back

                const docKeysToFieldNames = {
                    id: "fileId",
                    nonEnroll: "fileNonEnroll",
                    psa: "filePSA",
                    goodMoral: "fileGoodMoral",
                    underOath: "fileUnderOath",
                    photo: "filePhoto2x2",
                };

                for (const key in docKeysToFieldNames) {
                    const file = requiredDocs.value[key].file;
                    if (file) {
                        formData.append(docKeysToFieldNames[key], file);
                    }
                }

                // Other admission files
                if (requiredDocs.value.nonEnroll.file)
                    formData.append(
                        "fileNonEnroll",
                        requiredDocs.value.nonEnroll.file
                    );
                if (requiredDocs.value.psa.file)
                    formData.append("filePSA", requiredDocs.value.psa.file);
                if (requiredDocs.value.goodMoral.file)
                    formData.append(
                        "fileGoodMoral",
                        requiredDocs.value.goodMoral.file
                    );
                if (requiredDocs.value.underOath.file)
                    formData.append(
                        "fileUnderOath",
                        requiredDocs.value.underOath.file
                    );
                if (requiredDocs.value.photo.file)
                    formData.append(
                        "filePhoto2x2",
                        requiredDocs.value.photo.file
                    );

                await axios.post("/upload-files", formData, {
                    headers: {
                        "Content-Type": "multipart/form-data",
                        "X-Requested-With": "XMLHttpRequest",
                        Accept: "application/json",
                    },
                });

                // Continue with your grades saving API call, unchanged
                await axios.post(
                    "/api/store-grades",
                    {
                        email: form.email,
                        english: form.english,
                        mathematics: form.mathematics,
                        science: form.science,
                        g11_first_sem: form.firstSem,
                        g11_second_sem: form.secondSem,
                        g12_first_sem: form.g12firstSem,
                        g12_second_sem: form.g12secondSem,
                    },
                    {
                        headers: {
                            "X-Requested-With": "XMLHttpRequest",
                            Accept: "application/json",
                        },
                    }
                );

                alert("Registration successful! Files and grades saved.");
                form.reset("password", "password_confirmation", "file");
                router.visit("/applicant-dashboard");
            } catch (error) {
                console.error("Error uploading files or grades:", error);
                alert("Failed to upload files or grades. Please try again.");
            }
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
                    class="text-5xl font-black bg-gradient-to-r from-[rgba(128,0,0,0.7)] via-black to-orange-500 bg-clip-text text-transparent tracking-wide gradient-flowing-text"
                >
                    PUPT ADMISSION SYSTEM
                </h1>
            </div>
        </template>

        <!-- Step 1: User Information -->
        <form v-if="step === 1" @submit.prevent="step = 2">
            <div class="mb-4 text-xl font-semibold text-white text-center">
                Step 1: Personal Information
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

                <div class="flex items-center justify-end mt-4">
                    <PrimaryButton
                        class="ms-4"
                        :disabled="!!emailError || checkingEmail"
                        >Next</PrimaryButton
                    >
                </div>
            </div>
        </form>

        <!-- Step 2: HighSchool -->
        <form v-if="step === 2" @submit.prevent="step = 3">
            <div class="mb-4 text-xl font-semibold text-white text-center">
                Step 2.1: Senior High School Background
            </div>
            <div class="two-column-form">
                <div>
                    <InputLabel for="school" value="School" />
                    <TextInput
                        id="school"
                        v-model="form.school"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="school"
                        placeholder="e.g. BUKID SENIOR HIGH SCHOOL"
                    />
                    <InputError class="mt-2" :message="form.errors.school" />
                </div>

                <div>
                    <InputLabel for="schoolAdd" value="School Address" />
                    <TextInput
                        id="schoolAdd"
                        v-model="form.schoolAdd"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="schoolAdd"
                        placeholder="e.g. #39 Aguinaldo Street, Bukid, Manila"
                    />
                    <InputError class="mt-2" :message="form.errors.schoolAdd" />
                </div>

                <div>
                    <InputLabel for="schoolyear" value="School Year" />
                    <TextInput
                        id="schoolyear"
                        v-model="form.schoolyear"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="schoolyear"
                        placeholder="e.g. 2022-2025"
                    />
                    <InputError
                        class="mt-2"
                        :message="form.errors.schoolyear"
                    />
                </div>

                <div>
                    <InputLabel for="dateGrad" value="Date Graduated" />
                    <TextInput
                        id="dateGrad"
                        v-model="form.dateGrad"
                        type="date"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="dateGrad"
                    />
                    <InputError class="mt-2" :message="form.errors.dateGrad" />
                </div>

                <div>
                    <InputLabel for="strand" value="Strand" />
                    <select
                        id="strand"
                        v-model="form.strand"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500"
                        required
                        autocomplete="strand"
                    >
                        <option value="" disabled>Select your strand</option>
                        <option value="stem">STEM</option>
                        <option value="hummss">HUMMS</option>
                        <option value="tvl">TVL</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.strand" />
                </div>

                <div>
                    <InputLabel for="track" value="track" />
                    <select
                        id="track"
                        v-model="form.track"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-yellow-500 focus:border-yellow-500"
                        required
                        autocomplete="track"
                    >
                        <option value="" disabled>Select your strand</option>
                        <option value="cookery">COOKERY</option>
                        <option value="ict">ICT</option>
                        <option value="housekeeping">HOUSEKEEPING</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.track" />
                </div>
            </div>
            <div class="flex items-center justify-between mt-4">
                <PrimaryButton @click="step = 1" class="ms-4"
                    >Back</PrimaryButton
                >
                <PrimaryButton class="ms-4">Next</PrimaryButton>
            </div>
        </form>

        <!-- Step 2.1: Grade 10 File Upload -->
        <form v-if="step === 3" @submit.prevent="extractGrades">
            <div class="text-white">Step 2.2 Uploading/Inputing of Grades</div>
            <div class="mt-4">
                <InputLabel
                    for="file10Front"
                    value="Upload Grade 10 Front Image File (Name & LRN)"
                />
                <input
                    type="file"
                    id="file10Front"
                    @change="handleGrade10FrontUpload"
                    accept="image/*"
                    class="mt-1 text-white"
                />
            </div>
            <div class="flex space-x-4 mt-4">
                <div class="w-full md:w-1/2">
                    <InputLabel for="g11Name" value="Extracted Name" />
                    <TextInput
                        id="g11Name"
                        v-model="extracted10.name"
                        type="text"
                        class="mt-1 w-full"
                    />
                </div>
                <div class="w-full md:w-1/2">
                    <InputLabel for="g11LRN" value="Extracted LRN" />
                    <TextInput
                        id="g11LRN"
                        v-model="extracted10.lrn"
                        type="text"
                        class="mt-1 w-full"
                    />
                </div>
            </div>
            <div class="mt-4">
                <InputLabel
                    for="file10Back"
                    value="Upload Grade 10 Back page with grades only"
                />
                <input
                    type="file"
                    id="file10Back"
                    @change="handleFileUpload"
                    accept="image/*"
                    class="mt-1 text-white"
                    required
                    :disabled="isProcessing"
                />
                <InputError class="mt-2" :message="form.errors.file10Back" />
            </div>

            <div
                v-if="isProcessing"
                class="flex justify-center items-center mt-4"
            >
                <span class="ml-2 text-gray-600 font-semibold text-white"
                    >Processing... Please wait</span
                >
            </div>

            <div class="flex items-center justify-between mt-4">
                <PrimaryButton
                    @click="step = 2"
                    class="ms-4"
                    :disabled="isProcessing"
                    >Back</PrimaryButton
                >
                <PrimaryButton class="ms-4" :disabled="isProcessing"
                    >Extract Grades</PrimaryButton
                >
            </div>
        </form>

        <!-- Step 2.2: Grade 10 grades Input from extracted grades -->
        <form v-if="step === 4" @submit.prevent="step = 5">
            <div class="text-white">
                Please review if the uploaded grades are correct. If incorrect,
                edit with correct information before submitting
            </div>
            <div
                class="flex flex-col md:flex-row md:space-x-6 space-y-4 md:space-y-0 mt-4"
            >
                <div class="w-full md:w-1/3">
                    <InputLabel
                        for="english"
                        class="text-white"
                        value="English Grade"
                    />
                    <TextInput
                        id="english"
                        v-model="form.english"
                        type="text"
                        class="mt-1 w-full"
                        required
                    />
                </div>
                <div class="w-full md:w-1/3">
                    <InputLabel
                        for="mathematics"
                        class="text-white"
                        value="Mathematics Grade"
                    />
                    <TextInput
                        id="mathematics"
                        v-model="form.mathematics"
                        type="text"
                        class="mt-1 w-full"
                        required
                    />
                </div>
                <div class="w-full md:w-1/3">
                    <InputLabel
                        for="science"
                        class="text-white"
                        value="Science Grade"
                    />
                    <TextInput
                        id="science"
                        v-model="form.science"
                        type="text"
                        class="mt-1 w-full"
                        required
                    />
                </div>
            </div>
            <div class="flex items-center justify-between mt-4">
                <PrimaryButton @click="step = 3" class="ms-4"
                    >Back</PrimaryButton
                >
                <PrimaryButton class="ms-4">Review & Confirm</PrimaryButton>
            </div>
        </form>

        <!-- Step 2.3: Grade 11 File Upload -->
        <form v-if="step === 5" @submit.prevent="extractGrade11">
            <div class="text-white">
                Step 2.3: Upload Grade 11 page with grades only
            </div>
            <!-- Grade 11 Front Image Upload and Dynamic Fields -->
            <div class="mt-4">
                <InputLabel
                    for="file11Front"
                    value="Upload Grade 11 Front Image File (Name & LRN)"
                />
                <input
                    type="file"
                    id="file11Front"
                    @change="handleGrade11FrontUpload"
                    accept="image/*"
                    class="mt-1 text-white"
                />
            </div>
            <div class="flex space-x-4 mt-4">
                <div class="w-full md:w-1/2">
                    <InputLabel for="g11Name" value="Extracted Name" />
                    <TextInput
                        id="g11Name"
                        v-model="extracted11.name"
                        type="text"
                        class="mt-1 w-full"
                    />
                </div>
                <div class="w-full md:w-1/2">
                    <InputLabel for="g11LRN" value="Extracted LRN" />
                    <TextInput
                        id="g11LRN"
                        v-model="extracted11.lrn"
                        type="text"
                        class="mt-1 w-full"
                    />
                </div>
            </div>
            <div class="mt-4">
                <InputLabel
                    for="file11"
                    value="Upload Grade 11 Back Image File"
                />
                <input
                    type="file"
                    id="file11"
                    @change="handleGrade11Upload"
                    accept="image/*"
                    class="mt-1 text-white"
                    required
                    :disabled="isProcessing"
                />
            </div>
            <div
                v-if="isProcessing"
                class="flex justify-center items-center mt-4"
            >
                <span class="ml-2 text-gray-600 font-semibold text-white"
                    >Processing... Please wait</span
                >
            </div>

            <div class="flex items-center justify-between mt-4">
                <PrimaryButton
                    @click="step = 4"
                    class="ms-4"
                    :disabled="isProcessing"
                    >Back</PrimaryButton
                >
                <PrimaryButton class="ms-4" :disabled="isProcessing"
                    >Extract Grades</PrimaryButton
                >
            </div>
        </form>

        <!-- Step 2.4: Grade 11 grades Input from extracted grades -->
        <form v-if="step === 6" @submit.prevent="step = 7">
            <div class="text-white">
                Please review if the uploaded grades are correct. If incorrect,
                edit with correct information before submitting
            </div>
            <div
                class="flex flex-col md:flex-row md:space-x-6 space-y-4 md:space-y-0 mt-4"
            >
                <div class="w-full md:w-1/3">
                    <InputLabel
                        for="firstSem"
                        class="text-white"
                        value="First Sem"
                    />
                    <TextInput
                        id="firstSem"
                        v-model="form.firstSem"
                        type="text"
                        class="mt-1 w-full"
                        required
                    />
                </div>
                <div class="w-full md:w-1/3">
                    <InputLabel
                        for="secondSem"
                        class="text-white"
                        value="Second Sem"
                    />
                    <TextInput
                        id="secondSem"
                        v-model="form.secondSem"
                        type="text"
                        class="mt-1 w-full"
                        required
                    />
                </div>
            </div>
            <div class="flex items-center justify-between mt-4">
                <PrimaryButton @click="step = 5" class="ms-4"
                    >Back</PrimaryButton
                >
                <PrimaryButton class="ms-4">Review & Confirm</PrimaryButton>
            </div>
        </form>

        <!-- Step 2.5: Grade 12 File Upload -->
        <form v-if="step === 7" @submit.prevent="extractGrade12">
            <div class="text-white">
                Step 2.5: Upload Grade 12 page with grades only
            </div>

            <div class="mt-4">
                <InputLabel
                    for="file12Front"
                    value="Upload Grade 12 Front Image File (Name & LRN)"
                />
                <input
                    type="file"
                    id="file12Front"
                    @change="handleGrade12FrontUpload"
                    accept="image/*"
                    class="mt-1 text-white"
                />
            </div>

            <div class="flex space-x-4 mt-4">
                <div class="w-full md:w-1/2">
                    <InputLabel for="g12Name" value="Extracted Name" />
                    <TextInput
                        id="g12Name"
                        v-model="extracted12.name"
                        type="text"
                        class="mt-1 w-full"
                    />
                </div>
                <div class="w-full md:w-1/2">
                    <InputLabel for="g12LRN" value="Extracted LRN" />
                    <TextInput
                        id="g12LRN"
                        v-model="extracted12.lrn"
                        type="text"
                        class="mt-1 w-full"
                    />
                </div>
            </div>

            <div class="mt-4">
                <InputLabel
                    for="file11"
                    value="Upload Grade 12 Back Image File"
                />
                <input
                    type="file"
                    id="file11"
                    @change="handleGrade12Upload"
                    accept="image/*"
                    class="mt-1 text-white"
                    required
                    :disabled="isProcessing"
                />
            </div>

            <div
                v-if="isProcessing"
                class="flex justify-center items-center mt-4"
            >
                <span class="ml-2 text-gray-600 font-semibold text-white"
                    >Processing... Please wait</span
                >
            </div>

            <div class="flex items-center justify-between mt-4">
                <PrimaryButton
                    @click="step = 6"
                    class="ms-4"
                    :disabled="isProcessing"
                    >Back</PrimaryButton
                >
                <PrimaryButton class="ms-4" :disabled="isProcessing"
                    >Extract Grades</PrimaryButton
                >
            </div>
        </form>

        <!-- Step 2.6: Grade 12 grades Input from extracted grades -->
        <form v-if="step === 8" @submit.prevent="step = 9">
            <div class="text-white">
                Please review if the uploaded grades are correct. If incorrect,
                edit with correct information before submitting
            </div>
            <div
                class="flex flex-col md:flex-row md:space-x-6 space-y-4 md:space-y-0 mt-4"
            >
                <div class="w-full md:w-1/3">
                    <InputLabel
                        for="g12firstSem"
                        class="text-white"
                        value="First Sem"
                    />
                    <TextInput
                        id="g12firstSem"
                        v-model="form.g12firstSem"
                        type="text"
                        class="mt-1 w-full"
                        required
                    />
                </div>
                <div class="w-full md:w-1/3">
                    <InputLabel
                        for="g12secondSem"
                        class="text-white"
                        value="Second Sem"
                    />
                    <TextInput
                        id="g12secondSem"
                        v-model="form.g12secondSem"
                        type="text"
                        class="mt-1 w-full"
                        required
                    />
                </div>
            </div>
            <div class="flex items-center justify-between mt-4">
                <PrimaryButton @click="step = 7" class="ms-4"
                    >Back</PrimaryButton
                >
                <PrimaryButton class="ms-4">Review & Confirm</PrimaryButton>
            </div>
        </form>

        <!-- Step 3: Uploading of Admission Requirements -->
        <form v-if="step === 9" @submit.prevent="step = 10">
            <div class="text-white">
                Please upload all the necessary requirements.
            </div>
            <div class="mt-4">
                <InputLabel for="fileId" value="Upload School ID" />
                <input
                    type="file"
                    id="fileId"
                    @change="
                        (e) => handleRequiredDocUpload(e, 'id', extractedNameId)
                    "
                    accept="image/*"
                    class="mt-1 text-white"
                    :disabled="isProcessing"
                />
            </div>

            <div class="mt-2">
                <InputLabel for="idName" value="Extracted Name from ID" />
                <TextInput
                    id="idName"
                    v-model="extractedNameId"
                    type="text"
                    class="mt-1 w-full"
                />
            </div>

            <!-- Non-Enrollment Certificate Upload -->
            <div class="mt-4">
                <InputLabel
                    for="fileNonEnroll"
                    value="Upload Certificate of Non-Enrollment"
                />
                <input
                    type="file"
                    id="fileNonEnroll"
                    @change="
                        (e) =>
                            handleRequiredDocUpload(
                                e,
                                'nonEnroll',
                                extractedNameNonEnroll
                            )
                    "
                    accept="image/*"
                    class="mt-1 text-white"
                    :disabled="isProcessing"
                />
            </div>

            <div class="mt-2">
                <InputLabel
                    for="nonEnrollName"
                    value="Extracted Name from Certificate"
                />
                <TextInput
                    id="nonEnrollName"
                    v-model="extractedNameNonEnroll"
                    type="text"
                    class="mt-1 w-full"
                />
            </div>

            <!-- PSA Upload -->
            <div class="mt-4">
                <InputLabel
                    for="filePSA"
                    value="Upload PSA Birth Certificate"
                />
                <input
                    type="file"
                    id="filePSA"
                    @change="
                        (e) =>
                            handleRequiredDocUpload(e, 'psa', extractedNameId)
                    "
                    accept="image/*"
                    class="mt-1 text-white"
                    :disabled="isProcessing"
                />
            </div>

            <div class="mt-2">
                <InputLabel for="psaName" value="Extracted Name from PSA" />
                <TextInput
                    id="psaName"
                    v-model="extractedNamePSA"
                    type="text"
                    class="mt-1 w-full"
                />
            </div>

            <!-- Good Moral Upload -->
            <div class="mt-4">
                <InputLabel for="fileGoodMoral" value="Upload Good Moral" />
                <input
                    type="file"
                    id="fileGoodMoral"
                    @change="
                        (e) =>
                            handleRequiredDocUpload(
                                e,
                                'goodMoral',
                                extractedNameId
                            )
                    "
                    accept="image/*"
                    class="mt-1 text-white"
                    :disabled="isProcessing"
                />
            </div>

            <div class="mt-2">
                <InputLabel
                    for="goodMoralName"
                    value="Extracted Name from Good Moral"
                />
                <TextInput
                    id="goodMoralName"
                    v-model="extractedNameGoodMoral"
                    type="text"
                    class="mt-1 w-full"
                />
            </div>

            <!-- Underoath Upload -->
            <div class="mt-4">
                <InputLabel for="fileGoodMoral" value="Upload Good Moral" />
                <input
                    type="file"
                    id="fileUnderOath"
                    @change="
                        (e) =>
                            handleRequiredDocUpload(
                                e,
                                'underOath',
                                extractedNameId
                            )
                    "
                    accept="image/*"
                    class="mt-1 text-white"
                    :disabled="isProcessing"
                />
            </div>

            <div class="mt-2">
                <InputLabel
                    for="underOathName"
                    value="Extracted Name from Under Oath"
                />
                <TextInput
                    id="underOathName"
                    v-model="extractedNameUnderOath"
                    type="text"
                    class="mt-1 w-full"
                />
            </div>

            <!-- Photo Upload -->
            <div class="mt-4">
                <InputLabel for="filePhoto" value="Upload Photo" />
                <input
                    type="file"
                    id="filePhoto2x2"
                    @change="
                        (e) =>
                            handleRequiredDocUpload(e, 'photo', extractedNameId)
                    "
                    accept="image/*"
                    class="mt-1 text-white"
                    :disabled="isProcessing"
                />
            </div>

            <div class="mt-2">
                <InputLabel for="photoName" value="Extracted Name from Photo" />
                <TextInput
                    id="photoName"
                    v-model="extractedName2x2"
                    type="text"
                    class="mt-1 w-full"
                />
            </div>

            <div v-if="isProcessing" class="text-blue-600 font-semibold mt-4">
                Processing document, please wait...
            </div>

            <div class="flex items-center justify-between mt-4">
                <PrimaryButton @click="step = 8" class="ms-4"
                    >Back</PrimaryButton
                >
                <PrimaryButton class="ms-4">Review & Confirm</PrimaryButton>
            </div>
        </form>

        <!-- Step 4: Confirmation Before Submission -->
        <div v-if="step === 10">
            <h2 class="text-lg font-semibold text-white text-center">
                Confirm Your Information
            </h2>
            <ul class="mt-4 text-white text-center">
                <li>
                    <strong>Name:</strong> {{ form.firstname
                    }}{{ form.lastname }}
                </li>
                <li><strong>Email:</strong> {{ form.email }}</li>
                <li><strong>English Grade:</strong> {{ form.english }}</li>
                <li>
                    <strong>Mathematics Grade:</strong> {{ form.mathematics }}
                </li>
                <li><strong>Science Grade:</strong> {{ form.science }}</li>
                <li>
                    <strong>Grade 11 First Sem:</strong> {{ form.firstSem }}
                </li>
                <li>
                    <strong>Grade 11 Second Sem:</strong> {{ form.secondSem }}
                </li>
                <li>
                    <strong>Grade 12 First Sem:</strong> {{ form.firstSem }}
                </li>
                <li>
                    <strong>Grade 12 Second Sem:</strong> {{ form.secondSem }}
                </li>
            </ul>

            <div class="flex items-center justify-between mt-4">
                <PrimaryButton @click="step = 9" class="ms-4"
                    >Back</PrimaryButton
                >
                <PrimaryButton class="ms-4" @click="submit"
                    >Confirm & Submit</PrimaryButton
                >
            </div>
        </div>
    </AuthenticationCard>
</template>