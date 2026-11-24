# Kịch bản demo 5–7 phút

## 0:00–0:40 — Kiến trúc

Mở sơ đồ trong tài liệu SRS/SDS. Nêu MySQL là source of truth; Redis là cache/giữ ghế/leaderboard; MongoDB là review/chat/outbox; Cassandra là activity/audit; Neo4j là recommendation graph.

## 0:40–1:40 — Redis

1. Mở RedisInsight, kết nối `127.0.0.1:6379`.
2. Cho thấy namespace `cinema` và khóa cache.
3. Gọi API phim hai lần, chỉ header `X-Cache: MISS` rồi `HIT`.
4. Chạy `phase7-concurrency-test.ps1`, giải thích hai yêu cầu chỉ một chủ ghế thắng nhờ `SET NX EX`/Lua.

## 1:40–2:40 — MongoDB

1. Mở Compass, database `cinema_content`.
2. Cho thấy `movie_reviews`, `chat_sessions`, `integration_outbox` và index/TTL.
3. Tạo hoặc sửa review trên ứng dụng; refresh Compass để thấy document.
4. Nêu outbox dùng cùng `event_id`, replay idempotent sang Cassandra/Neo4j.

## 2:40–3:40 — Cassandra

1. Mở TablePlus, keyspace `cinema_analytics`.
2. Cho thấy ba bảng `user_activity_by_month`, `movie_events_by_day`, `audit_events_by_day`.
3. Gọi API lịch sử hoạt động của user; giải thích partition key và `LOCAL_QUORUM`, không dùng `ALLOW FILTERING`.

## 3:40–4:40 — Neo4j

1. Mở Neo4j Desktop > Query, kết nối `Cinema NoSQL Docker`.
2. Chỉ số node/relationship và nhãn `User`, `Movie`, `Genre`.
3. Gọi API phim tương tự và gợi ý cá nhân hóa; giải thích fallback `cold_start_popular`.

## 4:40–5:40 — Chịu lỗi và nhất quán

1. Mở `phase7-resilience.txt`: API MySQL vẫn HTTP 200 khi từng NoSQL bị dừng.
2. Mở `phase7-tests.txt`: 70 test, 679 assertions; test chức năng dùng SQLite và các driver NoSQL array, các phép thử live có minh chứng riêng.
3. Nêu thanh toán commit ở MySQL; projection chạy queue và outbox replay, vì vậy lỗi analytics/cache/recommendation không làm mất giao dịch chính.

## 5:40–6:30 — Backup/restore và kết luận

1. Mở `phase7-backup-restore.txt`, chỉ PASS của 4 CSDL.
2. Mở benchmark ngày 25/11: Redis hit 0,744 ms, MySQL không cache 0,589 ms. Lần này Redis chậm hơn trên dữ liệu rất nhỏ; không suy diễn thành hiệu năng production.
3. Kết luận: bốn NoSQL có chức năng riêng, có fallback, test, backup/restore và bằng chứng GUI.

## Chuẩn bị trước demo

Stack Docker hiện chưa có phim, suất chiếu và đơn hàng trong MySQL. Chuẩn bị dữ liệu mẫu và kiểm tra lại luồng trình duyệt trước khi quay; các probe kỹ thuật không thay thế dữ liệu demo nghiệp vụ.

```powershell
docker compose --env-file .env.nosql.local up -d
docker compose --env-file .env.nosql.local exec -T app php artisan nosql:mongo-setup
docker compose --env-file .env.nosql.local exec -T app php artisan nosql:neo4j-sync
```

Không hiển thị `.env.nosql.local` hoặc mật khẩu trên màn hình quay.
