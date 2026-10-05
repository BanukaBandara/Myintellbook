import { defineStore } from 'pinia';

export const useLoadingStore = defineStore('loading', {
    state: () => ({
        isLoading: false,
    }),
    actions: {
        loadingStart() {
        this.isLoading = true;
        },

        loadingStop() {
        this.isLoading = false;
        }

    },
    getters: {
        isLoadingState: (state) => state.isLoading,
    }
});