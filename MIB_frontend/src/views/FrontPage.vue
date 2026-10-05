<template>
    <div class="luxury-page-wrapper">
        <!-- Clean Modern Navigation Header -->
        <header class="navbar-wrapper">
            <div class="container d-flex justify-content-between align-items-center py-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="../assets/webIcon.jpeg" alt="MyIntelliBook Logo" class="brand-icon" />
                    <span class="brand-name">MyIntelli<span class="brand-highlight">Book</span></span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <button class="btn-secondary-luxury" @click="$router.push('/login')">Sign In</button>
                    <button class="btn-primary-luxury" @click="$router.push('/register')">Join Now</button>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="hero-section">
            <div class="container py-5">
                <div class="row align-items-center justify-content-between py-4">
                    <div class="col-lg-6 mb-5 mb-lg-0">
                        <span class="subtitle-badge mb-3">Professional Network Platform</span>
                        <h1 class="hero-title mb-4">
                            Welcome to <span class="brand-highlight">MyIntelliBook</span>
                        </h1>
                        <p class="hero-text mb-4">
                            Connect, grow, and showcase your professional journey. Build your comprehensive profile, take knowledge assessments, and climb the professional rankings.
                        </p>
                        
                        <!-- Auth Action Box -->
                        <div class="auth-box p-4 rounded-4">
                            <div class="d-flex flex-column gap-3 mb-3">
                                <Button 
                                    severity="contrast" 
                                    class="w-100 btn-email-login py-3 fw-semibold" 
                                    label="Login with email" 
                                    @click="$router.push('/login')" 
                                />
                                <div class="w-100 google-btn-wrap">
                                    <GoogleLogin :callback="handleLogin" class="w-100 rounded-5" prompt auto-login />
                                </div>
                            </div>
                            <div class="text-center pt-2">
                                <span class="text-muted">New To MyIntelliBook?</span>
                                <Button 
                                    size="large" 
                                    class="mx-1 fw-semibold btn-join-link" 
                                    severity="link" 
                                    label="Join now" 
                                    @click="$router.push('/register')" 
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Clean Showcase Graphic Card -->
                    <div class="col-lg-5">
                        <div class="hero-visual-card p-4 rounded-4">
                            <div class="visual-header mb-4 pb-3 border-bottom">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar-circle">
                                        <i class="pi pi-user text-muted fs-4"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 fw-bold text-dark">Verified Professional Profile</h6>
                                        <small class="text-muted">MyIntelliBook Network</small>
                                    </div>
                                </div>
                            </div>

                            <div class="visual-stats-grid row g-3 text-center mb-4">
                                <div class="col-6">
                                    <div class="stat-card p-3 rounded-3">
                                        <span class="stat-val text-brand">Top 5%</span>
                                        <small class="d-block text-muted">Global Ranking</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-card p-3 rounded-3">
                                        <span class="stat-val text-dark">Verified</span>
                                        <small class="d-block text-muted">IQ & Skills</small>
                                    </div>
                                </div>
                            </div>

                            <div class="visual-checklist">
                                <div class="checklist-item d-flex align-items-center gap-2 mb-2">
                                    <i class="pi pi-check-circle text-brand"></i>
                                    <span class="text-secondary fs-6">Comprehensive Career Journey</span>
                                </div>
                                <div class="checklist-item d-flex align-items-center gap-2 mb-2">
                                    <i class="pi pi-check-circle text-brand"></i>
                                    <span class="text-secondary fs-6">Standardized Domain Evaluations</span>
                                </div>
                                <div class="checklist-item d-flex align-items-center gap-2">
                                    <i class="pi pi-check-circle text-brand"></i>
                                    <span class="text-secondary fs-6">Real-time Performance Analytics</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features-section py-5">
            <div class="container py-4">
                <div class="text-center mb-5">
                    <h2 class="section-title mb-3">Everything You Need for Professional Growth</h2>
                    <p class="section-subtitle mx-auto">
                        Comprehensive tools to build, showcase, and enhance your professional profile
                    </p>
                </div>

                <div class="row g-4">
                    <!-- Feature 1 -->
                    <div class="col-lg-3 col-md-6">
                        <div class="feature-card p-4 h-100 rounded-4">
                            <div class="icon-box mb-3">
                                <i class="pi pi-users fs-2 text-brand"></i>
                            </div>
                            <h5 class="fw-bold mb-2">Complete Profile Management</h5>
                            <p class="text-muted fs-6 mb-3">Build a comprehensive professional profile with education, experience, skills, and certifications</p>
                            <ul class="feature-ul list-unstyled m-0">
                                <li>Personal & Contact Information</li>
                                <li>Education & Academic Records</li>
                                <li>Professional Experience</li>
                                <li>Skills & Expertise Assessment</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Feature 2 -->
                    <div class="col-lg-3 col-md-6">
                        <div class="feature-card p-4 h-100 rounded-4">
                            <div class="icon-box mb-3">
                                <i class="pi pi-book fs-2 text-brand"></i>
                            </div>
                            <h5 class="fw-bold mb-2">Knowledge Assessments</h5>
                            <p class="text-muted fs-6 mb-3">Test your expertise in various domains with our comprehensive evaluation system</p>
                            <ul class="feature-ul list-unstyled m-0">
                                <li>IQ & Cognitive Assessment</li>
                                <li>Technical Evaluations</li>
                                <li>Philosophy & Critical Thinking</li>
                                <li>Mathematics & Analytics</li>
                                <li>Timed & Secure Testing</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Feature 3 -->
                    <div class="col-lg-3 col-md-6">
                        <div class="feature-card p-4 h-100 rounded-4">
                            <div class="icon-box mb-3">
                                <i class="pi pi-star fs-2 text-brand"></i>
                            </div>
                            <h5 class="fw-bold mb-2">Professional Rankings</h5>
                            <p class="text-muted fs-6 mb-3">Get rated based on your profile completeness, test scores, and professional achievements</p>
                            <ul class="feature-ul list-unstyled m-0">
                                <li>Multi-factor Rating System</li>
                                <li>Peer Comparison & Rankings</li>
                                <li>Progress Tracking</li>
                                <li>Achievement Badges</li>
                                <li>Leaderboards</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Feature 4 -->
                    <div class="col-lg-3 col-md-6">
                        <div class="feature-card p-4 h-100 rounded-4">
                            <div class="icon-box mb-3">
                                <i class="pi pi-wave-pulse fs-2 text-brand"></i>
                            </div>
                            <h5 class="fw-bold mb-2">Growth Analytics</h5>
                            <p class="text-muted fs-6 mb-3">Track your professional growth with detailed analytics and insights</p>
                            <ul class="feature-ul list-unstyled m-0">
                                <li>Profile Completion Tracking</li>
                                <li>Skill Development Progress</li>
                                <li>Test Performance Analytics</li>
                                <li>Ranking Progression</li>
                                <li>Goal Setting & Milestones</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Call to Action Banner -->
        <section class="cta-section my-5">
            <div class="container">
                <div class="cta-box p-5 text-center rounded-4">
                    <h2 class="fw-bold mb-3 text-white">Ready to Build Your Professional Profile?</h2>
                    <p class="text-white-50 fs-5 mb-4 mx-auto" style="max-width: 600px;">
                        Join thousands of professionals who are advancing their careers with MyIntelliBook
                    </p>
                    <Button 
                        size="small" 
                        class="btn-cta-action fw-bold px-4 py-2" 
                        severity="secondary" 
                        @click="$router.push('/register')"
                    >
                        Start Your Journey <i class="pi pi-arrow-right ms-2"></i>
                    </Button>
                </div>
            </div>
        </section>

        <!-- Clean Footer -->
        <footer class="footer-wrapper py-4 border-top">
            <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <img src="../assets/webIcon.jpeg" alt="Logo" class="footer-logo" />
                    <span class="fw-bold text-dark">MyIntelliBook</span>
                    <span class="text-muted fs-7 ms-2">© 2026 All rights reserved.</span>
                    <span class="text-muted fs-7 ms-2">• Crafted with excellence by <strong class="text-brand">DuBaTech Solutions</strong></span>
                </div>
                <div class="d-flex gap-4 fs-6">
                    <router-link to="/about_site" class="footer-link">About</router-link>
                    <router-link to="/terms_conditions" class="footer-link">Terms</router-link>
                    <router-link to="/privacy_policy" class="footer-link">Privacy</router-link>
                    <router-link to="/how_it_works" class="footer-link">How It Works</router-link>
                </div>
            </div>
        </footer>
    </div>
</template>

<script lang="ts" setup>
import Button from 'primevue/button';
import { checkAuth, routeAfterLogin } from '../services/auth';
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router'; 
import { useUserStore } from '@/stores/User/userStore';

const userStore = useUserStore();
const isLoggedIn = ref<boolean>(true);
const router = useRouter();

const checkAuthUser = async () => {
    let result = await checkAuth();

    if (result) {
        isLoggedIn.value = true;
        router.push('/home');
    } else {
        isLoggedIn.value = false;
        router.push('/');
    }
}

const handleLogin = async (response: any) => {
    let request = {
        token: response.credential
    }
    let result = await userStore.googleLogin(request);
    if (result.code === 200) {
        localStorage.setItem('userToken', result.token);
        localStorage.setItem('userData', JSON.stringify(result.user));
        router.push(routeAfterLogin(result.user));
    }
}

onMounted(async () => {
    await checkAuthUser();
})
</script>

<style scoped>
/* Base Page & Brand Colors */
.luxury-page-wrapper {
    background-color: #fafbfc;
    color: #1e293b;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    min-height: 100vh;
}

.text-brand {
    color: rgb(160, 56, 41) !important;
}

.brand-highlight {
    color: rgb(160, 56, 41);
    font-weight: 800;
}

/* Header */
.navbar-wrapper {
    background: #ffffff;
    border-bottom: 1px solid var(--ds-border);
    position: sticky;
    top: 0;
    z-index: 100;
}
.brand-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
}
.brand-name {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--ds-text);
}
.btn-secondary-luxury {
    background: transparent;
    border: 1px solid var(--ds-border-strong);
    color: var(--ds-text-secondary);
    padding: 7px 18px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.2s ease;
}
.btn-secondary-luxury:hover {
    border-color: rgb(160, 56, 41);
    color: rgb(160, 56, 41);
}
.btn-primary-luxury {
    background: rgb(160, 56, 41);
    border: none;
    color: #ffffff;
    padding: 7px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: background-color 0.2s ease;
}
.btn-primary-luxury:hover {
    background: rgb(135, 42, 29);
}

/* Hero Section */
.hero-section {
    background: linear-gradient(180deg, #ffffff 0%, var(--ds-surface-muted) 100%);
    border-bottom: 1px solid var(--ds-border);
}
.subtitle-badge {
    text-transform: uppercase;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 1.2px;
    color: rgb(160, 56, 41);
}
.hero-title {
    font-size: clamp(2.2rem, 4.5vw, 3.6rem);
    font-weight: 800;
    line-height: 1.15;
    color: var(--ds-text);
}
.hero-text {
    font-size: 1.15rem;
    color: var(--ds-text-secondary);
    line-height: 1.6;
}

/* Auth Card */
.auth-box {
    background: #ffffff;
    border: 1px solid var(--ds-border);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    max-width: 480px;
}
.btn-email-login {
    background: rgb(160, 56, 41) !important;
    border: 1px solid rgb(160, 56, 41) !important;
    color: #ffffff !important;
    border-radius: 8px !important;
    font-size: 1rem !important;
}
.btn-email-login:hover {
    background: rgb(135, 42, 29) !important;
}
.btn-join-link {
    color: rgb(160, 56, 41) !important;
    text-decoration: none;
}

/* Visual Card Showcase */
.hero-visual-card {
    background: #ffffff;
    border: 1px solid var(--ds-border);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.06);
}
.user-avatar-circle {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--ds-surface-muted);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--ds-border-strong);
}
.stat-card {
    background: var(--ds-surface-subtle);
    border: 1px solid var(--ds-border);
}
.stat-val {
    font-size: 1.4rem;
    font-weight: 800;
}
.checklist-item {
    font-size: 0.95rem;
}

/* Features */
.section-title {
    font-size: 2.1rem;
    font-weight: 800;
    color: var(--ds-text);
}
.section-subtitle {
    color: var(--ds-text-muted);
    font-size: 1.05rem;
    max-width: 580px;
}
.feature-card {
    background: #ffffff;
    border: 1px solid var(--ds-border);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}
.feature-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.07);
    border-color: rgb(160, 56, 41);
}
.icon-box {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: rgba(160, 56, 41, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
}
.feature-ul li {
    font-size: 0.88rem;
    color: var(--ds-text-secondary);
    padding: 4px 0;
    position: relative;
    padding-left: 18px;
}
.feature-ul li::before {
    content: "•";
    color: rgb(160, 56, 41);
    font-weight: bold;
    position: absolute;
    left: 0;
}

/* CTA */
.cta-box {
    background: rgb(160, 56, 41);
    box-shadow: 0 15px 35px rgba(160, 56, 41, 0.3);
}
.btn-cta-action {
    background: #ffffff !important;
    color: var(--ds-text) !important;
    border: none !important;
    border-radius: 8px !important;
}
.btn-cta-action:hover {
    background: var(--ds-surface-subtle) !important;
}

/* Footer */
.footer-logo {
    width: 24px;
    height: 24px;
    border-radius: 6px;
}
.footer-link {
    color: var(--ds-text-muted);
    text-decoration: none;
    transition: color 0.2s ease;
}
.footer-link:hover {
    color: rgb(160, 56, 41);
}
.fs-7 {
    font-size: 0.75rem;
}
</style>