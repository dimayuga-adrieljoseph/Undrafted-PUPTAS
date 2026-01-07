<template>
    <AppLayout>
        <div class="main">
            <div class="container bg-white/50">
                <h1>Upload Excel File</h1>
                <form
                    @submit.prevent="submitForm"
                    enctype="multipart/form-data"
                >
                    <div class="form-group">
                        <div>
                            <label>Select Batch Number:</label>
                            <select v-model="batch" @change="onBatchChange">
                                <option value="Batch 1">Batch 1</option>
                                <option value="Batch 2">Batch 2</option>
                                <option value="Batch 3">Batch 3</option>
                                <option value="--Custom--">--Custom--</option>
                            </select>
                            <!-- Show custom batch input if '--Custom--' is selected -->
                            <input
                                v-if="batch === '--Custom--'"
                                v-model="customBatch"
                                placeholder="Enter custom batch name"
                                class="custom-input"
                            />
                        </div>

                        <div>
                            <label>Select School Year:</label>
                            <select v-model="year" @change="onYearChange">
                                <option
                                    v-for="yearOption in yearOptions"
                                    :key="yearOption"
                                    :value="yearOption"
                                >
                                    {{ yearOption }}
                                </option>
                                <option value="--Custom--">--Custom--</option>
                            </select>
                            <!-- Show custom year input if '--Custom--' is selected -->
                            <input
                                v-if="year === '--Custom--'"
                                v-model="customYear"
                                placeholder="Enter custom school year"
                                class="custom-input"
                            />
                        </div>
                    </div>

                    <label>Choose Excel File:</label>
                    <input type="file" @change="onFileChange" required />

                    <button type="submit" class="upload-button">
                        <i class="fa fa-upload"></i> Upload
                    </button>
                </form>

                <div v-if="showDialog" class="dialog-overlay show">
                    <div class="dialog-box">
                        <h2>Success</h2>
                        <p>Your records uploaded successfully!</p>
                        <button @click="redirectToEmails">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
const axios = window.axios;
import AppLayout from "@/Layouts/AppLayout.vue";
import { useGlobalLoading } from "@/Composables/useGlobalLoading";

// Reactive state variables using Vue 3's Composition API
const batch = ref("Batch 1");
const customBatch = ref("");
const year = ref("");
const customYear = ref("");
const file = ref(null);
const showDialog = ref(false);

// Define year options, including the current year dynamically
const yearOptions = ref([]);
onMounted(() => {
    const currentYear = new Date().getFullYear();
    yearOptions.value = [
        currentYear,
        currentYear - 1,
        currentYear - 2,
        currentYear - 3,
    ];
    year.value = currentYear; // Set the default year to the current year
});

// Event handler for batch selection change
const onBatchChange = () => {
    if (batch.value !== "--Custom--") customBatch.value = ""; // Clear custom batch if not custom
};

// Event handler for year selection change
const onYearChange = () => {
    if (year.value !== "--Custom--") customYear.value = ""; // Clear custom year if not custom
};

// Event handler for file selection change
const onFileChange = (e) => {
    file.value = e.target.files[0];
};

// Handle form submission and file upload
const submitForm = async () => {
    const formData = new FormData();
    formData.append(
        "batch_number",
        batch.value === "--Custom--" ? customBatch.value : batch.value
    );
    formData.append(
        "school_year",
        year.value === "--Custom--" ? customYear.value : year.value
    );
    formData.append("file", file.value);

    try {
        // POST request to upload the file
        await axios.post("/test-passers/upload", formData, {
            headers: {
                "Content-Type": "multipart/form-data",
            },
        });
        // Display success dialog
        showDialog.value = true;
    } catch (error) {
        // Handle error
        console.error(error);
        alert("Upload failed.");
    }
};

// Redirect to another page after upload success
const redirectToEmails = () => {
    window.location.href = "/test-passers";
};
</script>

<style scoped>
/* Mimicking the old design styles */
.main {
    font-family: "Figtree", sans-serif;
    margin: 0;
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 80vh;
}

.container {
    /* background-color: #ffffff; */
    padding: 60px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    width: 700px;
    text-align: center;
}

h1 {
    color: #333333;
    font-size: 24px;
    margin-bottom: 20px;
}

label {
    display: block;
    font-size: 14px;
    margin-bottom: 8px;
    color: #555555;
    text-align: left;
}

select,
input[type="file"],
input[type="text"] {
    width: 100%;
    padding: 8px;
    margin-bottom: 20px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
}

.custom-input {
    display: inline-block;
    margin-top: 10px;
    width: 100%;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

.upload-button {
    background-color: #800000;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 35px;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
}

.upload-button:hover {
    background-color: #660000;
}

.form-group {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.form-group > div {
    width: 48%;
}

/* Dialog styles */
.dialog-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    visibility: hidden;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.dialog-box {
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    max-width: 400px;
    width: 100%;
    text-align: center;
}

.dialog-box h2 {
    margin-top: 0;
    color: #333;
}

.dialog-box p {
    color: #555;
    font-size: 16px;
}

.dialog-box button {
    margin-top: 20px;
    padding: 10px 20px;
    background-color: #800000;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
}

.dialog-box button:hover {
    background-color: #660000;
}

.dialog-overlay.show {
    visibility: visible;
    opacity: 1;
}
</style>
