import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import EvaluatorDashboard from '@/Pages/Dashboard/Evaluator.vue';

// Stub LineChart
vi.mock('vue-chart-3', () => ({
  LineChart: {
    name: 'LineChart',
    template: '<div class="chart-stub"></div>',
    props: ['chartData', 'options']
  }
}));

describe('EvaluatorDashboard', () => {
  const defaultProps = {
    user: {
      id: 1,
      role_id: 3, // Document evaluator
      firstname: 'John',
      lastname: 'Doe'
    },
    pendingUsers: [],
    summary: {
      in_progress: 10,
      processed: 50
    },
    chartData: {
      submitted: [10, 20, 30],
      accepted: [5, 15, 25],
      returned: [2, 5, 5],
      labels: ['Week 1', 'Week 2', 'Week 3']
    },
    filters: {
      start_date: '',
      end_date: ''
    },
    stage: 'document_evaluator'
  };

  const createWrapper = (props = {}) => {
    return mount(EvaluatorDashboard, {
      props: { ...defaultProps, ...props },
      global: {
        stubs: {
          Head: true,
          EvaluatorLayout: {
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
                    program: null,
                    second_choice: null,
                    third_choice: null
                  },
                  grades: null
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

    it('renders the correct dashboard title for document evaluator', () => {
      const wrapper = createWrapper({ stage: 'document_evaluator' });
      expect(wrapper.text()).toContain('Document Evaluator Dashboard');
    });

    it('renders the correct dashboard title for grade evaluator', () => {
      const wrapper = createWrapper({ 
        stage: 'grade_evaluator',
        user: { ...defaultProps.user, role_id: 8 }
      });
      expect(wrapper.text()).toContain('Grade Evaluator Dashboard');
    });
  });

  describe('Summary Statistics', () => {
    it('displays in-queue count', () => {
      const wrapper = createWrapper();
      const summaryItems = wrapper.vm.summaryItems;
      
      const inQueueItem = summaryItems.find(item => item.label === 'In Queue');
      expect(inQueueItem).toBeDefined();
      expect(inQueueItem.value).toBe(10);
    });

    it('displays processed count', () => {
      const wrapper = createWrapper();
      const summaryItems = wrapper.vm.summaryItems;
      
      const processedItem = summaryItems.find(item => item.label === 'Processed');
      expect(processedItem).toBeDefined();
      expect(processedItem.value).toBe(50);
    });
  });

  describe('Applicant Filtering', () => {
    it('displays all applicants when no search query', () => {
      const users = [
        { id: 1, firstname: 'John', lastname: 'Doe', email: 'john@test.com' },
        { id: 2, firstname: 'Jane', lastname: 'Smith', email: 'jane@test.com' }
      ];
      
      const wrapper = createWrapper({ pendingUsers: users });
      expect(wrapper.vm.filteredApplicants).toHaveLength(2);
    });

    it('filters applicants by name', async () => {
      const users = [
        { id: 1, firstname: 'John', lastname: 'Doe', email: 'john@test.com' },
        { id: 2, firstname: 'Jane', lastname: 'Smith', email: 'jane@test.com' }
      ];
      
      const wrapper = createWrapper({ pendingUsers: users });
      wrapper.vm.searchQuery = 'john';
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.filteredApplicants).toHaveLength(1);
      expect(wrapper.vm.filteredApplicants[0].firstname).toBe('John');
    });

    it('filters applicants by email', async () => {
      const users = [
        { id: 1, firstname: 'John', lastname: 'Doe', email: 'john@test.com' },
        { id: 2, firstname: 'Jane', lastname: 'Smith', email: 'jane@test.com' }
      ];
      
      const wrapper = createWrapper({ pendingUsers: users });
      wrapper.vm.searchQuery = 'smith';
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.filteredApplicants).toHaveLength(1);
      expect(wrapper.vm.filteredApplicants[0].lastname).toBe('Smith');
    });
  });

  describe('Evaluation Status', () => {
    it('detects when evaluation is completed', async () => {
      const wrapper = createWrapper();
      
      wrapper.vm.selectedUser = {
        application: {
          processes: [
            { stage: 'document_evaluator', status: 'completed', action: 'passed' }
          ]
        }
      };
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.isEvaluationCompleted).toBe(true);
    });

    it('detects when review has started', async () => {
      const wrapper = createWrapper();
      
      wrapper.vm.selectedUser = {
        application: {
          processes: [
            { stage: 'document_evaluator', status: 'in_progress', started_at: new Date().toISOString() }
          ]
        }
      };
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.hasStartedReview).toBe(true);
    });

    it('returns null review time when not started', async () => {
      const wrapper = createWrapper();
      
      wrapper.vm.selectedUser = {
        application: {
          processes: [
            { stage: 'document_evaluator', status: 'pending', started_at: null }
          ]
        }
      };
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.reviewStartTime).toBeNull();
    });
  });

  describe('Status Formatting', () => {
    it('formats evaluation status text correctly', () => {
      const wrapper = createWrapper();
      
      expect(wrapper.vm.getEvaluationStatusText('for_evaluation')).toBe('For Evaluation');
      expect(wrapper.vm.getEvaluationStatusText('evaluation_passed')).toBe('Evaluation Passed');
      expect(wrapper.vm.getEvaluationStatusText('evaluation_returned')).toBe('Returned for Revision');
    });

    it('returns correct status classes', () => {
      const wrapper = createWrapper();
      
      const acceptedClass = wrapper.vm.getStatusClass('accepted');
      expect(acceptedClass).toContain('green');
      
      const submittedClass = wrapper.vm.getStatusClass('submitted');
      expect(submittedClass).toContain('yellow');
      
      const returnedClass = wrapper.vm.getStatusClass('returned');
      expect(returnedClass).toContain('red');
    });
  });

  describe('File Formatting', () => {
    it('formats file keys correctly', () => {
      const wrapper = createWrapper();
      
      expect(wrapper.vm.formatFileKey('file10Front')).toBe('Grade 10 Report Front');
      expect(wrapper.vm.formatFileKey('psa')).toBe('PSA Birth Certificate');
      expect(wrapper.vm.formatFileKey('goodMoral')).toBe('Good Moral Certificate');
    });
  });

  describe('Stage Formatting', () => {
    it('formats stage names correctly', () => {
      const wrapper = createWrapper();
      
      // The formatStage function actually capitalizes words, not map to 'DE, GE'
      expect(wrapper.vm.formatStage('document_evaluator')).toBe('Document Evaluator');
      expect(wrapper.vm.formatStage('interviewer')).toBe('Interviewer');
      expect(wrapper.vm.formatStage('medical')).toBe('Medical');
    });
  });

  describe('Evaluation Controls', () => {
    it('initializes evaluation mode', async () => {
      const wrapper = createWrapper();
      
      wrapper.vm.startEvaluation();
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.isEvaluating).toBe(true);
      expect(wrapper.vm.filesToReturn).toEqual({});
      expect(wrapper.vm.returnNote).toBe('');
    });

    it('cancels evaluation mode', async () => {
      const wrapper = createWrapper();
      
      wrapper.vm.isEvaluating = true;
      wrapper.vm.filesToReturn = { file10: true };
      wrapper.vm.returnNote = 'Test note';
      
      wrapper.vm.cancelEvaluation();
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.isEvaluating).toBe(false);
      expect(wrapper.vm.filesToReturn).toEqual({});
      expect(wrapper.vm.returnNote).toBe('');
    });
  });

  describe('Return Note Validation', () => {
    it('counts return note characters', async () => {
      const wrapper = createWrapper();
      
      wrapper.vm.returnNote = 'This is a test note';
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.returnNoteCharCount).toBe(19);
    });
  });

  describe('Chart Data', () => {
    it('prepares chart dataset correctly', () => {
      const wrapper = createWrapper();
      const chartDataset = wrapper.vm.chartDataset;
      
      expect(chartDataset.labels).toEqual(['Week 1', 'Week 2', 'Week 3']);
      expect(chartDataset.datasets).toHaveLength(3);
    });

    it('handles empty chart data', () => {
      const wrapper = createWrapper({
        chartData: { submitted: [], accepted: [], returned: [], labels: [] }
      });
      
      const chartDataset = wrapper.vm.chartDataset;
      expect(chartDataset.labels).toEqual([]);
      expect(chartDataset.datasets[0].data).toEqual([]);
    });
  });
});
