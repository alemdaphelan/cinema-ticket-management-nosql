# Nghiệm thu giai đoạn 7

Ngày nghiệm thu: 18/11/2026 (Asia/Bangkok)

## Kết quả tổng

- PHPUnit: 53/53 test đạt, 462 assertion.
- Outbox: một `event_id` dùng xuyên suốt publish/replay; ghi idempotent; retry 4 lần với backoff 5/30/120/300 giây.
- Fallback: API MySQL chính vẫn HTTP 200 khi lần lượt dừng Redis, MongoDB, Cassandra API và Neo4j; thanh toán không rollback vì lỗi NoSQL.
- Đồng thời giữ ghế: 2 yêu cầu tranh chấp, đúng 1 yêu cầu thắng bằng `SET NX EX`.
- Backup/restore: MongoDB, Redis, Cassandra và Neo4j đều khôi phục probe và đối chiếu thành công.
- GUI: đã xác nhận dữ liệu thật bằng Compass, RedisInsight, TablePlus và Neo4j Desktop.

## Danh sách truy vấn/nghiệm thu chính

| Mã | Kho dữ liệu | Truy vấn/primitive | Tiêu chí đạt | Bằng chứng |
|---|---|---|---|---|
| Q-R1 | Redis | `GET/SETEX` qua `RedisQueryCache` | HIT/MISS/TTL đúng, lỗi Redis trả BYPASS | `phase7-tests.txt`, `phase7-resilience.txt` |
| Q-R2 | Redis | `SET key owner NX EX ttl` | 2 yêu cầu chỉ 1 thắng | `phase7-concurrency.txt` |
| Q-R3 | Redis | ZSET leaderboard theo ngày | Xếp hạng giảm dần, TTL hợp lệ | `RedisPhaseThreeTest` |
| Q-M1 | MongoDB | aggregate review theo `movie_id/status` | Điểm trung bình và số lượng đúng | `mongodb-phase7-compass.png` |
| Q-M2 | MongoDB | outbox theo `published_at/occurred_at` | Replay không nhân đôi projection | `PhaseSevenAcceptanceTest` |
| Q-C1 | Cassandra | partition `user_id + activity_month` | Không `ALLOW FILTERING`, LOCAL_QUORUM | `cassandra-phase5-cqlsh.txt` |
| Q-C2 | Cassandra | partition `movie_id + activity_date` | Ghi/đọc activity đúng partition | `cassandra-phase7-tableplus.png` |
| Q-N1 | Neo4j | phim chung thể loại/người dùng | Không trả phim dừng, xếp theo score | `neo4j-phase6-profile.txt` |
| Q-N2 | Neo4j | gợi ý cá nhân hóa/cold-start | Có strategy rõ ràng và không lặp phim đã đặt | `neo4j-phase7-desktop.png` |

## Benchmark 30 vòng

| Đường đọc | Trung bình (ms) |
|---|---:|
| MySQL cold + ghi cache | 714.833 |
| MySQL không cache | 1.708 |
| Redis cache hit | 1.243 |
| MongoDB review summary | 89.387 |
| Cassandra health | 111.820 |
| Neo4j similar movies | 37.917 |

Kết luận trung thực: trong 30 vòng đo trên dữ liệu demo, Redis cache hit nhanh hơn MySQL không cache khoảng 1,37 lần. Đây chỉ là kết quả tham khảo của môi trường hiện tại; Redis vẫn được đánh giá thêm qua correctness, TTL, giảm tải truy vấn lặp và primitive nguyên tử giữ ghế, không dùng số đo này để suy diễn hiệu năng production.

## Lệnh tái nghiệm thu

```powershell
powershell -ExecutionPolicy Bypass -File scripts/nosql/phase7-acceptance.ps1
```

Script tạo/cập nhật các tệp trong `docs/evidence` và chỉ dùng dữ liệu probe cô lập cho thao tác xóa/khôi phục.
