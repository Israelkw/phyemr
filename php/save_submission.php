<?php

// Database connection parameters
$dbHost = 'YOUR_DB_HOST';
$dbUser = 'YOUR_DB_USER';
$dbPassword = 'YOUR_DB_PASSWORD';
$dbName = 'YOUR_DB_NAME';

// Ensure the script only processes POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

// Create the submissions directory if it doesn't already exist
$submissionsDir = '../submissions/';
if (!is_dir($submissionsDir)) {
    if (!mkdir($submissionsDir, 0777, true)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Failed to create submissions directory.']);
        exit;
    }
}

// Ensure the submissions directory is writable
if (!is_writable($submissionsDir)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Submissions directory is not writable.']);
    exit;
}

// Retrieve JSON data from the client
$jsonData = file_get_contents('php://input');

// Decode JSON data
$data = json_decode($jsonData, true);

// Check if decoding failed
if (json_last_error() !== JSON_ERROR_NONE) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid JSON data.']);
    exit;
}

// Extract form_name or use a default
$formName = isset($data['form_name']) ? $data['form_name'] : 'unknown_form';

// Generate a unique ID for the submission
$submissionId = uniqid();

// Construct the JSON filename and full path
$jsonFilename = $submissionId . '.json';
$filePath = $submissionsDir . $jsonFilename;

// Save the original received JSON data to the file
if (file_put_contents($filePath, $jsonData) === false) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to save submission data.']);
    exit;
}

// Connect to the MySQL database
$conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);

// Check connection
if ($conn->connect_error) {
    header('Content-Type: application/json');
    // In a production environment, log this error instead of echoing it
    echo json_encode(['error' => 'Database connection failed.']); 
    exit;
}

// Prepare an SQL INSERT statement
$stmt = $conn->prepare("INSERT INTO submissions (id, form_name, file_path) VALUES (?, ?, ?)");

// Check if statement preparation failed
if ($stmt === false) {
    header('Content-Type: application/json');
    // In a production environment, log this error instead of echoing it
    echo json_encode(['error' => 'Failed to prepare SQL statement.']);
    $conn->close();
    exit;
}

$stmt->bind_param("sss", $submissionId, $formName, $filePath);

// Execute the prepared statement
if ($stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'submission_id' => $submissionId,
        'file_path' => $filePath
    ]);
} else {
    header('Content-Type: application/json');
    // In a production environment, log this error instead of echoing it
    echo json_encode(['error' => 'Failed to execute SQL statement.']);
}

// Close statement and connection
$stmt->close();
$conn->close();

?>
