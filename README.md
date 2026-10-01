# SmarCery 🛒

SmarCery adalah aplikasi web rekomendasi produk supermarket pintar berbasis profil kesehatan pengguna. Menggunakan tiga database:

- **MySQL** (XAMPP) — user, profil diet/alergen, transaksi, bookmark, kategori
- **MongoDB** — detail produk & nutrisi (skema fleksibel), review & kontribusi audience
- **Neo4j** — graf relasi User↔Alergen↔Produk untuk rekomendasi & substitusi instan

Engine rekomendasi: **content-based filtering** dari profil diet/alergen user + traversal graf Neo4j untuk substitusi.

---

## Prasyarat

| Komponen | Versi | Catatan |
|----------|-------|---------|
| XAMPP    | 8.x+  | Apache + MySQL/MariaDB sudah cukup |
| PHP      | 8.0+  | Aktif di XAMPP |
| MongoDB  | 6.x+  | Install manual (tidak termasuk XAMPP), default port 27017 |
| Neo4j    | 5.x   | Install Neo4j Desktop, buat DB lokal di port 7687 (Bolt) |
| Composer | 2.x   | Untuk dependency PHP |

---

## Langkah Instalasi

### 1. Clone / Extract Proyek
Pastikan proyek ada di `c:\xampp\htdocs\SmarCery`.

### 2. Install Dependency PHP
```bash
cd c:\xampp\htdocs\SmarCery
composer install
```
Ini akan meng-install:
- `mongodb/mongodb` (driver MongoDB)
- `laverdet/neo4j-php-client` (driver Neo4j)

### 3. Setup MySQL (XAMPP)
1. Start Apache + MySQL di XAMPP Control Panel.
2. Buka `http://localhost/phpmyadmin`.
3. Pilih tab **Import**, upload file `sql/schema.sql` lalu `sql/seed_allergens.sql` dan `sql/seed_categories.sql` secara berurutan.
4. (Opsional) Jalankan `php tools/seed_admin.php admin@smarcery.local "Admin SmarCery" admin123` untuk buat akun admin dengan hash password yang benar.

> ⚠️ **Catatan password**: Hash di `sql/seed_users.sql` adalah placeholder. Selalu gunakan `tools/seed_admin.php` untuk membuat akun dengan hash `password_hash()` yang valid.

### 4. Setup MongoDB
1. Pastikan service MongoDB berjalan (`net start MongoDB` atau dari MongoDB Compass).
2. Import data sample:
   ```bash
   mongoimport --db smarcery --collection products --file mongo_seed/seed_products.json --jsonArray
   ```
   atau gunakan MongoDB Compass → pilih DB `smarcery` → collection `products` → Import JSON.
3. Buat index (opsional, untuk performa):
   ```js
   db.products.createIndex({ category_id: 1 });
   db.products.createIndex({ name: "text", brand: "text", tags: "text" });
   db.products.createIndex({ allergens: 1 });
   db.contributions.createIndex({ product_id: 1, submitted_at: -1 });
   ```

### 5. Setup Neo4j
1. Buka Neo4j Browser: `http://localhost:7474`.
2. Default user: `neo4j`, password akan diminta saat pertama kali (default: `neo4j`, lalu disuruh ganti).
4. **Edit password di `src/config/db_neo4j.php`** agar sesuai dengan yang Anda set.
5. Copy-paste isi file `neo4j_seed/seed.cypher` ke Neo4j Browser, klik **Run**.

### 6. Konfigurasi (opsional)
Edit `src/config/db_*.php` atau set environment variables:
```
DB_HOST=localhost
DB_NAME=smarcery
DB_USER=root
DB_PASS=

MONGO_URI=mongodb://127.0.0.1:27017

NEO4J_HOST=127.0.0.1
NEO4J_PORT=7687
NEO4J_USER=neo4j
NEO4J_PASS=<password-anda>
```

### 7. Akses Aplikasi
Buka browser: **`http://localhost/SmarCery/public/`**

Login dengan akun admin atau user yang Anda buat di langkah 3.

---

## Struktur Direktori

```
SmarCery/
├── public/                   # Document root (yang diakses browser)
│   ├── index.php             # Routing awal
│   ├── login.php / register.php / logout.php
│   ├── user/                 # Halaman user
│   ├── admin/                # Halaman admin
│   ├── api/                  # Endpoint JSON
│   ├── assets/css/, assets/js/
│   └── uploads/              # Foto kontribusi
├── src/
│   ├── config/               # DB connection (MySQL, Mongo, Neo4j)
│   ├── core/                 # Auth, Csrf, Flash, Validator, Response, bootstrap
│   ├── repositories/         # Akses DB per domain
│   ├── services/             # Orkestrasi (RecommendationService, dll)
│   └── helpers/              # url, format
├── views/                    # Template HTML (layouts + partials)
├── sql/                      # Schema MySQL + seed
├── mongo_seed/               # JSON sample produk
├── neo4j_seed/               # Skrip Cypher relasi awal
└── tools/seed_admin.php       # CLI helper buat admin
```

---

## Akun Demo (dari seed)

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@smarcery.local` | `admin123` (jalankan `tools/seed_admin.php`) |
| User  | `budi@example.com` (id=2) | alergi gluten, vegetarian |
| User  | `siti@example.com` (id=3) | alergi susu & telur, diet halal |

> Password untuk user demo di atas **tidak** bisa login sampai Anda generate hash baru. Gunakan CLI helper untuk set password sendiri, atau cukup register user baru dari halaman `/register.php`.

---

## Cara Kerja Rekomendasi

```
[User] → set diet & alergen di /profile
        ↓
   ProfileRepository::updateProfile()
        ├── MySQL: simpan diet_tags & user_allergens
        └── Neo4j: MERGE User + HAS_ALLERGEN + FOLLOWS_DIET

[Dashboard] → RecommendationService::getRecommendations(uid)
        ├── GraphRepository::recommend(allergens, diets) — Cypher traversal
        │       └── Filter produk yang TIDAK mengandung alergen user
        ├── ProductRepository::findMany(ids) — hydrate atribut dari Mongo
        └── ProductRepository::findMany(subIds) — substitusi per produk

[Product Detail] → RecommendationService::getAlternatives(productId, uid)
        └── GraphRepository::substitutes() — SUBSTITUTE_OF edges

[Admin CRUD Product]
        ├── ProductRepository::save() (Mongo)
        └── GraphRepository::syncProduct() — rel CONTAINS_ALLERGIEN + COMPATIBLE_WITH
```

---

## Pengujian End-to-End

1. **Login admin** (`admin@smarcery.local` / `admin123`)
2. Buka **Profil** (`user/profile.php`) — centang alergen "Gluten" + diet "Vegan", simpan.
3. Buka **Beranda** (`user/dashboard.php`) — hanya produk tanpa gluten + ilustrasi (Vegan-friendly) yang muncul.
4. Klik salah satu produk — lihat tabel "Alternatif Aman" (3 substitusi yang aman).
5. Klik ♡ pada produk untuk bookmark, cek MySQL `bookmarks`.
6. Buka **Kontribusi** (`user/contribute.php?product=ID_PRODUK`) — submit "Klaim alergen tambahan", tulis "kacang".
7. Login admin → **Review** (`admin/reviews.php`) → klik "Setujui".
8. Verify di Neo4j Browser:
   ```cypher
   MATCH (p:Product)-[:CONTAINS_ALLERGIEN]->(a:Allergen {slug:'kacang'})
   RETURN p.name, a.name;
   ```

---

## Skema Singkat

### MySQL — `smarcery`
- `users` (id, name, email, password_hash, role)
- `user_profiles` (user_id, diet_tags JSON, other_notes)
- `user_allergens` (user_id, allergen_id, severity) — pivot
- `allergens` (id, name, slug, icon)
- `categories` (id, parent_id, name, slug) — tree
- `bookmarks` (user_id, product_id) — product_id = MongoDB ObjectId
- `transactions` + `transaction_items`
- `contribution_status` (contribution_id, status, reviewed_by, reviewed_at)

### MongoDB — `smarcery`
- **`products`** — flexible `attributes` per kategori (food vs non-food)
- **`contributions`** — review/koreksi/klaim alergen dari user

### Neo4j
- Nodes: `(:User)`, `(:Product)`, `(:Allergen)`, `(:DietTag)`
- Edges: `HAS_ALLERGEN`, `FOLLOWS_DIET`, `CONTAINS_ALLERGIEN`, `COMPATIBLE_WITH`, `SUBSTITUTE_OF`

---

## Lisensi

MIT