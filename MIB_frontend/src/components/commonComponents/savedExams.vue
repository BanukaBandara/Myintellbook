<template>
  <div class="row flex-grow-1 overflow-auto m-0 justify-content-center mb-3 gap-1">
    <!-- Left Sidebar -->
    <div class="col-md-1"></div>

    <!-- Profile Section -->
    <div class="col-md-3 mt-3 d-none d-md-block">
      <NewExamCreate  @examCreated="getSavedExams"/>
          
    </div>

    <!-- Main Content -->
    <div class="col-md-6 mt-3">
      
      <Card>
        <!-- Header -->
        <template #title>
          <div class="row">
             <div class="col-md-6">
              <IconField
                :pt="{
                  root: 'w-100',
                  icon: 'bg-white border-0 p-2',
                  input: 'w-100'
                }"
              >
                <InputIcon class="pi pi-search" />
                <InputText
                  v-model="searchVal"
                  @keyup="getSavedExams"
                  placeholder="Search exams..."
                  class="form-control rounded-pill w-100"
                  style="height: 38px; font-size: 0.9rem;"
                />
              </IconField>
            </div>
           
            <TreeSelect
              v-model="selectedCategory"
              :options="categories"
              optionLabel="name"
              optionValue="id"
              placeholder="Filter by Category"
              class="fs-6 col-md-6 rounded-pill"
              showClear
              @change="getSavedExams"
            />
          </div>
        </template>

        <!-- Content -->
        <template #content>
          <div v-if="showExams.length === 0" class="text-center text-muted my-4">
            <p>No question groups found.</p>
          </div>

          <div class="row g-3">
            <div
              v-for="(group, index) in showExams"
              :key="group.id"
              class="col-md-6"
            >
              <!-- Question Group Card -->
              <Card
                v-if="group.isBooked && !group.exam_taken"
                
                class="h-100 shadow-sm d-flex flex-column justify-content-between"
              >
                <!-- Card Header -->
                <template #title>
                  <div class="row">
                    <div class="fw-semibold col-md-7">
                      {{ group.title }}
                    </div>
                    <div class="col-md-5 text-md-end">
                      <Button
                        label="Remove"
                        icon="pi pi-bookmark-fill"
                        size="small"
                        severity="danger"
                        variant="text"
                        @click="removeBookmarked(group.id, index)"
                      />
                    </div>
                  </div>
                </template>

                <!-- Card Content -->
                <template #content>
                  <div>
                    <p class="mb-1 text-muted small">
                      {{ group.exam_taken }}
                      Category: <strong>{{ group.profession }}</strong>
                    </p>
                    <p class="mb-1">Total Questions: {{ group.total_questions }}</p>
                    <p class="mb-1">Difficulty: {{ group.difficulty }}</p>
                    <p class="mb-4 small text-muted">{{ group.description }}</p>
                  </div>
                </template>

                <!-- Card Footer -->
                <template #footer>
                  <div class="d-flex justify-content-between align-items-center">
                    <p class="mb-0 text-muted">{{ group.created_at }}</p>
                    <div>
                      <Button
                        label="Open"
                        icon="pi pi-external-link"
                        size="small"
                        severity="primary"
                        variant="text"
                        @click="() => router.push('openExamQuestions/' + group.id)"
                      />
                      <Button
                        :label="group.exam_taken ? 'Done' : 'Start'"
                        icon="pi pi-play"
                        size="small"
                        severity="primary"
                        variant="text"
                        @click="startExam(group.id)"
                      />
                    </div>
                  </div>
                </template>
              </Card>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Right Spacer -->
    <div class="col-md-2"></div>
  </div>
</template>


<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import TreeSelect from 'primevue/treeselect';
import ProfileDetails from '@/components/HomePage/ProfileDetails.vue';
import Card from 'primevue/card';
import Button from 'primevue/button';
import { useuserExams } from '../../stores/User/userExams';
import type { CategorySubType } from '@/types/CategorySubType';
import { useRouter } from 'vue-router';
import showAlert from '@/composables/showAlert';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import { useRoute} from 'vue-router';
import NewExamCreate from "../HomePage/NewExamCreate.vue";

const route = useRoute();
const useExam = useuserExams();
const searchVal = ref('');
const router = useRouter();
const selectedCategory = ref();
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
    saveButton:'Save',
    saveButtonIcon:'pi pi-bookmark',
    saveButtonColor:'info',
    exam_taken:false,
}]);
const categories = ref<Array<CategorySubType>>([]);

const getSavedExams = async() => {
let params = route.params.category;
  if(params){
    searchVal.value = String(params);
  }
  useExam.categorykey = (selectedCategory.value) ? parseInt(Object.keys(selectedCategory.value)[0]) : 0;
  useExam.searchKey = searchVal.value;
  let result = await useExam.getMyExams();

    if(result.code == 200)
   {
    showExams.value = result.data;
   } 
}

const getFormatedCat = async() =>
{
    let result = await useExam.getUserFormatedCat();

    if(result.code == 200)
   {
       categories.value = result.data;
   }
    
}

const removeBookmarked = async(id:number,index:number) =>
{

  useExam.isBooked = false;
  showExams.value[index].isBooked = false;
  useExam.ExamSavedIndex = id;

  let result = await useExam.saveExam();

  if(result.code == 200)
   {
       categories.value = result.data;
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
    await getSavedExams();
    await getFormatedCat();
})
</script>

