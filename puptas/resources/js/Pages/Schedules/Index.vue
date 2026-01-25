<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, computed } from 'vue'
import VueCal from 'vue-cal'
import 'vue-cal/dist/vuecal.css'
import axios from 'axios'
import { enumOptions } from '@/utils/enums'

const modalOpen = ref(false)
const eventListModalOpen = ref(false)
const editingEvent = ref(null)
const selectedDateEvents = ref([])

const form = ref({
  name: '',
  date: '',
  startTime: '',
  endTime: '',
  type: 'application',
  description: '',
  location: '',
  affected_programs: []
})

const events = ref([])

function toLocalDateString(date) {
  const tzoffset = date.getTimezoneOffset() * 60000 // offset in ms
  return new Date(date.getTime() - tzoffset).toISOString().slice(0, 10)
}

async function fetchSchedules() {
  try {
    const res = await axios.get('/schedules', {
      headers: { Accept: 'application/json' }
    })
    const dataArray = Array.isArray(res.data) ? res.data : res.data.data || []
    events.value = dataArray.map(event => ({
      id: event.id,
      title: event.name,
      start: new Date(event.start), // use Date objects here
      end: new Date(event.end)
    }))
  } catch (error) {
    console.error('Failed to fetch schedules:', error)
  }
}

onMounted(() => {
  fetchSchedules()
})

const today = new Date()
today.setHours(0, 0, 0, 0)

const disabledDates = date => date < today

const nearestEvents = computed(() => {
  const now = new Date()
  return events.value
    .filter(e => e.start >= now)
    .sort((a, b) => a.start - b.start)
    .slice(0, 5)
})

function formatDateTime(date) {
  const d = date instanceof Date ? date : new Date(date)
  return (
    d.toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    }) +
    ' ' +
    d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
  )
}

function onDateSelect(payload) {
  const date = payload.date || payload
  if (!date) return
  if (disabledDates(date)) return

  const clickedDateStr = toLocalDateString(date)
  selectedDateEvents.value = events.value.filter(
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
      affected_programs: event.affected_programs || []
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
      affected_programs: []
    }
  }
  modalOpen.value = true
  eventListModalOpen.value = false
}

function closeEventListModal() {
  eventListModalOpen.value = false
}

function onEventClick(event) {
  editingEvent.value = event
  form.value = {
    name: event.title,
    date: toLocalDateString(event.start),
    startTime: event.start.toISOString().slice(11, 16),
    endTime: event.end.toISOString().slice(11, 16),
    type: event.type || 'application',
    description: event.description || '',
    location: event.location || '',
    affected_programs: event.affected_programs || []
  }
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  editingEvent.value = null
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
    affected_programs: form.value.affected_programs || null
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
  } catch (error) {
    console.error('Failed to delete schedule:', error)
    alert('Failed to delete schedule, please try again.')
  }
}

function confirmDelete() {
  if (!editingEvent.value) return
  if (confirm('Are you sure you want to delete this schedule?')) {
    deleteEvent(editingEvent.value.id)
    closeModal()
  }
}
</script>

<template>
  <AppLayout>
    <h2 class="text-3xl font-semibold text-[#9E122C] mb-6">Schedule Manager</h2>

    <div class="max-w-5xl mx-auto p-6 bg-white/25 rounded-xl shadow-md flex gap-8">

      <!-- Calendar -->
      <vue-cal
        style="flex:1; height: 400px"
        :events="events"
        :disable-views="['years', 'year', 'week', 'day', 'agenda']"
        default-view="month"
        @cell-click="onDateSelect"
        @event-click="onEventClick"
        :time="false"
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
                top: 4px;
                right: 4px;
                font-size: 14px;
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
      </vue-cal>

      <!-- Sidebar with nearest 5 upcoming events -->
      <div class="w-96 bg-white rounded-lg p-4 shadow-md">
        <h3 class="text-xl font-semibold mb-4 text-[#9E122C]">Upcoming Events</h3>
        <ul>
          <li v-if="nearestEvents.length === 0" class="text-gray-500">No upcoming events</li>
          <li
            v-for="event in nearestEvents"
            :key="event.id"
            class="mb-3 p-2 border rounded cursor-pointer hover:bg-[#EE6A43] hover:text-white transition"
            @click="openEventForm(event)"
          >
            <div class="font-semibold">{{ event.title }}</div>
            <div class="text-sm text-gray-600">
              {{ formatDateTime(event.start) }} - {{ formatDateTime(event.end) }}
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- Multiple events modal -->
    <transition name="fade">
      <div
        v-if="eventListModalOpen"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
        @click.self="closeEventListModal"
      >
        <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6 relative">
          <h3 class="text-xl font-semibold mb-4 text-[#9E122C]">
            Events on {{ selectedDateEvents[0] ? toLocalDateString(selectedDateEvents[0].start) : '' }}
          </h3>
          <ul class="mb-4 max-h-60 overflow-y-auto">
            <li
              v-for="event in selectedDateEvents"
              :key="event.id"
              class="p-2 border-b cursor-pointer hover:bg-gray-100"
              @click="openEventForm(event)"
            >
              {{ event.title }} — {{ event.start.toISOString().slice(11,16) }} to {{ event.end.toISOString().slice(11,16) }}
            </li>
          </ul>
          <div class="flex justify-between">
            <button
              @click="openEventForm(null, selectedDateEvents[0] ? toLocalDateString(selectedDateEvents[0].start) : '')"
              class="px-4 py-2 rounded bg-[#9E122C] text-white hover:bg-[#EE6A43] transition"
            >
              Create New Event
            </button>
            <button
              @click="closeEventListModal"
              class="px-4 py-2 rounded border hover:bg-gray-100 transition"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </transition>

    <!-- Create/Edit modal -->
    <transition name="fade">
      <div v-if="modalOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-lg max-w-md w-full max-h-[90vh] overflow-y-auto p-6 relative" @click.stop>
          <h3 class="text-xl font-semibold mb-4 text-[#9E122C] sticky top-0 bg-white">
            {{ editingEvent ? 'Edit Schedule' : 'Create Schedule' }}
          </h3>

          <form @submit.prevent="saveEvent">
            <label class="block mb-2 font-medium text-gray-700">Name</label>
            <input
              v-model="form.name"
              type="text"
              required
              class="w-full border border-gray-300 rounded px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-[#9E122C]"
              placeholder="Schedule Name"
              autofocus
            />

            <label class="block mb-2 font-medium text-gray-700">Date</label>
            <input
              v-model="form.date"
              type="date"
              required
              class="w-full border border-gray-300 rounded px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-[#9E122C]"
            />

            <label class="block mb-2 font-medium text-gray-700">Start Time</label>
            <input
              v-model="form.startTime"
              type="time"
              required
              class="w-full border border-gray-300 rounded px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-[#9E122C]"
            />

            <label class="block mb-2 font-medium text-gray-700">End Time</label>
            <input
              v-model="form.endTime"
              type="time"
              required
              class="w-full border border-gray-300 rounded px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-[#9E122C]"
            />

            <label class="block mb-2 font-medium text-gray-700">Type</label>
            <select
              v-model="form.type"
              required
              class="w-full border border-gray-300 rounded px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-[#9E122C]"
            >
              <option value="application">Application</option>
              <option value="interview">Interview</option>
              <option value="medical">Medical</option>
              <option value="announcement">Announcement</option>
              <option value="other">Other</option>
            </select>

            <label class="block mb-2 font-medium text-gray-700">Description (Optional)</label>
            <textarea
              v-model="form.description"
              rows="3"
              class="w-full border border-gray-300 rounded px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-[#9E122C]"
              placeholder="Add details about this schedule..."
            ></textarea>

            <label class="block mb-2 font-medium text-gray-700">Location (Optional)</label>
            <input
              v-model="form.location"
              type="text"
              class="w-full border border-gray-300 rounded px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-[#9E122C]"
              placeholder="e.g., Room 101, Building A"
            />

            <button
              type="button"
              class="w-full px-4 py-2 rounded bg-[#9E122C] text-white hover:bg-[#EE6A43] transition mb-4"
              @click="openEventForm(null, form.date)"
            >
              Create New Event on this Date
            </button>

            <!-- Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 justify-between sticky bottom-0 bg-white pt-4 border-t">
              <div v-if="editingEvent" class="w-full sm:w-auto">
                <button
                  type="button"
                  @click="confirmDelete"
                  class="w-full px-5 py-2 rounded bg-red-600 text-white hover:bg-red-700 transition"
                >
                  Delete
                </button>
              </div>

              <div class="flex gap-3 justify-end flex-grow w-full">
                <button
                  type="button"
                  @click="closeModal"
                  class="flex-1 sm:flex-none px-5 py-2 rounded border hover:bg-gray-100 transition"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  class="flex-1 sm:flex-none px-5 py-2 rounded bg-[#9E122C] text-white hover:bg-[#EE6A43] transition"
                >
                  Save
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </transition>
  </AppLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.vuecal__event {
  cursor: pointer;
  transition: background-color 0.2s ease;
}
.vuecal__event:hover {
  background-color: #EE6A43 !important;
}

.vuecal__header,
.vuecal__cell--week-day {
  color: #9E122C !important;
}

.vuecal__cell {
  border-color: #f0e6e6 !important;
}

input:focus {
  outline: none;
  box-shadow: 0 0 0 3px #9E122C88;
  transition: box-shadow 0.3s ease;
}
</style>
