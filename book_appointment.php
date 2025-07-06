<?php
require_once "db.php"; // Assuming db.php contains your PDO connection code

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $service = $_POST['service'] ?? '';
    $name = $_POST['fullName'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $notes = $_POST['notes'] ?? '';

    try {
        $stmt = $pdo->prepare("INSERT INTO appointments (service, full_name, email, phone, appointment_date,appointment_time, notes)
                               VALUES (:service, :name, :email, :phone, :date, :time, :notes)");
        $stmt->execute([
            ":service" => $service,
            ":name" => $name,
            ":email" => $email,
            ":phone" => $phone,
            ":date" => $date,
            ":time" => $time,
            ":notes" => $notes
        ]);

        echo "Appointment submitted successfully!";
    } catch (PDOException $e) {
        http_response_code(500);
        echo "Database error: " . $e->getMessage();
    }
} else {
    http_response_code(400);
    echo "Invalid request method.";
}
?>
