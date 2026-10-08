<template>
    <div class="d-flex justify-content-center align-items-start bg-light p-3">
        <Card class="shadow-sm rounded-3 experience-card">
    <template #title>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <h5 class="mb-0 fw-semibold text-dark">Education</h5>
          <Button
            variant="link"
            @click="router.back()"
            icon="pi pi-arrow-left"
            label="Back"
            class="fw-semibold text-primary p-0"
          />
        </div>
    </template>
    <template #content>
        <ul class="timeline list-unstyled m-0 p-0">
            <li
                v-for="(education, index) in educationDetails"
                :key="index"
                class="timeline-item pb-4 position-relative"
            >
                <!-- Timeline line -->
                <span class="timeline-line"></span>
                <!-- Timeline dot -->
                <span class="timeline-dot bg-primary"></span>

                <div class="ms-4">
                <div class="d-flex justify-content-between flex-wrap align-items-start gap-2">
                    <h6 class="fw-bold mb-1">
                    {{ education.school }}
                    </h6>
                    <Button
                    icon="pi pi-pen-to-square"
                    severity="secondary"
                    size="small"
                    rounded
                    v-if="isEdidted"
                    @click="()=>{router.push(`/profileEdit/educationInfo/${education.id}`)}"
                    />
                </div>
                <p class="mb-0 text-muted small">{{education.degree+"-"+ education.field_of_study}}</p>
                
                </div>
            </li>
            </ul>

        <!-- Empty State -->
        <div v-if="!educationDetails.length" class="text-center py-5 text-muted">
          <i class="pi pi-briefcase fs-1 mb-3"></i>
          <p class="mb-0">No Education added yet.</p>
        </div>
            <!-- <div class="row p-2 gap-4" v-for="(education, index) in educationDetails" :key="index">
                <div class="d-flex justify-content-between">
                    <div class="d-flex flex-column">
                        <span class="fw-bold">{{ education.school }}</span>
                        <span class="fs-6">{{education.degree+"-"+ education.field_of_study}}</span>
                    </div>
                    <span><Button icon="pi pi-pen-to-square" severity="secondary" size="small" class="fw-semibold" v-if="isEdidted" @click="()=>{router.push(`/profileEdit/educationInfo/${education.id}`)}"/> </span>
                </div>
            </div> -->
    </template>
</Card>

    </div>

</template>
<script lang="ts" setup>
import Card from 'primevue/card';
import { onMounted, ref, computed } from 'vue';
import { useUserProfile } from '@/stores/User/userProfile'; 
import { Button } from 'primevue';
import type { educationType } from '@/types/educationType';
import {Experiance, Education} from '@/services/profilePage';
import showAlert from '@/composables/showAlert';
import { useRouter, useRoute } from 'vue-router';

const router = useRouter();
const userProfile = useUserProfile();
const route = useRoute();
const userUrl = computed(()=> route.params.slug as String);
const educationDetails = ref<Array<educationType>>([]);
const isEdidted = computed(() => userProfile.getIsEditable)
const getEducationInfo = async() =>
{
    userProfile.slug = userUrl.value;
   let result = await Education();
   if(result.code == 200)
   {
    educationDetails.value =result.data.slice(0,3);
    // allExperiance.value = result.data;

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

onMounted(async() => {
    await getEducationInfo()
});
</script>
<style scoped>
.experience-card {
  width: 90%;          /* keeps margin on sides */
  max-width: 600px;    /* never too wide */
  min-width: 320px;    /* safe for small screens */
}

/* Timeline style */
.timeline-item {
  padding-left: 1.5rem;
}
.timeline-line {
  position: absolute;
  left: 8px;
  top: 0;
  bottom: 0;
  width: 2px;
  background: #dee2e6;
}
.timeline-dot {
  position: absolute;
  left: 2px;
  top: 6px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

/* Responsive tweaks */
@media (max-width: 576px) {
  .experience-card {
    width: 95%;
    max-width: 95%;  /* keep compact, not full */
  }
  .timeline-line {
    left: 5px;
  }
  .timeline-dot {
    left: 0;
    width: 8px;
    height: 8px;
  }
}
</style>