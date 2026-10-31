# PENTAGON — Project Status and Next Phases

Ngày cập nhật: 2026-10-31

Nhánh tích hợp và remote: `main`

Mốc hosted CI đã kiểm chứng: `837df40`

Remote: `origin` → `https://github.com/lumiere9823/pentagon_v4.git`

## 1. Trạng thái tổng quát

Pentagon hiện đã vượt qua giai đoạn prototype. Các module chính—document engine,
builder, media, revision/release, publishing, SEO, templates, submissions admin,
homepage và vận hành backup/restore—đều đã có implementation cùng automated
coverage. Hệ thống đang ở trạng thái **feature-complete cho luồng page cơ bản và
release-ready và **hosted CI đã PASS**, nhưng chưa được coi là production-ready vì
staging deployment, screen-reader acceptance và production cutover chưa diễn ra.

Homepage/Starter Set đã được fast-forward vào nhánh tích hợp. Mỗi website mới có
Home, About và Contact; Home là homepage `/`; database, backfill, public routing,
SEO, UI, concurrency và browser lifecycle đều đã được kiểm chứng local.

## 2. Các phase đã hoàn thành

| Phase | Phạm vi | Trạng thái | Mốc chính |
|---|---|---|---|
| Foundation 00–11 | Laravel/Inertia/Vue foundation, website/page/media, document schema, revision, publish | Hoàn thành | `ec7e074` và lịch sử trước đó |
| Audit 0–1 | Architecture, UX, performance, security audit và roadmap | Hoàn thành | Tài liệu `optimization/` |
| Safety 2A–2C | Backup correctness, publishing correctness, responsive contract, frontend architecture | PASS local | trước `857289e` |
| Capability A1–A4 | Inspector, style primitives có giới hạn, validation và workflow nền | PASS local | trước `7619d9d` |
| Package 3 | Layers, drag/drop, history, templates và workflow Builder | PASS local + browser coverage chính | `7619d9d`, `ffdd70d` |
| Phase A | Stabilization & Git Packaging; PostgreSQL, dependency audit, baseline chuẩn | Hoàn thành | `c2f2eff` |
| Phase B | Playwright, responsive 375/768/1280, axe, publish/unpublish lifecycle | PASS local | `ffdd70d` |
| Phase C | Deploy/rollback guard, backup/restore rehearsal, production health check | PASS local | `74f0f85` |
| D-048 | Homepage `/` và Starter Page Set Home/About/Contact | PASS local, đã merge vào integration | `355fbea`…`b493ab4` |
| Phase D | Remote integration, hosted CI và branch governance | Hoàn thành | `d7c2107`, runs `41181365205`/`41181626150` |
| Phase E.1 | Public-state serialization, activation revalidation, schema migration seam | PASS local, chờ hosted CI | `phase-e/reliability-closure` |

## 3. Verification tại mốc `b493ab4`

- Frontend format/lint: 131 file đúng format, 129 file không có lint warning/error.
- Vue TypeScript: PASS, 0 lỗi.
- Frontend unit/component: **415/415 PASS** trên 32 test files.
- Backend default/SQLite tại Phase E.1: **368 PASS, 29 PostgreSQL-only skipped, 0 fail,
  2.215 assertions**.
- Pint: PASS.
- PHPStan: PASS, 0 lỗi.
- PostgreSQL 18 disposable suite: **44/44 PASS, 239 assertions**.
- Fresh PostgreSQL migration: **19/19 migration PASS** trên `pentagon_test`.
- Production build: **3.553 modules**, 0 build error.
- Playwright core workflow: **1/1 PASS**; starter set, homepage publish, canonical
  root 200, technical slug 301, homepage reassignment, responsive và axe.
- Backup/restore rehearsal bằng PostgreSQL thật: PASS.
- Production health check với `APP_ENV=production`, debug tắt: `healthy: true`.
- `git diff --check`: PASS.
- Working tree sau merge: sạch.

Các con số trên là baseline local tại `b493ab4`. Hosted workflow `41181365205`
đã PASS cả job `ci` và `browser` trên `837df40`; staging/production vẫn chưa có evidence.

## 4. Khoảng trống còn mở

### Correctness và reliability

1. Operation lock đã bao toàn publish/rollback/unpublish và activation tái kiểm tra
   media trong transaction; cần tiếp tục theo dõi lock contention trên staging thật.
2. Draft/template persistence đã lưu canonical sanitized document. Historical rows
   và sealed release cũ không bị rewrite.
3. `DocumentSchemaMigrator` và current-version contract đã có; schema v1 vẫn là
   format duy nhất. Fixture v1→vNext chỉ được thêm cùng semantics schema v2 thực tế.
4. Responsive registry/breakpoints đã dùng contract backend→frontend và có parity
   tests; presentation CSS chi tiết vẫn cần test bổ sung khi thêm primitive mới.

### Product và Builder

1. Chưa có component `Form` trong registry; submissions endpoint/admin đã có
   nhưng page builder chưa tự sinh được form.
2. History UI chỉ tải 50 revisions/releases mới nhất, chưa có load-more/pagination.
3. Full style roadmap còn thiếu border/radius/shadow/opacity, per-side spacing,
   arbitrary dimensions/units, typography nâng cao, gradient/background image và
   token authoring.
4. `moveNode` đã có domain behavior nhưng chưa có UI caller cho một số thao tác.

### Verification và vận hành

1. Screenshot browser mới là artifact, chưa có golden-image visual regression.
2. Chưa thực hiện screen-reader acceptance thủ công.
3. Browser suite chưa phủ đầy đủ two-tab conflict, media upload/delete, release
   rollback và cross-tenant flows.
4. Hosted CI và branch protection đã xanh; `main` bắt buộc PR, `ci`, `browser`,
   linear history và resolved conversations; force-push/delete bị chặn.
5. Chưa có staging/production server inventory, secrets, TLS/systemd/worker và
   backup schedule evidence.

### Performance

1. R-21 mới có deterministic benchmark; chưa có browser trace/paint/heap budget.
2. R-24 chưa có page-list/history pagination measurement trên dataset lớn.
3. R-22 immutable asset strategy và R-25 image variants vẫn pending; chỉ triển
   khai sau khi measurement chứng minh cần thiết.

## 5. Các phase tiếp theo

### Phase D — Integration and Hosted CI (hoàn tất)

Mục tiêu: đưa mốc tích hợp lên repository dùng chung và có bằng chứng CI độc lập.

- [x] Cấu hình Git remote và push nhánh `main`.
- [x] Sửa dependency install cho Vite+, sinh Wayfinder form variants và bảo đảm build
  assets trước application tests trên runner sạch.
- [x] Xác nhận hosted `ci` và `browser` PASS trên commit `837df40`.
- [x] Cấu hình branch protection/required checks và quy trình PR phù hợp với repository.
- [ ] Chuẩn hóa retention/upload cho Playwright artifacts và test summary nếu cần lưu dài hạn.

Hosted CI và branch governance đã đạt. Nhánh Phase E hiện tại là PR đầu tiên dùng rule mới.

Không còn phụ thuộc mở cho Phase D.

### Phase E — Reliability Closure

Mục tiêu: đóng các rủi ro correctness còn lại trước khi thêm nhiều feature.

- Thiết kế lại publish/rollback serialization để activation và Case B rollback
  không race, có media revalidation rõ ràng.
- Chuẩn hóa document persistence hoặc ghi ADR nếu tiếp tục lưu source document.
- Thiết kế schema-version migration pipeline và fixture migration v1→vNext.
- Thêm parity contract chung cho responsive editor/public mapping.
- Bổ sung PostgreSQL concurrency và chaos tests cho các thay đổi trên.

Điều kiện hoàn thành: không còn finding correctness mức cam/đỏ trong §21 System
Reference; rollback/publish failure không làm mất active release.

Tiến độ:

- [x] Operation lock xuyên suốt publish/rollback/unpublish, không giữ DB transaction khi render.
- [x] Một activation implementation chung cho publish và hai nhánh rollback.
- [x] Media revalidation ngay trước pointer switch; rollback recompile fail closed và giữ active release.
- [x] Canonical persistence đã được xác nhận bằng test hiện hữu.
- [x] Schema migration seam fail closed và current-version constant dùng chung ở backend writers.
- [x] Responsive contract backend→frontend và parity coverage hiện hữu đã được audit lại.
- [ ] Thêm migration fixture v1→vNext khi schema v2 có thay đổi dữ liệu được phê duyệt.
- [ ] Hosted CI/PR acceptance cho package Phase E.1.

### Phase F — Product Completion: Forms

Mục tiêu: hoàn tất luồng thu lead từ Builder tới submissions admin.

- Thêm `Form` component vào registry, inspector và document validation.
- Render form parity giữa Vue canvas và PHP public renderer.
- Field schema, validation, honeypot, CSRF/origin và rate-limit UX.
- Submission success/error state, accessibility và privacy copy.
- Browser E2E: tạo form → publish → submit → xem/cập nhật/xóa submission.

Điều kiện hoàn thành: người dùng tạo và publish form hoàn toàn trong Builder;
submission hoạt động end-to-end và cross-tenant/security tests xanh.

### Phase G — Builder Completeness and History Navigation

Mục tiêu: đóng các phần UI/capability đang partial.

- History/release load-more hoặc cursor pagination.
- Page listing measurement và pagination khi dataset vượt ngưỡng.
- Hoàn thiện style primitives theo thứ tự nhu cầu sản phẩm.
- Nối các node actions còn chưa có UI caller; giảm transaction boilerplate trong
  `EditorShell` sau khi có characterization tests.
- Mở rộng browser coverage cho two-tab conflict, media lifecycle và rollback.

Điều kiện hoàn thành: không còn mục product workflow quan trọng ở trạng thái
PARTIAL; dataset lớn vẫn điều hướng được trong budget đã đặt.

### Phase H — Measured Performance and Asset Delivery

Mục tiêu: tối ưu dựa trên số đo, không tối ưu theo giả định.

- Thiết lập browser trace cho 10/100/250 nodes: scripting, paint, heap, interaction.
- Đo page listing, history, public transfer, LCP/CLS và cache hit behavior.
- Chỉ thực hiện R-22 immutable/versioned asset strategy nếu transfer/cache là nút
  thắt; giữ compatibility với release cũ.
- Chỉ thực hiện R-25 image variants nếu LCP/bandwidth chứng minh lợi ích.
- Gắn performance budgets vào CI ở mức ổn định, tránh flaky threshold.

Điều kiện hoàn thành: có baseline/before-after và rollback strategy cho từng tối ưu.

### Phase I — Release Acceptance and Production Launch

Mục tiêu: xác nhận hệ thống trên hạ tầng thật và phát hành có khả năng rollback.

- Golden visual regression cho viewport chính và screen-reader acceptance.
- Deploy staging bằng script thật; kiểm tra Nginx/TLS/PHP-FPM/scheduler/queue.
- Chạy smoke, security headers, backup, restore rehearsal và rollback staging.
- Xác nhận monitoring/log rotation, backup retention, RTO/RPO và incident runbook.
- Production cutover, post-deploy health check và rollback drill.

Điều kiện hoàn thành: staging acceptance ký duyệt, production health xanh, backup
khôi phục được và rollback đã diễn tập trên cùng loại hạ tầng.

## 6. Thứ tự đề xuất

```text
Phase D hosted CI + governance ✓

Phase E → Phase F → Phase G → Phase H
                           └──────────────┐
Phase D + local phases hoàn tất ─────────┴→ Phase I
```

Ưu tiên thực thi ngay: đưa **Phase E.1** qua PR/hosted CI, sau đó bắt đầu
**Phase F — Product Completion: Forms**; migration vNext đi cùng schema v2 thực tế.

## 7. Git packaging hiện tại

- `main` theo dõi `origin/main`; hosted CI đã PASS tại `837df40`.
- `feature/homepage-starter-set` giữ mốc review `b493ab4`.
- `verification/phase-b` giữ mốc `ffdd70d`.
- `stabilization/phase-a` giữ mốc `c2f2eff`.
- `master` vẫn ở mốc lịch sử `ec7e074`.
- Chưa tuyên bố staging hoặc production deployment pass.
