<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, computed, watch } from 'vue'
import VueCal from 'vue-cal'
import 'vue-cal/dist/vuecal.css'
import axios from 'axios'
import { enumOptions } from '@/utils/enums'
import { CalendarDays, Clock, MapPin, FileText, Tag, ChevronRight, Plus, Filter, Search, Edit2, Trash2, X } from 'lucide-vue-next'

// State management
const modalOpen = ref(false)
const eventListModalOpen = ref(false)
const editingEvent = ref(null)
const selectedDateEvents = ref([])
const viewMode = ref('month') // 'month', 'week', 'day'
const filterType = ref('all')
const searchQuery = ref('')
const loading = ref(false)

// Form data
const form = ref({
  name: '',
  date: '',
  startTime: '',
  endTime: '',
  type: 'application',
  description: '',
  location: '',
  affected_programs: [],
  priority: 'medium'
})

const events = ref([])

// Utility functions
function toLocalDateString(date) {
  const tzoffset = date.getTimezoneOffset() * 60000
  return new Date(date.getTime() - tzoffset).toISOString().slice(0, 10)
}

async function fetchSchedules() {
  loading.value = true
  try {
    const res = await axios.get('/schedules', {
      headers: { Accept: 'application/json' }
    })
    const dataArray = Array.isArray(res.data) ? res.data : res.data.data || []
    events.value = dataArray.map(event => ({
      id: event.id,
      title: event.name,
      start: new Date(event.start),
      end: new Date(event.end),
      type: event.type || 'application',
      description: event.description || '',
      location: event.location || '',
      priority: event.priority || 'medium',
      affected_programs: event.affected_programs || []
    }))
  } catch (error) {
    console.error('Failed to fetch schedules:', error)
    alert('Failed to load schedules')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchSchedules()
})

const today = new Date()
today.setHours(0, 0, 0, 0)

const disabledDates = date => date < today

// Computed properties
const filteredEvents = computed(() => {
  let filtered = events.value
  
  // Filter by type
  if (filterType.value !== 'all') {
    filtered = filtered.filter(event => event.type === filterType.value)
  }
  
  // Filter by search query
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(event => 
      event.title.toLowerCase().includes(query) ||
      event.description.toLowerCase().includes(query) ||
      event.location.toLowerCase().includes(query)
    )
  }
  
  return filtered
})

const upcomingEvents = computed(() => {
  const now = new Date()
  return filteredEvents.value
    .filter(e => e.start >= now)
    .sort((a, b) => a.start - b.start)
    .slice(0, 6)
})

const pastEvents = computed(() => {
  const now = new Date()
  return filteredEvents.value
    .filter(e => e.start < now)
    .sort((a, b) => b.start - a.start)
    .slice(0, 4)
})

const eventStats = computed(() => {
  const now = new Date()
  const upcoming = events.value.filter(e => e.start >= now).length
  const past = events.value.filter(e => e.start < now).length
  
  const typeCounts = {}
  events.value.forEach(event => {
    typeCounts[event.type] = (typeCounts[event.type] || 0) + 1
  })
  
  const mostCommonType = Object.entries(typeCounts).sort((a, b) => b[1] - a[1])[0]
  
  return { upcoming, past, mostCommonType }
})

// Formatting functions
function formatDateTime(date) {
  const d = date instanceof Date ? date : new Date(date)
  return d.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  }) + ' • ' + d.toLocaleTimeString(undefined, { 
    hour: '2-digit', 
    minute: '2-digit',
    hour12: true 
  })
}

function formatTimeOnly(date) {
  const d = date instanceof Date ? date : new Date(date)
  return d.toLocaleTimeString(undefined, { 
    hour: '2-digit', 
    minute: '2-digit',
    hour12: true 
  })
}

function getEventColor(type) {
  const colors = {
    application: 'bg-blue-100 text-blue-800 border-blue-200',
    interview: 'bg-green-100 text-green-800 border-green-200',
    medical: 'bg-red-100 text-red-800 border-red-200',
    announcement: 'bg-purple-100 text-purple-800 border-purple-200',
    other: 'bg-gray-100 text-gray-800 border-gray-200'
  }
  return colors[type] || colors.other
}

function getPriorityColor(priority) {
  const colors = {
    high: 'bg-red-500',
    medium: 'bg-yellow-500',
    low: 'bg-green-500'
  }
  return colors[priority] || colors.medium
}

// Event handlers
function onDateSelect(payload) {
  const date = payload.date || payload
  if (!date) return
  if (disabledDates(date)) return

  const clickedDateStr = toLocalDateString(date)
  selectedDateEvents.value = filteredEvents.value.filter(
    event => toLocalDateString(event.start) === clickedDateStr
  )

  if (selectedDateEvents.value.length > 1) {
    eventListModalOpen.value = true
  } else if (selectedDateEvents.value.length === 1) {
    openEventForm(selectedDateEvents.value[0])
  } else {
    openEventForm(null, clickedDateStr)
  }
}

function openEventForm(event = null, date = null) {
  editingEvent.value = event
  if (event) {
    form.value = {
      name: event.title,
      date: toLocalDateString(event.start),
      startTime: event.start.toISOString().slice(11, 16),
      endTime: event.end.toISOString().slice(11, 16),
      type: event.type || 'application',
      description: event.description || '',
      location: event.location || '',
      affected_programs: event.affected_programs || [],
      priority: event.priority || 'medium'
    }
  } else if (date) {
    form.value = {
      name: '',
      date,
      startTime: '09:00',
      endTime: '10:00',
      type: 'application',
      description: '',
      location: '',
      affected_programs: [],
      priority: 'medium'
    }
  }
  modalOpen.value = true
  eventListModalOpen.value = false
}

function closeEventListModal() {
  eventListModalOpen.value = false
}

function onEventClick(event) {
  openEventForm(event)
}

function closeModal() {
  modalOpen.value = false
  editingEvent.value = null
  form.value = {
    name: '',
    date: '',
    startTime: '',
    endTime: '',
    type: 'application',
    description: '',
    location: '',
    affected_programs: [],
    priority: 'medium'
  }
}

async function saveEvent() {
  if (form.value.endTime <= form.value.startTime) {
    alert('End time must be after start time.')
    return
  }

  const payload = {
    name: form.value.name,
    start: `${form.value.date}T${form.value.startTime}`,
    end: `${form.value.date}T${form.value.endTime}`,
    type: form.value.type,
    description: form.value.description || null,
    location: form.value.location || null,
    affected_programs: form.value.affected_programs || null,
    priority: form.value.priority
  }

  try {
    if (editingEvent.value) {
      await axios.put(`/schedules/${editingEvent.value.id}`, payload, {
        headers: { Accept: 'application/json' }
      })
    } else {
      await axios.post('/schedules', payload, {
        headers: { Accept: 'application/json' }
      })
    }
    await fetchSchedules()
    closeModal()
  } catch (error) {
    console.error('Failed to save schedule:', error)
    alert('Failed to save schedule, please try again.')
  }
}

async function deleteEvent(id) {
  try {
    await axios.delete(`/schedules/${id}`, {
      headers: { Accept: 'application/json' }
    })
    await fetchSchedules()
    closeModal()
  } catch (error) {
    console.error('Failed to delete schedule:', error)
    alert('Failed to delete schedule, please try again.')
  }
}

function quickCreateToday() {
  const today = new Date()
  const tomorrow = new Date(today)
  tomorrow.setDate(tomorrow.getDate() + 1)
  
  form.value = {
    name: 'New Event',
    date: toLocalDateString(today),
    startTime: '14:00',
    endTime: '15:00',
    type: 'application',
    description: '',
    location: '',
    affected_programs: [],
    priority: 'medium'
  }
  modalOpen.value = true
}
</script>

<template>
  <AppLayout>
    <!-- Header Section -->
    <div class="mb-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h2 class="text-3xl font-bold text-gray-900">Schedule Manager</h2>
          <p class="text-gray-600 mt-1">Manage and organize all your events and appointments</p>
        </div>
        <button
          @click="quickCreateToday"
          class="inline-flex items-center gap-2 px-4 py-3 bg-[#9E122C] hover:bg-[#EE6A43] text-white rounded-lg font-medium transition-colors shadow-sm hover:shadow-md"
        >
          <Plus class="w-5 h-5" />
          <span>Create Event</span>
        </button>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600">Upcoming Events</p>
              <p class="text-2xl font-bold text-gray-900 mt-1">{{ eventStats.upcoming }}</p>
            </div>
            <div class="p-3 bg-blue-50 rounded-lg">
              <CalendarDays class="w-6 h-6 text-blue-600" />
            </div>
          </div>
        </div>
        
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600">Past Events</p>
              <p class="text-2xl font-bold text-gray-900 mt-1">{{ eventStats.past }}</p>
            </div>
            <div class="p-3 bg-gray-50 rounded-lg">
              <Clock class="w-6 h-6 text-gray-600" />
            </div>
          </div>
        </div>
        
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600">Most Common Type</p>
              <p class="text-lg font-semibold text-gray-900 mt-1 capitalize">{{ eventStats.mostCommonType?.[0] || 'N/A' }}</p>
            </div>
            <div class="p-3 bg-green-50 rounded-lg">
              <Tag class="w-6 h-6 text-green-600" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="flex flex-col lg:flex-row gap-8">
      <!-- Left Column - Calendar & Controls -->
      <div class="lg:w-2/3">
        <!-- Calendar Controls -->
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100 mb-6">
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div class="flex items-center gap-3">
              <div class="flex bg-gray-100 rounded-lg p-1">
                <button
                  @click="viewMode = 'month'"
                  :class="[
                    'px-4 py-2 rounded-md text-sm font-medium transition-colors',
                    viewMode === 'month' 
                      ? 'bg-white text-[#9E122C] shadow-sm' 
                      : 'text-gray-600 hover:text-gray-900'
                  ]"
                >
                  Month
                </button>
                <button
                  @click="viewMode = 'week'"
                  :class="[
                    'px-4 py-2 rounded-md text-sm font-medium transition-colors',
                    viewMode === 'week' 
                      ? 'bg-white text-[#9E122C] shadow-sm' 
                      : 'text-gray-600 hover:text-gray-900'
                  ]"
                >
                  Week
                </button>
                <button
                  @click="viewMode = 'day'"
                  :class="[
                    'px-4 py-2 rounded-md text-sm font-medium transition-colors',
                    viewMode === 'day' 
                      ? 'bg-white text-[#9E122C] shadow-sm' 
                      : 'text-gray-600 hover:text-gray-900'
                  ]"
                >
                  Day
                </button>
              </div>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
              <div class="relative flex-1 sm:flex-none">
                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Search events..."
                  class="w-full sm:w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
                />
              </div>
              
              <select
                v-model="filterType"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-transparent"
              >
                <option value="all">All Types</option>
                <option value="application">Application</option>
                <option value="interview">Interview</option>
                <option value="medical">Medical</option>
                <option value="announcement">Announcement</option>
                <option value="other">Other</option>
              </select>
            </div>
          </div>

          <!-- Calendar -->
          <div class="rounded-xl overflow-hidden border border-gray-200">
            <vue-cal
              :key="viewMode"
              :style="{ height: viewMode === 'month' ? '500px' : '600px' }"
              :events="filteredEvents"
              :disable-views="['years', 'year']"
              :default-view="viewMode"
              @cell-click="onDateSelect"
              @event-click="onEventClick"
              :time="viewMode !== 'month'"
              :disabled-dates="disabledDates"
              class="vuecal-theme-red"
            >
              <template #cell-content="{ cell, events }">
                <div style="position: relative;">
                  {{ cell.content }}
                  <span
                    v-if="events.length"
                    style="
                      position: absolute;
                      top: 2px;
                      right: 2px;
                      font-size: 12px;
                      pointer-events: none;
                      user-select: none;
                      color: #EE6A43;
                      border-radius: 2px;
                      padding: 0 2px;
                      line-height: 1;
                    "
                  >
                    📅
                  </span>
                </div>
              </template>
              
              <template #event="{ event }">
                <div 
                  :class="[
                    'p-2 rounded text-xs font-medium truncate',
                    getEventColor(event.type)
                  ]"
                >
                  {{ event.title }}
                  <div v-if="viewMode !== 'month'" class="text-xs opacity-75">
                    {{ formatTimeOnly(event.start) }}
                  </div>
                </div>
              </template>
            </vue-cal>
          </div>
        </div>
      </div>

      <!-- Right Column - Upcoming & Past Events -->
      <div class="lg:w-1/3 space-y-6">
        <!-- Upcoming Events -->
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Upcoming Events</h3>
            <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full font-medium">
              {{ upcomingEvents.length }}
            </span>
          </div>
          
          <div v-if="loading" class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[#9E122C] mx-auto"></div>
          </div>
          
          <div v-else-if="upcomingEvents.length === 0" class="text-center py-8 text-gray-500">
            <CalendarDays class="w-12 h-12 mx-auto mb-3 text-gray-300" />
            <p>No upcoming events</p>
          </div>
          
          <div v-else class="space-y-3">
            <div
              v-for="event in upcomingEvents"
              :key="event.id"
              @click="openEventForm(event)"
              class="group p-4 border border-gray-200 rounded-lg hover:border-[#9E122C] hover:shadow-sm transition-all cursor-pointer"
            >
              <div class="flex justify-between items-start mb-2">
                <h4 class="font-medium text-gray-900 group-hover:text-[#9E122C] transition-colors">
                  {{ event.title }}
                </h4>
                <div class="flex items-center gap-2">
                  <div :class="['w-2 h-2 rounded-full', getPriorityColor(event.priority)]"></div>
                  <span :class="['px-2 py-1 rounded text-xs font-medium', getEventColor(event.type)]">
                    {{ event.type }}
                  </span>
                </div>
              </div>
              
              <div class="space-y-1 text-sm text-gray-600">
                <div class="flex items-center gap-2">
                  <CalendarDays class="w-4 h-4" />
                  <span>{{ formatDateTime(event.start) }}</span>
                </div>
                <div v-if="event.location" class="flex items-center gap-2">
                  <MapPin class="w-4 h-4" />
                  <span>{{ event.location }}</span>
                </div>
              </div>
              
              <div class="flex justify-between items-center mt-3">
                <button
                  @click.stop="openEventForm(event)"
                  class="text-sm text-[#9E122C] hover:text-[#EE6A43] font-medium inline-flex items-center gap-1"
                >
                  View Details
                  <ChevronRight class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Past Events -->
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Recent Past Events</h3>
            <span class="text-xs px-2 py-1 bg-gray-100 text-gray-800 rounded-full font-medium">
              {{ pastEvents.length }}
            </span>
          </div>
          
          <div v-if="pastEvents.length === 0" class="text-center py-4 text-gray-500">
            <p>No past events</p>
          </div>
          
          <div v-else class="space-y-3">
            <div
              v-for="event in pastEvents"
              :key="event.id"
              @click="openEventForm(event)"
              class="group p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer"
            >
              <div class="flex justify-between items-center">
                <h4 class="font-medium text-gray-700">{{ event.title }}</h4>
                <span :class="['px-2 py-1 rounded text-xs font-medium opacity-75', getEventColor(event.type)]">
                  {{ event.type }}
                </span>
              </div>
              <p class="text-sm text-gray-500 mt-1">{{ formatDateTime(event.start) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Event List Modal -->
    <transition name="fade">
      <div
        v-if="eventListModalOpen"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
        @click.self="closeEventListModal"
      >
        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[80vh] overflow-hidden">
          <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
              <h3 class="text-xl font-semibold text-gray-900">
                Events on {{ selectedDateEvents[0] ? toLocalDateString(selectedDateEvents[0].start) : '' }}
              </h3>
              <button
                @click="closeEventListModal"
                class="text-gray-400 hover:text-gray-600"
              >
                <X class="w-6 h-6" />
              </button>
            </div>
          </div>
          
          <div class="p-6 overflow-y-auto max-h-[calc(80vh-140px)]">
            <ul class="space-y-3">
              <li
                v-for="event in selectedDateEvents"
                :key="event.id"
                @click="openEventForm(event)"
                class="p-4 border border-gray-200 rounded-lg hover:border-[#9E122C] hover:shadow-sm transition-all cursor-pointer"
              >
                <div class="flex justify-between items-start mb-2">
                  <h4 class="font-medium text-gray-900">{{ event.title }}</h4>
                  <span :class="['px-2 py-1 rounded text-xs font-medium', getEventColor(event.type)]">
                    {{ event.type }}
                  </span>
                </div>
                <div class="text-sm text-gray-600">
                  {{ formatTimeOnly(event.start) }} - {{ formatTimeOnly(event.end) }}
                </div>
                <div v-if="event.location" class="text-sm text-gray-500 mt-1">
                  {{ event.location }}
                </div>
              </li>
            </ul>
          </div>
          
          <div class="p-6 border-t border-gray-200 bg-gray-50">
            <div class="flex gap-3">
              <button
                @click="openEventForm(null, selectedDateEvents[0] ? toLocalDateString(selectedDateEvents[0].start) : '')"
                class="flex-1 px-4 py-3 rounded-lg bg-[#9E122C] text-white hover:bg-[#EE6A43] transition-colors font-medium"
              >
                Create New Event
              </button>
              <button
                @click="closeEventListModal"
                class="px-4 py-3 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors font-medium"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- Create/Edit Modal -->
    <transition name="fade">
      <div v-if="modalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-hidden" @click.stop>
          <!-- Modal Header -->
          <div class="p-6 border-b border-gray-200 bg-gradient-to-r from-[#9E122C] to-[#EE6A43] text-white">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="text-xl font-semibold">
                  {{ editingEvent ? 'Edit Event' : 'Create New Event' }}
                </h3>
                <p class="text-white/80 text-sm mt-1">
                  {{ editingEvent ? 'Update event details' : 'Schedule a new event' }}
                </p>
              </div>
              <button
                @click="closeModal"
                class="text-white hover:text-white/80"
              >
                <X class="w-6 h-6" />
              </button>
            </div>
          </div>

          <!-- Modal Body -->
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-180px)]">
            <form @submit.prevent="saveEvent" class="space-y-5">
              <!-- Event Name -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Event Name *
                </label>
                <input
                  v-model="form.name"
                  type="text"
                  required
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-transparent transition"
                  placeholder="Enter event name"
                />
              </div>

              <!-- Date & Time -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Date *
                  </label>
                  <input
                    v-model="form.date"
                    type="date"
                    required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-transparent transition"
                  />
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Start Time *
                    </label>
                    <input
                      v-model="form.startTime"
                      type="time"
                      required
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-transparent transition"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      End Time *
                    </label>
                    <input
                      v-model="form.endTime"
                      type="time"
                      required
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-transparent transition"
                    />
                  </div>
                </div>
              </div>

              <!-- Type & Priority -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Event Type *
                  </label>
                  <select
                    v-model="form.type"
                    required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-transparent transition"
                  >
                    <option value="application">Application</option>
                    <option value="interview">Interview</option>
                    <option value="medical">Medical</option>
                    <option value="announcement">Announcement</option>
                    <option value="other">Other</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Priority
                  </label>
                  <select
                    v-model="form.priority"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-transparent transition"
                  >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                  </select>
                </div>
              </div>

              <!-- Location -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Location
                </label>
                <div class="relative">
                  <MapPin class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                  <input
                    v-model="form.location"
                    type="text"
                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-transparent transition"
                    placeholder="Enter location (optional)"
                  />
                </div>
              </div>

              <!-- Description -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Description
                </label>
                <div class="relative">
                  <FileText class="absolute left-3 top-3 w-5 h-5 text-gray-400" />
                  <textarea
                    v-model="form.description"
                    rows="4"
                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#9E122C] focus:border-transparent transition resize-none"
                    placeholder="Add event description (optional)"
                  ></textarea>
                </div>
              </div>
            </form>
          </div>

          <!-- Modal Footer -->
          <div class="p-6 border-t border-gray-200 bg-gray-50">
            <div class="flex flex-col sm:flex-row gap-3">
              <div v-if="editingEvent" class="w-full sm:w-auto">
                <button
                  type="button"
                  @click="deleteEvent(editingEvent.id)"
                  class="w-full px-5 py-3 rounded-lg bg-red-600 hover:bg-red-700 text-white font-medium transition-colors flex items-center justify-center gap-2"
                >
                  <Trash2 class="w-4 h-4" />
                  Delete Event
                </button>
              </div>
              
              <div class="flex gap-3 flex-grow justify-end">
                <button
                  type="button"
                  @click="closeModal"
                  class="px-5 py-3 rounded-lg border border-gray-300 hover:bg-gray-50 font-medium transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  @click="saveEvent"
                  class="px-5 py-3 rounded-lg bg-[#9E122C] hover:bg-[#EE6A43] text-white font-medium transition-colors"
                >
                  {{ editingEvent ? 'Update Event' : 'Create Event' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </AppLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.vuecal__header,
.vuecal__cell--week-day {
  color: #9E122C !important;
}

.vuecal__cell {
  border-color: #f0e6e6 !important;
}

.vuecal__event {
  border-radius: 6px !important;
  margin: 2px !important;
  transition: all 0.2s ease !important;
}

.vuecal__event:hover {
  transform: translateY(-1px) !important;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

.vuecal__cell--today {
  background-color: rgba(238, 106, 67, 0.1) !important;
}

.vuecal__cell--selected {
  background-color: rgba(158, 18, 44, 0.1) !important;
}
</style>