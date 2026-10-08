<script setup lang="ts">
import { RouterView } from 'vue-router'
import {useLoadingStore} from '@/stores/loadingStore';
import navBar from '@/components/navBar.vue';
import { useRoute } from 'vue-router';
import { computed, ref, onMounted, watch } from 'vue';
import { useUserProfile} from '@/stores/User/userProfile';
import type {userGeneralInfoType} from '@/types/userGeneralInfoType'
import { useUserStore } from '@/stores/User/userStore';
import FAQ from '@/components/commonComponents/FAQ.vue';

const loadingStore = useLoadingStore();
const route = useRoute();
const userStore = useUserStore();
const isLoading = computed(()=>loadingStore.isLoadingState);
const userProfile = useUserProfile();
const isLogged = computed(()=> (route.path !== '/login' && route.path !== '/register' && route.path !== '/' && route.path !== '/examForm'));
const userGeneralInfo = ref<userGeneralInfoType>({
    first_name: '',
    last_name: '',
    gender: 0,
    birth_date: '',
    profile_image:'',
    cover_image:'',
    school:'',
    total_points:0,
    rank:0,
    posts:[],
    visibility:{},
    profession:{
      company:'',
      location:'',
      profession:''
    },
    slug:''
});

const isJuryRoute = computed(() => route.path.startsWith('/jury'));
const isAdminRoute = computed(() => route.path.startsWith('/admin'));

watch(isLoading,async()=>
{
  if(isLoading.value && !isJuryRoute.value && !isAdminRoute.value)
  {
     await BasicInfo();
     await profileCompliation();
     await getProfileList();
  }
});

const profileCompliation = async() =>
{
  let result =await userProfile.getProfileComplete();
  
  if(result.code == 200){
     userProfile.profileComplete = result.data;
  }else{
    console.error(result.error)
  }
}

const BasicInfo = async() =>
{
  let result =await userProfile.basicInfo();

  if(result.code == 200){
    userProfile.summaryDetails = result.data[0];
    console.log(result.data[0]);
  }else{
    console.error(result.error)
  }
}

const getProfileList = async() =>{
    let result = await userProfile.getUserProfiles();
    if(result.code == 200)
    {

        userProfile.userProfiles = result.data;
    }
    else
    {
        console.error('Failed to fetch profiles:', result.message);
    }
}

</script>

<template>
  <div class="app-shell">
    <navBar v-if="!isJuryRoute && !isAdminRoute" />
    <div v-if="loadingStore.isLoadingState" class="loader-overlay">
      <div class="spinner"></div>
    </div>

    <main class="app-content-scroll">
      <router-view />
      <FAQ v-if="isLogged && !isJuryRoute && !isAdminRoute"/>
    </main>
  </div>
</template>
<style>
.app-shell {
  display: flex;
  flex-direction: column;
  width: 100%;
  height: 100vh;
  height: 100dvh;
  overflow: hidden;
}
.app-content-scroll {
  flex: 1 1 auto;
  width: 100%;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
}
.loader-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(255, 255, 255, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
.spinner {
  width: 50px;
  height: 50px;
  border: 5px solid var(--ds-border-strong);
  border-top-color: var(--ds-info);
  border-radius: 50%;
  animation: spin 1s infinite linear;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
