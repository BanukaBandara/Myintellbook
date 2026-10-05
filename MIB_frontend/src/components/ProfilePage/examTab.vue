<template>

    <Panel header="Header" :collapsed="collapsed" toggleable :pt="{
        root:'shadow-sm border-0 rounded-4 mb-4',
        pcToggleButton:'w-100',
    }">
    <template #header>      
        <div class=" fs-6 d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-primary mb-0 fs-5">📚 Exam Timeline</h4>
        </div>
    </template>
      <NewExamCreate v-if="isShow"/>
      <template #togglebutton v-if="isShow">
        <Button icon="pi pi-plus" label="create New" severity="success" size="small" class="w-100" rounded outlined @click="showPanel" v-if="isShow"/>
    </template>
    </Panel>

    <!-- Upcoming Exams -->
    <Card v-if="upcomingExams.length && isShow" class="shadow-sm border-0 rounded-4 mb-4">
        <template #title>
        <h5 class="fw-bold text-secondary mb-0">Not Compeleted Exams</h5>
        </template>
        <template #content>
        <div v-for="(exam, index) in upcomingExams" :key="'upcoming-'+index" class="p-3 mb-3 border-bottom last:border-0">
            <div class="d-flex justify-content-between align-items-start">
            <div>
                <h6 class="fw-bold mb-1">{{ exam.title }}</h6>
                <p class="text-muted small mb-1">{{ exam.profession }} • {{ exam.difficulty }}</p>
                <p class="small mb-0"><i class="pi pi-calendar"></i> {{ exam.created_at }}</p>
            </div>
            <Button label="Start Exam" class="p-button-sm rounded-pill" @click="() => { router.push('/startExam/'+exam.id)}"/>
            </div>
        </div>
        </template>
    </Card>

    <!-- Completed Exams -->
    <Card v-if="completedExams.length" class="shadow-sm border-0 rounded-4 mb-4">
        <template #title>
        <h5 class="fw-bold text-success mb-0">Completed Exams</h5>
        </template>
        <template #content>
        <div v-for="(exam, index) in completedExams" :key="'completed-'+index" class="p-3 mb-3 border-bottom last:border-0">
            <div class="d-flex justify-content-between align-items-start">
            <div>
                <h6 class="fw-bold mb-1">{{ exam.title }}</h6>
                <p class="text-muted small mb-1">{{ exam.profession }} • {{ exam.difficulty }}</p>
                <p class="small mb-0">
                <i class="pi pi-check-circle text-success"></i> Completed on {{ exam.attempted_at }}
                </p>
            </div>
            <div class="text-end">
                <span class="badge bg-success p-2 rounded-pill mb-2" style="font-size: 0.85rem">
                Score: {{ exam.percentage }}%
                </span>
                <br />
                <Button label="View Result" class="p-button-outlined p-button-sm rounded-pill mt-2" v-if="isShow"/>
            </div>
            </div>
        </div>
        </template>
    </Card>

    <!-- Empty State -->
    <Card v-if="!upcomingExams.length && !completedExams.length" class="shadow-sm border-0 rounded-4 text-center p-5">
        <i class="pi pi-inbox text-muted mb-3" style="font-size: 2rem"></i>
        <p class="text-muted">No exams found in your timeline.</p>
    </Card>
</template>
<script setup lang="ts">

import Button from 'primevue/button';
import Card from 'primevue/card';
import { ref, type PropType, computed } from 'vue';
import type { compeletedExamsType } from '@/types/compeletedExamType';
import { useRouter } from 'vue-router';
import NewExamCreate from '../HomePage/NewExamCreate.vue'; 
import Panel from 'primevue/panel';

const collapsed = ref(true);
const router = useRouter();
const props = defineProps({
  completedExams: {
    type: Array as PropType<compeletedExamsType[]>,
    default: () => []
  },
   upcommingExams: {
    type: Array as PropType<compeletedExamsType[]>,
    default: () => []
  },
  isShow:{
    type: Boolean,
    default:false
  }
});

const completedExams = computed(() => props.completedExams);
const upcomingExams = computed(() => props.upcommingExams);
const isShow = computed(()=> props.isShow);

const showPanel = () => {
    collapsed.value = !collapsed.value;
};
// const upcomingExams = ref([
//   { title: "Math Mock Test", subject: "Mathematics", level: "Grade 10", date: "Oct 10, 2025" },
//   { title: "History Quiz", subject: "History", level: "Grade 9", date: "Oct 15, 2025" }
// ]);

// const completedExams = ref([
//   { title: "Physics Midterm", subject: "Physics", level: "Grade 11", date: "Sep 28, 2025", score: 85 },
//   { title: "English Literature", subject: "English", level: "Grade 12", date: "Sep 20, 2025", score: 92 }
// ]);
</script>