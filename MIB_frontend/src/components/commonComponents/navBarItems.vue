<template>
    <ul class="nav-list d-flex align-items-center gap-2 m-0 p-0 list-unstyled w-auto">
        <li v-for="(item, index) in items" :key="index" class="nav-item d-flex flex-column justify-content-center align-items-center" :class="{ 'is-active': isActive(item.label) }" @click="item.command">
            <OverlayBadge value="2" size="small" v-if="item.label == 'Notifications'" :pt="{
                root: 'mx-2',
                badge: 'bg-danger text-white rounded-circle d-flex justify-content-center align-items-center',
                badgeValue: 'text-white'
            }">
                <!-- <Button icon="pi pi-bell" severity="secondary" rounded text size="small" class="d-flex flex-column" @click="showNotifications" > -->
                    <i class="pi pi-bell" style="font-size: 1.2rem;"></i>

                    <!-- </Button> -->
            </OverlayBadge>
            <OverlayBadge :value="String(item.badge)" size="small" v-else-if="item.badge && item.badge > 0" :pt="{
                root: 'mx-1',
                badge: 'bg-danger text-white rounded-circle d-flex justify-content-center align-items-center',
                badgeValue: 'text-white'
            }">
                <i class="pi" :class="item.icon" style="font-size: 1.2rem;"></i>
            </OverlayBadge>
            <i class="pi" v-else :class="item.icon" style="font-size: 1.2rem; margin-right: 0.5rem;"></i>
            <span class="nav-label">{{item.label}}</span>
            <!-- <Button :label="item.label" :icon="item.icon" class="nav-button" @click="item.command" /> -->
        </li>
    </ul>

    <Popover ref="op">
            <div class="w-100">
                <div class="d-flex justify-content-between">
                    <h6 class="fw-semibold">Notifications</h6>
                    <p class="fw-semibold text-danger">Mark all as read</p>
                </div>
                <Notifications />
                <Notifications />
                <Notifications />
            </div>
    </Popover>
</template>
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Popover from 'primevue/popover';
import OverlayBadge from 'primevue/overlaybadge';
import Notifications from '../../components/commonComponents/Notifications.vue';
import { useTribunalStore } from '@/stores/tribunal';

const router = useRouter();
const route = useRoute();
const tribunalStore = useTribunalStore();
const op = ref< InstanceType<typeof Popover> | null>(null);

onMounted(async () => {
    if (localStorage.getItem('userToken') && !tribunalStore.capabilities) {
        await tribunalStore.fetchCapabilities();
    }
});

const items = computed(() => {
    const list: Array<{
        label: string;
        icon: string;
        command: () => void;
        badge?: number;
    }> = [
       {
           label: 'Home',
           icon: 'pi pi-home',
           command: () => {router.push('/home');}
       },
       {
           label: 'Profile',
           icon: 'pi pi-user',
           command: () => {router.push('/profile');}
       },
       {
           label: 'Tribunal',
           icon: 'pi pi-users',
           command: () => {router.push('/submit_case');}
       },
    ];

    if (tribunalStore.capabilities?.adjudicator?.eligible) {
        const pendingCount = tribunalStore.capabilities?.adjudicator?.pending_assignments || 0;
        list.push({
            label: 'Adjudicator Assignments',
            icon: 'pi pi-building-columns',
            command: () => { router.push('/tribunal/jury'); },
            badge: pendingCount
        });
    }

    list.push(
       {
           label: 'learn',
           icon: `pi-book`,
           command: () => {router.push('/learn-module');}
       },
       {
           label: 'Exam',
           icon: 'pi pi-bars',
           command: () => {router.push('/exam-module');}
       },
       {
           label: 'Score',
           icon: 'pi pi-gauge',
           command: () => {router.push('/scores');}
       },
       {
           label: 'Testament',
           icon: 'pi pi-bell',
           command: () => {router.push('/testament');}
       }
    );

    return list;
});

const isActive = (label: string): boolean => {
    const path = route.path;
    if (label === 'Home') return path === '/home';
    if (label === 'Profile') return path === '/profile' || path.startsWith('/showUserProfile/');
    if (label === 'Tribunal' || label === 'Adjudicator Assignments') return path.startsWith('/tribunal') || path.startsWith('/submit_case') || path.startsWith('/internal-tribunal');
    if (label === 'learn') return path.startsWith('/learn');
    if (label === 'Exam') return path.startsWith('/exam-module') || path.startsWith('/exams') || path.startsWith('/openExamQuestions/');
    if (label === 'Score') return path === '/scores';
    if (label === 'Testament') return path === '/testament';
    return false;
};

const showNotifications = (event:any) =>
{
    if(op.value)
    {
        op.value.toggle(event);
    }
}
</script>
