<template>
  <div class="row flex-grow-1 overflow-auto m-0 justify-content-center mb-4">
    <!-- Left Spacer -->
    <div class="col-md-2"></div>

    <!-- Main Content -->
    <div class="col-md-8 mt-3">
      <Card class="shadow-sm border-0 rounded-4">
        <!-- Header -->
        <template #title>
          <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
            <h5 class="fw-bold text-primary mb-0">🏛️ Complaints</h5>
          </div>
        </template>

        <!-- Tabs -->
        <template #content>
          <Tabs v-model:value="tabValue" class="w-100 mt-2">
            <TabList>
              <Tab value="0">
                <span class="fw-semibold text-dark">Internal</span>
              </Tab>
              <Tab value="1">
                <span class="fw-semibold text-dark">External</span>
              </Tab>
            </TabList>

            <TabPanels>
              <!-- INTERNAL TAB (UPGRADED) -->
              <TabPanel value="0">
                <div class="p-3">

                  <!-- Internal Inputs -->
                  <div class="mb-3 row gap-2">
                    <Select 
                      v-model="user" 
                      :options="users" 
                      optionLabel="label" 
                      placeholder="Defendant"
                      class="col-md-4 md:w-56 mb-2" 
                    />

                    <Select 
                      v-model="category" 
                      :options="profileCriteria" 
                      optionLabel="label"
                      placeholder="Select a complaint category"
                      class="col-md-5 md:w-56 mb-2" 
                    />
                    <Select 
                      v-model="jury" 
                      :options="users" 
                      optionLabel="label" 
                      placeholder="Select a jury member"
                      class="col-md-4 md:w-56 mb-2" 
                    />

                    <div class="col-md-2">
                      <Button
                        label="Submit"
                        icon="pi pi-send"
                        class="p-button p-button-outlined p-button-primary"
                        @click="submitComplain(1)"
                      />
                    </div>
                  </div>

                  <!-- FILTERS (ONLY INTERNAL) -->
                 <ComplainShows 
                    :ComplainsArray="ComplainsArray" 
                    :activeStatus="activeStatus" 
                    @statusChange="getStatusComplains"
                 />

                </div>
              </TabPanel>

              <!-- EXTERNAL TAB -->
              <TabPanel value="1">
                <div class="p-4 text-center">
                  <div class="mb-3">
                    <span class="fs-1">⚖️</span>
                    <h4 class="fw-bold text-dark mt-2 mb-1">External Tribunal</h4>
                    <p class="text-muted mx-auto" style="max-width: 540px;">
                      The External Tribunal handles formal community and platform disputes with structured respondent workflows and case tracking.
                    </p>
                  </div>

                  <div class="row g-3 justify-content-center mt-2">
                    <div class="col-md-5">
                      <div class="card h-100 p-4 border rounded-4 shadow-sm text-center">
                        <i class="bi bi-folder2-open fs-2 text-primary mb-2" />
                        <h5 class="fw-bold">My Cases</h5>
                        <p class="text-muted small mb-4">
                          View cases you submitted and cases filed involving your account.
                        </p>
                        <Button
                          label="View My Cases"
                          icon="pi pi-list"
                          class="p-button p-button-primary mt-auto"
                          @click="router.push('/tribunal/cases')"
                        />
                      </div>
                    </div>

                    <div class="col-md-5">
                      <div class="card h-100 p-4 border rounded-4 shadow-sm text-center">
                        <i class="bi bi-plus-circle fs-2 text-success mb-2" />
                        <h5 class="fw-bold">Submit New Case</h5>
                        <p class="text-muted small mb-4">
                          Submit an external case by selecting a respondent and providing details.
                        </p>
                        <Button
                          label="File a Case"
                          icon="pi pi-send"
                          class="p-button p-button-success mt-auto"
                          @click="router.push('/tribunal/create')"
                        />
                      </div>
                    </div>

                    <template v-if="tribunalStore.capabilities?.adjudicator?.eligible">
                      <div class="col-12 mt-4 text-start">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-0">
                          <i class="bi bi-bank text-primary me-1"></i>
                          Tribunal Adjudicator Portal
                        </h6>
                      </div>

                      <div class="col-md-5">
                        <div class="card h-100 p-4 border rounded-4 shadow-sm text-center">
                          <i class="bi bi-bank fs-2 text-primary mb-2" />
                          <h5 class="fw-bold">Adjudicator Assignments</h5>
                          <p class="text-muted small mb-4">
                            Review case appointments, declare conflicts of interest, and deliberate on assigned dispute cases.
                          </p>
                          <Button
                            :label="`Assignments (${tribunalStore.capabilities?.adjudicator?.pending_assignments || 0})`"
                            icon="pi pi-building-columns"
                            class="p-button p-button-primary mt-auto"
                            @click="router.push('/tribunal/jury')"
                          />
                        </div>
                      </div>
                    </template>

                    <template v-if="tribunalStore.capabilities?.representative?.eligible">
                      <div class="col-12 mt-4 text-start">
                        <h6 class="fw-bold text-dark border-bottom pb-2 mb-0">
                          <i class="bi bi-briefcase-fill text-primary me-1"></i>
                          Legal Representative Portal
                        </h6>
                      </div>

                      <div class="col-md-5">
                        <div class="card h-100 p-4 border rounded-4 shadow-sm text-center">
                          <i class="bi bi-inbox fs-2 text-warning mb-2" />
                          <h5 class="fw-bold">Representation Requests</h5>
                          <p class="text-muted small mb-4">
                            Review and respond to client requests for External Tribunal counsel.
                          </p>
                          <Button
                            :label="`Inbox (${tribunalStore.capabilities?.representative?.pending_requests || 0})`"
                            icon="pi pi-inbox"
                            class="p-button p-button-warning mt-auto"
                            @click="router.push('/tribunal/representation-requests')"
                          />
                        </div>
                      </div>

                      <div class="col-md-5">
                        <div class="card h-100 p-4 border rounded-4 shadow-sm text-center">
                          <i class="bi bi-shield-shaded fs-2 text-info mb-2" />
                          <h5 class="fw-bold">Represented Cases</h5>
                          <p class="text-muted small mb-4">
                            Active disputes where you are retained as legal counsel.
                          </p>
                          <Button
                            label="Practice Dashboard"
                            icon="pi pi-briefcase"
                            class="p-button p-button-info mt-auto"
                            @click="router.push('/tribunal/represented-cases')"
                          />
                        </div>
                      </div>
                    </template>
                  </div>
                </div>
              </TabPanel>

            </TabPanels>
          </Tabs>
        </template>
      </Card>
    </div>

    <!-- Right Spacer -->
    <div class="col-md-2"></div>
  </div>
</template>


<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import Textarea from 'primevue/textarea';
import IftaLabel from 'primevue/iftalabel';
import ProfileDetails from '@/components/HomePage/ProfileDetails.vue';
import Card from 'primevue/card';
import Button from 'primevue/button';
import { useRouter } from 'vue-router';
import showAlert from '@/composables/showAlert';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';
import { useRoute} from 'vue-router';
import { useUserProfile } from '@/stores/User/userProfile';
import { useTribunalStore } from '@/stores/tribunal';
import ComplainShows from './ComplainShows.vue';
import Select from 'primevue/select';

export interface ComplainType{
    user:string,
    complain:string,
    complain_from:string,
    status:string, // Changed from optional to required
    date:string    // Changed from optional to required
    jury:Array<{
        name:string,
        profile_image:string,
        profile_url:string
    }>;
}

const route = useRoute();
const tribunalStore = useTribunalStore();
const jury  = ref();
const userProfile = useUserProfile();
const activeStatus = ref<string>('submitted');
const user = ref<{label:string, id:number}>({
  label:'',
  id:0
});
const Complaine = ref<string>('')
const router = useRouter();

const openExternalTribunal = () => {
  router.push('/tribunal/create');
};
const ComplainsArray = ref<Array<ComplainType>>([]);
const ExternalComplainsArray = ref<Array<ComplainType>>([]);
const category = ref();
const users = ref<Array<{label:string, id:number}>>([
    { label: '', id: 0 },
]);
const tabValue = ref<string>('0');
const profileCriteria = [
  // Profile Verification
  { id: 1, category: "Profile Verification", label: "Photo Verification" },

  // Education & Career
  { id: 3, category: "Education & Career", label: "PhD / Doctorate" },
  { id: 4, category: "Education & Career", label: "Founder (20+ Years Company)" },
  { id: 5, category: "Education & Career", label: "Managerial / Leadership Role" },
  { id: 6, category: "Education & Career", label: "Published Book / Author" },

  // Legal Standing
  { id: 7, category: "Legal Standing", label: "Solved Legal Case (Plaintiff)" },
  { id: 8, category: "Legal Standing", label: "Legal Case (Defendant, cleared honorably)" },
  { id: 9, category: "Legal Standing", label: "Legal Case (Defendant, convicted)" },
  { id: 10, category: "Legal Standing", label: "Divorce (mutual)" },
  { id: 11, category: "Legal Standing", label: "Divorce (with misconduct proven)" },
  { id: 12, category: "Legal Standing", label: "Stable Family / No Legal Disputes" },

  // Social & Ethical
  { id: 13, category: "Social & Ethical", label: "Volunteer Work (per year)" },
  { id: 14, category: "Social & Ethical", label: "NGO / Charity Founder" },
  { id: 15, category: "Social & Ethical", label: "Political or Religious Extremism" },
  { id: 16, category: "Social & Ethical", label: "Ethical Breach / Bribery Allegation" },

  // Academic & Mentorship
  { id: 17, category: "Academic & Mentorship", label: "University Lecturer / Trainer" },
  { id: 18, category: "Academic & Mentorship", label: "Mentorship / Student Guidance" },

  // Professional Contributions
  { id: 19, category: "Professional Contributions", label: "Global Project Management" },
  { id: 20, category: "Professional Contributions", label: "Public Speaking / Presenter" },
  { id: 21, category: "Professional Contributions", label: "Media Column Writer / Appearance" },
  { id: 22, category: "Professional Contributions", label: "Disciplinary Action in Employment" },
  { id: 23, category: "Professional Contributions", label: "Ethical / Sustainable Projects" },
];

const submitComplain = async(from:number) =>{


    let submitComplain:{
          from:number,
          defendent:number,
          category:string,
          jury:number
        } 
          = {
        from: from,
        defendent:user.value.id,
        category: category.value ? category.value.label : Complaine.value,
        jury: jury.value.id
    }
  
  
  // let userId = (userData) ?? JSON.parse(userData);

    let result = await userProfile.submitComplains(submitComplain);

    if(result.code == 200)
    {
        getComplains(1);
    }
}

const getComplains = async(status:number) =>
{
    let result = await userProfile.getComplains(status);

     if(result.code == 200)
   {
       ComplainsArray.value =  result.data.complains;
       ExternalComplainsArray.value =  result.data.external_complains;

   }
}

const getUsersList = async() =>
{
    let result = await userProfile.getUserProfiles();

     if(result.code == 200)
   {
       users.value =  result.data.map((user:any)=>{
           return {
               label:user.full_name,
               id:user.id
           }
       });
       console.log(users.value)
   }
} 

const getStatusComplains = async(status:string) =>
{
    activeStatus.value = status;

    let statusCode = 1; // Default to 'submitted'

    if(status === 'inprogress')
    {
        statusCode = 2;
    }
    else if(status === 'solved')
    {
        statusCode = 3;
    }

    await getComplains(statusCode);
}

const checkLink = async()=>{
  const param = route.params.slug;

  if(param == 'external')
  {
    tabValue.value = '1';
  }
}

onMounted(async()=>{
    await getComplains(1)
    await getUsersList()
    await checkLink()
    await tribunalStore.fetchCapabilities()
})
</script>
<style scoped>
.card {
  background-color: #ffffff;
  border-radius: 1rem;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
}

.list-group-item {
  background-color: #f8f9fa;
  border: none;
}

.p-tabview-nav li a {
  padding: 0.75rem 1.25rem;
  font-weight: 500;
}

.p-tabview-nav li.p-highlight a {
  background-color: #eef3ff !important;
  color: #0d6efd !important;
  border-radius: 0.75rem;
}

@media (max-width: 768px) {
  .col-md-7 {
    width: 100%;
  }
}
.complain-item {
  transition: all 0.2s ease;
}

.complain-item:hover {
  background-color: #fdfdfd;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
  transform: translateY(-2px);
}
</style>