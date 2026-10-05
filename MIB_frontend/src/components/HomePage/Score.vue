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
                <div v-if="lciLoading" class="lci-skeleton" aria-label="Loading your LCI"></div>

                <div v-else-if="lciError" class="lci-error" role="alert">
                  <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                  {{ lciError }}
                  <button type="button" class="link-button" @click="loadLci">Try again</button>
                </div>

                <template v-else-if="lci">
                  <section class="lci-hero" :class="`tier-${lci.rank_tier.toLowerCase()}`">
                    <p class="lci-eyebrow">LIFE COMPETENCY INDEX</p>
                    <p class="lci-total">{{ formatScore(lci.lci_score) }}</p>
                    <p class="lci-caption">Sum of all verified section scores</p>
                    <HipRankBadge
                      size="large"
                      :score="lci.lci_score"
                      :rank="lci.hip_rank"
                      :tier="lci.rank_tier"
                      :color="lci.rank_badge_color"
                    />
                    <p class="lci-position">
                      <template v-if="lci.highest_lci > lci.lowest_lci">
                        {{ lci.percent_below_max === 0 ? 'Top score in the system' : `${lci.percent_below_max}% below the top score` }}
                        · Range {{ formatScore(lci.lowest_lci) }} – {{ formatScore(lci.highest_lci) }}
                      </template>
                      <template v-else>Everyone is currently level, so all users share the top rank.</template>
                    </p>
                  </section>

                  <h3 class="lci-subtitle">Score breakdown</h3>
                  <ul class="lci-breakdown">
                    <li v-for="item in lci.breakdown" :key="item.key">
                      <span class="lci-label">{{ item.label }}</span>
                      <span class="lci-bar" aria-hidden="true">
                        <span :style="{ width: `${barWidth(item.score)}%` }" :class="{ negative: item.score < 0 }"></span>
                      </span>
                      <span class="lci-value" :class="{ negative: item.score < 0, zero: item.score === 0 }">
                        {{ item.score > 0 ? '+' : '' }}{{ formatScore(item.score) }}
                      </span>
                    </li>
                    <li class="lci-total-row">
                      <span class="lci-label">Life Competency Index</span>
                      <span class="lci-value">{{ formatScore(lci.lci_score) }}</span>
                    </li>
                  </ul>

                  <p class="lci-quote">“Build your Human Intelligence Portfolio — where knowledge meets integrity.”</p>
                </template>
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
import HipRankBadge from '@/components/commonComponents/HipRankBadge.vue';
import { refreshLci, type LciSummary } from '@/services/lci';


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

const lci = ref<LciSummary | null>(null);
const lciLoading = ref(true);
const lciError = ref('');

const loadLci = async () => {
    lciLoading.value = true;
    lciError.value = '';
    try {
        lci.value = await refreshLci();
    } catch {
        lciError.value = 'Your LCI could not be loaded.';
    } finally {
        lciLoading.value = false;
    }
};

const formatScore = (value: number) => new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(value);

const barWidth = (value: number) => {
    const largest = Math.max(1, ...(lci.value?.breakdown ?? []).map((item) => Math.abs(item.score)));
    return Math.round(Math.abs(value) / largest * 100);
};

onMounted(() => {
    getScores();
    loadLci();
});
</script>

<style scoped>
.lci-skeleton { height: 220px; background: linear-gradient(90deg, var(--ds-surface-muted), var(--ds-border), var(--ds-surface-muted)); background-size: 200% 100%; border-radius: 14px; animation: lci-shimmer 1.4s ease-in-out infinite; }
@keyframes lci-shimmer { to { background-position: -200% 0; } }

.lci-error { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 12px; color: var(--ds-danger-text); font-size: 13px; background: var(--ds-danger-soft); border: 1px solid var(--ds-danger-border); border-radius: 10px; }
.link-button { padding: 0; color: var(--ds-primary-hover); font-weight: 700; text-decoration: underline; background: none; border: 0; }

.lci-hero { padding: 24px 16px; text-align: center; background: linear-gradient(160deg, #fff, var(--ds-surface-subtle)); border: 1px solid var(--ds-border); border-radius: 16px; }
.lci-hero.tier-platinum { background: linear-gradient(160deg, #fff, var(--ds-surface-muted)); border-color: var(--ds-border-strong); }
.lci-hero.tier-gold { background: linear-gradient(160deg, #fff, var(--ds-warning-soft)); border-color: var(--ds-warning-border); }
.lci-hero.tier-silver { background: linear-gradient(160deg, #fff, var(--ds-surface-muted)); border-color: var(--ds-border-strong); }
.lci-hero.tier-bronze { background: linear-gradient(160deg, #fff, #fdf2e9); border-color: #f5c9a3; }
.lci-eyebrow { margin: 0; color: var(--ds-text-muted); font-size: 10px; font-weight: 700; letter-spacing: .12em; }
.lci-total { margin: 6px 0 2px; color: var(--ds-text); font-size: clamp(32px, 7vw, 44px); font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1.1; }
.lci-caption { margin: 0 0 14px; color: var(--ds-text-muted); font-size: 12px; }
.lci-position { margin: 12px 0 0; color: var(--ds-text-muted); font-size: 12px; }

.lci-subtitle { margin: 22px 0 10px; color: var(--ds-text); font-size: 15px; font-weight: 700; }
.lci-breakdown { margin: 0; padding: 0; list-style: none; }
.lci-breakdown li { display: grid; grid-template-columns: minmax(130px, 1.2fr) 2fr auto; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--ds-surface-muted); }
.lci-label { color: var(--ds-text-secondary); font-size: 13px; }
.lci-bar { height: 6px; overflow: hidden; background: var(--ds-surface-muted); border-radius: 999px; }
.lci-bar span { display: block; height: 100%; background: var(--ds-success); border-radius: 999px; }
.lci-bar span.negative { background: var(--ds-primary); }
.lci-value { min-width: 80px; color: var(--ds-success-text); font-size: 13px; font-weight: 700; font-variant-numeric: tabular-nums; text-align: right; }
.lci-value.negative { color: var(--ds-primary-hover); }
.lci-value.zero { color: var(--ds-text-subtle); }
.lci-breakdown .lci-total-row { grid-template-columns: 1fr auto; border-bottom: 0; border-top: 2px solid var(--ds-border); }
.lci-total-row .lci-label, .lci-total-row .lci-value { color: var(--ds-text); font-weight: 800; }
.lci-quote { margin: 16px 0 0; color: var(--ds-text-muted); font-size: 12px; font-style: italic; text-align: center; }

@media (max-width: 575px) {
  .lci-breakdown li { grid-template-columns: 1fr auto; }
  .lci-bar { grid-column: 1 / -1; grid-row: 2; }
}

@media (prefers-reduced-motion: reduce) {
  .lci-skeleton { animation: none; }
}
</style>
