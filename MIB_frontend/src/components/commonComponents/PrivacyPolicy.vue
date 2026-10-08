<template>
  <div class="row flex-grow-1 overflow-auto m-0 justify-content-center mb-3 gap-1">
    <div class="col-md-1"></div>
    <div class="col-md-2 mt-3 d-none d-md-block">
      <ProfileDetails />
      <addSiteDeails />
    </div>
    <div class="col-md-4 mt-3">
      <section class="about-container">
        <h1>Privacy Policy</h1>
        <p><strong>Effective Date :</strong> [Insert Date] &nbsp; | &nbsp; <strong>Last Updated :</strong> 12/11/2025</p>

        <p><strong>1. Introduction</strong><br/>
        MyIntellibook (“we,” “our,” or “the Platform”) is committed to protecting your privacy and maintaining the highest standards of data protection. This Privacy Policy explains how we collect, use, store, and safeguard your personal information when you access or use MyIntellibook, including the HIP, Tribunal, and Testament Management modules. By using MyIntellibook, you agree to the terms of this Privacy Policy.
        </p>

        <p><strong>2. Information We Collect</strong><br/>
        We collect only the data necessary to provide accurate, fair, and ethical assessment and communication services.
        <ul>
          <li><em>a. Personal Information</em> – Name, email address, contact details, date of birth, gender, academic and professional background.</li>
          <li><em>b. Assessment Data</em> – HIP responses, test results, scores, ranking data, feedback, daily performance, Tribunal notes.</li>
          <li><em>c. Testament and Legal Data</em> – Executor details, ethical declarations, testament documents.</li>
          <li><em>d. Technical Information</em> – Device identifiers, IP address, browser type, session duration.</li>
        </ul>
        </p>

        <p><strong>3. How We Use Your Information</strong><br/>
        Your information is used only for legitimate purposes:
        <ul>
          <li>To calculate and maintain your HIP score</li>
          <li>To manage Tribunal evaluations fairly</li>
          <li>To operate Testament Management functions</li>
          <li>To improve user experience and performance</li>
          <li>To communicate updates and support</li>
          <li>To comply with legal requirements</li>
        </ul>
        We do not sell, rent, or trade your personal data.
        </p>

        <p><strong>4. Data Ownership and Control</strong><br/>
        You remain the sole owner of your data. MyIntellibook acts as custodian. Users may request access, correction, or deletion (subject to verification).
        </p>

        <p><strong>5. Data Storage and Security</strong><br/>
        All data is encrypted and stored securely under ISO/IEC 27001 and ISO/IEC 27701 standards. Access is restricted to authorized personnel.
        </p>

        <p><strong>6. Data Sharing</strong><br/>
        Limited sharing may occur:
        <ul>
          <li>With Tribunal members for reviews</li>
          <li>With legal authorities when required</li>
          <li>With trusted partners under NDA</li>
        </ul>
        No personal data is disclosed without consent.
        </p>

        <p><strong>7. Cookies and Analytics</strong><br/>
        Cookies are used for session management and analytics. Disabling may affect functionality.
        </p>

        <p><strong>8. International Data Transfers</strong><br/>
        Data may be processed on secure servers outside your country, with equivalent protection.
        </p>

        <p><strong>9. Retention Policy</strong><br/>
        Data is retained only as long as necessary. Inactive accounts or records older than 5 years may be deleted after notice.
        </p>

        <p><strong>10. Rights of Users</strong><br/>
        Users may:
        <ul>
          <li>Access, review, and correct data</li>
          <li>Withdraw consent</li>
          <li>Request deletion or restriction</li>
          <li>File complaints with authorities</li>
        </ul>
        </p>

        <p><strong>11. Children’s Privacy</strong><br/>
        Designed for users 16+. Under 16 requires parental consent.
        </p>

        <p><strong>12. Updates to This Policy</strong><br/>
        Policy may be updated for improvements or legal changes. Users will be notified.
        </p>

        <p><strong>13. Contact Us</strong><br/>
        Data Protection Officer – MyIntellibook<br/>
        Email: info@myintellibook.com<br/>
        Website: <a href="http://myintellibook.com" target="_blank">http://myintellibook.com</a>
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
