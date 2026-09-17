import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import ApplicantDashboard from '@/Pages/Dashboard/Applicant.vue';

describe('ApplicantDashboard', () => {
  const defaultProps = {
    user: {
      id: 1,
      firstname: 'John',
      lastname: 'Doe',
      email: 'john@test.com'
    },
    gradeUrl: null,
    canDownloadSlip: false,
    canDownloadF137: false
  };

  const createWrapper = (props = {}) => {
    return mount(ApplicantDashboard, {
      props: { ...defaultProps, ...props },
      global: {
        stubs: {
          Head: true,
          ApplicantLayout: {
            template: '<div><slot /></div>'
          },
          BlurText: {
            template: '<div>{{ text }}</div>',
            props: ['text']
          },
          ApplicationReviewModal: true
        },
        mocks: {
          route: vi.fn((name) => `/${name}`),
          axios: {
            get: vi.fn(() => Promise.resolve({
              data: {
                uploadedFiles: {},
                status: 'draft',
                enrollment_status: '',
                processes: [],
                show_medical_redirect: false,
                show_f137_button: false,
                show_cor_upload: false,
                requires_promissory_note: false,
                requires_guidance_office: false,
                requires_admission_office: false,
                has_grades: false
              }
            }))
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
  });

  describe('Enrollment Status Display', () => {
    it('displays draft status correctly', async () => {
      const wrapper = createWrapper();
      await wrapper.vm.$nextTick();
      
      const enrollmentInfo = wrapper.vm.enrollmentInfo;
      expect(enrollmentInfo.badgeLabel).toBe('Application Draft');
      expect(enrollmentInfo.statusTag).toBe('Not Yet Submitted');
    });

    it('displays officially enrolled status', async () => {
      const wrapper = createWrapper();
      wrapper.vm.enrollmentStatus = 'officially_enrolled';
      await wrapper.vm.$nextTick();
      
      const enrollmentInfo = wrapper.vm.enrollmentInfo;
      expect(enrollmentInfo.badgeLabel).toBe('Officially Enrolled');
      expect(enrollmentInfo.label).toBe('Officially Enrolled Iskolar');
    });

    it('displays temporarily enrolled status', async () => {
      const wrapper = createWrapper();
      wrapper.vm.enrollmentStatus = 'temporary';
      await wrapper.vm.$nextTick();
      
      const enrollmentInfo = wrapper.vm.enrollmentInfo;
      expect(enrollmentInfo.badgeLabel).toBe('Temporarily Enrolled');
    });

    it('displays waitlisted status', async () => {
      const wrapper = createWrapper();
      wrapper.vm.enrollmentStatus = 'waitlisted';
      await wrapper.vm.$nextTick();
      
      const enrollmentInfo = wrapper.vm.enrollmentInfo;
      expect(enrollmentInfo.badgeLabel).toBe('Waitlisted');
      expect(enrollmentInfo.statusTag).toBe('Queue Position Active');
    });
  });

  describe('Application Status Display', () => {
    it('displays submitted status', async () => {
      const wrapper = createWrapper();
      wrapper.vm.applicationStatus = 'submitted';
      await wrapper.vm.$nextTick();
      
      const enrollmentInfo = wrapper.vm.enrollmentInfo;
      expect(enrollmentInfo.badgeLabel).toBe('Under Review');
    });

    it('displays returned status', async () => {
      const wrapper = createWrapper();
      wrapper.vm.applicationStatus = 'returned';
      await wrapper.vm.$nextTick();
      
      const enrollmentInfo = wrapper.vm.enrollmentInfo;
      expect(enrollmentInfo.badgeLabel).toBe('Action Required');
      expect(enrollmentInfo.nextStep).toBe('Re-upload Returned Documents');
    });

    it('displays accepted status', async () => {
      const wrapper = createWrapper();
      wrapper.vm.applicationStatus = 'accepted';
      await wrapper.vm.$nextTick();
      
      const enrollmentInfo = wrapper.vm.enrollmentInfo;
      expect(enrollmentInfo.badgeLabel).toBe('Accepted');
    });

    it('displays rejected status', async () => {
      const wrapper = createWrapper();
      wrapper.vm.applicationStatus = 'rejected';
      await wrapper.vm.$nextTick();
      
      const enrollmentInfo = wrapper.vm.enrollmentInfo;
      expect(enrollmentInfo.badgeLabel).toBe('Rejected');
      expect(enrollmentInfo.label).toBe('Application Not Accepted');
    });
  });

  describe('Document Upload Progress', () => {
    it('calculates upload progress correctly', async () => {
      const wrapper = createWrapper();
      
      wrapper.vm.fileStatuses = {
        file10Front: { url: 'test.jpg', status: 'completed' },
        file10: { url: 'test2.jpg', status: 'completed' },
        file11Front: null,
        file11: null
      };
      await wrapper.vm.$nextTick();
      
      // 2 out of 4 uploaded = 50%
      expect(wrapper.vm.uploadProgressPercentage).toBe(50);
    });

    it('identifies when all documents are uploaded', async () => {
      const wrapper = createWrapper();
      
      wrapper.vm.fileStatuses = {
        file10Front: { url: 'test.jpg', status: 'completed' },
        file10: { url: 'test2.jpg', status: 'completed' }
      };
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.allDocumentsUploaded).toBe(true);
    });

    it('identifies when documents are missing', async () => {
      const wrapper = createWrapper();
      
      wrapper.vm.fileStatuses = {
        file10Front: { url: 'test.jpg', status: 'completed' },
        file10: null
      };
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.allDocumentsUploaded).toBe(false);
    });
  });

  describe('Pipeline Timeline', () => {
    it('displays all pipeline stages', async () => {
      const wrapper = createWrapper();
      await wrapper.vm.$nextTick();
      
      const steps = wrapper.vm.timelineSteps;
      expect(steps).toHaveLength(5);
      
      const stageLabels = steps.map(s => s.label);
      expect(stageLabels).toContain('Document Evaluator');
      expect(stageLabels).toContain('Grade Evaluator');
      expect(stageLabels).toContain('Interviewer');
      expect(stageLabels).toContain('Medical');
      expect(stageLabels).toContain('Registrar');
    });

    it('marks completed stages correctly', async () => {
      const wrapper = createWrapper();
      
      wrapper.vm.applicationProcesses = [
        { stage: 'document_evaluator', status: 'completed', action: 'passed' },
        { stage: 'grade_evaluator', status: 'in_progress' }
      ];
      await wrapper.vm.$nextTick();
      
      const steps = wrapper.vm.timelineSteps;
      const docEval = steps.find(s => s.key === 'document_evaluator');
      const gradeEval = steps.find(s => s.key === 'grade_evaluator');
      
      expect(docEval.status).toBe('completed');
      expect(docEval.isPast).toBe(true);
      expect(gradeEval.status).toBe('in_progress');
      expect(gradeEval.isCurrent).toBe(true);
    });
  });

  describe('File Formatting', () => {
    it('formats file keys correctly', () => {
      const wrapper = createWrapper();
      
      expect(wrapper.vm.formatKey('file10Front')).toBe('Grade 10 Report Card (Front)');
      expect(wrapper.vm.formatKey('file10')).toBe('Grade 10 Report Card (Back)');
      expect(wrapper.vm.formatKey('file11Front')).toBe('Grade 11 Report Card (Front)');
    });
  });

  describe('Grade Edit Permissions', () => {
    it('allows grade editing when status is returned from grade evaluator', async () => {
      const wrapper = createWrapper();
      
      wrapper.vm.applicationStatus = 'returned';
      wrapper.vm.applicationProcesses = [
        { stage: 'grade_evaluator', status: 'returned', created_at: new Date().toISOString() }
      ];
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.canEditGrades).toBe(true);
    });

    it('does not allow grade editing when returned from document evaluator', async () => {
      const wrapper = createWrapper();
      
      wrapper.vm.applicationStatus = 'returned';
      wrapper.vm.applicationProcesses = [
        { stage: 'document_evaluator', status: 'returned', created_at: new Date().toISOString() }
      ];
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.canEditGrades).toBe(false);
    });
  });

  describe('Download Permissions', () => {
    it('enables slip download when grades exist and application submitted', async () => {
      const wrapper = createWrapper({
        gradeUrl: '/grades/123',
        canDownloadSlip: false
      });
      
      wrapper.vm.applicationStatus = 'submitted';
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.canDownloadSlipReactive).toBe(true);
    });

    it('disables slip download for draft applications', async () => {
      const wrapper = createWrapper({
        gradeUrl: '/grades/123'
      });
      
      wrapper.vm.applicationStatus = 'draft';
      await wrapper.vm.$nextTick();
      
      expect(wrapper.vm.canDownloadSlipReactive).toBe(false);
    });
  });
});
