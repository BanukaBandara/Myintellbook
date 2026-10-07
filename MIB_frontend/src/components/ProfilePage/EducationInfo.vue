<template>
        <section class="pe-card">
            <header class="pe-card-header">
                <div>
                    <h2 class="pe-title">Education Information</h2>
                    <p class="pe-subtitle">Add a school, degree or qualification.</p>
                </div>
                <Button v-if="showDelete" class="pe-delete" aria-label="Delete this education entry" @click="confirmDelete">
                    <Trash2 :size="16" aria-hidden="true" />
                </Button>
            </header>

            <div class="pe-grid">
                <div class="pe-field">
                    <FloatLabel variant="on">
                        <InputText v-model="educationDetails.school" id="School" size="small" class="w-100"/>
                        <label for="School">School</label>
                    </FloatLabel>
                </div>
                <div class="pe-field">
                    <FloatLabel variant="on">
                        <Select
                            v-model="educationDetails.degree_category"
                            :options="degreeCategories"
                            optionLabel="label"
                            optionValue="value"
                            class="w-100"
                            inputId="degreeCategory"
                            size="small"
                        />
                        <label for="degreeCategory">Degree Category</label>
                    </FloatLabel>
                </div>
                <div class="pe-field">
                    <FloatLabel variant="on">
                        <InputText v-model="educationDetails.degree" id="Degree" size="small" class="w-100" />
                        <label for="Degree">Degree</label>
                    </FloatLabel>
                </div>
                <div class="pe-field">
                    <FloatLabel variant="on">
                        <InputText v-model="educationDetails.field_of_study" id="study" size="small" class="w-100" />
                        <label for="study">Field of Study</label>
                    </FloatLabel>
                </div>
            </div>

            <footer class="pe-actions">
                <Button class="pe-save" @click="submitData">
                    <Loader2 v-if="btnName == 'Please wait .....'" :size="16" class="pe-spin" aria-hidden="true" />
                    <Save v-else :size="16" aria-hidden="true" />
                    <label>{{ btnName }}</label>
                </Button>
            </footer>
        </section>
</template>
<script lang="ts" setup>
import { ref, computed, onMounted } from 'vue';
import { Loader2, Save, Trash2 } from 'lucide-vue-next';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import FloatLabel from 'primevue/floatlabel';
import type  { educationType } from '../../types/educationType';
import {useUserProfile} from '../../stores/User/userProfile';
import showAlert from '../../composables/showAlert';
import {useRouter, useRoute} from 'vue-router';

const userProfile = useUserProfile();
const route = useRoute();
const showDelete = computed(()=>route.params.id)
const router = useRouter();
const btnName = ref<string>('Save');
const educationDetails = ref<educationType>({
    school:'',
    degree:'',
    field_of_study:'',
    degree_category:''
});
const degreeCategories = [
  { label: 'Diploma', value: 'Diploma' },
  { label: "Bachelor's", value: 'Bachelors' },
  { label: "Master's", value: 'Masters' },
  { label: 'PhD', value: 'PhD' },
  { label: 'Certificate', value: 'Certificates' },
  { label: 'Other', value: 'Other' }
]

const submitData = async()=>
{
   if(showDelete.value)
   {
     editEducation();
   }else{
        submitEducationDetails();
        
   }
}

const submitEducationDetails = async() =>
{
    btnName.value = 'Please wait .....';
    userProfile.educationDetails = educationDetails.value;
   let result = await userProfile.submitEducationData();
   

   if(result.code == 200)
   {
         let config ={
                    icon:'success',
                    title:'Success',
                    text: result.message,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#a03829',
                    showConfirmButton:false,
                    timer:3000
                }
       let confirm = await showAlert(config);

       if(confirm.isDismissed){
        btnName.value = 'Save';
        router.push('/profile');
       }
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
        if(confirm.isConfirmed){
            btnName.value = 'Save';
        }
   }
}

const getEducationDetail = async() =>
{
    btnName.value = 'Please wait .....';
    userProfile.education_id = parseInt(Array.isArray(route.params.id) ? route.params.id[0] : route.params.id, 10);
    let result = await userProfile.getEducationDetail();
   
     if(result.code == 200)
   {
      educationDetails.value = result.data[0];
      educationDetails.value.degree_category = result.data[0].category
      btnName.value = 'Save';
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
        if(confirm.isConfirmed){
            btnName.value = 'Save';
        }
   }
}

const editEducation = async() =>
{
    btnName.value = 'Please wait .....';
    userProfile.educationDetails = educationDetails.value;
    let result = await userProfile.editEducation();

    if(result.code == 200)
   {
      let config ={
                    icon:'success',
                    title:'Success',
                    text: result.message,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#a03829',
                    showConfirmButton:true
                }
                let confirm = await showAlert(config);
                if(confirm.isConfirmed){
                    btnName.value = 'Save';
                    router.push('/profile')
                }
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
        if(confirm.isConfirmed){
            btnName.value = 'Save';
        }
   }
}

const confirmDelete = async() =>
{
    let config ={
                icon:'warning',
                title:'Warning',
                text: 'Are you sure? Delete this work Education',
                confirmButtonText: 'OK',
                confirmButtonColor: '#a03829',
                showConfirmButton:true,
                showCancelButton:true
            }
            
        let confirm = await showAlert(config);

        if(confirm.isConfirmed)
        {
            let result = await userProfile.deleteEducation();

            if(result.code == 200){
                let config ={
                    icon:'success',
                    title:'Success',
                    text: result.message,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#a03829',
                    showConfirmButton:true
                }
                let confirm = await showAlert(config);

                if(confirm.isConfirmed){
                    btnName.value = 'Save';
                    router.push('/profile')
                }
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
}

onMounted(async()=>{
    if(showDelete.value)
    {
     await getEducationDetail();
    }

});
</script>