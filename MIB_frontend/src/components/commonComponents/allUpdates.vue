<template>
  <div class="d-flex justify-content-center bg-light py-4">
    <Card
      class="shadow rounded-4 border-0"
      :pt="{ root: 'w-75 w-md-50' }"
    >
      <!-- Header -->
      <template #title>
        <div class="d-flex justify-content-between align-items-center">
          <span class="fw-semibold fs-5 text-dark">
            Latest Updates
          </span>
          <Button asChild variant="link" size="small">
            <RouterLink
              to="/home"
              class="text-decoration-none text-primary fw-semibold"
              style="font-size: 14px;"
            >
              Back to Home
            </RouterLink>
          </Button>
        </div>
      </template>

      <!-- Content -->
      <template #content>
        <div
          v-if="notifications.length > 0"
          class="d-flex flex-column gap-3 p-2"
          style="max-height: 65vh; overflow-y: auto;"
        >
          <div
            v-for="notification in notifications"
            :key="notification.id"
            class="p-3 rounded-3 bg-white border shadow-sm hover-shadow-sm transition"
          >
            <Notifications
              :message="notification.message"
              :created_at="notification.created_at"
              :profileImage="notification.profile_image"
              :name="notification.user_name"
            />
          </div>
        </div>

        <!-- Empty state -->
        <div
          v-else
          class="text-center text-muted py-5 fs-6"
        >
          No updates available ✨
        </div>
      </template>
    </Card>
  </div>
</template>

<script lang="ts" setup>
import Card from 'primevue/card';
import { onMounted, ref } from 'vue';
import { useUserProfile } from '@/stores/User/userProfile'; 
import type { notificationsType } from '@/types/notifications';
import Notifications from './Notifications.vue';
import { Button } from 'primevue';

const userProfile = useUserProfile();
const notifications = ref<Array<notificationsType>>([]);

const getNotifications = async () => {
  const result = await userProfile.getAllNotifications();
  notifications.value = result.data;
};

onMounted(async () => {
  await getNotifications();
});
</script>

<style scoped>
.hover-shadow-sm:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.transition {
  transition: box-shadow 0.3s ease;
}
</style>
