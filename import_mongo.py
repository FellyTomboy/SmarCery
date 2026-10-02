import json
from pymongo import MongoClient

# 1. Terhubung ke MongoDB Atlas kamu
uri = "mongodb+srv://flc982006_db_user:lKuk9B9UirPeW9gD@smarcery.au7wfq8.mongodb.net/smarcery"
client = MongoClient(uri)
db = client.smarcery

# 2. Buka dan baca file JSON seeder
try:
    with open('mongo_seed/seed_products.json', 'r') as file:
        data = json.load(file)
        
        # 3. Ambil isi dari dalam "products"
        products_array = data.get("products", [])
        
        if products_array:
            # 4. Masukkan semua data sekaligus ke database cloud
            db.products.insert_many(products_array)
            print(f"🎉 BERHASIL! {len(products_array)} produk telah dimasukkan ke MongoDB Atlas.")
        else:
            print("Gagal: Data produk kosong atau format JSON tidak sesuai.")

except Exception as e:
    print(f"Terjadi kesalahan: {e}")