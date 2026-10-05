<template>
    <div class="row gap-4 p-4 bg-card mt-4 flex-grow-1 overflow-auto rounded-4">
        <h5>Change Cover Photo</h5>
        <div class="">
            <informationShow message="Your Cover Photo will be used to customize the header of your profile." borderClass="border-primary" bgClass="bg-primary"/>
            <div class="w-100">
                <ProfileImageCropper @coverPhoto="setImage" from="coverPhoto"/>
            </div>
            <Button label="Save" icon="pi pi-save" severity="secondary" size="small" class="fw-semibold col-md-3 mt-4" @click="SaveImageToDataBase" >
                 <i class="pi pi-save"  v-if="btnName == 'Save'"/>
                <i class="pi pi-spin pi-spinner" v-else />
                <label class="fw-semibold"> {{btnName}}</label>
            </Button>
        </div>
          
    </div> 
</template>
<script lang="ts" setup>
import { ref } from 'vue';
import Button from 'primevue/button';
import informationShow from '../../components/commonComponents/infomationShow.vue';
import ProfileImageCropper from './ProfileImageCropper.vue';
import { useUserProfile } from '@/stores/User/userProfile';
import showAlert from '@/composables/showAlert';
import { useRouter } from 'vue-router';

const userProfile = useUserProfile();
const btnName = ref<String>('Save');
const router = useRouter();
const coverImage = ref({
    image:''
})

const setImage = (image:string) =>
{
    coverImage.value.image = image;
}

const SaveImageToDataBase = async() =>
{
    btnName.value = 'Please wait .....';
    userProfile.cover_image = coverImage.value;
   let result = await userProfile.saveCoverPhoto();

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

</script>