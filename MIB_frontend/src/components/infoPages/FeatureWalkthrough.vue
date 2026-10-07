<template>
  <section class="walkthrough" :aria-label="label">
    <div class="progress-head no-print">
      <span class="progress-label">Step {{ current + 1 }} of {{ steps.length }}</span>
      <span class="progress-title">{{ steps[current].title }}</span>
    </div>
    <div
      class="progress-track no-print"
      role="progressbar"
      :aria-valuenow="current + 1"
      aria-valuemin="1"
      :aria-valuemax="steps.length"
      :aria-label="`Step ${current + 1} of ${steps.length}`"
    >
      <span :style="{ width: `${((current + 1) / steps.length) * 100}%` }"></span>
    </div>

    <ol class="step-pills no-print">
      <li v-for="(step, index) in steps" :key="step.id">
        <button
          type="button"
          class="step-pill"
          :class="{ current: index === current, done: index < current }"
          :aria-current="index === current ? 'step' : undefined"
          @click="go(index)"
        >
          <span class="pill-icon" aria-hidden="true">
            <i :class="['bi', index < current ? 'bi-check-lg' : step.icon]"></i>
          </span>
          <span class="pill-text">{{ step.title }}</span>
        </button>
      </li>
    </ol>

    <div
      v-for="(step, index) in steps"
      v-show="index === current"
      :key="step.id"
      class="step-card"
      data-print-expand
    >
      <div class="step-card-head">
        <span class="step-badge" aria-hidden="true"><i :class="['bi', step.icon]"></i></span>
        <div>
          <p class="step-kicker">Step {{ index + 1 }}</p>
          <h2 :id="`walk-${step.id}`" class="step-title" tabindex="-1">{{ step.title }}</h2>
        </div>
      </div>
      <div class="step-body">
        <slot :name="`step-${step.id}`" />
      </div>
    </div>

    <footer class="step-nav no-print">
      <button type="button" class="nav-button ghost" :disabled="current === 0" @click="go(current - 1)">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Previous
      </button>
      <button v-if="current < steps.length - 1" type="button" class="nav-button primary" @click="go(current + 1)">
        Next: {{ steps[current + 1].title }} <i class="bi bi-arrow-right" aria-hidden="true"></i>
      </button>
      <button v-else type="button" class="nav-button primary" @click="go(0)">
        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Start over
      </button>
    </footer>
  </section>
</template>

<script setup lang="ts">
import { nextTick, ref } from 'vue';

export type WalkthroughStep = { id: string; title: string; icon: string };

const props = withDefaults(defineProps<{ steps: WalkthroughStep[]; label?: string }>(), { label: 'Walkthrough' });
const current = ref(0);

async function go(index: number): Promise<void> {
  current.value = Math.min(Math.max(index, 0), props.steps.length - 1);
  await nextTick();
  // Move focus to the new step's heading so keyboard and screen-reader users follow along.
  document.getElementById(`walk-${props.steps[current.value].id}`)?.focus({ preventScroll: true });
}
</script>

<style scoped>
.walkthrough {
  padding: 18px;
  background: #fff;
  border: 1px solid var(--ds-border);
  border-radius: 18px;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.progress-head { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 6px; margin-bottom: 8px; }
.progress-label { color: var(--ds-primary); font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
.progress-title { color: var(--ds-text-muted); font-size: 12.5px; font-weight: 600; }

.progress-track { height: 6px; overflow: hidden; background: var(--ds-surface-muted); border-radius: 999px; }
.progress-track span { display: block; height: 100%; background: linear-gradient(90deg, var(--ds-primary-300), var(--ds-primary)); border-radius: 999px; transition: width .3s ease; }

.step-pills { display: flex; gap: 6px; margin: 14px 0 16px; padding: 0 0 4px; overflow-x: auto; list-style: none; scrollbar-width: thin; }

.step-pill {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 6px 12px 6px 6px;
  color: var(--ds-text-muted);
  font-size: 12.5px;
  font-weight: 600;
  white-space: nowrap;
  background: var(--ds-surface-subtle);
  border: 1px solid var(--ds-border);
  border-radius: 999px;
  transition: background-color .2s ease, color .2s ease, border-color .2s ease;
}

.step-pill:hover { color: var(--ds-primary-hover); border-color: var(--ds-primary-200); }
.step-pill:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }
.pill-icon { display: grid; width: 24px; height: 24px; font-size: 12px; place-items: center; background: #fff; border-radius: 50%; }
.step-pill.current { color: var(--ds-primary-hover); background: var(--ds-primary-soft); border-color: var(--ds-primary-200); }
.step-pill.current .pill-icon { color: #fff; background: var(--ds-primary); }
.step-pill.done .pill-icon { color: #fff; background: var(--ds-success); }

.step-card { padding: 18px; background: linear-gradient(180deg, #fffbfb, #fff); border: 1px solid #f5e1e4; border-radius: 16px; }
.step-card + .step-card { margin-top: 12px; }
.step-card-head { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.step-badge { display: grid; flex: 0 0 44px; width: 44px; height: 44px; color: #fff; font-size: 20px; place-items: center; background: linear-gradient(135deg, var(--ds-primary-300), var(--ds-primary)); border-radius: 13px; box-shadow: 0 6px 14px rgba(225, 29, 72, .22); }
.step-kicker { margin: 0; color: var(--ds-text-subtle); font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
.step-title { margin: 0; color: var(--ds-text); font-size: 18px; font-weight: 750; outline: none; }

.step-body { color: var(--ds-text-secondary); font-size: 14.5px; line-height: 1.75; }
.step-body :deep(p) { margin: 0 0 12px; }
.step-body :deep(p:last-child) { margin-bottom: 0; }
.step-body :deep(strong) { color: var(--ds-primary-hover); }
.step-body :deep(ul) { margin: 0 0 12px; padding-left: 18px; }
.step-body :deep(li) { margin-bottom: 6px; }
.step-body :deep(li::marker) { color: var(--ds-primary); }

.step-nav { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px; margin-top: 16px; }

.nav-button {
  display: inline-flex;
  max-width: 100%;
  align-items: center;
  gap: 7px;
  min-height: 42px;
  padding: 9px 16px;
  overflow: hidden;
  font-size: 13.5px;
  font-weight: 500;
  text-overflow: ellipsis;
  white-space: nowrap;
  border-radius: 12px;
  transition: background .2s ease, color .2s ease, box-shadow .2s ease;
}

.nav-button:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }
.nav-button.primary { color: #fff; background: var(--ds-primary); border: 0; box-shadow: var(--ds-shadow-sm); }
.nav-button.ghost { color: var(--ds-text-secondary); background: #fff; border: 1px solid var(--ds-border); }
.nav-button.primary:hover:not(:disabled) { background: var(--ds-primary-hover); }
.nav-button.ghost:hover:not(:disabled) { color: var(--ds-primary-hover); background: var(--ds-primary-soft); }
.nav-button:disabled { cursor: not-allowed; opacity: .45; }

@media (max-width: 575px) {
  .walkthrough { padding: 14px; }
  .step-nav { flex-direction: column-reverse; }
  .nav-button { justify-content: center; width: 100%; }
}

@media (prefers-reduced-motion: reduce) {
  .progress-track span, .step-pill, .nav-button { transition: none; }
}
</style>
