<template>
  <InfoPageShell
    icon="bi-info-circle"
    eyebrow="About MyIntellibook"
    title="Learning, integrity and human potential — in one platform"
    subtitle="Take a quick tour of what MyIntellibook is and the pillars it is built on."
  >
    <FeatureWalkthrough :steps="steps" label="About MyIntellibook">
      <template #step-platform>
        <p>
          MyIntellibook is an innovative learning and assessment platform designed to enhance
          critical thinking, ethical reasoning, and practical knowledge across multiple disciplines.
          It provides interactive quizzes, performance-based scoring, and intelligent feedback to help
          learners evaluate and improve their understanding.
        </p>
        <ul class="feature-chips">
          <li><i class="bi bi-lightning-charge" aria-hidden="true"></i> Interactive quizzes</li>
          <li><i class="bi bi-bar-chart" aria-hidden="true"></i> Performance-based scoring</li>
          <li><i class="bi bi-chat-square-text" aria-hidden="true"></i> Intelligent feedback</li>
        </ul>
      </template>

      <template #step-hip>
        <p>
          Beyond learning, MyIntellibook introduces the <strong>Human Intelligence Portfolio (HIP)</strong> —
          a dynamic system that groups individuals according to their <strong>Life Competency Index (LCI)</strong>.
          The LCI reflects a person’s achievements, experiences, and personal growth, creating a
          measurable profile of real-life intelligence and capability. In the long term, the LCI may also
          contribute to recognizing individuals’ value and influence within communities and decision-making
          frameworks — representing a thoughtful evolution toward competency-based contribution.
        </p>
        <RouterLink to="/scores" class="inline-link">See your LCI and HIP rank <i class="bi bi-arrow-right" aria-hidden="true"></i></RouterLink>
      </template>

      <template #step-tribunal>
        <p>
          The platform also features an <strong>Online Tribunal</strong>, a digital conflict-resolution system designed
          to help members address personal or professional issues through fair, positive, and time-efficient
          dialogue — eliminating the need for travel while ensuring transparency and real justice.
        </p>
        <RouterLink to="/submit_case/external" class="inline-link">Visit the Tribunal <i class="bi bi-arrow-right" aria-hidden="true"></i></RouterLink>
      </template>

      <template #step-testament>
        <p>
          In addition, MyIntellibook is developing a <strong>Fixed Arm for Testament Management</strong>, providing
          secure, intelligent, and transparent handling of testamentary records. This feature aims to
          simplify the process of managing wills, inheritances, and related commitments — ensuring
          fairness, trust, and accountability in the preservation and execution of personal legacies.
        </p>
      </template>

      <template #step-mission>
        <blockquote class="mission">
          <i class="bi bi-quote" aria-hidden="true"></i>
          <p>
            <strong>Our mission</strong> is to make education and human development smarter, more equitable, and
            deeply connected to real-life values.
          </p>
        </blockquote>
      </template>
    </FeatureWalkthrough>
  </InfoPageShell>
</template>

<script setup lang="ts">
import InfoPageShell from '@/components/infoPages/InfoPageShell.vue';
import FeatureWalkthrough, { type WalkthroughStep } from '@/components/infoPages/FeatureWalkthrough.vue';

const steps: WalkthroughStep[] = [
  { id: 'platform', title: 'The platform', icon: 'bi-mortarboard' },
  { id: 'hip', title: 'HIP & LCI', icon: 'bi-person-badge' },
  { id: 'tribunal', title: 'Online Tribunal', icon: 'bi-bank' },
  { id: 'testament', title: 'Testament Management', icon: 'bi-journal-bookmark' },
  { id: 'mission', title: 'Our mission', icon: 'bi-flag' },
];
</script>

<style scoped>
.feature-chips { display: flex; flex-wrap: wrap; gap: 8px; margin: 4px 0 0 !important; padding: 0 !important; list-style: none; }
.feature-chips li { display: inline-flex; align-items: center; gap: 6px; margin: 0 !important; padding: 6px 12px; color: var(--ds-primary-hover); font-size: 12.5px; font-weight: 600; background: var(--ds-primary-soft); border-radius: 999px; }

.inline-link { display: inline-flex; align-items: center; gap: 6px; color: var(--ds-primary); font-size: 13.5px; font-weight: 700; text-decoration: none; }
.inline-link:hover { text-decoration: underline; }
.inline-link:focus-visible { outline: 2px solid var(--ds-primary); outline-offset: 2px; border-radius: 4px; }

.mission { position: relative; margin: 0; padding: 18px 18px 18px 52px; background: var(--ds-primary-soft); border-radius: 14px; }
.mission > i { position: absolute; top: 12px; left: 16px; color: var(--ds-primary-300); font-size: 28px; }
.mission p { margin: 0; color: #4c0519; font-size: 16px; line-height: 1.6; }
</style>
