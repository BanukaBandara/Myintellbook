<template>
    <div class="row flex-grow-1 m-0 overflow-auto">
        <div class="col-md-2"></div>
        <div class="col-md-5">
            <div v-if="loading"><i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i></div>
             <userUpperSection :userGeneralInfo="userGeneralInfo"/>
            <div class="d-flex flex-row align-items-center menuBar gap-4 mx-4 mt-md-5">
               <!-- <div @click="() => {showTimeLine = true; showProfile = false; }" :class="(showTimeLine) ? 'underline': ''">Timeline</div> -->
                <div @click="()=>{showProfile = true; showTimeLine = false;}"  :class="(showProfile) ? 'underline': ''">Profile</div>
                <div @click="()=>{showProfile = false; showTimeLine = true;}"  :class="(showTimeLine) ? 'underline': ''">Exams</div>
            </div>
                <profileDetails 
                v-if="showProfile"  
                :generalInfo="userGeneralInfo"
                :workExperiance="workExperiance"
                :allExperiance="allExperiance"
                :educationDetails="educationDetails"
                :skills="skills"
                @skilldeleted="getSkills"
                from="profile"
                />
            <div v-if="showTimeLine" class="mt-4">
                <examTab 
                :completedExams="completedExams"
                :upcommingExams="upcommingExams"
                :isShow="true"
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
import { ref, onMounted, computed } from 'vue';
import Divider from 'primevue/divider';
import ProfileList from '../../components/HomePage/ProfileList.vue';
import profileDetails from '../../components/ProfilePage/profileDetails.vue';
import { useUserProfile } from '@/stores/User/userProfile';
import type {userGeneralInfoType} from '../../types/userGeneralInfoType';
import showAlert from '@/composables/showAlert';
import type { workExperianceType } from '@/types/workExperianceType';
import type  { educationType } from '../../types/educationType';
import latestUpdates from '@/components/commonComponents/latestUpdates.vue';
import userUpperSection from '@/components/commonComponents/userUpperSection.vue';
import examTab from '@/components/ProfilePage/examTab.vue';
import type { compeletedExamsType } from '@/types/compeletedExamType';

const showProfile = ref(true);
const loading = ref<boolean>(false);
const showTimeLine = ref(false);
const userProfile = useUserProfile();
const currentWorking = ref();
const skills = ref<Array<{skill:'',id:0}>>([]);
const allExperiance = ref<Array<workExperianceType>>([]);
const educationDetails = ref<Array<educationType>>([]);
const completedExams = ref<Array<compeletedExamsType>>([]);
const upcommingExams = ref<Array<compeletedExamsType>>([]);

const userGeneralInfo = ref<userGeneralInfoType>({
    first_name: '',
    last_name: '',
    gender: 0,
    birth_date: '',
    profile_image:'',
    cover_image:'',
    total_points:0,
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

const getGeneralInfo = async() =>
{
    let result = await userProfile.getGeneralInfo();
    if(result.code == 200)
   {
       userGeneralInfo.value = result.data[0];
        userGeneralInfo.value.gender = (userGeneralInfo.value.gender == 1) ? 'Male':'Female';
        userProfile.profile_image_set =  userGeneralInfo.value.profile_image;
        userProfile.cover_image_set =  userGeneralInfo.value.cover_image;
        workExperiance.value = result.data[0].experiance
        skills.value = result.data[0].skills
        educationDetails.value = result.data[0].education
        completedExams.value = result.data[0].completed_exams
        upcommingExams.value = result.data[0].upcomming_exams
        
   }else{
    let config ={
                    icon:'error',
                    title:'Error',
                    text: result.message,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#a03829',
                    showConfirmButton:true
                }
            
        let confirm = await showAlert(config);
   }
}

const getSkills = async() =>
{
    let result = await userProfile.getSkills()

   if(result.code == 200)
   {
    skills.value =result.data;
    // allExperiance.value = result.data;

   }else{
    let config ={
                    icon:'error',
                    title:'Error',
                    text: result.message,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#a03829',
                    showConfirmButton:true
                }
            
        let confirm = await showAlert(config);
        
   }
   userProfile.setIsEdit(true);
}



onMounted(async()=>{
    await getGeneralInfo();
    await getSkills();
})
</script>