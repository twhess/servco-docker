import { ref, watch, onMounted } from 'vue';

export interface DraftOptions {
  key: string;
  debounceMs?: number;
  excludeFields?: string[];
}

export function useDraftState<T extends Record<string, any>>(
  formData: T,
  options: DraftOptions
) {
  const { key, debounceMs = 500, excludeFields = [] } = options;
  const hasDraft = ref(false);
  const draftTimestamp = ref<Date | null>(null);
  let saveTimeout: ReturnType<typeof setTimeout> | null = null;

  const storageKey = `draft_${key}`;

  /**
   * Save draft to localStorage
   */
  const saveDraft = () => {
    try {
      const dataToSave: Record<string, any> = {};

      // Only save fields that aren't excluded
      Object.keys(formData).forEach((field) => {
        if (!excludeFields.includes(field)) {
          dataToSave[field] = formData[field];
        }
      });

      const draft = {
        data: dataToSave,
        timestamp: new Date().toISOString(),
      };

      localStorage.setItem(storageKey, JSON.stringify(draft));
      hasDraft.value = true;
      draftTimestamp.value = new Date(draft.timestamp);
    } catch (error) {
      console.error('Failed to save draft:', error);
    }
  };

  /**
   * Save draft with debounce
   */
  const debouncedSave = () => {
    if (saveTimeout) {
      clearTimeout(saveTimeout);
    }

    saveTimeout = setTimeout(() => {
      saveDraft();
    }, debounceMs);
  };

  /**
   * Load draft from localStorage
   */
  const loadDraft = (): boolean => {
    try {
      const stored = localStorage.getItem(storageKey);
      if (!stored) return false;

      const draft = JSON.parse(stored);

      // Restore form data
      Object.keys(draft.data).forEach((field) => {
        if (field in formData) {
          (formData as any)[field] = draft.data[field];
        }
      });

      hasDraft.value = true;
      draftTimestamp.value = new Date(draft.timestamp);
      return true;
    } catch (error) {
      console.error('Failed to load draft:', error);
      return false;
    }
  };

  /**
   * Clear draft from localStorage
   */
  const clearDraft = () => {
    try {
      localStorage.removeItem(storageKey);
      hasDraft.value = false;
      draftTimestamp.value = null;
    } catch (error) {
      console.error('Failed to clear draft:', error);
    }
  };

  /**
   * Check if draft exists
   */
  const checkForDraft = (): boolean => {
    try {
      const stored = localStorage.getItem(storageKey);
      if (stored) {
        const draft = JSON.parse(stored);
        hasDraft.value = true;
        draftTimestamp.value = new Date(draft.timestamp);
        return true;
      }
      return false;
    } catch (error) {
      return false;
    }
  };

  /**
   * Get draft age in minutes
   */
  const getDraftAge = (): number | null => {
    if (!draftTimestamp.value) return null;

    const now = new Date();
    const diff = now.getTime() - draftTimestamp.value.getTime();
    return Math.floor(diff / 60000); // Convert to minutes
  };

  /**
   * Format draft age for display
   */
  const formatDraftAge = (): string => {
    const age = getDraftAge();
    if (age === null) return '';

    if (age < 1) return 'less than a minute ago';
    if (age === 1) return '1 minute ago';
    if (age < 60) return `${age} minutes ago`;

    const hours = Math.floor(age / 60);
    if (hours === 1) return '1 hour ago';
    if (hours < 24) return `${hours} hours ago`;

    const days = Math.floor(hours / 24);
    if (days === 1) return '1 day ago';
    return `${days} days ago`;
  };

  // Watch form data and save drafts automatically
  watch(
    () => formData,
    () => {
      debouncedSave();
    },
    { deep: true }
  );

  // Check for existing draft on mount
  onMounted(() => {
    checkForDraft();
  });

  return {
    hasDraft,
    draftTimestamp,
    saveDraft,
    loadDraft,
    clearDraft,
    checkForDraft,
    getDraftAge,
    formatDraftAge,
  };
}
