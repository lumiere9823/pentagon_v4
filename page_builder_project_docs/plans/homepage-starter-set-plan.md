# Homepage and Starter Page Set Implementation Plan

Status: Ready for implementation  
Target base: `operations/phase-c`  
Product language: English UI; Vietnamese planning notes are allowed only in docs.

## 1. Outcome

Every website must leave a committed application transaction with exactly one
homepage. Creating a website also creates a usable starter set:

| Page | Canonical public path | Initial status |
|---|---|---|
| Home | `/` | Draft, homepage |
| About | `/about` | Draft |
| Contact | `/contact` | Draft |

Here `/` is relative to a public website. Under the current path-based delivery
adapter, the URLs are:

- `/sites/{website-slug}` for Home;
- `/sites/{website-slug}/about`;
- `/sites/{website-slug}/contact`.

The Pentagon application route `/` remains the product Welcome page. A future
custom-domain adapter may expose the same homepage as the domain root without
changing the domain model.

Starter pages are drafts. Provisioning must not publish content on behalf of a
user. An unpublished homepage exists in the model while its public URL returns
404 until it has an active published release.

## 2. Invariants

These are completion criteria, not best-effort behavior:

1. A newly created website has exactly one homepage before its transaction
   commits.
2. A homepage belongs to the same website it represents.
3. Concurrent homepage assignment leaves exactly one homepage.
4. The current homepage cannot be deleted until another page is assigned.
5. Homepage assignment changes the canonical URL, not the page document or
   publication state.
6. At most one homepage per website is enforced by the database.
7. Existing websites are backfilled idempotently before the new release is
   activated.
8. Website deletion may cascade through all of its pages normally.

## 3. Data model

Add `pages.is_homepage` as a non-null boolean with default `false` and expose it
through the `Page` model cast, factory states and serialized page summaries.

Create a partial unique index on `pages(website_id)` where `is_homepage = true`.
The migration must support both PostgreSQL and SQLite because the default test
suite and browser harness use SQLite while production/concurrency verification
uses PostgreSQL.

Do not represent the homepage with an empty slug, `/`, a magic slug, or a
`homepage_page_id` pointer on `websites`. Pages retain ordinary technical slugs;
the homepage flag determines canonical routing. Initially Home uses slug `home`.

The database guarantees at most one homepage. The provisioning, assignment,
delete guard and backfill flows guarantee at least one at committed application
seams.

## 4. Domain modules

### 4.1 WebsiteProvisioner

Move website creation out of `WebsiteController::store` behind one interface:

```php
public function provision(
    User $owner,
    array $websiteAttributes,
    string $starterSet = 'default',
): Website;
```

Its implementation owns one database transaction that:

1. creates the website;
2. creates the owner membership;
3. creates Home, About and Contact;
4. creates a valid canonical schema-v1 document for every page;
5. creates revision 1 for every page with a non-autosave initialization summary;
6. marks Home as the only homepage.

The controller validates input, calls the module once and redirects. It must not
reimplement provisioning steps.

### 4.2 HomepageManager

Expose one mutation interface:

```php
public function assign(Website $website, Page $page, User $actor): void;
```

The implementation must authorize at the controller/request seam, then inside a
transaction lock the website homepage rows, verify ownership, clear the old flag
and set the new flag. It must be safe when assigning the existing homepage.

Use the website `update` policy for this operation. Homepage assignment is a
website-level navigation decision, so editors who can edit page content do not
automatically gain this permission.

### 4.3 PublicPageLocator

Centralize root-versus-slug resolution and canonical paths behind a small read
interface. Public controller, sitemap, SEO/canonical metadata and redirects must
consume the same rule:

- homepage canonical path: `/sites/{website-slug}`;
- ordinary page canonical path: `/sites/{website-slug}/{page-slug}`.

Keep static release delivery, ETag and cache behavior unchanged. Refactor shared
response code rather than copying the current `PublicPageController::show`
implementation into a second action.

## 5. Starter documents

Create a single catalog for starter-set definitions. The default catalog entry
contains Home, About and Contact metadata and their initial schema-v1 documents.
Use `DocumentValidator` to validate/sanitize every document before persistence.

Prefer existing canonical template fixtures where the semantic match is clean.
Keep the catalog independent from database seeding so website creation works
even if an operator forgot to run `templates:seed`. Seeder and runtime catalog
may share immutable fixture builders; neither should call the other.

The first implementation needs only the `default` set. Shape the catalog so a
future `blank`, `business` or `portfolio` set can be added without changing the
provisioner interface or controller.

## 6. HTTP and routing

Add these interfaces:

```text
PUT /websites/{website}/homepage
body: { "page_id": integer }

GET /sites/{website:slug}
```

The homepage mutation returns to the website detail screen with a success flash.
Validation/authorization errors follow existing Inertia conventions.

Register the public root route before the page-slug route. Behavior:

- missing website: 404;
- missing homepage: 404 plus an operational log/error signal;
- draft/unpublished homepage: 404;
- published homepage without active release/file: 404, matching current rules;
- published homepage with active release: 200 with existing ETag/cache headers;
- request to the technical slug of the current homepage: permanent redirect to
  the website root;
- a former homepage resumes its ordinary slug URL after reassignment.

Update redirect generation, canonical URL generation, robots/sitemap output and
all hard-coded public page URL builders found by repository search.

## 7. Existing-data backfill

Implement an idempotent Artisan command with dry-run output. For each website
without a homepage:

1. choose its `home` page when present;
2. otherwise choose its oldest page;
3. otherwise create a canonical Home page plus revision 1 through the same
   domain implementation used by provisioning;
4. assign it as homepage;
5. report the action and totals.

Suggested interface:

```text
php artisan websites:backfill-homepages
php artisan websites:backfill-homepages --commit
```

The default is dry-run. `--commit` performs mutations. Repeated committed runs
must produce zero further changes.

Update `deploy/scripts/deploy.sh` so the committed backfill runs after migrations
and before cache warmup/atomic activation. A failure aborts deployment while the
old release remains active. Add the command to production operational docs.

## 8. UI specification

Reuse the current Vue/Inertia design system and visual language:

- existing `Card`, `CardHeader`, `CardContent`, `CardDescription`;
- existing `Button`, `Badge`, form controls and `InputError`;
- Lucide icons already installed;
- existing spacing, muted text, border, radius and responsive patterns;
- English copy consistent with current screens.

Do not add a new UI library or a parallel design token system.

### Website creation

Add a non-editable “Starter pages” card below Website Details. It previews:

```text
Home      /
About     /about
Contact   /contact
```

Copy should explain that pages start as drafts and can be changed before
publishing. The first version has one starter set, so it does not need a selector.

### Website detail page

- Sort homepage first, then retain the current ordering for remaining pages.
- Show a visually clear `Homepage` badge and `/` path on the homepage row/card.
- Show normal paths for other pages.
- Add “Set as homepage” for eligible non-home pages, visible only when the user
  can update the website.
- Disable or remove homepage deletion with nearby explanatory copy: “Set another
  page as homepage before deleting this page.”
- Add/open the public-root action using the canonical homepage URL.
- Preserve current responsive and keyboard behavior.

### Page edit/builder context

Display `/` as the effective public path when editing the homepage. The technical
slug remains stored and editable, with helper text explaining it becomes the path
if another page is made homepage. Do not overload `slug` with `/`.

## 9. Delete and publication behavior

Enforce homepage deletion protection in backend domain/controller behavior even
when the UI hides the action. Return a validation-style 422 response with an
actionable message. Do not silently choose another homepage during deletion.

Allow homepage unpublish. This intentionally takes the website root offline
without destroying its navigation identity. Assigning a draft page as homepage
is also allowed; root remains 404 until that page is published.

## 10. Verification

### Backend feature/domain tests

- Website creation atomically creates membership and exactly three starter pages.
- Each starter page has a valid document and revision 1.
- Home alone is homepage and all pages begin as drafts.
- Provisioning failure rolls back website, membership, pages and revisions.
- Assignment is idempotent and rejects cross-website pages.
- Unauthorized roles cannot assign homepage.
- Backend rejects homepage deletion with 422.
- Non-home pages remain deletable under existing policy.
- Backfill selection order is `home`, oldest, create Home.
- Backfill dry-run makes no changes; committed rerun is idempotent.

### PostgreSQL tests

- Partial unique index rejects two homepages for one website.
- Concurrent assignments finish with exactly one homepage.
- Different websites can each have one homepage.

### Public/SEO tests

- Draft/unreleased root is 404.
- Published root returns its release HTML and existing ETag behavior.
- Conditional request returns 304.
- Current homepage technical slug redirects permanently to root.
- Former homepage serves its ordinary slug after reassignment.
- Sitemap contains root exactly once and does not contain `/home` for homepage.
- Canonical metadata uses root for homepage and slug paths for other pages.

### Frontend tests

- Website Create renders the starter-page preview.
- Website Show sorts and labels homepage correctly.
- Assignment sends the expected Inertia request.
- Homepage delete action is unavailable with explanatory text.
- Effective public paths render correctly.

### Browser workflow

Extend Playwright to verify:

1. creating a website immediately shows Home, About and Contact;
2. Home is labeled `/` and Homepage;
3. editing/publishing Home makes `/sites/{website-slug}` return 200;
4. `/sites/{website-slug}/home` redirects to root;
5. homepage assignment changes the badge/path without breaking the old page;
6. responsive and axe checks continue to pass.

## 11. Required gates

Run and report all of the following:

```bash
composer ci:check
npm run test:frontend
npm run build
php artisan migrate:fresh --force
php artisan test -c phpunit.pgsql.xml
npm run test:e2e
git diff --check
```

Use a disposable PostgreSQL database for migration/concurrency verification.
Preserve the user’s existing data and unrelated working-tree changes.

## 12. Delivery sequence

1. Characterize current website creation, page deletion, public delivery and URL
   generation with tests.
2. Add schema/model support and database uniqueness tests.
3. Implement starter catalog and `WebsiteProvisioner`; move controller caller.
4. Implement `HomepageManager`, mutation request/route and delete guard.
5. Implement `PublicPageLocator`, root delivery and canonical redirects.
6. Update sitemap, SEO, robots and every URL consumer.
7. Implement idempotent backfill and deploy integration.
8. Implement UI changes with frontend tests.
9. Extend browser flow and run all gates.
10. Update system reference, README behavior notes and phase handoff evidence.

Each step is complete only when its targeted tests are green and no duplicate
rule remains in a controller or UI caller.

## 13. Non-goals

- Custom domains, DNS or TLS provisioning.
- Multiple selectable starter sets in the first release.
- Automatic publishing during website creation.
- Replacing the Pentagon application Welcome route `/`.
- Navigation-menu authoring.
- Silent homepage reassignment on delete.
- Broad visual redesign of Website or Builder screens.

## 14. Definition of done

The feature is done when every invariant in section 2 is proven by automated
tests, existing websites have a safe rollout path, all required gates pass, and
the final report distinguishes local evidence from hosted CI or deployed
evidence.

