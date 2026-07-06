<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router, Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ChangesConfirmationModal from '@/Components/ChangesConfirmationModal.vue';

const props = defineProps({
    user:     Object,
    programs: Array,
    roles:    Object,
    currentUserRoleId: Number,
    readOnly: { type: Boolean, default: false },
});

const isSuperAdmin = computed(() => props.currentUserRoleId === 7);

const isApplicant = computed(() => parseInt(props.user.role_id) === 1);

const fullName = computed(() => {
    const parts = [
        props.user.firstname,
        props.user.middlename ? props.user.middlename[0] + '.' : null,
        props.user.lastname,
        props.user.extension_name || null,
    ].filter(Boolean);
    return parts.join(' ');
});

const initials = computed(() =>
    `${props.user.firstname?.[0] || ''}${props.user.lastname?.[0] || ''}`.toUpperCase()
);

const formatDateForInput = (dateVal) => {
    if (!dateVal) return '';
    const d = new Date(dateVal);
    if (isNaN(d.getTime())) return '';
    return d.toISOString().split('T')[0];
};

const formatDateDisplay = (dateVal) => {
    if (!dateVal) return '—';
    const d = new Date(dateVal);
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });
};

const form = useForm({
    firstname:               props.user.firstname || '',
    lastname:                props.user.lastname || '',
    middlename:              props.user.middlename || '',
    extension_name:          props.user.extension_name || '',
    email:                   props.user.email || '',
    role_id:                 props.user.role_id || '',
    program:                 props.user.programs?.map(p => p.code) ?? [],
    applicant_program:       props.user.program?.code ?? '',
    applicant_second_program: props.user.second_program?.code ?? '',
    applicant_third_program:  props.user.third_program?.code ?? '',
    strand:                  props.user.strand || '',
    school:                  props.user.school || '',
    date_graduated:          formatDateForInput(props.user.date_graduated),
});

const showProgramAssignment = computed(() => ['3', '4', '8'].includes(form.role_id.toString()));
const showApplicantProgram  = computed(() => form.role_id.toString() === '1');

const onRoleChange = () => {
    if (!showProgramAssignment.value) form.program = [];
    if (!showApplicantProgram.value) {
        form.applicant_program = '';
        form.applicant_second_program = '';
        form.applicant_third_program = '';
    }
};

// ── Unified Save All Changes ──────────────────────────────
const showConfirmModal = ref(false);
const allChanges = ref([]);
const allChangesSaving = ref(false);

const profileFieldLabels = {
    firstname: 'First Name',
    lastname: 'Last Name',
    middlename: 'Middle Name',
    extension_name: 'Extension Name',
    email: 'Email',
    role_id: 'Role',
    program: 'Program Assignment',
    applicant_program: '1st Program Choice',
    applicant_second_program: '2nd Program Choice',
    applicant_third_program: '3rd Program Choice',
    strand: 'Strand',
    school: 'School (SHS)',
    date_graduated: 'Date Graduated',
};

function getOriginalValue(key) {
    if (key === 'program') {
        return props.user.programs?.map(p => p.code) ?? [];
    }
    if (key === 'applicant_program') {
        return props.user.program?.code ?? '';
    }
    if (key === 'applicant_second_program') {
        return props.user.second_program?.code ?? '';
    }
    if (key === 'applicant_third_program') {
        return props.user.third_program?.code ?? '';
    }
    if (key === 'date_graduated') {
        return formatDateForInput(props.user.date_graduated);
    }
    if (key === 'role_id') {
        return props.user.role_id != null ? String(props.user.role_id) : '';
    }
    return props.user[key] ?? '';
}

function formatDisplayValue(key, value) {
    if (value == null || value === '') return '—';
    if (key === 'program') {
        if (Array.isArray(value)) return value.length ? value.join(', ') : '—';
        return value;
    }
    if (key === 'role_id') {
        return props.roles?.[value] ?? `Role ${value}`;
    }
    if (key === 'date_graduated') {
        return formatDateDisplay(value);
    }
    return String(value);
}

function formatGradeValue(val) {
    if (val === '' || val == null) return '—';
    const num = parseFloat(val);
    return isNaN(num) ? '—' : Number(num).toFixed(2);
}

function collectProfileChanges() {
    const changes = [];
    const keysToCheck = isApplicant.value
        ? ['firstname', 'lastname', 'middlename', 'extension_name', 'email', 'strand', 'school', 'date_graduated', 'applicant_program', 'applicant_second_program', 'applicant_third_program']
        : ['firstname', 'lastname', 'middlename', 'extension_name', 'email', 'role_id', 'program'];

    keysToCheck.forEach(key => {
        const oldVal = getOriginalValue(key);
        const newVal = form[key];

        let oldDisplay = formatDisplayValue(key, oldVal);
        let newDisplay = formatDisplayValue(key, newVal);

        const oldNorm = Array.isArray(oldVal) ? [...oldVal].sort().join(',') : String(oldVal ?? '');
        const newNorm = Array.isArray(newVal) ? [...newVal].sort().join(',') : String(newVal ?? '');

        if (oldNorm !== newNorm) {
            changes.push({ section: 'Profile', field: profileFieldLabels[key] || key, oldValue: oldDisplay, newValue: newDisplay });
        }
    });
    return changes;
}

function collectGradeChanges() {
    const changes = [];
    const originalGrades = props.user.grades ?? {};

    // Check known grade fields
    knownGradeFields.value.forEach(f => {
        const oldRaw = originalGrades[f.key];
        const newRaw = editableGrades.value[f.key];
        const oldVal = oldRaw != null ? String(oldRaw) : '';
        const newVal = newRaw !== '' && newRaw != null ? String(newRaw) : '';

        if (oldVal !== newVal) {
            changes.push({ section: 'Grades', field: f.label, oldValue: formatGradeValue(oldRaw), newValue: formatGradeValue(newRaw) });
        }
    });

    // Check dynamic subjects
    const originalDyn = originalGrades.dynamic_subjects ?? [];
    const currentDyn = dynamicGradeSubjects.value.filter(s => s.name.trim() !== '');

    const origMap = new Map();
    originalDyn.forEach(s => {
        const key = `${s.subject ?? s.name ?? ''}|${s.category ?? 'math'}`;
        origMap.set(key.toLowerCase(), s);
    });

    currentDyn.forEach(s => {
        const key = `${s.name.trim()}|${s.category}`;
        const orig = origMap.get(key.toLowerCase());
        const origGrade = orig ? (orig.grade != null ? String(orig.grade) : '') : '';
        const newGrade = s.grade !== '' && s.grade != null ? String(s.grade) : '';

        if (!orig) {
            changes.push({ section: 'Grades', field: `[Dynamic] ${s.name.trim()} (${categoryLabel[s.category] || s.category})`, oldValue: '(new subject)', newValue: formatGradeValue(newGrade) });
        } else if (origGrade !== newGrade) {
            changes.push({ section: 'Grades', field: `[Dynamic] ${s.name.trim()} (${categoryLabel[s.category] || s.category})`, oldValue: formatGradeValue(origGrade), newValue: formatGradeValue(newGrade) });
        }
        origMap.delete(key.toLowerCase());
    });

    origMap.forEach((orig) => {
        const name = orig.subject ?? orig.name ?? 'Subject';
        const cat = categoryLabel[orig.category] || orig.category || 'math';
        changes.push({ section: 'Grades', field: `[Dynamic] ${name} (${cat})`, oldValue: formatGradeValue(orig.grade), newValue: '(removed)' });
    });

    return changes;
}

const saveAllChanges = () => {
    const profileChanges = collectProfileChanges();
    const gradeChanges = isApplicant.value ? collectGradeChanges() : [];
    const changes = [...profileChanges, ...gradeChanges];

    if (changes.length === 0) return;

    allChanges.value = changes;
    showConfirmModal.value = true;
};

async function confirmAllSave() {
    allChangesSaving.value = true;

    const profileChanges = collectProfileChanges();
    const gradeChanges = isApplicant.value ? collectGradeChanges() : [];

    const hasProfileChanges = profileChanges.length > 0;
    const hasGradeChanges = gradeChanges.length > 0;

    try {
        // Save profile first
        if (hasProfileChanges) {
            await new Promise((resolve, reject) => {
                form.put(route('users.update', props.user.id), {
                    preserveScroll: true,
                    onSuccess: () => resolve(),
                    onError: (errors) => {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        reject(errors);
                    },
                });
            });
        }

        // Save grades second
        if (hasGradeChanges) {
            const payload = {};
            knownGradeFields.value.forEach(f => { const v = editableGrades.value[f.key]; payload[f.key] = v !== '' && v != null ? parseFloat(v) : null; });
            payload.dynamic_subjects = dynamicGradeSubjects.value.filter(s => s.name.trim() !== '').map(s => ({ subject: s.name.trim(), grade: s.grade !== '' && s.grade != null ? parseFloat(s.grade) : null, category: s.category }));

            await new Promise((resolve, reject) => {
                router.put(route('users.grades.update', props.user.id), payload, {
                    preserveScroll: true,
                    onSuccess: () => { gradesSaved.value = true; setTimeout(() => { gradesSaved.value = false; }, 3000); resolve(); },
                    onError: (errors) => {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        reject(errors);
                    },
                });
            });
        }
    } catch (e) {
        // errors are handled by the form/Inertia automatically
    } finally {
        allChangesSaving.value = false;
        showConfirmModal.value = false;
    }
}

const activeTab = ref('profile');
const tabs = [
    { id: 'profile',     label: 'Profile',      icon: 'M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z' },
    { id: 'application', label: 'Application',   icon: 'M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z' },
    { id: 'grades',      label: 'Grades',        icon: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z' },
    { id: 'documents',   label: 'Documents',     icon: 'M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z' },
];

const appStatusBadge = computed(() => {
    const status = props.user.current_application?.status;
    const map = { pending: { label: 'Pending', cls: 'status-pending' }, submitted: { label: 'Submitted', cls: 'status-submitted' }, approved: { label: 'Approved', cls: 'status-approved' }, rejected: { label: 'Rejected', cls: 'status-rejected' }, waitlisted: { label: 'Waitlisted', cls: 'status-waitlisted' } };
    return map[status] ?? { label: status ?? 'N/A', cls: 'status-default' };
});

const enrollmentBadge = computed(() => {
    const status = props.user.current_application?.enrollment_status;
    const map = { not_enrolled: { label: 'Not Enrolled', cls: 'status-default' }, officially_enrolled: { label: 'Officially Enrolled', cls: 'status-approved' }, waitlisted: { label: 'Waitlisted', cls: 'status-waitlisted' } };
    return map[status] ?? { label: status ?? 'N/A', cls: 'status-default' };
});

const docTypeLabels = { file10_front: 'Grade 10 Report Card (Front)', file10_back: 'Grade 10 Report Card (Back)', file11_front: 'Grade 11 Report Card (Front)', file11_back: 'Grade 11 Report Card (Back)', file12_front: 'Grade 12 Report Card (Front)', file12_back: 'Grade 12 Report Card (Back)', school_id: 'School ID', non_enroll_cert: 'Non-Enrollment Certificate', psa: 'PSA Birth Certificate', good_moral: 'Good Moral Certificate', under_oath: 'Under Oath Statement', photo_2x2: '2x2 Photo' };

const docStatusBadge = (status) => {
    const map = { pending: { label: 'Pending', cls: 'status-pending' }, approved: { label: 'Approved', cls: 'status-approved' }, returned: { label: 'Returned', cls: 'status-rejected' }, failed: { label: 'Failed', cls: 'status-rejected' }, uploading: { label: 'Uploading', cls: 'status-submitted' } };
    return map[status] ?? { label: status, cls: 'status-default' };
};

const gradeGroups = computed(() => {
    const g = props.user.grades;
    if (!g) return [];
    const strand = (props.user.strand || '').toUpperCase();
    const groups = [];
    const core = [];
    if (g.english != null) core.push({ label: 'English (Average)', value: g.english });
    if (g.mathematics != null) core.push({ label: 'Mathematics (Average)', value: g.mathematics });
    if (g.science != null) core.push({ label: 'Science (Average)', value: g.science });
    if (g.g12_first_sem != null) core.push({ label: 'G12 First Semester Average', value: g.g12_first_sem });
    if (g.g12_second_sem != null) core.push({ label: 'G12 Second Semester Average', value: g.g12_second_sem });
    if (core.length) groups.push({ title: 'Core Averages', subjects: core });

    // G11 english fields vary by strand — STEM uses G12 versions instead of G11
    const g11EnglishFields = strand === 'STEM'
        ? [
            { key: 'g11_oral_communication', label: 'Oral Communication' },
            { key: 'g11_reading_writing', label: 'Reading & Writing' },
          ]
        : [
            { key: 'g11_oral_communication', label: 'Oral Communication' },
            { key: 'g11_21st_century_lit', label: '21st Century Literature' },
            { key: 'g11_academic_professional', label: 'Academic & Professional Literacy' },
            { key: 'g11_reading_writing', label: 'Reading & Writing' },
          ];

    const g11 = [
        ...g11EnglishFields,
        { key: 'g11_general_mathematics', label: 'General Mathematics' },
        { key: 'g11_statistics_probability', label: 'Statistics & Probability' },
        { key: 'g11_earth_life_science', label: 'Earth & Life Science' },
        { key: 'g11_physical_science', label: 'Physical Science' },
        { key: 'g11_business_mathematics', label: 'Business Mathematics' },
        { key: 'g11_pre_calculus', label: 'Pre-Calculus' },
        { key: 'g11_basic_calculus', label: 'Basic Calculus' },
        { key: 'g11_earth_science', label: 'Earth Science' },
        { key: 'g11_general_chemistry_1', label: 'General Chemistry 1' },
    ].filter(s => g[s.key] != null).map(s => ({ label: s.label, value: g[s.key] }));
    if (g11.length) groups.push({ title: 'Grade 11 Subjects', subjects: g11 });

    const g12 = [
        { key: 'g12_21st_century_lit', label: '21st Century Literature' },
        { key: 'g12_academic_professional', label: 'Academic & Professional Literacy' },
        { key: 'g12_general_physics_1', label: 'General Physics 1' },
        { key: 'g12_general_physics_2', label: 'General Physics 2' },
        { key: 'g12_general_biology_1', label: 'General Biology 1' },
        { key: 'g12_general_biology_2', label: 'General Biology 2' },
        { key: 'g12_general_chemistry_2', label: 'General Chemistry 2' },
        { key: 'g12_earth_life_science', label: 'Earth & Life Science' },
        { key: 'g12_physical_science', label: 'Physical Science' },
    ].filter(s => g[s.key] != null).map(s => ({ label: s.label, value: g[s.key] }));
    if (g12.length) groups.push({ title: 'Grade 12 Subjects', subjects: g12 });
    const dynamic = g.dynamic_subjects ?? [];
    if (dynamic.length) groups.push({ title: 'Additional Subjects', subjects: dynamic.map(s => ({ label: s.subject ?? s.name ?? 'Subject', value: s.grade ?? s.value })) });
    return groups;
});

const gradeColor = (val) => {
    const n = parseFloat(val);
    if (isNaN(n)) return 'grade-neutral';
    if (n >= 90) return 'grade-high';
    if (n >= 80) return 'grade-mid';
    if (n >= 75) return 'grade-pass';
    return 'grade-low';
};

const knownGradeFields = computed(() => {
    const strand = (props.user.strand || '').toUpperCase();

    // English fields differ by strand — STEM uses G12 versions, everyone else uses G11 versions
    const englishG11Fields = strand === 'STEM'
        ? [
            { key: 'g11_oral_communication', label: 'Oral Communication', group: 'Grade 11 Subjects', category: 'english' },
            { key: 'g11_reading_writing', label: 'Reading & Writing', group: 'Grade 11 Subjects', category: 'english' },
          ]
        : [
            { key: 'g11_oral_communication', label: 'Oral Communication', group: 'Grade 11 Subjects', category: 'english' },
            { key: 'g11_21st_century_lit', label: '21st Century Literature', group: 'Grade 11 Subjects', category: 'english' },
            { key: 'g11_academic_professional', label: 'Academic & Professional Literacy', group: 'Grade 11 Subjects', category: 'english' },
            { key: 'g11_reading_writing', label: 'Reading & Writing', group: 'Grade 11 Subjects', category: 'english' },
          ];

    const englishG12Fields = strand === 'STEM'
        ? [
            { key: 'g12_21st_century_lit', label: '21st Century Literature', group: 'Grade 12 Subjects', category: 'english' },
            { key: 'g12_academic_professional', label: 'Academic & Professional Literacy', group: 'Grade 12 Subjects', category: 'english' },
          ]
        : strand === 'ABM'
        ? [
            { key: 'g12_21st_century_lit', label: '21st Century Literature', group: 'Grade 12 Subjects', category: 'english' },
          ]
        : [];

    return [
        { key: 'g12_first_sem', label: 'G12 First Semester Average', group: 'Core Averages', category: null },
        { key: 'g12_second_sem', label: 'G12 Second Semester Average', group: 'Core Averages', category: null },
        // G11 English — strand-aware
        ...englishG11Fields,
        // G11 Math
        { key: 'g11_general_mathematics', label: 'General Mathematics', group: 'Grade 11 Subjects', category: 'math' },
        { key: 'g11_statistics_probability', label: 'Statistics & Probability', group: 'Grade 11 Subjects', category: 'math' },
        { key: 'g11_business_mathematics', label: 'Business Mathematics', group: 'Grade 11 Subjects', category: 'math' },
        { key: 'g11_pre_calculus', label: 'Pre-Calculus', group: 'Grade 11 Subjects', category: 'math' },
        { key: 'g11_basic_calculus', label: 'Basic Calculus', group: 'Grade 11 Subjects', category: 'math' },
        // G11 Science
        { key: 'g11_earth_life_science', label: 'Earth & Life Science', group: 'Grade 11 Subjects', category: 'science' },
        { key: 'g11_physical_science', label: 'Physical Science', group: 'Grade 11 Subjects', category: 'science' },
        { key: 'g11_earth_science', label: 'Earth Science', group: 'Grade 11 Subjects', category: 'science' },
        { key: 'g11_general_chemistry_1', label: 'General Chemistry 1', group: 'Grade 11 Subjects', category: 'science' },
        // G12 English — strand-aware
        ...englishG12Fields,
        // G12 Science
        { key: 'g12_general_physics_1', label: 'General Physics 1', group: 'Grade 12 Subjects', category: 'science' },
        { key: 'g12_general_physics_2', label: 'General Physics 2', group: 'Grade 12 Subjects', category: 'science' },
        { key: 'g12_general_biology_1', label: 'General Biology 1', group: 'Grade 12 Subjects', category: 'science' },
        { key: 'g12_general_biology_2', label: 'General Biology 2', group: 'Grade 12 Subjects', category: 'science' },
        { key: 'g12_general_chemistry_2', label: 'General Chemistry 2', group: 'Grade 12 Subjects', category: 'science' },
        { key: 'g12_earth_life_science', label: 'Earth & Life Science', group: 'Grade 12 Subjects', category: 'science' },
        { key: 'g12_physical_science', label: 'Physical Science', group: 'Grade 12 Subjects', category: 'science' },
    ];
});

const buildInitialGrades = () => {
    const g = props.user.grades ?? {};
    const result = {};
    knownGradeFields.value.forEach(f => { result[f.key] = g[f.key] != null ? String(g[f.key]) : ''; });
    return result;
};
const editableGrades = ref(buildInitialGrades());

let _dynCounter = 0;
const buildInitialDynamic = () => {
    const raw = props.user.grades?.dynamic_subjects ?? [];
    return raw.map(s => ({ id: ++_dynCounter, name: s.subject ?? s.name ?? '', grade: s.grade != null ? String(s.grade) : '', category: s.category && ['math','english','science'].includes(s.category) ? s.category : 'math' }));
};
const dynamicGradeSubjects = ref(buildInitialDynamic());

const addDynamicSubject = () => { dynamicGradeSubjects.value.push({ id: ++_dynCounter, name: '', grade: '', category: 'math' }); };
const removeDynamicSubject = (id) => { dynamicGradeSubjects.value = dynamicGradeSubjects.value.filter(s => s.id !== id); };

const knownGradeGroups = computed(() => {
    const groups = {};
    knownGradeFields.value.forEach(f => { if (!groups[f.group]) groups[f.group] = []; groups[f.group].push(f); });
    return Object.entries(groups).map(([title, fields]) => ({ title, fields }));
});

const computeAvg = (category) => {
    const values = [];
    knownGradeFields.value.forEach(f => { if (f.category === category) { const v = parseFloat(editableGrades.value[f.key]); if (!isNaN(v) && v >= 0 && v <= 100) values.push(v); } });
    dynamicGradeSubjects.value.forEach(s => { if (s.category === category && s.name.trim() !== '') { const v = parseFloat(s.grade); if (!isNaN(v) && v >= 0 && v <= 100) values.push(v); } });
    if (!values.length) return null;
    return (values.reduce((a, b) => a + b, 0) / values.length).toFixed(2);
};

const liveAvgMath = computed(() => computeAvg('math'));
const liveAvgEnglish = computed(() => computeAvg('english'));
const liveAvgScience = computed(() => computeAvg('science'));
const categoryLabel = { math: 'Math', english: 'English', science: 'Science' };
const categoryColor = { math: 'avg-math', english: 'avg-english', science: 'avg-science' };
const gradesSaved = ref(false);

// ── Grade Input Validation ────────────────
function preventInvalidGradeInput(event) {
    const input = event.target;
    const key = event.key;
    const currentValue = input.value;
    if (key === 'Backspace' || key === 'Delete' || key === 'Tab' || key === 'ArrowLeft' || key === 'ArrowRight' || key === 'ArrowUp' || key === 'ArrowDown' || key === 'Home' || key === 'End' || (event.ctrlKey && (key === 'a' || key === 'c' || key === 'v' || key === 'x'))) return;
    if (key === '-') { event.preventDefault(); return; }
    // Allow decimal point (only one)
    if (key === '.') {
        if (currentValue.includes('.')) event.preventDefault();
        return;
    }
    if (!/^\d$/.test(key)) { event.preventDefault(); return; }
    const selectionStart = input.selectionStart;
    const selectionEnd = input.selectionEnd;
    const futureValue = currentValue.substring(0, selectionStart) + key + currentValue.substring(selectionEnd);
    // Block if more than 2 decimal places
    const dotIndex = futureValue.indexOf('.');
    if (dotIndex !== -1 && futureValue.length - dotIndex - 1 > 2) { event.preventDefault(); return; }
    // Only block if no decimal point — digits after a dot can never push value over 100
    if (!futureValue.includes('.')) {
        const futureNum = parseFloat(futureValue);
        if (!isNaN(futureNum) && futureNum > 100) { event.preventDefault(); }
    }
}

// Clamp known fields reactively
watch(editableGrades, (grades) => {
    for (const key in grades) {
        const val = grades[key];
        if (val !== '' && val != null) {
            const num = parseFloat(val);
            if (!isNaN(num)) {
                if (num < 0) grades[key] = '0';
                else if (num > 100) grades[key] = '100';
            }
        }
    }
}, { deep: true });

// Clamp dynamic subjects reactively
watch(dynamicGradeSubjects, (subjects) => {
    for (const s of subjects) {
        const val = s.grade;
        if (val !== '' && val != null) {
            const num = parseFloat(val);
            if (!isNaN(num)) {
                if (num < 0) s.grade = '0';
                else if (num > 100) s.grade = '100';
            }
        }
    }
}, { deep: true });

const roleName = computed(() => props.roles?.[props.user.role_id] ?? `Role ${props.user.role_id}`);
const roleColor = computed(() => {
    const map = { 1: 'role-applicant', 2: 'role-staff', 3: 'role-evaluator', 4: 'role-interviewer', 5: 'role-admin', 6: 'role-registrar', 7: 'role-super' };
    return map[parseInt(props.user.role_id)] ?? 'role-default';
});

// ── Pull-Out Feature ──────────────────────────────────────────────────────────
const isAdmin     = computed(() => props.currentUserRoleId === 2);
const canActOnPullout = computed(() => isSuperAdmin.value || isAdmin.value);

// Pull-out is only allowed when the interviewer stage has action = 'passed'
const canPullOut = computed(() => {
    if (!isApplicant.value || !canActOnPullout.value) return false;
    const processes = props.user.current_application?.processes ?? [];
    const interviewerProcess = processes.find(p => p.stage === 'interviewer');
    return interviewerProcess?.action === 'passed';
});

const pulloutNotes        = ref('');
const pulloutConfirming   = ref(false);
const pulloutSaving       = ref(false);
const pulloutSuccess      = ref('');
const pulloutError        = ref('');

const openPulloutConfirm = () => {
    if (!pulloutNotes.value.trim() || pulloutNotes.value.trim().length < 5) {
        pulloutError.value = 'Please enter a reason of at least 5 characters.';
        return;
    }
    pulloutError.value = '';
    pulloutConfirming.value = true;
};

const cancelPulloutConfirm = () => {
    pulloutConfirming.value = false;
};

const submitPullout = () => {
    pulloutSaving.value = true;
    pulloutError.value  = '';
    pulloutSuccess.value = '';
    router.post(route('users.pullout', props.user.id), { notes: pulloutNotes.value }, {
        preserveScroll: true,
        onSuccess: (page) => {
            pulloutConfirming.value = false;
            pulloutSuccess.value = page.props.flash?.pullout_success ?? 'Pull-out processed successfully.';
            pulloutNotes.value = '';
        },
        onError: (errors) => {
            pulloutConfirming.value = false;
            pulloutError.value = errors?.notes ?? errors?.message ?? 'An error occurred. Please try again.';
        },
        onFinish: () => {
            pulloutSaving.value = false;
        },
    });
};
</script>

<template>
    <Head title="User Profile" />
    <AppLayout title="User Profile">

        <!-- Access Denied for users without proper role -->
        <div v-if="!isSuperAdmin && !readOnly" class="profile-root">
            <div class="card" style="max-width:600px;margin:4rem auto;">
                <div class="card-header">
                    <div class="card-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    </div>
                    <div>
                        <h2 class="card-title">Access Denied</h2>
                        <p class="card-subtitle">You do not have permission to view this user's profile.</p>
                    </div>
                </div>
                <div style="padding:1rem 1.5rem 1.5rem;">
                    <Link :href="route('users.index')" class="btn btn--primary">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
                        Back to User Management
                    </Link>
                </div>
            </div>
        </div>

        <!-- SuperAdmin or Admin Content -->
        <div v-else>
        <div class="profile-root">

            <!-- ── Read-Only Banner ───────────────────────────────── -->
            <div v-if="readOnly" class="readonly-banner">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                <div>
                    <p class="readonly-banner-title">Read-Only Mode</p>
                    <p class="readonly-banner-text">You are viewing this user's profile. Editing is restricted to SuperAdmins only.</p>
                </div>
            </div>

            <!-- ── Breadcrumb ──────────────────────────────────────── -->
            <nav class="breadcrumb">
                <Link :href="route('users.index')" class="breadcrumb-link">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                    Manage Users
                </Link>
                <span class="breadcrumb-sep">/</span>
                <span class="breadcrumb-current">{{ fullName }}</span>
            </nav>

            <!-- ── Hero ───────────────────────────────────────────── -->
            <div class="hero-card">
                <div class="hero-banner"><div class="hero-banner-pattern"></div></div>
                <div class="hero-body">
                    <div class="hero-avatar-row">
                        <div class="hero-avatar">{{ initials }}</div>
                        <div class="hero-meta">
                            <h1 class="hero-name">{{ fullName }}</h1>
                            <div class="hero-tags">
                                <span :class="['role-badge', roleColor]">{{ roleName }}</span>
                                <span v-if="user.student_number" class="meta-chip">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
                                    #{{ user.student_number }}
                                </span>
                                <span v-if="isApplicant && user.test_passer?.reference_number" class="meta-chip">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                                    Ref: {{ user.test_passer.reference_number }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="hero-contact">
                        <span class="contact-item">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                            {{ user.email }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════ -->
            <!--  APPLICANT VIEW                                        -->
            <!-- ══════════════════════════════════════════════════════ -->
            <template v-if="isApplicant">

                <!-- Tab Bar -->
                <div class="tab-bar">
                    <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id" :class="['tab-btn', activeTab === tab.id ? 'tab-btn--active' : '']">
                        <svg class="shrink-0" viewBox="0 0 24 24" fill="currentColor"><path :d="tab.icon"/></svg>
                        <span class="hidden sm:inline">{{ tab.label }}</span>
                    </button>
                </div>

                <!-- ── Profile Tab ───────────────────────────────── -->
                <div v-show="activeTab === 'profile'" class="tab-panel">
                    <div class="two-col-layout">

                        <!-- Edit Form -->
                        <div class="card card--form">
                            <div class="card-header">
                                <div class="card-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></div>
                                <div>
                                    <h2 class="card-title">{{ readOnly ? 'Profile Details' : 'Edit Profile' }}</h2>
                                    <p class="card-subtitle">{{ readOnly ? 'View applicant account information' : 'Update applicant account details' }}</p>
                                </div>
                            </div>

                            <form @submit.prevent="saveAllChanges" class="form-body">
                                <div v-if="Object.keys(form.errors).length" class="error-banner">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                                    <div>
                                        <p class="error-banner-title">Please fix the following errors:</p>
                                        <ul class="error-list"><li v-for="(err, key) in form.errors" :key="key">{{ err }}</li></ul>
                                    </div>
                                </div>

                                <!-- Personal Info -->
                                <fieldset class="fieldset">
                                    <legend class="fieldset-legend">Personal Information</legend>
                                    <div class="field-grid">
                                        <div class="field"><label class="field-label">First Name <span v-if="!readOnly" class="req">*</span></label><input v-model="form.firstname" type="text" :required="!readOnly" :disabled="readOnly" :class="['field-input', form.errors.firstname ? 'field-input--error' : '', readOnly ? 'field-input--readonly' : '']" placeholder="First name" /><p v-if="form.errors.firstname" class="field-error">{{ form.errors.firstname }}</p></div>
                                        <div class="field"><label class="field-label">Last Name <span v-if="!readOnly" class="req">*</span></label><input v-model="form.lastname" type="text" :required="!readOnly" :disabled="readOnly" :class="['field-input', form.errors.lastname ? 'field-input--error' : '', readOnly ? 'field-input--readonly' : '']" placeholder="Last name" /><p v-if="form.errors.lastname" class="field-error">{{ form.errors.lastname }}</p></div>
                                        <div class="field"><label class="field-label">Middle Name</label><input v-model="form.middlename" type="text" :disabled="readOnly" :class="['field-input', readOnly ? 'field-input--readonly' : '']" placeholder="Middle name" /></div>
                                        <div class="field"><label class="field-label">Extension Name</label><select v-model="form.extension_name" :disabled="readOnly" :class="['field-input', readOnly ? 'field-input--readonly' : '']"><option value="">None</option><option>Jr.</option><option>Sr.</option><option>II</option><option>III</option><option>IV</option></select></div>
                                    </div>
                                </fieldset>

                                <!-- Contact Info -->
                                <fieldset class="fieldset">
                                    <legend class="fieldset-legend">Contact Information</legend>
                                    <div class="field-grid">
                                        <div class="field"><label class="field-label">Email <span class="req">*</span></label><input v-model="form.email" type="email" :required="!readOnly" :disabled="readOnly" :class="['field-input', form.errors.email ? 'field-input--error' : '', readOnly ? 'field-input--readonly' : '']" placeholder="email@example.com" /><p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p></div>
                                    </div>
                                </fieldset>

                                <!-- Academic Background (Applicant only) -->
                                <fieldset v-if="isApplicant" class="fieldset">
                                    <legend class="fieldset-legend">Academic Background</legend>
                                    <div class="field-grid">
                                        <div class="field"><label class="field-label">Strand</label><select v-model="form.strand" :disabled="readOnly" :class="['field-input', readOnly ? 'field-input--readonly' : '']"><option value="">Select Strand</option><option value="STEM">STEM</option><option value="HUMSS">HUMSS</option><option value="ABM">ABM</option><option value="TVL">TVL</option><option value="ICT">ICT</option><option value="GAS">GAS</option></select></div>
                                        <div class="field"><label class="field-label">School (SHS)</label><input v-model="form.school" type="text" :disabled="readOnly" :class="['field-input', readOnly ? 'field-input--readonly' : '']" placeholder="Senior High School name" /></div>
                                        <div class="field"><label class="field-label">Date Graduated</label><input v-model="form.date_graduated" type="date" :disabled="readOnly" :class="['field-input', readOnly ? 'field-input--readonly' : '']" /></div>
                                    </div>
                                </fieldset>

                                <!-- Program Choices -->
                                <fieldset class="fieldset">
                                    <legend class="fieldset-legend">Program Choices</legend>
                                    <div class="field-grid field-grid--3">
                                        <div class="field"><label class="field-label">1st Choice <span v-if="!readOnly" class="req">*</span></label><select v-model="form.applicant_program" :required="!readOnly" :disabled="readOnly" :class="['field-input', form.errors.applicant_program ? 'field-input--error' : '', readOnly ? 'field-input--readonly' : '']"><option value="" disabled>Select program</option><option v-for="p in programs" :key="p.id" :value="p.code">{{ p.name }}</option></select></div>
                                        <div class="field"><label class="field-label">2nd Choice</label><select v-model="form.applicant_second_program" :disabled="readOnly" :class="['field-input', readOnly ? 'field-input--readonly' : '']"><option value="">None</option><option v-for="p in programs" :key="p.id" :value="p.code">{{ p.name }}</option></select></div>
                                        <div class="field"><label class="field-label">3rd Choice</label><select v-model="form.applicant_third_program" :disabled="readOnly" :class="['field-input', readOnly ? 'field-input--readonly' : '']"><option value="">None</option><option v-for="p in programs" :key="p.id" :value="p.code">{{ p.name }}</option></select></div>
                                    </div>
                                </fieldset>

                                <!-- Actions -->
                                <div class="form-actions">
                                    <Link :href="route('users.index')" class="btn btn--ghost">Back</Link>
                                    <Link v-if="!readOnly" :href="route('users.index')" class="btn btn--ghost">Cancel</Link>
                                    <button v-if="!readOnly" type="submit" :disabled="form.processing" class="btn btn--primary">
                                        <span v-if="form.processing" class="spinner"></span>
                                        <svg v-else viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                                        {{ form.processing ? 'Saving…' : 'Save Changes' }}
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Sidebar -->
                        <aside class="sidebar">
                            <div class="card">
                                <div class="card-header"><div class="card-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg></div><div><h3 class="card-title">Academic Background</h3></div></div>
                                <dl class="info-list">
                                    <div class="info-row"><dt>School</dt><dd>{{ user.school || '—' }}</dd></div>
                                    <div class="info-row"><dt>Strand</dt><dd>{{ user.strand || '—' }}</dd></div>
                                    <div class="info-row"><dt>Track</dt><dd>{{ user.track || '—' }}</dd></div>
                                    <div class="info-row"><dt>Date Graduated</dt><dd>{{ formatDateDisplay(user.date_graduated) }}</dd></div>
                                    <div class="info-row"><dt>Graduate Type</dt><dd>{{ user.graduate_types?.[0]?.label || '—' }}</dd></div>
                                </dl>
                            </div>
                            <div class="card">
                                <div class="card-header"><div class="card-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg></div><div><h3 class="card-title">Document Status</h3></div></div>
                                <div class="chip-group">
                                    <span v-for="ds in user.document_statuses" :key="ds.id" class="chip">{{ ds.document_status?.replace(/_/g, ' ') ?? ds.label ?? '—' }}</span>
                                    <span v-if="!user.document_statuses?.length" class="empty-note">No status yet</span>
                                </div>
                            </div>
                            <div v-if="user.test_passer" class="card">
                                <div class="card-header"><div class="card-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 4l5 2.18V11c0 3.5-2.33 6.79-5 7.93C9.33 17.79 7 14.5 7 11V7.18L12 5z"/></svg></div><div><h3 class="card-title">PUPCET Result</h3></div></div>
                                <dl class="info-list">
                                    <div class="info-row"><dt>Reference No.</dt><dd>{{ user.test_passer.reference_number || '—' }}</dd></div>
                                    <div v-if="user.test_passer.pupcet_score" class="info-row"><dt>PUPCET Score</dt><dd class="score-value">{{ user.test_passer.pupcet_score }}</dd></div>
                                </dl>
                            </div>
                        </aside>
                    </div>
                </div>

                <!-- ── Application Tab ───────────────────────────── -->
                <div v-show="activeTab === 'application'" class="tab-panel">
                    <div class="two-col-layout">
                        <div class="card">
                            <div class="card-header"><div class="card-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg></div><div><h2 class="card-title">Application Status</h2></div></div>
                            <template v-if="user.current_application">
                                <dl class="info-list">
                                    <div class="info-row"><dt>Status</dt><dd><span :class="['status-badge', appStatusBadge.cls]">{{ appStatusBadge.label }}</span></dd></div>
                                    <div class="info-row"><dt>Enrollment</dt><dd><span :class="['status-badge', enrollmentBadge.cls]">{{ enrollmentBadge.label }}</span></dd></div>
                                    <div v-if="user.current_application.enrollment_position" class="info-row"><dt>Position</dt><dd class="font-semibold">#{{ user.current_application.enrollment_position }}</dd></div>
                                    <div v-if="user.current_application.submitted_at" class="info-row"><dt>Submitted</dt><dd>{{ new Date(user.current_application.submitted_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) }}</dd></div>
                                </dl>
                            </template>
                            <div v-else class="empty-state"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg><p>No application found</p></div>
                        </div>
                        <div class="card">
                            <div class="card-header"><div class="card-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg></div><div><h2 class="card-title">Program Choices</h2></div></div>
                            <div class="program-list">
                                <div v-if="user.program" class="program-item program-item--first"><span class="program-rank program-rank--first">1</span><div><p class="program-name">{{ user.program.name }}</p><p class="program-code">{{ user.program.code }}</p></div></div>
                                <div v-if="user.second_program" class="program-item"><span class="program-rank program-rank--second">2</span><div><p class="program-name">{{ user.second_program.name }}</p><p class="program-code">{{ user.second_program.code }}</p></div></div>
                                <div v-if="user.third_program" class="program-item"><span class="program-rank program-rank--third">3</span><div><p class="program-name">{{ user.third_program.name }}</p><p class="program-code">{{ user.third_program.code }}</p></div></div>
                                <p v-if="!user.program && !user.second_program && !user.third_program" class="empty-note">No program choices recorded</p>
                            </div>
                        </div>
                        <div v-if="user.current_application?.processes?.length" class="card card--wide">
                            <div class="card-header"><div class="card-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M13 2.05V4.05C17.39 4.59 20.5 8.58 19.96 12.97C19.5 16.61 16.64 19.5 13 19.93V21.93C18.5 21.38 22.5 16.5 21.95 11C21.5 6.25 17.73 2.5 13 2.05M11 2.06C9.05 2.25 7.19 3 5.67 4.26L7.1 5.74C8.22 4.84 9.57 4.26 11 4.06V2.06M4.26 5.67C3 7.19 2.25 9.04 2.05 11H4.05C4.24 9.58 4.8 8.23 5.69 7.1L4.26 5.67M2.06 13C2.26 14.96 3.03 16.81 4.27 18.33L5.69 16.9C4.81 15.77 4.24 14.42 4.06 13H2.06M7.1 18.37L5.67 19.74C7.18 21 9.04 21.79 11 22V20C9.58 19.82 8.23 19.25 7.1 18.37M12 7L9.5 11.5H11.5V17L14.5 12.5H12.5L12 7Z"/></svg></div><div><h2 class="card-title">Application Process Timeline</h2></div></div>
                            <div class="timeline">
                                <div v-for="(process, idx) in user.current_application.processes" :key="process.id" class="timeline-item">
                                    <div :class="['timeline-dot', process.status === 'completed' ? 'timeline-dot--done' : process.status === 'in_progress' ? 'timeline-dot--active' : 'timeline-dot--pending']"></div>
                                    <div class="timeline-content">
                                        <div class="timeline-header"><p class="timeline-name">{{ process.name ?? process.type ?? `Step ${idx + 1}` }}</p><span :class="['status-badge', process.status === 'completed' ? 'status-approved' : process.status === 'in_progress' ? 'status-submitted' : 'status-default']">{{ process.status?.replace(/_/g, ' ') ?? 'Pending' }}</span></div>
                                        <p v-if="process.updated_at" class="timeline-date">{{ new Date(process.updated_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) }}</p>
                                        <p v-if="process.remarks" class="timeline-remark">{{ process.remarks }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- ── Pull-Out Card (Admin/SuperAdmin only, post-interview) ── -->
                        <div v-if="canPullOut || (isApplicant && canActOnPullout && !canPullOut)" class="card card--wide pullout-card">
                            <div class="card-header pullout-card-header">
                                <div class="card-icon pullout-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                                </div>
                                <div>
                                    <h2 class="card-title pullout-title">Process Pull-Out</h2>
                                    <p class="card-subtitle">Revert applicant to interview queue &amp; reclaim program slot</p>
                                </div>
                            </div>

                            <!-- Success Banner -->
                            <div v-if="pulloutSuccess" class="pullout-success-banner">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                                <span>{{ pulloutSuccess }}</span>
                            </div>

                            <!-- Not Eligible State -->
                            <div v-if="!canPullOut" class="pullout-not-eligible">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                                <p>Pull-out is only available for applicants who have <strong>passed the interview stage</strong>. This applicant has not yet passed their interview.</p>
                            </div>

                            <!-- Active Pull-Out Form -->
                            <template v-if="canPullOut">
                                <div class="pullout-body">
                                    <div class="pullout-warning">
                                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                                        <div>
                                            <p class="pullout-warning-title">This action cannot be undone automatically.</p>
                                            <p class="pullout-warning-text">
                                                This will revert <strong>{{ user.firstname }} {{ user.lastname }}</strong> back to the interviewer queue,
                                                delete their medical/records stage progress, and return <strong>1 slot</strong> to
                                                <strong>{{ user.current_application?.program?.name ?? user.program?.name ?? 'their program' }}</strong>.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label pullout-label">Pull-Out Reason / Notes <span class="req">*</span></label>
                                        <textarea
                                            v-model="pulloutNotes"
                                            rows="3"
                                            maxlength="1000"
                                            placeholder="e.g., Applicant informed us they are pulling out and will not pursue PUP enrollment."
                                            class="field-input pullout-textarea"
                                        ></textarea>
                                        <p v-if="pulloutError" class="field-error">{{ pulloutError }}</p>
                                        <p class="field-hint">{{ pulloutNotes.length }}/1000 characters. Minimum 5 characters.</p>
                                    </div>
                                    <div class="pullout-actions">
                                        <button type="button" class="btn btn--danger" @click="openPulloutConfirm" :disabled="pulloutSaving">
                                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                                            Process Pull-Out
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- ── Pull-Out Confirmation Modal ──────────────────────── -->
                <Teleport to="body">
                    <div v-if="pulloutConfirming" class="pullout-overlay" @click.self="cancelPulloutConfirm">
                        <div class="pullout-modal" role="dialog" aria-modal="true">
                            <div class="pullout-modal-header">
                                <div class="pullout-modal-icon">
                                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                                </div>
                                <div>
                                    <h3 class="pullout-modal-title">Confirm Pull-Out</h3>
                                    <p class="pullout-modal-subtitle">Please review the changes below before confirming.</p>
                                </div>
                            </div>
                            <div class="pullout-modal-body">
                                <p class="pullout-modal-applicant">Applicant: <strong>{{ user.firstname }} {{ user.lastname }}</strong> ({{ user.email }})</p>
                                <ul class="pullout-modal-list">
                                    <li>Application status → <strong>Submitted</strong> (enrollment cleared)</li>
                                    <li>Interviewer stage → <strong>In Progress</strong> (returned to interview queue)</li>
                                    <li>Medical &amp; Records stages → <strong>Deleted</strong></li>
                                    <li><strong>+1 slot</strong> returned to <strong>{{ user.current_application?.program?.name ?? user.program?.name ?? 'their program' }}</strong></li>
                                </ul>
                                <div class="pullout-modal-notes">
                                    <p class="pullout-modal-notes-label">Reason/Notes:</p>
                                    <p class="pullout-modal-notes-text">{{ pulloutNotes }}</p>
                                </div>
                            </div>
                            <div class="pullout-modal-footer">
                                <button type="button" class="btn btn--ghost" @click="cancelPulloutConfirm" :disabled="pulloutSaving">Cancel</button>
                                <button type="button" class="btn btn--danger" @click="submitPullout" :disabled="pulloutSaving">
                                    <span v-if="pulloutSaving" class="spinner"></span>
                                    <svg v-else viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                                    {{ pulloutSaving ? 'Processing…' : 'Confirm Pull-Out' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </Teleport>
                <!-- ── Grades Tab ─────────────────────────────────── -->
                <div v-show="activeTab === 'grades'" class="tab-panel">
                    <div v-if="gradesSaved" class="grades-saved-banner"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>Grades saved successfully. Category averages and program qualification have been recomputed.</div>
                    <div class="avg-summary-bar">
                        <div class="avg-chip avg-chip--math"><span class="avg-chip-label">Math Avg</span><span class="avg-chip-value" :class="liveAvgMath ? gradeColor(liveAvgMath) : 'grade-neutral'">{{ liveAvgMath ?? '—' }}</span></div>
                        <div class="avg-chip avg-chip--english"><span class="avg-chip-label">English Avg</span><span class="avg-chip-value" :class="liveAvgEnglish ? gradeColor(liveAvgEnglish) : 'grade-neutral'">{{ liveAvgEnglish ?? '—' }}</span></div>
                        <div class="avg-chip avg-chip--science"><span class="avg-chip-label">Science Avg</span><span class="avg-chip-value" :class="liveAvgScience ? gradeColor(liveAvgScience) : 'grade-neutral'">{{ liveAvgScience ?? '—' }}</span></div>
                        <p v-if="!readOnly" class="avg-summary-note">Averages update live as you edit. Saved values determine program qualification.</p>
                        <p v-else class="avg-summary-note">Grade averages are displayed below.</p>
                    </div>
                    <form @submit.prevent="saveAllChanges" class="grade-groups">
                        <div v-for="group in knownGradeGroups" :key="group.title" class="card">
                            <div class="card-header"><div class="card-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg></div><h2 class="card-title">{{ group.title }}</h2></div>
                            <div class="grade-edit-list">
                                <div v-for="field in group.fields" :key="field.key" class="grade-edit-row">
                                    <span v-if="field.category" :class="['cat-badge', 'cat-badge--' + field.category]">{{ categoryLabel[field.category] }}</span>
                                    <label class="grade-edit-label">{{ field.label }}</label>
                                    <div class="grade-edit-right">
                                        <input v-model="editableGrades[field.key]" type="text" inputmode="decimal" @keydown="preventInvalidGradeInput" :disabled="readOnly" :class="['grade-edit-input', readOnly ? 'field-input--readonly' : '']" placeholder="—" />
                                        <span v-if="editableGrades[field.key] !== ''" :class="['grade-value', gradeColor(editableGrades[field.key])]">{{ Number(editableGrades[field.key]).toFixed(2) }}</span>
                                        <span v-else class="grade-value grade-neutral">—</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header"><div class="card-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg></div><h2 class="card-title">Additional Subjects</h2></div>
                            <div v-if="dynamicGradeSubjects.length" class="grade-dynamic-header">
                                <span class="dyn-col-cat">Category</span>
                                <span class="dyn-col-name">Subject Name <span v-if="!readOnly" class="req">*</span></span>
                                <span class="dyn-col-grade">Grade <span v-if="!readOnly" class="req">*</span></span>
                                <span class="dyn-col-remove"></span>
                            </div>
                            <div class="grade-edit-list">
                                <div v-if="dynamicGradeSubjects.length === 0" class="grade-empty-note">No additional subjects yet. Click "Add Subject" to add one.</div>
                                <div v-for="subject in dynamicGradeSubjects" :key="subject.id" class="grade-dynamic-row">
                                    <select v-model="subject.category" :required="!readOnly" :disabled="readOnly" :class="['grade-edit-input grade-edit-input--cat', readOnly ? 'field-input--readonly' : '']"><option value="math">Math</option><option value="english">English</option><option value="science">Science</option></select>
                                    <input v-model="subject.name" type="text" :required="!readOnly" :disabled="readOnly" :class="['grade-edit-input grade-edit-input--name', readOnly ? 'field-input--readonly' : '']" placeholder="Subject name" maxlength="100" />
                                    <input v-model="subject.grade" type="text" inputmode="decimal" @keydown="preventInvalidGradeInput" :required="!readOnly" :disabled="readOnly" :class="['grade-edit-input grade-edit-input--grade', readOnly ? 'field-input--readonly' : '']" placeholder="0–100" />
                                    <button v-if="!readOnly" type="button" class="grade-remove-btn" @click="removeDynamicSubject(subject.id)" title="Remove subject"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
                                </div>
                            </div>
                            <div v-if="!readOnly" class="grade-add-row">
                                <button type="button" class="btn-add-subject" @click="addDynamicSubject"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>Add Subject</button>
                                <p class="grade-add-hint">Each subject is included in its category's average, which affects program qualification.</p>
                            </div>
                        </div>
                        <!-- Save button -->
                        <div v-if="!readOnly" class="grades-save-bar">
                            <button type="submit" class="btn btn--primary" :disabled="allChangesSaving">
                                <span v-if="allChangesSaving" class="spinner"></span>
                                <svg v-else viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                                {{ allChangesSaving ? 'Saving…' : 'Save Changes' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- ── Documents Tab ──────────────────────────────── -->
                <div v-show="activeTab === 'documents'" class="tab-panel tab-panel--documents">
                    <template v-if="user.files?.length">
                        <div class="doc-grid">
                            <div v-for="file in user.files" :key="file.id" class="doc-card">
                                <div class="doc-card-top">
                                    <div class="doc-icon shrink-0"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg></div>
                                    <div class="doc-meta"><p class="doc-type" :title="docTypeLabels[file.type] ?? file.type?.replace(/_/g, ' ') ?? 'Document'">{{ docTypeLabels[file.type] ?? file.type?.replace(/_/g, ' ') ?? 'Document' }}</p><p class="doc-filename" :title="file.original_name ?? '—'">{{ file.original_name ?? '—' }}</p></div>
                                    <span :class="['status-badge', 'shrink-0', docStatusBadge(file.status).cls]">{{ docStatusBadge(file.status).label }}</span>
                                </div>
                                <div v-if="file.comment" class="doc-remark"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg><span>{{ file.comment }}</span></div>
                                <p class="doc-date">Uploaded {{ file.created_at ? new Date(file.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—' }}</p>
                            </div>
                        </div>
REPLACE
                        <div class="card summary-card card--wide">
                            <div class="card-header"><div class="card-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg></div><h3 class="card-title">Upload Summary</h3></div>
                            <div class="summary-grid">
                                <div class="summary-stat summary-stat--neutral"><span class="summary-num">{{ user.files.length }}</span><span class="summary-label">Total Files</span></div>
                                <div class="summary-stat summary-stat--green"><span class="summary-num">{{ user.files.filter(f => f.status === 'approved').length }}</span><span class="summary-label">Approved</span></div>
                                <div class="summary-stat summary-stat--yellow"><span class="summary-num">{{ user.files.filter(f => f.status === 'pending').length }}</span><span class="summary-label">Pending</span></div>
                                <div class="summary-stat summary-stat--red"><span class="summary-num">{{ user.files.filter(f => f.status === 'returned').length }}</span><span class="summary-label">Returned</span></div>
                            </div>
                        </div>
                    </template>
                    <div v-else class="empty-card"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg><p class="empty-card-title">No documents uploaded yet</p><p class="empty-card-sub">Documents will appear here once the applicant uploads their requirements.</p></div>
                </div>
            </template>

            <!-- ══════════════════════════════════════════════════════ -->
            <!--  STAFF VIEW                                            -->
            <!-- ══════════════════════════════════════════════════════ -->
            <template v-else>
                <div class="staff-form-wrap">
                    <div class="card card--form">
                        <div class="card-header"><div class="card-icon"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></div><div><h2 class="card-title">{{ readOnly ? 'Staff Profile' : 'Edit Staff Profile' }}</h2><p class="card-subtitle">{{ readOnly ? 'View account details, role, and program assignments' : 'Update account details, role, and program assignments' }}</p></div></div>
                        <form @submit.prevent="saveAllChanges" class="form-body">
                            <div v-if="Object.keys(form.errors).length" class="error-banner"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg><div><p class="error-banner-title">Please fix the following errors:</p><ul class="error-list"><li v-for="(err, key) in form.errors" :key="key">{{ err }}</li></ul></div></div>
                            <fieldset class="fieldset"><legend class="fieldset-legend">Personal Information</legend>
                                <div class="field-grid">
                                    <div class="field"><label class="field-label">First Name <span class="req">*</span></label><input v-model="form.firstname" type="text" :required="!readOnly" :disabled="readOnly" :class="['field-input', form.errors.firstname ? 'field-input--error' : '', readOnly ? 'field-input--readonly' : '']" placeholder="First name" /><p v-if="form.errors.firstname" class="field-error">{{ form.errors.firstname }}</p></div>
                                    <div class="field"><label class="field-label">Last Name <span v-if="!readOnly" class="req">*</span></label><input v-model="form.lastname" type="text" :required="!readOnly" :disabled="readOnly" :class="['field-input', form.errors.lastname ? 'field-input--error' : '', readOnly ? 'field-input--readonly' : '']" placeholder="Last name" /><p v-if="form.errors.lastname" class="field-error">{{ form.errors.lastname }}</p></div>
                                    <div class="field"><label class="field-label">Middle Name</label><input v-model="form.middlename" type="text" :disabled="readOnly" :class="['field-input', readOnly ? 'field-input--readonly' : '']" placeholder="Middle name" /></div>
                                    <div class="field"><label class="field-label">Extension Name</label><select v-model="form.extension_name" :disabled="readOnly" :class="['field-input', readOnly ? 'field-input--readonly' : '']"><option value="">None</option><option>Jr.</option><option>Sr.</option><option>II</option><option>III</option><option>IV</option></select></div>
                                </div>
                            </fieldset>
                            <fieldset class="fieldset"><legend class="fieldset-legend">Contact Information</legend>
                                <div class="field-grid"><div class="field"><label class="field-label">Email <span class="req">*</span></label><input v-model="form.email" type="email" :required="!readOnly" :disabled="readOnly" :class="['field-input', form.errors.email ? 'field-input--error' : '', readOnly ? 'field-input--readonly' : '']" placeholder="email@example.com" /><p v-if="form.errors.email" class="field-error">{{ form.errors.email }}</p></div></div>
                            </fieldset>
                            <fieldset class="fieldset"><legend class="fieldset-legend">Role & Assignment</legend>
                                <div class="field-grid">
                                    <div class="field"><label class="field-label">Role <span v-if="!readOnly" class="req">*</span></label><select v-model="form.role_id" @change="onRoleChange" :required="!readOnly" :disabled="readOnly" :class="['field-input', form.errors.role_id ? 'field-input--error' : '', readOnly ? 'field-input--readonly' : '']"><option v-for="(name, id) in roles" :key="id" :value="id">{{ name }}</option></select><p v-if="form.errors.role_id" class="field-error">{{ form.errors.role_id }}</p></div>
                                    <div v-if="showProgramAssignment" class="field"><label class="field-label">Program Assignment <span v-if="!readOnly" class="req">*</span></label><select v-model="form.program" multiple size="4" :disabled="readOnly" :class="['field-input field-input--multi', form.errors.program ? 'field-input--error' : '', readOnly ? 'field-input--readonly' : '']"><option v-for="p in programs" :key="p.id" :value="p.code">{{ p.name }} ({{ p.code }})</option></select><p v-if="!readOnly" class="field-hint">Hold Ctrl / Cmd to select multiple programs.</p><p v-if="form.errors.program" class="field-error">{{ form.errors.program }}</p></div>
                                </div>
                            </fieldset>
                            <div class="form-actions">
                                <Link :href="route('users.index')" class="btn btn--ghost">{{ readOnly ? 'Back' : 'Cancel' }}</Link>
                                <button v-if="!readOnly" type="submit" :disabled="form.processing" class="btn btn--primary"><span v-if="form.processing" class="spinner"></span><svg v-else viewBox="0 0 24 24" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>{{ form.processing ? 'Saving…' : 'Save Changes' }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </template>
        </div>
    </div>
    </AppLayout>

    <!-- Unified Save All Confirmation Modal -->
    <ChangesConfirmationModal
        :show="showConfirmModal"
        :changes="allChanges"
        :loading="allChangesSaving"
        title="Confirm All Changes"
        subtitle="Review all changes across all sections before saving"
        @confirm="confirmAllSave"
        @cancel="showConfirmModal = false"
    />
</template>

<style scoped>
:root { --brand: #9E122C; --brand-light: #c81e3d; --brand-pale: #fdf2f4; --brand-dim: rgba(158,18,44,.08); }
.profile-root { min-height:100vh; background:#f4f5f7; padding:1.25rem 1rem 3rem; font-family:'DM Sans','Segoe UI',system-ui,sans-serif; }
@media (min-width:768px) { .profile-root { padding:1.75rem 1.5rem 3rem; } }
.breadcrumb { display:flex; align-items:center; gap:.5rem; font-size:.8rem; color:#6b7280; margin-bottom:1.25rem; }
.breadcrumb-link { display:flex; align-items:center; gap:.3rem; color:#6b7280; text-decoration:none; transition:color .15s; }
.breadcrumb-link:hover { color:var(--brand); }
.breadcrumb-link svg { width:14px; height:14px; }
.breadcrumb-sep { color:#d1d5db; }
.breadcrumb-current { color:#111827; font-weight:600; }
.hero-card { background:linear-gradient(135deg,var(--brand) 0%,var(--brand-light) 100%); border-radius:20px; border:none; overflow:hidden; margin-bottom:1.25rem; box-shadow:0 4px 20px rgba(158,18,44,.35); position:relative; }
.hero-banner { display:none; }
.hero-banner-pattern { position:absolute; inset:0; z-index:0; pointer-events:none; background-image:radial-gradient(circle at 10% 30%, rgba(255,255,255,.12) 0%, transparent 50%), radial-gradient(circle at 85% 15%, rgba(255,255,255,.08) 0%, transparent 40%), radial-gradient(circle at 60% 80%, rgba(0,0,0,.08) 0%, transparent 40%); }
.hero-body { padding:1.5rem 1.5rem 1.25rem; position:relative; z-index:1; }
.hero-avatar-row { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem; margin-top:0; }
.hero-avatar { width:72px; height:72px; border-radius:16px; background:rgba(255,255,255,.15); border:2px solid rgba(255,255,255,.4); box-shadow:0 4px 16px rgba(0,0,0,.2); display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:700; color:#fff; flex-shrink:0; backdrop-filter:blur(4px); }
.hero-meta { padding-bottom:2px; }
.hero-name { font-size:1.15rem; font-weight:700; color:#fff; margin:0 0 .35rem; }
.hero-tags { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
.meta-chip { display:inline-flex; align-items:center; gap:.3rem; font-size:.7rem; color:rgba(255,255,255,.9); background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.25); border-radius:20px; padding:2px 8px; }
.meta-chip svg { width:12px; height:12px; }
.hero-contact { display:flex; flex-wrap:wrap; gap:.75rem 1.25rem; margin-top:.75rem; padding-top:.75rem; border-top:1px solid rgba(255,255,255,.2); }
.contact-item { display:flex; align-items:center; gap:.4rem; font-size:.8rem; color:rgba(255,255,255,.85); }
.contact-item svg { width:14px; height:14px; color:rgba(255,255,255,.6); }
.role-badge { font-size:.68rem; font-weight:700; letter-spacing:.03em; text-transform:uppercase; padding:3px 10px; border-radius:20px; }
.role-applicant { background:#dbeafe; color:#1d4ed8; }
.role-staff { background:#fef9c3; color:#854d0e; }
.role-evaluator { background:#ede9fe; color:#6d28d9; }
.role-interviewer { background:#dcfce7; color:#15803d; }
.role-admin { background:#fce7f3; color:#be185d; }
.role-registrar { background:#ffedd5; color:#c2410c; }
.role-super { background:#fee2e2; color:#b91c1c; }
.role-default { background:#f3f4f6; color:#374151; }
.tab-bar { display:flex; overflow-x:auto; background:#fff; border-radius:16px; border:1px solid #e5e7eb; margin-bottom:1.25rem; box-shadow:0 1px 4px rgba(0,0,0,.06); scrollbar-width:none; }
.tab-bar::-webkit-scrollbar { display:none; }
.tab-btn { display:flex; align-items:center; gap:.5rem; padding:.85rem 1.25rem; font-size:.82rem; font-weight:500; color:#6b7280; border:none; background:transparent; cursor:pointer; border-bottom:2px solid transparent; white-space:nowrap; transition:color .15s,border-color .15s,background .15s; }
.tab-btn svg { width:15px; height:15px; }
.tab-btn:hover { color:#374151; background:#f9fafb; }
.tab-btn--active { color:var(--brand); border-bottom-color:var(--brand); background:var(--brand-pale); }
.tab-panel { animation:fadeIn .18s ease; }
@keyframes fadeIn { from { opacity:0; transform:translateY(4px); } to { opacity:1; transform:none; } }
.two-col-layout { display:grid; grid-template-columns:1fr; gap:1.25rem; }
@media (min-width:1024px) { .two-col-layout { grid-template-columns:1fr 340px; } }
.sidebar { display:flex; flex-direction:column; gap:1.25rem; }
.staff-form-wrap { max-width:780px; margin:0 auto; }
.grade-groups { display:flex; flex-direction:column; gap:1.25rem; }
.card { background:#fff; border-radius:16px; border:1px solid #e5e7eb; box-shadow:0 1px 4px rgba(0,0,0,.05); overflow:hidden; }
.card--accent { background:linear-gradient(135deg,var(--brand) 0%,var(--brand-light) 100%); border-color:transparent; }
.card--wide { grid-column:1 / -1; }
.card-header { display:flex; align-items:center; gap:.75rem; padding:1rem 1.25rem; border-bottom:1px solid #f3f4f6; }
.card-icon { width:34px; height:34px; border-radius:10px; background:var(--brand-pale); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.card-icon svg { width:16px; height:16px; fill:var(--brand); }
.card-icon--light { background:rgba(255,255,255,.2); }
.card-icon--light svg { fill:#fff; }
.card-title { font-size:.9rem; font-weight:700; color:#111827; margin:0; }
.card-title--light { color:#fff; }
.card-subtitle { font-size:.73rem; color:#6b7280; margin:2px 0 0; }
.form-body { padding:1.25rem; display:flex; flex-direction:column; gap:1.5rem; }
.fieldset { border:none; padding:0; margin:0; }
.fieldset-legend { font-size:.72rem; font-weight:700; letter-spacing:.07em; text-transform:uppercase; color:#9ca3af; margin-bottom:.75rem; display:block; }
.field-grid { display:grid; grid-template-columns:1fr; gap:.875rem; }
@media (min-width:640px) { .field-grid { grid-template-columns:1fr 1fr; } }
@media (min-width:768px) { .field-grid--3 { grid-template-columns:1fr 1fr 1fr; } }
.field { display:flex; flex-direction:column; gap:.3rem; }
.field-label { font-size:.8rem; font-weight:600; color:#374151; }
.req { color:var(--brand); }
.field-input { width:100%; padding:.55rem .75rem; border-radius:10px; border:1.5px solid #e5e7eb; background:#f9fafb; font-size:.83rem; color:#111827; transition:border-color .15s,box-shadow .15s; outline:none; appearance:none; }
.field-input:focus { border-color:var(--brand); box-shadow:0 0 0 3px var(--brand-dim); background:#fff; }
.field-input--error { border-color:#f87171; background:#fff5f5; }
.field-input--multi { height:auto; }
.field-error { font-size:.72rem; color:#dc2626; }
.field-hint { font-size:.72rem; color:#9ca3af; }
.form-actions { display:flex; justify-content:flex-end; gap:.75rem; padding-top:.5rem; border-top:1px solid #f3f4f6; }
.btn { display:inline-flex; align-items:center; gap:.5rem; padding:.55rem 1.1rem; border-radius:10px; font-size:.83rem; font-weight:600; cursor:pointer; transition:all .15s; border:none; }
.btn svg { width:15px; height:15px; }
.btn--ghost { background:transparent; border:1.5px solid #e5e7eb; color:#374151; text-decoration:none; }
.btn--ghost:hover { background:#f9fafb; }
.btn--primary { background:linear-gradient(135deg,var(--brand),var(--brand-light)); color:#fff; box-shadow:0 2px 8px rgba(158,18,44,.3); }
.btn--primary:hover { box-shadow:0 4px 14px rgba(158,18,44,.4); transform:translateY(-1px); }
.btn--primary:disabled { opacity:.6; transform:none; cursor:not-allowed; }
.spinner { width:14px; height:14px; border:2px solid rgba(255,255,255,.3); border-top-color:#fff; border-radius:50%; animation:spin .6s linear infinite; flex-shrink:0; }
@keyframes spin { to { transform:rotate(360deg); } }
.error-banner { display:flex; gap:.75rem; padding:.9rem 1rem; background:#fff5f5; border:1px solid #fecaca; border-radius:10px; }
.error-banner svg { width:18px; height:18px; fill:#dc2626; flex-shrink:0; margin-top:1px; }
.error-banner-title { font-size:.8rem; font-weight:700; color:#dc2626; margin-bottom:.3rem; }
.error-list { font-size:.78rem; color:#b91c1c; padding-left:1.1rem; margin:0; }
.info-list { padding:.75rem 1.25rem; display:flex; flex-direction:column; gap:0; }
.info-row { display:flex; justify-content:space-between; align-items:baseline; gap:1rem; padding:.6rem 0; border-bottom:1px solid #f3f4f6; font-size:.82rem; }
.info-row:last-child { border-bottom:none; }
.info-row dt { color:#6b7280; flex-shrink:0; }
.info-row dd { font-weight:600; color:#111827; text-align:right; }
.info-list--light { padding:.75rem 1.25rem; }
.info-row--light { border-bottom-color:rgba(255,255,255,.15); }
.info-row--light dt { color:rgba(255,255,255,.7); }
.info-row--light dd { color:#fff; }
.score-value { font-size:1.1rem; font-weight:800; color:#fff; }
.status-badge { font-size:.68rem; font-weight:700; padding:3px 9px; border-radius:20px; white-space:nowrap; text-transform:capitalize; }
.status-pending { background:#fef9c3; color:#92400e; }
.status-submitted { background:#dbeafe; color:#1e40af; }
.status-approved { background:#dcfce7; color:#15803d; }
.status-rejected { background:#fee2e2; color:#b91c1c; }
.status-waitlisted { background:#ffedd5; color:#c2410c; }
.status-default { background:#f3f4f6; color:#374151; }
.chip-group { padding:.75rem 1.25rem; display:flex; flex-wrap:wrap; gap:.4rem; }
.chip { font-size:.72rem; font-weight:500; padding:3px 10px; border-radius:20px; background:#f3f4f6; color:#374151; text-transform:capitalize; }
.empty-note { font-size:.82rem; color:#9ca3af; padding:.25rem 0; }
.program-list { padding:.75rem 1.25rem; display:flex; flex-direction:column; gap:.6rem; }
.program-item { display:flex; align-items:center; gap:.75rem; padding:.7rem .9rem; border-radius:12px; background:#f9fafb; border:1px solid #f3f4f6; }
.program-item--first { background:var(--brand-pale); border-color:rgba(158,18,44,.15); }
.program-rank { width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.72rem; font-weight:800; color:#fff; flex-shrink:0; }
.program-rank--first { background:var(--brand); }
.program-rank--second { background:#9ca3af; }
.program-rank--third { background:#d1d5db; color:#374151; }
.program-name { font-size:.83rem; font-weight:700; color:#111827; margin:0 0 2px; }
.program-code { font-size:.72rem; color:#6b7280; margin:0; }
.timeline { padding:.75rem 1.25rem 1rem; position:relative; }
.timeline::before { content:''; position:absolute; left:calc(1.25rem + 7px); top:1.25rem; bottom:1.25rem; width:2px; background:#f3f4f6; }
.timeline-item { display:flex; gap:1rem; position:relative; margin-bottom:1rem; }
.timeline-item:last-child { margin-bottom:0; }
.timeline-dot { width:16px; height:16px; border-radius:50%; flex-shrink:0; margin-top:3px; border:2px solid #fff; box-shadow:0 0 0 2px currentColor; z-index:1; position:relative; }
.timeline-dot--done { background:#22c55e; color:#22c55e; }
.timeline-dot--active { background:#3b82f6; color:#3b82f6; }
.timeline-dot--pending { background:#d1d5db; color:#d1d5db; }
.timeline-content { flex:1; background:#f9fafb; border:1px solid #f3f4f6; border-radius:12px; padding:.65rem .9rem; }
.timeline-header { display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap; }
.timeline-name { font-size:.83rem; font-weight:700; color:#111827; margin:0; }
.timeline-date { font-size:.72rem; color:#9ca3af; margin:.25rem 0 0; }
.timeline-remark { font-size:.76rem; color:#4b5563; margin:.3rem 0 0; font-style:italic; }
.grade-edit-list { display:flex; flex-direction:column; padding:.5rem 1.25rem .75rem; gap:.5rem; }
.grade-edit-row { display:flex; align-items:center; gap:.75rem; padding:.45rem 0; border-bottom:1px solid #f9fafb; }
.grade-edit-row:last-child { border-bottom:none; }
.grade-edit-label { font-size:.8rem; color:#4b5563; flex:1; }
.grade-edit-right { display:flex; align-items:center; gap:.6rem; }
.grade-edit-input { width:90px; padding:.35rem .6rem; border-radius:8px; border:1.5px solid #e5e7eb; background:#f9fafb; font-size:.82rem; color:#111827; text-align:right; outline:none; transition:border-color .15s,box-shadow .15s; -moz-appearance:textfield; appearance:textfield; }
.grade-edit-input::-webkit-outer-spin-button, .grade-edit-input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
.grade-edit-input:focus { border-color:var(--brand); box-shadow:0 0 0 3px var(--brand-dim); background:#fff; }
.grade-edit-input--name { width:auto; flex:1; text-align:left; }
.grade-edit-input--grade { width:90px; }
.grade-dynamic-row { display:flex; align-items:center; gap:.6rem; padding:.35rem 0; border-bottom:1px solid #f9fafb; }
.grade-dynamic-row:last-child { border-bottom:none; }
.grade-remove-btn { flex-shrink:0; width:28px; height:28px; border-radius:50%; border:none; background:transparent; cursor:pointer; display:flex; align-items:center; justify-content:center; color:#ef4444; transition:background .15s,color .15s; }
.grade-remove-btn:hover { background:#fee2e2; color:#b91c1c; }
.grade-remove-btn svg { width:14px; height:14px; }
.grade-add-row { padding:.6rem 1.25rem 1rem; }
.btn-add-subject { display:inline-flex; align-items:center; gap:.4rem; padding:.4rem .9rem; border-radius:8px; font-size:.8rem; font-weight:600; color:var(--brand); background:var(--brand-pale); border:1.5px solid rgba(158,18,44,.2); cursor:pointer; transition:background .15s,box-shadow .15s; }
.btn-add-subject:hover { background:#fce7ec; box-shadow:0 2px 6px rgba(158,18,44,.15); }
.btn-add-subject svg { width:14px; height:14px; stroke:var(--brand); }
.grade-empty-note { font-size:.8rem; color:#9ca3af; padding:.5rem 0; }
.grades-save-bar { display:flex; justify-content:flex-end; margin-top:1.25rem; }
.grades-saved-banner { display:flex; align-items:center; gap:.5rem; padding:.75rem 1rem; margin-bottom:1rem; background:#dcfce7; border:1px solid #86efac; border-radius:10px; font-size:.83rem; font-weight:600; color:#15803d; }
.grades-saved-banner svg { width:16px; height:16px; fill:#15803d; flex-shrink:0; }
.avg-summary-bar { display:flex; flex-wrap:wrap; align-items:center; gap:.75rem; padding:.85rem 1.1rem; background:#fff; border:1px solid #e5e7eb; border-radius:14px; margin-bottom:1.25rem; box-shadow:0 1px 4px rgba(0,0,0,.05); }
.avg-chip { display:flex; align-items:center; gap:.5rem; padding:.35rem .8rem; border-radius:10px; }
.avg-chip--math { background:#eff6ff; border:1px solid #bfdbfe; }
.avg-chip--english { background:#f0fdf4; border:1px solid #bbf7d0; }
.avg-chip--science { background:#fefce8; border:1px solid #fde68a; }
.avg-chip-label { font-size:.72rem; font-weight:600; color:#6b7280; }
.avg-chip-value { font-size:.88rem; font-weight:800; font-variant-numeric:tabular-nums; }
.avg-summary-note { font-size:.72rem; color:#9ca3af; margin-left:auto; }
.cat-badge { font-size:.62rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; padding:2px 7px; border-radius:20px; flex-shrink:0; }
.cat-badge--math { background:#dbeafe; color:#1d4ed8; }
.cat-badge--english { background:#dcfce7; color:#15803d; }
.cat-badge--science { background:#fef9c3; color:#854d0e; }
.grade-dynamic-header { display:flex; align-items:center; gap:.6rem; padding:.3rem 1.25rem; background:#f9fafb; border-bottom:1px solid #f3f4f6; }
.dyn-col-cat { width:110px; font-size:.7rem; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; flex-shrink:0; }
.dyn-col-name { flex:1; font-size:.7rem; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; }
.dyn-col-grade { width:90px; font-size:.7rem; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; flex-shrink:0; }
.dyn-col-remove { width:28px; flex-shrink:0; }
.grade-edit-input--cat { width:110px; flex-shrink:0; text-align:left; cursor:pointer; }
.grade-add-hint { font-size:.72rem; color:#9ca3af; margin:.4rem 0 0; }
.tab-panel--documents { width:100%; max-width:100%; }
.doc-grid { display:grid; grid-template-columns:1fr; gap:1rem; margin-bottom:1.25rem; }
@media (min-width:640px) { .doc-grid { grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); } }
.doc-card { background:#fff; border-radius:14px; border:1px solid #e5e7eb; padding:1rem; display:flex; flex-direction:column; gap:.6rem; box-shadow:0 1px 3px rgba(0,0,0,.04); overflow:hidden; min-width:0; }
.doc-card-top { display:flex; align-items:flex-start; gap:.75rem; min-width:0; }
.doc-icon { width:38px; height:38px; border-radius:10px; background:var(--brand-pale); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.doc-icon svg { width:18px; height:18px; fill:var(--brand); }
.doc-meta { flex:1; min-width:0; }
.doc-type { font-size:.8rem; font-weight:700; color:#111827; margin:0 0 2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
.doc-filename { font-size:.72rem; color:#6b7280; margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
.doc-remark { display:flex; gap:.4rem; font-size:.74rem; color:#b91c1c; background:#fff5f5; border:1px solid #fecaca; border-radius:8px; padding:.45rem .65rem; word-break:break-word; overflow-wrap:break-word; min-width:0; }
.doc-remark span { min-width:0; word-break:break-word; }
.doc-remark svg { width:14px; height:14px; fill:#dc2626; flex-shrink:0; margin-top:1px; }
.doc-date { font-size:.72rem; color:#9ca3af; margin:auto 0 0; }
.summary-card { max-width:100%; }
.summary-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:.75rem; padding:.75rem 1.25rem 1.25rem; }
@media (min-width:640px) { .summary-grid { grid-template-columns:repeat(4,1fr); } }
.summary-stat { border-radius:12px; padding:.9rem .75rem; display:flex; flex-direction:column; align-items:center; gap:.2rem; }
.summary-stat--neutral { background:#f3f4f6; }
.summary-stat--green { background:#dcfce7; }
.summary-stat--yellow { background:#fef9c3; }
.summary-stat--red { background:#fee2e2; }
.summary-num { font-size:1.6rem; font-weight:800; line-height:1; color:#111827; }
.summary-stat--green .summary-num { color:#15803d; }
.summary-stat--yellow .summary-num { color:#92400e; }
.summary-stat--red .summary-num { color:#b91c1c; }
.summary-label { font-size:.72rem; color:#6b7280; font-weight:500; }
.readonly-banner { display:flex; align-items:flex-start; gap:.75rem; padding:.85rem 1rem; margin-bottom:1.25rem; background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; }
.readonly-banner svg { width:18px; height:18px; fill:#3b82f6; flex-shrink:0; margin-top:2px; }
.readonly-banner-title { font-size:.82rem; font-weight:700; color:#1d4ed8; margin-bottom:1px; }
.readonly-banner-text { font-size:.75rem; color:#3b82f6; margin:0; }
.dark .readonly-banner { background:rgba(59,130,246,.1); border-color:rgba(59,130,246,.2); }
.dark .readonly-banner svg { fill:#60a5fa; }
.dark .readonly-banner-title { color:#60a5fa; }
.dark .readonly-banner-text { color:#93bbfd; }
.field-input--readonly { background:#f3f4f6 !important; color:#4b5563; cursor:not-allowed; border-color:#e5e7eb; }
.dark .field-input--readonly { background:#1e2130 !important; color:#94a3b8; border-color:#2a2d3a; }
.empty-state { text-align:center; padding:3rem 1rem; }
.empty-state svg { width:48px; height:48px; fill:#e5e7eb; margin:0 auto .75rem; display:block; }
.empty-state p { font-size:.87rem; color:#9ca3af; }
.empty-card { background:#fff; border-radius:16px; border:1px solid #e5e7eb; text-align:center; padding:4rem 1rem; }
.empty-card svg { width:56px; height:56px; fill:#e5e7eb; margin:0 auto 1rem; display:block; }
.empty-card-title { font-size:.95rem; font-weight:600; color:#6b7280; margin-bottom:.35rem; }
.empty-card-sub { font-size:.82rem; color:#9ca3af; }
.dark .profile-root { background:#0f1117; }
.dark .hero-card,.dark .card,.dark .tab-bar,.dark .doc-card { background:#1a1d27; border-color:#2a2d3a; }
.dark .hero-card { background:linear-gradient(135deg,#7a0e22 0%,#9E122C 100%); border-color:transparent; }
.dark .hero-name { color:#fff; }
.dark .hero-contact,.dark .contact-item { border-color:rgba(255,255,255,.2); color:rgba(255,255,255,.8); }
.dark .card-title,.dark .timeline-name,.dark .program-name,.dark .doc-type,.dark .grade-label,.dark .field-label,.dark .empty-card-title { color:#f1f5f9; }
.dark .card-title--light { color:#fff; }
.dark .card-header,.dark .info-row { border-color:#2a2d3a; }
.dark .card-icon { background:rgba(158,18,44,.2); }
.dark .card-subtitle,.dark .meta-chip,.dark .info-row dt,.dark .timeline-date,.dark .doc-filename,.dark .doc-date,.dark .summary-label,.dark .empty-card-sub,.dark .empty-state p,.dark .breadcrumb-link,.dark .program-code { color:#64748b; }
.dark .meta-chip { background:#2a2d3a; border-color:#3a3d4a; }
.dark .info-row dd,.dark .timeline-name,.dark .program-name,.dark .doc-type { color:#f1f5f9; }
.dark .breadcrumb-current { color:#f1f5f9; }
.dark .tab-btn { color:#64748b; }
.dark .tab-btn:hover { color:#94a3b8; background:#1e2130; }
.dark .tab-btn--active { color:#f87171; border-color:#9E122C; background:rgba(158,18,44,.12); }
.dark .field-input { background:#0f1117; border-color:#2a2d3a; color:#f1f5f9; }
.dark .grade-row:hover { background:#1e2130; }
.dark .grade-bar-track { background:#2a2d3a; }
.dark .program-item { background:#1e2130; border-color:#2a2d3a; }
.dark .program-item--first { background:rgba(158,18,44,.15); border-color:rgba(158,18,44,.25); }
.dark .timeline-content { background:#1e2130; border-color:#2a2d3a; }
.dark .timeline::before { background:#2a2d3a; }
.dark .doc-icon { background:rgba(158,18,44,.2); }
.dark .chip { background:#2a2d3a; color:#94a3b8; }
.dark .summary-stat--neutral { background:#2a2d3a; }
.dark .summary-stat--green { background:rgba(34,197,94,.1); }
.dark .summary-stat--yellow { background:rgba(234,179,8,.1); }
.dark .summary-stat--red { background:rgba(239,68,68,.1); }
.dark .summary-num { color:#f1f5f9; }
.dark .empty-card { background:#1a1d27; border-color:#2a2d3a; }
.dark .error-banner { background:rgba(239,68,68,.1); border-color:rgba(239,68,68,.2); }
.dark .form-actions { border-color:#2a2d3a; }
.dark .btn--ghost { border-color:#2a2d3a; color:#94a3b8; }
.dark .btn--ghost:hover { background:#1e2130; }
.dark .fieldset-legend { color:#64748b; }
.dark .field-hint { color:#475569; }
.dark .field-error { color:#f87171; }
.dark .grade-row { border-color:#1e2130; }
.dark .empty-state svg,.dark .empty-card svg { fill:#2a2d3a; }
.dark .doc-remark { background:rgba(239,68,68,.1); border-color:rgba(239,68,68,.2); color:#f87171; }
.dark .doc-remark svg { fill:#f87171; }
.dark .timeline-remark { color:#94a3b8; }
.dark .breadcrumb { color:#64748b; }
.dark .breadcrumb-sep { color:#374151; }

/* ── Pull-Out Feature Styles ──────────────────────────────────── */
.pullout-card { border:1.5px solid #fee2e2; margin-top:1.25rem; }
.pullout-card-header { background:linear-gradient(135deg, #fff5f5 0%, #fff 100%); }
.pullout-icon { background:rgba(239,68,68,.12) !important; color:#dc2626 !important; }
.pullout-title { color:#dc2626 !important; }
.pullout-body { padding:1rem 1.5rem 1.5rem; display:flex; flex-direction:column; gap:1rem; }
.pullout-warning { display:flex; gap:.75rem; align-items:flex-start; background:#fef2f2; border:1px solid #fecaca; border-radius:.625rem; padding:.875rem 1rem; }
.pullout-warning svg { width:20px; height:20px; fill:#dc2626; flex-shrink:0; margin-top:2px; }
.pullout-warning-title { font-size:.825rem; font-weight:700; color:#dc2626; margin-bottom:.25rem; }
.pullout-warning-text { font-size:.825rem; color:#7f1d1d; line-height:1.5; }
.pullout-label { color:#dc2626 !important; }
.pullout-textarea { min-height:80px; resize:vertical; }
.pullout-actions { display:flex; justify-content:flex-end; }
.pullout-not-eligible { display:flex; align-items:flex-start; gap:.75rem; padding:1rem 1.5rem 1.5rem; color:#6b7280; }
.pullout-not-eligible svg { width:20px; height:20px; fill:#d1d5db; flex-shrink:0; margin-top:2px; }
.pullout-not-eligible p { font-size:.875rem; line-height:1.5; }
.pullout-success-banner { display:flex; align-items:center; gap:.625rem; background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; border-radius:.5rem; padding:.75rem 1.25rem; margin:.75rem 1.5rem 0; font-size:.875rem; }
.pullout-success-banner svg { width:18px; height:18px; fill:#16a34a; flex-shrink:0; }

/* Danger button */
.btn--danger { display:inline-flex; align-items:center; gap:.5rem; padding:.5rem 1.25rem; border-radius:.5rem; font-size:.875rem; font-weight:600; background:#dc2626; color:#fff; border:none; cursor:pointer; transition:background .15s, transform .1s; }
.btn--danger:hover:not(:disabled) { background:#b91c1c; transform:translateY(-1px); }
.btn--danger:disabled { opacity:.6; cursor:not-allowed; }
.btn--danger svg { width:16px; height:16px; fill:currentColor; }

/* Pull-Out Confirmation Modal */
.pullout-overlay { position:fixed; inset:0; background:rgba(0,0,0,.55); backdrop-filter:blur(3px); z-index:9999; display:flex; align-items:center; justify-content:center; padding:1rem; }
.pullout-modal { background:#fff; border-radius:1rem; box-shadow:0 20px 60px rgba(0,0,0,.2); width:100%; max-width:520px; overflow:hidden; animation:pullout-slide-in .2s ease; }
@keyframes pullout-slide-in { from { opacity:0; transform:scale(.95) translateY(-8px); } to { opacity:1; transform:scale(1) translateY(0); } }
.pullout-modal-header { display:flex; align-items:flex-start; gap:1rem; padding:1.25rem 1.5rem; background:linear-gradient(135deg,#fff5f5 0%,#fff 100%); border-bottom:1px solid #fee2e2; }
.pullout-modal-icon { width:40px; height:40px; border-radius:.625rem; background:rgba(239,68,68,.12); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.pullout-modal-icon svg { width:22px; height:22px; fill:#dc2626; }
.pullout-modal-title { font-size:1.05rem; font-weight:700; color:#dc2626; margin:0 0 .2rem; }
.pullout-modal-subtitle { font-size:.8rem; color:#6b7280; margin:0; }
.pullout-modal-body { padding:1.25rem 1.5rem; display:flex; flex-direction:column; gap:1rem; }
.pullout-modal-applicant { font-size:.9rem; color:#374151; background:#f9fafb; border:1px solid #e5e7eb; border-radius:.5rem; padding:.625rem .875rem; margin:0; }
.pullout-modal-list { margin:0; padding:0 0 0 1.25rem; display:flex; flex-direction:column; gap:.4rem; }
.pullout-modal-list li { font-size:.875rem; color:#374151; }
.pullout-modal-notes { background:#fef2f2; border:1px solid #fecaca; border-radius:.5rem; padding:.75rem 1rem; }
.pullout-modal-notes-label { font-size:.75rem; font-weight:600; color:#dc2626; margin:0 0 .3rem; text-transform:uppercase; letter-spacing:.04em; }
.pullout-modal-notes-text { font-size:.875rem; color:#7f1d1d; margin:0; white-space:pre-wrap; line-height:1.5; }
.pullout-modal-footer { display:flex; align-items:center; justify-content:flex-end; gap:.75rem; padding:1rem 1.5rem; border-top:1px solid #f3f4f6; background:#fafafa; }

/* Dark mode overrides */
.dark .pullout-card { border-color:rgba(239,68,68,.25); }
.dark .pullout-card-header { background:linear-gradient(135deg,rgba(239,68,68,.08) 0%,#0f1117 100%); }
.dark .pullout-warning { background:rgba(239,68,68,.08); border-color:rgba(239,68,68,.2); }
.dark .pullout-warning-text { color:#fca5a5; }
.dark .pullout-success-banner { background:rgba(34,197,94,.08); border-color:rgba(34,197,94,.2); color:#86efac; }
.dark .pullout-not-eligible { color:#64748b; }
.dark .pullout-modal { background:#0f1117; }
.dark .pullout-modal-header { background:linear-gradient(135deg,rgba(239,68,68,.1) 0%,#0f1117 100%); border-color:rgba(239,68,68,.2); }
.dark .pullout-modal-applicant { background:#1a1d27; border-color:#2a2d3a; color:#94a3b8; }
.dark .pullout-modal-list li { color:#94a3b8; }
.dark .pullout-modal-notes { background:rgba(239,68,68,.08); border-color:rgba(239,68,68,.2); }
.dark .pullout-modal-notes-text { color:#fca5a5; }
.dark .pullout-modal-footer { background:#1a1d27; border-color:#2a2d3a; }
</style>