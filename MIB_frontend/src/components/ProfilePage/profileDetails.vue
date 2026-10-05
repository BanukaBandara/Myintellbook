<template>
    <div>
        <Card class="mt-3">
           <template #content>
                    <div class="d-flex flex-row align-items-center justify-content-between">
                        <h5>General</h5>
                        <Button v-if="isEditable" label="Edit" icon="pi pi-pencil" severity="secondary" size="small" class="fw-semibold" @click="router.push('/profileEdit/generalInfo')"/> 
                    </div>
                    <Divider />
                    <div class="row">
                        <div class="col-md-6 opacity-50 mb-4">First Name</div>
                        <div class="col-md-6 text-start mb-4">{{ generalInfo.first_name }}</div>
                        <div class="col-md-6 opacity-50 mb-4">Last Name</div>
                        <div class="col-md-6 mb-4">{{ generalInfo.last_name }}</div>
                        <div class="col-md-6 opacity-50 mb-4" v-if="canShow('birth_date')">Birth Date</div>
                        <div class="col-md-6 mb-4" v-if="canShow('birth_date')">{{ generalInfo.birth_date }}</div>
                        <div class="col-md-6 opacity-50 mb-4">Gender</div>
                        <div class="col-md-6 mb-4">{{ generalInfo.gender }}</div>
                    </div>
                </template>
            </Card>
            <Card class="mt-3">
                <template #content>
                    <div class="d-flex flex-row align-items-center justify-content-between">
                            <div class="d-flex flex-row align-items-center justify-content-between">
                                <h5>Experiance</h5>
                                <Button v-if="isEditable" icon="pi pi-fw pi-plus" severity="secondary"  label="Add" text size="small" class="fw-semibol" @click="()=>{userProfile.showExperianceEdit = true;router.push('/profileEdit/workExperience');}"/>
                            </div>
                            <Button variant="link"  label="see all" @click="()=>{router.push(`/allExperiances/${generalInfo.profile_url}`)}"/>
                    </div>
                    <Divider />
                    <div class="row p-2 gap-4" v-for="(experiance , index) in props.workExperiance.slice(0,showLength)" :key="index">
                         <div class="d-flex justify-content-between">
                           <div class="d-flex flex-column">
                                <span class="fw-bold">{{ experiance.title }} <Badge severity="secondary" v-if="(experiance.currently_working)">current position</Badge></span>
                                <span class="fs-6">{{ experiance.company }} </span>
                                <span class="fs-6">{{experiance.location}}</span>
                           </div>
                            <span><Button v-if="isEditable" icon="pi pi-pen-to-square" severity="secondary" size="small" class="fw-semibold" @click="experiance.id !== undefined && goToEdit(experiance.id)"/> </span>
                        </div>
                    </div>
                    
                </template>
                <template #footer>
                        <Button severity="secondary" class="w-100" v-if="(props.workExperiance.length > 3 || showLength == 6)" @click="()=>{ (showLength == 3) ? showLength = 6 : showLength = 3}">{{ (showLength == 3 ) ? 'Show more Experiences':'Show less Experiences' }}</Button>
                </template>
            </Card>
            <Card class="mt-3">
                <template #content>
                    <h5>Achievements</h5>
                    <Divider />
                    <p v-if="!props.achievements.length" class="text-secondary mb-0">No verified achievements yet.</p>
                    <div v-for="achievement in props.achievements" :key="achievement.id" class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ achievement.title || achievement.category }}</span>
                        <small class="text-secondary">{{ achievement.category }}</small>
                    </div>
                </template>
            </Card>
             <Card class="mt-3">
                <template #content>
                    <div class="d-flex flex-row align-items-center justify-content-between">
                            <div class="d-flex flex-row align-items-center justify-content-between">
                                <h5>Education</h5>
                                <Button v-if="isEditable" icon="pi pi-fw pi-plus" severity="secondary" label="Add" text size="small" class="fw-semibol" @click="()=>{ userProfile.showExperianceEdit = false;router.push('/profileEdit/educationInfo');}"/>
                            </div>
                            <Button variant="link"  label="see all" @click="()=>{router.push(`/showAllEducation/${generalInfo.profile_url}`)}"/>
                    </div>
                    <Divider />
                    <div class="row p-2 gap-4" v-for="(education, index) in educationDetails.slice(0,showLengthEdu)" :key="index">
                        <div class="d-flex justify-content-between">
                           <div class="d-flex flex-column">
                                <span class="fw-bold">{{ education.school }}</span>
                                <span class="fs-6">{{education.degree+"-"+ education.field_of_study}}</span>
                           </div>
                            <span><Button v-if="isEditable" icon="pi pi-pen-to-square" severity="secondary" size="small" class="fw-semibold" @click="()=>{router.push(`/profileEdit/educationInfo/${education.id}`)}"/> </span>
                        </div>
                    </div>
                    
                </template>
                 <template #footer>
                        <Button severity="secondary" class="w-100" v-if="(educationDetails.length  > 3 || showLengthEdu == 6)" @click="()=>{ (showLengthEdu == 3) ? showLengthEdu = 6 : showLengthEdu = 3}">{{ (showLengthEdu == 3 ) ? 'Show more Education Details':'Show less Education Details' }}</Button>
                 </template>
            </Card>
             <Card class="mt-3">
                <template #content>
                     <div class="d-flex flex-row align-items-center justify-content-between">
                            <div class="d-flex flex-row align-items-center justify-content-between">
                                <h5>Licensed profession</h5>
                                <Button v-if="isEditable" icon="pi pi-fw pi-plus" severity="secondary" label="Add" text size="small" class="fw-semibol" @click="()=>{userProfile.showExperianceEdit = false;router.push('/profileEdit/skillsInfo');}"/>
                            </div>
                        
                    </div>

                    <Divider />
                    <Chip  v-for=" (skill,index) in props.skills.licensed" :label="skill.skill" :key="index" :removable="isEditable" class="mx-2" v-if="showData" >
                        <template #removeicon="{ removeCallback, keydownCallback }">
                            <i  v-if="isEditable" class="pi pi-minus-circle" @click="deleteSkill(skill.id)" @keydown="keydownCallback" />
                        </template>
                    </Chip>
                    <div class="w-100 text-center" v-else>
                        Please wait ......
                    </div>
                </template>
            </Card>
            <Card class="mt-3">
                <template #content>
                     <div class="d-flex flex-row align-items-center justify-content-between">
                            <div class="d-flex flex-row align-items-center justify-content-between">
                                <h5>Technical and vocational certification</h5>
                                <Button v-if="isEditable" icon="pi pi-fw pi-plus" severity="secondary" label="Add" text size="small" class="fw-semibold" @click="()=>{userProfile.showExperianceEdit = false;router.push('/profileEdit/skillsInfo/1');}"/>
                            </div>
                        
                    </div>

                    <Divider />
                    <Chip  v-for=" (skill,index) in props.skills.vocational" :label="skill.skill" :key="index" :removable="isEditable" class="mx-2" v-if="showData" >
                        <template #removeicon="{ removeCallback, keydownCallback }">
                            <i  v-if="isEditable" class="pi pi-minus-circle" @click="deleteSkill(skill.id)" @keydown="keydownCallback" />
                        </template>
                    </Chip>
                    <div class="w-100 text-center" v-else>
                        Please wait ......
                    </div>
                </template>
            </Card>
            <Card class="mt-3">
                <template #content>
                     <div class="d-flex flex-row align-items-center justify-content-between">
                            <div class="d-flex flex-row align-items-center justify-content-between">
                                <h5>Recognized certification</h5>
                                <Button v-if="isEditable" icon="pi pi-fw pi-plus" severity="secondary" label="Add" text size="small" class="fw-semibol" @click="()=>{userProfile.showExperianceEdit = false;router.push('/profileEdit/skillsInfo/2');}"/>
                            </div>
                        
                    </div>

                    <Divider />
                    <Chip  v-for=" (skill,index) in props.skills.recognized" :label="skill.skill" :key="index" :removable="isEditable" class="mx-2" v-if="showData" >
                        <template #removeicon="{ removeCallback, keydownCallback }">
                            <i  v-if="isEditable" class="pi pi-minus-circle" @click="deleteSkill(skill.id)" @keydown="keydownCallback" />
                        </template>
                    </Chip>
                    <div class="w-100 text-center" v-else>
                        Please wait ......
                    </div>
                </template>
            </Card>
    </div>
</template>
<script lang="ts" setup>
import { ref, onMounted, type PropType, computed, watch } from 'vue';
import Chip from 'primevue/chip';
import Card from 'primevue/card';
import Menu from 'primevue/menu';
import Button from 'primevue/button';
import Divider from 'primevue/divider';
import Badge from 'primevue/badge';
import { useRouter } from 'vue-router';
import type {userGeneralInfoType} from '../../types/userGeneralInfoType';
import type { workExperianceType } from '@/types/workExperianceType';
import { useUserProfile } from '@/stores/User/userProfile';
import type  { educationType } from '../../types/educationType';
import showAlert from '@/composables/showAlert';

const router = useRouter();
const emit = defineEmits(['skilldeleted']);
const showMenuEd = ref< InstanceType<typeof Menu> | null>(null);
const userProfile = useUserProfile();
const showData = ref<boolean>(true);
const props = defineProps({
    generalInfo:{
        type:Object as PropType<userGeneralInfoType>,
        default:{}
    },
    workExperiance:{
        type:Array as PropType<Array<workExperianceType>>,
        default:{}
    },
    educationDetails:{
        type:Array as PropType<Array<educationType>>,
        default:{}
    },
    skills:{
        type: Object,
        default:{}
    },
    achievements:{
        type:Array as PropType<Array<{ id:number; title:string; category:string }>>,
        default:() => []
    },
    isEditable:{
        type:Boolean,
        default:true
    },
    from:{
        type:String,
        default:'Other'
    }
});
const isEditable = computed(()=> props.isEditable);
const generalInfo = computed(()=> props.generalInfo);
const workExperiance = computed(() => props.workExperiance);
const educationDetails = computed(() => props.educationDetails);
const from = computed(()=> props.from);
const showLength = ref<number>(0);
const showLengthEdu = ref<number>(0);

watch(workExperiance, (newValue, oldValue) => {
  if(newValue.length < 3 ){
    showLength.value = newValue.length
  }else{
    showLength.value = 3
  }
});

watch(educationDetails, (newValue, oldValue) => {
  if(newValue.length < 3 ){
    showLengthEdu.value = newValue.length
  }else{
    showLengthEdu.value = 3
  }
});

watch(isEditable,(newValue, oldValue) =>{
    userProfile.setIsEdit(newValue);
});

const goToEdit = (id:number) =>
{
    userProfile.showExperianceEdit = true;
    router.push(`/profileEdit/workExperience/${id}`);
}

const deleteSkill = async(id:number) => {
     let config ={
                icon:'warning',
                title:'Warning',
                text: 'Are you sure? Delete this Skill',
                confirmButtonText: 'OK',
                confirmButtonColor: '#a03829',
                showConfirmButton:true,
                showCancelButton:true
            }
            
        let confirm = await showAlert(config);

        if(confirm.isConfirmed)
        {
            showData.value = false;
            userProfile.skill_id = id;
            let result = await userProfile.deleteSkill();
            if(result.code == 200)
            {
                let config ={
                    icon:'success',
                    title:'Success',
                    text: result.message,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#a03829',
                    showConfirmButton:false,
                    timer: 3000
                }
                let confirm = await showAlert(config);

                if(confirm.isDismissed){
                    emit('skilldeleted');
                    showData.value = true;
                    
                }
            }
        }
}

const canShow = (field:string) =>
{
    let user = localStorage.getItem('userData');
    let userData: any = {}
    let privacy = generalInfo.value.visibility[field]
        if(!privacy) return true;

    if(user)
    {
        userData = JSON.parse(user); 
    }

    if(userData.id == generalInfo.value.id) return true;

    return privacy === 'Public'
}

</script>