<template>
    <div class="profile-edit">
        <aside class="profile-edit-sidebar" aria-label="Edit profile sections">
            <RouterLink to="/profile" class="back-link">
                <ArrowLeft :size="16" aria-hidden="true" />
                View Profile
            </RouterLink>

            <p class="nav-heading">Profile Information</p>
            <nav class="nav-list">
                <RouterLink
                    v-for="item in profileItems"
                    :key="item.to"
                    :to="item.to"
                    class="nav-item"
                    :class="{ active: isActive(item.to) }"
                    :aria-current="isActive(item.to) ? 'page' : undefined"
                >
                    <component :is="item.icon" :size="18" aria-hidden="true" />
                    <span>{{ item.label }}</span>
                </RouterLink>
            </nav>

            <p class="nav-heading">Photos</p>
            <nav class="nav-list">
                <RouterLink
                    v-for="item in photoItems"
                    :key="item.to"
                    :to="item.to"
                    class="nav-item"
                    :class="{ active: isActive(item.to) }"
                    :aria-current="isActive(item.to) ? 'page' : undefined"
                >
                    <component :is="item.icon" :size="18" aria-hidden="true" />
                    <span>{{ item.label }}</span>
                </RouterLink>
            </nav>
        </aside>

        <!-- Mobile: the same sections as a horizontally scrollable strip -->
        <nav class="profile-edit-tabs" aria-label="Edit profile sections">
            <RouterLink
                v-for="item in allItems"
                :key="item.to"
                :to="item.to"
                class="tab-item"
                :class="{ active: isActive(item.to) }"
                :aria-current="isActive(item.to) ? 'page' : undefined"
            >
                <component :is="item.icon" :size="16" aria-hidden="true" />
                <span>{{ item.label }}</span>
            </RouterLink>
        </nav>

        <main class="profile-edit-content">
            <router-view />
        </main>
    </div>
</template>
<script lang="ts" setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { ArrowLeft, Award, Briefcase, Camera, GraduationCap, Image, User } from 'lucide-vue-next';

const route = useRoute();

const profileItems = [
    { label: 'General Information', icon: User, to: '/profileEdit/generalInfo' },
    { label: 'Work Experience', icon: Briefcase, to: '/profileEdit/workExperience' },
    { label: 'Education Information', icon: GraduationCap, to: '/profileEdit/educationInfo' },
    { label: 'Skills Information', icon: Award, to: '/profileEdit/skillsInfo' },
];

const photoItems = [
    { label: 'Profile Photo', icon: Camera, to: '/profileEdit/addProfileImage' },
    { label: 'Cover Photo', icon: Image, to: '/profileEdit/addCoverImage' },
];

const allItems = [...profileItems, ...photoItems];

// Sections with an :id/:slug (editing an existing entry) still highlight their parent item.
const currentPath = computed(() => route.path);
const isActive = (to: string) => currentPath.value === to || currentPath.value.startsWith(`${to}/`);
</script>
<style scoped>
.profile-edit {
    display: grid;
    grid-template-columns: 264px minmax(0, 1fr);
    gap: 24px;
    width: 100%;
    max-width: 1120px;
    margin: 0 auto;
    padding: 24px 16px 48px;
    align-items: start;
}

/* ---------- Sidebar ---------- */
.profile-edit-sidebar {
    position: sticky;
    top: 88px;
    padding: 16px 12px;
    background: #fff;
    border: 1px solid rgba(226, 232, 240, .8);
    border-radius: 16px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.back-link {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 4px 12px;
    padding: 8px 12px;
    color: #475569;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    transition: color .15s ease, background-color .15s ease, border-color .15s ease;
}

.back-link:hover { color: #0f172a; background: #f8fafc; border-color: #cbd5e1; }

.nav-heading {
    margin: 16px 16px 6px;
    color: #94a3b8;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.nav-list { display: flex; flex-direction: column; gap: 2px; }

.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    color: #475569; /* slate-600 */
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    border-right: 2px solid transparent;
    border-radius: 12px;
    transition: all .15s ease;
}

.nav-item:hover { color: #0f172a; background: #f8fafc; }

.nav-item.active {
    color: #e11d48; /* rose-600 */
    font-weight: 600;
    background: #fff1f2; /* rose-50 */
    border-right-color: #e11d48;
}

.nav-item:focus-visible, .tab-item:focus-visible, .back-link:focus-visible {
    outline: 2px solid #fda4af;
    outline-offset: 2px;
}

/* ---------- Mobile tab strip ---------- */
.profile-edit-tabs { display: none; }

@media (max-width: 767.98px) {
    .profile-edit { grid-template-columns: minmax(0, 1fr); gap: 16px; padding-top: 16px; }
    .profile-edit-sidebar { display: none; }

    .profile-edit-tabs {
        display: flex;
        gap: 6px;
        margin: 0 -16px;
        padding: 0 16px 4px;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .profile-edit-tabs::-webkit-scrollbar { display: none; }

    .tab-item {
        display: inline-flex;
        flex: 0 0 auto;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        color: #475569;
        font-size: 13px;
        font-weight: 500;
        white-space: nowrap;
        text-decoration: none;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        transition: all .15s ease;
    }

    .tab-item.active { color: #e11d48; font-weight: 600; background: #fff1f2; border-color: #fecdd3; }
}

/* ---------- Shared form styling for every /profileEdit/* section ---------- */
.profile-edit-content {
    min-width: 0;

    /* PrimeVue reads these tokens where each property is used, so InputText, Select and
       DatePicker all pick up the same field style in every state. */
    --p-inputtext-background: rgba(248, 250, 252, .5);
    --p-inputtext-disabled-background: #f1f5f9;
    --p-inputtext-disabled-color: #94a3b8;
    --p-inputtext-border-color: #e2e8f0;
    --p-inputtext-hover-border-color: #cbd5e1;
    --p-inputtext-focus-border-color: #f43f5e;
    --p-inputtext-color: #0f172a;
    --p-inputtext-placeholder-color: #94a3b8;
    --p-inputtext-border-radius: 12px;
    --p-inputtext-padding-x: 16px;
    --p-inputtext-padding-y: 12px;
    --p-inputtext-sm-padding-x: 16px;
    --p-inputtext-sm-padding-y: 12px;
    --p-inputtext-sm-font-size: 14px;
    --p-inputtext-shadow: none;
    --p-inputtext-focus-ring-width: 0;
    --p-inputtext-focus-ring-shadow: 0 0 0 3px rgba(244, 63, 94, .2);

    --p-select-background: rgba(248, 250, 252, .5);
    --p-select-disabled-background: #f1f5f9;
    --p-select-border-color: #e2e8f0;
    --p-select-hover-border-color: #cbd5e1;
    --p-select-focus-border-color: #f43f5e;
    --p-select-color: #0f172a;
    --p-select-placeholder-color: #94a3b8;
    --p-select-dropdown-color: #94a3b8;
    --p-select-border-radius: 12px;
    --p-select-padding-x: 16px;
    --p-select-padding-y: 12px;
    --p-select-sm-padding-x: 16px;
    --p-select-sm-padding-y: 12px;
    --p-select-sm-font-size: 14px;
    --p-select-shadow: none;
    --p-select-focus-ring-width: 0;
    --p-select-focus-ring-shadow: 0 0 0 3px rgba(244, 63, 94, .2);

    --p-floatlabel-position-x: 16px;
    --p-floatlabel-color: #94a3b8;
    --p-floatlabel-active-color: #64748b;
    --p-floatlabel-focus-color: #e11d48;
    --p-floatlabel-active-font-size: 12px;
    --p-floatlabel-on-active-background: #fff;
    --p-floatlabel-on-active-padding: 0 6px;
    --p-floatlabel-on-border-radius: 4px;
}

.profile-edit-content :deep(.p-inputtext),
.profile-edit-content :deep(.p-select-label) {
    font-size: 14px;
    line-height: 1.43;
}

.profile-edit-content :deep(.p-floatlabel label) { font-size: 14px; }

/* Card: bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-6 */
.profile-edit-content :deep(.pe-card) {
    display: flex;
    flex-direction: column;
    gap: 24px;
    margin: 0;
    padding: 24px;
    background: #fff;
    border: 1px solid rgba(226, 232, 240, .8);
    border-radius: 16px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.profile-edit-content :deep(.pe-card-header) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f1f5f9;
}

.profile-edit-content :deep(.pe-title) {
    margin: 0;
    color: #0f172a;
    font-size: 18px;
    font-weight: 600;
    letter-spacing: -.01em;
}

.profile-edit-content :deep(.pe-subtitle) {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 13px;
}

/* Two-column field grid, one column on small screens */
.profile-edit-content :deep(.pe-grid) {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px 16px;
}

.profile-edit-content :deep(.pe-field) {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 6px;
}

.profile-edit-content :deep(.pe-field-label) {
    color: #334155;
    font-size: 13px;
    font-weight: 500;
}

.profile-edit-content :deep(.pe-inline) {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
}

.profile-edit-content :deep(.pe-hint) { margin: 0; color: #64748b; font-size: 13px; }

.profile-edit-content :deep(.pe-actions) {
    display: flex;
    justify-content: flex-end;
    padding-top: 16px;
    border-top: 1px solid #f1f5f9;
}

/* Primary save: bg-rose-600 hover:bg-rose-700 text-white px-6 py-2.5 rounded-xl shadow-sm active:scale-95 */
.profile-edit-content :deep(.p-button.pe-save) {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 24px;
    color: #fff !important;
    font-size: 14px;
    font-weight: 500;
    background: #e11d48 !important;
    border: 1px solid #e11d48 !important;
    border-radius: 12px !important;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .1);
    transition: all .15s ease;
}

.profile-edit-content :deep(.p-button.pe-save:not(:disabled):hover) {
    background: #be123c !important;
    border-color: #be123c !important;
    box-shadow: 0 4px 6px -1px rgba(15, 23, 42, .1), 0 2px 4px -2px rgba(15, 23, 42, .1);
}

.profile-edit-content :deep(.p-button.pe-save:not(:disabled):active) { transform: scale(.95); }
.profile-edit-content :deep(.p-button.pe-save:disabled) { cursor: not-allowed; opacity: .55; }
.profile-edit-content :deep(.p-button.pe-save:focus-visible) { outline: 2px solid #fda4af; outline-offset: 2px; }
.profile-edit-content :deep(.p-button.pe-save label) { margin: 0; color: inherit; font-weight: 500; cursor: inherit; }

.profile-edit-content :deep(.pe-spin) { animation: pe-spin .8s linear infinite; }
@keyframes pe-spin { to { transform: rotate(360deg); } }

.profile-edit-content :deep(.p-button.pe-delete) {
    width: 36px;
    height: 36px;
    padding: 0;
    color: #94a3b8 !important;
    background: transparent !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 10px !important;
    box-shadow: none !important;
    transition: all .15s ease;
}

.profile-edit-content :deep(.p-button.pe-delete:hover) {
    color: #dc2626 !important;
    background: #fef2f2 !important;
    border-color: #fecaca !important;
}

@media (max-width: 575.98px) {
    .profile-edit-content :deep(.pe-card) { padding: 20px; }
    .profile-edit-content :deep(.pe-grid) { grid-template-columns: minmax(0, 1fr); }
    .profile-edit-content :deep(.pe-actions) { justify-content: stretch; }
    .profile-edit-content :deep(.p-button.pe-save) { justify-content: center; width: 100%; }
}

@media (prefers-reduced-motion: reduce) {
    .nav-item, .tab-item, .back-link,
    .profile-edit-content :deep(.p-button.pe-save) { transition: none; }
}
</style>
