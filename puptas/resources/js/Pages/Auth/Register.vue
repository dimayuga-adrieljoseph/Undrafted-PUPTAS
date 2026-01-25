<script setup>
import { ref, watch } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
const axios = window.axios;
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
    // Add loading feedback
    isProcessing.value = true;
    
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
            } finally {
                isProcessing.value = false;
            }
        },
        onError: (errors) => {
            console.error("Registration error:", errors);
            isProcessing.value = false;
            
            // Show user-friendly error messages
            const errorMessages = Object.values(errors).flat().join('\n');
            alert(`Registration failed:\n\n${errorMessages || 'Please check your inputs and try again.'}`);
        },
        onFinish: () => {
            isProcessing.value = false;
        }
    });
};
</script>

<template>
    <Head title="Applicant Registration" />

    <AuthenticationCard wide>
        <!-- HEADER -->
        <template #logo>
            <div class="flex flex-col items-center space-y-2">
                <AuthenticationCardLogo />
                <h1
                    class="text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-red-800 to-yellow-500"
                >
                    PUPT Admission Portal
                </h1>
                <p class="text-sm text-gray-400">
                    Secure Applicant Registration System
                </p>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 mt-8">
            <!-- STEPPER -->
            <aside class="lg:col-span-1">
                <ol class="space-y-4 text-sm">
                    <li v-for="n in 10" :key="n"
                        class="flex items-center space-x-2"
                        :class="step === n ? 'text-yellow-400 font-semibold' : 'text-gray-400'"
                    >
                        <span
                            class="w-6 h-6 flex items-center justify-center rounded-full border"
                            :class="step >= n ? 'border-yellow-400 bg-yellow-400 text-black' : 'border-gray-600'"
                        >
                            {{ n }}
                        </span>
                        <span>
                            {{
                                [
                                    'Personal Info',
                                    'School Info',
                                    'Grade 10 Upload',
                                    'Grade 10 Review',
                                    'Grade 11 Upload',
                                    'Grade 11 Review',
                                    'Grade 12 Upload',
                                    'Grade 12 Review',
                                    'Requirements',
                                    'Confirmation',
                                ][n - 1]
                            }}
                        </span>
                    </li>
                </ol>
            </aside>

            <!-- CONTENT -->
            <section class="lg:col-span-3 bg-gray-900 rounded-xl p-8 shadow-lg">

                <!-- STEP 1 -->
                <form v-if="step === 1" @submit.prevent="step = 2">
                    <h2 class="text-xl font-bold text-white mb-6">
                        Personal Information
                    </h2>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <InputLabel value="Lastname" />
                            <TextInput v-model="form.lastname" required />
                        </div>
                        <div>
                            <InputLabel value="Firstname" />
                            <TextInput v-model="form.firstname" required />
                        </div>
                        <div>
                            <InputLabel value="Middlename" />
                            <TextInput v-model="form.middlename" required />
                        </div>
                        <div>
                            <InputLabel value="Birthday" />
                            <TextInput type="date" v-model="form.birthday" required />
                        </div>

                        <div>
                            <InputLabel value="Gender" />
                            <select v-model="form.sex" class="input-select">
                                <option disabled value="">Select</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="non-binary">Non-Binary</option>
                            </select>
                        </div>

                        <div>
                            <InputLabel value="Contact Number" />
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-gray-500">+63</span>
                                <TextInput
                                    class="pl-10"
                                    :value="form.contactnumber"
                                    @input="enforcePHFormat"
                                />
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <InputLabel value="Address" />
                            <TextInput v-model="form.address" />
                        </div>

                        <div>
                            <InputLabel value="Email" />
                            <TextInput
                                v-model="form.email"
                                @blur="checkEmailAvailability"
                                required
                            />
                            <InputError :message="emailError" />
                        </div>

                        <div>
                            <InputLabel value="Password" />
                            <TextInput
                                :type="showPassword ? 'text' : 'password'"
                                v-model="form.password"
                            />
                        </div>

                        <div>
                            <InputLabel value="Confirm Password" />
                            <TextInput
                                :type="showPasswordConfirmation ? 'text' : 'password'"
                                v-model="form.password_confirmation"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end mt-8">
                        <PrimaryButton>Continue</PrimaryButton>
                    </div>
                </form>

                <!-- STEP 2 -->
                <form v-if="step === 2" @submit.prevent="step = 3">
                    <h2 class="section-title">Senior High School Background</h2>

                    <div class="grid md:grid-cols-2 gap-6">
                        <TextInput v-model="form.school" placeholder="School Name" />
                        <TextInput v-model="form.schoolAdd" placeholder="School Address" />
                        <TextInput v-model="form.schoolyear" placeholder="School Year" />
                        <TextInput type="date" v-model="form.dateGrad" />
                        <select v-model="form.strand" class="input-select">
                            <option disabled value="">Strand</option>
                            <option>STEM</option>
                            <option>HUMSS</option>
                            <option>TVL</option>
                        </select>
                        <select v-model="form.track" class="input-select">
                            <option disabled value="">Track</option>
                            <option>ICT</option>
                            <option>Cookery</option>
                        </select>
                    </div>

                    <div class="flex justify-between mt-8">
                        <PrimaryButton @click="step = 1">Back</PrimaryButton>
                        <PrimaryButton>Continue</PrimaryButton>
                    </div>
                </form>

                <!-- STEP 3–9 -->
                <!-- STEP 3: GRADE 10 UPLOAD -->
                <form v-if="step === 3" @submit.prevent="extractGrades">
                    <h2 class="section-title">Grade 10 Report Card</h2>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="upload-card">
                            <p class="upload-title">Front Page (Name & LRN)</p>
                            <input type="file" accept="image/*" @change="handleGrade10FrontUpload" />
                            <div class="mt-4 space-y-2">
                                <TextInput placeholder="Extracted Name" v-model="extracted10.name" />
                                <TextInput placeholder="Extracted LRN" v-model="extracted10.lrn" />
                            </div>
                        </div>

                        <div class="upload-card">
                            <p class="upload-title">Back Page (Grades)</p>
                            <input type="file" accept="image/*" required @change="handleFileUpload" />
                        </div>
                    </div>

                    <div class="wizard-actions">
                        <PrimaryButton @click="step = 2">Back</PrimaryButton>
                        <PrimaryButton :disabled="isProcessing">Extract Grades</PrimaryButton>
                    </div>
                </form>

                <!-- STEP 4: GRADE 10 REVIEW -->
                <form v-if="step === 4" @submit.prevent="step = 5">
                    <h2 class="section-title">Review Grade 10 Grades</h2>

                    <div class="grid md:grid-cols-3 gap-6">
                        <TextInput v-model="form.english" placeholder="English" />
                        <TextInput v-model="form.mathematics" placeholder="Mathematics" />
                        <TextInput v-model="form.science" placeholder="Science" />
                    </div>

                    <div class="wizard-actions">
                        <PrimaryButton @click="step = 3">Back</PrimaryButton>
                        <PrimaryButton>Continue</PrimaryButton>
                    </div>
                </form>

                <!-- STEP 5: GRADE 11 UPLOAD -->
                <form v-if="step === 5" @submit.prevent="extractGrade11">
                    <h2 class="section-title">Grade 11 Report Card</h2>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="upload-card">
                            <p class="upload-title">Front Page</p>
                            <input type="file" accept="image/*" @change="handleGrade11FrontUpload" />
                            <TextInput class="mt-2" placeholder="Name" v-model="extracted11.name" />
                            <TextInput placeholder="LRN" v-model="extracted11.lrn" />
                        </div>

                        <div class="upload-card">
                            <p class="upload-title">Back Page</p>
                            <input type="file" accept="image/*" required @change="handleGrade11Upload" />
                        </div>
                    </div>

                    <div class="wizard-actions">
                        <PrimaryButton @click="step = 4">Back</PrimaryButton>
                        <PrimaryButton :disabled="isProcessing">Extract Grades</PrimaryButton>
                    </div>
                </form>

                <!-- STEP 6: GRADE 11 REVIEW -->
                <form v-if="step === 6" @submit.prevent="step = 7">
                    <h2 class="section-title">Review Grade 11 Grades</h2>

                    <div class="grid md:grid-cols-2 gap-6">
                        <TextInput v-model="form.firstSem" placeholder="First Semester Average" />
                        <TextInput v-model="form.secondSem" placeholder="Second Semester Average" />
                    </div>

                    <div class="wizard-actions">
                        <PrimaryButton @click="step = 5">Back</PrimaryButton>
                        <PrimaryButton>Continue</PrimaryButton>
                    </div>
                </form>

                <!-- STEP 7: GRADE 12 UPLOAD -->
                <form v-if="step === 7" @submit.prevent="extractGrade12">
                    <h2 class="section-title">Grade 12 Report Card</h2>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="upload-card">
                            <p class="upload-title">Front Page</p>
                            <input type="file" accept="image/*" @change="handleGrade12FrontUpload" />
                            <TextInput class="mt-2" placeholder="Name" v-model="extracted12.name" />
                            <TextInput placeholder="LRN" v-model="extracted12.lrn" />
                        </div>

                        <div class="upload-card">
                            <p class="upload-title">Back Page</p>
                            <input type="file" accept="image/*" required @change="handleGrade12Upload" />
                        </div>
                    </div>

                    <div class="wizard-actions">
                        <PrimaryButton @click="step = 6">Back</PrimaryButton>
                        <PrimaryButton :disabled="isProcessing">Extract Grades</PrimaryButton>
                    </div>
                </form>

                <!-- STEP 8: GRADE 12 REVIEW -->
                <form v-if="step === 8" @submit.prevent="step = 9">
                    <h2 class="section-title">Review Grade 12 Grades</h2>

                    <div class="grid md:grid-cols-2 gap-6">
                        <TextInput v-model="form.g12firstSem" placeholder="First Semester" />
                        <TextInput v-model="form.g12secondSem" placeholder="Second Semester" />
                    </div>

                    <div class="wizard-actions">
                        <PrimaryButton @click="step = 7">Back</PrimaryButton>
                        <PrimaryButton>Continue</PrimaryButton>
                    </div>
                </form>

                <!-- STEP 9: REQUIREMENTS -->
                <form v-if="step === 9" @submit.prevent="step = 10">
                    <h2 class="section-title">Admission Requirements</h2>

                    <div class="grid md:grid-cols-2 gap-6">
                        <div
                            v-for="(doc, key) in requiredDocs"
                            :key="key"
                            class="upload-card"
                        >
                            <p class="upload-title capitalize">{{ key.replace(/([A-Z])/g, ' $1') }}</p>
                            <input type="file" @change="(e) => handleRequiredDocUpload(e, key)" />
                        </div>
                    </div>

                    <div class="wizard-actions">
                        <PrimaryButton @click="step = 8">Back</PrimaryButton>
                        <PrimaryButton>Review Submission</PrimaryButton>
                    </div>
                </form>

                <!-- STEP 10 -->
                <div v-if="step === 10">
                    <h2 class="text-xl font-bold text-white text-center">
                        Final Confirmation
                    </h2>

                    <div class="mt-6 text-gray-300 space-y-2">
                        <p><strong>Name:</strong> {{ form.firstname }} {{ form.lastname }}</p>
                        <p><strong>Email:</strong> {{ form.email }}</p>
                        <p><strong>G10 English:</strong> {{ form.english }}</p>
                        <p><strong>G11:</strong> {{ form.firstSem }} / {{ form.secondSem }}</p>
                        <p><strong>G12:</strong> {{ form.g12firstSem }} / {{ form.g12secondSem }}</p>
                    </div>

                    <div class="flex justify-between mt-8">
                        <PrimaryButton @click="step = 9" :disabled="isProcessing || form.processing">Back</PrimaryButton>
                        <PrimaryButton @click="submit" :disabled="isProcessing || form.processing">
                            {{ isProcessing || form.processing ? 'Submitting...' : 'Submit Application' }}
                        </PrimaryButton>
                    </div>
                </div>

            </section>
        </div>
    </AuthenticationCard>
</template>

<style>

.upload-card {
    background-color: #1f2937; /* gray-800 */
    border: 1px solid #374151; /* gray-700 */
    border-radius: 0.5rem;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.upload-title {
    color: #ffffff;
    font-weight: 600;
    font-size: 0.875rem;
}

.wizard-actions {
    display: flex;
    justify-content: space-between;
    margin-top: 2.5rem;
}

</style>