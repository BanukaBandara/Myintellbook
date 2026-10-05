<template>
  <section class="tracker" aria-labelledby="tracker-title">
    <div class="tracker-head">
      <h2 id="tracker-title" class="tracker-title">Verification status</h2>
      <div class="segmented" role="group" aria-label="Filter by status">
        <button
          v-for="filter in FILTERS"
          :key="filter.key"
          type="button"
          class="segment"
          :class="{ active: activeStatus === filter.key }"
          :aria-pressed="activeStatus === filter.key"
          @click="emits('statusChange', filter.key)"
        >
          <i :class="['bi', filter.icon]" aria-hidden="true"></i> {{ filter.label }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="skeleton-list" aria-label="Loading requests">
      <div v-for="n in 2" :key="n" class="skeleton-card"></div>
    </div>

    <ul v-else-if="ComplainsArray.length" class="request-list">
      <li v-for="(complain, index) in ComplainsArray" :key="index" class="request-card">
        <div class="request-top">
          <div class="request-people">
            <p class="request-claim">{{ complain.complain || 'Verification request' }}</p>
            <p class="request-meta">
              <span><i class="bi bi-person" aria-hidden="true"></i> {{ complain.user }}</span>
              <span><i class="bi bi-arrow-return-right" aria-hidden="true"></i> requested by {{ complain.complain_from }}</span>
            </p>
          </div>
          <span class="status-pill" :class="`status-${stageOf(complain.status)}`">
            <i :class="['bi', STAGES[stageOf(complain.status)].icon]" aria-hidden="true"></i>
            {{ STAGES[stageOf(complain.status)].label }}
          </span>
        </div>

        <!-- Stage tracker -->
        <ol class="stages" :aria-label="`Progress: ${STAGES[stageOf(complain.status)].label}`">
          <li
            v-for="(stage, stageIndex) in STAGES"
            :key="stage.key"
            :class="{ reached: stageIndex <= stageOf(complain.status), current: stageIndex === stageOf(complain.status) }"
          >
            <span class="stage-dot" aria-hidden="true"></span>
            <span class="stage-label">{{ stage.label }}</span>
          </li>
        </ol>

        <div class="request-foot">
          <span class="request-date"><i class="bi bi-calendar3" aria-hidden="true"></i> {{ complain.date }}</span>
          <div v-if="Array.isArray(complain.jury) && complain.jury.length" class="jury">
            <span class="jury-label">Juror</span>
            <button
              v-for="(juror, jIndex) in complain.jury"
              :key="jIndex"
              type="button"
              class="juror-chip"
              @click="router.push(`/showUserProfile/${juror.profile_url}`)"
            >
              <Avatar :image="juror.profile_image || undefined" :label="juror.profile_image ? undefined : initials(juror.name)" shape="circle" class="juror-avatar" />
              {{ juror.name }}
            </button>
          </div>
          <span v-else-if="typeof complain.jury === 'string'" class="jury-pending">{{ complain.jury }}</span>
        </div>
      </li>
    </ul>

    <div v-else class="empty-state">
      <i class="bi bi-inbox" aria-hidden="true"></i>
      <p>No {{ FILTERS.find((filter) => filter.key === activeStatus)?.label.toLowerCase() }} requests yet.</p>
    </div>
  </section>
</template>

<script setup lang="ts">
import type { PropType } from 'vue';
import Avatar from 'primevue/avatar';
import router from '@/router';

type Juror = { name: string; profile_image: string; profile_url: string };

defineProps({
  activeStatus: { type: String, default: 'submitted' },
  loading: { type: Boolean, default: false },
  ComplainsArray: {
    type: Array as PropType<Array<{
      user: string;
      complain_from: string;
      complain: string;
      status: string | number;
      date: string;
      jury: Juror[] | string;
    }>>,
    default: () => [],
  },
});

const emits = defineEmits(['statusChange']);

const FILTERS = [
  { key: 'submitted', label: 'Submitted', icon: 'bi-clock' },
  { key: 'inprogress', label: 'In progress', icon: 'bi-hourglass-split' },
  { key: 'solved', label: 'Resolved', icon: 'bi-check-circle' },
];

const STAGES = [
  { key: 'submitted', label: 'Submitted', icon: 'bi-clock' },
  { key: 'inprogress', label: 'In review', icon: 'bi-hourglass-split' },
  { key: 'solved', label: 'Resolved', icon: 'bi-check-circle-fill' },
];

/** The API sends 1/2/3; older data may use the status names. */
function stageOf(status: string | number): number {
  const value = String(status).toLowerCase();
  if (value === '3' || value === 'solved') return 2;
  if (value === '2' || value === 'inprogress') return 1;
  return 0;
}

const initials = (name: string) => name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
</script>

<style scoped>
.tracker { padding: 18px; background: #fff; border: 1px solid var(--ds-border); border-radius: 18px; box-shadow: 0 1px 2px rgba(15, 23, 42, .05); }
.tracker-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px; }
.tracker-title { margin: 0; color: var(--ds-text); font-size: 16px; font-weight: 800; }

.segmented { display: flex; gap: 2px; padding: 3px; background: var(--ds-surface-muted); border-radius: 12px; }
.segment { display: inline-flex; align-items: center; gap: 5px; padding: 6px 11px; color: var(--ds-text-secondary); font-size: 12.5px; font-weight: 700; background: none; border: 0; border-radius: 9px; transition: background-color .2s ease, color .2s ease; }
.segment:hover { color: var(--ds-primary-hover); }
.segment.active { color: var(--ds-primary-hover); background: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, .1); }
.segment:focus-visible, .juror-chip:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }

.request-list { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
.request-card { padding: 14px 16px; background: #fcfcfd; border: 1px solid var(--ds-surface-muted); border-radius: 14px; }
.request-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
.request-people { min-width: 0; }
.request-claim { margin: 0; color: var(--ds-text); font-size: 14.5px; font-weight: 750; }
.request-meta { display: flex; flex-wrap: wrap; gap: 4px 14px; margin: 3px 0 0; color: var(--ds-text-muted); font-size: 12.5px; }

.status-pill { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 5px; padding: 3px 10px; font-size: 11.5px; font-weight: 800; border-radius: 999px; }
.status-0 { color: var(--ds-text-secondary); background: var(--ds-surface-muted); }
.status-1 { color: var(--ds-warning-text); background: #fef3c7; }
.status-2 { color: var(--ds-success-text); background: #d1fae5; }

.stages { display: grid; grid-template-columns: repeat(3, 1fr); margin: 14px 0 12px; padding: 0; list-style: none; }
.stages li { position: relative; display: flex; flex-direction: column; align-items: center; gap: 5px; color: var(--ds-text-subtle); font-size: 11.5px; font-weight: 600; }
.stages li:not(:first-child)::before { position: absolute; top: 5px; right: 50%; left: -50%; height: 2px; content: ''; background: var(--ds-border); }
.stages li.reached:not(:first-child)::before { background: var(--ds-primary); }
.stage-dot { position: relative; z-index: 1; width: 12px; height: 12px; background: #fff; border: 2px solid var(--ds-border-strong); border-radius: 50%; }
.stages li.reached { color: var(--ds-primary-hover); }
.stages li.reached .stage-dot { background: var(--ds-primary); border-color: var(--ds-primary); }
.stages li.current .stage-dot { box-shadow: 0 0 0 4px var(--ds-primary-100); }

.request-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding-top: 10px; border-top: 1px solid var(--ds-surface-muted); }
.request-date { color: var(--ds-text-muted); font-size: 12px; }
.jury { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
.jury-label { color: var(--ds-text-subtle); font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.juror-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px 3px 3px; color: var(--ds-text-secondary); font-size: 12.5px; font-weight: 600; background: #fff; border: 1px solid var(--ds-border); border-radius: 999px; }
.juror-chip:hover { color: var(--ds-primary-hover); border-color: var(--ds-primary-200); }
.juror-avatar { width: 24px !important; height: 24px !important; font-size: 10px !important; }
.jury-pending { color: var(--ds-warning-text); font-size: 12px; font-weight: 600; }

.empty-state { padding: 28px 12px; color: var(--ds-text-muted); font-size: 13.5px; text-align: center; }
.empty-state i { display: block; margin-bottom: 6px; color: var(--ds-primary-300); font-size: 26px; }
.empty-state p { margin: 0; }

.skeleton-list { display: grid; gap: 10px; }
.skeleton-card { height: 130px; background: linear-gradient(90deg, var(--ds-surface-muted), var(--ds-border), var(--ds-surface-muted)); background-size: 200% 100%; border-radius: 14px; animation: shimmer 1.4s ease-in-out infinite; }
@keyframes shimmer { to { background-position: -200% 0; } }

@media (max-width: 575px) {
  .tracker { padding: 16px 14px; }
  .segmented { width: 100%; }
  .segment { flex: 1; justify-content: center; padding: 6px 4px; }
  .request-top { flex-direction: column; }
}

@media (prefers-reduced-motion: reduce) {
  .skeleton-card { animation: none; }
  .segment { transition: none; }
}
</style>
