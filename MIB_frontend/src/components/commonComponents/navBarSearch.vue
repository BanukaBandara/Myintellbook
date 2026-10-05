<template>
  <IconField
    :pt="{
      root: 'navbar-search mx-2',
      icon: 'bg-transparent border-0 p-1',
      input: 'w-75'
    }"
  >
    <InputIcon class="pi pi-search" />
    <InputText
      placeholder="Search..."
      class="navbar-search-input w-100"
      style="height: 40px; font-size: 0.9rem;"
      @keyup="searchProfile"
    />

    <Popover
      ref="search"
      class="popover-responsive"
      style="max-height: 90%; overflow: auto;"
    >
      <div class="w-100 px-2">

        <!-- Profiles -->
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-2 sticky-top bg-white py-1">
            <h6 class="fw-semibold m-0">Profiles</h6>
            <RouterLink to="/profiles" class="text-decoration-none small text-primary">See all</RouterLink>
          </div>

          <div
            v-for="(profile, index) in profileList.slice(0, showLength)"
            :key="index"
            class="dashboard-profile-row d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between p-2 rounded-4 mb-2 border bg-white gap-2"
          >
            <div class="d-flex align-items-center gap-2 gap-md-3 w-100">
              <small class="text-secondary fw-semibold">#{{ profile.rank }}</small>
              <Avatar :image="profile.profile_image || userPng" shape="circle" size="large" />
              <div class="flex-grow-1">
                <div class="fw-semibold text-truncate">{{ profile.full_name }}</div>
                <small class="text-muted d-block">{{ profile.profession || '' }}</small>
                <small class="dashboard-verified-pill">HIP {{ Number(profile.hip_score ?? 0).toLocaleString() }}</small>
              </div>
            </div>
            <Button
              label="View"
              size="small"
              severity="primary"
              outlined
              class="dashboard-action-button w-100 w-md-auto"
              @click="() => router.push({ name: 'showUserProfile', params: { id: profile.id } })"
            />
          </div>
        </div>

        <!-- Exams -->
        <!-- Exams -->
          <!-- Exams -->
              <div>
                <div class="d-flex justify-content-between align-items-center mb-2 sticky-top bg-white py-1">
                  <h6 class="fw-semibold m-0">Exams</h6>
                  <RouterLink to="/learn" class="text-decoration-none small text-primary">See all</RouterLink>
                </div>

                <div
                  v-for="(e, index) in exam.slice(0, 6)"
                  :key="index"
                  class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between p-2 rounded mb-2 border bg-light-subtle gap-2"
                >
                  <div class="flex-grow-1">
                    <div class="fw-semibold text-truncate">{{ e.title }}</div>
                    <!-- 👇 shorter, max 2 lines -->
                    <small class="exam-description d-block">
                      {{ e.description }}
                    </small>
                    <small class="text-secondary">⏱ {{ e.duration_minutes }} min</small>
                  </div>
                  <Button
                    label="Open"
                    size="small"
                    severity="primary"
                    outlined
                    class="w-100 w-md-auto"
                    @click="() => router.push(`/openExamQuestions/${e.id}`)"
                  />
                </div>
              </div>


      </div>
    </Popover>
  </IconField>
</template>

<script setup lang="ts">
import { ref , watch} from 'vue';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Popover from 'primevue/popover';
import Button from 'primevue/button';
import type {profileListSearch} from '@/types/profileListSearch';
import { useUserProfile } from '@/stores/User/userProfile';
import userPng from '@/assets/user.png';
import Avatar from 'primevue/avatar'; 
import { useRouter} from 'vue-router';


const profileList = ref<Array<profileListSearch>>([]);
const search = ref< InstanceType<typeof Popover> | null>(null);
const showLength=ref<number>(0);
const userProfile = useUserProfile();
const router = useRouter();
const exam = ref();
const categories = ref();

watch(profileList, (newValue, oldValue) => {
  if(newValue.length < 3 ){
    showLength.value = newValue.length
  }else{
    showLength.value = 3
  }
});

const searchProfile = async(event:any)=>
{
    if(search.value)
    {
        search.value.toggle(event);
        userProfile.SearchKey = event.target.value;
        let result =await userProfile.searchProfile();
        profileList.value = result.data;
        exam.value = result.exams;
        categories.value = result.categories;
    }
}

const followCategory = async(categoryId:number) =>{
  let result = await userProfile.followCategory(categoryId); 

  if(result.code == 200)
  {
    categories.value = categories.value.map((cat:any) => {
      if(cat.id === categoryId) {
        return { ...cat, isFollowed: !cat.isFollowed };
      }
      return cat;
    });
  }

}

const openCategory = (name:string,follow:boolean) =>{
  if(follow){
    router.push(`/exams/${name}`);
  }else{
    router.push(`/learn/${name}`);
  }
  
}
</script>

<style scoped>
/* ✅ Responsive popover width */
.popover-responsive {
  width: 100%;              /* Mobile: full width */
  max-width: 580px;         /* Laptop/Desktop: clean readable width */
  min-width: 320px;         /* Prevent too small */
}
.navbar-search {
  width: clamp(13rem, 24vw, 22rem);
}
.navbar-search-input {
  height: 40px;
  border: 1px solid transparent;
  border-radius: 0.85rem;
  background: rgb(243 244 246 / 84%);
  padding: 0.55rem 0.85rem 0.55rem 2.45rem;
  transition: background-color 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}
.navbar-search-input:enabled:focus {
  border-color: var(--ds-primary);
  background: #fff;
  box-shadow: 0 0 0 3px rgb(239 68 68 / 10%);
}
.exam-description {
  display: -webkit-box;
  -webkit-line-clamp: 2;     /* limit to 2 lines */
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
  color: var(--ds-text-muted);
  font-size: 0.85rem;
  line-height: 1.2rem;
}
</style>