-- =========================================================================
-- Seed categories — supermarket hierarchy
-- =========================================================================
USE smarcery;

INSERT INTO categories (id, parent_id, name, slug, icon, sort_order) VALUES
    (1,  NULL, 'Makanan',           'makanan',           '🍚', 1),
    (2,  1,    'Makanan Ringan',    'makanan-ringan',    '🍪', 1),
    (3,  1,    'Bumbu Dapur',       'bumbu-dapur',       '🧂', 2),
    (4,  1,    'Bahan Pokok',       'bahan-pokok',       '🌾', 3),
    (5,  1,    'Minuman',           'minuman',           '🥤', 4),

    (6,  NULL, 'Produk Segar',      'produk-segar',      '🥬', 2),
    (7,  6,    'Buah',              'buah',              '🍎', 1),
    (8,  6,    'Sayur',             'sayur',             '🥦', 2),

    (10, NULL, 'Perawatan & Kebersihan', 'perawatan',    '🧴', 3),
    (11, 10,   'Sabun & Sampo',     'sabun-sampo',       '🧼', 1),
    (12, 10,   'Peralatan Mandi',   'peralatan-mandi',   '🪥', 2),

    (13, NULL, 'Rumah Tangga',      'rumah-tangga',      '🏡', 1),
    (14, 13,   'Deterjen',          'deterjen',          '🧺', 1);