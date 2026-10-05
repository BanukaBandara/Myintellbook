<template>
    <Card :pt="{
               body:{
                class:'p-0'
               }
            }">
                <template #content>
                    <div class="profile-header-card position-relative overflow-hidden rounded-3 bg-white shadow-sm">
                        <!-- Cover Photo Banner -->
                        <div
                            class="cover-photo text-end position-relative"
                            @click="openLightbox('cover')"
                            :style="{ backgroundImage: `url('${coverPhotoUrl}')` }"
                            title="Click to view cover photo"
                        >
                            <button
                                v-if="isOwnProfile"
                                type="button"
                                class="cover-edit-button position-absolute"
                                aria-label="Change cover photo"
                                @click.stop="router.push('/profileEdit/addCoverImage')"
                                title="Change cover photo"
                            >
                                <i class="pi pi-camera me-1" aria-hidden="true"></i>
                                <span class="d-none d-sm-inline">Edit Cover</span>
                            </button>
                        </div>

                        <!-- Profile Image (absolute, overlapping cover) -->
                        <div class="avatar-wrapper position-absolute" @click="openLightbox('profile')">
                          <button
                            type="button"
                            class="avatar-view-button"
                            aria-label="View profile photo"
                            @click.stop="openLightbox('profile')"
                            title="Click to view profile photo"
                          >
                            <Avatar :image="profilePhotoUrl" class="profile-image" shape="circle" @click.stop="openLightbox('profile')" />
                          </button>
                          <button
                            v-if="isOwnProfile"
                            type="button"
                            class="avatar-edit-button"
                            aria-label="Change profile photo"
                            @click.stop="router.push('/profileEdit/addProfileImage')"
                            title="Change profile photo"
                          >
                            <i class="pi pi-pencil" aria-hidden="true"></i>
                          </button>
                        </div>

                        <!-- Info Section -->
                        <div class="profile-details-body px-4 pb-4">
                          <div class="row pt-2 align-items-start">
                            <!-- Left Column -->
                            <div class="col-md-7 col-12 mb-2 mb-md-0">
                              <h3 class="mb-1 fw-bold text-dark fs-4">{{ (userGeneralInfo.first_name || '') + " " + (userGeneralInfo.last_name || '') }}</h3>

                              <p class="text-muted mb-2 fw-semibold fs-6" v-if="userGeneralInfo.profession?.profession || userGeneralInfo.profession?.location">
                                <i class="bi bi-person-badge text-primary me-1" v-if="userGeneralInfo.profession?.profession"></i>
                                <span>{{ userGeneralInfo.profession?.profession }}</span>
                                <span v-if="userGeneralInfo.profession?.profession && userGeneralInfo.profession?.location">, </span>
                                <i class="bi bi-geo-alt text-primary ms-1 me-1" v-if="userGeneralInfo.profession?.location"></i>
                                <span>{{ userGeneralInfo.profession?.location }}</span>
                              </p>

                              <p class="text-muted mb-0 fs-6 d-flex align-items-center gap-2">
                                <span v-tooltip="'Human Intelligence Portfolio'" class="badge bg-light text-secondary border fw-medium px-2 py-1">HIP</span>
                                <strong class="text-dark fw-bold">{{ formattedHipScore }}</strong>
                              </p>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="col-md-5 col-12 text-md-end text-start">
                              <p class="fw-semibold text-primary mb-1 fs-6" v-if="userGeneralInfo.profession?.company">
                                <i class="bi bi-building me-1"></i>{{ userGeneralInfo.profession.company }}
                              </p>

                              <p class="text-muted mb-0 fs-6" v-if="userGeneralInfo.school">
                                <i class="bi bi-book me-1"></i>{{ userGeneralInfo.school }}
                              </p>
                            </div>
                          </div>
                        </div>

                    </div>
                </template>
            </Card>

            <!-- Modern Luxury Profile & Cover Photo Lightbox Modal -->
            <Teleport to="body">
              <Transition name="profile-photo-fade">
                <div
                  v-if="lightboxMode !== null"
                  class="profile-photo-lightbox-overlay"
                  role="dialog"
                  aria-modal="true"
                  :aria-label="activePhotoTitle"
                  @click.self="closeLightbox"
                  @keydown.esc.window="closeLightbox"
                >
                  <div class="profile-photo-modal-card">
                    <!-- Header -->
                    <div class="profile-photo-modal-header">
                      <div class="profile-photo-modal-user">
                        <Avatar :image="profilePhotoUrl" shape="circle" class="profile-modal-avatar" />
                        <div class="profile-modal-user-info">
                          <span class="profile-modal-username">
                            {{ activePhotoTitle }}
                          </span>
                          <!-- Photo Switcher Tabs -->
                          <div class="lightbox-tabs mt-1">
                            <button
                              type="button"
                              class="lightbox-tab-btn"
                              :class="{ active: lightboxMode === 'profile' }"
                              @click="lightboxMode = 'profile'; zoomLevel = 1;"
                            >
                              Profile Photo
                            </button>
                            <button
                              type="button"
                              class="lightbox-tab-btn"
                              :class="{ active: lightboxMode === 'cover' }"
                              @click="lightboxMode = 'cover'; zoomLevel = 1;"
                            >
                              Cover Photo
                            </button>
                          </div>
                        </div>
                      </div>

                      <div class="profile-photo-modal-actions">
                        <button
                          v-if="isOwnProfile"
                          type="button"
                          class="profile-modal-action-btn"
                          :title="lightboxMode === 'cover' ? 'Change Cover Photo' : 'Change Profile Photo'"
                          @click="goToEditActivePhoto"
                        >
                          <i class="pi pi-pencil"></i>
                          <span class="action-btn-label">Edit</span>
                        </button>

                        <button
                          type="button"
                          class="profile-modal-close-btn"
                          title="Close (Esc)"
                          aria-label="Close photo preview"
                          @click="closeLightbox"
                        >
                          <i class="pi pi-times"></i>
                        </button>
                      </div>
                    </div>

                    <!-- Content (Image View) -->
                    <div class="profile-photo-modal-body" @click.self="closeLightbox">
                      <div class="profile-photo-stage">
                        <img
                          :src="activePhotoUrl"
                          :alt="activePhotoTitle"
                          class="profile-photo-main-img"
                          :style="{ transform: `scale(${zoomLevel})` }"
                        />
                      </div>
                    </div>

                    <!-- Footer / Zoom Controls -->
                    <div class="profile-photo-modal-footer">
                      <div class="zoom-controls">
                        <button
                          type="button"
                          class="zoom-btn"
                          title="Zoom Out"
                          :disabled="zoomLevel <= 0.6"
                          @click="zoomOut"
                        >
                          <i class="pi pi-search-minus"></i>
                        </button>

                        <span class="zoom-level-text">{{ Math.round(zoomLevel * 100) }}%</span>

                        <button
                          type="button"
                          class="zoom-btn"
                          title="Zoom In"
                          :disabled="zoomLevel >= 2.5"
                          @click="zoomIn"
                        >
                          <i class="pi pi-search-plus"></i>
                        </button>

                        <button
                          v-if="zoomLevel !== 1"
                          type="button"
                          class="zoom-reset-btn"
                          title="Reset Zoom"
                          @click="resetZoom"
                        >
                          Reset
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </Transition>
            </Teleport>
    </template>
<script lang="ts" setup>

import { useRouter } from 'vue-router';
import userPng from '../../assets/user.png';
import type {userGeneralInfoType} from '../../types/userGeneralInfoType';
import {ref, type PropType ,computed} from 'vue';
import coverImageSet from '../../assets/default-cover-2.jpg';
import Avatar from 'primevue/avatar';
import Card from 'primevue/card';

const router = useRouter();
const lightboxMode = ref<'profile' | 'cover' | null>(null);
const zoomLevel = ref(1);

const props = defineProps({
    userGeneralInfo:{
        type:Object as PropType<userGeneralInfoType>,
        default:() => ({})
    }
});

const userGeneralInfo = computed(()=> props.userGeneralInfo || {})
const formattedHipScore = computed(() => {
  const points = Number(userGeneralInfo.value?.hip_score ?? 0);
  return new Intl.NumberFormat().format(Number.isFinite(points) ? points : 0);
});

const profilePhotoUrl = computed(() => {
  const img = userGeneralInfo.value?.profile_image;
  if (typeof img === 'string' && img.trim() !== '') {
    return img;
  }
  return userPng;
});

const coverPhotoUrl = computed(() => {
  const img = userGeneralInfo.value?.cover_image;
  if (typeof img === 'string' && img.trim() !== '') {
    return img;
  }
  return coverImageSet;
});

const activePhotoUrl = computed(() => {
  if (lightboxMode.value === 'cover') {
    return coverPhotoUrl.value;
  }
  return profilePhotoUrl.value;
});

const activePhotoTitle = computed(() => {
  const name = ((userGeneralInfo.value.first_name || '') + ' ' + (userGeneralInfo.value.last_name || '')).trim() || 'User';
  return lightboxMode.value === 'cover' ? `${name}'s Cover Photo` : `${name}'s Profile Photo`;
});

const isOwnProfile = computed(() => router.currentRoute.value.name === 'profile');

const openLightbox = (mode: 'profile' | 'cover') => {
  lightboxMode.value = mode;
  zoomLevel.value = 1;
};

const closeLightbox = () => {
  lightboxMode.value = null;
  zoomLevel.value = 1;
};

const zoomIn = () => {
  if (zoomLevel.value < 2.5) {
    zoomLevel.value = parseFloat((zoomLevel.value + 0.25).toFixed(2));
  }
};

const zoomOut = () => {
  if (zoomLevel.value > 0.6) {
    zoomLevel.value = parseFloat((zoomLevel.value - 0.25).toFixed(2));
  }
};

const resetZoom = () => {
  zoomLevel.value = 1;
};

const goToEditActivePhoto = () => {
  const mode = lightboxMode.value;
  closeLightbox();
  if (mode === 'cover') {
    router.push('/profileEdit/addCoverImage');
  } else {
    router.push('/profileEdit/addProfileImage');
  }
};
</script>
<style>
.profile-header-card {
  width: 100%;
  margin: auto;
}

.cover-photo {
  height: 180px;
  width: 100%;
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  cursor: pointer;
  transition: opacity 0.2s ease;
}

.cover-photo:hover {
  opacity: 0.9;
}

.cover-edit-button {
  top: 12px;
  right: 12px;
  z-index: 5;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 20px;
  border: 1px solid rgba(255, 255, 255, 0.4);
  background: rgba(0, 0, 0, 0.55);
  color: #ffffff;
  font-size: 0.82rem;
  font-weight: 500;
  cursor: pointer;
  backdrop-filter: blur(8px);
  transition: all 0.2s ease;
}

.cover-edit-button:hover {
  background: rgba(0, 0, 0, 0.8);
  border-color: #ffffff;
}

.avatar-wrapper {
  left: 24px;
  top: 110px;
  width: 135px;
  height: 135px;
  z-index: 10;
}

.avatar-view-button {
  width: 100%;
  height: 100%;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  cursor: zoom-in;
  display: block;
}

.profile-image {
  width: 100% !important;
  height: 100% !important;
  border-radius: 50% !important;
  border: 4px solid #ffffff !important;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15) !important;
  background-color: #ffffff;
  overflow: hidden !important;
}

.profile-image img {
  width: 100% !important;
  height: 100% !important;
  object-fit: cover !important;
}

.avatar-edit-button {
  position: absolute;
  right: 2px;
  bottom: 2px;
  z-index: 12;
  display: grid;
  width: 34px;
  height: 34px;
  place-items: center;
  border: 2px solid #fff;
  border-radius: 50%;
  background: #252525;
  color: #fff;
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.avatar-edit-button:hover {
  background: #000;
}

.profile-details-body {
  padding-top: 75px !important;
}

.lightbox-tabs {
  display: flex;
  gap: 6px;
}

.lightbox-tab-btn {
  padding: 2px 10px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  background: rgba(255, 255, 255, 0.05);
  color: rgba(255, 255, 255, 0.7);
  font-size: 0.75rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.lightbox-tab-btn:hover {
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
}

.lightbox-tab-btn.active {
  background: rgba(255, 255, 255, 0.25);
  border-color: rgba(255, 255, 255, 0.5);
  color: #ffffff;
  font-weight: 600;
}

/* Lightbox Overlay & Modal Styles */
.profile-photo-lightbox-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  inset: 0;
  z-index: 999999 !important;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(10, 12, 18, 0.88);
  backdrop-filter: blur(20px) saturate(180%);
  -webkit-backdrop-filter: blur(20px) saturate(180%);
}

.profile-photo-modal-card {
  position: relative;
  display: flex;
  flex-direction: column;
  width: 100%;
  max-width: 820px;
  max-height: 90vh;
  background: rgba(22, 27, 38, 0.95);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 20px;
  box-shadow: 0 30px 90px rgba(0, 0, 0, 0.7), 0 0 40px rgba(255, 255, 255, 0.05);
  overflow: hidden;
  color: #ffffff;
}

.profile-photo-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  background: rgba(255, 255, 255, 0.04);
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.profile-photo-modal-user {
  display: flex;
  align-items: center;
  gap: 12px;
}

.profile-modal-avatar {
  width: 42px !important;
  height: 42px !important;
  border: 2px solid rgba(255, 255, 255, 0.2);
}

.profile-modal-user-info {
  display: flex;
  flex-direction: column;
}

.profile-modal-username {
  font-weight: 700;
  font-size: 1.05rem;
  color: #ffffff;
  line-height: 1.2;
}

.profile-photo-modal-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.profile-modal-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: 30px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.08);
  color: #ffffff;
  font-size: 0.85rem;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s ease;
}

.profile-modal-action-btn:hover {
  background: rgba(255, 255, 255, 0.2);
  border-color: rgba(255, 255, 255, 0.35);
  color: #ffffff;
}

.profile-modal-close-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  border: 1px solid rgba(255, 255, 255, 0.2);
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff;
  font-size: 1rem;
  cursor: pointer;
  transition: all 0.25s ease;
}

.profile-modal-close-btn:hover {
  background: rgba(239, 68, 68, 0.85);
  border-color: rgba(239, 68, 68, 0.9);
  transform: rotate(90deg);
}

.profile-photo-modal-body {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 1;
  padding: 24px;
  min-height: 320px;
  max-height: 65vh;
  overflow: auto;
  background: radial-gradient(circle at center, rgba(30, 38, 54, 0.6) 0%, rgba(12, 15, 22, 0.9) 100%);
}

.profile-photo-stage {
  display: flex;
  align-items: center;
  justify-content: center;
  max-width: 100%;
  max-height: 100%;
}

.profile-photo-main-img {
  max-width: 100%;
  max-height: 55vh;
  object-fit: contain;
  border-radius: 12px;
  box-shadow: 0 15px 45px rgba(0, 0, 0, 0.5);
  transition: transform 0.25s cubic-bezier(0.2, 0.8, 0.2, 1);
  user-select: none;
}

.profile-photo-modal-footer {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 12px 20px;
  background: rgba(255, 255, 255, 0.03);
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.zoom-controls {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 16px;
  border-radius: 30px;
  background: rgba(0, 0, 0, 0.4);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.zoom-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  border: none;
  background: rgba(255, 255, 255, 0.12);
  color: #ffffff;
  cursor: pointer;
  transition: background 0.2s;
}

.zoom-btn:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.3);
}

.zoom-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.zoom-level-text {
  font-size: 0.82rem;
  font-weight: 600;
  min-width: 45px;
  text-align: center;
  color: rgba(255, 255, 255, 0.9);
}

.zoom-reset-btn {
  padding: 3px 10px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  background: transparent;
  color: rgba(255, 255, 255, 0.8);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
}

.zoom-reset-btn:hover {
  background: rgba(255, 255, 255, 0.15);
  color: #fff;
}

.profile-photo-fade-enter-active,
.profile-photo-fade-leave-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.profile-photo-fade-enter-from,
.profile-photo-fade-leave-to {
  opacity: 0;
}

.profile-photo-fade-enter-from .profile-photo-modal-card,
.profile-photo-fade-leave-to .profile-photo-modal-card {
  transform: scale(0.92);
  opacity: 0;
}

.profile-photo-fade-enter-to .profile-photo-modal-card,
.profile-photo-fade-leave-from .profile-photo-modal-card {
  transform: scale(1);
  opacity: 1;
}

.menuBar > div:hover{
    cursor:pointer;
    color:orange;
    border-bottom:orange 2px solid;
}
.underline{
    border-bottom:orange 2px solid;
}

@media (max-width: 576px) {
  .cover-photo {
    height: 130px;
  }

  .avatar-wrapper {
    left: 16px;
    top: 75px;
    width: 105px;
    height: 105px;
  }

  .profile-details-body {
    padding-top: 55px !important;
  }

  .profile-photo-modal-card {
    max-height: 95vh;
    border-radius: 16px;
  }
  .profile-modal-username {
    font-size: 0.95rem;
  }
  .action-btn-label {
    display: none;
  }
  .profile-photo-modal-header {
    padding: 12px 14px;
  }
}
</style>