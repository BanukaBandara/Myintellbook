<template>
  <div class="d-flex justify-content-center mt-5 px-3 w-full">
    <Card class="shadow-3 w-full md:w-8 lg:w-6 xl:w-5 border-round-2xl">
      <!-- Title -->
      <template #title>
        <div class="text-center">
          <h2 class="text-primary font-bold">🎉 Exam Completed!</h2>
          <p class="text-secondary">Here’s your result summary</p>
        </div>
      </template>

      <!-- Content -->
      <template #content>
        <div class="text-center mb-5">
          <h3 class="text-2xl font-bold text-primary">{{ score }} / {{ total }}</h3>
          <p class="text-secondary mb-3">Your Score</p>
          <div class="d-flex justify-content-center align-items-center gap-2">
            <i class="pi pi-clock text-2xl text-primary"></i>
            <p class="mb-0">Time Taken: <b>{{ timeTaken }}</b></p>
          </div>
        </div>

        <!-- Questions & Answers inside the Card -->
        <Accordion multiple>
          <AccordionTab 
            v-for="(item, index) in examResults" 
            :key="index" 
            :header="`Q${index + 1}: ${truncateText(item.question, 50)}`"
          >
            <div class="mb-2">
              <p><b>Question:</b> {{ item.question }}</p>
              <p :class="{'text-success': item.user_answer === item.correct_answer, 'text-danger': item.user_answer !== item.correct_answer}">
                <b>Your Answer:</b> {{ item.user_answer }}
              </p>
              <p class="text-success"><b>Correct Answer:</b> {{ item.correct_answer }}</p>
            </div>
          </AccordionTab>
        </Accordion>
      </template>

      <!-- Footer -->
      <template #footer>
        <div class="d-flex justify-content-center gap-3">
          <!-- <Button label="Retry Exam" icon="pi pi-refresh" severity="secondary" /> -->
          <Button label="Finish" icon="pi pi-check" severity="primary" @click="()=>{ router.push('/exams')}" />
        </div>
      </template>
    </Card>
  </div>
</template>
<script setup lang="ts">
import { onMounted, ref } from "vue";
import Card from 'primevue/card';
import Button from 'primevue/button';
import { useuserExams } from '@/stores/User/userExams';
import Accordion from 'primevue/accordion';
import AccordionTab from 'primevue/accordiontab';
import { useRouter} from 'vue-router'

const router = useRouter()
const useExam = useuserExams();
const score = ref(75);
const total = ref(100);
const correct = ref(15);
const wrong = ref(5);
const timeTaken = ref("12m 30s");
const showAnswers = ref(true);
const examResults = ref([
  { 
    question: "What is the capital of France?", 
    user_answer: "Paris", 
    correct_answer: "Paris",
    isCorrect: false 
  },
]);

const setExamData = async() =>
{
  timeTaken.value = useExam.getExamEndTime;
  let examId = useExam.getExamId;

  let result = await useExam.getSummaryExam(examId);

  score.value = result.data.score;
  total.value = (result.data.No_questions) * 5;

  examResults.value = result.data.questions
  console.log(result);
}
// Sample data


const truncateText = (text: string, maxLength: number) => {
  return text.length > maxLength ? text.substring(0, maxLength) + "..." : text;
}

onMounted(async()=>{
  await setExamData();
})
</script>
<style scoped>
.text-success {
  color: #28a745;
}
.text-danger {
  color: #dc3545;
}
</style>