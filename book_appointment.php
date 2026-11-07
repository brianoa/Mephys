<?php
<<<<<<< HEAD

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Load database and SMTP configuration.
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

// Collect form data.
$service = trim($_POST['service'] ?? '');
$name = trim($_POST['fullName'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$date = trim($_POST['date'] ?? '');
$time = trim($_POST['time'] ?? '');
$location = trim($_POST['location'] ?? '');
$referral = trim($_POST['referral'] ?? '');
$notes = trim($_POST['notes'] ?? '');

// Validate required fields.
if (
    $service === '' ||
    $name === '' ||
    $email === '' ||
    $phone === '' ||
    $date === '' ||
    $time === '' ||
    $location === ''
) {
    http_response_code(400);
    exit('Please complete all required fields.');
}

// Validate email.
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('Please enter a valid email address.');
}

// Validate field lengths.
if (
    mb_strlen($name) > 150 ||
    mb_strlen($email) > 255 ||
    mb_strlen($phone) > 30 ||
    mb_strlen($service) > 150 ||
    mb_strlen($location) > 255
) {
    http_response_code(400);
    exit('Some submitted information is too long.');
}

// Safely prepare values for HTML emails.
$safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safePhone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$safeService = htmlspecialchars($service, ENT_QUOTES, 'UTF-8');
$safeDate = htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
$safeTime = htmlspecialchars($time, ENT_QUOTES, 'UTF-8');
$safeLocation = htmlspecialchars($location, ENT_QUOTES, 'UTF-8');
$safeReferral = htmlspecialchars($referral, ENT_QUOTES, 'UTF-8');
$safeNotes = nl2br(htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'));

try {

    // Save appointment in the database.
    $stmt = $pdo->prepare(
        "INSERT INTO appointments
        (
            service,
            full_name,
            email,
            phone,
            appointment_date,
            appointment_time,
            location,
            referral,
            notes
        )
        VALUES
        (
            :service,
            :name,
            :email,
            :phone,
            :date,
            :time,
            :location,
            :referral,
            :notes
        )"
    );

    $stmt->execute([
        ':service' => $service,
        ':name' => $name,
        ':email' => $email,
        ':phone' => $phone,
        ':date' => $date,
        ':time' => $time,
        ':location' => $location,
        ':referral' => $referral,
        ':notes' => $notes
    ]);

    // Initialize PHPMailer.
    $mail = new PHPMailer(true);

    // SMTP configuration.
    $mail->isSMTP();
    $mail->Host = $mailConfig['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $mailConfig['username'];
    $mail->Password = $mailConfig['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $mailConfig['port'];

    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->isHTML(true);

    // Sender information.
    $mail->setFrom(
        $mailConfig['username'],
        'Mephys Physiotherapy Clinic'
    );

    // --------------------------------------
    // Send confirmation to the client.
    // --------------------------------------

    $mail->addAddress($email, $name);

    $mail->Subject = 'Appointment Confirmation - Mephys Clinic';

    $mail->Body = "
        <h3>Dear $safeName,</h3>

        <p>Thank you for booking an appointment with
        <strong>Mephys Physiotherapy Clinic</strong>.</p>

        <p>We have received your appointment request with the following details:</p>

        <p>
            <strong>Service:</strong> $safeService<br>
            <strong>Date:</strong> $safeDate<br>
            <strong>Time:</strong> $safeTime<br>
            <strong>Location:</strong> $safeLocation
        </p>

        <p>We look forward to seeing you!</p>

        <br>

        <p>Warm regards,<br>
        <strong>Mephys Clinic Team</strong></p>
    ";

    $mail->AltBody =
        "Dear $name,\n\n" .
        "Thank you for booking an appointment with Mephys Physiotherapy Clinic.\n\n" .
        "Service: $service\n" .
        "Date: $date\n" .
        "Time: $time\n" .
        "Location: $location\n\n" .
        "We look forward to seeing you!\n\n" .
        "Mephys Clinic Team";

    $mail->send();

    // --------------------------------------
    // Send notification to clinic.
    // --------------------------------------

    $mail->clearAddresses();

    $mail->addAddress($mailConfig['admin_email'], 'Clinic Admin');

    // Allow the clinic to reply directly to the client.
    $mail->addReplyTo($email, $name);

    $mail->Subject = "New Appointment Booked by $name";

    $mail->Body = "
        <h3>New Appointment Received</h3>

        <p><strong>Name:</strong> $safeName<br>
        <strong>Email:</strong> $safeEmail<br>
        <strong>Phone:</strong> $safePhone<br>
        <strong>Service:</strong> $safeService<br>
        <strong>Date:</strong> $safeDate<br>
        <strong>Time:</strong> $safeTime<br>
        <strong>Location:</strong> $safeLocation<br>
        <strong>Referral:</strong> $safeReferral</p>

        <p><strong>Notes:</strong><br>$safeNotes</p>
    ";

    $mail->AltBody =
        "New Appointment Received\n\n" .
        "Name: $name\n" .
        "Email: $email\n" .
        "Phone: $phone\n" .
        "Service: $service\n" .
        "Date: $date\n" .
        "Time: $time\n" .
        "Location: $location\n" .
        "Referral: $referral\n" .
        "Notes: $notes";

    $mail->send();

    // Redirect to the existing appointment management page.
    header('Location: crud.php');
    exit;

} catch (PDOException $e) {

    error_log('Mephys appointment database error: ' . $e->getMessage());

    http_response_code(500);
    echo 'Unable to save the appointment. Please try again later.';

} catch (PHPMailerException $e) {

    error_log('Mephys appointment email error: ' . $e->getMessage());

    http_response_code(500);
    echo 'The appointment was received, but email processing was unsuccessful. Please contact the clinic.';

} catch (Throwable $e) {

    error_log('Mephys appointment unexpected error: ' . $e->getMessage());

    http_response_code(500);
    echo 'Something went wrong. Please try again later.';
}
=======
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
    $location = $_POST['location'] ?? '';
    $referral = $_POST['referral'] ?? '';
    $notes = $_POST['notes'] ?? '';

    try {
        $stmt = $pdo->prepare("INSERT INTO appointments (service, full_name, email, phone, appointment_date,appointment_time, location, referral, notes)
                               VALUES (:service, :name, :email, :phone, :date, :time, :location, :referral, :notes)");
        $stmt->execute([
            ":service" => $service,
            ":name" => $name,
            ":email" => $email,
            ":phone" => $phone,
            ":date" => $date,
            ":time" => $time,
            ":location" => $location,
            ":referral" => $referral,
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
               <strong>Location:</strong> $location<br>
               <strong>Referral:</strong> $referral</p>
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
>>>>>>> 511db4de60dc602d917786c0a608706d19c94f93
