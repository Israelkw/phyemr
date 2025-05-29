<?php
session_start(); // Recommended if you need to access session variables like clinician_id if not passed directly
require_once '../includes/db_connect.php'; // This will provide the $mysqli object

// Ensure the script only processes POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

// Retrieve JSON data from the client
$jsonData = file_get_contents('php://input');

// Decode JSON data
$data = json_decode($jsonData, true);

// Check if decoding failed
if (json_last_error() !== JSON_ERROR_NONE) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid JSON data: ' . json_last_error_msg()]);
    exit;
}

// Extract necessary IDs and form name
$patient_id = $data['patient_id'] ?? null;
$clinician_id = $data['clinician_id'] ?? null; // This will be submitted_by_user_id
$form_name = $data['form_name'] ?? 'unknown_form';

// Validate required fields
if (empty($patient_id) || empty($clinician_id) || $form_name === 'unknown_form') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing required data: patient_id, clinician_id, or form_name.']);
    exit;
}

$form_data_json = $jsonData; // Store the original full JSON payload
$submitted_by_user_id = $clinician_id;

// Determine form_directory based on form_name (simple inference)
$form_directory = 'unknown_directory'; // Default
if (isset($data['form_name'])) {
    if (strpos($data['form_name'], 'general-information') !== false) {
        $form_directory = 'patient_general_info';
    } elseif (strpos($data['form_name'], 'generalAssessmentForm') !== false || 
              strpos($data['form_name'], 'cervical') !== false || 
              strpos($data['form_name'], 'lumbar') !== false || 
              strpos($data['form_name'], 'neuro') !== false || 
              strpos($data['form_name'], 'pediatric_assesment') !== false || 
              strpos($data['form_name'], 'thoracic') !== false) {
        $form_directory = 'patient_evaluation_form';
    }
}

// Prepare an SQL INSERT statement for the patient_form_submissions table
$stmt = $mysqli->prepare("INSERT INTO patient_form_submissions (patient_id, submitted_by_user_id, form_name, form_directory, form_data) VALUES (?, ?, ?, ?, ?)");

if (!$stmt) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to prepare SQL statement: ' . $mysqli->error]);
    exit;
}

$stmt->bind_param("iisss", $patient_id, $submitted_by_user_id, $form_name, $form_directory, $form_data_json);

// Execute the prepared statement
if ($stmt->execute()) {
    $submission_id = $mysqli->insert_id; // Get the ID of the inserted row
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'submission_id' => $submission_id
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to execute SQL statement: ' . $stmt->error]);
}

// Close statement and connection
$stmt->close();
$mysqli->close();

?>
