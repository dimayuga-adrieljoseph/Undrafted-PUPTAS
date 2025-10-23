document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
    const roleSelect = document.getElementById("role_id");
    const programGroup = document.getElementById("program-group");

    roleSelect.addEventListener("change", function () {
        if (["2", "3", "4", "5", "6"].includes(roleSelect.value)) {
            programGroup.style.display = "none";
            programGroup.classList.remove("full-width");
        } else {
            programGroup.style.display = "block";
            programGroup.classList.add("full-width");
        }
    });

    roleSelect.dispatchEvent(new Event("change"));
});

document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
    const requiredFields = document.querySelectorAll("[required]");

    function validateField(field) {
        const errorSpan = document.getElementById(`${field.id}-error`);
        if (errorSpan) {
            // Check if errorSpan exists
            if (!field.value.trim()) {
                errorSpan.textContent = "This field is required";
                field.classList.add("input-error");
            } else {
                errorSpan.textContent = "";
                field.classList.remove("input-error");
            }
        } else {
            console.warn(`No error span found for field with id ${field.id}`);
        }
    }

    requiredFields.forEach((field) => {
        field.addEventListener("blur", function () {
            validateField(field);
        });
    });

    const roleSelect = document.getElementById("role_id");
    const programGroup = document.getElementById("program-group");

    roleSelect.addEventListener("change", function () {
        if (["2", "3", "4", "5", "6"].includes(roleSelect.value)) {
            programGroup.style.display = "none";
            programGroup.classList.remove("full-width");
        } else {
            programGroup.style.display = "block";
            programGroup.classList.add("full-width");
        }
    });

    roleSelect.dispatchEvent(new Event("change"));
});
document.addEventListener("DOMContentLoaded", function () {
    const contactNumberInput = document.getElementById("contactnumber");
    const contactNumberError = document.getElementById("contactnumber-error");
    const form = document.querySelector("form");

    contactNumberInput.addEventListener("input", function () {
        const contactNumberValue = contactNumberInput.value;
        if (/^\d{10}$/.test(contactNumberValue)) {
            contactNumberError.textContent = "";
        } else {
            contactNumberError.textContent =
                "Invalid contact number. Must be exactly 10 digits.";
        }

        if (contactNumberValue.length > 10) {
            contactNumberInput.value = contactNumberValue.slice(0, 10);
        }
    });

    form.addEventListener("submit", function (event) {
        const contactNumberValue = contactNumberInput.value;
        if (!/^\d{10}$/.test(contactNumberValue)) {
            event.preventDefault();
            contactNumberError.textContent =
                "Invalid contact number. Must be exactly 10 digits.";
        }
    });
});

document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("addUserForm");
    const requiredFields = document.querySelectorAll("[required]");
    const roleSelect = document.getElementById("role_id");
    const programGroup = document.getElementById("program-group");

    function validateField(field) {
        const errorSpan = document.getElementById(`${field.id}-error`);
        if (errorSpan) {
            if (!field.value.trim()) {
                errorSpan.textContent = "This field is required";
                field.classList.add("input-error");
            } else {
                errorSpan.textContent = "";
                field.classList.remove("input-error");
            }
        }
    }

    requiredFields.forEach((field) => {
        field.addEventListener("blur", function () {
            validateField(field);
        });
    });

    roleSelect.addEventListener("change", function () {
        if (["2", "3", "4", "5", "6"].includes(roleSelect.value)) {
            programGroup.style.display = "none";
            programGroup.classList.remove("full-width");
        } else {
            programGroup.style.display = "block";
            programGroup.classList.add("full-width");
        }
    });

    // Trigger initial display setting for program-group
    roleSelect.dispatchEvent(new Event("change"));

    const contactNumberInput = document.getElementById("contactnumber");
    const contactNumberError = document.getElementById("contactnumber-error");

    contactNumberInput.addEventListener("input", function () {
        const contactNumberValue = contactNumberInput.value;
        if (/^\d{10}$/.test(contactNumberValue)) {
            contactNumberError.textContent = "";
        } else {
            contactNumberError.textContent =
                "Invalid contact number. Must be exactly 10 digits.";
        }

        // Limit input to 10 digits
        if (contactNumberValue.length > 10) {
            contactNumberInput.value = contactNumberValue.slice(0, 10);
        }
    });

    form.addEventListener("submit", function (event) {
        const contactNumberValue = contactNumberInput.value;
        if (!/^\d{10}$/.test(contactNumberValue)) {
            event.preventDefault();
            contactNumberError.textContent =
                "Invalid contact number. Must be exactly 10 digits.";
        }

        requiredFields.forEach((field) => {
            validateField(field);
            if (field.classList.contains("input-error")) {
                event.preventDefault();
            }
        });
    });
});
