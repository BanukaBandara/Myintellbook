<template>
  <section class="wizard" aria-labelledby="wizard-title">
    <header class="wizard-head">
      <div>
        <p class="eyebrow"><i class="bi bi-lock-fill" aria-hidden="true"></i> Encrypted draft</p>
        <h2 id="wizard-title" class="wizard-title">{{ testament ? 'Edit your testament' : 'Create your testament' }}</h2>
      </div>
      <button type="button" class="link-button" @click="emit('close')">Close</button>
    </header>

    <div class="progress-track" role="progressbar" :aria-valuenow="step + 1" aria-valuemin="1" :aria-valuemax="STEPS.length" :aria-label="`Step ${step + 1} of ${STEPS.length}`">
      <span :style="{ width: `${((step + 1) / STEPS.length) * 100}%` }"></span>
    </div>
    <ol class="step-list">
      <li v-for="(item, index) in STEPS" :key="item.key">
        <button
          type="button"
          class="step-button"
          :class="{ current: index === step, done: index < step }"
          :aria-current="index === step ? 'step' : undefined"
          @click="goTo(index)"
        >
          <span class="step-dot" aria-hidden="true"><i :class="['bi', index < step ? 'bi-check-lg' : item.icon]"></i></span>
          <span class="step-name">{{ item.label }}</span>
        </button>
      </li>
    </ol>

    <div class="step-body">
      <!-- 1. Details -->
      <div v-if="step === 0" class="fields">
        <label class="field">
          <span class="field-label">Title</span>
          <input v-model.trim="form.title" type="text" maxlength="150" placeholder="e.g. Last will and testament">
        </label>
        <label class="field">
          <span class="field-label">Your instructions</span>
          <textarea v-model="form.instructions" rows="8" maxlength="20000" placeholder="Describe how your estate and personal commitments should be handled."></textarea>
          <span class="field-hint">{{ form.instructions.length.toLocaleString() }} / 20,000 characters · stored encrypted</span>
        </label>
      </div>

      <!-- 2. Health verification -->
      <div v-else-if="step === 1" class="fields">
        <p class="intro">
          The Legal &amp; Quality groups use this declaration to confirm you prepared your testament in good health and of
          your own free will.
        </p>
        <label class="check-card" :class="{ checked: form.health.sound_mind }">
          <input v-model="form.health.sound_mind" type="checkbox">
          <span><strong>I am of sound mind</strong> and understand the nature and effect of this testament.</span>
        </label>
        <label class="check-card" :class="{ checked: form.health.no_duress }">
          <input v-model="form.health.no_duress" type="checkbox">
          <span><strong>I am acting freely</strong>, without pressure or undue influence from anyone.</span>
        </label>
        <div class="field-row">
          <label class="field">
            <span class="field-label">Physician name <span class="optional">optional</span></span>
            <input v-model.trim="form.health.physician_name" type="text" maxlength="120">
          </label>
          <label class="field">
            <span class="field-label">Assessment date <span class="optional">optional</span></span>
            <input v-model="form.health.assessment_date" type="date" :max="today">
          </label>
        </div>
        <label class="field">
          <span class="field-label">Health notes <span class="optional">optional</span></span>
          <textarea v-model="form.health.notes" rows="3" maxlength="2000"></textarea>
        </label>
      </div>

      <!-- 3. Beneficiaries -->
      <div v-else-if="step === 2" class="fields">
        <div class="share-meter" :class="shareState">
          <div class="share-head">
            <span>Total allocated</span>
            <strong>{{ formatShare(shareTotal) }}%</strong>
          </div>
          <div class="share-track" aria-hidden="true"><span :style="{ width: `${Math.min(shareTotal, 100)}%` }"></span></div>
          <p class="share-note">
            <template v-if="shareState === 'complete'"><i class="bi bi-check-circle" aria-hidden="true"></i> Shares add up to 100%.</template>
            <template v-else-if="shareState === 'over'"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Over by {{ formatShare(shareTotal - 100) }}%.</template>
            <template v-else>{{ formatShare(100 - shareTotal) }}% left to allocate.</template>
          </p>
        </div>

        <ul class="beneficiary-list">
          <li v-for="(beneficiary, index) in form.beneficiaries" :key="index" class="beneficiary-row">
            <label class="field grow">
              <span class="field-label">Name</span>
              <input v-model.trim="beneficiary.name" type="text" maxlength="120">
            </label>
            <label class="field">
              <span class="field-label">Relationship</span>
              <input v-model.trim="beneficiary.relationship" type="text" maxlength="60" placeholder="e.g. Daughter">
            </label>
            <label class="field share">
              <span class="field-label">Share %</span>
              <input v-model.number="beneficiary.share" type="number" min="0.01" max="100" step="0.01" inputmode="decimal">
            </label>
            <button type="button" class="icon-button" :aria-label="`Remove ${beneficiary.name || 'beneficiary'}`" @click="form.beneficiaries.splice(index, 1)">
              <i class="bi bi-trash" aria-hidden="true"></i>
            </button>
          </li>
        </ul>
        <div class="row-actions">
          <button type="button" class="ghost-button" :disabled="form.beneficiaries.length >= 20" @click="addBeneficiary">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Add beneficiary
          </button>
          <button v-if="form.beneficiaries.length > 1" type="button" class="link-button" @click="splitEqually">Split equally</button>
        </div>
      </div>

      <!-- 4. Witness -->
      <div v-else-if="step === 3" class="fields">
        <p class="intro">
          Your witness confirms from their own MyIntellibook account that you declared this testament as your own.
          <strong>They will not see its contents.</strong>
        </p>
        <label class="field">
          <span class="field-label">Witness</span>
          <Select
            v-model="form.witness_user_id"
            :options="members"
            option-label="label"
            option-value="id"
            placeholder="Search members"
            filter
            show-clear
            :loading="membersLoading"
            class="w-100"
          />
        </label>
        <p v-if="testament?.witness?.status === 'declined'" class="notice warn">
          <i class="bi bi-info-circle" aria-hidden="true"></i> {{ testament.witness.name }} declined your last request. Choose a witness and resubmit.
        </p>
      </div>

      <!-- 5. Review & consent -->
      <div v-else class="fields">
        <dl class="review">
          <div><dt>Title</dt><dd>{{ form.title || '—' }}</dd></div>
          <div><dt>Instructions</dt><dd class="clamp">{{ form.instructions || '—' }}</dd></div>
          <div>
            <dt>Health</dt>
            <dd>{{ form.health.sound_mind && form.health.no_duress ? 'Declaration complete' : 'Incomplete' }}{{ form.health.physician_name ? ` · ${form.health.physician_name}` : '' }}</dd>
          </div>
          <div>
            <dt>Beneficiaries</dt>
            <dd>
              <span v-for="(beneficiary, index) in form.beneficiaries" :key="index" class="review-chip">
                {{ beneficiary.name || 'Unnamed' }} · {{ formatShare(Number(beneficiary.share) || 0) }}%
              </span>
              <template v-if="!form.beneficiaries.length">—</template>
            </dd>
          </div>
          <div><dt>Witness</dt><dd>{{ witnessLabel || '—' }}</dd></div>
        </dl>
        <label class="check-card consent" :class="{ checked: consent }">
          <input v-model="consent" type="checkbox">
          <span>{{ consentStatement }}</span>
        </label>
      </div>
    </div>

    <p v-if="error" class="alert error" role="alert"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ error }}</p>
    <p v-else-if="savedAt" class="alert success" role="status"><i class="bi bi-shield-check" aria-hidden="true"></i> Draft saved and encrypted · {{ savedAt }}</p>

    <footer class="wizard-nav">
      <button type="button" class="ghost-button" :disabled="step === 0 || busy !== null" @click="goTo(step - 1)">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Back
      </button>
      <div class="nav-right">
        <button type="button" class="ghost-button" :disabled="busy !== null" @click="save()">
          <span v-if="busy === 'save'" class="spinner dark" aria-hidden="true"></span> Save draft
        </button>
        <button v-if="step < STEPS.length - 1" type="button" class="primary-button" :disabled="busy !== null" @click="goTo(step + 1)">
          Continue <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </button>
        <button v-else type="button" class="primary-button" :disabled="!consent || busy !== null" @click="submit">
          <span v-if="busy === 'submit'" class="spinner" aria-hidden="true"></span>
          Submit for witnessing
        </button>
      </div>
    </footer>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import Select from 'primevue/select';
import instance from '@/assets/axios';
import { errorMessage, testamentApi, type Beneficiary, type DraftInput, type Testament } from '@/services/testament';

const props = defineProps<{ testament: Testament | null; consentStatement: string }>();
const emit = defineEmits<{ (event: 'saved', value: Testament): void; (event: 'submitted', value: Testament): void; (event: 'close'): void }>();

const STEPS = [
  { key: 'details', label: 'Details', icon: 'bi-file-text' },
  { key: 'health', label: 'Health', icon: 'bi-heart-pulse' },
  { key: 'beneficiaries', label: 'Beneficiaries', icon: 'bi-people' },
  { key: 'witness', label: 'Witness', icon: 'bi-person-check' },
  { key: 'review', label: 'Review', icon: 'bi-shield-check' },
];

const today = new Date().toISOString().slice(0, 10);
const step = ref(0);
const consent = ref(false);
const busy = ref<'save' | 'submit' | null>(null);
const error = ref('');
const savedAt = ref('');
const members = ref<{ id: number; label: string }[]>([]);
const membersLoading = ref(false);

const form = reactive<DraftInput>({
  title: props.testament?.title ?? '',
  instructions: props.testament?.instructions ?? '',
  health: {
    sound_mind: props.testament?.health?.sound_mind ?? false,
    no_duress: props.testament?.health?.no_duress ?? false,
    physician_name: props.testament?.health?.physician_name ?? '',
    assessment_date: props.testament?.health?.assessment_date ?? '',
    notes: props.testament?.health?.notes ?? '',
  },
  beneficiaries: (props.testament?.beneficiaries ?? []).map((b) => ({ ...b })),
  witness_user_id: props.testament?.witness?.id ?? null,
});

if (!form.beneficiaries.length) addBeneficiary();

const shareTotal = computed(() => Math.round(form.beneficiaries.reduce((sum, b) => sum + (Number(b.share) || 0), 0) * 100) / 100);
const shareState = computed(() => (Math.abs(shareTotal.value - 100) <= 0.01 ? 'complete' : shareTotal.value > 100 ? 'over' : 'under'));
const witnessLabel = computed(() => members.value.find((m) => m.id === form.witness_user_id)?.label ?? props.testament?.witness?.name ?? '');

function formatShare(value: number): string {
  return Number.isInteger(value) ? `${value}` : value.toFixed(2);
}

function addBeneficiary(): void {
  form.beneficiaries.push({ name: '', relationship: '', share: 0 } as Beneficiary);
}

function splitEqually(): void {
  const count = form.beneficiaries.length;
  const base = Math.floor((100 / count) * 100) / 100;
  form.beneficiaries.forEach((b, index) => {
    // The last share absorbs the rounding remainder so the total is exactly 100.
    b.share = index === count - 1 ? Math.round((100 - base * (count - 1)) * 100) / 100 : base;
  });
}

function payload(): DraftInput {
  return {
    title: form.title,
    instructions: form.instructions,
    health: {
      ...form.health,
      physician_name: form.health.physician_name || null,
      assessment_date: form.health.assessment_date || null,
      notes: form.health.notes || null,
    },
    // Rows with no name are treated as unfinished and not sent.
    beneficiaries: form.beneficiaries.filter((b) => b.name.trim() !== '').map((b) => ({ ...b, share: Number(b.share) || 0 })),
    witness_user_id: form.witness_user_id,
  };
}

async function save(): Promise<Testament | null> {
  busy.value = 'save';
  error.value = '';
  try {
    const saved = await testamentApi.save(payload());
    savedAt.value = new Intl.DateTimeFormat(undefined, { timeStyle: 'short' }).format(new Date());
    emit('saved', saved);
    return saved;
  } catch (e) {
    error.value = errorMessage(e, 'Your draft could not be saved. Please try again.');
    return null;
  } finally {
    busy.value = null;
  }
}

async function submit(): Promise<void> {
  if (!(await save())) return;
  busy.value = 'submit';
  try {
    emit('submitted', await testamentApi.submit());
  } catch (e) {
    error.value = errorMessage(e, 'Your testament could not be submitted.');
  } finally {
    busy.value = null;
  }
}

function goTo(index: number): void {
  error.value = '';
  step.value = Math.min(Math.max(index, 0), STEPS.length - 1);
}

onMounted(async () => {
  membersLoading.value = true;
  try {
    const { data } = await instance.get('/profile-list');
    members.value = (data?.data ?? [])
      .filter((member: any) => member.full_name)
      .map((member: any) => ({ id: member.id, label: member.full_name }));
  } catch {
    members.value = [];
  } finally {
    membersLoading.value = false;
  }
});
</script>

<style scoped>
.wizard { padding: 20px; background: #fff; border: 1px solid var(--ds-border); border-radius: 18px; box-shadow: 0 1px 2px rgba(15, 23, 42, .05); }
.wizard-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
.eyebrow { display: flex; align-items: center; gap: 6px; margin: 0; color: var(--ds-success-text); font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
.wizard-title { margin: 2px 0 0; color: var(--ds-text); font-size: 18px; font-weight: 800; }

.progress-track { height: 6px; overflow: hidden; background: var(--ds-surface-muted); border-radius: 999px; }
.progress-track span { display: block; height: 100%; background: linear-gradient(90deg, var(--ds-primary-300), var(--ds-primary)); border-radius: 999px; transition: width .3s ease; }

.step-list { display: grid; grid-template-columns: repeat(5, 1fr); gap: 4px; margin: 12px 0 18px; padding: 0; list-style: none; }
.step-button { display: flex; width: 100%; flex-direction: column; align-items: center; gap: 5px; padding: 4px 2px; color: var(--ds-text-subtle); font-size: 11.5px; font-weight: 700; background: none; border: 0; border-radius: 10px; }
.step-button:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }
.step-dot { display: grid; width: 30px; height: 30px; font-size: 13px; place-items: center; background: var(--ds-surface-muted); border-radius: 50%; transition: background-color .2s ease; }
.step-button.current { color: var(--ds-primary-hover); }
.step-button.current .step-dot { color: #fff; background: var(--ds-primary); box-shadow: 0 0 0 4px var(--ds-primary-100); }
.step-button.done { color: var(--ds-success-text); }
.step-button.done .step-dot { color: #fff; background: var(--ds-success); }

.fields { display: grid; gap: 14px; }
.field { display: grid; gap: 6px; min-width: 0; }
.field-label { color: var(--ds-text); font-size: 13px; font-weight: 700; }
.optional { margin-left: 4px; color: var(--ds-text-subtle); font-size: 11px; font-weight: 600; }
.field input, .field textarea {
  width: 100%;
  min-height: 42px;
  padding: 9px 12px;
  color: var(--ds-text);
  font-size: 14px;
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 10px;
  transition: border-color .2s ease, box-shadow .2s ease;
}
.field textarea { resize: vertical; line-height: 1.55; }
.field input:focus, .field textarea:focus { border-color: var(--ds-primary-300); outline: 0; box-shadow: 0 0 0 4px rgba(225, 29, 72, .1); }
.field-hint { color: var(--ds-text-muted); font-size: 12px; }
.field-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
.intro { margin: 0; color: var(--ds-text-secondary); font-size: 13.5px; line-height: 1.6; }

.check-card { display: flex; align-items: flex-start; gap: 10px; padding: 12px 14px; color: var(--ds-text-secondary); font-size: 13.5px; line-height: 1.5; cursor: pointer; background: var(--ds-surface-subtle); border: 1px solid var(--ds-border); border-radius: 12px; transition: background-color .2s ease, border-color .2s ease; }
.check-card input { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--ds-primary); }
.check-card.checked { background: var(--ds-success-soft); border-color: var(--ds-success-border); }
.check-card:focus-within { outline: 2px solid var(--ds-primary); outline-offset: 2px; }
.check-card.consent { font-weight: 600; }

.share-meter { padding: 12px 14px; background: var(--ds-surface-subtle); border: 1px solid var(--ds-border); border-radius: 12px; }
.share-head { display: flex; justify-content: space-between; color: var(--ds-text-secondary); font-size: 13px; }
.share-head strong { font-variant-numeric: tabular-nums; }
.share-track { height: 8px; margin: 8px 0; overflow: hidden; background: var(--ds-border); border-radius: 999px; }
.share-track span { display: block; height: 100%; background: var(--ds-info); border-radius: 999px; transition: width .3s ease; }
.share-note { margin: 0; color: var(--ds-text-muted); font-size: 12.5px; }
.share-meter.complete { background: var(--ds-success-soft); border-color: var(--ds-success-border); }
.share-meter.complete .share-track span { background: var(--ds-success); }
.share-meter.complete .share-note { color: var(--ds-success-text); }
.share-meter.over { background: var(--ds-danger-soft); border-color: var(--ds-danger-border); }
.share-meter.over .share-track span { background: var(--ds-primary); }
.share-meter.over .share-note { color: var(--ds-danger-text); }

.beneficiary-list { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; }
.beneficiary-row { display: grid; grid-template-columns: 1.4fr 1fr 96px 40px; align-items: end; gap: 8px; padding: 10px; background: #fcfcfd; border: 1px solid var(--ds-surface-muted); border-radius: 12px; }
.icon-button { display: grid; width: 40px; height: 42px; color: var(--ds-text-subtle); place-items: center; background: none; border: 0; border-radius: 10px; }
.icon-button:hover { color: var(--ds-primary-hover); background: var(--ds-primary-soft); }
.row-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }

.notice { display: flex; gap: 8px; margin: 0; padding: 10px 12px; font-size: 13px; border-radius: 10px; }
.notice.warn { color: var(--ds-warning-text); background: var(--ds-warning-soft); }

.review { display: grid; gap: 8px; margin: 0; }
.review > div { display: grid; grid-template-columns: 110px 1fr; gap: 10px; padding: 10px 12px; background: var(--ds-surface-subtle); border-radius: 12px; }
.review dt { color: var(--ds-text-muted); font-size: 12.5px; font-weight: 700; }
.review dd { margin: 0; color: var(--ds-text); font-size: 13.5px; overflow-wrap: anywhere; }
.clamp { display: -webkit-box; overflow: hidden; -webkit-line-clamp: 3; -webkit-box-orient: vertical; white-space: pre-line; }
.review-chip { display: inline-block; margin: 0 6px 4px 0; padding: 2px 10px; font-size: 12.5px; background: #fff; border: 1px solid var(--ds-border); border-radius: 999px; }

.alert { display: flex; gap: 8px; margin: 14px 0 0; padding: 10px 12px; font-size: 13px; border-radius: 10px; }
.alert.error { color: var(--ds-danger-text); background: var(--ds-danger-soft); border: 1px solid var(--ds-danger-border); }
.alert.success { color: var(--ds-success-text); background: var(--ds-success-soft); border: 1px solid var(--ds-success-border); }

.wizard-nav { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px; margin-top: 18px; }
.nav-right { display: flex; flex-wrap: wrap; gap: 8px; }
.primary-button, .ghost-button { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 42px; padding: 9px 16px; font-size: 13.5px; font-weight: 700; border-radius: 12px; }
.primary-button { color: #fff; background: linear-gradient(135deg, var(--ds-primary), var(--ds-primary-hover)); border: 0; box-shadow: 0 4px 12px rgba(225, 29, 72, .22); }
.ghost-button { color: var(--ds-text-secondary); background: #fff; border: 1px solid var(--ds-border); }
.ghost-button:hover:not(:disabled) { color: var(--ds-primary-hover); background: var(--ds-primary-soft); }
.primary-button:disabled, .ghost-button:disabled { cursor: not-allowed; opacity: .45; box-shadow: none; }
.primary-button:focus-visible, .ghost-button:focus-visible, .link-button:focus-visible, .icon-button:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }
.link-button { padding: 4px; color: var(--ds-primary-hover); font-size: 13px; font-weight: 700; background: none; border: 0; }
.spinner { width: 14px; height: 14px; border: 2px solid #ffffff70; border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
.spinner.dark { border-color: var(--ds-border); border-top-color: var(--ds-primary-hover); }
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 575px) {
  .wizard { padding: 16px 14px; }
  .step-name { display: none; }
  .beneficiary-row { grid-template-columns: 1fr 1fr; }
  .beneficiary-row .grow { grid-column: 1 / -1; }
  .review > div { grid-template-columns: 1fr; gap: 2px; }
  .wizard-nav, .nav-right { width: 100%; }
  .nav-right > * { flex: 1; }
}

@media (prefers-reduced-motion: reduce) {
  .progress-track span, .share-track span, .step-dot { transition: none; }
  .spinner { animation: none; }
}
</style>
