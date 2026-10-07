<template>
        <section class="pe-card">
            <header class="pe-card-header">
                <div>
                    <h2 class="pe-title">Work Experience</h2>
                    <p class="pe-subtitle">Add a role you hold or have held.</p>
                </div>
                <Button v-if="showDelete" class="pe-delete" aria-label="Delete this work experience" @click="confirmDelete">
                    <Trash2 :size="16" aria-hidden="true" />
                </Button>
            </header>

            <SwitchField
                v-model="currentlyWorking"
                label="I am currently working in this role"
                @change="setCurrentlyWork"
            />

            <div class="pe-grid">
                <div class="pe-field">
                    <FloatLabel variant="on">
                        <InputText v-model="workExperiance.title" id="Title" size="small" class="w-100"/>
                        <label for="Title">Title</label>
                    </FloatLabel>
                    <Visibility field="title" @visibilityChange="visibilityChange"/>
                </div>
                <div class="pe-field">
                    <FloatLabel variant="on">
                        <InputText v-model="workExperiance.company" id="company" size="small" class="w-100"/>
                        <label for="company">Company or Organization</label>
                    </FloatLabel>
                    <Visibility field="company" @visibilityChange="visibilityChange" />
                </div>

                <div class="pe-field">
                    <FloatLabel variant="on">
                        <Select v-model="SelectEmpType" size="small" :options="empTypes" optionLabel="name" inputId="empType" class="w-100" />
                        <label for="empType">Employment Type</label>
                    </FloatLabel>
                    <Visibility field="empType" @visibilityChange="visibilityChange" />
                </div>
                <div class="pe-field">
                    <FloatLabel variant="on">
                        <Select v-model="SelectLocationType" size="small" :options="types" optionLabel="name" inputId="locationType" class="w-100" />
                        <label for="locationType">Location Type</label>
                    </FloatLabel>
                    <Visibility field="locationType" @visibilityChange="visibilityChange" />
                </div>

                <div class="pe-field">
                    <FloatLabel variant="on">
                        <DatePicker v-model="workExperiance.startingDate" inputId="startingDate" class="w-100" dateFormat="dd/mm/yy"/>
                        <label for="startingDate">Starting Date</label>
                    </FloatLabel>
                    <Visibility field="empType" @visibilityChange="visibilityChange" />
                </div>
                <div class="pe-field">
                    <FloatLabel variant="on">
                        <DatePicker v-model="workExperiance.endDate" inputId="endDate" class="w-100" dateFormat="dd/mm/yy" :disabled="isDisabled"/>
                        <label for="endDate">End Date</label>
                    </FloatLabel>
                    <Visibility field="empType" @visibilityChange="visibilityChange" />
                </div>

                <div class="pe-field">
                    <FloatLabel variant="on">
                        <InputText v-model="workExperiance.location" id="location" size="small" class="w-100"/>
                        <label for="location">Location</label>
                    </FloatLabel>
                    <Visibility field="location" @visibilityChange="visibilityChange" />
                </div>
                <div class="pe-field">
                    <FloatLabel variant="on">
                        <Select v-model="position" :options="positionType" optionLabel="name" size="small" inputId="positionType" class="w-100" />
                        <label for="positionType">Position Type</label>
                    </FloatLabel>
                    <Visibility field="locationType" @visibilityChange="visibilityChange" />
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
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import FloatLabel from 'primevue/floatlabel';
import Visibility from '../commonComponents/Visibility.vue';
import SwitchField from '../commonComponents/SwitchField.vue';
import { useRoute } from 'vue-router';
import showAlert from '@/composables/showAlert';
import type { workExperianceType } from '@/types/workExperianceType';
import { useUserProfile } from '@/stores/User/userProfile';
import { useRouter } from 'vue-router';
import DatePicker from 'primevue/datepicker';
import { Loader2, Save, Trash2 } from 'lucide-vue-next';

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

const setCurrentlyWork = ()=>
{
    workExperiance.value.currently_working = (currentlyWorking.value) ? 1 : 0;
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
                text: 'Are you sure? Delete this work experience',
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
