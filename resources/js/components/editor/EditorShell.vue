<script setup lang="ts">
import { useEditorState } from '@/composables/useEditorState';
import ComponentLibrary from './ComponentLibrary.vue';
import EditorCanvas from './EditorCanvas.vue';
import EditorToolbar from './EditorToolbar.vue';
import InspectorPanel from './InspectorPanel.vue';
import type { ViewportMode } from '@/types/document';

interface WebsiteSummary {
    id: number;
    name: string;
    slug: string;
}

interface PageSummary {
    id: number;
    title: string;
    slug: string;
    status: 'draft' | 'published' | 'archived' | string;
    updated_at?: string | null;
}

const props = defineProps<{
    website: WebsiteSummary;
    page: PageSummary;
    userRole: string;
    can: {
        edit: boolean;
        delete: boolean;
    };
}>();

const {
    viewport,
    leftSidebarOpen,
    rightPanelOpen,
    setViewport,
    toggleLeftSidebar,
    toggleRightPanel,
} = useEditorState();
</script>

<template>
    <div
        class="flex h-screen w-screen flex-col overflow-hidden bg-background text-foreground antialiased"
    >
        <!-- Top Toolbar -->
        <EditorToolbar
            :website="website"
            :page="page"
            :viewport="viewport"
            :left-sidebar-open="leftSidebarOpen"
            :right-panel-open="rightPanelOpen"
            :user-role="userRole"
            :can-edit="can.edit"
            @update:viewport="setViewport"
            @toggle-left-sidebar="toggleLeftSidebar"
            @toggle-right-panel="toggleRightPanel"
        />

        <!-- Main Body: 3-column Layout (Components, Canvas, Inspector) -->
        <div class="relative flex flex-1 overflow-hidden">
            <!-- Left Sidebar: Component Library -->
            <transition
                enter-active-class="transition-transform duration-200 ease-out"
                enter-from-class="-translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform duration-150 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="-translate-x-full"
            >
                <ComponentLibrary v-show="leftSidebarOpen" />
            </transition>

            <!-- Center: Editor Canvas Frame -->
            <EditorCanvas :viewport="viewport" :page-title="page.title" />

            <!-- Right Panel: Inspector -->
            <transition
                enter-active-class="transition-transform duration-200 ease-out"
                enter-from-class="translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform duration-150 ease-in"
                leave-from-class="translate-x-0"
                leave-to-class="translate-x-full"
            >
                <InspectorPanel v-show="rightPanelOpen" />
            </transition>
        </div>
    </div>
</template>
