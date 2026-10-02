# SmarCery

SmarCery adalah website rekomendasi produk supermarket berdasarkan profil kesehatan pengguna.
Backend saat ini menggunakan:

- Python 3.11+
- Flask + Jinja
- MySQL untuk user, profil, kategori, transaksi, dan bookmark
- MongoDB untuk produk dan kontribusi
- Neo4j untuk relasi rekomendasi

## Struktur

```text
python_app/
  app.py
  templates/
requirements.txt
sql/
mongo_seed/
neo4j_seed/
```

## Menjalankan di Codespaces

Pastikan MySQL, MongoDB, dan Neo4j aktif. Install dependency Python:

```bash
python3 -m pip install -r requirements.txt
```

Set environment variable:

```bash
export DB_HOST=127.0.0.1
export DB_NAME=smarcery
export DB_USER=root
export DB_PASS=root
export MONGO_URI=mongodb://127.0.0.1:27017
export NEO4J_HOST=127.0.0.1
export NEO4J_HTTP_PORT=7474
export NEO4J_USER=neo4j
export NEO4J_PASS=password123
```

## Import Data XLSX

Jalankan setelah skema MySQL, seed kategori/alergen, dan ketiga database aktif:

```bash
python3 -m pip install -r requirements.txt
python3 tools/import_xlsx_products.py
```

Importer membaca `makanan data.xlsx` dan `minuman data.xlsx` lalu:

- menyimpan 43 produk dan komposisinya ke MongoDB `smarcery.products`;
- memastikan kategori serta master alergen tersedia di MySQL;
- membuat node produk, kategori, dan relasi alergen di Neo4j.

Import aman dijalankan ulang karena produk memakai ID deterministik dan prosesnya
menggunakan upsert. Untuk memeriksa XLSX tanpa koneksi database:

```bash
python3 tools/import_xlsx_products.py --dry-run
```

Untuk Neo4j, importer menggunakan `NEO4J_URI`, `NEO4J_USER`, dan
`NEO4J_PASSWORD` (bukan `NEO4J_PASS`).

Jalankan Flask:

```bash
PORT=8001 python3 python_app/app.py
```

Buka:

```text
http://localhost:8001/SmarCery/public/
```

## Akun Demo

```text
Admin: admin@smarcery.local / admin123
User:  demo@smarcery.local / demo12345
```

## Endpoint Utama

- `/SmarCery/public/login.php`
- `/SmarCery/public/register.php`
- `/SmarCery/public/admin/dashboard.php`
- `/SmarCery/public/admin/products.php`
- `/SmarCery/public/user/dashboard.php`
- `/SmarCery/public/user/browse.php`
- `/SmarCery/public/user/product.php?id=<object-id>`
- `/SmarCery/public/api/health`
- `/SmarCery/public/api/recommend.php`
- `/SmarCery/public/api/autocomplete.php?q=<query>`

PHP, Composer, XAMPP, Apache, dan folder `vendor/` tidak lagi digunakan.
