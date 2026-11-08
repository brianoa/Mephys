
<?php

// Manual appointment creation has been disabled.
// New appointments must be submitted through the public website.

http_response_code(410);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Appointment Creation Unavailable - Mephys</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-5">
    <div class="card border-0 shadow-sm mx-auto text-center p-4" style="max-width: 550px;">

        <h3 class="mb-3">Appointment Creation Unavailable</h3>

        <p class="text-muted">
            New appointments must be submitted through the official
            Mephys Physiotherapy Clinic website booking form.
        </p>

        <a href="crud.php" class="btn btn-primary">
            Return to Staff Dashboard
        </a>

    </div>
</div>

</body>
</html>
