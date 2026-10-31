export interface PublishGuardOptions {
    needsSave: boolean;
    saveDraft: () => Promise<boolean>;
    publish: () => Promise<void>;
}

export async function publishAfterDraftSave({
    needsSave,
    saveDraft,
    publish,
}: PublishGuardOptions): Promise<boolean> {
    if (needsSave && !(await saveDraft())) {
        return false;
    }

    await publish();

    return true;
}
