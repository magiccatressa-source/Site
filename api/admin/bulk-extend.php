<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/response.php';

header('Content-Type: application/json; charset=utf-8');
only_post();
$admin = require_admin();
csrf_verify();

$data = body();
$days = (int)($data['days'] ?? 0);

if ($days < 1 || $days > 365) json_err('invalid_days');

// Продление теперь в разрезе продукта. Без параметра — клуб,
// чтобы существующая кнопка в админке работала как раньше.
$productId = (int)($data['product_id'] ?? 0) ?: club_product_id();
$product   = product_by_id($productId);

$count = bulk_extend_product($productId, $days);

log_admin($admin['id'], 'access.bulk_extend', '', 0, [
    'product'  => $product['slug'] ?? $productId,
    'days'     => $days,
    'affected' => $count,
]);

json_ok(['affected' => $count]);
