<template>
  <div class="row flex-grow-1 overflow-auto m-0 justify-content-center mb-3 gap-1">
    <div class="col-md-1"></div>
    <div class="col-md-2 mt-3 d-none d-md-block">
      <ProfileDetails />
      <addSiteDeails />
    </div>
    <div class="col-md-4 mt-3">
      <section class="about-container">
        <h1>Encryption and Data Security</h1>

        <p><strong>1. Purpose</strong><br/>
        MyIntellibook implements multi-layer encryption and access-control mechanisms to ensure the confidentiality, integrity, and availability of all user data processed within the platform. All HIP (Human Intelligence Portfolio), Tribunal, and Testament Management information is protected by industry-standard cryptographic technologies during storage, transmission, and processing.
        </p>

        <p><strong>2. Encryption Framework Overview</strong></p>
        <table>
          <thead>
            <tr>
              <th>Layer</th>
              <th>Technique</th>
              <th>Standard / Algorithm</th>
              <th>Purpose</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Data Transmission</td>
              <td>TLS 1.3 with AES-256 bit encryption</td>
              <td>Transport Layer Security (TLS 1.3)</td>
              <td>Encrypts communication between clients and servers to prevent interception.</td>
            </tr>
            <tr>
              <td>Data Storage (At Rest)</td>
              <td>AES-256 encryption in GCM mode</td>
              <td>Advanced Encryption Standard (AES)</td>
              <td>Encrypts stored data (HIP scores, profiles, testament files) so only authorized systems can decrypt.</td>
            </tr>
            <tr>
              <td>Password Protection</td>
              <td>PBKDF2 with SHA-512 + unique salt, &gt;100,000 iterations</td>
              <td>FIPS 140-2 compliant</td>
              <td>Secures passwords via one-way hashing to resist dictionary and rainbow-table attacks.</td>
            </tr>
            <tr>
              <td>API Key &amp; Token Security</td>
              <td>HMAC-SHA256 signatures with JWT</td>
              <td>JSON Web Token (JWT) framework</td>
              <td>Protects session authentication and integrations from tampering or replay attacks.</td>
            </tr>
            <tr>
              <td>Database Field-Level Encryption</td>
              <td>AES-256 per-field keys</td>
              <td>ISO/IEC 19790 &amp; ISO/IEC 27018</td>
              <td>Encrypts sensitive columns (national ID, contact number, legal documents).</td>
            </tr>
          </tbody>
        </table>

        <p><strong>3. Key Management</strong><br/>
        - Keys generated and managed via HSM or secure key vault.<br/>
        - Rotated every 12 months or after security events.<br/>
        - Access restricted to DPO/admins under MFA.<br/>
        - All operations audited and logged.
        </p>

        <p><strong>4. Data Segregation and Access Control</strong><br/>
        - User data segregated by account ID/module.<br/>
        - RBAC ensures authorized access.<br/>
        - Queries/admin access require encrypted API tokens.<br/>
        - Staff trained and cleared before privileges.
        </p>

        <p><strong>5. Backup and Recovery Encryption</strong><br/>
        - Daily backups encrypted with AES-256.<br/>
        - Transmission secured with TLS 1.3 + mutual auth.<br/>
        - Disaster recovery servers follow same encryption standards.
        </p>

        <p><strong>6. End-to-End Confidentiality</strong><br/>
        All sensitive data encrypted in transit and at rest. Decryption only occurs at runtime in secure memory; plaintext is never written to disk.
        </p>

        <p><strong>7. Compliance and Auditing</strong><br/>
        MyIntellibook follows:<br/>
        - ISO/IEC 27001 – Information Security Management<br/>
        - ISO/IEC 27701 – Privacy Information Management<br/>
        - GDPR Articles 32 &amp; 33<br/>
        - Sri Lanka ICTA Data Protection Act (2022)<br/>
        Regular vulnerability assessments and annual penetration testing ensure compliance.
        </p>

        <p><strong>8. Encryption Responsibility</strong></p>
        <table>
          <thead>
            <tr>
              <th>Role</th>
              <th>Responsibility</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Data Protection Officer (DPO)</td>
              <td>Oversees encryption policies, key management, incident reporting.</td>
            </tr>
            <tr>
              <td>System Administrator</td>
              <td>Implements and maintains server-level encryption configurations.</td>
            </tr>
            <tr>
              <td>Software Developers</td>
              <td>Ensure code adheres to encryption and sanitization guidelines.</td>
            </tr>
            <tr>
              <td>Auditor / External Reviewer</td>
              <td>Verifies compliance with ISO and data-protection standards.</td>
            </tr>
          </tbody>
        </table>

        <p><strong>9. Breach Response</strong><br/>
        - Revoke compromised keys.<br/>
        - Incident Response Team activated within 2 hours.<br/>
        - Impact analysis + user notification within 72 hours.<br/>
        - Independent audit review for corrective actions.
        </p>

        <p><strong>10. Summary</strong><br/>
        MyIntellibook employs comprehensive, end-to-end encryption and secure key-management practices to ensure your personal, academic, and legal data remain protected under the highest international standards.
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
