<template>
  <ApplicantLayout>
    <div class="max-w-4xl mx-auto p-6">
      <h1 class="text-xl font-bold mb-4">HUMSS Strand Grade Input</h1>
      <p class="mb-6">Enter your academic grades for Grade 11 and Grade 12</p>

      <form @submit.prevent="submitForm">
        <!-- Grade 11 Section -->
        <div class="mb-8">
          <h2 class="text-lg font-bold mb-4">Grade 11 Subjects</h2>

          <!-- Math Subjects -->
          <div class="mb-6">
            <h3 class="font-semibold mb-2">Math-Related Subjects</h3>
            <div class="mb-2">
              <label class="block text-sm">General Mathematics</label>
              <input
                v-model.number="form.g11_general_mathematics"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
            <div class="mb-2">
              <label class="block text-sm">Statistics and Probability</label>
              <input
                v-model.number="form.g11_statistics_probability"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
            <p class="text-sm font-semibold mb-4">Math Average: {{ mathAverage || '—' }}</p>
          </div>

          <!-- English Subjects -->
          <div class="mb-6">
            <h3 class="font-semibold mb-2">English-Related Subjects</h3>
            <div class="mb-2">
              <label class="block text-sm">Oral Communication in Context</label>
              <input
                v-model.number="form.g11_oral_communication"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
            <div class="mb-2">
              <label class="block text-sm">21st Century Literature from the Philippines and the World</label>
              <input
                v-model.number="form.g11_21st_century_lit"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
            <div class="mb-2">
              <label class="block text-sm">English for Academic and Professional Purposes</label>
              <input
                v-model.number="form.g11_academic_professional"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
            <div class="mb-2">
              <label class="block text-sm">Reading and Writing Skills</label>
              <input
                v-model.number="form.g11_reading_writing"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
            <p class="text-sm font-semibold mb-4">English Average: {{ englishAverage || '—' }}</p>
          </div>

          <!-- Science Subjects -->
          <div class="mb-6">
            <h3 class="font-semibold mb-2">Science-Related Subjects</h3>
            <div class="mb-2">
              <label class="block text-sm">Earth and Life Science</label>
              <input
                v-model.number="form.g11_earth_life_science"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
            <p class="text-sm font-semibold mb-4">Science Average: {{ scienceAverage || '—' }}</p>
          </div>
        </div>

        <!-- Grade 12 Section -->
        <div class="mb-8">
          <h2 class="text-lg font-bold mb-4">Grade 12 Subjects</h2>

          <!-- Math Subjects (2) -->
          <div class="mb-6">
            <h3 class="font-semibold mb-2">Math Subjects (2 subjects)</h3>
            <div class="mb-3">
              <label class="block text-sm">Math Subject 1 Name</label>
              <input
                v-model="form.g12_math_subject_1"
                type="text"
                class="w-full border px-2 py-1 mb-1"
              />
              <label class="block text-sm">Grade</label>
              <input
                v-model.number="form.g12_math_grade_1"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
            <div class="mb-3">
              <label class="block text-sm">Math Subject 2 Name</label>
              <input
                v-model="form.g12_math_subject_2"
                type="text"
                class="w-full border px-2 py-1 mb-1"
              />
              <label class="block text-sm">Grade</label>
              <input
                v-model.number="form.g12_math_grade_2"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
          </div>

          <!-- Science Subjects (2) -->
          <div class="mb-6">
            <h3 class="font-semibold mb-2">Science Subjects (2 subjects)</h3>
            <div class="mb-3">
              <label class="block text-sm">Science Subject 1 Name</label>
              <input
                v-model="form.g12_science_subject_1"
                type="text"
                class="w-full border px-2 py-1 mb-1"
              />
              <label class="block text-sm">Grade</label>
              <input
                v-model.number="form.g12_science_grade_1"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
            <div class="mb-3">
              <label class="block text-sm">Science Subject 2 Name</label>
              <input
                v-model="form.g12_science_subject_2"
                type="text"
                class="w-full border px-2 py-1 mb-1"
              />
              <label class="block text-sm">Grade</label>
              <input
                v-model.number="form.g12_science_grade_2"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
          </div>

          <!-- English Subjects (4) -->
          <div class="mb-6">
            <h3 class="font-semibold mb-2">English Subjects (4 subjects)</h3>
            <div class="mb-3">
              <label class="block text-sm">English Subject 1 Name</label>
              <input
                v-model="form.g12_english_subject_1"
                type="text"
                class="w-full border px-2 py-1 mb-1"
              />
              <label class="block text-sm">Grade</label>
              <input
                v-model.number="form.g12_english_grade_1"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
            <div class="mb-3">
              <label class="block text-sm">English Subject 2 Name</label>
              <input
                v-model="form.g12_english_subject_2"
                type="text"
                class="w-full border px-2 py-1 mb-1"
              />
              <label class="block text-sm">Grade</label>
              <input
                v-model.number="form.g12_english_grade_2"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
            <div class="mb-3">
              <label class="block text-sm">English Subject 3 Name</label>
              <input
                v-model="form.g12_english_subject_3"
                type="text"
                class="w-full border px-2 py-1 mb-1"
              />
              <label class="block text-sm">Grade</label>
              <input
                v-model.number="form.g12_english_grade_3"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
            <div class="mb-3">
              <label class="block text-sm">English Subject 4 Name</label>
              <input
                v-model="form.g12_english_subject_4"
                type="text"
                class="w-full border px-2 py-1 mb-1"
              />
              <label class="block text-sm">Grade</label>
              <input
                v-model.number="form.g12_english_grade_4"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
          </div>

          <!-- Semester GWA Input -->
          <div class="mb-6">
            <h3 class="font-semibold mb-2">Semester GWA</h3>
            <div class="mb-2">
              <label class="block text-sm">1st Semester</label>
              <input
                v-model.number="form.g12_first_sem_gwa"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
            <div class="mb-2">
              <label class="block text-sm">2nd Semester</label>
              <input
                v-model.number="form.g12_second_sem_gwa"
                type="number"
                min="0"
                max="100"
                step="0.01"
                class="w-full border px-2 py-1"
              />
            </div>
          </div>

          <!-- Grade 12 GWA -->
          <div class="mb-6">
            <h3 class="font-semibold">Grade 12 GWA</h3>
            <p class="text-xl font-bold">{{ g12GWA || '—' }}</p>
          </div>
        </div>

        <!-- Summary Section -->
        <div class="border p-3 mb-6">
          <h3 class="font-semibold mb-2">Grade Summary</h3>
          <p class="text-sm">Math Average: <strong>{{ mathAverage || '—' }}</strong></p>
          <p class="text-sm">English Average: <strong>{{ englishAverage || '—' }}</strong></p>
          <p class="text-sm">Science Average: <strong>{{ scienceAverage || '—' }}</strong></p>
          <p class="text-sm">Grade 12 GWA: <strong>{{ g12GWA || '—' }}</strong></p>
        </div>

        <!-- Program Qualification Section -->
        <div class="border p-3 mb-6">
          <h3 class="font-semibold mb-3">Program Qualification</h3>
          
          <!-- Qualified Programs -->
          <div class="mb-4">
            <h4 class="font-semibold text-green-600 mb-2">Qualified Programs</h4>
            <div v-if="qualifiedPrograms.length > 0">
              <div v-for="program in qualifiedPrograms" :key="program.id" class="border p-2 mb-2 bg-green-50">
                <p class="font-semibold text-sm">{{ program.code }} - {{ program.name }}</p>
                <p class="text-xs">Requirements: Math {{ program.math }}, English {{ program.english }}, Science {{ program.science }}, GWA {{ program.gwa }}</p>
              </div>
            </div>
            <p v-else class="text-sm">No qualified programs based on current grades</p>
          </div>

          <!-- Not Qualified Programs -->
          <div>
            <h4 class="font-semibold text-red-600 mb-2">Not Qualified Programs</h4>
            <div v-if="notQualifiedPrograms.length > 0">
              <div v-for="program in notQualifiedPrograms" :key="program.id" class="border p-2 mb-2 bg-red-50">
                <p class="font-semibold text-sm">{{ program.code }} - {{ program.name }}</p>
                <p class="text-xs">Requirements: Math {{ program.math }}, English {{ program.english }}, Science {{ program.science }}, GWA {{ program.gwa }}</p>
              </div>
            </div>
            <p v-else class="text-sm">All programs are qualified</p>
          </div>
        </div>

        <!-- Program Choices -->
        <div class="border p-3 mb-6">
          <h3 class="font-semibold mb-3 text-red-600">* Choose Your Programs (Required)</h3>
          
          <div v-if="qualifiedPrograms.length > 0">
            <div class="mb-3">
              <label class="block text-sm font-semibold mb-1">First Choice Program *</label>
              <select 
                v-model="form.first_choice_program" 
                class="w-full border px-2 py-1"
                required
              >
                <option value="">-- Select First Choice --</option>
                <option 
                  v-for="program in qualifiedPrograms" 
                  :key="program.id" 
                  :value="program.id"
                  :disabled="program.id === form.second_choice_program"
                >
                  {{ program.code }} - {{ program.name }}
                </option>
              </select>
              <p v-if="errors.first_choice_program" class="text-red-500 text-xs mt-1">
                {{ errors.first_choice_program }}
              </p>
            </div>

            <div class="mb-3">
              <label class="block text-sm font-semibold mb-1">Second Choice Program *</label>
              <select 
                v-model="form.second_choice_program" 
                class="w-full border px-2 py-1"
                required
              >
                <option value="">-- Select Second Choice --</option>
                <option 
                  v-for="program in qualifiedPrograms" 
                  :key="program.id" 
                  :value="program.id"
                  :disabled="program.id === form.first_choice_program"
                >
                  {{ program.code }} - {{ program.name }}
                </option>
              </select>
              <p v-if="errors.second_choice_program" class="text-red-500 text-xs mt-1">
                {{ errors.second_choice_program }}
              </p>
            </div>

            <!-- Selected Programs Display -->
            <div v-if="form.first_choice_program || form.second_choice_program" class="border p-2 bg-blue-50 mt-2">
              <p class="font-semibold text-sm mb-1">Your Selected Programs:</p>
              <p v-if="form.first_choice_program" class="text-sm">1st Choice: <strong>{{ getSelectedProgramName(form.first_choice_program) }}</strong></p>
              <p v-if="form.second_choice_program" class="text-sm">2nd Choice: <strong>{{ getSelectedProgramName(form.second_choice_program) }}</strong></p>
            </div>
          </div>

          <div v-else class="text-red-600 p-2 bg-red-50">
            <p class="font-semibold text-sm">⚠️ No Qualified Programs</p>
            <p class="text-xs">You need to enter all your grades to see which programs you qualify for.</p>
          </div>

          <p v-if="errors.programs" class="text-red-500 text-xs mt-2">
            {{ errors.programs }}
          </p>
        </div>

        <!-- Buttons -->
        <button
          type="submit"
          :disabled="loading"
          class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-2 mb-2"
        >
          {{ loading ? 'Saving...' : 'Save Grades' }}
        </button>
        <button
          type="button"
          @click="$inertia.visit('/applicant-dashboard')"
          class="w-full bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2"
        >
          Cancel
        </button>

        <!-- Success Message -->
        <div v-if="successMessage" class="border border-green-500 bg-green-100 text-green-700 px-3 py-2 mt-3">
          {{ successMessage }}
        </div>
      </form>
    </div>
  </ApplicantLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import ApplicantLayout from '@/Layouts/ApplicantLayout.vue'

const page = usePage()
const props = defineProps({
  grade: Object,
  user: Object,
  programs: Array,
  strand: String,
})

const loading = ref(false)
const successMessage = ref('')
const errors = ref({})

const form = reactive({
  g11_general_mathematics: null,
  g11_statistics_probability: null,
  g11_oral_communication: null,
  g11_21st_century_lit: null,
  g11_academic_professional: null,
  g11_reading_writing: null,
  g11_earth_life_science: null,
  // Grade 12 Math subjects
  g12_math_subject_1: '',
  g12_math_grade_1: null,
  g12_math_subject_2: '',
  g12_math_grade_2: null,
  // Grade 12 Science subjects
  g12_science_subject_1: '',
  g12_science_grade_1: null,
  g12_science_subject_2: '',
  g12_science_grade_2: null,
  // Grade 12 English subjects
  g12_english_subject_1: '',
  g12_english_grade_1: null,
  g12_english_subject_2: '',
  g12_english_grade_2: null,
  g12_english_subject_3: '',
  g12_english_grade_3: null,
  g12_english_subject_4: '',
  g12_english_grade_4: null,
  // Grade 12 Semester GWA
  g12_first_sem_gwa: null,
  g12_second_sem_gwa: null,
  // Program choices
  first_choice_program: '',
  second_choice_program: '',
})

// Computed properties for averages
const mathAverage = computed(() => {
  const grades = [
    form.g11_general_mathematics, 
    form.g11_statistics_probability,
    form.g12_math_grade_1,
    form.g12_math_grade_2
  ].filter(g => g !== null && g !== '')
  return grades.length > 0 ? (grades.reduce((a, b) => a + b, 0) / grades.length).toFixed(2) : null
})

const englishAverage = computed(() => {
  const grades = [
    form.g11_oral_communication, 
    form.g11_21st_century_lit,
    form.g11_academic_professional,
    form.g11_reading_writing, 
    form.g12_english_grade_1,
    form.g12_english_grade_2,
    form.g12_english_grade_3,
    form.g12_english_grade_4
  ].filter(g => g !== null && g !== '')
  return grades.length > 0 ? (grades.reduce((a, b) => a + b, 0) / grades.length).toFixed(2) : null
})

const scienceAverage = computed(() => {
  const grades = [
    form.g11_earth_life_science,
    form.g12_science_grade_1,
    form.g12_science_grade_2
  ].filter(g => g !== null && g !== '')
  return grades.length > 0 ? (grades.reduce((a, b) => a + b, 0) / grades.length).toFixed(2) : null
})

const g12GWA = computed(() => {
  const semGrades = [
    form.g12_first_sem_gwa,
    form.g12_second_sem_gwa
  ].filter(g => g !== null && g !== '')

  return semGrades.length > 0 ? (semGrades.reduce((a, b) => a + b, 0) / semGrades.length).toFixed(2) : null
})

// Program qualification logic
const qualifiedPrograms = computed(() => {
  if (!props.programs || !mathAverage.value || !englishAverage.value || !scienceAverage.value || !g12GWA.value) {
    return []
  }

  return props.programs.filter(program => {
    if (!isStrandAllowed(program)) {
      return false
    }
    const meetsMath = parseFloat(mathAverage.value) >= parseFloat(program.math)
    const meetsEnglish = parseFloat(englishAverage.value) >= parseFloat(program.english)
    const meetsScience = parseFloat(scienceAverage.value) >= parseFloat(program.science)
    const meetsGWA = parseFloat(g12GWA.value) >= parseFloat(program.gwa)

    return meetsMath && meetsEnglish && meetsScience && meetsGWA
  })
})

const notQualifiedPrograms = computed(() => {
  if (!props.programs || !mathAverage.value || !englishAverage.value || !scienceAverage.value || !g12GWA.value) {
    return props.programs || []
  }

  return props.programs.filter(program => {
    if (!isStrandAllowed(program)) {
      return true
    }
    const meetsMath = parseFloat(mathAverage.value) >= parseFloat(program.math)
    const meetsEnglish = parseFloat(englishAverage.value) >= parseFloat(program.english)
    const meetsScience = parseFloat(scienceAverage.value) >= parseFloat(program.science)
    const meetsGWA = parseFloat(g12GWA.value) >= parseFloat(program.gwa)

    return !(meetsMath && meetsEnglish && meetsScience && meetsGWA)
  })
})

const currentStrand = computed(() => (props.strand || 'HUMSS').toUpperCase())

const isStrandAllowed = (program) => {
  const strandValue = (program.strand || '').toString().toUpperCase()
  if (!strandValue || strandValue.includes('OPEN TO ALL')) {
    return true
  }

  if (strandValue.includes('OTHER WITH BRIDGING')) {
    return true
  }

  const allowed = strandValue.split(',').map(s => s.trim()).filter(Boolean)
  return allowed.includes(currentStrand.value)
}

// Helper function to get selected program name
const getSelectedProgramName = (programId) => {
  const program = props.programs?.find(p => p.id === programId)
  return program ? `${program.code} - ${program.name}` : ''
}

const submitForm = async () => {
  loading.value = true
  errors.value = {}

  // Validate required grades
  if (!mathAverage.value || !englishAverage.value || !scienceAverage.value || !g12GWA.value) {
    errors.value = { programs: 'Please complete all subject grades and semester GWAs before submitting' }
    loading.value = false
    return
  }

  // Validate that program choices are selected
  if (!form.first_choice_program || !form.second_choice_program) {
    errors.value = { programs: 'Please select both first and second choice programs' }
    loading.value = false
    return
  }

  // Validate that choices are different
  if (form.first_choice_program === form.second_choice_program) {
    errors.value = { programs: 'First and second choice programs must be different' }
    loading.value = false
    return
  }

  // Prepare data with only computed averages
  const payload = {
    mathematics: parseFloat(mathAverage.value),
    english: parseFloat(englishAverage.value),
    science: parseFloat(scienceAverage.value),
    g12_first_sem: parseFloat(form.g12_first_sem_gwa),
    g12_second_sem: parseFloat(form.g12_second_sem_gwa),
    first_choice_program: form.first_choice_program,
    second_choice_program: form.second_choice_program,
  }

  console.log('Saving grades with payload:', payload)
  
  router.post('/grades/humss', payload, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: (response) => {
      console.log('Success response:', response)
      successMessage.value = 'Grades and program choices saved successfully!'
      alert('✅ Grades saved successfully! Redirecting to dashboard...')
      setTimeout(() => {
        router.visit('/applicant-dashboard')
      }, 1000)
      loading.value = false
    },
    onError: (errorResponse) => {
      console.error('Error response:', errorResponse)
      errors.value = errorResponse
      const firstError = Object.values(errorResponse)[0]
      alert('❌ ' + (firstError || 'Failed to save grades. Please check the form.'))
      loading.value = false
    },
    onFinish: () => {
      loading.value = false
    },
  })
}
</script>
