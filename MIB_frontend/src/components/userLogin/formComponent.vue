<template>
<form class="w-100" @submit.prevent="submitUserData">
    <div class="auth-card p-4 p-md-5 rounded-4 bg-white border shadow-sm mx-auto">
        <div class="text-center mb-4">
            <router-link to="/" class="d-inline-block mb-2">
                <img src="@/assets/webIcon.jpeg" alt="MyIntelliBook Logo" class="auth-logo" />
            </router-link>
            <h4 class="fw-bold text-dark mb-1">Sign In to MyIntelliBook</h4>
            <p class="text-muted fs-6 mb-0">Welcome back! Access your professional account.</p>
        </div>

        <div class="d-flex flex-column gap-3 mb-4">
            <div>
                <label for="userEmail" class="form-label fw-semibold text-dark fs-6 mb-1">Email Address</label>
                <InputText 
                    v-model="userLogin.email"  
                    placeholder="Enter your email" 
                    size="normal" 
                    id="userEmail"
                    class="w-100 luxury-input"
                />
            </div>

            <div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="userPassword" class="form-label fw-semibold text-dark fs-6 mb-0">Password</label>
                    <router-link to="/password/reset" class="brand-link fs-7">Forgot Password?</router-link>
                </div>
                <InputGroup class="luxury-input-group">
                    <InputText 
                        :type="password" 
                        v-model="userLogin.password" 
                        placeholder="Enter your password" 
                        id="userPassword"
                        class="luxury-input"
                    />
                    <InputGroupAddon class="luxury-addon">
                        <i :class="iconString" @click="showPassword('password')" id="showPassword" style="cursor: pointer;"></i>
                    </InputGroupAddon>
                </InputGroup>
            </div>
        </div>

        <Button 
            :label="submitButtonLabel" 
            class="w-100 btn-submit-luxury py-3 mb-4" 
            size="normal" 
            @click="submitUserData" 
            id="login"
        >
            <template #icon>
                <i class="pi pi-spin pi-spinner me-2" style="font-size: 1rem" v-if="submitData"></i>
            </template>
        </Button>

        <div class="auth-card-footer text-center border-top pt-3">
            <p class="text-muted fs-7 mb-2">
                By clicking Sign In, you agree to the MyIntelliBook 
                <router-link to="/privacy_policy" class="brand-link">Privacy Policy</router-link> and 
                <router-link to="/terms_conditions" class="brand-link">Cookie Policy</router-link>.
            </p>
            <p class="fs-6 mb-0">
                Don't have an account? 
                <router-link to="/register" class="brand-link fw-bold ms-1">Sign Up</router-link>
            </p>
        </div>
    </div>
</form>
</template>

<script setup lang="ts">
import { ref, defineEmits } from 'vue';
import type userRegisterType from '@/types/userRegisterType';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import InputGroup from 'primevue/inputgroup';
import InputGroupAddon from 'primevue/inputgroupaddon';
import Button from 'primevue/button';
import showAlert from '@/composables/showAlert';
import { useUserStore } from '@/stores/User/userStore';
import { useRouter } from 'vue-router';
import { routeAfterLogin } from '@/services/auth';

const router = useRouter();
const userStore = useUserStore();
const submitButtonLabel = ref<string>('Sign In');
const userLogin = ref<userRegisterType>({
    email: '',
    password: '',
});
const submitData = ref<boolean>(false);
const isLoggedIn = ref<boolean>(false);

const password = ref<string>('password');
const iconString = ref<string>('bi bi-eye-slash');

const showPassword = (type: string) => {
    if (type === 'password') {
        password.value = password.value === 'password' ? 'text' : 'password';
        iconString.value = password.value === 'password' ? 'bi bi-eye-slash' : 'bi bi-eye';
    }
};

const submitUserData = async () => {
    userStore.userData = userLogin.value;
    submitButtonLabel.value = 'please wait...';
    submitData.value = true;
    let result = await userStore.loginUser();

    if (result.code === 200) {
        localStorage.setItem('userToken', result.token);
        localStorage.setItem('userData', JSON.stringify(result.user));
        submitButtonLabel.value = 'Sign In';
        submitData.value = false;
        router.push(routeAfterLogin(result.user));
    } else {
        let config = {
            icon: 'error',
            title: 'Error',
            text: result.message,
            confirmButtonText: 'OK',
            confirmButtonColor: '#a03829',
            showConfirmButton: true
        }

        let confirm = await showAlert(config);

        if (confirm.isConfirmed) {
            submitButtonLabel.value = 'Sign In';
            submitData.value = false;
        }
    }
}
</script>

<style scoped>
.auth-card {
    max-width: 450px;
    border-color: var(--ds-border) !important;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05) !important;
}

.auth-logo {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(160, 56, 41, 0.2);
}

.brand-link {
    color: rgb(160, 56, 41) !important;
    text-decoration: none;
    transition: opacity 0.2s ease;
}

.brand-link:hover {
    opacity: 0.85;
    text-decoration: underline;
}

.btn-submit-luxury {
    background: rgb(160, 56, 41) !important;
    border: none !important;
    color: #ffffff !important;
    border-radius: 10px !important;
    font-weight: 600 !important;
    font-size: 1rem !important;
    box-shadow: 0 4px 14px rgba(160, 56, 41, 0.3) !important;
    transition: transform 0.2s ease, background-color 0.2s ease !important;
}

.btn-submit-luxury:hover {
    transform: translateY(-1px);
    background: rgb(135, 42, 29) !important;
}

:deep(.luxury-input) {
    border-radius: 8px;
    border-color: var(--ds-border-strong);
    padding: 10px 14px;
}

:deep(.luxury-input:focus) {
    border-color: rgb(160, 56, 41);
    box-shadow: 0 0 0 3px rgba(160, 56, 41, 0.15);
}

:deep(.luxury-input-group) {
    border-radius: 8px;
}

:deep(.luxury-addon) {
    background: var(--ds-surface-subtle);
    border-color: var(--ds-border-strong);
    color: var(--ds-text-muted);
}

.fs-7 {
    font-size: 0.78rem;
}
</style>