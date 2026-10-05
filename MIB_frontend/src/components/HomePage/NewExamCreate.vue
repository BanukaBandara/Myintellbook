<template>
  <Card class="shadow-sm border-round-3xl p-4 bg-white w-100">
    <!-- Header -->
    <template #title>
      <div class="d-flex align-items-center justify-content-between w-100 mb-3">
        <div class="d-flex align-items-center gap-2">
          <i class="pi pi-pencil text-primary" style="font-size:14px;"></i>
          <span class="fw-bold text-primary" style="font-size:14px;">Create New Exam</span>
        </div>
      </div>
    </template>

    <!-- Content -->
    <template #content>
      <div class="row mt-2 px-1 gy-3">
        <!-- Category -->
        <div class="col-12">
          <label class="form-label text-secondary" style="font-size:12px;">Select Category</label>
          <Select
            v-model="selectedCategory"
            :options="categories"
            optionLabel="name"
            placeholder="Choose a Category"
            class="w-100"
            style="font-size:12px;"
            @change="getSubCategories"
          />
        </div>

        <!-- Sub Category -->
        <div class="col-12">
          <label class="form-label text-secondary" style="font-size:12px;">Select Sub Category</label>
          <Select
            v-model="selectedSubCategory"
            :options="subCategories"
            optionLabel="name"
            placeholder="Choose a Sub Category"
            class="w-100"
            style="font-size:12px;"
          />
        </div>

        <!-- Exam Level -->
        <div class="col-12">
          <label class="form-label text-secondary" style="font-size:12px;">Select Exam Level</label>
          <Select
            v-model="selectLevel"
            :options="levels"
            optionLabel="name"
            placeholder="Choose Level"
            class="w-100"
            style="font-size:12px;"
          />
        </div>

        <!-- Number of Questions -->
        <div class="col-12">
          <label for="NoOfQuestions" class="form-label text-secondary" style="font-size:12px;">
            Number of Questions
            <i
              class="pi pi-question-circle text-muted ms-2"
              style="font-size:11px;"
              v-tooltip="'Minimum 10 questions required for the exam'"
            ></i>
          </label>
          <InputText
            type="number"
            v-model="NoOfQuestions"
            id="NoOfQuestions"
            placeholder="Minimum 10 questions"
            class="w-100"
            style="font-size:12px;"
          />
        </div>

        <!-- Button -->
        <div class="col-12">
          <Button
            label="Create Exam"
            icon="pi pi-check-circle"
            @click="createExam"
            severity="primary"
            class="w-100 mt-2 border-round-lg shadow-sm"
            size="small"
          />
        </div>
      </div>
    </template>
  </Card>
</template>


<script lang="ts" setup>
import { ref, onMounted, defineEmits } from 'vue';
import RadioButton from 'primevue/radiobutton';
import RadioButtonGroup from 'primevue/radiobuttongroup';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Card from 'primevue/card';
import { useuserExams } from '@/stores/User/userExams';
import { useUserProfile } from '@/stores/User/userProfile';
import type { CategoryType, subCategoryType } from '@/types/CategoryType';

const userExam = useuserExams();
const useProfile = useUserProfile();
const selectedCategory = ref<CategoryType>({id:0,name:''});
const selectedSubCategory = ref<subCategoryType>({id:0,name:''});
const categories = ref<Array<CategoryType>>([])
const subCategories = ref<Array<subCategoryType>>([]);
const selectLevel = ref({name:''});
const levels = [{'name':'easy'},{'name':'medium'},{'name':'hard'}]; 
const NoOfQuestions = ref<string>('');

const emit = defineEmits(['examCreated'])

const getCategories = async() =>
{
    let result = await userExam.getCategories();

    if(result.code == 200)
   {
       categories.value = result.data;
   }
    
}

const getSubCategories = async() =>
{
    userExam.catId = selectedCategory.value?.id;

    let result = await userExam.getSubCategories();

    if(result.code == 200)
   {
       subCategories.value = result.data; 
   }
    
}

const createExam = async() =>{

 let exam ={
    'profession':selectedSubCategory.value.id,
    'level':selectLevel.value.name,
    'NoQuestions':NoOfQuestions.value
 }

  let result = await userExam.createExam(exam);

    if(result.code == 200)
   {
    useProfile.isAnswered = true;
    selectedSubCategory.value = {id:0,name:''};
    selectedCategory.value = {id:0,name:''};
    selectLevel.value = {name:''};
    NoOfQuestions.value = '';
    emit('examCreated');
    //    subCategories.value = result.data; 
   } 

}

onMounted(async()=>{
    await getCategories();
})
</script>
