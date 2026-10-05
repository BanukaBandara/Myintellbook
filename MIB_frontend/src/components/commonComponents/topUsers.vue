<template>
  <div class="d-flex justify-content-center vh-100 bg-light">
    <Card
      class="mb-3 mt-3 shadow-sm"
      :pt="{
        root: 'col-md-6'
      }"
    >
      <!-- Card Header -->
      <template #title>
        <div class="d-flex justify-content-between align-items-center">
          <span class="fw-semibold fs-5">🏆 Top Users</span>
          <Button asChild v-slot="slotProps" variant="link" label="see all">
            <RouterLink
              to="/home"
              class="text-decoration-none"
              style="font-size: 14px"
            >
              ← Back to Home
            </RouterLink>
          </Button>
        </div>
      </template>

      <!-- Card Content -->
      <template #content>
        <div
          v-for="(topUser, index) in topUsers"
          :key="index"
          class="w-100"
        >
          <div
            class="user-card d-flex flex-column flex-sm-row align-items-center justify-content-between border rounded-4 p-3 mb-3 bg-white shadow-sm hover-shadow"
          >
            <!-- Left: Rank Badge + Avatar + Info -->
            <div class="d-flex align-items-center flex-grow-1 w-100">
              <!-- Rank Badge -->
              <div
                class="rank-badge text-white fw-bold d-flex align-items-center justify-content-center me-3"
                :class="getRankClass(topUser.rank)"
              >
                {{ topUser.rank }}
              </div>

              <!-- Avatar + User Info -->
              <div class="d-flex align-items-center flex-grow-1 flex-sm-row flex-column">
                <Avatar
                  :image="topUser.profile_image ? topUser.profile_image : userPng"
                  class="me-sm-3 mb-2 mb-sm-0"
                  shape="circle"
                  size="large"
                  style="width: 55px; height: 55px"
                />
                <div class="d-flex flex-column text-center text-sm-start">
                  <span class="fw-semibold text-capitalize">
                    {{ topUser.name }}
                  </span>
                  <span class="text-muted small">Rank {{ topUser.rank }}</span>
                </div>
              </div>
            </div>

            <!-- Right: Points -->
            <div class="text-center text-sm-end mt-2 mt-sm-0">
              <span class="fw-semibold fs-6 d-block">{{ topUser.score }}</span>
              <span class="text-muted small">Points</span>
            </div>
          </div>
        </div>
      </template>
    </Card>
  </div>
</template>

<script lang="ts" setup>
import Card from "primevue/card";
import { onMounted, ref } from "vue";
import { useUserProfile } from "@/stores/User/userProfile";
import type { topUsersType } from "@/types/topUsersType";
import { Button } from "primevue";
import Avatar from "primevue/avatar";
import userPng from "@/assets/user.png";

const userProfile = useUserProfile();
const topUsers = ref<Array<topUsersType>>([]);

const getTopUsers = async () => {
  let result = await userProfile.getTopUsers();
  topUsers.value = result.data;
};

onMounted(async () => {
  await getTopUsers();
});

const getRankClass = (rank: number) => {
  if (rank === 1) return "bg-warning"; // gold
  if (rank === 2) return "bg-secondary"; // silver
  if (rank === 3) return "bg-orange"; // bronze
  return "bg-light text-dark";
};
</script>

<style scoped>
.rank-badge {
  width: 40px;
  height: 40px;
  border-radius: 50%;
}
.bg-orange {
  background-color: #fd7e14 !important;
}
.hover-shadow:hover {
  box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.12);
  transition: 0.2s;
}
.user-card {
  transition: transform 0.2s ease;
}
.user-card:hover {
  transform: translateY(-2px);
}
</style>
