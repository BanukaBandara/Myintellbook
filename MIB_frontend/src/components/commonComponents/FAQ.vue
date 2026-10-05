<template>

      <Button  icon="pi pi-question" class="help-button" @click="toggleHelp" />
    <div v-if="showHelp" class="help-box">
        <div class="help-header">
            <span>FAQ</span>
            <button class="close-btn" @click="toggleHelp">&times;</button>
            </div>
            <div class="help-content">
            <div v-for="(faq, index) in faqs" :key="index" class="faq-item">
                <div class="faq-question" @click="toggleFaq(index)">
                {{ faq.question }}
                <span>{{ activeFaq === index ? '-' : '+' }}</span>
                </div>
                <div v-if="activeFaq === index" class="faq-answer">
                {{ faq.answer }}
                </div>
            </div>
            </div>
    </div>
</template>
<script setup lang="ts">
import { ref } from 'vue';
import Button from 'primevue/button';

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
.faq-item {
  border-bottom: 1px solid var(--ds-border-strong);
  padding: 8px 0;
}

.faq-question {
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  font-weight: bold;
  color: var(--ds-info);
}

.faq-answer {
  padding: 5px 0 5px 10px;
  color: var(--ds-text-secondary);
}

.help-button {
  position: fixed;
  bottom: 20px;
  right: 20px;
  width: 50px;
  height: 50px;
  border-radius: 50%;
  border: none;
  background-color: rgb(160, 56, 41);
  color: white;
  font-size: 24px;
  cursor: pointer;
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.2s;
}

.help-button:hover {
  transform: scale(1.1);
}

.help-box {
  position: fixed;
  bottom: 90px; /* above the button */
  right: 20px;
  width: 300px;
  max-height: 400px;
  background: white;
  border-radius: 10px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.3);
  z-index: 10000;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.help-header {
  background-color: rgb(160, 56, 41);
  color: white;
  padding: 10px;
  font-weight: bold;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.close-btn {
  background: transparent;
  border: none;
  color: white;
  font-size: 20px;
  cursor: pointer;
}

.help-content {
  padding: 10px;
  overflow-y: auto;
}
</style>