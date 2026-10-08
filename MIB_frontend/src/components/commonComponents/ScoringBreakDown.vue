<template>
  <div class="row flex-grow-1 overflow-auto m-0 justify-content-center mb-3 gap-1">
    <div class="col-md-1"></div>

    <!-- Left Sidebar -->
    <div class="col-md-2 mt-3 d-none d-md-block">
      <ProfileDetails />
      <addSiteDeails />
    </div>

    <!-- Main Content - Same style as Terms & Conditions -->
    <div class="col-md-4 mt-3">
      <section class="about-container">
        <h1>LCI & HIP 42-Item Scoring and Verification Framework</h1>

        <p>
          This document defines the complete scoring and verification model used in MyIntellibook for calculating
          the Life Competency Index (LCI) and the Human Intelligence Portfolio (HIP), based on 42 measurable items.
          It includes positive and negative factors to ensure a balanced, transparent, and ethically verified assessment.
        </p>

        <p><strong>1. Life Competency Index (LCI) – 42-Item Framework</strong><br/>
          The LCI measures overall capability across academic, professional, ethical, health, and social domains.
          It aggregates results from 42 measurable items grouped under five categories:
        </p>
        <ul>
          <li>Knowledge & Learning Performance</li>
          <li>Professional & Career Growth</li>
          <li>Social & Ethical Conduct</li>
          <li>Health & Lifestyle Stability</li>
          <li>Self-Evaluation & Reflection</li>
        </ul>

        <p><strong>A. Score Composition</strong></p>
        <div class="table-responsive">
          <table class="info-table">
            <thead>
              <tr>
                <th>Category</th>
                <th>Example Items</th>
                <th>Typical Score Range</th>
                <th>Verification Method</th>
              </tr>
            </thead>
            <tbody>
              <tr><td>Knowledge & Learning</td><td>Daily quiz, subject exam, profile completion</td><td>0–5/day; up to 3072/year</td><td>System verified</td></tr>
              <tr><td>Education</td><td>PhD, Master’s, Bachelor’s, Diploma, Certificate</td><td>6,137–30,687 points</td><td>Tribunal & academic verification</td></tr>
              <tr><td>Work Experience</td><td>Executive / non-executive per year</td><td>1,227–2,455 points</td><td>Appointment letters / Tribunal</td></tr>
              <tr><td>Social Conduct (Positive)</td><td>Participation, mentorship, verified contribution</td><td>0–1,000 points</td><td>Peer verification</td></tr>
              <tr><td>Social Conduct (Negative)</td><td>False submission, unethical behavior</td><td>−500 to −2,000 points</td><td>Tribunal investigation</td></tr>
              <tr><td>Health & Lifestyle</td><td>Fitness, self-declared stability</td><td>0–500 points</td><td>Health certificate / third-party</td></tr>
              <tr><td>Self-Reflection</td><td>Improvement participation, feedback response</td><td>0–1,000 points</td><td>System tracking & verification</td></tr>
            </tbody>
          </table>
        </div>

        <p><strong>B. Calculation Formula</strong><br/>
          LCI = Σ (Si × Wi), where Si = individual item score and Wi = weight (0–1).
        </p>

        <p><strong>C. Negative Indicators and Penalties</strong></p>
        <div class="table-responsive">
          <table class="info-table">
            <thead>
              <tr><th>Type of Violation</th><th>Penalty</th><th>Verification / Approval</th></tr>
            </thead>
            <tbody>
              <tr><td>Minor Misconduct</td><td>−500</td><td>Internal tribunal review</td></tr>
              <tr><td>Moderate Ethical Breach</td><td>−1,000</td><td>Cross-verification with peers & tribunal</td></tr>
              <tr><td>Severe Violation / False Data</td><td>−2,000</td><td>External tribunal confirmation</td></tr>
            </tbody>
          </table>
        </div>

        <p><strong>D. Verification and Ranking</strong></p>
        <div class="table-responsive">
          <table class="info-table">
            <thead>
              <tr><th>Rank</th><th>Range (Percentile)</th><th>Description</th></tr>
            </thead>
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

        <p><strong>2. Human Intelligence Portfolio (HIP) – Integrated Calculation</strong><br/>
          The HIP combines knowledge, applied experience, ethics, reflection, and emotion, converting verified LCI results
          into a normalized intelligence performance score.
        </p>

        <div class="table-responsive">
          <table class="info-table">
            <thead>
              <tr><th>Intelligence Dimension</th><th>Source Items</th><th>Weight</th><th>Verification</th></tr>
            </thead>
            <tbody>
              <tr><td>Knowledge Intelligence (KI)</td><td>Learning & academic results</td><td>30%</td><td>System verified</td></tr>
              <tr><td>Applied Intelligence (AI)</td><td>Work experience, projects</td><td>25%</td><td>Document verified</td></tr>
              <tr><td>Ethical Intelligence (EI)</td><td>Social behavior & penalties</td><td>20%</td><td>Tribunal verified</td></tr>
              <tr><td>Reflective Intelligence (RI)</td><td>Self-evaluation & reflection</td><td>15%</td><td>Peer & system verified</td></tr>
              <tr><td>Emotional Intelligence (EMI)</td><td>Health, communication & stability</td><td>10%</td><td>Tribunal / peer verified</td></tr>
            </tbody>
          </table>
        </div>

        <p><strong>A. Formula</strong><br/>
          HIP = (KI × 0.30) + (AI × 0.25) + (EI × 0.20) + (RI × 0.15) + (EMI × 0.10)
        </p>

        <p><strong>B. Normalization</strong><br/>
          HIP Index = (HIP Score / Highest HIP in System) × 100
        </p>

        <div class="table-responsive">
          <table class="info-table">
            <thead>
              <tr><th>Level</th><th>Range</th><th>Description</th></tr>
            </thead>
            <tbody>
              <tr><td>Level A – Platinum</td><td>90–100</td><td>Exceptional intelligence & ethics</td></tr>
              <tr><td>Level B – Gold</td><td>75–89</td><td>High capability & integrity</td></tr>
              <tr><td>Level C – Silver</td><td>60–74</td><td>Competent & balanced</td></tr>
              <tr><td>Level D – Bronze</td><td>45–59</td><td>Developing consistency</td></tr>
              <tr><td>Level E – Base</td><td>Below 45</td><td>Needs improvement</td></tr>
            </tbody>
          </table>
        </div>

        <p><strong>C. Verification Path</strong></p>
        <ol>
          <li>Data Ingestion – 42 items imported from verified user records.</li>
          <li>Auto Scoring – Positive and negative weightings applied.</li>
          <li>Tribunal Validation – Confirms academic, experience, and penalties.</li>
          <li>Peer Validation – Adds transparency for social and reflective components.</li>
          <li>Final HIP Calculation – Aggregated and normalized automatically.</li>
          <li>HIP Level Publication – Issued with certification and tribunal endorsement.</li>
        </ol>
      </section>
    </div>

    <!-- Right Sidebar -->
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

.about-container ul,
.about-container ol {
  margin: 0.5rem 0 1rem 1.4rem;
  padding-left: 1.2rem;
}

.about-container li {
  margin-bottom: 0.4rem;
}

/* Simple clean table style that fits the Terms page look */
.table-responsive {
  overflow-x: auto;
  margin: 1rem 0;
}

.info-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.94rem;
  background: #fff;
}

.info-table th,
.info-table td {
  border: 1px solid #ddd;
  padding: 0.6rem 0.8rem;
  text-align: left;
}

.info-table th {
  background-color: #f1f1f1;
  font-weight: 600;
  color: #A03829;
}

.info-table tr:nth-child(even) {
  background-color: #fdfdfd;
}
</style>