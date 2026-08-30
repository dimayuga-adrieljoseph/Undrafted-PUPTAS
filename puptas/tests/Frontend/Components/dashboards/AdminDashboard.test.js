import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import AdminDashboard from '@/Pages/Dashboard/Admin.vue';

// Stub LineChart to avoid Chart.js registration
vi.mock('vue-chart-3', () => ({
  LineChart: {
    name: 'LineChart',
    template: '<div class="chart-stub"></div>',
    props: ['chartData', 'options']
  }
}));

describe('AdminDashboard', () => {
  const defaultProps = {
    allUsers: [],
    summary: {
      total: 100,
      accepted: 50,
      pending: 30,
      returned: 20
    },
    stageSummary: {
      document_evaluator: 10,
      grade_evaluator: 15,
      interviewer: 20,
      medical: 25,
      records: 10,
      enrollment: 20
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
    }
  };

  const createWrapper = (props = {}) => {
    return mount(AdminDashboard, {
      props: { ...defaultProps, ...props },
      global: {
        stubs: {
          Head: true,
          AppLayout: {
            template: '<div><slot /></div>'
          },
          BlurText: {
            template: '<div>{{ text }}</div>',
            props: ['text']
          },
          UserDetailsModal: true,
          LineChart: {
            template: '<div class="chart-stub"></div>'
          }
        },
        mocks: {
          route: vi.fn((name) => `/${name}`)
        }
      }
    });
  };

  describe('Component Mounting', () => {
    it('mounts without errors', () => {
      const wrapper = createWrapper();
      expect(wrapper.exists()).toBe(true);
    });

    it('renders the dashboard header', () => {
      const wrapper = createWrapper();
      expect(wrapper.text()).toContain('Admissions Dashboard');
    });
  });

  describe('Summary Statistics', () => {
    it('displays total applications count', () => {
      const wrapper = createWrapper();
      const summaryItems = wrapper.vm.summaryItems;
      
      const totalItem = summaryItems.find(item => item.label === 'Total Applications');
      expect(totalItem).toBeDefined();
      expect(totalItem.value).toBe(100);
    });

    it('displays stage summary counts', () => {
      const wrapper = createWrapper();
      const summaryItems = wrapper.vm.summaryItems;
      
      expect(summaryItems.find(item => item.label === 'Document Evaluation').value).toBe(10);
      expect(summaryItems.find(item => item.label === 'Grade Evaluation').value).toBe(15);
      expect(summaryItems.find(item => item.label === 'For Interview').value).toBe(20);
    });

    it('calculates percentages correctly', () => {
      const wrapper = createWrapper();
      const summaryItems = wrapper.vm.summaryItems;
      
      const docEvalItem = summaryItems.find(item => item.label === 'Document Evaluation');
      expect(docEvalItem.percentage).toBe(10); // 10/100 * 100 = 10%
    });
  });

  describe('Chart Data Preparation', () => {
    it('prepares chart dataset with correct structure', () => {
      const wrapper = createWrapper();
      const chartDataset = wrapper.vm.chartDataset;
      
      expect(chartDataset.labels).toEqual(['Week 1', 'Week 2', 'Week 3']);
      expect(chartDataset.datasets).toHaveLength(3);
    });

    it('includes accepted, pending, and returned datasets', () => {
      const wrapper = createWrapper();
      const chartDataset = wrapper.vm.chartDataset;
      
      const labels = chartDataset.datasets.map(ds => ds.label);
      expect(labels).toContain('Accepted');
      expect(labels).toContain('Pending');
      expect(labels).toContain('Returned');
    });

    it('handles empty chart data gracefully', () => {
      const wrapper = createWrapper({
        chartData: { submitted: [], accepted: [], returned: [], labels: [] }
      });
      
      const chartDataset = wrapper.vm.chartDataset;
      expect(chartDataset.labels).toEqual([]);
      expect(chartDataset.datasets[0].data).toEqual([]);
    });
  });

  describe('User Filtering and Pagination', () => {
    beforeEach(() => {
      vi.clearAllMocks();
    });

    it('displays all users when no search query', () => {
      const users = [
        { id: 1, firstname: 'John', lastname: 'Doe', email: 'john@test.com', application: { status: 'submitted' } },
        { id: 2, firstname: 'Jane', lastname: 'Smith', email: 'jane@test.com', application: { status: 'accepted' } }
      ];
      
      const wrapper = createWrapper({ allUsers: users });
      expect(wrapper.vm.filteredUsers).toHaveLength(2);
    });

    it('filters users by name', async () => {
      const users = [
        { id: 1, firstname: 'John', lastname: 'Doe', email: 'john@test.com', application: { status: 'submitted' } },
        { id: 2, firstname: 'Jane', lastname: 'Smith', email: 'jane@test.com', application: { status: 'accepted' } }
      ];
      
      const wrapper = createWrapper({ allUsers: users });
      wrapper.vm.searchQuery = 'john';
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.filteredUsers).toHaveLength(1);
      expect(wrapper.vm.filteredUsers[0].firstname).toBe('John');
    });

    it('filters users by email', async () => {
      const users = [
        { id: 1, firstname: 'John', lastname: 'Doe', email: 'john@test.com', application: { status: 'submitted' } },
        { id: 2, firstname: 'Jane', lastname: 'Smith', email: 'jane@test.com', application: { status: 'accepted' } }
      ];
      
      const wrapper = createWrapper({ allUsers: users });
      wrapper.vm.searchQuery = 'jane@test';
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.filteredUsers).toHaveLength(1);
      expect(wrapper.vm.filteredUsers[0].email).toBe('jane@test.com');
    });
  });

  describe('Status Styling', () => {
    it('returns correct class for accepted status', () => {
      const wrapper = createWrapper();
      const statusClass = wrapper.vm.getStatusClass('accepted');
      expect(statusClass).toContain('green');
    });

    it('returns correct class for submitted status', () => {
      const wrapper = createWrapper();
      const statusClass = wrapper.vm.getStatusClass('submitted');
      expect(statusClass).toContain('yellow');
    });

    it('returns correct class for returned status', () => {
      const wrapper = createWrapper();
      const statusClass = wrapper.vm.getStatusClass('returned');
      expect(statusClass).toContain('red');
    });
  });

  describe('Date Formatting', () => {
    it('formats date correctly', () => {
      const wrapper = createWrapper();
      const formatted = wrapper.vm.formatDate('2024-01-15T10:30:00');
      // Check that it contains key date components (locale-independent)
      expect(formatted).toContain('2024');
      expect(formatted).toContain('15');
    });

    it('returns em dash for null date', () => {
      const wrapper = createWrapper();
      const formatted = wrapper.vm.formatDate(null);
      expect(formatted).toBe('—');
    });
  });
});
