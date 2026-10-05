<template>
  <InfoPageShell
    icon="bi-graph-up-arrow"
    eyebrow="Scoring"
    title="LCI & HIP Scoring Breakdown"
    subtitle="See exactly how each section adds to your Life Competency Index, and how your HIP rank is decided."
    printable
  >
    <!-- Live breakdown: the user's own LCI per category, from /scores/lci -->
    <section class="live-card" aria-labelledby="live-title">
      <div class="live-head">
        <div>
          <p class="live-eyebrow">Your LCI, piece by piece</p>
          <h2 id="live-title" class="live-title">
            <template v-if="lci">{{ formatPoints(lci.lci_score) }} <span>LCI</span></template>
            <template v-else>How your LCI is built</template>
          </h2>
        </div>
        <HipRankBadge
          v-if="lci"
          :score="lci.lci_score"
          :rank="lci.hip_rank"
          :tier="lci.rank_tier"
          :color="lci.rank_badge_color"
        />
      </div>

      <p class="formula" aria-label="LCI equals the sum of all seven sections">
        <span class="formula-lhs">LCI =</span>
        <template v-for="(category, index) in CATEGORIES" :key="category.key">
          <button
            type="button"
            class="formula-term"
            :class="{ active: selected === category.key }"
            @click="selected = category.key"
          >{{ category.short }}</button>
          <span v-if="index < CATEGORIES.length - 1" class="formula-plus" aria-hidden="true">+</span>
        </template>
      </p>

      <p v-if="lciError" class="live-error" role="alert">
        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> {{ lciError }}
        <button type="button" class="link-button" @click="loadLci">Try again</button>
      </p>

      <!-- Bar list doubles as the table view: every row states its value in text. -->
      <ul class="bar-list" aria-label="Your score by section">
        <li v-for="category in CATEGORIES" :key="category.key">
          <button
            type="button"
            class="bar-row"
            :class="{ active: selected === category.key }"
            :aria-pressed="selected === category.key"
            @click="selected = category.key"
          >
            <span class="bar-label"><i :class="['bi', category.icon]" aria-hidden="true"></i>{{ category.label }}</span>
            <span class="bar-track" aria-hidden="true">
              <span
                class="bar-fill"
                :class="{ negative: scoreFor(category.key) < 0 }"
                :style="{ width: barWidth(scoreFor(category.key)) }"
              ></span>
            </span>
            <span class="bar-value" :class="{ zero: scoreFor(category.key) === 0 }">
              <template v-if="lciLoading">…</template>
              <template v-else>{{ signed(scoreFor(category.key)) }}</template>
            </span>
          </button>
        </li>
      </ul>

      <!-- Earning rules for the selected section -->
      <div class="rules-panel" aria-live="polite">
        <div class="rules-head">
          <span class="rules-icon" aria-hidden="true"><i :class="['bi', current.icon]"></i></span>
          <div>
            <h3 class="rules-title">{{ current.label }}</h3>
            <p class="rules-desc">{{ current.description }}</p>
          </div>
        </div>
        <ul class="rule-list">
          <li v-for="rule in current.rules" :key="rule.label" class="rule-row">
            <span class="rule-label">{{ rule.label }}</span>
            <span class="rule-track" aria-hidden="true">
              <span class="rule-fill" :class="{ negative: rule.points < 0 }" :style="{ width: ruleWidth(rule.points) }"></span>
            </span>
            <span class="rule-points">{{ signed(rule.points) }}<small v-if="rule.unit"> {{ rule.unit }}</small></span>
          </li>
        </ul>
        <p v-if="current.note" class="rules-note"><i class="bi bi-info-circle" aria-hidden="true"></i> {{ current.note }}</p>
      </div>

      <!-- Rank ladder -->
      <div class="ladder">
        <h3 class="ladder-title">HIP rank matrix</h3>
        <p class="ladder-desc">
          Ranks are relative: your position is measured by how far your LCI sits below the highest LCI in the system,
          as a share of the range between the highest and lowest scores.
        </p>
        <ol class="ladder-list">
          <li
            v-for="tier in TIERS"
            :key="tier.name"
            class="ladder-row"
            :class="{ current: lci?.rank_tier === tier.name }"
            :style="{ '--tier': tier.color }"
          >
            <span class="tier-swatch" aria-hidden="true"></span>
            <span class="tier-name">{{ tier.name }}</span>
            <span class="tier-levels">{{ tier.levels }}</span>
            <span class="tier-band">{{ tier.band }}</span>
            <span v-if="lci?.rank_tier === tier.name" class="tier-you">You · {{ lci.hip_rank }}</span>
          </li>
        </ol>
      </div>
    </section>

    <!-- The published framework document, unchanged -->
    <h2 class="doc-heading">Framework document</h2>
    <p class="doc-note no-print">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      The live card above reflects how the platform currently calculates your LCI and rank.
    </p>

    <InfoAccordion label="Scoring framework sections" :default-open="['s0']">
      <InfoAccordionItem id="s0" icon="bi-file-earmark-text" title="LCI & HIP 42-Item Scoring and Verification Framework">
        <p>
          This document defines the complete scoring and verification model used in MyIntellibook for calculating
          the Life Competency Index (LCI) and the Human Intelligence Portfolio (HIP), based on 42 measurable items.
          It includes positive and negative factors to ensure a balanced, transparent, and ethically verified assessment.
        </p>
      </InfoAccordionItem>

      <InfoAccordionItem id="s1" number="1" title="Life Competency Index (LCI) – 42-Item Framework">
        <p>
          The LCI measures overall capability across academic, professional, ethical, health, and social domains.
          It aggregates results from 42 measurable items grouped under five categories:
        </p>
        <ul>
          <li>Knowledge &amp; Learning Performance</li>
          <li>Professional &amp; Career Growth</li>
          <li>Social &amp; Ethical Conduct</li>
          <li>Health &amp; Lifestyle Stability</li>
          <li>Self-Evaluation &amp; Reflection</li>
        </ul>
      </InfoAccordionItem>

      <InfoAccordionItem id="sA" number="A" title="Score Composition">
        <div class="table-scroll">
          <table class="info-table">
            <thead>
              <tr><th>Category</th><th>Example Items</th><th>Typical Score Range</th><th>Verification Method</th></tr>
            </thead>
            <tbody>
              <tr><td>Knowledge &amp; Learning</td><td>Daily quiz, subject exam, profile completion</td><td>0–5/day; up to 3072/year</td><td>System verified</td></tr>
              <tr><td>Education</td><td>PhD, Master’s, Bachelor’s, Diploma, Certificate</td><td>6,137–30,687 points</td><td>Tribunal &amp; academic verification</td></tr>
              <tr><td>Work Experience</td><td>Executive / non-executive per year</td><td>1,227–2,455 points</td><td>Appointment letters / Tribunal</td></tr>
              <tr><td>Social Conduct (Positive)</td><td>Participation, mentorship, verified contribution</td><td>0–1,000 points</td><td>Peer verification</td></tr>
              <tr><td>Social Conduct (Negative)</td><td>False submission, unethical behavior</td><td>−500 to −2,000 points</td><td>Tribunal investigation</td></tr>
              <tr><td>Health &amp; Lifestyle</td><td>Fitness, self-declared stability</td><td>0–500 points</td><td>Health certificate / third-party</td></tr>
              <tr><td>Self-Reflection</td><td>Improvement participation, feedback response</td><td>0–1,000 points</td><td>System tracking &amp; verification</td></tr>
            </tbody>
          </table>
        </div>
      </InfoAccordionItem>

      <InfoAccordionItem id="sB" number="B" title="Calculation Formula">
        <p class="formula-block">LCI = Σ (Si × Wi), where Si = individual item score and Wi = weight (0–1).</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="sC" number="C" title="Negative Indicators and Penalties">
        <div class="table-scroll">
          <table class="info-table">
            <thead><tr><th>Type of Violation</th><th>Penalty</th><th>Verification / Approval</th></tr></thead>
            <tbody>
              <tr><td>Minor Misconduct</td><td>−500</td><td>Internal tribunal review</td></tr>
              <tr><td>Moderate Ethical Breach</td><td>−1,000</td><td>Cross-verification with peers &amp; tribunal</td></tr>
              <tr><td>Severe Violation / False Data</td><td>−2,000</td><td>External tribunal confirmation</td></tr>
            </tbody>
          </table>
        </div>
      </InfoAccordionItem>

      <InfoAccordionItem id="sD" number="D" title="Verification and Ranking">
        <div class="table-scroll">
          <table class="info-table">
            <thead><tr><th>Rank</th><th>Range (Percentile)</th><th>Description</th></tr></thead>
            <tbody>
              <tr><td>Platinum 1</td><td>Top 10%</td><td>Exceptional performance</td></tr>
              <tr><td>Platinum 2</td><td>Next 5–7%</td><td>Excellent performer</td></tr>
              <tr><td>Gold 1</td><td>Next 10–12%</td><td>Consistent performer</td></tr>
              <tr><td>Gold 2</td><td>Next 12–15%</td><td>Above average</td></tr>
              <tr><td>Silver 1</td><td>Next 15–20%</td><td>Competent</td></tr>
              <tr><td>Bronze</td><td>Below 20%</td><td>Developing stage</td></tr>
            </tbody>
          </table>
        </div>
      </InfoAccordionItem>

      <InfoAccordionItem id="s2" number="2" title="Human Intelligence Portfolio (HIP) – Integrated Calculation">
        <p>
          The HIP combines knowledge, applied experience, ethics, reflection, and emotion, converting verified LCI results
          into a normalized intelligence performance score.
        </p>
        <div class="table-scroll">
          <table class="info-table">
            <thead><tr><th>Intelligence Dimension</th><th>Source Items</th><th>Weight</th><th>Verification</th></tr></thead>
            <tbody>
              <tr><td>Knowledge Intelligence (KI)</td><td>Learning &amp; academic results</td><td>30%</td><td>System verified</td></tr>
              <tr><td>Applied Intelligence (AI)</td><td>Work experience, projects</td><td>25%</td><td>Document verified</td></tr>
              <tr><td>Ethical Intelligence (EI)</td><td>Social behavior &amp; penalties</td><td>20%</td><td>Tribunal verified</td></tr>
              <tr><td>Reflective Intelligence (RI)</td><td>Self-evaluation &amp; reflection</td><td>15%</td><td>Peer &amp; system verified</td></tr>
              <tr><td>Emotional Intelligence (EMI)</td><td>Health, communication &amp; stability</td><td>10%</td><td>Tribunal / peer verified</td></tr>
            </tbody>
          </table>
        </div>
        <p><strong>A. Formula</strong></p>
        <p class="formula-block">HIP = (KI × 0.30) + (AI × 0.25) + (EI × 0.20) + (RI × 0.15) + (EMI × 0.10)</p>
        <p><strong>B. Normalization</strong></p>
        <p class="formula-block">HIP Index = (HIP Score / Highest HIP in System) × 100</p>
        <div class="table-scroll">
          <table class="info-table">
            <thead><tr><th>Level</th><th>Range</th><th>Description</th></tr></thead>
            <tbody>
              <tr><td>Level A – Platinum</td><td>90–100</td><td>Exceptional intelligence &amp; ethics</td></tr>
              <tr><td>Level B – Gold</td><td>75–89</td><td>High capability &amp; integrity</td></tr>
              <tr><td>Level C – Silver</td><td>60–74</td><td>Competent &amp; balanced</td></tr>
              <tr><td>Level D – Bronze</td><td>45–59</td><td>Developing consistency</td></tr>
              <tr><td>Level E – Base</td><td>Below 45</td><td>Needs improvement</td></tr>
            </tbody>
          </table>
        </div>
      </InfoAccordionItem>

      <InfoAccordionItem id="sPath" number="C" title="Verification Path">
        <ol>
          <li>Data Ingestion – 42 items imported from verified user records.</li>
          <li>Auto Scoring – Positive and negative weightings applied.</li>
          <li>Tribunal Validation – Confirms academic, experience, and penalties.</li>
          <li>Peer Validation – Adds transparency for social and reflective components.</li>
          <li>Final HIP Calculation – Aggregated and normalized automatically.</li>
          <li>HIP Level Publication – Issued with certification and tribunal endorsement.</li>
        </ol>
      </InfoAccordionItem>
    </InfoAccordion>
  </InfoPageShell>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import InfoPageShell from '@/components/infoPages/InfoPageShell.vue';
import InfoAccordion from '@/components/infoPages/InfoAccordion.vue';
import InfoAccordionItem from '@/components/infoPages/InfoAccordionItem.vue';
import HipRankBadge from '@/components/commonComponents/HipRankBadge.vue';
import { refreshLci, type LciSummary } from '@/services/lci';

type Rule = { label: string; points: number; unit?: string };
type Category = { key: string; short: string; label: string; icon: string; description: string; rules: Rule[]; note?: string };

// Point values mirror App\Services\HipScoreCalculator; keys match the /scores/lci breakdown.
const CATEGORIES: Category[] = [
  {
    key: 'tqm', short: 'TQM', label: 'Daily Questions (TQM)', icon: 'bi-calendar-check',
    description: 'Points from the Daily HIP Question. Each option carries its own value.',
    rules: [
      { label: 'Best answer', points: 5, unit: 'per day' },
      { label: 'Partial answers', points: 4, unit: 'max' },
    ],
    note: 'You can change your answer until midnight; it is scored then.',
  },
  {
    key: 'em', short: 'EM', label: 'Exams (EM)', icon: 'bi-stopwatch',
    description: 'Timed category exams: 30 questions in 15 minutes, 0–5 points per answer.',
    rules: [{ label: 'One exam (30 × 5 points)', points: 150, unit: 'max' }],
    note: 'One attempt per category per day.',
  },
  {
    key: 'pcm', short: 'PCM', label: 'Profile Completion (PCM)', icon: 'bi-person-check',
    description: 'Verified profile completion and identity verification.',
    rules: [
      { label: 'Profile completion', points: 2455 },
      { label: 'Profile verification', points: 2455 },
    ],
  },
  {
    key: 'education', short: 'Education', label: 'Education', icon: 'bi-mortarboard',
    description: 'Points for each qualification on your profile.',
    rules: [
      { label: 'PhD / Doctorate', points: 30687.5 },
      { label: 'Master’s degree', points: 18412.5 },
      { label: 'Bachelor’s degree', points: 12275 },
      { label: 'Diploma (3 years+)', points: 11047.5 },
      { label: 'Certificate', points: 6137.5 },
    ],
  },
  {
    key: 'experience', short: 'Experience', label: 'Experience', icon: 'bi-briefcase',
    description: 'Points for every full year in a role, by position type.',
    rules: [
      { label: 'Executive', points: 2455, unit: '/ year' },
      { label: 'Life care', points: 1636.5, unit: '/ year' },
      { label: 'Non-executive', points: 1227.5, unit: '/ year' },
      { label: 'General life', points: 102.5, unit: '/ year' },
    ],
  },
  {
    key: 'formal_recognition', short: 'Recognition', label: 'Formal Recognition', icon: 'bi-award',
    description: 'Verified achievements and professional registration.',
    rules: [
      { label: 'Founder (20+ years company)', points: 36825 },
      { label: 'Author / published book', points: 24550 },
      { label: 'Patent holder', points: 24550 },
      { label: 'Tech startup / NGO founder', points: 18412.5 },
      { label: 'Registered professional', points: 12275 },
      { label: 'ISO / compliance certified', points: 12275 },
    ],
  },
  {
    key: 'others_legal', short: 'Others/Legal', label: 'Others / Legal', icon: 'bi-bank',
    description: 'Penalties confirmed by the Tribunal.',
    rules: [
      { label: 'Ethical / bribery breach', points: -30687.5 },
      { label: 'Extremism', points: -24550 },
      { label: 'Legal conviction', points: -18412.5 },
      { label: 'Employment disciplinary action', points: -12275 },
    ],
  },
];

// Tier colours are the rank-badge colours from App\Services\HipRankMatrix.
const TIERS = [
  { name: 'Platinum', levels: '1 – 3', band: 'Within 10% of the top', color: '#E5E4E2' },
  { name: 'Gold', levels: '1 – 4', band: '10% – 20% below the top', color: '#FFD700' },
  { name: 'Silver', levels: '1 – 8', band: '20% – 50% below the top', color: '#C0C0C0' },
  { name: 'Bronze', levels: '1 – 2', band: '50% or more below the top', color: '#CD7F32' },
];

const lci = ref<LciSummary | null>(null);
const lciLoading = ref(true);
const lciError = ref('');
const selected = ref('education');

const current = computed(() => CATEGORIES.find((category) => category.key === selected.value) ?? CATEGORIES[0]);

const scoreFor = (key: string) => lci.value?.breakdown.find((item) => item.key === key)?.score ?? 0;
const maxAbsScore = computed(() => Math.max(1, ...CATEGORIES.map((category) => Math.abs(scoreFor(category.key)))));
const maxAbsRule = computed(() => Math.max(1, ...current.value.rules.map((rule) => Math.abs(rule.points))));

// Non-zero values always get a visible sliver so tiny sections (e.g. a few TQM points) still register.
const barWidth = (value: number) => (value === 0 ? '0%' : `max(4px, ${(Math.abs(value) / maxAbsScore.value) * 100}%)`);
const ruleWidth = (value: number) => `max(4px, ${(Math.abs(value) / maxAbsRule.value) * 100}%)`;

const formatPoints = (value: number) => new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 }).format(value);
const signed = (value: number) => (value > 0 ? `+${formatPoints(value)}` : formatPoints(value));

async function loadLci(): Promise<void> {
  lciLoading.value = true;
  lciError.value = '';
  try {
    lci.value = await refreshLci();
  } catch {
    lciError.value = 'Your scores could not be loaded. The rules below still apply.';
  } finally {
    lciLoading.value = false;
  }
}

onMounted(loadLci);
</script>

<style scoped>
.live-card { margin-bottom: 18px; padding: 20px; background: #fff; border: 1px solid var(--ds-border); border-radius: 18px; box-shadow: 0 1px 2px rgba(15, 23, 42, .05); }

.live-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
.live-eyebrow { margin: 0; color: var(--ds-text-subtle); font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
.live-title { margin: 2px 0 0; color: var(--ds-text); font-size: 26px; font-weight: 800; font-variant-numeric: tabular-nums; }
.live-title span { color: var(--ds-text-muted); font-size: 14px; font-weight: 600; }

.formula { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; margin: 14px 0 12px; padding: 10px 12px; background: var(--ds-surface-subtle); border-radius: 12px; }
.formula-lhs { margin-right: 4px; color: var(--ds-text); font-size: 13px; font-weight: 800; }
.formula-plus { color: var(--ds-text-subtle); font-weight: 700; }
.formula-term { padding: 3px 9px; color: var(--ds-text-secondary); font-size: 12.5px; font-weight: 700; background: #fff; border: 1px solid var(--ds-border); border-radius: 8px; transition: background-color .2s ease, color .2s ease, border-color .2s ease; }
.formula-term:hover { color: var(--ds-primary-hover); border-color: var(--ds-primary-200); }
.formula-term.active { color: #fff; background: var(--ds-primary); border-color: var(--ds-primary); }
.formula-term:focus-visible, .bar-row:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; }

.live-error { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 0 0 12px; padding: 10px 12px; color: var(--ds-danger-text); font-size: 13px; background: var(--ds-danger-soft); border-radius: 10px; }
.link-button { padding: 0; color: var(--ds-primary-hover); font-weight: 700; text-decoration: underline; background: none; border: 0; }

.bar-list { display: grid; gap: 2px; margin: 0; padding: 0; list-style: none; }

.bar-row {
  display: grid;
  width: 100%;
  grid-template-columns: minmax(150px, 1.1fr) 2fr minmax(84px, auto);
  align-items: center;
  gap: 12px;
  padding: 8px 10px;
  color: var(--ds-text-secondary);
  text-align: left;
  background: none;
  border: 0;
  border-radius: 10px;
  transition: background-color .2s ease;
}

.bar-row:hover { background: var(--ds-surface-subtle); }
.bar-row.active { background: var(--ds-primary-soft); }
.bar-label { display: flex; align-items: center; gap: 8px; min-width: 0; overflow: hidden; font-size: 13px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.bar-label i { color: var(--ds-text-subtle); }
.bar-row.active .bar-label i { color: var(--ds-primary); }

/* One series → one hue (blue); penalties use rose, and every value is also written out with its sign. */
.bar-track, .rule-track { height: 8px; overflow: hidden; background: var(--ds-surface-muted); border-radius: 4px; }
.bar-fill, .rule-fill { display: block; height: 100%; background: var(--ds-info); border-radius: 4px; transition: width .4s ease; }
.bar-fill.negative, .rule-fill.negative { background: var(--ds-primary); }
.bar-value { color: var(--ds-text); font-size: 13px; font-weight: 700; font-variant-numeric: tabular-nums; text-align: right; }
.bar-value.zero { color: var(--ds-text-subtle); font-weight: 600; }

.rules-panel { margin-top: 14px; padding: 16px; background: #fcfcfd; border: 1px solid var(--ds-surface-muted); border-radius: 14px; }
.rules-head { display: flex; gap: 12px; margin-bottom: 12px; }
.rules-icon { display: grid; flex: 0 0 38px; width: 38px; height: 38px; color: var(--ds-primary); font-size: 17px; place-items: center; background: var(--ds-primary-soft); border-radius: 11px; }
.rules-title { margin: 0; color: var(--ds-text); font-size: 15px; font-weight: 750; }
.rules-desc { margin: 2px 0 0; color: var(--ds-text-muted); font-size: 13px; }
.rule-list { display: grid; gap: 8px; margin: 0; padding: 0; list-style: none; }
.rule-row { display: grid; grid-template-columns: minmax(150px, 1.2fr) 1.6fr minmax(96px, auto); align-items: center; gap: 12px; }
.rule-label { color: var(--ds-text-secondary); font-size: 13px; }
.rule-points { color: var(--ds-text); font-size: 13px; font-weight: 700; font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; }
.rule-points small { color: var(--ds-text-muted); font-weight: 600; }
.rules-note { display: flex; gap: 6px; margin: 12px 0 0; color: var(--ds-text-muted); font-size: 12.5px; }

.ladder { margin-top: 18px; }
.ladder-title { margin: 0; color: var(--ds-text); font-size: 15px; font-weight: 750; }
.ladder-desc { margin: 4px 0 10px; color: var(--ds-text-muted); font-size: 13px; line-height: 1.55; }
.ladder-list { display: grid; gap: 6px; margin: 0; padding: 0; list-style: none; }
.ladder-row { display: grid; grid-template-columns: 14px 84px 56px 1fr auto; align-items: center; gap: 10px; padding: 9px 12px; background: #fff; border: 1px solid var(--ds-surface-muted); border-radius: 12px; }
.ladder-row.current { background: color-mix(in srgb, var(--tier) 22%, #fff); border-color: color-mix(in srgb, var(--tier) 70%, var(--ds-text-subtle)); }
.tier-swatch { width: 14px; height: 14px; background: var(--tier); border: 1px solid rgba(0, 0, 0, .12); border-radius: 4px; }
.tier-name { color: var(--ds-text); font-size: 13.5px; font-weight: 750; }
.tier-levels { color: var(--ds-text-muted); font-size: 12px; font-variant-numeric: tabular-nums; }
.tier-band { color: var(--ds-text-secondary); font-size: 12.5px; }
.tier-you { padding: 2px 9px; color: var(--ds-text); font-size: 11px; font-weight: 800; background: #fff; border: 1px solid var(--ds-border-strong); border-radius: 999px; }

.doc-heading { margin: 4px 2px 6px; color: var(--ds-text); font-size: 17px; font-weight: 800; }
.doc-note { display: flex; gap: 6px; margin: 0 2px 10px; color: var(--ds-text-muted); font-size: 12.5px; }

.table-scroll { margin: 4px 0 12px; overflow-x: auto; border: 1px solid var(--ds-surface-muted); border-radius: 12px; }
.info-table { width: 100%; min-width: 480px; border-collapse: collapse; font-size: 13px; background: #fff; }
.info-table th, .info-table td { padding: 9px 12px; text-align: left; border-bottom: 1px solid var(--ds-surface-muted); }
.info-table th { color: var(--ds-primary-hover); font-size: 11.5px; font-weight: 800; letter-spacing: .03em; background: #fff7f8; }
.info-table tbody tr:last-child td { border-bottom: 0; }
.info-table tbody tr:hover td { background: var(--ds-surface-subtle); }
.formula-block { padding: 10px 12px; color: var(--ds-text); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; background: var(--ds-surface-subtle); border-radius: 10px; }

@media (max-width: 575px) {
  .live-card { padding: 16px 14px; }
  .bar-row { grid-template-columns: 1fr auto; }
  .bar-track { grid-column: 1 / -1; grid-row: 2; }
  .rule-row { grid-template-columns: 1fr auto; }
  .rule-track { grid-column: 1 / -1; grid-row: 2; }
  .ladder-row { grid-template-columns: 14px 1fr auto; }
  .tier-levels { display: none; }
  .tier-band { grid-column: 2 / -1; }
}

@media (prefers-reduced-motion: reduce) {
  .bar-fill, .rule-fill, .formula-term, .bar-row { transition: none; }
}
</style>
