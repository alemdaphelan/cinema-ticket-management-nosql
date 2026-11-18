# Cinema Ticket Management API

Dự án API Quản lý vé xem phim được xây dựng trên nền tảng Laravel.

## Hướng dẫn cài đặt khi clone từ Github về

Để chạy được dự án sau khi clone, cần thực hiện đúng theo các bước sau:

**Bước 1: Cài đặt các thư viện (Dependencies)**
```bash
composer install
```

**Bước 2: Tạo file cấu hình môi trường (.env)**
Copy từ file mẫu `.env.example`:
```bash
cp .env.example .env
```
*(Trên Windows/Command Prompt có thể dùng: `copy .env.example .env`)*

Nếu chạy Laravel trực tiếp ngoài Docker, tạo khóa ứng dụng sau khi copy file:

```bash
php artisan key:generate
```

Sau đó, mở file `.env` và cấu hình Database. Bạn có 2 cách chạy dự án:

**Cách 1: Chạy toàn bộ bằng Docker (Khuyên dùng)**
Nếu máy bạn đã cài Docker, bạn không cần sửa gì thêm trong `.env`. Chạy lệnh sau để khởi động cả Database (MySQL) và App (Laravel):
```bash
docker-compose up -d
```
App sẽ tự động chạy tại `http://localhost:8000`.

### Hạ tầng NoSQL

Docker Compose đã khai báo MongoDB, Redis, Cassandra và Neo4j với volume, xác thực, health check và cấu trúc ban đầu. Redis phục vụ cache, giữ ghế và leaderboard; MongoDB phục vụ review, lịch sử chatbot và outbox; Cassandra phục vụ lịch sử hoạt động/audit theo user-tháng và phim-ngày; Neo4j phục vụ phim tương tự và gợi ý cá nhân hóa.

Tạo file mật khẩu cục bộ, kiểm tra cấu hình và khởi động bốn CSDL cùng các dịch vụ khởi tạo:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/nosql/init-env.ps1
docker compose --env-file .env.nosql.local config --quiet
docker compose --env-file .env.nosql.local up -d mongodb redis cassandra neo4j redis-init cassandra-init cassandra-api app worker scheduler neo4j-init
```

Script `init-env.ps1` tạo cả khóa Laravel `APP_KEY` và mật khẩu cục bộ cho bốn NoSQL. File `.env.nosql.local` bị Git bỏ qua và không được đưa lên repository.

Cassandra cần nhiều thời gian khởi động hơn các dịch vụ còn lại. Khi các container đã healthy và ba dịch vụ init kết thúc với mã 0, chạy kiểm tra kết nối:

Giai đoạn 5 bổ sung `cassandra-api` (driver Node chính thức) và `worker`. Laravel đưa sự kiện vào queue; worker ghi bất đồng bộ với retry, nên Cassandra lỗi không rollback nghiệp vụ MySQL. Dữ liệu hoạt động có TTL 90 ngày, audit 180 ngày, TWCS và consistency `LOCAL_QUORUM`.

```powershell
docker compose --env-file .env.nosql.local up -d --build cassandra-api worker
powershell -ExecutionPolicy Bypass -File scripts/nosql/cassandra-phase5-test.ps1
```

API `GET /api/activity/history?month=YYYY-MM&event_type=MOVIE_VIEWED` chỉ trả lịch sử của tài khoản Sanctum hiện tại. Các truy vấn Cassandra luôn dùng đủ partition key, không dùng `ALLOW FILTERING`.

```powershell
powershell -ExecutionPolicy Bypass -File scripts/nosql/smoke-test.ps1
```

Tạo bản backup đầu tiên trong `storage/backups/nosql`:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/nosql/backup.ps1
```

Sau khi ứng dụng tích hợp Redis ở giai đoạn 3, chạy bộ kiểm tra cache hit/miss, TTL, giữ ghế đơn, Lua giữ nhiều ghế và leaderboard:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/nosql/redis-phase3-test.ps1
```

Các API đọc phim và lịch chiếu trả header `X-Cache` với một trong ba giá trị `MISS`, `HIT` hoặc `BYPASS`. Endpoint `GET /api/movies/leaderboard?date=YYYY-MM-DD&limit=10` trả bảng xếp hạng phim theo ngày.

Sau khi tích hợp MongoDB ở giai đoạn 4, tạo hoặc cập nhật collection/index rồi sao chép review cũ từ MySQL:

```powershell
php artisan nosql:mongo-setup
php artisan nosql:mongo-migrate-reviews
```

Lệnh migration không xóa bảng SQL cũ. Có thể xóa riêng các document đã sao chép bằng:

```powershell
php artisan nosql:mongo-migrate-reviews --rollback
```

Chạy smoke test MongoDB thật, JSON Schema, unique index, aggregation, TTL và thử backup/restore trên database tạm:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/nosql/mongodb-phase4-test.ps1
```

Review dùng các endpoint `GET/POST /api/movies/{movieId}/reviews`, `PATCH /api/movies/{movieId}/reviews/{reviewId}` và `DELETE /api/movies/{movieId}/reviews/{reviewId}`. Chatbot nhận thêm `session_id` và trả lại `session_id`, `expires_at`; MongoDB tự xóa phiên hết hạn theo TTL index.

Ảnh MongoDB Compass xác nhận database `cinema_content` cùng ba collection và số index được lưu tại `docs/evidence/mongodb-phase4-compass.png`.

Giai đoạn 6 đồng bộ `User`, `Movie`, `Genre` cùng các quan hệ `VIEWED`, `BOOKED`, `RATED`, `IN_GENRE` bằng `MERGE`. Laravel gọi trực tiếp Transactional HTTP API của Neo4j; kết quả gợi ý được cache Redis 5 phút và sự kiện mới được chiếu sang graph qua queue.

```powershell
docker exec cinema_nosql_app php artisan nosql:neo4j-sync
powershell -ExecutionPolicy Bypass -File scripts/nosql/neo4j-phase6-test.ps1
```

Hai API chính là `GET /api/movies/{movieId}/similar` và `GET /api/recommendations` (cần Sanctum). Trang danh sách phim có thể kèm gợi ý cá nhân hóa khi đã đăng nhập; trang chi tiết trả thêm `similar_movies`. Người dùng chưa có lịch sử nhận fallback phim phổ biến, có thể lọc theo `genre`. Bằng chứng constraints, đối soát, `PROFILE` và logical backup/restore được lưu tại `docs/evidence/neo4j-phase6-profile.txt`.

### Nghiệm thu giai đoạn 7

Chạy một lệnh để kiểm tra toàn bộ 53 test ứng dụng, tranh chấp giữ ghế, benchmark, fallback khi từng NoSQL dừng và backup/restore bốn CSDL:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/nosql/phase7-acceptance.ps1
```

Kết quả được lưu trong `docs/evidence`. Hồ sơ chi tiết nằm ở `docs/PHASE7_ACCEPTANCE.md`, kịch bản trình bày 5–7 phút ở `docs/DEMO_SCRIPT.md`, và danh mục đóng gói ở `docs/SUBMISSION_MANIFEST.md`.

Outbox được replay mỗi phút bằng `nosql:outbox-replay`; cùng một `event_id` được dùng cho publish trực tiếp và replay để Cassandra/Neo4j xử lý idempotent. Tài khoản MongoDB ứng dụng chỉ giữ quyền `readWrite`; validator collection do init script đặc quyền quản lý.

Script backup dùng công cụ gốc của MongoDB, Redis và Cassandra. Neo4j được dừng ngắn để đóng gói volume nhất quán rồi tự khởi động lại.

Các cổng quản trị chỉ bind vào máy cục bộ:

- MongoDB: `127.0.0.1:27018` (cổng trong mạng Docker vẫn là `27017`)
- Redis: `127.0.0.1:6379`
- Cassandra: `127.0.0.1:9042`
- Neo4j Browser: `http://127.0.0.1:7474`
- Neo4j Bolt: `127.0.0.1:7687`

**Cách 2: Chạy thủ công bằng XAMPP / MySQL cục bộ**
Nếu không dùng Docker, bạn phải tự tạo một Database có tên `cinema_ticket_management` trong MySQL của bạn. Sau đó trong file `.env`, điều chỉnh thông tin nếu cần:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cinema_ticket_management
DB_USERNAME=root
DB_PASSWORD=mật_khẩu_của_bạn
```

**Bước 3: Tạo Application Key**
```bash
php artisan key:generate
```
*(Lưu ý: Nếu dùng Cách 1 - Docker, bạn cần vào container để chạy: `docker exec -it cinema_app php artisan key:generate`)*

**Bước 4: Khởi tạo các bảng trong Database (Migrate)**
```bash
php artisan migrate
```
*(Lưu ý: Nếu dùng Cách 1 - Docker, lệnh sẽ là: `docker exec -it cinema_app php artisan migrate`)*

**Bước 5: Khởi chạy Server (Chỉ dành cho Cách 2)**
```bash
php artisan serve
```

---

## Swagger API Documentation
Sau khi server chạy, truy cập đường dẫn sau để xem tài liệu API:
`http://localhost:8000/api/docs`
