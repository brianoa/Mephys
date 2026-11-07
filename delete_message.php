<?php
require 'db.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);

    try {
        $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->execute([$id]);

        header("Location: contact_crud.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        die("Error deleting: " . $e->getMessage());
    }
} else {
    echo "Invalid ID.";
}
