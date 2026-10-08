<template>
  <div class="d-flex justify-content-center align-items-start bg-light p-3">
    <Card class="shadow-sm rounded-3 experience-card">
      <!-- Header -->
      <template #title>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <h5 class="mb-0 fw-semibold text-dark">Work Experience</h5>
          <Button
            variant="link"
            @click="router.back()"
            icon="pi pi-arrow-left"
            label="Back"
            class="fw-semibold text-primary p-0"
          />
        </div>
      </template>

      <!-- Content -->
      <template #content>
        <ul class="timeline list-unstyled m-0 p-0">
          <li
            v-for="(experience, index) in allExperiance"
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
                  {{ experience.title }}
                  <Badge
                    v-if="experience.currently_working"
                    severity="success"
                    value="Current"
                    class="ms-2"
                  />
                </h6>
                <Button
                  icon="pi pi-pen-to-square"
                  severity="secondary"
                  size="small"
                  rounded
                  v-if="isEdidted"
                  @click="experience.id !== undefined && goToEdit(experience.id)"
                />
              </div>
              <p class="mb-0 text-muted small">{{ experience.company }}</p>
              <p class="mb-0 text-muted small">{{ experience.location }}</p>
            </div>
          </li>
        </ul>

        <!-- Empty State -->
        <div v-if="!allExperiance.length" class="text-center py-5 text-muted">
          <i class="pi pi-briefcase fs-1 mb-3"></i>
          <p class="mb-0">No work experience added yet.</p>
        </div>
      </template>
    </Card>
  </div>
</template>
<script lang="ts" setup>
import Card from 'primevue/card';
import { onMounted, ref, computed } from 'vue';
import { useUserProfile } from '@/stores/User/userProfile'; 
import Badge from 'primevue/badge';
import { Button } from 'primevue';
import type { workExperianceType } from '@/types/workExperianceType';
import {Experiance, Education} from '@/services/profilePage';
import showAlert from '@/composables/showAlert';
import { useRouter, useRoute } from 'vue-router';

const router = useRouter();
const userProfile = useUserProfile();
const route = useRoute();
const userUrl = computed(()=> route.params.slug as String);
const allExperiance = ref<Array<workExperianceType>>([]);
const isEdidted = computed(() => userProfile.getIsEditable)
const getExperiance = async() =>
{
    userProfile.slug = userUrl.value;
    let result = await Experiance();

    if(result.code == 200)
   {
    allExperiance.value = result.data;

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

const goToEdit = (id:number) =>
{
    userProfile.showExperianceEdit = true;
    router.push(`/profileEdit/workExperience/${id}`);
}
onMounted(async() => {
    await getExperiance();
    // await checkEditable()
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