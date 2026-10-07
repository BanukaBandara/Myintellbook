<template>
  <div class="row flex-grow-1 overflow-auto m-0 justify-content-center mb-3 gap-4">

    <!-- Scores Section -->
    <div class="col-md-8 mt-3">
      <Card class="shadow-sm border-0 rounded-3">
        <template #title>
          <div class="d-flex justify-content-between align-items-center w-100">
            <h5 class="fw-semibold m-0">Your Scores</h5>
            <RouterLink :to="{ name: 'profile' }" class="back-link">
              <i class="bi bi-arrow-left" aria-hidden="true"></i> go back
            </RouterLink>
          </div>
        </template>

        <template #content>
          <Tabs :value="activeTab" scrollable class="score-tabs mt-3" @update:value="selectTab">
            <TabList>
              <Tab v-for="tab in tabs" :key="tab.value" :value="tab.value">
                <span v-if="tab.tooltip" v-tooltip="tab.tooltip">{{ tab.label }}</span>
                <template v-else>{{ tab.label }}</template>
              </Tab>
            </TabList>

            <TabPanels>
              <TabPanel value="lci">
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

              <TabPanel v-for="tab in sectionTabs" :key="tab.value" :value="tab.value">
                <div v-if="scoresLoading" class="score-skeleton" aria-label="Loading scores"></div>

                <div v-else-if="scoresError" class="lci-error" role="alert">
                  <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                  {{ scoresError }}
                  <button type="button" class="link-button" @click="loadScores">Try again</button>
                </div>

                <div v-else-if="!scores?.[tab.value]?.length" class="score-empty">
                  <i :class="['bi', tab.icon]" aria-hidden="true"></i>
                  <p>{{ tab.empty }}</p>
                </div>

                <template v-else>
                  <ul class="score-list">
                    <li v-for="item in scores[tab.value]" :key="item.id" class="score-item">
                      <div class="score-item-text">
                        <span class="score-item-title">{{ item.title }}</span>
                        <span v-if="item.subtitle || item.date" class="score-item-meta">
                          {{ item.subtitle }}<template v-if="item.subtitle && item.date"> · </template>{{ formatDate(item.date) }}
                        </span>
                      </div>
                      <span v-if="statusLabel(item.status)" class="score-status">{{ statusLabel(item.status) }}</span>
                      <span class="score-points" :class="pointsClass(item.points)">{{ formatPoints(item.points) }}</span>
                    </li>
                  </ul>
                  <div class="score-section-total">
                    <span>{{ tab.label }} total</span>
                    <span class="score-points" :class="pointsClass(sectionTotal(tab.value))">
                      {{ formatPoints(sectionTotal(tab.value)) }}
                    </span>
                  </div>
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
import { ref, onMounted } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useUserProfile } from '../../stores/User/userProfile';
import Card from 'primevue/card';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import type { ScoreDetails, ScoreSectionKey } from '@/types/ScoreDetails';
import HipRankBadge from '@/components/commonComponents/HipRankBadge.vue';
import { refreshLci, type LciSummary } from '@/services/lci';

type SectionTab = { value: ScoreSectionKey; label: string; icon: string; empty: string };

const sectionTabs: SectionTab[] = [
  { value: 'identity', label: 'Identity verification', icon: 'bi-person-check', empty: 'No verified identity checks yet.' },
  { value: 'education', label: 'Education', icon: 'bi-mortarboard', empty: 'No education records yet. Add one from your profile.' },
  { value: 'experience', label: 'Experience', icon: 'bi-briefcase', empty: 'No work experience yet. Add one from your profile.' },
  { value: 'formal_recognition', label: 'Formal Recognition', icon: 'bi-award', empty: 'No verified awards or professional registrations yet.' },
  { value: 'daily_questions', label: 'Daily Questions', icon: 'bi-question-circle', empty: 'You have not answered any daily questions yet.' },
  { value: 'exams', label: 'Exams', icon: 'bi-journal-check', empty: 'You have not completed any exams yet.' },
  { value: 'others', label: 'Others', icon: 'bi-shield-check', empty: 'Nothing to show here.' },
];

const tabs: { value: string; label: string; tooltip?: string }[] = [
  { value: 'lci', label: 'LCI', tooltip: 'Life Competency Index' },
  ...sectionTabs,
];

const route = useRoute();
const router = useRouter();
const userProfile = useUserProfile();

// The active tab lives in ?tab= so a refresh or shared link reopens the same tab.
const initialTab = String(route.query.tab ?? '');
const activeTab = ref(tabs.some((tab) => tab.value === initialTab) ? initialTab : 'lci');

const selectTab = (value: string | number) => {
  activeTab.value = String(value);
  router.replace({ query: { ...route.query, tab: activeTab.value === 'lci' ? undefined : activeTab.value } });
};

const scores = ref<ScoreDetails | null>(null);
const scoresLoading = ref(true);
const scoresError = ref('');

const loadScores = async () => {
  scoresLoading.value = true;
  scoresError.value = '';
  const result = await userProfile.getScores();
  if (result?.data) {
    scores.value = result.data;
  } else {
    scoresError.value = 'Your score history could not be loaded.';
  }
  scoresLoading.value = false;
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

const formatPoints = (value: number) => `${value >= 0 ? '+' : ''}${formatScore(value)}`;

const pointsClass = (value: number) => (value > 0 ? 'positive' : value < 0 ? 'negative' : 'zero');

const formatDate = (value: string | null) =>
  value ? new Date(`${value}T00:00:00`).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '';

const STATUS_LABELS: Record<string, string> = {
  pending: 'Pending',
  incorrect: 'Incorrect',
  expired: 'Expired',
  'already credited': 'Already credited',
};

const statusLabel = (status: string) => STATUS_LABELS[status] ?? '';

const sectionTotal = (key: ScoreSectionKey) =>
  Math.round((scores.value?.[key] ?? []).reduce((sum, item) => sum + item.points, 0) * 100) / 100;

const barWidth = (value: number) => {
  const largest = Math.max(1, ...(lci.value?.breakdown ?? []).map((item) => Math.abs(item.score)));
  return Math.round(Math.abs(value) / largest * 100);
};

onMounted(() => {
  loadScores();
  loadLci();
});
</script>

<style scoped>
.back-link { display: inline-flex; align-items: center; gap: 6px; color: var(--ds-primary-hover); font-size: 14px; font-weight: 600; text-decoration: none; }
.back-link:hover { text-decoration: underline; }

/* Active tab: rose-600 text and a 2px underline that slides between tabs. */
.score-tabs :deep(.p-tab) { color: var(--ds-text-muted); font-weight: 500; border-bottom: 2px solid transparent; white-space: nowrap; transition: color .2s ease; }
.score-tabs :deep(.p-tab:hover) { color: #e11d48; }
.score-tabs :deep(.p-tab-active) { color: #e11d48; font-weight: 600; }
.score-tabs :deep(.p-tablist-active-bar) { height: 2px; background: #e11d48; transition: left .25s ease, width .25s ease; }

.lci-skeleton, .score-skeleton { height: 220px; background: linear-gradient(90deg, var(--ds-surface-muted), var(--ds-border), var(--ds-surface-muted)); background-size: 200% 100%; border-radius: 14px; animation: lci-shimmer 1.4s ease-in-out infinite; }
.score-skeleton { height: 140px; }
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

.score-list { margin: 0; padding: 0; list-style: none; }
.score-item { display: flex; align-items: center; gap: 12px; padding: 12px 14px; margin-bottom: 8px; background: var(--ds-surface-subtle); border: 1px solid var(--ds-border); border-radius: 10px; }
.score-item-text { display: flex; flex: 1; flex-direction: column; min-width: 0; }
.score-item-title { overflow: hidden; color: var(--ds-text); font-size: 14px; font-weight: 600; text-overflow: ellipsis; }
.score-item-meta { color: var(--ds-text-muted); font-size: 12px; }
.score-status { flex-shrink: 0; padding: 2px 8px; color: var(--ds-text-muted); font-size: 11px; font-weight: 600; background: var(--ds-surface-muted); border-radius: 999px; }

.score-points { flex-shrink: 0; min-width: 72px; font-size: 14px; font-weight: 700; font-variant-numeric: tabular-nums; text-align: right; }
.score-points.positive { color: var(--ds-success-text); }
.score-points.zero { color: var(--ds-text-subtle); }
.score-points.negative { color: var(--ds-primary-hover); }

.score-section-total { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px 0; margin-top: 4px; color: var(--ds-text); font-size: 14px; font-weight: 800; border-top: 2px solid var(--ds-border); }

.score-empty { padding: 32px 16px; color: var(--ds-text-muted); text-align: center; }
.score-empty i { font-size: 28px; }
.score-empty p { margin: 8px 0 0; font-size: 13px; }

@media (max-width: 575px) {
  .lci-breakdown li { grid-template-columns: 1fr auto; }
  .lci-bar { grid-column: 1 / -1; grid-row: 2; }
  .score-item { flex-wrap: wrap; }
  .score-item-text { flex-basis: 100%; }
}

@media (prefers-reduced-motion: reduce) {
  .lci-skeleton, .score-skeleton { animation: none; }
  .score-tabs :deep(.p-tablist-active-bar) { transition: none; }
}
</style>
