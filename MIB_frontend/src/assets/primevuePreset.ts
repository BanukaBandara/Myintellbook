import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';

/** Aura re-tinted to the MyIntelliBook design system (see design-system.css). */
const MibPreset = definePreset(Aura, {
    primitive: {
        borderRadius: { none: '0', xs: '4px', sm: '6px', md: '10px', lg: '12px', xl: '16px' },
    },
    semantic: {
        primary: {
            50: '#fff1f2',
            100: '#ffe4e6',
            200: '#fecdd3',
            300: '#fda4af',
            400: '#fb7185',
            500: '#f43f5e',
            600: '#e11d48',
            700: '#be123c',
            800: '#9f1239',
            900: '#881337',
            950: '#4c0519',
        },
        focusRing: { width: '2px', style: 'solid', color: '{primary.600}', offset: '2px', shadow: 'none' },
        formField: { borderRadius: '10px', paddingX: '0.8rem', paddingY: '0.55rem' },
        colorScheme: {
            light: {
                surface: {
                    0: '#ffffff',
                    50: '#f8fafc',
                    100: '#f1f5f9',
                    200: '#e2e8f0',
                    300: '#cbd5e1',
                    400: '#94a3b8',
                    500: '#64748b',
                    600: '#475569',
                    700: '#334155',
                    800: '#1e293b',
                    900: '#0f172a',
                    950: '#020617',
                },
                primary: {
                    color: '{primary.600}',
                    contrastColor: '#ffffff',
                    hoverColor: '{primary.700}',
                    activeColor: '{primary.800}',
                },
                highlight: {
                    background: '{primary.50}',
                    focusBackground: '{primary.100}',
                    color: '{primary.700}',
                    focusColor: '{primary.800}',
                },
                text: { color: '{surface.900}', mutedColor: '{surface.500}', hoverMutedColor: '{surface.700}' },
                content: { borderColor: '{surface.200}' },
                formField: { borderColor: '{surface.300}', hoverBorderColor: '{surface.400}', focusBorderColor: '{primary.400}' },
            },
        },
    },
});

export default MibPreset;
