<!-- resources/js/Pages/Upload.vue -->
<template>
    <AppLayout>
        <div class="container">
            <h1>Upload Excel File</h1>
            <form @submit.prevent="submitForm" enctype="multipart/form-data">
                <div class="form-group">
                    <!-- Batch Selection -->
                    <div>
                        <label for="batch_number">Select Batch Number:</label>
                        <select
                            v-model="selectedBatch"
                            @change="handleBatchSelection"
                        >
                            <option value="Batch 1">Batch 1</option>
                            <option value="Batch 2">Batch 2</option>
                            <option value="Batch 3">Batch 3</option>
                            <option value="--Custom--">--Custom--</option>
                        </select>
                        <input
                            v-if="selectedBatch === '--Custom--'"
                            v-model="customBatch"
                            placeholder="Enter custom batch name"
                            class="custom-input"
                        />
                    </div>

                    <!-- School Year -->
                    <div>
                        <label for="school_year">Select School Year:</label>
                        <select
                            v-model="selectedYear"
                            @change="handleYearSelection"
                        >
                            <option value="2023">2023</option>
                            <option value="2022">2022</option>
                            <option value="2021">2021</option>
                            <option value="--Custom--">--Custom--</option>
                        </select>
                        <input
                            v-if="selectedYear === '--Custom--'"
                            v-model="customYear"
                            placeholder="Enter custom school year"
                            class="custom-input"
                        />
                    </div>
                </div>

                <!-- File Upload -->
                <label for="file">Choose Excel File:</label>
                <input type="file" @change="handleFileUpload" required />

                <button type="submit" class="upload-button">
                    <i class="fa fa-upload"></i> Upload
                </button>
            </form>

            <!-- Success Dialog -->
            <div v-if="showDialog" class="dialog-overlay">
                <div class="dialog-box">
                    <h2>Success</h2>
                    <p>Your records uploaded successfully!</p>
                    <button @click="hideDialogAndRedirect">OK</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from "vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import axios from "axios";
import { useRouter } from "vue-router";

const selectedBatch = ref("Batch 1");
const customBatch = ref("");
const selectedYear = ref("2023");
const customYear = ref("");
const file = ref(null);
const showDialog = ref(false);

const router = useRouter();

function handleBatchSelection() {
    if (selectedBatch.value !== "--Custom--") customBatch.value = "";
}
function handleYearSelection() {
    if (selectedYear.value !== "--Custom--") customYear.value = "";
}
function handleFileUpload(e) {
    file.value = e.target.files[0];
}
async function submitForm() {
    const formData = new FormData();
    formData.append(
        "batch_number",
        selectedBatch.value === "--Custom--"
            ? customBatch.value
            : selectedBatch.value
    );
    formData.append(
        "school_year",
        selectedYear.value === "--Custom--"
            ? customYear.value
            : selectedYear.value
    );
    formData.append("file", file.value);

    try {
        await axios.post("/test-passers/upload", formData);
        showDialog.value = true;
    } catch (error) {
        console.error(error);
        alert("Upload failed.");
    }
}
function hideDialogAndRedirect() {
    showDialog.value = false;
    router.push("/test-passers/send-emails-form");
}
</script>

<style scoped>
/* Add your styles here */
</style>
