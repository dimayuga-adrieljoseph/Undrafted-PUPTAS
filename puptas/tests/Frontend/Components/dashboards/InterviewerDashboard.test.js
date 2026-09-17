import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import InterviewerDashboard from '@/Pages/Dashboard/Interviewer.vue';

// Stub LineChart
vi.mock('vue-chart-3', () => ({
  LineChart: {
    name: 'LineChart',
    template: '<div class="chart-stub"></div>',
    props: ['chartData', 'options']
  }
}));

describe('InterviewerDashboard', () => {
  const defaultProps = {
    user: {
      id: 1,
      firstname: 'John',
      lastname: 'Doe'
    },
    pendingUsers: [],
    assignedPrograms: [],
    summary: {
      in_progress: 15,
      processed: 35
    },
    chartData: {
      submitted: [5, 10, 15],
      accepted: [3, 8, 12],
      returned: [1, 2, 3],
      labels: ['Week 1', 'Week 2', 'Week 3']
    },
    filters: {
      start_date: '',
      end_date: ''
    }
  };

  const createWrapper = (props = {}) => {
    return mount(InterviewerDashboard, {
      props: { ...defaultProps, ...props },
      global: {
        stubs: {
          Head: true,
          InterviewerLayout: {
            template: '<div><slot /></div>'
          },
          BlurText: {
            template: '<div>{{ text }}</div>',
            props: ['text']
          },
          UserDetailsModal: true,
          ChangesConfirmationModal: true,
          LineChart: {
            template: '<div class="chart-stub"></div>'
          }
        },
        mocks: {
          route: vi.fn((name) => `/${name}`),
          axios: {
            get: vi.fn(() => Promise.resolve({
              data: {
                user: {
                  application: {
                    processes: [],
                    program: null
                  },
                  grades: null,
                  unqualified_programs: []
                },
                uploadedFiles: {}
              }
            })),
            post: vi.fn(() => Promise.resolve({ data: { started_at: new Date().toISOString() } }))
          }
        }
      }
    });
  };

  describe('Component Mounting', () => {
    it('mounts without errors', () => {
      const wrapper = createWrapper();
      expect(wrapper.exists()).toBe(true);
    });

    it('renders the dashboard title', () => {
      const wrapper = createWrapper();
      expect(wrapper.text()).toContain('Interviewer Dashboard');
    });
  });

  describe('Summary Statistics', () => {
    it('displays in-queue count', () => {
      const wrapper = createWrapper();
      const summaryItems = wrapper.vm.summaryItems;
      
      const inQueueItem = summaryItems.find(item => item.label === 'In Queue');
      expect(inQueueItem).toBeDefined();
      expect(inQueueItem.value).toBe(15);
    });

    it('displays processed count', () => {
      const wrapper = createWrapper();
      const summaryItems = wrapper.vm.summaryItems;
      
      const processedItem = summaryItems.find(item => item.label === 'Processed');
      expect(processedItem).toBeDefined();
      expect(processedItem.value).toBe(35);
    });
  });

  describe('Low Slot Alert', () => {
    it('identifies programs with low slots', () => {
      const programs = [
        { id: 1, code: 'BSCS', name: 'Computer Science', slots: 5 },
        { id: 2, code: 'BSIT', name: 'Information Technology', slots: 50 },
        { id: 3, code: 'BSCE', name: 'Civil Engineering', slots: 8 }
      ];
      
      const wrapper = createWrapper({ assignedPrograms: programs });
      const lowSlotPrograms = wrapper.vm.lowSlotPrograms;
      
      expect(lowSlotPrograms).toHaveLength(2);
      expect(lowSlotPrograms.map(p => p.code)).toContain('BSCS');
      expect(lowSlotPrograms.map(p => p.code)).toContain('BSCE');
    });

    it('shows no alert when all programs have sufficient slots', () => {
      const programs = [
        { id: 1, code: 'BSCS', name: 'Computer Science', slots: 50 },
        { id: 2, code: 'BSIT', name: 'Information Technology', slots: 45 }
      ];
      
      const wrapper = createWrapper({ assignedPrograms: programs });
      expect(wrapper.vm.lowSlotPrograms).toHaveLength(0);
    });
  });

  describe('Applicant Filtering', () => {
    it('displays all applicants when no search query', () => {
      const users = [
        { id: 1, firstname: 'John', lastname: 'Doe', email: 'john@test.com' },
        { id: 2, firstname: 'Jane', lastname: 'Smith', email: 'jane@test.com' }
      ];
      
      const wrapper = createWrapper({ pendingUsers: users });
      expect(wrapper.vm.filteredUsers).toHaveLength(2);
    });

    it('filters applicants by firstname', async () => {
      const users = [
        { id: 1, firstname: 'John', lastname: 'Doe', email: 'john@test.com' },
        { id: 2, firstname: 'Jane', lastname: 'Smith', email: 'jane@test.com' }
      ];
      
      const wrapper = createWrapper({ pendingUsers: users });
      wrapper.vm.searchQuery = 'jane';
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.filteredUsers).toHaveLength(1);
      expect(wrapper.vm.filteredUsers[0].firstname).toBe('Jane');
    });

    it('filters applicants by email', async () => {
      const users = [
        { id: 1, firstname: 'John', lastname: 'Doe', email: 'john@test.com' },
        { id: 2, firstname: 'Jane', lastname: 'Smith', email: 'jane@test.com' }
      ];
      
      const wrapper = createWrapper({ pendingUsers: users });
      wrapper.vm.searchQuery = 'john@test';
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.filteredUsers).toHaveLength(1);
      expect(wrapper.vm.filteredUsers[0].email).toBe('john@test.com');
    });
  });

  describe('Applicant Qualification Check', () => {
    it('marks applicant as qualified when program not in unqualified list', async () => {
      const wrapper = createWrapper();
      
      wrapper.vm.selectedUser = {
        unqualified_programs: [
          { id: 2, code: 'BSIT' },
          { id: 3, code: 'BSCE' }
        ]
      };
      wrapper.vm.selectedProgramId = '1';
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.isApplicantQualified).toBe(true);
    });

    it('marks applicant as unqualified when program is in unqualified list', async () => {
      const wrapper = createWrapper();
      
      wrapper.vm.selectedUser = {
        unqualified_programs: [
          { id: 1, code: 'BSCS' },
          { id: 2, code: 'BSIT' }
        ]
      };
      wrapper.vm.selectedProgramId = '1';
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.isApplicantQualified).toBe(false);
    });
  });

  describe('Interview State Management', () => {
    it('tracks interview start time', async () => {
      const wrapper = createWrapper();
      const startTime = new Date().toISOString();
      
      wrapper.vm.interviewStartTime = startTime;
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.interviewStartTime).toBe(startTime);
    });

    it('clears interview state when closing user card', async () => {
      const wrapper = createWrapper();
      
      wrapper.vm.selectedUser = { id: 1 };
      wrapper.vm.selectedProgramId = '5';
      wrapper.vm.interviewStartTime = new Date().toISOString();
      wrapper.vm.interviewNotes = 'Test notes';
      
      wrapper.vm.closeUserCard();
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.selectedUser).toBeNull();
      expect(wrapper.vm.selectedProgramId).toBe('');
      expect(wrapper.vm.interviewStartTime).toBeNull();
      expect(wrapper.vm.interviewNotes).toBe('');
    });
  });

  describe('File Formatting', () => {
    it('formats file keys correctly', () => {
      const wrapper = createWrapper();
      
      expect(wrapper.vm.formatFileKey('file10Front')).toBe('Grade 10 Report Front');
      expect(wrapper.vm.formatFileKey('psa')).toBe('PSA Birth Certificate');
      expect(wrapper.vm.formatFileKey('photo2x2')).toBe('2x2 Photo');
    });
  });

  describe('Stage Formatting', () => {
    it('formats stage names correctly', () => {
      const wrapper = createWrapper();
      
      expect(wrapper.vm.formatStage('evaluator')).toBe('DE, GE');
      expect(wrapper.vm.formatStage('interviewer')).toBe('Interviewer');
      expect(wrapper.vm.formatStage('medical')).toBe('Medical');
    });
  });

  describe('Status Styling', () => {
    it('returns correct class for accepted status', () => {
      const wrapper = createWrapper();
      const statusClass = wrapper.vm.getStatusClass('accepted');
      expect(statusClass).toContain('green');
    });

    it('returns correct class for pending status', () => {
      const wrapper = createWrapper();
      const statusClass = wrapper.vm.getStatusClass('pending');
      expect(statusClass).toContain('yellow');
    });

    it('returns correct class for returned status', () => {
      const wrapper = createWrapper();
      const statusClass = wrapper.vm.getStatusClass('returned');
      expect(statusClass).toContain('red');
    });
  });

  describe('Chart Data', () => {
    it('prepares chart dataset correctly', () => {
      const wrapper = createWrapper();
      const chartDataset = wrapper.vm.chartDataset;
      
      expect(chartDataset.labels).toEqual(['Week 1', 'Week 2', 'Week 3']);
      expect(chartDataset.datasets).toHaveLength(3);
    });

    it('uses fallback for missing chart labels', () => {
      const wrapper = createWrapper({
        chartData: {
          submitted: [5, 10],
          accepted: [3, 8],
          returned: [1, 2],
          years: ['2023', '2024']
        }
      });
      
      const chartDataset = wrapper.vm.chartDataset;
      expect(chartDataset.labels).toEqual(['2023', '2024']);
    });
  });

  describe('Date Formatting', () => {
    it('formats dates correctly', () => {
      const wrapper = createWrapper();
      const formatted = wrapper.vm.formatDate('2024-01-15T10:30:00');
      expect(formatted).toMatch(/Jan/);
      expect(formatted).toMatch(/15/);
      expect(formatted).toMatch(/2024/);
    });

    it('returns em dash for null dates', () => {
      const wrapper = createWrapper();
      const formatted = wrapper.vm.formatDate(null);
      expect(formatted).toBe('—');
    });
  });
});
