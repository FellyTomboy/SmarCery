# migrate_seed_fields.py
import os
from dotenv import load_dotenv
from pymongo import MongoClient

load_dotenv()
col = MongoClient(os.getenv("MONGO_URI")).smarcery.products

updated = 0
for doc in col.find({}):
    attrs = doc.get("attributes") or {}
    changed = False

    # ingredients -> composition
    if "ingredients" in attrs and "composition" not in attrs:
        attrs["composition"] = attrs.pop("ingredients")
        changed = True

    # normalisasi nutrisi
    for key, basis in (("nutrition_per_100g", "per 100g"), ("nutrition_per_100ml", "per 100ml")):
        nutrition = attrs.get(key)
        if not nutrition or "basis" in nutrition:
            continue
        fixed = {"basis": basis}
        if "calories" in nutrition:
            fixed["calories_kcal"] = nutrition["calories"]
        for field in ("protein_g", "fat_g", "sugar_g"):
            if field in nutrition:
                fixed[field] = nutrition[field]
        if "carbs_g" in nutrition:
            fixed["carbohydrates_g"] = nutrition["carbs_g"]
        if "salt_g" in nutrition:
            # perkiraan: 1 g garam ≈ 393 mg natrium
            fixed["sodium_mg"] = round(nutrition["salt_g"] * 393.4, 1)
        attrs[key] = fixed
        changed = True

    if changed:
        col.update_one({"_id": doc["_id"]}, {"$set": {"attributes": attrs}})
        updated += 1

print(f"{updated} produk diperbarui")