<template>
        <section class="pe-card">
            <header class="pe-card-header">
                <div>
                    <h2 class="pe-title">{{ sectionTitle }}</h2>
                    <p class="pe-subtitle">{{ message }}</p>
                </div>
            </header>

            <div class="pe-field">
                <FloatLabel variant="on">
                    <InputText id="skill" size="small" class="w-100" v-model="skillDetails.skill"/>
                    <label for="skill">{{ fieldLabel }}</label>
                </FloatLabel>
            </div>

            <footer class="pe-actions">
                <Button class="pe-save" @click="submitSkill">
                    <Loader2 v-if="btnName == 'Please wait .....'" :size="16" class="pe-spin" aria-hidden="true" />
                    <Save v-else :size="16" aria-hidden="true" />
                    <label>{{ btnName }}</label>
                </Button>
            </footer>
        </section>
</template>
<script lang="ts" setup>
import { ref, watch, computed, onMounted } from 'vue';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import FloatLabel from 'primevue/floatlabel';
import { Loader2, Save } from 'lucide-vue-next';
import { useUserProfile } from '@/stores/User/userProfile';
import showAlert from '@/composables/showAlert';
import {useRouter, useRoute} from 'vue-router';

const router = useRouter();
const route = useRoute();
const skillDetails = ref({
    type:'0',
    skill:''
});
const userProfile = useUserProfile();
const btnName = ref<string>('Save');
const inputPlaceholder = ref<string>('skills');
const slug = ref('');
const message = ref<string>('You can add new Licensed profession');
const fieldLabel = computed(() => inputPlaceholder.value.charAt(0).toUpperCase() + inputPlaceholder.value.slice(1));
const sectionTitle = computed(() => slug.value === '1' || slug.value === '2' ? inputPlaceholder.value : 'Skills Information');

watch(slug,(oldValue,newValue)=>{

    if(slug.value == '1'){
        inputPlaceholder.value = 'Vocational certification';
        message.value = `You can add new ${inputPlaceholder.value} `
    }else if(slug.value == '2'){
        inputPlaceholder.value = 'Recognized certification'
        message.value = `You can add new ${inputPlaceholder.value} `
    }
})

const submitSkill = async() =>
{
    btnName.value = 'Please wait .....';
    skillDetails.value.type = slug.value;
    userProfile.skillDetails = skillDetails.value;
    let result = await userProfile.addSkill();

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
            btnName.value = 'Save';
            router.push('/profile')
        }
    }

}
onMounted(()=>{
    // Optional param: may be absent on /profileEdit/skillsInfo.
    slug.value = String(route.params.slug ?? '').charAt(0)
})
</script>