<template>
  <InfoPageShell
    :icon="heroConfig.icon"
    :eyebrow="heroConfig.eyebrow"
    :title="heroConfig.title"
    :subtitle="heroConfig.subtitle"
  >
    <nav class="mode-switch" aria-label="Trust and Tribunal">
      <RouterLink
        to="/submit_case/report-misconduct"
        class="mode-tab"
        :class="{ active: activeMode === 'report-misconduct' }"
        :aria-current="activeMode === 'report-misconduct' ? 'page' : undefined"
      >
        <i class="bi bi-shield-exclamation" aria-hidden="true"></i> Report Misconduct
      </RouterLink>
      <RouterLink
        to="/submit_case/external"
        class="mode-tab"
        :class="{ active: activeMode === 'external' }"
        :aria-current="activeMode === 'external' ? 'page' : undefined"
      >
        <i class="bi bi-send" aria-hidden="true"></i> Submit a Case
      </RouterLink>
    </nav>

    <!-- REPORT MISCONDUCT: Internal Tribunal reporting -->
    <template v-if="activeMode === 'report-misconduct'">
      <InternalReportWizard />

      <div class="action-grid mt-3">
        <RouterLink to="/internal-tribunal" class="action-card">
          <span class="action-icon"><i class="bi bi-folder2-open" aria-hidden="true"></i></span>
          <span class="action-title">My Misconduct Reports</span>
          <span class="action-text">View the history, review status, and outcomes of internal reports you submitted.</span>
          <span class="action-cta">View My Reports <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
        </RouterLink>
        <div class="action-card confidentiality-card">
          <span class="action-icon confidential"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
          <span class="action-title">Confidentiality Guarantee</span>
          <span class="action-text">Internal reports are reviewed exclusively by Super Administrators. The reported member receives zero information about who filed the report.</span>
          <span class="action-cta text-muted"><i class="bi bi-check-circle" aria-hidden="true"></i> Fully Anonymous & Protected</span>
        </div>
      </div>
    </template>

    <!-- SUBMIT A CASE: External Tribunal hub -->
    <template v-else>
      <section class="panel" aria-labelledby="flow-title">
        <h2 id="flow-title" class="panel-title">How a case works</h2>
        <ol class="flow">
          <li v-for="(stage, index) in CASE_FLOW" :key="stage.title" class="flow-step">
            <span class="flow-num">{{ index + 1 }}</span>
            <div>
              <p class="flow-title">{{ stage.title }}</p>
              <p class="flow-text">{{ stage.text }}</p>
            </div>
          </li>
        </ol>
      </section>

      <div class="action-grid">
        <RouterLink to="/tribunal/create" class="action-card featured">
          <span class="action-icon"><i class="bi bi-plus-circle" aria-hidden="true"></i></span>
          <span class="action-title">File a new case</span>
          <span class="action-text">Submit an external case by selecting a respondent and providing details.</span>
          <span class="action-cta">File a Case <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
        </RouterLink>
        <RouterLink to="/tribunal/cases" class="action-card">
          <span class="action-icon"><i class="bi bi-folder2-open" aria-hidden="true"></i></span>
          <span class="action-title">My Cases</span>
          <span class="action-text">View cases you submitted and cases filed involving your account.</span>
          <span class="action-cta">View My Cases <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
        </RouterLink>
      </div>

      <template v-if="tribunalStore.capabilities?.adjudicator?.eligible || tribunalStore.capabilities?.representative?.eligible">
        <h2 class="portal-heading">Your portals</h2>
        <div class="action-grid">
          <RouterLink v-if="tribunalStore.capabilities?.adjudicator?.eligible" to="/tribunal/jury" class="action-card">
            <span class="action-icon"><i class="bi bi-bank" aria-hidden="true"></i></span>
            <span class="action-title">Adjudicator Assignments
              <span v-if="tribunalStore.capabilities?.adjudicator?.pending_assignments" class="count-badge">{{ tribunalStore.capabilities.adjudicator.pending_assignments }}</span>
            </span>
            <span class="action-text">Review case appointments, declare conflicts of interest, and deliberate on assigned dispute cases.</span>
            <span class="action-cta">Open assignments <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
          </RouterLink>
          <RouterLink v-if="tribunalStore.capabilities?.representative?.eligible" to="/tribunal/representation-requests" class="action-card">
            <span class="action-icon"><i class="bi bi-inbox" aria-hidden="true"></i></span>
            <span class="action-title">Representation Requests
              <span v-if="tribunalStore.capabilities?.representative?.pending_requests" class="count-badge">{{ tribunalStore.capabilities.representative.pending_requests }}</span>
            </span>
            <span class="action-text">Review and respond to client requests for External Tribunal counsel.</span>
            <span class="action-cta">Open inbox <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
          </RouterLink>
          <RouterLink v-if="tribunalStore.capabilities?.representative?.eligible" to="/tribunal/represented-cases" class="action-card">
            <span class="action-icon"><i class="bi bi-briefcase" aria-hidden="true"></i></span>
            <span class="action-title">Represented Cases</span>
            <span class="action-text">Active disputes where you are retained as legal counsel.</span>
            <span class="action-cta">Practice Dashboard <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
          </RouterLink>
        </div>
      </template>
    </template>
  </InfoPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import InfoPageShell from '@/components/infoPages/InfoPageShell.vue';
import { useTribunalStore } from '@/stores/tribunal';
import InternalReportWizard from '@/components/internalTribunal/InternalReportWizard.vue';

interface Props {
  initialMode?: 'external' | 'report-misconduct';
}

const props = withDefaults(defineProps<Props>(), {
  initialMode: undefined,
});

const route = useRoute();
const tribunalStore = useTribunalStore();

const activeMode = computed<'external' | 'report-misconduct'>(() => {
  if (props.initialMode) return props.initialMode;
  const slug = route.params.slug;
  const tab = route.query.tab;
  if (slug === 'external' || tab === 'external') {
    return 'external';
  }
  return 'report-misconduct';
});

const heroConfig = computed(() => {
  if (activeMode.value === 'report-misconduct') {
    return {
      icon: 'bi-shield-exclamation',
      eyebrow: 'CONFIDENTIAL OVERSIGHT',
      title: 'Report Misconduct',
      subtitle: 'Submit a confidential internal report for policy violations, fraud, harassment, or abuse directly to Super Administrators.',
    };
  }
  return {
    icon: 'bi-send',
    eyebrow: 'TRIBUNAL',
    title: 'Submit a Case',
    subtitle: 'Submit and manage Tribunal cases through the MyIntelliBook dispute resolution process.',
  };
});

const CASE_FLOW = [
  { title: 'File', text: 'Choose a respondent and describe the issue.' },
  { title: 'Respond', text: 'The respondent acknowledges and replies; evidence can be added.' },
  { title: 'Resolve', text: 'Mediation or an adjudicator panel reaches a decision.' },
];

watch(activeMode, (mode) => {
  if (mode === 'external') {
    void tribunalStore.fetchCapabilities();
  }
});

onMounted(async () => {
  await tribunalStore.fetchCapabilities();
});
</script>

<style scoped>
.mode-switch {
  display: flex;
  gap: 4px;
  margin-bottom: 14px;
  padding: 4px;
  background: var(--ds-surface-muted);
  border-radius: 14px;
}

.mode-tab {
  display: flex;
  flex: 1;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 40px;
  padding: 8px 12px;
  color: var(--ds-text-secondary);
  font-size: 13.5px;
  font-weight: 700;
  text-decoration: none;
  border-radius: 11px;
  transition: background-color .2s ease, color .2s ease, box-shadow .2s ease;
}

.mode-tab:hover { color: var(--ds-primary-hover); }
.mode-tab.active { color: var(--ds-primary-hover); background: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, .1); }
.mode-tab:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }

.panel {
  margin-bottom: 14px;
  padding: 18px;
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 18px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.panel-title {
  margin: 0 0 4px;
  color: var(--ds-text);
  font-size: 16px;
  font-weight: 800;
}

.flow {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 10px;
  margin: 10px 0 0;
  padding: 0;
  list-style: none;
}

.flow-step {
  position: relative;
  display: flex;
  gap: 10px;
  padding: 12px;
  background: #fcfcfd;
  border: 1px solid var(--ds-surface-muted);
  border-radius: 14px;
}

.flow-num {
  display: grid;
  flex: 0 0 28px;
  width: 28px;
  height: 28px;
  color: #fff;
  font-size: 13px;
  font-weight: 800;
  place-items: center;
  background: var(--ds-primary);
  border-radius: 50%;
}

.flow-title {
  margin: 0;
  color: var(--ds-text);
  font-size: 14px;
  font-weight: 750;
}

.flow-text {
  margin: 2px 0 0;
  color: var(--ds-text-muted);
  font-size: 12.5px;
  line-height: 1.5;
}

.portal-heading {
  margin: 18px 2px 10px;
  color: var(--ds-text);
  font-size: 15px;
  font-weight: 800;
}

.action-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
}

.action-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 18px;
  color: var(--ds-text-secondary);
  text-decoration: none;
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 18px;
  transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
}

.action-card:hover {
  color: var(--ds-text-secondary);
  border-color: var(--ds-primary-200);
  box-shadow: 0 8px 22px rgba(15, 23, 42, .07);
  transform: translateY(-2px);
}

.action-card:focus-visible {
  outline: 2px solid var(--ds-primary);
  outline-offset: 2px;
}

.action-card.featured {
  background: linear-gradient(160deg, var(--ds-primary-soft), #fff 60%);
  border-color: var(--ds-primary-200);
}

.action-icon {
  display: grid;
  width: 42px;
  height: 42px;
  margin-bottom: 4px;
  color: var(--ds-primary);
  font-size: 19px;
  place-items: center;
  background: var(--ds-primary-soft);
  border-radius: 12px;
}

.featured .action-icon {
  color: #fff;
  background: linear-gradient(135deg, var(--ds-primary-300), var(--ds-primary));
}

.action-title {
  display: flex;
  align-items: center;
  gap: 8px;
  color: var(--ds-text);
  font-size: 15px;
  font-weight: 750;
}

.action-text {
  flex: 1;
  color: var(--ds-text-muted);
  font-size: 13px;
  line-height: 1.55;
}

.action-cta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 6px;
  color: var(--ds-primary);
  font-size: 13px;
  font-weight: 700;
}

.count-badge {
  padding: 1px 8px;
  color: #fff;
  font-size: 11px;
  font-weight: 800;
  background: var(--ds-primary);
  border-radius: 999px;
}

.mt-3 { margin-top: 14px; }

.action-card.confidentiality-card { cursor: default; }
.action-card.confidentiality-card:hover {
  transform: none;
  border-color: var(--ds-border);
  box-shadow: none;
}

.action-icon.confidential {
  color: #1e40af;
  background: #eff6ff;
}

.action-cta.text-muted {
  color: var(--ds-text-muted);
  cursor: default;
}

@media (max-width: 575px) {
  .panel { padding: 16px 14px; }
  .mode-tab { font-size: 12.5px; padding: 8px 8px; }
}

@media (prefers-reduced-motion: reduce) {
  .action-card, .mode-tab { transition: none; }
}
</style>
