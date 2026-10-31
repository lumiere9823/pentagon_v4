<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FormSubmissionAdminController extends Controller
{
    /**
     * Display a listing of form submissions for the website.
     */
    public function index(Request $request, Website $website): JsonResponse|Response
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

        $submissions = $query->paginate(25)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($submissions);
        }

        /** @var User $user */
        $user = $request->user();

        /** @var list<string> $distinctFormNames */
        $distinctFormNames = $website->formSubmissions()
            ->select('form_name')
            ->distinct()
            ->orderBy('form_name')
            ->pluck('form_name')
            ->all();

        return Inertia::render('Websites/Submissions', [
            'website' => [
                'id' => $website->id,
                'name' => $website->name,
                'slug' => $website->slug,
            ],
            'submissions' => $submissions,
            'distinctFormNames' => $distinctFormNames,
            'filters' => [
                'form_name' => $request->query('form_name') ? (string) $request->query('form_name') : null,
                'status' => $request->query('status') ? (string) $request->query('status') : null,
            ],
            'userRole' => $website->getMemberRole($user),
            'can' => [
                'manage_submissions' => Gate::allows('manageFormSubmissions', $website),
            ],
        ]);
    }

    /**
     * Update the status of a form submission (e.g. read, archived, spam).
     */
    public function update(Request $request, Website $website, FormSubmission $formSubmission): JsonResponse
    {
        if ($formSubmission->website_id !== $website->id) {
            abort(404);
        }

        Gate::authorize('manageFormSubmissions', $website);

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
        if ($formSubmission->website_id !== $website->id) {
            abort(404);
        }

        Gate::authorize('manageFormSubmissions', $website);

        $formSubmission->delete();

        return response()->json(['success' => true]);
    }
}
