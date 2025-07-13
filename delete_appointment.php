<?php
require_once "db.php";

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);

    try {
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = :id");
        $stmt->execute([':id' => $id]);

        // Redirect back to the main CRUD page after deletion
        header("Location: crud.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        echo "Error deleting appointment: " . $e->getMessage();
    }
} else {
    echo "Invalid ID.";
}
?>
