import { ref } from 'vue';
import type { ComponentCategoryId } from '@/config/editor';
import type { ViewportMode } from '@/types/document';

export function useEditorState() {
    const viewport = ref<ViewportMode>('desktop');
    const leftSidebarOpen = ref<boolean>(true);
    const rightPanelOpen = ref<boolean>(true);
    const activeLeftTab = ref<'components' | 'layers'>('components');
    const searchQuery = ref<string>('');
    const selectedCategory = ref<ComponentCategoryId>('all');

    const setViewport = (mode: ViewportMode) => {
        viewport.value = mode;
    };

    const toggleLeftSidebar = () => {
        leftSidebarOpen.value = !leftSidebarOpen.value;
    };

    const setActiveLeftTab = (tab: 'components' | 'layers') => {
        activeLeftTab.value = tab;
        if (!leftSidebarOpen.value) {
            leftSidebarOpen.value = true;
        }
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
        activeLeftTab,
        searchQuery,
        selectedCategory,
        setViewport,
        toggleLeftSidebar,
        setActiveLeftTab,
        toggleRightPanel,
        resetFilters,
    };
}
