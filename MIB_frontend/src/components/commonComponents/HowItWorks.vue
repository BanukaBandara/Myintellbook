<template>
  <div class="row flex-grow-1 overflow-auto m-0 justify-content-center mb-3 gap-1">
    <div class="col-md-1"></div>
    <div class="col-md-2 mt-3 d-none d-md-block">
      <ProfileDetails />
      <addSiteDeails />
    </div>
    <div class="col-md-4 mt-3">
      <section class="about-container">
        <h1>See How It Works</h1>

        <p>
          MyIntellibook is an intelligent evaluation and ranking platform that integrates the Human Intelligence Portfolio (HIP), Tribunal Evaluation, and Testament Management into a unified, transparent, and ethical framework. The HIP Score is derived from Holistic Index Points (HIP Values) that represent intellectual ability, ethical reasoning, and social responsibility. This model connects knowledge with accountability and transforms learning into measurable impact.
        </p>

        <p><strong>1. HIP Calculation (Human Intelligence Portfolio)</strong><br/>
        The HIP algorithm evaluates participants through daily and cumulative performance indicators. It combines several core elements:
        </p>
        <ul>
          <li>Current Affairs Daily Question Score – Measures awareness and understanding of current global and national issues.</li>
          <li>Self-Evaluation Score (Personal Reflection Score) – Reflects an individual’s ability to assess reasoning, responsibility, and ethical decision-making.</li>
          <li>Negative Scoring – Applied when a participant engages in socially or ethically negative behavior, ensuring HIP promotes ethical conduct, discipline, and positive social behavior.</li>
        </ul>

        <table>
          <thead>
            <tr>
              <th>Score</th>
              <th>Meaning</th>
            </tr>
          </thead>
          <tbody>
            <tr><td>0</td><td>No Understanding / Incorrect Response</td></tr>
            <tr><td>1</td><td>Very Low Clarity or Effort</td></tr>
            <tr><td>2</td><td>Basic Awareness with Limited Accuracy</td></tr>
            <tr><td>3</td><td>Moderate Understanding with Logical Reasoning</td></tr>
            <tr><td>4</td><td>Strong Analytical and Ethical Reasoning</td></tr>
            <tr><td>5</td><td>Excellent Insight, Reasoning, and Application</td></tr>
          </tbody>
        </table>

        <p><strong>2. Tribunal Evaluation</strong><br/>
        The MyIntellibook Tribunal operates as a dual-level mechanism ensuring fairness, validation, and social responsibility:
        </p>
        <ul>
          <li><em>Internal Tribunal</em> – Validates HIP Scores, verifies data integrity, ensures transparency, and detects anomalies.</li>
          <li><em>External Tribunal</em> – Resolves external issues, promotes harmony, and offers ethical guidance.</li>
        </ul>

        <p><strong>3. Testament Management</strong><br/>
        The Testament Management module engages senior citizens and experienced professionals to contribute wisdom and mentorship. A Fixed Arm — composed of Legal and Quality divisions — oversees ethical and transparent administration of testamentary activities, ensuring alignment with MyIntellibook’s standards while supporting the elderly community.
        </p>

        <p><strong>4. Final Ranking</strong><br/>
        Once verified, HIP, Tribunal, and Traditional metrics are combined to produce a comprehensive performance ranking. Results are displayed in an interactive dashboard highlighting leadership indicators, learning capacity, and community impact.
        </p>

        <p><strong>5. Transparency and Security</strong><br/>
        All participant data is securely handled under MyIntellibook’s confidentiality and data protection policies. Manual intervention in results is strictly prohibited, ensuring complete trust, accuracy, and traceability.
        </p>

        <p><em>In essence, MyIntellibook transforms evaluation into a life mission — building a Human Intelligence Portfolio that measures not only knowledge but also ethical responsibility, compassion, and social contribution.</em></p>
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
  font-family: 'Segoe UI', sans-serif;
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

.about-container table {
  width: 100%;
  border-collapse: collapse;
  margin: 1rem 0;
}

.about-container table th,
.about-container table td {
  border: 1px solid #ddd;
  padding: 0.5rem;
  text-align: left;
}

.about-container table th {
  background-color: #f0f0f0;
  color: #2c3e50;
}
</style>
