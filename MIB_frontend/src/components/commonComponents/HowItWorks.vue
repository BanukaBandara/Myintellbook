<template>
  <InfoPageShell
    icon="bi-question-circle"
    eyebrow="See how it works"
    title="From daily learning to a verified ranking"
    subtitle="Walk through each stage of the MyIntellibook evaluation model."
  >
    <FeatureWalkthrough :steps="steps" label="How MyIntellibook works">
      <template #step-overview>
        <p>
          MyIntellibook is an intelligent evaluation and ranking platform that integrates the Human Intelligence Portfolio (HIP), Tribunal Evaluation, and Testament Management into a unified, transparent, and ethical framework. The HIP Score is derived from Holistic Index Points (HIP Values) that represent intellectual ability, ethical reasoning, and social responsibility. This model connects knowledge with accountability and transforms learning into measurable impact.
        </p>
      </template>

      <template #step-hip>
        <p>The HIP algorithm evaluates participants through daily and cumulative performance indicators. It combines several core elements:</p>
        <ul class="element-list">
          <li><i class="bi bi-newspaper" aria-hidden="true"></i><span><strong>Current Affairs Daily Question Score</strong> – Measures awareness and understanding of current global and national issues.</span></li>
          <li><i class="bi bi-person-check" aria-hidden="true"></i><span><strong>Self-Evaluation Score (Personal Reflection Score)</strong> – Reflects an individual’s ability to assess reasoning, responsibility, and ethical decision-making.</span></li>
          <li><i class="bi bi-dash-circle" aria-hidden="true"></i><span><strong>Negative Scoring</strong> – Applied when a participant engages in socially or ethically negative behavior, ensuring HIP promotes ethical conduct, discipline, and positive social behavior.</span></li>
        </ul>

        <p class="scale-heading">Answer score scale</p>
        <ol class="score-scale">
          <li v-for="item in scale" :key="item.score" :style="{ '--level': item.score }">
            <span class="score-chip">{{ item.score }}</span>
            <span class="score-meaning">{{ item.meaning }}</span>
          </li>
        </ol>
      </template>

      <template #step-tribunal>
        <p>The MyIntellibook Tribunal operates as a dual-level mechanism ensuring fairness, validation, and social responsibility:</p>
        <div class="tribunal-grid">
          <div class="mini-card">
            <i class="bi bi-shield-check" aria-hidden="true"></i>
            <p><em>Internal Tribunal</em> – Validates HIP Scores, verifies data integrity, ensures transparency, and detects anomalies.</p>
          </div>
          <div class="mini-card">
            <i class="bi bi-people" aria-hidden="true"></i>
            <p><em>External Tribunal</em> – Resolves external issues, promotes harmony, and offers ethical guidance.</p>
          </div>
        </div>
      </template>

      <template #step-testament>
        <p>
          The Testament Management module engages senior citizens and experienced professionals to contribute wisdom and mentorship. A Fixed Arm — composed of Legal and Quality divisions — oversees ethical and transparent administration of testamentary activities, ensuring alignment with MyIntellibook’s standards while supporting the elderly community.
        </p>
      </template>

      <template #step-ranking>
        <p>
          Once verified, HIP, Tribunal, and Traditional metrics are combined to produce a comprehensive performance ranking. Results are displayed in an interactive dashboard highlighting leadership indicators, learning capacity, and community impact.
        </p>
        <RouterLink to="/scores" class="inline-link">Open your score dashboard <i class="bi bi-arrow-right" aria-hidden="true"></i></RouterLink>
      </template>

      <template #step-security>
        <p>
          All participant data is securely handled under MyIntellibook’s confidentiality and data protection policies. Manual intervention in results is strictly prohibited, ensuring complete trust, accuracy, and traceability.
        </p>
        <blockquote class="essence">
          <em>In essence, MyIntellibook transforms evaluation into a life mission — building a Human Intelligence Portfolio that measures not only knowledge but also ethical responsibility, compassion, and social contribution.</em>
        </blockquote>
      </template>
    </FeatureWalkthrough>
  </InfoPageShell>
</template>

<script setup lang="ts">
import InfoPageShell from '@/components/infoPages/InfoPageShell.vue';
import FeatureWalkthrough, { type WalkthroughStep } from '@/components/infoPages/FeatureWalkthrough.vue';

const steps: WalkthroughStep[] = [
  { id: 'overview', title: 'Overview', icon: 'bi-compass' },
  { id: 'hip', title: 'HIP Calculation', icon: 'bi-calculator' },
  { id: 'tribunal', title: 'Tribunal Evaluation', icon: 'bi-bank' },
  { id: 'testament', title: 'Testament Management', icon: 'bi-journal-bookmark' },
  { id: 'ranking', title: 'Final Ranking', icon: 'bi-trophy' },
  { id: 'security', title: 'Transparency & Security', icon: 'bi-shield-lock' },
];

const scale = [
  { score: 0, meaning: 'No Understanding / Incorrect Response' },
  { score: 1, meaning: 'Very Low Clarity or Effort' },
  { score: 2, meaning: 'Basic Awareness with Limited Accuracy' },
  { score: 3, meaning: 'Moderate Understanding with Logical Reasoning' },
  { score: 4, meaning: 'Strong Analytical and Ethical Reasoning' },
  { score: 5, meaning: 'Excellent Insight, Reasoning, and Application' },
];
</script>

<style scoped>
.element-list { display: grid; gap: 8px; margin: 0 0 16px !important; padding: 0 !important; list-style: none; }
.element-list li { display: flex; gap: 10px; margin: 0 !important; padding: 10px 12px; background: #fff; border: 1px solid var(--ds-surface-muted); border-radius: 12px; }
.element-list li > i { flex: 0 0 auto; margin-top: 2px; color: var(--ds-primary); font-size: 16px; }
.element-list strong { color: var(--ds-text) !important; }

.scale-heading { margin: 0 0 8px !important; color: var(--ds-text-muted); font-size: 11px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
.score-scale { display: grid; gap: 6px; margin: 0 !important; padding: 0 !important; list-style: none; }
.score-scale li { display: flex; align-items: center; gap: 10px; margin: 0 !important; }

/* Chip colour deepens with the score: grey at 0 to strong green at 5. */
.score-chip {
  display: grid;
  flex: 0 0 34px;
  width: 34px;
  height: 34px;
  color: #fff;
  font-size: 14px;
  font-weight: 800;
  place-items: center;
  background: color-mix(in srgb, var(--ds-success) calc(var(--level) * 20%), var(--ds-text-subtle));
  border-radius: 10px;
}

.score-meaning { font-size: 13.5px; line-height: 1.4; }

.tribunal-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
.mini-card { padding: 14px; background: #fff; border: 1px solid var(--ds-surface-muted); border-radius: 14px; }
.mini-card > i { display: inline-grid; width: 34px; height: 34px; margin-bottom: 8px; color: var(--ds-primary); font-size: 16px; place-items: center; background: var(--ds-primary-soft); border-radius: 10px; }
.mini-card p { margin: 0 !important; font-size: 13.5px; }
.mini-card em { color: var(--ds-text); font-style: normal; font-weight: 700; }

.inline-link { display: inline-flex; align-items: center; gap: 6px; color: var(--ds-primary); font-size: 13.5px; font-weight: 700; text-decoration: none; }
.inline-link:hover { text-decoration: underline; }
.inline-link:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; border-radius: 4px; }

.essence { margin: 4px 0 0; padding: 14px 16px; color: #4c0519; background: var(--ds-primary-soft); border-left: 3px solid var(--ds-primary); border-radius: 0 12px 12px 0; }
</style>
