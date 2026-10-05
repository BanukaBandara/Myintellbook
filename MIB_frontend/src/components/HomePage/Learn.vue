<template>
  <div class="row flex-grow-1 overflow-auto m-0 justify-content-center mb-3 gap-1">
    <!-- Left Sidebar -->
    <div class="col-md-1"></div>
    <div class="col-md-2 mt-3 d-none d-md-block">
      <ProfileDetails />
    </div>

    <!-- Main Content -->
    <div class="col-md-7 mt-3">
      <Card>
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
                  @keyup="getExams"
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
              @change="getExams"
            />
          </div>
        </template>

        <template #content>
          <div v-if="showExams.length === 0" class="text-center text-muted my-4">
            <p>No question groups found.</p>
          </div>

          <div class="row g-3">
            <div
              v-for="(group , index) in showExams"
              :key="group.id"
              class="col-md-6"
            >
              <Card class="h-100 shadow-sm d-flex flex-column justify-content-between">
                <template #title>
                 <div class="row">
                     <div class="fw-semibold col-md-7">
                    {{ group.title }}
                   
                  </div>
                  <div class="col-md-5 text-md-end">
                     <Button
                      
                      :label="group.saveButton"
                      :icon="group.saveButtonIcon"
                      size="small"
                      :severity="group.saveButtonColor"
                      variant="text"
                      @click="bookmarked(group.id,index)"
                      
                    />
                    
                  </div>
                 </div>
                   
                </template>

                <template #content>
                  <div>
                    <p class="mb-1 text-muted small">
                     {{ group.exam_taken }} Category: <strong>{{ group.profession }}</strong>
                    </p>
                    <p class="mb-1">Total Questions: {{ group.total_questions }}</p>
                    <p class="mb-1">Difficulty: {{ group.difficulty }}</p>
                    <p class="mb-4 small text-muted">

                      {{group.description}}
                     
                    </p>
                     
                  </div>
                </template>

                <template #footer>
                  <div class="d-flex justify-content-between align-content-end">
                   <p class="mb-0 text-muted"> {{ group.created_at }}</p>
                    <Button
                      label="Open"
                      icon="pi pi-external-link"
                      size="small"
                      severity="primary"
                      variant="text"
                      @click="()=>{router.push('/openExamQuestions/'+group.id)}"
                    />
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
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import { useRoute} from 'vue-router';

const route = useRoute();
const router = useRouter();
const useExam = useuserExams();
const selectedCategory = ref();
const searchVal = ref('');
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
    exam_taken:false
}]);
const categories = ref<Array<CategorySubType>>([]);

const chengeButton = (index:number)=>{
 if(showExams.value[index].isBooked){
    showExams.value[index].saveButton = 'Remove';
    showExams.value[index].saveButtonIcon = 'pi pi-bookmark-fill';
    showExams.value[index].saveButtonColor = 'danger';
  }else{
    showExams.value[index].saveButton = 'Save';
    showExams.value[index].saveButtonIcon = 'pi pi-bookmark';
    showExams.value[index].saveButtonColor = 'info';
  }
}

const getExams = async() => {

  useExam.categorykey = (selectedCategory.value) ? parseInt(Object.keys(selectedCategory.value)[0]) : 0;
  useExam.searchKey = searchVal.value;
  let result = await useExam.getExams();

    if(result.code == 200)
   {
    showExams.value = result.data;
   } 
}

const getFormatedCat = async() =>
{
    let result = await useExam.getFormatedCat();

    if(result.code == 200)
   {
       categories.value = result.data;
   }
    
}

const bookmarked = async(id:number,index:number) =>
{
  showExams.value[index].isBooked = !showExams.value[index].isBooked;
  useExam.isBooked = showExams.value[index].isBooked
  useExam.ExamSavedIndex = id;

  chengeButton(index);

  let result = await useExam.saveExam();

  if(result.code == 200)
   {
       categories.value = result.data;
   }
}

onMounted(async()=>{
    await getExams();
    await getFormatedCat();

})
</script>

