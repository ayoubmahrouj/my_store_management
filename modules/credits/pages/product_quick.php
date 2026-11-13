<?php
declare(strict_types=1);

require_login();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$token = $_POST['_token'] ?? '';

if ($token === '' || ! is_string($token) || ! hash_equals($_SESSION['csrf'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['error' => t('error_required')]);
    exit;
}

$brand = trim((string) post('brand'));
$name = trim((string) post('name'));
$description = trim((string) post('description'));
$price = post_float('price');

if ($name === '') {
    http_response_code(422);
    echo json_encode(['error' => t('error_required')]);
    exit;
}

$produits = new \Gos\Domain\Repositories\ProduitRepository();
$existing = $produits->findByDesignationAndBrand($name, $brand);

if ($existing !== null) {
    if ($existing->isActive()) {
        http_response_code(409);
        echo json_encode(['error' => t('product_exists')]);
        exit;
    }

    $produits->reactivate($existing->id(), $price, $description);
    $id = $existing->id();
} else {
    $marqueId = $brand !== ''
        ? (new \Gos\Domain\Repositories\MarqueRepository())->quickCreate($brand)->id()
        : null;

    $id = $produits->save(new \Gos\Domain\Entities\Produit(
        null,
        $name,
        null,
        null,
        $description,
        $marqueId,
        null,
        null,
        0.0,
        $price,
        null,
        0.0,
        true,
        $brand,
    ));
}

echo json_encode([
    'id' => $id,
    'name' => $name . ($brand !== '' ? ' — ' . $brand : ''),
    'brand' => $brand,
    'price' => $price,
]);
