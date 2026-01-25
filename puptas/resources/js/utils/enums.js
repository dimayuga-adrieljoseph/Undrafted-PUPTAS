// Common enum options for Vue components
export const enumOptions = {
  // Application statuses
  applicationStatus: [
    { value: 'draft', label: 'Draft' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'endorsed', label: 'Endorsed' },
    { value: 'accepted', label: 'Accepted' },
    { value: 'returned', label: 'Returned' },
    { value: 'rejected', label: 'Rejected' }
  ],

  // Enrollment statuses
  enrollmentStatus: [
    { value: 'temporary', label: 'Temporary' },
    { value: 'officially_enrolled', label: 'Officially Enrolled' }
  ],

  // ApplicationProcess stages
  processStage: [
    { value: 'submitted', label: 'Submitted' },
    { value: 'evaluator', label: 'Evaluator' },
    { value: 'interview', label: 'Interview' },
    { value: 'medical', label: 'Medical' },
    { value: 'record', label: 'Record' }
  ],

  // ApplicationProcess statuses
  processStatus: [
    { value: 'pending', label: 'Pending' },
    { value: 'reviewed', label: 'Reviewed' },
    { value: 'endorsed', label: 'Endorsed' },
    { value: 'returned', label: 'Returned' },
    { value: 'accepted', label: 'Accepted' },
    { value: 'rejected', label: 'Rejected' }
  ],

  // Schedule types
  scheduleType: [
    { value: 'application_deadline', label: 'Application Deadline' },
    { value: 'exam_schedule', label: 'Exam Schedule' },
    { value: 'interview_schedule', label: 'Interview Schedule' },
    { value: 'result_release', label: 'Result Release' },
    { value: 'enrollment_period', label: 'Enrollment Period' }
  ],

  // UserFile statuses
  fileStatus: [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'returned', label: 'Returned' },
    { value: 'rejected', label: 'Rejected' }
  ],

  // TestPasser statuses
  testPasserStatus: [
    { value: 'pending', label: 'Pending' },
    { value: 'registered', label: 'Registered' },
    { value: 'inactive', label: 'Inactive' }
  ]
};

// Helper to format decimal values for display
export const formatDecimal = (value, decimals = 2) => {
  if (!value && value !== 0) return '—';
  return parseFloat(value).toFixed(decimals);
};

// Helper to parse decimal input
export const parseDecimal = (value) => {
  if (!value && value !== '0') return null;
  return parseFloat(value);
};

// Helper to format date
export const formatDate = (date) => {
  if (!date) return '—';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

// Helper to format datetime
export const formatDateTime = (datetime) => {
  if (!datetime) return '—';
  return new Date(datetime).toLocaleString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

// Helper to get enum label
export const getEnumLabel = (value, enumArray) => {
  const item = enumArray.find(e => e.value === value);
  return item ? item.label : value;
};
