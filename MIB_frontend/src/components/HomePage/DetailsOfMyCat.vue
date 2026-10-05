<template>
    <Dialog  :visible="visible"  @update:visible="emit('update:visible', $event)" modal :header="title" :style="{ width: '25rem' }">
                <div class="d-flex flex-column">
                
                    <span>
                        <p class="text-secondary opacity-75 fw-semibold" style="font-size:12px;">You can add sub categories to this category or you can go to test</p>
                    </span>
                   
                </div>
                
                <NewExamCreate />
                 <!-- <Button label="New Test" severity="secondary" size="small" @click="toggle"></Button>
               
                    <Popover ref="op" class="pt-2">
                        <h6>New Exam<i class="pi pi-question-circle mx-2" style="font-size:12px;" v-tooltip="'In here you can create a new test'"></i></h6>
                        <i class="pi pi-times pt-2" style="cursor: pointer; position: absolute; right: 10px; top: 10px;" @click="op.hide()"></i>
                        <div class="d-flex flex-column p-4 col-md-12">
                             <Select v-model="selectedCity" :options="cities" optionLabel="name" placeholder="Select a sub Category" />
                            <label class="mt-4 mb-2">Select a Exam Level
                                <i class="pi pi-question-circle mx-2" style="font-size:12px;" v-tooltip="'you can choose the level of the exam , mixed will be for the easy and intermediate, expert combination'"></i>
                            </label> 
                                <RadioButtonGroup name="ingredient" class="row">
                                    <div class="col-md-6 d-flex gap-4">
                                        <RadioButton inputId="Easy" value="Easy" />
                                        <label for="Easy">Easy</label>
                                    </div>
                                    <div class="col-md-6 d-flex gap-4">
                                        <RadioButton inputId="Intermediate" value="Intermediate" />
                                        <label for="Intermediate">Intermediate</label>
                                    </div>
                                    <div class="col-md-6 d-flex gap-4">
                                        <RadioButton inputId="Expert" value="Expert" />
                                        <label for="Expert">Expert</label>
                                    </div>
                                     <div class="col-md-6 d-flex gap-4">
                                        <RadioButton inputId="Mixed" value="Mixed" />
                                        <label for="Mixed">Mixed</label>
                                    </div>
                                </RadioButtonGroup>
                               
                               <label for="NoOfQuestions" class="mt-4">
                                    Number of Questions
                                    <i class="pi pi-question-circle mx-2" style="font-size:12px;" v-tooltip="'You can add minimum 10 questions for the exam'"></i>
                                </label>
                                <InputText type="number" placeholder="for exam minimum 10 questions" class="w-100 " id="NoOfQuestions" />
                                <Button label="Create Exam" severity="secondary" class="w-100 mt-3" size="small" @click="createExam" autofocus />
                            </div>
                            
                    </Popover> -->
                    <template #footer>
                   
                    <Button label="Ad To Category" outlined severity="secondary" class="w-50" size="small" v-if="categories.length" @click="addCategory" autofocus />
                </template>
            
    </Dialog>
</template>
<script lang="ts" setup>
import { ref, defineProps, computed, watch, defineEmits } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import showAlert from '@/composables/showAlert';
import Message from 'primevue/message';
import Popover from 'primevue/popover';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import RadioButton from 'primevue/radiobutton';
import RadioButtonGroup from 'primevue/radiobuttongroup';
import NewExamCreate from './NewExamCreate.vue';


const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    title: {
        type: String,
        default: 'Edit Profile'
    }
});

const emit = defineEmits(['update:visible','showTestFilter']);

const visible = computed(() => props.visible);
const title = computed(() => props.title || 'Edit Profile');
const selectedCategories = ref<string[]>([]);
const showAlertError = ref(false);
const op = ref< InstanceType<typeof Popover> | null>(null);
const selectedCity = ref();
const cities = ref([
    { name: 'New York', code: 'NY' },
    { name: 'Rome', code: 'RM' },
    { name: 'London', code: 'LDN' },
    { name: 'Istanbul', code: 'IST' },
    { name: 'Paris', code: 'PRS' }
]);

const categories = ref([]);

const addCategory = async() => {
    // Logic to add the selected categories
    if(selectedCategories.value.length === 0) {
        showAlertError.value = true;
        return;
    }
        showAlertError.value = false;


    emit('update:visible', false); // Close the dialog after saving
};

const openTest = () => {
    // Logic to open the test
   emit('showTestFilter'); // Emit an event to show the test filter
    emit('update:visible', false); // Close the dialog after opening the test
};

const toggle = (event:any) => {
    if(op.value)
    {
        op.value.toggle(event);
    }
}
</script>