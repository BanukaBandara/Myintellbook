<template>
<!-- Empty State -->
      <div v-if="showExams.length === 0" class="d-flex flex-column align-items-center p-4 bg-light rounded-3 border">
        <i class="pi pi-file text-secondary mb-3" style="font-size: 3rem;"></i>
        <p class="text-secondary mb-0" style="font-size: 14px;">
          You have not created any exams yet.
        </p>
      </div>

      <!-- Exams List -->
      <div v-else class="d-flex flex-column gap-3">

          <div
            v-for="exam in showExams.slice(0,showLength)"
            :key="exam.id"
            class="d-flex flex-row align-items-center justify-content-between py-3 px-3 mb-2 rounded-3 border shadow-sm bg-white hover-shadow-sm transition"
          >
            <!-- Exam Info -->
            <div class="d-flex flex-column w-100">
              <div class="fw-semibold text-dark d-flex justify-content-between" style="font-size: 15px;">
                {{ exam.title }}
              </div>
              <div class="d-flex flex-row gap-3 mt-1 text-secondary small">
                <span>{{ exam.duration_minutes }} min</span>
                <span>•</span>
                <span>{{ exam.total_marks }} marks</span>
              </div>
            </div>

            <!-- Actions -->
            <div class="d-flex flex-row gap-2">
              <Button label="open" size="small" severity="primary" outlined @click="()=>{router.push('openExamQuestions/' + exam.id)}"/>
              <Button :label="(exam.exam_taken) ? 'Done': 'Start'" size="small" severity="secondary" outlined  @click="startExam(exam.id)"/>
            </div>
          </div>
      </div>
      <div class="w-100 text-center">
          <Button class="text-decoration-none text-center w-100 fw-semibold text-primary d-flex flex-row gap-2 align-items-center" severity="link" 
          v-if="(showExams.length  > 3 || showLength == 6)" @click="()=>{ (showLength == 3) ? showLength = 6 : showLength = 3}">
          {{(showLength == 3) ? 'See more Exams' : 'See less Exams'}}
            <i :class="(showLength == 3) ?'pi pi-angle-down' : 'pi pi-angle-up'"></i>
          </Button>
      </div>

</template>
<script setup lang="ts">
import { useuserExams } from '../../stores/User/userExams';
import { onMounted, ref} from 'vue';
import Button from 'primevue/button';
import { useRouter} from 'vue-router';
import showAlert from '@/composables/showAlert';

const useExam = useuserExams();
const router = useRouter();
const showLength = ref(3);
const showExams = ref([{
    id:0,
    title:'',
    description:'',
    duration_minutes:0,
    total_marks:0,
    total_questions:0,
    difficulty:'',
    profession:'',
    created_at:'',
    isBooked:false,
    showMessage:false,
    exam_taken:false,
}]);

const getMyExams = async() => {

  let result = await useExam.getMyExams();
  

    if(result.code == 200)
   {
    showExams.value = result.data;
    console.log(showExams.value);
   } 
}

const startExam = async(id:number) => {

    let config ={
                icon:'warning',
                title:'Warning',
                text: 'Once you start the exam you will not be able to pause or stop it until you finish. Are you sure you want to start the exam?',
                confirmButtonText: 'yes, start it!',
                confirmButtonColor: '#a03829',
                showConfirmButton:true,
                showCancelButton:true,
                cancelButtonText:'No, take me back',
            }
        
    let confirm = await showAlert(config);
    if(confirm.isConfirmed)
    {
      router.push('/startExam/'+id)
    }
  
}

onMounted(async()=>{
    await getMyExams();
})
</script>