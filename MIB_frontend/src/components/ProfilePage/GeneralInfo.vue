<template>
        
        <div class="row gap-4 p-4 bg-card mt-4 flex-grow-1 overflow-auto rounded-4">
            <div class="d-flex flex-row align-items-center justify-content-between">
                <h5>Edit General Information</h5>
            </div>
             <div class="d-flex flex-column"> 
                <FloatLabel variant="on" class="p-0 col-md-9">
                    <InputText v-model="userGeneralInfo.first_name" inputId="FirstName" id="FirstName" size="small" class="w-100"/>
                    <label for="FirstName">First Name</label>
                </FloatLabel>
               
                <Visibility field="first_name" @visibilityChange="visibilityChange" :visibility="userGeneralInfo.visibility.first_name" id="visiFirstName" />
            </div>
            <div class="d-flex flex-column"> 
                <FloatLabel variant="on" class="p-0 col-md-9">
                    <InputText v-model="userGeneralInfo.last_name" inputId="LastName" class="w-100" size="small"  id="LastName"/>
                    <label for="LastName">Last Name</label>
                </FloatLabel>
                <Visibility field="last_name" @visibilityChange="visibilityChange" :visibility="userGeneralInfo.visibility.last_name" id="visiLastName" />
            </div>
             <div class="d-flex flex-column"> 
                <FloatLabel variant="on" class="p-0 col-md-5">
                    <Select v-model="SelectedGender" :options="genders" placeholder="Select a Gender" optionLabel="name" value="id" inputId="SelectedGender" class="w-100" id="SelectedGender" />
                    <label for="SelectedGender">Select a Gender</label>
                </FloatLabel>
                <Visibility field="gender" @visibilityChange="visibilityChange" :visibility="userGeneralInfo.visibility.gender" id="visiSelectGender" />
             </div>
             <div class="d-flex flex-column"> 
                 <label class="mb-2">Birth Date</label>
                 <div class="d-flex flex-row">
                    <Select v-model="year" :options="listOfYears" placeholder="Year" optionLabel="label" value="code" inputId="SelectedGender" class="w-100" id="SelectedGender" size="small"/>
                 <Select v-model="month" :options="listOfMonths" placeholder="Month" optionLabel="label" value="code" inputId="SelectedGender" class="w-100" id="SelectedGender" size="small"/>
                 <Select v-model="day" :options="listOfDays" placeholder="Day" optionLabel="label" value="code" inputId="SelectedGender" class="w-100" id="SelectedGender" size="small"/>
                 </div>
                <!-- <FloatLabel variant="on" class="p-0 col-md-3">
                    <DatePicker v-model="userGeneralInfo.birth_date" inputId="over_label" showIcon iconDisplay="input" id="birthdate" />
                    <label for="over_label">Birth Date</label>
                </FloatLabel> -->
                <Visibility field="birth_date" @visibilityChange="visibilityChange" :visibility="userGeneralInfo.visibility.birth_date" id="visiBirthDate" />
            </div>
                <Button :label="SaveBtnName" icon="pi pi-save" id="saveData" severity="secondary" size="small" class="fw-semibold col-md-3" @click="saveGeneralInfo">
                    <i class="pi pi-spin pi-spinner" v-if="(SaveBtnName == 'Please wait .....')" />
                    <label class="fw-semibold">{{ SaveBtnName }}</label>
                    </Button>
        </div> 
</template>
<script lang="ts" setup>
import { ref,onMounted } from 'vue';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import FloatLabel from 'primevue/floatlabel';
import Visibility from '../commonComponents/Visibility.vue';
import type {userGeneralInfoType} from '../../types/userGeneralInfoType'
import { useUserProfile } from '@/stores/User/userProfile';
import showAlert from '@/composables/showAlert';
import {listOfYears, listOfMonths, listOfDays} from '@/services/years';

const userProfile = useUserProfile();
const SaveBtnName = ref<string>('Save');
const year = ref({
    label:'',
    code:0
});
const month = ref({
    label:'',
    code:''
});
const day = ref({
    label:'',
    code:0
});
const SelectedGender = ref({
    name:'',
    id:0
});
const userGeneralInfo = ref<userGeneralInfoType>({
   first_name: '',
    last_name: '',
    gender: 0,
    birth_date: '',
    profile_image:'',
    cover_image:'',
    school:'',
    total_points:0,
    rank:0,
    posts:[],
    visibility:{},
    profession:{
      company:'',
      location:'',
      profession:''
    },
    slug:''
 
});
const dataSet = ref<Array<{field:string,value:string}>>([]);

const getGeneralInfo = async() =>
{
    let result = await userProfile.getGeneralInfo();
    if(result.code == 200)
   {
        userGeneralInfo.value = result.data[0];
        SelectedGender.value = genders.value.find((item)=> item.id === userGeneralInfo.value.gender) || {name: '', id: 0 };
        let birthdate = userGeneralInfo.value.birth_date.split('-');
        year.value['label']= birthdate[0];
        year.value['code'] = parseInt(birthdate[0]);
        month.value['label']= birthdate[1];
        month.value['code'] = birthdate[1];
        day.value['label']= birthdate[2];
        day.value['code'] = parseInt(birthdate[2]);

        localStorage.setItem('visibility',JSON.stringify(userGeneralInfo.value.visibility))
                
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

const saveGeneralInfo = async() =>{
    userGeneralInfo.value.gender = SelectedGender.value.id;
    userProfile.userGeneralInfo = userGeneralInfo.value;
    SaveBtnName.value = 'Please wait .....';
    userGeneralInfo.value.birth_date = year.value.code+"-"+month.value.code+"-"+day.value.code;
   let result = await userProfile.editUserGeneralInfo();

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
        SaveBtnName.value = 'Save';
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
            SaveBtnName.value = 'Save';
        }
   }
    // userGeneralInfo.value.gender = userGeneralInfo.value.gender;
}
const visibilityChange = (value: {field:string,value:string}) =>
{
    dataSet.value.push(value);
    userGeneralInfo.value.visibility=  Object.fromEntries(
    dataSet.value.map(item => [item.field, item.value])
    );
}


onMounted(async()=>{
await getGeneralInfo();
})
 const genders = ref([
    { name: 'Male', id: 1 },
    { name: 'Female', id: 2 },
]);
</script>