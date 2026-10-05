<template>
  <div class="d-flex justify-content-center mt-4">
    <Card class="shadow-lg p-4 col-lg-8 col-md-10 col-sm-12 border-0 rounded-4 bg-light">
      
      <!-- Exam Header -->
      <template #title>
        <div class="d-flex justify-content-between align-items-start mb-3">
          <!-- Empty (spacer) -->
          <div>
            <Button icon="pi pi-arrow-left" rounded outlined size="small" severity="info" @click="router.go(-1)"/>
          </div>

          <!-- Title & Description -->
          <div class="text-center flex-grow-1">
            <h2 class="fw-bold text-primary mb-1">{{ examData.title }}</h2>
            <p class="text-muted fs-6">{{ examData.description }}</p>
          </div>

          <!-- Save Button -->
          <div>
            <Button
              :icon="examData.isBooked ? 'pi pi-bookmark-fill' : 'pi pi-bookmark'"
              :label="examData.isBooked ? 'Remove' : 'Save'"
              rounded
              outlined
              size="small"
              :severity="examData.isBooked ? 'danger' : 'info'"
              @click="bookmarked(examData.id)"
            />
          </div>
        </div>
      </template>

      <!-- Exam Info Badges -->
      <template #content>
        <div class="d-flex justify-content-center gap-4 mb-4">
          <div class="text-center bg-white rounded-3 px-4 py-2 shadow-sm">
            <h6 class="text-secondary mb-1">⏳ Duration</h6>
            <span class="fw-bold fs-5 text-dark">{{ examData.duration }} min</span>
          </div>
          <div class="text-center bg-white rounded-3 px-4 py-2 shadow-sm">
            <h6 class="text-secondary mb-1">📝 Total Marks</h6>
            <span class="fw-bold fs-5 text-dark">{{ examData.total_marks }}</span>
          </div>
           <!-- ✅ Correct Answer Count -->
          <div class="text-center bg-success text-white rounded-3 px-4 py-2 shadow-sm">
            <h6 class="mb-1">✅ Correct Answers</h6>
            <span class="fw-bold fs-5">{{ correctCount }}/{{ examData.questions.length }}</span>
          </div>
        </div>

        <!-- Questions Section -->
        <div class="mt-3">
          <Panel
            v-for="(question, qIndex) in examData.questions"
            :key="question.id"
            :header="'Q' + (qIndex + 1) + '. ' + question.question"
            toggleable
            collapsed
            class="mb-3 border-0 rounded-3 shadow-sm"
          >
            <!-- Options -->
            <div class="d-flex flex-column gap-2">
              <Button
                v-for="(option, idx) in question.options"
                :key="idx"
                :label="option"
                class="text-start w-100 p-button-outlined shadow-sm rounded-pill"
                @click="checkAnswer(idx, question.id)"
              />
            </div>

            <!-- Answer Result -->
            <div v-if="question.isCorrect !== null" 
                 class="mt-3 d-flex align-items-center fs-6 fw-semibold px-2">
              <i :class="question.isCorrect ? 'pi pi-check-circle text-success fs-5 me-2' : 'pi pi-times-circle text-danger fs-5 me-2'"></i>
              <span :class="question.isCorrect ? 'text-success' : 'text-danger'">
                {{ question.isCorrect ? 'Correct!' : 'Wrong!' }}
              </span>
              <span class="ms-3 text-muted">✔ Correct Answer: <b>{{ question.correctAnswer }}</b></span>
            </div>
          </Panel>
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
import Panel from 'primevue/panel';


const router = useRouter();
const route = useRoute();
const useExam = useuserExams();
const showAnswer = ref(false);
const correctCount = ref(0);
const examData = ref(
  {
    id:0,
    title: '',
    description:'',
    duration:0,
    total_marks:'',
    total_questions:0,
    isBooked:false,
    questions:[
      {
        id:0,
        options:{},
        question:'',
        isCorrect:false,
        correctAnswer:'',
      }
    ]
  }
)

const getExamData = async() =>
{
  const examId = route.params.id;
  useExam.from = 'preview';
  let result = await useExam.getExamData(examId)

  if(result.code == 200){
    examData.value = result.data;
    localStorage.setItem('exam.questions', JSON.stringify(examData.value));
  }
}



const bookmarked = async(id:number) =>
{
  examData.value.isBooked = !examData.value.isBooked
  useExam.isBooked = examData.value.isBooked
  useExam.ExamSavedIndex = id;

  let result = await useExam.saveExam();
}

const checkAnswer = async(answer:string,id:number) =>
{
  let result = await useExam.checkAnswer(answer,id);


  if(result.code == 200)
  {
    
    examData.value.questions.forEach((question)=>{
      if(question.id == result.data.QuestionId)
      {
        question.isCorrect= result.data.isCorrect;
        question.correctAnswer = result.data.CorrectAnswer;
        if(result.data.isCorrect) correctCount.value += 1;
        showAnswer.value = true;
      }
    })
  }
}

onMounted(async() => {
  await getExamData();
});
</script>
