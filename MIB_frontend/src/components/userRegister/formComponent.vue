<template>
<form class="">
    <div class="row align-items-center justify-content-center flex-grow-1">
        <div class="d-flex flex-column gap-4 col-md-6 col-sm-12">
            <h4 class="fw-bold">Sign Up to Myintelbook</h4>
            
                <InputText v-model="userRegister.email"  placeholder="Email" size="normal" id="userEmail"/>
                <InputGroup>
                    <InputText :type="password" v-model="userRegister.password" placeholder="Password" id="userPassword" @focus="toggle"/>
                    <InputGroupAddon><i :class="iconString" @click="showPassword('password')" id="showPassword"></i></InputGroupAddon>
                </InputGroup>

                <InputGroup>
                    <InputText :type="confirmPassword" v-model="userRegister.password_confirmation" placeholder="Confirm Password" id="userConfirmPassword"/>
                    <InputGroupAddon><i :class="conIconString" @click="showPassword('con-password')" id="showConfirmPassword"></i></InputGroupAddon>
                </InputGroup>
                <PasswordValidation :password="userRegister.password"  ref="UserRegistration"/>
                <p style="font-size:14px;">By clicking Agree and Join, you agreed to the Myintelbook <span style="color:#a03829;">Privacy Policy</span> and <span style="color:#a03829;">Cookie Policy.</span></p>
                <p style="font-size:14px;">Already have an account? <router-link to="/login" style="color:#a03829;">Log in</router-link></p>
                <Button :label="submitButtonLabel" class="w-100" style="background-color:#a03829;" size="normal" @click="submitUserData" id="register">
                    <template #icon>
                    <i class="pi pi-spin pi-spinner" style="font-size: 1rem" v-if="submitData"></i>
                    </template>
                </Button>
                 
        </div>
    </div>
</form>
</template>
<script setup lang="ts">
import { ref, defineEmits, onMounted } from 'vue';
import type userRegisterType from '@/types/userRegisterType';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import InputGroup from 'primevue/inputgroup';
import InputGroupAddon from 'primevue/inputgroupaddon';
import Button from 'primevue/button';
import showAlert from '@/composables/showAlert';
import { useUserStore } from '@/stores/User/userStore';
import { useRouter } from 'vue-router'
import  PasswordValidation  from '@/components/PasswordValidation.vue';


const emits = defineEmits(['submitUserData']);
const userStore = useUserStore();
const submitButtonLabel = ref<string>('Agree and Join');
const router = useRouter()
const userRegister = ref<userRegisterType>({
    email: '',
    password: '',
    password_confirmation: '',
});

const password = ref<string>('password');
const confirmPassword = ref<string>('password');
const iconString = ref<string>('bi bi-eye-slash');
const conIconString = ref<string>('bi bi-eye-slash');
const submitData = ref<boolean>(false);
const UserRegistration = ref< InstanceType<typeof PasswordValidation>| null>(null)

const showPassword = (type: string) => {
    if(type === 'password'){
        password.value = password.value === 'password' ? 'text' : 'password';
        iconString.value = password.value === 'password' ? 'bi bi-eye-slash' : 'bi bi-eye';
    }else{
        confirmPassword.value = confirmPassword.value === 'password' ? 'text' : 'password';
        conIconString.value = confirmPassword.value === 'password' ? 'bi bi-eye-slash' : 'bi bi-eye';
    }
};

const toggle = (event:any) => {
    if(UserRegistration.value)
    {
        UserRegistration.value.toggle(event);
    }
}

const submitUserData = async() => {

userStore.userData =userRegister.value;
submitButtonLabel.value = 'please wait...';
submitData.value = true;
let result = await userStore.submitUserData();

    if(result.code  === 200){
        let config ={
                    icon:'success',
                    title:'Success',
                    text: result.message,
                    confirmButtonColor: '#a03829',
                    showConfirmButton:false,
                    timer:3000
                }
        let confirm = await showAlert(config);

        // if(confirm.isConfirmed){
           submitButtonLabel.value = 'Agree and Join';
           router.push({ name: 'EmailConfirmation' })
           submitData.value = false;
        // }
        
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
           submitButtonLabel.value = 'Agree and Join';
           submitData.value = false;
        }
    }
}

</script>