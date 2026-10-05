<template>
  <div class="exam-wrapper vh-100 d-flex flex-column bg-light">
    
    <!-- Fixed Header -->
    <div class="exam-header shadow-sm p-3 bg-white d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
      <h3 class="fw-bold text-primary text-center text-md-start mb-0">{{ questionsData.title }}</h3>
      <div class="text-center text-md-end">
        <p class="mb-0 fw-semibold"><i class="pi pi-clock text-danger"></i> {{ formattedTime }}</p>
        <small class="text-muted">Remaining Time</small>
      </div>
    </div>

    <!-- Main Exam Area -->
    <div class="flex-grow-1 d-flex justify-content-center align-items-start px-2 py-3">
      <Card class="shadow-lg exam-card border-0 rounded-4 d-flex flex-column">
        
        <!-- Content -->
        <template #content>
          <div 
            v-if="questionsData.questions.length" 
            class="flex-grow-1 d-flex flex-column justify-content-center text-center px-3 px-md-5 py-3"
          >
            <!-- Question Number -->
            <h5 class="fw-semibold mb-3">
              Question {{ currentQuestion + 1 }} of {{ totalQuestions }}
            </h5>

            <!-- Question Text -->
            <p class="fs-6 fs-md-5 mb-4">{{ questionsData.questions[currentQuestion].question }}</p>

            <!-- Options -->
            <div class="d-flex flex-column gap-3 align-items-start align-items-md-center">
              <div
                v-for="(option, idx) in questionsData.questions[currentQuestion].options"
                :key="idx"
                class="d-flex align-items-center w-100 w-md-75 w-lg-50 border rounded-3 p-2"
              >
                <RadioButton
                  :inputId="'option-' + currentQuestion + '-' + idx"
                  :value="idx"
                  v-model="selectedAnswers[currentQuestion]"
                  @change="storeAnswer(idx, questionsData.questions[currentQuestion].id)"
                />
                <label
                  class="ms-2 flex-grow-1 cursor-pointer"
                  :for="'option-' + currentQuestion + '-' + idx"
                >
                  {{ (idx+1) + '. ' + option }}
                </label>
              </div>
            </div>
          </div>

          <!-- Navigation -->
          <div class="d-flex flex-column flex-md-row justify-content-between mt-4 gap-2">
            <Button 
              label="Previous" 
              icon="pi pi-arrow-left" 
              class="p-button-secondary w-100 w-md-auto" 
              @click="previous" 
              :disabled="currentQuestion === 0" 
            />
            <div class="d-flex gap-2 w-100 w-md-auto">
              <Button 
                v-if="!(currentQuestion === totalQuestions - 1)" 
                label="Next" 
                icon="pi pi-arrow-right" 
                iconPos="right" 
                class="p-button-primary w-100 w-md-auto" 
                @click="next" 
              />
              <Button 
                v-else 
                label="Finish" 
                icon="pi pi-check" 
                class="p-button-success w-100 w-md-auto" 
                @click="finish" 
              />
            </div>
          </div>
        </template>
      </Card>
    </div>
  </div>
</template>
<script lang="ts" setup>
import { ref, onMounted, watch, computed , onUnmounted } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import { useRouter } from 'vue-router';
import { useuserExams } from '@/stores/User/userExams';
import RadioButton from 'primevue/radiobutton'

const selectedAnswers = ref<Record<number, number | null>>({})


export interface AnswerType{
    qId:number,
    answer:string | number,
}

const duration = ref(0);
const timeLeft = ref(duration);
const useExam = useuserExams();
const examId = ref<number>(0);

let timer: number | null = null;
const answers = ref<Array<AnswerType>>([]);
const examActive = ref(false);

const questionsData = ref(
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

const currentQuestion = ref(0);
const router = useRouter();
const totalQuestions = ref<number>(0)

// Go to next question
const next = () => {
  if (currentQuestion.value < totalQuestions.value - 1) {
    currentQuestion.value++
  }
}

// Go to previous question
const previous = () => {
  if (currentQuestion.value > 0) {
    currentQuestion.value--
  }
}

const disableRightClick = (event:any) => {
  if (examActive.value) {
    event.preventDefault()
    alert("Right-click is disabled during the exam.")
  }
}

const handleVisibilityChange = () => {
  if (examActive.value && document.hidden) {
    alert("You switched tabs! Exam will be close.")
    getQuestions()
  }
}

const finish = () =>
{
    submitAnswers();
    useExam.examEndTime = formattedTime.value;
    useExam.examId = examId.value
     document.removeEventListener("contextmenu", disableRightClick)
     document.removeEventListener("visibilitychange", handleVisibilityChange)
    router.push('/examEnd')
}

const storeAnswer = (id: number, qId: number) => {
  selectedAnswers.value[currentQuestion.value] = id

  const answerIndex = answers.value.findIndex(ans => ans.qId === qId)
  if (answerIndex !== -1) {
    answers.value[answerIndex].answer = id
  } else {
    answers.value.push({ qId: qId, answer: id })
  }
  console.log("Selected:", selectedAnswers.value[currentQuestion.value])

}

const submitAnswers = async() =>
{
  let questionAnswers = {
    'answers':answers.value,
    'exam': examId.value
  }
    let result = await useExam.submitAnswers(questionAnswers);

    if(result.code == 200)
    {
    }
    document.removeEventListener("contextmenu", disableRightClick)
    document.removeEventListener("visibilitychange", handleVisibilityChange)
}

// Format as HH:MM:SS
const formattedTime = computed(() => {
  const h = String(Math.floor(timeLeft.value / 3600)).padStart(2, "0");
  const m = String(Math.floor((timeLeft.value % 3600) / 60)).padStart(2, "0");
  const s = String(timeLeft.value % 60).padStart(2, "0");
  return `${h}:${m}:${s}`;
});

const getQuestions =() =>
{
    const questions = localStorage.getItem('exam.questions');
    examActive.value = true;
   document.addEventListener("contextmenu", disableRightClick)
   document.addEventListener("visibilitychange", handleVisibilityChange)

    if(questions){
        questionsData.value = JSON.parse(questions);
    }
        examId.value = questionsData.value.id;
        totalQuestions.value = questionsData.value.questions.length;
        duration.value = (questionsData.value.duration) * 60;
}

// Start timer automatically
onMounted(() => {
  timer = setInterval(() => {
    if (timeLeft.value > 0) {
      timeLeft.value--;
    } else {
      stopTimer();
      alert("⏰ Time is up! Your exam will be submitted.");
      finish();
    //   router.push('/')
    }
  }, 1000);
  getQuestions();
});


// Cleanup when component is destroyed
onUnmounted(() => {
  stopTimer()
  examActive.value = false;
   document.removeEventListener("contextmenu", disableRightClick)
   document.removeEventListener("visibilitychange", handleVisibilityChange)
});

const isSelected = (qId:number, idx:number) => {
  const answer = answers.value.find(ans => ans.qId === qId)
  return answer && answer.answer === idx
}
function stopTimer() {
  if (timer) {
    clearInterval(timer);
    timer = null;
  }
}
</script>
<style scoped>
.exam-wrapper {
  overflow: hidden;
}

.exam-header {
  position: sticky;
  top: 0;
  z-index: 1050;
}

.exam-card {
  width: 100%;          /* Mobile full width */
  max-width: 900px;     /* Comfortable on laptops */
  min-height: 70vh;     /* Taller look */
}

/* Adjust header fonts for small screens */
@media (max-width: 576px) {
  .exam-header h3 {
    font-size: 1.25rem;
  }
  .exam-header p {
    font-size: 0.9rem;
  }
}

/* On very large monitors (desktops) */
@media (min-width: 1400px) {
  .exam-card {
    max-width: 1000px; /* Wider card on large screens */
  }
}


</style>