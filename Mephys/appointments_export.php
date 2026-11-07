<?php
// ✅ Step 1: Show errors clearly
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ✅ Step 2: Connect to database
require_once "db.php";

// ✅ Step 3: Include Dompdf (for PDF export)
require_once __DIR__ . '/vendor/autoload.php'; // Make sure this path is correct

use Dompdf\Dompdf; // ✅ MUST be at the top level (not inside switch)

// ✅ Step 4: Fetch data
$type = $_GET['type'] ?? 'csv';

$stmt = $pdo->query("SELECT * FROM appointments ORDER BY appointment_date DESC");
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($data)) {
    die("No appointment data found.");
}

// ✅ Step 5: Export based on type
switch ($type) {
    case 'csv':
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=appointments.csv");
        $output = fopen("php://output", "w");
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        break;

    case 'excel':
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=appointments.xls");
        echo "<table border='1'>";
        echo "<tr>";
        foreach (array_keys($data[0]) as $col) {
            echo "<th>" . htmlspecialchars($col) . "</th>";
        }
        echo "</tr>";
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $val) {
                echo "<td>" . htmlspecialchars($val) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
        break;

    case 'pdf':
        // ✅ Build HTML content
        $html = "<h2 style='text-align:center;'>Appointments Report</h2>";
        $html .= "<table border='1' cellpadding='5' cellspacing='0' style='width:100%; border-collapse:collapse;'>";
        $html .= "<tr>";
        foreach (array_keys($data[0]) as $col) {
            $html .= "<th style='background:#eee;'>" . htmlspecialchars($col) . "</th>";
        }
        $html .= "</tr>";
        foreach ($data as $row) {
            $html .= "<tr>";
            foreach ($row as $val) {
                $html .= "<td>" . htmlspecialchars($val) . "</td>";
            }
            $html .= "</tr>";
        }
        $html .= "</table>";

        // ✅ Generate PDF
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream("appointments.pdf");
        break;

    default:
        die("Invalid export type.");
}
?>
