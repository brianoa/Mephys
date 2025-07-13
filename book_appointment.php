<?php
require_once "db.php"; // Assuming db.php contains your PDO connection code

require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
        // Initialize PHPMailer
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Your SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'omaebrian03@gmail.com'; // Your clinic email
        $mail->Password = 'rixs ncrg wetz ytnn'; // Use app password if needed
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // --------------------------------------
        // Send confirmation to the client
        // --------------------------------------
        $mail->setFrom('omaebrian03@gmail.com', 'Mephys Physiotherapy Clinic');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = "Appointment Confirmation - Mephys Clinic";
        $mail->Body = "
            <h3>Dear $name,</h3>
            <p>Thank you for booking an appointment with <strong>Mephys Physiotherapy Clinic</strong>.</p>
            <p>We look forward to seeing you!</p>
            <br>
            <p>Warm regards,<br><strong>Mephys Clinic Team</strong></p>
        ";
        $mail->send();

        // --------------------------------------
        // Send notification to clinic
        // --------------------------------------
        $mail->clearAddresses();
        $mail->addAddress('omaebrian03@gmail.com', 'Clinic Admin');
        $mail->Subject = "New Appointment Booked by $name";
        $mail->Body = "
            <h3>New appointment received</h3>
            <p><strong>Name:</strong> $name<br>
               <strong>Email:</strong> $email<br>
               <strong>Phone:</strong> $phone<br>
               <strong>Service:</strong> $service<br>
               <strong>Date:</strong> $date<br>
               <strong>Time:</strong> $time<br>
               <strong>Notes:</strong> $notes</p>
        ";
        $mail->send();

        // Redirect back to the CRUD list
        header("Location: crud.php");
        exit;
        //echo "Appointment submitted successfully!";
    } catch (Exception $e) {
        http_response_code(500);
        echo "Mailer Error: " . $mail->ErrorInfo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo "Database error: " . $e->getMessage();
    }
} else {
    http_response_code(400);
    echo "Invalid request method.";
}
?>
