
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// ========================================
// GENERAL CONFIGURATION
// ========================================

date_default_timezone_set('Africa/Nairobi');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function bookingResponse(int $statusCode, array $data): void
{
    http_response_code($statusCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

// Accept POST requests only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    bookingResponse(405, [
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
}

// ========================================
// SESSION AND CSRF PROTECTION
// ========================================

if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    if (
        isset($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    ) {
        ini_set('session.cookie_secure', '1');
    }

    session_start();
}

$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['booking_csrf_token'] ?? '';

if (
    !is_string($submittedToken) ||
    !is_string($sessionToken) ||
    $submittedToken === '' ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    bookingResponse(403, [
        'success' => false,
        'message' => 'Your booking session has expired or is invalid. Please refresh the page and try again.'
    ]);
}

// ========================================
// COLLECT FORM DATA
// ========================================

function bookingField(string $key): string {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}
function bookingLength(string $text): int {
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : count(preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($text));
}
$service = bookingField('service');
$name = bookingField('fullName');
$email = bookingField('email');
$phone = bookingField('phone');
$date = bookingField('date');
$time = bookingField('time');
$location = bookingField('location');
$referral = bookingField('referral');
$notes = bookingField('notes');
$appointmentType = bookingField('appointment_type');
$facilityChoice = bookingField('facility_choice');
$otherFacilityName = bookingField('other_facility_name');
$visitAddress = bookingField('visit_address');
$visitContactPhone = bookingField('visit_contact_phone');
$visitDirections = bookingField('visit_directions');

// ========================================
// VALIDATE REQUIRED FIELDS
// ========================================


// ========================================
// VALIDATE REQUIRED FIELDS
// ========================================

$requiredFields = [
    'service'  => $service,
    'fullName' => $name,
    'email'    => $email,
    'phone'    => $phone,
    'date'     => $date,
    'time'     => $time,
    'referral' => $referral
];

$missingFields = [];

foreach ($requiredFields as $field => $value) {
    if ($value === '') {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    bookingResponse(400, [
        'success' => false,
        'message' => 'Missing required fields: ' .
            implode(', ', $missingFields)
    ]);
}


// ========================================
// VALIDATE SERVICE
// ========================================

$allowedServices = [
    'Musculoskeletal Physiotherapy',
    'Neurological Physiotherapy',
    'Cardiorespiratory Physiotherapy',
    'Geriatric Physiotherapy',
    'Sports Physiotherapy',
    'Orthopedic Physiotherapy',
    'Corporate Ergonomic Training',
    'Pediatric Physiotherapy',
    'Lamaze for Expectant Mothers',
    'Home-Based Physiotherapy',
    'Lymphatic drainage post surgery',
    'Physical Therapy Consultancy',
    'Sexual Health and wellness',
    'Pain Management',
    'Physical Therapy Products and Support'
];

if (!in_array($service, $allowedServices, true)) {
    bookingResponse(400, [
        'success' => false,
        'message' => 'Please select a valid clinic service.'
    ]);
}

// ========================================
// VALIDATE NAME
// ========================================

if (
    bookingLength($name) < 2 ||
    bookingLength($name) > 150 ||
    !preg_match("/^[\p{L}\p{M}][\p{L}\p{M}\s.'’-]{1,149}$/u", $name)
) {
    bookingResponse(400, [
        'success' => false,
        'message' => 'Please enter a valid full name.'
    ]);
}

// ========================================
// VALIDATE EMAIL
// ========================================

if (
    bookingLength($email) > 255 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    bookingResponse(400, [
        'success' => false,
        'message' => 'Please enter a valid email address.'
    ]);
}

// ========================================
// VALIDATE PHONE
// ========================================

if (
    bookingLength($phone) > 30 ||
    !preg_match('/^\+?[0-9\s().-]{7,20}$/', $phone)
) {
    bookingResponse(400, [
        'success' => false,
        'message' => 'Please enter a valid phone number.'
    ]);
}

// ========================================
// VALIDATE VISIT TYPE, FACILITY, ADDRESS AND CONTACT
// ========================================
require_once __DIR__ . '/clinic-info.php';
$additionalBookingLines = [];
// Old cached forms without appointment_type remain accepted through their existing location field.
if ($appointmentType !== '') {
    if (!in_array($appointmentType, ['facility','home'], true)) {
        bookingResponse(400, ['success'=>false, 'message'=>'Please choose a valid appointment setting.']);
    }
    if ($appointmentType === 'facility') {
        if (!in_array($facilityChoice, ['syokimau','komarocks','other'], true)) {
            bookingResponse(400, ['success'=>false,'message'=>'Select the facility for your appointment.']);
        }
        if ($facilityChoice === 'other') {
            if (bookingLength($otherFacilityName)<3 || bookingLength($otherFacilityName)>70) bookingResponse(400,['success'=>false,'message'=>'Enter a valid other facility name.']);
            $location = 'Other facility: ' . $otherFacilityName . ' - ' . $visitAddress;
            $additionalBookingLines[] = 'Visit type: Other facility';
            $additionalBookingLines[] = 'Facility: ' . $otherFacilityName;
        } else {
            $available = mephysClinicLocations();
            $location = $available[$facilityChoice]['name'];
            $additionalBookingLines[] = 'Visit type: Mephys facility';
            $additionalBookingLines[] = 'Facility: ' . $available[$facilityChoice]['name'];
        }
    } else {
        $location = 'Home-based care: ' . $visitAddress;
        $additionalBookingLines[] = 'Visit type: Home-based care';
    }
    if ($appointmentType === 'home' || $facilityChoice === 'other') {
        if (bookingLength($visitAddress)<12 || bookingLength($visitAddress)>160 || preg_match('/[<>]/', $visitAddress)) bookingResponse(400,['success'=>false,'message'=>'Provide a complete street/building, town and county address (12–160 characters).']);
        if (!preg_match('/^\+?[0-9\s().-]{7,20}$/', $visitContactPhone)) bookingResponse(400,['success'=>false,'message'=>'Provide a valid contact number at the visit location.']);
        if (bookingLength($visitDirections)>300) bookingResponse(400,['success'=>false,'message'=>'Directions must be 300 characters or fewer.']);
        $additionalBookingLines[] = 'Full visit address: ' . $visitAddress;
        $additionalBookingLines[] = 'Visit contact phone: ' . $visitContactPhone;
        if ($visitDirections!=='') $additionalBookingLines[] = 'Landmark / directions: ' . $visitDirections;
    } elseif ($visitAddress!=='' || $visitDirections!=='' || $visitContactPhone!=='' || $otherFacilityName!=='') {
        bookingResponse(400,['success'=>false,'message'=>'Please review the visit fields for your selected facility.']);
    }
} elseif ($location === '') {
    bookingResponse(400,['success'=>false,'message'=>'Please choose a visit location.']);
}
if (bookingLength($location)>255) bookingResponse(400,['success'=>false,'message'=>'Location is too long. Use a shorter address and add landmarks in directions.']);
if (bookingLength($notes)>1200) bookingResponse(400,['success'=>false,'message'=>'Additional notes cannot exceed 1,200 characters.']);
if ($additionalBookingLines) {
    $notes = implode("\n", $additionalBookingLines) . ($notes!=='' ? "\n---\nPatient notes: " . $notes : '');
}
if (bookingLength($notes)>2200) bookingResponse(400,['success'=>false,'message'=>'Appointment details are too long. Please shorten your directions or notes.']);

// ========================================
// VALIDATE REFERRAL
// ========================================

$allowedReferrals = [
    'Self Referral',
    'Hospital Referral',
    'Doctor Referral'
];

if (!in_array($referral, $allowedReferrals, true)) {
    bookingResponse(400, [
        'success' => false,
        'message' => 'Please select a valid referral type.'
    ]);
}

// ========================================
// VALIDATE NOTES
// ========================================

if (bookingLength($notes) > 2500) {
    bookingResponse(400, [
        'success' => false,
        'message' => 'Additional notes cannot exceed 2,000 characters.'
    ]);
}

// ========================================
// VALIDATE APPOINTMENT DATE
// ========================================

$timezone = new DateTimeZone('Africa/Nairobi');

$appointmentDate = DateTimeImmutable::createFromFormat(
    '!Y-m-d',
    $date,
    $timezone
);

$dateErrors = DateTimeImmutable::getLastErrors();

if (
    !$appointmentDate ||
    $appointmentDate->format('Y-m-d') !== $date ||
    (
        $dateErrors !== false &&
        (
            $dateErrors['warning_count'] > 0 ||
            $dateErrors['error_count'] > 0
        )
    )
) {
    bookingResponse(400, [
        'success' => false,
        'message' => 'Please select a valid appointment date.'
    ]);
}

// Earliest booking date is tomorrow in Kenya.
$earliestDate = new DateTimeImmutable('tomorrow', $timezone);

if ($appointmentDate < $earliestDate) {
    bookingResponse(400, [
        'success' => false,
        'message' => 'Appointments must be booked at least one day in advance. Please select tomorrow or a future date.'
    ]);
}

// ========================================
// VALIDATE APPOINTMENT TIME
// ========================================

if (
    !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) ||
    $time < '09:00' ||
    $time > '16:00'
) {
    bookingResponse(400, [
        'success' => false,
        'message' => 'Appointment times must be between 9:00 AM and 4:00 PM.'
    ]);
}

// ========================================
// SAVE APPOINTMENT
// ========================================

try {

    require_once __DIR__ . '/db.php';

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
            notes,
            status
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
            :notes,
            'pending'
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
        ':notes' => $notes !== '' ? $notes : null
    ]);

    $appointmentId = (int) $pdo->lastInsertId();

} catch (Throwable $e) {

    error_log(
        'Mephys appointment database error: ' . $e->getMessage()
    );

    bookingResponse(500, [
        'success' => false,
        'message' => 'Unable to save your appointment request. Please try again later.'
    ]);
}

// ========================================
// ROTATE CSRF TOKEN AFTER SUCCESSFUL SAVE
// ========================================

// This prevents the same form token from being reused
// to submit the same booking again.

$_SESSION['booking_csrf_token'] = bin2hex(random_bytes(32));

$newCsrfToken = $_SESSION['booking_csrf_token'];

// ========================================
// PREPARE EMAIL CONTENT
// ========================================

$safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeEmail = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safePhone = htmlspecialchars($phone, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeService = htmlspecialchars($service, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeDate = htmlspecialchars($date, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$timeLabel = DateTimeImmutable::createFromFormat('!H:i', $time, $timezone)->format('g:i A');
$safeTime = htmlspecialchars($timeLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeLocation = htmlspecialchars($location, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeReferral = htmlspecialchars($referral, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeNotes = nl2br(
    htmlspecialchars($notes, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
);

// ========================================
// SEND EMAIL NOTIFICATIONS
// ========================================

// Email failure must not undo a successfully saved appointment.
// Log email problems so they can be investigated.

try {

    $mailConfig = require __DIR__ . '/smtp-config.php';

    require_once __DIR__ . '/src/Exception.php';
    require_once __DIR__ . '/src/PHPMailer.php';
    require_once __DIR__ . '/src/SMTP.php';

    // Validate required SMTP configuration.
    foreach (
        ['host', 'username', 'password', 'port', 'admin_email']
        as $configKey
    ) {
        if (
            !isset($mailConfig[$configKey]) ||
            $mailConfig[$configKey] === ''
        ) {
            throw new RuntimeException(
                'Missing SMTP configuration: ' . $configKey
            );
        }
    }

    // ====================================
    // CLIENT CONFIRMATION EMAIL
    // ====================================

    try {

        $clientMail = new PHPMailer(true);

        $clientMail->isSMTP();
        $clientMail->Host = $mailConfig['host'];
        $clientMail->SMTPAuth = true;
        $clientMail->Username = $mailConfig['username'];
        $clientMail->Password = $mailConfig['password'];
        $clientMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $clientMail->Port = (int) $mailConfig['port'];

        $clientMail->CharSet = PHPMailer::CHARSET_UTF8;
        $clientMail->isHTML(true);

        $clientMail->setFrom(
            $mailConfig['username'],
            'Mephys Physiotherapy Clinic'
        );

        $clientMail->addAddress($email, $name);

        $clientMail->Subject =
            'Appointment Request Received - Mephys Clinic';

        $clientMail->Body = "
            <h3>Dear {$safeName},</h3>

            <p>Thank you for requesting an appointment with
            <strong>Mephys Physiotherapy Clinic</strong>.</p>

            <p>We have received your appointment request:</p>

            <p>
                <strong>Reference:</strong> {$appointmentId}<br>
                <strong>Service:</strong> {$safeService}<br>
                <strong>Date:</strong> {$safeDate}<br>
                <strong>Time:</strong> {$safeTime}<br>
                <strong>Location:</strong> {$safeLocation}
            </p>

            <p><strong>Current status:</strong> Pending approval.</p>

            <p>Your appointment is not yet confirmed.
            Our clinic team will review your request and contact
            you regarding confirmation.</p>

            <p>Warm regards,<br>
            <strong>Mephys Clinic Team</strong></p>
        ";

        $clientMail->AltBody =
            "Dear {$name},\n\n" .
            "Thank you for requesting an appointment with Mephys Physiotherapy Clinic.\n\n" .
            "Reference: {$appointmentId}\n" .
            "Service: {$service}\n" .
            "Date: {$date}\n" .
            "Time: {$timeLabel}\n" .
            "Location: {$location}\n\n" .
            "Your request is pending clinic approval. " .
            "Our team will contact you regarding confirmation.\n\n" .
            "Mephys Clinic Team";

        $clientMail->send();

    } catch (Throwable $e) {

        error_log(
            'Mephys client confirmation email failed for appointment #' .
            $appointmentId . ': ' . $e->getMessage()
        );
    }

    // ====================================
    // CLINIC NOTIFICATION EMAIL
    // ====================================

    try {

        $adminMail = new PHPMailer(true);

        $adminMail->isSMTP();
        $adminMail->Host = $mailConfig['host'];
        $adminMail->SMTPAuth = true;
        $adminMail->Username = $mailConfig['username'];
        $adminMail->Password = $mailConfig['password'];
        $adminMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $adminMail->Port = (int) $mailConfig['port'];

        $adminMail->CharSet = PHPMailer::CHARSET_UTF8;
        $adminMail->isHTML(true);

        $adminMail->setFrom(
            $mailConfig['username'],
            'Mephys Physiotherapy Clinic'
        );

        $adminMail->addAddress(
            $mailConfig['admin_email'],
            'Clinic Admin'
        );

        $adminMail->addReplyTo($email, $name);

        $adminMail->Subject =
            "New Appointment Request #{$appointmentId}";

        $adminMail->Body = "
            <h3>New Appointment Request</h3>

            <p>
                <strong>Appointment Reference:</strong> {$appointmentId}<br>
                <strong>Status:</strong> Pending
            </p>

            <p>
                <strong>Name:</strong> {$safeName}<br>
                <strong>Email:</strong> {$safeEmail}<br>
                <strong>Phone:</strong> {$safePhone}<br>
                <strong>Service:</strong> {$safeService}<br>
                <strong>Date:</strong> {$safeDate}<br>
                <strong>Time:</strong> {$safeTime}<br>
                <strong>Location:</strong> {$safeLocation}<br>
                <strong>Referral:</strong> {$safeReferral}
            </p>

            <p><strong>Notes:</strong><br>{$safeNotes}</p>

            <p>Please review this request in the staff dashboard.</p>
        ";

        $adminMail->AltBody =
            "New Appointment Request\n\n" .
            "Reference: {$appointmentId}\n" .
            "Status: Pending\n" .
            "Name: {$name}\n" .
            "Email: {$email}\n" .
            "Phone: {$phone}\n" .
            "Service: {$service}\n" .
            "Date: {$date}\n" .
            "Time: {$timeLabel}\n" .
            "Location: {$location}\n" .
            "Referral: {$referral}\n" .
            "Notes: {$notes}\n\n" .
            "Please review this request in the staff dashboard.";

        $adminMail->send();

    } catch (Throwable $e) {

        error_log(
            'Mephys clinic notification email failed for appointment #' .
            $appointmentId . ': ' . $e->getMessage()
        );
    }

} catch (Throwable $e) {

    error_log(
        'Mephys appointment email configuration error for appointment #' .
        $appointmentId . ': ' . $e->getMessage()
    );
}

// ========================================
// RETURN SUCCESSFUL BOOKING RESPONSE
// ========================================

bookingResponse(201, [
    'success' => true,
    'message' => 'Your appointment request has been received successfully. It is pending clinic approval. Our team will contact you regarding confirmation.',
    'appointment_id' => $appointmentId,
    'csrf_token' => $newCsrfToken
]);
