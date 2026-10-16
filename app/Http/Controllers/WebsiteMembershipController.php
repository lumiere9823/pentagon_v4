<?php

namespace App\Http\Controllers;

use App\Enums\WebsiteRole;
use App\Http\Requests\StoreWebsiteMembershipRequest;
use App\Http\Requests\UpdateWebsiteMembershipRequest;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WebsiteMembershipController extends Controller
{
    public function store(StoreWebsiteMembershipRequest $request, Website $website): RedirectResponse
    {
        $user = User::where('email', $request->validated('email'))->firstOrFail();

        $website->memberships()->create([
            'user_id' => $user->id,
            'role' => WebsiteRole::from($request->validated('role')),
        ]);

        return redirect()->route('websites.show', $website)
            ->with('success', 'Member added successfully.');
    }

    public function update(UpdateWebsiteMembershipRequest $request, Website $website, WebsiteMembership $membership): RedirectResponse
    {
        // Enforce IDOR protection
        if ($membership->website_id !== $website->id) {
            abort(404);
        }

        $membership->update([
            'role' => WebsiteRole::from($request->validated('role')),
        ]);

        return redirect()->route('websites.show', $website)
            ->with('success', 'Member role updated successfully.');
    }

    public function destroy(Request $request, Website $website, WebsiteMembership $membership): RedirectResponse
    {
        // Enforce IDOR protection
        if ($membership->website_id !== $website->id) {
            abort(404);
        }

        $this->authorize('delete', [$membership, $website, $membership]);

        $membership->delete();

        return redirect()->route('websites.show', $website)
            ->with('success', 'Member removed successfully.');
    }
}
