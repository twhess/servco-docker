import { ref, computed, nextTick } from 'vue';

export interface ValidationRule {
  validate: (value: unknown) => boolean;
  message: string;
}

export interface FieldValidation {
  value: unknown;
  rules: ValidationRule[];
  touched: boolean;
  error: string | null;
}

export function useFormValidation() {
  const fields = ref<Map<string, FieldValidation>>(new Map());
  const validationMode = ref<'blur' | 'submit'>('blur');
  const isSubmitting = ref(false);

  /**
   * Register a field for validation
   */
  const registerField = (name: string, rules: ValidationRule[] = []) => {
    fields.value.set(name, {
      value: null,
      rules,
      touched: false,
      error: null,
    });
  };

  /**
   * Update field value
   */
  const updateField = (name: string, value: unknown) => {
    const field = fields.value.get(name);
    if (field) {
      field.value = value;
      // Validate on change if field was already touched or if we're in submit mode
      if (field.touched || validationMode.value === 'submit') {
        validateField(name);
      }
    }
  };

  /**
   * Mark field as touched (on blur)
   */
  const touchField = (name: string) => {
    const field = fields.value.get(name);
    if (field) {
      field.touched = true;
      validateField(name);
    }
  };

  /**
   * Validate a single field
   */
  const validateField = (name: string): boolean => {
    const field = fields.value.get(name);
    if (!field) return true;

    // Run all validation rules
    for (const rule of field.rules) {
      if (!rule.validate(field.value)) {
        field.error = rule.message;
        return false;
      }
    }

    field.error = null;
    return true;
  };

  /**
   * Validate all fields
   */
  const validateAll = (): boolean => {
    let isValid = true;

    fields.value.forEach((field, name) => {
      const fieldValid = validateField(name);
      if (!fieldValid) {
        isValid = false;
        // Mark as touched so error shows
        field.touched = true;
      }
    });

    return isValid;
  };

  /**
   * Scroll to first error field
   */
  const scrollToFirstError = async () => {
    await nextTick();

    // Find first field with error
    let firstErrorField: string | null = null;
    fields.value.forEach((field, name) => {
      if (field.error && !firstErrorField) {
        firstErrorField = name;
      }
    });

    if (firstErrorField) {
      // Find the element with data-field-name attribute
      const element = document.querySelector(`[data-field-name="${String(firstErrorField)}"]`);
      if (element) {
        element.scrollIntoView({
          behavior: 'smooth',
          block: 'center'
        });

        // Focus the input if it exists
        const input = element.querySelector('input, select, textarea');
        if (input instanceof HTMLElement) {
          input.focus();
        }
      }
    }
  };

  /**
   * Submit handler that validates and scrolls to errors
   */
  const handleSubmit = async (callback: () => void | Promise<void>) => {
    isSubmitting.value = true;
    validationMode.value = 'submit';

    const isValid = validateAll();

    if (isValid) {
      try {
        await callback();
      } finally {
        isSubmitting.value = false;
      }
    } else {
      await scrollToFirstError();
      isSubmitting.value = false;
    }
  };

  /**
   * Reset all validation
   */
  const reset = () => {
    fields.value.forEach((field) => {
      field.value = null;
      field.touched = false;
      field.error = null;
    });
    validationMode.value = 'blur';
    isSubmitting.value = false;
  };

  /**
   * Get error for a field
   */
  const getError = (name: string): string | null => {
    const field = fields.value.get(name);
    return field?.error || null;
  };

  /**
   * Check if field has error
   */
  const hasError = (name: string): boolean => {
    const field = fields.value.get(name);
    return !!(field?.touched && field?.error);
  };

  /**
   * Check if form is valid
   */
  const isValid = computed(() => {
    let valid = true;
    fields.value.forEach((field) => {
      if (field.error) valid = false;
    });
    return valid;
  });

  return {
    registerField,
    updateField,
    touchField,
    validateField,
    validateAll,
    scrollToFirstError,
    handleSubmit,
    reset,
    getError,
    hasError,
    isValid,
    isSubmitting,
  };
}

/**
 * Common validation rules
 */
export const validationRules = {
  required: (message = 'This field is required'): ValidationRule => ({
    validate: (value) => {
      if (typeof value === 'string') return value.trim().length > 0;
      if (Array.isArray(value)) return value.length > 0;
      return value !== null && value !== undefined;
    },
    message,
  }),

  email: (message = 'Please enter a valid email address'): ValidationRule => ({
    validate: (value) => {
      if (!value) return true; // Use with required() if field is required
      if (typeof value !== 'string') return false;
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(value);
    },
    message,
  }),

  phone: (message = 'Please enter a valid phone number'): ValidationRule => ({
    validate: (value) => {
      if (!value) return true;
      const phoneRegex = /^[\d\s\-()+]+$/;
      return typeof value === 'string' && phoneRegex.test(value) && value.replace(/\D/g, '').length >= 10;
    },
    message,
  }),

  minLength: (min: number, message?: string): ValidationRule => ({
    validate: (value) => {
      if (!value) return true;
      if (typeof value === 'string' || Array.isArray(value)) {
        return value.length >= min;
      }
      return false;
    },
    message: message || `Must be at least ${min} characters`,
  }),

  maxLength: (max: number, message?: string): ValidationRule => ({
    validate: (value) => {
      if (!value) return true;
      if (typeof value === 'string' || Array.isArray(value)) {
        return value.length <= max;
      }
      return false;
    },
    message: message || `Must be no more than ${max} characters`,
  }),

  min: (min: number, message?: string): ValidationRule => ({
    validate: (value) => {
      if (value === null || value === undefined || value === '') return true;
      return Number(value) >= min;
    },
    message: message || `Must be at least ${min}`,
  }),

  max: (max: number, message?: string): ValidationRule => ({
    validate: (value) => {
      if (value === null || value === undefined || value === '') return true;
      return Number(value) <= max;
    },
    message: message || `Must be no more than ${max}`,
  }),

  custom: (validationFn: (value: unknown) => boolean, message: string): ValidationRule => ({
    validate: validationFn,
    message,
  }),
};
