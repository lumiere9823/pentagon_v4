# Phase 1 - Performance Review

## 1. Phương pháp và giới hạn

Chỉ số trong báo cáo này đến từ:

- production build local;
- benchmark command có sẵn chạy 50 iteration;
- PostgreSQL EXPLAIN do benchmark thực hiện;
- static inspection cho request/render/state behavior.

Không có browser runner, Lighthouse, Chrome Performance trace, React/Vue devtools profile hay production dataset. Vì vậy không có số giả định cho builder startup, rerender, LCP, CLS, INP hoặc TTFB production.

## 2. Baseline đo được

### 2.1 Build

`npm run build`:

- 3.523 module transformed.
- Vite build: 4,17 giây; toàn command: 5,51 giây.
- 87% build time nằm trong plugin hooks; Vue transform 34%, Wayfinder buildStart 24%.
- Builder chunk: 133,38 KB raw / 35,51 KB gzip.
- App chunk: 146,08 KB raw / 41,90 KB gzip.
- Shared dist: 233,45 KB raw / 79,77 KB gzip.
- CSS chính: 137,82 KB raw / 21,79 KB gzip.
- Tổng asset JS raw: 762.364 byte; CSS raw: 155.091 byte.

Các số chunk không được cộng thành “initial load” vì cần network waterfall thực tế để biết chunk nào tải trên Builder route.

### 2.2 Server document benchmark

`php artisan app:benchmark-performance --iterations=50`:

- 50 node/document.
- Validate + render: 2,02 ms trung bình.
- Memory delta: 0,03 MB.
- Peak memory: 36 MB.
- HTML: 146.686 byte.
- Bốn query nóng đều dùng Index Scan.

Kết luận giới hạn: CPU/memory của renderer chưa cho thấy bottleneck ở 50 node, nhưng output HTML lớn chủ yếu do inline stylesheet.

## 3. Findings

### P-01 - Mỗi release inline toàn bộ stylesheet ứng dụng

- Severity: High cho public performance/bandwidth.
- Bằng chứng: `DocumentHtmlRenderer.php:118` mặc định gọi `inlineCompiledStylesheet()`; CSS entry raw 137,82 KB; HTML benchmark 50 node là 146.686 byte.
- Tác động: HTML parse/transfer lớn cho mọi page, cache CSS không dùng chung giữa page/release; thay đổi nhỏ nội dung vẫn nhân bản CSS trong release.
- Trade-off hiện tại: release self-contained và không gãy khi Vite hash thay đổi. Không nên quay lại link hash hiện tại mà không có asset versioning/retention.
- Cần đo tiếp: TTFB compressed/uncompressed, HTML transfer size, parse/style cost, cache hit behavior.

### P-02 - Registry request bị lặp khi mở editor

- Severity: Medium.
- Bằng chứng: `EditorShell.vue:233` và `ComponentLibrary.vue:91` cùng GET `/api/components/registry`.
- Tác động: thêm một request/JSON parse; metadata có thể lệch state lỗi giữa library và inspector.
- Fix candidate: fetch một lần ở composition root, truyền cùng response xuống; verify request count = 1.

### P-03 - Document deep watch và full-tree snapshot trên mọi nhóm edit

- Severity: Risk, chưa chứng minh bottleneck.
- Bằng chứng: deep watch toàn document (`EditorShell.vue:127`); history clone toàn document; state tree reactive sâu.
- Tác động tiềm năng: inspector edit gây traversal + snapshot clone; tăng theo node count/payload.
- Không được tối ưu ngay bằng command log/memoization nếu chưa có profile.
- Cần benchmark 10/50/100/250 node: input-to-paint, snapshot duration, heap delta, rerender count.

### P-04 - Inspector và shell là component lớn

- Severity: Medium risk.
- `InspectorPanel.vue` 1.099 dòng và `EditorShell.vue` 609 dòng.
- Kích thước file không tự chứng minh chậm, nhưng làm dependency graph/reactive scope khó quan sát.
- Cần Vue render profiling trước; ưu tiên tách state theo vùng chỉ khi profile cho thấy invalidation rộng.

### P-05 - List không pagination ở một số màn hình

- Severity: Medium risk.
- Website show tải toàn bộ pages/members; releases/revisions dùng `get()`.
- Chưa có latency/query-count ở dataset lớn nên chưa khẳng định regression.
- Ngưỡng cần benchmark: 100/1.000 page, 1.000 revision, 100 member.

### P-06 - Media usage scan tăng theo lịch sử

- Severity: Medium risk, correctness-sensitive.
- Scanner quét draft, mọi revision và template để bảo vệ reference.
- Đây là fail-closed invariant; không được bỏ nguồn scan để tăng tốc.
- Cần đo delete/save/publish với lịch sử lớn, sau đó xem xét reference index/materialized relation nếu thực sự cần.

### P-07 - Public image delivery chưa có variant/resizing pipeline

- Severity: Medium.
- Upload giới hạn dimension nhưng public Image dùng URL gốc và chỉ `loading=lazy`, `decoding=async`.
- Không có `srcset`, width/height attributes hay generated thumbnails cho public renderer theo code audit.
- Tác động tiềm năng: LCP lớn và CLS nếu ảnh hero/above-fold; chưa có page/browser metric.
- Cần đo ảnh thật trước khi thiết kế derivative pipeline.

### P-08 - Build warning font fallback

- Severity: Low.
- Build báo thiếu optional `fontaine` cho optimized fallback.
- Không được tự thêm dependency trong audit; cần đo CLS/font swap trước khi quyết định bật/tắt optimized fallback.

## 4. Request và autosave behavior

- Autosave debounce 2 giây và tối đa 3 retry exponential, cap 8 giây.
- Thay đổi trong lúc request đang chạy được coalesce bằng `pendingChangeAfterSave`.
- Khi tab hidden, dirty state được flush.
- Không có telemetry để đo save frequency thực tế hoặc payload size distribution.
- Payload luôn gửi toàn bộ `document_json`; ở giới hạn 512 KB, autosave có thể tốn bandwidth đáng kể.
- Không nên chuyển sang patch/delta trước khi có idempotency, conflict semantics và benchmark rõ.

## 5. Database

Điểm đã xác minh:

- Active release, page slug, redirect và submission list dùng index trong EXPLAIN local.
- Các controller chính dùng eager loading cho relation hiển thị.
- Publish/save dùng transaction ngắn và thả lock trước render/disk I/O.

Chưa xác minh:

- Query count và latency end-to-end cho builder load/publish trên dataset production.
- Advisory lock wait/contention dưới concurrent editors/publishers.
- Cache hit/miss cho sitemap/robots/public page.
- Table/index bloat theo revision/release lịch sử.

## 6. Bộ đo bắt buộc trước và sau tối ưu

### Editor

| Metric | Scenario | Cách đo đề xuất |
|---|---|---|
| Time to interactive | Mở builder 10/100/250 node | Browser performance mark từ navigation đến canvas usable |
| Canvas render | Switch document/viewport | Vue profiler + performance measure |
| Rerender count | Edit một prop inspector | Vue devtools/per-component counter trong test harness |
| Inspector latency | Input đến canvas paint | `performance.now()` + RAF |
| Undo snapshot cost | 10/100/250 node, payload gần 512 KB | Duration + heap allocation |
| Request count | Initial builder/load media | Browser network trace |
| Autosave | Requests/minute, bytes/request, p50/p95 latency | Client telemetry/test proxy |

### Public

| Metric | Scenario | Cách đo đề xuất |
|---|---|---|
| TTFB | Cold/warm active release | HTTP timing, local staging và production-like host |
| Transfer size | HTML inline CSS, gzip/brotli | curl/browser network |
| LCP | Hero image/text fixtures | Lighthouse/Web Vitals nhiều run |
| CLS | Image/font/layout fixtures | Web Vitals + screenshot |
| INP | FAQ/form interaction | Browser trace |
| Cache correctness | ETag 200/304 và release switch | Integration test + HTTP timing |

### Backend

- Publish phase 1/2/3 duration tách riêng.
- Query count và total DB time cho builder, save, publish, public show.
- Lock acquisition wait và conflict rate.
- Media scan duration theo số page/revision/template.
- Release HTML/CSS size theo số node.

## 7. Performance budget đề xuất để xác nhận

Các giá trị dưới đây là target đề xuất, chưa phải cam kết đã đạt:

- Không tăng initial Builder JS gzip sau mỗi phase nếu không có feature justification.
- Một registry request khi mount.
- Inspector input-to-paint p95 dưới 100 ms ở fixture 250 node trên máy chuẩn đã thống nhất.
- Undo/redo p95 dưới 100 ms ở fixture 250 node.
- Không quá một autosave request cho một burst edit debounce, trừ queued change có bằng chứng.
- Public HTML transfer phải được đo sau compression; target cụ thể chỉ đặt sau khi có page fixture và hosting profile.

## 8. Tối ưu không nên làm trước khi có bằng chứng

- Không thêm virtualization chỉ vì tree tối đa 250 node.
- Không thay snapshot bằng command log khi chưa profile và chưa có correctness suite.
- Không debounce thêm để che race/conflict.
- Không cache response có tenant/permission mà chưa thiết kế cache key.
- Không bỏ media/revision scan hoặc lock để giảm latency.
- Không thêm Redis/queue/SSR daemon theo suy đoán.

## 9. Tiêu chí hoàn thành review performance

- [x] Có baseline render, memory, build asset và query plan đo được.
- [x] Mọi con số nêu môi trường và giới hạn.
- [x] Không tuyên bố cải thiện khi chưa có before/after.
- [x] Candidate optimization được xếp theo bằng chứng và rủi ro correctness.
