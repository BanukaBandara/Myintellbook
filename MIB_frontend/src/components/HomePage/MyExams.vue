<template>
  <Card :pt="{ root: 'w-75 shadow-sm rounded-3 border-0' }">
    <template #content>
      <!-- Header -->
      <div class="text-center mb-3">
        <div class="d-flex justify-content-between align-items-center">
                 <span class="fw-semibold text-dark">📘 My Exams</span>
                <Button asChild v-slot="slotProps" variant="link"  label="see all">
                    <RouterLink to="/exams" class="text-decoration-none" style="font-size:15px;">See all</RouterLink>
                </Button>
            </div>
      </div>

      <!-- Create New Exam -->
      <div class="mb-4">
        <NewExamCreate />
      </div>

       <showMyExams />
    </template>
  </Card>
</template>

<script lang="ts" setup>
import Card from "primevue/card";
import Button from 'primevue/button';
import NewExamCreate from "../HomePage/NewExamCreate.vue";
import showMyExams from "../commonComponents/showMyExams.vue";
</script>
