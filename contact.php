<?php
<<<<<<< HEAD

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Load database and email configuration.
require_once __DIR__ . '/db.php';

$mailConfig = require __DIR__ . '/smtp-config.php';

// Load PHPMailer library.
require_once __DIR__ . '/src/Exception.php';
require_once __DIR__ . '/src/PHPMailer.php';
require_once __DIR__ . '/src/SMTP.php';

// Accept POST requests only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method.');
}

// Collect submitted form data.
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validate required fields.
if ($name === '' || $email === '' || $message === '') {
    http_response_code(400);
    exit('All fields are required.');
}

// Validate email address.
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('Please enter a valid email address.');
}

// Validate field lengths.
if (mb_strlen($name) > 150 || mb_strlen($email) > 255) {
    http_response_code(400);
    exit('Some submitted information is too long.');
}

try {

    // Save the contact message in the database.
    $stmt = $pdo->prepare(
        "INSERT INTO messages (name, email, message)
         VALUES (:name, :email, :message)"
    );

    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':message' => $message
    ]);

    // Prepare reusable email settings.
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = $mailConfig['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $mailConfig['username'];
    $mail->Password = $mailConfig['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $mailConfig['port'];

    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->isHTML(true);

    // Sender details.
    $mail->setFrom(
        $mailConfig['username'],
        'Mephys Physiotherapy Clinic'
    );

    // Send notification to clinic administrator.
    $mail->addAddress($mailConfig['admin_email']);

    $mail->Subject = 'New Contact Form Submission - Mephys Clinic';

    $mail->Body = "
        <h2>New Contact Form Submission</h2>
        <p><strong>Name:</strong> " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Email:</strong> " . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</p>
        <p><strong>Message:</strong></p>
        <p>" . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . "</p>
    ";

    $mail->AltBody =
        "New Contact Form Submission\n\n" .
        "Name: $name\n" .
        "Email: $email\n\n" .
        "Message:\n$message";

    $mail->send();

    // Prepare confirmation email for the visitor.
    $mail->clearAddresses();
    $mail->clearAttachments();

    $mail->addAddress($email, $name);

    $mail->Subject = 'Thank You for Contacting Mephys Clinic';

    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

    $mail->Body = "
        <h3>Dear $safeName,</h3>

        <p>Thank you for contacting Mephys Physiotherapy Clinic.</p>

        <p>We have successfully received your message.</p>

        <p>Our team will get back to you as soon as possible.</p>

        <br>

        <p>Kind regards,<br>
        <strong>Mephys Physiotherapy Clinic</strong></p>
    ";

    $mail->AltBody =
        "Dear $name,\n\n" .
        "Thank you for contacting Mephys Physiotherapy Clinic.\n" .
        "We have successfully received your message.\n" .
        "Our team will get back to you as soon as possible.\n\n" .
        "Kind regards,\nMephys Physiotherapy Clinic";

    $mail->send();

    // Success response expected by the existing JavaScript.
    echo 'success';

} catch (PDOException $e) {

    error_log('Mephys database error: ' . $e->getMessage());

    http_response_code(500);
    echo 'Unable to save your message. Please try again later.';

} catch (PHPMailerException $e) {

    error_log('Mephys email error: ' . $e->getMessage());

    http_response_code(500);
    echo 'Your message could not be processed completely. Please contact the clinic directly.';

} catch (Throwable $e) {

    error_log('Mephys unexpected error: ' . $e->getMessage());

    http_response_code(500);
    echo 'Something went wrong. Please try again later.';
=======
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
>>>>>>> 511db4de60dc602d917786c0a608706d19c94f93
}
