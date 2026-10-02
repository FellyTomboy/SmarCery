import os
import uuid
from functools import wraps
from pathlib import Path

import bcrypt
import mysql.connector
import requests
from bson import ObjectId
from flask import (
    Flask,
    flash,
    jsonify,
    redirect,
    render_template,
    request,
    send_from_directory,
    session,
    url_for,
)
from pymongo import MongoClient
from werkzeug.security import check_password_hash, generate_password_hash
from werkzeug.utils import secure_filename

BASE_PATH = "/SmarCery/public"
ROOT = Path(__file__).resolve().parent
UPLOAD_FOLDER = ROOT / "uploads"
ALLOWED_IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp"}
MAX_IMAGE_SIZE = 5 * 1024 * 1024

app = Flask(__name__, template_folder=str(ROOT / "templates"))
app.secret_key = os.getenv("FLASK_SECRET", "smarcery-development-secret")
app.config.update(SESSION_COOKIE_HTTPONLY=True, SESSION_COOKIE_SAMESITE="Lax")
app.config["MAX_CONTENT_LENGTH"] = MAX_IMAGE_SIZE


def mysql_connection():
    return mysql.connector.connect(
        host=os.getenv("DB_HOST", "127.0.0.1"),
        database=os.getenv("DB_NAME", "smarcery"),
        user=os.getenv("DB_USER", "root"),
        password=os.getenv("DB_PASS", "root"),
    )


def mongo_collection(name="products"):
    client = MongoClient(os.getenv("MONGO_URI", "mongodb://127.0.0.1:27017"))
    return client.smarcery[name]


def save_product_image(upload):
    if not upload or not upload.filename:
        return None
    extension = Path(secure_filename(upload.filename)).suffix.lower()
    if extension not in ALLOWED_IMAGE_EXTENSIONS:
        raise ValueError("Foto harus berformat JPG, PNG, atau WEBP.")
    header = upload.stream.read(12)
    upload.stream.seek(0)
    valid_header = (
        header.startswith(b"\xff\xd8\xff")
        or header.startswith(b"\x89PNG\r\n\x1a\n")
        or (header.startswith(b"RIFF") and header[8:12] == b"WEBP")
    )
    if not valid_header:
        raise ValueError("File yang diunggah bukan gambar yang valid.")
    UPLOAD_FOLDER.mkdir(parents=True, exist_ok=True)
    filename = f"{uuid.uuid4().hex}{extension}"
    upload.save(UPLOAD_FOLDER / filename)
    return f"{BASE_PATH}/uploads/{filename}"


def delete_product_image(image_url):
    if not image_url or not image_url.startswith(f"{BASE_PATH}/uploads/"):
        return
    filename = Path(image_url).name
    if filename:
        (UPLOAD_FOLDER / filename).unlink(missing_ok=True)


@app.get(f"{BASE_PATH}/uploads/<path:filename>")
def product_upload(filename):
    return send_from_directory(UPLOAD_FOLDER, filename)


def category_map():
    conn = mysql_connection()
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT id, name FROM categories")
        return {row["id"]: row["name"] for row in cur.fetchall()}
    finally:
        conn.close()


def products_from_mongo(query=None, limit=100):
    docs = list(mongo_collection().find(query or {"is_active": True}).sort("popularity", -1).limit(limit))
    categories = category_map()
    for product in docs:
        product["_id"] = str(product["_id"])
        product["category_name"] = categories.get(product.get("category_id"), "-")
    return docs


def current_user():
    return session.get("user")


def verify_password(stored_hash, password):
    # PHP password_hash() uses the compatible bcrypt $2y$ prefix.
    if stored_hash and stored_hash.startswith(("$2y$", "$2b$", "$2a$")):
        try:
            return bcrypt.checkpw(password.encode(), stored_hash.encode().replace(b"$2y$", b"$2b$"))
        except ValueError:
            return False
    try:
        return bool(stored_hash) and check_password_hash(stored_hash, password)
    except ValueError:
        return False


def login_required(view):
    @wraps(view)
    def wrapped(*args, **kwargs):
        if not current_user():
            return redirect(url("/login.php"))
        return view(*args, **kwargs)

    return wrapped


def admin_required(view):
    @wraps(view)
    def wrapped(*args, **kwargs):
        user = current_user()
        if not user:
            return redirect(url("/login.php"))
        if user["role"] != "admin":
            flash("Akses hanya untuk admin.", "danger")
            return redirect(url("/user/dashboard.php"))
        return view(*args, **kwargs)

    return wrapped


@app.context_processor
def template_helpers():
    return {"url": url, "user": current_user()}


def url(path):
    return f"{BASE_PATH}{path}"


@app.get(f"{BASE_PATH}/")
def index():
    user = current_user()
    if not user:
        return redirect(url("/login.php"))
    destination = "/admin/dashboard.php" if user["role"] == "admin" else "/user/dashboard.php"
    return redirect(url(destination))


@app.route(f"{BASE_PATH}/login.php", methods=["GET", "POST"])
def login():
    error = None
    if request.method == "POST":
        email = request.form.get("email", "").strip().lower()
        password = request.form.get("password", "")
        conn = mysql_connection()
        try:
            cur = conn.cursor(dictionary=True)
            cur.execute("SELECT id, name, email, password_hash, role FROM users WHERE email=%s LIMIT 1", (email,))
            account = cur.fetchone()
        finally:
            conn.close()
        if account and verify_password(account["password_hash"], password):
            session["user"] = {"id": account["id"], "name": account["name"], "email": account["email"], "role": account["role"]}
            destination = "/admin/dashboard.php" if account["role"] == "admin" else "/user/dashboard.php"
            return redirect(url(destination))
        error = "Email atau password salah."
    return render_template("login.html", error=error, title="Login")


@app.get(f"{BASE_PATH}/logout.php")
def logout():
    session.clear()
    return redirect(url("/login.php"))


@app.route(f"{BASE_PATH}/register.php", methods=["GET", "POST"])
def register():
    error = None
    if request.method == "POST":
        name = request.form.get("name", "").strip()
        email = request.form.get("email", "").strip().lower()
        password = request.form.get("password", "")
        if len(name) < 2 or "@" not in email or len(password) < 8:
            error = "Nama, email, atau password tidak valid."
        elif password != request.form.get("password_confirm", ""):
            error = "Konfirmasi password tidak cocok."
        else:
            conn = mysql_connection()
            try:
                cur = conn.cursor()
                cur.execute("SELECT id FROM users WHERE email=%s", (email,))
                if cur.fetchone():
                    error = "Email sudah terdaftar."
                else:
                    cur.execute("INSERT INTO users (name,email,password_hash,role) VALUES (%s,%s,%s,'user')", (name, email, generate_password_hash(password)))
                    user_id = cur.lastrowid
                    cur.execute("INSERT INTO user_profiles (user_id,diet_tags) VALUES (%s,%s)", (user_id, "[]"))
                    conn.commit()
                    session["user"] = {"id": user_id, "name": name, "email": email, "role": "user"}
                    return redirect(url("/user/dashboard.php"))
            finally:
                conn.close()
    return render_template("register.html", error=error, title="Daftar")


@app.get(f"{BASE_PATH}/admin/dashboard.php")
@admin_required
def admin_dashboard():
    conn = mysql_connection()
    try:
        cur = conn.cursor()
        cur.execute("SELECT COUNT(*) FROM users")
        users = cur.fetchone()[0]
        cur.execute("SELECT COUNT(*) FROM categories")
        categories = cur.fetchone()[0]
    finally:
        conn.close()
    return render_template("dashboard.html", admin=True, stats={"products": mongo_collection().count_documents({"is_active": True}), "users": users, "categories": categories}, title="Admin Dashboard")


@app.get(f"{BASE_PATH}/user/dashboard.php")
@login_required
def user_dashboard():
    return render_template("dashboard.html", admin=False, products=products_from_mongo(limit=12), title="Beranda")


@app.route(f"{BASE_PATH}/admin/products.php", methods=["GET", "POST"])
@admin_required
def admin_products():
    if request.method == "POST" and request.form.get("action") == "delete":
        try:
            mongo_collection().delete_one({"_id": ObjectId(request.form.get("product_id", ""))})
            flash("Produk dihapus.", "success")
        except Exception:
            flash("ID produk tidak valid.", "danger")
        return redirect(url("/admin/products.php"))
    return render_template("admin_products.html", products=products_from_mongo(), title="Produk")


@app.route(f"{BASE_PATH}/admin/product_form.php", methods=["GET", "POST"])
@admin_required
def admin_product_form():
    product_id = request.args.get("id", "")
    existing = None
    if product_id:
        try:
            existing = mongo_collection().find_one({"_id": ObjectId(product_id)})
        except Exception:
            existing = None
    if request.method == "POST":
        fields = {
            "name": request.form.get("name", "").strip(),
            "brand": request.form.get("brand", "").strip(),
            "barcode": request.form.get("barcode", "").strip(),
            "category_id": int(request.form.get("category_id", 0)),
            "price": float(request.form.get("price", 0) or 0),
            "stock": int(request.form.get("stock", 0) or 0),
            "is_active": request.form.get("is_active") == "1",
            "popularity": int(request.form.get("popularity", 0) or 0),
            "allergens": [a for a in request.form.get("allergens", "").split(",") if a.strip()],
            "attributes": {"type": "food", "vegan": request.form.get("vegan") == "1", "halal_certified": request.form.get("halal") == "1"},
        }
        if not fields["name"]:
            flash("Nama produk wajib diisi.", "danger")
        else:
            upload = request.files.get("image")
            try:
                image_url = save_product_image(upload)
            except ValueError as error:
                flash(str(error), "danger")
            else:
                fields["image_url"] = image_url or (existing or {}).get("image_url")
                if existing:
                    mongo_collection().update_one({"_id": existing["_id"]}, {"$set": fields})
                    if image_url:
                        delete_product_image(existing.get("image_url"))
                    flash("Produk diperbarui.", "success")
                else:
                    mongo_collection().insert_one(fields)
                    flash("Produk ditambahkan.", "success")
                return redirect(url("/admin/products.php"))
    conn = mysql_connection()
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT id,name FROM categories ORDER BY name")
        categories = cur.fetchall()
    finally:
        conn.close()
    return render_template("product_form.html", product=existing, categories=categories, title="Form Produk")


@app.get(f"{BASE_PATH}/user/browse.php")
@login_required
def browse():
    return render_template("products.html", products=products_from_mongo(), title="Katalog")


@app.get(f"{BASE_PATH}/user/product.php")
@login_required
def product_detail():
    product_id = request.args.get("id", "")
    try:
        product = mongo_collection().find_one({"_id": ObjectId(product_id)})
    except Exception:
        product = None
    if not product:
        return redirect(url("/user/browse.php"))
    product["_id"] = str(product["_id"])
    product["category_name"] = category_map().get(product.get("category_id"), "-")
    return render_template("product.html", product=product, title=product.get("name", "Produk"))


@app.get(f"{BASE_PATH}/admin/users.php")
@admin_required
def admin_users():
    conn = mysql_connection()
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT id,name,email,role,created_at FROM users ORDER BY id")
        users = cur.fetchall()
    finally:
        conn.close()
    return render_template("users.html", users=users, title="User")


@app.route(f"{BASE_PATH}/admin/categories.php", methods=["GET", "POST"])
@admin_required
def admin_categories():
    conn = mysql_connection()
    try:
        cur = conn.cursor(dictionary=True)
        if request.method == "POST":
            action = request.form.get("action")
            category_id = request.form.get("id")
            if action == "delete":
                cur.execute("DELETE FROM categories WHERE id=%s", (category_id,))
            elif action == "update":
                cur.execute("UPDATE categories SET name=%s,slug=%s WHERE id=%s", (request.form.get("name"), request.form.get("slug"), category_id))
            else:
                cur.execute("INSERT INTO categories (name,slug,parent_id,sort_order) VALUES (%s,%s,NULL,0)", (request.form.get("name"), request.form.get("slug")))
            conn.commit()
            flash("Kategori berhasil diperbarui.", "success")
            return redirect(url("/admin/categories.php"))
        cur.execute("SELECT id, name, slug, parent_id, sort_order FROM categories ORDER BY parent_id, sort_order, name")
        categories = cur.fetchall()
    finally:
        conn.close()
    return render_template("master.html", heading="Kategori", kind="category", columns=["ID", "Nama", "Slug", "Induk"], rows=[[c["id"], c["name"], c["slug"], c["parent_id"] or "-"] for c in categories], title="Kategori")


@app.route(f"{BASE_PATH}/admin/allergens.php", methods=["GET", "POST"])
@admin_required
def admin_allergens():
    conn = mysql_connection()
    try:
        cur = conn.cursor(dictionary=True)
        if request.method == "POST":
            action = request.form.get("action")
            allergen_id = request.form.get("id")
            if action == "delete":
                cur.execute("DELETE FROM allergens WHERE id=%s", (allergen_id,))
            elif action == "update":
                cur.execute("UPDATE allergens SET name=%s,slug=%s WHERE id=%s", (request.form.get("name"), request.form.get("slug"), allergen_id))
            else:
                cur.execute("INSERT INTO allergens (name,slug) VALUES (%s,%s)", (request.form.get("name"), request.form.get("slug")))
            conn.commit()
            flash("Alergen berhasil diperbarui.", "success")
            return redirect(url("/admin/allergens.php"))
        cur.execute("SELECT id, name, slug FROM allergens ORDER BY name")
        allergens = cur.fetchall()
    finally:
        conn.close()
    return render_template("master.html", heading="Master Alergen", kind="allergen", columns=["ID", "Nama", "Slug"], rows=[[a["id"], a["name"], a["slug"]] for a in allergens], title="Alergen")


@app.get(f"{BASE_PATH}/admin/reviews.php")
@admin_required
def admin_reviews():
    documents = list(mongo_collection("contributions").find().sort("submitted_at", -1).limit(50))
    rows = [[str(doc["_id"]), doc.get("type", "-"), doc.get("user_id", "-"), doc.get("payload", {}).get("new_value", "-")] for doc in documents]
    return render_template("table.html", heading="Moderasi Kontribusi", columns=["ID", "Tipe", "User", "Nilai"], rows=rows, title="Review")


@app.get(f"{BASE_PATH}/user/bookmarks.php")
@login_required
def bookmarks():
    conn = mysql_connection()
    try:
        cur = conn.cursor()
        cur.execute("SELECT product_id FROM bookmarks WHERE user_id=%s ORDER BY created_at DESC", (current_user()["id"],))
        ids = [row[0] for row in cur.fetchall()]
    finally:
        conn.close()
    object_ids = []
    for product_id in ids:
        try:
            object_ids.append(ObjectId(product_id))
        except Exception:
            pass
    products = products_from_mongo({"_id": {"$in": object_ids}}) if object_ids else []
    return render_template("products.html", products=products, title="Favorit Saya")


@app.get(f"{BASE_PATH}/user/transactions.php")
@login_required
def transactions():
    conn = mysql_connection()
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute("SELECT id,total_amount,purchased_at FROM transactions WHERE user_id=%s ORDER BY purchased_at DESC", (current_user()["id"],))
        records = cur.fetchall()
        for record in records:
            cur.execute("SELECT product_name,qty,price FROM transaction_items WHERE transaction_id=%s ORDER BY id", (record["id"],))
            record["items"] = cur.fetchall()
    finally:
        conn.close()
    return render_template("transactions.html", transactions=records, title="Riwayat Belanja")


@app.route(f"{BASE_PATH}/user/profile.php", methods=["GET", "POST"])
@login_required
def profile():
    conn = mysql_connection()
    try:
        cur = conn.cursor(dictionary=True)
        if request.method == "POST":
            diets = [d for d in request.form.getlist("diet") if d in {"vegan", "vegetarian", "halal", "keto"}]
            cur.execute("UPDATE user_profiles SET diet_tags=%s, other_notes=%s WHERE user_id=%s", (str(diets).replace("'", '"'), request.form.get("other_notes", "")[:500], current_user()["id"]))
            conn.commit()
            flash("Profil kesehatan tersimpan.", "success")
            return redirect(url("/user/profile.php"))
        cur.execute("SELECT id,name,slug FROM allergens ORDER BY name")
        allergens = cur.fetchall()
        cur.execute("SELECT diet_tags,other_notes FROM user_profiles WHERE user_id=%s", (current_user()["id"],))
        profile_data = cur.fetchone() or {"diet_tags": "[]", "other_notes": ""}
    finally:
        conn.close()
    return render_template("profile.html", allergens=allergens, profile=profile_data, title="Profil Kesehatan")


@app.post(f"{BASE_PATH}/api/bookmark.php")
@login_required
def api_bookmark():
    product_id = request.form.get("product_id", "")
    try:
        ObjectId(product_id)
    except Exception:
        return jsonify({"ok": False, "error": "product_id tidak valid"}), 400
    conn = mysql_connection()
    try:
        cur = conn.cursor()
        cur.execute("SELECT 1 FROM bookmarks WHERE user_id=%s AND product_id=%s", (current_user()["id"], product_id))
        exists = cur.fetchone() is not None
        if exists:
            cur.execute("DELETE FROM bookmarks WHERE user_id=%s AND product_id=%s", (current_user()["id"], product_id))
        else:
            cur.execute("INSERT INTO bookmarks (user_id,product_id) VALUES (%s,%s)", (current_user()["id"], product_id))
        conn.commit()
    finally:
        conn.close()
    return jsonify({"ok": True, "bookmarked": not exists, "product_id": product_id})


@app.get(f"{BASE_PATH}/api/recommend.php")
@login_required
def api_recommend():
    limit = min(24, max(1, int(request.args.get("limit", 12))))
    return jsonify({"ok": True, "recommendations": [{"id": p["_id"], "name": p["name"], "price": p.get("price", 0), "brand": p.get("brand", "")} for p in products_from_mongo(limit=limit)]})


@app.get(f"{BASE_PATH}/api/autocomplete.php")
@login_required
def api_autocomplete():
    query = request.args.get("q", "").strip()
    if not query:
        return jsonify({"ok": True, "suggestions": []})
    regex = {"$regex": query, "$options": "i"}
    products = products_from_mongo({"$or": [{"name": regex}, {"brand": regex}, {"tags": regex}]}, limit=8)
    return jsonify({"ok": True, "suggestions": [{"id": p["_id"], "name": p["name"], "brand": p.get("brand", "")} for p in products]})


@app.get(f"{BASE_PATH}/api/product_search.php")
@login_required
def api_product_search():
    product_id = request.args.get("product", "")
    try:
        source = mongo_collection().find_one({"_id": ObjectId(product_id)})
    except Exception:
        source = None
    if not source:
        return jsonify({"ok": False, "error": "Produk tidak ditemukan"}), 404
    alternatives = products_from_mongo({"category_id": source.get("category_id"), "_id": {"$ne": source["_id"]}}, limit=4)
    return jsonify({"ok": True, "alternatives": [{"id": p["_id"], "name": p["name"], "brand": p.get("brand", ""), "price": p.get("price", 0)} for p in alternatives]})


@app.route(f"{BASE_PATH}/user/contribute.php", methods=["GET", "POST"])
@login_required
def contribute():
    product_id = request.args.get("product") or request.form.get("product_id", "")
    if request.method == "POST":
        contribution_type = request.form.get("type", "allergen_claim")
        payload = {"new_value": request.form.get("new_value", "")[:500], "note": request.form.get("note", "")[:1000]}
        try:
            object_id = ObjectId(product_id)
        except Exception:
            flash("Produk tidak valid.", "danger")
            return redirect(url("/user/browse.php"))
        result = mongo_collection("contributions").insert_one({"product_id": object_id, "user_id": current_user()["id"], "type": contribution_type, "payload": payload, "attachments": []})
        conn = mysql_connection()
        try:
            cur = conn.cursor()
            cur.execute("INSERT INTO contribution_status (contribution_id) VALUES (%s)", (str(result.inserted_id),))
            conn.commit()
        finally:
            conn.close()
        flash("Kontribusi berhasil dikirim untuk ditinjau.", "success")
        return redirect(url(f"/user/product.php?id={product_id}"))
    return render_template("contribute.html", product_id=product_id, title="Kontribusi")


@app.get(f"{BASE_PATH}/api/health")
def health():
    return {"ok": True, "mysql": mysql_connection().is_connected(), "mongo_products": mongo_collection().count_documents({})}


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=int(os.getenv("PORT", "8001")), debug=False)
