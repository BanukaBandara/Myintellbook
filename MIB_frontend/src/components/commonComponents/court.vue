<template>
  <InfoPageShell
    :icon="heroConfig.icon"
    :eyebrow="heroConfig.eyebrow"
    :title="heroConfig.title"
    :subtitle="heroConfig.subtitle"
  >
    <nav class="mode-switch" aria-label="Trust and Tribunal">
      <RouterLink to="/submit_case" class="mode-tab" :class="{ active: activeMode === 'verify' }" :aria-current="activeMode === 'verify' ? 'page' : undefined">
        <i class="bi bi-patch-check" aria-hidden="true"></i> Verify Identity
      </RouterLink>
      <RouterLink to="/submit_case/external" class="mode-tab" :class="{ active: activeMode === 'external' }" :aria-current="activeMode === 'external' ? 'page' : undefined">
        <i class="bi bi-send" aria-hidden="true"></i> Submit a Case
      </RouterLink>
      <RouterLink to="/submit_case/report-misconduct" class="mode-tab" :class="{ active: activeMode === 'report-misconduct' }" :aria-current="activeMode === 'report-misconduct' ? 'page' : undefined">
        <i class="bi bi-shield-exclamation" aria-hidden="true"></i> Report Misconduct
      </RouterLink>
    </nav>

    <!-- VERIFY IDENTITY: internal peer verification -->
    <template v-if="activeMode === 'verify'">
      <section class="panel" aria-labelledby="request-title">
        <div class="panel-head">
          <h2 id="request-title" class="panel-title">New verification request</h2>
          <span class="step-count">Step {{ step + 1 }} of {{ STEPS.length }}</span>
        </div>

        <div class="progress-track" role="progressbar" :aria-valuenow="step + 1" aria-valuemin="1" :aria-valuemax="STEPS.length" :aria-label="`Step ${step + 1} of ${STEPS.length}`">
          <span :style="{ width: `${((step + 1) / STEPS.length) * 100}%` }"></span>
        </div>
        <ol class="step-list">
          <li v-for="(item, index) in STEPS" :key="item.key" :class="{ current: index === step, done: index < step }">
            <span class="step-dot" aria-hidden="true"><i :class="['bi', index < step ? 'bi-check-lg' : item.icon]"></i></span>
            <span class="step-name">{{ item.label }}</span>
          </li>
        </ol>

        <div class="step-body">
          <div v-if="step === 0" class="field">
            <label for="member-select" class="field-label">Which member’s claim should be verified?</label>
            <Select
              v-model="member"
              input-id="member-select"
              :options="users"
              option-label="label"
              placeholder="Search members"
              filter
              class="w-100"
              :loading="usersLoading"
            />
            <p class="field-hint">Choose from members on MyIntellibook.</p>
          </div>

          <div v-else-if="step === 1" class="field">
            <label for="claim-select" class="field-label">What should be verified?</label>
            <Select
              v-model="category"
              input-id="claim-select"
              :options="criteriaGroups"
              option-label="label"
              option-group-label="label"
              option-group-children="items"
              placeholder="Select a claim category"
              filter
              class="w-100"
            />
            <p class="field-hint">Verified claims count toward the member’s LCI; confirmed misconduct is scored as a penalty.</p>
          </div>

          <div v-else-if="step === 2" class="field">
            <label for="juror-select" class="field-label">Who should review it?</label>
            <Select
              v-model="jury"
              input-id="juror-select"
              :options="jurorOptions"
              option-label="label"
              placeholder="Search for a peer juror"
              filter
              class="w-100"
            />
            <p class="field-hint">The juror can’t be the member being verified.</p>
          </div>

          <dl v-else class="review-list">
            <div><dt>Member</dt><dd>{{ member?.label }}</dd></div>
            <div><dt>Claim</dt><dd>{{ category?.label }} <span class="review-group">{{ category?.category }}</span></dd></div>
            <div><dt>Peer juror</dt><dd>{{ jury?.label }}</dd></div>
          </dl>
        </div>

        <p v-if="formError" class="alert error" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ formError }}</p>
        <p v-if="formSuccess" class="alert success" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i> {{ formSuccess }}</p>

        <footer class="form-nav">
          <button type="button" class="btn ghost" :disabled="step === 0 || submitting" @click="step--">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Back
          </button>
          <button v-if="step < STEPS.length - 1" type="button" class="btn primary" :disabled="!canContinue" @click="next">
            Continue <i class="bi bi-arrow-right" aria-hidden="true"></i>
          </button>
          <button v-else type="button" class="btn primary" :disabled="submitting" @click="submitRequest">
            <span v-if="submitting" class="spinner" aria-hidden="true"></span>
            {{ submitting ? 'Submitting' : 'Submit request' }}
          </button>
        </footer>
      </section>

      <ComplainShows
        :ComplainsArray="ComplainsArray"
        :activeStatus="activeStatus"
        :loading="listLoading"
        @statusChange="getStatusComplains"
      />
    </template>

    <!-- SUBMIT A CASE: External Tribunal hub -->
    <template v-else-if="activeMode === 'external'">
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

    <!-- REPORT MISCONDUCT: Internal Tribunal reporting -->
    <template v-else>
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
  </InfoPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import Select from 'primevue/select';
import InfoPageShell from '@/components/infoPages/InfoPageShell.vue';
import { useUserProfile } from '@/stores/User/userProfile';
import { useTribunalStore } from '@/stores/tribunal';
import ComplainShows from './ComplainShows.vue';
import InternalReportWizard from '@/components/internalTribunal/InternalReportWizard.vue';

interface Props {
  initialMode?: 'verify' | 'external' | 'report-misconduct';
}

const props = withDefaults(defineProps<Props>(), {
  initialMode: undefined,
});

export interface ComplainType {
  user: string;
  complain: string;
  complain_from: string;
  status: string | number;
  date: string;
  jury: Array<{ name: string; profile_image: string; profile_url: string }> | string;
}

type Option = { label: string; id: number };
type Criterion = { id: number; category: string; label: string };

const route = useRoute();
const userProfile = useUserProfile();
const tribunalStore = useTribunalStore();

const activeMode = computed<'verify' | 'external' | 'report-misconduct'>(() => {
  if (props.initialMode) return props.initialMode;
  const slug = route.params.slug;
  const tab = route.query.tab;
  if (
    slug === 'report-misconduct' ||
    slug === 'internal' ||
    tab === 'report-misconduct' ||
    route.path.startsWith('/internal-tribunal')
  ) {
    return 'report-misconduct';
  }
  if (slug === 'external') {
    return 'external';
  }
  return 'verify';
});

const isExternal = computed(() => activeMode.value === 'external');

const heroConfig = computed(() => {
  if (activeMode.value === 'report-misconduct') {
    return {
      icon: 'bi-shield-exclamation',
      eyebrow: 'Confidential Oversight',
      title: 'Report Misconduct',
      subtitle: 'Submit a confidential internal report for policy violations, fraud, harassment, or abuse directly to Super Administrators.',
    };
  }
  if (activeMode.value === 'external') {
    return {
      icon: 'bi-send',
      eyebrow: 'Online Tribunal',
      title: 'Submit a Case',
      subtitle: 'File a case with the External Tribunal, or follow the cases you are involved in.',
    };
  }
  return {
    icon: 'bi-patch-check',
    eyebrow: 'Peer verification',
    title: 'Verify Identity',
    subtitle: 'Ask a peer juror to verify a member’s claim — a qualification, role or conduct record — and track its progress.',
  };
});

const STEPS = [
  { key: 'member', label: 'Member', icon: 'bi-person' },
  { key: 'claim', label: 'Claim', icon: 'bi-card-checklist' },
  { key: 'juror', label: 'Peer juror', icon: 'bi-people' },
  { key: 'review', label: 'Review', icon: 'bi-eye' },
];

const CASE_FLOW = [
  { title: 'File', text: 'Choose a respondent and describe the issue.' },
  { title: 'Respond', text: 'The respondent acknowledges and replies; evidence can be added.' },
  { title: 'Resolve', text: 'Mediation or an adjudicator panel reaches a decision.' },
];

const profileCriteria: Criterion[] = [
  // Profile Verification
  { id: 1, category: 'Profile Verification', label: 'Photo Verification' },

  // Education & Career
  { id: 3, category: 'Education & Career', label: 'PhD / Doctorate' },
  { id: 4, category: 'Education & Career', label: 'Founder (20+ Years Company)' },
  { id: 5, category: 'Education & Career', label: 'Managerial / Leadership Role' },
  { id: 6, category: 'Education & Career', label: 'Published Book / Author' },

  // Legal Standing
  { id: 7, category: 'Legal Standing', label: 'Solved Legal Case (Plaintiff)' },
  { id: 8, category: 'Legal Standing', label: 'Legal Case (Defendant, cleared honorably)' },
  { id: 9, category: 'Legal Standing', label: 'Legal Case (Defendant, convicted)' },
  { id: 10, category: 'Legal Standing', label: 'Divorce (mutual)' },
  { id: 11, category: 'Legal Standing', label: 'Divorce (with misconduct proven)' },
  { id: 12, category: 'Legal Standing', label: 'Stable Family / No Legal Disputes' },

  // Social & Ethical
  { id: 13, category: 'Social & Ethical', label: 'Volunteer Work (per year)' },
  { id: 14, category: 'Social & Ethical', label: 'NGO / Charity Founder' },
  { id: 15, category: 'Social & Ethical', label: 'Political or Religious Extremism' },
  { id: 16, category: 'Social & Ethical', label: 'Ethical Breach / Bribery Allegation' },

  // Academic & Mentorship
  { id: 17, category: 'Academic & Mentorship', label: 'University Lecturer / Trainer' },
  { id: 18, category: 'Academic & Mentorship', label: 'Mentorship / Student Guidance' },

  // Professional Contributions
  { id: 19, category: 'Professional Contributions', label: 'Global Project Management' },
  { id: 20, category: 'Professional Contributions', label: 'Public Speaking / Presenter' },
  { id: 21, category: 'Professional Contributions', label: 'Media Column Writer / Appearance' },
  { id: 22, category: 'Professional Contributions', label: 'Disciplinary Action in Employment' },
  { id: 23, category: 'Professional Contributions', label: 'Ethical / Sustainable Projects' },
];

const criteriaGroups = computed(() => {
  const groups = new Map<string, Criterion[]>();
  profileCriteria.forEach((item) => groups.set(item.category, [...(groups.get(item.category) ?? []), item]));
  return [...groups].map(([label, items]) => ({ label, items }));
});

const users = ref<Option[]>([]);
const usersLoading = ref(false);
const member = ref<Option | null>(null);
const category = ref<Criterion | null>(null);
const jury = ref<Option | null>(null);
const step = ref(0);
const submitting = ref(false);
const formError = ref('');
const formSuccess = ref('');

const jurorOptions = computed(() => users.value.filter((user) => user.id !== member.value?.id));

const canContinue = computed(() => [member.value, category.value, jury.value][step.value] != null);

const ComplainsArray = ref<ComplainType[]>([]);
const activeStatus = ref('submitted');
const listLoading = ref(false);

function next(): void {
  formError.value = '';
  formSuccess.value = '';
  if (step.value === 0 && jury.value?.id === member.value?.id) jury.value = null;
  step.value++;
}

async function submitRequest(): Promise<void> {
  if (!member.value || !category.value || !jury.value || submitting.value) return;
  submitting.value = true;
  formError.value = '';

  const request: { from: number; defendent: number; category: string; jury: number } = {
    from: 1,
    defendent: member.value.id,
    category: category.value.label,
    jury: jury.value.id,
  };

  try {
    const result = await userProfile.submitComplains(request);
    if (result?.code !== 200) throw new Error(result?.message);

    formSuccess.value = `Request sent to ${jury.value.label}. You can follow it below.`;
    member.value = null;
    category.value = null;
    jury.value = null;
    step.value = 0;
    await getStatusComplains('submitted');
  } catch {
    formError.value = 'The request could not be submitted. Please try again.';
  } finally {
    submitting.value = false;
  }
}

async function getComplains(status: number): Promise<void> {
  listLoading.value = true;
  try {
    const result = await userProfile.getComplains(status);
    ComplainsArray.value = result?.code === 200 ? result.data.complains : [];
  } finally {
    listLoading.value = false;
  }
}

async function getStatusComplains(status: string): Promise<void> {
  activeStatus.value = status;
  const statusCode = status === 'inprogress' ? 2 : status === 'solved' ? 3 : 1;
  await getComplains(statusCode);
}

async function getUsersList(): Promise<void> {
  usersLoading.value = true;
  try {
    const result = await userProfile.getUserProfiles();
    if (result?.code === 200) {
      users.value = result.data
        .filter((user: any) => user.full_name)
        .map((user: any) => ({ label: user.full_name, id: user.id }));
    }
  } finally {
    usersLoading.value = false;
  }
}

// The same component serves both sidebar links, so react when the URL changes between them.
watch(isExternal, (external) => {
  if (external) void tribunalStore.fetchCapabilities();
});

onMounted(async () => {
  await Promise.all([getComplains(1), getUsersList(), tribunalStore.fetchCapabilities()]);
});
</script>

<style scoped>
.mode-switch { display: flex; gap: 4px; margin-bottom: 14px; padding: 4px; background: var(--ds-surface-muted); border-radius: 14px; }

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

.panel { margin-bottom: 14px; padding: 18px; background: #fff; border: 1px solid var(--ds-border); border-radius: 18px; box-shadow: 0 1px 2px rgba(15, 23, 42, .05); }
.panel-head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 6px; margin-bottom: 10px; }
.panel-title { margin: 0 0 4px; color: var(--ds-text); font-size: 16px; font-weight: 800; }
.step-count { color: var(--ds-primary); font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }

.progress-track { height: 6px; overflow: hidden; background: var(--ds-surface-muted); border-radius: 999px; }
.progress-track span { display: block; height: 100%; background: linear-gradient(90deg, var(--ds-primary-300), var(--ds-primary)); border-radius: 999px; transition: width .3s ease; }

.step-list { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin: 12px 0 18px; padding: 0; list-style: none; }
.step-list li { display: flex; flex-direction: column; align-items: center; gap: 5px; color: var(--ds-text-subtle); font-size: 12px; font-weight: 600; text-align: center; }
.step-dot { display: grid; width: 30px; height: 30px; font-size: 13px; place-items: center; background: var(--ds-surface-muted); border-radius: 50%; }
.step-list li.current { color: var(--ds-primary-hover); }
.step-list li.current .step-dot { color: #fff; background: var(--ds-primary); box-shadow: 0 0 0 4px var(--ds-primary-100); }
.step-list li.done { color: var(--ds-success-text); }
.step-list li.done .step-dot { color: #fff; background: var(--ds-success); }

.step-body { min-height: 110px; }
.field { display: grid; gap: 8px; }
.field-label { color: var(--ds-text); font-size: 14.5px; font-weight: 700; }
.field-hint { margin: 0; color: var(--ds-text-muted); font-size: 12.5px; }

.review-list { display: grid; gap: 8px; margin: 0; }
.review-list > div { display: grid; grid-template-columns: 110px 1fr; gap: 10px; padding: 10px 12px; background: var(--ds-surface-subtle); border-radius: 12px; }
.review-list dt { color: var(--ds-text-muted); font-size: 12.5px; font-weight: 700; }
.review-list dd { margin: 0; color: var(--ds-text); font-size: 14px; font-weight: 600; }
.review-group { display: inline-block; margin-left: 6px; padding: 1px 8px; color: var(--ds-primary-hover); font-size: 11px; font-weight: 700; background: var(--ds-primary-soft); border-radius: 999px; }

.alert { display: flex; gap: 8px; margin: 14px 0 0; padding: 10px 12px; font-size: 13px; border-radius: 10px; }
.alert.error { color: var(--ds-danger-text); background: var(--ds-danger-soft); border: 1px solid var(--ds-danger-border); }
.alert.success { color: var(--ds-success-text); background: var(--ds-success-soft); border: 1px solid var(--ds-success-border); }

.form-nav { display: flex; justify-content: space-between; gap: 10px; margin-top: 18px; }

.btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 42px; padding: 9px 18px; font-size: 13.5px; font-weight: 500; border-radius: 12px; transition: background .2s ease, color .2s ease; }
.btn:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }
.btn.primary { color: #fff; background: var(--ds-primary); border: 0; box-shadow: var(--ds-shadow-sm); }
.btn.ghost { color: var(--ds-text-secondary); background: #fff; border: 1px solid var(--ds-border); }
.btn.primary:hover:not(:disabled) { background: var(--ds-primary-hover); }
.btn.ghost:hover:not(:disabled) { color: var(--ds-primary-hover); background: var(--ds-primary-soft); }
.btn:disabled { cursor: not-allowed; opacity: .45; box-shadow: none; }
.spinner { width: 14px; height: 14px; border: 2px solid #ffffff70; border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.flow { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; margin: 10px 0 0; padding: 0; list-style: none; }
.flow-step { position: relative; display: flex; gap: 10px; padding: 12px; background: #fcfcfd; border: 1px solid var(--ds-surface-muted); border-radius: 14px; }
.flow-num { display: grid; flex: 0 0 28px; width: 28px; height: 28px; color: #fff; font-size: 13px; font-weight: 800; place-items: center; background: var(--ds-primary); border-radius: 50%; }
.flow-title { margin: 0; color: var(--ds-text); font-size: 14px; font-weight: 750; }
.flow-text { margin: 2px 0 0; color: var(--ds-text-muted); font-size: 12.5px; line-height: 1.5; }

.portal-heading { margin: 18px 2px 10px; color: var(--ds-text); font-size: 15px; font-weight: 800; }
.action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }

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

.action-card:hover { color: var(--ds-text-secondary); border-color: var(--ds-primary-200); box-shadow: 0 8px 22px rgba(15, 23, 42, .07); transform: translateY(-2px); }
.action-card:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }
.action-card.featured { background: linear-gradient(160deg, var(--ds-primary-soft), #fff 60%); border-color: var(--ds-primary-200); }
.action-icon { display: grid; width: 42px; height: 42px; margin-bottom: 4px; color: var(--ds-primary); font-size: 19px; place-items: center; background: var(--ds-primary-soft); border-radius: 12px; }
.featured .action-icon { color: #fff; background: linear-gradient(135deg, var(--ds-primary-300), var(--ds-primary)); }
.action-title { display: flex; align-items: center; gap: 8px; color: var(--ds-text); font-size: 15px; font-weight: 750; }
.action-text { flex: 1; color: var(--ds-text-muted); font-size: 13px; line-height: 1.55; }
.action-cta { display: inline-flex; align-items: center; gap: 6px; margin-top: 6px; color: var(--ds-primary); font-size: 13px; font-weight: 700; }
.count-badge { padding: 1px 8px; color: #fff; font-size: 11px; font-weight: 800; background: var(--ds-primary); border-radius: 999px; }

.mt-3 { margin-top: 14px; }
.action-card.confidentiality-card { cursor: default; }
.action-card.confidentiality-card:hover { transform: none; border-color: var(--ds-border); box-shadow: none; }
.action-icon.confidential { color: #1e40af; background: #eff6ff; }
.action-cta.text-muted { color: var(--ds-text-muted); cursor: default; }

@media (max-width: 575px) {
  .panel { padding: 16px 14px; }
  .step-name { font-size: 11px; }
  .review-list > div { grid-template-columns: 1fr; gap: 2px; }
}

@media (prefers-reduced-motion: reduce) {
  .progress-track span, .action-card, .mode-tab, .btn { transition: none; }
  .spinner { animation: none; }
}
</style>
