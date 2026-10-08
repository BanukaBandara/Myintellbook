<template>
        <div class="row gap-4 p-4 bg-card mt-4 flex-grow-1 overflow-auto rounded-4">
            <h5>Login Information</h5>
            <FloatLabel variant="on" class="p-0 col-md-9">
                <InputGroup>
                    <InputText :type="password"  id="userPassword"/>
                    <InputGroupAddon><i :class="iconString" @click="showPassword('password')" id="showPassword"></i></InputGroupAddon>
                </InputGroup>
                <label for="School">Current Password</label>
            </FloatLabel>

            <router-link to="/password/reset" style="color:#a03829;text-decoration: none;" >Forgot Password?</router-link> 

            <informationShow message="Create a new password that is at least 8 characters long. and Strong one" />

             <!-- <InputGroup :pt="{root:'col-md-8'}"> 
                    <InputGroupAddon :pt="{
                        root:'bg-primary'
                    }">
                        <i class="pi pi-info rounded-circle bg-light p-1 text-dark fs-6"></i>
                    </InputGroupAddon>
                    <InputText  disabled :pt="{ root:{style:'background-color:#ffff'}}" placeholder="Create a new password that is at least 8 characters long. and Strong one" />
            </InputGroup> -->

            <FloatLabel variant="on" class="p-0 col-md-9">
                <InputGroup>
                    <InputText :type="password"  id="userPassword"/>
                    <InputGroupAddon><i :class="iconString" @click="showPassword('password')" id="showPassword"></i></InputGroupAddon>
                </InputGroup>
                <label for="School">Add Your New Password</label>
            </FloatLabel>
            <FloatLabel variant="on" class="p-0 col-md-9">
                <InputGroup>
                    <InputText :type="password"  id="userPassword"/>
                    <InputGroupAddon><i :class="iconString" @click="showPassword('password')" id="showPassword"></i></InputGroupAddon>
                </InputGroup>
                <label for="School">Repeat Your New Password</label>
            </FloatLabel>
                <Button label="Save" icon="pi pi-save" severity="secondary" size="small" class="fw-semibold col-md-3" />
        </div> 
</template>
<script lang="ts" setup>
import { ref } from 'vue';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import FloatLabel from 'primevue/floatlabel';
import InputGroup from 'primevue/inputgroup';
import InputGroupAddon from 'primevue/inputgroupaddon';
import informationShow from '../../components/commonComponents/infomationShow.vue';


const password = ref<string>('password');
const iconString = ref<string>('bi bi-eye-slash');

const showPassword = (type: string) => {
    if(type === 'password'){
        password.value = password.value === 'password' ? 'text' : 'password';
        iconString.value = password.value === 'password' ? 'bi bi-eye-slash' : 'bi bi-eye';
    }
};
</script>