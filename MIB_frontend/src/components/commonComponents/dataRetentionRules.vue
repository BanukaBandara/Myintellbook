<template>
  <InfoPageShell
    icon="bi-database"
    eyebrow="Data governance"
    title="Data Retention Rules"
    subtitle="How long MyIntellibook keeps each category of data, and how it is deleted."
    :badges="['Minimum retention', 'Secure disposal', 'Deletion on request']"
    printable
  >
    <InfoAccordion label="Data retention sections" :default-open="['r3']">
      <InfoAccordionItem id="r1" number="1" title="Purpose of Retention Rules">
        <p>These retention rules describe how long MyIntellibook keeps different categories of data, in alignment with the platform’s Privacy Policy. Data is retained only for as long as needed for operational, legal, assessment, or compliance purposes.</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="r2" number="2" title="General Retention Principle">
        <p>MyIntellibook retains personal, behavioural, learning, and assessment data only for the minimum required period. Data older than the defined retention duration is permanently deleted unless required for legal or compliance reasons.</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="r3" number="3" title="Retention Periods by Data Category">
        <ul class="retention-grid">
          <li v-for="item in categories" :key="item.title" class="retention-card">
            <div class="retention-head">
              <span class="retention-icon" aria-hidden="true"><i :class="['bi', item.icon]"></i></span>
              <div>
                <h3 class="retention-title">{{ item.title }}</h3>
                <p v-if="item.scope" class="retention-scope">{{ item.scope }}</p>
              </div>
              <span class="period-chip">{{ item.period }}</span>
            </div>
            <ul class="retention-rules">
              <li v-for="rule in item.rules" :key="rule">{{ rule }}</li>
            </ul>
          </li>
        </ul>
      </InfoAccordionItem>

      <InfoAccordionItem id="r4" number="4" title="Inactive Accounts">
        <p>Accounts with no login or activity for 5 years may be permanently deleted. Users will receive a notification before deletion.</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="r5" number="5" title="Manual Deletion Upon Request">
        <p>Users may request deletion of their data at any time. Identity verification is required. Deletion will occur within 30–90 days depending on the category of data.</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="r6" number="6" title="Legal or Compliance Exceptions">
        <p>Some data may be retained longer if required by:</p>
        <ul>
          <li>Law enforcement requests</li>
          <li>Court orders</li>
          <li>Regulatory obligations</li>
          <li>Ongoing investigations</li>
        </ul>
      </InfoAccordionItem>

      <InfoAccordionItem id="r7" number="7" title="Secure Disposal">
        <p>All deletions (automatic or manual) are performed through secure data destruction methods ensuring no recovery of personal or assessment data.</p>
      </InfoAccordionItem>

      <InfoAccordionItem id="r8" number="8" title="Updates to Retention Rules">
        <p>MyIntellibook may revise these retention rules periodically to reflect internal process changes, legal requirements, or system improvements.</p>
      </InfoAccordionItem>
    </InfoAccordion>
  </InfoPageShell>
</template>

<script setup lang="ts">
import InfoPageShell from '@/components/infoPages/InfoPageShell.vue';
import InfoAccordion from '@/components/infoPages/InfoAccordion.vue';
import InfoAccordionItem from '@/components/infoPages/InfoAccordionItem.vue';

// Text from the retention policy; `period` is a short label summarising the rules below it.
const categories = [
  {
    title: 'a. Personal Profile Data',
    scope: 'Name, contact details, registration info',
    icon: 'bi-person-vcard',
    period: 'While active',
    rules: [
      'Retained for as long as the user maintains an active account.',
      'Deleted within 12 months after account deletion request.',
    ],
  },
  {
    title: 'b. Learning & Assessment Data',
    scope: 'HIP, LCI, daily questions, exam results',
    icon: 'bi-mortarboard',
    period: '5 years',
    rules: [
      'Retained for 5 years from the last date of activity.',
      'Automatically purged after 5 years of inactivity.',
    ],
  },
  {
    title: 'c. Tribunal Records',
    scope: '',
    icon: 'bi-bank',
    period: '5 years',
    rules: [
      'Retained for 5 years for audit and behavioural integrity purposes.',
      'Deleted after 5 years unless linked to an ongoing investigation.',
    ],
  },
  {
    title: 'd. Testament & Legal Data',
    scope: 'if activated',
    icon: 'bi-journal-bookmark',
    period: 'Until executed',
    rules: [
      'Retained until the testament is executed or user withdraws the feature.',
      'After execution or withdrawal, data is deleted within 180 days.',
    ],
  },
  {
    title: 'e. Technical System Logs',
    scope: '',
    icon: 'bi-terminal',
    period: '12 months',
    rules: [
      'Retained for 12 months for performance, security, and troubleshooting.',
      'Automatically deleted afterward.',
    ],
  },
];
</script>

<style scoped>
.retention-grid { display: grid; gap: 8px; margin: 0 !important; padding: 0 !important; list-style: none; }
.retention-card { margin: 0 !important; padding: 14px; background: #fff; border: 1px solid var(--ds-surface-muted); border-radius: 14px; }
.retention-head { display: flex; align-items: flex-start; gap: 10px; }
.retention-head > div { flex: 1; min-width: 0; }
.retention-icon { display: grid; flex: 0 0 36px; width: 36px; height: 36px; color: var(--ds-primary); font-size: 16px; place-items: center; background: var(--ds-primary-soft); border-radius: 10px; }
.retention-title { margin: 0; color: var(--ds-text); font-size: 14px; font-weight: 750; }
.retention-scope { margin: 1px 0 0 !important; color: var(--ds-text-muted); font-size: 12px; }
.period-chip { flex: 0 0 auto; padding: 4px 10px; color: #1e40af; font-size: 11.5px; font-weight: 800; white-space: nowrap; background: var(--ds-info-soft); border: 1px solid var(--ds-info-border); border-radius: 999px; }
.retention-rules { margin: 10px 0 0 46px !important; padding: 0 0 0 14px !important; font-size: 13px; }
.retention-rules li { margin-bottom: 3px !important; }

@media (max-width: 575px) {
  .retention-head { flex-wrap: wrap; }
  .period-chip { margin-left: 46px; }
  .retention-rules { margin-left: 0 !important; }
}
</style>
