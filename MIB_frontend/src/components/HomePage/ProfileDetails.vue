<template>
  <Card :pt="{ body: 'p-0 m-0' }" class="dashboard-card profile-summary-card">
    <template #content>
      <div
        class="profile-cover"
        :style="{ backgroundImage: `url(${basicInfo.cover_image || coverImage})` }"
      >
        <Avatar
            :image="basicInfo.profile_image || base64Image"
            shape="circle"
            class="profile-avatar"
        />
      </div>

      <section class="profile-summary-body">
        <h2 class="profile-name">{{ basicInfo.full_name || 'Your profile' }}</h2>
        <p class="profile-role">{{ basicInfo.profession?.profession || 'Professional profile' }}</p>
        <p v-if="basicInfo.profession?.location" class="profile-location">
          <i class="pi pi-map-marker"></i>
          {{ basicInfo.profession.location }}
        </p>
        <RouterLink to="/scores" class="score-link" aria-label="View your LCI and HIP rank">
          <HipRankBadge
            :score="basicInfo.lci_score ?? basicInfo.hip_score"
            :rank="basicInfo.hip_rank"
            :tier="basicInfo.rank_tier"
            :color="basicInfo.rank_badge_color"
          />
        </RouterLink>

        <div class="profile-facts">
          <div>
            <span class="profile-fact-label">Company</span>
            <span class="profile-fact-value">{{ basicInfo.profession?.company || '—' }}</span>
          </div>
          <div>
            <span class="profile-fact-label">Education</span>
            <span class="profile-fact-value">{{ basicInfo.school || '—' }}</span>
          </div>
        </div>
      </section>

      <Divider v-if="!isCompelete">
        <span class="text-secondary small">Complete your profile</span>
      </Divider>
      <compeletedProfile v-if="!isCompelete" @profileCompleted="setVisibility" />
    </template>
  </Card>
</template>

<script setup lang="ts">
import Divider from "primevue/divider";
import Avatar from "primevue/avatar";
import Card from "primevue/card";
import { ref, computed } from "vue";
import { useUserProfile } from "@/stores/User/userProfile";
import coverImageSet from "@/assets/default-cover-2.jpg";
import userPng from "@/assets/user.png";
import compeletedProfile from "@/components/HomePage/compeleteProfile.vue";
import HipRankBadge from "@/components/commonComponents/HipRankBadge.vue";

const userProfile = useUserProfile();
const basicInfo = computed(() => userProfile.getSummaryDetails);
const coverImage = ref<string>(coverImageSet);
const base64Image = ref<string>(userPng);
const isCompelete = ref<boolean>(false);

const setVisibility = (value: boolean) => {
  isCompelete.value = value;
};
</script>

<style scoped>
.profile-cover {
  position: relative;
  display: flex;
  justify-content: center;
  background-size: cover;
  background-position: center;
  min-height: 108px;
  border-radius: 1rem 1rem 0 0;
}

.profile-avatar {
  position: absolute;
  bottom: -2.3rem;
  width: 5rem;
  height: 5rem;
  border: 4px solid #fff;
  border-radius: 50%;
  box-shadow: 0 4px 14px rgb(17 24 39 / 14%);
}

.profile-summary-body {
  padding: 3rem 1.25rem 1.25rem;
  text-align: center;
}

.profile-name {
  margin: 0;
  color: #17191d;
  font-size: 1.1rem;
  font-weight: 750;
}

.profile-role,
.profile-location {
  margin: 0.35rem 0 0;
  color: var(--ds-text-muted);
  font-size: 0.875rem;
}

.profile-location {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.8rem;
}

.score-link {
  display: inline-flex;
  max-width: 100%;
  margin-top: 0.9rem;
  text-decoration: none;
  border-radius: 999px;
}

.score-link:focus-visible {
  outline: 2px solid var(--ds-primary);
  outline-offset: 2px;
}

.profile-facts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1px solid var(--ds-border);
  text-align: left;
}

.profile-facts > div {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.25rem;
}

.profile-fact-label {
  color: #9298a2;
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.profile-fact-value {
  overflow: hidden;
  color: #363a40;
  font-size: 0.8rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
