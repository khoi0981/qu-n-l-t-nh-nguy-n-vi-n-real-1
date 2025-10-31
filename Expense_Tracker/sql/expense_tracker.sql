-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th10 30, 2025 lúc 05:06 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `expense_tracker`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `checkins`
--

CREATE TABLE `checkins` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `checkin_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `date` datetime NOT NULL,
  `location` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `points` int(11) NOT NULL DEFAULT 0,
  `participants` int(11) NOT NULL DEFAULT 0,
  `current_participants` int(11) NOT NULL DEFAULT 0,
  `end_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `image`, `date`, `location`, `created_at`, `points`, `participants`, `current_participants`, `end_date`) VALUES
(13, 'Workshop “Sáng tạo từ rác thải tái chế”', 'Chương trình hợp tác giữa Tổ chức Green Youth Việt Nam và Khoa Môi Trường – Đại học Công nghiệp TP.HCM.\r\n\r\nNội dung: Hướng dẫn sinh viên tái chế các vật liệu đã qua sử dụng (chai nhựa, giấy báo, vải vụn, nắp lon...) để tạo ra các sản phẩm hữu ích như chậu cây mini, đèn handmade, mô hình trang trí.\r\n\r\nLịch trình:\r\n\r\n08:00: Đón khách và ổn định chỗ ngồi.\r\n\r\n08:15–09:00: Giới thiệu về tái chế và phân loại rác.\r\n\r\n09:00–11:00: Thực hành sáng tạo sản phẩm tái chế.\r\n\r\n11:00–11:45: Trưng bày, bình chọn sản phẩm đẹp.\r\n\r\n11:45–12:00: Tổng kết, trao quà lưu niệm.\r\n\r\nLiên hệ: Phạm Minh Thư – 0906 222 789 – Email: minhthu@greenyouth.org', '/Expense_tracker-main/Expense_Tracker/uploads/events/4dc97a00a4f1ca3ded8f.jpeg', '2025-10-21 16:06:00', 'Trường Đại học Công nghiệp TP.HCM, 12 Nguyễn Văn Bảo, Gò Vấp, TP.HCM', '2025-10-21 09:07:35', 60000, 50, 0, NULL),
(14, 'Chương trình “Trồng cây gây rừng – Hành trình xanh”', 'Địa chỉ: Khu rừng ngập mặn Cần Giờ, TP.HCM\r\n\r\nChi tiết hoạt động:\r\nChương trình hợp tác giữa Trung tâm Bảo tồn Thiên nhiên Nam Bộ và Trường Đại học Khoa học Tự nhiên TP.HCM.\r\n\r\nNội dung: Trồng cây đước con tại khu vực rừng ngập mặn; thu gom rác thải nhựa trôi dạt.\r\n\r\nLịch trình:\r\n\r\n06:00: Xuất phát tại trường.\r\n\r\n08:00–11:30: Trồng cây và thu gom rác.\r\n\r\n11:30–13:00: Ăn trưa, nghỉ ngơi.\r\n\r\n13:00–16:30: Chăm sóc cây, tổng kết hoạt động.\r\n\r\n17:00: Trở về TP.HCM.\r\n\r\nLiên hệ: Lê Văn Nam – 0913 345 987 – Email: namlv@naturecenter.org', '/Expense_tracker-main/Expense_Tracker/uploads/events/dc1e64371c90b048012a.jpeg', '2025-10-24 19:32:00', 'Khu rừng ngập mặn Cần Giờ, TP.HCM', '2025-10-24 12:33:23', 90000, 30, 0, NULL),
(39, 'Hoạt động: “Ngày Chủ Nhật Xanh”', 'Hợp tác: Trường Đại học Khoa học Tự nhiên TP.HCM & CLB GreenEarth\r\nNội dung:\r\nTổ chức dọn rác tại các khu vực công viên trung tâm và kênh rạch lân cận.\r\nMục tiêu là lan tỏa ý thức bảo vệ môi trường, hạn chế rác thải nhựa và giữ gìn cảnh quan xanh – sạch – đẹp.\r\nNgười tham gia được hướng dẫn phân loại rác và nhận điểm thưởng GreenStep sau hoạt động.\r\nThời gian – Địa điểm:\r\nCông viên Tao Đàn, Quận 1, TP.HCM\r\nLiên hệ: greenstep.volunteer@gmail.com', NULL, '2025-10-27 19:13:00', 'Công viên Tao Đàn, Quận 1, TP.HCM', '2025-10-27 12:12:28', 30000, 30, 0, '2025-10-27 19:15:00'),
(40, 'Hoạt động: “Trồng Cây Xanh – Ươm Mầm Tương Lai”', 'Hợp tác: Trường THPT Nguyễn Thượng Hiền & Phòng Tài nguyên Môi trường Quận Tân Bình\r\nNội dung:\r\nHuy động tình nguyện viên tham gia trồng 300 cây xanh quanh khu vực trường học và tuyến đường chính của quận.\r\nHoạt động kết hợp tuyên truyền cho học sinh về vai trò cây xanh và giảm hiệu ứng nhà kính.\r\nThời gian – Địa điểm:\r\n⏰ Thứ Bảy, 30/11/2025 – 8h00 đến 15h00\r\n📍 Trường THPT Nguyễn Thượng Hiền, Quận Tân Bình\r\nLiên hệ: greenstep.project@gmail.com', '/Expense_tracker-main/Expense_Tracker/uploads/events/8cf897421df7b4a9ae65.jpeg', '2025-10-27 19:14:00', 'Trường THPT Nguyễn Thượng Hiền, Quận Tân Bình', '2025-10-27 12:14:09', 20000, 20, 0, '2025-10-27 19:15:00'),
(41, 'Hoạt động: “Vì Một Biển Xanh – Không Rác Thải”', 'Hợp tác: CLB Thanh niên Xanh & Tổ chức OceanCare Vietnam\r\nNội dung:\r\nChiến dịch làm sạch bãi biển Cần Giờ, thu gom rác nhựa và chai lọ trôi dạt.\r\nTình nguyện viên được trang bị dụng cụ bảo hộ và được đào tạo ngắn về phân loại rác tái chế.\r\nNgười tham gia sẽ được nhận chứng nhận “Green Hero” trên trang cá nhân GreenStep.\r\nThời gian – Địa điểm:\r\n⏰ Chủ nhật, 8/12/2025 – 6h00 đến 12h00\r\n📍 Bãi biển Cần Giờ, TP.HCM\r\nLiên hệ: ocean.greenstep@gmail.com', '/Expense_tracker-main/Expense_Tracker/uploads/events/80976234e5e319d56db0.jpeg', '2025-10-27 19:15:00', 'Bãi biển Cần Giờ, TP.HCM', '2025-10-27 12:15:45', 20000, 30, 0, '2025-10-31 19:15:00'),
(42, 'Hoạt động: “Tuyên Truyền Xanh Trong Trường Học”', 'Hợp tác: Đại học Sư phạm TP.HCM & Sở Giáo dục Thành phố\r\nNội dung:\r\nCác tình nguyện viên sẽ tổ chức chuỗi buổi truyền thông và trò chơi tương tác về phân loại rác, tiết kiệm năng lượng, và bảo vệ nguồn nước.\r\nMục tiêu là tạo ý thức bảo vệ môi trường cho học sinh tiểu học.\r\nĐiểm cộng thưởng cho mỗi buổi tham gia.\r\nThời gian – Địa điểm:\r\n⏰ Từ 15/12 đến 20/12/2025\r\n📍 Trường Tiểu học Lê Quý Đôn, Quận 3\r\nLiên hệ: edu.greenstep@gmail.com', '/Expense_tracker-main/Expense_Tracker/uploads/events/f69deedfd0d63cbfc8a1.jpeg', '2025-10-31 19:16:00', 'Trường Tiểu học Lê Quý Đôn, Quận 3', '2025-10-27 12:16:42', 20000, 30, 0, '2025-11-09 19:16:00'),
(43, 'Chương trình “Trồng cây gây rừng – Hành trình xanh', 'Địa chỉ: Khu rừng ngập mặn Cần Giờ, TP.HCM\r\n\r\nChi tiết hoạt động:\r\nChương trình hợp tác giữa Trung tâm Bảo tồn Thiên nhiên Nam Bộ và Trường Đại học Khoa học Tự nhiên TP.HCM.\r\n\r\nNội dung: Trồng cây đước con tại khu vực rừng ngập mặn; thu gom rác thải nhựa trôi dạt.\r\n\r\nLịch trình:\r\n\r\n06:00: Xuất phát tại trường.\r\n\r\n08:00–11:30: Trồng cây và thu gom rác.\r\n\r\n11:30–13:00: Ăn trưa, nghỉ ngơi.\r\n\r\n13:00–16:30: Chăm sóc cây, tổng kết hoạt động.\r\n\r\n17:00: Trở về TP.HCM.\r\n\r\nLiên hệ: Lê Văn Nam – 0913 345 987 – Email: namlv@naturecenter.org', '/Expense_tracker-main/Expense_Tracker/uploads/events/9bb431704751daaab8e9.jpeg', '2025-10-28 23:36:00', 'Khu rừng ngập mặn Cần Giờ, TP.HCM', '2025-10-28 16:35:21', 40000, 20, 0, '2025-11-08 23:34:00');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `news`
--

CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `author_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `views` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `news`
--

INSERT INTO `news` (`id`, `title`, `content`, `author_id`, `created_at`, `image`, `slug`, `views`) VALUES
(4, 'Chung tay “Xanh hóa học đường” cùng Trường THPT Lê Quý Đôn', 'Ngày 18/10 vừa qua, nhóm tình nguyện GreenYouth đã phối hợp cùng Trường THPT Lê Quý Đôn (TP. Hồ Chí Minh) tổ chức hoạt động “Xanh hóa học đường” với hơn 50 học sinh tham gia.\r\nBuổi hoạt động bao gồm việc trồng cây xanh, dọn rác khuôn viên trường và tuyên truyền phân loại rác tại nguồn.\r\nKhông khí buổi sáng tràn đầy năng lượng và tiếng cười, các bạn học sinh cùng nhau trang trí lại khu vườn sinh học, biến những khoảng sân trống thành góc xanh thân thiện.\r\n<img src=\"/Expense_tracker-main/Expense_Tracker/uploads/news/n_68f752da4bea2.jpg\" alt=\"Ảnh bài viết\" style=\"max-width:100%;\">\r\nĐại diện nhà trường chia sẻ: “Các em đã mang đến tinh thần rất tích cực, truyền cảm hứng cho toàn thể học sinh.”\r\nĐây là hoạt động nằm trong chuỗi “Trường học xanh – Hành tinh xanh” do nhóm GreenYouth phát động, hướng đến nâng cao ý thức bảo vệ môi trường trong học đường.', NULL, '2025-10-21 04:31:22', 'n_68f752ea62f51.jpg', 'chung-tay-xanh-h-a-h-c-ng-c-ng-tr-ng-thpt-l-qu-n-1761039082', 2),
(5, 'Thu gom rác nhựa – Lan tỏa yêu thương cùng SaigonXanh', 'Vừa qua, chương trình “Thu gom nhựa đổi quà” do nhóm SaigonXanh thực hiện đã thu hút hơn 200 người dân tham gia tại công viên 23/9.\r\nMọi người có thể mang chai nhựa, lon, hộp đến đổi lấy cây xanh nhỏ, túi vải hoặc huy hiệu “Tình nguyện xanh”.\r\nHoạt động không chỉ giảm thiểu rác thải nhựa mà còn tạo cơ hội để người dân trải nghiệm niềm vui “cho đi – nhận lại” vì môi trường.\r\n<img src=\"/Expense_tracker-main/Expense_Tracker/uploads/news/n_68f7532e25e81.jpg\" alt=\"Ảnh bài viết\" style=\"max-width:100%;\">\r\nChị Hương, một người dân tham gia chia sẻ: “Tôi thấy vui khi chai nhựa mình bỏ đi giờ có thể góp phần trồng thêm cây mới.”\r\nĐây là bước khởi đầu cho chiến dịch “Một chai nhựa – Một mầm cây” dự kiến sẽ được mở rộng tại nhiều quận trong thành phố.', NULL, '2025-10-21 04:32:37', 'n_68f75335ef8a8.jpg', 'thu-gom-r-c-nh-a-lan-t-a-y-u-th-ng-c-ng-saigonxanh-1761039157', 30),
(18, 'chương trình “Đi bộ vì Trái Đất” tại TP.HCM', 'Ngày 10/11/2025, dự án GreenStep chính thức phát động chiến dịch “Đi bộ vì Trái Đất” tại Công viên Gia Định, thu hút hơn 700 người tham gia.\r\nNgười tham dự không chỉ đi bộ mà còn tham gia thu gom rác dọc tuyến đường, trồng cây xanh và tuyên truyền giảm rác nhựa.\r\nHoạt động này giúp cộng đồng hiểu rằng bảo vệ môi trường có thể bắt đầu từ những hành động nhỏ nhất trong cuộc sống hàng ngày.\r\nTất cả người tham gia được cấp chứng nhận điện tử và tích điểm đổi quà xanh trên hệ thống GreenStep.\r\n<img src=\"/Expense_tracker-main/Expense_Tracker/uploads/news/n_6901161765355.jpg\" alt=\"Ảnh bài viết\" style=\"max-width:100%;\">', NULL, '2025-10-28 13:14:32', 'n_690116184e197.jpg', 'ch-ng-tr-nh-i-b-v-tr-i-t-t-i-tp-hcm-1761678872', 0),
(19, 'QR Check-in cho tình nguyện viên', 'Để tối ưu quy trình quản lý, GreenStep chính thức ra mắt tính năng QR Check-in cho các hoạt động tình nguyện.\r\nTình nguyện viên chỉ cần mở ứng dụng hoặc website GreenStep, quét mã QR tại khu vực sự kiện để xác nhận tham gia.\r\nCông nghệ này giúp ban tổ chức ghi nhận nhanh chóng, chính xác và minh bạch số lượng người tham dự.\r\nTheo thống kê, hơn 85% người dùng đánh giá tính năng này giúp họ tham gia sự kiện thuận tiện hơn.\r\n<img src=\"/Expense_tracker-main/Expense_Tracker/uploads/news/n_69011636cd682.jpg\" alt=\"Ảnh bài viết\" style=\"max-width:100%;\">', NULL, '2025-10-28 13:15:03', 'n_69011637d4134.jpg', 'qr-check-in-cho-t-nh-nguy-n-vi-n-1761678903', 0),
(20, 'Dự án “Tái chế cùng GreenStep” thu gom hơn 2 tấn rác nhựa', 'Với sự phối hợp của CLB Môi Trường Xanh, GreenStep triển khai chiến dịch “Tái chế cùng GreenStep” tại 5 trường đại học.\r\nNgười tham gia mang chai nhựa, hộp sữa, ống hút đến điểm thu gom và đổi lấy điểm thưởng hoặc sản phẩm tái chế.\r\nTrong vòng 30 ngày, chương trình đã thu được hơn 2 tấn rác nhựa và quyên góp 300 sản phẩm tái chế cho cộng đồng.\r\nĐây là minh chứng rõ ràng cho tác động tích cực của mô hình “đổi rác lấy quà” mà GreenStep đang phát triển.', NULL, '2025-10-28 13:15:25', 'n_6901164d71d36.jpg', 'd-n-t-i-ch-c-ng-greenstep-thu-gom-h-n-2-t-n-r-c-nh-a-1761678925', 0),
(21, 'GreenStep hợp tác cùng doanh nghiệp xanh trồng 1.000 cây tại Bình Dương', 'Ngày 18/12/2025, dự án GreenStep phối hợp cùng Công ty TNHH EcoGrow tổ chức trồng cây tại khu công nghiệp Sóng Thần (Bình Dương).\r\nHơn 200 tình nguyện viên đã góp sức trong quá trình trồng và chăm sóc cây, với tổng số 1.000 cây keo và sao đen được gieo xuống.\r\nHoạt động này nhằm tạo mảng xanh bền vững và giảm thiểu khí thải trong khu vực công nghiệp.\r\nGreenStep cam kết tiếp tục mở rộng mô hình “Doanh nghiệp đồng hành vì môi trường” trong năm 2026.\r\n<img src=\"/Expense_tracker-main/Expense_Tracker/uploads/news/n_690116649ab20.jpg\" alt=\"Ảnh bài viết\" style=\"max-width:100%;\">', NULL, '2025-10-28 13:15:49', 'n_690116654f2ac.jpg', 'greenstep-h-p-t-c-c-ng-doanh-nghi-p-xanh-tr-ng-1-000-c-y-t-i-b-nh-d-ng-1761678949', 5);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(60) DEFAULT 'info',
  `message` text NOT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `message`, `meta`, `is_read`, `created_at`) VALUES
(6, 1, 'info', 'Yêu cầu đổi quà đã bị hủy và điểm được hoàn lại.', '{\"redemption_id\": \"97\"}', 0, '2025-10-30 10:17:06');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `redemptions`
--

CREATE TABLE `redemptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reward_title` varchar(255) NOT NULL,
  `reward_cost` int(11) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `redemption_code` varchar(20) NOT NULL,
  `rewards_id` int(10) UNSIGNED DEFAULT NULL,
  `admin_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `redemptions`
--

INSERT INTO `redemptions` (`id`, `user_id`, `reward_title`, `reward_cost`, `image`, `status`, `created_at`, `redemption_code`, `rewards_id`, `admin_notes`) VALUES
(86, 1, 'Bình nước tái sử dụng “GreenWave”', 40000, 'r_68f7d95e67229.jpg', 'pending', '2025-10-27 22:47:29', 'R8629849', NULL, NULL),
(87, 1, 'Bình nước tái sử dụng “GreenWave”', 40000, 'r_68f7d95e67229.jpg', 'rejected', '2025-10-27 22:47:36', 'R5848763', NULL, ''),
(91, 1, 'Bộ sản phẩm “Zero-Waste Starter Kit”', 70000, 'r_68f7d994581ca.jpg', 'approved', '2025-10-27 23:00:41', 'R6225205', NULL, ''),
(96, 1, 'Bộ sản phẩm “Zero-Waste Starter Kit”', 70000, 'r_68f7d994581ca.jpg', 'pending', '2025-10-29 14:55:49', 'R3439697', NULL, NULL),
(97, 1, 'Bộ sản phẩm “Zero-Waste Starter Kit”', 70000, '/Expense_tracker-main/Expense_Tracker/uploads/rewards/r_68f7d994581ca.jpg', '', '2025-10-29 14:55:56', 'R5805545', NULL, NULL),
(98, 1, 'Bình nước tái sử dụng “GreenWave”', 40000, 'r_68f7d95e67229.jpg', '', '2025-10-29 14:56:00', 'R8418758', NULL, NULL),
(99, 1, 'Bình nước tái sử dụng “GreenWave”', 40000, 'r_68f7d95e67229.jpg', '', '2025-10-30 10:16:39', 'R8717965', NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `registrations`
--

CREATE TABLE `registrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `age` int(11) NOT NULL,
  `address` text DEFAULT NULL,
  `id_image` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `health_status` varchar(100) DEFAULT NULL,
  `class_name` varchar(100) DEFAULT NULL,
  `school` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `registrations`
--

INSERT INTO `registrations` (`id`, `event_id`, `user_id`, `name`, `age`, `address`, `id_image`, `created_at`, `health_status`, `class_name`, `school`) VALUES
(38, 39, 1, 'dangkhoi', 32, 'sdsf', '/Expense_tracker-main/Expense_Tracker/uploads/registrations/9cb7ebd842c04481.png', '2025-10-27 19:12:53', NULL, NULL, NULL),
(40, 43, 1, 'khoi', 32, 'dsfsdf', '/Expense_tracker-main/Expense_Tracker/uploads/registrations/04bdd6ffea92f941.jpeg', '2025-10-28 23:35:53', 'Tốt', 'lt601', 'ctech');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `rewards`
--

CREATE TABLE `rewards` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `points_cost` int(11) NOT NULL DEFAULT 0,
  `stock` int(11) NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `points` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `rewards`
--

INSERT INTO `rewards` (`id`, `name`, `description`, `points_cost`, `stock`, `image`, `created_at`, `points`) VALUES
(4, 'Bình nước tái sử dụng “GreenWave”', 'Bình nước làm từ thép không gỉ, thiết kế tối giản, thay thế 300 chai nhựa mỗi năm. Màu xanh lá, dung tích 500ml, nắp kín chống rò rỉ.', 0, 0, 'r_68f7d95e67229.jpg', '2025-10-21 21:05:02', 40000),
(5, 'Bộ sản phẩm “Zero-Waste Starter Kit”', 'Gồm: bàn chải tre, ống hút inox, túi vải nhỏ đựng đồ, khay ăn inox. Hộp quà được làm từ vật liệu tái chế. Sản phẩm phù hợp để bắt đầu lối sống “không rác”.', 0, 0, 'r_68f7d994581ca.jpg', '2025-10-21 21:05:56', 70000);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) DEFAULT 'user',
  `is_admin` tinyint(1) DEFAULT 0,
  `avatar` varchar(255) DEFAULT NULL,
  `points` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `is_admin`, `avatar`, `points`, `created_at`) VALUES
(1, 'khoi', 'dangkhoi0981@gmail.com', '$2y$10$yf1tA8TWKLUh/faLXVm1D.9rNjqs9nMWp1y5nN.V8j2H98W/WiVDe', 'admin', 0, '/Expense_tracker-main/Expense_Tracker/uploads/users/6bd5216379420e91.jpeg', 279999, '2025-10-17 19:44:15'),
(4, 'peter', 'dfgdfgdgd@gmail.com', '$2y$10$ob5QoRT56c5eYviX7l08ZOHw6Yvqz5pVEWSQuqTd3fKjVyMxMbR6K', 'user', 0, '/Expense_tracker-main/Expense_Tracker/uploads/users/7efa15ff6951793b.jpeg', 0, '2025-10-25 06:22:29');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `user_activities`
--

CREATE TABLE `user_activities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_title` varchar(255) NOT NULL,
  `activity_description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `volunteers`
--

CREATE TABLE `volunteers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `age` int(11) NOT NULL,
  `address` varchar(255) NOT NULL,
  `points` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` varchar(32) NOT NULL DEFAULT 'volunteer',
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `volunteers`
--

INSERT INTO `volunteers` (`id`, `name`, `age`, `address`, `points`, `created_at`, `role`, `user_id`, `email`) VALUES
(1, 'dangkhoi', 0, '', 0, '2025-10-17 12:44:15', 'admin', NULL, 'dangkhoi0981@gmail.com'),
(2, 'khoisigma', 0, '', 0, '2025-10-18 06:01:50', 'volunteer', NULL, 'dangkhoi098@gmail.com');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `volunteer_register`
--

CREATE TABLE `volunteer_register` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `id_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `volunteer_register`
--

INSERT INTO `volunteer_register` (`id`, `name`, `age`, `address`, `id_image`, `created_at`) VALUES
(2, 'khoi', 23, 'hà noi', 'uploads/68f0d5dd909b3_Ảnh chụp màn hình (71).png', '2025-10-16 11:24:13'),
(3, 'khoi', 23, 'hà noi', 'uploads/68f0d5e0b0b60_Ảnh chụp màn hình (71).png', '2025-10-16 11:24:16'),
(4, 'khoi', 23, 'hà noi', 'uploads/68f0d5e15dddc_Ảnh chụp màn hình (71).png', '2025-10-16 11:24:17'),
(5, 'khoi', 23, 'hà noi', 'uploads/68f0d5e34de52_Ảnh chụp màn hình (71).png', '2025-10-16 11:24:19'),
(6, 'khoi', 24, 'hà noi', 'uploads/68f0d77a76462_Ảnh chụp màn hình (71).png', '2025-10-16 11:31:06');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `checkins`
--
ALTER TABLE `checkins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_checkin` (`event_id`,`user_id`),
  ADD KEY `fk_checkins_user` (`user_id`);

--
-- Chỉ mục cho bảng `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_news_author` (`author_id`);

--
-- Chỉ mục cho bảng `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notifications_user` (`user_id`);

--
-- Chỉ mục cho bảng `redemptions`
--
ALTER TABLE `redemptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_redemption_code` (`redemption_code`),
  ADD KEY `fk_redemptions_user` (`user_id`),
  ADD KEY `fk_redemptions_reward` (`rewards_id`);

--
-- Chỉ mục cho bảng `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_registrations_user` (`user_id`),
  ADD KEY `fk_registrations_event` (`event_id`);

--
-- Chỉ mục cho bảng `rewards`
--
ALTER TABLE `rewards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_rewards_name` (`name`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Chỉ mục cho bảng `user_activities`
--
ALTER TABLE `user_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_activities_user` (`user_id`);

--
-- Chỉ mục cho bảng `volunteers`
--
ALTER TABLE `volunteers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Chỉ mục cho bảng `volunteer_register`
--
ALTER TABLE `volunteer_register`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `checkins`
--
ALTER TABLE `checkins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT cho bảng `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT cho bảng `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `redemptions`
--
ALTER TABLE `redemptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT cho bảng `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT cho bảng `rewards`
--
ALTER TABLE `rewards`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `user_activities`
--
ALTER TABLE `user_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `volunteers`
--
ALTER TABLE `volunteers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `volunteer_register`
--
ALTER TABLE `volunteer_register`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `checkins`
--
ALTER TABLE `checkins`
  ADD CONSTRAINT `fk_checkins_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_checkins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `news`
--
ALTER TABLE `news`
  ADD CONSTRAINT `fk_news_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `redemptions`
--
ALTER TABLE `redemptions`
  ADD CONSTRAINT `fk_redemption_reward` FOREIGN KEY (`rewards_id`) REFERENCES `rewards` (`id`),
  ADD CONSTRAINT `fk_redemptions_reward` FOREIGN KEY (`rewards_id`) REFERENCES `rewards` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_redemptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `fk_registrations_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_registrations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `user_activities`
--
ALTER TABLE `user_activities`
  ADD CONSTRAINT `fk_user_activities_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `volunteers`
--
ALTER TABLE `volunteers`
  ADD CONSTRAINT `fk_volunteers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
