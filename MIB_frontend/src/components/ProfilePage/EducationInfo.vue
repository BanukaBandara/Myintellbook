<template>
        <div class="row gap-4 p-4 bg-card mt-4 flex-grow-1 overflow-auto rounded-4">
            <div class="d-flex justify-content-between align-items-center">
                <h5>Education Information</h5>
                <Button icon="pi pi-trash" severity="danger" size="small" class="fw-semibold col-md-3" v-if="showDelete" @click="confirmDelete"/>
            </div>
            
            <FloatLabel variant="on" class="p-0 col-md-5">
                <InputText  inputId="School"  v-model="educationDetails.school" id="FirstName" size="small" class="w-100"/>
                <label for="School">School</label>
            </FloatLabel>
             <FloatLabel variant="on" class="p-0 col-md-6">
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
            <FloatLabel variant="on" class="p-0 col-md-5">
                <InputText inputId="Degree" v-model="educationDetails.degree" class="w-100" size="small" />
                <label for="Degree">Degree</label>
            </FloatLabel>
            <FloatLabel variant="on" class="p-0 col-md-6">
                <InputText inputId="study" v-model="educationDetails.field_of_study" class="w-100" size="small" />
                <label for="study">Field Of Study</label>
            </FloatLabel>
                <Button label="Save" icon="pi pi-save" severity="secondary" size="small" class="fw-semibold col-md-3" @click="submitData">
                    <i class="pi pi-spin pi-spinner" v-if="(btnName == 'Please wait .....')" />
                    <i class="pi pi-save" v-else />
                    <label class="fw-semibold">{{btnName}}</label>
                </Button>
        </div> 
</template>
<script lang="ts" setup>
import { ref, computed, onMounted } from 'vue';
import DatePicker from 'primevue/datepicker';
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