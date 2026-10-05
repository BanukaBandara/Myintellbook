<script setup lang="ts">
import { ref, watch, nextTick, computed } from 'vue';
import { useTribunalStore } from '@/stores/tribunal';

const props = defineProps<{
  show: boolean;
  caseId: number;
  caseNumber: string;
  caseTitle: string;
  otherPartyName?: string;
  otherPartyRole?: string;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const tribunalStore = useTribunalStore();
const messageBody = ref('');
const messagesContainer = ref<HTMLElement | null>(null);
const isLoading = ref(false);

const conversation = computed(() => tribunalStore.activeConversation);
const messages = computed(() => tribunalStore.conversationMessages);
const isSending = computed(() => tribunalStore.isSendingMessage);

const scrollToBottom = async () => {
  await nextTick();
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
  }
};

const loadConversation = async () => {
  if (!props.caseId) return;
  isLoading.value = true;
  try {
    await tribunalStore.fetchCaseConversation(props.caseId);
    await scrollToBottom();
  } finally {
    isLoading.value = false;
  }
};

watch(
  () => props.show,
  (newVal) => {
    if (newVal) {
      loadConversation();
    } else {
      messageBody.value = '';
    }
  }
);

const handleSendMessage = async () => {
  if (!messageBody.value.trim() || !conversation.value?.id || isSending.value) return;

  const text = messageBody.value.trim();
  messageBody.value = '';

  try {
    await tribunalStore.sendMessage(conversation.value.id, text);
    await scrollToBottom();
  } catch (err) {
    messageBody.value = text; // restore on error
  }
};

const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    handleSendMessage();
  }
};

const formatTime = (isoString: string) => {
  if (!isoString) return '';
  const d = new Date(isoString);
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true }) +
    ' · ' + d.toLocaleDateString([], { month: 'short', day: 'numeric' });
};
</script>

<template>
  <div v-if="show" class="modal-backdrop fade show"></div>
  <div
    v-if="show"
    class="modal fade show d-block"
    tabindex="-1"
    role="dialog"
    aria-modal="true"
  >
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content shadow-lg border-0">
        <!-- Header -->
        <div class="modal-header bg-dark text-white border-bottom border-secondary py-3">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-shield-lock-fill text-warning fs-4"></i>
            <div>
              <h5 class="modal-title mb-0 fs-6 fw-bold">
                Confidential Legal Consultation
              </h5>
              <div class="text-white-50 small">
                Case {{ caseNumber }} · {{ caseTitle }}
              </div>
            </div>
          </div>
          <button
            type="button"
            class="btn-close btn-close-white"
            aria-label="Close"
            @click="emit('close')"
          ></button>
        </div>

        <!-- Privilege Notice Banner -->
        <div class="bg-warning-subtle text-dark border-bottom px-3 py-2 small d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-octagon-fill text-warning fs-6"></i>
          <div>
            <strong>ATTORNEY-CLIENT PRIVILEGE:</strong> This private room is strictly confidential between client and authorized attorney. Opposing parties and adjudicators have no access.
          </div>
        </div>

        <!-- Body -->
        <div
          ref="messagesContainer"
          class="modal-body p-3 bg-light"
          style="min-height: 380px; max-height: 520px; overflow-y: auto;"
        >
          <!-- Loading State -->
          <div v-if="isLoading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading conversation...</span>
            </div>
            <div class="text-muted mt-2 small">Loading confidential channel...</div>
          </div>

          <!-- Empty State -->
          <div
            v-else-if="messages.length === 0"
            class="text-center py-5 text-muted"
          >
            <i class="bi bi-chat-dots fs-1 opacity-50 mb-2"></i>
            <h6>Confidential Thread Opened</h6>
            <p class="small mb-0">
              No messages have been exchanged yet. Start the conversation with your legal counsel below.
            </p>
          </div>

          <!-- Messages Stream -->
          <div v-else class="d-flex flex-column gap-3">
            <div
              v-for="msg in messages"
              :key="msg.id"
              class="d-flex flex-column"
              :class="msg.is_mine ? 'align-items-end' : 'align-items-start'"
            >
              <div class="small text-muted mb-1 px-1">
                <span class="fw-semibold">{{ msg.sender_name }}</span>
                <span class="ms-1 opacity-75">· {{ formatTime(msg.created_at) }}</span>
              </div>
              <div
                class="p-3 rounded-4 shadow-sm"
                :class="msg.is_mine 
                  ? 'bg-primary text-white text-end rounded-bottom-end-0' 
                  : 'bg-white text-dark border text-start rounded-bottom-start-0'"
                style="max-width: 80%; white-space: pre-wrap; word-break: break-word;"
              >
                {{ msg.body }}
              </div>
            </div>
          </div>
        </div>

        <!-- Footer / Input Box -->
        <div class="modal-footer bg-white border-top p-3">
          <form class="w-100" @submit.prevent="handleSendMessage">
            <div class="input-group">
              <textarea
                v-model="messageBody"
                class="form-control"
                rows="2"
                placeholder="Type a confidential message... (Enter to send, Shift+Enter for newline)"
                :disabled="isSending || !conversation?.active"
                @keydown="handleKeyDown"
              ></textarea>
              <button
                type="submit"
                class="btn btn-primary d-flex align-items-center gap-1 px-3"
                :disabled="!messageBody.trim() || isSending || !conversation?.active"
              >
                <span
                  v-if="isSending"
                  class="spinner-border spinner-border-sm"
                  role="status"
                ></span>
                <i v-else class="bi bi-send-fill"></i>
                <span>Send</span>
              </button>
            </div>
            <div v-if="!conversation?.active" class="text-danger small mt-1">
              <i class="bi bi-slash-circle me-1"></i>
              This legal representation has ended. Further messages cannot be sent.
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  z-index: 1050;
  background-color: rgba(0, 0, 0, 0.5);
}
.modal {
  z-index: 1055;
}
</style>
