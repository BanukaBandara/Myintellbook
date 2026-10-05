<template>
  <div class="container-fluid py-3">
    <div class="row justify-content-center g-3">
      <!-- Left Sidebar: Profile Details -->
      <div class="col-lg-2 d-none d-lg-block">
        <ProfileDetails />
      </div>

      <!-- Main Content -->
      <div class="col-12 col-lg-7">
        <Card class="shadow-sm">
          <!-- Card Title -->
          <template #title>
            <div class="d-flex align-items-center">
              <i class="pi pi-arrow-left me-2 cursor-pointer" @click="$router.back()" />
              <span class="fs-5 fw-semibold text-secondary">Other Users Profiles</span>
            </div>
          </template>

          <!-- Card Content -->
          <template #content>
            <!-- Search Bar -->
            <div class="mb-3">
              <IconField
                :pt="{
                  root: 'w-100',
                  icon: 'bg-white border-0 p-2',
                  input: 'w-100'
                }"
              >
                <InputIcon class="pi pi-search" />
                <InputText
                  v-model="searchVal"
                  @keyup="searchProfile"
                  placeholder="Search profiles..."
                  class="form-control rounded-pill"
                  style="height: 38px; font-size: 0.9rem;"
                />
              </IconField>
            </div>

            <!-- Profiles Grid -->
            <div class="row g-3">
              <div
                class="col-6 col-md-4 col-lg-3"
                v-for="(profile, index) in profileList"
                :key="index"
              >
                <ProfilesSummary :profile="profile" />
              </div>
            </div>

            <!-- Loading Indicator -->
            <div v-if="loading" class="text-center my-4">
              <i class="pi pi-spin pi-spinner fs-3 text-primary"></i>
            </div>
          </template>
        </Card>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
import ProfilesSummary from '@/components/HomePage/ProfilesSummary.vue';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import Card from 'primevue/card';
import { useUserProfile } from '@/stores/User/userProfile';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import type { profileListSearch } from '../../types/profileListSearch';
import ProfileDetails from '../../components/HomePage/ProfileDetails.vue';

const userProfile = useUserProfile();
const profileList = ref<Array<profileListSearch>>([]);
const searchVal = ref<string>('');
const loading = ref<boolean>(false);
const finished = ref<boolean>(false);
const thisPage = ref<number>(1);

// Search Profiles
const searchProfile = async () => {
  loading.value = true;
  userProfile.SearchKey = searchVal.value;
  const result = await userProfile.searchProfile();
  profileList.value = result.data;
  finished.value = result.data.length === 0;
  loading.value = false;
};

// Infinite Scroll
const handleScroll = () => {
  if (loading.value || finished.value) return;

  const scrollPosition = window.innerHeight + window.scrollY;
  const threshold = document.body.offsetHeight - 50;

  if (scrollPosition >= threshold) {
    loadProfiles();
  }
};

const loadProfiles = async () => {
  loading.value = true;
  const result = await userProfile.getPaginatedProfiles(thisPage.value);
  if (result.data.length === 0) {
    finished.value = true;
  } else {
    profileList.value.push(...result.data); // Spread operator to merge arrays
    thisPage.value++;
  }
  loading.value = false;
};

onMounted(async () => {
  await searchProfile();
  window.addEventListener('scroll', handleScroll);
});

onBeforeUnmount(() => {
  window.removeEventListener('scroll', handleScroll);
});
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}

.shadow-sm {
  box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.rounded-pill {
  border-radius: 50rem !important;
}
</style>
