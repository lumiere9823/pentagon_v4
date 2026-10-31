import { computed, ref, type Ref } from 'vue';
import {
    CONTAINER_COMPONENT_TYPES,
    DEFAULT_COMPONENT_PROPS,
    DOCUMENT_LIMITS,
    ROOT_COMPONENT_TYPES,
    type CoreComponentType,
    type DocumentNode,
    type DocumentSchema,
    type InsertPosition,
    type TreeMutationResult,
} from '@/types/document';

export function createNodeId(type: string): string {
    const cleanType = type
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '')
        .slice(0, 8);
    const rand = Math.random().toString(36).substring(2, 8);
    const time = Date.now().toString(36).slice(-4);
    return `node_${cleanType}_${rand}${time}`;
}

function createUniqueNodeId(type: string, usedIds: Set<string>): string {
    const generatedId = createNodeId(type);
    if (!usedIds.has(generatedId)) return generatedId;

    let suffix = 1;
    while (true) {
        const suffixText = `_${suffix.toString(36)}`;
        const candidate = `${generatedId.slice(
            0,
            64 - suffixText.length,
        )}${suffixText}`;
        if (!usedIds.has(candidate)) return candidate;
        suffix += 1;
    }
}

export function createDefaultDocument(): DocumentSchema {
    return {
        schemaVersion: 1,
        root: {
            id: 'root_section_default',
            type: 'Section',
            props: {
                padding: 'medium',
                background: 'transparent',
            },
            children: [
                {
                    id: 'node_container_default',
                    type: 'Container',
                    props: {
                        maxWidth: 'lg',
                        centered: true,
                    },
                    children: [],
                },
            ],
        },
        pageSettings: {
            title: 'New Page',
            description: '',
        },
    };
}

export interface NodeSearchResult {
    node: DocumentNode;
    parent: DocumentNode | null;
    index: number;
    depth: number;
}

function validateDocumentTree(candidate: DocumentSchema): string | null {
    if (candidate.schemaVersion !== 1) {
        return 'Replacement rejected: unsupported document schema version.';
    }

    if (!candidate.root || typeof candidate.root !== 'object') {
        return 'Replacement rejected: document root is missing or invalid.';
    }

    if (candidate.root.hidden === true) {
        return 'Replacement rejected: the root node cannot be hidden.';
    }

    const seenIds = new Set<string>();
    const seenNodes = new WeakSet<object>();
    let totalNodes = 0;

    function validateNode(node: DocumentNode, depth: number): string | null {
        if (!node || typeof node !== 'object') {
            return 'Replacement rejected: every tree entry must be a node object.';
        }
        if (seenNodes.has(node)) {
            return 'Replacement rejected: the document tree contains a cycle or shared node.';
        }
        seenNodes.add(node);

        totalNodes += 1;
        if (totalNodes > DOCUMENT_LIMITS.MAX_TOTAL_NODES) {
            return `Replacement rejected: maximum ${DOCUMENT_LIMITS.MAX_TOTAL_NODES} total nodes allowed.`;
        }
        if (depth > DOCUMENT_LIMITS.MAX_TREE_DEPTH) {
            return `Replacement rejected: maximum tree depth is ${DOCUMENT_LIMITS.MAX_TREE_DEPTH}.`;
        }
        if (
            typeof node.id !== 'string' ||
            !DOCUMENT_LIMITS.NODE_ID_REGEX.test(node.id)
        ) {
            return `Replacement rejected: node IDs must match the document ID contract.`;
        }
        if (seenIds.has(node.id)) {
            return 'Replacement rejected: node IDs must be unique.';
        }
        seenIds.add(node.id);

        if (node.locked !== undefined && typeof node.locked !== 'boolean') {
            return `Replacement rejected: 'locked' must be a boolean.`;
        }

        if (node.hidden !== undefined && typeof node.hidden !== 'boolean') {
            return `Replacement rejected: 'hidden' must be a boolean.`;
        }

        if (node.label !== undefined && typeof node.label !== 'string') {
            return `Replacement rejected: 'label' must be a string.`;
        }
        if (
            typeof node.label === 'string' &&
            node.label.trim().length > DOCUMENT_LIMITS.MAX_LABEL_LENGTH
        ) {
            return `Replacement rejected: 'label' cannot exceed ${DOCUMENT_LIMITS.MAX_LABEL_LENGTH} characters.`;
        }

        if (
            typeof node.type !== 'string' ||
            !Object.prototype.hasOwnProperty.call(
                DEFAULT_COMPONENT_PROPS,
                node.type,
            )
        ) {
            return `Replacement rejected: '${String(node.type)}' is not a valid component type.`;
        }

        const children = node.children ?? [];
        if (!Array.isArray(children)) {
            return `Replacement rejected: children for '${node.id}' must be an array.`;
        }
        if (!CONTAINER_COMPONENT_TYPES.has(node.type) && children.length > 0) {
            return `Replacement rejected: leaf component '${node.type}' cannot have children.`;
        }
        if (children.length > DOCUMENT_LIMITS.MAX_CHILDREN_PER_NODE) {
            return `Replacement rejected: maximum ${DOCUMENT_LIMITS.MAX_CHILDREN_PER_NODE} direct children allowed.`;
        }

        for (const child of children) {
            const error = validateNode(child, depth + 1);
            if (error) return error;
        }

        return null;
    }

    if (!ROOT_COMPONENT_TYPES.has(candidate.root.type)) {
        return `Replacement rejected: '${String(candidate.root.type)}' is not eligible to be the document root.`;
    }

    return validateNode(candidate.root, 1);
}

export function useDocumentState(initialDoc?: DocumentSchema) {
    const document: Ref<DocumentSchema> = ref(
        initialDoc ?? createDefaultDocument(),
    );
    const selectedNodeId: Ref<string | null> = ref(null);
    const hoveredNodeId: Ref<string | null> = ref(null);
    const lastError: Ref<string | null> = ref(null);

    // Tree Search & Traversal
    function findNode(
        id: string,
        current: DocumentNode = document.value.root,
        parent: DocumentNode | null = null,
        index: number = 0,
        depth: number = 1,
    ): NodeSearchResult | null {
        if (current.id === id) {
            return { node: current, parent, index, depth };
        }

        if (current.children && current.children.length > 0) {
            for (let i = 0; i < current.children.length; i++) {
                const res = findNode(
                    id,
                    current.children[i],
                    current,
                    i,
                    depth + 1,
                );
                if (res) return res;
            }
        }

        return null;
    }

    function countNodes(current: DocumentNode = document.value.root): number {
        let count = 1;
        if (current.children && current.children.length > 0) {
            for (const child of current.children) {
                count += countNodes(child);
            }
        }
        return count;
    }

    function getMaxDepth(
        current: DocumentNode = document.value.root,
        depth: number = 1,
    ): number {
        if (!current.children || current.children.length === 0) {
            return depth;
        }
        let max = depth;
        for (const child of current.children) {
            max = Math.max(max, getMaxDepth(child, depth + 1));
        }
        return max;
    }

    function collectNodeIds(
        current: DocumentNode = document.value.root,
        ids: Set<string> = new Set(),
    ): Set<string> {
        ids.add(current.id);
        for (const child of current.children ?? []) {
            collectNodeIds(child, ids);
        }
        return ids;
    }

    // Cycle Prevention: check if targetId is inside searchNodeId's subtree
    function isDescendant(
        searchNodeId: string,
        potentialAncestorId: string,
    ): boolean {
        if (searchNodeId === potentialAncestorId) return true;
        const ancestorSearchResult = findNode(potentialAncestorId);
        if (!ancestorSearchResult) return false;

        function checkChildren(node: DocumentNode): boolean {
            if (node.id === searchNodeId) return true;
            if (node.children) {
                for (const child of node.children) {
                    if (checkChildren(child)) return true;
                }
            }
            return false;
        }

        return checkChildren(ancestorSearchResult.node);
    }

    // Recursively clone a node and assign new unique IDs
    function cloneNodeWithNewIds(
        node: DocumentNode,
        usedIds: Set<string>,
    ): DocumentNode {
        const id = createUniqueNodeId(node.type, usedIds);
        usedIds.add(id);
        const cloned: DocumentNode = {
            id,
            type: node.type,
            props: JSON.parse(JSON.stringify(node.props)),
            styles: node.styles
                ? JSON.parse(JSON.stringify(node.styles))
                : undefined,
            children: node.children
                ? node.children.map((child) =>
                      cloneNodeWithNewIds(child, usedIds),
                  )
                : undefined,
            locked: node.locked ? true : undefined,
            hidden: node.hidden ? true : undefined,
            label: node.label ? String(node.label).trim() : undefined,
        };
        return cloned;
    }

    // Node locking helpers
    function isNodeLocked(nodeId: string): boolean {
        const search = findNode(nodeId);
        return Boolean(search?.node.locked);
    }

    function getProtectingAncestor(nodeId: string): DocumentNode | null {
        let currentSearch = findNode(nodeId);
        if (!currentSearch) return null;

        while (currentSearch.parent) {
            if (currentSearch.parent.locked) {
                return currentSearch.parent;
            }
            currentSearch = findNode(currentSearch.parent.id);
            if (!currentSearch) break;
        }

        return null;
    }

    function isNodeOrAncestorLocked(nodeId: string): boolean {
        let currentSearch = findNode(nodeId);
        if (!currentSearch) return false;

        if (currentSearch.node.locked) {
            return true;
        }

        return getProtectingAncestor(nodeId) !== null;
    }

    function hasLockedDescendant(node: DocumentNode): boolean {
        for (const child of node.children ?? []) {
            if (child.locked || hasLockedDescendant(child)) {
                return true;
            }
        }
        return false;
    }

    function setNodeLocked(
        nodeId: string,
        locked: boolean,
    ): TreeMutationResult {
        lastError.value = null;
        const search = findNode(nodeId);
        if (!search) {
            const error = `Node '${nodeId}' not found.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        const currentlyLocked = Boolean(search.node.locked);
        const targetLocked = Boolean(locked);

        if (currentlyLocked === targetLocked) {
            return { success: true, changed: false };
        }

        if (targetLocked) {
            search.node.locked = true;
        } else {
            delete search.node.locked;
        }

        return { success: true, changed: true };
    }

    // Node visibility helpers
    function isNodeHidden(nodeId: string): boolean {
        const search = findNode(nodeId);
        return Boolean(search?.node.hidden);
    }

    function getHidingAncestor(nodeId: string): DocumentNode | null {
        let currentSearch = findNode(nodeId);
        if (!currentSearch) return null;

        while (currentSearch.parent) {
            if (currentSearch.parent.hidden) {
                return currentSearch.parent;
            }
            currentSearch = findNode(currentSearch.parent.id);
            if (!currentSearch) break;
        }

        return null;
    }

    function isNodeOrAncestorHidden(nodeId: string): boolean {
        const search = findNode(nodeId);
        if (!search) return false;

        if (search.node.hidden) {
            return true;
        }

        return getHidingAncestor(nodeId) !== null;
    }

    function setNodeHidden(
        nodeId: string,
        hidden: boolean,
    ): TreeMutationResult {
        lastError.value = null;
        if (nodeId === document.value.root.id) {
            const error = 'The root node cannot be hidden.';
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        const search = findNode(nodeId);
        if (!search) {
            const error = `Node '${nodeId}' not found.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        if (isNodeOrAncestorLocked(nodeId)) {
            const error =
                'Cannot change visibility of locked node or node inside a locked container.';
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        const currentlyHidden = Boolean(search.node.hidden);
        const targetHidden = Boolean(hidden);

        if (currentlyHidden === targetHidden) {
            return { success: true, changed: false };
        }

        if (targetHidden) {
            search.node.hidden = true;
        } else {
            delete search.node.hidden;
        }

        return { success: true, changed: true };
    }

    // Node custom label helpers
    function setNodeLabel(
        nodeId: string,
        label: string | null | undefined,
    ): TreeMutationResult {
        lastError.value = null;
        const search = findNode(nodeId);
        if (!search) {
            const error = `Node '${nodeId}' not found.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        if (isNodeOrAncestorLocked(nodeId)) {
            const error =
                'Cannot rename locked node or node inside a locked container.';
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        if (
            label !== null &&
            label !== undefined &&
            typeof label !== 'string'
        ) {
            const error = 'Node label must be a string.';
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        const trimmed =
            typeof label === 'string'
                ? label.trim().slice(0, DOCUMENT_LIMITS.MAX_LABEL_LENGTH)
                : '';
        const current = search.node.label ?? '';

        if (trimmed === current) {
            return { success: true, changed: false };
        }

        if (trimmed !== '') {
            search.node.label = trimmed;
        } else {
            delete search.node.label;
        }

        return { success: true, changed: true };
    }

    // Selection management
    function selectNode(id: string | null): void {
        selectedNodeId.value = id;
    }

    function clearSelection(): void {
        selectedNodeId.value = null;
    }

    function setHoveredNode(id: string | null): void {
        hoveredNodeId.value = id;
    }

    const selectedNode = computed(() => {
        if (!selectedNodeId.value) return null;
        return findNode(selectedNodeId.value)?.node ?? null;
    });

    // Mutations
    function insertNode(
        type: CoreComponentType,
        targetId?: string | null,
        position: InsertPosition = 'inside',
        customProps?: Record<string, unknown>,
        targetIndex?: number,
    ): TreeMutationResult {
        lastError.value = null;

        // Check total node limit
        const totalNodes = countNodes();
        if (totalNodes >= DOCUMENT_LIMITS.MAX_TOTAL_NODES) {
            const error = `Document limit reached: maximum ${DOCUMENT_LIMITS.MAX_TOTAL_NODES} nodes allowed.`;
            lastError.value = error;
            return { success: false, error };
        }

        // Determine destination container and index
        let destContainer: DocumentNode;
        let insertIndex: number;
        let targetDepth: number;

        if (!targetId) {
            // Default: append into root
            destContainer = document.value.root;
            const childCount = destContainer.children?.length ?? 0;
            insertIndex =
                typeof targetIndex === 'number' && !isNaN(targetIndex)
                    ? Math.max(0, Math.min(targetIndex, childCount))
                    : childCount;
            targetDepth = 1;
        } else {
            const search = findNode(targetId);
            if (!search) {
                const error = `Target node '${targetId}' not found.`;
                lastError.value = error;
                return { success: false, error };
            }

            if (position === 'inside') {
                if (!CONTAINER_COMPONENT_TYPES.has(search.node.type)) {
                    const error = `Cannot insert inside '${search.node.type}' because it is a leaf component.`;
                    lastError.value = error;
                    return { success: false, error };
                }
                destContainer = search.node;
                const childCount = destContainer.children?.length ?? 0;
                insertIndex =
                    typeof targetIndex === 'number' && !isNaN(targetIndex)
                        ? Math.max(0, Math.min(targetIndex, childCount))
                        : childCount;
                targetDepth = search.depth;
            } else {
                // 'before' or 'after'
                if (!search.parent) {
                    const error = `Cannot insert siblings relative to the Root node.`;
                    lastError.value = error;
                    return { success: false, error };
                }
                destContainer = search.parent;
                if (!destContainer.children) destContainer.children = [];
                insertIndex =
                    position === 'before' ? search.index : search.index + 1;
                targetDepth = search.depth - 1;
            }
        }

        // Check destination container lock
        if (isNodeOrAncestorLocked(destContainer.id)) {
            const error = `Cannot insert into locked container '${destContainer.id}'.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        // Check container children limit
        if (
            destContainer.children &&
            destContainer.children.length >=
                DOCUMENT_LIMITS.MAX_CHILDREN_PER_NODE
        ) {
            const error = `Container limit reached: maximum ${DOCUMENT_LIMITS.MAX_CHILDREN_PER_NODE} child nodes per container.`;
            lastError.value = error;
            return { success: false, error };
        }

        // Check tree depth limit
        if (targetDepth + 1 > DOCUMENT_LIMITS.MAX_TREE_DEPTH) {
            const error = `Tree depth limit reached: maximum ${DOCUMENT_LIMITS.MAX_TREE_DEPTH} levels allowed.`;
            lastError.value = error;
            return { success: false, error };
        }

        // Create new node with valid default props
        const defaultProps = DEFAULT_COMPONENT_PROPS[type] || {};
        const mergedProps = { ...defaultProps, ...customProps };

        const newNode: DocumentNode = {
            id: createUniqueNodeId(type, collectNodeIds()),
            type,
            props: JSON.parse(JSON.stringify(mergedProps)),
            children: CONTAINER_COMPONENT_TYPES.has(type) ? [] : undefined,
        };

        if (!destContainer.children) destContainer.children = [];
        destContainer.children.splice(insertIndex, 0, newNode);

        // Auto-select newly inserted node
        selectedNodeId.value = newNode.id;

        return { success: true };
    }

    function updateNodeProps(
        nodeId: string,
        newProps: Record<string, unknown>,
    ): TreeMutationResult {
        lastError.value = null;
        const search = findNode(nodeId);
        if (!search) {
            const error = `Node '${nodeId}' not found.`;
            lastError.value = error;
            return { success: false, error };
        }

        if (isNodeOrAncestorLocked(nodeId)) {
            const error = `Cannot update props of locked node '${nodeId}'.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        search.node.props = {
            ...search.node.props,
            ...JSON.parse(JSON.stringify(newProps)),
        };

        return { success: true };
    }

    function updateNodeStyle(
        nodeId: string,
        viewport: 'tablet' | 'mobile',
        propKey: string,
        value: unknown,
    ): TreeMutationResult {
        lastError.value = null;
        const search = findNode(nodeId);
        if (!search) {
            const error = `Node '${nodeId}' not found.`;
            lastError.value = error;
            return { success: false, error };
        }

        if (isNodeOrAncestorLocked(nodeId)) {
            const error = `Cannot update style of locked node '${nodeId}'.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        if (!search.node.styles) {
            search.node.styles = {};
        }
        if (!search.node.styles[viewport]) {
            search.node.styles[viewport] = {};
        }

        search.node.styles[viewport][propKey] = JSON.parse(
            JSON.stringify(value),
        );

        return { success: true };
    }

    function resetNodeStyle(
        nodeId: string,
        viewport: 'tablet' | 'mobile',
        propKey: string,
    ): TreeMutationResult {
        lastError.value = null;
        const search = findNode(nodeId);
        if (!search) {
            const error = `Node '${nodeId}' not found.`;
            lastError.value = error;
            return { success: false, error };
        }

        if (isNodeOrAncestorLocked(nodeId)) {
            const error = `Cannot reset style of locked node '${nodeId}'.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        if (search.node.styles && search.node.styles[viewport]) {
            delete search.node.styles[viewport][propKey];

            if (Object.keys(search.node.styles[viewport]).length === 0) {
                delete search.node.styles[viewport];
            }

            if (Object.keys(search.node.styles).length === 0) {
                delete search.node.styles;
            }
        }

        return { success: true };
    }

    function deleteNode(nodeId: string): TreeMutationResult {
        lastError.value = null;
        if (nodeId === document.value.root.id) {
            const error = 'The root node cannot be deleted.';
            lastError.value = error;
            return { success: false, error };
        }

        const search = findNode(nodeId);
        if (!search || !search.parent || !search.parent.children) {
            const error = `Node '${nodeId}' or its parent container not found.`;
            lastError.value = error;
            return { success: false, error };
        }

        if (isNodeOrAncestorLocked(nodeId)) {
            const error = `Cannot delete locked node '${nodeId}'.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        if (hasLockedDescendant(search.node)) {
            const error = `Cannot delete container '${nodeId}' because it contains locked elements.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        const shouldClearSelection =
            selectedNodeId.value === nodeId ||
            (selectedNodeId.value !== null &&
                isDescendant(selectedNodeId.value, nodeId));

        search.parent.children.splice(search.index, 1);

        if (shouldClearSelection) {
            selectedNodeId.value = null;
        }

        return { success: true };
    }

    function duplicateNode(nodeId: string): TreeMutationResult {
        lastError.value = null;
        if (nodeId === document.value.root.id) {
            const error = 'The root node cannot be duplicated.';
            lastError.value = error;
            return { success: false, error };
        }

        const search = findNode(nodeId);
        if (!search || !search.parent || !search.parent.children) {
            const error = `Node '${nodeId}' not found.`;
            lastError.value = error;
            return { success: false, error };
        }

        if (isNodeOrAncestorLocked(nodeId)) {
            const error = `Cannot duplicate locked node '${nodeId}'.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        // Count nodes in clone to ensure total nodes limit is respected
        const cloneSubtree = cloneNodeWithNewIds(search.node, collectNodeIds());
        const subtreeNodeCount = countNodes(cloneSubtree);
        const currentTotal = countNodes();

        if (currentTotal + subtreeNodeCount > DOCUMENT_LIMITS.MAX_TOTAL_NODES) {
            const error = `Cannot duplicate: would exceed total document limit of ${DOCUMENT_LIMITS.MAX_TOTAL_NODES} nodes.`;
            lastError.value = error;
            return { success: false, error };
        }

        if (
            search.parent.children.length >=
            DOCUMENT_LIMITS.MAX_CHILDREN_PER_NODE
        ) {
            const error = `Cannot duplicate: container already at maximum of ${DOCUMENT_LIMITS.MAX_CHILDREN_PER_NODE} children.`;
            lastError.value = error;
            return { success: false, error };
        }

        search.parent.children.splice(search.index + 1, 0, cloneSubtree);
        selectedNodeId.value = cloneSubtree.id;

        return { success: true };
    }

    function moveNodeRelative(
        nodeId: string,
        direction: 'up' | 'down',
    ): TreeMutationResult {
        lastError.value = null;
        if (nodeId === document.value.root.id) {
            const error = 'The root node cannot be moved.';
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        const search = findNode(nodeId);
        if (!search || !search.parent || !search.parent.children) {
            const error = `Node '${nodeId}' not found.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        if (isNodeOrAncestorLocked(nodeId)) {
            const error = `Cannot move locked node '${nodeId}'.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        const siblings = search.parent.children;
        const currentIndex = search.index;

        if (direction === 'up') {
            if (currentIndex === 0) {
                return { success: true, changed: false }; // Already at top
            }
            const temp = siblings[currentIndex];
            siblings[currentIndex] = siblings[currentIndex - 1];
            siblings[currentIndex - 1] = temp;
        } else {
            if (currentIndex === siblings.length - 1) {
                return { success: true, changed: false }; // Already at bottom
            }
            const temp = siblings[currentIndex];
            siblings[currentIndex] = siblings[currentIndex + 1];
            siblings[currentIndex + 1] = temp;
        }

        return { success: true, changed: true };
    }

    function moveNode(
        nodeId: string,
        targetParentId: string,
        targetIndex: number,
    ): TreeMutationResult {
        lastError.value = null;
        if (nodeId === document.value.root.id) {
            const error = 'The root node cannot be moved.';
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        if (isNodeOrAncestorLocked(nodeId)) {
            const error = `Cannot move locked node '${nodeId}'.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        if (isNodeOrAncestorLocked(targetParentId)) {
            const error = `Cannot move into locked container '${targetParentId}'.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        // Cycle prevention: target parent cannot be the node itself or inside the node
        if (isDescendant(targetParentId, nodeId)) {
            const error =
                'Cannot move a container into itself or its own descendants.';
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        const search = findNode(nodeId);
        if (!search || !search.parent || !search.parent.children) {
            const error = `Source node '${nodeId}' not found.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        const targetSearch = findNode(targetParentId);
        if (!targetSearch) {
            const error = `Target container '${targetParentId}' not found.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        if (!CONTAINER_COMPONENT_TYPES.has(targetSearch.node.type)) {
            const error = `Target '${targetSearch.node.type}' cannot accept children because it is a leaf component.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        const isSameParent = search.parent.id === targetSearch.node.id;
        const targetChildCount = targetSearch.node.children?.length ?? 0;
        if (
            !isSameParent &&
            targetChildCount >= DOCUMENT_LIMITS.MAX_CHILDREN_PER_NODE
        ) {
            const error = `Move rejected: target container already has the maximum of ${DOCUMENT_LIMITS.MAX_CHILDREN_PER_NODE} children.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        // Check depth limit
        const movingSubtreeHeight = getMaxDepth(search.node);
        if (
            targetSearch.depth + movingSubtreeHeight >
            DOCUMENT_LIMITS.MAX_TREE_DEPTH
        ) {
            const error = `Move rejected: would exceed maximum tree depth of ${DOCUMENT_LIMITS.MAX_TREE_DEPTH}.`;
            lastError.value = error;
            return { success: false, changed: false, error };
        }

        if (!targetSearch.node.children) targetSearch.node.children = [];

        // For same-parent moves, clamp against array length after removal to check if position is unchanged
        const maxIndexAfterRemoval = isSameParent
            ? Math.max(0, targetSearch.node.children.length - 1)
            : targetSearch.node.children.length;
        const clampedIndex = Math.max(
            0,
            Math.min(targetIndex, maxIndexAfterRemoval),
        );

        // No-op check: if same parent and clampedIndex === search.index, order is unchanged
        if (isSameParent && clampedIndex === search.index) {
            return { success: true, changed: false };
        }

        // Remove from old parent
        search.parent.children.splice(search.index, 1);

        // Add to new parent at target index
        targetSearch.node.children.splice(clampedIndex, 0, search.node);

        return { success: true, changed: true };
    }

    // Replace entire document (used by undo/redo and revision restore)
    function setDocument(newDoc: DocumentSchema): TreeMutationResult {
        lastError.value = null;
        const validationError = validateDocumentTree(newDoc);
        if (validationError) {
            lastError.value = validationError;
            return { success: false, changed: false, error: validationError };
        }

        document.value = JSON.parse(JSON.stringify(newDoc));
        if (selectedNodeId.value && !findNode(selectedNodeId.value)) {
            selectedNodeId.value = null;
        }

        return { success: true, changed: true };
    }

    return {
        document,
        selectedNodeId,
        hoveredNodeId,
        selectedNode,
        lastError,
        findNode,
        countNodes,
        getMaxDepth,
        isDescendant,
        selectNode,
        clearSelection,
        setHoveredNode,
        insertNode,
        updateNodeProps,
        updateNodeStyle,
        resetNodeStyle,
        deleteNode,
        duplicateNode,
        moveNodeRelative,
        moveNode,
        isNodeLocked,
        isNodeOrAncestorLocked,
        getProtectingAncestor,
        hasLockedDescendant,
        setNodeLocked,
        isNodeHidden,
        getHidingAncestor,
        isNodeOrAncestorHidden,
        setNodeHidden,
        setNodeLabel,
        setDocument,
    };
}
