<template>
    <section class="pe-card">
        <header class="pe-card-header">
            <div>
                <h2 class="pe-title">Cover Photo</h2>
                <p class="pe-subtitle">Your cover photo is used to customize the header of your profile.</p>
            </div>
        </header>
        <div class="w-100">
            <ProfileImageCropper @coverPhoto="setImage" from="coverPhoto"/>
        </div>
        <footer class="pe-actions">
            <Button class="pe-save" @click="SaveImageToDataBase">
                <Save v-if="btnName == 'Save'" :size="16" aria-hidden="true" />
                <Loader2 v-else :size="16" class="pe-spin" aria-hidden="true" />
                <label>{{ btnName }}</label>
            </Button>
        </footer>
    </section>
</template>
<script lang="ts" setup>
import { ref } from 'vue';
import Button from 'primevue/button';
import { Loader2, Save } from 'lucide-vue-next';
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