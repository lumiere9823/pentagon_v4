import { nextTick, onMounted, onUnmounted, ref, type Ref, watch } from 'vue';

export interface UseModalFocusOptions {
    onClose?: () => void;
    initialFocusSelector?: string;
    closeOnEscape?: boolean;
}

export function useModalFocus(
    isOpen: Ref<boolean>,
    modalRef: Ref<HTMLElement | null>,
    options: UseModalFocusOptions = {},
) {
    const { closeOnEscape = true, onClose, initialFocusSelector } = options;
    const previousActiveElement = ref<HTMLElement | null>(null);

    const getFocusableElements = (container: HTMLElement): HTMLElement[] => {
        const selector = [
            'a[href]',
            'button:not([disabled])',
            'input:not([disabled])',
            'textarea:not([disabled])',
            'select:not([disabled])',
            '[tabindex]:not([tabindex="-1"])',
        ].join(', ');
        const elements = Array.from(
            container.querySelectorAll<HTMLElement>(selector),
        );
        return elements.filter((el) => {
            return (
                el.offsetWidth > 0 ||
                el.offsetHeight > 0 ||
                el.getClientRects().length > 0 ||
                // fallback for testing environments (e.g. happy-dom without layout geometry)
                el.style.display !== 'none'
            );
        });
    };

    const handleKeyDown = (e: KeyboardEvent) => {
        if (!isOpen.value || !modalRef.value) return;

        if (e.key === 'Escape' && closeOnEscape) {
            e.preventDefault();
            e.stopPropagation();
            onClose?.();
            return;
        }

        if (e.key === 'Tab') {
            const focusables = getFocusableElements(modalRef.value);
            if (focusables.length === 0) {
                e.preventDefault();
                return;
            }

            const first = focusables[0];
            const last = focusables[focusables.length - 1];

            if (e.shiftKey) {
                if (
                    document.activeElement === first ||
                    !modalRef.value.contains(document.activeElement)
                ) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (
                    document.activeElement === last ||
                    !modalRef.value.contains(document.activeElement)
                ) {
                    e.preventDefault();
                    first.focus();
                }
            }
        }
    };

    watch(
        () => isOpen.value,
        async (open) => {
            if (open) {
                if (typeof document !== 'undefined') {
                    previousActiveElement.value =
                        document.activeElement as HTMLElement | null;
                }
                await nextTick();
                if (modalRef.value) {
                    if (initialFocusSelector) {
                        const target =
                            modalRef.value.querySelector<HTMLElement>(
                                initialFocusSelector,
                            );
                        if (target) {
                            target.focus();
                            return;
                        }
                    }
                    const focusables = getFocusableElements(modalRef.value);
                    if (focusables.length > 0) {
                        focusables[0].focus();
                    } else {
                        modalRef.value.focus();
                    }
                }
            } else {
                await nextTick();
                if (
                    previousActiveElement.value &&
                    typeof previousActiveElement.value.focus === 'function' &&
                    document.body.contains(previousActiveElement.value)
                ) {
                    previousActiveElement.value.focus();
                }
                previousActiveElement.value = null;
            }
        },
    );

    onMounted(() => {
        if (typeof window !== 'undefined') {
            window.addEventListener('keydown', handleKeyDown, true);
        }
    });

    onUnmounted(() => {
        if (typeof window !== 'undefined') {
            window.removeEventListener('keydown', handleKeyDown, true);
        }
        if (
            previousActiveElement.value &&
            typeof previousActiveElement.value.focus === 'function' &&
            document.body.contains(previousActiveElement.value)
        ) {
            previousActiveElement.value.focus();
        }
    });

    return {
        previousActiveElement,
        getFocusableElements,
    };
}
