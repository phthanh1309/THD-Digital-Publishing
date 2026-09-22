# THD Web Projects

Source code và các dự án web được phát triển trong môi trường **XAMPP / PHP**.

Repository này hiện được sử dụng để lưu trữ workspace `htdocs` trong quá trình phát triển và thử nghiệm các dự án web.

## 📁 Cấu trúc

```text
htdocs/
├── flipbook/          # THD Digital Publishing
├── ...
└── README.md
```

## 🚀 Môi trường phát triển

* **OS:** Windows
* **Web server:** Apache
* **PHP:** PHP 8+
* **Database:** XML / file-based storage tùy dự án
* **Frontend:** HTML, CSS, JavaScript
* **Local environment:** XAMPP

## 📚 THD Digital Publishing

`flipbook/` là dự án **THD Digital Publishing – Thư viện Ấn phẩm số** dành cho:

**THPT A Trần Hưng Đạo**

Mục tiêu của dự án là xây dựng một hệ thống lưu trữ và trình đọc các ấn phẩm số của nhà trường, với giao diện thân thiện trên máy tính và thiết bị di động.

### Công nghệ

* PHP 8+
* HTML5
* CSS3
* JavaScript (ES6+)
* XML-based storage
* Apache
* XAMPP

### Các chức năng chính

* 📚 Thư viện ấn phẩm số
* 🔎 Tìm kiếm ấn phẩm
* 📖 Trình đọc tài liệu
* 🔗 Chia sẻ và truy cập trực tiếp tới trang
* 🔐 Khu vực quản trị
* 👤 Quản lý tài khoản quản trị
* 📤 Quản lý và xuất bản ấn phẩm
* 💾 Sao lưu dữ liệu
* 🛡️ CSRF / session / upload validation và các cơ chế bảo vệ cơ bản

## 🛠️ Chạy local

Cài đặt XAMPP, sau đó đặt repository vào thư mục:

```text
D:\windows\xampp\htdocs
```

Khởi động **Apache** trong XAMPP và truy cập:

```text
http://localhost/flipbook/
```

Tùy từng dự án, có thể cần thiết lập thêm file cấu hình hoặc chạy quá trình cài đặt ban đầu.

## 🔒 Dữ liệu nhạy cảm

Repository chỉ nên chứa **source code và các tài nguyên cần thiết cho việc phát triển**.

Các dữ liệu như:

* mật khẩu
* API key
* file `.env`
* dữ liệu người dùng
* backup
* log
* file database chứa dữ liệu thực tế

không nên được đưa lên Git.

## 📌 Trạng thái

Dự án đang trong quá trình phát triển và thử nghiệm.

Một số thành phần có thể thay đổi khi tiếp tục phát triển hoặc triển khai thực tế.

## 👤 Author

**TLN Minh**
**PV Thanh**
**DVM Đức**

THPT A Trần Hưng Đạo

---

> Repository phục vụ mục đích phát triển, thử nghiệm và lưu trữ source code các dự án web.
