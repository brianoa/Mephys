<?php
require_once "db.php";

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: crud.php");
    exit;
}

// Fetch the appointment
$stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
$stmt->execute([$id]);
$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    echo "Appointment not found.";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $service = $_POST['service'];
    $name = $_POST['fullName'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    $notes = $_POST['notes'];

    $stmt = $pdo->prepare("UPDATE appointments SET service = ?, full_name = ?, email = ?, phone = ?, appointment_date = ?, appointment_time = ?, notes = ? WHERE id = ?");
    $stmt->execute([$service, $name, $email, $phone, $date, $time, $notes, $id]);

    header("Location: crud.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Appointment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7f9;
        }
        .form-container {
            margin-top: 50px;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 {
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="form-container">
        <h2 class="mb-4 text-center">Edit Appointment</h2>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Service</label>
                <input type="text" class="form-control" name="service" value="<?= htmlspecialchars($appointment['service']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" name="fullName" value="<?= htmlspecialchars($appointment['full_name']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($appointment['email']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($appointment['phone']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" name="date" value="<?= htmlspecialchars($appointment['appointment_date']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Time</label>
                <input type="time" class="form-control" name="time" value="<?= htmlspecialchars($appointment['appointment_time']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="notes" rows="3"><?= htmlspecialchars($appointment['notes']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100">Update Appointment</button>
            <a href="crud.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
        </form>
    </div>
</div>

</body>
</html>
