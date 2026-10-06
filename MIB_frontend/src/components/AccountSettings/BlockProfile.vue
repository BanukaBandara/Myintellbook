<template>
  <div class="bg-card p-4 mt-4 flex-grow-1 overflow-auto rounded-4 shadow-sm border border-light-subtle">
    <h5 class="mb-4">Blocking</h5>
    <div class="border border-1 rounded p-4">
      <h6>Block profiles</h6>
      <div class="d-flex justify-content-between">
        <p class="text-muted small">
          When you block someone, you won’t be able to see or interact with each other’s profiles, posts, comments, or messages. This doesn’t include shared groups, events, or apps where you both participate.
        If you’re currently connected, blocking will automatically remove any existing connections such as friends, followers, or likes.
        </p>
        <div><Button size="small" variant="outlined" @click="()=>{isShowList = true}">Edit</Button></div>
      </div>
      
    </div>
 <Dialog
    v-model:visible="isShowList"
    modal
    header="Block Profiles"
    :style="{ width: '35rem' }"
    class="p-fluid"
  >
    <!-- Info text -->
    <div class="mb-4">
      <p class="text-muted text-sm leading-relaxed">
        When you block someone, you won’t be able to view or interact with each other’s
        profiles, posts, comments, or messages. This doesn’t include shared groups, events,
        or apps where you both participate.
      </p>
      <p class="text-muted text-sm">
        If you’re currently connected, blocking will automatically remove any existing
        connections such as friends, followers, or likes.
      </p>
    </div>

    <!-- Add to block list -->
    <div class="flex justify-end mb-3">
      <Button
        icon="pi pi-plus"
        label="Add to Block List"
        class="p-button-sm p-button-outlined"
        @click="()=>{ showAdd = true}"
      />
    </div>

    <!-- Blocked users list -->
    <div v-if="!showAdd" class="space-y-3">
      <div
        v-for="user in blockedUsers"
        :key="user.id"
        class="d-flex items-center justify-content-between border rounded-lg p-3 hover:shadow-sm transition"
      >
        <div class="d-flex align-items-center gap-3">
          <Avatar :image="user.avatar" size="large" shape="circle" />
          <div>
            <div class="font-medium text-base">{{ user.name }}</div>
          </div>
        </div>
        <div>
          <Button
          
          label="Unblock"
          size="small"
          variant="outlined"
          />
        </div>
      </div>
    </div>

    <div v-if="showAdd" class="space-y-3 mt-4">
  <!-- Search bar -->
  <div class="flex items-center gap-2">
    <span class="p-input-icon-left w-full">
      <i class="pi pi-search" />
      <InputText
        placeholder="Search users to block..."
        class="w-full p-inputtext-sm"
      />
    </span>
  </div>

  <!-- Search results -->
  <div v-if="filteredUsers.length" class="mt-3 space-y-2">
    <!-- <div
      v-for="user in filteredUsers"
      :key="user.id"
      class="flex items-center justify-between border rounded-lg p-3 hover:bg-gray-50 transition"
    >
      <div class="flex items-center gap-3">
        <Avatar :image="user.avatar" size="large" shape="circle" />
        <div>
          <div class="font-medium text-base">{{ user.name }}</div>
          <div class="text-muted text-xs">{{ user.email }}</div>
        </div>
      </div>
      <Button
        label="Block"
        icon="pi pi-ban"
        class="p-button-sm p-button-danger"
        @click="blockUser(user)"
      />
    </div> -->
  </div>

  <div v-else class="text-center text-muted py-3">
    <i class="pi pi-user text-2xl mb-2 block"></i>
    <span>No users found</span>
  </div>
</div>

  </Dialog>
    
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import  InputText  from "primevue/inputtext";

// Example data

const blockedUsers = ref([
  {
    id: 1,
    name: "Emma Johnson",
    email: "emma.johnson@example.com",
    avatar: "https://randomuser.me/api/portraits/women/44.jpg",
  },
  {
    id: 2,
    name: "David Miller",
    email: "david.miller@example.com",
    avatar: "https://randomuser.me/api/portraits/men/46.jpg",
  },
]);
const isShowList = ref<boolean>(false);
const showAdd = ref<boolean>(false);
const filteredUsers = ref([]);

</script>

<style scoped>
.text-muted {
  color: #6c757d;
}
.space-y-3 > * + * {
  margin-top: 0.75rem;
}
</style>