<template>
    <div class="navbar-user-trigger d-flex justify-content-center align-items-center cursor-pointer gap-2" @click="toggle">
        <Avatar :image="(basicInfo.profile_image)? basicInfo.profile_image : userPng" shape="circle" class="m-0"/>
        <span class="d-flex flex-column text-start">
            <strong class="navbar-user-name">{{ basicInfo.full_name || 'My account' }}</strong>
        <span style="font-size:12px;" class="opacity-50 d-flex flex-row align-items-center">
            Me
            <i class="pi pi-chevron-down ms-1" style="font-size: 0.8rem;"></i>
        </span>
        </span>
    </div>
    <TieredMenu ref="menu" id="overlay_menu" :model="sidebar" :popup="true" class="mib-user-menu">
        <!-- User Profile Section -->
        <template #start>
            <div class="mib-menu-header">
                <div class="mib-menu-identity">
                    <Avatar
                        :image="basicInfo.profile_image ? basicInfo.profile_image : userPng"
                        shape="circle"
                        class="mib-menu-avatar"
                    />
                    <div class="mib-menu-identity-text">
                        <span class="mib-menu-name">{{ basicInfo.full_name || 'My account' }}</span>
                        <span class="mib-menu-subtitle">
                            {{ [basicInfo.profession?.company, basicInfo.profession?.location].filter(Boolean).join(' · ') || 'Member' }}
                        </span>
                    </div>
                </div>

                <!-- View Profile Button -->
                <button type="button" class="mib-menu-view-profile" @click="router.push('/profile')">
                    View Profile
                </button>
            </div>
        </template>

        <!-- Menu Items -->
        <template #item="{ item, props, hasSubmenu }">
            <a v-bind="props.action" class="mib-menu-row">
                <component :is="menuIcon(item.label)" v-if="menuIcon(item.label)" :size="16" :stroke-width="1.75" class="mib-menu-icon" aria-hidden="true" />
                <i v-else :class="item.icon" class="mib-menu-icon" aria-hidden="true"></i>
                <span class="mib-menu-label">{{ item.label }}</span>
                <ChevronRight v-if="hasSubmenu" :size="14" :stroke-width="1.75" class="mib-menu-chevron" aria-hidden="true" />
            </a>
        </template>

        <!-- Logout Section -->
        <template #end>
            <div class="mib-menu-footer">
                <button type="button" class="mib-menu-row mib-menu-signout" @click="logOut">
                    <LogOut :size="16" :stroke-width="1.75" class="mib-menu-icon" aria-hidden="true" />
                    <span class="mib-menu-label">Sign Out</span>
                </button>
            </div>
        </template>
    </TieredMenu>
</template>
<script setup lang="ts">
import Avatar from 'primevue/avatar';
import {
    Briefcase, ChevronRight, Gavel, GraduationCap, House, Inbox, LockKeyhole, LogOut,
    Settings, ShieldCheck, User, UserPen, Users, type LucideIcon,
} from 'lucide-vue-next';

import TieredMenu from 'primevue/tieredmenu';
import { ref, computed} from 'vue';
import { useRouter } from 'vue-router';
import { useUserStore } from '@/stores/User/userStore';
import { useUserProfile } from '@/stores/User/userProfile';
import userPng from '@/assets/user.png';

import { onMounted } from 'vue';
import { useTribunalStore } from '@/stores/tribunal';

const menu = ref<InstanceType<typeof TieredMenu> | null>(null);
const router = useRouter(); 
const userStore = useUserStore();
const userProfile = useUserProfile();
const tribunalStore = useTribunalStore();
const basicInfo = computed(()=> userProfile.getSummaryDetails);
const mobileMenuVisible = ref(false);

const isAdmin = computed(() => {
    if (tribunalStore.capabilities?.is_admin_reviewer) {
        return true;
    }
    try {
        const stored = localStorage.getItem('userData');
        if (stored) {
            const u = JSON.parse(stored);
            if (u?.is_admin) return true;
        }
    } catch {
        // ignore
    }
    return false;
});

onMounted(async () => {
    if (localStorage.getItem('userToken') && !tribunalStore.capabilities) {
        await tribunalStore.fetchCapabilities();
    }
});

const sidebar = computed(() => {
    const items: Array<{
        label: string;
        icon: string;
        command?: () => void;
        items?: Array<{ label: string; icon: string; command: () => void }>;
    }> = [
        {
            label: 'Home',
            icon: 'pi pi-home',
            command: () => { router.push('/home'); } 
        },
        {
            label: 'Profile',
            icon: 'pi pi-user',
            command: () => { router.push('/profile'); }
        },
        {
            label: 'Users',
            icon: 'pi pi-users',
            command: () => { router.push('/profiles'); }
        },
        {
            label: 'Exam',
            icon: 'pi pi-bars',
            command: () => { router.push('/exam-module'); }
        },
    ];

    if (isAdmin.value) {
        items.push({
            label: 'Professional Verification Reviews',
            icon: 'pi pi-shield',
            command: () => { router.push('/admin/professional-verifications'); }
        });
    }

    if (tribunalStore.capabilities?.adjudicator?.eligible) {
        const pendingCount = tribunalStore.capabilities?.adjudicator?.pending_assignments || 0;
        items.push({
            label: pendingCount > 0 ? `Adjudicator Assignments (${pendingCount})` : 'Adjudicator Assignments',
            icon: 'pi pi-building-columns',
            command: () => { router.push('/tribunal/jury'); }
        });
    }

    if (tribunalStore.capabilities?.representative?.eligible) {
        const pendingCount = tribunalStore.capabilities?.representative?.pending_requests || 0;
        items.push({
            label: pendingCount > 0 ? `Representation Requests (${pendingCount})` : 'Representation Requests',
            icon: 'pi pi-inbox',
            command: () => { router.push('/tribunal/representation-requests'); }
        });
        items.push({
            label: 'My Represented Cases',
            icon: 'pi pi-briefcase',
            command: () => { router.push('/tribunal/represented-cases'); }
        });
    }

    items.push({
        label: 'Settings',
        icon: 'pi pi-cog',
        items: [
            {
                label: 'Login Informations',
                icon: 'pi pi-user-edit',
                command: () => { router.push('/accountSettings/loginInformations'); }
            },
            {
                label: 'Privacy Center',
                icon: 'pi pi-trash',
                command: () => { router.push('/accountSettings/privacyInformations'); }
            }
        ]
    });

    return items;
});

const logOut = async() =>{
    let result = await userStore.logOut();
    if(result.code == 200){
        mobileMenuVisible.value = false;
         router.push('/')
    }
}

// Presentation only: Lucide icon per menu label (labels with live counts match by prefix).
const MENU_ICONS: Array<[string, LucideIcon]> = [
    ['Home', House],
    ['Profile', User],
    ['Users', Users],
    ['Exam', GraduationCap],
    ['Settings', Settings],
    ['Professional Verification Reviews', ShieldCheck],
    ['Adjudicator Assignments', Gavel],
    ['Representation Requests', Inbox],
    ['My Represented Cases', Briefcase],
    ['Login Informations', UserPen],
    ['Privacy Center', LockKeyhole],
];

const menuIcon = (label: unknown): LucideIcon | undefined =>
    typeof label === 'string'
        ? MENU_ICONS.find(([prefix]) => label === prefix || label.startsWith(`${prefix} (`))?.[1]
        : undefined;

const toggle = (event:any) => {
    if(menu.value)
    {
        menu.value.toggle(event);
    }
};
</script>
<style>
/* The menu overlay is teleported to <body>, so these rules are global but namespaced under .mib-user-menu. */
.mib-user-menu.p-tieredmenu {
    min-width: 220px;
    padding: 8px;
    z-index: 50;
    background: rgb(255 255 255 / 95%);
    border: 1px solid rgb(226 232 240 / 80%);
    border-radius: 16px;
    box-shadow: 0 20px 25px -5px rgb(15 23 42 / 10%), 0 8px 10px -6px rgb(15 23 42 / 10%);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    animation: mib-menu-in 150ms ease-out;
}

@keyframes mib-menu-in {
    from { opacity: 0; transform: translateY(-4px) scale(.98); }
    to { opacity: 1; transform: none; }
}

.mib-user-menu .p-tieredmenu-root-list,
.mib-user-menu .p-tieredmenu-submenu { gap: 2px; padding: 0; }

/* Nested "Settings" flyout gets the same card treatment. */
.mib-user-menu .p-tieredmenu-submenu {
    min-width: 200px;
    padding: 6px;
    background: rgb(255 255 255 / 97%);
    border: 1px solid rgb(226 232 240 / 80%);
    border-radius: 14px;
    box-shadow: 0 20px 25px -5px rgb(15 23 42 / 10%), 0 8px 10px -6px rgb(15 23 42 / 10%);
}

.mib-user-menu .p-tieredmenu-item-content,
.mib-user-menu .p-tieredmenu-item:not(.p-disabled) > .p-tieredmenu-item-content:hover {
    color: inherit;
    background: transparent !important;
    border-radius: 12px;
}

/* ---- Header ---- */
.mib-menu-header {
    margin-bottom: 8px;
    padding: 8px 12px 12px;
    border-bottom: 1px solid #f1f5f9;
}

.mib-menu-identity { display: flex; align-items: center; gap: 10px; min-width: 0; }

.mib-menu-avatar.p-avatar {
    flex: 0 0 40px;
    width: 40px;
    height: 40px;
    box-shadow: 0 0 0 2px #fff, 0 0 0 3px #e2e8f0;
}

.mib-menu-identity-text { display: flex; flex-direction: column; min-width: 0; line-height: 1.3; }

.mib-menu-name,
.mib-menu-subtitle { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.mib-menu-name { color: #0f172a; font-size: 14px; font-weight: 600; letter-spacing: -.01em; }
.mib-menu-subtitle { color: #64748b; font-size: 12px; }

.mib-menu-view-profile {
    width: 100%;
    margin-top: 8px;
    padding: 6px 0;
    color: var(--ds-primary, #e11d48);
    font-size: 12px;
    font-weight: 600;
    text-align: center;
    background: var(--ds-primary-soft, #fff1f2);
    border: 0;
    border-radius: 8px;
    transition: background-color 150ms ease;
}

.mib-menu-view-profile:hover { background: var(--ds-primary-100, #ffe4e6); }

/* ---- Rows ---- */
.mib-menu-row {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 8px 12px;
    color: #475569;
    font-size: 12px;
    font-weight: 500;
    text-align: left;
    text-decoration: none;
    background: transparent;
    border: 0;
    border-radius: 12px;
    cursor: pointer;
    transition: color 150ms ease, background-color 150ms ease;
}

.mib-menu-row:hover,
.mib-user-menu .p-tieredmenu-item.p-focus > .p-tieredmenu-item-content > .mib-menu-row,
.mib-user-menu .p-tieredmenu-item-active > .p-tieredmenu-item-content > .mib-menu-row {
    color: #0f172a;
    background: #f8fafc;
}

.mib-menu-icon { flex: 0 0 16px; color: #94a3b8; font-size: 14px; transition: color 150ms ease; }
.mib-menu-row:hover .mib-menu-icon,
.mib-user-menu .p-tieredmenu-item-active > .p-tieredmenu-item-content .mib-menu-icon { color: #0f172a; }

.mib-menu-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mib-menu-chevron { flex: 0 0 auto; color: #94a3b8; }

/* ---- Sign out ---- */
.mib-menu-footer {
    margin: 4px 0;
    padding-top: 4px;
    border-top: 1px solid #f1f5f9;
}

.mib-menu-signout.mib-menu-row,
.mib-menu-signout .mib-menu-icon { color: var(--ds-primary, #e11d48); }
.mib-menu-signout.mib-menu-row { font-weight: 600; }
.mib-menu-signout.mib-menu-row:hover { color: var(--ds-primary, #e11d48); background: var(--ds-primary-soft, #fff1f2); }

.mib-menu-row:focus-visible,
.mib-menu-view-profile:focus-visible { outline: 2px solid var(--ds-primary, #e11d48); outline-offset: 1px; }

@media (prefers-reduced-motion: reduce) {
    .mib-user-menu.p-tieredmenu { animation: none; }
}
</style>
