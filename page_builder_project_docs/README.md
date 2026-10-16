# Page Builder Admin — Project Documentation

Bộ tài liệu định hướng xây dựng Page Builder Admin có khả năng kéo-thả, chỉnh sửa nội dung/giao diện, quản lý bản nháp, preview, publish ra HTML tĩnh và hỗ trợ nội dung động qua Laravel.

## Đọc theo thứ tự
1. `01_PRODUCT_REQUIREMENTS.md` — phạm vi sản phẩm và MVP.
2. `02_ARCHITECTURE.md` — kiến trúc tổng thể, luồng dữ liệu, publish.
3. `03_DATA_MODEL.md` — mô hình dữ liệu dự kiến.
4. `04_ADR.md` — các quyết định kiến trúc cần chốt.
5. `05_PHASE_PLAN.md` — lộ trình theo phase/module.
6. `06_RISK_REGISTER.md` — rủi ro và phương án giảm thiểu.
7. `07_ENGINEERING_RULES.md` — quy tắc kỹ thuật cho team/AI coding agent.
8. `08_PHASE_00_DISCOVERY_PROMPT.md` — prompt yêu cầu AI khảo sát repo và lập plan Phase 00.
9. `IMPLEMENTATION_STATUS.md` — theo dõi tiến độ.
10. `DECISIONS.md` — nhật ký quyết định.
11. `KNOWN_ISSUES.md` — lỗi/vấn đề đã biết.

## Stack dự kiến
- Laravel + Vue 3 + TypeScript + Inertia + Vite
- PostgreSQL
- Nginx + PHP-FPM
- Chưa dùng Redis, SSR server, Octane, Elasticsearch hay Kubernetes ở MVP nếu chưa có bằng chứng cần thiết.
- Public page ưu tiên HTML tĩnh khi publish; tính năng động dùng Laravel/API theo hợp đồng rõ ràng.

## Lưu ý
Đây là baseline để khảo sát và ra quyết định, không phải khẳng định rằng repo hiện tại đã có các thành phần này. Trước khi code, cần kiểm tra repository, phiên bản framework, hạ tầng, cách deploy và ngân sách RAM thực tế. Nếu PostgreSQL chạy cùng máy với ứng dụng, cần tính chung RAM cho cả hai.
