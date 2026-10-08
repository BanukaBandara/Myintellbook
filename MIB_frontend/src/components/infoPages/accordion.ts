import type { InjectionKey } from 'vue';

export type AccordionApi = {
    register: (id: string) => void;
    unregister: (id: string) => void;
    isOpen: (id: string) => boolean;
    toggle: (id: string) => void;
};

export const ACCORDION_KEY: InjectionKey<AccordionApi> = Symbol('info-accordion');
