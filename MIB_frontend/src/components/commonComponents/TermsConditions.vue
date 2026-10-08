<template>
  <div class="row flex-grow-1 overflow-auto m-0 justify-content-center mb-3 gap-1">
    <div class="col-md-1"></div>
    <div class="col-md-2 mt-3 d-none d-md-block">
      <ProfileDetails />
      <addSiteDeails />
    </div>
    <div class="col-md-4 mt-3">
      <section class="about-container">
        <h1>Terms &amp; Conditions</h1>
        <p><strong>1. Introduction</strong><br/>
        These Terms &amp; Conditions (“T&amp;C”) govern your access to and use of MyIntellibook,
        including all assessments, scoring models, learning modules, the Human Intelligence
        Portfolio (HIP), Life Competency Index (LCI), Tribunal System, Negative Scoring System,
        and all related services. By registering or using MyIntellibook, you agree to comply with these Terms.
        </p>

        <p><strong>2. Purpose of MyIntellibook</strong><br/>
        MyIntellibook is designed to help individuals:
        <ul>
          <li>Identify strengths and weaknesses</li>
          <li>Develop competency-based skills</li>
          <li>Improve personal discipline and learning habits</li>
          <li>Build a long-term Human Intelligence Portfolio (HIP)</li>
          <li>Track growth through continuous scoring and evaluation</li>
          <li>Maintain transparency in behaviour, learning attitude, and decision-making</li>
        </ul>
        </p>

        <p><strong>3. User Responsibilities</strong><br/>
        Users agree to:
        <ul>
          <li>Provide accurate personal information</li>
          <li>Answer questions honestly</li>
          <li>Follow platform rules and ethical guidelines</li>
          <li>Respect other users</li>
          <li>Not misuse or manipulate scoring systems</li>
        </ul>
        Intentional misuse may lead to negative scoring or suspension.
        </p>

        <p><strong>4. Scoring System</strong><br/>
        <em>4.1 HIP Score</em><br/>
        HIP is calculated based on:
        <ul>
          <li>Daily Learning Score</li>
          <li>Self-Exam Score</li>
          <li>Behavioural Score</li>
          <li>Discipline Score</li>
          <li>LCI Component Scores</li>
          <li>Negative/Positive behavioural patterns</li>
          <li>Tribunal outcomes</li>
        </ul>
        <em>4.2 Negative Scoring</em><br/>
        Negative scoring applies for dishonesty, cheating, misuse of features, harassment,
        or repeated refusal to correct behaviour.
        </p>

        <p><strong>5. Daily Learning Questions</strong><br/>
        Users receive a fixed number of daily questions across multiple subjects.
        Scores are based on correctness, consistency, effort, learning discipline, and completion behaviour.
        Repeated skipping may reduce user reliability.
        </p>

        <p><strong>6. Weekly &amp; Monthly Assessments</strong><br/>
        Weekly exams and monthly evaluations influence HIP and LCI, providing long-term trend analysis.
        Cheating or external assistance results in automatic negative scoring.
        </p>

        <p><strong>7. Tribunal System</strong><br/>
        The Tribunal handles misconduct, behaviour conflicts, dishonesty, complaints, and score appeals.
        Outcomes may include:
        <ul>
          <li>Warnings</li>
          <li>Negative scoring</li>
          <li>Score adjustments</li>
          <li>Temporary suspension</li>
          <li>Permanent removal</li>
        </ul>
        </p>

        <p><strong>8. Fixed Arm for Testament Management</strong><br/>
        For users who activate Testament Management, the Fixed Arm ensures:
        <ul>
          <li>Proper health-care oversight</li>
          <li>Transparent will/testament handling</li>
          <li>Audit of testament management activities</li>
          <li>Delivery of the final will to the correct party</li>
          <li>Prevention of misuse or manipulation</li>
        </ul>
        The Fixed Arm reports to the MyIntellibook Legal &amp; Quality Panel.
        </p>

        <p><strong>9. Data Privacy &amp; Security</strong><br/>
        MyIntellibook does not disclose personal data except when required by law,
        or when the user activates testament-related features.
        All data is encrypted and monitored for integrity.
        </p>

        <p><strong>10. Ranking System</strong><br/>
        User ranking is determined by:
        <ul>
          <li>HIP Score</li>
          <li>LCI Score</li>
          <li>Daily/Weekly participation</li>
          <li>Behaviour rating</li>
          <li>Tribunal decisions</li>
          <li>Learning discipline trends</li>
        </ul>
        </p>

        <p><strong>11. Account Suspension &amp; Termination</strong><br/>
        Accounts may be suspended or terminated for:
        <ul>
          <li>Fraud</li>
          <li>Cheating</li>
          <li>Harassment</li>
          <li>Sharing login credentials</li>
          <li>Repeated behavioural violations</li>
        </ul>
        </p>

        <p><strong>12. Limitation of Liability</strong><br/>
        MyIntellibook is an educational and behavioural development platform.
        It does not guarantee:
        <ul>
          <li>Employment</li>
          <li>Academic certification</li>
          <li>Psychological assessment outcomes</li>
          <li>Legal or medical advice</li>
        </ul>
        </p>

        <p><strong>13. Amendments to Terms</strong><br/>
        MyIntellibook reserves the right to update these Terms at any time.
        Continued use indicates acceptance of all updates.
        </p>

        <p><strong>14. Acceptance</strong><br/>
        By using MyIntellibook, the user confirms:<br/>
        <em>“I have read, understood, and agree to all Terms &amp; Conditions.”</em>
        </p>
      </section>
    </div>
    <div class="col-md-3 mt-3 d-none d-md-block">
      <latestUpdates />
      <Divider class="w-75"/>
      <Divider class="w-75" />
      <Divider class="w-75" />
      <ProfileList />
    </div>
    <div class="col-md-2"></div>
  </div>
</template>

<script setup lang="ts">
import { defineAsyncComponent } from 'vue';
const addSiteDeails = defineAsyncComponent(() => import('../../components/commonComponents/addSiteDeails.vue'));
const latestUpdates = defineAsyncComponent(() => import('../../components/commonComponents/latestUpdates.vue'));
const userProfile = defineAsyncComponent(() => import('../../stores/User/userProfile'));
import Divider from 'primevue/divider';
</script>

<style scoped>
.about-container {
  max-width: 800px;
  padding: 1rem 2rem;
  font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
  line-height: 1.6;
  background-color: #f9f9f9;
  border-radius: 8px;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
}

.about-container h1 {
  font-size: 1.3rem;
  margin-bottom: 1rem;
  color: #2c3e50;
  font-weight: 600;
}

.about-container p {
  margin-bottom: 1rem;
  color: #333;
}

.about-container strong {
  color: #A03829;
}
</style>
