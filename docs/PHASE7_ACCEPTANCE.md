# Nghiệm thu giai đoạn 7

Ngày tái nghiệm thu: 25/11/2026 (Asia/Bangkok)

## Kết quả tổng

- PHPUnit: 70/70 test đạt, 679 assertions trên SQLite in-memory và driver NoSQL array. Kết quả live bên dưới chạy riêng trên bốn dịch vụ Docker thật.
- Outbox: một `event_id` dùng xuyên suốt publish/replay; ghi idempotent; retry 4 lần với backoff 5/30/120/300 giây.
- Fallback live: API `/api/shows?date=...` vẫn HTTP 200 khi lần lượt dừng Redis, MongoDB, Cassandra API và Neo4j; header MISS/BYPASS xác nhận đọc MySQL thay vì cache đã có. Thanh toán khi NoSQL lỗi được kiểm tra tự động với SQLite và queue giả lập, chưa phải thanh toán end-to-end live.
- Đồng thời giữ ghế: 2 yêu cầu tranh chấp, đúng 1 yêu cầu thắng bằng `SET NX EX`.
- Backup/restore: MongoDB, Redis, Cassandra và Neo4j đều khôi phục probe và đối chiếu thành công.
- Redis live: cache MISS/HIT, TTL, SET NX EX, Lua nhiều ghế và ZSET đều đạt; namespace ngẫu nhiên bảo vệ leaderboard đang dùng.
- GUI: các ảnh Compass, RedisInsight, TablePlus và Neo4j Desktop là minh chứng lần trước; lượt 25/11 kiểm tra bằng CLI/API, chưa chụp lại GUI.

## Phạm vi và giới hạn

MySQL trong stack Docker hiện có 0 phim, 0 suất chiếu và 0 đơn hàng. Benchmark tự tạo rồi xóa đúng một phim probe khi cần; các kiểm tra chức năng tự động sử dụng dữ liệu riêng trong SQLite. Cần chuẩn bị dữ liệu mẫu trước khi demo toàn bộ luồng đặt vé trên trình duyệt. Các kết quả dưới đây xác nhận dịch vụ, truy vấn, tính nguyên tử, chịu lỗi và khôi phục probe; không thay thế nghiệm thu thủ công giao diện với dữ liệu demo đầy đủ.

Trong quá trình tái nghiệm thu đã đồng bộ các dependency còn thiếu trong volume `vendor` theo `composer.lock`, bổ sung kiểm tra mã thoát của bước setup, bỏ build lại Cassandra API mặc định và sửa truy vấn minh chứng Cassandra về UTC. Các dịch vụ đã được bật lại sau phép thử chịu lỗi.

## Danh sách truy vấn/nghiệm thu chính

| Mã | Kho dữ liệu | Truy vấn/primitive | Tiêu chí đạt | Bằng chứng |
|---|---|---|---|---|
| Q-R1 | Redis | `GET/SETEX` qua `RedisQueryCache` | HIT/MISS/TTL đúng, lỗi Redis trả BYPASS | `phase7-tests.txt`, `phase7-resilience.txt` |
| Q-R2 | Redis | `SET key owner NX EX ttl` | 2 yêu cầu chỉ 1 thắng | `phase7-concurrency.txt` |
| Q-R3 | Redis | ZSET leaderboard theo ngày | Xếp hạng giảm dần, TTL hợp lệ | `redis-live-smoke.txt`, `RedisPhaseThreeTest` |
| Q-M1 | MongoDB | aggregate review theo `movie_id/status` | Điểm trung bình và số lượng đúng | `mongodb-phase7-compass.png` |
| Q-M2 | MongoDB | outbox theo `published_at/occurred_at` | Replay không nhân đôi projection | `PhaseSevenAcceptanceTest` |
| Q-C1 | Cassandra | partition `user_id + activity_month` | Không `ALLOW FILTERING`, LOCAL_QUORUM | `cassandra-phase5-cqlsh.txt` |
| Q-C2 | Cassandra | partition `movie_id + activity_date` | Ghi/đọc activity đúng partition | `cassandra-phase7-tableplus.png` |
| Q-N1 | Neo4j | phim chung thể loại/người dùng | Không trả phim dừng, xếp theo score | `neo4j-phase6-profile.txt` |
| Q-N2 | Neo4j | gợi ý cá nhân hóa/cold-start | Có strategy rõ ràng và không lặp phim đã đặt | `neo4j-phase7-desktop.png` |

## Benchmark 30 vòng

| Đường đọc | Trung bình (ms) |
|---|---:|
| MySQL cold + ghi cache | 3.696 |
| MySQL không cache | 0.589 |
| Redis cache hit | 0.744 |
| MongoDB review summary | 2.006 |
| Cassandra health | 5.374 |
| Neo4j similar movies | 10.167 |

Trong 30 vòng đo này, Redis cache hit 0,744 ms chậm hơn truy vấn MySQL không cache 0,589 ms. MySQL không có dữ liệu nghiệp vụ, MongoDB/Neo4j cũng không có lịch sử đầy đủ tương ứng với phim probe, nên các số đo chỉ phản ánh độ trễ đường truy vấn trên môi trường này. Không dùng chúng để kết luận hiệu năng production hoặc chất lượng gợi ý; Redis vẫn đạt kiểm tra cache, TTL và giữ ghế nguyên tử.

## Lệnh tái nghiệm thu

```powershell
powershell -ExecutionPolicy Bypass -File scripts/nosql/phase7-acceptance.ps1
```

Script tạo/cập nhật các tệp trong `docs/evidence` và chỉ dùng dữ liệu probe cô lập cho thao tác xóa/khôi phục.
