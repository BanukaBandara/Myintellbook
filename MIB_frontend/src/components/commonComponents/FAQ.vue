<template>
    <Transition name="faq-pop">
        <div
            v-if="showHelp"
            id="faq-popover"
            class="help-box"
            role="dialog"
            aria-labelledby="faq-title"
        >
            <div class="help-header">
                <h2 id="faq-title">Frequently Asked Questions</h2>
                <button class="close-btn" type="button" aria-label="Close FAQ" @click="toggleHelp">
                    <X :size="18" aria-hidden="true" />
                </button>
            </div>
            <div class="help-content">
                <div v-for="(faq, index) in faqs" :key="index" class="faq-item">
                    <button
                        class="faq-question"
                        type="button"
                        :aria-expanded="activeFaq === index"
                        :aria-controls="`faq-answer-${index}`"
                        @click="toggleFaq(index)"
                    >
                        <span>{{ faq.question }}</span>
                        <ChevronDown
                            class="faq-icon"
                            :class="{ open: activeFaq === index }"
                            :size="16"
                            aria-hidden="true"
                        />
                    </button>
                    <div
                        :id="`faq-answer-${index}`"
                        class="faq-answer"
                        :class="{ open: activeFaq === index }"
                        role="region"
                        :aria-hidden="activeFaq !== index"
                    >
                        <div class="faq-answer-inner">
                            <p>{{ faq.answer }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Transition>

    <button
        class="help-button"
        type="button"
        :aria-label="showHelp ? 'Close FAQ' : 'Open FAQ'"
        :aria-expanded="showHelp"
        aria-controls="faq-popover"
        @click="toggleHelp"
    >
        <X v-if="showHelp" :size="22" aria-hidden="true" />
        <HelpCircle v-else :size="22" aria-hidden="true" />
    </button>
</template>
<script setup lang="ts">
import { ref } from 'vue';
import { ChevronDown, HelpCircle, X } from 'lucide-vue-next';

const showHelp = ref(false);
const activeFaq = ref<number | null>(null);

const faqs = ref([
  { question: 'How do I reset my password?', answer: 'Click on the top navigation avatar then navigate to settings from their click on login informations".' },
  { question: 'How do I edit my profile?', answer: 'Click on your avatar in the top bar, then choose "View Profile".' },
  { question: 'How can I contact support?', answer: 'You can email support@example.com or use the chat button.' },
  { question: 'What is learn?', answer: 'In learn you can See the exam groups created by the Users, You can Open that groups and check the questions' },
  { question: 'What is Exam?', answer: 'In  Exam you can See the exam groups created or saved By you, You can Open that groups and check the questions or you can start the exam' },
  { question: 'What is Score?', answer: 'Gain points when doing exam adding details and dooing daily quetion ' },
  { question: 'Who is Top Users?', answer: 'The users that got hights points on this system' },
  { question: 'What is Save Exams?', answer: 'The exams that you saved for later use' },
  { question: 'How do I create exam groups?', answer: 'Navigate to Create Exam from the side bar, fill in the details and submit.' },
  { question: 'How do I delete my account?', answer: 'Click on the top navigation avatar then navigate to settings from their click on Delete Account' }

]);

const toggleHelp = () => {
  showHelp.value = !showHelp.value;
}

const toggleFaq = (index: number) => {
  activeFaq.value = activeFaq.value === index ? null : index;
}
</script>
<style scoped>
/* Floating trigger */
.help-button {
  position: fixed;
  right: 20px;
  bottom: 20px;
  z-index: 10000;
  display: flex;
  width: 48px;
  height: 48px;
  align-items: center;
  justify-content: center;
  padding: 0;
  color: #fff;
  cursor: pointer;
  background: #e11d48; /* rose-600 */
  border: 0;
  border-radius: 50%;
  box-shadow: 0 10px 15px -3px rgba(15, 23, 42, .1), 0 4px 6px -4px rgba(15, 23, 42, .1);
  transition: background-color .2s ease, box-shadow .2s ease, transform .15s ease;
}

.help-button:hover {
  background: #be123c; /* rose-700 */
  box-shadow: 0 20px 25px -5px rgba(15, 23, 42, .12), 0 8px 10px -6px rgba(15, 23, 42, .12);
}

.help-button:active { transform: scale(.95); }
.help-button:focus-visible { outline: 2px solid #fda4af; outline-offset: 3px; }

/* Popover — sits 16px above the trigger */
.help-box {
  position: fixed;
  right: 20px;
  bottom: 84px;
  z-index: 10000;
  display: flex;
  width: 320px;
  max-width: calc(100vw - 32px);
  max-height: min(480px, calc(100vh - 120px));
  flex-direction: column;
  padding: 20px;
  background: rgba(255, 255, 255, .95);
  -webkit-backdrop-filter: blur(12px);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(226, 232, 240, .8); /* slate-200/80 */
  border-radius: 16px;
  box-shadow: 0 25px 50px -12px rgba(15, 23, 42, .25);
}

@media (min-width: 768px) {
  .help-box { width: 384px; }
}

.help-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-bottom: 8px;
}

.help-header h2 {
  margin: 0;
  color: #0f172a; /* slate-900 */
  font-family: inherit;
  font-size: 16px;
  font-weight: 600;
  line-height: 1.5;
}

.close-btn {
  display: flex;
  padding: 4px;
  color: #94a3b8; /* slate-400 */
  cursor: pointer;
  background: transparent;
  border: 0;
  border-radius: 8px;
  transition: color .15s ease, background-color .15s ease;
}

.close-btn:hover { color: #475569; background: #f1f5f9; }
.close-btn:focus-visible { outline: 2px solid #fda4af; outline-offset: 1px; }

.help-content {
  margin-right: -8px;
  padding-right: 8px;
  overflow-y: auto;
}

/* Accordion */
.faq-item { border-bottom: 1px solid #f1f5f9; /* slate-100 */ }
.faq-item:last-child { border-bottom: 0; }

.faq-question {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 0;
  color: #1e293b; /* slate-800 */
  font-family: inherit;
  font-size: 14px;
  font-weight: 500;
  line-height: 1.45;
  text-align: left;
  cursor: pointer;
  background: transparent;
  border: 0;
  transition: color .15s ease;
}

.faq-question:hover, .faq-question[aria-expanded="true"] { color: #e11d48; }
.faq-question:focus-visible { outline: 2px solid #fda4af; outline-offset: 2px; border-radius: 4px; }

.faq-icon {
  flex: 0 0 16px;
  color: #94a3b8;
  transition: transform .2s ease, color .15s ease;
}

.faq-question:hover .faq-icon { color: #e11d48; }
.faq-icon.open { transform: rotate(180deg); }

/* Grid-rows trick animates to the content's natural height. */
.faq-answer {
  display: grid;
  grid-template-rows: 0fr;
  opacity: 0;
  transition: grid-template-rows .22s ease, opacity .22s ease;
}

.faq-answer.open { grid-template-rows: 1fr; opacity: 1; }

.faq-answer-inner { min-height: 0; overflow: hidden; }

.faq-answer-inner > p {
  margin: 0;
  padding: 0 4px 12px;
  color: #64748b; /* slate-500 */
  font-size: 12px;
  line-height: 1.625;
}

/* Popover enter/leave: fade + slide up from the trigger */
.faq-pop-enter-active, .faq-pop-leave-active { transition: opacity .2s ease, transform .2s ease; }
.faq-pop-enter-from, .faq-pop-leave-to { opacity: 0; transform: translateY(8px); }

@media (max-width: 480px) {
  .help-box { right: 16px; }
  .help-button { right: 16px; }
}

@media (prefers-reduced-motion: reduce) {
  .help-button, .faq-icon, .faq-answer, .faq-pop-enter-active, .faq-pop-leave-active { transition: none; }
}
</style>
