<template>
<div class="flex-grow-1 justify-content-center align-items-center image-section overflow-auto row">
    <Panel
        :pt="{
            root: 'col-md-4',
            header: '',
        }">
        <template #header>
           <h4>Forgot Password</h4>
           
        </template>
        <template #icons>
            <router-link to="/"><Button icon="pi pi-arrow-left" severity="secondary" rounded text /></router-link>
            <Menu ref="menu" id="config_menu"  popup />
        </template>
        <InputText  v-model="userEmail" placeholder="Email" size="normal" id="userEmail" class="w-100 mt-3"/>
        <p class="mt-3">We’ll send a verification link to this email if it matches an existing account.</p>
         <Button :label="passwordResetButtonLabel" class="w-100" style="background-color:#a03829;" size="normal" @click="sentPasswordResetLink" id="passwordReset">
            <template #icon>
                <i class="pi pi-spin pi-spinner" style="font-size: 1rem" v-if="submitData"></i>
            </template>
        </Button>
    </Panel>
</div>
</template>
<script lang="ts" setup>
import { ref, onMounted } from 'vue';
import Panel from 'primevue/panel';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import { useUserStore } from '@/stores/User/userStore';
import showAlert from '@/composables/showAlert';
import Menu from 'primevue/menu';
import { useRouter } from 'vue-router';

const router = useRouter();
const passwordResetButtonLabel = ref<string>('Send Verification Link');
const userEmail = ref<string>('');
const userStore = useUserStore();
const submitData = ref<boolean>(false);

const sentPasswordResetLink = async() =>{
    passwordResetButtonLabel.value = 'please wait...';
    submitData.value = true;
    userStore.email = userEmail.value;
    let result = await userStore.resetPassword();
    if(result.code == 200){

        let config ={
                    icon:'success',
                    title:'Success',
                    text: result.message+"This link will expire in 60 minutes.",
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#a03829',
                    showConfirmButton:true
                }
       let confirm = await showAlert(config);

        if(confirm.isConfirmed){
            passwordResetButtonLabel.value = 'Send Verification Link';
            submitData.value = false;
            router.push('/login')
        }
    }
    else{
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
            passwordResetButtonLabel.value = 'Send Verification Link';
            submitData.value = false;
        }
    }
    userEmail.value = '';
    
}
const setNavBarShow = ()=>
{
    userStore.isShowNavBar = false
}

onMounted(()=>{
    setNavBarShow();
})
</script>
<style scoped>
.image-section{
    background-color:#ebe5d4;
    color:#a03829;
}
</style>