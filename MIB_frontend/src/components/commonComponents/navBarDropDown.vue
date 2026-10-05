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
    <TieredMenu ref="menu" id="overlay_menu" :model="sidebar" :popup="true">
        <!-- User Profile Section -->
        <template #start>
            <div class="d-flex flex-column align-items-center p-3 border-bottom bg-light rounded-top">
            <!-- Profile Avatar & Info -->
            <div class="d-flex align-items-center w-100">
                <Avatar 
                :image="basicInfo.profile_image ? basicInfo.profile_image : userPng" 
                shape="circle" 
                style="width:45px;height:45px;" 
                class="me-2 border border-2"
                />
                <div class="d-flex flex-column text-truncate">
                <span class="fw-bold">{{ basicInfo.full_name }}</span>
                <small class="text-muted">
                    {{ basicInfo.profession ? basicInfo.profession.company : '' }}
                </small>
                <small class="text-secondary">
                    {{ basicInfo.profession ? basicInfo.profession.location : '' }}
                </small>
                </div>
            </div>

            <!-- View Profile Button -->
            <Button  
                label="View Profile" 
                variant="outlined" 
                severity="info" 
                size="small" 
                class="w-100 mt-3 rounded-3 fw-semibold" 
                @click="router.push('/profile')"
            />
            </div>
        </template>

        <!-- Menu Items -->
        <template #item="{ item, props }">
            <a v-bind="props.action" class="d-flex align-items-center px-3 py-2 text-decoration-none w-100">
            <i :class="item.icon" class="me-2"></i>
            <span>{{ item.label }}</span>
            </a>
        </template>

        <!-- Logout Section -->
        <template #end>
            <div class="p-2 border-top bg-light">
            <Button  
                label="Sign Out" 
                variant="text" 
                severity="danger" 
                size="small" 
                class="w-100 rounded-3 fw-semibold" 
                @click="logOut"
            />
            </div>
        </template>
    </TieredMenu>
</template>
<script setup lang="ts">
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';

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
            command: () => { router.push('/exams'); }
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

const toggle = (event:any) => {
    if(menu.value)
    {
        menu.value.toggle(event);
    }
};
</script>