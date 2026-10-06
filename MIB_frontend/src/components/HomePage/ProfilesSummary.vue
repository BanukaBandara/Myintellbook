<template>
     <Card :pt="{
            root:'w-100 h-100 p-0 m-0 rounded',
            content:'text-center',
            body:'pt-0'
        }">
            <template #header>
                <Avatar :image="(profile.profile_image)? profile.profile_image : userPng"  class="w-100 h-auto"  />
            </template>
            <template #content>
                
                <div class="d-flex flex-column align-items-center ">
                    <p class="mt-2 fw-semibold">#{{ profileDeatils.rank }} in Top Users</p>
                    <p class="m-0 mt-3 fw-semibold">{{profileDeatils.full_name}}</p>
                    <p class="text-secondary m-0">{{profileDeatils.profession}} </p>
                    <p>⭐ {{ profileDeatils.points }} pts</p>
                
                </div>
            </template>
            <template #footer>
                <div>
                    <Button label="View Profile" class="w-100 mb-6 " size="small" severity="secondary" @click="()=>{router.push(`/showUserProfile/${profileDeatils.profile_url}`)}" />
                </div>
               
            </template>
    </Card>
</template>
<script lang="ts" setup>
import Card from 'primevue/card';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';  
import { ref, defineProps, computed } from 'vue'; 
import type {profileListSearch} from '../../types/profileListSearch'
import type { PropType } from 'vue';
import userPng from '@/assets/user.png';
import { useRouter } from 'vue-router';

const router = useRouter();
const props = defineProps({
    profile: {
        type: Object as PropType<profileListSearch>,
        required: true
    }
});

const profileDeatils = computed(() => props.profile);
</script>