<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FormSubmissionAdminController extends Controller
{
    /**
     * Display a listing of form submissions for the website.
     */
    public function index(Request $request, Website $website): JsonResponse
    {
        Gate::authorize('view', $website);

        $query = $website->formSubmissions()
            ->with('page:id,title,slug')
            ->orderByDesc('created_at');

        if ($formName = $request->query('form_name')) {
            $query->where('form_name', trim((string) $formName));
        }

        if ($status = $request->query('status')) {
            $query->where('status', trim((string) $status));
        }

        $submissions = $query->paginate(25);

        return response()->json($submissions);
    }

    /**
     * Update the status of a form submission (e.g. read, archived, spam).
     */
    public function update(Request $request, Website $website, FormSubmission $formSubmission): JsonResponse
    {
        Gate::authorize('view', $website);

        if ($formSubmission->website_id !== $website->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:new,read,archived,spam',
        ]);

        $formSubmission->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'submission' => $formSubmission,
        ]);
    }

    /**
     * Delete a form submission.
     */
    public function destroy(Website $website, FormSubmission $formSubmission): JsonResponse
    {
        Gate::authorize('view', $website);

        if ($formSubmission->website_id !== $website->id) {
            abort(404);
        }

        $formSubmission->delete();

        return response()->json(['success' => true]);
    }
}
