<?php
// Simple test to render part of POS cart logic
$html = file_get_contents('c:/xampp/htdocs/kts_grocery/pages/pos.php');
if (strpos($html, 'editInline(') !== false && strpos($html, 'triggerF2()') !== false) {
    echo "Inline edit logic is present in pos.php.\n";
    if (strpos($html, 'inline-qty-input') !== false && strpos($html, 'ondblclick="editInline(') !== false) {
        echo "Double-click trigger and inline input CSS classes found successfully.\n";
    } else {
        echo "WARNING: Missing inline edit input or double-click trigger.\n";
    }
} else {
    echo "ERROR: Missing primary inline editing logic.\n";
}
