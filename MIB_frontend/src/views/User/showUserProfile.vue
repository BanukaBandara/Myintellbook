<template>
    <div class="row flex-grow-1 m-0 overflow-auto">
        <div class="col-md-2"></div>
        <div class="col-md-5 mt-3">
            <div v-if="loading"><i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i></div>
            <userUpperSection :userGeneralInfo="userGeneralInfo"/>
            <p v-if="errorMessage" class="text-danger text-center mt-3">{{ errorMessage }}</p>
            <div class="d-flex flex-row align-items-center menuBar gap-4 mx-4 mt-3">
               <!-- <div @click="() => {showTimeLine = true; showProfile = false; }" :class="(showTimeLine) ? 'underline': ''">Timeline</div> -->
                <div @click="()=>{showProfile = true; showTimeLine = false;}"  :class="(showProfile) ? 'underline': ''">Profile</div>
                 <div @click="()=>{showProfile = false; showTimeLine = true;}"  :class="(showTimeLine) ? 'underline': ''">Exams</div>
            </div>
                <profileDetails 
                v-if="showProfile"  
                :generalInfo="userGeneralInfo"
                :workExperiance="workExperiance"
                :educationDetails="educationDetails"
                :skills="skills"
                :achievements="achievements"
                :isEditable="isOwnProfile"
               
                />
                <div v-if="showTimeLine" class="mt-4">
                    <examTab 
                    :completedExams="completedExams"
                    :upcommingExams="upcommingExams"
                    :isShow="false"
                    />
            </div>
        </div>
         <div class="col-md-3 mt-3 d-none d-md-block">
                
                <!-- <myExams />
                <Divider /> -->
                <latestUpdates />
                <!-- <Divider />
                <categoriesShow /> -->
                <Divider />
                <ProfileList />
        </div>
        <div class="col-md-2"></div>
    </div>
</template>
<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import Divider from 'primevue/divider';
import ProfileList from '../../components/HomePage/ProfileList.vue';
import profileDetails from '../../components/ProfilePage/profileDetails.vue';
import { useUserProfile } from '@/stores/User/userProfile';
import type {userGeneralInfoType} from '../../types/userGeneralInfoType';
import type { workExperianceType } from '@/types/workExperianceType';
import type  { educationType } from '../../types/educationType';
import latestUpdates from '@/components/commonComponents/latestUpdates.vue';
import { useRouter, useRoute } from 'vue-router';
import userUpperSection from '@/components/commonComponents/userUpperSection.vue';
import type { compeletedExamsType } from '@/types/compeletedExamType';
import examTab from '@/components/ProfilePage/examTab.vue';

const showProfile = ref(true);
const loading = ref<boolean>(false);
const showTimeLine = ref(false);
const route = useRoute();
const userProfile = useUserProfile();
const userUrl = computed(() => String(route.params.id ?? ''));
const isOwnProfile = computed(() => {
    const targetUserId = Number(userUrl.value);
    if (!Number.isSafeInteger(targetUserId) || targetUserId <= 0) return false;

    try {
        const authUser = JSON.parse(localStorage.getItem('userData') ?? 'null');
        return Number(authUser?.id) === targetUserId;
    } catch {
        return false;
    }
});
const skills = ref<Array<{skill:'',id:0}>>([]);
const educationDetails = ref<Array<educationType>>([]);
const achievements = ref<Array<{ id: number; title: string; category: string }>>([]);
const errorMessage = ref('');
const userGeneralInfo = ref<userGeneralInfoType>({
    first_name: '',
    last_name: '',
    gender: 0,
    birth_date: '',
    profile_image:'',
    cover_image:'',
    total_points:0,
    hip_score:0,
    rank:0,
    school:'',
    visibility:{},
    profession:{
        company:'',
        location:'',
        profession:''
    },
    slug:''
});
const workExperiance = ref<Array<workExperianceType>>([]);
const completedExams = ref<Array<compeletedExamsType>>([]);
const upcommingExams = ref<Array<compeletedExamsType>>([]);

const getUserInfomations = async() =>
{
    if (!userUrl.value) return;
    loading.value = true;
    errorMessage.value = '';
    try {
        const isId = /^\d+$/.test(userUrl.value);
        const result = isId
            ? await userProfile.getUserById(userUrl.value)
            : await (async () => {
                userProfile.slug = userUrl.value;
                return userProfile.getUserInfomations();
            })();
        const details = isId
            ? result.user
            : (result.code === 200 ? result.data?.[0] : undefined);

        if (result.code !== 200 || !details) {
            errorMessage.value = result.message ?? 'Unable to load this profile.';
            return;
        }

        userGeneralInfo.value = details;
        userGeneralInfo.value.gender = details.gender == 1 ? 'Male' : 'Female';
        userProfile.profile_image_set = details.profile_image;
        userProfile.cover_image_set = details.cover_image;
        workExperiance.value = details.experiance ?? [];
        skills.value = details.skills ?? [];
        educationDetails.value = details.education ?? [];
        achievements.value = details.achievements ?? [];
        completedExams.value = details.completed_exams ?? [];
        upcommingExams.value = details.upcomming_exams ?? [];
    } catch (error) {
        console.error('Unable to load profile details', error);
        errorMessage.value = 'Unable to load this profile.';
    } finally {
        loading.value = false;
        userProfile.setIsEdit(false);
    }
}

watch(userUrl,async()=>
{
  if(userUrl.value)
  {
     await getUserInfomations();
  }
});



onMounted(async()=>{
    await getUserInfomations();
})
</script>
