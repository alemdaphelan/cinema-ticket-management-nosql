const STORAGE_KEY = 'cinema_locale';
const COOKIE_NAME = 'cinema_locale';
const SKIP_TAGS = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'CODE', 'PRE', 'SVG', 'PATH', 'TEXTAREA']);
const ATTR_NAMES = ['placeholder', 'title', 'aria-label'];
const HAS_VI_CHAR = /[\u00C0-\u024F\u1E00-\u1EFF]/;
const HAS_ASCII_VI_PATTERN = /\b(?:Ca\s+\d+|Trang\s+\d+|\d+\s+ca\s*•)/;

const originalTextMap = new WeakMap();
const originalAttrMap = new WeakMap();
const dynamicMap = new Map();
let sortedMovieTitles = [];
let originalDocTitle = null;
let observer = null;
let titleObserver = null;
let isApplying = false;
let currentLocale = 'vi';

const EXACT_MAP = {
    // Navbars & common
    'Trang chủ': 'Home',
    'Thể loại': 'Genres',
    'Lịch chiếu': 'Showtimes',
    'Vé của tôi': 'My Tickets',
    'Giới thiệu': 'About Us',
    'Tìm kiếm phim...': 'Search movies...',
    'Cài đặt': 'Settings',
    'Đổi mật khẩu': 'Change Password',
    'Đăng xuất': 'Logout',
    'Đăng nhập': 'Login',
    'Đăng ký': 'Register',
    'Liên kết': 'Quick Links',
    'Hỗ trợ': 'Support',
    'Giờ làm việc: 8:00 - 22:00': 'Hours: 8:00 - 22:00',
    'Chính sách': 'Policies',
    'Điều khoản sử dụng': 'Terms of Use',
    'Chính sách bảo mật': 'Privacy Policy',
    'Câu hỏi thường gặp': 'FAQ',
    'Kết nối với chúng tôi': 'Connect With Us',
    'Đang tìm kiếm...': 'Searching...',
    'Không tìm thấy phim nào phù hợp.': 'No matching movies found.',
    'Có lỗi xảy ra, vui lòng thử lại sau.': 'An error occurred, please try again later.',
    'Xem trang chủ': 'View Website',
    'Chế độ Staff': 'Staff Mode',
    'Menu quản trị': 'Admin Menu',
    'Menu nhân viên': 'Staff Menu',
    'Quay về bảng điều khiển quản trị': 'Back to Admin Dashboard',
    'Về trang Admin': 'Back to Admin',
    'Về Admin': 'Admin',
    'Quyền Quản trị viên': 'Administrator Access',

    // Theme & notifications
    'Đổi giao diện Sáng / Tối / Hệ thống': 'Switch Light / Dark / System theme',
    'Giao diện Sáng': 'Light Mode',
    'Giao diện Tối': 'Dark Mode',
    'Theo hệ thống': 'System Default',
    'Thông báo': 'Notice',
    'Nội dung thông báo': 'Notification details',
    'Đã hiểu': 'Got it',
    'Xác nhận': 'Confirm',
    'AI Trợ lý Rạp phim': 'AI Cinema Assistant',
    'Phim nào đang hot nhất?': 'Which movies are trending?',
    'Lịch chiếu hôm nay thế nào?': "What are today's showtimes?",
    'Nhập câu hỏi của bạn...': 'Ask a question...',

    // Admin sidebar tabs
    'Thống kê': 'Analytics',
    'Thống kê - Admin': 'Analytics - Admin',
    'Quản lý Phim': 'Movies',
    'Suất chiếu': 'Showtimes',
    'Bắp nước': 'Snacks',
    'Voucher': 'Vouchers',
    'Đơn hàng': 'Orders',
    'Quản lý nhân viên': 'Staff Management',

    // Staff sidebar tabs
    'Quét vé': 'Ticket Scan',
    'Đơn hàng & Doanh thu': 'Orders & Revenue',
    'Bán vé tại quầy': 'Box Office POS',
    'Lịch làm': 'My Schedule',
    'Chấm công': 'Attendance',
    'Tính lương': 'Payroll',

    // Admin Staff Management tabs & page headers
    'Quản lý nhân viên - Lịch tổng': 'Staff Management - Master Schedule',
    'Quản lý nhân viên - Bảng chấm công': 'Staff Management - Timesheet',
    'Quản lý nhân viên - Tài khoản nhân viên': 'Staff Management - Staff Accounts',
    'Lịch tổng': 'Master Schedule',
    'Bảng chấm công': 'Timesheet',
    'Tài khoản nhân viên': 'Staff Accounts',

    // Staff Workspace headers & titles
    'Quét Mã QR - Nhân viên': 'QR Ticket Scan - Staff',
    'Quét Mã QR Soát Vé': 'Scan QR Ticket',
    'Dành cho nhân viên rạp chiếu': 'For cinema staff',
    'Đơn hàng & Doanh thu - Nhân viên': 'Orders & Revenue - Staff',
    'Doanh thu & Đơn hàng kết ca': 'End-of-Shift Orders & Revenue',
    'Lịch làm việc - Nhân viên': 'Work Schedule - Staff',
    'Lịch làm cá nhân': 'My Work Schedule',
    'Thời khóa biểu ca trực': 'Weekly Shift Timetable',
    'Chấm công - Nhân viên': 'Attendance - Staff',
    'Chấm công nhân viên': 'Staff Attendance',
    'Tính lương - Nhân viên': 'Payroll - Staff',
    'Bảng chấm công & Tính lương': 'Timesheet & Payroll',

    // Master & Staff Schedule
    'Cấu trúc ca rạp chiếu': 'Cinema Shift Structure',
    '3 Ca/Ngày • 8 tiếng/ca • 5 NV/ca': '3 Shifts/Day • 8h/shift • 5 staff/shift',
    '2 Soát vé • 2 Thu ngân • 1 Chuẩn bị snacks': '2 Ticket Checkers • 2 Cashiers • 1 Snack Prep',
    'Tổng doanh thu tuần đang xem': 'Selected Week Total Revenue',
    'Vi phạm & Vắng mặt cần xử lý': 'Pending Violations & Absences',
    'Không có cảnh báo mới': 'No new alerts',
    'Xem chi tiết cảnh báo': 'View Alert Details',
    'Lịch trực ca nhân viên theo tuần': 'Weekly Staff Shift Schedule',
    'Tất cả': 'All',
    'Hiện tại': 'Today',
    'In lịch': 'Print Schedule',
    '‹ Trở về': '‹ Prev',
    'Tiếp ›': 'Next ›',
    'Toàn màn hình': 'Fullscreen',
    'Ca làm': 'Shift',
    'Đang tải...': 'Loading...',
    'Đang tải lịch tổng...': 'Loading master schedule...',
    'Đang tải lịch làm...': 'Loading schedule...',
    'Ca sắp diễn ra': 'Upcoming Shift',
    'Ca sắp tới': 'Upcoming Shift',
    'Ca đang làm việc': 'Active Shift',
    'Ca đã hoàn thành': 'Completed Shift',
    'Có nhân viên vi phạm / vắng mặt': 'Staff Violation / Absence',
    'Đã chấm công / Hoàn thành': 'Checked In / Completed',
    'Vi phạm / Vắng mặt': 'Violation / Absent',
    'Bắt buộc chấm công trước giờ bắt đầu 10 phút và không kết thúc ca sớm.': 'Must check in 10 minutes before shift starts and not check out early.',
    'Chi tiết ca làm việc': 'Shift Details',
    'Danh sách 5 nhân viên trực ca & Chấm công': 'Assigned 5-Staff Shift Roster & Attendance',
    'Cảnh báo Vi phạm & Vắng mặt nhân viên': 'Staff Violation & Absence Alerts',
    'Hệ thống tự động phát hiện nhân viên đi trễ, về sớm hoặc không đi làm': 'System automatically detects late check-ins, early check-outs, or absences',
    'Xác nhận đã xem tất cả': 'Acknowledge All',
    'Hiện tại không có vi phạm hoặc vắng mặt mới': 'No new violations or absences',
    'Tất cả cảnh báo trước đó đã được xác nhận.': 'All previous alerts have been acknowledged.',
    'Doanh thu tại quầy': 'Counter Revenue',
    'Tổng doanh thu ca': 'Total Shift Revenue',
    'Doanh thu ca': 'Shift Revenue',
    'Đánh dấu vắng': 'Mark Absent',
    'Chấm công vào ca': 'Check In Now',
    'Chấm công ra ca': 'Check Out Now',
    'Sắp làm': 'Upcoming',
    'Đang trực': 'On Duty',
    'Đã xong': 'Completed',
    'Vắng mặt': 'Absent',
    'Vi phạm': 'Violation',
    'Đúng giờ': 'On Time',
    'Hoàn thành': 'Completed',
    'Đi trễ': 'Late',
    'Về sớm': 'Early Leave',
    'Đi trễ & Về sớm': 'Late & Early',
    'Chờ chấm công': 'Pending',
    'Tạm ngưng': 'Cancelled',
    'Soát vé': 'Ticket Checker',
    'Thu ngân': 'Cashier',
    'Chuẩn bị snacks': 'Snack Prep',
    'SOÁT VÉ': 'TICKET CHECKER',
    'THU NGÂN': 'CASHIER',
    'CHUẨN BỊ SNACKS': 'SNACK PREP',

    // Attendance & Timesheet & Payroll
    'Bảng chấm công nhân viên theo tháng / kỳ': 'Monthly / Period Staff Timesheet',
    'Theo dõi thời gian bắt đầu ca, kết thúc ca, phương thức chấm công và xuất báo cáo sang file Excel': 'Track shift start/end times, check-in methods, and export reports to Excel',
    'Xuất file Excel': 'Export Excel',
    'Xuất báo cáo Excel': 'Export Excel Report',
    'Tổng lượt phân ca': 'Total Shifts',
    'Đúng giờ / Hoàn thành': 'On Time / Completed',
    'Tổng giờ làm': 'Total Hours',
    'Lọc theo kỳ': 'Filter by Period',
    'Theo tháng': 'By Month',
    'Theo tuần': 'By Week',
    'Theo năm': 'By Year',
    'Ngày cụ thể': 'Specific Date',
    'Tất cả thời gian': 'All Time',
    'Chọn tháng': 'Select Month',
    'Chọn tuần': 'Select Week',
    'Nhập năm': 'Enter Year',
    'Chọn ngày cụ thể': 'Select Specific Date',
    'Chọn nhân viên': 'Select Staff',
    '-- Tất cả nhân viên --': '-- All Staff --',
    'Tìm theo tên / mã NV': 'Search by Name / Staff ID',
    'VD: Nguyễn Văn An, NV001...': 'e.g. Nguyen Van An, NV001...',
    'Tất cả 3 ca': 'All 3 Shifts',
    'Trạng thái chấm công': 'Attendance Status',
    'Tất cả trạng thái': 'All Statuses',
    'Hợp lệ': 'Valid',
    'Có vi phạm': 'Violations',
    'Tìm kiếm': 'Search',
    'Đặt lại': 'Reset',
    'Nhân viên': 'Staff',
    'Vị trí': 'Position',
    'Ngày & Ca làm': 'Date & Shift',
    'Ngày & Ca': 'Date & Shift',
    'Bắt đầu ca': 'Check-in',
    'Kết thúc ca': 'Check-out',
    'Vào ca': 'Check-in',
    'Ra ca': 'Check-out',
    'Số giờ': 'Hours',
    'Trạng thái & Ghi chú': 'Status & Notes',
    'Trạng thái': 'Status',
    'Đang tải dữ liệu chấm công...': 'Loading attendance records...',
    'Không tìm thấy dữ liệu chấm công phù hợp bộ lọc.': 'No attendance records match the current filters.',
    'Hiển thị 0 bản ghi': 'Showing 0 records',
    'Hiển thị:': 'Show:',
    'Thủ công': 'Manual',
    '‹ Trước': '‹ Prev',
    'Sau ›': 'Next ›',
    'Trước': 'Prev',
    'Sau': 'Next',
    'Giờ hệ thống': 'Server Time',
    'Ca làm việc được phân công': 'Assigned Shifts',
    'Đang tải danh sách ca...': 'Loading assigned shifts...',
    'Chọn một ca làm bên trái để thực hiện chấm công.': 'Select a shift on the left to check in or check out.',
    'Chấm công thủ công': 'Manual Attendance',
    'Chấm công thủ công ghi nhận trực tiếp thời điểm hiện tại theo giờ máy chủ Việt Nam.': 'Manual attendance records the current timestamp using Vietnam server time.',
    'Camera AI (mô phỏng)': 'Camera AI (simulation)',
    'Camera AI (mô phỏng):': 'Camera AI (simulation):',
    'Chức năng minh họa chấm công bằng camera. Không nhận diện hoặc xác thực khuôn mặt; tỷ lệ tin cậy là số ngẫu nhiên để minh họa.': 'Demonstration of camera attendance. No face recognition or identity verification; confidence scores are randomly generated for illustration.',
    'Mô phỏng sẵn sàng': 'Simulation ready',
    'Khung camera minh họa': 'Camera demonstration frame',
    'Tin cậy mô phỏng': 'Simulated confidence',
    'Bật Camera': 'Start Camera',
    'Tắt Camera': 'Stop Camera',
    'Sẵn sàng': 'Ready',
    'Camera chưa bật': 'Camera is off',
    'Nhấn «Bật Camera» hoặc bấm nút mô phỏng để minh họa chấm công.': 'Click Start Camera or a simulation button to demonstrate attendance.',
    'Lịch sử chấm công gần đây của bạn': 'Your Recent Attendance History',
    'Xem bảng tính lương chi tiết →': 'View Detailed Payroll →',
    'Vai trò': 'Role',
    'Giờ công': 'Worked Hours',
    'Không có ca trực nào trong hôm nay hoặc 2 ngày tới.': 'No shifts scheduled today or in the next 2 days.',
    'Chưa chọn ca làm việc nào.': 'No shift selected.',
    'Chưa có lịch sử chấm công.': 'No attendance history yet.',
    'Đơn giá / giờ': 'Hourly Rate',
    'Ca hoàn thành / Giờ công': 'Completed Shifts / Hours',
    'Lương cơ bản': 'Base Pay',
    'Phụ cấp ca đêm · +30%': 'Night Shift Bonus · +30%',
    'Khấu trừ vi phạm': 'Violation Deduction',
    'Tổng lương thực lãnh': 'Total Net Salary',
    'Vào – Ra': 'In – Out',
    'Giờ tính lương': 'Billable Hours',
    'Phụ cấp đêm': 'Night Bonus',
    'Khấu trừ': 'Deduction',
    'Thực lãnh': 'Net Pay',
    'Đang tính bảng lương...': 'Calculating payroll...',
    'Không có dữ liệu chấm công trong kỳ đã chọn.': 'No attendance records in the selected period.',

    // Staff Accounts
    'Danh sách & Tài khoản nhân viên rạp': 'Staff Directory & Accounts',
    'Quản lý thông tin cá nhân, vị trí làm việc, tài khoản/mật khẩu đăng nhập, tình trạng tài khoản và reset mật khẩu về mã số nhân viên': 'Manage personal details, job positions, login credentials, account status, and reset passwords to employee code',
    '+ Thêm nhân viên': '+ Add Staff',
    'Tổng nhân viên': 'Total Staff',
    'Đang hoạt động': 'Active',
    'Tài khoản bị khóa': 'Locked',
    'Nghỉ việc': 'Inactive',
    'Tìm nhân viên': 'Find Staff',
    'Mã NV, họ tên, email, SĐT...': 'Staff ID, name, email, phone...',
    'Vị trí công việc': 'Job Position',
    'Tất cả vị trí': 'All Positions',
    'Tình trạng tài khoản': 'Account Status',
    'Đang khóa': 'Locked',
    'Mã NV': 'Staff ID',
    'Thông tin cá nhân': 'Personal Info',
    'Vị trí & Lương/giờ': 'Position & Hourly Rate',
    'Tài khoản & Mật khẩu': 'Account & Password',
    'Tình trạng': 'Status',
    'Đang tải danh sách nhân viên...': 'Loading staff directory...',
    'Không có nhân viên nào phù hợp.': 'No matching staff found.',
    'Thêm nhân viên mới': 'Add New Staff',
    'Chỉnh sửa nhân viên': 'Edit Staff',
    'Đóng': 'Close',
    'Hủy': 'Cancel',
    'Lưu thông tin': 'Save Changes',
    'Mã nhân viên': 'Staff ID',
    'Tự sinh, VD: NV017': 'Auto-generated, e.g. NV017',
    'Họ và tên *': 'Full Name *',
    'Họ và tên': 'Full Name',
    'Email đăng nhập *': 'Login Email *',
    'Mật khẩu': 'Password',
    '· để trống = mã NV': '· leave blank = Staff ID',
    'Mặc định = mã nhân viên': 'Default = Staff ID',
    'Số điện thoại': 'Phone Number',
    'Ngày sinh': 'Date of Birth',
    'Giới tính': 'Gender',
    'Nam': 'Male',
    'Nữ': 'Female',
    'Khác': 'Other',
    'Vị trí làm việc *': 'Job Position *',
    'Lương / giờ · VNĐ': 'Hourly Rate · VND',
    'Ngày vào làm': 'Hired Date',
    'Địa chỉ': 'Address',
    'Hiện/Ẩn mật khẩu': 'Show/Hide Password',
    'Reset MK': 'Reset Password',
    'Khóa TK': 'Lock Account',
    'Mở khóa': 'Unlock',

    // Admin Dashboard & Catalog / Orders
    'Bộ lọc & Thống kê doanh thu': 'Filters & Revenue Analytics',
    'Chọn nhanh mốc thống kê theo ngày, tuần, tháng, năm hoặc lọc khoảng thời gian tùy chọn': 'Quickly filter analytics by day, week, month, year, or custom date range',
    'Chế độ lọc dữ liệu': 'Data Filter Mode',
    'Theo Ngày': 'Daily',
    'Theo Tuần': 'Weekly',
    'Theo Tháng': 'Monthly',
    'Theo Năm': 'Yearly',
    'Toàn bộ': 'All Time',
    'Theo ngày cụ thể': 'Specific Date',
    'Theo tuần này': 'This Week',
    'Khoảng ngày tùy chọn': 'Custom Date Range',
    'Chọn ngày': 'Select Date',
    'Từ ngày': 'From Date',
    'Đến ngày': 'To Date',
    'Áp dụng bộ lọc': 'Apply Filter',
    'Doanh thu kỳ lọc': 'Filtered Revenue',
    'Hôm nay': 'Today',
    'Tổng số vé bán:': 'Total tickets sold:',
    'Phim đang chiếu': 'Now Showing',
    'Suất chiếu hôm nay:': "Today's showtimes:",
    'Tỷ lệ thanh toán': 'Payment Breakdown',
    'Kỳ lọc': 'Period',
    'Chuyển khoản:': 'Bank Transfer:',
    'Tiền mặt:': 'Cash:',
    'Lượt đặt vé theo phim': 'Bookings by Movie',
    'Mỗi lượt là một đơn đã thanh toán hoặc đã sử dụng trong kỳ lọc.': 'Each count represents a paid or completed order in the period.',
    'Đang tải…': 'Loading…',
    'Tải thêm': 'Load More',
    'Tổng doanh thu kỳ lọc': 'Total Filtered Revenue',
    'Chuyển khoản': 'Bank Transfer',
    'Tiền mặt': 'Cash',
    'Biến động doanh thu (Chuyển khoản vs Tiền mặt)': 'Revenue Trend (Transfer vs Cash)',
    'Theo ngày': 'By Day',
    'Cơ cấu hình thức thanh toán': 'Payment Method Breakdown',
    'Quản lý Đơn hàng - CineStar': 'Order Management - CineStar',
    'Quản lý Đơn hàng': 'Order Management',
    'Danh sách & Tra cứu Đơn hàng': 'Orders & Lookup',
    'Khách hàng': 'Customer',
    'Nhập tên, email hoặc mã user...': 'Enter name, email, or user ID...',
    'Nhập tên phim...': 'Enter movie title...',
    'Trạng thái đơn hàng': 'Order Status',
    'Đã thanh toán': 'Paid',
    'Hoàn tất / Đã xem': 'Completed / Watched',
    'Chờ thanh toán': 'Pending Payment',
    'Đã hủy': 'Cancelled',
    'Khoảng thời gian': 'Date Range',
    'Mã ĐH': 'Order ID',
    'Phim & Suất chiếu': 'Movie & Showtime',
    'Ghế ngồi': 'Seats',
    'Hình thức': 'Method',
    'Tổng tiền': 'Total',
    'Ngày tạo': 'Created At',
    'Đang tải dữ liệu...': 'Loading data...',
    'Không thể tải dữ liệu đơn hàng. Vui lòng thử lại.': 'Could not load orders. Please try again.',
    'Không tìm thấy đơn hàng nào phù hợp với bộ lọc.': 'No orders match the current filters.',
    'Quản lý Suất chiếu': 'Showtime Management',
    '📅 Quản lý Suất chiếu': '📅 Showtime Management',
    '+ Thêm suất chiếu': '+ Add Showtime',
    'Thời gian chiếu': 'Showtime Window',
    'Mọi thời điểm': 'Any Time',
    'Khoảng ngày giờ': 'Date & Time Range',
    'Phòng chiếu': 'Screen',
    'Tất cả phòng': 'All Screens',
    'Tất cả phim': 'All Movies',
    'Từ': 'From',
    'Đến': 'To',
    'Phòng': 'Screen',
    'Giá vé cơ bản': 'Base Price',
    'Không tìm thấy suất chiếu phù hợp.': 'No matching showtimes found.',
    'Quản lý Bắp nước': 'Snack Management',
    'Tên / Combo': 'Item / Combo',
    'Giá bán': 'Price',
    'Giá bán (VNĐ)': 'Price (VND)',
    'Đường dẫn ảnh': 'Image URL',
    'Quản lý Voucher': 'Voucher Management',
    'Mã voucher': 'Voucher Code',
    'Giá trị giảm': 'Discount Value',
    'Đơn tối thiểu': 'Min Order',
    'Lượt / khách': 'Uses / User',
    'Thời hạn': 'Validity',
    'Kiểu giảm giá': 'Discount Type',
    'Phần trăm (%)': 'Percentage (%)',
    'Số tiền (VNĐ)': 'Fixed Amount (VND)',
    'Đơn tối thiểu (VNĐ)': 'Minimum Order (VND)',
    'Số lượt mỗi khách': 'Max Uses per Customer',
    'Lưu': 'Save',
    'Thao tác': 'Actions',
    'Sửa': 'Edit',
    'Xóa': 'Delete',
    '+ Thêm phim mới': '+ Add New Movie',
    'Thêm phim mới': 'Add New Movie',
    '← Quay lại': '← Back',
    'Tên phim *': 'Movie Title *',
    'Tên phim': 'Movie Title',
    'Đạo diễn': 'Director',
    'Thời lượng *': 'Duration *',
    'Thời lượng': 'Duration',
    'Số phút': 'Minutes',
    'Trạng thái *': 'Status *',
    'Sắp chiếu': 'Coming Soon',
    'Đang chiếu': 'Now Showing',
    'Ngừng chiếu': 'Ended',
    'Giới hạn độ tuổi': 'Age Rating',
    'Không giới hạn': 'Unrated',
    'Ngày khởi chiếu': 'Release Date',
    'Poster - Tải file lên': 'Poster - Upload File',
    'Hoặc nhập URL Poster': 'Or Enter Poster URL',
    'Teaser - Tải file MP4': 'Teaser - Upload MP4',
    'Hoặc nhập URL Teaser': 'Or Enter Teaser URL',
    'Mô tả': 'Description',
    'Thêm phim': 'Add Movie',
    'Sửa phim': 'Edit Movie',
    'Sửa thông tin phim': 'Edit Movie Details',
    'Cập nhật phim': 'Update Movie',
    'Không tìm thấy phim.': 'Movie not found.',
    'Tiền mặt tại két': 'Cash in Drawer',
    'Hành động': 'Actions',
    'Đang tải thông tin chi tiết đơn hàng...': 'Loading order details...',
    'Thông tin chiếu': 'Screening Info',
    'Ghế ngồi đã đặt': 'Booked Seats',
    'Không có ghế': 'No seats',
    'Bắp nước & Combo': 'Snacks & Combos',
    'Không thể tải thông tin chi tiết đơn hàng.': 'Failed to load order details.',

    // Public Customer Pages
    'CineStar - Hệ thống đặt vé xem phim trực tuyến hàng đầu Việt Nam': "CineStar - Vietnam's leading online cinema ticket booking platform",
    'CineStar - Đặt vé xem phim online': 'CineStar - Book Movie Tickets Online',
    'Trải nghiệm điện ảnh': 'Cinema Experience',
    'tuyệt vời nhất': 'Like Never Before',
    'Tuyệt đỉnh': 'At Its Finest',
    'Đặt vé nhanh chóng, chọn ghế yêu thích, thưởng thức bộ phim bom tấn cùng gia đình và bạn bè.': 'Book tickets fast, pick your favorite seats, and enjoy blockbusters with family and friends.',
    'Khám phá thể loại': 'Explore Genres',
    'Tìm bộ phim hoàn hảo theo sở thích của bạn': 'Find the perfect movie for your taste',
    'Xem tất cả thể loại →': 'Browse all genres →',
    'Xem tất cả →': 'View all →',
    'Những bộ phim hot nhất tại rạp': 'Hottest movies in theaters',
    'Kết quả tìm kiếm cho:': 'Search results for:',
    'Xóa tìm kiếm ✕': 'Clear search ✕',
    'Xóa bộ lọc ✕': 'Clear filters ✕',
    'Đang tải phim...': 'Loading movies...',
    'Phim Nổi Bật': 'Featured Movies',
    'Những tác phẩm được yêu thích nhất': 'Most loved titles',
    'Đón chờ những bom tấn sắp ra mắt': 'Stay tuned for upcoming blockbusters',
    'Ưu đãi đặc biệt': 'Special Offers',
    'Sử dụng mã giảm giá khi đặt vé để nhận ưu đãi hấp dẫn!': 'Apply promo codes at checkout for great discounts!',
    'Chưa có phim nào.': 'No movies available.',
    'Lỗi tải dữ liệu phim.': 'Failed to load movies.',
    'Hành Động': 'Action',
    'Hài Hước': 'Comedy',
    'Kinh Dị': 'Horror',
    'Hoạt Hình': 'Animation',
    'Tình Cảm': 'Romance',
    'Khoa Học Viễn Tưởng': 'Sci-Fi',
    'Phiêu Lưu': 'Adventure',
    'Tâm Lý': 'Drama',
    'Giới thiệu - CineStar': 'About - CineStar',
    '50+ Rạp chiếu': '50+ Cinemas',
    'Phủ sóng khắp các tỉnh thành trên toàn quốc': 'Available across cities nationwide',
    'Công nghệ IMAX': 'IMAX Technology',
    'Âm thanh vòm Dolby Atmos, màn hình siêu nét': 'Dolby Atmos surround sound and ultra-sharp screens',
    'Dịch vụ 5 sao': '5-Star Service',
    'Thưởng thức điện ảnh với phong cách thượng lưu': 'Enjoy cinema in luxury comfort',
    'Khám phá Phim theo Thể loại - CineStar': 'Explore Movies by Genre - CineStar',
    'Theo Thể Loại': 'By Genre',
    'Tìm kiếm tên phim...': 'Search movie title...',
    'Mới cập nhật': 'Recently Updated',
    'Đánh giá cao nhất': 'Highest Rated',
    'Thời lượng dài nhất': 'Longest Runtime',
    'Tên phim (A - Z)': 'Movie Title (A - Z)',
    'Bộ lọc đang áp dụng:': 'Active filters:',
    'Không tìm thấy bộ phim nào': 'No movies found',
    'Chi tiết phim - CineStar': 'Movie Details - CineStar',
    'Nội dung phim': 'Synopsis',
    'Chọn ngày để xem lịch chiếu': 'Select a date to view showtimes',
    'Phim sắp chiếu — Lịch chiếu sẽ được cập nhật sớm!': 'Coming soon — Showtimes will be updated shortly!',
    'Đánh giá từ khán giả': 'Audience Reviews',
    'Đang tổng hợp đánh giá...': 'Loading review summary...',
    'Bạn đã đánh giá bộ phim này!': 'You have already reviewed this movie!',
    'Bạn vừa thưởng thức phim xong, cảm nhận của bạn thế nào?': 'How was the movie? Share your thoughts!',
    'Chọn số điểm từ 1 đến 10': 'Select a rating from 1 to 10',
    'Gợi ý cảm xúc nhanh (nhấn để thêm vào bình luận):': 'Quick tags (click to add to comment):',
    'Nhận xét chi tiết': 'Detailed Review',
    'Chia sẻ chi tiết về kịch bản, diễn xuất, âm thanh, bối cảnh phim để cộng đồng cùng thảo luận nhé...': 'Share details about plot, acting, audio, and visuals...',
    'Quyền đánh giá dành cho khán giả đã xem phim': 'Reviews are available for verified ticket holders',
    'Đăng nhập để đánh giá phim': 'Login to rate this movie',
    'Đang tải danh sách nhận xét...': 'Loading reviews...',
    'Chưa có nhận xét nào trong mục này': 'No reviews in this category yet',
    'Hãy là người đầu tiên chia sẻ cảm nhận về bộ phim!': 'Be the first to share your thoughts on this movie!',
    'Khán giả đã mua vé và xem phim hoàn tất tại rạp': 'Verified audience who completed watching at the cinema',
    'Ngày đăng đánh giá': 'Review Date',
    'Không có suất chiếu trong ngày này.': 'No showtimes available on this date.',
    'Đã chiếu': 'Ended',
    'Lịch chiếu phim - CineStar': 'Movie Schedule - CineStar',
    'Lịch chiếu phim': 'Movie Showtimes',
    'Đang tải lịch chiếu...': 'Loading showtimes...',
    'Đặt vé ngay': 'Book Now',
    'Đặt vé ngay!': 'Book Now!',
    'Chi tiết': 'Details',

    // Auth, Profile & Booking
    'Đăng nhập - CineStar': 'Login - CineStar',
    'Chào mừng bạn quay trở lại CineStar': 'Welcome back to CineStar',
    'Ghi nhớ đăng nhập': 'Remember me',
    'Quên mật khẩu?': 'Forgot password?',
    'Chưa có tài khoản?': "Don't have an account?",
    'Đăng ký ngay': 'Register now',
    'Đăng ký - CineStar': 'Register - CineStar',
    'Tạo tài khoản': 'Create Account',
    'Đăng ký để đặt vé và nhận ưu đãi': 'Sign up to book tickets and earn rewards',
    'Nguyễn Văn A': 'Nguyen Van A',
    'Tối thiểu 6 ký tự': 'Minimum 6 characters',
    'Xác nhận mật khẩu': 'Confirm Password',
    'Nhập lại mật khẩu': 'Re-enter password',
    'Quên mật khẩu - CineStar': 'Forgot Password - CineStar',
    'Quên mật khẩu?': 'Forgot Password?',
    'Nhập email của bạn và chúng tôi sẽ gửi liên kết để đặt lại mật khẩu': 'Enter your email and we will send a password reset link',
    'Email đã đăng ký': 'Registered Email',
    '← Quay lại đăng nhập': '← Back to Login',
    'Thanh toán - CineStar': 'Checkout - CineStar',
    'Thanh toán': 'Checkout',
    'Thông tin đặt vé': 'Booking Details',
    'Ngày chiếu': 'Show Date',
    'Ghế đã chọn': 'Selected Seats',
    'Bắp nước:': 'Snacks:',
    'Chi tiết thanh toán': 'Payment Summary',
    'Mã giảm giá': 'Promo Code',
    'Nhập mã voucher (VD: WELCOME50)': 'Enter voucher code (e.g. WELCOME50)',
    'Áp dụng': 'Apply',
    'Phương thức thanh toán': 'Payment Method',
    'Thanh toán qua ví VNPay': 'Pay via VNPay Wallet',
    'Thanh toán qua ví MoMo': 'Pay via MoMo Wallet',
    'Thời gian còn lại': 'Time Remaining',
    'Tổng cộng': 'Total',
    'Tiền vé': 'Tickets',
    'Giảm giá': 'Discount',
    'Tổng tiền vé': 'Ticket Subtotal',
    'Tổng bắp nước': 'Snack Subtotal',
    'Thành tiền': 'Amount Due',
    'Chọn ghế - CineStar': 'Select Seats - CineStar',
    '← Chọn suất chiếu khác': '← Choose another showtime',
    'MÀN HÌNH': 'SCREEN',
    'Sơ đồ ghế': 'Seat Map',
    'Đang tải sơ đồ ghế...': 'Loading seat map...',
    'Chưa chọn': 'None selected',
    'Số lượng': 'Quantity',
    'Chọn bắp nước - CineStar': 'Select Snacks - CineStar',
    'Chọn bắp nước & combo': 'Select Snacks & Combos',
    'Giỏ hàng': 'Cart',
    'Chưa chọn gì': 'Nothing selected',
    'Chi tiết vé - CineStar': 'Ticket Details - CineStar',
    'Đang tải thông tin vé...': 'Loading ticket info...',
    'Mã QR - Xuất trình khi vào rạp': 'QR Code - Present at cinema entrance',
    'Đang tạo QR...': 'Generating QR...',
    'Nhân viên sẽ quét mã QR này để soát vé': 'Staff will scan this QR code for admission',
    'Xem lịch sử vé': 'View Ticket History',
    'Về trang chủ': 'Back to Home',
    'Giờ chiếu': 'Showtime',
    'Ghế': 'Seats',
    'Lỗi tạo QR Code': 'Failed to generate QR Code',
    'Đổi Mật Khẩu - CineStar': 'Change Password - CineStar',
    'Đổi Mật Khẩu': 'Change Password',
    'Mật khẩu hiện tại': 'Current Password',
    'Mật khẩu mới': 'New Password',
    'Xác nhận mật khẩu mới': 'Confirm New Password',
    'Cài đặt Tài khoản - CineStar': 'Account Settings - CineStar',
    'Cài đặt Tài khoản': 'Account Settings',
    'Lịch sử vé - CineStar': 'Ticket History - CineStar',
    'Lịch sử đặt vé': 'Booking History',
    'Đang tải vé…': 'Loading tickets…',
    'Thử lại': 'Retry',
    'Thanh toán ngay': 'Pay Now',
    'Thời gian giữ:': 'Hold time:',
    'Ghế của bạn đang được giữ trong phiên đặt vé này. Bấm vào các bước phía trên để quay lại đổi ghế hoặc bắp nước.': 'Your seats are being held for this session. Click the steps above to modify seats or snacks.',
    'HOẶC': 'OR',
    'Dữ liệu QR': 'QR Data',
    'Nhập dữ liệu QR': 'Enter QR data',
    'Quét': 'Scan',
    'Tiếp tục soát vé khác': 'Scan Another Ticket',
    'Không thể tải thư viện quét Camera, vui lòng dùng ô nhập tay để test.': 'Camera scanner could not load, please use manual QR input.',
    'Thành công:': 'Success:',
    'Cảnh báo:': 'Warning:',
    'Thông tin vé:': 'Ticket Info:',
    'Trạng thái vé': 'Ticket Status',

    // Standalone units & Dashboard / Analytics dynamic strings
    'vé': 'tickets',
    'suất': 'shows',
    'đơn': 'orders',
    'đ': 'VND',
    'Ngày': 'Date',
    'Tháng': 'Month',
    'Năm': 'Year',
    'Chọn năm': 'Select Year',
    '— đơn vé': '— ticket orders',
    '— đơn chuyển khoản': '— online orders',
    '— đơn tiền mặt': '— cash orders',
    'Nhiều lượt đặt nhất': 'Most Booked',
    'Ít lượt đặt nhất': 'Least Booked',
    'Theo ngày trong tuần': 'Daily this Week',
    'Theo ngày trong tháng': 'Daily this Month',
    'Theo tháng trong năm': 'Monthly this Year',
    'Toàn thời gian': 'All Time',
    'Theo chu kỳ': 'By Period',
    'Doanh thu trong tuần': 'Weekly Revenue',
    'Doanh thu khoảng ngày': 'Range Revenue',
    'Tổng doanh thu toàn rạp': 'Total Cinema Revenue',
    'Không tải được thống kê.': 'Failed to load analytics.',
    'Không tải được thống kê phim.': 'Failed to load movie bookings.',
    'Không có phim trong kỳ lọc này.': 'No movies in this period.',
    'Không tải được thêm phim.': 'Failed to load more movies.',
    'Không có dữ liệu': 'No data',
    'Chưa có giao dịch': 'No transactions yet',

    // Admin Catalog, Movies, Shows, Orders
    'P - Phổ biến': 'P - General',
    'K - Dưới 13 tuổi': 'K - Under 13',
    'T13 - Từ 13 tuổi': 'T13 - 13+',
    'T16 - Từ 16 tuổi': 'T16 - 16+',
    'T18 - Từ 18 tuổi': 'T18 - 18+',
    'C - Không phổ biến': 'C - Restricted',
    'VD: Hành động, Tâm lý': 'e.g. Action, Drama',
    'Đang xử lý...': 'Processing...',
    'Thành công': 'Success',
    'Thêm phim mới thành công!': 'Movie added successfully!',
    'Thêm phim thành công!': 'Movie added!',
    'Không thể tạo phim mới.': 'Could not create movie.',
    'Lỗi thêm phim': 'Add Movie Error',
    'Lỗi hệ thống': 'System Error',
    'Có lỗi xảy ra khi kết nối máy chủ.': 'Server connection error occurred.',
    'Có lỗi xảy ra': 'An error occurred',
    'Poster hiện tại': 'Current Poster',
    'Cập nhật phim thành công!': 'Movie updated successfully!',
    'Cập nhật thành công!': 'Updated!',
    'Không thể cập nhật phim.': 'Could not update movie.',
    'Lỗi cập nhật': 'Update Error',
    '🎥 Quản lý Phim': '🎥 Movie Management',
    '🚀 Kéo dữ liệu từ TMDB': '🚀 Fetch from TMDB',
    '⏳ Đang kéo dữ liệu...': '⏳ Fetching data...',
    'Bạn có chắc muốn xóa phim này?': 'Are you sure you want to delete this movie?',
    'Bạn có chắc muốn kéo dữ liệu phim từ TMDB về không? Quá trình này có thể mất vài giây.': 'Fetch movies from TMDB? This may take a few seconds.',
    'Đã kéo phim từ TMDB về hệ thống thành công!': 'Movies fetched from TMDB successfully!',
    'Thành công! Đã kéo phim từ TMDB về hệ thống.': 'Success! Movies fetched from TMDB.',
    'Lỗi kéo dữ liệu TMDB': 'TMDB Fetch Error',
    'Không thể kéo dữ liệu từ TMDB': 'Could not fetch from TMDB',
    'Lỗi:': 'Error:',
    'Không thể kéo dữ liệu': 'Fetch Failed',
    'Lỗi kết nối': 'Connection Error',
    'Không thể kết nối đến máy chủ TMDB/API!': 'Could not connect to TMDB/API server!',
    'Lỗi kết nối Server!': 'Server connection failed!',
    'Tra cứu đơn theo khách hàng, tên phim, trạng thái hoặc khoảng thời gian và xuất báo cáo Excel': 'Search orders by customer, movie, status, or date range and export to Excel',
    'Hoàn tất': 'Completed',
    'tiền mặt': 'cash',
    'Khách vãng lai': 'Walk-in Guest',
    'Quay lại danh sách suất chiếu': 'Back to Showtimes',
    'Quy định:': 'Rule:',
    '🎬 Chọn phim chiếu': '🎬 Select Movie',
    '-- Chọn phim --': '-- Select Movie --',
    '🚪 Phòng chiếu': '🚪 Screen',
    '📆 Ngày chiếu': '📆 Show Date',
    '⏰ Giờ bắt đầu chiếu': '⏰ Start Time',
    '💵 Giá vé cơ bản (VNĐ)': '💵 Base Ticket Price (VND)',
    'Giá gợi ý:': 'Suggested price:',
    'Tạo suất chiếu': 'Create Showtime',
    'Thông tin phim': 'Movie Info',
    '📋 Lịch chiếu trong ngày': '📋 Daily Screen Schedule',
    'Đang tải lịch phòng...': 'Loading screen schedule...',
    'Đang tải lịch phòng…': 'Loading screen schedule…',
    'Không thể tải lịch phòng.': 'Failed to load screen schedule.',
    'Sau mỗi suất chiếu, phòng cần': 'After each screening, the room needs',
    '30 phút dọn rạp': '30 min cleanup',
    '. Suất tiếp theo chỉ được đặt sau khi dọn rạp xong.': '. The next showtime can only start after cleanup.',
    'Đang kiểm tra & tạo suất...': 'Validating & creating showtime...',
    'Thêm Suất Chiếu Mới': 'Add New Showtime',
    '📅 Thêm Suất Chiếu Mới': '📅 Add New Showtime',
    'Chưa phân loại': 'Uncategorized',
    'Chọn phòng và ngày để xem lịch.': 'Select a screen and date to view schedule.',
    'Phòng không có suất chiếu hoặc thời gian dọn rạp trong ngày này.': 'No showtimes or cleanup blocks on this date.',
    'Dọn rạp đến': 'Cleanup until',
    'Không thể đặt vào giờ này': 'Unavailable time slot',
    'Khung giờ hợp lệ': 'Valid time slot',
    'Chiếu đến': 'Screens until',
    'Vui lòng điền đầy đủ các trường bắt buộc!': 'Please fill in all required fields!',
    'Lỗi tạo suất chiếu!': 'Failed to create showtime!',
    'Tạo suất chiếu thành công!': 'Showtime created successfully!',
    'Không thể tạo suất chiếu': 'Could not create showtime',
    'Phân trang suất chiếu': 'Showtime pagination',
    'Bạn có chắc muốn xóa suất chiếu này?': 'Are you sure you want to delete this showtime?',
    'Không thể xóa suất chiếu. Vui lòng thử lại.': 'Could not delete showtime. Please try again.',
    'Không thể xóa suất chiếu': 'Delete Showtime Error',
    'Đang bán': 'Available',
    'Ngừng bán': 'Unavailable',
    'Đã tắt': 'Disabled',
    'Chưa có thời hạn': 'No expiry',
    'Chưa đến hạn': 'Upcoming',
    'Hết hạn': 'Expired',
    'Đang hiệu lực': 'Active',
    'Kích hoạt': 'Activate',
    'bắp nước': 'snack items',
    'Thêm': 'Add',
    'Đã xóa': 'Deleted',
    'Không thể xóa': 'Could not delete',
    'Chưa có': 'None',
    'Đang lưu…': 'Saving…',
    'Không thể thực hiện thao tác. Vui lòng thử lại.': 'Action failed. Please try again.',

    // Staff & Admin Staff Extra Strings
    'Reset mật khẩu về mặc định?': 'Reset password to default?',
    'Reset mật khẩu': 'Reset Password',
    'Reset thất bại.': 'Reset failed.',
    'Reset mật khẩu thành công': 'Password reset successfully',
    'Đã cập nhật trạng thái.': 'Status updated.',
    'Thêm tài khoản nhân viên mới': 'Add New Staff Account',
    'Không thể lưu thông tin.': 'Could not save details.',
    'Đang tải dữ liệu lịch trực 3 ca...': 'Loading 3-shift schedule...',
    'Quy định: Chấm công bắt đầu': 'Rule: Check in',
    'trước 10 phút': '10 min before',
    'khi vào ca &': 'shift start &',
    'không kết thúc ca sớm': 'do not check out early',
    'hơn giờ quy định.': 'before shift end.',
    'Không thể tải dữ liệu lịch tổng.': 'Failed to load master schedule.',
    'Chưa chấm công': 'Not checked in',
    'Thao tác thất bại.': 'Action failed.',
    'Đã xác nhận cảnh báo.': 'Alerts acknowledged.',
    'Quy định bắt buộc: Phải chấm công bắt đầu ca': 'Mandatory rule: You must check in',
    'khi ca bắt đầu và': 'before the shift starts and',
    'không được chấm công kết thúc ca sớm hơn': 'must not check out earlier than',
    'giờ kết thúc ca.': 'the scheduled end time.',
    'Mô phỏng & Bắt đầu ca': 'Simulate & Check In',
    'Mô phỏng & Kết thúc ca': 'Simulate & Check Out',
    'Trình duyệt không hỗ trợ webcam API': 'Browser does not support webcam API',
    'Camera minh họa đang hoạt động': 'Demo camera is active',
    'Hình ảnh xem trước, không xác thực khuôn mặt': 'Camera preview only, no face verification',
    'Chế độ mô phỏng Camera AI': 'Camera AI Simulation Mode',
    'Đang chạy mô phỏng...': 'Running simulation...',
    'Đang minh họa thao tác chấm công': 'Demonstrating attendance',
    'Hoàn tất mô phỏng, không xác thực khuôn mặt': 'Simulation complete, no face verification',
    'Đã hoàn tất mô phỏng': 'Simulation completed',
    'Không thể chấm công': 'Check-in Failed',
    'Chấm công · Đi trễ': 'Checked In · Late',
    'Chấm công thành công': 'Check-in Successful',
    'Chưa tới giờ kết thúc ca!': 'Shift has not ended yet!',
    'Vẫn kết thúc ca': 'Check Out Anyway',
    'Ở lại làm tiếp': 'Keep Working',
    'Không thể kết thúc ca': 'Check-out Failed',
    'Kết thúc ca sớm · Vi phạm': 'Early Check-out · Violation',
    'Hoàn thành ca làm!': 'Shift Completed!',
    '• Dữ liệu chỉ hiển thị bảng chấm công và lương của riêng nhân viên này': "• Only displays attendance and payroll data for this staff member",
    'Công thức tính lương:': 'Payroll Formula:',
    'Lương ca = Số giờ công chuẩn tối đa 8h × Đơn giá/giờ + Phụ cấp 30% cho Ca 1 khung 22:00–06:00 − Khấu trừ 50.000đ nếu vi phạm chấm công. Các ca vắng mặt không tính lương.': 'Shift Pay = Billable Hours (max 8h) × Hourly Rate + 30% Bonus for Shift 1 (22:00–06:00) − 50,000 VND penalty per violation. Absent shifts are unpaid.',
    '• Chỉ hiển thị những ca làm bạn phụ trách': '• Only displays shifts assigned to you',
    'Đi chấm công': 'Go to Attendance',
    'Theo dõi doanh thu thực tế, tiền mặt, chuyển khoản và chi tiết vé để kiểm và bàn giao ca': 'Track shift revenue, cash, bank transfers, and ticket details for handover',
    'Số đơn tiền mặt:': 'Cash orders:',
    'Số đơn chuyển khoản:': 'Online orders:',
    'Tuần này': 'This Week',
    'Khoảng ngày': 'Date Range',
    'Món ăn': 'Snacks',
    'Khi quét bằng tệp, dùng ảnh PNG tải từ trang chi tiết vé. Nếu dùng ảnh chụp, hãy cắt quanh mã QR và giữ nguyên viền trắng.': 'When scanning a file, use the PNG downloaded from the ticket details page with white margins intact.',
    'Thiếu dữ liệu': 'Missing Data',
    'Vui lòng nhập hoặc quét mã QR hợp lệ': 'Please enter or scan a valid QR code',
    'Vui lòng nhập mã QR': 'Please enter QR data',
    'Không thể kết nối đến máy chủ để xác thực vé!': 'Cannot connect to server to verify ticket!',

    // Customer, Booking, Reviews, Profile Extra Strings
    'Về Chúng Tôi': 'About Us',
    'Chúng tôi không chỉ là rạp chiếu phim, chúng tôi là nơi kết nối những tâm hồn yêu nghệ thuật, nơi chia sẻ cảm xúc và lan tỏa niềm đam mê điện ảnh.': 'More than a cinema, we connect art lovers to share emotions and celebrate the magic of film.',
    'Rạp chiếu phim CineStar': 'CineStar Cinema',
    'Gửi liên kết khôi phục': 'Send Reset Link',
    'Đây là giao diện demo, chức năng gửi email chưa được kết nối!': 'This is a demo view; email sending is not connected yet!',
    'Đã có tài khoản?': 'Already have an account?',
    'Đang cập nhật': 'Updating',
    'Không áp dụng được voucher.': 'Voucher could not be applied.',
    'Không thể lưu phương thức thanh toán': 'Could not save payment method',
    'Vui lòng thử lại.': 'Please try again.',
    'Thanh toán không thành công': 'Payment Failed',
    'Không thể xử lý giao dịch. Vui lòng kiểm tra lại!': 'Unable to process payment. Please check and try again!',
    'Trống': 'Available',
    'Đang chọn': 'Selected',
    'Đã đặt': 'Booked',
    'Giữ ghế & Tiếp tục': 'Hold Seats & Continue',
    'Một ghế bạn đang xem vừa được người khác giữ. Sơ đồ ghế đã được cập nhật!': 'A seat you were viewing was just held by someone else. The seat map has been refreshed!',
    'Giới hạn số ghế': 'Seat Limit',
    'Bạn chỉ được chọn tối đa 8 ghế trong một lần đặt vé!': 'You can select up to 8 seats per booking!',
    'Không thể giữ ghế.': 'Could not hold seats.',
    'Không thể giữ ghế': 'Seat Hold Failed',
    'Hủy ghế đang giữ để chọn lại?': 'Release held seats and reselect?',
    'Các ghế bạn đang giữ ở đơn hàng này sẽ được giải phóng ngay lập tức để bạn có thể chọn lại ghế mới.': 'Your held seats will be released immediately so you can select new seats.',
    'Giải phóng ghế': 'Release Seats',
    'Tiếp tục giữ': 'Keep Holding',
    'Bạn có chắc muốn hủy ghế đang giữ để chọn lại?': 'Release held seats and choose again?',
    'Đang giải phóng ghế...': 'Releasing seats...',
    'Đã giải phóng ghế. Bạn có thể chọn ghế mới!': 'Seats released. You can select new seats!',
    'Sơ đồ rạp': 'Seat Map',
    'Voucher & Cổng TT': 'Voucher & Payment',
    'Vé xem phim & QR': 'Tickets & QR',
    'Đổi lại ghế đã chọn?': 'Change selected seats?',
    'Xác nhận đổi ghế': 'Confirm Seat Change',
    'Giữ ghế hiện tại': 'Keep Current Seats',
    'Bạn có chắc muốn chọn lại ghế cho suất chiếu này? Ghế đang giữ hiện tại sẽ được giải phóng.': 'Reselect seats for this showtime? Your currently held seats will be released.',
    'Đang giải phóng ghế để bạn chọn lại...': 'Releasing seats...',
    'Mã giữ chỗ:': 'Hold Code:',
    'Tiếp tục thanh toán →': 'Proceed to Payment →',
    'Không tải được danh sách bắp nước.': 'Failed to load snacks.',
    'Lỗi chọn bắp nước': 'Snack Selection Error',
    'Không thể lưu danh sách bắp nước. Vui lòng thử lại!': 'Could not save snacks. Please try again!',
    'Tải ảnh QR (PNG)': 'Download QR (PNG)',
    'Đặt vé thành công!': 'Booking Successful!',
    'Cảm ơn bạn đã sử dụng dịch vụ CineStar': 'Thank you for choosing CineStar',
    'Đơn hàng đã hủy': 'Order Cancelled',
    'Đơn hàng này đã bị hủy bỏ do hết thời gian thanh toán hoặc được người dùng hủy.': 'This order was cancelled due to timeout or user action.',
    'Đơn hàng của bạn đang chờ thanh toán.': 'Your order is awaiting payment.',
    'Chi tiết đơn hàng': 'Order Details',
    'Thông tin chi tiết về đơn đặt vé của bạn.': 'Detailed summary of your ticket booking.',
    'Thông tin suất chiếu của vé cũ chưa được khôi phục.': 'Showtime details for this legacy ticket were not archived.',
    'Phiên giữ ghế đã kết thúc. Vui lòng chọn lại ghế.': 'Seat hold expired. Please select seats again.',
    'Đang kiểm tra trạng thái giữ ghế. Vui lòng chờ.': 'Checking seat hold status. Please wait.',
    'Tạm khóa đặt vé': 'Booking Paused',
    'Hết thời gian giữ ghế': 'Seat Hold Expired',
    'Phiên giữ ghế đã kết thúc': 'Seat Hold Ended',
    'Chọn lại ghế': 'Reselect Seats',
    'Hủy bỏ': 'Cancel',
    'Xin chào! Tôi là Trợ lý AI của rạp. Tôi có thể giúp gì cho bạn?': 'Hello! I am your AI Cinema Assistant. How can I help you?',
    'Xin lỗi, đã có lỗi xảy ra khi kết nối. Vui lòng thử lại sau.': 'Sorry, a connection error occurred. Please try again later.',
    'Khám Phá Kho Phim Đặc Sắc': 'Explore Curated Movies',
    'Vũ Trụ Điện Ảnh': 'Cinema Universe',
    'Khám phá hàng chục tác phẩm điện ảnh đỉnh cao kết nối trực tiếp từ cơ sở dữ liệu rạp CineStar. Thưởng thức trọn vẹn từng khoảnh khắc theo sở thích của bạn!': 'Browse dozens of top films directly from the CineStar catalog and enjoy every moment tailored to your taste!',
    'Chọn thể loại yêu thích': 'Choose Favorite Genres',
    'Tìm kiếm:': 'Search:',
    'Không có bộ phim nào phù hợp với bộ lọc hiện tại. Vui lòng thử chọn thể loại khác hoặc xóa bớt tiêu chí lọc.': 'No movies match your current filters. Try another genre or clear filters.',
    'Khám phá tất cả phim': 'Explore All Movies',
    'Có lỗi khi kết nối dữ liệu. Vui lòng thử lại sau!': 'Failed to load data. Please try again later!',
    'Chi tiết →': 'Details →',
    '← Trước': '← Prev',
    'Viễn Tưởng': 'Sci-Fi',
    'Giật Gân': 'Thriller',
    'Tội Phạm': 'Crime',
    'Gia Đình': 'Family',
    'Võ Thuật': 'Martial Arts',
    'Điện ảnh': 'Cinema',
    'Đang lọc theo thể loại:': 'Filtering by genre:',
    'Phim sắp chiếu': 'Coming Soon',
    'Đặt vé': 'Book',
    '← Quay lại trang chủ': '← Back to Home',
    'Cập Nhật Mật Khẩu': 'Update Password',
    'Thay đổi ảnh đại diện': 'Change Avatar',
    'Lưu Thay Đổi': 'Save Changes',
    'Bạn chưa có vé nào.': 'You have no tickets yet.',
    'Đã sử dụng': 'Used',
    'Đã hoàn tiền': 'Refunded',
    'Chưa xác định ngày đặt': 'Unknown booking date',
    'Hôm qua': 'Yesterday',
    'Ngày mai': 'Tomorrow',
    '7 ngày qua': 'Last 7 days',
    '30 ngày qua': 'Last 30 days',
    'Vé cũ chưa rõ suất chiếu': 'Legacy ticket showtime unavailable',
    'Không tải được lịch sử đặt vé.': 'Failed to load ticket history.',
    'Không tải được vé. Vui lòng thử lại.': 'Failed to load tickets. Please try again.',
    'Đánh giá minh bạch & khách quan từ cộng đồng khán giả đã thưởng thức phim tại rạp': 'Verified reviews from moviegoers who watched this film in theaters',
    'Cảm ơn bạn đã chia sẻ nhận xét thực tế. Đánh giá của bạn giúp cộng đồng yêu phim có góc nhìn chân thực và đa chiều nhất.': 'Thank you for sharing your honest review with the community.',
    'Viết đánh giá của bạn': 'Write Your Review',
    'Đã xem tại rạp': 'Watched in Theater',
    'Chấm điểm bộ phim:': 'Rate this movie:',
    '+ Diễn xuất đỉnh': '+ Top Acting',
    '+ Kịch bản lôi cuốn': '+ Gripping Plot',
    '+ Kỹ xảo mãn nhãn': '+ Stunning VFX',
    '+ Âm thanh sống động': '+ Immersive Sound',
    '+ Rất đáng tiền vé': '+ Worth Every Penny',
    '+ Cái kết bất ngờ': '+ Plot Twist',
    'Gửi đánh giá ngay': 'Submit Review',
    'Để đảm bảo tính khách quan và uy tín, chỉ những khán giả đã đặt vé và xem phim xong tại cụm rạp CineStar mới có quyền tham gia chấm điểm và viết bình luận.': 'Only verified CineStar ticket holders who completed watching the movie can rate and comment.',
    'Đặt vé xem phim ngay': 'Book Tickets Now',
    'Vui lòng đăng nhập để kiểm tra quyền đánh giá (Dành riêng cho khán giả đã mua vé và xem phim xong tại rạp).': 'Please sign in to verify your review eligibility (for verified ticket holders).',
    'Đăng nhập tài khoản': 'Login to Account',
    'Khen nhiều (9 - 10 sao)': 'Praise (9 - 10 stars)',
    'Khá & Hay (7 - 8 sao)': 'Good (7 - 8 stars)',
    'Ý kiến khác (≤ 6 sao)': 'Mixed (≤ 6 stars)',
    'Xem thêm nhận xét khác ↓': 'Load more reviews ↓',
    'Điểm nhấn cảm xúc': 'Highlights',
    '#DiễnXuấtĐỉnh': '#TopActing',
    '#KịchBảnLôiCuốn': '#GrippingPlot',
    '#KỹXảoMãnNhãn': '#StunningVFX',
    '#ÂmThanhSốngĐộng': '#ImmersiveSound',
    '#RấtĐángTiềnVé': '#WorthEveryPenny',
    '#CáiKếtBấtNgờ': '#PlotTwist',
    '10/10 - Cực phẩm! Tuyệt tác điện ảnh không thể bỏ lỡ': '10/10 - Masterpiece! A must-watch cinematic gem',
    '9/10 - Phim rất hay! Đáng từng phút giây thưởng thức': '9/10 - Amazing! Worth every single minute',
    '8/10 - Hay! Kịch bản cuốn hút, diễn xuất rất tốt': '8/10 - Great! Engaging story and strong acting',
    '7/10 - Khá ổn! Phim giải trí tốt cho ngày cuối tuần': '7/10 - Solid! Enjoyable weekend watch',
    '6/10 - Tạm được! Xem ổn nhưng chưa có nhiều đột phá': '6/10 - Decent, though lacks major surprises',
    '5/10 - Bình thường, không có nhiều ấn tượng đọng lại': '5/10 - Average, nothing too memorable',
    '4/10 - Dưới kỳ vọng, kịch bản còn nhiều điểm trừ': '4/10 - Below expectations, several flaws',
    '3/10 - Khá chán, nhịp phim buồn ngủ và rời rạc': '3/10 - Slow and disjointed pacing',
    '2/10 - Tệ, không khuyến khích bạn bè đi xem': '2/10 - Poor, would not recommend',
    '1/10 - Rất tệ, trải nghiệm xem phim thất vọng': '1/10 - Very poor experience',
    'Chưa chọn điểm': 'No rating selected',
    'Vui lòng chọn số điểm đánh giá (từ 1 đến 10)!': 'Please select a score from 1 to 10!',
    'Chúc mừng bạn đã gửi đánh giá thành công! Nhận xét đã được ghi nhận vào hệ thống.': 'Review submitted successfully! Thank you for sharing.',
    'Chúc mừng bạn đã gửi đánh giá thành công!': 'Review submitted successfully!',
    'Không thể gửi đánh giá': 'Review Failed',
    'Không thể gửi đánh giá. Vui lòng kiểm tra lại!': 'Could not submit review. Please try again!',
    'Có lỗi kết nối máy chủ. Vui lòng thử lại sau!': 'Server connection error. Please try again later!',
    'Gần đây': 'Recently',
    'Vừa xem hôm nay': 'Watched today',
    'Khán giả CineStar': 'CineStar Viewer',
    'Cực phẩm': 'Masterpiece',
    'Ổn': 'Okay',
    'Bình thường': 'Average',

    // ASCII Vietnamese labels, rooms, showtimes, seat types, snacks
    'Phim': 'Movie',
    'Ca 1': 'Shift 1',
    'Ca 2': 'Shift 2',
    'Ca 3': 'Shift 3',
    'Ca 1 · 22h-6h': 'Shift 1 · 22h-6h',
    'Ca 2 · 6h-14h': 'Shift 2 · 6h-14h',
    'Ca 3 · 14h-22h': 'Shift 3 · 14h-22h',
    'Ca 1 · 22:00 - 06:00': 'Shift 1 · 22:00 - 06:00',
    'Ca 2 · 06:00 - 14:00': 'Shift 2 · 06:00 - 14:00',
    'Ca 3 · 14:00 - 22:00': 'Shift 3 · 14:00 - 22:00',
    'Lọc theo giờ bắt đầu của suất chiếu, giờ Việt Nam': 'Filter by showtime start (Vietnam time)',
    'Ghế thường': 'Standard Seat',
    'Ghế VIP': 'VIP Seat',
    'Ghế đôi': 'Couple Seat',
    'Ghế Sweetbox': 'Sweetbox Seat',
    'Combo 1 - Bắp nhỏ + Nước ngọt': 'Combo 1 - Small Popcorn + Soft Drink',
    'Combo 2 - Bắp lớn + 2 Nước ngọt': 'Combo 2 - Large Popcorn + 2 Soft Drinks',
    'Combo Đôi - 2 Bắp lớn + 2 Nước ngọt': 'Couple Combo - 2 Large Popcorns + 2 Soft Drinks',
    'Combo Gia đình - 2 Bắp lớn + 4 Nước ngọt': 'Family Combo - 2 Large Popcorns + 4 Soft Drinks',
    'Nước ngọt Pepsi': 'Pepsi Soft Drink',
    'Nước suối Aquafina': 'Aquafina Bottled Water',
    'Bắp rang caramel': 'Caramel Popcorn',
    'Hotdog xúc xích': 'Sausage Hotdog',
    '+ Thêm bắp nước': '+ Add Snack',
    'Thêm bắp nước': 'Add Snack',
    'Sửa bắp nước': 'Edit Snack',
    'Chưa có bắp nước': 'No snacks yet',
    'Đã xóa bắp nước': 'Snack deleted',
    'Không thể xóa bắp nước': 'Could not delete snack',
    '+ Thêm voucher': '+ Add Voucher',
    'Thêm voucher': 'Add Voucher',
    'Sửa voucher': 'Edit Voucher',
    'Chưa có voucher': 'No vouchers yet',
    'Đã xóa voucher': 'Voucher deleted',
    'Không thể xóa voucher': 'Could not delete voucher',
};

const VI_BOUNDARY_L = '(?<![A-Za-z\\u00C0-\\u024F\\u1E00-\\u1EFF])';
const VI_BOUNDARY_R = '(?![A-Za-z\\u00C0-\\u024F\\u1E00-\\u1EFF])';

function rb(pattern) {
    return new RegExp(`${VI_BOUNDARY_L}(?:${pattern})${VI_BOUNDARY_R}`, 'g');
}

const PATTERN_RULES = [
    // Currency first BEFORE đ -> d stripping
    [/(\d[\d.,]*)\s*đ(?![A-Za-z\u00C0-\u024F\u1E00-\u1EFF])/g, '$1 VND'],
    [/VNĐ/g, 'VND'],

    // Dashboard Analytics dynamic strings
    [/^Doanh thu ngày\s+(.+)$/i, 'Revenue for $1'],
    [/^Doanh thu tháng\s+(.+)$/i, 'Revenue for $1'],
    [/^Doanh thu năm\s+(.+)$/i, 'Revenue for $1'],
    [/^Tuần:\s*(\S+)\s+đến\s+(\S+)$/i, 'Week: $1 to $2'],
    [/^(\d{4}-\d{2}-\d{2})\s+đến\s+(\d{4}-\d{2}-\d{2})$/, '$1 to $2'],
    [/^Ngày\s+(\d{4}-\d{2}-\d{2}|\d{2}\/\d{2}\/\d{4})$/, 'Date $1'],
    [/^Tháng\s+(\d{4}-\d{2}|\d{1,2})$/, 'Month $1'],
    [/^Năm\s+(\d{4})$/, 'Year $1'],
    [/^(\d[\d.,]*)\s+vé bán thành công$/, '$1 tickets sold'],
    [/^(\d[\d.,]*)\s+giao dịch online$/, '$1 online transactions'],
    [/^(\d[\d.,]*)\s+giao dịch tiền mặt$/, '$1 cash transactions'],
    [/^(\d[\d.,]*)\s+lượt$/, '$1 bookings'],

    // Orders, Movies, Shows, Reviews dynamic strings
    [/^Tìm thấy\s+(\d+)\s+đơn hàng$/, 'Found $1 orders'],
    [/^Tìm thấy\s+(\d+)\s+bộ phim$/, 'Found $1 movies'],
    [/Hiển thị\s+(\d+)\s*-\s*(\d+)\s+trên tổng số\s+(\d+)\s+đơn hàng\s*\(Trang\s+(\d+)\/(\d+)\)/g, 'Showing $1 - $2 of $3 orders (Page $4/$5)'],
    [/^Cập nhật nhân viên\s+(.+)$/, 'Edit Staff $1'],
    [/^Mật khẩu tài khoản\s+(.+?)\s+sẽ được đặt lại thành mã nhân viên:\s*(.+)$/, 'Password for $1 will be reset to staff ID: $2'],
    [/^Lịch làm việc theo tuần\s*—$/, 'Weekly Schedule —'],
    [/^•\s*Vị trí chính:/, '• Primary Position:'],
    [/^(\d+)\s+\/\s+1000 ký tự$/, '$1 / 1000 chars'],
    [/^(\d+)\/10 Điểm$/, '$1/10 Rating'],
    [/^(\d+(?:\.\d+)?)% \(mô phỏng\)$/, '$1% (simulated)'],
    [/^Camera AI \(mô phỏng\) · (\d+(?:\.\d+)?)% \(mô phỏng\)$/, 'Camera AI (simulation) · $1% (simulated)'],
    [/^Xem hôm nay \((\d+) giờ trước\)$/, 'Watched today ($1h ago)'],
    [/^Xem (\d+) ngày trước$/, 'Watched $1 days ago'],
    [/^Xem (\d+) tuần trước$/, 'Watched $1 weeks ago'],
    [/^Xem (\d+) tháng trước$/, 'Watched $1 months ago'],
    [/^Xem (\d+) năm trước$/, 'Watched $1 years ago'],
    [/Trùng với suất «([^»]+)» hoặc thời gian dọn rạp đến\s+(\S+)/g, 'Conflicts with "$1" or cleanup until $2'],
    [/^Bạn muốn xóa “(.+)” khỏi danh sách\?$/, 'Delete "$1" from the list?'],
    [/^Lỗi tải danh sách phim:\s*(.+)$/, 'Failed to load movies: $1'],
    [/^Lỗi tải đánh giá:\s*(.+)$/, 'Failed to load reviews: $1'],
    [/^Tất cả \((\d+)\)$/, 'All ($1)'],

    // Pagination & counters
    [/Hiển thị\s+(\d+)\s*[–-]\s*(\d+)\s*\/\s*(\d+)\s*bản ghi\s*•\s*Trang\s+(\d+)\s*\/\s*(\d+)/g, 'Showing $1–$2 / $3 records • Page $4/$5'],
    [/Trang\s+(\d+)\s*\/\s*(\d+)\s*•\s*Tổng\s+(\d+)\s+lượt chấm công/g, 'Page $1 / $2 • Total $3 records'],
    [/(\d+)\s+dòng/g, '$1 rows'],
    [/(\d+)\s+cảnh báo chưa xác nhận/g, '$1 unacknowledged alerts'],
    [/(\d+)\s+cảnh báo/g, '$1 alerts'],
    [/(\d+)\s+ca\s*•\s*([\d.]+h)/g, '$1 shifts • $2'],
    [/(\d+)\s*Ca\/Ngày/g, '$1 Shifts/Day'],
    [/(\d+)\s+tiếng\/ca/g, '$1h/shift'],
    [/(\d+)\s+tiếng/g, '$1 hours'],
    [/(\d+)\s+NV\/ca/g, '$1 staff/shift'],
    [/(\d+)\s+phút/g, '$1 min'],
    [/(\d+)\s+vé/g, '$1 tickets'],
    [/(\d+)\s+suất chiếu/g, '$1 showtimes'],
    [/(\d+)\s+suất/g, '$1 shows'],
    [/(\d+)\s+đơn/g, '$1 orders'],
    [/\bTrang\s+(\d+)\s*\/\s*(\d+)/g, 'Page $1/$2'],
    [/Phòng\s+(\d+)/g, 'Screen $1'],
    [rb('Combo Đôi'), 'Couple Combo'],
    [rb('Combo Gia đình'), 'Family Combo'],
    [rb('Bắp rang caramel'), 'Caramel Popcorn'],
    [rb('Bắp rang bơ'), 'Butter Popcorn'],
    [rb('Bắp rang phô mai'), 'Cheese Popcorn'],
    [rb('Bắp lớn'), 'Large Popcorn'],
    [rb('Bắp nhỏ'), 'Small Popcorn'],
    [rb('Nước ngọt'), 'Soft Drink'],
    [rb('Nước suối'), 'Bottled Water'],
    [rb('Hotdog xúc xích'), 'Sausage Hotdog'],
    [rb('Xúc xích'), 'Sausage'],
    [rb('xúc xích'), 'Sausage'],
    [rb('Ghế thường'), 'Standard Seat'],
    [rb('Ghế VIP'), 'VIP Seat'],
    [rb('Ghế đôi'), 'Couple Seat'],
    [/Hữu ích\s*\(/g, 'Helpful ('],

    // Dates, weeks, days of week, shifts
    [/Tuần\s+(\d{2}\/\d{2}\/\d{4})\s*[–-]\s*(\d{2}\/\d{2}\/\d{4})/g, 'Week $1 – $2'],
    [rb('Thứ 2'), 'Monday'],
    [rb('Thứ 3'), 'Tuesday'],
    [rb('Thứ 4'), 'Wednesday'],
    [rb('Thứ 5'), 'Thursday'],
    [rb('Thứ 6'), 'Friday'],
    [rb('Thứ 7'), 'Saturday'],
    [rb('Chủ nhật'), 'Sunday'],
    [/Ca\s+(\d+)/g, 'Shift $1'],

    // Violations & attendance sentences
    [/Chấm công vào ca trễ (\d+) min — quy định phải trước 10 min khi ca bắt đầu/g, 'Checked in $1 min late — required 10 min before shift'],
    [/Chấm công vào ca trễ (\d+) phút — quy định phải trước 10 phút khi ca bắt đầu/g, 'Checked in $1 min late — required 10 min before shift'],
    [/Chấm công ra ca sớm (\d+) (?:min|phút) trước giờ kết thúc ca/g, 'Checked out $1 min early before shift end'],
    [/Không đi làm \/ Vắng mặt không chấm công/g, 'No show / Absent without check-in'],

    // Voucher promo cards
    [/Giảm\s+(\S+)\s+đơn từ\s+(\S+)/g, 'Save $1 on orders from $2'],

    // Prefixes & labels preserving following Vietnamese proper names for accent-stripping
    [/Tổng hợp chấm công & Lương\s*—/g, 'Attendance & Payroll Summary —'],
    [/Chấm công ca làm\s*—/g, 'Shift Attendance —'],
    [/Lịch làm việc của\s+/g, 'Work Schedule of '],
    [/Xin chào,/g, 'Welcome,'],
    [/Khách hàng\s+/g, 'Customer '],
    [/Nhân viên:/g, 'Staff:'],
    [/Admin:/g, 'Admin:'],
    [/Xem theo NV:/g, 'Preview Staff:'],
    [/Mã NV:/g, 'Staff ID:'],
    [/Vị trí:/g, 'Position:'],
    [/Vai trò:/g, 'Role:'],
    [/Ngày:/g, 'Date:'],
    [/Giờ:/g, 'Time:'],
    [/Trạng thái:/g, 'Status:'],
    [/Vào đúng giờ trước:/g, 'Check in before:'],
    [/Hạn vào đúng giờ:/g, 'On-time deadline:'],
    [/Kết thúc ca từ:/g, 'Check out from:'],
    [/Vào ca:/g, 'Check-in:'],
    [/Ra ca:/g, 'Check-out:'],
    [/Giờ công:/g, 'Worked hours:'],
    [/Vào\s+(\d{2}:\d{2})/g, 'In $1'],
    [/Ra\s+(\d{2}:\d{2})/g, 'Out $1'],
    [/Vào:/g, 'In:'],
    [/Ra:/g, 'Out:'],
    [/Địa chỉ:/g, 'Address:'],
    [/SĐT:/g, 'Phone:'],
    [/Ngày sinh:/g, 'DOB:'],
    [/Giới tính:/g, 'Gender:'],
    [/Ngày vào làm:/g, 'Hired:'],
    [/Đơn giá:/g, 'Rate:'],
    [/Suất chiếu:/g, 'Showtime:'],
    [/Phòng chiếu\s+/g, 'Screen '],
    [/Phòng:/g, 'Screen:'],
    [/Ghế:/g, 'Seats:'],
    [/Đặt ngày/g, 'Booked on'],

    // Roles & positions
    [rb('SOÁT VÉ'), 'TICKET CHECKER'],
    [rb('THU NGÂN'), 'CASHIER'],
    [rb('Soát vé'), 'Ticket Checker'],
    [rb('Thu ngân'), 'Cashier'],
    [rb('Chuẩn bị snacks'), 'Snack Prep'],
    [rb('Quản trị viên'), 'Administrator'],
    [rb('Việt Nam'), 'Vietnam'],

    // Status badges inside dynamic rows
    [rb('Đúng giờ'), 'On Time'],
    [rb('Hoàn thành'), 'Completed'],
    [rb('Đi trễ & Về sớm'), 'Late & Early'],
    [rb('Đi trễ'), 'Late'],
    [rb('Về sớm'), 'Early Leave'],
    [rb('Vắng mặt'), 'Absent'],
    [rb('Chờ chấm công'), 'Pending'],
    [rb('Sắp làm'), 'Upcoming'],
    [rb('Đang trực'), 'On Duty'],
    [rb('Đã xong'), 'Completed'],
    [rb('Sắp diễn ra'), 'Upcoming'],
    [rb('Đang làm việc'), 'Active'],
    [rb('Đã hoàn thành'), 'Completed'],
    [rb('Tạm ngưng'), 'Cancelled'],
    [rb('Thủ công'), 'Manual'],
    [rb('Đang hoạt động'), 'Active'],
    [rb('Đang khóa'), 'Locked'],
    [rb('Nghỉ việc'), 'Inactive'],
    [rb('Reset MK'), 'Reset Password'],
    [rb('Khóa TK'), 'Lock Account'],
    [rb('Mở khóa'), 'Unlock'],
    [rb('Sửa'), 'Edit'],
    [rb('Xóa'), 'Delete'],
    [rb('Chưa vào'), 'Not checked in'],
    [rb('Bắp nước'), 'Snacks'],
    [rb('Đạo diễn Hollywood'), 'Hollywood Director'],
    [rb('Hành động, Phiêu lưu'), 'Action, Adventure'],
    [/Đang cập nhật\.\.\./g, 'Updating...'],
    [/(\d[\d.,]*)\s+đánh giá/g, '$1 reviews'],
    [rb('Đặt vé'), 'Book'],
    [rb('Doanh thu ca'), 'Shift Revenue'],
    [rb('Thời lượng:'), 'Duration:'],
    [rb('Thể loại:'), 'Genres:'],
    [rb('Độ tuổi:'), 'Rating:'],
];

function rebuildSortedTitles() {
    sortedMovieTitles = Array.from(dynamicMap.keys())
        .filter(k => k.length >= 4 && k.length <= 140 && HAS_VI_CHAR.test(k))
        .sort((a, b) => b.length - a.length);
}

export function registerI18nTranslations(pairs) {
    if (!pairs || typeof pairs !== 'object') return;
    let changed = false;

    for (const [rawVi, rawEn] of Object.entries(pairs)) {
        const vi = typeof rawVi === 'string' ? rawVi.trim().replace(/\s+/g, ' ') : '';
        const en = typeof rawEn === 'string' ? rawEn.trim().replace(/\s+/g, ' ') : '';
        if (!vi || !en || vi === en) continue;
        if (dynamicMap.get(vi) !== en) {
            dynamicMap.set(vi, en);
            changed = true;
        }
    }

    if (changed) {
        rebuildSortedTitles();
        if (currentLocale === 'en' && typeof document !== 'undefined' && document.body) {
            applySubtree(document.body, 'en');
        }
    }
}

export function stripVietnameseDiacritics(text) {
    if (!text) return text;
    return text
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D');
}

export function translateViToEn(raw) {
    if (typeof raw !== 'string' || !raw) return raw;

    const lead = raw.match(/^\s*/)?.[0] ?? '';
    const trail = raw.match(/\s*$/)?.[0] ?? '';
    const trimmed = raw.trim();
    if (!trimmed) return raw;

    const collapsed = trimmed.replace(/\s+/g, ' ');
    if (dynamicMap.has(collapsed)) {
        return lead + dynamicMap.get(collapsed) + trail;
    }

    if (Object.prototype.hasOwnProperty.call(EXACT_MAP, collapsed)) {
        return lead + EXACT_MAP[collapsed] + trail;
    }

    if (!HAS_VI_CHAR.test(trimmed) && !HAS_ASCII_VI_PATTERN.test(trimmed)) {
        return raw;
    }

    let translated = collapsed;
    if (sortedMovieTitles.length > 0) {
        for (const viTitle of sortedMovieTitles) {
            if (translated.includes(viTitle)) {
                translated = translated.split(viTitle).join(dynamicMap.get(viTitle));
            }
        }
    }

    for (const [regex, replacement] of PATTERN_RULES) {
        translated = translated.replace(regex, replacement);
    }

    translated = stripVietnameseDiacritics(translated);
    return lead + translated + trail;
}

function shouldSkipElement(el) {
    if (!el || el.nodeType !== 1) return true;
    if (SKIP_TAGS.has(el.tagName)) return true;
    if (el.closest('[data-no-translate]')) return true;
    return false;
}

function applyToTextNode(node, locale) {
    const parent = node.parentElement;
    if (!parent || shouldSkipElement(parent)) return;

    if (!originalTextMap.has(node)) {
        originalTextMap.set(node, node.nodeValue ?? '');
    }

    const original = originalTextMap.get(node);
    const target = locale === 'en' ? translateViToEn(original) : original;
    if (node.nodeValue !== target) {
        node.nodeValue = target;
    }
}

function applyToAttributes(el, locale) {
    if (shouldSkipElement(el)) return;
    let saved = originalAttrMap.get(el);
    if (!saved) {
        saved = {};
        originalAttrMap.set(el, saved);
    }

    for (const attr of ATTR_NAMES) {
        if (!el.hasAttribute(attr)) continue;
        if (!(attr in saved)) {
            saved[attr] = el.getAttribute(attr);
        }
        const orig = saved[attr];
        const target = locale === 'en' ? translateViToEn(orig) : orig;
        if (el.getAttribute(attr) !== target) {
            el.setAttribute(attr, target);
        }
    }
}

function applySubtree(root, locale) {
    if (!root) return;
    isApplying = true;
    try {
        if (root.nodeType === 3) {
            applyToTextNode(root, locale);
            return;
        }
        if (root.nodeType === 1) {
            applyToAttributes(root, locale);
            root.querySelectorAll('[placeholder],[title],[aria-label]').forEach(el => applyToAttributes(el, locale));
        }

        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walker.nextNode())) {
            applyToTextNode(node, locale);
        }

        if (originalDocTitle === null) {
            originalDocTitle = document.title;
        }
        const targetTitle = locale === 'en' ? translateViToEn(originalDocTitle) : originalDocTitle;
        if (document.title !== targetTitle) {
            document.title = targetTitle;
        }
    } finally {
        isApplying = false;
    }
}

function startObservers() {
    if (!observer && document.body) {
        observer = new MutationObserver(mutations => {
            if (isApplying || currentLocale !== 'en') return;
            for (const m of mutations) {
                if (m.type === 'characterData') {
                    const target = m.target;
                    if (!shouldSkipElement(target.parentElement)) {
                        originalTextMap.set(target, target.nodeValue ?? '');
                        applySubtree(target, 'en');
                    }
                } else if (m.type === 'childList') {
                    for (const added of m.addedNodes) {
                        applySubtree(added, 'en');
                    }
                }
            }
        });
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true,
        });
    }

    const titleEl = document.querySelector('title');
    if (!titleObserver && titleEl) {
        titleObserver = new MutationObserver(() => {
            if (isApplying || currentLocale !== 'en') return;
            originalDocTitle = document.title;
            isApplying = true;
            try {
                document.title = translateViToEn(originalDocTitle);
            } finally {
                isApplying = false;
            }
        });
        titleObserver.observe(titleEl, { childList: true });
    }
}

function stopObservers() {
    if (observer) {
        observer.disconnect();
        observer = null;
    }
    if (titleObserver) {
        titleObserver.disconnect();
        titleObserver = null;
    }
}

function updateToggleButtons(locale) {
    document.querySelectorAll('[data-lang-btn]').forEach(btn => {
        const isActive = btn.getAttribute('data-lang-btn') === locale;
        btn.classList.toggle('bg-[var(--color-cinema-primary)]', isActive);
        btn.classList.toggle('text-white', isActive);
        btn.classList.toggle('shadow-sm', isActive);
        btn.classList.toggle('text-[var(--color-cinema-text-muted)]', !isActive);
        btn.classList.toggle('hover:text-cinema-text', !isActive);
        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
}

function readCookie(name) {
    if (typeof document === 'undefined') return null;
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : null;
}

function writePersistence(locale) {
    try {
        localStorage.setItem(STORAGE_KEY, locale);
    } catch {}
    document.cookie = `${COOKIE_NAME}=${locale}; path=/; max-age=${60 * 60 * 24 * 365}; SameSite=Lax`;
}

function syncServerLocale(locale) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!token) return;

    fetch('/locale', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify({ locale }),
        keepalive: true,
    }).catch(() => {});
}

export function initializeI18n() {
    if (typeof document === 'undefined') return;

    if (window.CINEMA_MOVIE_TITLES && typeof window.CINEMA_MOVIE_TITLES === 'object') {
        registerI18nTranslations(window.CINEMA_MOVIE_TITLES);
    }

    const cookieLocale = readCookie(COOKIE_NAME);
    const htmlLocale = document.documentElement.lang;
    let storedLocale = null;
    try {
        storedLocale = localStorage.getItem(STORAGE_KEY);
    } catch {}

    const initial = [cookieLocale, htmlLocale, storedLocale].find(l => l === 'vi' || l === 'en') || 'vi';
    currentLocale = initial;
    document.documentElement.lang = initial;
    updateToggleButtons(initial);

    if (initial === 'en') {
        applySubtree(document.body, 'en');
        startObservers();
    }

    window.__cinemaI18nReady = true;
    window.stripVietnameseDiacritics = stripVietnameseDiacritics;
    window.translateViToEn = translateViToEn;
    window.registerI18nTranslations = registerI18nTranslations;
    window.getAppLocale = () => currentLocale;

    window.setAppLocale = function (lang) {
        const nextLocale = lang === 'en' ? 'en' : 'vi';
        currentLocale = nextLocale;
        document.documentElement.lang = nextLocale;
        writePersistence(nextLocale);
        updateToggleButtons(nextLocale);

        if (nextLocale === 'en') {
            applySubtree(document.body, 'en');
            startObservers();
        } else {
            stopObservers();
            applySubtree(document.body, 'vi');
        }

        window.dispatchEvent(new CustomEvent('cinema:locale-change', { detail: { locale: nextLocale } }));
        syncServerLocale(nextLocale);
    };
}
