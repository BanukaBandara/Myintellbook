<template>
<Card class="mb-3" :pt="{
        root:'w-75'
    }">
    <template #title>
        <div class="d-flex justify-content-between">
            <span class="fw-semibold fs-5">
            Latest Updates
        </span>
         <Button asChild v-slot="slotProps" variant="link"  label="see all">
            <RouterLink to="/allUpdates" class="text-decoration-none" style="font-size:15px;">See all</RouterLink>
        </Button>
        </div>
    </template>
    <template #content>
        <div v-for="notification in notifications.slice(0,showLength)" :key="notification.id" class="mt-3"> 
                <Notifications 
                 :message="notification.message" 
                 :created_at="notification.created_at" 
                 :profileImage="notification.profile_image"
                 :name="notification.user_name"
                 />
            </div>
            <div class="w-100 text-center">
                <Button class="text-decoration-none text-center w-100 fw-semibold text-secondary d-flex flex-row gap-2 align-items-center" severity="link"  icon="pi pi-angle-up"
                v-if="(notifications.length  > 3 || showLength == 6)" @click="()=>{ (showLength == 3) ? showLength = 6 : showLength = 3}">
                {{(showLength == 3) ? 'See more Updates ' : 'See less Updates '}}
                <i :class="(showLength == 3) ?'pi pi-angle-down' : 'pi pi-angle-up'"></i>
                </Button>
            </div>
    </template>
</Card>
</template>
<script lang="ts" setup>
import Card from 'primevue/card';
import { onMounted, ref, watch } from 'vue';
import { useUserProfile } from '@/stores/User/userProfile'; 
import type { notificationsType } from '@/types/notifications';
import Notifications from './Notifications.vue';
import { Button } from 'primevue';

const userProfile = useUserProfile();
const notifications = ref<Array<notificationsType>>([])
const showLength = ref<number>(0); 

watch(notifications, (newValue, oldValue) => {
  if(newValue.length < 3 ){
    showLength.value = newValue.length
  }else{
    showLength.value = 3
  }
});

  watch(
  () => userProfile.getIsAnswered,
  async (newVal, oldVal) => {
    await getNotifications()
  }
)

const getNotifications = async() =>
{
    let result = await userProfile.getNotifications();
    notifications.value = result.data;
}
onMounted(async() => {
    await getNotifications()
});
</script>