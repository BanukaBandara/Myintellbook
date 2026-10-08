<template>
  <Card :pt="{ body: 'p-0 m-0' }" class="shadow-sm rounded-3">
    <template #content>
      <!-- Cover + Avatar -->
      <div
        class="w-100 rounded-top background-image d-flex justify-content-center"
        :style="{ backgroundImage: `url(${basicInfo.cover_image || coverImage})` }"
        >
        <Avatar
            :image="basicInfo.profile_image || base64Image"
            shape="circle"
            size="xlarge"
            class="border border-3 border-light avatar-float"
        />
        </div>

      <!-- Name + Profession -->
      <div class="text-center mt-5">
        <h6 class="mb-1">{{ basicInfo.full_name }}</h6>
        <p class="m-0 text-secondary small">
          {{ basicInfo.profession?.profession || "" }}
        </p>
      </div>

      <Divider />

      <!-- Info Grid -->
      <div class="row text-center">
        <div class="col-6 border-end">
          <p class="fw-semibold text-primary m-0 small">
            {{ basicInfo.profession?.company || "" }}
          </p>
          <p class="m-0 small">{{ basicInfo.profession?.location || "" }}</p>
        </div>
        <div class="col-6">
          <p class="fw-semibold text-success m-0 small">
            {{ basicInfo.school }}
          </p>
          <!-- <p class="m-0 small">Rank: {{ basicInfo.rank }}</p> -->
        </div>
      </div>

      <Divider />

      <p class="d-flex align-items-center justify-content-center fw-bold ps-4 fs-5" v-tooltip="'Human Intelegence Portfolio'">
       HIP :-
      </p>

      <!-- Points -->
      <!-- <div class="text-center">
        <h6 class="text-secondary mb-1">Total Points</h6>
        <h4 class="fw-bold">{{ basicInfo.total_points }}</h4>
      </div> -->

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
.background-image {
  background-size: cover;
  min-height: 60px;
  margin-bottom: 20px;
}

.avatar-float {
  position: relative;
  top: 30px; /* keep it slightly overlapping cover */
}
</style>
