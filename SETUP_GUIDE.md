# 🚀 Panduan Setup SmarCery — Step by Step (Windows + XAMPP)

> **Target akhir:** Website SmarCery bisa dibuka di browser, register/login jalan, rekomendasi dari Neo4j tampil.
> **Estimasi waktu:** 30-60 menit untuk pertama kali.
> **Tingkat kesulitan:** Pemula-menengah (asal ikutin step-by-step).

---

## 📦 DAFTAR YANG PERLU DIINSTALL

| # | Aplikasi          | Fungsi                       | Link Download                                   | Default Port |
|---|-------------------|------------------------------|-------------------------------------------------|--------------|
| 1 | XAMPP             | Apache + MySQL + PHP         | https://www.apachefriends.org/                 | 80, 3306     |
| 2 | Composer          | PHP package manager          | https://getcomposer.org/download               | -            |
| 3 | MongoDB Community | Database NoSQL (produk)      | https://www.mongodb.com/try/download/community | 27017        |
| 4 | MongoDB Compass    | GUI untuk MongoDB (opsional)| https://www.mongodb.com/try/download/compass    | -            |
| 5 | Neo4j Desktop      | Database Graf (rekomendasi)  | https://neo4j.com/download/                    | 7474, 7687   |
| 6 | Java JDK 17+      | Diperlukan Neo4j Desktop     | https://adoptium.net/                          | -            |

> ⚠️ **Pastikan PHP di XAMPP versi 8.0+** (cek di http://localhost/dashboard/phpinfo.php atau `php -v` di Command Prompt).

---

## STEP 0 — Persiapan Folder Proyek

### 0.1. Extract / Letakkan Proyek
Pastikan folder SmarCery ada di persis seperti ini:
```
C:\xampp\htdocs\SmarCery\
├── public\
├── src\
├── views\
├── sql\
├── mongo_seed\
├── neo4j_seed\
├── tools\
├── composer.json
├── README.md
└── .htaccess
```

### 0.2. Buka Command Prompt (atau PowerShell)
Tekan `Win + R`, ketik `cmd`, Enter. Atau klik kanan Start → **Terminal (Admin)**.

```cmd
cd C:\xampp\htdocs\SmarCery
dir
```
Pastikan folder & file di atas muncul.

---

## STEP 1 — Install & Start XAMPP

### 1.1. Install XAMPP
Download di link di atas, install ke `C:\xampp` (default).

### 1.2. Start Apache & MySQL
1. Buka **XAMPP Control Panel** dari Start Menu.
2. Klik tombol **Start** di sebelah **Apache** → tunggu jadi hijau.
3. Klik tombol **Start** di sebelah **MySQL** → tunggu jadi hijau.
4. Kalau ada notifikasi Windows Firewall, klik **Allow access**.

### 1.3. Verifikasi
- Buka browser → `http://localhost/` → harus muncul halaman XAMPP dashboard.
- Buka `http://localhost/phpmyadmin/` → harusnya masuk phpMyAdmin.

### 1.4. Cek PHP Version
Di Command Prompt:
```cmd
C:\xampp\php\php.exe -v
```
Output harus `PHP 8.0` atau lebih baru. Kalau versi lebih lama, update XAMPP.

---

## STEP 2 — Setup MySQL (phpMyAdmin)

### 2.1. Buka phpMyAdmin
Browser: `http://localhost/phpmyadmin/`

### 2.2. Import Schema
1. Klik tab **Import** (di bar atas).
2. Klik **Choose File** → pilih file `C:\xampp\htdocs\SmarCery\sql\schema.sql`.
3. Scroll bawah, klik **Go** / **Import**.
4. Seharusnya muncul pesan "Import has been successfully finished".
5. Database `smarcery` otomatis dibuat dengan semua tabel (`users`, `allergens`, dll).

### 2.3. Import Alergen (Master)
Ulangi langkah 2.2, tapi file: `C:\xampp\htdocs\SmarCery\sql\seed_allergens.sql`

### 2.4. Import Kategori (Master)
Ulangi langkah 2.2, tapi file: `C:\xampp\htdocs\SmarCery\sql\seed_categories.sql`

### 2.5. Verifikasi
Di sidebar kiri phpMyAdmin, klik database `smarcery` → harus muncul tabel-tabel:
- allergens, bookmarks, categories, contribution_status, transaction_items, transactions, user_allergens, user_profiles, users.

Klik tabel `allergens` → harus ada 10 alergen (Telur, Susu sapi, Gluten, dll).
Klik tabel `categories` → harus ada ~14 kategori.

---

## STEP 3 — Install Composer

### 3.1. Download & Install
- Buka https://getcomposer.org/download/
- Download **Composer-Setup.exe**
- Install, **pastikan path PHP terdeteksi otomatis** (default: `C:\xampp\php\php.exe`).
- Klik Next → Install → Finish.

### 3.2. Verifikasi
Tutup Command Prompt yang lama, buka yang baru:
```cmd
composer --version
```
Harus muncul `Composer version 2.x.x`.

### 3.3. Install Dependency SmarCery
```cmd
cd C:\xampp\htdocs\SmarCery
composer install
```
Output: dependency `mongodb/mongodb` akan terdownload ke folder `vendor/`.

> ⚠️ Kalau muncul error "requires ext-mongodb", install extension MongoDB untuk XAMPP:
> 1. Download di https://windows.php.net/downloads/pecl/releases/mongodb/
> 2. Pilih yang sesuai versi PHP (mis. `php_mongodb-1.17.x-8.0-ts-vc15-x64.zip`)
> 3. Extract → ambil `php_mongodb.dll`
> 4. Copy ke `C:\xampp\php\ext\`
> 5. Edit `C:\xampp\php\php.ini` → tambah: `extension=php_mongodb.dll`
> 6. Restart Apache dari XAMPP Control Panel.
> 7. Cek: `php -m | findstr mongodb` harusnya muncul `mongodb`.

---

## STEP 4 — Install MongoDB Community Server

### 4.1. Download & Install
- Download di https://www.mongodb.com/try/download/community
- Pilih versi **Windows**, format **MSI**.
- Install dengan opsi:
  - ✅ Complete Setup
  - ✅ Install MongoDB as a Service (Service Name: `MongoDB`)
  - ✅ Run service as Network Service User
  - ✅ Install MongoDB Compass (GUI, opsional tapi sangat membantu)

### 4.2. Verifikasi MongoDB Berjalan
Buka Command Prompt baru:
```cmd
net start | findstr /i "mongo"
```
Harus muncul `MongoDB Server`. Kalau belum:
```cmd
net start MongoDB
```

Atau via services.msc → cari "MongoDB Server" → klik Start.

### 4.3. Import Data Sample
Jalankan command ini di Command Prompt:
```cmd
cd C:\xampp\htdocs\SmarCery

mongosh
```
(Pakai `mongo` untuk versi lama.) Lalu di dalam shell:
```js
use smarcery
db.products.insertMany(/* paste isi array dari mongo_seed/seed_products.json */)
```
**Cara paling mudah:**

1. Buka **MongoDB Compass** → Connect ke `mongodb://localhost:27017`
2. Klik **+** di sidebar kiri untuk create database: `smarcery`
3. Pilih collection name: `products`
4. Klik **Create Database**
5. Klik collection `products` → klik tab **Add Data** → **Import JSON** → pilih file `C:\xampp\htdocs\SmarCery\mongo_seed\seed_products.json`
6. Format harus **JSON Array**, klik **Import**.

### 4.4. Verifikasi
Di Compass, klik collection `products` → harus muncul 11 dokumen (produk).

---

## STEP 5 — Install Neo4j Desktop

### 5.1. Install Java JDK dulu (kalau belum)
Download dari https://adoptium.net/ → pilih **JDK 17+** untuk Windows x64 → install.

### 5.2. Install Neo4j Desktop
- Download dari https://neo4j.com/download/
- Install, login / sign-up Neo4j account (free).

### 5.3. Buat Local Database
1. Buka Neo4j Desktop.
2. Klik **+ New** → **Create a local project** (atau pakai default "Project").
3. Di project, klik tombol **+ Add** → **Local DBMS**.
4. Isi:
   - **Name**: `smarcery-db`
   - **Password**: `password123` (atau apapun, **catat & simpan baik-baik**)
5. Klik **Create**.
6. Tunggu status jadi **Active** (warna hijau).
7. Klik tombol **Open** di sebelah kanan DBMS → buka **Neo4j Browser**.

### 5.4. Set Password di File Konfigurasi PHP
Buka file `C:\xampp\htdocs\SmarCery\src\config\db_neo4j.php` di text editor (Notepad / VSCode).

Cari baris:
```php
$pass = getenv('NEO4J_PASS') ?: 'password';
```

Ganti dengan password yang Anda set di Neo4j:
```php
$pass = getenv('NEO4J_PASS') ?: 'password123';
```

(Juga ganti `getenv('NEO4J_PASS')` → `'password123'` kalau password `password123`.)

> Atau lebih aman, set environment variable di Windows:
> 1. Buka System Properties → Environment Variables
> 2. New System Variable: `NEO4J_PASS=password123`
> 3. Restart Command Prompt & Apache

### 5.5. Jalankan Seed Cypher
1. Di Neo4j Browser (`http://localhost:7474`), pastikan Anda **terkoneksi** ke `smarcery-db` (lihat pojok kiri atas).
2. Buka file `C:\xampp\htdocs\SmarCery\neo4j_seed\seed.cypher` di Notepad.
3. **Copy semua isinya** (`Ctrl + A` → `Ctrl + C`).
4. Paste ke Neo4j Browser (kotak atas yang ada `$`).
5. Klik tombol **▶ Run** (atau tekan `Ctrl + Enter`).
6. Akan ada beberapa output pernyataan sukses. Kalau ada error, biasanya karena constraint sudah ada — aman diabaikan.

### 5.6. Verifikasi
Di Neo4j Browser, jalankan query berikut (copy-paste ke editor Cypher):
```cypher
MATCH (p:Product) RETURN p.name, p.allergens LIMIT 5;
```
Harus tampil 5 produk dengan daftar alergen masing-masing.

```cypher
MATCH (u:User) RETURN u.name LIMIT 5;
```
Awalnya mungkin kosong (User baru ada setelah register).

---

## STEP 6 — Buat Akun Admin di MySQL

### 6.1. Buka Command Prompt
```cmd
cd C:\xampp\htdocs\SmarCery
php tools/seed_admin.php admin@smarcery.local "Admin SmarCery" admin123
```

Output yang diharapkan:
```
Created admin id=1 (admin@smarcery.local)
```

(Ganti password `admin123` dengan yang lebih kuat kalau untuk produksi.)

### 6.2. Verifikasi
- Buka `http://localhost/phpmyadmin/`
- Database `smarcery` → tabel `users` → Browse.
- Harus ada 1 row: Admin SmarCery / admin@smarcery.local / role=admin.

---

## STEP 7 — Buka Website SmarCery

### 7.1. Restart Apache (kalau belum)
Pastikan Apache & MySQL di XAMPP Control Panel masih hijau. Kalau baru saja edit `php.ini`, restart Apache.

### 7.2. Buka di Browser
Kunjungi:
```
http://localhost/SmarCery/public/
```

### 7.3. Login sebagai Admin
- Klik tombol "Masuk" atau langsung ke `http://localhost/SmarCery/public/login.php`
- Email: `admin@smarcery.local`
- Password: `admin123`
- Klik **Masuk** → otomatis redirect ke `http://localhost/SmarCery/public/admin/dashboard.php`

### 7.4. Cek Halaman Lainnya
- **Admin → Produk**: `http://localhost/SmarCery/public/admin/products.php`
- **Admin → Kategori**: `http://localhost/SmarCery/public/admin/categories.php`
- **Admin → Alergen**: `http://localhost/SmarCery/public/admin/allergens.php`
- **Admin → Review**: `http://localhost/SmarCery/public/admin/reviews.php`

### 7.5. Register User Baru
- Logout (klik "Keluar" di kanan atas)
- Buka `http://localhost/SmarCery/public/register.php`
- Isi nama, email, password (min 8 karakter)
- Klik **Daftar** → otomatis login & redirect ke `http://localhost/SmarCery/public/user/profile.php`

### 7.6. Test Alur Rekomendasi
1. Di halaman profil, centang **alergen Gluten** + diet **Vegan**, klik Simpan.
2. Buka Beranda (`/user/dashboard.php`) → hanya produk yang **tidak** mengandung gluten dan **cocok** dengan vegan yang muncul.
3. Buka `/user/browse.php` → katalog lengkap, sidebar kiri ada filter kategori.

---

## STEP 8 — Verifikasi Semua Koneksi Berhasil

Jalankan Command Prompt:
```cmd
cd C:\xampp\htdocs\SmarCery

REM 1. MySQL
C:\xampp\php\php.exe -r "require 'src/core/bootstrap.php'; \$pdo = db_mysql(); \$row = \$pdo->query('SELECT COUNT(*) c FROM users')->fetch(); echo 'MySQL OK: ' . \$row['c'] . ' users' . PHP_EOL;"

REM 2. MongoDB
C:\xampp\php\php.exe -r "require 'vendor/autoload.php'; \$client = new MongoDB\Client('mongodb://127.0.0.1:27017'); \$count = \$client->smarcery->products->countDocuments([]); echo 'MongoDB OK: ' . \$count . ' products' . PHP_EOL;"

REM 3. Neo4j
curl -s -u neo4j:password123 -H "Content-Type: application/json" -X POST -d "{\"statements\":[{\"statement\":\"MATCH (p:Product) RETURN count(p) AS c\",\"parameters\":{}}]}" http://localhost:7474/db/data/transaction/commit
```

Output yang diharapkan:
```
MySQL OK: 1 users
MongoDB OK: 11 products
{"results":[{"columns":["c"],"data":[{"row":[11]}]}]}
```

Kalau salah satu gagal, cek:
- **MySQL gagal** → Apache/MySQL belum jalan? Cek XAMPP Control Panel.
- **MongoDB gagal** → service MongoDB belum start? `net start MongoDB`.
- **Neo4j gagal** → DB belum Active? Cek Neo4j Desktop. Password salah? Cek `db_neo4j.php`.

---

## STEP 9 — (Opsional) Konfigurasi Folder Images

Saat ini gambar produk di `seed_products.json` mengarah ke path seperti `/assets/img/products/biskuit-coklat.jpg`, tapi file gambar **tidak ada** (placeholder). Browser akan otomatis fallback ke emoji kategori (🥪 dst).

Kalau mau menambahkan gambar asli:
1. Siapkan file gambar JPG/PNG (resolusi 800x600px direkomendasikan).
2. Simpan di: `C:\xampp\htdocs\SmarCery\public\assets\img\products\`
3. Beri nama sesuai path di seed (mis. `biskuit-coklat.jpg`, `kerupuk-udang.png`, dll.)
4. Refresh halaman produk → gambar akan muncul.

---

## 🆘 TROUBLESHOOTING (Masalah Umum)

### ❌ "Cannot connect to MySQL"
- XAMPP Control Panel → pastikan MySQL **hijau**.
- Cek `src/config/db_mysql.php` → host/user/password.

### ❌ "Class 'MongoDB\Driver\Manager' not found"
- Install extension `php_mongodb.dll` (lihat STEP 3.3).
- Restart Apache.

### ❌ "Connection refused on mongodb://127.0.0.1:27017"
- MongoDB service belum jalan: `net start MongoDB`.
- Cek port: `netstat -an | findstr :27017`.

### ❌ "Neo4j connection failed"
- Buka Neo4j Desktop → DBMS `smarcery-db` harus **Active**.
- Cek password di `src/config/db_neo4j.php` sesuai dengan yang Anda set.
- Cek port 7474 (HTTP): `netstat -an | findstr :7474`.

### ❌ "Page not found" / "404 Not Found"
- Pastikan Anda akses `http://localhost/SmarCery/public/` (bukan `/SmarCery/`).
- Cek Apache sudah Start di XAMPP.
- Cek file `C:\xampp\htdocs\SmarCery\public\index.php` ada.

### ❌ Halaman putih tanpa error
- Edit `src/config/app.php` → pastikan `APP_DEBUG = true`.
- Refresh → akan muncul error message.
- Atau cek error log Apache: `C:\xampp\apache\logs\error.log`.

### ❌ Bookmark tidak berfungsi
- Buka Console browser (F12) → cek error JS.
- Pastikan `window.SMARCERY.csrf` ada di HTML (cek View Source).

### ❌ Rekomendasi kosong
- Pastikan Neo4j sudah di-seed (lihat STEP 5.6).
- Pastikan User sudah set alergen di `/user/profile.php`.
- Cek apakah MongoDB sudah ada data produk.

### ❌ Composer's `composer install` error
- Pastikan PHP minimal 8.0.
- Aktifkan extension `mongodb` di `php.ini`.

---

## 📁 CHEATSHEET — Lokasi Penting

| Komponen                | Path                                                              |
|-------------------------|-------------------------------------------------------------------|
| Document root Apache    | `C:\xampp\htdocs\`                                                |
| Folder proyek           | `C:\xampp\htdocs\SmarCery\`                                       |
| Document root SmarCery  | `C:\xampp\htdocs\SmarCery\public\`                                |
| Akses website           | `http://localhost/SmarCery/public/`                               |
| Konfigurasi DB          | `C:\xampp\htdocs\SmarCery\src\config\`                            |
| Konfigurasi PHP         | `C:\xampp\php\php.ini`                                            |
| Konfigurasi Apache      | `C:\xampp\apache\conf\httpd.conf`                                 |
| Error log Apache        | `C:\xampp\apache\logs\error.log`                                  |
| Composer                | `C:\xampp\htdocs\SmarCery\composer.json`                          |
| Vendor (composer)       | `C:\xampp\htdocs\SmarCery\vendor\`                                |
| MongoDB data            | `C:\Program Files\MongoDB\Server\7.0\data\`                       |
| Neo4j DBMS              | `%USERPROFILE%\.Neo4jDesktop\neo4jDatabases\`                     |

---

## ✅ CHECKLIST AKHIR

Sebelum declare "berhasil", cek semua ini:

- [ ] XAMPP Apache & MySQL jalan
- [ ] Composer dependency terinstall (folder `vendor/` ada)
- [ ] MongoDB service running, ada 11 produk di collection `products`
- [ ] Neo4j Desktop running, DBMS `smarcery-db` Active, seed.cypher sudah dijalankan
- [ ] phpMyAdmin → database `smarcery` punya 9 tabel + 1 user admin
- [ ] Browser → `http://localhost/SmarCery/public/` bisa dibuka
- [ ] Login admin berhasil (`admin@smarcery.local` / `admin123`)
- [ ] Register user baru berhasil
- [ ] Set profil alergen → dashboard menampilkan produk yang TIDAK mengandung alergen
- [ ] Klik produk → muncul section "Alternatif Aman"

Kalau semua ✅, **SmarCery siap dipakai!** 🎉

---

## 📞 Butuh Bantuan?

1. Cek log error Apache: `C:\xampp\apache\logs\error.log` (tail -50 di text editor)
2. Cek output browser console (F12) untuk error JS
3. Cek file `README.md` untuk overview proyek
4. Cek `C:\xampp\htdocs\SmarCery\docs\architecture.md` (jika ada) untuk diagram

Selamat mencoba! 🚀