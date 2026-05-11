import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

/**
 * Composable for lazy loading applicant documents
 * 
 * Features:
 * - Intersection Observer for viewport-based loading
 * - Batch loading for multiple documents
 * - Individual document loading
 * - Loading state management
 * - Error handling
 */
export function useLazyLoadDocuments(userId, initialFiles = {}) {
    const loadedFiles = ref({});
    const loadingFiles = ref({});
    const errors = ref({});
    const observers = ref(new Map());

    /**
     * Load a single document
     */
    const loadDocument = async (fileType) => {
        if (loadedFiles.value[fileType] || loadingFiles.value[fileType]) {
            return; // Already loaded or loading
        }

        loadingFiles.value[fileType] = true;

        try {
            const response = await axios.get(`/api/lazy-load/document/${userId}/${fileType}`);
            
            if (response.data.url) {
                loadedFiles.value[fileType] = {
                    url: response.data.url,
                    status: response.data.status,
                    comment: response.data.comment,
                    isImage: response.data.isImage,
                    originalName: response.data.originalName,
                };
            } else {
                loadedFiles.value[fileType] = null;
            }
        } catch (error) {
            console.error(`Failed to load document ${fileType}:`, error);
            
            // Provide user-friendly error message
            const errorMessage = error.response?.data?.message || 'Failed to load document. Please try again.';
            errors.value[fileType] = errorMessage;
            loadedFiles.value[fileType] = null;
        } finally {
            loadingFiles.value[fileType] = false;
        }
    };

    /**
     * Load multiple documents in batch
     */
    const loadDocumentsBatch = async (fileTypes) => {
        const typesToLoad = fileTypes.filter(
            type => !loadedFiles.value[type] && !loadingFiles.value[type]
        );

        if (typesToLoad.length === 0) {
            return;
        }

        // Mark all as loading
        typesToLoad.forEach(type => {
            loadingFiles.value[type] = true;
        });

        try {
            const response = await axios.post(`/api/lazy-load/documents-batch/${userId}`, {
                fileTypes: typesToLoad,
            });

            const files = response.data.files;

            Object.keys(files).forEach(fileType => {
                const fileData = files[fileType];
                if (fileData.url) {
                    loadedFiles.value[fileType] = {
                        url: fileData.url,
                        status: fileData.status,
                        comment: fileData.comment,
                        isImage: fileData.isImage,
                        originalName: fileData.originalName,
                    };
                } else {
                    loadedFiles.value[fileType] = null;
                }
            });
        } catch (error) {
            console.error('Failed to load documents batch:', error);
            
            // Provide user-friendly error message
            const errorMessage = error.response?.data?.message || 'Failed to load documents. Please try again.';
            
            typesToLoad.forEach(type => {
                errors.value[type] = errorMessage;
                loadedFiles.value[type] = null;
            });
        } finally {
            typesToLoad.forEach(type => {
                loadingFiles.value[type] = false;
            });
        }
    };

    /**
     * Setup intersection observer for an element
     * Loads document when element enters viewport
     */
    const observeElement = (element, fileType) => {
        if (!element || observers.value.has(fileType)) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        loadDocument(fileType);
                        observer.disconnect();
                        observers.value.delete(fileType);
                    }
                });
            },
            {
                rootMargin: '50px', // Start loading 50px before entering viewport
                threshold: 0.01,
            }
        );

        observer.observe(element);
        observers.value.set(fileType, observer);
    };

    /**
     * Get file data (loaded or metadata)
     */
    const getFile = (fileType) => {
        if (loadedFiles.value[fileType]) {
            return loadedFiles.value[fileType];
        }

        // Return metadata from initial files
        if (initialFiles[fileType]) {
            return {
                ...initialFiles[fileType],
                url: null, // Not loaded yet
                loading: loadingFiles.value[fileType] || false,
            };
        }

        return null;
    };

    /**
     * Check if file is loaded
     */
    const isLoaded = (fileType) => {
        return !!loadedFiles.value[fileType]?.url;
    };

    /**
     * Check if file is loading
     */
    const isLoading = (fileType) => {
        return !!loadingFiles.value[fileType];
    };

    /**
     * Get file URL
     */
    const getFileUrl = (fileType) => {
        return loadedFiles.value[fileType]?.url || null;
    };

    /**
     * Preload all visible documents
     */
    const preloadVisibleDocuments = () => {
        const fileTypes = Object.keys(initialFiles).filter(
            type => initialFiles[type]?.hasFile
        );

        if (fileTypes.length > 0) {
            loadDocumentsBatch(fileTypes);
        }
    };

    /**
     * Cleanup observers on unmount
     */
    onUnmounted(() => {
        observers.value.forEach(observer => observer.disconnect());
        observers.value.clear();
    });

    return {
        loadedFiles,
        loadingFiles,
        errors,
        loadDocument,
        loadDocumentsBatch,
        observeElement,
        getFile,
        isLoaded,
        isLoading,
        getFileUrl,
        preloadVisibleDocuments,
    };
}

/**
 * Composable for lazy loading grades
 */
export function useLazyLoadGrades(userId, endpoint) {
    const grades = ref(null);
    const loading = ref(false);
    const error = ref(null);

    const loadGrades = async () => {
        if (grades.value || loading.value) {
            return;
        }

        loading.value = true;
        error.value = null;

        try {
            const response = await axios.get(endpoint || `/api/lazy-load/grades/${userId}`);
            grades.value = response.data.grades;
        } catch (err) {
            console.error('Failed to load grades:', err);
            
            // Provide user-friendly error message
            error.value = err.response?.data?.message || 'Failed to load grades. Please try again.';
        } finally {
            loading.value = false;
        }
    };

    return {
        grades,
        loading,
        error,
        loadGrades,
    };
}
