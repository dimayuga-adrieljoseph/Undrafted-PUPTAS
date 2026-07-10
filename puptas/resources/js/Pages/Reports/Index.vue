<script setup>
import { ref, computed, onMounted, watch } from "vue"
import { Head } from "@inertiajs/vue3"
import AppLayout from "@/Layouts/AppLayout.vue"
import BlurText from "@/Components/BlurText.vue"
import axios from "axios"

const VALID_TABS = ['applicant', 'sis', 'logbook', 'control-list']

const props = defineProps({
	programs: Array,
	logbookEntries: Object,
	logbookCurrentStep: Number,
	logbookCurrentDate: String,
	controlListApplicants: Object,
	controlListSelectedProgramId: [String, Number],
	initialTab: String,
})

const applicants = ref([])
const loading = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = ref(15)

const filterType = ref("overall")
const filterDate = ref("")
const filterMonth = ref("")
const filterProgram = ref("")

// SIS Upload state
const sisSchoolYear = ref("")
const sisSchoolYears = ref([])
const sisLoading = ref(false)

// Logbook tab state
const logbookStep = ref(props.logbookCurrentStep ?? 1)
const logbookDate = ref(props.logbookCurrentDate ?? new Date().toISOString().slice(0, 10))
const logbookEntries = ref(props.logbookEntries)
const logbookLoading = ref(false)
const logbookError = ref(null)

const fetchLogbook = async (page = 1) => {
	logbookLoading.value = true
	logbookError.value = null
	try {
		const res = await axios.get(route('reports.logbook.index'), {
			params: { step: logbookStep.value, date: logbookDate.value, page }
		})
		logbookEntries.value = res.data
	} catch {
		logbookError.value = 'Could not load logbook entries. Please try again.'
	} finally {
		logbookLoading.value = false
	}
}

watch([logbookStep, logbookDate], () => fetchLogbook(1))

const getCurrentAY = () => {
	const now = new Date()
	const yr = now.getFullYear()
	return `${yr}-${yr + 1}`
}

// Control List tab state
const controlListProgram = ref(props.controlListSelectedProgramId ? Number(props.controlListSelectedProgramId) : '')
const controlListYear = ref(getCurrentAY())
const controlListData = ref(props.controlListApplicants)
const controlListLoading = ref(false)
const controlListError = ref(null)

const fetchControlList = async (page = 1) => {
	if (!controlListProgram.value) return
	controlListLoading.value = true
	controlListError.value = null
	try {
		const res = await axios.get(route('reports.control-list.index'), {
			params: { program_id: controlListProgram.value, page }
		})
		controlListData.value = res.data.applicants
	} catch {
		controlListError.value = 'Could not load control list. Please try again.'
	} finally {
		controlListLoading.value = false
	}
}

watch(controlListProgram, (val) => { if (val) fetchControlList(1) })

// ---- Tabs ----
const activeTab = ref(VALID_TABS.includes(props.initialTab) ? props.initialTab : 'applicant')

const setTab = (tab) => {
	activeTab.value = tab
	history.replaceState(null, '', '?tab=' + tab)
}

const fetchSisSchoolYears = async () => {
	try {
		const res = await axios.get(route("sis-upload.school-years"))
		sisSchoolYears.value = res.data.school_years || []
		if (sisSchoolYears.value.length > 0) {
			sisSchoolYear.value = sisSchoolYears.value[0]
		}
	} catch (e) {
		console.error("Failed to load school years:", e)
	}
}

const downloadSisPassers = () => {
	sisLoading.value = true
	let url = route("sis-upload.passers")
	if (sisSchoolYear.value) url += `?school_year=${encodeURIComponent(sisSchoolYear.value)}`
	window.open(url, "_blank")
	setTimeout(() => {
		sisLoading.value = false
	}, 2000)
}

const downloadSisRecon = () => {
	sisLoading.value = true
	let url = route("sis-upload.recon")
	if (sisSchoolYear.value) url += `?school_year=${encodeURIComponent(sisSchoolYear.value)}`
	window.open(url, "_blank")
	setTimeout(() => {
		sisLoading.value = false
	}, 2000)
}

let abortController = null

const fetchReportData = async (page = 1) => {
	if (abortController) abortController.abort()
	abortController = new AbortController()

	loading.value = true
	try {
		const params = { type: filterType.value, page }
		if (filterDate.value) params.date_filter = filterDate.value
		if (filterMonth.value) params.month_filter = filterMonth.value
		if (filterProgram.value) params.program_id = filterProgram.value

		const response = await axios.get(route("reports.data"), {
			params,
			signal: abortController.signal,
		})

		applicants.value = response.data.data
		currentPage.value = response.data.current_page
		lastPage.value = response.data.last_page
		total.value = response.data.total
		perPage.value = response.data.per_page || 15
	} catch (err) {
		if (!axios.isCancel(err)) console.error("Failed to fetch report data:", err)
	} finally {
		if (!abortController.signal.aborted) loading.value = false
	}
}

onMounted(() => {
	fetchReportData(1)
	fetchSisSchoolYears()
})

const downloadPdf = () => {
	let url = route("reports.export.pdf") + `?type=${filterType.value}`
	if (filterDate.value) url += `&date_filter=${filterDate.value}`
	if (filterMonth.value) url += `&month_filter=${filterMonth.value}`
	if (filterProgram.value) url += `&program_id=${filterProgram.value}`
	window.open(url, "_blank")
}

const downloadExcel = () => {
	let url = route("reports.export.excel") + `?type=${filterType.value}`
	if (filterDate.value) url += `&date_filter=${filterDate.value}`
	if (filterMonth.value) url += `&month_filter=${filterMonth.value}`
	if (filterProgram.value) url += `&program_id=${filterProgram.value}`
	window.open(url, "_blank")
}

const downloadLogbookPdf = () => {
	const url = route('reports.logbook.export.pdf') + `?step=${logbookStep.value}&date=${logbookDate.value}`
	window.open(url, '_blank')
}

const downloadControlListPdf = () => {
	if (!controlListProgram.value || !controlListYear.value) return
	const url = route('reports.control-list.export') + `?program_id=${controlListProgram.value}&academic_year=${encodeURIComponent(controlListYear.value)}`
	window.open(url, '_blank')
}

const clearFilters = () => {
	filterType.value = "overall"
	filterDate.value = ""
	filterMonth.value = ""
	filterProgram.value = ""
	fetchReportData(1)
}

const reportTypeLabel = computed(() => {
	const map = {
		overall: "Overall Report",
		interview: "Finished Interview",
		medical: "Finished Medical Clearance",
		enrollment: "Finished Enrollment Process",
		pulled_out: "Pulled Out",
	}
	return map[filterType.value] || "Report"
})

const getStatusBadge = (status) => {
	const s = (status || "").toLowerCase()
	if (s.includes("enrolled"))
		return "bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-400/10 dark:text-emerald-300 dark:border-emerald-400/20"
	if (s.includes("medical"))
		return "bg-sky-50 text-sky-700 border border-sky-200 dark:bg-sky-400/10 dark:text-sky-300 dark:border-sky-400/20"
	if (s.includes("interview"))
		return "bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-400/10 dark:text-amber-300 dark:border-amber-400/20"
	if (s.includes("return"))
		return "bg-orange-50 text-orange-700 border border-orange-200 dark:bg-orange-400/10 dark:text-orange-300 dark:border-orange-400/20"
	if (s.includes("reject"))
		return "bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-400/10 dark:text-rose-300 dark:border-rose-400/20"
	return "bg-gray-50 text-gray-600 border border-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"
}

const getStatusDot = (status) => {
	const s = (status || "").toLowerCase()
	if (s.includes("enrolled")) return "bg-emerald-500"
	if (s.includes("medical")) return "bg-sky-500"
	if (s.includes("interview")) return "bg-amber-500"
	if (s.includes("return")) return "bg-orange-500"
	if (s.includes("reject")) return "bg-rose-500"
	return "bg-gray-400"
}

const paginationStart = computed(() => (currentPage.value - 1) * perPage.value + 1)
const paginationEnd = computed(() => (currentPage.value - 1) * perPage.value + applicants.value.length)
</script>

<template>
	<Head title="Reports" />
	<AppLayout>
		<template #title>
			<h1 class="text-lg md:text-xl font-semibold text-gray-800 dark:text-gray-100">Reports</h1>
		</template>

		<!-- Page header -->
		<div class="mb-6">
			<BlurText
				text="Reports Dashboard"
				:delay="100"
				animate-by="words"
				direction="top"
				class-name="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white"
			/>
			<BlurText
				text="Filter, generate, and export applicant reports. Manage SIS upload files."
				:delay="60"
				animate-by="words"
				direction="top"
				:step-duration="0.3"
				class-name="text-gray-500 dark:text-gray-400 mt-1 text-sm"
			/>
		</div>

		<!-- ── Unified Tabbed Card (Applicant Report + SIS Upload) ────── -->
		<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 mb-6 overflow-hidden">
			<!-- Tabs navigation -->
			<div class="flex border-b border-gray-200 dark:border-gray-700">
				<button
					@click="setTab('applicant')"
					:class="[
						'flex-1 px-5 py-3.5 text-sm font-semibold transition-colors relative focus:outline-none',
						activeTab === 'applicant'
							? 'text-[#9E122C] dark:text-red-400'
							: 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
					]"
				>
					<span class="flex items-center justify-center gap-2">
						<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6M4 20h16"/>
						</svg>
						Applicant Report
					</span>
					<div v-if="activeTab === 'applicant'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-[#9E122C] dark:bg-red-400"></div>
				</button>
				<button
					@click="setTab('sis')"
					:class="[
						'flex-1 px-5 py-3.5 text-sm font-semibold transition-colors relative focus:outline-none',
						activeTab === 'sis'
							? 'text-[#9E122C] dark:text-red-400'
							: 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
					]"
				>
					<span class="flex items-center justify-center gap-2">
						<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
						</svg>
						SIS Upload
					</span>
					<div v-if="activeTab === 'sis'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-[#9E122C] dark:bg-red-400"></div>
				</button>
				<button
					@click="setTab('logbook')"
					:class="[
						'flex-1 px-5 py-3.5 text-sm font-semibold transition-colors relative focus:outline-none',
						activeTab === 'logbook'
							? 'text-[#9E122C] dark:text-red-400'
							: 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
					]"
				>
					<span class="flex items-center justify-center gap-2">
						<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
						</svg>
						Official Logbook
					</span>
					<div v-if="activeTab === 'logbook'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-[#9E122C] dark:bg-red-400"></div>
				</button>
				<button
					@click="setTab('control-list')"
					:class="[
						'flex-1 px-5 py-3.5 text-sm font-semibold transition-colors relative focus:outline-none',
						activeTab === 'control-list'
							? 'text-[#9E122C] dark:text-red-400'
							: 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
					]"
				>
					<span class="flex items-center justify-center gap-2">
						<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
						</svg>
						Control List
					</span>
					<div v-if="activeTab === 'control-list'" class="absolute bottom-0 left-0 right-0 h-0.5 bg-[#9E122C] dark:bg-red-400"></div>
				</button>
			</div>

			<!-- Tab content -->
			<div class="px-5 sm:px-6 py-5">
				<!-- Applicant Report Panel -->
				<div v-show="activeTab === 'applicant'">
					<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
						<div>
							<label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Report Type</label>
							<select v-model="filterType"
								class="w-full px-3.5 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/30 focus:border-[#9E122C] transition-all appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2212%22%20height%3D%2212%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%239ca3af%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22m6%209%206%206%206-6%22%2F%3E%3C%2Fsvg%3E')] bg-[length:12px] bg-[right_12px_center] bg-no-repeat pr-9">
								<option value="overall">Overall Report</option>
								<option value="interview">Finished Interview</option>
								<option value="medical">Finished Medical Clearance</option>
								<option value="enrollment">Finished Enrollment Process</option>
								<option value="pulled_out">Pulled Out</option>
							</select>
						</div>
						<div>
							<label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Date</label>
							<input type="date" v-model="filterDate" @change="filterMonth = ''"
								class="w-full px-3.5 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/30 focus:border-[#9E122C] transition-all" />
						</div>
						<div>
							<label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Month</label>
							<input type="month" v-model="filterMonth" @change="filterDate = ''"
								class="w-full px-3.5 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/30 focus:border-[#9E122C] transition-all" />
						</div>
						<div>
							<label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Course / Program</label>
							<select v-model="filterProgram"
								class="w-full px-3.5 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/30 focus:border-[#9E122C] transition-all appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2212%22%20height%3D%2212%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%239ca3af%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22m6%209%206%206%206-6%22%2F%3E%3C%2Fsvg%3E')] bg-[length:12px] bg-[right_12px_center] bg-no-repeat pr-9">
								<option value="">All Courses</option>
								<option v-for="program in programs" :key="program.id" :value="program.id">
									{{ program.code }} – {{ program.name }}
								</option>
							</select>
						</div>
						<div class="flex items-end gap-2">
							<button @click="fetchReportData(1)" :disabled="loading"
								class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-white rounded-xl shadow-sm transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex-1 justify-center min-h-[42px] hover:shadow-md active:scale-[0.98]"
								style="background-color:#9E122C"
								onmouseover="this.style.backgroundColor='#800000'"
								onmouseout="this.style.backgroundColor='#9E122C'">
								<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6M4 20h16"/></svg>
								Generate
							</button>
							<button @click="clearFilters" :disabled="loading"
								class="px-4 py-2.5 text-sm font-medium border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all min-h-[42px] active:scale-[0.98]">
								Clear
							</button>
						</div>
					</div>
					<!-- Export row -->
					<div class="flex flex-wrap items-center gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
						<span class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Export as:</span>
						<button @click="downloadPdf" :disabled="loading"
							class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-xl border transition-all duration-200 disabled:opacity-50 hover:shadow-sm active:scale-[0.97]"
							style="border-color:#9E122C; color:#9E122C"
							onmouseover="this.style.backgroundColor='#9E122C'; this.style.color='white'; this.style.borderColor='#9E122C'"
							onmouseout="this.style.backgroundColor='transparent'; this.style.color='#9E122C'; this.style.borderColor='#9E122C'">
							<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
							PDF
						</button>
						<button @click="downloadExcel" :disabled="loading"
							class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-xl border border-emerald-500 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500 hover:text-white dark:hover:bg-emerald-500 dark:hover:text-white disabled:opacity-50 transition-all duration-200 hover:shadow-sm active:scale-[0.97]">
							<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
							Excel
						</button>
					</div>
				</div>

				<!-- SIS Upload Panel -->
				<div v-show="activeTab === 'sis'">
					<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
						<div>
							<label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">School Year</label>
							<select v-model="sisSchoolYear"
								class="w-full px-3.5 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/30 focus:border-[#9E122C] transition-all appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2212%22%20height%3D%2212%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%239ca3af%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22m6%209%206%206%206-6%22%2F%3E%3C%2Fsvg%3E')] bg-[length:12px] bg-[right_12px_center] bg-no-repeat pr-9">
								<option value="">All School Years</option>
								<option v-for="yr in sisSchoolYears" :key="yr" :value="yr">{{ yr }}</option>
							</select>
						</div>
					</div>
					<!-- Export row -->
					<div class="flex flex-wrap items-center gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
						<span class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Download:</span>
						<button @click="downloadSisPassers" :disabled="sisLoading"
							class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-xl border transition-all duration-200 disabled:opacity-50 hover:shadow-sm active:scale-[0.97]"
							style="border-color:#9E122C; color:#9E122C"
							onmouseover="this.style.backgroundColor='#9E122C'; this.style.color='white'; this.style.borderColor='#9E122C'"
							onmouseout="this.style.backgroundColor='transparent'; this.style.color='#9E122C'; this.style.borderColor='#9E122C'">
							<svg v-if="!sisLoading" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
							<svg v-else class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
							{{ sisLoading ? 'Preparing…' : 'Passers XLSX' }}
						</button>
						<button @click="downloadSisRecon" :disabled="sisLoading"
							class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-xl border border-emerald-500 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500 hover:text-white dark:hover:bg-emerald-500 dark:hover:text-white disabled:opacity-50 transition-all duration-200 hover:shadow-sm active:scale-[0.97]">
							<svg v-if="!sisLoading" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
							<svg v-else class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
							{{ sisLoading ? 'Preparing…' : 'Recon XLSX' }}
						</button>
					</div>
				</div>

				<!-- Official Logbook Panel -->
				<div v-show="activeTab === 'logbook'">
					<!-- Filters -->
					<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
						<div>
							<label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Process Step</label>
							<select v-model="logbookStep"
								class="w-full px-3.5 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/30 focus:border-[#9E122C] transition-all appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2212%22%20height%3D%2212%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%239ca3af%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22m6%209%206%206%206-6%22%2F%3E%3C%2Fsvg%3E')] bg-[length:12px] bg-[right_12px_center] bg-no-repeat pr-9">
								<option :value="1">1 - Checking of Completeness and Authenticity of Documents</option>
								<option :value="2">2 - Grade Computation and Verification</option>
								<option :value="3">3 - Interview and Submission of Entrance Credentials</option>
							</select>
						</div>
						<div>
							<label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Date</label>
							<input type="date" v-model="logbookDate"
								class="w-full px-3.5 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/30 focus:border-[#9E122C] transition-all" />
						</div>
						<div class="flex items-end sm:col-span-2 lg:col-span-2">
							<button
								@click="downloadLogbookPdf"
								:disabled="!logbookEntries?.total || logbookEntries.total === 0"
								class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold rounded-xl border transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed hover:shadow-sm active:scale-[0.97]"
								style="border-color:#9E122C; color:#9E122C"
								onmouseover="this.style.backgroundColor='#9E122C'; this.style.color='white'"
								onmouseout="this.style.backgroundColor='transparent'; this.style.color='#9E122C'">
								<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
								Export PDF Logbook
							</button>
						</div>
					</div>

					<!-- Error -->
					<div v-if="logbookError" class="mb-4 px-4 py-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 text-sm">
						{{ logbookError }}
					</div>

					<!-- Loading -->
					<div v-if="logbookLoading" class="flex flex-col items-center justify-center py-16 gap-3">
						<svg class="animate-spin h-7 w-7" style="color:#9E122C" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
						<p class="text-sm text-gray-500 dark:text-gray-400">Loading entries…</p>
					</div>

					<!-- Empty state -->
					<div v-else-if="!logbookEntries?.data?.length" class="flex flex-col items-center justify-center py-16 gap-3 text-gray-400 dark:text-gray-500">
						<div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-gray-100 dark:bg-gray-700/50">
							<svg class="w-7 h-7 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253"/></svg>
						</div>
						<p class="text-sm font-semibold text-gray-500 dark:text-gray-400">No entries found for this step and date.</p>
					</div>

					<!-- Table -->
					<div v-else class="overflow-x-auto">
						<table class="min-w-full text-sm">
							<thead>
								<tr class="bg-gray-50/80 dark:bg-gray-900/40 border-b border-gray-200 dark:border-gray-700 text-left">
									<th class="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">#</th>
									<th class="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap">Date/Time Requested</th>
									<th class="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Client Name</th>
									<th class="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Program</th>
									<th class="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Sex</th>
									<th class="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Email</th>
									<th class="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap">Date/Time Processed</th>
									<th class="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Minutes</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
								<tr v-for="(entry, idx) in logbookEntries.data" :key="idx" class="hover:bg-gray-50/80 dark:hover:bg-gray-700/40 transition-colors">
									<td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ (logbookEntries.from ?? 0) + idx }}</td>
									<td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ entry.requested_at }}</td>
									<td class="px-4 py-3 text-xs font-semibold text-gray-900 dark:text-white">{{ entry.client_name }}</td>
									<td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ entry.program }}</td>
									<td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ entry.sex }}</td>
									<td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ entry.email }}</td>
									<td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ entry.processed_at }}</td>
									<td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ entry.minutes_processed }}</td>
								</tr>
							</tbody>
						</table>
					</div>

					<!-- Pagination -->
					<div v-if="!logbookLoading && logbookEntries?.last_page > 1" class="mt-4 flex flex-wrap items-center justify-between gap-3">
						<p class="text-xs text-gray-500 dark:text-gray-400">
							Showing <strong class="font-semibold text-gray-700 dark:text-gray-300">{{ logbookEntries.from }}–{{ logbookEntries.to }}</strong> of <strong class="font-semibold text-gray-700 dark:text-gray-300">{{ logbookEntries.total }}</strong>
						</p>
						<div class="flex items-center gap-2">
							<button :disabled="logbookEntries.current_page === 1" @click="fetchLogbook(logbookEntries.current_page - 1)"
								class="inline-flex items-center gap-1 px-3.5 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-xl text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all min-h-[38px] active:scale-[0.97]">
								<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
								<span class="hidden sm:inline">Prev</span>
							</button>
							<span class="text-xs text-gray-500 dark:text-gray-400">Page {{ logbookEntries.current_page }} of {{ logbookEntries.last_page }}</span>
							<button :disabled="logbookEntries.current_page === logbookEntries.last_page" @click="fetchLogbook(logbookEntries.current_page + 1)"
								class="inline-flex items-center gap-1 px-3.5 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-xl text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all min-h-[38px] active:scale-[0.97]">
								<span class="hidden sm:inline">Next</span>
								<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
							</button>
						</div>
					</div>
				</div>

				<!-- Control List Panel -->
				<div v-show="activeTab === 'control-list'">
					<!-- Filters -->
					<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
						<div>
							<label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Program</label>
							<select v-model="controlListProgram"
								class="w-full px-3.5 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/30 focus:border-[#9E122C] transition-all appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2212%22%20height%3D%2212%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%239ca3af%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22m6%209%206%206%206-6%22%2F%3E%3C%2Fsvg%3E')] bg-[length:12px] bg-[right_12px_center] bg-no-repeat pr-9">
								<option value="">Select a Program</option>
								<option v-for="program in programs" :key="program.id" :value="program.id">
									{{ program.code }} – {{ program.name }}
								</option>
							</select>
						</div>
						<div>
							<label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Academic Year</label>
							<input type="text" v-model="controlListYear" placeholder="e.g. 2026-2027"
								class="w-full px-3.5 py-2.5 text-sm border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#9E122C]/30 focus:border-[#9E122C] transition-all" />
						</div>
						<div class="flex items-end sm:col-span-2 lg:col-span-2">
							<button
								@click="downloadControlListPdf"
								:disabled="!controlListProgram || !controlListYear"
								class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold rounded-xl border transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed hover:shadow-sm active:scale-[0.97]"
								style="border-color:#9E122C; color:#9E122C"
								onmouseover="this.style.backgroundColor='#9E122C'; this.style.color='white'"
								onmouseout="this.style.backgroundColor='transparent'; this.style.color='#9E122C'">
								<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
								Export Control List PDF
							</button>
						</div>
					</div>

					<!-- Error -->
					<div v-if="controlListError" class="mb-4 px-4 py-3 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 text-sm">
						{{ controlListError }}
					</div>

					<!-- No program selected placeholder -->
					<div v-if="!controlListProgram && !controlListLoading" class="flex flex-col items-center justify-center py-16 gap-3 text-gray-400 dark:text-gray-500">
						<div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-gray-100 dark:bg-gray-700/50">
							<svg class="w-7 h-7 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
						</div>
						<p class="text-sm font-semibold text-gray-500 dark:text-gray-400">Please select a program.</p>
					</div>

					<!-- Loading -->
					<div v-else-if="controlListLoading" class="flex flex-col items-center justify-center py-16 gap-3">
						<svg class="animate-spin h-7 w-7" style="color:#9E122C" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
						<p class="text-sm text-gray-500 dark:text-gray-400">Loading applicants…</p>
					</div>

					<!-- Empty state (program selected, no results) -->
					<div v-else-if="controlListProgram && !controlListData?.data?.length" class="flex flex-col items-center justify-center py-16 gap-3 text-gray-400 dark:text-gray-500">
						<div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-gray-100 dark:bg-gray-700/50">
							<svg class="w-7 h-7 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
						</div>
						<p class="text-sm font-semibold text-gray-500 dark:text-gray-400">No applicants found for the selected program.</p>
					</div>

					<!-- Table -->
					<div v-else-if="controlListProgram && controlListData?.data?.length" class="overflow-x-auto">
						<table class="min-w-full text-sm">
							<thead>
								<tr class="bg-gray-50/80 dark:bg-gray-900/40 border-b border-gray-200 dark:border-gray-700 text-left">
									<th class="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Name of Candidate</th>
									<th class="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Strand/Track</th>
									<th class="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">GWA</th>
									<th class="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Math</th>
									<th class="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Science</th>
									<th class="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">English</th>
									<th class="px-4 py-3 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Notes</th>
								</tr>
							</thead>
							<tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
								<tr v-for="applicant in controlListData.data" :key="applicant.id" class="hover:bg-gray-50/80 dark:hover:bg-gray-700/40 transition-colors">
									<td class="px-4 py-3 text-xs font-semibold text-gray-900 dark:text-white">{{ applicant.full_name }}</td>
									<td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ applicant.strand }}</td>
									<td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ applicant.gwa }}</td>
									<td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ applicant.math_gwa }}</td>
									<td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ applicant.science_gwa }}</td>
									<td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ applicant.english_gwa }}</td>
									<td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 italic">{{ applicant.notes || '—' }}</td>
								</tr>
							</tbody>
						</table>
					</div>

					<!-- Pagination -->
					<div v-if="!controlListLoading && controlListData?.last_page > 1" class="mt-4 flex flex-wrap items-center justify-between gap-3">
						<p class="text-xs text-gray-500 dark:text-gray-400">
							Showing <strong class="font-semibold text-gray-700 dark:text-gray-300">{{ controlListData.from }}–{{ controlListData.to }}</strong> of <strong class="font-semibold text-gray-700 dark:text-gray-300">{{ controlListData.total }}</strong>
						</p>
						<div class="flex items-center gap-2">
							<button :disabled="controlListData.current_page === 1" @click="fetchControlList(controlListData.current_page - 1)"
								class="inline-flex items-center gap-1 px-3.5 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-xl text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all min-h-[38px] active:scale-[0.97]">
								<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
								<span class="hidden sm:inline">Prev</span>
							</button>
							<span class="text-xs text-gray-500 dark:text-gray-400">Page {{ controlListData.current_page }} of {{ controlListData.last_page }}</span>
							<button :disabled="controlListData.current_page === controlListData.last_page" @click="fetchControlList(controlListData.current_page + 1)"
								class="inline-flex items-center gap-1 px-3.5 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-xl text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all min-h-[38px] active:scale-[0.97]">
								<span class="hidden sm:inline">Next</span>
								<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- ── Results Table Card (unchanged functionality) ─────────────── -->
		<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
			<!-- Header -->
			<div class="flex items-center justify-between px-5 sm:px-6 py-3.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/60">
				<div class="flex items-center gap-2.5">
					<div class="w-7 h-7 rounded-md flex items-center justify-center shrink-0" style="background-color:#9E122C15">
						<svg class="w-3.5 h-3.5" style="color:#9E122C" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10"/></svg>
					</div>
					<div>
						<h3 class="text-[11px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">{{ reportTypeLabel }}</h3>
						<p class="text-[10px] text-gray-400 dark:text-gray-500">{{ total.toLocaleString() }} result{{ total !== 1 ? 's' : '' }}</p>
					</div>
				</div>
				<span v-if="!loading && total > 0" class="text-[10px] font-semibold bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 px-2.5 py-1 rounded-full">{{ perPage }} per page</span>
			</div>

			<!-- Loading -->
			<div v-if="loading" class="flex flex-col items-center justify-center py-20 gap-4">
				<svg class="animate-spin h-8 w-8" style="color:#9E122C" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
				<p class="text-sm font-medium text-gray-600 dark:text-gray-300">Loading data…</p>
			</div>

			<!-- Table -->
			<div v-else class="overflow-x-auto">
				<table class="min-w-full text-sm">
					<thead>
						<tr class="bg-gray-50/80 dark:bg-gray-900/40 border-b border-gray-200 dark:border-gray-700 text-left">
							<th class="px-5 sm:px-6 py-3.5 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap">Reference No.</th>
							<th class="px-5 sm:px-6 py-3.5 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Name</th>
							<th class="px-5 sm:px-6 py-3.5 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap">Course / Program</th>
							<th class="px-5 sm:px-6 py-3.5 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
							<th v-if="filterType === 'pulled_out'" class="px-5 sm:px-6 py-3.5 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap">Pull-Out Notes</th>
							<th class="px-5 sm:px-6 py-3.5 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap">Date</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
						<tr v-for="app in applicants" :key="app.id" class="hover:bg-gray-50/80 dark:hover:bg-gray-700/40 transition-colors">
							<td class="px-5 sm:px-6 py-4 font-mono text-xs font-semibold text-gray-700 dark:text-gray-300 whitespace-nowrap">
								<span class="bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded-md">{{ app.reference_number }}</span>
							</td>
							<td class="px-5 sm:px-6 py-4 font-semibold text-gray-900 dark:text-white">{{ app.name }}</td>
							<td class="px-5 sm:px-6 py-4 text-gray-600 dark:text-gray-300 text-xs">{{ app.program }}</td>
							<td class="px-5 sm:px-6 py-4">
								<span :class="getStatusBadge(app.status)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold whitespace-nowrap">
									<span :class="getStatusDot(app.status)" class="w-1.5 h-1.5 rounded-full shrink-0"></span>
									{{ app.status }}
								</span>
							</td>
							<td v-if="filterType === 'pulled_out'" class="px-5 sm:px-6 py-4 text-gray-500 dark:text-gray-400 text-xs italic max-w-[200px] truncate">{{ app.pullout_notes || '—' }}</td>
							<td class="px-5 sm:px-6 py-4 text-gray-500 dark:text-gray-400 whitespace-nowrap text-xs tabular-nums">{{ app.date }}</td>
						</tr>
						<tr v-if="applicants.length === 0">
							<td :colspan="filterType === 'pulled_out' ? 6 : 5" class="py-20 text-center">
								<div class="flex flex-col items-center gap-4 text-gray-400 dark:text-gray-500">
									<div class="w-16 h-16 rounded-2xl flex items-center justify-center bg-gray-100 dark:bg-gray-700/50">
										<svg class="w-8 h-8 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6M4 20h16M4 4h16v8H4z"/></svg>
									</div>
									<div>
										<p class="text-sm font-semibold text-gray-500 dark:text-gray-400">No results found</p>
										<p class="text-xs mt-1">Try adjusting your filters or selecting a different report type.</p>
									</div>
									<button @click="clearFilters" class="text-xs font-medium underline underline-offset-2 hover:text-[#9E122C] dark:hover:text-red-400 transition-colors">Reset all filters</button>
								</div>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Pagination -->
			<div v-if="!loading && lastPage > 1" class="px-5 sm:px-6 py-3.5 border-t border-gray-100 dark:border-gray-700 flex flex-wrap items-center justify-between gap-3 bg-gray-50/40 dark:bg-gray-800/40">
				<p class="text-xs text-gray-500 dark:text-gray-400">
					<span v-if="total === 0">No results</span>
					<span v-else>Showing <strong class="text-gray-700 dark:text-gray-300 font-semibold">{{ paginationStart }}–{{ paginationEnd }}</strong> of <strong class="text-gray-700 dark:text-gray-300 font-semibold">{{ total.toLocaleString() }}</strong></span>
				</p>
				<div class="flex items-center gap-2">
					<button :disabled="currentPage === 1" @click.prevent="fetchReportData(currentPage - 1)"
						class="inline-flex items-center gap-1 px-3.5 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-xl text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all min-h-[38px] active:scale-[0.97]">
						<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
						<span class="hidden sm:inline">Prev</span>
					</button>
					<div class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400">
						<span class="text-xs hidden sm:inline">Page</span>
						<input type="number" :value="currentPage" min="1" :max="lastPage || 1"
							@change="fetchReportData(Math.max(1, Math.min(Number($event.target.value), lastPage || 1)))"
							class="w-16 px-2.5 py-1.5 text-center text-sm font-semibold border border-gray-200 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-[#9E122C]/30 focus:border-[#9E122C] focus:outline-none transition-all" />
						<span class="text-xs">of <strong class="font-semibold">{{ lastPage || 1 }}</strong></span>
					</div>
					<button :disabled="currentPage === lastPage || lastPage === 0" @click.prevent="fetchReportData(currentPage + 1)"
						class="inline-flex items-center gap-1 px-3.5 py-2 text-sm border border-gray-200 dark:border-gray-600 rounded-xl text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all min-h-[38px] active:scale-[0.97]">
						<span class="hidden sm:inline">Next</span>
						<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
					</button>
				</div>
			</div>
			<div v-if="!loading && lastPage <= 1 && total > 0" class="px-5 sm:px-6 py-3 border-t border-gray-100 dark:border-gray-700 bg-gray-50/40 dark:bg-gray-800/40 text-center">
				<p class="text-xs text-gray-400 dark:text-gray-500">Showing all {{ total.toLocaleString() }} result{{ total !== 1 ? 's' : '' }}</p>
			</div>
		</div>
	</AppLayout>
</template>