# Optimization Roadmap

> Trạng thái chuẩn hoá ngày 2026-10-31 nằm trong
> `page_builder_project_docs/STABILIZATION_STATUS.md`. Các bảng bên dưới giữ
> nguyên acceptance criteria gốc; trạng thái PASS chỉ được dùng khi bằng chứng
> đáp ứng đúng loại kiểm thử đã yêu cầu.

## 0. Trạng thái thực hiện

- Phase 0 (baseline audit): hoàn thành về mặt khảo sát và tài liệu; không có thay đổi code ứng dụng.
- Phase 1 (architecture/UX/performance/security review và roadmap): hoàn thành về mặt audit; các finding chưa được remediation.
- Phase 2A (Safety Blockers): hoàn thành triển khai và kiểm chứng local ngày 2026-10-24; xem bảng trạng thái bên dưới.
- Phase 2B: hoàn thành local. Phase 2C: R-11..R-15 hoàn thành local.
- Capability architecture A1 (dirty tracking), A2 (canonicalization/alias dispatch), A3 (tree mutation invariants), and A4 (schema-driven Inspector metadata): completed locally.
- Package 3 Layers/DnD, locking, visibility, labeling, accessibility và validation guidance: completed locally. Chưa có browser/E2E/visual verification.
- Phase 4: R-23 completed locally; R-21 mới có deterministic Vitest benchmark, chưa đạt browser-harness acceptance; R-24 mới bounded revisions/releases, chưa có pagination/navigation hoặc measured page listing; R-22/R-25 chưa triển khai.
- Phase 5: submissions admin, preview share, releases, templates, unpublish và advanced SEO đã hoàn thành local. Form component vẫn chưa triển khai.
- Quality gate hiện tại cho Package 2 remediation (2026-10-25): frontend checks/tests/build, Pint, PHPStan, and the SQLite suite pass locally. The 26 PostgreSQL-only tests were skipped in this run; the earlier disposable PostgreSQL result remains historical evidence, not fresh verification.
- GitHub-hosted workflow chưa thể chạy trong session vì không có remote và không được commit/push; workflow đã pass `actionlint` local.

## 1. Nguyên tắc ưu tiên

Thứ tự ưu tiên dùng các tiêu chí: severity, user/data impact, khả năng tái hiện, risk của thay đổi, effort, dependency, cách xác minh và rollback. Không bắt đầu refactor diện rộng trước khi P0/P1 có characterization tests.

## 2. Top 10 vấn đề impact cao

| # | Vấn đề | Severity | Bằng chứng chính |
|---:|---|---|---|
| 1 | Viewer update/delete form submission | Critical | `FormSubmissionAdminController.php:42`, `:65` dùng quyền `view` cho mutation |
| 2 | Publish tiếp tục khi save lỗi/conflict | Critical | `EditorShell.vue:194`; `saveNow()` trả false nhưng bị bỏ qua |
| 3 | Responsive override khác display không xuất hiện public | High | `DocumentHtmlRenderer.php:646` chỉ xử lý visibility |
| 4 | Tablet hidden và viewport canvas lệch editor/public | High | `DocumentHtmlRenderer.php:666`; canvas chỉ đổi width, breakpoint theo window |
| 5 | Quality gate hiện đỏ | High | `composer ci:check` fail với 3 lỗi PHPStan |
| 6 | PostgreSQL concurrency không chạy CI an toàn | High | 26 test skip; workflow không có PostgreSQL; `phpunit.pgsql.xml:27` trỏ dev DB |
| 7 | Nguy cơ AppLayout lồng đôi trên màn hình quản trị | High/Medium | default layout ở `app.ts:20`, page lại tự bọc AppLayout |
| 8 | Image src rỗng chỉ fail khi publish | High | validator cho empty safe URL; renderer fail ở `DocumentHtmlRenderer.php:381` |
| 9 | Backup bỏ release và nuốt lỗi | High operational | `deploy/scripts/backup.sh:42` |
| 10 | Mỗi release inline 137,82 KB CSS | High performance | build output + `DocumentHtmlRenderer.php:118`; HTML 50 node 146.686 byte |

Các issue kế tiếp: preview banner trước DOCTYPE, registry double fetch, raw document persistence, restore không clear history, thiếu release/template/media/submission UI, thiếu visual/a11y/E2E suite.

## 3. Roadmap theo phase

### Phase 2A - Safety blockers và green baseline

| ID | Hạng mục | Impact | Risk | Effort | Dependencies | Verify | Rollback |
|---|---|---|---|---|---|---|---|
| R-01 | Chặn Viewer mutation submission | Ngăn ghi/xóa trái quyền | Low | S | Policy decision Owner+Editor | Auth tests Viewer 403, cross-tenant 404 | Revert policy/controller commit |
| R-02 | Chặn publish nếu save không thành công/conflict | Ngăn publish draft cũ | Low/Medium | S | Characterization autosave/publish | Không có POST publish sau save false; success path giữ nguyên | Revert frontend coordinator |
| R-03 | Sửa 3 lỗi PHPStan root cause | Khôi phục CI gate | Low | S | Test sanitizer/pivot typing | `composer ci:check` pass | Revert từng fix |
| R-04 | Sửa CI branch trigger và PostgreSQL disposable suite | Bảo vệ production locks | Medium | M | CI PostgreSQL service/database isolation | SQLite + pgsql suite đều pass; không đụng dev DB | Revert workflow/config |
| R-05 | Sửa backup path/error handling | Backup trung thực | Medium | S/M | Fixture storage + restore rehearsal | Archive chứa media/releases; tar failure non-zero | Revert script; không xóa backup cũ |

#### Kết quả Phase 2A

| ID | Trạng thái | Bằng chứng nghiệm thu |
|---|---|---|
| R-01 | PASS | Viewer GET được nhưng PATCH/DELETE trả 403; Owner/Editor mutation thành công; cross-tenant giữ 404 trong `AuthorizationMatrixTest`. |
| R-02 | PASS | Publish coordinator chỉ gọi publish sau save thành công; false/conflict/exception không gọi publish; loading được khóa từ trước save. |
| R-03 | PASS | `parse_url()` false fail-closed, iterable type và pivot access được sửa theo root cause; `composer types:check` 0 lỗi. |
| R-04 | PASS | Push CI chạy mọi branch, PostgreSQL 18 service dùng `pentagon_test`, bootstrap từ chối database khác; suite PostgreSQL disposable pass 36/36. |
| R-05 | PASS | Archive dùng `private/releases`, không nuốt lỗi `tar`, không ghi đè thư mục backup trùng timestamp; fixture archive/checksum/extract và tar failure đều pass. |

Phase 2A đạt điều kiện chuyển giao. Phase 2B chỉ được bắt đầu sau khi chấp nhận rõ các rủi ro còn lại và quyết định responsive contract.

### Phase 2B - Responsive contract và publish correctness

| ID | Hạng mục | Impact | Risk | Effort | Dependencies | Verify | Rollback |
|---|---|---|---|---|---|---|---|
| R-06 | Viết fixture/characterization responsive 12 component | Tạo baseline an toàn | Low | M | Quy tắc inheritance được xác nhận | Matrix TS/PHP cho desktop/tablet/mobile | Test-only rollback |
| R-07 | Một nguồn sự thật mapping responsive | Editor/public đồng nhất | High | L | R-06; quyết định class strategy | Unit + visual 375/768/1280 | Feature flag/renderer version |
| R-08 | Sửa `tablet=hidden` cascade | Đúng visibility | Medium | S | R-06 | Fixtures hidden combinations | Revert renderer mapping |
| R-09 | Pre-publish validation cho Image src và lỗi node-path | Giảm publish fail muộn | Medium | M | Product copy/UI state | Unit + E2E select/focus node lỗi | Revert preflight; server fail-closed còn giữ |
| R-10 | Sửa preview banner injection | Preview hợp lệ | Low | S | Không | Assert banner nằm trong body sau doctype | Revert controller change |

#### Kết quả Phase 2B - 2026-10-24

| ID roadmap | Trạng thái | Kết quả thực tế |
|---|---|---|
| R-06 | PASS | Characterization PHP bao phủ responsive contract của đủ 12 component; frontend kiểm tra cascade desktop/tablet/mobile và output editor độc lập kích thước cửa sổ thật. |
| R-07 | PASS | `ComponentRegistry` là nguồn runtime cho allowlist + breakpoint qua `/api/components/registry`; editor hydrate contract này rồi dùng `buildEditorResponsivePresentation`, public renderer đọc cùng constants và sinh CSS enum-safe tại 768/1024. Preset desktop 1280 không bị dùng làm breakpoint. |
| R-08 | PASS | `tablet=hidden` nay kế thừa xuống mobile; `mobile=visible` có thể reset tường minh. Renderer tests kiểm tra CSS thực tế thay vì chỉ JSON. |
| R-09 | PASS | `validateForPublishing()` chặn Image `src` rỗng/whitespace với `empty_image_source` và node path; URL/media path hợp lệ vẫn được chấp nhận; draft/preview vẫn giữ placeholder. |
| R-10 | PASS | Preview banner được truyền vào `renderDocument()` như body prefix tin cậy; DOCTYPE vẫn là byte đầu tiên và public output không có banner. |

Mapping theo brief Phase 2B hiện tại: hạng mục “R-09 preview banner” tương ứng R-10 của roadmap gốc; R-09 roadmap là Image preflight.

Release mới dùng `renderer_version = 2`. Release cũ giữ nguyên HTML/CSS tự chứa, không bị sửa hoặc republish tự động. Muốn nhận responsive semantics mới phải publish lại có kiểm soát.

### Phase 2C - Frontend architecture có kiểm soát

| ID | Hạng mục | Impact | Risk | Effort | Dependencies | Verify | Rollback |
|---|---|---|---|---|---|---|---|
| R-11 | Xác nhận và loại AppLayout owner trùng | Sửa layout/overflow | Medium | S | Browser/component test | Mỗi route có một layout | Revert page/app config |
| R-12 | Fetch registry một lần | Giảm request/coupling | Low | S | Component contract | Network request count = 1 | Revert prop/provider |
| R-13 | Shared JSON/CSRF client nhỏ | Error/header nhất quán | Medium | M | Characterization request tests | Autosave/media/template/revision parity | Migrate từng callsite; revert riêng |
| R-14 | Tách publish/save protocol khỏi EditorShell | Testability/reliability | Medium | M | R-02 tests | Existing behavior + failure matrix pass | Revert module extraction |
| R-15 | Tách Inspector theo group ổn định | Maintainability/render scope | Medium | L | Component tests | No content loss on node/viewport switch | Incremental commits per group |

Không gom R-11..R-15 vào một commit.

#### Kết quả Phase 2C - 2026-10-24

| ID | Trạng thái | Bằng chứng / giới hạn |
|---|---|---|
| R-11 | PASS | App-level layout resolver là owner duy nhất; 5 page không còn tự render AppLayout. Test ownership 6/6; chưa có DOM browser harness. |
| R-12 | PASS | Registry request dedupe trong từng editor session, retry/error và session isolation tests 3/3. Không có cache global cross-website. |
| R-13 | PASS | Shared JSON/CSRF client có characterization headers/body/status; autosave/media/template/revision/publish caller giữ nhánh lỗi hiện có. |
| R-14 | PASS | Protocol module giữ save-before-publish, conflict/save failure stop, exception recovery và double-submit prevention. |
| R-15 | PASS | Mounted DOM interaction harness bảo vệ selected node, content/media edits, responsive viewport, reopen group, node change và unmount/remount. Content, Style, Settings, Node Identity và Quick Actions đã tách; document mutation vẫn do parent sở hữu, media modal transient đóng khi node đổi. |

R-15 verification cuối: `npm run check` pass 121/121 format và 120/120 lint; `npm run types:check` pass; `npm run test:frontend` pass 17 file, 117/117; `npm run build` pass; Pint/PHPStan pass; SQLite suite pass 282/308 với 26 PostgreSQL-only skip và 1.390 assertions. Browser smoke 375/768/1280 chưa được thiết lập vì chưa có automation provider và builder thật cần authenticated disposable fixtures; không tuyên bố visual parity.

Mặc định release compatibility: giữ active/old release bất biến; renderer v2 chỉ qua controlled republish. Inventory/republish page cũ là task riêng cần media check, backup, visual smoke và rollback confirmation.

### Capability architecture correctness - A1/A2/A3

| ID | Trạng thái | Kết quả đã kiểm chứng |
|---|---|---|
| A1 | PASS | First mutation/dirty tracking, queued save, save-before-publish và unload protection đã được sửa và kiểm chứng; xem capability audit và handoff. |
| A2 | PASS | Hai alias hiện có (`Rich Text`, `Card Grid`) được canonicalize qua kết quả `DocumentValidator`; persistence, revisions, templates, preview và publish dùng canonical data. Defaults và RichText/FAQ sanitization được áp dụng nhất quán; invalid input fail closed; historical revisions và sealed releases không bị mutate. Focused 128/128, full SQLite 292 pass + 26 PostgreSQL-only skip. |
| A3 | PASS | Public tree mutations preserve root/leaf, valid unique ID, depth 10, total 250, direct-child 50, and cycle invariants. Reparent uses actual subtree height, rejects a full destination without penalizing same-parent reorder, preserves IDs, and is atomic with selection/history. Replacement documents are validated before undo/redo or revision restore. Focused frontend 32/32; full frontend 138/138; full SQLite 292 pass + 26 PostgreSQL-only skip. |
| A4 | PASS | All 12 registered components expose typed Inspector property metadata from the existing component definitions. Content, style, settings, responsive visibility, validation/coercion, alias lookup, and media targeting are metadata-driven while writes continue through `EditorShell` and `useDocumentState`. Full frontend 144/144; full SQLite 293 pass + 26 PostgreSQL-only skip. |

### Package 2 - Shared style primitives

| Package | Trạng thái | Kết quả đã kiểm chứng |
|---|---|---|
| Package 2 | PASS WITH CAVEATS (bounded slice remediation) | The independent review originally returned FAIL. This remediation fixes Hero child foreground selection for all bounded backgrounds and responsive overrides, cross-validates Inspector descriptors against property schemas, and enforces primitive responsive eligibility in PHP and TypeScript. Full frontend 161/161; SQLite suite 301 passed + 26 PostgreSQL-only skipped; PHPStan 0. This is not full original-roadmap acceptance. |

Compatibility remains schema v1 with optional defaults; old enum semantics, historical revisions, sealed releases, renderer version, and fixed 768/1024 breakpoints are unchanged. Package 3 Layers/DnD was implemented in later packages; browser/E2E evidence remains outstanding.

Deferred from this slice: general border width/style controls, radius/shadow/opacity, per-side spacing, arbitrary dimensions/units, font family/weight/line-height/letter-spacing, gradients/background images, token authoring, and Package 3 Layers/DnD. Browser screenshot parity, computed accessibility contrast, hosted CI, deployment, and fresh PostgreSQL concurrency verification also remain outstanding. These are required separately before claiming the original full Package 2 roadmap acceptance criteria are complete.

Rủi ro tương thích A2 đã được characterize: future supported saves/templates/revision copies chuyển sang canonical JSON và canonical hashes; publish có thể reuse legacy revision nếu canonical semantics bằng nhau mà không rewrite row lịch sử. Không migration, republish tự động hoặc sửa release file cũ.

### Phase 3 - UX workflow và accessibility

| ID | Hạng mục | Impact | Risk | Effort | Dependencies | Verify | Rollback |
|---|---|---|---|---|---|---|---|
| R-16 | Save/publish live region và focus recovery | Accessibility | Low | S/M | A11y harness | Axe + keyboard smoke | Revert ARIA/focus patch |
| R-17 | Required/FAQ max/URL client guidance | Giảm lỗi muộn | Low | M | Server rules canonical | Client + server validation parity | Revert client-only checks |
| R-18 | Restore revision history boundary | Tránh undo bất ngờ | Medium | S | Product decision | Restore/undo integration test | Revert clear/boundary behavior |
| R-19 | Layers panel MVP, read/select/navigation trước | Cây sâu dễ dùng | Medium | M/L | UX design + tree tests | Keyboard navigation, selected parent/child | Feature flag/remove panel |
| R-20 | DnD reorder/reparent sau Layers | Nhanh thao tác | High | L | R-19; insertion/validation design | Cycle/depth/children/keyboard tests | Feature flag giữ move up/down |

### Phase 4 - Performance có số đo

Trạng thái 2026-10-31:

- R-21: **PARTIAL** — có benchmark deterministic trong Vitest cho 10/100/250 node, nhưng chưa chạy browser trace/paint/heap.
- R-22: **PENDING**.
- R-23: **PASS LOCAL** cho snapshot/history optimization; kết quả hiện tại là execution benchmark, không phải browser input-to-paint.
- R-24: **PARTIAL** — revisions/releases nhận `limit` bounded và UI lấy 50 bản mới nhất; chưa có cursor/offset/load-more, page listing và before/after dataset measurement.
- R-25: **PENDING**.

| ID | Hạng mục | Impact | Risk | Effort | Dependencies | Verify | Rollback |
|---|---|---|---|---|---|---|---|
| R-21 | Browser performance harness 10/100/250 node | Có baseline client | Low | M | Stable fixtures | Repeatable trace/report | Test tooling only |
| R-22 | Giảm inline CSS duplication với versioned immutable asset strategy | Public transfer/cache | High | L | Deployment/asset retention design | TTFB/transfer/LCP before-after; old release works | Feature flag; retain old assets |
| R-23 | Tối ưu reactive/snapshot theo profile | Inspector/undo latency | Medium/High | M/L | R-21 proves bottleneck | p50/p95 + regression tests | Small isolated commits |
| R-24 | Pagination page/revision/release theo measured threshold | Scale | Medium | M | Dataset benchmark/API UX | Query/time/payload before-after | Keep old contract version or optional pagination |
| R-25 | Image delivery variants nếu LCP chứng minh cần | LCP/bandwidth | High | L | Storage/data migration design | LCP/bytes/CLS before-after | Original URL fallback |

### Phase 5 - Tính năng thiếu theo ưu tiên product

Trạng thái 2026-10-31: submission administration UI và các UI template/preview/release/unpublish/SEO đã hoàn thành local. **Form component trong Page Builder chưa được triển khai**, nên Phase 5 chưa thể đóng toàn bộ.

Thứ tự đề xuất giữ theo yêu cầu người dùng, nhưng chỉ bắt đầu sau safety/responsive:

1. Publish-save guard và Image src validation: nằm trong R-02/R-09.
2. Responsive parity: R-06..R-08.
3. Layers panel: R-19.
4. DnD reparent: R-20.
5. Form component/submission UI.
6. Template management UI.
7. Preview share-link UI.
8. Release history/rollback UI.
9. Unpublish workflow.
10. Advanced SEO fields.
11. FAQ/client validations.

Mỗi feature phải có user story, acceptance criteria, permission matrix, failure state, test và doc update trước implementation.

## 4. Kế hoạch test/regression

### Luôn chạy sau mỗi nhóm nhỏ

- `npm run check`
- `npm run types:check`
- `npm run test:frontend`
- `npm run build`
- `composer lint:check`
- `composer types:check`
- `php artisan test`

### Sau khi có CI database an toàn

- `php artisan test -c phpunit.pgsql.xml` trên database disposable, không phải `page_builder_dev`.

### Browser/E2E cần bổ sung

- Blank/template page creation.
- Add/edit/nest/duplicate/move/delete.
- Undo/redo/shortcuts và restore boundary.
- Autosave/retry/conflict/two-tab.
- Media upload/reference/delete protection.
- Draft preview và active release preservation khi publish fail.
- Release rollback, slug redirect.
- Responsive editor/public 375/768/1280.
- Viewer direct API mutation denial và cross-tenant 404.
- Sanitization fixtures.

## 5. Performance và UX acceptance metrics

Không dùng một điểm tổng hợp. Mỗi phase phải báo riêng:

- Builder TTI với 10/100/250 node.
- Inspector input-to-paint p50/p95.
- Undo/redo duration và heap delta.
- Initial request count và transferred bytes.
- Autosave requests/minute, payload bytes, p50/p95, conflict/failure rate.
- Publish phase 1/2/3 duration và failure reason.
- Public TTFB, HTML transfer size, LCP, CLS, INP.
- Visual mismatch count editor/public ở ba viewport.
- Keyboard task completion, axe critical/serious count, focus return pass rate.

Môi trường đo, fixture, số lần chạy, cache state và giới hạn phải được ghi cùng kết quả.

## 6. Quyết định cần người dùng xác nhận

1. Viewer có được phép quản lý trạng thái/xóa submission không? Baseline product role nói không; roadmap mặc định Owner/Editor only.
2. Responsive breakpoint public giữ `md=768`, `lg=1024` hay desktop phải bắt đầu đúng preset 1280? Hiện preset 1280 nhưng public desktop behavior bắt đầu 1024.
3. Khi restore revision, local undo history nên bị xóa hay tạo boundary có thể undo?
4. Publish/rollback đồng thời: serialize toàn bộ hay last-completed action wins?
5. Có yêu cầu self-contained release tuyệt đối, hay cho phép release tham chiếu immutable versioned CSS asset được giữ theo retention?
6. Runtime chuẩn là PHP 8.3 hay 8.4?
7. Default branch intended là `master` hay `main`?
8. Có thể cấp một PostgreSQL test database disposable cho concurrency CI không?
9. Website/page delete cần xóa storage ngay, enqueue cleanup, hay giữ theo retention?
10. Custom domain có nằm trong roadmap gần hay public URL tiếp tục `/sites/{website}/{page}`?

## 7. Tiêu chí kết thúc Phase 1

- [x] Architecture, Page Builder, UX, performance và security được đối chiếu với system reference và code.
- [x] Roadmap có severity, impact, risk, effort, dependencies, verify và rollback.
- [x] P0 ưu tiên data/permission/publish correctness trước refactor thẩm mỹ.
- [x] Không sửa code ứng dụng, schema, dependency hoặc migration.
- [x] Chờ xác nhận trước refactor diện rộng hoặc thay đổi architecture/contract.
