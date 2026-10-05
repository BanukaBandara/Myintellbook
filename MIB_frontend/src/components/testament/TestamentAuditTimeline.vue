<template>
  <section class="audit" aria-labelledby="audit-title">
    <header class="audit-head">
      <div>
        <h2 id="audit-title" class="audit-title">Audit trail &amp; consent log</h2>
        <p class="audit-sub">Read-only. Each entry is chained to the one before it, so any alteration is detectable.</p>
      </div>
    </header>

    <ul class="badges" aria-label="Verification">
      <li :class="testament.verification.encrypted_at_rest ? 'ok' : 'bad'">
        <i :class="['bi', testament.verification.encrypted_at_rest ? 'bi-shield-lock-fill' : 'bi-shield-exclamation']" aria-hidden="true"></i>
        {{ testament.verification.encrypted_at_rest ? 'Encrypted at rest · AES-256' : 'Decryption failed' }}
      </li>
      <li :class="testament.verification.content_hash_matches ? 'ok' : 'bad'">
        <i :class="['bi', testament.verification.content_hash_matches ? 'bi-fingerprint' : 'bi-exclamation-octagon']" aria-hidden="true"></i>
        {{ testament.verification.content_hash_matches ? 'Content hash verified' : 'Content hash mismatch' }}
      </li>
      <li :class="testament.verification.audit_chain_intact ? 'ok' : 'bad'">
        <i :class="['bi', testament.verification.audit_chain_intact ? 'bi-link-45deg' : 'bi-exclamation-octagon']" aria-hidden="true"></i>
        {{ testament.verification.audit_chain_intact ? 'Audit chain intact' : 'Audit chain broken' }}
      </li>
      <li :class="testament.tribunal_status === 'awaiting_review' ? 'pending' : 'idle'">
        <i class="bi bi-bank" aria-hidden="true"></i>
        Tribunal audit: {{ testament.tribunal_status === 'awaiting_review' ? 'Awaiting review' : 'Not yet submitted' }}
      </li>
    </ul>

    <ol class="timeline">
      <li v-for="entry in entriesNewestFirst" :key="entry.id" class="entry" :class="EVENTS[entry.event]?.tone ?? 'neutral'">
        <span class="entry-icon" aria-hidden="true"><i :class="['bi', EVENTS[entry.event]?.icon ?? 'bi-dot']"></i></span>
        <div class="entry-body">
          <div class="entry-top">
            <p class="entry-title">{{ EVENTS[entry.event]?.label ?? entry.event }}</p>
            <time class="entry-time" :datetime="entry.created_at">{{ formatTime(entry.created_at) }}</time>
          </div>
          <p class="entry-actor">by {{ entry.actor }}</p>
          <p v-if="describe(entry)" class="entry-detail">{{ describe(entry) }}</p>
          <p class="entry-hash" :title="entry.hash"><i class="bi bi-hash" aria-hidden="true"></i>{{ entry.hash.slice(0, 16) }}…</p>
        </div>
      </li>
    </ol>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { AuditEntry, Testament } from '@/services/testament';

const props = defineProps<{ testament: Testament }>();

const EVENTS: Record<string, { label: string; icon: string; tone: string }> = {
  draft_created: { label: 'Draft created', icon: 'bi-file-earmark-plus', tone: 'neutral' },
  draft_updated: { label: 'Draft updated', icon: 'bi-pencil', tone: 'neutral' },
  consent_given: { label: 'Consent recorded', icon: 'bi-hand-thumbs-up', tone: 'consent' },
  witness_requested: { label: 'Witness requested', icon: 'bi-person-plus', tone: 'neutral' },
  witness_confirmed: { label: 'Witness confirmed', icon: 'bi-person-check', tone: 'good' },
  witness_declined: { label: 'Witness declined', icon: 'bi-person-x', tone: 'warn' },
  submission_recalled: { label: 'Submission recalled', icon: 'bi-arrow-counterclockwise', tone: 'warn' },
  sealed: { label: 'Testament sealed', icon: 'bi-lock-fill', tone: 'good' },
  withdrawn: { label: 'Withdrawn', icon: 'bi-x-circle', tone: 'warn' },
  integrity_check_failed: { label: 'Integrity check failed', icon: 'bi-exclamation-octagon', tone: 'bad' },
};

const FIELD_LABELS: Record<string, string> = {
  title: 'title',
  instructions: 'instructions',
  health: 'health declaration',
  beneficiaries: 'beneficiaries',
  witness_user_id: 'witness',
};

const entriesNewestFirst = computed(() => [...props.testament.audit].reverse());

const formatTime = (iso: string) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));

function describe(entry: AuditEntry): string {
  const details = entry.details ?? {};
  if (Array.isArray(details.fields)) {
    const fields = (details.fields as string[]).map((field) => FIELD_LABELS[field] ?? field).join(', ');
    const count = typeof details.beneficiary_count === 'number' ? ` · ${details.beneficiary_count} beneficiaries` : '';
    return `Changed: ${fields}${count}`;
  }
  if (typeof details.statement === 'string') return `“${details.statement}”`;
  if (typeof details.content_hash === 'string') return `Sealed content hash ${details.content_hash.slice(0, 16)}…`;
  return '';
}
</script>

<style scoped>
.audit { padding: 20px; background: #fff; border: 1px solid var(--ds-border); border-radius: 18px; box-shadow: 0 1px 2px rgba(15, 23, 42, .05); }
.audit-title { margin: 0; color: var(--ds-text); font-size: 16px; font-weight: 800; }
.audit-sub { margin: 3px 0 0; color: var(--ds-text-muted); font-size: 12.5px; }

.badges { display: flex; flex-wrap: wrap; gap: 6px; margin: 14px 0 18px; padding: 0; list-style: none; }
.badges li { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; font-size: 12px; font-weight: 700; border-radius: 999px; }
.badges .ok { color: var(--ds-success-text); background: var(--ds-success-soft); border: 1px solid var(--ds-success-border); }
.badges .bad { color: var(--ds-danger-text); background: var(--ds-danger-soft); border: 1px solid var(--ds-danger-border); }
.badges .pending { color: var(--ds-warning-text); background: var(--ds-warning-soft); border: 1px solid var(--ds-warning-border); }
.badges .idle { color: var(--ds-text-secondary); background: var(--ds-surface-muted); border: 1px solid var(--ds-border); }

.timeline { position: relative; margin: 0; padding: 0; list-style: none; }
.timeline::before { position: absolute; top: 6px; bottom: 6px; left: 17px; width: 2px; content: ''; background: var(--ds-surface-muted); }
.entry { position: relative; display: flex; gap: 12px; padding-bottom: 16px; }
.entry:last-child { padding-bottom: 0; }

.entry-icon { position: relative; z-index: 1; display: grid; flex: 0 0 36px; width: 36px; height: 36px; color: var(--ds-text-muted); font-size: 15px; place-items: center; background: var(--ds-surface-muted); border: 3px solid #fff; border-radius: 50%; }
.consent .entry-icon { color: #1d4ed8; background: #dbeafe; }
.good .entry-icon { color: var(--ds-success-text); background: #d1fae5; }
.warn .entry-icon { color: var(--ds-warning-text); background: #fef3c7; }
.bad .entry-icon { color: var(--ds-primary-hover); background: #fee2e2; }

.entry-body { flex: 1; min-width: 0; padding-top: 6px; }
.entry-top { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 4px 10px; }
.entry-title { margin: 0; color: var(--ds-text); font-size: 13.5px; font-weight: 750; }
.entry-time { color: var(--ds-text-subtle); font-size: 12px; white-space: nowrap; }
.entry-actor { margin: 1px 0 0; color: var(--ds-text-muted); font-size: 12.5px; }
.entry-detail { margin: 4px 0 0; color: var(--ds-text-secondary); font-size: 12.5px; line-height: 1.5; }
.entry-hash { display: inline-flex; align-items: center; gap: 2px; margin: 6px 0 0; padding: 1px 8px; color: var(--ds-text-muted); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 11px; background: var(--ds-surface-subtle); border-radius: 6px; }

@media (max-width: 575px) {
  .audit { padding: 16px 14px; }
}
</style>
