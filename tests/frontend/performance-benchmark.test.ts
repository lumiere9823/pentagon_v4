import { describe, expect, it } from 'vitest';
import { useDocumentState } from '@/composables/useDocumentState';
import { useEditorHistory } from '@/composables/useEditorHistory';
import {
    countNodesInTree,
    generateBenchmarkDocument,
} from './fixtures/benchmarkDocuments';

interface LatencyStats {
    p50: number;
    p95: number;
    avg: number;
    min: number;
    max: number;
}

function calculateStats(samples: number[]): LatencyStats {
    const sorted = [...samples].sort((a, b) => a - b);
    const p50Index = Math.floor(sorted.length * 0.5);
    const p95Index = Math.min(
        sorted.length - 1,
        Math.floor(sorted.length * 0.95),
    );
    const sum = sorted.reduce((acc, v) => acc + v, 0);

    return {
        p50: Number(sorted[p50Index].toFixed(3)),
        p95: Number(sorted[p95Index].toFixed(3)),
        avg: Number((sum / sorted.length).toFixed(3)),
        min: Number(sorted[0].toFixed(3)),
        max: Number(sorted[sorted.length - 1].toFixed(3)),
    };
}

describe('Package 4 / Phase 4 R-21: Performance Benchmark Harness (10 / 100 / 250 nodes)', () => {
    describe('Document Fixture Integrity & Node Counts', () => {
        it.each([10, 100, 250] as const)(
            'generates valid document with exactly %i nodes',
            (targetCount) => {
                const doc = generateBenchmarkDocument(targetCount);
                expect(doc.schemaVersion).toBe(1);
                expect(doc.root.type).toBe('Section');
                const counted = countNodesInTree(doc.root);
                expect(counted).toBe(targetCount);

                const { countNodes } = useDocumentState(doc);
                expect(countNodes()).toBe(targetCount);
            },
        );
    });

    describe('Tree Initialization & State Hydration Benchmarks', () => {
        it.each([10, 100, 250] as const)(
            'measures hydration latency for %i nodes',
            (targetCount) => {
                const samples: number[] = [];
                const doc = generateBenchmarkDocument(targetCount);

                for (let i = 0; i < 20; i++) {
                    const start = performance.now();
                    const state = useDocumentState(doc);
                    state.countNodes();
                    const end = performance.now();
                    samples.push(end - start);
                }

                const stats = calculateStats(samples);
                // Hydration should complete well under 25ms even at max capacity
                expect(stats.p50).toBeLessThan(25);
            },
        );
    });

    describe('Prop Mutation Benchmarks across 10, 100, and 250 node trees', () => {
        it.each([10, 100, 250] as const)(
            'measures updateNodeProps latency for %i nodes',
            (targetCount) => {
                const doc = generateBenchmarkDocument(targetCount);
                const { document, updateNodeProps, selectNode } =
                    useDocumentState(doc);

                // Find a heading node
                const targetNode = doc.root.children![0].children![0];
                selectNode(targetNode.id);

                const samples: number[] = [];
                for (let i = 0; i < 30; i++) {
                    const start = performance.now();
                    const res = updateNodeProps(targetNode.id, {
                        text: `Updated Text Iteration ${i}`,
                    });
                    const end = performance.now();
                    expect(res.success).toBe(true);
                    samples.push(end - start);
                }

                const stats = calculateStats(samples);
                // Prop mutations should be sub-millisecond p50
                expect(stats.p50).toBeLessThan(10);
            },
        );
    });

    describe('History Snapshot, Undo, and Redo Latency (R-21 / R-23 Profile)', () => {
        it.each([10, 100, 250] as const)(
            'measures snapshot capture, undo, and redo latency for %i nodes',
            (targetCount) => {
                const initialDoc = generateBenchmarkDocument(targetCount);
                const { document, setDocument, updateNodeProps } =
                    useDocumentState(initialDoc);

                const {
                    pushSnapshot,
                    undo,
                    redo,
                    canUndo,
                    canRedo,
                    past,
                    future,
                } = useEditorHistory(
                    () => document.value,
                    (restored) => setDocument(restored),
                    { maxSnapshots: 30 },
                );

                const targetNode = initialDoc.root.children![0].children![0];
                const snapshotSamples: number[] = [];
                const undoSamples: number[] = [];
                const redoSamples: number[] = [];

                // 1. Record 15 discrete snapshots
                for (let i = 0; i < 15; i++) {
                    const beforeDoc = JSON.parse(
                        JSON.stringify(document.value),
                    );
                    updateNodeProps(targetNode.id, { text: `Edit ${i}` });

                    const startSnap = performance.now();
                    pushSnapshot(beforeDoc);
                    const endSnap = performance.now();
                    snapshotSamples.push(endSnap - startSnap);
                }

                expect(canUndo.value).toBe(true);
                expect(past.value.length).toBe(15);

                // 2. Measure 15 Undo operations
                for (let i = 0; i < 15; i++) {
                    const startUndo = performance.now();
                    const res = undo();
                    const endUndo = performance.now();
                    expect(res).toBe(true);
                    undoSamples.push(endUndo - startUndo);
                }

                expect(canUndo.value).toBe(false);
                expect(canRedo.value).toBe(true);

                // 3. Measure 15 Redo operations
                for (let i = 0; i < 15; i++) {
                    const startRedo = performance.now();
                    const res = redo();
                    const endRedo = performance.now();
                    expect(res).toBe(true);
                    redoSamples.push(endRedo - startRedo);
                }

                expect(canRedo.value).toBe(false);

                const snapStats = calculateStats(snapshotSamples);
                const undoStats = calculateStats(undoSamples);
                const redoStats = calculateStats(redoSamples);

                // Ensure fast undo/redo execution even at 250 nodes (p50 < 15ms)
                expect(snapStats.p50).toBeLessThan(15);
                expect(undoStats.p50).toBeLessThan(20);
                expect(redoStats.p50).toBeLessThan(20);
            },
        );
    });
});
