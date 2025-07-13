<?php require_once "db.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointments CRUD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7f9;
            font-family: 'Segoe UI', sans-serif;
        }
        .container {
            margin-top: 50px;
        }
        .table-container {
            background-color: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.08);
        }
        .btn-space {
            margin-right: 10px;
        }
        h2 {
            font-weight: 600;
            color: #2c3e50;
        }
    </style>
</head>
<body>

<div class="container">
    <h2 class="mb-4 text-center">Manage Appointments</h2>
    <div class="table-container">
        <a href="add_appointment.php" class="btn btn-success mb-3">+ New Appointment</a>
        <div class="mb-3 d-flex gap-2">
            <a href="appointments_export.php?type=pdf" class="btn btn-danger">Export PDF</a>
            <a href="appointments_export.php?type=excel" class="btn btn-success">Export Excel</a>
            <a href="appointments_export.php?type=csv" class="btn btn-primary">Export CSV</a>
        </div>

        <table class="table table-hover table-bordered align-middle text-center">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Service</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query("SELECT * FROM appointments ORDER BY appointment_date DESC");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['service']) ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['phone']) ?></td>
                    <td><?= htmlspecialchars($row['appointment_date']) ?></td>
                    <td><?= htmlspecialchars($row['appointment_time']) ?></td>
                    <td><?= htmlspecialchars($row['notes']) ?></td>
                    <td>
                        <a href="update_appointment.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary btn-space">Edit</a>
                        <a href="delete_appointment.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger"
                           onclick="return confirm('Are you sure you want to delete this appointment?');">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
