<template>
  <div class="d-flex justify-content-center align-items-center vh-100">
    <Card class="shadow-lg p-4 col-md-6 text-center border-0 rounded-4">
      <!-- Title -->
      <template #title>
        <h3 class="fw-bold text-primary mb-3">{{ examData.title }}</h3>
      </template>

      <!-- Content -->
      <template #content>
        <div class="mb-4">
          <p class="text-secondary fs-5 fw-semibold">
            {{ examData.description }}
          </p>
          <div class="d-flex justify-content-around my-3">
            <div>
              <h6 class="text-muted mb-1">Duration</h6>
              <span class="fw-semibold">{{examData.duration}}min</span>
            </div>
            <div>
              <h6 class="text-muted mb-1">Total Marks</h6>
              <span class="fw-semibold">{{ examData.total_marks }}</span>
            </div>
          </div>
        </div>

        <!-- Buttons -->
        <div class="d-flex justify-content-center gap-3">
          <Button label="Start" class="p-button-primary px-4" @click="()=>{router.push('/examForm')}"/>
          <Button label="Close" class="p-button-outlined p-button-danger px-4" @click="()=>{router.go(-1)}"/>
        </div>
      </template>
    </Card>
  </div>
</template>

<script lang="ts" setup>
import Card from 'primevue/card'
import Button from 'primevue/button'
import { useRouter, useRoute} from 'vue-router';
import { useuserExams } from '../../stores/User/userExams';
import { onMounted, ref } from 'vue';

const router = useRouter();
const route = useRoute();
const useExam = useuserExams();
const examData = ref(
  {
    id:0,
    title: '',
    description:'',
    duration:0,
    total_marks:'',
    total_questions:0,
    questions:[
      {
        id:0,
        options:{},
        question:'',
      }
    ]
  }
)

const getExamData = async() =>
{
  const examId = route.params.id;
  useExam.from = 'exam';
  let result = await useExam.getExamData(examId)

  if(result.code == 200){
    examData.value = result.data;
    localStorage.setItem('exam.questions', JSON.stringify(examData.value));
  }
}

onMounted(async() => {
  await getExamData();
});
</script>
