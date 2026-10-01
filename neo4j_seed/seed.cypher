// =========================================================================
// SmarCery — Neo4j seed
// Run via Neo4j Browser (http://localhost:7474) or
//   cypher-shell -u neo4j -p <password> < neo4j_seed/seed.cypher
// =========================================================================

// 0. Constraints & indexes
CREATE CONSTRAINT user_id       IF NOT EXISTS FOR (u:User)       REQUIRE u.id   IS UNIQUE;
CREATE CONSTRAINT product_id    IF NOT EXISTS FOR (p:Product)    REQUIRE p.id   IS UNIQUE;
CREATE CONSTRAINT allergen_slug IF NOT EXISTS FOR (a:Allergen)   REQUIRE a.slug IS UNIQUE;
CREATE CONSTRAINT diet_name     IF NOT EXISTS FOR (d:DietTag)    REQUIRE d.name IS UNIQUE;
CREATE INDEX product_allergen   IF NOT EXISTS FOR (p:Product)    ON (p.allergens);

// 1. Master allergens (mirror MySQL)
MERGE (a:Allergen {slug:'telur'})    SET a.name='Telur';
MERGE (a:Allergen {slug:'susu'})     SET a.name='Susu sapi';
MERGE (a:Allergen {slug:'gluten'})   SET a.name='Gluten';
MERGE (a:Allergen {slug:'kacang'})   SET a.name='Kacang tanah';
MERGE (a:Allergen {slug:'kedelai'})  SET a.name='Kedelai';
MERGE (a:Allergen {slug:'ikan'})     SET a.name='Ikan';
MERGE (a:Allergen {slug:'udang'})    SET a.name='Udang';
MERGE (a:Allergen {slug:'kerang'})   SET a.name='Kerang';
MERGE (a:Allergen {slug:'wijen'})    SET a.name='Wijen';
MERGE (a:Allergen {slug:'sulfit'})   SET a.name='Sulfit';

// 2. Diet tags
MERGE (d:DietTag {name:'vegan'});
MERGE (d:DietTag {name:'vegetarian'});
MERGE (d:DietTag {name:'halal'});
MERGE (d:DietTag {name:'keto'});

// 3. Users (mirror MySQL IDs)
MERGE (u:User {id:2}) SET u.name='Budi Santoso';
MERGE (u:User {id:3}) SET u.name='Siti Aminah';

// Budi -> HAS_ALLERGEN gluten
MATCH (u:User {id:2}), (a:Allergen {slug:'gluten'})
MERGE (u)-[r:HAS_ALLERGEN]->(a) SET r.severity='intolerance';

// Siti -> HAS_ALLERGEN susu & telur
MATCH (u:User {id:3}), (a:Allergen {slug:'susu'})
MERGE (u)-[:HAS_ALLERGEN]->(a);
MATCH (u:User {id:3}), (a:Allergen {slug:'telur'})
MERGE (u)-[:HAS_ALLERGEN]->(a);

// Budi vegetarian, Siti halal
MATCH (u:User {id:2}), (d:DietTag {name:'vegetarian'}) MERGE (u)-[:FOLLOWS_DIET]->(d);
MATCH (u:User {id:3}), (d:DietTag {name:'halal'})       MERGE (u)-[:FOLLOWS_DIET]->(d);

// 4. Products + allergen relations
MERGE (p:Product {id:'65f4e2a000000000000000a1'}) SET p.name='Biskuit Coklat Gandum', p.popularity=85, p.is_active=true;
MERGE (p:Product {id:'65f4e2a000000000000000a2'}) SET p.name='Kerupuk Udang Tradisional', p.popularity=70, p.is_active=true;
MERGE (p:Product {id:'65f4e2a000000000000000a3'}) SET p.name='Biskuit Rice Crackers Vegan', p.popularity=65, p.is_active=true;
MERGE (p:Product {id:'65f4e2a000000000000000a4'}) SET p.name='Kopi Sachihasta', p.popularity=90, p.is_active=true;
MERGE (p:Product {id:'65f4e2a000000000000000a5'}) SET p.name='Susu UHT Fullcream 1L', p.popularity=88, p.is_active=true;
MERGE (p:Product {id:'65f4e2a000000000000000a6'}) SET p.name='Bumbu Dapur Lengkap 8in1', p.popularity=60, p.is_active=true;
MERGE (p:Product {id:'65f4e2a000000000000000a7'}) SET p.name='Beras Premium 5kg', p.popularity=95, p.is_active=true;
MERGE (p:Product {id:'65f4e2a000000000000000a8'}) SET p.name='Apel Fuji', p.popularity=78, p.is_active=true;
MERGE (p:Product {id:'65f4e2a000000000000000a9'}) SET p.name='Brokoli Organik 500g', p.popularity=55, p.is_active=true;
MERGE (p:Product {id:'65f4e2a000000000000000aa'}) SET p.name='Sabun Mandi Cair Sensitif', p.popularity=50, p.is_active=true;
MERGE (p:Product {id:'65f4e2a000000000000000ab'}) SET p.name='Deterjen Cair Premium 1L', p.popularity=62, p.is_active=true;

// Set allergen list on each product
MATCH (p:Product {id:'65f4e2a000000000000000a1'}) SET p.allergens=['gluten','telur','susu'];
MATCH (p:Product {id:'65f4e2a000000000000000a2'}) SET p.allergens=['udang','gluten'];
MATCH (p:Product {id:'65f4e2a000000000000000a3'}) SET p.allergens=['kedelai'];
MATCH (p:Product {id:'65f4e2a000000000000000a4'}) SET p.allergens=[];
MATCH (p:Product {id:'65f4e2a000000000000000a5'}) SET p.allergens=['susu'];
MATCH (p:Product {id:'65f4e2a000000000000000a6'}) SET p.allergens=['kedelai'];
MATCH (p:Product {id:'65f4e2a000000000000000a7'}) SET p.allergens=[];
MATCH (p:Product {id:'65f4e2a000000000000000a8'}) SET p.allergens=[];
MATCH (p:Product {id:'65f4e2a000000000000000a9'}) SET p.allergens=[];
MATCH (p:Product {id:'65f4e2a000000000000000aa'}) SET p.allergens=[];
MATCH (p:Product {id:'65f4e2a000000000000000ab'}) SET p.allergens=[];

// 5. Product -> Allergen relations
MATCH (p:Product {id:'65f4e2a000000000000000a1'}), (a:Allergen {slug:'gluten'}) MERGE (p)-[:CONTAINS_ALLERGIEN]->(a);
MATCH (p:Product {id:'65f4e2a000000000000000a1'}), (a:Allergen {slug:'telur'})  MERGE (p)-[:CONTAINS_ALLERGIEN]->(a);
MATCH (p:Product {id:'65f4e2a000000000000000a1'}), (a:Allergen {slug:'susu'})   MERGE (p)-[:CONTAINS_ALLERGIEN]->(a);
MATCH (p:Product {id:'65f4e2a000000000000000a2'}), (a:Allergen {slug:'udang'})  MERGE (p)-[:CONTAINS_ALLERGIEN]->(a);
MATCH (p:Product {id:'65f4e2a000000000000000a2'}), (a:Allergen {slug:'gluten'}) MERGE (p)-[:CONTAINS_ALLERGIEN]->(a);
MATCH (p:Product {id:'65f4e2a000000000000000a3'}), (a:Allergen {slug:'kedelai'}) MERGE (p)-[:CONTAINS_ALLERGIEN]->(a);
MATCH (p:Product {id:'65f4e2a000000000000000a5'}), (a:Allergen {slug:'susu'})   MERGE (p)-[:CONTAINS_ALLERGIEN]->(a);
MATCH (p:Product {id:'65f4e2a000000000000000a6'}), (a:Allergen {slug:'kedelai'}) MERGE (p)-[:CONTAINS_ALLERGIEN]->(a);

// 6. Product -> DietTag compatibility
MATCH (p:Product {id:'65f4e2a000000000000000a3'}), (d:DietTag {name:'vegan'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);
MATCH (p:Product {id:'65f4e2a000000000000000a4'}), (d:DietTag {name:'vegan'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);
MATCH (p:Product {id:'65f4e2a000000000000000a6'}), (d:DietTag {name:'vegan'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);
MATCH (p:Product {id:'65f4e2a000000000000000a7'}), (d:DietTag {name:'vegan'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);
MATCH (p:Product {id:'65f4e2a000000000000000a8'}), (d:DietTag {name:'vegan'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);
MATCH (p:Product {id:'65f4e2a000000000000000a9'}), (d:DietTag {name:'vegan'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);
MATCH (p:Product {id:'65f4e2a000000000000000aa'}), (d:DietTag {name:'vegan'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);
MATCH (p:Product {id:'65f4e2a000000000000000ab'}), (d:DietTag {name:'vegan'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);
MATCH (p:Product {id:'65f4e2a000000000000000a3'}), (d:DietTag {name:'vegetarian'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);
MATCH (p:Product {id:'65f4e2a000000000000000a4'}), (d:DietTag {name:'vegetarian'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);
MATCH (p:Product {id:'65f4e2a000000000000000a6'}), (d:DietTag {name:'vegetarian'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);
MATCH (p:Product {id:'65f4e2a000000000000000a7'}), (d:DietTag {name:'vegetarian'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);
MATCH (p:Product {id:'65f4e2a000000000000000a8'}), (d:DietTag {name:'vegetarian'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);
MATCH (p:Product {id:'65f4e2a000000000000000a9'}), (d:DietTag {name:'vegetarian'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);
MATCH (p:Product {id:'65f4e2a000000000000000aa'}), (d:DietTag {name:'vegetarian'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);
MATCH (p:Product {id:'65f4e2a000000000000000ab'}), (d:DietTag {name:'vegetarian'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);
// Halal: semua kecuali non-halal (semua produk di seed adalah halal)
MATCH (p:Product), (d:DietTag {name:'halal'}) MERGE (p)-[:COMPATIBLE_WITH]->(d);

// 7. Substitution edges (simetris)
MATCH (a:Product {id:'65f4e2a000000000000000a1'}), (b:Product {id:'65f4e2a000000000000000a3'})
MERGE (a)-[:SUBSTITUTE_OF {score:0.7}]-(b);
MATCH (a:Product {id:'65f4e2a000000000000000a2'}), (b:Product {id:'65f4e2a000000000000000a7'})
MERGE (a)-[:SUBSTITUTE_OF {score:0.4}]-(b);
MATCH (a:Product {id:'65f4e2a000000000000000a5'}), (b:Product {id:'65f4e2a000000000000000a8'})
MERGE (a)-[:SUBSTITUTE_OF {score:0.5}]-(b);

// --- quick verification queries (commented out, run manually) ---
// MATCH (p:Product)-[:CONTAINS_ALLERGIEN]->(a:Allergen) RETURN p.name, collect(a.slug);
// MATCH (u:User {id:2})-[r:HAS_ALLERGEN]->(a) RETURN u.name, collect(a.slug);
