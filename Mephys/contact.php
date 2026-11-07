<?php
require 'db.php'; // Includes your DB connection

// Include PHPMailer classes manually
require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $message = $_POST['message'] ?? '';

    if (empty($name) || empty($email) || empty($message)) {
        echo "All fields are required.";
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO messages (name, email, message) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $message]);

         // Setup PHPMailer
        $mail = new PHPMailer(true);

        // SMTP settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';        // Replace with your SMTP host
        $mail->SMTPAuth = true;
        $mail->Username = 'omaebrian03@gmail.com';  // Replace with your clinic's email
        $mail->Password = 'rixs ncrg wetz ytnn';        // Replace with email app password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;


        // -----------------------
        // Send confirmation to user
        // -----------------------

        $mail->setFrom('omaebrian03@gmail.com', 'Mephys Physiotherapy Clinic');
        $mail->addAddress($email, $name); // To user
        $mail->isHTML(true);
        $mail->Subject = "Thank you for contacting Mephys Clinic";
        $mail->Body = "
            <h3>Dear $name,</h3>
            <p>Thank you for reaching out to us. We’ve received your message:</p>
            <p>Our team will get back to you shortly.</p>
            <br><p>Warm regards,<br><strong>Mephys Physiotherapy Clinic</strong></p>
        ";
        //<blockquote>$message</blockquote>
        $mail->send();



        // -----------------------
        // Send notification to clinic
        // -----------------------
        
        $mail->clearAddresses(); // clear previous recipient
        $mail->addAddress('omaebrian03@gmail.com', 'Clinic Admin'); // To clinic
        $mail->Subject = "New Contact Form Submission from $name";
        $mail->Body = "
            <h3>New message received from website</h3>
            <p><strong>Name:</strong> $name</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Message:</strong><br>$message</p>
        ";
        $mail->send();


        echo "success";
    } catch (Exception $e) {
        echo "Mailer Error: " . $email->ErrorInfo();
    } catch (PDOException $e){
        echo "Database error: " . $e->getMessage();
    }
} else {
    echo "Invalid request method.";
}
