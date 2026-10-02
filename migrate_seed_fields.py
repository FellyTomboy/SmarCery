import os
from dotenv import load_dotenv
from pymongo import MongoClient

load_dotenv()
col = MongoClient(os.getenv("MONGO_URI")).smarcery.products
doc = col.find_one({"name": "Beras Premium 5kg"})
print(type(doc["_id"]), doc["_id"])
print(doc["attributes"])