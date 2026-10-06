<template>
  <div class="row flex-grow-1 overflow-auto m-0 justify-content-center mb-3 gap-1">
    <div class="col-md-1"></div>

    <div class="col-md-2 mt-3 d-none d-md-block">
      <ProfileDetails />
      <addSiteDeails />
    </div>

    <div class="col-md-4 mt-3">
      <section class="fixed-arm-container">
        <h1>About the Fixed Arm for Testament Management</h1>

        <p>
          The Fixed Arm for Testament Management (FATM) of MyIntellibook is a dedicated unit within MyIntellibook
          designed to ensure secure, transparent, and ethical handling of testamentary affairs. This system oversees
          the creation, preservation, and execution of wills and related commitments with the highest standards of
          integrity and accountability.
        </p>

        <h2>Responsibilities of Legal and Quality Groups</h2>
        <ul>
          <li>Ensuring proper health care considerations and ethical compliance for individuals during testament preparation.</li>
          <li>Overseeing the authentic and timely delivery of last wills and directives.</li>
          <li>Conducting independent audits of all testament management activities to ensure transparency, accuracy, and fairness.</li>
          <li>Remaining fully answerable to the MyIntellibook Group, upholding its principles of justice, compassion, and human dignity.</li>
        </ul>

        <h2>Operational Oversight</h2>
        <p>
          It operates through two dedicated teams — the Legal Group and the Quality Group — working under the oversight
          of the MyIntellibook Central Tribunal. Together, they ensure:
        </p>
        <ul>
          <li>Proper health and ethical verification of testators</li>
          <li>Legal and procedural accuracy of all testamentary records</li>
          <li>Encrypted storage within the MyIntellibook Testament Vault (MTV)</li>
          <li>Periodic audits for integrity and compliance</li>
          <li>Controlled, verified release of testaments upon activation</li>
        </ul>

        <h2>Ethical and Technical Safeguards</h2>
        <p>
          By combining digital intelligence with ethical governance, the Fixed Arm for Testament Management strengthens trust,
          protects legacies, and ensures that every individual’s final intentions are honored with respect and accountability.
          The FATM integrates digital security (AES-256 encryption, blockchain keys) with human oversight (tribunal review,
          audit trail, and consent logs) to guarantee fairness, accountability, and respect for human dignity.
        </p>

        <p>
          By combining technology, law, and compassion, the Fixed Arm for Testament Management protects personal legacies
          and upholds MyIntellibook’s commitment to justice, privacy, and ethical governance.
        </p>
      </section>
    </div>

    <div class="col-md-3 mt-3 d-none d-md-block">
      <latestUpdates />
      <Divider class="w-75"/>
      <!-- <myExams  @createExam="showExamCreate" :visible="examsCreateShow"/> -->
      <Divider class="w-75" />
      <!-- <categoriesShow /> -->
      <Divider class="w-75" />
      <ProfileList />
    </div>

    <div class="col-md-2"></div>
  </div>
</template>

<script setup>
import { defineAsyncComponent } from 'vue';
const addSiteDeails = defineAsyncComponent(() => import('../../components/commonComponents/addSiteDeails.vue'));
const latestUpdates = defineAsyncComponent(() => import('../../components/commonComponents/latestUpdates.vue'));
const userProfile = defineAsyncComponent(() => import('../../stores/User/userProfile'));
import Divider from 'primevue/divider';
</script>

<style scoped>
.fixed-arm-container {
  max-width: 850px;
  padding: 1.5rem 2rem;
  font-family: 'Segoe UI', sans-serif;
  background-color: #fdfdfd;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  line-height: 1.7;
  color: #333;
}

.fixed-arm-container h1 {
  font-size: 1.9rem;
  margin-bottom: 1rem;
  color: #2c3e50;
}

.fixed-arm-container h2 {
  font-size: 1.3rem;
  margin-top: 2rem;
  margin-bottom: 0.8rem;
  color: #A03829;
}

.fixed-arm-container p {
  margin-bottom: 1.2rem;
}

.fixed-arm-container ul {
  padding-left: 1.2rem;
  margin-bottom: 1.5rem;
}

.fixed-arm-container li {
  margin-bottom: 0.6rem;
  list-style-type: disc;
}
</style>
