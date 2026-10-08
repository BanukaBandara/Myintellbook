<template>
<form class="w-100">
    <div class="row align-items-center justify-content-center flex-grow-1">
        <div class="d-flex flex-column gap-4 col-md-6 col-sm-12">
            <h4 class="fw-bold">Sign In to Myintelbook</h4>
            
                <InputText v-model="userLogin.email"  placeholder="Email" size="normal" id="userEmail"/>
                <InputGroup>
                    <InputText :type="password" v-model="userLogin.password" placeholder="Password" id="userPassword"/>
                    <InputGroupAddon><i :class="iconString" @click="showPassword('password')" id="showPassword"></i></InputGroupAddon>
                </InputGroup>
        <div>
            <p style="font-size:14px;">By clicking Sign In, you agreed to the Myintelbook <span style="color:#a03829;">Privacy Policy</span> and <span style="color:#a03829;">Cookie Policy.</span></p>
            <div class="row">
                <p style="font-size:14px;" class="col-md-7">Don't have an account? <router-link to="/register" style="color:#a03829;">Sign Up</router-link></p>
                <p class="col-md-5">
                    <router-link to="/password/reset" style="color:#a03829;" >Forgot Password?</router-link>  
                </p>
            </div>
        </div>
                <Button :label="submitButtonLabel" class="w-100" style="background-color:#a03829;" size="normal" @click="submitUserData" id="login">
                    <template #icon>
                    <i class="pi pi-spin pi-spinner" style="font-size: 1rem" v-if="submitData"></i>
                    </template>
                </Button>
                
        </div>
    </div>
</form>
</template>
<script setup lang="ts">
import { ref, defineEmits } from 'vue';
import type userRegisterType from '@/types/userRegisterType';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import InputGroup from 'primevue/inputgroup';
import InputGroupAddon from 'primevue/inputgroupaddon';
import Button from 'primevue/button';
import showAlert from '@/composables/showAlert';
import { useUserStore } from '@/stores/User/userStore';
import { useRouter } from 'vue-router';

const router = useRouter();
const userStore = useUserStore();
const submitButtonLabel = ref<string>('Sign In');
const userLogin = ref<userRegisterType>({
    email: '',
    password: '',
});
const submitData = ref<boolean>(false);
const isLoggedIn = ref<boolean>(false);

const password = ref<string>('password');
const iconString = ref<string>('bi bi-eye-slash');

const showPassword = (type: string) => {
    if(type === 'password'){
        password.value = password.value === 'password' ? 'text' : 'password';
        iconString.value = password.value === 'password' ? 'bi bi-eye-slash' : 'bi bi-eye';
    }
};

const submitUserData = async() => {

userStore.userData =userLogin.value;
submitButtonLabel.value = 'please wait...';
submitData.value = true;
let result = await userStore.loginUser();

    if(result.code  === 200){
        localStorage.setItem('userToken', result.token);
        localStorage.setItem('userData', JSON.stringify(result.user));
        submitButtonLabel.value = 'Sign In';
        submitData.value = false;

        if (result.user?.is_jury_panel) {
            router.push('/jury');
            return;
        }

        let re = await userStore.getUserData();
        if(re.data != null){
            router.push('/home');
        }else{
            router.push('/basicDetails-fill')
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
           submitButtonLabel.value = 'Sign In';
           submitData.value = false;
        }
    }
}

</script>