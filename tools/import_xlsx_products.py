"""Import the food and drink spreadsheets into SmarCery's databases.

Products are stored in MongoDB, while category/allergen master data is kept in
MySQL and the product graph is mirrored to Neo4j. The import is repeatable:
product ids are derived from the source file and row, and MongoDB/Neo4j use
upserts.
"""

from __future__ import annotations

import argparse
import hashlib
import os
import re
import sys
from datetime import datetime, timezone
from pathlib import Path
from typing import Any
from zipfile import ZipFile
from xml.etree import ElementTree


ROOT = Path(__file__).resolve().parents[1]
NS = "{http://schemas.openxmlformats.org/spreadsheetml/2006/main}"
ALLERGEN_RULES = (
    ("kacang tanah|peanut", "kacang"),
    ("kedelai|soy|soya", "kedelai"),
    ("susu|milk|whey|keju|yogurt", "susu"),
    ("telur|egg", "telur"),
    ("gluten|gandum|terigu|barley|rye|oat", "gluten"),
    ("ikan|fish", "ikan"),
    ("udang|shrimp|krustase", "udang"),
    ("kerang|moluska", "kerang"),
    ("wijen|sesame", "wijen"),
    ("sulfit|sulfite", "sulfit"),
)
ALLERGEN_NAMES = {
    "telur": "Telur",
    "susu": "Susu sapi",
    "gluten": "Gluten",
    "kacang": "Kacang tanah",
    "kedelai": "Kedelai",
    "ikan": "Ikan",
    "udang": "Udang",
    "kerang": "Kerang",
    "wijen": "Wijen",
    "sulfit": "Sulfit",
}


def clean(value: Any) -> str:
    return re.sub(r"\s+", " ", str(value or "")).strip()


def column_number(reference: str) -> int:
    letters = re.match(r"[A-Z]+", reference.upper()).group(0)
    number = 0
    for letter in letters:
        number = number * 26 + ord(letter) - ord("A") + 1
    return number


def xlsx_rows(path: Path) -> list[list[str]]:
    with ZipFile(path) as workbook:
        strings: list[str] = []
        if "xl/sharedStrings.xml" in workbook.namelist():
            shared = ElementTree.fromstring(workbook.read("xl/sharedStrings.xml"))
            for item in shared.findall(NS + "si"):
                strings.append("".join(node.text or "" for node in item.iter(NS + "t")))

        sheet = ElementTree.fromstring(workbook.read("xl/worksheets/sheet1.xml"))
        rows: list[list[str]] = []
        for row in sheet.findall(".//" + NS + "row"):
            values: dict[int, str] = {}
            for cell in row.findall(NS + "c"):
                cell_type = cell.get("t")
                value = cell.find(NS + "v")
                text = "" if value is None else value.text or ""
                if cell_type == "s" and text:
                    text = strings[int(text)]
                elif cell_type == "inlineStr":
                    text = "".join(node.text or "" for node in cell.iter(NS + "t"))
                values[column_number(cell.get("r", "A1"))] = text
            if values:
                rows.append([values.get(index, "") for index in range(1, max(values) + 1)])
        return rows


def detect_allergens(composition: str) -> list[str]:
    text = composition.casefold()
    found = []
    for pattern, slug in ALLERGEN_RULES:
        if re.search(pattern, text) and slug not in found:
            found.append(slug)
    return found


def product_id(source: Path, row_number: int, brand: str) -> str:
    key = f"{source.name}:{row_number}:{brand.casefold()}".encode()
    return hashlib.md5(key).hexdigest()[:24]


def read_products() -> list[dict[str, Any]]:
    products = []
    sources = ((ROOT / "makanan data.xlsx", "food", 1), (ROOT / "minuman data.xlsx", "drink", 5))
    for source, product_type, category_id in sources:
        if not source.exists():
            raise FileNotFoundError(source)
        rows = xlsx_rows(source)
        if len(rows) < 3 or [clean(value).casefold() for value in rows[1][:3]] != ["brand", "komposisi", "nutrisi"]:
            raise ValueError(f"Header XLSX tidak sesuai: {source}")
        for row_number, row in enumerate(rows[2:], start=3):
            brand = clean(row[0] if len(row) > 0 else "")
            composition = clean(row[1] if len(row) > 1 else "")
            if not brand:
                continue
            allergens = detect_allergens(composition)
            products.append(
                {
                    "id": product_id(source, row_number, brand),
                    "name": brand.title(),
                    "brand": brand,
                    "category_id": category_id,
                    "type": product_type,
                    "composition": composition,
                    "allergens": allergens,
                    "source": source.name,
                    "source_row": row_number,
                }
            )
    return products


def import_mysql(products: list[dict[str, Any]]) -> None:
    import mysql.connector

    connection = mysql.connector.connect(
        host=os.getenv("DB_HOST", "127.0.0.1"),
        port=int(os.getenv("DB_PORT", "3306")),
        database=os.getenv("DB_NAME", "smarcery"),
        user=os.getenv("DB_USER", "root"),
        password=os.getenv("DB_PASS", "root"),
    )
    try:
        cursor = connection.cursor()
        cursor.executemany(
            """INSERT INTO categories (id, parent_id, name, slug, sort_order)
               VALUES (%s, NULL, %s, %s, %s)
               ON DUPLICATE KEY UPDATE name=VALUES(name), parent_id=NULL""",
            [(1, "Makanan", "makanan", 1), (5, "Minuman", "minuman", 4)],
        )
        cursor.executemany(
            """INSERT INTO allergens (name, slug) VALUES (%s, %s)
               ON DUPLICATE KEY UPDATE name=VALUES(name)""",
            [(name, slug) for slug, name in ALLERGEN_NAMES.items()],
        )
        connection.commit()
        print(f"MySQL: master data siap ({len(products)} produk direferensikan).")
    finally:
        connection.close()


def import_mongo(products: list[dict[str, Any]]) -> None:
    from bson import ObjectId
    from pymongo import MongoClient

    client = MongoClient(os.getenv("MONGO_URI", "mongodb://127.0.0.1:27017"))
    collection = client[os.getenv("MONGO_DB", "smarcery")]["products"]
    now = datetime.now(timezone.utc)
    for product in products:
        document = {
            "_id": ObjectId(product["id"]),
            "category_id": product["category_id"],
            "name": product["name"],
            "brand": product["brand"],
            "barcode": None,
            "price": 0,
            "stock": 0,
            "image_url": None,
            "allergens": product["allergens"],
            "attributes": {
                "type": product["type"],
                "composition": product["composition"],
                "nutrition": None,
                "source": product["source"],
                "source_row": product["source_row"],
            },
            "tags": [product["type"], *product["allergens"]],
            "is_active": True,
            "popularity": 0,
            "updated_at": now,
        }
        collection.replace_one({"_id": document["_id"]}, document, upsert=True)
    collection.create_index("category_id")
    collection.create_index("allergens")
    client.close()
    print(f"MongoDB: {len(products)} produk di-upsert.")


def import_neo4j(products: list[dict[str, Any]]) -> None:
    from neo4j import GraphDatabase

    driver = GraphDatabase.driver(
        os.getenv("NEO4J_URI", "bolt://127.0.0.1:7687"),
        auth=(os.getenv("NEO4J_USER", "neo4j"), os.getenv("NEO4J_PASSWORD", "")),
    )
    query = """
    MERGE (p:Product {id: $id})
    SET p.name=$name, p.brand=$brand, p.category_id=$category_id,
        p.type=$type, p.allergens=$allergens, p.is_active=true
    WITH p
    MERGE (c:Category {id: $category_id})
    SET c.name=$category_name
    MERGE (p)-[:BELONGS_TO]->(c)
    WITH p
    OPTIONAL MATCH (p)-[old:CONTAINS_ALLERGIEN]->()
    DELETE old
    WITH p
    UNWIND $allergens AS allergen_slug
    MERGE (a:Allergen {slug: allergen_slug})
    SET a.name = allergen_name(allergen_slug)
    MERGE (p)-[:CONTAINS_ALLERGIEN]->(a)
    """
    # Neo4j cannot call a Python function from Cypher, so allergen names are
    # passed as a map and applied in a separate, parameterized query.
    product_query = query.replace("allergen_name(allergen_slug)", "names[allergen_slug]")
    with driver.session() as session:
        for product in products:
            session.run(
                product_query,
                **product,
                category_name="Makanan" if product["category_id"] == 1 else "Minuman",
                names=ALLERGEN_NAMES,
            ).consume()
    driver.close()
    print(f"Neo4j: {len(products)} produk dan relasinya di-upsert.")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dry-run", action="store_true", help="Baca dan validasi XLSX tanpa koneksi database")
    parser.add_argument("--skip-mysql", action="store_true")
    parser.add_argument("--skip-mongo", action="store_true")
    parser.add_argument("--skip-neo4j", action="store_true")
    args = parser.parse_args()

    products = read_products()
    print(f"Ditemukan {len(products)} produk dari XLSX.")
    if args.dry_run:
        for product in products:
            print(f"- {product['type']}: {product['name']} [{', '.join(product['allergens']) or 'tanpa alergen'}]")
        return 0
    if not args.skip_mysql:
        import_mysql(products)
    if not args.skip_mongo:
        import_mongo(products)
    if not args.skip_neo4j:
        import_neo4j(products)
    return 0


if __name__ == "__main__":
    sys.exit(main())