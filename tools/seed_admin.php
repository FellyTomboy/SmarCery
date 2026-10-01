<?php
/**
 * tools/seed_admin.php
 *
 * Helper CLI: creates an admin user (or resets password).
 *
 * Usage:
 *   php tools/seed_admin.php admin@smarcery.local "Admin SmarCery" admin123
 */

require __DIR__ . '/../src/core/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$email = $argv[1] ?? null;
$name  = $argv[2] ?? null;
$pass  = $argv[3] ?? null;

if (!$email || !$name || !$pass) {
    echo "Usage: php tools/seed_admin.php <email> <name> <password>\n";
    exit(1);
}

$pdo  = db_mysql();
$hash = password_hash($pass, PASSWORD_DEFAULT);

// Upsert
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);
$existing = $stmt->fetch();

if ($existing) {
    $pdo->prepare('UPDATE users SET name=?, password_hash=?, role="admin" WHERE id=?')
        ->execute([$name, $hash, $existing['id']]);
    echo "Updated existing user id={$existing['id']} -> admin ({$email})\n";
} else {
    $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, "admin")')
        ->execute([$name, $email, $hash]);
    $id = $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO user_profiles (user_id, diet_tags, other_notes) VALUES (?, JSON_ARRAY(), NULL)')
        ->execute([$id]);
    echo "Created admin id={$id} ({$email})\n";
}
