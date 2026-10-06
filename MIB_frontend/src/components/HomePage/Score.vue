<template>
  <div class="row flex-grow-1 overflow-auto m-0 justify-content-center mb-3 gap-4">

    <!-- Scores Section -->
    <div class="col-md-8 mt-3">
      <Card class="shadow-sm border-0 rounded-3">
        <template #title>
          <div class="d-flex justify-content-between align-items-center w-100">
            <h5 class="fw-semibold m-0">Your Scores</h5>
            <Button variant="link" icon="pi pi-arrow-left" label="go back" @click="()=> router.go(-1)"></Button>
            <!-- <span class="badge bg-primary fs-6">
              Total: {{ scores.totalScore }}
            </span> -->
          </div>
        </template>

        <template #content>
          <Tabs value="0" class="mt-3">
            <TabList>
              <Tab value="0"><span v-tooltip="'Life Competency Index'">LCI</span></Tab>
              <Tab value="1">Identity verification</Tab>
              <Tab value="2">Education</Tab>
              <Tab value="3">Experiance</Tab>
              <Tab value="4">Formal Recognition</Tab>
              <Tab value="5">Daily Questions</Tab>
              <Tab value="6">Exams</Tab>
              <Tab value="6">Others</Tab>
            </TabList>

            <TabPanels>
            
              <TabPanel value="1">
                <div
                  class="d-flex justify-content-between align-items-center p-3 border rounded-3 mb-2 bg-light"
                >
                 <table class="table">
                  <thead>
                    <tr>
                      <th></th>
                      <th>Verified</th>
                      <th>Verified By</th>
                      <th>Score</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>1. School/ univercity mate</td>
                      <td></td>
                      <td></td>
                      <td></td>
                    </tr>
                    <tr>
                      <td>2. School/ univercity mate</td>
                      <td></td>
                      <td></td>
                      <td></td>
                    </tr>
                    <tr>
                      <td>3. School/ univercity mate</td>
                      <td></td>
                      <td></td>
                      <td></td>
                    </tr>
                    <tr>
                      <td>4. Neighbour within same district</td>
                      <td></td>
                      <td></td>
                      <td></td>
                    </tr>
                    <tr>
                      <td>5. Neighbour within same district</td>
                      <td></td>
                      <td></td>
                      <td></td>
                    </tr>
                  </tbody>
                 </table>
                </div>
              </TabPanel>

              <!-- Profile Updates -->
              <TabPanel value="5">
                 <!-- <div
                  v-for="question in scores.daily_questions"
                  :key="question.id"
                  class="d-flex justify-content-between align-items-center p-3 border rounded-3 mb-2 bg-light"
                >
                  <small class="text-muted">{{ question.question_date }}</small>
                  <span class="fw-semibold flex-grow-1 text-center px-3">
                    {{ question.question }}
                  </span>
                  <span class="fw-bold text-success">
                    +{{ question.points }}
                  </span>
                </div> -->

              </TabPanel>

              <!-- Exams -->
              <TabPanel value="6">
                <!-- <div
                  v-for="update in scores.exam"
                  :key="update.id"
                  class="d-flex justify-content-between align-items-center p-3 border rounded-3 mb-2 bg-light"
                >
                  <small class="text-muted">{{ update.exam }}</small>
                  <span class="fw-semibold flex-grow-1 text-center px-3">
                    {{ update.diffuculty_level }}
                  </span>
                  <span class="fw-bold text-primary">
                    +{{ update.points }}
                  </span>
                </div> -->
              </TabPanel>
              <TabPanel value="2">
                 <div
                  v-for="education in scores.education"
                  :key="education.id"
                  class="d-flex justify-content-between align-items-center p-3 border rounded-3 mb-2 bg-light"
                >
                  <small class="text-muted">{{ education.degree }}</small>
                  <span class="fw-semibold flex-grow-1 text-center px-3">
                    {{ education.category }}
                  </span>
                  <span class="fw-bold text-success">
                    +{{ education.calculated_score }}
                  </span>
                </div>

              </TabPanel>

               <TabPanel value="3">
                 <div
                  v-for="experiance in scores.experience"
                  :key="experiance.id"
                  class="d-flex justify-content-between align-items-center p-3 border rounded-3 mb-2 bg-light"
                >
                  <small class="text-muted">{{ experiance.company }}</small>
                  <span class="fw-semibold flex-grow-1 text-center px-3">
                    {{ experiance.position }}
                  </span>
                  <span class="fw-bold text-success">
                    +{{ experiance.calculated_score }}
                  </span>
                </div>

              </TabPanel>

              <TabPanel value="0">
                <div
                  class="d-flex justify-content-between align-items-center p-3 border rounded-3 mb-2 bg-light"
                >
                  <small class="text-muted">Life Competency Index(LCI)</small>
                  {{scores.totalScore}}
                </div>
                <div
                  class="d-flex justify-content-between align-items-center p-3 border rounded-3 mb-2 bg-light"
                >
                  <small class="text-muted">Human Intelligence Portfolio - “ Build your Human Intelligence Portfolio — where knowledge meets integrity.”</small>
                </div>
              </TabPanel>
            </TabPanels>
          </Tabs>
        </template>
      </Card>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref , computed, onMounted, onBeforeUnmount} from 'vue';
import ProfileDetails from '@/components/HomePage/ProfileDetails.vue';
import { useUserProfile} from '../../stores/User/userProfile';
import Card from 'primevue/card';
import Divider from 'primevue/divider';
import Button from 'primevue/button'
import type {ScoreDetails, DailyQuestions, education}  from '@/types/ScoreDetails';
import { useRouter} from 'vue-router'


import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';


const userProfile = useUserProfile();
const router = useRouter();

const scores = ref({
  education:[{
    'id':'',
    'degree':'',
    'category':'',
    'calculated_score':0
  }],
  experience:[{
    'id':'',
    'company':'',
    'position':'',
    'calculated_score':0
  }],
  daily_questions:[],
  totalScore:0
});

const getScores = async () => {
    let result = await userProfile.getScores();
    if(result.data){
        scores.value = result.data;
        console.log(scores.value)
    }
};

onMounted(() => {
    getScores();
});
</script>
