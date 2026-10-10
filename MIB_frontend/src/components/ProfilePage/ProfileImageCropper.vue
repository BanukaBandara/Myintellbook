<template>
  <div class="upload-container">
    <label class="file-dropzone w-100" for="fileInput" v-if="!imageSrc">
      <div class="upload-icon">📁</div>
      <div class="upload-text">Click to upload a profile picture</div>
      <input
        id="fileInput"
        type="file"
        accept="image/*"
        @change="onFileChange"
        @dragover.prevent
        @drop.prevent="onDrop"
        class="file-input w-100"
      />
    </label>

    <div class="row">
      <div v-if="imageSrc" class="image-cropper col-md-6" ref="cropperRef">
        <img :src="imageSrc" alt="Profile" class="image-preview" />
        <div
          class="crop-box"
          :style="cropBoxStyle"
          @mousedown="startDrag"
          @touchstart.prevent="startDrag"
          v-if="from === 'profilePhoto'"
        >
          <div class="resize-handle br" @mousedown.stop.prevent="startResize('br', $event)"
               @touchstart.stop.prevent="startResize('br', $event)"></div>
        </div>
      </div>

      <div v-if="croppedImage" class="cropped-preview col-md-6">
        <h4>Cropped Preview:</h4>
        <img :src="croppedImage" alt="Cropped" />
      </div>
    </div>

    <p v-if="imageError" class="upload-error" role="alert">{{ imageError }}</p>

    <button v-if="imageSrc && from === 'profilePhoto'" @click="cropImage" class="crop-button">Crop</button>
    <button v-if="imageSrc" @click="() => { imageSrc = null; croppedImage = null }" class="crop-button">Cancel</button>
  </div>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from "vue";

const emit = defineEmits(['imageSelected', 'coverPhoto']);
const props = defineProps({ from: { type: String, default: "profilePhoto" } });

const from = computed(() => props.from);
const imageSrc = ref<string | null>(null);
const croppedImage = ref<string | null>(null);
const imageError = ref('');

const cropperRef = ref<HTMLDivElement | null>(null);

const cropBoxPos = reactive({ left: 0, top: 0 });
const cropBoxSize = reactive({ width: 200, height: 200 });

const minSize = 50;

let isDragging = false;
let dragStart = { x: 0, y: 0 };
let boxStart = { left: 0, top: 0 };

let isResizing = false;
let resizeDir: 'br' | null = null;
let resizeStart = { x: 0, y: 0 };
let initialSize = { width: 0, height: 0 };

function onFileChange(e: Event) {
  const target = e.target as HTMLInputElement;
  if (target.files?.[0]) {
    void processImage(target.files[0]);
  }
}

function onDrop(e: DragEvent) {
  if (!e.dataTransfer?.files.length) return;
  void processImage(e.dataTransfer.files[0]);
  e.dataTransfer.clearData();
}

async function processImage(file: File): Promise<void> {
  imageError.value = '';
  if (!file.type.startsWith('image/')) {
    imageError.value = 'Choose a valid image file.';
    return;
  }

  try {
    if (from.value === 'coverPhoto') {
      const image = await loadImage(file);
      imageSrc.value = compressCoverImage(image);
      emit('coverPhoto', imageSrc.value);
    } else {
      imageSrc.value = await readAsDataUrl(file);
    }

    croppedImage.value = null;
    cropBoxPos.left = 0;
    cropBoxPos.top = 0;
    cropBoxSize.width = 200;
    cropBoxSize.height = 200;
  } catch {
    imageError.value = 'This image could not be prepared. Try a smaller image.';
  }
}

function loadImage(file: File): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const objectUrl = URL.createObjectURL(file);
    const image = new Image();
    image.onload = () => {
      URL.revokeObjectURL(objectUrl);
      resolve(image);
    };
    image.onerror = () => {
      URL.revokeObjectURL(objectUrl);
      reject(new Error('Image decode failed'));
    };
    image.src = objectUrl;
  });
}

function compressCoverImage(image: HTMLImageElement): string {
  // Must stay under the server limit (App\Rules\Base64Image: 2 MB decoded, about 2.8M base64 chars).
  const maxDataUrlLength = 2_600_000;
  const maxDimension = 1920;
  let scale = Math.min(1, maxDimension / Math.max(image.naturalWidth, image.naturalHeight));
  const canvas = document.createElement('canvas');
  const context = canvas.getContext('2d');
  if (!context) throw new Error('Canvas is unavailable');

  for (let attempt = 0; attempt < 8; attempt++) {
    canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
    canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
    context.drawImage(image, 0, 0, canvas.width, canvas.height);

    const quality = Math.max(0.5, 0.82 - attempt * 0.05);
    const dataUrl = canvas.toDataURL('image/jpeg', quality);
    if (dataUrl.length <= maxDataUrlLength) return dataUrl;

    scale *= 0.82;
  }

  throw new Error('Image exceeds upload limit after compression');
}

function readAsDataUrl(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => typeof reader.result === 'string'
      ? resolve(reader.result)
      : reject(new Error('Image read failed'));
    reader.onerror = () => reject(new Error('Image read failed'));
    reader.readAsDataURL(file);
  });
}

function startDrag(event: MouseEvent | TouchEvent) {
  isDragging = true;
  const clientX = "touches" in event ? event.touches[0].clientX : event.clientX;
  const clientY = "touches" in event ? event.touches[0].clientY : event.clientY;
  dragStart = { x: clientX, y: clientY };
  boxStart.left = cropBoxPos.left;
  boxStart.top = cropBoxPos.top;

  window.addEventListener("mousemove", onDrag);
  window.addEventListener("mouseup", stopDrag);
  window.addEventListener("touchmove", onDrag, { passive: false });
  window.addEventListener("touchend", stopDrag);
}

function onDrag(event: MouseEvent | TouchEvent) {
  if (!isDragging) return;

  const clientX = "touches" in event ? event.touches[0].clientX : event.clientX;
  const clientY = "touches" in event ? event.touches[0].clientY : event.clientY;

  const container = cropperRef.value;
  if (!container) return;
  const rect = container.getBoundingClientRect();

  const dx = clientX - dragStart.x;
  const dy = clientY - dragStart.y;

  cropBoxPos.left = Math.max(0, Math.min(boxStart.left + dx, rect.width - cropBoxSize.width));
  cropBoxPos.top = Math.max(0, Math.min(boxStart.top + dy, rect.height - cropBoxSize.height));
}

function stopDrag() {
  isDragging = false;
  window.removeEventListener("mousemove", onDrag);
  window.removeEventListener("mouseup", stopDrag);
  window.removeEventListener("touchmove", onDrag);
  window.removeEventListener("touchend", stopDrag);
}

function startResize(direction: 'br', event: MouseEvent | TouchEvent) {
  isResizing = true;
  resizeDir = direction;
  const clientX = "touches" in event ? event.touches[0].clientX : event.clientX;
  const clientY = "touches" in event ? event.touches[0].clientY : event.clientY;
  resizeStart = { x: clientX, y: clientY };
  initialSize.width = cropBoxSize.width;
  initialSize.height = cropBoxSize.height;

  window.addEventListener("mousemove", onResize);
  window.addEventListener("mouseup", stopResize);
}

function onResize(event: MouseEvent) {
  if (!isResizing || !cropperRef.value) return;

  const dx = event.clientX - resizeStart.x;
  const dy = event.clientY - resizeStart.y;

  const container = cropperRef.value;
  const rect = container.getBoundingClientRect();

  if (resizeDir === 'br') {
    cropBoxSize.width = Math.max(minSize, Math.min(initialSize.width + dx, rect.width - cropBoxPos.left));
    cropBoxSize.height = Math.max(minSize, Math.min(initialSize.height + dy, rect.height - cropBoxPos.top));
  }
}

function stopResize() {
  isResizing = false;
  resizeDir = null;
  window.removeEventListener("mousemove", onResize);
  window.removeEventListener("mouseup", stopResize);
}

const cropBoxStyle = computed(() => ({
  width: cropBoxSize.width + "px",
  height: cropBoxSize.height + "px",
  left: cropBoxPos.left + "px",
  top: cropBoxPos.top + "px",
}));

function cropImage() {
  const img = cropperRef.value?.querySelector("img") as HTMLImageElement;
  if (!img) return;

  const scaleX = img.naturalWidth / img.clientWidth;
  const scaleY = img.naturalHeight / img.clientHeight;

  const sx = cropBoxPos.left * scaleX;
  const sy = cropBoxPos.top * scaleY;
  const sw = cropBoxSize.width * scaleX;
  const sh = cropBoxSize.height * scaleY;

  const canvas = document.createElement("canvas");
  canvas.width = cropBoxSize.width;
  canvas.height = cropBoxSize.height;
  const ctx = canvas.getContext("2d");
  if (!ctx) return;

  ctx.drawImage(img, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
  croppedImage.value = canvas.toDataURL("image/png");
  emit('imageSelected', croppedImage.value);
}
</script>

<style scoped>
.upload-container {
  width: 100%;
  margin: 1rem auto;
  font-family: Arial, sans-serif;
  user-select: none;
}

.file-dropzone {
  border: 2px dashed var(--ds-info);
  border-radius: 8px;
  padding: 2rem 1rem;
  text-align: center;
  color: var(--ds-info);
  cursor: pointer;
  transition: background-color 0.3s ease, border-color 0.3s ease;
}

.file-dropzone:hover {
  background-color: #e3f2fd;
  border-color: #1976d2;
}

.file-input {
  display: none;
}

.upload-icon {
  font-size: 3rem;
  margin-bottom: 0.5rem;
}

.upload-text {
  font-weight: 500;
}

.upload-error {
  margin: 0.75rem 0 0;
  color: var(--ds-primary-hover);
  font-size: 0.875rem;
}

.image-cropper {
  position: relative;
  margin-top: 1rem;
  max-width: 400px;
  border: 1px solid var(--ds-border-strong);
  overflow: hidden;
  border-radius: 8px;
}

.image-preview {
  display: block;
  width: 100%;
  border-radius: 8px;
  user-select: none;
  pointer-events: none;
}

.crop-box {
  position: absolute;
  border: 2px solid var(--ds-info);
  background: rgba(33, 150, 243, 0.2);
  border-radius: 6px;
  cursor: move;
}

.resize-handle.br {
  position: absolute;
  width: 16px;
  height: 16px;
  right: -8px;
  bottom: -8px;
  background: var(--ds-info);
  border-radius: 50%;
  cursor: se-resize;
}

.cropped-preview {
  margin-top: 1rem;
  text-align: center;
}

.cropped-preview img {
  border-radius: 50%;
  border: 2px solid var(--ds-info);
  width: 200px;
  height: 200px;
  object-fit: cover;
}

.crop-button {
  margin-top: 1rem;
  background-color: var(--ds-info);
  color: white;
  padding: 0.6rem 1.2rem;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: background-color 0.3s ease;
}

.crop-button:hover {
  background-color: #1976d2;
}
</style>
