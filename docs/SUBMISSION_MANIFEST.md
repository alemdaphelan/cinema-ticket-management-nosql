# Danh mục đóng gói nộp bài

## Thành phần

- Mã nguồn: toàn bộ repository, trừ `.env.nosql.local`, `vendor`, `node_modules` và dữ liệu volume.
- Hạ tầng: `docker-compose.yml`, `Dockerfile`, thư mục `docker/`.
- Script: `scripts/nosql/` gồm init, smoke, backup và nghiệm thu giai đoạn 3–7.
- SRS/SDS: `../SRS_SDS_Cinema_NoSQL.docx`.
- Kế hoạch/checklist: `../KeHoach_TichHop_4_CSDL_NoSQL.docx`.
- Báo cáo minh chứng: `../BaoCaoMinhChung_DoAn_DuLieuNoSQL.docx`.
- Kịch bản demo: `docs/DEMO_SCRIPT.md`.
- Bằng chứng: `docs/evidence/`.

## Phân công theo dấu vết repository

| Thành viên/định danh | Phần việc xác nhận được | Bằng chứng |
|---|---|---|
| `alemdaphelan` (chủ repository GitHub) | Tích hợp và đóng gói repository; điều phối các giai đoạn | Git remote và lịch sử repository |
| Nhóm dự án | Kiểm tra nghiệp vụ, chuẩn bị thuyết trình, bổ sung họ tên/MSSV chính thức trước khi nộp | Bảng phân công trong báo cáo nhóm |

Nếu dự án có nhiều thành viên, nhóm phải thay dòng “Nhóm dự án” bằng họ tên, MSSV và tỷ lệ đóng góp thực tế; không suy diễn thông tin cá nhân từ tài khoản GitHub.

## Kiểm tra trước khi nén

- [ ] Không có mật khẩu/token trong source hoặc ảnh.
- [ ] `phase7-acceptance.ps1` PASS.
- [ ] `docker compose ps` cho thấy các dịch vụ healthy.
- [ ] Tài liệu DOCX mở được, không tràn bảng/trang.
- [ ] Ảnh Compass, RedisInsight, TablePlus và Neo4j Desktop có trong `docs/evidence`.
- [ ] Điền đầy đủ họ tên, MSSV, lớp, nhóm và tỷ lệ đóng góp.
