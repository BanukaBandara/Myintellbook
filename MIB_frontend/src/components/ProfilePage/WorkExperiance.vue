<template>
   
        <div class="row gap-4 p-4 bg-card mt-4 flex-grow-1 overflow-auto rounded-4">
            <div class="d-flex justify-content-between align-items-center">
                <h5>Work Experiance</h5>
                <Button icon="pi pi-trash" severity="danger" size="small" class="fw-semibold col-md-3" v-if="showDelete" @click="confirmDelete"/>
            </div>
           
            <div class="p-0 d-flex flex-row align-items-center gap-2">
                 <ToggleSwitch v-model="currentlyWorking" @change="setCurrentlyWork">
                    <template #handle="{ checked }">
                        <i :class="['!text-xs pi', { 'pi-check': checked, 'pi-times': !checked }]" />
                    </template>
                </ToggleSwitch>
                <label for="ingredient1"> I am currently working in this role </label>
            </div>
            <div class="d-flex flex-row gap-2">
                <div class="col-md-6">
                    <FloatLabel variant="on" class="p-0 w-100">
                    <InputText v-model="workExperiance.title" inputId="Title" id="FirstName" size="small" class="w-100"/>
                    <label for="Title">Title</label>
                    </FloatLabel>
                    <Visibility field="title" @visibilityChange="visibilityChange"/>
                </div>
                <div class="col-md-6">
                    <FloatLabel variant="on" class="p-0 w-100">
                    <InputText  v-model="workExperiance.company" inputId="company" id="FirstName" size="small" class="w-100"/>
                    <label for="company">Company Or Organization</label>
                    </FloatLabel>
                    <Visibility field="company" @visibilityChange="visibilityChange" />
                </div>
            </div>
            
            <div class="d-flex flex-row gap-2">
               <div class="col-md-6">
                 <FloatLabel variant="on" class="p-0 w-100">
                    <Select v-model="SelectEmpType" size="small" :options="empTypes" optionLabel="name" inputId="SelectedGender" class="w-100" />
                    <label for="SelectedGender">Employment Type</label>
                </FloatLabel>
                <Visibility field="empType" @visibilityChange="visibilityChange" />
               </div>
                <div class="col-md-6">
                    <FloatLabel variant="on" class="p-0 w-100">
                    <Select v-model="SelectLocationType" size="small" :options="types" optionLabel="name" inputId="locatoinType" class="w-100" />
                    <label for="locatoinType">Location Type</label>
                    </FloatLabel>
                    <Visibility field="locationType" @visibilityChange="visibilityChange" />
                </div>
            </div>

            <div class="d-flex flex-row gap-2">
               <div class="col-md-6">
                 <FloatLabel variant="on" class="p-0 w-100">
                    <DatePicker v-model="workExperiance.startingDate"  class="w-100" dateFormat="dd/mm/yy"/>
                    <label for="SelectedGender">Starting Date</label>
                </FloatLabel>
                <Visibility field="empType" @visibilityChange="visibilityChange" />
               </div>

                <div class="col-md-6" >
                    <FloatLabel variant="on" class="p-0 w-100">
                    <DatePicker v-model="workExperiance.endDate" class="w-100" dateFormat="dd/mm/yy" :disabled="isDisabled"/>
                    <label for="SelectedGender">End Date</label>
                    </FloatLabel>
                    <Visibility field="empType" @visibilityChange="visibilityChange" />
                </div>
            </div>
            
            <div class="d-flex flex-row gap-2">
                <div class="col-md-6">
                    <FloatLabel variant="on" class="p-0 w-100">
                    <InputText  inputId="location" v-model="workExperiance.location" id="FirstName" size="small" class="w-100"/>
                    <label for="location">Location</label>
                    </FloatLabel>
                    <Visibility field="location" @visibilityChange="visibilityChange" />
                </div>
                <div class="col-md-6">
                    <FloatLabel variant="on" class="p-0 w-100">
                    <Select v-model="position" :options="positionType" optionLabel="name" size="small" inputId="locatoinType" class="w-100" />
                    <label for="locatoinType">Position Type</label>
                    </FloatLabel>
                    <Visibility field="locationType" @visibilityChange="visibilityChange" />
                </div>
            </div>
            
            <Button label="Save" icon="pi pi-save" severity="secondary" size="small" class="fw-semibold col-md-3" @click="submitData" >
                 <i class="pi pi-spin pi-spinner" v-if="(btnName == 'Please wait .....')" />
                 <i class="pi pi-save" v-else />
                <label class="fw-semibold">{{ btnName }}</label>
            </Button>
            
        </div>
    
</template>
<script lang="ts" setup>
import { ref, computed, onMounted, watch } from 'vue';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import FloatLabel from 'primevue/floatlabel';
import Checkbox from 'primevue/checkbox';
import Visibility from '../commonComponents/Visibility.vue';
import { useRoute } from 'vue-router';
import showAlert from '@/composables/showAlert';
import type { workExperianceType } from '@/types/workExperianceType';
import { useUserProfile } from '@/stores/User/userProfile';
import { useRouter } from 'vue-router';
import DatePicker from 'primevue/datepicker';
import ToggleSwitch from 'primevue/toggleswitch';

const route = useRoute();
const userProfile = useUserProfile();
const router = useRouter();
const btnName = ref<string>('Save');
const showDelete = computed(() => route.params.id);
const workExperiance = ref<workExperianceType>({
    title:'',
    company:'',
    currently_working:0,
    location:'',
    selectEmpType:0,
    locationType:0,
    startingDate:null,
    endDate:null,
    position:'',
    visibility:{}
});
const SelectEmpType = ref({
    name:'',
    id:0
});
const SelectLocationType = ref({
    name:'',
    id:0
});

const position = ref({
    name:'',
    id:''
});
const currentlyWorking = ref(false);
const isDisabled = computed(()=> currentlyWorking.value)
const dataSet = ref<Array<{field:string,value:number}>>([]);
const empTypes = ref([
    { name : 'Full Time', id: 1 },
    { name : 'Part Time', id: 2 },
    { name : 'Contract', id: 3 },
    { name : 'Internship', id: 4 },
]);
const types = ref([
    { name : 'Office', id: 1 },
    { name : 'Home', id: 2 },
]);

const positionType = ref([
    { name:"Executive", id:"executive"},
    {name:"Non Executive" , id:"non-executive"}
])

const setCurrentlyWork = (event:any)=>
{
    workExperiance.value.currently_working = (currentlyWorking.value) ? 1 : 0;
    console.log(workExperiance.value.currently_working)
}

const submitExperianceData = async() =>
{
    workExperiance.value.locationType = SelectLocationType.value.id;
    workExperiance.value.selectEmpType = SelectEmpType.value.id;
    workExperiance.value.position = position.value.id;

    userProfile.workExperiance = workExperiance.value;

    let result = await userProfile.addWorkExperiance();

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

const confirmDelete = async() => {
  let config ={
                icon:'warning',
                title:'Warning',
                text: 'Are you sure? Delete this work experiance',
                confirmButtonText: 'OK',
                confirmButtonColor: '#a03829',
                showConfirmButton:true,
                showCancelButton:true
            }
            
        let confirm = await showAlert(config);

        if(confirm.isConfirmed)
        {
            let result = await userProfile.deleteExperiance();

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

const visibilityChange = (value: {field:string,value:number}) =>
{
    dataSet.value.push(value);
    workExperiance.value.visibility=  Object.fromEntries(
    dataSet.value.map(item => [item.field, item.value])
    );
}

const submitData = () =>
{
    btnName.value = 'Please wait .....';
    if(showDelete.value)
    {
       editDetails(); 
    }else{
        submitExperianceData();
    }
}

const editDetails =async() =>
{
    workExperiance.value.locationType = SelectLocationType.value.id;
    workExperiance.value.selectEmpType = SelectEmpType.value.id;
    userProfile.workExperiance = workExperiance.value;
    workExperiance.value.position = position.value.id;

    let result = await userProfile.editExperiance();
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

const getDetails = async() =>
{
    btnName.value = 'Please wait .....';
    const idParam = Array.isArray(route.params.id) ? route.params.id[0] : route.params.id;
    userProfile.experiance_id = parseInt(idParam);

    let result = await userProfile.getExperianceDetails();

      if(result.code == 200)
    {
        workExperiance.value = result.data[0];
        SelectEmpType.value = empTypes.value.find((item)=>item.id === workExperiance.value.selectEmpType) || { name: '', id: 0 };
        SelectLocationType.value = types.value.find((item)=> item.id == workExperiance.value.locationType) || {name:'',id:0};
        currentlyWorking.value = (workExperiance.value.currently_working) ? true : false;
        position.value = positionType.value.find((item)=> item.id == result.data[0].positionType) || { name:'', id:''}
        workExperiance.value.startingDate = result.data[0].starting_date
        workExperiance.value.endDate = result.data[0].end_date
        btnName.value = 'Save';
    }else{
    let config ={
                    icon:'error',
                    title:'Error',
                    text: result.message,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#a03829',
                    showConfirmButton:false,
                    timer: 3000
                }
            
        let confirm = await showAlert(config);
        if(confirm.isDismissed){
            btnName.value = 'Save';
        }
   }

}

onMounted(async()=>{
    if(showDelete.value){
        await getDetails();
    }
});


</script>