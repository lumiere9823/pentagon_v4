import type { Component } from 'vue';
import {
    Box,
    Columns,
    FileText,
    Grid3X3,
    Heading,
    HelpCircle,
    Image,
    Minus,
    MousePointerClick,
    MoveVertical,
    Sparkles,
    Square,
} from '@lucide/vue';
import type { CoreComponentType } from '@/types/document';

export const COMPONENT_ICONS: Record<CoreComponentType, Component> = {
    Section: Square,
    Container: Box,
    Heading: Heading,
    RichText: FileText,
    Image: Image,
    Button: MousePointerClick,
    Hero: Sparkles,
    Columns: Columns,
    CardGrid: Grid3X3,
    FAQ: HelpCircle,
    Spacer: MoveVertical,
    Divider: Minus,
};

export function getComponentIcon(type: string): Component {
    return (
        COMPONENT_ICONS[type as CoreComponentType] ||
        (Box as unknown as Component)
    );
}
