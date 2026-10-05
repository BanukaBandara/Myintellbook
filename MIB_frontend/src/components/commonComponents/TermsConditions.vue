<template>
  <InfoPageShell
    icon="bi-file-text"
    eyebrow="Legal"
    title="Terms & Conditions"
    subtitle="The rules that govern your use of MyIntellibook, its scoring models, the Tribunal and related services."
    printable
  >
    <InfoAccordion label="Terms and Conditions sections" :default-open="['t1']">
      <InfoAccordionItem id="t1" number="1" title="Introduction">
        <p>
          These Terms &amp; Conditions (“T&amp;C”) govern your access to and use of MyIntellibook,
          including all assessments, scoring models, learning modules, the Human Intelligence
          Portfolio (HIP), Life Competency Index (LCI), Tribunal System, Negative Scoring System,
          and all related services. By registering or using MyIntellibook, you agree to comply with these Terms.
        </p>
      </InfoAccordionItem>

      <InfoAccordionItem id="t2" number="2" title="Purpose of MyIntellibook">
        <p>MyIntellibook is designed to help individuals:</p>
        <ul>
          <li>Identify strengths and weaknesses</li>
          <li>Develop competency-based skills</li>
          <li>Improve personal discipline and learning habits</li>
          <li>Build a long-term Human Intelligence Portfolio (HIP)</li>
          <li>Track growth through continuous scoring and evaluation</li>
          <li>Maintain transparency in behaviour, learning attitude, and decision-making</li>
        </ul>
      </InfoAccordionItem>

      <InfoAccordionItem id="t3" number="3" title="User Responsibilities">
        <p>Users agree to:</p>
        <ul>
          <li>Provide accurate personal information</li>
          <li>Answer questions honestly</li>
          <li>Follow platform rules and ethical guidelines</li>
          <li>Respect other users</li>
          <li>Not misuse or manipulate scoring systems</li>
        </ul>
        <p class="callout warn"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Intentional misuse may lead to negative scoring or suspension.</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="t4" number="4" title="Scoring System">
        <p><strong>4.1 HIP Score</strong> — HIP is calculated based on:</p>
        <ul>
          <li>Daily Learning Score</li>
          <li>Self-Exam Score</li>
          <li>Behavioural Score</li>
          <li>Discipline Score</li>
          <li>LCI Component Scores</li>
          <li>Negative/Positive behavioural patterns</li>
          <li>Tribunal outcomes</li>
        </ul>
        <p><strong>4.2 Negative Scoring</strong> — Negative scoring applies for dishonesty, cheating, misuse of features, harassment,
          or repeated refusal to correct behaviour.</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="t5" number="5" title="Daily Learning Questions">
        <p>
          Users receive a fixed number of daily questions across multiple subjects.
          Scores are based on correctness, consistency, effort, learning discipline, and completion behaviour.
          Repeated skipping may reduce user reliability.
        </p>
      </InfoAccordionItem>

      <InfoAccordionItem id="t6" number="6" title="Weekly & Monthly Assessments">
        <p>
          Weekly exams and monthly evaluations influence HIP and LCI, providing long-term trend analysis.
          Cheating or external assistance results in automatic negative scoring.
        </p>
      </InfoAccordionItem>

      <InfoAccordionItem id="t7" number="7" title="Tribunal System">
        <p>The Tribunal handles misconduct, behaviour conflicts, dishonesty, complaints, and score appeals. Outcomes may include:</p>
        <ul>
          <li>Warnings</li>
          <li>Negative scoring</li>
          <li>Score adjustments</li>
          <li>Temporary suspension</li>
          <li>Permanent removal</li>
        </ul>
      </InfoAccordionItem>

      <InfoAccordionItem id="t8" number="8" title="Fixed Arm for Testament Management">
        <p>For users who activate Testament Management, the Fixed Arm ensures:</p>
        <ul>
          <li>Proper health-care oversight</li>
          <li>Transparent will/testament handling</li>
          <li>Audit of testament management activities</li>
          <li>Delivery of the final will to the correct party</li>
          <li>Prevention of misuse or manipulation</li>
        </ul>
        <p>The Fixed Arm reports to the MyIntellibook Legal &amp; Quality Panel.</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="t9" number="9" title="Data Privacy & Security">
        <p>
          MyIntellibook does not disclose personal data except when required by law,
          or when the user activates testament-related features.
          All data is encrypted and monitored for integrity.
        </p>
        <RouterLink to="/privacy_policy" class="inline-link no-print">Read the Privacy Policy <i class="bi bi-arrow-right" aria-hidden="true"></i></RouterLink>
      </InfoAccordionItem>

      <InfoAccordionItem id="t10" number="10" title="Ranking System">
        <p>User ranking is determined by:</p>
        <ul>
          <li>HIP Score</li>
          <li>LCI Score</li>
          <li>Daily/Weekly participation</li>
          <li>Behaviour rating</li>
          <li>Tribunal decisions</li>
          <li>Learning discipline trends</li>
        </ul>
      </InfoAccordionItem>

      <InfoAccordionItem id="t11" number="11" title="Account Suspension & Termination">
        <p>Accounts may be suspended or terminated for:</p>
        <ul>
          <li>Fraud</li>
          <li>Cheating</li>
          <li>Harassment</li>
          <li>Sharing login credentials</li>
          <li>Repeated behavioural violations</li>
        </ul>
      </InfoAccordionItem>

      <InfoAccordionItem id="t12" number="12" title="Limitation of Liability">
        <p>MyIntellibook is an educational and behavioural development platform. It does not guarantee:</p>
        <ul>
          <li>Employment</li>
          <li>Academic certification</li>
          <li>Psychological assessment outcomes</li>
          <li>Legal or medical advice</li>
        </ul>
      </InfoAccordionItem>

      <InfoAccordionItem id="t13" number="13" title="Amendments to Terms">
        <p>
          MyIntellibook reserves the right to update these Terms at any time.
          Continued use indicates acceptance of all updates.
        </p>
      </InfoAccordionItem>

      <InfoAccordionItem id="t14" number="14" title="Acceptance">
        <p>By using MyIntellibook, the user confirms:</p>
        <p class="callout accept"><i class="bi bi-check2-circle" aria-hidden="true"></i> <em>“I have read, understood, and agree to all Terms &amp; Conditions.”</em></p>
      </InfoAccordionItem>
    </InfoAccordion>
  </InfoPageShell>
</template>

<script setup lang="ts">
import InfoPageShell from '@/components/infoPages/InfoPageShell.vue';
import InfoAccordion from '@/components/infoPages/InfoAccordion.vue';
import InfoAccordionItem from '@/components/infoPages/InfoAccordionItem.vue';
</script>

<style scoped>
.callout { display: flex; gap: 8px; padding: 10px 12px; border-radius: 10px; }
.callout.warn { color: var(--ds-warning-text); background: var(--ds-warning-soft); }
.callout.accept { color: var(--ds-success-text); background: var(--ds-success-soft); }
.callout em { color: inherit !important; }
.inline-link { display: inline-flex; align-items: center; gap: 6px; color: var(--ds-primary); font-size: 13.5px; font-weight: 700; text-decoration: none; }
.inline-link:hover { text-decoration: underline; }
</style>
