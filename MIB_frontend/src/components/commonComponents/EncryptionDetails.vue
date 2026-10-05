<template>
  <InfoPageShell
    icon="bi-shield-check"
    eyebrow="Security"
    title="Encryption and Data Security"
    subtitle="The cryptographic and access-control layers protecting HIP, Tribunal and Testament data."
    :badges="['TLS 1.3', 'AES-256-GCM', 'PBKDF2-SHA512', 'HSM key management']"
    printable
  >
    <InfoAccordion label="Encryption sections" :default-open="['e1', 'e2']">
      <InfoAccordionItem id="e1" number="1" title="Purpose">
        <p>
          MyIntellibook implements multi-layer encryption and access-control mechanisms to ensure the confidentiality, integrity, and availability of all user data processed within the platform. All HIP (Human Intelligence Portfolio), Tribunal, and Testament Management information is protected by industry-standard cryptographic technologies during storage, transmission, and processing.
        </p>
      </InfoAccordionItem>

      <InfoAccordionItem id="e2" number="2" title="Encryption Framework Overview">
        <ol class="layer-stack">
          <li v-for="layer in layers" :key="layer.layer" class="layer-card">
            <span class="layer-icon" aria-hidden="true"><i :class="['bi', layer.icon]"></i></span>
            <div class="layer-body">
              <h3 class="layer-name">{{ layer.layer }}</h3>
              <p class="layer-purpose">{{ layer.purpose }}</p>
              <dl class="layer-meta">
                <div><dt>Technique</dt><dd>{{ layer.technique }}</dd></div>
                <div><dt>Standard / Algorithm</dt><dd>{{ layer.standard }}</dd></div>
              </dl>
            </div>
          </li>
        </ol>
      </InfoAccordionItem>

      <InfoAccordionItem id="e3" number="3" title="Key Management">
        <ul>
          <li>Keys generated and managed via HSM or secure key vault.</li>
          <li>Rotated every 12 months or after security events.</li>
          <li>Access restricted to DPO/admins under MFA.</li>
          <li>All operations audited and logged.</li>
        </ul>
      </InfoAccordionItem>

      <InfoAccordionItem id="e4" number="4" title="Data Segregation and Access Control">
        <ul>
          <li>User data segregated by account ID/module.</li>
          <li>RBAC ensures authorized access.</li>
          <li>Queries/admin access require encrypted API tokens.</li>
          <li>Staff trained and cleared before privileges.</li>
        </ul>
      </InfoAccordionItem>

      <InfoAccordionItem id="e5" number="5" title="Backup and Recovery Encryption">
        <ul>
          <li>Daily backups encrypted with AES-256.</li>
          <li>Transmission secured with TLS 1.3 + mutual auth.</li>
          <li>Disaster recovery servers follow same encryption standards.</li>
        </ul>
      </InfoAccordionItem>

      <InfoAccordionItem id="e6" number="6" title="End-to-End Confidentiality">
        <p>All sensitive data encrypted in transit and at rest. Decryption only occurs at runtime in secure memory; plaintext is never written to disk.</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="e7" number="7" title="Compliance and Auditing">
        <p>MyIntellibook follows:</p>
        <ul class="standard-badges">
          <li><i class="bi bi-patch-check-fill" aria-hidden="true"></i> ISO/IEC 27001 – Information Security Management</li>
          <li><i class="bi bi-patch-check-fill" aria-hidden="true"></i> ISO/IEC 27701 – Privacy Information Management</li>
          <li><i class="bi bi-patch-check-fill" aria-hidden="true"></i> GDPR Articles 32 &amp; 33</li>
          <li><i class="bi bi-patch-check-fill" aria-hidden="true"></i> Sri Lanka ICTA Data Protection Act (2022)</li>
        </ul>
        <p>Regular vulnerability assessments and annual penetration testing ensure compliance.</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="e8" number="8" title="Encryption Responsibility">
        <dl class="role-list">
          <div v-for="role in roles" :key="role.role">
            <dt><i class="bi bi-person-gear" aria-hidden="true"></i> {{ role.role }}</dt>
            <dd>{{ role.responsibility }}</dd>
          </div>
        </dl>
      </InfoAccordionItem>

      <InfoAccordionItem id="e9" number="9" title="Breach Response">
        <ol class="response-timeline">
          <li><span>Revoke compromised keys.</span></li>
          <li><span>Incident Response Team activated within 2 hours.</span></li>
          <li><span>Impact analysis + user notification within 72 hours.</span></li>
          <li><span>Independent audit review for corrective actions.</span></li>
        </ol>
      </InfoAccordionItem>

      <InfoAccordionItem id="e10" number="10" title="Summary">
        <p>MyIntellibook employs comprehensive, end-to-end encryption and secure key-management practices to ensure your personal, academic, and legal data remain protected under the highest international standards.</p>
      </InfoAccordionItem>
    </InfoAccordion>
  </InfoPageShell>
</template>

<script setup lang="ts">
import InfoPageShell from '@/components/infoPages/InfoPageShell.vue';
import InfoAccordion from '@/components/infoPages/InfoAccordion.vue';
import InfoAccordionItem from '@/components/infoPages/InfoAccordionItem.vue';

const layers = [
  {
    layer: 'Data Transmission',
    icon: 'bi-arrow-left-right',
    technique: 'TLS 1.3 with AES-256 bit encryption',
    standard: 'Transport Layer Security (TLS 1.3)',
    purpose: 'Encrypts communication between clients and servers to prevent interception.',
  },
  {
    layer: 'Data Storage (At Rest)',
    icon: 'bi-hdd-stack',
    technique: 'AES-256 encryption in GCM mode',
    standard: 'Advanced Encryption Standard (AES)',
    purpose: 'Encrypts stored data (HIP scores, profiles, testament files) so only authorized systems can decrypt.',
  },
  {
    layer: 'Password Protection',
    icon: 'bi-key',
    technique: 'PBKDF2 with SHA-512 + unique salt, >100,000 iterations',
    standard: 'FIPS 140-2 compliant',
    purpose: 'Secures passwords via one-way hashing to resist dictionary and rainbow-table attacks.',
  },
  {
    layer: 'API Key & Token Security',
    icon: 'bi-fingerprint',
    technique: 'HMAC-SHA256 signatures with JWT',
    standard: 'JSON Web Token (JWT) framework',
    purpose: 'Protects session authentication and integrations from tampering or replay attacks.',
  },
  {
    layer: 'Database Field-Level Encryption',
    icon: 'bi-table',
    technique: 'AES-256 per-field keys',
    standard: 'ISO/IEC 19790 & ISO/IEC 27018',
    purpose: 'Encrypts sensitive columns (national ID, contact number, legal documents).',
  },
];

const roles = [
  { role: 'Data Protection Officer (DPO)', responsibility: 'Oversees encryption policies, key management, incident reporting.' },
  { role: 'System Administrator', responsibility: 'Implements and maintains server-level encryption configurations.' },
  { role: 'Software Developers', responsibility: 'Ensure code adheres to encryption and sanitization guidelines.' },
  { role: 'Auditor / External Reviewer', responsibility: 'Verifies compliance with ISO and data-protection standards.' },
];
</script>

<style scoped>
.layer-stack { display: grid; gap: 8px; margin: 0 !important; padding: 0 !important; list-style: none; }
.layer-card { display: flex; gap: 12px; margin: 0 !important; padding: 14px; background: #fff; border: 1px solid var(--ds-surface-muted); border-radius: 14px; }
.layer-icon { display: grid; flex: 0 0 38px; width: 38px; height: 38px; color: var(--ds-success-text); font-size: 17px; place-items: center; background: var(--ds-success-soft); border-radius: 11px; }
.layer-body { min-width: 0; }
.layer-name { margin: 0; color: var(--ds-text); font-size: 14.5px; font-weight: 750; }
.layer-purpose { margin: 2px 0 8px !important; color: var(--ds-text-secondary); font-size: 13.5px; }
.layer-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 6px 14px; margin: 0; }
.layer-meta dt { color: var(--ds-text-subtle); font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.layer-meta dd { margin: 0; color: var(--ds-text); font-size: 12.5px; font-weight: 600; overflow-wrap: anywhere; }

.standard-badges { display: grid; gap: 6px; padding: 0 !important; list-style: none; }
.standard-badges li { display: flex; align-items: center; gap: 8px; margin: 0 !important; padding: 8px 12px; color: var(--ds-success-text); font-size: 13px; font-weight: 600; background: var(--ds-success-soft); border-radius: 10px; }

.role-list { display: grid; gap: 8px; margin: 0; }
.role-list > div { padding: 10px 12px; background: #fff; border: 1px solid var(--ds-surface-muted); border-radius: 12px; }
.role-list dt { color: var(--ds-text); font-size: 13.5px; font-weight: 700; }
.role-list dt i { color: var(--ds-primary); }
.role-list dd { margin: 2px 0 0; color: var(--ds-text-secondary); font-size: 13px; }

.response-timeline { position: relative; margin: 0 !important; padding: 0 0 0 26px !important; list-style: none; counter-reset: step; }
.response-timeline::before { position: absolute; top: 8px; bottom: 8px; left: 9px; width: 2px; content: ''; background: var(--ds-primary-200); }
.response-timeline li { position: relative; margin: 0 0 10px !important; counter-increment: step; }
.response-timeline li::before { position: absolute; top: 1px; left: -26px; display: grid; width: 20px; height: 20px; color: #fff; font-size: 11px; font-weight: 800; content: counter(step); place-items: center; background: var(--ds-primary); border-radius: 50%; }
</style>
