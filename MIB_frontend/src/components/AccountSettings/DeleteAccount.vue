<template>
  <div class="bg-card p-4 mt-4 flex-grow-1 overflow-auto rounded-4 shadow-sm border border-light-subtle">
    <h5 class="text-danger mb-4">Deactivating or deleting your Myintellibook account</h5>
    <p class="text-muted mb-2">If you want to take a break from Myintellibook, you can temporarily deactivate this account. If you want to permanently delete your account, let us know.</p>
    <!-- Deactivate Section -->
    <div v-if="!isShow">
         <div class="p-3 mb-4 rounded-3 border d-flex justify-content-between align-items-start hover-bg-light">
            <div class="pe-3">
                <h5 class="mb-2 text-dark">Deactivate Account</h5>
                <p class="text-muted mb-0 small">
                Temporarily disable your account. Your profile, name, and photos will be hidden from other users, but your information will be kept securely. You can reactivate your account at any time by signing back in.
                </p>
            </div>
            <RadioButton inputId="deactivate" name="accountAction" value="Deactivate" v-model="Delete" />
        </div>

            <!-- Delete Section -->
        <div class="p-3 mb-4 rounded-3 border d-flex justify-content-between align-items-start hover-bg-light">
            <div class="pe-3">
                <h5 class="mb-2 text-dark">Delete Account</h5>
                <p class="text-muted mb-0 small">
                Permanently delete your account and all related information. Once deletion is requested, your account will be deactivated for 30 days before being permanently removed. During this period, you can still log in to cancel the deletion. After 30 days, all your data will be permanently erased and cannot be recovered.
                </p>
            </div>
            <RadioButton inputId="delete" name="accountAction" value="Delete" v-model="Delete"/>
        </div>
    </div>
    <div v-else>
      <div class="p-3 mb-4 rounded-3 border hover-bg-light">
        <h5 class="mb-3 text-dark">Confirm Account Deletion</h5>
        <p class="text-muted small mb-4">
          To proceed with deleting your account, please verify your identity by entering the mobile verification code and your account password.
        </p>

        <!-- Mobile Verification -->
        <div class="mb-3">
          <label class="form-label fw-semibold small">Mobile Verification Code</label>
          <div class="d-flex gap-2">
            <InputText  placeholder="Enter code" class="flex-grow-1" />
            <Button label="Send Code" icon="pi pi-send" size="small" outlined class="fw-semibold" />
          </div>
          <small class="text-muted">A 6-digit code will be sent to your registered mobile number.</small>
        </div>

        <!-- Password Confirmation -->
        <div class="mb-4">
          <label class="form-label fw-semibold small">Confirm Password</label>
          <Password placeholder="Enter your password" toggleMask :feedback="false" class="w-100" />
        </div>

        <!-- Delete Info -->
        <div class="alert alert-warning small rounded-3 py-2">
          Once deletion is confirmed, your account will be deactivated for <b>30 days</b>. You can still log in during this period to cancel the deletion. After 30 days, all data will be permanently erased.
        </div>
      </div>
    </div>

    <!-- Button -->
    <div class="text-end d-flex justify-content-between">
        <Button
            v-if="isShow"
            label="Back"
            icon="pi pi-arrow-left"
            severity="light"
            size="small"
            class="fw-semibold col-md-3"
            @click="()=>{isShow = false;}"
        />
      <Button
        label="Continue"
        icon="pi pi-trash"
        severity="danger"
        size="small"
        class="fw-semibold col-md-3"
        @click="Continue"
      />
    </div>
  </div>
</template>
<script lang="ts" setup>
import { ref } from 'vue';
import RadioButton from 'primevue/radiobutton';
import Button from 'primevue/button';
import { InputText } from 'primevue';
import {Password} from 'primevue';
import showAlert from '@/composables/showAlert';
import { useUserProfile } from '@/stores/User/userProfile';
const useProfile = useUserProfile();
const Delete = ref<string>('');
const isShow = ref<boolean>(false);

const Continue = () =>
{
    if(Delete.value == 'Delete')
    {
        isShow.value = true;
    }
    console.log(Delete.value);
}

const deleteAccount = async() => {
         let config ={
                icon:'warning',
                title:'Warning',
                text: 'Are you sure you ?',
                confirmButtonText: 'yes',
                confirmButtonColor: '#a03829',
                showConfirmButton:true,
                showCancelButton:true,
                cancelButtonText:'No',
            }
        
    let confirm = await showAlert(config);

    if(confirm.isConfirmed){
        //call delete api
        let result =useProfile.deleteAccount();

    //     if(result.code == 200){
    //         let config ={
    //             icon:'success',
    //             title:'Success',
    //             text: 'Your account has been deleted successfully.',
    //             confirmButtonText: 'OK',
    //             confirmButtonColor: '#3085d6',
    //             showConfirmButton:true,
    //             showCancelButton:false,
    //         }
        
    //         await showAlert(config);
    //         //redirect to login page
    //         window.location.href = '/login';    

    // }
}
}
</script>
<style scope>
.hover-bg-light:hover {
  background-color: var(--ds-surface-subtle);
  transition: background-color 0.2s ease;
}

.bg-card {
  background-color: #fff;
}

.text-muted {
  line-height: 1.5;
}
</style>