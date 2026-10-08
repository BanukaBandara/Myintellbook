<template>
  <div class="row flex-grow-1 overflow-auto m-0 justify-content-center mb-3 gap-1">
      <div class="col-md-1"></div>
      <div class="col-md-2 mt-3 d-none d-md-block">
          <ProfileDetails />
          <addSiteDeails />
      </div>
      <div class="col-md-4 mt-3">
        <section class="about-container">
          <h1>About</h1>
          <p>
            MyIntellibook is an innovative learning and assessment platform designed to enhance
            critical thinking, ethical reasoning, and practical knowledge across multiple disciplines.
            It provides interactive quizzes, performance-based scoring, and intelligent feedback to help
            learners evaluate and improve their understanding.
          </p>
          <p>
            Beyond learning, MyIntellibook introduces the <strong>Human Intelligence Portfolio (HIP)</strong> —
            a dynamic system that groups individuals according to their <strong>Life Competency Index (LCI)</strong>.
            The LCI reflects a person’s achievements, experiences, and personal growth, creating a
            measurable profile of real-life intelligence and capability. In the long term, the LCI may also
            contribute to recognizing individuals’ value and influence within communities and decision-making
            frameworks — representing a thoughtful evolution toward competency-based contribution.
          </p>
          <p>
            The platform also features an <strong>Online Tribunal</strong>, a digital conflict-resolution system designed
            to help members address personal or professional issues through fair, positive, and time-efficient
            dialogue — eliminating the need for travel while ensuring transparency and real justice.
          </p>
          <p>
            In addition, MyIntellibook is developing a <strong>Fixed Arm for Testament Management</strong>, providing
            secure, intelligent, and transparent handling of testamentary records. This feature aims to
            simplify the process of managing wills, inheritances, and related commitments — ensuring
            fairness, trust, and accountability in the preservation and execution of personal legacies.
          </p>
          <p>
            <strong>Our mission</strong> is to make education and human development smarter, more equitable, and
            deeply connected to real-life values.
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
  font-size: 2rem;
  margin-bottom: 1rem;
  color: #2c3e50;
}

.about-container p {
  margin-bottom: 1rem;
  color: #333;
}

.about-container strong {
  color: #A03829;
}
</style>
