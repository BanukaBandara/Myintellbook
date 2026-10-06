<template>
  <div class="row flex-grow-1 overflow-auto m-0 justify-content-center mb-3 gap-1">
    <div class="col-md-1"></div>
    <div class="col-md-2 mt-3 d-none d-md-block">
      <ProfileDetails />
      <addSiteDeails />
    </div>
    <div class="col-md-4 mt-3">
      <section class="about-container">
        <h1>Data Retention Rules</h1>

        <p><strong>1. Purpose of Retention Rules</strong><br/>
        These retention rules describe how long MyIntellibook keeps different categories of data, in alignment with the platform’s Privacy Policy. Data is retained only for as long as needed for operational, legal, assessment, or compliance purposes.
        </p>

        <p><strong>2. General Retention Principle</strong><br/>
        MyIntellibook retains personal, behavioural, learning, and assessment data only for the minimum required period. Data older than the defined retention duration is permanently deleted unless required for legal or compliance reasons.
        </p>

        <p><strong>3. Retention Periods by Data Category</strong></p>
        <ul>
          <li><em>a. Personal Profile Data</em> (Name, contact details, registration info)<br/>
            • Retained for as long as the user maintains an active account.<br/>
            • Deleted within 12 months after account deletion request.
          </li>
          <li><em>b. Learning &amp; Assessment Data</em> (HIP, LCI, daily questions, exam results)<br/>
            • Retained for 5 years from the last date of activity.<br/>
            • Automatically purged after 5 years of inactivity.
          </li>
          <li><em>c. Tribunal Records</em><br/>
            • Retained for 5 years for audit and behavioural integrity purposes.<br/>
            • Deleted after 5 years unless linked to an ongoing investigation.
          </li>
          <li><em>d. Testament &amp; Legal Data</em> (if activated)<br/>
            • Retained until the testament is executed or user withdraws the feature.<br/>
            • After execution or withdrawal, data is deleted within 180 days.
          </li>
          <li><em>e. Technical System Logs</em><br/>
            • Retained for 12 months for performance, security, and troubleshooting.<br/>
            • Automatically deleted afterward.
          </li>
        </ul>

        <p><strong>4. Inactive Accounts</strong><br/>
        Accounts with no login or activity for 5 years may be permanently deleted. Users will receive a notification before deletion.
        </p>

        <p><strong>5. Manual Deletion Upon Request</strong><br/>
        Users may request deletion of their data at any time. Identity verification is required. Deletion will occur within 30–90 days depending on the category of data.
        </p>

        <p><strong>6. Legal or Compliance Exceptions</strong><br/>
        Some data may be retained longer if required by:<br/>
        • Law enforcement requests<br/>
        • Court orders<br/>
        • Regulatory obligations<br/>
        • Ongoing investigations
        </p>

        <p><strong>7. Secure Disposal</strong><br/>
        All deletions (automatic or manual) are performed through secure data destruction methods ensuring no recovery of personal or assessment data.
        </p>

        <p><strong>8. Updates to Retention Rules</strong><br/>
        MyIntellibook may revise these retention rules periodically to reflect internal process changes, legal requirements, or system improvements.
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
  font-family: 'Segoe UI', sans-serif;
  line-height: 1.6;
  background-color: #f9f9f9;
  border-radius: 8px;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
}

.about-container h1 {
  font-size: 2rem;
  margin-bottom: 1rem;
  color: #2c3e50;
}

.about-container p {
  margin-bottom: 1rem;
  color: #333;
}

.about-container strong {
  color: #A03829;
}

.about-container ul {
  margin: 0.5rem 0 1rem 1.2rem;
  padding: 0;
}

.about-container li {
  margin-bottom: 0.8rem;
  color: #333;
}
</style>
