# 04 — Architecture Decision Records (ADR)

Trạng thái ban đầu: **Proposed**. Chốt các ADR sau khi hoàn thành Phase 00 discovery.

## ADR-001: Modular monolith
- **Status:** Proposed
- **Context:** MVP cần phát triển nhanh và vận hành trên server tài nguyên thấp.
- **Decision:** Laravel modular monolith; phân miền logic rõ ràng, deploy một ứng dụng.
- **Consequences:** Dễ triển khai và debug; cần giữ module boundaries và tránh coupling không kiểm soát.
- **Revisit when:** Team/deployment scale hoặc boundary domain cho thấy nhu cầu tách service.

## ADR-002: PostgreSQL làm source of truth
- **Status:** Proposed
- **Decision:** PostgreSQL là database chính; dùng JSONB cho page document đã validate, relational columns cho metadata và constraints.
- **Consequences:** Query metadata có index rõ ràng; document schema cần versioning và validation server-side.

## ADR-003: Static-first public rendering
- **Status:** Proposed
- **Decision:** Public page được render thành HTML tĩnh khi publish; dynamic features dùng Laravel/API riêng.
- **Consequences:** Tốc độ và chi phí request tốt; cần pipeline publish, asset versioning và cơ chế release swap an toàn.
- **Revisit when:** Nội dung thay đổi liên tục hoặc yêu cầu cá nhân hóa không phù hợp static.

## ADR-004: Không dùng Redis ở MVP nếu chưa cần
- **Status:** Proposed
- **Decision:** Ưu tiên database/cache/queue driver phù hợp sẵn có; chỉ thêm Redis khi đo đạc chứng minh có lợi.
- **Consequences:** Ít dịch vụ vận hành; phải đánh giá tải DB nếu dùng database queue/cache.

## ADR-005: Component registry allowlist
- **Status:** Proposed
- **Decision:** Component type và schema nằm trong registry được kiểm soát; không chạy code người dùng nhập.
- **Consequences:** Bảo mật và khả năng migration tốt hơn; giảm khả năng tùy biến vô hạn.

## ADR-006: Revision bất biến và rollback tạo revision mới
- **Status:** Proposed
- **Decision:** Mỗi revision là snapshot bất biến. Rollback copy document từ revision mục tiêu thành revision mới.
- **Consequences:** Audit rõ ràng, lịch sử không bị viết lại; cần giới hạn số revision/retention nếu dữ liệu lớn.

## ADR-007: Atomic publish
- **Status:** Proposed
- **Decision:** Render ra release tạm, validate, sau đó atomic switch sang release mới; giữ release trước.
- **Consequences:** Tránh public site bị nửa cũ nửa mới; cần kiểm tra khả năng filesystem/hosting hỗ trợ.

## ADR-008: Optimistic locking cho draft
- **Status:** Proposed
- **Decision:** Draft update mang version token/revision expectation; server từ chối update stale thay vì âm thầm ghi đè.
- **Consequences:** Cần UX xử lý conflict; giảm rủi ro mất nội dung khi mở nhiều tab.

## ADR-009: RAM budget dựa trên đo đạc
- **Status:** Proposed
- **Decision:** Không cam kết số worker hay thông số DB cố định trước khi đo peak RSS và tải đại diện. Bắt đầu bảo thủ, quan sát rồi điều chỉnh.
- **Consequences:** Cần baseline performance và load test nhỏ trước production.
