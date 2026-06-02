import { ref, reactive, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Composable for managing grade input forms across all strands.
 *
 * Handles reactive form state, dynamic subject management, category average
 * computation, program qualification logic, validation helpers, and form submission.
 *
 * @param {Object} options
 * @param {string} options.strand - The applicant's strand (e.g., 'STEM', 'ABM')
 * @param {Object} options.defaultSubjects - { math: string[], english: string[], science: string[] }
 * @param {Object|null} options.grade - Existing grade record from props
 * @param {Array} options.programs - Available programs for qualification
 * @param {Object|null} options.profile - Applicant profile with program choices
 * @param {boolean} options.isLocked - Whether the application is locked
 */
export function useGradeForm({ strand, defaultSubjects, grade, programs, profile, isLocked: lockedProp }) {
    const loading = ref(false);
    const errors = ref({});

    // Toast notification state
    const toastMessage = ref('');
    const toastType = ref('error'); // 'error' | 'success' | 'warning'
    const toastVisible = ref(false);
    const showRetryOption = ref(false);

    // Locked state - prevents all modifications
    const isLocked = ref(lockedProp || false);

    // Auto-dismiss toast after delay
    let toastTimeout = null;
    function showToast(message, type = 'error', retry = false) {
        if (toastTimeout) clearTimeout(toastTimeout);
        toastMessage.value = message;
        toastType.value = type;
        toastVisible.value = true;
        showRetryOption.value = retry;
        if (!retry) {
            toastTimeout = setTimeout(() => {
                dismissToast();
            }, 5000);
        }
    }

    function dismissToast() {
        toastVisible.value = false;
        toastMessage.value = '';
        showRetryOption.value = false;
    }

    // Initialize reactive form state from default subjects
    const form = reactive(buildInitialFormState(defaultSubjects));

    // Dynamic subjects grouped by category
    const dynamicSubjects = ref({
        math: [],
        english: [],
        science: [],
    });

    // --- Dynamic Subject Management ---

    /**
     * Add a new dynamic subject to a category.
     * Max 5 per category. Blocked when application is locked.
     */
    function addSubject(category) {
        if (isLocked.value) return;
        if (!canAddSubject(category)) return;

        dynamicSubjects.value[category].push({
            id: generateUUID(),
            name: '',
            grade: null,
        });
    }

    /**
     * Remove a dynamic subject by category and id.
     * Blocked when application is locked.
     * Average is recalculated automatically via computed refs.
     */
    function removeSubject(category, id) {
        if (isLocked.value) return;
        dynamicSubjects.value[category] = dynamicSubjects.value[category].filter(
            (s) => s.id !== id
        );
    }

    /**
     * Check if more subjects can be added to a category (max 5).
     */
    function canAddSubject(category) {
        return (dynamicSubjects.value[category] || []).length < 5;
    }

    // --- Category Average Computation ---

    /**
     * Compute category average from default subject fields + dynamic subjects.
     * Only includes:
     * - Default fields with valid numeric values (0-100)
     * - Dynamic subjects with non-whitespace names AND valid grades (0-100)
     * Returns string rounded to 2 decimal places, or null if no valid values.
     */
    function computeCategoryAverage(fieldKeys, category) {
        const validValues = [];

        // Collect valid values from default subject fields
        for (const key of fieldKeys) {
            const val = form[key];
            if (isValidGradeValue(val)) {
                validValues.push(parseFloat(val));
            }
        }

        // Collect valid values from dynamic subjects
        const dynamicEntries = dynamicSubjects.value[category] || [];
        for (const entry of dynamicEntries) {
            if (hasNonWhitespaceName(entry.name) && isValidGradeValue(entry.grade)) {
                validValues.push(parseFloat(entry.grade));
            }
        }

        if (validValues.length === 0) return null;

        const mean = validValues.reduce((sum, v) => sum + v, 0) / validValues.length;
        return mean.toFixed(2);
    }

    const mathAverage = computed(() =>
        computeCategoryAverage(defaultSubjects.math, 'math')
    );

    const englishAverage = computed(() =>
        computeCategoryAverage(defaultSubjects.english, 'english')
    );

    const scienceAverage = computed(() =>
        computeCategoryAverage(defaultSubjects.science, 'science')
    );

    // G12 GWA from semester values
    const g12GWA = computed(() => {
        const first = form.g12_first_sem_gwa;
        const second = form.g12_second_sem_gwa;

        if (!isValidGradeValue(first) || !isValidGradeValue(second)) {
            return null;
        }

        const average = (parseFloat(first) + parseFloat(second)) / 2;
        return average.toFixed(2);
    });

    // --- Subject Counts ---

    function computeSubjectCount(fieldKeys, category) {
        let count = 0;

        for (const key of fieldKeys) {
            if (isValidGradeValue(form[key])) {
                count++;
            }
        }

        const dynamicEntries = dynamicSubjects.value[category] || [];
        for (const entry of dynamicEntries) {
            if (hasNonWhitespaceName(entry.name) && isValidGradeValue(entry.grade)) {
                count++;
            }
        }

        return count;
    }

    const mathCount = computed(() =>
        computeSubjectCount(defaultSubjects.math, 'math')
    );

    const englishCount = computed(() =>
        computeSubjectCount(defaultSubjects.english, 'english')
    );

    const scienceCount = computed(() =>
        computeSubjectCount(defaultSubjects.science, 'science')
    );

    // --- Program Qualification ---

    const currentStrand = computed(() => (strand || '').toUpperCase());

    /**
     * Program choice selection is disabled when any category average is null
     * (meaning no valid grades in that category).
     */
    const programChoiceDisabled = computed(() => {
        return !mathAverage.value || !englishAverage.value || !scienceAverage.value || !g12GWA.value;
    });

    function meetsRequirement(studentValue, requiredValue) {
        if (requiredValue === null || requiredValue === undefined || requiredValue === '') {
            return true;
        }
        const required = parseFloat(requiredValue);
        if (isNaN(required) || required === 0) {
            return true;
        }
        return parseFloat(studentValue) >= required;
    }

    function isStrandAllowed(program) {
        const strandValue = (program.strand_names || '').toString().toUpperCase();

        // If no strand requirement specified, allow all
        if (!strandValue) {
            return true;
        }

        // If explicitly open to all strands
        if (strandValue.includes('OPEN TO ALL')) {
            return true;
        }

        // Parse the allowed strands
        const allowed = strandValue
            .split(/[,/]/)
            .map((s) => s.trim())
            .filter(Boolean)
            .map((s) => {
                if (s.includes('TECH-VOC') || s.includes('TVL')) return 'TVL';
                if (s.includes('STEM')) return 'STEM';
                if (s.includes('ABM')) return 'ABM';
                if (s.includes('HUMSS')) return 'HUMSS';
                if (s.includes('GAS')) return 'GAS';
                if (s.includes('ICT')) return 'ICT';
                return s;
            });

        const isAllowed = allowed.includes(currentStrand.value);

        // Check if "other with bridging" is mentioned
        if (!isAllowed && strandValue.includes('OTHER') && strandValue.includes('BRIDGING')) {
            return true;
        }

        return isAllowed;
    }

    const qualifiedPrograms = computed(() => {
        if (
            !programs ||
            !mathAverage.value ||
            !scienceAverage.value ||
            !englishAverage.value ||
            !g12GWA.value
        ) {
            return [];
        }

        return programs.filter((program) => {
            if (!isStrandAllowed(program)) return false;

            return (
                meetsRequirement(mathAverage.value, program.math) &&
                meetsRequirement(scienceAverage.value, program.science) &&
                meetsRequirement(englishAverage.value, program.english) &&
                meetsRequirement(g12GWA.value, program.gwa)
            );
        });
    });

    const notQualifiedPrograms = computed(() => {
        if (!programs) return [];

        // When averages are missing, all programs are not qualified with "grades missing" reason
        if (
            !mathAverage.value ||
            !scienceAverage.value ||
            !englishAverage.value ||
            !g12GWA.value
        ) {
            return (programs || []).map((program) => {
                const unmetRequirements = [];
                if (!mathAverage.value) unmetRequirements.push('Math grades missing');
                if (!scienceAverage.value) unmetRequirements.push('Science grades missing');
                if (!englishAverage.value) unmetRequirements.push('English grades missing');
                if (!g12GWA.value) unmetRequirements.push('G12 GWA missing');
                return { ...program, unmetRequirements };
            });
        }

        return programs
            .filter((program) => {
                if (!isStrandAllowed(program)) return true;

                const qualified =
                    meetsRequirement(mathAverage.value, program.math) &&
                    meetsRequirement(scienceAverage.value, program.science) &&
                    meetsRequirement(englishAverage.value, program.english) &&
                    meetsRequirement(g12GWA.value, program.gwa);

                return !qualified;
            })
            .map((program) => {
                const unmetRequirements = [];

                if (!isStrandAllowed(program)) {
                    unmetRequirements.push(`Strand not eligible (requires: ${program.strand_names || 'N/A'})`);
                }
                if (!meetsRequirement(mathAverage.value, program.math)) {
                    unmetRequirements.push(`Math: ${mathAverage.value} < ${program.math} required`);
                }
                if (!meetsRequirement(scienceAverage.value, program.science)) {
                    unmetRequirements.push(`Science: ${scienceAverage.value} < ${program.science} required`);
                }
                if (!meetsRequirement(englishAverage.value, program.english)) {
                    unmetRequirements.push(`English: ${englishAverage.value} < ${program.english} required`);
                }
                if (!meetsRequirement(g12GWA.value, program.gwa)) {
                    unmetRequirements.push(`GWA: ${g12GWA.value} < ${program.gwa} required`);
                }

                return { ...program, unmetRequirements };
            });
    });

    // --- Validation Helpers ---

    /**
     * Validate grade input to ensure it's between 0 and 100.
     * Attached to @input event on grade fields.
     */
    function validateGrade(event) {
        const input = event.target;
        let value = input.value;
        let changed = false;

        // Remove any negative signs
        if (value.includes('-')) {
            value = value.replace(/-/g, '');
            changed = true;
            showToast('Grades cannot be negative.', 'error');
        }

        const numValue = parseFloat(value);

        // Cap at 100
        if (!isNaN(numValue) && numValue > 100) {
            value = '100';
            changed = true;
            showToast('Grade cannot exceed 100.', 'error');
        }

        // If value is less than 0, clear
        if (!isNaN(numValue) && numValue < 0) {
            value = '';
            changed = true;
            showToast('Grades cannot be negative.', 'error');
        }

        // Limit to 2 decimal places
        const dotIndex = value.indexOf('.');
        if (dotIndex !== -1 && value.length - dotIndex - 1 > 2) {
            value = value.substring(0, dotIndex + 3);
            changed = true;
        }

        if (changed) {
            input.value = value;
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    /**
     * Prevent invalid keyboard input on grade fields.
     * Attached to @keydown event.
     */
    function preventInvalidInput(event) {
        const input = event.target;
        const key = event.key;
        const currentValue = input.value;

        // Prevent minus sign
        if (key === '-') {
            event.preventDefault();
            return;
        }

        // Allow control keys
        if (
            key === 'Backspace' ||
            key === 'Delete' ||
            key === 'Tab' ||
            key === 'ArrowLeft' ||
            key === 'ArrowRight' ||
            key === 'ArrowUp' ||
            key === 'ArrowDown' ||
            key === 'Home' ||
            key === 'End' ||
            (event.ctrlKey && (key === 'a' || key === 'c' || key === 'v' || key === 'x'))
        ) {
            return;
        }

        // Only allow numbers and decimal point
        if (!/^\d$/.test(key) && key !== '.') {
            event.preventDefault();
            return;
        }

        // Prevent multiple decimal points
        if (key === '.' && currentValue.includes('.')) {
            event.preventDefault();
            return;
        }

        // Check if future value would exceed 100 or exceed 2 decimal places
        const selectionStart = input.selectionStart;
        const selectionEnd = input.selectionEnd;
        const futureValue =
            currentValue.substring(0, selectionStart) +
            key +
            currentValue.substring(selectionEnd);

        // Block if more than 2 decimal places
        const dotIndex = futureValue.indexOf('.');
        if (dotIndex !== -1 && futureValue.length - dotIndex - 1 > 2) {
            event.preventDefault();
            return;
        }

        if (!futureValue.includes('.')) {
            const futureNum = parseFloat(futureValue);
            if (!isNaN(futureNum) && futureNum > 100) {
                event.preventDefault();
                return;
            }
        }
    }

    // --- Form Submission ---

    /**
     * Check if a category has at least one valid grade (from default or dynamic subjects).
     */
    function categoryHasValidGrade(fieldKeys, category) {
        for (const key of fieldKeys) {
            if (isValidGradeValue(form[key])) return true;
        }
        const dynamicEntries = dynamicSubjects.value[category] || [];
        for (const entry of dynamicEntries) {
            if (hasNonWhitespaceName(entry.name) && isValidGradeValue(entry.grade)) return true;
        }
        return false;
    }

    /**
     * Validate that each category has at least one valid grade before submission.
     * Returns true if valid, false if not (shows toast).
     */
    function validateCategoriesBeforeSubmit() {
        const mathHasGrade = categoryHasValidGrade(defaultSubjects.math, 'math');
        const englishHasGrade = categoryHasValidGrade(defaultSubjects.english, 'english');
        const scienceHasGrade = categoryHasValidGrade(defaultSubjects.science, 'science');

        if (!mathHasGrade || !englishHasGrade || !scienceHasGrade) {
            showToast('At least one grade is required in each category', 'error');
            return false;
        }
        return true;
    }

    /**
     * Submit the grade form to the unified /grades/store endpoint via Inertia.
     * Validates categories before submission, handles network errors with retry.
     */
    function submitForm() {
        // Prevent submission if application is locked
        if (isLocked.value) {
            showToast('Grade submission is no longer allowed. Your application is locked.', 'error');
            return;
        }

        // Validate minimum per category before submitting
        if (!validateCategoriesBeforeSubmit()) {
            return;
        }

        loading.value = true;
        errors.value = {};
        dismissToast();

        // Build payload with all individual grade fields
        const payload = {};

        // Add all default subject grades
        const allDefaultFields = [
            ...defaultSubjects.math,
            ...defaultSubjects.english,
            ...defaultSubjects.science,
        ];

        for (const field of allDefaultFields) {
            const val = form[field];
            payload[field] = val !== null && val !== '' && !isNaN(val) ? parseFloat(val) : null;
        }

        // Add GWA semester values
        payload.g12_first_sem = form.g12_first_sem_gwa !== null ? parseFloat(form.g12_first_sem_gwa) : null;
        payload.g12_second_sem = form.g12_second_sem_gwa !== null ? parseFloat(form.g12_second_sem_gwa) : null;

        // Add computed averages (for backend verification)
        payload.mathematics = mathAverage.value ? parseFloat(mathAverage.value) : null;
        payload.english = englishAverage.value ? parseFloat(englishAverage.value) : null;
        payload.science = scienceAverage.value ? parseFloat(scienceAverage.value) : null;

        // Add dynamic subjects as flat array with category
        const dynamicSubjectsPayload = [];
        for (const category of ['math', 'english', 'science']) {
            for (const entry of dynamicSubjects.value[category]) {
                const hasName = hasNonWhitespaceName(entry.name);
                const hasGrade = isValidGradeValue(entry.grade);

                if (hasGrade && !hasName) {
                    loading.value = false;
                    showToast(`Please enter a subject name for the additional ${category} grade of ${entry.grade}.`, 'error');
                    return;
                }

                if (hasName && !hasGrade) {
                    loading.value = false;
                    showToast(`Please enter a valid grade for the additional subject "${entry.name}".`, 'error');
                    return;
                }

                // Only include entries with non-whitespace names and valid grades
                if (hasName && hasGrade) {
                    dynamicSubjectsPayload.push({
                        category,
                        name: entry.name.trim(),
                        grade: parseFloat(entry.grade),
                    });
                }
            }
        }
        payload.dynamic_subjects = dynamicSubjectsPayload;

        // Add strand
        payload.strand = currentStrand.value;

        // Add program choices
        payload.first_choice_program = form.first_choice_program || null;
        payload.second_choice_program = form.second_choice_program || null;
        payload.third_choice_program = form.third_choice_program || null;
        payload.qualified_programs_count = qualifiedPrograms.value.length;

        let requestHandled = false;

        router.post('/grades/store', payload, {
            onSuccess: () => {
                requestHandled = true;
                router.visit('/applicant-dashboard?success=grades_saved', {
                    preserveState: false,
                    preserveScroll: false,
                });
            },
            onError: (errorResponse) => {
                requestHandled = true;
                errors.value = errorResponse;
                loading.value = false;
            },
            onFinish: () => {
                // If neither onSuccess nor onError was called, it's a network error
                if (!requestHandled) {
                    loading.value = false;
                    showToast('Network error. Please check your connection and try again.', 'error', true);
                } else {
                    loading.value = false;
                }
            },
        });
    }

    /**
     * Retry the last submission after a network error.
     */
    function retrySubmit() {
        dismissToast();
        submitForm();
    }

    // --- Initialization / Restore ---

    onMounted(() => {
        // Load saved program choices from profile
        if (profile) {
            if (profile.first_choice_program) {
                form.first_choice_program = profile.first_choice_program;
            }
            if (profile.second_choice_program) {
                form.second_choice_program = profile.second_choice_program;
            }
            if (profile.third_choice_program) {
                form.third_choice_program = profile.third_choice_program;
            }
        }

        // Load saved grades from database
        if (grade && grade.id) {
            // Restore GWA semester values
            if (grade.g12_first_sem != null) {
                form.g12_first_sem_gwa = grade.g12_first_sem;
            }
            if (grade.g12_second_sem != null) {
                form.g12_second_sem_gwa = grade.g12_second_sem;
            }

            // Restore all default subject grades
            const allDefaultFields = [
                ...defaultSubjects.math,
                ...defaultSubjects.english,
                ...defaultSubjects.science,
            ];

            for (const field of allDefaultFields) {
                if (grade[field] != null) {
                    form[field] = grade[field];
                }
            }

            // Restore dynamic subjects grouped by category
            if (grade.dynamic_subjects && Array.isArray(grade.dynamic_subjects)) {
                const restored = { math: [], english: [], science: [] };

                for (const entry of grade.dynamic_subjects) {
                    const category = entry.category;
                    if (category && restored[category]) {
                        restored[category].push({
                            id: generateUUID(),
                            name: entry.name || '',
                            grade: entry.grade != null ? entry.grade : null,
                        });
                    }
                }

                dynamicSubjects.value = restored;
            }
        }
    });

    // --- Return Public API ---

    return {
        // Reactive form state
        form,
        dynamicSubjects,

        // Computed averages
        mathAverage,
        englishAverage,
        scienceAverage,
        g12GWA,

        // Subject counts
        mathCount,
        englishCount,
        scienceCount,

        // Dynamic subject management
        addSubject,
        removeSubject,
        canAddSubject,

        // Program qualification
        qualifiedPrograms,
        notQualifiedPrograms,
        programChoiceDisabled,

        // Validation
        validateGrade,
        preventInvalidInput,
        errors,

        // Submission
        submitForm,
        retrySubmit,
        loading,

        // Locked state
        isLocked,

        // Toast notifications
        toastMessage,
        toastType,
        toastVisible,
        showRetryOption,
        showToast,
        dismissToast,
    };
}

// --- Internal Helpers ---

/**
 * Build initial form state from default subjects.
 * All subject fields start as null, plus GWA and program choice fields.
 */
function buildInitialFormState(defaultSubjects) {
    const state = {};

    // Initialize all default subject fields to null
    for (const key of defaultSubjects.math) {
        state[key] = null;
    }
    for (const key of defaultSubjects.english) {
        state[key] = null;
    }
    for (const key of defaultSubjects.science) {
        state[key] = null;
    }

    // GWA semester fields
    state.g12_first_sem_gwa = null;
    state.g12_second_sem_gwa = null;

    // Program choices
    state.first_choice_program = '';
    state.second_choice_program = '';
    state.third_choice_program = '';

    return state;
}

/**
 * Check if a grade value is valid (non-null, numeric, 0-100).
 */
function isValidGradeValue(val) {
    if (val === null || val === '' || val === undefined) return false;
    const num = parseFloat(val);
    return !isNaN(num) && isFinite(num) && num >= 0 && num <= 100;
}

/**
 * Check if a name contains at least one non-whitespace character.
 */
function hasNonWhitespaceName(name) {
    if (!name || typeof name !== 'string') return false;
    return /\S/.test(name);
}

/**
 * Generate a UUID for dynamic subject tracking.
 * Uses crypto.randomUUID() if available, otherwise falls back to a simple generator.
 */
function generateUUID() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    // Fallback UUID v4 generator
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}
