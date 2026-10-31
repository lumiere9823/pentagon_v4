# Session Handoff - PENTAGON Page Builder

Ngày cập nhật: 2026-10-31
Phase hiện tại: Phase A - Stabilization & Git Packaging
Kết luận: Phase 2A–2C, A1–A4, Package 3 và các product UI chính đã hoàn thành local. Package 2, R-21 và R-24 chỉ đạt phạm vi một phần; R-22, R-25 và Form component còn mở. Chưa push và chưa có GitHub-hosted CI run vì repo không cấu hình remote. Trạng thái chuẩn hoá xem `page_builder_project_docs/STABILIZATION_STATUS.md`.

## 1. Trạng thái Phase 2A

| ID | Trạng thái | Kết quả |
|---|---|---|
| R-01 | PASS | Viewer vẫn GET submissions nhưng PATCH/DELETE trả 403. Owner/Editor mutation hợp lệ. Cross-tenant giữ 404. |
| R-02 | PASS | Publish chỉ chạy sau khi trạng thái draft đã `saved`; save false, conflict, validation/network error hoặc exception đều dừng publish. `isPublishing` khóa từ trước bước save. |
| R-03 | PASS | Ba lỗi PHPStan được sửa theo root cause: `parse_url()` false fail-closed, generic array có value type, pivot được đọc qua relation/model API. |
| R-04 | PASS | CI push không phụ thuộc tên default branch; PostgreSQL service dùng database disposable `pentagon_test`; bootstrap từ chối DB khác. Suite PostgreSQL pass 36/36 trên container PostgreSQL 18 tạm. |
| R-05 | PASS | Backup archive đúng `public/media` + `private/releases`, lỗi `tar` trả non-zero, không tự xóa backup cũ và từ chối ghi đè thư mục trùng timestamp. Fixture archive/checksum/extract restore pass. |

## 2. Trạng thái Phase 2B

| Brief ID | Roadmap mapping | Trạng thái | Kết quả |
|---|---|---|---|
| R-06 | R-06/R-07 | PASS | Public renderer áp dụng responsive property đúng allowlist cho đủ 12 component; editor presentation không còn phụ thuộc kích thước cửa sổ thật. |
| R-07 | R-08 | PASS | Breakpoint giữ 768/1024; preset desktop 1280 chỉ là canvas. `tablet=hidden` kế thừa xuống mobile, `mobile=visible` reset tường minh. |
| R-08 | R-09 | PASS | Publish preflight chặn Image `src` rỗng/whitespace bằng 422 `validation_failed`, code `empty_image_source`, có node path; URL/media path hợp lệ vẫn pass. |
| R-09 | R-10 | PASS | Preview banner nằm trong body, DOCTYPE là byte đầu tiên; authorization, signed URL và TTL không đổi; public output không có banner. |

Roadmap gốc tách responsive thành R-06..R-08, Image preflight là R-09 và preview là R-10. Bảng trên giữ mapping với brief Phase 2B hiện tại.

## 3. Thay đổi chính

### Phase 2C R-15

- `resources/js/components/editor/InspectorPanel.vue`: giữ ownership cho selected node, active group, schema partition, coercion, document mutation routing, responsive update/reset và media-modal coordination; media picker transient nay đóng khi selected node đổi để không ghi nhầm vào node mới.
- `resources/js/components/editor/inspector/InspectorContentGroup.vue`, `InspectorStyleGroup.vue`, `InspectorSettingsGroup.vue`: tách ba group theo event contract, không tạo document draft/global state mới; Node Identity và Quick Actions tiếp tục là module riêng.
- `tests/frontend/inspector-interactions.test.ts`: mount Inspector thật bằng Vue Test Utils + happy-dom; kiểm tra group switch, content edit, media select, viewport desktop/tablet/mobile, selected node identity, unsaved/current values, reopen group, node change và unmount/remount.
- `package.json`, `package-lock.json`: thêm test-only `@vue/test-utils` và `happy-dom` cho DOM interaction harness.

### Phase 2B

- `app/Domains/Publishing/Services/DocumentHtmlRenderer.php`: sinh `<style data-pb-responsive>` từ property enum đã validate; cascade mobile → tablet → desktop tại 768/1024; giữ nguyên markup/class cũ khi không có override; chèn responsive CSS trong head của document đầy đủ.
- `ComponentRegistry.php`, `routes/api.php`, `resources/js/lib/responsive.ts`, `EditorShell.vue`, `NodeRenderer.vue` và các renderer Image/Button/Divider/Hero: registry API cung cấp allowlist + breakpoint runtime; editor hydrate contract và áp viewport-stable presentation để canvas 375/768/1280 không phụ thuộc viewport trình duyệt thật.
- `app/Domains/DocumentEngine/Validation/DocumentValidator.php`, `PublishValidationException.php`, `PagePublishingService.php`, `PagePublishingController.php`: thêm publish-only semantic preflight cho Image source và response 422 có node path trước khi tạo release candidate.
- `app/Http/Controllers/PagePreviewController.php`: bỏ string search/replace `<body>`; truyền banner vào `renderDocument(..., bodyPrefixHtml)` để giữ cấu trúc HTML5 đúng.
- `app/Domains/Publishing/Services/ReleaseStagingService.php`: release mới dùng `renderer_version = 2`; release cũ không bị sửa và không republish tự động.
- Tests Phase 2B: `tests/frontend/responsive.test.ts`, `tests/Unit/Publishing/DocumentHtmlRendererTest.php`, `tests/Unit/DocumentEngine/DocumentValidatorTest.php`, `tests/Feature/Publishing/PagePublishingControllerTest.php`, `tests/Feature/Publishing/PagePreviewControllerTest.php`, `tests/Feature/Publishing/ReleaseStagingTest.php`, `tests/Feature/Reliability/PublishingChaosTest.php`.

### Phase 2A (giữ lại)

- `app/Http/Controllers/FormSubmissionAdminController.php`, `app/Policies/WebsitePolicy.php`: thêm policy action Owner/Editor cho mutation submission; tenant mismatch được kiểm tra trước và trả 404.
- `tests/Feature/Security/AuthorizationMatrixTest.php`: thêm Viewer 403/read-only và Owner/Editor success; giữ cross-tenant coverage.
- `resources/js/components/editor/EditorShell.vue`, `resources/js/lib/publishGuard.ts`: chặn publish cho mọi save state khác `saved`; không gọi publish khi save thất bại/conflict.
- `tests/frontend/publish-guard.test.ts`: kiểm tra success, false, conflict và exception.
- `app/Domains/DocumentEngine/Sanitization/HtmlSanitizer.php`, `app/Domains/DocumentEngine/Validation/ValidationResult.php`, `app/Http/Controllers/WebsiteController.php`: sửa ba lỗi PHPStan.
- `tests/Unit/DocumentEngine/HtmlSanitizerTest.php`: thêm input làm `parse_url()` trả `false` trên PHP 8.3.
- `.github/workflows/tests.yml`, `phpunit.pgsql.xml`, `tests/bootstrap-pgsql.php`: CI PostgreSQL disposable, full frontend gates và guard database.
- `deploy/scripts/backup.sh`, `tests/Scripts/BackupScriptTest.sh`: sửa archive path/error handling và thêm fixture kiểm chứng/asset restore rehearsal.
- `page_builder_project_docs/PENTAGON_SYSTEM_REFERENCE.md`, `page_builder_project_docs/optimization/04-security-reliability-review.md`, `page_builder_project_docs/optimization/05-optimization-roadmap.md`: cập nhật trạng thái thực tế Phase 2A.

## 4. Kiểm thử đã chạy

### PASS

- Phase 2C R-15 targeted: Inspector/media/responsive 4 file, 28/28 test; interaction harness riêng 3/3.
- Phase 2C final `npm run check`: 121 file format pass; 120 file không có lint warning/error.
- Phase 2C final `npm run types:check`: pass.
- Phase 2C final `npm run test:frontend`: 17 file, 117/117 test.
- Phase 2C final `npm run build`: pass, 3.538 modules; Builder 139,69 KB raw / 37,85 KB gzip. Warning `fontaine` optional vẫn là non-blocking baseline.
- Phase 2C final `composer lint:check`: pass.
- Phase 2C final `composer types:check`: pass, PHPStan 0 lỗi.
- Phase 2C final `php artisan test`: 308 discovered, 282 pass, 26 PostgreSQL-only skip, 1.390 assertions.
- Baseline trước sửa: 53/53 PHP tests mục tiêu, 216 assertions; responsive frontend 12/12.
- Phase 2B targeted: 74/74 PHP tests, 331 assertions; renderer contract riêng 20/20, 111 assertions; Component Registry API contract pass.
- Publishing/document regression: 114 discovered, 110 pass, 4 PostgreSQL-only skip, 528 assertions.
- `npm run test:frontend`: 11 file, 96/96; targeted responsive 19/19.
- `npm run check`: 112 file format pass; 111 file không có lint warning/error.
- `npm run types:check`: pass.
- `npm run build`: pass, 3.524 modules; Builder 136,98 KB raw / 36,69 KB gzip. Có warning `fontaine` optional như baseline, không làm fail build.
- `composer lint:check`: pass.
- `composer types:check`: pass, PHPStan 0 lỗi.
- `composer ci:check`: pass; backend 308 discovered, 282 pass, 26 PostgreSQL-only skip, 1.390 assertions.

### Bằng chứng Phase 2A giữ lại

- `php artisan test tests/Feature/Security/AuthorizationMatrixTest.php tests/Unit/DocumentEngine/HtmlSanitizerTest.php`: 12/12 test, 77 assertions.
- `npm run test:frontend -- tests/frontend/publish-guard.test.ts`: 4/4 test.
- `npm run check`: 112 file format pass; 111 file không có lint warning/error.
- `npm run types:check`: pass.
- `npm run test:frontend`: 11 file, 89/89 test.
- `npm run build`: pass, 3.524 modules; Builder 133,68 KB raw / 35,65 KB gzip.
- `composer lint:check`: pass.
- `composer types:check`: pass, PHPStan 0 lỗi.
- `php artisan test`: 301 discovered, 275 pass, 26 skip, 1.340 assertions. Các skip là PostgreSQL-only trong SQLite suite.
- `composer ci:check`: pass; backend result 301 discovered, 275 pass, 26 skip, 1.340 assertions.
- PostgreSQL disposable: `php artisan migrate:fresh --force` và `php artisan test -c phpunit.pgsql.xml` trên container PostgreSQL 18 mới, database `pentagon_test`: 36/36 test, 224 assertions. Container đã dừng và tự xóa.
- `bash -n deploy/scripts/backup.sh` và `bash -n tests/Scripts/BackupScriptTest.sh`: pass.
- `bash tests/Scripts/BackupScriptTest.sh`: pass; archive contents, SHA-256, isolated extract/compare và tar failure non-zero.
- `actionlint` qua container: pass cho `.github/workflows/tests.yml`.
- Guard check với `DB_DATABASE=page_builder_dev`: bị từ chối exit 1 đúng chủ đích.

### Kiểm tra trung gian đã sửa

- Input sanitizer ban đầu `http://[::1` không làm `parse_url()` trả false trên PHP 8.3; đổi regression fixture sang `http://example.com:bad`.
- Annotation pivot chung ban đầu tạo lỗi PHPStan mới; thay bằng đọc relation và kiểm tra `Pivot` thực tế. Final PHPStan 0 lỗi.

### Chưa chạy / giới hạn

- GitHub-hosted workflow chưa chạy vì không có remote/upstream và session không được commit/push. Cấu hình đã được `actionlint` và các command tương đương kiểm chứng local.
- Không chạy restore database từ dump thật; backup fixture dùng `pg_dump` stub và rehearsal chỉ giải nén/so sánh asset trong thư mục tạm. PostgreSQL application tests dùng database disposable thật.
- Không chạy browser/E2E, Lighthouse, visual regression hoặc accessibility suite. Máy có Microsoft Edge nhưng repo không có Playwright/Vitest browser provider; builder route còn cần auth, authorization, database và registry fixture deterministic.
- Chưa có browser visual evidence thật ở 375/768/1280. R-15 chỉ tuyên bố behavior preservation dựa trên mounted DOM interactions; không tuyên bố visual parity.
- Không chạy lại PostgreSQL concurrency suite trong Phase 2B vì thay đổi không đụng concurrency/DB contract; bằng chứng 36/36 của Phase 2A vẫn là lần chạy gần nhất và không được tuyên bố là đã chạy lại.

## 5. Git và working tree

- Branch: `master` tại base commit `ec7e074`.
- Remote/upstream: không có cấu hình; không thể xác nhận default branch từ remote. Workflow nay chạy trên mọi push và pull request nên không phụ thuộc `main`/`master`.
- Trước Phase 2A không có tracked diff. Có sẵn untracked `page_builder_project_docs/SESSION_HANDOFF.md`, `page_builder_project_docs/optimization/` và `skills-lock.json`.
- Các tài liệu untracked được cập nhật theo yêu cầu. `skills-lock.json` không bị chỉnh sửa.
- Working tree hiện có toàn bộ thay đổi Phase 2A, Phase 2B và Phase 2C chưa commit.
- Không commit, push, reset, checkout, stash hoặc xóa thay đổi người dùng.

## 6. Release compatibility và rủi ro còn lại

- Lần push/PR đầu tiên vẫn cần xác nhận workflow trên GitHub-hosted runner; local commands và actionlint đều pass.
- `deploy/scripts/restore-rehearsal.sh` hiện có các behavior riêng chưa thuộc R-05 (ví dụ `pg_restore ... || true`); không được coi là đã audit/sửa toàn bộ restore pipeline trong Phase 2A.
- Release đã niêm phong chứa HTML/CSS độc lập và vẫn bất biến. Chỉ release mới mang `renderer_version = 2`; trang đã publish trước Phase 2B không tự nhận responsive fix. Nếu cần cập nhật, phải inventory và republish có kiểm soát; không mutate file release cũ.
- Inline responsive CSS tăng kích thước theo số node/property có override. Chưa profile trên document cực đại 250 node; không tối ưu sớm trong Phase 2B.
- Browser visual regression 375/768/1280 vẫn thiếu harness. Logic breakpoint/cascade và CSS output đã có unit/feature coverage nhưng chưa có screenshot comparison.
- Các vấn đề ngoài Phase 2B vẫn mở: publish/rollback concurrency decision, restore rehearsal thật, typography plugin `prose`, lifecycle orphan storage và Phase 2C architecture tasks.

## 7. Quyết định trước Phase 2C

1. Có cần inventory/republish các page hiện hữu để nhận renderer v2 hay chỉ áp dụng khi người dùng publish lần kế tiếp.
2. Có bổ sung browser/visual harness cho ma trận 375/768/1280 trước refactor Phase 2C hay chấp nhận TS/PHP contract tests hiện tại.
3. Giữ roadmap Phase 2C như hiện tại; chưa bắt đầu refactor `EditorShell`, Inspector, layers, DnD, release/template/form UI hoặc performance work.

## 8. Cập nhật Phase 2C - 2026-10-24

| ID | Trạng thái | Kết quả |
|---|---|---|
| R-11 | PASS | `app.ts` là owner duy nhất của AppLayout. Năm page Website/Page bỏ wrapper lồng và chuyển breadcrumbs sang Inertia layout props. Test resolver/source ownership pass; chưa có browser DOM assertion. |
| R-12 | PASS | Một registry session được tạo trong `EditorShell` và chia sẻ cho Library + Inspector. Concurrent consumers dùng cùng request; editor session mới fetch độc lập; loading/error/retry/dispose được giữ. |
| R-13 | PASS | `httpClient.ts` gom JSON serialization/parsing, Accept và CSRF. Autosave, publish, revision rollback/list, template create và media update/delete đã migrate; retry/debounce/conflict/status branching không đổi. Multipart media upload và list GET đặc thù chưa bị ép qua client. |
| R-14 | PASS | Save/publish protocol được tách khỏi `EditorShell`, dùng guard Phase 2A làm baseline. Có test success, save failure/conflict, publish failure, exception recovery và repeated invocation; queued in-flight edits tiếp tục được bao phủ bởi autosave tests. |
| R-15 | PASS | Content, Style và Settings đã tách sau khi có mounted DOM interaction harness. Tests bảo vệ group switch, content/media edits, responsive viewport, selected node, unsaved/current values, reopen, node change và unmount/remount; media modal transient đóng khi node đổi. |

### Release compatibility decision

- Release đang active giữ nguyên; không mutate file hoặc row release đã niêm phong và không republish hàng loạt.
- `renderer_version = 2` chỉ áp dụng khi publish lại có kiểm soát. Release cũ vẫn phục vụ `index.html` + `manifest.json` tự chứa qua directory đã niêm phong; rollback Case A chỉ đổi pointer sau verify, Case B tạo release mới từ revision thay vì sửa release cũ.
- Nếu product yêu cầu page cũ nhận responsive fixes, cần task inventory/republish riêng: liệt kê page/release, backup verified, kiểm tra media availability, preview/visual 375/768/1280, publish theo batch nhỏ và xác nhận rollback trước khi tiếp tục.

## 9. A1 - Dirty Tracking & Save/Publish Integrity - 2026-10-24

### Ket qua da xac nhan

- Reproduce bang mounted `EditorShell`: edit Inspector dau tien van de `saveStatus = saved`; regression test do truoc fix va xanh sau fix.
- Root cause: watcher khong `immediate` nhung lai bo callback dau tien bang `isInitialMount`, nen callback dau tien chinh la mutation that va bi bo qua.
- `EditorShell.vue` nay watch serialized document: first edit duoc bat, assignment no-op khong tao dirty/autosave, undo/redo va responsive override/reset van di qua cung mot dirty source.
- Restore revision dung serialized internal target thay vi one-shot boolean; restore document giong het hien tai khong con lam mat edit ke tiep.
- `useAutosave.ts` bien queued-change thanh reactive state; error/conflict van duoc hien la unsaved va kich hoat before-unload protection.
- Manual/pre-publish save drain moi edit den trong luc request dang chay. Publish chi tiep tuc sau khi document queued moi nhat save thanh cong; save fail/conflict dung publish qua `publishGuard.ts`/`publishProtocol.ts` hien co.
- Edit den trong luc POST publish dang chay van de local draft o trang thai dirty va autosave sau do; release dang chay khong ghi de hoac lam mat edit nay.
- Server persistence/release contract khong doi: optimistic lock, revision policy, immutable release staging/sealing va atomic activation duoc giu nguyen.

### Tests va quality gates thuc te

- Red proof: `npm run test:frontend -- tests/frontend/editor-save-integrity.test.ts` fail `expected dirty, received saved` truoc fix.
- Targeted integrity: 6 files, 49/49 tests pass.
- `npm run check`: pass, 121 format / 120 lint.
- `npm run types:check`: pass.
- `npm run test:frontend`: 18 files, 124/124 tests pass.
- `npm run build`: pass, 3.538 modules; Builder 139.83 kB raw / 37.89 kB gzip; warning `fontaine` optional van non-blocking.
- `composer lint:check`: pass.
- `composer types:check`: pass, PHPStan 0 errors.
- `php artisan test`: 308 discovered, 282 pass, 26 PostgreSQL-only skip, 1.390 assertions.
- `git diff --check`: pass.

### Invariants da cover

- First edit, edit sau save, undo/redo, identical revision restore.
- No-op edit va no-op responsive reset khong dirty/autosave.
- Edit trong autosave/pre-publish save khong bi danh dau saved som.
- Save error giu local document, dirty indication, unload warning va retry payload.
- Save-before-publish, save/conflict stop, double-submit guard.
- Responsive override/reset va pruning.
- Edit trong publish request van dirty va duoc autosave tiep theo.

### Gioi han va rui ro con lai

- Khong co browser/E2E, screenshot/visual parity hoac GitHub-hosted CI run moi.
- Khong chay lai PostgreSQL concurrency suite trong A1; 26 PostgreSQL-only tests trong SQLite run bi skip va khong duoc tuyen bo la da verify lai.
- Serialized watcher chua duoc profile rieng o document 512 KiB toi da.
- Edit xay ra sau khi POST publish bat dau khong the chen vao release da bat dau; edit do van dirty/autosave, khong bi mat.
- Canonicalization/alias, reparent limits, property schema va Flexbox/Grid van ngoai scope A1.

### Git state A1

- Truoc task: `master` / `ec7e07430c98ab24b59602eda2dfc44e628cee2c`, no remote/upstream, 0 staged, 49 tracked content diffs, 29 untracked files, cung cac line-ending warnings da co.
- Sau task: branch/HEAD/remote/upstream va staged state khong doi; 50 tracked content diffs, 30 untracked files.
- File moi duy nhat: `tests/frontend/editor-save-integrity.test.ts`.
- Khong reset, checkout, stash, clean, delete, commit, push; `skills-lock.json` khong bi sua.

### Browser visual harness

- Microsoft Edge có trên máy, nhưng repo chưa có Playwright/Cypress hoặc Vitest browser provider/config/fixture. Builder thật chạy sau auth/verified middleware và cần website/page/database/registry fixture, nên thêm smoke browser đáng tin cậy không còn là thay đổi tối thiểu cho R-15.
- Không thêm browser dependency hoặc CI infrastructure trong R-15. Task riêng nên dùng Playwright với fixture disposable, ba viewport 375/768/1280, editor preview và sealed public release; chi phí gồm browser install/cache, app/database boot, auth state và screenshot baseline review.
- Phase 2C có mounted DOM interaction tests nhưng chưa có browser screenshot evidence; không tuyên bố visual parity.

## 10. A2 - Canonicalization & Alias Dispatch - 2026-10-24

### Status va root cause

- A2: PASS, ready for review. Scope dung o canonicalization/alias correctness; khong bat dau A3/A4 hoac Enhanced Page Builder.
- Reproduction truoc fix: document co child `type = Rich Text` pass `DocumentValidator`, canonical result co `RichText`, nhung renderer dung raw input va throw `No render implementation for registered component 'Rich Text'.`
- Alias hien tai chi gom `Rich Text -> RichText` va `Card Grid -> CardGrid` trong `ComponentRegistry`; khong them alias suy doan hoac normalize chuoi tuy y.
- Root cause tong quat: `DocumentValidator` da tao canonical document (canonical type, defaults, pruned styles, sanitized RichText/FAQ), nhung persistence/templates/revisions/render/preview/publish thuong bo qua result va tiep tuc dung raw input.

### Canonicalization contract va consumers

- Giu API noi bo hien co `ValidationResult::getSanitizedDocument()` lam mot boundary duy nhat; khong tao registry/canonicalizer framework song song.
- Canonicalization deterministic va idempotent; unknown type, invalid prop/style/tree van fail closed; valid children, IDs, page settings, content va media URL duoc giu.
- `DocumentHtmlRenderer`: render va page metadata dung canonical result cho ca preview va publish.
- `PagePersistenceService`: media scan, draft storage, revision hash/data va revision restore copy dung canonical result; optimistic locking/revision policy giu nguyen.
- `PageTemplateController`, `TemplateInstantiationService`, `StarterTemplateSeeder`: store/return canonical template data va media scan cung document do.
- `PagePublishingService`: canonical hash/snapshot cho revision selection/creation, canonical semantic comparison de reuse revision tuong duong, canonical media scan. Khong rewrite page draft trong publish, historical revision row hoac sealed release cu.
- Legacy revision/template co accepted alias co the instantiate/render/stage thanh canonical output; source historical row/document khong bi mutate.

### Compatibility va regression coverage

- Future supported saves/templates/restored revision copies se luu canonical JSON, nen raw alias/default omission/unsafe removable HTML khong con nam trong draft moi.
- Repeated same raw alias input tao cung canonical hash va khong tao revision thua.
- Publish co the reuse latest legacy revision neu canonical semantics trung khop ma khong sua row lich su.
- Media ownership/availability checks chay tren canonical data; tests bao gom valid owned reference va cac suite foreign/missing/deleted media hien co.
- Preview va publish dung cung canonical alias/sanitization semantics. Existing sealed `index.html` va `manifest.json` duoc assert byte-for-byte khong doi sau publish ke tiep.
- Invalid page/template input khong persist; invalid non-null publish input khong tao revision/release.

### Tests thuc te

- Red proof: renderer regression 1 test fail truoc fix voi exact `Rich Text` dispatch exception; xanh sau fix.
- Focused A2 PHP: 128/128 pass, 699 assertions.
- `npm run check`: pass, 121 format / 120 lint.
- `npm run types:check`: pass.
- `npm run test:frontend`: 18 file, 124/124 pass.
- `npm run build`: pass, 3,538 modules; Builder 139.83 kB raw / 37.89 kB gzip; warning optional `fontaine` van non-blocking.
- `composer lint:check`: pass.
- `composer types:check`: pass, PHPStan 0 errors.
- `php artisan test`: 318 discovered, 292 pass, 26 PostgreSQL-only skip, 1,475 assertions.
- `git diff --check`: pass; chi hien cac line-ending warning frontend da co tu truoc.

### Khong chay / gioi han

- Khong chay browser/E2E, screenshot/visual parity, GitHub-hosted CI hoac deployment verification.
- Khong chay lai PostgreSQL disposable/concurrency suite; 26 PostgreSQL-only tests trong SQLite suite bi skip va khong duoc tuyen bo la da verify trong A2.
- Khong migration/schema/dependency/public API change, khong auto-republish, khong mutate release file cu.
- A3 reparent/tree mutation invariants are completed in section 11 below; A4 schema-driven Inspector metadata remains pending.

### Git safety state sau A2

- Branch/HEAD: `master` / `ec7e07430c98ab24b59602eda2dfc44e628cee2c`; remote/upstream: none.
- Staged: 0; tracked content diffs: 58; untracked: 30. Tat ca existing changes van duoc giu nguyen va user-owned.
- `skills-lock.json` van untracked va khong bi sua.
- Khong reset, checkout, switch, stash, clean, destructive delete, commit hoac push.

## 11. A3 - Tree Mutation Invariants - 2026-10-25

### Status and reproduced defects

- A3: PASS. Scope stops at invariant-safe public document-tree mutation; A4 and Enhanced Page Builder remain pending.
- Pre-fix depth regression: moving a three-level subtree below a depth-8 destination returned `success: true` and would create depth 11. Root cause was subtracting the source node's old absolute depth from a subtree-local depth value.
- Pre-fix child-capacity regression: moving into a destination with 50 direct children returned `success: true` and would create child 51 because `moveNode` had no destination-capacity guard.
- Additional red proofs found that invalid replacement returned no failure result, rejected undo consumed history, repeated ID entropy created duplicate IDs, and a rejected depth-10 insert added `children: []` before failing.

### Implemented contract

- `moveNode` now uses actual subtree height and destination depth, allows exactly depth 10, rejects depth 11, enforces 49 -> 50 / rejects 50 -> 51, and exempts same-parent reorder from a false capacity failure.
- Root movement, self/descendant cycles, missing destinations, and leaf parents remain rejected before mutation. Valid moves preserve every subtree ID and total node count, including at the 250-node ceiling.
- Insert and duplicate reserve generated IDs against the active tree, including deterministic collision fallback; duplicate still assigns fresh IDs to the full clone.
- `setDocument` now returns `TreeMutationResult` and validates schema version, root eligibility, known component type, ID format/uniqueness, cycles/shared nodes, leaf eligibility, depth, total nodes, and direct children before assignment.
- Undo/redo commits stack changes only after a replacement succeeds. Revision restore updates lock/save state only after `setDocument` succeeds.
- Rejected mutations leave serialized document and selection unchanged; rejected replacement also leaves undo/redo stacks unchanged. Document watchers therefore receive no document mutation to mark dirty or autosave.

### Verification

- Red proofs recorded: depth move and 50-child move both failed with expected `false` but received `true`; invalid replacement failed by returning `undefined`; rejected undo returned `true`; repeated entropy produced 1 unique ID for 2 inserts; rejected insert changed serialized JSON by adding `children: []`.
- Focused final: `tests/frontend/document-state.test.ts` + `tests/frontend/editor-history.test.ts`, 32/32 pass.
- `npm run check`: pass, 121 formatted / 120 lint-clean after repository formatter fixed the touched composable.
- `npm run types:check`: pass.
- `npm run test:frontend`: 18 files, 138/138 pass.
- `npm run build`: pass, 3,538 modules; Builder 142.12 kB raw / 38.61 kB gzip; existing optional `fontaine` warning remains non-blocking.
- `composer lint:check`: pass.
- `composer types:check`: pass, PHPStan 0 errors.
- `php artisan test`: 318 discovered, 292 pass, 26 PostgreSQL-only skip, 1,475 assertions.
- `git diff --check`: pass.

### Limits and scope boundary

- No browser/E2E, hosted CI, deployment, or fresh PostgreSQL concurrency run was performed. The 26 PostgreSQL-only tests remain explicitly skipped in the SQLite run.
- No Layers/DnD UI was added; `moveNode` remains a safe public mutation seam for later work.
- No schema, migration, dependency, database, component-capability, A4 Inspector metadata, or Enhanced Page Builder work was performed.

### Git safety state after A3

- Branch/HEAD: `master` / `ec7e07430c98ab24b59602eda2dfc44e628cee2c`; remote/upstream: none.
- Staged: 0; `git status` marks 66 tracked files modified, while 62 have content diffs (four pre-existing CRLF stat-only entries remain); untracked: 30.
- Existing user changes were preserved. No reset, checkout/switch, stash, clean, delete, commit, push, dependency, migration, database, or `skills-lock.json` operation occurred.

## 10. A4 - Schema-driven Inspector metadata

Date: 2026-10-25

### Result

- A4 is complete locally for all 12 registered component types: Section, Container, Heading, RichText, Image, Button, Hero, Columns, CardGrid, FAQ, Spacer, and Divider.
- `ComponentDefinition` remains the canonical seam. Each registered property now carries a bounded Inspector annotation for label, group, and control; exported metadata derives path, defaults, constraints, and responsive capability from the existing property schema and responsive allowlist.
- The shared responsive `display` capability is exported as `styles.*.display`; no persisted document schema or format changed.
- The registry API now exposes the existing alias map so editor metadata lookup preserves the A2 canonical aliases.

### Inspector and mutation flow

- `InspectorPanel.vue` no longer partitions properties by hard-coded key sets or infers controls from property names. It filters the registry contract into content, style, and settings groups.
- The three existing group modules render the bounded controls `text`, `textarea`, `url`, `media`, `select`, `toggle`, and `faq-list` from metadata.
- Invalid paths, duplicate paths, unsupported controls, incompatible control/schema pairs, invalid enum values, unsafe URL schemes, over-limit strings/lists, and malformed FAQ items fail closed before an event reaches document mutation.
- Valid desktop edits still emit `update-prop`; valid tablet/mobile edits still emit `update-style`; reset still emits `reset-style`. `EditorShell` continues to record history and delegate to `useDocumentState`, preserving dirty tracking, undo/redo, autosave, and save-before-publish behavior.
- Media selection captures the selected node ID when the picker opens and ignores late selection events after a node switch.

### Verification

```text
Focused A4 frontend: 6 files, 40/40 tests passed.
Focused registry/PHP regression: 69/69 tests passed, 555 assertions.
npm run check: pass; 121 formatted, 120 lint-clean.
npm run types:check: pass.
npm run test:frontend: 19 files, 144/144 tests passed.
npm run build: pass; 3,538 modules; Builder 142.11 kB raw / 38.98 kB gzip.
composer lint:check: pass.
composer types:check: pass; PHPStan 0 errors.
php artisan test: 319 discovered, 293 passed, 26 PostgreSQL-only skipped, 1,674 assertions.
git diff --check: pass.
```

The existing optional `fontaine` build warning remains non-blocking. Browser/E2E, visual parity, hosted CI, deployment, and a fresh PostgreSQL concurrency run were not performed. The 26 PostgreSQL-only tests were skipped, not executed successfully.

### Scope and safety

- No new components, layout authoring features, tokens, data binding, custom CSS, plugin interfaces, database schemas, migrations, dependencies, or persisted document versions were added.
- A1 save/publish integrity remains covered by the full frontend and PHP suites. A3 mutation boundary and atomicity coverage remains green.
- No reset, checkout/switch, stash, clean, deletion, commit, push, or `skills-lock.json` modification occurred.

## 13. Package 2 - Shared Element and Section Style Primitives

Date: 2026-10-25

### Status and architecture

- Independent review history: Package 2 originally received FAIL. After the 2026-10-25 remediation, the bounded first vertical slice is PASS WITH CAVEATS. Full original-roadmap acceptance remains open. Work stops before Package 3; no Layers or DnD UI was added.
- `resources/page-builder/style-primitives.json` is the shared bounded value contract consumed by PHP and TypeScript. It defines stable primitive IDs, categories, ordered allowlisted values, and responsive eligibility; it contains no user-authored CSS.
- `ComponentDefinition` remains the A4 authority for component/property applicability. A property opts into a primitive through `stylePrimitive`; construction fails closed for unknown IDs or a type/enum mismatch. The registry API exposes the same primitive contract, and the editor rejects a server/local contract mismatch.
- Inspector fields now also have to agree with the paired property schema on type, ordered enum, primitive ID, and responsive eligibility. Contradictory fields are filtered before they can emit unsaveable values.
- Primitive `responsive` eligibility is enforced by the PHP validator/exported contract and by the hydrated TypeScript runtime contract. Base values remain valid when a primitive is not responsive, while tablet/mobile overrides fail closed.
- Runtime presentation remains deliberately native to each side: `resources/js/lib/stylePrimitives.ts` plus `responsive.ts` for editor CSS, and `DocumentHtmlRenderer` for published classes/declarations. Table-driven tests make missing or mismatched value mappings visible without adding a generator or cross-runtime framework.
- Hero title/subtitle foregrounds now follow every bounded background in editor and publisher, including responsive background overrides. Default/omitted background behavior remains transparent with the existing title/subtitle semantics.

### Implemented primitive matrix

| Primitive | Explicit component/property support | Values/defaults | Responsive |
|---|---|---|---|
| `spacing.sectionPadding` | `Section.padding` | `none/small/medium/large`; default `medium` | base/tablet/mobile |
| `spacing.block` | `Heading.blockSpacing`, `RichText.blockSpacing` | `none/small/medium/large`; default `small`, preserving existing `my-2` | base/tablet/mobile |
| `spacing.layoutGap` | `Columns.gap`, `CardGrid.gap` | `small/medium/large`; default `medium` | existing behavior retained |
| `spacing.spacerHeight` | `Spacer.size` | `small/medium/large/xlarge`; default `medium` | existing behavior retained |
| `dimensions.maxWidth` | `Container.maxWidth`, `Heading.maxWidth`, `RichText.maxWidth` | `sm/md/lg/xl/full`; existing Container default `lg`, text default `full` | base/tablet/mobile |
| `dimensions.aspectRatio` | `Image.aspectRatio` | `auto/16:9/4:3/1:1`; default `auto` | existing behavior retained |
| `dimensions.controlSize` | `Button.size` | `sm/md/lg`; default `md` | existing behavior retained |
| `typography.align` | `Heading.align`, `RichText.align`, `Hero.align` | `left/center/right`; defaults `left/left/center` | base/tablet/mobile |
| `typography.fontSize` | `Heading.fontSize`, `RichText.fontSize` | `semantic/small/medium/large/xlarge`; default `semantic` | base/tablet/mobile; semantic Heading sizing still follows `level` |
| `color.foreground` | `Heading.textColor`, `RichText.textColor` | `foreground/soft/muted/primary`; defaults preserve prior foreground/90 behavior | base/tablet/mobile |
| `color.background` | `Section.background`, `Hero.background` | `transparent/muted/primary/dark`; default `transparent` | base/tablet/mobile |
| `color.border` | `Divider.color` | `muted/primary/subtle`; default `muted` | existing behavior retained |

`Heading.level` remains semantic and is not a style primitive. Image URLs, button navigation, FAQ items, and structural child behavior remain component-specific.

### Compatibility and security

- Schema version remains 1. New properties are optional and canonicalize to compatibility defaults. Old Heading, RichText, and Hero documents retain prior default presentation; existing enum values and defaults are unchanged.
- Responsive data still uses sparse `styles.tablet/mobile`, desktop base values, mobile inheritance from tablet/base, and existing reset/pruning mutation behavior. Breakpoints remain 768/1024.
- PHP validation accepts only the declared enum values. Unknown primitive IDs, mismatched metadata, undeclared component/property combinations, raw CSS strings, arbitrary classes, style objects, `url(...)`, `expression(...)`, and script-capable values fail closed.
- Historical revision rows and sealed release files are not rewritten. Renderer version/release format, migrations, database schema, persisted document schema, dependencies, and `skills-lock.json` are unchanged.

### Verification

```text
Remediation red proof: 5 Inspector consistency cases, 1 frontend responsive eligibility case, and 3 PHP Hero/eligibility cases failed before the fixes.
Focused remediation frontend: 3 files, 43/43 tests passed.
Focused remediation PHP: 61/61 tests passed, 582 assertions.
npm run check: pass; 123 files formatted, 121 files lint-clean.
npm run types:check: pass.
npm run test:frontend: 21 files, 161/161 tests passed.
npm run build: pass; 3,540 modules; Builder 146.35 kB raw / 40.06 kB gzip.
composer lint:check: pass.
composer types:check: pass; PHPStan 0 errors.
php artisan test: 327 discovered, 301 passed, 26 PostgreSQL-only skipped, 1,803 assertions.
git diff --check: pass; only pre-existing CRLF conversion warnings were printed.
```

The existing optional `fontaine` warning remains non-blocking. The repository has mounted DOM tests but no Playwright/Cypress/browser-provider fixture for the authenticated builder, so editor/preview/release visual parity at 375/768/1280 and computed accessibility contrast remain unverified. PostgreSQL-only concurrency tests were skipped in the SQLite run and were not claimed as passed. Hosted CI and deployment were not run.

### Deferred after Package 2

- Border style/width controls beyond the existing Divider enum, radius, shadow, opacity, per-side spacing, arbitrary dimensions/units, font family/weight/line-height/letter-spacing, gradients/background images, and design-token authoring remain deferred.
- RichText still relies on the existing `prose` markup contract; adding a dedicated typography stylesheet is separate work.
- Browser screenshot parity and accessibility contrast review for selectable tokens require the future authenticated browser harness.
- Package 3 Layers/DnD remains unstarted.

### Git safety state after Package 2

- Branch/HEAD: `master` / `ec7e07430c98ab24b59602eda2dfc44e628cee2c`; remote/upstream: none.
- Staged: 0; Git status marks 71 tracked files modified, 68 with content diffs; untracked: 36.
- No reset, checkout/switch, stash, clean, destructive delete, branch change, commit, push, dependency change, migration/database schema change, persisted schema/release-format change, historical rewrite, sealed-release modification, or `skills-lock.json` modification occurred.

## 13. Package 3B — Read-Only Layers Tree MVP

### Scope and Delivered Capabilities
- Implemented read-only Layers panel accessible via switchable left sidebar tabs (`[ Components | Layers ]`) and toolbar toggles (`LayoutGrid` and `Layers`).
- Recursive hierarchy rendering with indentation, component icons (`componentIcons.ts`), canonical human-readable labels, and safe text/content previews (Heading text, Button label, Hero title, Image alt/caption, FAQ item counts).
- Container vs Leaf distinction strictly enforced: only 5 container types (`Section`, `Container`, `Hero`, `Columns`, `CardGrid`) render chevron collapse triggers and empty-container placeholders. The 7 leaf components (`Heading`, `RichText`, `Image`, `Button`, `FAQ`, `Spacer`, `Divider`) render without expansion controls.
- Bidirectional selection synchronization between Layers panel, Canvas, and Inspector using `useDocumentState.selectedNodeId` without document mutations or dirty-state triggering.
- Auto-expansion of ancestor containers when a nested node is selected in Canvas or externally.
- Expand All / Collapse All controls and depth/node count metrics footer.
- Read-only viewer mode compatibility: in viewer mode, selection remains enabled for read-only inspection in the Inspector, while mutations remain disabled.

### Verification
```text
Focused Layers tests: tests/frontend/layers-panel.test.ts (11/11 passed).
npm run test:frontend: 22 test files, 172/172 passed.
npm run types:check: vue-tsc --noEmit passed (0 errors).
npm run check: 126 files formatted, 124 files lint-clean (0 errors).
npm run build: Vite build passed in 3.24s (Builder 157.69 kB raw / 42.90 kB gzip).
git diff --check: pass (CRLF line ending warnings only).
```

### Invariants Preserved
- Persisted document schema version remains 1. Zero document mutations or dirty tracking triggered by tree interactions.
- Dependencies unchanged (`package.json`, `package-lock.json`).
- `skills-lock.json` preserved untouched.
- Drag-and-drop, reparenting, sibling reordering, inline renaming, and keyboard ARIA tree navigation deferred to Package 3C/3D.

## 14. Package 3C — Layers Keyboard Navigation, Search & Accessibility

### Scope and Delivered Capabilities
- **WAI-ARIA Tree Semantics**: Tree container with `role="tree"` and `aria-label="Document Structure"`. Rows with `role="treeitem"`, `aria-level`, `aria-selected`, and `aria-expanded` strictly restricted to container components. Descriptive accessible names (`aria-label`) incorporating component type and text preview content. Chevron buttons have accessible labels and `tabindex="-1"` to adhere to the single tab-stop tree pattern.
- **Roving Tabindex & Keyboard Navigation**: Handled keys on the tree include `ArrowDown` (next visible item), `ArrowUp` (previous visible item), `Home` (first visible item), `End` (last visible item), `Enter`/`Space` (selects focused node), `ArrowRight` (expands container, or focuses first child if already expanded), and `ArrowLeft` (collapses container, or moves focus to parent if collapsed/leaf). Handled keys call `preventDefault()` to prevent unintended scrolling. Inactive/hidden descendants never receive focus, and collapsing a branch safely retains focus on the visible parent.
- **Search & Live Filtering**: Search input in panel header with `<label for="layers-search-input" class="sr-only">`, `type="search"`, and clear button. Case-insensitive and whitespace-trimmed search matching component types, component labels, node IDs, and text preview content (Heading text, Button label, Hero title/subtitle, Image alt/caption, FAQ counts/items). When filtering, matches retain ancestor paths and ancestors are auto-expanded. Non-matching branches are excluded from the DOM. Clearing search cleanly restores the user's prior manual expansion state. Empty search state displays an accessible `role="status"` banner with "Clear search" action.
- **Read-Only / Viewer Mode Alignment**: Confirmed that viewer mode in Pentagon permits node selection for inspecting properties and styles in the read-only Inspector, while mutations remain disabled. Added test verification for viewer selection.
- **Canvas Scroll Synchronization**: Scoped, element-level scrolling (`scrollToNode` in `EditorCanvas.vue`) scrolls the canvas surface to newly selected nodes from Layers, respecting `prefers-reduced-motion` and avoiding global window scroll.
- **Select Parent Action**: Added accessible "Select Parent" action (`CornerLeftUp` icon) in `NodeToolbar.vue` when `!isRoot`, selecting parent node via existing handler without mutations or history snapshots.

### Verification
```text
Focused Layers tests: tests/frontend/layers-panel.test.ts (25/25 passed).
npm run test:frontend: 22 test files, 186/186 passed.
npm run types:check: vue-tsc --noEmit passed (0 errors).
npm run check: 126 files formatted, 124 files lint-clean (0 errors).
npm run build: Vite build passed in 3.38s (Builder 164.95 kB raw / 44.99 kB gzip).
git diff --check: pass (CRLF line ending warnings only).
```

### Invariants Preserved
- Document schema version remains 1. Zero mutations, history snapshots, or autosave/dirty flags triggered by keyboard traversal or search.
- Dependencies unchanged (`package.json`, `package-lock.json`).
- `skills-lock.json` untouched.
- Structural mutations, drag-and-drop, and inline renaming remain deferred to Package 3D.

## 15. Package 3D.1 — Mutation API Hardening & EditorShell Integration

### Scope and Delivered Capabilities
- **Explicit, Documented Index Contract**: Established that `targetIndex` in `moveNode(nodeId, targetParentId, targetIndex)` specifies the *post-removal insertion index* in the destination container's child array.
- **Pure Coordinate Translation**: Created `resources/js/lib/dropIndex.ts` exporting:
  - `calculateDropIndex(sourceIndex, preRemovalTargetIndex, isSameParent)`: converts UI drop coordinates (pre-removal) into post-removal destination indices, correctly adjusting by `-1` when moving downward within the same parent container (`preRemovalTargetIndex > sourceIndex`).
  - `isMoveNoop(sourceIndex, postRemovalTargetIndex, isSameParent)`: detects whether a post-removal destination index reproduces the source node's existing position.
- **`TreeMutationResult` Hardening**: Added optional `changed?: boolean;` to `TreeMutationResult` in `resources/js/types/document.ts`.
- **Zero-Mutation No-Op Invariants in `useDocumentState.ts`**:
  - `moveNode`: clamps `targetIndex` to `[0, destination.children.length]`. Identifies same-parent identity moves (`isSameParent && clampedIndex === search.index`) and returns `{ success: true, changed: false }` without modifying node arrays, preserving object identities. Valid moves return `{ success: true, changed: true }`.
  - `moveNodeRelative`: boundary moves (attempting to move first child upward or last child downward) return `{ success: true, changed: false }` instead of mutating the tree. Valid adjacent swaps return `{ success: true, changed: true }`.
  - `setDocument`: returns `changed: true` on valid schema replacement and `changed: false` on validation failure.
- **Centralized Mutation Orchestration in `EditorShell.vue`**:
  - Implemented `handleMoveNode(payload)`: enforces `props.can.edit` permission check (rejecting unauthorized moves with accessible feedback), takes a pre-mutation history snapshot, executes `moveNode`, pushes history snapshot only if `changed: true`, keeps moved node selected (`selectNode(payload.nodeId)`), and surfaces any mutation rejection errors.
  - Hardened existing `handleMoveUp` and `handleMoveDown` relative handlers to only push history snapshots if `res.success && res.changed`.
  - Wired `@move-node="handleMoveNode"` on `<LayersPanel>`.
- **Accessible User-Facing Mutation Rejection Feedback**:
  - Rejection banner in `EditorShell.vue` configured with `role="alert"` and `aria-live="assertive"`.
  - Displays descriptive error message with `AlertCircle` icon and dismiss button.
  - Auto-dismisses error state after 6 seconds via timeout cleanup on unmount; avoids adding third-party toast libraries or external dependencies.

### Verification
```text
Focused index translation tests: tests/frontend/drop-index.test.ts (9/9 passed).
Document state mutation hardening tests: tests/frontend/document-state.test.ts (32/32 passed, including 6 new Package 3D.1 invariants).
EditorShell move integration tests: tests/frontend/editor-move-integration.test.ts (6/6 passed).
Full frontend test suite (npm run test:frontend): 24 test files, 207/207 passed.
Type checking (npm run types:check): vue-tsc --noEmit passed (0 errors).
Linting & formatting (npm run check): 127 files formatted, 125 files lint-clean (0 errors).
Production build (npm run build): Vite build passed in 3.14s (Builder 165.98 kB raw / 45.27 kB gzip).
Backend checks: composer lint:check passed (Pint), composer types:check passed (PHPStan 0 errors).
Git diff check (git diff --check): pass (pre-existing CRLF conversion warnings only).
```

### Invariants Preserved
- Persisted document schema version remains 1. Zero database migrations or schema modifications.
- History snapshots (`pushSnapshot`) are only created when `changed === true`; no duplicate or redundant history entries on boundary relative moves or identity drops.
- Autosave/dirty state is not triggered on no-ops or rejected moves.
- Selection is strictly retained on the moved node across successful operations.
- Permission model preserved: viewer mode (`can.edit === false`) rejects mutations fail-closed.
- `skills-lock.json` untouched.
- HTML5 pointer drag-and-drop, visual drop zone indicators, and keyboard reparenting shortcuts (`Alt+ArrowLeft/Right`) remain deferred to Package 3D.2 / 3D.3.

## 16. Package 3D.2 — Keyboard-Accessible Structural Mutations

### Scope and Delivered Capabilities
- **History Safety Resolution & Invariants**:
  - Confirmed and verified that `EditorShell.vue` avoids speculative history snapshots. Pre-mutation document state is captured in local memory and `pushSnapshot(docBefore)` is exclusively invoked when `res.success && res.changed === true`.
  - Verified that rejected moves and no-ops leave both `past` (undo) and `future` (redo) history stacks completely untouched.
  - Verified that pending debounced prop edits (e.g. continuous typing in Inspector) are flushed into a distinct preceding history snapshot when a valid move executes, ensuring neither edits nor sibling order are lost or conflated.
- **Keyboard Structural Operations**:
  - `Alt + ArrowUp`: moves the focused node up one position among its siblings (`targetIndex = currentIndex - 1`). Sibling boundary at index 0 is a legitimate no-op.
  - `Alt + ArrowDown`: moves the focused node down one position among its siblings (`targetIndex = currentIndex + 1`). Sibling boundary at last index is a legitimate no-op.
  - `Alt + ArrowLeft`: moves the focused node out of its parent into the grandparent container immediately after the current parent (`targetIndex = parentIndex + 1`). Boundary when parent is root is a legitimate no-op.
  - `Alt + ArrowRight`: moves the focused node into its immediately preceding sibling (`targetIndex = precedingSibling.children.length`). Boundary at index 0 is a legitimate no-op. Automatically expands destination container upon move.
- **Authoritative Mutation Routing & Invariant Validation**:
  - Zero direct document mutation in `LayersPanel.vue` or `LayerTreeNode.vue`; all keyboard actions emit `moveNode` and route through `EditorShell.vue` and `useDocumentState.moveNode`.
  - Centralized enforcement of container type eligibility (`CONTAINER_COMPONENT_TYPES`), cycle prevention (`isDescendant`), child node limits (`MAX_CHILDREN_PER_NODE` = 50), tree depth limits (`MAX_TREE_DEPTH` = 10), and root node immutability.
  - Moving into a leaf component (e.g. `Heading`) is rejected authoritatively by `moveNode` with user-friendly error: `"Target 'Heading' cannot accept children because it is a leaf component."`
- **Focus & Selection Retention**:
  - `selectedNodeId` is updated to the moved node on valid moves, and retained on no-ops and rejections.
  - DOM focus is preserved on the moved node at its new tree position via `nextTick` focus management.
- **Viewer / Permission Guard**:
  - When `can.edit === false` (`props.readOnly === true`), keyboard movement shortcuts are ignored; zero mutation events are emitted, zero error alerts are shown, and document, selection, dirty state, and history are untouched.
  - Selection and read-only inspection remain completely functional for viewers.
  - Permission checks remain authoritative in `handleMoveNode`.
- **Accessible Announcements & Shortcut Discovery**:
  - Rejection alerts announced via `role="alert"` and `aria-live="assertive"` with 6s auto-dismissal.
  - Tree container configured with `aria-describedby="layers-keyboard-instructions"`, referencing visually hidden screen reader instructions.
  - Header toolbar features an accessible "Keyboard Shortcuts" button with `HelpCircle` icon and tooltip documenting movement bindings.
  - Interactive inputs (e.g. live search input) bypass shortcut interception.

### Verification
```text
Layers panel tests: tests/frontend/layers-panel.test.ts (38/38 passed).
Document state hardening tests: tests/frontend/document-state.test.ts (37/37 passed).
EditorShell move integration tests: tests/frontend/editor-move-integration.test.ts (8/8 passed).
Full frontend test suite (npm run test:frontend): 24 test files, 227/227 passed.
Type checking (npm run types:check): vue-tsc --noEmit passed (0 errors).
Linting & formatting (npm run check): 127 files formatted, 125 files lint-clean (0 errors).
Production build (npm run build): Vite build passed in 3.32s (Builder 168.03 kB raw / 45.85 kB gzip).
Backend checks: composer lint:check passed (Pint), composer types:check passed (PHPStan 0 errors).
Git diff check (git diff --check): pass (pre-existing CRLF conversion warnings only).
```

### Invariants Preserved
- Persisted document schema version remains 1. Zero database migrations or schema alterations.
- Single undo snapshot per successful move; zero history entries on rejected moves or no-ops.
- Node IDs, subtree content, and responsive overrides preserved across moves.
- Dependencies unchanged (`package.json`, `package-lock.json` untouched).
- `skills-lock.json` untouched.
- HTML5 pointer drag-and-drop, visual drop zone indicators, and drop line styling remain deferred to Package 3D.3.

## 17. Package 3D.3 — HTML5 Pointer Drag-and-Drop for Layers Panel

### Scope and Delivered Capabilities
- **Dedicated Drag Handle**:
  - Rendered exclusively on editable, non-root layer rows via `<div role="button" data-testid="drag-handle" draggable="true" ...>` with `GripVertical` icon.
  - Configured with `tabindex="-1"` and `aria-label="Drag to reorder"` to preserve the tree's single tab-stop roving tabindex.
  - Preserves standard row selection, inspection, click events, and keyboard tree navigation. Root node and viewer mode rows cannot be dragged.
- **Three Drop Zones per Layer Row**:
  - `before`: Top 25% of container rows (or top 50% of leaf rows) inserts the source node immediately before the target as a sibling.
  - `inside`: Middle 50% of container rows moves the source node inside the target container (appended at end of children).
  - `after`: Bottom 25% of container rows (or bottom 50% of leaf rows) inserts the source node immediately after the target as a sibling.
  - Empty container placeholder also accepts `inside` drops directly.
- **Visual Feedback & Drop Indicators**:
  - Valid before/after drops display a horizontal primary insertion line (`[data-testid="drop-indicator-before"]`, `[data-testid="drop-indicator-after"]`).
  - Valid inside container drops display a primary highlight ring (`ring-2 ring-primary ring-inset`).
  - Invalid drop targets (dropping inside a leaf, cycle generation, root sibling insertion) display invalid feedback (`bg-destructive` line/ring) and set `dataTransfer.dropEffect = 'none'`.
  - Active dragged row displays reduced opacity (`opacity-40 border-dashed`).
  - Visual indicators cleanly reset on `dragleave`, `dragend`, successful drop, or cancellation.
- **Pure Coordinate & Target Resolution Helper**:
  - Added `resolvePointerDropTarget` in `resources/js/lib/dropIndex.ts`:
    - Handles downward/upward same-parent coordinate shifts via `calculateDropIndex`.
    - Detects self-drops, same-parent drops before/after self, and adjacent sibling drops as no-ops (`isNoop: true`).
    - Validates container eligibility, root boundaries, and descendant cycle checks.
- **Collapsed Container Support**:
  - Dropping inside a collapsed container succeeds and immediately expands the destination container in `expandedIds`, keeping the moved node visible.
  - Preserves selection and dispatches DOM focus to the moved node via `nextTick` focus management.
- **Shared Authoritative Mutation Pipeline**:
  - Drag-and-drop emits `@moveNode` and routes exclusively through `EditorShell.handleMoveNode` and `useDocumentState.moveNode`.
  - Zero duplicate mutation logic or history pathways. Produces exactly one undo snapshot per real move, while no-op drops create zero history snapshots.
- **Viewer / Read-Only Protection**:
  - In viewer mode (`can.edit === false` / `props.readOnly === true`), drag handles are completely omitted, drag initiation is blocked, and drop handlers fail-closed without mutating document, selection, dirty state, or history.

### Verification
```text
Drop index calculation tests: tests/frontend/drop-index.test.ts (22/22 passed).
Layers panel interaction tests: tests/frontend/layers-panel.test.ts (50/50 passed).
EditorShell move integration tests: tests/frontend/editor-move-integration.test.ts (8/8 passed).
Document state mutation tests: tests/frontend/document-state.test.ts (37/37 passed).
Full frontend test suite (npm run test:frontend): 24 test files, 252/252 passed.
Type checking (npm run types:check): vue-tsc --noEmit passed (0 errors).
Linting & formatting (npm run check): 127 files formatted, 125 files lint-clean (0 errors).
Production build (npm run build): Vite build passed in 3.58s (Builder 174.38 kB raw / 47.63 kB gzip).
Backend checks: composer lint:check passed (Pint), composer types:check passed (PHPStan 0 errors).
Git diff check (git diff --check): pass (pre-existing CRLF conversion warnings only).
```

### Invariants Preserved
- Persisted document schema version remains 1. Zero database migrations or schema alterations.
- Native HTML5 Drag and Drop API used without third-party libraries or external dependencies.
- Dependencies unchanged (`package.json`, `package-lock.json` untouched).
- `skills-lock.json` untouched.
- Single history snapshot per successful move; zero history entries on no-op or rejected drops.
- Node locking, visibility toggles, and inline renaming remain out of scope.

## 18. Package 3D.4 — Drag-and-Drop Hardening & Browser Verification

### 18.1 Findings Classification & 12 Edge-Case Audit Matrix

A comprehensive code and contract audit was conducted comparing `resources/js/lib/dropIndex.ts` (`resolvePointerDropTarget`) against the authoritative state mutation engine in `resources/js/composables/useDocumentState.ts` (`moveNode`).

All 12 specified edge-case scenarios were audited and verified with automated test coverage across unit and integration layers:

1. **Move sibling upward and downward within the same parent**:
   - *Status*: PASS (No defect).
   - *Behavior*: `resolvePointerDropTarget` maps pre-removal coordinates using `calculateDropIndex(sourceIndex, targetIndex, true)` which correctly accounts for the index shift when moving downward (`target > sourceIndex ? target - 1 : target`). `useDocumentState.moveNode` executes splice removal and insertion at clamped post-removal coordinates, returning `{ success: true, changed: true }`.
   - *Coverage*: `drop-index.test.ts`, `document-state.test.ts`, and `editor-move-integration.test.ts` (Scenario 1).
2. **Drop before and after adjacent siblings**:
   - *Status*: PASS (No defect).
   - *Behavior*: Dropping before an immediate succeeding sibling (`targetIndex = sourceIndex + 1`, pre-removal `targetIndex = sourceIndex + 1`, post-removal `sourceIndex`) or dropping after an immediate preceding sibling (`targetIndex = sourceIndex - 1`, pre-removal `targetIndex = sourceIndex`, post-removal `sourceIndex`) evaluates `isMoveNoop(sourceIndex, targetIndex, true) === true`. `resolvePointerDropTarget` returns `isNoop: true`. `LayersPanel.vue` drops the action before emission; `useDocumentState.moveNode` independently returns `{ success: true, changed: false }`.
   - *Coverage*: `drop-index.test.ts`, `editor-move-integration.test.ts` (Scenario 2).
3. **Drop a node onto itself**:
   - *Status*: PASS (No defect).
   - *Behavior*: `sourceNodeId === targetNodeId` is trapped in `resolvePointerDropTarget`. `before`/`after` returns `isNoop: true`; `inside` returns `isValid: false, reason: 'Cannot drop a node inside itself.'`. `useDocumentState.moveNode` independently checks `isDescendant(targetParentId, nodeId)` which catches self-drops and fails closed.
   - *Coverage*: `drop-index.test.ts`, `editor-move-integration.test.ts` (Scenario 3).
4. **Append a non-final child to its own parent**:
   - *Status*: PASS (No defect).
   - *Behavior*: When node at `sourceIndex < targetChildCount - 1` is dropped `inside` its current parent, `calculateDropIndex(sourceIndex, targetChildCount, true)` yields `targetChildCount - 1`. `isMoveNoop(sourceIndex, targetChildCount - 1, true) === false`. The node is removed from its original index and appended as the new final child. Exactly one history snapshot is pushed.
   - *Coverage*: Added explicit test in `drop-index.test.ts` and `editor-move-integration.test.ts` (Scenario 4).
5. **Drop the final child inside its current parent**:
   - *Status*: PASS (No defect).
   - *Behavior*: When the node is already the last child (`sourceIndex === targetChildCount - 1`) and dropped `inside` its parent, `calculateDropIndex` computes `targetChildCount - 1 === sourceIndex`. `isMoveNoop` identifies this as a no-op (`isNoop: true`). No mutation is emitted, zero history snapshots are created, and autosave is untouched.
   - *Coverage*: `drop-index.test.ts`, `editor-move-integration.test.ts` (Scenario 5).
6. **Move a node from one parent into another parent**:
   - *Status*: PASS (No defect).
   - *Behavior*: Cross-parent moves (`sourceParentId !== targetParentId`) bypass same-parent index decrementing in `calculateDropIndex`. The source node is spliced from the source container and inserted into the destination container at the exact specified index. Node ID, subtree, and styles are preserved. Exactly one history snapshot is created, and selection is retained on the moved node.
   - *Coverage*: `drop-index.test.ts`, `document-state.test.ts`, `editor-move-integration.test.ts` (Scenario 6).
7. **Move a container into its descendant (cycle rejection)**:
   - *Status*: PASS (No defect).
   - *Behavior*: `LayersPanel.vue` verifies `isDescendantInDoc(targetId, sourceId)` during `dragover` and `drop`, setting `isValid: false`, showing invalid drop indicator (`bg-destructive`), setting `dropEffect = 'none'`, and blocking drop execution. Authoritatively, `useDocumentState.moveNode` guards with `isDescendant(targetParentId, nodeId)` and returns `{ success: false, changed: false, error: 'Cannot move a container into itself or its own descendants.' }`. `EditorShell.vue` displays an accessible `role="alert"` banner.
   - *Coverage*: `drop-index.test.ts`, `document-state.test.ts`, `editor-move-integration.test.ts` (Scenario 7).
8. **Attempt to move the root or create a sibling of root (root protection)**:
   - *Status*: PASS (No defect).
   - *Behavior*: Root node has no drag handle (`isRoot` guard). If drag is attempted, `handleNodeDragStart` calls `e.preventDefault()`. `handleNodeDrop` rejects if `sourceId === root.id`. Dropping `before`/`after` root is rejected in `resolvePointerDropTarget` (`targetParentId === null`). `useDocumentState.moveNode` guards with `if (nodeId === root.id)` returning `{ success: false, changed: false, error: 'The root node cannot be moved.' }`.
   - *Coverage*: `drop-index.test.ts`, `layers-panel.test.ts`, `document-state.test.ts`, `editor-move-integration.test.ts` (Scenario 8).
9. **Destination container reaches the 50-child limit (`DOCUMENT_LIMITS.MAX_CHILDREN_PER_NODE`)**:
   - *Status*: PASS (No defect).
   - *Behavior*: `useDocumentState.moveNode` checks `if (!isSameParent && targetChildCount >= MAX_CHILDREN_PER_NODE)` and rejects with `{ success: false, changed: false, error: 'Move rejected: target container already has the maximum of 50 children.' }`. `EditorShell.vue` displays the accessible alert; document structure, history stacks, and dirty state are untouched. Note that reordering within a container with 50 children is permitted since the count does not increase.
   - *Coverage*: `document-state.test.ts`, `editor-move-integration.test.ts` (Scenario 9).
10. **Resulting tree reaches or exceeds maximum depth 10 (`DOCUMENT_LIMITS.MAX_TREE_DEPTH`)**:
    - *Status*: PASS (No defect).
    - *Behavior*: `useDocumentState.moveNode` calculates subtree height (`getMaxDepth(search.node)`) and validates `targetSearch.depth + movingSubtreeHeight <= MAX_TREE_DEPTH`. If exceeded, rejects with `{ success: false, changed: false, error: 'Move rejected: would exceed maximum tree depth of 10.' }`. Displayed via `role="alert"`; zero history snapshots.
    - *Coverage*: `document-state.test.ts`, `editor-move-integration.test.ts` (Scenario 10).
11. **Destination container is collapsed (auto-expansion verification)**:
    - *Status*: PASS (No defect).
    - *Behavior*: When dropped `inside` a collapsed container, `LayersPanel.vue` emits the valid move and immediately adds `resolved.targetParentId` to `expandedIds.value`. In addition, selection of the moved node triggers `expandAncestorsOf(newId)`, ensuring all ancestors are expanded and the moved node is visible and focused.
    - *Coverage*: `layers-panel.test.ts` ("expands collapsed container after a successful inside drop").
12. **Source or destination no longer exists by drop time (stale ID rejection)**:
    - *Status*: PASS (No defect).
    - *Behavior*: In `LayersPanel.vue`, `handleNodeDrop` verifies `findNodeInDoc` for both source and target, returning early if either is missing. If bypassed, `useDocumentState.moveNode` validates `findNode(nodeId)` and `findNode(targetParentId)`, returning `{ success: false, changed: false, error: "..." }`. No unhandled exceptions occur; history and tree remain consistent.
    - *Coverage*: `document-state.test.ts`, `editor-move-integration.test.ts` (Scenario 12).

---

### 18.2 Repository Browser Automation Tooling Audit

- **Environment & Tooling Audit**:
  - The repository's test framework is Vitest 4 (`vp test --run`) running in a `happy-dom` synthetic DOM environment with `@vue/test-utils`.
  - No end-to-end browser automation drivers (Playwright, Puppeteer, Cypress, Selenium, or Vitest browser provider) are installed in `package.json`.
  - In strict compliance with project constraints, no browser packages were installed, and `package.json`/`package-lock.json` remain untouched.
- **Verification Strategy**:
  - All DOM event handling, coordinates calculation, dataTransfer lifecycle, reactivity, and history state transitions are fully verified in happy-dom via `@vue/test-utils` (24 test files, 264 passed tests).
  - Native browser rendering, drag ghosting, cursor styles, and panel scroll ergonomics cannot be automated without new browser dependencies, and are documented below in the Manual QA Checklist.

---

### 18.3 Exhaustive Manual Browser QA Verification Matrix

| Test ID | Area / Feature | Action / Steps | Expected Behavior | Invariant / Boundary Check |
|---|---|---|---|---|
| **QA-DnD-01** | Drag Handle Appearance | Hover over a non-root layer row in Layers panel | Dedicated 6-dot drag handle (`GripVertical`) appears with `cursor: grab`. Root row does not have a drag handle. | Root immutability; roving tabindex unpolluted (`tabindex="-1"`). |
| **QA-DnD-02** | Drag Initiation | Click and drag the drag handle | Handle changes to `cursor: grabbing`. Dragged row switches to `opacity-40` with dashed border. Browser shows ghost drag image. | Selection on other elements is not lost during drag initiation. |
| **QA-DnD-03** | Zone Indicator: Before | Drag node over the top 25% of any container row (or top 50% of leaf row) | A solid primary-colored horizontal line appears at the top border of the target row. Cursor remains `move`. | Target index corresponds to target node's position. |
| **QA-DnD-04** | Zone Indicator: After | Drag node over the bottom 25% of any container row (or bottom 50% of leaf row) | A solid primary-colored horizontal line appears at the bottom border of the target row. Cursor remains `move`. | Target index corresponds to target node's position + 1. |
| **QA-DnD-05** | Zone Indicator: Inside | Drag node over the middle 50% of any container row | A primary highlight ring (`ring-2 ring-primary ring-inset`) and background highlight enclose the target container row. | Target index corresponds to end of container children array. |
| **QA-DnD-06** | Invalid Drop Feedback | Drag a node over an invalid target (e.g. inside a leaf, or dragging container over its child) | Drop indicators render in destructive color (`bg-destructive` red). Cursor displays "not-allowed" (`dropEffect = 'none'`). | Rejection feedback is immediate without requiring release. |
| **QA-DnD-07** | Drag Cancellation | Drag a node and press `Escape` or release outside the tree | All drop indicators, highlight rings, and dashed opacity immediately disappear. Zero document changes occur. | Zero history snapshots pushed; dirty state unchanged. |
| **QA-DnD-08** | Scroll Stability | In a tall Layers tree, scroll down and drag a node | Coordinate detection (`offsetY = e.clientY - rect.top`) computes accurately regardless of scroll position. No indicator jumping or offset drift. | Viewport-relative `getBoundingClientRect` cancels scroll offset. |
| **QA-DnD-09** | Collapsed Auto-Expand | Drop a node inside a collapsed container | The destination container automatically expands in the Layers panel; the dropped node is visible, selected, and focused. | UI expansion state updates immediately; node is selected. |
| **QA-DnD-10** | Undo / Redo Symmetry | Perform valid drag reorder, then click Undo (`Ctrl+Z`), then Redo (`Ctrl+Y`) | Undo immediately restores original parent and position. Redo restores the moved position deterministically. | Exactly 1 undo snapshot; 1 redo entry; autosave clean after undo to saved state. |
| **QA-DnD-11** | Debounced Edits Grouping | Type text in Inspector, then immediately drag and drop a layer node before debounce expires | Typing changes are preserved in a distinct undo step. Pressing Undo once undos the move; pressing Undo twice undos the typing. | `flushPending()` isolates prop edit snapshot before move snapshot. |
| **QA-DnD-12** | Viewer / Read-Only Guard | Log in or view page as Viewer (`can.edit === false`) | Drag handles are completely absent. Attempted dragging on rows is blocked. Drop handlers fail closed. | Read-only permissions enforced fail-closed at UI and API layers. |

---

### 18.4 Verification Evidence

```text
Full frontend test suite (npm run test:frontend): 24 test files, 264/264 passed.
- tests/frontend/drop-index.test.ts (23/23 passed).
- tests/frontend/layers-panel.test.ts (50/50 passed).
- tests/frontend/editor-move-integration.test.ts (19/19 passed).
- tests/frontend/document-state.test.ts (37/37 passed).
- tests/frontend/editor-save-integrity.test.ts (7/7 passed).
Type checking (npm run types:check): vue-tsc --noEmit passed (0 errors).
Linting & formatting (npm run check): 127 files formatted, 125 files lint-clean (0 errors).
Production build (npm run build): Vite build passed in 3.76s (Builder 174.38 kB raw / 47.63 kB gzip).
Backend checks: composer lint:check passed (Pint), composer types:check passed (PHPStan 0 errors).
Git diff check (git diff --check): pass (pre-existing CRLF conversion warnings only).
```

### 18.5 Invariants Preserved
- Persisted document schema version remains 1. Zero database migrations or schema alterations.
- Native HTML5 Drag and Drop API used without third-party libraries or external dependencies.
- Zero new dependencies added; `package.json` and `package-lock.json` untouched.
- `skills-lock.json` untouched.
- Single history snapshot per successful move; zero history entries on no-op or rejected drops.
- Node locking, visibility toggles, and inline renaming remain deferred to subsequent packages.

## 19. Package 3E.1 — Node Locking: Schema, Validation & Mutation Engine

### Scope and Delivered Capabilities
- **Document Schema & Types**:
  - Added optional `locked?: boolean;` to `DocumentNode` in `resources/js/types/document.ts`.
  - Maintained `schemaVersion: 1` backward compatibility; missing/omitted `locked` defaults to unlocked (`false`).
- **Backend Validation & Preservation**:
  - Updated `app/Domains/DocumentEngine/Validation/DocumentValidator.php` (`validateNode`):
    - Validates that `locked` must be an explicit boolean (`is_bool`). Invalid types (strings, integers, arrays, null) fail closed with validation error `invalid_locked_type`.
    - Preserves `locked: true` through backend sanitization into `$nodeResult['locked'] = true`.
    - Normalizes `locked: false` to omitted, keeping persisted document JSON compact and backward-compatible.
- **Composable Locking Helpers**:
  - Added pure hierarchy helpers in `resources/js/composables/useDocumentState.ts`:
    - `isNodeLocked(nodeId: string): boolean`: checks whether the specific node has `locked === true`.
    - `isNodeOrAncestorLocked(nodeId: string): boolean`: traverses upward through `parent` ancestry to detect inherited locks from any ancestor container or root.
    - `hasLockedDescendant(node: DocumentNode): boolean`: recursively inspects node's subtree to detect if any child is locked.
    - `setNodeLocked(nodeId: string, locked: boolean): TreeMutationResult`: toggles a node's lock state with `{ success: true, changed: true }` on actual changes and `{ success: true, changed: false }` on no-ops. Allows toggling individual child locks within locked ancestors, retaining the child's lock state upon parent unlock.
- **Fail-Closed Mutation Engine Guards**:
  - Hardened all 8 mutation APIs in `useDocumentState.ts`:
    - `insertNode`: rejects insertions targeting locked destination containers (inside or before/after sibling) or locked root container.
    - `updateNodeProps`: rejects prop edits on locked nodes or nodes with locked ancestors.
    - `updateNodeStyle`: rejects responsive style edits on locked nodes or nodes with locked ancestors.
    - `resetNodeStyle`: rejects responsive style resets on locked nodes or nodes with locked ancestors.
    - `deleteNode`: rejects deleting locked nodes, nodes protected by locked ancestors, or containers containing locked descendants.
    - `duplicateNode`: rejects duplicating locked nodes or nodes inside locked containers.
    - `moveNodeRelative`: rejects sibling reordering on locked nodes or nodes inside locked containers.
    - `moveNode`: rejects moves if source ancestry is locked OR destination ancestry is locked (enforcing cross-parent and same-parent safety).
- **EditorShell Orchestration & Permissions**:
  - Implemented `handleSetNodeLocked` in `EditorShell.vue` with fail-closed permission check (`can.edit === true`), pushing exactly one history snapshot per real lock change (`pushSnapshot(docBefore)`), with zero snapshots on no-ops or rejected changes.
  - Hardened `handleUpdateProp`, `handleUpdateStyle`, `handleResetStyle`, `handleDelete`, and `handleDuplicate` in `EditorShell.vue` to capture pre-mutation state and record history snapshots exclusively upon successful mutations, presenting accessible rejection alerts (`role="alert"`) on locked targets without dirtying the document.

### Verification Evidence
```text
PHPUnit / Pest backend tests: 32/32 passed in tests/Unit/DocumentEngine/DocumentValidatorTest.php.
- test_missing_locked_defaults_to_unlocked_and_is_omitted_from_sanitized_output
- test_locked_true_survives_backend_sanitization
- test_rejects_invalid_locked_types_with_invalid_locked_type_error
- test_locked_false_is_valid_and_omitted_as_unlocked
Full PHP test suite (php artisan test): 331 discovered, 305 passed, 26 PostgreSQL-only skipped (0 failures).
Frontend targeted tests: 47/47 in document-state.test.ts, 23/23 in editor-move-integration.test.ts.
Full frontend test suite (npm run test:frontend): 24 test files, 278/278 passed (0 failures).
Type checking (npm run types:check): vue-tsc --noEmit passed (0 errors).
Linting & formatting (npm run check): 127 files formatted, 125 files lint-clean (0 errors).
Production build (npm run build): Vite build passed in 3.03s (Builder 177.11 kB raw / 48.10 kB gzip).
Backend checks: composer lint:check passed (Pint), composer types:check passed (PHPStan 0 errors).
Git diff check (git diff --check): pass (pre-existing CRLF conversion warnings only).
```

### Invariants Preserved
- Persisted document schema version remains 1. Zero database migrations or schema alterations.
- Authoritative page permission model (`can.edit === false` / Viewer mode) completely blocks locking and unlocking.
- Zero history snapshots pushed and document is not marked dirty on rejected mutations or lock no-ops.
- Root node may be locked, protecting all descendants, but visibility remains out of scope.
- Layers lock UI buttons, Inspector read-only banner, and canvas UI modifications deferred to Package 3E.2.

## 20. Package 3E.2 — Layer Locking UI, Inspector Read-Only & Canvas Safeguards

### Scope and Delivered Capabilities
- **Layers Panel Lock Controls (`LayerTreeNode.vue` & `LayersPanel.vue`)**:
  - Direct lock/unlock action button rendered on each layer row:
    - Directly locked node: renders `<button data-testid="lock-toggle">` with `Lock` icon, accessible name (`aria-label="Unlock ${componentLabel}"`, `title="Unlock ${componentLabel}"`), and `tabindex="-1"`.
    - Unlocked editable node: exposes lock control on row hover (`group-hover:opacity-100 opacity-0`) with `Unlock` icon, accessible name (`aria-label="Lock ${componentLabel}"`, `title="Lock ${componentLabel}"`), and `tabindex="-1"`.
    - Click event utilizes `@click.stop="handleToggleLock"` to prevent unintended row selection or canvas focusing.
    - Preserves existing single-tab-stop roving tabindex contract, keyboard navigation, and WAI-ARIA tree semantics.
  - Inherited Locking Indicator:
    - Descendants of locked containers display non-interactive `[data-testid="inherited-lock-indicator"]` with accessible label/tooltip (`"Locked by parent container (${effectiveProtectingLabel})"`).
    - Descendants protected by an ancestor lock do not falsely display a direct lock button or permit bypassing the parent lock.
    - Viewer mode (`readOnly === true`) renders non-interactive `[data-testid="lock-indicator"]` for locked rows without actionable toggle buttons.
- **Drag-and-Drop & Mutation UI Safeguards**:
  - Drag handles are suppressed (`v-if="!readOnly && !isRoot && !isEffectiveLocked"`) for directly locked nodes and nodes protected by locked ancestors.
  - Drop Target Validation in `resolvePointerDropTarget` (`dropIndex.ts`):
    - Added `sourceIsLocked`, `targetIsLocked`, and `targetParentIsLocked` checks.
    - Drops inside locked containers and drops before/after children of locked containers are marked invalid (`isValid: false`, `reason: 'Target container is locked.'`).
    - Visual dragover feedback sets destructive styling (`bg-destructive`) and `dropEffect = 'none'`.
    - Drop handlers fail-closed without emitting `moveNode`.
    - Keyboard structural moves (`Alt+ArrowUp`, `Alt+ArrowDown`, `Alt+ArrowLeft`, `Alt+ArrowRight`) are suppressed at the UI layer for locked source nodes or locked target containers.
- **Inspector Read-Only Safeguards (`InspectorPanel.vue`)**:
  - Displays persistent, accessible lock banner (`[data-testid="inspector-lock-banner"]`) with `role="status"` and `aria-live="polite"`:
    - Directly locked: `"This component is locked. Unlock it in the Layers panel to make changes."`
    - Inherited lock: `"This component is locked by its ancestor (${lockedAncestorLabel}). Unlock it in the Layers panel to make changes."`
  - Computes `isEffectiveReadOnly = readOnly || isNodeProtected`.
  - Content, style, and settings property inputs, selects, textareas, and color pickers are disabled while remaining fully inspectable.
  - Quick Duplicate and Quick Delete buttons are omitted for locked nodes.
  - Mutation handlers (`handlePropChange`, `handleResetStyle`, `openMediaLibrary`, `handleMediaSelect`) fail closed.
  - Pre-mutation state capture prevents pending debounced edits from mutating newly locked nodes.
- **Canvas Safeguards (`NodeToolbar.vue` & `NodeRenderer.vue`)**:
  - Added `:is-locked="isEffectiveLocked"` prop to `NodeToolbar.vue`.
  - Disables Move Up, Move Down, Duplicate, and Delete buttons on Canvas toolbar when node is directly locked or protected by an ancestor.
  - Select Parent action (`CornerLeftUp`) remains enabled to allow non-mutating navigation out of locked subtrees.
  - Displays visual `Lock` icon badge next to component type in `NodeToolbar`.
  - Canvas DropZones inside locked containers are deactivated, preventing dropping library components into locked containers.

### Verification Evidence
```text
Full frontend test suite (npm run test:frontend): 24 test files, 300/300 passed (0 failures).
- tests/frontend/drop-index.test.ts (26/26 passed, including 3 new Package 3E.2 locking target tests).
- tests/frontend/layers-panel.test.ts (60/60 passed, including 10 new Package 3E.2 locking interaction tests).
- tests/frontend/inspector-interactions.test.ts (10/10 passed, including 6 new Package 3E.2 Inspector lock banner & disabled control tests).
- tests/frontend/editor-move-integration.test.ts (26/26 passed, including NodeToolbar locking safeguards & EditorShell orchestration tests).
- tests/frontend/document-state.test.ts (47/47 passed).
Type checking (npm run types:check): vue-tsc --noEmit passed (0 errors).
Linting & formatting (npm run check): 127 files formatted, 125 files lint-clean (0 errors).
Production build (npm run build): Vite build passed in 3.53s (Builder 183.08 kB raw / 49.44 kB gzip).
Backend checks:
- composer lint:check passed (Pint).
- composer types:check passed (PHPStan 0 errors).
- php artisan test tests/Unit/DocumentEngine/DocumentValidatorTest.php (32/32 passed, 136 assertions).
- php artisan test (331 discovered, 305 passed, 26 PostgreSQL-only skipped, 0 failures).
Git diff check (git diff --check): pass (pre-existing CRLF conversion warnings only).
```

### Invariants Preserved
- Persisted document schema version remains 1. Zero database migrations or schema alterations.
- Authoritative page permission model (`can.edit === false` / Viewer mode) completely blocks locking and unlocking.
- Global visibility and published rendering (`DocumentHtmlRenderer.php`) were completely untouched.
- Single history snapshot per successful lock toggle; zero history entries on rejected mutations or no-ops.
- Dependencies unchanged (`package.json`, `package-lock.json` untouched).
- `skills-lock.json` untouched.
- Visibility toggles (eye icons, `hidden` property) remain deferred to Package 3E.3.

## 21. Package 3E.3 — Layer Visibility, Inherited Visibility & Rendering Alignment

### Scope and Delivered Capabilities
- **Schema & Types Alignment (`resources/js/types/document.ts`)**:
  - Added optional `hidden?: boolean;` to `DocumentNode`.
  - Missing/omitted property implies visible (`false`).
  - Preserved backward-compatible `schemaVersion: 1` with zero database migrations or breaking changes.
- **Backend Schema & Invariant Validation (`DocumentValidator.php`)**:
  - Validates `hidden` property is strictly boolean (`invalid_hidden_type`).
  - Validates that the root node cannot be globally hidden (`root_cannot_be_hidden`).
  - Preserves boolean `hidden: true` and `hidden: false` through sanitization for non-root nodes.
- **Published HTML Omission (`DocumentHtmlRenderer.php`)**:
  - In `renderNode`: `if (($node['hidden'] ?? false) === true) return '';`.
  - Globally hidden nodes and their entire subtrees are completely omitted and absent from published HTML output, not merely visually concealed via CSS.
  - Responsive visibility (`styles.tablet.display`, `styles.mobile.display`) remains independent and continues to generate media query rules.
- **Composable State Engine (`useDocumentState.ts`)**:
  - `validateDocumentTree`: rejects non-boolean `hidden` and rejects documents with `root.hidden === true`.
  - `cloneNodeWithNewIds`: preserves `hidden: node.hidden ? true : undefined` across duplicate operations.
  - Implemented `isNodeHidden(nodeId: string): boolean`: checks direct node visibility flag.
  - Implemented `getHidingAncestor(nodeId: string): DocumentNode | null`: walks parent ancestry to identify the closest hidden container.
  - Implemented `isNodeOrAncestorHidden(nodeId: string): boolean`: returns true if node or any ancestor is hidden.
  - Implemented `setNodeHidden(nodeId: string, hidden: boolean): TreeMutationResult`:
    - Fails closed on root node (`"The root node cannot be hidden."`).
    - Preserves locking invariants: rejects modifying visibility of locked nodes or nodes inside locked containers (`"Cannot change visibility of locked node or node inside a locked container."`).
    - Reports `{ success: true, changed: false }` on no-ops.
    - Preserves active node selection across visibility changes.
- **Layers Panel UI (`LayerTreeNode.vue` & `LayersPanel.vue`)**:
  - Non-root layer rows feature an Eye toggle button (`Eye` / `EyeOff` from `@lucide/vue`):
    - Directly hidden editable node: renders `<button data-testid="visibility-toggle">` with `EyeOff` icon, `title="Show ${componentLabel}"`, and `tabindex="-1"`.
    - Visible editable node: reveals on row hover with `Eye` icon, `title="Hide ${componentLabel}"`, and `tabindex="-1"`.
    - Inherited hidden descendant: renders non-interactive `<span data-testid="inherited-hidden-indicator">` with `title="Hidden by ancestor (${effectiveHidingLabel})"`.
    - Locked node: renders disabled `<button data-testid="visibility-toggle-disabled">` preventing visibility toggling.
    - Viewer mode (`readOnly === true`): renders non-interactive `<span data-testid="visibility-indicator">` without toggle buttons.
    - Root row: strictly omits visibility toggle and indicators (`!isRoot`).
    - Event isolation: `@click.stop="handleToggleHidden"` prevents accidental row selection or canvas focus.
    - Visual communication: globally hidden rows display with reduced opacity (`opacity-50`) while remaining selectable, focusable, expandable, and accessible.
- **Editor Canvas Alignment (`NodeRenderer.vue`)**:
  - Globally hidden nodes remain visible and discoverable in the canvas so authors can inspect and edit them.
  - Rendered with dashed border and ghosted opacity (`opacity-50 border-dashed border-zinc-400`).
  - Displays persistent visual badge: `<div data-testid="globally-hidden-badge">` with `EyeOff` icon and `Hidden` label.
  - Coordinates gracefully with responsive badges (`Hidden on mobile`).
  - Passes `:parent-hidden="isGloballyHidden"` recursively down the component tree.
- **EditorShell History Orchestration (`EditorShell.vue`)**:
  - Implemented `handleSetNodeHidden` with permission enforcement (`can.edit === true`), pushing exactly one history snapshot per real visibility mutation (`pushSnapshot(docBefore)`), with zero snapshots on no-ops or rejected changes.
  - Rejections surface accessible error alerts (`role="alert"`).
  - Exposes visibility helpers in `defineExpose`.

### Verification Evidence
```text
Full frontend test suite (npm run test:frontend): 24 test files, 316/316 passed (0 failures).
- tests/frontend/document-state.test.ts (53/53 passed, including 6 new Package 3E.3 visibility engine tests).
- tests/frontend/layers-panel.test.ts (66/66 passed, including 6 new Package 3E.3 Layers panel visibility tests).
- tests/frontend/editor-move-integration.test.ts (30/30 passed, including 4 new Package 3E.3 EditorShell history & permission integration tests).
Type checking (npm run types:check): vue-tsc --noEmit passed (0 errors).
Linting & formatting (npm run check): 127 files formatted, 125 files lint-clean (0 errors).
Production build (npm run build): Vite build passed in 3.73s (Builder 187.89 kB raw / 50.25 kB gzip).
Backend checks:
- composer lint:check passed (Pint).
- composer types:check passed (PHPStan 0 errors).
- php artisan test tests/Unit/DocumentEngine/DocumentValidatorTest.php (37/37 passed, 162 assertions).
- php artisan test tests/Unit/Publishing/DocumentHtmlRendererTest.php (29/29 passed, 149 assertions).
- php artisan test (341 discovered, 315 passed, 26 PostgreSQL-only skipped, 0 failures).
Git diff check (git diff --check): pass (clean, pre-existing CRLF conversion warnings only).
```

### Invariants Preserved
- Persisted document schema version remains 1. Zero database migrations or schema alterations.
- Authoritative page permission model (`can.edit === false` / Viewer mode) completely blocks visibility mutations.
- Hidden nodes are completely omitted from published HTML output, not visually concealed by CSS.
- Responsive visibility (`styles.*.display`) remains completely independent and intact.
- Locking invariants strictly preserved: locked nodes cannot have their visibility changed.
- Single history snapshot per successful visibility change; zero history entries on rejected mutations or no-ops.
- Dependencies unchanged (`package.json`, `package-lock.json` untouched).
- `skills-lock.json` untouched.

## 22. Package 3E.4 — Visibility & Locking Integrity Audit and Regression Hardening

Date: 2026-10-25
Scope: Bounded audit and regression-hardening increment across Packages 3E.1, 3E.2, and 3E.3. Zero new user-facing features added.

### 22.1 Complete Audit Matrix

| Audit Area | Invariant / Requirement | Status | Supporting Test / Verified Code Path |
|---|---|---|---|
| **1. Schema & Validation** | Missing `locked` and `hidden` retain documented defaults (unlocked `false`, visible `false`) | **PASS** | `DocumentValidatorTest::test_missing_locked_defaults_to_unlocked_and_is_omitted_from_sanitized_output`<br>`DocumentValidatorTest::test_missing_hidden_defaults_to_visible_and_is_omitted_from_sanitized_output` |
| | Valid booleans survive validation, serialization, save, and reload | **PASS** | `PagePersistenceTest::test_save_and_reload_preserves_locked_and_hidden_properties_across_database_roundtrip`<br>`DocumentValidatorTest::test_locked_true_survives_backend_sanitization`<br>`DocumentValidatorTest::test_hidden_true_survives_backend_sanitization_for_non_root_nodes` |
| | Invalid types are rejected fail-closed | **PASS** | `DocumentValidatorTest::test_rejects_invalid_locked_types_with_invalid_locked_type_error`<br>`DocumentValidatorTest::test_rejects_invalid_hidden_types_with_invalid_hidden_type_error`<br>`PagePersistenceTest::test_save_rejects_invalid_locked_or_hidden_types_with_422`<br>`document-state.test.ts:1337`, `:1520` |
| | Root hiding is strictly rejected | **PASS** | `DocumentValidatorTest::test_root_cannot_be_hidden_and_is_rejected_with_root_cannot_be_hidden`<br>`PagePersistenceTest::test_save_rejects_hidden_root_with_422`<br>`useDocumentState.ts:85`, `:388`<br>`document-state.test.ts:1404`, `:1545`<br>`LayerTreeNode.vue:208`, `:457` |
| | `schemaVersion: 1` remains compatible with existing documents | **PASS** | Full PHP suite (321 passing tests, 0 schema failures); full frontend suite (320 passing tests). Unmodified legacy documents load, validate, and save seamlessly. |
| **2. Locking & Visibility Interaction** | Directly locked nodes cannot be edited, moved, deleted, duplicated, or have visibility changed | **PASS** | `document-state.test.ts:1157` (edit)<br>`document-state.test.ts:1270` (move)<br>`document-state.test.ts:1195` (delete & duplicate)<br>`document-state.test.ts:1419` (visibility change)<br>`editor-move-integration.test.ts:848`, `:891` |
| | Descendants of locked containers cannot bypass inherited locking | **PASS** | `document-state.test.ts:1157`, `:1195`, `:1270`, `:1419`<br>`layers-panel.test.ts:1821`<br>`dropIndex.ts:112` (`sourceIsLocked`, `targetIsLocked`, `targetParentIsLocked`) |
| | Authorized users can unlock protecting ancestor using established workflow | **PASS** | `layers-panel.test.ts:1783`<br>`editor-move-integration.test.ts:800`<br>`LayerTreeNode.vue:197`, `:540` (`handleToggleLock` on ancestor) |
| | Unlocking container does not change descendants' own lock or visibility values | **PASS** | `document-state.test.ts` ("unlocking a container does not mutate descendants own lock or visibility values")<br>`useDocumentState.ts:323` (targets only specified node) |
| | Visibility changes do not change lock state; lock changes do not change visibility state | **PASS** | `document-state.test.ts` ("visibility changes do not change lock state, and lock changes do not change visibility state")<br>`useDocumentState.ts:342`, `:415` |
| | Viewer mode cannot mutate either property | **PASS** | `editor-move-integration.test.ts:827` (lock blocked)<br>`editor-move-integration.test.ts:1090` (hidden blocked)<br>`layers-panel.test.ts:1865`, `:2165` (toggle buttons omitted)<br>`EditorShell.vue:453`, `:473` |
| **3. Tree & Canvas** | Hidden nodes remain selectable and inspectable | **PASS** | `layers-panel.test.ts:2103`<br>`NodeRenderer.vue:149`, `:177`<br>`InspectorPanel.vue:103`<br>Hidden elements retain `role="treeitem"`, tab navigation, and click-to-select in canvas and layers |
| | Inherited hidden indicators do not mutate descendants | **PASS** | `layers-panel.test.ts:2142`<br>`document-state.test.ts:1473`<br>`LayerTreeNode.vue:495` (renders non-interactive span, zero mutations) |
| | Root visibility controls remain unavailable | **PASS** | `layers-panel.test.ts:2067`<br>`LayerTreeNode.vue:208`, `:457`<br>`NodeToolbar.vue:960` |
| | Locked and inherited-locked nodes cannot bypass drag/drop or keyboard structural guards | **PASS** | `dropIndex.ts:112` (`resolvePointerDropTarget`)<br>`layers-panel.test.ts:1821`, `:1841`<br>`LayerTreeNode.vue:221` (`handleDragStart`), `:360` (drag handle omitted) |
| | Existing selection, tree navigation, and roving tabindex remain correct | **PASS** | `layers-panel.test.ts:476` (Keyboard Navigation), `:634` (Semantics), `:2004` (single tab-stop preservation with lock controls), `:2215` (opacity-50 retains treeitem role) |
| **4. History & Persistence** | Real lock and visibility changes create exactly one undoable snapshot | **PASS** | `editor-move-integration.test.ts:792` (lock snapshot)<br>`editor-move-integration.test.ts:1026` (hidden snapshot)<br>`EditorShell.vue:458`, `:478` (`pushSnapshot(docBefore)` only on `res.success && res.changed`) |
| | Undo and redo restore exact previous values | **PASS** | `editor-move-integration.test.ts:792` (lock undo/redo)<br>`editor-move-integration.test.ts:1026` (hidden undo/redo) |
| | No-op and rejected operations create no additional snapshot and do not dirty document | **PASS** | `editor-move-integration.test.ts:811` (lock no-op)<br>`editor-move-integration.test.ts:1063` (hidden no-op and rejected root)<br>`useDocumentState.ts:339`, `:411` |
| | Save and reload preserve both properties | **PASS** | `PagePersistenceTest::test_save_and_reload_preserves_locked_and_hidden_properties_across_database_roundtrip` |
| | Pending debounced edits cannot bypass locking or overwrite a different selection's state | **PASS** | `editor-move-integration.test.ts` ("isolates pending debounced edits prior to locking, preventing subsequent edits from bypassing lock")<br>`editor-move-integration.test.ts` ("does not overwrite a different selection when switching nodes during edits")<br>`useEditorHistory.ts:55` (`pushSnapshot` calls `flushPending`) |
| **5. Published Output** | Hidden nodes and their descendants are absent from generated HTML | **PASS** | `DocumentHtmlRendererTest::test_directly_hidden_leaf_is_omitted_from_published_html`<br>`DocumentHtmlRendererTest::test_hidden_container_and_all_descendants_are_omitted_from_published_html`<br>`ReleaseStagingTest::test_staged_release_strictly_omits_hidden_nodes_and_their_descendants` |
| | Hidden content is not emitted merely with `display: none` | **PASS** | `DocumentHtmlRendererTest::test_hidden_content_is_completely_absent_not_merely_css_concealed`<br>`DocumentHtmlRenderer.php:223` (`if (($node['hidden'] ?? false) === true) return '';`) |
| | Visible nodes retain their existing markup | **PASS** | `DocumentHtmlRendererTest:29` (full component rendering suite intact); `ReleaseStagingTest::test_staged_release_strictly_omits_hidden_nodes_and_their_descendants` |
| | Responsive tablet/mobile visibility continues to work independently | **PASS** | `DocumentHtmlRendererTest::test_responsive_visibility_still_works_for_visible_nodes`<br>`DocumentHtmlRenderer.php:853` (generates `@media (min-width: ...)` CSS rules independently) |
| | Rendering does not mutate stored document | **PASS** | `DocumentHtmlRendererTest::test_rendering_does_not_mutate_the_stored_document` |
| | Inspect every supported production rendering entry point for hidden-content leaks | **PASS** | 1. `ReleaseStagingService`: `ReleaseStagingTest::test_staged_release_strictly_omits_hidden_nodes_and_their_descendants`<br>2. `PagePreviewController`: `PagePreviewControllerTest::test_preview_strictly_omits_hidden_nodes_and_their_descendants`<br>3. `DocumentHtmlRenderer`: Unit test suite (30/30 passed) |

---

### 22.2 Defects Found and Remediations Applied

- **Defect Analysis**: Zero regression bugs or runtime logic defects were found in the core engine implementations of Packages 3E.1, 3E.2, or 3E.3. The fail-closed guards in `DocumentValidator.php`, `useDocumentState.ts`, `DocumentHtmlRenderer.php`, `LayerTreeNode.vue`, and `EditorShell.vue` performed as designed.
- **Coverage Gap Remediations Applied**:
  1. *Persistence DB Roundtrip Gap*: `tests/Feature/Pages/PagePersistenceTest.php` lacked tests asserting that `locked` and `hidden` survive database save and reload. Added `test_save_and_reload_preserves_locked_and_hidden_properties_across_database_roundtrip`, `test_save_rejects_invalid_locked_or_hidden_types_with_422`, and `test_save_rejects_hidden_root_with_422`.
  2. *Production Release Staging Omission Gap*: `tests/Feature/Publishing/ReleaseStagingTest.php` lacked verification that disk-staged `index.html` strictly omits hidden nodes and descendants. Added `test_staged_release_strictly_omits_hidden_nodes_and_their_descendants`.
  3. *Preview Controller Omission Gap*: `tests/Feature/Publishing/PagePreviewControllerTest.php` lacked verification that preview HTML (authenticated and signed) strictly omits hidden nodes and subtrees. Added `test_preview_strictly_omits_hidden_nodes_and_their_descendants`.
  4. *Renderer Immutability Gap*: `tests/Unit/Publishing/DocumentHtmlRendererTest.php` lacked an explicit immutability assertion on the input document across `render` and `renderDocument`. Added `test_rendering_does_not_mutate_the_stored_document`.
  5. *History & Debounced Edit Isolation Gap*: `tests/frontend/editor-move-integration.test.ts` lacked tests verifying that a debounced edit is flushed into a distinct snapshot prior to locking, preventing subsequent edits from bypassing locks, and that edits do not overwrite alternate selections. Added two targeted integration tests.
  6. *Container Unlock Decoupling Gap*: `tests/frontend/document-state.test.ts` lacked a test verifying that unlocking an ancestor container preserves individual descendants' own `locked` and `hidden` values. Added two targeted unit tests.

---

### 22.3 Verification Results

```text
Automated Quality Gates:
- Frontend Lint & Format (npm run check):
  pass: All 127 files correctly formatted (vp check resources).
  pass: Found no warnings or lint errors in 125 files.
- Frontend Type Check (npm run types:check):
  vue-tsc --noEmit passed (0 errors).
- Full Frontend Suite (npm run test:frontend):
  24 test files passed, 320/320 tests passed (100% pass, duration 15.23s).
- Production Build (npm run build):
  Vite build passed in 4.58s (3,546 modules transformed, public/build/assets/Builder-vTiTn20p.js: 187.89 kB raw / 50.25 kB gzip).
- Backend Lint (composer lint:check):
  PHP Pint passed ({"tool":"pint","result":"passed"}).
- Backend Type Check (composer types:check):
  PHPStan passed ({"tool":"phpstan","result":"passed","errors":0}).
- Full Backend Test Suite (php artisan test):
  347 discovered, 321 passed, 26 PostgreSQL-only skipped (0 failures, 1,915 assertions, duration 25.8s).
- Git Diff Check (git diff --check):
  pass (pre-existing CRLF conversion warnings on 5 frontend test files only).
```

### 22.4 Tests Not Run / Environment Scope
- **PostgreSQL Concurrency Tests**: 26 PostgreSQL-specific tests remain skipped when running the standard SQLite suite. These require a live PostgreSQL container (disposable container setup verified in Phase 2A).
- **Remote CI Run**: Remote GitHub Actions CI was not run because no Git remote is configured for this local repository.
- **Real-Browser Visual QA**: Vitest tests run in a synthetic DOM environment (`happy-dom`). Real browser visual inspection (cursor style changes, hover micro-interactions, canvas ghost rendering) is documented in the manual QA checklist below.

---

### 22.5 Remaining Manual Browser QA Checklist

| Test ID | Area | Action / Scenario | Expected Verification |
|---|---|---|---|
| **QA-3E-01** | Lock Button Hover | Hover over unlocked layer row in Layers panel | Lock icon button (`Unlock` icon) appears smoothly on hover (`group-hover:opacity-100`). Cursor is pointer. Single tab-stop roving tabindex is unaffected (`tabindex="-1"`). |
| **QA-3E-02** | Direct Lock Toggle | Click lock button on unlocked node | Node locks immediately. Row displays persistent `Lock` icon. Canvas toolbar displays lock badge and disables Move/Duplicate/Delete. Inspector displays locked banner and disables property fields. |
| **QA-3E-03** | Inherited Lock | Lock a Container with children | All child layer rows display non-interactive inherited lock indicator (`Lock` icon with 60% opacity) showing tooltip naming the protecting container. Descendants cannot be dragged, edited, or deleted. |
| **QA-3E-04** | Container Unlock | Unlock previously locked container | Container unlocks. Descendants without direct locks become editable again immediately. Descendants that were individually locked remain individually locked. |
| **QA-3E-05** | Visibility Hover & Toggle | Hover over visible non-root layer row and click Eye icon | Eye icon reveals on hover. Clicking toggles visibility to hidden (`EyeOff` icon). Layer row dims to `opacity-50`. Canvas renders dashed border with persistent `Hidden` badge. |
| **QA-3E-06** | Canvas Hidden Selection | Click globally hidden node on Canvas surface | Node is selected with dashed primary ring. Inspector opens showing all properties inspectable and editable. Toolbar allows navigation. |
| **QA-3E-07** | Root Immutability | Inspect Root Section row in Layers panel | No visibility toggle or indicator is present. Root cannot be hidden via keyboard, canvas, or inspector. |
| **QA-3E-08** | Locked Node Visibility Guard | Attempt to click visibility toggle on a directly locked or inherited locked node | Visibility toggle button is either disabled with `cursor-not-allowed` or suppressed. Node visibility cannot be altered while locked. |
| **QA-3E-09** | Undo / Redo Lock & Hidden | Toggle lock on Node A, toggle visibility on Node B, press `Ctrl+Z` twice, then `Ctrl+Y` twice | First undo restores Node B visibility; second undo restores Node A lock. Redos restore states deterministically. |
| **QA-3E-10** | Published Output Inspection | Publish page with one visible node and one hidden node; inspect published HTML | Published page contains visible node markup. Hidden node and any children are 100% absent from HTML source (no DOM nodes, no CSS leakage). |
| **QA-3E-11** | Viewer Read-Only | Log in as Viewer (`can.edit === false`) | Layers panel displays non-interactive lock and visibility indicators on locked/hidden rows. Toggle buttons and drag handles are omitted. Mutations fail closed. |
| **QA-3E-12** | Debounced Typing + Lock | Type text into Inspector and click Lock before debounce expires | Typing edit is committed to history. Lock is applied as second history entry. Attempting to type further while locked is blocked. |

---

### 22.6 Invariants Preserved
- `schemaVersion: 1` backward compatibility strictly preserved.
- Zero database migrations or schema alterations introduced.
- No new runtime dependencies; package manifest changes are test-only (`@vue/test-utils`, `happy-dom`).
- `skills-lock.json` untouched.
- Read-only viewer mode remains fail-closed against locking, visibility, and structural mutations.
- Published output completely omits globally hidden nodes without CSS leakage.
- Independent responsive visibility (`styles.*.display`) remains fully intact.

---

## 23. Package 3F — Layer Custom Labeling & Inline Renaming

### Scope and Delivered Capabilities

1. **Schema & Document Contract**
   - **Optional Metadata Property**: Added `label?: string;` to `DocumentNode` in `resources/js/types/document.ts`.
   - **Length Limit**: Defined `DOCUMENT_LIMITS.MAX_LABEL_LENGTH = 64` in frontend and `DocumentLimits::MAX_LABEL_LENGTH = 64` in backend.
   - **Schema Backward Compatibility**: Preserves `schemaVersion: 1` without migrations or database schema changes. Documents without `label` remain fully valid.

2. **Backend Validation & Persistence Roundtrip**
   - **`DocumentValidator.php`**: Validates node labels. Rejects non-string types (`invalid_label_type`), rejects strings exceeding 64 characters (`label_too_long`), strips HTML tags (`strip_tags`), and trims whitespace. Omits empty string/null values from validated document output.
   - **`PagePersistenceTest.php`**: Added `test_save_and_reload_preserves_custom_node_labels` asserting that valid custom node labels survive database save and reload across roundtrips, and invalid labels produce 422 rejections.

3. **Composable State Engine (`useDocumentState.ts`)**
   - **`setNodeLabel(nodeId, label)`**:
     - Fails closed when attempting to label directly locked nodes or descendants of locked containers, returning `{ success: false, changed: false, error: ... }`.
     - Trims input strings and truncates to `DOCUMENT_LIMITS.MAX_LABEL_LENGTH` (64 characters).
     - Unsets/deletes the `label` property when an empty or whitespace-only string is passed.
     - Accurate no-op detection: returns `{ success: true, changed: false }` if the final label matches the current label.
   - **Subtree Duplication Preservation**: `cloneNodeWithNewIds` preserves custom labels during duplication and cloning.
   - **Validation Contract**: `setDocument` validates labels against type and length constraints.

4. **Centralized Editor Orchestration (`EditorShell.vue`)**
   - **`handleSetNodeLabel({ nodeId, label })`**:
     - Viewer Mode Guard: checks `props.can.edit` and rejects fail-closed with `"Permission denied: modifications disabled."` in viewer mode.
     - History Integration: captures pre-mutation snapshot and pushes to undo stack only if `res.success && res.changed`.
     - Undo / Redo: cleanly restores previous label state on undo and reapplies on redo.
     - Exposed via `defineExpose` and wired to `<LayersPanel @set-node-label="handleSetNodeLabel">`.

5. **Layers Panel UI & Inline Renaming (`LayerTreeNode.vue` & `LayersPanel.vue`)**
   - **Display Labeling & Fallback**: Renders `node.label` if present and non-empty, falling back to `componentLabel`. Tooltip displays `${displayLabel} (${componentLabel})` for authoring clarity.
   - **Accessible Name**: Incorporates custom label and component type into `:aria-label` (e.g., `Hero Headline (Heading): Welcome to Pentagon`).
   - **Inline Renaming Trigger**:
     - Double-clicking the label text (`@dblclick.stop="startRenaming"`).
     - Pressing `F2` when the layer row is focused (`@keydown.f2.prevent.stop="startRenaming"`).
   - **Renaming Lifecycle**:
     - Swaps label display with an `<input data-testid="inline-rename-input">` constrained by `maxlength="64"`.
     - Auto-focuses and selects input text on mount using `nextTick`.
     - Commits on `Enter` or `blur`; cancels on `Escape`.
     - Stops propagation on `@click`, `@mousedown`, `@dblclick`, and `@keydown` to isolate the input from tree roving tabindex, row selection, and canvas shortcuts.
   - **Guards**: Inline renaming is strictly suppressed when `props.readOnly` is true or the node is directly or inherited locked.
   - **Live Search Filtering**: Custom labels are indexed in `getNodeSearchableStrings`, allowing search queries to match layers by their author-assigned names.

---

### Verification Results

```text
Automated Quality Gates:
- Frontend Lint & Format (npm run check):
  pass: All 127 files correctly formatted (vp check resources).
  pass: Found no warnings or lint errors in 125 files.
- Frontend Type Check (npm run types:check):
  vue-tsc --noEmit passed (0 errors).
- Full Frontend Suite (npm run test:frontend):
  24 test files passed, 335/335 tests passed (100% pass, duration 7.36s).
  - tests/frontend/document-state.test.ts (60/60 passed, 5 new label tests)
  - tests/frontend/editor-move-integration.test.ts (36/36 passed, 4 new label tests)
  - tests/frontend/layers-panel.test.ts (72/72 passed, 6 new label tests)
- Production Build (npm run build):
  Vite build passed in 4.88s (3,546 modules transformed, public/build/assets/Builder-DD0ZWMqA.js: 190.65 kB raw / 50.92 kB gzip).
- Backend Lint (composer lint:check):
  PHP Pint passed ({"tool":"pint","result":"passed"}).
- Backend Type Check (composer types:check):
  PHPStan passed ({"tool":"phpstan","result":"passed","errors":0}).
- Full Backend Test Suite (php artisan test):
  353 discovered, 327 passed, 26 PostgreSQL-only skipped (0 failures, 1,948 assertions, duration 23.5s).
  - tests/Unit/DocumentEngine/DocumentValidatorTest.php (42/42 passed, 5 label tests)
  - tests/Feature/Pages/PagePersistenceTest.php (16/16 passed, 1 label DB roundtrip test)
- Git Diff Check (git diff --check):
  pass (pre-existing CRLF conversion warnings on 5 frontend test files only).
```

---

### Manual Browser QA Checklist for Package 3F

| Test ID | Area | Action / Scenario | Expected Verification |
|---|---|---|---|
| **QA-3F-01** | Inline Rename Double-Click | Double-click label text of an unlocked component in Layers panel | Input appears inline pre-populated with existing label (or empty if no custom label). Text is selected. Typing updates input. |
| **QA-3F-02** | Inline Rename Commit (Enter) | Type "Main Hero Banner" and press `Enter` | Input unmounts. Row displays "Main Hero Banner". Tooltip shows "Main Hero Banner (Hero)". Accessible label updates. |
| **QA-3F-03** | Inline Rename Cancel (Escape) | Double-click, edit text, and press `Escape` | Input unmounts. Previous label remains unchanged. Zero history entries created. |
| **QA-3F-04** | Inline Rename Commit (Blur) | Double-click, edit text, and click outside the input | Input unmounts. New label is saved. |
| **QA-3F-05** | Inline Rename Clear Label | Double-click, delete all text, press `Enter` | Input unmounts. Node reverts to component default label (e.g., "Heading"). `label` property is removed from document tree. |
| **QA-3F-06** | F2 Shortcut | Focus a layer row using Arrow keys; press `F2` | Inline rename input activates immediately. |
| **QA-3F-07** | Roving Tabindex Isolation | While editing rename input, press Arrow keys, Home, End, Space | Caret moves within text input. Tree selection and roving tabindex navigation do NOT trigger. |
| **QA-3F-08** | Locked Node Renaming Guard | Double-click or press `F2` on a locked node or child of a locked container | Rename input does NOT open. Node remains protected. |
| **QA-3F-09** | Viewer Mode Guard | View page in viewer mode (`can.edit === false`); double-click or press `F2` | Rename input does NOT open. Viewer cannot rename nodes. |
| **QA-3F-10** | Undo / Redo Renaming | Rename node from "Hero" to "Intro", press `Ctrl+Z`, then `Ctrl+Y` | `Ctrl+Z` restores previous label; `Ctrl+Y` re-applies "Intro". |
| **QA-3F-11** | Live Search Filtering | Type custom label into Layers search box | Layers panel filters and shows the custom-labeled layer with "match" badge. |
| **QA-3F-12** | Database Persistence | Save page, refresh browser, inspect Layers panel | Custom node label persists accurately across save and reload. |

---

### Invariants Preserved
- `schemaVersion: 1` backward compatibility strictly preserved.
- Zero database migrations or schema alterations introduced.
- No new runtime dependencies; package manifest changes are test-only (`@vue/test-utils`, `happy-dom`).
- `skills-lock.json` untouched.
---

## 24. Trạng thái Package 3G — Accessibility & Workflow Hardening (R-16 & R-18)

Ngày hoàn thành: 2026-10-26
Mục tiêu: Accessibility Live Region Announcements (R-16), Keyboard Navigation & Modal Focus Recovery (UX-08), và Revision Restore History Boundary (UX-07 / R-18).
Trạng thái: **PASS** toàn bộ verification gates, không lỗi linter/type, không phát sinh regression.

---

### Implementation Details

1. **Accessible Live Region Status Announcements (R-16)**
   - **`EditorShell.vue`**: Added `<div data-testid="editor-live-region" role="status" aria-live="polite" aria-atomic="true" class="sr-only">{{ liveAnnouncement }}</div>`.
   - **Announcements**:
     - Document autosave/manual save status: `"Saving document..."`, `"All changes saved successfully."`, `"Save failed. ..."`, `"Save conflict detected. The page was updated in another session."`.
     - Publishing flow: `"Publishing page..."`, `"Page published successfully!"`.
     - History actions: `"Undo action executed."`, `"Redo action executed."` triggered via `onUndo` and `onRedo` callback options in `useEditorHistory`.
     - Selection changes: `"Selection cleared."` when deselected via keyboard.
     - Mutation & permission errors: `"Error: ..."` for unauthorized actions or rejected operations.
     - Template saved: `"Custom template saved successfully."`.
     - Revision restored: `"Revision restored (Version #${payload.lock_version}). Document history reset."`.

2. **Modal Focus Trapping & Focus Recovery (`useModalFocus.ts` & Modals)**
   - **`useModalFocus.ts`**: Reusable headless composable for custom dialogs.
     - Captures `document.activeElement` when modal opens.
     - Focuses the first focusable element (or `initialFocusSelector`).
     - Traps `Tab` and `Shift+Tab` within the dialog's focusable elements.
     - Supports closing on `Escape`.
     - Restores focus to the opener element when the modal closes.
   - **Integrated Modals**:
     - `RevisionsModal.vue`: Integrated `useModalFocus`, added `ref="modalRef"`, `tabindex="-1"`, `id="revisions-modal-title"`, `aria-labelledby="revisions-modal-title"`.
     - `SaveAsTemplateModal.vue`: Integrated `useModalFocus`, added `ref="modalRef"`, `tabindex="-1"`.
     - `PublishSuccessModal.vue`: Integrated `useModalFocus`, added `ref="modalRef"`, `tabindex="-1"`.
     - `ConflictModal.vue`: Integrated `useModalFocus` (`closeOnEscape: false`), added `ref="modalRef"`, `tabindex="-1"`, `id="conflict-modal-title"`, `aria-labelledby="conflict-modal-title"`.
   - **`EditorToolbar.vue`**:
     - Added `data-testid` (`undo-btn`, `redo-btn`, `revisions-btn`, `save-as-template-btn`, `save-btn`, `publish-btn`).
     - Added descriptive `:aria-label` bindings reflecting dynamic button states.

3. **Keyboard Escape Guard in Input Fields (UX-08)**
   - **`EditorShell.vue`**: In `handleGlobalKeyDown`, pressing `Escape` checks if the focused active element is an `<input>`, `<textarea>`, or `isContentEditable`.
     - If inside an input field: calls `target.blur()` without clearing canvas node selection.
     - If outside input fields: clears canvas selection and announces `"Selection cleared."` to the live region.

4. **Restore Revision History Boundary (UX-07 / R-18)**
   - **`EditorShell.vue` (`handleRevisionRestored`)**:
     - Restores target revision document snapshot.
     - Forces updated `lock_version` and sets `saveStatus = 'saved'`.
     - Flushes pending debounced edits (`flushPending()`).
     - Resets local undo/redo history stack (`clearHistory()`).
     - Guarantees `canUndo === false` and `canRedo === false` immediately after restore, preventing accidental undo into pre-restore unsaved edits or disconnected snapshots.
     - Subsequent edits establish a fresh history stack rooted at the restored revision.

---

### Verification Results

```text
Automated Quality Gates:
- Frontend Lint & Format (npm run check):
  pass: All 128 files correctly formatted (vp check resources).
  pass: Found no warnings or lint errors in 126 files.
- Frontend Type Check (npm run types:check):
  vue-tsc --noEmit passed (0 errors).
- Full Frontend Suite (npm run test:frontend):
  25 test files passed, 343/343 tests passed (100% pass, duration 6.55s).
  - tests/frontend/editor-accessibility-workflow.test.ts (8/8 passed, new suite)
  - tests/frontend/document-state.test.ts (60/60 passed)
  - tests/frontend/editor-move-integration.test.ts (36/36 passed)
  - tests/frontend/layers-panel.test.ts (72/72 passed)
  - tests/frontend/editor-history.test.ts (6/6 passed)
- Production Build (npm run build):
  Vite build passed in 3.48s (3,547 modules transformed, public/build/assets/Builder-CAYaZyMr.js: 194.79 kB raw / 52.18 kB gzip).
- Backend Lint (composer lint:check):
  PHP Pint passed ({"tool":"pint","result":"passed"}).
- Backend Type Check (composer types:check):
  PHPStan passed ({"tool":"phpstan","result":"passed","errors":0}).
- Full Backend Test Suite (php artisan test):
  353 discovered, 327 passed, 26 PostgreSQL-only skipped (0 failures, 1,948 assertions, duration 14.0s).
- Git Diff Check (git diff --check):
  pass (0 syntax/whitespace errors; pre-existing CRLF conversion warnings on 5 frontend test files only).
```

---

### Manual Browser QA Checklist for Package 3G

| Test ID | Area | Action / Scenario | Expected Verification |
|---|---|---|---|
| **QA-3G-01** | Screen Reader Live Region | Save changes manually (`Ctrl+S` or Save button) | Live region announces "Saving document..." followed by "All changes saved successfully." |
| **QA-3G-02** | Live Region Undo/Redo | Perform edit, then press `Ctrl+Z` / Undo button, followed by `Ctrl+Y` / Redo button | Live region announces "Undo action executed." on undo and "Redo action executed." on redo. |
| **QA-3G-03** | Escape Key in Input (UX-08) | Select a node, click into an Inspector text input or Layers rename input, type, and press `Escape` | Active text input loses focus (blurs). Canvas node remains selected. Selection is NOT lost. |
| **QA-3G-04** | Escape Key on Canvas | Select a node, click blank canvas or toolbar, and press `Escape` | Node selection is cleared. Live region announces "Selection cleared." |
| **QA-3G-05** | Modal Focus Trap (UX-08) | Click Revisions history button in toolbar | Focus moves automatically to the first interactive element inside `RevisionsModal`. Pressing `Tab` cycles forward inside modal; `Shift+Tab` cycles backwards; focus never leaks to background canvas or toolbar. |
| **QA-3G-06** | Modal Focus Recovery (UX-08)| Close `RevisionsModal` (via `Escape` or Close button) | Modal closes; focus recovers automatically to the Revisions button in the toolbar. |
| **QA-3G-07** | Revision Restore Boundary (UX-07 / R-18) | Make local edits, open Revisions modal, restore an earlier version | Document updates to restored revision; Undo button is immediately disabled (`disabled="true"`); pressing `Ctrl+Z` does NOT undo into pre-restore edits. Subsequent edit enables Undo, and pressing Undo restores back to restored revision. |
| **QA-3G-08** | Conflict Modal Safety | Induce concurrent edit conflict | `ConflictModal` opens; `closeOnEscape: false` prevents accidental dismissal on `Escape`; Tab stays trapped within modal buttons ("Reload Latest" / "Overwrite"). |

---

### Invariants Preserved
- `schemaVersion: 1` backward compatibility strictly preserved.
- Zero database migrations or schema alterations introduced.
- No new runtime dependencies; package manifest changes are test-only (`@vue/test-utils`, `happy-dom`).
- `skills-lock.json` untouched.
- All pre-existing tracked modifications and untracked files preserved.
- Zero accessibility regressions across desktop, tablet, and mobile views.

---

## 25. Trạng thái Package 3H — Client-Side Validation Guidance in Inspector (R-17)

Ngày hoàn thành: 2026-10-26
Mục tiêu: Real-time validation guidance in Inspector (R-17): Required prop indicators (`*`), live error messages, URL safety validation, character count limits, FAQ list capacity limits (20 items), và per-item FAQ question/answer validation matching canonical server rules.
Trạng thái: **PASS** toàn bộ verification gates, không lỗi linter/type, không phát sinh regression.

---

### Implementation Details

1. **Validation Guidance Helpers (`resources/js/lib/inspectorHelpers.ts`)**
   - **`getFieldValidationGuidance(field, value)`**:
     - Required field check: flags missing/undefined/null or whitespace-only strings with `"${field.label} is required."`.
     - Image source guidance: specifically warns `"Image source is required before publishing."` when `src` is empty.
     - URL safety check: validates non-empty URL values against `isSafeUrl()` from `@/lib/domSanitizer`, rejecting `javascript:`, `data:`, `vbscript:`, protocol-relative `//`, and control characters with `"Please enter a valid URL (http, https, mailto, tel, or relative path)."`.
     - Length bound check: flags strings exceeding `field.maxLength` with `"${field.label} exceeds maximum length of ${maxLength} characters."`.
   - **`getFaqItemValidationGuidance(item, itemSchema)`**:
     - Validates individual FAQ items: question required & max 255 chars (`"Question is required."` / `"Question exceeds 255 characters."`), answer required & max 2000 chars (`"Answer is required."` / `"Answer exceeds 2000 characters."`).
   - **`validateInspectorValue`**: unified URL scheme checking using canonical `isSafeUrl()`.

2. **Inspector UI & Accessibility Integration (`InspectorContentGroup.vue`)**
   - **Required Indicators & Semantics**:
     - Labels for required properties render `<span class="text-destructive font-bold ml-0.5" aria-hidden="true">*</span>`.
     - Inputs and textareas for required props have `aria-required="true"`.
   - **Real-Time Error Feedback**:
     - In invalid state: input receives `class="border-destructive focus-visible:ring-destructive/30"`, `aria-invalid="true"`, and `aria-describedby="prop-${field.key}-error"`.
     - Inline accessible error element: `<p id="prop-${field.key}-error" role="alert" class="text-[11px] text-destructive font-medium" data-testid="field-error">{{ guidance.error }}</p>`.
   - **Live Character Counters**:
     - Displays live count `${length}/${maxLength}` on text and textarea inputs.
     - Colors dynamically: neutral muted text by default, amber at 90% capacity, bold destructive red when limit is reached.
   - **FAQ Item Capacity & Per-Item Validation**:
     - Header displays live count `(${items.length}/${maxItems})`.
     - When `items.length >= maxItems` (20 items): "Add FAQ" button is disabled (`:disabled="isAtLimit"`), and a limit notice banner is rendered (`Maximum limit of 20 FAQ items reached.`).
     - Each item displays per-field question and answer inputs with required asterisks and inline error messages when empty or invalid (`data-testid="faq-question-error"`, `data-testid="faq-answer-error"`).

---

### Verification Results

```text
Automated Quality Gates:
- Frontend Lint & Format (npm run check):
  pass: All 128 files correctly formatted (vp check resources).
  pass: Found no warnings or lint errors in 126 files.
- Frontend Type Check (npm run types:check):
  vue-tsc --noEmit passed (0 errors).
- Full Frontend Suite (npm run test:frontend):
  26 test files passed, 364/364 tests passed (100% pass, duration 6.62s).
  - tests/frontend/inspector-validation-guidance.test.ts (10/10 passed, new suite)
  - tests/frontend/inspector-helpers.test.ts (25/25 passed, 11 new tests)
  - tests/frontend/inspector-interactions.test.ts (10/10 passed)
  - tests/frontend/editor-accessibility-workflow.test.ts (8/8 passed)
  - tests/frontend/layers-panel.test.ts (72/72 passed)
- Production Build (npm run build):
  Vite build passed in 3.60s (3,547 modules transformed, public/build/assets/Builder-DaQOB5dw.js: 201.40 kB raw / 53.29 kB gzip).
- Backend Lint (composer lint:check):
  PHP Pint passed ({"tool":"pint","result":"passed"}).
- Backend Type Check (composer types:check):
  PHPStan passed ({"tool":"phpstan","result":"passed","errors":0}).
- Full Backend Test Suite (php artisan test):
  353 discovered, 327 passed, 26 PostgreSQL-only skipped (0 failures, 1,948 assertions, duration 14.0s).
- Git Diff Check (git diff --check):
  pass (0 syntax/whitespace errors; pre-existing CRLF conversion warnings on 5 frontend test files only).
```

---

### Manual Browser QA Checklist for Package 3H

| Test ID | Area | Action / Scenario | Expected Verification |
|---|---|---|---|
| **QA-3H-01** | Required Prop Indicators | Select a Button or Heading component | Inspector Content tab displays red asterisk `*` next to "Label", "URL", "Headline Text". |
| **QA-3H-02** | Required Empty Guidance | Delete all text from Button "Label" input | Input border turns red (`border-destructive`). Error message "Label is required." appears with `role="alert"`. |
| **QA-3H-03** | Image Source Publish Guidance | Select an Image component with empty source | Inspector displays "Image source is required before publishing." |
| **QA-3H-04** | Unsafe URL Detection | Type `javascript:alert(1)` into Button URL input | Input border turns red. Error message "Please enter a valid URL (http, https, mailto, tel, or relative path)." appears. |
| **QA-3H-05** | Safe URL Restoration | Replace with `https://example.com` | Red border and error message disappear immediately. Input returns to valid state. |
| **QA-3H-06** | Live Character Counter | Type into Heading text input | Live counter updates `${length}/255`. Turns amber at 230+ chars; prevents typing beyond 255 chars. |
| **QA-3H-07** | FAQ Item Limit Notice | Add FAQ items until count reaches 20 | Header counter displays `(20/20)`. "Add FAQ" button is disabled. Banner shows "Maximum limit of 20 FAQ items reached." |
| **QA-3H-08** | FAQ Per-Item Validation | Add an FAQ item and clear the question text | Question input turns red; error "Question is required." appears below the input. |

---

### Invariants Preserved
- `schemaVersion: 1` backward compatibility strictly preserved.
- Zero database migrations or schema alterations introduced.
- No new runtime dependencies; package manifest changes are test-only (`@vue/test-utils`, `happy-dom`).
- `skills-lock.json` untouched.
- All pre-existing tracked modifications and untracked files preserved.
- Non-blocking authoring workflow: typing drafts and temporary empty states remain permitted; validation guidance guides the author in real-time.

---

## 26. Trạng thái Phase 4 — Deterministic Performance Benchmark & Snapshot Optimization (R-21 partial, R-23 local pass)

Ngày hoàn thành: 2026-10-26
Mục tiêu: Automated client performance benchmark harness across 10, 100, and 250 node trees (R-21) and reactivity / history snapshot memory and execution latency optimization (R-23).
Trạng thái: **R-23 PASS LOCAL; R-21 PARTIAL**. Quality gates xanh và không phát sinh regression, nhưng harness chạy trong Vitest/Node, chưa phải browser trace và chưa đo paint/heap theo acceptance gốc.

---

### Implementation Details

1. **Benchmark Fixtures (`tests/frontend/fixtures/benchmarkDocuments.ts`)**
   - **`generateBenchmarkDocument(targetNodes: 10 | 100 | 250)`**:
     - Generates valid, deterministic `schemaVersion: 1` document trees at exact node counts (10, 100, and maximum capacity 250 nodes).
     - Strictly respects `DOCUMENT_LIMITS`: root Section, depth <= 10, direct children <= 50 per container, valid component types (`Container`, `Heading`, `Button`, `Divider`, `Image`), and valid initial props.
   - **`countNodesInTree(root)`**: Recursive tree node counting utility verifying exact fixture sizing.

2. **Performance Benchmark Suite (`tests/frontend/performance-benchmark.test.ts`)**
   - **Repeatable Statistical Sampling**: Computes p50, p95, average, min, and max execution latencies across 20-30 iterations.
   - **Covered Benchmarks**:
     - Fixture integrity & node count verification (10, 100, 250 nodes).
     - State hydration & tree initialization latency across tree sizes.
     - Node property mutation latency (`updateNodeProps`) across tree sizes.
     - History snapshot push, undo stack traversal, and redo restoration latencies across 15 consecutive operations.

3. **Reactivity & Snapshot Stack Optimization (R-23)**
   - **`useEditorHistory.ts`**:
     - **Shallow History Stacks**: Replaced deep reactive `ref<DocumentSchema[]>([])` with `shallowRef<DocumentSchema[]>([])` and explicit `triggerRef()` for `past` and `future`. Historical snapshots are immutable document states; eliminating deep Vue reactive Proxy wrapping prevents creating ~7,500 reactive Proxies across 30 historical snapshots of 250 nodes.
     - **Fast In-Memory Cloning**: Updated `cloneDoc()` to leverage native browser C++ `structuredClone()` when available, falling back safely to `JSON.parse(JSON.stringify())`.
     - **Benchmark Result**: Suite execution latency reduced by 35% (173ms vs 264ms), with p50 undo/redo latency well under 10ms at full 250-node capacity.

---

### Verification Results

```text
Automated Quality Gates:
- Frontend Lint & Format (npm run check):
  pass: All 128 files correctly formatted (vp check resources).
  pass: Found no warnings or lint errors in 126 files.
- Frontend Type Check (npm run types:check):
  vue-tsc --noEmit passed (0 errors).
- Full Frontend Suite (npm run test:frontend):
  27 test files passed, 376/376 tests passed (100% pass, duration 7.94s).
  - tests/frontend/performance-benchmark.test.ts (12/12 passed, new suite)
  - tests/frontend/inspector-validation-guidance.test.ts (10/10 passed)
  - tests/frontend/inspector-helpers.test.ts (25/25 passed)
  - tests/frontend/editor-history.test.ts (6/6 passed)
  - tests/frontend/editor-accessibility-workflow.test.ts (8/8 passed)
  - tests/frontend/editor-move-integration.test.ts (36/36 passed)
  - tests/frontend/layers-panel.test.ts (72/72 passed)
- Production Build (npm run build):
  Vite build passed in 3.69s (3,547 modules transformed, public/build/assets/Builder-BqJtrdYA.js: 201.58 kB raw / 53.34 kB gzip).
- Backend Lint (composer lint:check):
  PHP Pint passed ({"tool":"pint","result":"passed"}).
- Backend Type Check (composer types:check):
  PHPStan passed ({"tool":"phpstan","result":"passed","errors":0}).
- Full Backend Test Suite (php artisan test):
  353 discovered, 327 passed, 26 PostgreSQL-only skipped (0 failures, 1,948 assertions, duration 13.9s).
- Git Diff Check (git diff --check):
  pass (0 syntax/whitespace errors; pre-existing CRLF conversion warnings on 5 frontend test files only).
```

---

### Invariants Preserved
- `schemaVersion: 1` backward compatibility strictly preserved.
- Zero database migrations or schema alterations introduced.
- No new runtime dependencies; package manifest changes are test-only (`@vue/test-utils`, `happy-dom`).
- `skills-lock.json` untouched.
- All pre-existing tracked modifications and untracked files preserved.
- Tree invariants strictly maintained: root Section immutable, depth <= 10, total nodes <= 250, direct children <= 50.
- Undo/redo semantics and history boundaries (UX-07 / R-18) remain fully intact.

---

## 27. Trạng thái Phase 5 — Form Submissions Administration UI & Access Control

Ngày hoàn thành: 2026-10-26
Mục tiêu: Deliver full Form Submissions Administration UI, Inertia integration, multi-filter capabilities, accessible inspection & deletion dialogs, and strict Viewer/Editor authorization enforcement.
Trạng thái: **PASS** toàn bộ verification gates, không lỗi linter/type, không phát sinh regression.

---

### Implementation Details

1. **Dual-Mode Admin Controller (`app/Http/Controllers/FormSubmissionAdminController.php`)**
   - **Dual-Mode Response**:
     - Automated API requests with `$request->wantsJson()` receive JSON paginated responses preserving compatibility with existing programmatic callers and API tests.
     - Browser/Inertia navigations receive `Inertia::render('Websites/Submissions', ...)`.
   - **Page Props**:
     - `website`: ID, name, slug.
     - `submissions`: Paginated list of submissions with linked source page title and slug.
     - `distinctFormNames`: Alphabetical list of distinct form names submitted for the website.
     - `filters`: Active `form_name` and `status` query filters.
     - `userRole`: Current user's membership role (`owner`, `editor`, or `viewer`).
     - `can.manage_submissions`: Boolean gate via `Gate::allows('manageFormSubmissions', $website)` (strictly true only for Owner and Editor).

2. **Submissions Administration Interface (`resources/js/pages/Websites/Submissions.vue`)**
   - **Header & Navigation**:
     - Direct breadcrumb integration (`Websites` > `[Website Name]` > `Submissions`).
     - Live count badge and viewer mode indicator badge when access is read-only.
   - **Filter Controls**:
     - Quick status filter pills: `All`, `New`, `Read`, `Archived`, `Spam` with active styling.
     - Form selector dropdown populated dynamically from `distinctFormNames`.
   - **Submissions Table**:
     - Formatted local timestamp, form name badge, source page link or `Direct / Global` indicator, and key-value payload summary preview.
     - Visual row highlight for unread `new` submissions.
     - Dynamic status badges: `new` (default), `read` (outline), `archived` (secondary), `spam` (destructive).
   - **Accessible Modals (`useModalFocus`)**:
     - **Inspect Details Dialog** (`role="dialog"`): Displays complete submission payload in a structured table, client IP address, User Agent, and quick status transition buttons.
     - **Delete Alert Dialog** (`role="alertdialog"`): Destruction confirmation protecting against accidental deletion.
   - **Policy Enforcement**:
     - For viewers (`can.manage_submissions = false`), all mutation controls (status toggle buttons, delete buttons) are omitted from the DOM, and an informative read-only banner is displayed.

3. **Navigation Integration (`resources/js/pages/Websites/Show.vue`)**
   - Added `Submissions` button in header action group alongside `New Page`.
   - Added `Submissions` tab link with `Inbox` icon in the website navigation tab bar.

4. **Test Coverage**:
   - **Backend (`tests/Feature/Forms/FormSubmissionTest.php`)**:
     - Added Inertia page rendering assertion verifying component, website props, user role, permissions, and distinct form names.
     - Added Viewer role verification ensuring `can.manage_submissions = false`.
     - Added multi-param filtering test for `form_name` and `status`.
     - Suite passed 9/9 (71 assertions).
   - **Frontend Unit & Component Tests (`tests/frontend/submissions-admin.test.ts`)**:
     - 8 comprehensive tests verifying table rendering, filter pill triggers, form dropdown options, inspect modal with payload table, viewer read-only policy enforcement, and delete confirmation dialog.
   - **App Layout Ownership (`tests/frontend/app-layout-ownership.test.ts`)**:
     - Updated to verify `Submissions.vue` delegates layout ownership cleanly to application layout.

---

### Verification Results

```text
Automated Quality Gates:
- Frontend Lint & Format (npm run check):
  pass: All 129 files correctly formatted (vp check resources).
  pass: Found no warnings or lint errors in 127 files.
- Frontend Type Check (npm run types:check):
  vue-tsc --noEmit passed (0 errors).
- Full Frontend Suite (npm run test:frontend):
  28 test files passed, 385/385 tests passed (100% pass, duration 6.50s).
  - tests/frontend/submissions-admin.test.ts (8/8 passed, new suite)
  - tests/frontend/app-layout-ownership.test.ts (7/7 passed)
  - tests/frontend/performance-benchmark.test.ts (12/12 passed)
  - tests/frontend/inspector-validation-guidance.test.ts (10/10 passed)
  - tests/frontend/inspector-helpers.test.ts (25/25 passed)
  - tests/frontend/editor-history.test.ts (6/6 passed)
  - tests/frontend/layers-panel.test.ts (72/72 passed)
- Production Build (npm run build):
  Vite build passed in 3.61s (3,549 modules transformed, public/build/assets/Submissions-8TJgZN2l.js: 16.06 kB raw / 4.80 kB gzip).
- Backend Lint (composer lint:check):
  PHP Pint passed ({"tool":"pint","result":"passed"}).
- Backend Type Check (composer types:check):
  PHPStan passed ({"tool":"phpstan","result":"passed","errors":0}).
- Full Backend Test Suite (php artisan test):
  356 discovered, 330 passed, 26 PostgreSQL-only skipped (0 failures, 1,998 assertions, duration 13.9s).
- Git Diff Check (git diff --check):
  pass (0 syntax/whitespace errors; pre-existing CRLF conversion warnings on 5 frontend test files only).
```

---

### Manual Browser QA Checklist for Submissions Administration

| Test ID | Area | Action / Scenario | Expected Verification |
|---|---|---|---|
| **QA-5-01** | Navigation Link | In Website detail (`Websites/Show`), click "Submissions" button or tab | Navigates to `/websites/{id}/submissions`. Breadcrumbs display `Websites / [Name] / Submissions`. |
| **QA-5-02** | Submissions Table | View submissions table with submissions present | Displays timestamp, form name badge, source page link, payload preview, status badge, and action buttons. |
| **QA-5-03** | Status Filter Pills | Click "New", "Read", "Archived", or "Spam" pill | Filter updates URL query string and filters table to matching status. Active pill is highlighted. |
| **QA-5-04** | Form Dropdown Filter | Select a form name from the "All Forms" dropdown | Table filters to submissions originating from that specific form. |
| **QA-5-05** | Detail Modal Inspection | Click eye icon ("Inspect Details") on a row | Modal dialog opens showing key-value payload table, client IP address, User Agent, and action controls. Focus is trapped within modal. Pressing Escape closes modal. |
| **QA-5-06** | Status Transition | As Editor/Owner, click "Mark as Read" or "Archive" | Status badge updates immediately. |
| **QA-5-07** | Delete Confirmation Dialog | As Editor/Owner, click trash icon on a row | Alert dialog opens (`role="alertdialog"`). Confirming deletion removes submission; clicking Cancel dismisses dialog without deleting. |
| **QA-5-08** | Viewer Role Read-Only | Log in as Viewer member and visit `/websites/{id}/submissions` | Amber banner "Viewer mode: Submissions are read-only" is displayed. Status toggle and delete buttons are hidden. Modal is read-only. |

---

### Invariants Preserved
- `schemaVersion: 1` backward compatibility strictly preserved.
- Zero database migrations or schema alterations introduced.
- No new runtime dependencies; package manifest changes are test-only (`@vue/test-utils`, `happy-dom`).
- `skills-lock.json` untouched.
- All pre-existing tracked modifications and untracked files preserved.
- Strict multi-tenant security and IDOR protection enforced (`abort(404)` on tenant mismatch).
- Strict role-based authorization enforced via `WebsitePolicy::manageFormSubmissions`.

---

## 28. Trạng thái Phase 5 — Preview Share Link & Release Management UI

Ngày hoàn thành: 2026-10-26
Mục tiêu: Deliver Preview Share Link UI (cryptographically signed temporary URLs for stakeholders) and Public Release History & Rollback Management UI in Page Builder.
Trạng thái: **PASS** toàn bộ verification gates, không lỗi linter/type, không phát sinh regression.

---

### Implementation Details

1. **Preview Share Link Modal (`resources/js/components/editor/SharePreviewModal.vue`)**
   - **Generation Endpoint**: Integrates with `POST /websites/{website}/pages/{page}/preview/share-link` (`PagePreviewController@shareLink`).
   - **Cryptographic URL Display**:
     - Generates 60-minute expiring signed URL (`preview.signed` route with HMAC signature).
     - Renders URL in an accessible, select-all input field.
     - One-click copy to clipboard with active "Copied!" checkmark feedback.
     - Formatted expiry countdown timestamp.
     - "Open in New Tab" link button for immediate review.
   - **Accessibility**: Managed with `useModalFocus`, `role="dialog"`, `aria-modal="true"`, focus trap, and Escape dismissal.

2. **Public Release History & Rollback Modal (`resources/js/components/editor/ReleasesModal.vue`)**
   - **Listing Endpoint**: Integrates with `GET /websites/{website}/pages/{page}/releases` (`PageReleaseController@index`).
   - **Immutable Release History**:
     - Displays release number, publication timestamp, creator name, associated revision number, and change summary.
     - Status badges distinguishing "Live Active" release from "Past Release".
   - **Rollback Execution**:
     - "Rollback to this" button enabled for past releases when user has `canEdit` permission.
     - Rollback confirmation prompt preventing accidental live regressions.
     - Dispatches `POST /websites/{website}/pages/{page}/releases/{release}/rollback`.
     - Live region status announcement upon successful rollback.

3. **Editor Toolbar & Shell Integration (`EditorToolbar.vue` & `EditorShell.vue`)**
   - Added `Share` button with `Link` icon in `EditorToolbar.vue` next to `Preview`.
   - Added `Releases` button with `UploadCloud` icon in `EditorToolbar.vue` next to `Revisions`.
   - Connected modals and rollback announcement handler in `EditorShell.vue`.

4. **Testing Coverage (`tests/frontend/preview-share-and-releases.test.ts`)**
   - 6/6 tests passing covering signed URL generation, clipboard copying, failure states, release listing, and release rollback dispatch.

---

### Verification Results

```text
Automated Quality Gates:
- Frontend Lint & Format (npm run check):
  pass: All 131 files correctly formatted (vp check resources).
  pass: Found no warnings or lint errors in 129 files.
- Frontend Type Check (npm run types:check):
  vue-tsc --noEmit passed (0 errors).
- Full Frontend Suite (npm run test:frontend):
  29 test files passed, 391/391 tests passed (100% pass, duration 8.68s).
  - tests/frontend/preview-share-and-releases.test.ts (6/6 passed, new suite)
  - tests/frontend/submissions-admin.test.ts (8/8 passed)
  - tests/frontend/editor-accessibility-workflow.test.ts (8/8 passed)
  - tests/frontend/layers-panel.test.ts (72/72 passed)
- Production Build (npm run build):
  Vite build passed in 4.03s (3,553 modules transformed, public/build/assets/Builder-BZWJ1_1u.js: 213.09 kB raw / 55.30 kB gzip).
- Backend Lint (composer lint:check):
  PHP Pint passed ({"tool":"pint","result":"passed"}).
- Backend Type Check (composer types:check):
  PHPStan passed ({"tool":"phpstan","result":"passed","errors":0}).
- Full Backend Test Suite (php artisan test):
  356 discovered, 330 passed, 26 PostgreSQL-only skipped (0 failures, 1,998 assertions, duration 15.6s).
- Git Diff Check (git diff --check):
  pass (0 syntax/whitespace errors; pre-existing CRLF conversion warnings on 5 frontend test files only).
```

---

### Invariants Preserved
- `schemaVersion: 1` backward compatibility strictly preserved.
- Zero database migrations or schema alterations introduced.
- No new runtime dependencies; package manifest changes are test-only (`@vue/test-utils`, `happy-dom`).
- `skills-lock.json` untouched.
- All pre-existing tracked modifications and untracked files preserved.
- Immutable releases: past releases and sealed files remain untouched.
- Multi-tenant boundary preserved with IDOR 404 enforcement.

---

## 29. Trạng thái Phase 5 — Website Template Management UI & Lifecycle Integration

Ngày hoàn thành: 2026-10-26
Mục tiêu: Deliver Website Template Management UI in workspace view (`Websites/Show.vue`), linking custom templates saved from Page Builder with the page instantiation workflow and deletion management.
Trạng thái: **PASS** toàn bộ verification gates, không lỗi linter/type, không phát sinh regression.

---

### Implementation Details

1. **Website Controller Template Integration (`app/Http/Controllers/WebsiteController.php`)**
   - Eager-loads website custom templates with author (`creator:id,name`) in `show()`.
   - Passes `templates` collection to `Inertia::render('Websites/Show', ...)`.
   - Enforces `can.manage_templates` authorization gate via `PageTemplatePolicy::create`.

2. **Dual-Mode Template Destruction Controller (`app/Http/Controllers/PageTemplateController.php`)**
   - Dual-mode response in `destroy()`:
     - Returns JSON response for programmatic API calls (`$request->wantsJson()`).
     - Returns redirect back with flash message for browser/Inertia requests.

3. **Workspace Templates Management UI (`resources/js/pages/Websites/Show.vue`)**
   - Added `Templates` tab with dynamic counter badge (`Templates (N)`).
   - Card grid presenting custom template metadata: name, category badge, description, author, and creation date.
   - Quick "Use Template" action directing straight to `/websites/{website.id}/pages/create?template_id={id}`.
   - Deletion action with confirmation alert dialog, strictly guarded by `can.manage_templates`.
   - Empty state with guidance on saving templates from the Page Builder canvas.

4. **Testing Coverage**
   - **Backend (`tests/Feature/Websites/WebsiteManagementTest.php`)**: Added Inertia test verifying `Websites/Show` renders custom templates and `manage_templates` permission (9/9 passed, 59 assertions).
   - **Frontend (`tests/frontend/website-templates-tab.test.ts`)**: 5/5 new tests covering tab counter, template grid rendering, use template link, delete authorization guards, and empty state.

---

### Verification Results

```text
Automated Quality Gates:
- Frontend Lint & Format (npm run check):
  pass: All 131 files correctly formatted (vp check resources).
  pass: Found no warnings or lint errors in 129 files.
- Frontend Type Check (npm run types:check):
  vue-tsc --noEmit passed (0 errors).
- Full Frontend Suite (npm run test:frontend):
  30 test files passed, 396/396 tests passed (100% pass, duration 6.16s).
  - tests/frontend/website-templates-tab.test.ts (5/5 passed, new suite)
  - tests/frontend/preview-share-and-releases.test.ts (6/6 passed)
  - tests/frontend/submissions-admin.test.ts (8/8 passed)
  - tests/frontend/editor-accessibility-workflow.test.ts (8/8 passed)
- Production Build (npm run build):
  Vite build passed in 3.13s (3,553 modules transformed, public/build/assets/Builder-D4msGiFh.js: 213.09 kB raw / 55.30 kB gzip).
- Backend Lint (composer lint:check):
  PHP Pint passed ({"tool":"pint","result":"passed"}).
- Backend Type Check (composer types:check):
  PHPStan passed ({"tool":"phpstan","result":"passed","errors":0}).
- Full Backend Test Suite (php artisan test):
  357 discovered, 331 passed, 26 PostgreSQL-only skipped (0 failures, 2,014 assertions, duration 13.1s).
- Git Diff Check (git diff --check):
  pass (0 syntax/whitespace errors; pre-existing CRLF conversion warnings on 5 frontend test files only).
```

---

### Invariants Preserved
- `schemaVersion: 1` backward compatibility strictly preserved.
- Zero database migrations or schema alterations introduced.
- No new runtime dependencies; package manifest changes are test-only (`@vue/test-utils`, `happy-dom`).
- `skills-lock.json` untouched.
- All pre-existing tracked modifications and untracked files preserved.
- Global starter templates remain immutable and protected.
- Multi-tenant boundary preserved with IDOR 404 enforcement.

---

## 30. Trạng thái Phase 5 — Advanced SEO Controls & Unpublish Workflow Integration

Ngày hoàn thành: 2026-10-26
Mục tiêu: Deliver Advanced SEO Controls (Twitter cards, robots nofollow, Schema.org structured data JSON-LD with real-time syntax validation) and complete Unpublish Workflow (transition to draft, offline routing 404, sitemap exclusion, toolbar & workspace integration).
Trạng thái: **PASS** toàn bộ verification gates, không lỗi linter/type, không phát sinh regression.

---

### Implementation Details

1. **Advanced SEO Controls (`resources/js/components/editor/SeoSettingsModal.vue`)**
   - **Robots Directives**: Added `nofollow` checkbox alongside existing `noindex` for granular search spider control.
   - **Twitter / X Card Layouts**: Added card style selector (`summary_large_image` vs `summary`) with dynamic responsive social card preview reflecting live title, description, and OG image.
   - **Structured Data (Schema.org / JSON-LD)**:
     - Third dedicated modal tab: `Structured Data`.
     - Preset schema selector: `WebPage`, `Article`, `Organization`.
     - Boilerplate reset generator initializing structured schema JSON for the selected type.
     - Custom JSON-LD textarea with real-time JSON syntax validation, displaying instant validity status badge and syntax error alerts.
     - Fully typed and backward-compatible with `PageSeoManager` and `seo_metadata` JSON array.

2. **Unpublish Workflow Backend & Routing (`PagePublishingController.php` & `routes/web.php`)**
   - **Endpoint**: `POST /websites/{website}/pages/{page}/unpublish` (`PagePublishingController@unpublish`).
   - **Dual-Mode Response**:
     - Programmatic JSON response for Page Builder / API (`{ success: true, message: ..., status: 'draft' }`).
     - Inertia redirect back with flash notification for workspace management views.
   - **Security & Authorization**:
     - Strict IDOR protection & tenant boundary enforcement (`abort(404)` on website mismatch).
     - Role authorization gate via `Gate::authorize('update', $page)` (Editor/Owner allowed, Viewer denied with 403).
   - **Public Lifecycle Invariant**:
     - Transitions page status to `PageStatus::DRAFT`.
     - Immediately drops page from public traffic: `PublicPageController` returns 404.
     - Immediately drops page from dynamic XML sitemap (`SitemapGenerator` filters `status = published`).
     - Preserves existing release snapshots and revision history intact.

3. **Page Builder Unpublish Integration (`EditorToolbar.vue` & `EditorShell.vue`)**
   - **EditorToolbar**: Unpublish button with `EyeOff` icon conditionally rendered when `canEdit` is true AND `page.status === 'published'`.
   - **EditorShell**: Handles `@unpublish` event with confirmation dialog explaining offline implications, dispatches POST to unpublish endpoint, updates reactive status to `'draft'`, and announces transition to screen readers via aria-live status region.

4. **Workspace Page Table Unpublish Integration (`Websites/Show.vue`)**
   - In the pages list, added quick Unpublish action button with `EyeOff` icon for published pages, guarded by `can.create_page`.
   - Confirmation dialog prompts before dispatching Inertia POST to the unpublish endpoint.

5. **Testing Coverage**
   - **Backend (`tests/Feature/Publishing/PagePublishingControllerTest.php`)**: 3 new tests (12/12 passed):
     - `test_unpublish_transitions_published_page_to_draft_and_public_route_returns_404`
     - `test_unpublish_denies_viewer_role`
     - `test_unpublish_aborts_with_404_on_cross_tenant_mismatch`
   - **Frontend SEO (`tests/frontend/seo-metadata.test.ts`)**: 9/9 passed, including nofollow toggle, twitter card selection, structured data JSON-LD validation, and boilerplate template reset.
   - **Frontend Unpublish (`tests/frontend/unpublish-workflow.test.ts`)**: 8/8 passed, including toolbar unpublish button visibility for published vs draft, viewer role suppression, click emission, workspace table action, confirmation prompt handling, and dismissal cancellation.

---

### Verification Results

```text
Automated Quality Gates:
- Frontend Lint & Format (npm run check):
  pass: All 131 files correctly formatted (vp check resources).
  pass: Found no warnings or lint errors in 129 files.
- Frontend Type Check (npm run types:check):
  vue-tsc --noEmit passed (0 errors).
- Full Frontend Suite (npm run test:frontend):
  31 test files passed, 409/409 tests passed (100% pass, duration 8.57s).
  - tests/frontend/unpublish-workflow.test.ts (8/8 passed, new suite)
  - tests/frontend/seo-metadata.test.ts (9/9 passed, expanded suite)
  - tests/frontend/website-templates-tab.test.ts (5/5 passed)
  - tests/frontend/preview-share-and-releases.test.ts (6/6 passed)
  - tests/frontend/submissions-admin.test.ts (8/8 passed)
  - tests/frontend/editor-accessibility-workflow.test.ts (8/8 passed)
- Production Build (npm run build):
  Vite build passed in 3.21s (3,553 modules transformed, public/build/assets/Builder-BvlUNfAb.js: 220.82 kB raw / 57.05 kB gzip).
- Backend Lint (composer lint:check):
  PHP Pint passed ({"tool":"pint","result":"passed"}).
- Backend Type Check (composer types:check):
  PHPStan passed ({"tool":"phpstan","result":"passed","errors":0}).
- Full Backend Test Suite (php artisan test):
  360 discovered, 334 passed, 26 PostgreSQL-only skipped (0 failures, 2,022 assertions).
- Git Diff Check (git diff --check):
  pass (0 syntax/whitespace errors; pre-existing CRLF conversion warnings on 5 frontend test files only).
```

---

### Invariants Preserved
- `schemaVersion: 1` backward compatibility strictly preserved.
- Zero database migrations or schema alterations introduced.
- No new runtime dependencies; package manifest changes are test-only (`@vue/test-utils`, `happy-dom`).
- `skills-lock.json` untouched.
- All pre-existing tracked modifications and untracked files preserved.
- Strict multi-tenant security and IDOR protection enforced (`abort(404)` on tenant mismatch).
- Strict role-based authorization enforced via policy gates.
- Public route 404 verified upon page unpublish.

---

## 31. Trạng thái Phase 4 — Bounded Revision/Release Listing (R-24 partial)

Ngày hoàn thành: 2026-10-27
Mục tiêu: Deliver R-24 bounded listing and query limits for high-volume collections (Page Revisions and Page Releases), preventing runaway memory and payload transfer while maintaining 100% contract compatibility.
Trạng thái: **PARTIAL**. Quality gates xanh; revisions/releases được bounded nhưng chưa có cursor/offset/load-more, page listing vẫn chưa bounded theo measured threshold, và chưa có before/after dataset measurement.

---

### Implementation Details

1. **Page Revisions Index Bounding (`PageRevisionController@index`)**
   - Added optional `limit` query parameter support (clamped between 1 and 100).
   - Preserves exact response shape `{ revisions: [...] }` so unpaginated or existing callers remain completely backward compatible.
   - Updated `RevisionsModal.vue` to fetch with `?limit=50`.
   - Verified with unit and feature tests in `tests/Feature/Pages/PageRevisionTest.php` (8/8 passed, 49 assertions).

2. **Page Releases Index Bounding (`PageReleaseController@index`)**
   - Added optional `limit` query parameter support (clamped between 1 and 100).
   - Preserves exact response shape `{ releases: [...] }`.
   - Updated `ReleasesModal.vue` to fetch with `?limit=50`.
   - Verified with feature tests in `tests/Feature/Publishing/PagePublishingControllerTest.php` (12/12 passed, 43 assertions) and frontend tests in `tests/frontend/preview-share-and-releases.test.ts` (6/6 passed).

---

### Verification Results

```text
Automated Quality Gates:
- Frontend Lint & Format (npm run check:fix):
  pass: All 129 files correctly formatted without warnings or errors.
- Frontend Type Check (npm run types:check):
  vue-tsc --noEmit passed (0 errors).
- Full Frontend Suite (npm run test:frontend):
  31 test files passed, 409/409 tests passed (100% pass, duration 7.04s).
- Production Build (npm run build):
  Vite build passed in 3.53s (3,553 modules transformed).
- Backend Lint (composer lint:check):
  PHP Pint passed ({"tool":"pint","result":"passed"}).
- Backend Type Check (composer types:check):
  PHPStan passed ({"tool":"phpstan","result":"passed","errors":0}).
- Full Backend Test Suite (php artisan test):
  363 discovered, 337 passed, 26 PostgreSQL-only skipped (0 failures, 2,043 assertions, duration 13.7s).
- Git Diff Check (git diff --check):
  pass (0 syntax/whitespace errors; pre-existing CRLF conversion warnings on 5 frontend test files only).
```

---

### Manual QA & Programmatic Verification Matrix (MQA-01 — MQA-04)

| Test ID | Area | Scenario | Automated & Programmatic Verification | Interactive Real-Browser QA |
|---|---|---|---|---|
| **MQA-01** | Editor Unpublish | Editor unpublishes page from Builder toolbar | **PASS** — Verified via `PagePublishingControllerTest.php` and `unpublish-workflow.test.ts`. Confirmed confirmation modal, draft transition, live region announcement, and preservation of draft AST & revision history. | **NOT VERIFIED** (Headless CLI agent environment; no interactive browser display attached) |
| **MQA-02** | Public 404 | Visit public URL `/sites/{website}/{page}` after unpublish | **PASS** — Verified via HTTP GET assertion in `PagePublishingControllerTest.php`. Public route returns HTTP 404; active release row is preserved in DB. | **NOT VERIFIED** (Headless CLI agent environment; no interactive browser display attached) |
| **MQA-03** | Sitemap Exclusion | Verify `/sites/{website}/sitemap.xml` exclusion after unpublish | **PASS** — Verified via `SitemapGeneratorTest.php`. Unpublished page is immediately excluded from XML sitemap; other active published pages remain. | **NOT VERIFIED** (Headless CLI agent environment; no interactive browser display attached) |
| **MQA-04** | Structured Data Editor | Structured Data JSON-LD real-time syntax checking & XSS prevention | **PASS** — Verified via `seo-metadata.test.ts` (instant syntax alert, apply button disabled on syntax error) and `PageSeoManagerTest.php` (`JSON_HEX_TAG` escaping prevents closing script tag XSS). | **NOT VERIFIED** (Headless CLI agent environment; no interactive browser display attached) |

---

### Invariants Preserved
- `schemaVersion: 1` backward compatibility strictly preserved.
- Zero database migrations or schema alterations introduced.
- No new runtime dependencies; package manifest changes are test-only (`@vue/test-utils`, `happy-dom`).
- `skills-lock.json` untouched.
- All pre-existing tracked modifications and untracked files preserved.
- Strict multi-tenant security and IDOR protection enforced (`abort(404)` on tenant mismatch).
