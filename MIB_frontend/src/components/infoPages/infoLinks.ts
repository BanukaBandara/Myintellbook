import type { RouteLocationNormalizedLoaded } from 'vue-router';

export type InfoLink = {
    key: string;
    label: string;
    to: string;
    icon: string; // bootstrap-icons class
    variant?: 'primary' | 'secondary';
    isActive: (route: RouteLocationNormalizedLoaded) => boolean;
};

const exact = (path: string) => (route: RouteLocationNormalizedLoaded) => route.path === path;

/** Links in the profile sidebar, shared with the compact mobile nav on the info pages. */
export const INFO_LINKS: InfoLink[] = [
    { key: 'about', label: 'About', to: '/about_site', icon: 'bi-info-circle', isActive: exact('/about_site') },
    { key: 'glossary', label: 'Glossary', to: '/glossary', icon: 'bi-book', isActive: exact('/glossary') },
    { key: 'terms', label: 'Terms & Conditions', to: '/terms_conditions', icon: 'bi-file-text', isActive: exact('/terms_conditions') },
    { key: 'how', label: 'See How It Works', to: '/how_it_works', icon: 'bi-question-circle', isActive: exact('/how_it_works') },
    { key: 'privacy', label: 'Privacy Policy', to: '/privacy_policy', icon: 'bi-lock', isActive: exact('/privacy_policy') },
    { key: 'encryption', label: 'Encryption Details', to: '/encription_details', icon: 'bi-shield-check', isActive: exact('/encription_details') },
    { key: 'retention', label: 'Data Retention Rule', to: '/data_retention_rules', icon: 'bi-database', isActive: exact('/data_retention_rules') },
    { key: 'scoring', label: 'Scoring Breakdown', to: '/scoring_breakdown', icon: 'bi-graph-up-arrow', isActive: exact('/scoring_breakdown') },
];

export const INFO_ACTIONS: InfoLink[] = [
    {
        key: 'report',
        label: 'Report Misconduct',
        to: '/submit_case',
        icon: 'bi-shield-exclamation',
        variant: 'primary',
        isActive: (route) =>
            route.path === '/submit_case' ||
            route.path === '/submit_case/' ||
            route.path === '/submit_case/report-misconduct' ||
            route.query.tab === 'report-misconduct' ||
            route.path.startsWith('/internal-tribunal'),
    },
    {
        key: 'case',
        label: 'Submit a Case',
        to: '/submit_case/external',
        icon: 'bi-send',
        variant: 'secondary',
        isActive: (route) =>
            route.path === '/submit_case/external' ||
            route.query.tab === 'external',
    },
];
