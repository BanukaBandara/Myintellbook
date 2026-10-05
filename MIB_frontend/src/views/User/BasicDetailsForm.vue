<template>
    <div class="flex-grow-1 justify-content-center align-items-center image-section overflow-auto row">
            <Panel
            
            :pt="{
                root: 'col-md-4',
                header: '',
            }">
                <template #header>
                <h4 >Personal Details <i class="pi pi-check" style="font-size: 1rem" v-if="submitData" v-tooltip="'Enter your username'"></i></h4>
                
                </template>
                <template #icons>
                    <Button icon="pi pi-user" severity="secondary" rounded text  />
                    <Menu ref="menu" id="config_menu"  popup />
                </template>
                 <h6 class="mt-3 m-0">Your Basics</h6>
                 <div class="row">
                    <InputText  v-model="UserDetails.first_name" placeholder="First Name" size="normal" id="first_name" class="col-md-6 col-sm-12 mt-3 "/>
                    <InputText  v-model="UserDetails.last_name" placeholder="Last Name" size="normal" id="last_name" class="col-md-6 col-sm-12 mt-3"/>
                    <Select v-model="SelectedGender" :options="gender" optionLabel="name" placeholder="Select a Gender" class="w-100 mt-3" id="gender"/>
                    <label class="mb-2 mt-2">Birth Date</label>
                    <div class="d-flex flex-row p-0">
                        <Select v-model="year" :options="listOfYears" placeholder="Year" optionLabel="label" value="code" inputId="SelectedGender" class="w-100" id="SelectedGender" size="small"/>
                        <Select v-model="month" :options="listOfMonths" placeholder="Month" optionLabel="label" value="code" inputId="SelectedGender" class="w-100" id="SelectedGender" size="small"/>
                        <Select v-model="day" :options="listOfDays" placeholder="Day" optionLabel="label" value="code" inputId="SelectedGender" class="w-100" id="SelectedGender" size="small"/>
                    </div>
                    <h6 class="mt-3 m-0">Your Profession</h6>
                    <Select v-model="SelectedCategory" :options="Categories" optionLabel="name" placeholder="Select a Category" class="col-md-6 mt-3" id="category" @click="fetchCategories"  @change="fetchProfession"/>
                    <Select v-model="SelectedProfession" :options="Professions" optionLabel="name" placeholder="Select a Profession" class="col-md-6 mt-3" id="profession"/>
                    
                    <p class="mt-3"> Enter your details to get started.</p>

                    <Button :label="DetailsButtonLabel" class="w-100" style="background-color:var(--ds-primary-hover);" size="normal" @click="submitUserData" id="submitDetails">
                        <template #icon>
                            <i class="pi pi-spin pi-spinner" style="font-size: 1rem" v-if="submitData"></i>
                        </template>
                    </Button>
                 </div>
            </Panel>
    </div>
</template>
<script lang="ts" setup>
import { ref, onMounted } from 'vue';
import Panel from 'primevue/panel';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Divider from 'primevue/divider';
import Tooltip from 'primevue/tooltip';
import type {basicUserDetails} from '../../types/basicUserDetails';
import Menu from 'primevue/menu';
import { useUserProfile } from '@/stores/User/userProfile';
import showAlert from '@/composables/showAlert';
import RadioButton from 'primevue/radiobutton';
import {getCategory} from '@/composables/getCategory';
import type {professionCategoryType} from '../../types/professionCategoryType';
import { useRouter } from 'vue-router';
import {listOfYears, listOfMonths, listOfDays} from '@/services/years';


const router = useRouter();
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
    value:0
});
const SelectedCategory = ref({
    name:'',
    id:0
});
const SelectedProfession =  ref({
    name:'',
    category_id:0,
    id:0
});
const UserDetails = ref<basicUserDetails>({
    first_name: '',
    last_name: '',
    gender: 0,
    category:0,
    profession_id: 0,
    birth_date:'',
    profile_image:''
});

const { CategoriesList, getProfessions } = getCategory();

const Categories = ref<Array<professionCategoryType>>([]);
const Professions = ref([]);

const fetchCategories = async() => {
    let result = await CategoriesList();
    Categories.value = result.data;
}

const fetchProfession = async() => {
    let result = await getProfessions(SelectedCategory.value.id);
    Professions.value = result.data;
}


const gender = ref([
    {name: 'Male',value:1},
    {name: 'Female',value:2},
    {name: 'Other',value:3},
]);



const DetailsButtonLabel = ref<string>('Submit and Continue');
const submitData = ref<boolean>(false);
const userProfile = useUserProfile();
const submitUserData = async() =>{
    DetailsButtonLabel.value = 'please wait...';
    submitData.value = true;
    UserDetails.value.profession_id = SelectedProfession.value.id;
    UserDetails.value.category= SelectedCategory.value.id;
    UserDetails.value.gender = SelectedGender.value.value;
    UserDetails.value.birth_date = year.value.code+"-"+month.value.code+"-"+day.value.code;
    userProfile.userDetails = UserDetails.value;

    let result = await userProfile.submitUserDetails();
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
            DetailsButtonLabel.value = 'Submit and Continue';
            submitData.value = false;
            router.push({ name: 'home' });
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

onMounted(() => {

})
</script>
<style scoped>
.image-section{
    background-color:#ebe5d4;
    color:var(--ds-primary-hover);
}
</style>