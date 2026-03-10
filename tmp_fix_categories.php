<?php
require_once 'config/db.php';
$db = getDB();

try {
    $db->beginTransaction();

    echo "--- Category Deduplication Start ---\n";

    // 1. Get all categories and group them by name
    $allCats = $db->query("SELECT id, name FROM categories ORDER BY id ASC")->fetchAll();
    $nameToIds = [];
    foreach ($allCats as $cat) {
        $nameToIds[trim($cat['name'])][] = $cat['id'];
    }

    foreach ($nameToIds as $name => $ids) {
        if (count($ids) > 1) {
            $primaryId = $ids[0];
            $duplicateIds = array_slice($ids, 1);
            $duplicateIdsStr = implode(',', $duplicateIds);

            echo "Processing category: '$name' (Keep ID: $primaryId, Duplicates: $duplicateIdsStr)\n";

            // Update products to use the primary ID
            $stmt = $db->prepare("UPDATE products SET category_id = ? WHERE category_id IN ($duplicateIdsStr)");
            $stmt->execute([$primaryId]);
            echo "  Updated products for '$name'.\n";

            // Delete duplicate categories
            $db->exec("DELETE FROM categories WHERE id IN ($duplicateIdsStr)");
            echo "  Deleted duplicate categories for '$name'.\n";
        }
    }

    // 2. Add UNIQUE constraint to name
    echo "Adding UNIQUE constraint to categories.name...\n";
    try {
        $db->exec("ALTER TABLE categories ADD UNIQUE (name)");
        echo "  UNIQUE constraint added successfully.\n";
    } catch (Exception $e) {
        echo "  Warning: Could not add UNIQUE constraint (maybe it already exists?): " . $e->getMessage() . "\n";
    }

    // 3. Update categories to standard supermarket set
    $standardCats = [
        'Fruits & Vegetables',
        'Dairy & Eggs',
        'Bakery & Bread',
        'Meat & Seafood',
        'Beverages',
        'Snacks & Confectionery',
        'Frozen Foods',
        'Rice, Pasta & Grains',
        'Spices, Oils & Sauces',
        'Canned & Packaged Foods',
        'Household & Cleaning',
        'Personal Care',
        'Baby Care',
        'Pet Care'
    ];

    echo "Seeding/Updating to standard categories...\n";
    $stmt = $db->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
    foreach ($standardCats as $cat) {
        $stmt->execute([$cat]);
    }
    echo "  Standard categories seeded.\n";

    $db->commit();
    echo "--- Category Deduplication Complete ---\n";

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo "ERROR: " . $e->getMessage() . "\n";
}
