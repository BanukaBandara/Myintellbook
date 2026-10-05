<template>
  <InfoPageShell
    icon="bi-lock"
    eyebrow="Legal"
    title="Privacy Policy"
    subtitle="How MyIntellibook collects, uses, stores and safeguards your personal information."
    :badges="['ISO/IEC 27001', 'ISO/IEC 27701', 'Encrypted storage', 'We never sell your data']"
    printable
  >
    <template #hero-extra>
      <p class="policy-dates">
        <span><strong>Effective Date :</strong> [Insert Date]</span>
        <span><strong>Last Updated :</strong> 12/11/2025</span>
      </p>
    </template>

    <InfoAccordion label="Privacy Policy sections" :default-open="['p1']">
      <InfoAccordionItem id="p1" number="1" title="Introduction">
        <p>
          MyIntellibook (“we,” “our,” or “the Platform”) is committed to protecting your privacy and maintaining the highest standards of data protection. This Privacy Policy explains how we collect, use, store, and safeguard your personal information when you access or use MyIntellibook, including the HIP, Tribunal, and Testament Management modules. By using MyIntellibook, you agree to the terms of this Privacy Policy.
        </p>
      </InfoAccordionItem>

      <InfoAccordionItem id="p2" number="2" title="Information We Collect">
        <p>We collect only the data necessary to provide accurate, fair, and ethical assessment and communication services.</p>
        <ul>
          <li><em>a. Personal Information</em> – Name, email address, contact details, date of birth, gender, academic and professional background.</li>
          <li><em>b. Assessment Data</em> – HIP responses, test results, scores, ranking data, feedback, daily performance, Tribunal notes.</li>
          <li><em>c. Testament and Legal Data</em> – Executor details, ethical declarations, testament documents.</li>
          <li><em>d. Technical Information</em> – Device identifiers, IP address, browser type, session duration.</li>
        </ul>
      </InfoAccordionItem>

      <InfoAccordionItem id="p3" number="3" title="How We Use Your Information">
        <p>Your information is used only for legitimate purposes:</p>
        <ul>
          <li>To calculate and maintain your HIP score</li>
          <li>To manage Tribunal evaluations fairly</li>
          <li>To operate Testament Management functions</li>
          <li>To improve user experience and performance</li>
          <li>To communicate updates and support</li>
          <li>To comply with legal requirements</li>
        </ul>
        <p class="callout good"><i class="bi bi-shield-check" aria-hidden="true"></i> We do not sell, rent, or trade your personal data.</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="p4" number="4" title="Data Ownership and Control">
        <p>You remain the sole owner of your data. MyIntellibook acts as custodian. Users may request access, correction, or deletion (subject to verification).</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="p5" number="5" title="Data Storage and Security">
        <p>All data is encrypted and stored securely under ISO/IEC 27001 and ISO/IEC 27701 standards. Access is restricted to authorized personnel.</p>
        <RouterLink to="/encription_details" class="inline-link no-print">See encryption details <i class="bi bi-arrow-right" aria-hidden="true"></i></RouterLink>
      </InfoAccordionItem>

      <InfoAccordionItem id="p6" number="6" title="Data Sharing">
        <p>Limited sharing may occur:</p>
        <ul>
          <li>With Tribunal members for reviews</li>
          <li>With legal authorities when required</li>
          <li>With trusted partners under NDA</li>
        </ul>
        <p>No personal data is disclosed without consent.</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="p7" number="7" title="Cookies and Analytics">
        <p>Cookies are used for session management and analytics. Disabling may affect functionality.</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="p8" number="8" title="International Data Transfers">
        <p>Data may be processed on secure servers outside your country, with equivalent protection.</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="p9" number="9" title="Retention Policy">
        <p>Data is retained only as long as necessary. Inactive accounts or records older than 5 years may be deleted after notice.</p>
        <RouterLink to="/data_retention_rules" class="inline-link no-print">See data retention rules <i class="bi bi-arrow-right" aria-hidden="true"></i></RouterLink>
      </InfoAccordionItem>

      <InfoAccordionItem id="p10" number="10" title="Rights of Users">
        <p>Users may:</p>
        <ul>
          <li>Access, review, and correct data</li>
          <li>Withdraw consent</li>
          <li>Request deletion or restriction</li>
          <li>File complaints with authorities</li>
        </ul>
      </InfoAccordionItem>

      <InfoAccordionItem id="p11" number="11" title="Children’s Privacy">
        <p>Designed for users 16+. Under 16 requires parental consent.</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="p12" number="12" title="Updates to This Policy">
        <p>Policy may be updated for improvements or legal changes. Users will be notified.</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="p13" number="13" title="Contact Us">
        <div class="contact-card">
          <i class="bi bi-person-lock" aria-hidden="true"></i>
          <div>
            <p class="contact-role">Data Protection Officer – MyIntellibook</p>
            <p>Email: <strong>info@myintellibook.com</strong></p>
            <p>Website: <a href="http://myintellibook.com" target="_blank" rel="noopener">http://myintellibook.com</a></p>
          </div>
        </div>
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
.policy-dates { display: flex; flex-wrap: wrap; gap: 6px 16px; margin: 12px 0 0; color: var(--ds-text-muted); font-size: 12.5px; }
.policy-dates strong { color: var(--ds-text-secondary); }

.callout { display: flex; gap: 8px; padding: 10px 12px; border-radius: 10px; }
.callout.good { color: var(--ds-success-text); background: var(--ds-success-soft); }

.inline-link { display: inline-flex; align-items: center; gap: 6px; color: var(--ds-primary); font-size: 13.5px; font-weight: 700; text-decoration: none; }
.inline-link:hover { text-decoration: underline; }

.contact-card { display: flex; gap: 12px; padding: 14px; background: #fff; border: 1px solid var(--ds-surface-muted); border-radius: 14px; }
.contact-card > i { display: grid; flex: 0 0 40px; width: 40px; height: 40px; color: var(--ds-primary); font-size: 18px; place-items: center; background: var(--ds-primary-soft); border-radius: 12px; }
.contact-card p { margin: 0 0 2px !important; }
.contact-role { color: var(--ds-text); font-weight: 700; }
.contact-card a { color: var(--ds-primary); }
</style>
