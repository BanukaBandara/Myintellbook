<template>

   <div v-if="percentageCompleted != 100">
     <ProgressBar :value="percentageCompleted" class="mb-4 mx-2"></ProgressBar>
    <ProfileComplete description="General Information" :completedquantity="generalInfo" :totalSteps="4" link="/profileEdit/generalInfo" :completed="generalInfoCompleted"/>
    <ProfileComplete description="Work Experience" :completedquantity="workExperiance" :totalSteps="1" link="/profileEdit/workExperience" :completed="workExperianceCompleted" />
    <ProfileComplete description="Education Information" :completedquantity="educationInfo" :totalSteps="1" link="/profileEdit/educationInfo" :completed="educationInfoCompleted" />
    <ProfileComplete description="Skills Information" :completedquantity="SkillsInfo" :totalSteps="1" link="/profileEdit/skillsInfo" :completed="SkillsInfoCompleted"/>
    <ProfileComplete description="Profile Photo" :completedquantity="profileImage" :totalSteps="1" link="/profileEdit/addProfileImage" :completed="profileImageCompleted"/>
    <ProfileComplete description="Cover Photo" :completedquantity="coverImage" :totalSteps="1" :completed="coverImageCompleted" link="/profileEdit/addCoverImage"/>
   </div>
   <div v-else class="d-flex justify-content-center align-items-center h-100">
        <Card class="w-100 h-100 d-flex justify-content-center align-items-center">
           <template #content>
             <div class="text-center">
                <h2 class="fw-bold mb-4">Profile Completed!</h2>
                <p class="text-secondary fs-5">Congratulations! Your profile is now complete.</p>
            </div>
           </template>
        </Card>
   </div>

</template>
<script lang="ts" setup>
import Card from 'primevue/card';
import ProgressBar from 'primevue/progressbar';
import ProfileComplete from './ProfileComplete.vue';
import Popover from 'primevue/popover';
import {useUserProfile} from '@/stores/User/userProfile';
import { ref, computed, onMounted, watch } from 'vue';
import { loadingIcon } from '@primeuix/themes/aura/datatable';

const emit = defineEmits(['profileCompleted']);
const op = ref<InstanceType<typeof Popover> | null>(null);
const showPopover = (event:any) => {
        op.value?.toggle(event);
};
const userProfile = useUserProfile();
const loading = ref<boolean>(false);
const profileCompleted = computed(()=> userProfile.getProfiileComplete);
const profileImage = ref<number>(0);
const coverImage = ref<number>(0);
const generalInfo = ref<number>(0);
const workExperiance = ref<number>(0);
const educationInfo = ref<number>(0);
const SkillsInfo = ref<number>(0);
const percentageCompleted = ref<number>(0);
const generalInfoCompleted = computed(() => {
    return Object.values(profileCompleted.value.generalInfo).filter(Boolean).length >= 4;
});
const profileImageCompleted = computed(() => {
    return profileCompleted.value.profileImage ? true : false;
});
const coverImageCompleted = computed(() => {
    return profileCompleted.value.coverImage ? true : false;
});
const workExperianceCompleted = computed(() => {
    return profileCompleted.value.workExperiance ? true : false;
});
const educationInfoCompleted = computed(() => {
    return profileCompleted.value.education ? true : false;
}); 
const SkillsInfoCompleted = computed(() => {
    return profileCompleted.value.skills ? true : false;
});


const percentage = () =>
{
    loading.value = true;
    generalInfo.value = Object.values(profileCompleted.value.generalInfo).filter(Boolean).length;

    profileImage.value = profileCompleted.value.profileImage ? 1 : 0;
    coverImage.value = profileCompleted.value.coverImage ? 1 : 0;
    workExperiance.value = profileCompleted.value.workExperiance ? 1 : 0;
    educationInfo.value = profileCompleted.value.education ? 1 : 0;
    SkillsInfo.value = profileCompleted.value.skills ? 1 : 0;
    percentageCompleted.value = Math.round((generalInfo.value + workExperiance.value + educationInfo.value + SkillsInfo.value + profileImage.value + coverImage.value) / 9 * 100);
    loading.value = false;
}

watch(percentageCompleted, (newValue) => {
    if (newValue === 100) {
        emit('profileCompleted', true);
    } else {
        emit('profileCompleted', false);
    }
}, { immediate: true });
watch(profileCompleted, (newValue) => {
    if (newValue) {
        percentage();
    }
}, { immediate: true });
</script>