import { onMounted } from 'vue';
import { setCssVar } from 'quasar';

interface ColorScheme {
  name: string;
  colors: {
    primary: string;
    secondary: string;
    accent: string;
    positive: string;
    negative: string;
    warning: string;
  };
}

export function useTheme() {
  function loadSavedTheme() {
    const savedScheme = localStorage.getItem('color_scheme');
    if (savedScheme) {
      try {
        const scheme: ColorScheme = JSON.parse(savedScheme);

        setCssVar('primary', scheme.colors.primary);
        setCssVar('secondary', scheme.colors.secondary);
        setCssVar('accent', scheme.colors.accent);
        setCssVar('positive', scheme.colors.positive);
        setCssVar('negative', scheme.colors.negative);
        setCssVar('warning', scheme.colors.warning);
      } catch (e) {
        console.error('Failed to load saved theme:', e);
      }
    }
  }

  onMounted(() => {
    loadSavedTheme();
  });

  return {
    loadSavedTheme,
  };
}
