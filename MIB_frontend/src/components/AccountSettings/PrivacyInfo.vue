<template>
        <div class="row gap-4 bg-card p-5 mt-4 flex-grow-1 overflow-auto rounded-4">
            <h5>Profile Visibility Settings</h5>

            <informationShow message="Select who may see your profile details." borderClass="border-primary" bgClass="bg-primary"/>
            <div class="border row p-4">
                    <h6>General Information</h6>
                    <div class="row">
                        <div class="p-2 d-flex align-items-center col-md-6">
                            First Name
                        </div>
                        <div class="col-md-6 p-2">
                            <Select v-model="settings.first_name" :options="cities" optionLabel="name" placeholder="" size="small" class="w-100" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="p-2 d-flex align-items-center col-md-6">
                            Last Name
                        </div>
                        <div class="col-md-6 p-2">
                            <Select v-model="settings.last_name" :options="cities" optionLabel="name" placeholder="" size="small" class="w-100" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="p-2 d-flex align-items-center col-md-6">
                            Birth Date
                        </div>
                        <div class="col-md-6 p-2">
                            <Select v-model="settings.birth_date" :options="cities" optionLabel="name" placeholder="" size="small" class="w-100" />
                        </div>
                    </div>
                     <div class="row">
                        <div class="p-2 d-flex align-items-center col-md-6">
                            Gender
                        </div>
                        <div class="col-md-6 p-2">
                            <Select v-model="settings.gender" :options="cities" optionLabel="name" placeholder="" size="small" class="w-100" />
                        </div>
                    </div>
            </div>
            <div class="border row p-4">
                    <h6>Work Experience</h6>
                    <div class="row">
                        <div class="p-2 d-flex align-items-center col-md-6">
                            Title
                        </div>
                        <div class="col-md-6 p-2">
                            <Select v-model="settings.title" :options="cities" optionLabel="name" placeholder="" size="small" class="w-100" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="p-2 d-flex align-items-center col-md-6">
                            Company Or Organization
                        </div>
                        <div class="col-md-6 p-2">
                            <Select v-model="settings.organization" :options="cities" optionLabel="name" placeholder="" size="small" class="w-100" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="p-2 d-flex align-items-center col-md-6">
                            Location
                        </div>
                        <div class="col-md-6 p-2">
                            <Select v-model="settings.location" :options="cities" optionLabel="name" placeholder="" size="small" class="w-100" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="p-2 d-flex align-items-center col-md-6">
                            Employment Type
                        </div>
                        <div class="col-md-6 p-2">
                            <Select v-model="settings.employment_type" :options="cities" optionLabel="name" placeholder="" size="small" class="w-100" />
                        </div>
                    </div>
                     <div class="row">
                        <div class="p-2 d-flex align-items-center col-md-6">
                            Location Type
                        </div>
                        <div class="col-md-6 p-2">
                            <Select v-model="settings.location_type" :options="cities" optionLabel="name" placeholder="" size="small" class="w-100" />
                        </div>
                    </div>
            </div>
            <div class="border row p-4">
                    <h6>Education Information</h6>
                    <div class="row">
                        <div class="p-2 d-flex align-items-center col-md-6">
                            School
                        </div>
                        <div class="col-md-6 p-2">
                            <Select v-model="settings.school" :options="cities" optionLabel="name" placeholder="" size="small" class="w-100" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="p-2 d-flex align-items-center col-md-6">
                            Degree
                        </div>
                        <div class="col-md-6 p-2">
                            <Select v-model="settings.degree" :options="cities" optionLabel="name" placeholder="" size="small" class="w-100" />
                        </div>
                    </div>
                    <div class="row">
                        <div class="p-2 d-flex align-items-center col-md-6">
                            Field Of Study
                        </div>
                        <div class="col-md-6 p-2">
                            <Select v-model="settings.field_of_study" :options="cities" optionLabel="name" placeholder="" size="small" class="w-100" />
                        </div>
                    </div>
            </div>
            <div class="border row p-4">
                    <h6>Skill Information</h6>
                    <div class="row">
                        <div class="p-2 d-flex align-items-center col-md-6">
                            Skills
                        </div>
                        <div class="col-md-6 p-2">
                            <Select v-model="settings.skills" :options="cities" optionLabel="name" placeholder="" size="small" class="w-100" />
                        </div>
                    </div>
            </div>
            <Button label="Save" icon="pi pi-save" severity="secondary" size="small" class="fw-semibold col-md-3" @click="submitSettings" />
        </div> 
</template>
<script lang="ts" setup>
import { onMounted, ref } from 'vue';
import Button from 'primevue/button';
import Select from 'primevue/select';
import informationShow from '../../components/commonComponents/infomationShow.vue';
import { useUserStore } from '@/stores/User/userStore';
import showAlert from '@/composables/showAlert';

const settings = ref({
    'first_name':{'name':''},
    'last_name':{'name':''},
    'birth_date':{'name':''},
    'gender':{'name':''},
    'title':{'name':''},
    'organization':{'name':''},
    'location':{'name':''},
    'employment_type':{'name':''},
    'location_type':{'name':''},
    'school':{'name':''},
    'degree':{'name':''},
    'field_of_study':{'name':''},
    'skills':{'name':''}
});
const userStore = useUserStore();
const selectedCity = ref();
const cities = ref([
    { name: 'Public'},
    { name: 'Only Me'},
]);

const getSettings =async() =>{

    let result = await userStore.getUserSettings();

    settings.value = result.data
}


const formatSettings = ()=>{
    return {
        'first_name':settings.value.first_name.name || '',
        'last_name':settings.value.last_name.name || '',
        'birth_date':settings.value.birth_date.name || '',
        'gender':settings.value.gender.name || '',
        'title':settings.value.title.name || '',
        'organization':settings.value.organization.name || '',
        'location':settings.value.location.name || '',
        'employment_type':settings.value.employment_type.name || '',
        'location_type':settings.value.location_type.name || '',
        'school':settings.value.school.name || '',
        'degree':settings.value.degree.name || '',
        'field_of_study':settings.value.field_of_study.name || '',
        'skills':settings.value.skills.name || ''
    }
}

const submitSettings = async() =>
{
    console.log(settings.value);
    let result = await userStore.submitSettings(formatSettings());

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
    }
}

onMounted(async()=>{
   await  getSettings();
})
</script>