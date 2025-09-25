<?php
require_once __DIR__ . '/vendor/autoload.php';

function getGoogleClient() {
    $client = new Google_Client();
    $client->setApplicationName("Evaluation System");
    $client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
    $client->setAuthConfig(__DIR__ . '/credentials.json'); // Downloaded from Google Cloud
    $client->setAccessType('offline');
    return $client;
}

function getStudentsFromSheet() {
    $client = getGoogleClient();
    $service = new Google_Service_Sheets($client);

    // Replace with your actual Spreadsheet ID and range
    $spreadsheetId = $_ENV['GOOGLE_SHEET_ID'] ?? '1cFkTmh_1DUX4lb6VLK-EJjw5hlB9zvnYK8W__rTML-I';
    $range = 'Students!A2:E'; // Example: columns A–E, skipping header row

    try {
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();

        $students = [];
        if (!empty($values)) {
            foreach ($values as $row) {
                $students[] = [
                    'student_id' => $row[0] ?? null,
                    'name'       => $row[1] ?? null,
                    'email'      => $row[2] ?? null,
                    'course'     => $row[3] ?? null,
                    'year'       => $row[4] ?? null,
                ];
            }
        }
        return $students;

    } catch (Exception $e) {
        error_log("Google Sheets API error: " . $e->getMessage());
        return [];
    }
}
?>
