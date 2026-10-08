<template>
  <section v-if="requests.length || message" class="witness" aria-labelledby="witness-title">
    <h2 id="witness-title" class="witness-title">
      <i class="bi bi-person-check" aria-hidden="true"></i> Witness requests
      <span v-if="requests.length" class="count">{{ requests.length }}</span>
    </h2>
    <p class="witness-sub">You were asked to witness these testaments. You confirm the declaration, not the contents — which stay private.</p>

    <p v-if="message" class="alert" :class="messageTone" role="status">{{ message }}</p>

    <ul class="request-list">
      <li v-for="request in requests" :key="request.id" class="request">
        <div class="request-who">
          <p class="request-name">{{ request.testator }}</p>
          <p v-if="request.requested_at" class="request-date">Requested {{ formatDate(request.requested_at) }}</p>
        </div>
        <label class="attest">
          <input v-model="attested[request.id]" type="checkbox">
          <span>{{ statement }}</span>
        </label>
        <div class="request-actions">
          <button type="button" class="ghost" :disabled="busyId === request.id" @click="respond(request.id, 'decline')">Decline</button>
          <button type="button" class="primary" :disabled="!attested[request.id] || busyId === request.id" @click="respond(request.id, 'confirm')">
            <i class="bi bi-pen" aria-hidden="true"></i> Confirm as witness
          </button>
        </div>
      </li>
    </ul>
  </section>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { errorMessage, testamentApi, type WitnessRequest } from '@/services/testament';

const emit = defineEmits<{ (event: 'changed'): void }>();

const requests = ref<WitnessRequest[]>([]);
const statement = ref('');
const attested = reactive<Record<number, boolean>>({});
const busyId = ref<number | null>(null);
const message = ref('');
const messageTone = ref<'success' | 'error'>('success');

const formatDate = (iso: string) => new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(iso));

async function load(): Promise<void> {
  try {
    const data = await testamentApi.witnessRequests();
    requests.value = data.requests;
    statement.value = data.attestation_statement;
  } catch {
    requests.value = [];
  }
}

async function respond(id: number, decision: 'confirm' | 'decline'): Promise<void> {
  busyId.value = id;
  message.value = '';
  try {
    await testamentApi.respond(id, decision);
    messageTone.value = 'success';
    message.value = decision === 'confirm' ? 'Thank you — the testament is now sealed.' : 'You declined the request.';
    requests.value = requests.value.filter((request) => request.id !== id);
    emit('changed');
  } catch (e) {
    messageTone.value = 'error';
    message.value = errorMessage(e, 'Your response could not be recorded.');
    await load();
  } finally {
    busyId.value = null;
  }
}

defineExpose({ load });
onMounted(load);
</script>

<style scoped>
.witness { margin-bottom: 14px; padding: 18px; background: linear-gradient(160deg, var(--ds-info-soft), #fff 55%); border: 1px solid var(--ds-info-border); border-radius: 18px; }
.witness-title { display: flex; align-items: center; gap: 8px; margin: 0; color: #1e3a8a; font-size: 15px; font-weight: 800; }
.count { padding: 0 8px; color: #fff; font-size: 11px; background: var(--ds-info); border-radius: 999px; }
.witness-sub { margin: 4px 0 12px; color: var(--ds-text-secondary); font-size: 13px; }

.request-list { display: grid; gap: 10px; margin: 0; padding: 0; list-style: none; }
.request { display: grid; gap: 10px; padding: 14px; background: #fff; border: 1px solid #dbeafe; border-radius: 14px; }
.request-name { margin: 0; color: var(--ds-text); font-size: 14.5px; font-weight: 750; }
.request-date { margin: 1px 0 0; color: var(--ds-text-muted); font-size: 12px; }
.attest { display: flex; gap: 10px; color: var(--ds-text-secondary); font-size: 13px; line-height: 1.5; cursor: pointer; }
.attest input { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--ds-info); }
.request-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }

.primary, .ghost { display: inline-flex; align-items: center; gap: 6px; min-height: 40px; padding: 8px 14px; font-size: 13px; font-weight: 700; border-radius: 11px; }
.primary { color: #fff; background: var(--ds-info); border: 0; }
.ghost { color: var(--ds-text-secondary); background: #fff; border: 1px solid var(--ds-border); }
.primary:disabled, .ghost:disabled { cursor: not-allowed; opacity: .45; }
.primary:focus-visible, .ghost:focus-visible, .attest:focus-within { outline: 2px solid var(--ds-info); outline-offset: 2px; }

.alert { margin: 0 0 10px; padding: 9px 12px; font-size: 13px; border-radius: 10px; }
.alert.success { color: var(--ds-success-text); background: var(--ds-success-soft); }
.alert.error { color: var(--ds-danger-text); background: var(--ds-danger-soft); }
</style>
