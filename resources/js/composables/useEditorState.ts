import { ref } from 'vue';
import type { ComponentCategoryId } from '@/config/editor';
import type { ViewportMode } from '@/types/document';

export function useEditorState() {
    const viewport = ref<ViewportMode>('desktop');
    const leftSidebarOpen = ref<boolean>(true);
    const rightPanelOpen = ref<boolean>(true);
    const searchQuery = ref<string>('');
    const selectedCategory = ref<ComponentCategoryId>('all');

    const setViewport = (mode: ViewportMode) => {
        viewport.value = mode;
    };

    const toggleLeftSidebar = () => {
        leftSidebarOpen.value = !leftSidebarOpen.value;
    };

    const toggleRightPanel = () => {
        rightPanelOpen.value = !rightPanelOpen.value;
    };

    const resetFilters = () => {
        searchQuery.value = '';
        selectedCategory.value = 'all';
    };

    return {
        viewport,
        leftSidebarOpen,
        rightPanelOpen,
        searchQuery,
        selectedCategory,
        setViewport,
        toggleLeftSidebar,
        toggleRightPanel,
        resetFilters,
    };
}
