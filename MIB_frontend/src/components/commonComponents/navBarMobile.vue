<template>
    <div class="d-flex d-xl-none justify-content-between align-items-center p-2 bg-white border-bottom-1 border" v-if="!routeParam">
         <img :src="webIcon" alt="Logo" class="logo" style="width:45px; " />
          <navBarSearch />
         <Button icon="pi pi-bars"
        severity="secondary"
        rounded text size="small"
        class="d-flex d-sm-flex d-xl-none"
        @click="toggleMobileMenu" />
    </div>

    <div v-if="mobileMenuVisible" class="mobile-dropdown d-xl-none bg-white border-top p-3 w-100">
        <ul class="list-unstyled mb-2">
            <li v-for="(item, index) in items" :key="'m-' + index" class="mb-2 d-flex align-items-center gap-2" @click="item.command">
            <i :class="`pi ${item.icon}`"></i>
            <span>{{ item.label }}</span>
            </li>
        </ul>

        <div class="d-flex align-items-center gap-2 mb-2">
            <Avatar :image="basicInfo.profile_image || userPng" shape="circle" />
            <div class="d-flex flex-column">
            <strong>{{ basicInfo.full_name }}</strong>
            <small class="text-muted">{{ basicInfo.profession?.company }}</small>
            </div>
        </div>
        <Button label="Sign Out" severity="danger" class="w-100" @click="logOut" />
    </div>
</template>
<script setup lang="ts">
import { computed, ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Button from 'primevue/button';
import Avatar from 'primevue/avatar'; 
import { useUserStore } from '@/stores/User/userStore';
import { useUserProfile } from '@/stores/User/userProfile';
import Popover from 'primevue/popover';
import userPng from '@/assets/user.png';
import navBarSearch from './navBarSearch.vue';
import webIcon from '@/assets/webIcon.jpeg';

import { useTribunalStore } from '@/stores/tribunal';

const route = useRoute();
const router = useRouter();
const userProfile = useUserProfile();
const tribunalStore = useTribunalStore();
const routeParam = computed(()=>route.meta.hideNavBar);
const userStore = useUserStore();
const basicInfo = computed(()=> userProfile.getSummaryDetails);
const op = ref< InstanceType<typeof Popover> | null>(null);
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

const items = computed(() => {
    const list = [
        {
            label: 'Home',
            icon: 'pi-home',
            command: () => {router.push('/home');} 
        },
        {
            label: 'Profile',
            icon: 'pi-user',
            command: () => {router.push('/profile');}
        },
        {
            label: 'Top Users',
            icon: 'pi-users',
            command: () => {router.push('/topUsers');}
        },
        {
            label: 'learn',
            icon: `pi-book`,
            command: () => {router.push('/learn');}
        },
        {
            label: 'Exam',
            icon: 'pi-bars',
            command: () => {router.push('/exams');}
        },
        {
            label: 'Score',
            icon: 'pi-gauge',
            command: () => {router.push('/scores');}
        },
    ];

    if (isAdmin.value) {
        list.push({
            label: 'Professional Verification Reviews',
            icon: 'pi-shield',
            command: () => { router.push('/admin/professional-verifications'); }
        });
        list.push({
            label: 'Jury Panel Management',
            icon: 'pi-users',
            command: () => { router.push('/admin/tribunal/jury-panels'); }
        });
    }

    if (tribunalStore.capabilities?.adjudicator?.eligible) {
        const pendingCount = tribunalStore.capabilities?.adjudicator?.pending_assignments || 0;
        list.push({
            label: pendingCount > 0 ? `Adjudicator Assignments (${pendingCount})` : 'Adjudicator Assignments',
            icon: 'pi-building-columns',
            command: () => { router.push('/tribunal/jury'); }
        });
    }

    if (tribunalStore.capabilities?.representative?.eligible) {
        const pendingCount = tribunalStore.capabilities?.representative?.pending_requests || 0;
        list.push({
            label: pendingCount > 0 ? `Representation Requests (${pendingCount})` : 'Representation Requests',
            icon: 'pi-inbox',
            command: () => { router.push('/tribunal/representation-requests'); }
        });
        list.push({
            label: 'My Represented Cases',
            icon: 'pi-briefcase',
            command: () => { router.push('/tribunal/represented-cases'); }
        });
    }

    list.push({
        label: 'Your suggestions',
        icon: 'pi-bell',
        command: (event?: any) => { showNotifications(event); }
    });

    return list;
});

const toggleMobileMenu = () => {
  mobileMenuVisible.value = !mobileMenuVisible.value
}

const logOut = async() =>{
    let result = await userStore.logOut();
    if(result.code == 200){
        mobileMenuVisible.value = false;
         router.push('/')
    }
}

const showNotifications = (event:any) =>
{
    if(op.value)
    {
        op.value.toggle(event);
    }
}
</script>
