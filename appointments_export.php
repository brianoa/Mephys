
<?php

require_once __DIR__ . '/staff-auth.php';

// Only authenticated staff can download appointment records.
$staff = mephysRequireStaffLogin();

global $pdo;

// Only allow the three supported export formats.
$type = strtolower(trim($_GET['type'] ?? 'csv'));

$allowedTypes = ['csv', 'excel', 'pdf'];

if (!in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    exit('Invalid export type.');
}

// Prevent browsers from caching confidential appointment records.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

try {

    // Retrieve appointment records.
    $stmt = $pdo->query(
        "SELECT * FROM appointments
         ORDER BY appointment_date DESC, appointment_time DESC"
    );

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log('Mephys appointment export database error: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to export appointment records. Please try again later.');
}

// Handle an empty appointment table.
if (empty($data)) {
    http_response_code(404);
    exit('No appointment records are available for export.');
}

// Escape values before placing them inside HTML.
function exportEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// Protect spreadsheet users against formula injection.
// This is applied to data cells, not PDF content.
function spreadsheetSafeValue($value): string
{
    $value = (string) ($value ?? '');

    if (preg_match('/^[\s]*[=+\-@]/u', $value)) {
        return "'" . $value;
    }

    return $value;
}

// Get the column names.
$columns = array_keys($data[0]);

switch ($type) {

    // -------------------------------------------------
    // CSV EXPORT
    // -------------------------------------------------

    case 'csv':

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="appointments.csv"');

        $output = fopen('php://output', 'w');

        if ($output === false) {
            error_log('Mephys CSV export: Unable to open output stream.');
            http_response_code(500);
            exit('Unable to generate CSV file.');
        }

        // UTF-8 BOM helps Excel display special characters correctly.
        fwrite($output, "\xEF\xBB\xBF");

        // Column headings.
        fputcsv($output, $columns, ',', '"', '');

        // Appointment records.
        foreach ($data as $row) {

            $csvRow = [];

            foreach ($columns as $column) {
                $csvRow[] = spreadsheetSafeValue($row[$column] ?? '');
            }

            fputcsv($output, $csvRow, ',', '"', '');
        }

        fclose($output);
        exit;


    // -------------------------------------------------
    // EXCEL EXPORT
    // -------------------------------------------------

    case 'excel':

        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="appointments.xls"');

        // UTF-8 BOM for better Excel compatibility.
        echo "\xEF\xBB\xBF";

        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<title>Appointments Report</title>';
        echo '</head>';
        echo '<body>';

        echo '<h2>Mephys Physiotherapy Clinic - Appointments Report</h2>';

        echo '<table border="1" cellpadding="5" cellspacing="0">';

        echo '<thead>';
        echo '<tr>';

        foreach ($columns as $column) {
            echo '<th>' . exportEscape($column) . '</th>';
        }

        echo '</tr>';
        echo '</thead>';

        echo '<tbody>';

        foreach ($data as $row) {

            echo '<tr>';

            foreach ($columns as $column) {

                $value = spreadsheetSafeValue($row[$column] ?? '');

                echo '<td>' . exportEscape($value) . '</td>';
            }

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        echo '</body>';
        echo '</html>';

        exit;


    // -------------------------------------------------
    // PDF EXPORT
    // -------------------------------------------------

    case 'pdf':

        try {

            // Load Dompdf only when a PDF is requested.
            require_once __DIR__ . '/vendor/autoload.php';

            if (!class_exists(\Dompdf\Dompdf::class)) {
                throw new RuntimeException('Dompdf is not available.');
            }

            // Build the report HTML.
            $html = '
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">

                    <style>
                        body {
                            font-family: DejaVu Sans, sans-serif;
                            font-size: 9px;
                            color: #333;
                        }

                        h2 {
                            text-align: center;
                            margin-bottom: 20px;
                        }

                        table {
                            width: 100%;
                            border-collapse: collapse;
                        }

                        th {
                            background-color: #eeeeee;
                            font-weight: bold;
                        }

                        th, td {
                            border: 1px solid #cccccc;
                            padding: 5px;
                            text-align: left;
                            word-wrap: break-word;
                        }
                    </style>

                </head>
                <body>

                    <h2>Mephys Physiotherapy Clinic - Appointments Report</h2>

                    <table>
                        <thead>
                            <tr>
            ';

            foreach ($columns as $column) {
                $html .= '<th>' . exportEscape($column) . '</th>';
            }

            $html .= '
                            </tr>
                        </thead>
                        <tbody>
            ';

            foreach ($data as $row) {

                $html .= '<tr>';

                foreach ($columns as $column) {
                    $html .= '<td>' . exportEscape($row[$column] ?? '') . '</td>';
                }

                $html .= '</tr>';
            }

            $html .= '
                        </tbody>
                    </table>

                </body>
                </html>
            ';

            // Generate the PDF.
            $dompdf = new \Dompdf\Dompdf();

            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            // Send the PDF as a downloadable file.
            $dompdf->stream(
                'appointments.pdf',
                ['Attachment' => true]
            );

            exit;

        } catch (Throwable $e) {

            error_log('Mephys PDF export error: ' . $e->getMessage());

            http_response_code(500);
            exit('Unable to generate the PDF report. Please try again later.');
        }

}
