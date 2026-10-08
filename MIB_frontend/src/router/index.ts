import { createRouter, createWebHistory } from 'vue-router'
import Register from '../views/User/Register.vue';
import EmailConfirmation from '../views/User/EmailConfirmation.vue';
import Login from '../views/User/Login.vue';
import { fetchAuthUser, isProfileCompleted, isJuryPanelUser } from '../services/auth';
import Swal from 'sweetalert2';
import {useLoadingStore} from '@/stores/loadingStore';
import CreateTribunalCase from '@/views/tribunal/CreateTribunalCase.vue';
import TribunalCases from '@/views/tribunal/TribunalCases.vue';
import TribunalCaseDetails from '@/views/tribunal/TribunalCaseDetails.vue';
import TribunalJuryCases from '@/views/tribunal/TribunalJuryCases.vue';
import ProfessionalVerification from '@/views/professional/ProfessionalVerification.vue';
import AdminProfessionalVerifications from '@/views/admin/AdminProfessionalVerifications.vue';
import TribunalRepresentationRequests from '@/views/tribunal/TribunalRepresentationRequests.vue';
import TribunalRepresentedCases from '@/views/tribunal/TribunalRepresentedCases.vue';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'frontPage',
      component: () => import('../views/FrontPage.vue'),
      meta: {
        hideNavBar: true,
        title:'Welcome'
      }
    },
    {
      path: '/register',
      name: 'register',
      component: Register,
      meta:{
        hideNavBar:true,
        title:'Register'
      }
    },
    {
      path: '/EmailConfirmation',
      name: 'EmailConfirmation',
      component: EmailConfirmation,
      meta:{
        hideNavBar:true,
        title:'EmailConfirmation'
      }
    },
    {
      path:'/emailVerified',
      name:'emailVerified',
      component: () => import('../views/User/EmailVerified.vue'),
      meta:{
        hideNavBar:true
      }
    },
    {
      path: '/login',
      name: 'login',
      component: Login,
      meta:{
        hideNavBar:true,
        title:'Login'
      }
    },
    {
      path: '/password/reset',
      name: 'password.reset',
      component: () => import('../views/User/PasswordReset.vue'),
      meta:{
        hideNavBar:true
      }
    },
    {
      path: '/password/reset/:token',
      name: 'password.reset.token',
      component: () => import('../views/User/ChangePassword.vue'),
      meta:{
        hideNavBar:true
      }
    },
    {
      path:'/basicDetails-fill',
      name:'basicDetails-fill',
      component: () => import('../views/User/BasicDetailsForm.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:true,
      }
    },
    {
      path: '/home',
      name: 'home',
      component: () => import('../views/User/Home.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title: 'Home'
      }
    },
    {
      path:'/profiles',
      name:'profiles',
      component: () => import('../views/User/ProfilesList.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'ProfileList'
      }
    },
    {
      path:'/profileEdit',
      name:'profileEdit',
      component: () => import('../views/User/ProfileEditPage.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'Edit Profile'
      },
       children: [
        {
          path:'/profileEdit/generalInfo',
          name:'generalInfo',
          component: () => import('../components/ProfilePage/GeneralInfo.vue'),
          meta: { title:'General Information' },
        },
        {
          path:'/profileEdit/workExperience/:id?',
          name:'workExperience',
          component: () => import('../components/ProfilePage/WorkExperience.vue'),
          meta: { title:'Work Experience' },
        },
        {
          path:'/profileEdit/educationInfo/:id?',
          name:'education',
          component: () => import('../components/ProfilePage/EducationInfo.vue'),
          meta: { title:'Education Information' },
        },
        {
          path:'/profileEdit/skillsInfo/:slug?',
          name:'skillsInfo',
          component: () => import('../components/ProfilePage/SkillsInfo.vue'),
          meta: { title:'Skills Information' },
        },
        {
          path:'/profileEdit/addProfileImage',
          name:'addProfileImage',
          component: () => import('../components/ProfilePage/profilePohoto.vue'),
          meta: { title:'Profile Photo' },
        },
        {
          path:'/profileEdit/addCoverImage',
          name:'addCoverImage',
          component: () => import('../components/ProfilePage/coverPhoto.vue'),
          meta: { title:'Cover Photo' },
        }
    ]
    },
    {
      path:'/profile',
      name:'profile',
      component: () => import('../views/User/ProfilePage.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'Profile'
      }
    },
    {
      path:'/accountSettings',
      name:'accountSettings',
      component: () => import('../views/User/AccountSettings.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'AccountSettings'
      },
      children:[
        {
          path:'/accountSettings/loginInformations',
          name:'loginInformations',
          component: () => import('../components/AccountSettings/LoginInformations.vue'),
        },
        {
           path:'/accountSettings/deleteAccount',
          name:'deleteAccount',
          component: () => import('../components/AccountSettings/DeleteAccount.vue'),
        },
        {
          path:'/accountSettings/privacyInformations',
          name:'privacyInformations',
          component: () => import('../components/AccountSettings/PrivacyInfo.vue'),
        },
        {
          path:'/accountSettings/blockUsers',
          name:'blockUsers',
          component: () => import('../components/AccountSettings/BlockProfile.vue'),
        }
      ]
    },
    {
      path:'/scores',
      name:'scores',
      component: () => import('../components/HomePage/Score.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'Scores'
      }
    },
    {
      path:'/allUpdates',
      name:'allUpdates',
      component: () => import('../components/commonComponents/allUpdates.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'AllUpdates'
      }
    },
    {
      path:'/topUsers',
      name:'topUsers',
      component: () => import('../components/commonComponents/topUsers.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'TopUsers'
      }
    },
    {
      path:'/allExperiances/:slug?',
      name:'allExperiances',
      component: () => import('../components/ProfilePage/allExperiance.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'All Experiences'
      }
    },
    {
      path:'/showAllEducation/:slug?',
      name:'showAllEducation',
      component: () => import('../components/ProfilePage/allEducation.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'AllEducations'
      }
    },
    {

      path:'/showUserProfile/:id',
      name:'showUserProfile',
      component: () => import('../views/User/showUserProfile.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'profile'
      }
    },
    {

      path: '/exam-module',
      name: 'exam-module',
      component: () => import('../views/User/ExamModule.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar: false,
        title: 'Exam'
      }
    },
    {
      path: '/learn-module',
      name: 'learn-module',
      component: () => import('../views/User/LearnModule.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar: false,
        title: 'Learn'
      }
    },
    {
      path:'/learn/:category?',
      name:'learn',
      component: () => import('../components/HomePage/Learn.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'learn'
      }
    },
    {

      path:'/exams/:category?',
      name:'exams',
      component: () => import('../components/commonComponents/savedExams.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'exams'
      }
    },
    {

      path:'/startExam/:id',
      name:'startExam',
      component: () => import('@/components/commonComponents/startExam.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:true,
        title:'startExam'
      }
    },
     {

      path:'/examForm',
      name:'examForm',
      component: () => import('@/components/commonComponents/examForm.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:true,
        title:'startExam'
      }
    },
    {

      path:'/openExamQuestions/:id',
      name:'openExamQuestions',
      component: () => import('@/components/commonComponents/OpenExamQuestions.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'openExam'
      }
    },
    {
      path:'/examEnd',
      name:'examEnd',
      component: () => import('@/components/commonComponents/examEnd.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:''
      }
    },
    {
      path:'/testament',
      name:'testament',
      component: () => import('@/components/commonComponents/testament.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'Testament Management'
      }
    },
    {
      path:'/about_site',
      name:'about_site',
      component: () => import('@/components/commonComponents/AboutSite.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'About Site'
      }
    },
    {
      path:'/glossary',
      name:'glossary',
      component: () => import('@/components/commonComponents/Glossary.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'Glossary'
      }
    },
    {
      path:'/verify_idnetity',
      name:'verify_idnetity',
      component: () => import('@/components/commonComponents/VerifyIdentity.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'Verify Identity'
      }
    },
    {
      path:'/submit_case/:slug?',
      name:'submit_case',
      component: () => import('@/components/commonComponents/court.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'Submit Case'
      }
    },
    {
      path:'/terms_conditions',
      name:'terms_conditions',
      component: () => import('@/components/commonComponents/TermsConditions.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'Terms & Conditions'
      }
    },
    {
      path:'/how_it_works',
      name:'how_it_works',
      component: () => import('@/components/commonComponents/HowItWorks.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'How It Works'
      }
    },
    {
      path:'/privacy_policy',
      name:'privacy_policy',
      component: () => import('@/components/commonComponents/PrivacyPolicy.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'Privacy Policy'
      }
    },
    {
      path:'/encription_details',
      name:'encription_details',
      component: () => import('@/components/commonComponents/EncryptionDetails.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'Encryption Details'
      }
    },
    {
      path:'/scoring_breakdown',
      name:'scoring_breakdown',
      component: () => import('@/components/commonComponents/ScoringBreakDown.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'Scoring Breakdown'
      }
    },
    {
      path:'/data_retention_rules',
      name:'data_retention_rules',
      component: () => import('@/components/commonComponents/dataRetentionRules.vue'),
      meta: {
        requiresAuth: true,
        hideNavBar:false,
        title:'Scoring Breakdown'
      }
    },
    {
      path: '/tribunal/create',
      name: 'tribunal-create',
      component: CreateTribunalCase,
      meta: {
        requiresAuth: true,
        hideNavBar: false,
        title: 'Submit Tribunal Case'
      },
    },
    {
      path: '/tribunal/cases',
      name: 'tribunal-cases',
      component: TribunalCases,
      meta: {
        requiresAuth: true,
        hideNavBar: false,
        title: 'My Tribunal Cases'
      },
    },
    {
      path: '/tribunal/cases/:id',
      name: 'tribunal-case-details',
      component: TribunalCaseDetails,
      meta: {
        requiresAuth: true,
        hideNavBar: false,
        title: 'Tribunal Case Details'
      },
    },
    {
      path: '/tribunal/jury',
      name: 'tribunal-jury',
      component: TribunalJuryCases,
      meta: {
        requiresAuth: true,
        hideNavBar: false,
        title: 'Tribunal Adjudicator Portal'
      },
    },
    {
      path: '/professional-verification',
      name: 'professional-verification',
      component: ProfessionalVerification,
      meta: {
        requiresAuth: true,
        hideNavBar: false,
        title: 'Professional Verification'
      },
    },
    {
      path: '/admin/professional-verifications',
      name: 'admin-professional-verifications',
      component: AdminProfessionalVerifications,
      meta: {
        requiresAuth: true,
        hideNavBar: false,
        title: 'Professional Verifications Review'
      },
    },
    {
      path: '/tribunal/representation-requests',
      name: 'tribunal-representation-requests',
      component: TribunalRepresentationRequests,
      meta: {
        requiresAuth: true,
        hideNavBar: false,
        title: 'Representation Requests Inbox'
      },
    },
    {
      path: '/tribunal/represented-cases',
      name: 'tribunal-represented-cases',
      component: TribunalRepresentedCases,
      meta: {
        requiresAuth: true,
        hideNavBar: false,
        title: 'My Represented Cases'
      },
    },
    {
      path: '/admin/tribunal/jury-panels',
      name: 'admin-tribunal-jury-panels',
      component: () => import('@/views/admin/AdminJuryPanels.vue'),
      meta: {
        requiresAuth: true,
        title: 'Manage Jury Panels',
      },
    },
    {
      path: '/tribunal/reports/verify/:verificationCode?',
      name: 'tribunal-report-verification',
      component: () => import('@/views/tribunal/TribunalReportVerification.vue'),
      meta: {
        title: 'Verify Tribunal Report',
      },
    },
    {
      path: '/jury',
      component: () => import('@/layouts/JuryPanelLayout.vue'),
      meta: {
        requiresAuth: true,
        requiresJuryPanel: true,
      },
      children: [
        {
          path: '',
          name: 'jury-dashboard',
          component: () => import('@/views/jury/JuryDashboard.vue'),
          meta: {
            title: 'Jury Portal Dashboard',
            requiresAuth: true,
            requiresJuryPanel: true,
            hideNavBar: true,
          },
        },
        {
          path: 'cases',
          name: 'jury-cases',
          component: () => import('@/views/jury/JuryAssignedCases.vue'),
          meta: {
            title: 'Assigned Cases',
            requiresAuth: true,
            requiresJuryPanel: true,
            hideNavBar: true,
          },
        },
        {
          path: 'cases/:id',
          name: 'jury-case-details',
          component: () => import('@/views/jury/JuryCaseDetails.vue'),
          meta: {
            title: 'Case Details',
            requiresAuth: true,
            requiresJuryPanel: true,
            hideNavBar: true,
          },
        },
      ],
    },
  ],
});
router.beforeEach(async(to, from, next) => {
  const loadingStore = useLoadingStore();
  loadingStore.loadingStart();
  const isAuthRequired = to.meta.requiresAuth;

  if (isAuthRequired) {
    const authUser = await fetchAuthUser();

    if (authUser) {
      loadingStore.loadingStop();
      const isJury = isJuryPanelUser() || Boolean(authUser.is_jury_panel);

      // Rule 1: If Jury Panel tries to access non-jury routes, redirect to /jury
      if (isJury && !to.path.startsWith('/jury')) {
        next('/jury');
        return;
      }

      // Rule 2: If normal user / lawyer / admin tries to access /jury routes, redirect to /home
      if (!isJury && to.path.startsWith('/jury')) {
        next('/home');
        return;
      }

      // Onboarding is only for users without a profile; everyone else goes to the dashboard.
      if (to.name === 'basicDetails-fill' && isProfileCompleted(authUser)) {
        next({ name: 'home' });
      } else {
        next();
      }

    } else {
      loadingStore.loadingStop();
      // Redirect to login if not authenticated
      let confirm = await Swal.fire({
        icon: 'error',
        title: 'error',
        text: 'You are not authenticated. Please login to continue.',
        showCancelButton: false,
        showConfirmButton: false,
        timer: 3000,
      });

      next('/');
    }
  } else {
    // If user is already authenticated as Jury Panel and visits public auth pages (/ or /login), redirect to /jury
    const isJury = isJuryPanelUser();
    if (isJury && (to.path === '/' || to.path === '/login' || to.path === '/register')) {
      const authUser = await fetchAuthUser();
      if (authUser) {
        loadingStore.loadingStop();
        next('/jury');
        return;
      }
    }
    loadingStore.loadingStop();
    next();
  }
});

router.afterEach((to) => {
  const appName = 'MyIntellibook';
  document.title = to.meta.title ? `${to.meta.title} | ${appName}` : appName;
});

export default router
