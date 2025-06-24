<?php
// Ensure this path is correct for SessionManager from php/save_submission.php
require_once __DIR__ . '/../includes/SessionManager.php';
SessionManager::startSession();

// Ensure this path is correct for db_connect.php and it provides $pdo
require_once __DIR__ . '/../includes/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['error' => 'Invalid JSON data: ' . json_last_error_msg()]);
    exit;
}

// CSRF Token Validation
$submitted_token = $data['csrf_token'] ?? null;
if (!SessionManager::validateCsrfToken($submitted_token)) {
    echo json_encode(['error' => 'Invalid or missing CSRF token.']);
    exit;
}
unset($data['csrf_token']); // Remove CSRF token from the main data array as it's already validated

// Basic initial validation
$patient_id = $data['patient_id'] ?? null;
$submitted_by_user_id = $data['clinician_id'] ?? ($_SESSION['user_id'] ?? null); // 'clinician_id' from JS
$form_name = $data['form_name'] ?? 'unknown_form';
$fields_array = $data['fields'] ?? []; // This is the new array of field objects

if (empty($patient_id) || empty($submitted_by_user_id) || $form_name === 'unknown_form' || !is_array($fields_array)) {
    echo json_encode(['error' => 'Missing required data: patient_id, submitted_by_user_id, form_name, or fields array.']);
    exit;
}

// Helper function to find a field's value from the new 'fields' array structure
// It searches for the first occurrence of a field name within a list of possible keys.
function find_field_value_from_array(array $fields_array, array $possible_field_names, $default = null) {
    foreach ($possible_field_names as $field_name_to_find) {
        foreach ($fields_array as $field_object) {
            if (isset($field_object['name']) && $field_object['name'] === $field_name_to_find) {
                // Return the value if it's set and not an empty string.
                // For checkboxes, 'value' might be 'checked', 'on', etc. or a specific value.
                // If an empty string should be considered valid, this condition needs adjustment.
                if (isset($field_object['value']) && $field_object['value'] !== '') {
                    return $field_object['value'];
                }
                // If value is an empty string, it might be intentional, but for extraction
                // to dedicated columns, we usually want non-empty.
                // If an empty string is a valid distinct value, this logic might change.
                // For now, if found but empty, we continue to check other possible keys or return default.
            }
        }
    }
    return $default;
}

// Define keys for extraction (these are the 'name' attributes from form elements)
$treating_clinician_keys = ['treating_clinician', 'GA_B5_TreatingClinician', 'PA_P1_B5_TreatingClinician'];
$chief_complaint_keys = ['chief-complaint', 'chief_complaint', 'GA_B6_ChiefComplaint', 'PA_P1_B6_ChiefComplaint', 'presenting_complaint'];
$eval_diag_keys = ['medical-diagnosis', 'evaluation_summary_diagnosis', 'GA2_B8_PtDiagnosis', 'PA_P3_B1_Assessment', 'cervical_specific_diagnosis', 'lumbar_specific_diagnosis', 'thoracic_specific_diagnosis', 'neuro_specific_diagnosis', 'pediatric_specific_diagnosis', 'diagnosis'];
$submission_notes_keys = ['submission_notes', 'notes', 'assessment_notes', 'general_notes'];

// Vitals keys
$temperature_keys = ['temperature', 'temp', 'vital_temperature'];
$pulse_rate_keys = ['pulse-rate', 'pulse', 'vital_pulse_rate'];
$bp_systolic_keys = ['bp-systolic', 'systolic', 'bp_sys', 'vital_bp_systolic'];
$bp_diastolic_keys = ['bp-diastolic', 'diastolic', 'bp_dia', 'vital_bp_diastolic'];
$respiratory_rate_keys = ['respiratory-rate', 'resp_rate', 'vital_respiratory_rate'];
$oxygen_saturation_keys = ['oxygen-saturation', 'spo2', 'vital_oxygen_saturation'];
$height_cm_keys = ['height_cm', 'height', 'vital_height'];
$weight_kg_keys = ['weight_kg', 'weight', 'vital_weight'];
$bmi_keys = ['bmi', 'vital_bmi'];
$pain_scale_keys = ['pain-scale', 'pain', 'vital_pain_scale'];

// Clinical Details keys
$allergies_keys = ['allergies', 'allergic-history', 'known_allergies'];
$medical_history_summary_keys = ['medical_history_summary', 'history-of-present-illness', 'medical_history', 'PA_P1_B7_MedHistory'];
$current_medications_keys = ['current_medications', 'medications', 'PA_P1_B8_Meds'];
$eval_treatment_plan_summary_keys = ['evaluation_treatment_plan_summary', 'treatment_plan', 'plan_of_care', 'PA_P3_B2_PlanOfCare'];
$eval_short_term_goals_keys = ['evaluation_short_term_goals', 'short_term_goals', 'PA_P3_B3_ShortTermGoals'];
$eval_long_term_goals_keys = ['evaluation_long_term_goals', 'long_term_goals', 'PA_P3_B4_LongTermGoals'];

// Extract data for patient_form_submissions using the new helper
$treating_clinician = find_field_value_from_array($fields_array, $treating_clinician_keys);
$chief_complaint = find_field_value_from_array($fields_array, $chief_complaint_keys);
$evaluation_summary_diagnosis = find_field_value_from_array($fields_array, $eval_diag_keys);
$submission_notes = find_field_value_from_array($fields_array, $submission_notes_keys);

// Extract data for submission_vitals
$vitals_data = [
    'temperature' => find_field_value_from_array($fields_array, $temperature_keys),
    'pulse_rate' => find_field_value_from_array($fields_array, $pulse_rate_keys),
    'bp_systolic' => find_field_value_from_array($fields_array, $bp_systolic_keys),
    'bp_diastolic' => find_field_value_from_array($fields_array, $bp_diastolic_keys),
    'respiratory_rate' => find_field_value_from_array($fields_array, $respiratory_rate_keys),
    'oxygen_saturation' => find_field_value_from_array($fields_array, $oxygen_saturation_keys),
    'height_cm' => find_field_value_from_array($fields_array, $height_cm_keys),
    'weight_kg' => find_field_value_from_array($fields_array, $weight_kg_keys),
    'bmi' => find_field_value_from_array($fields_array, $bmi_keys),
    'pain_scale' => find_field_value_from_array($fields_array, $pain_scale_keys),
];
$has_vitals_data = false;
foreach ($vitals_data as $value) {
    if ($value !== null) { // Note: find_field_value_from_array returns null if not found or empty after checks
        $has_vitals_data = true;
        break;
    }
}

// Extract data for submission_clinical_details
$clinical_details_data = [
    'allergies' => find_field_value_from_array($fields_array, $allergies_keys),
    'medical_history_summary' => find_field_value_from_array($fields_array, $medical_history_summary_keys),
    'current_medications' => find_field_value_from_array($fields_array, $current_medications_keys),
    'evaluation_treatment_plan_summary' => find_field_value_from_array($fields_array, $eval_treatment_plan_summary_keys),
    'evaluation_short_term_goals' => find_field_value_from_array($fields_array, $eval_short_term_goals_keys),
    'evaluation_long_term_goals' => find_field_value_from_array($fields_array, $eval_long_term_goals_keys),
];
$has_clinical_details_data = false;
foreach ($clinical_details_data as $value) {
    if ($value !== null) {
        $has_clinical_details_data = true;
        break;
    }
}

// Determine form_directory (simple inference, can be expanded)
// This uses $data['form_name'] which is still a top-level key in the payload
$form_directory = 'unknown_directory';
if (isset($data['form_name'])) { // $form_name variable already holds $data['form_name']
    $fn = $form_name;
    if (strpos($fn, 'general-information') !== false || strpos($fn, 'basic_info') !== false || strpos($fn, 'demo') !== false ) {
        $form_directory = 'patient_general_info';
    } elseif (strpos($fn, 'generalAssessmentForm') !== false || strpos($fn, 'cervical') !== false ||
              strpos($fn, 'lumbar') !== false || strpos($fn, 'neuro') !== false ||
              strpos($fn, 'pediatric_assessment') !== false || strpos($fn, 'thoracic') !== false ||
              strpos($fn, 'evaluation') !== false ) {
        $form_directory = 'patient_evaluation_form';
    }
}

// The entire $fields_array (which contains {name, value, label} objects)
// will be stored in the form_data column.
$form_data_to_store_in_json = json_encode($fields_array);

if ($form_data_to_store_in_json === false) {
    echo json_encode(['error' => 'Failed to encode fields array for storage. JSON Error: ' . json_last_error_msg()]);
    exit;
}

// Ensure $pdo is provided by db_connect.php
if (!isset($pdo)) {
    echo json_encode(['error' => 'Database connection not available (PDO object not found). Check db_connect.php.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Insert into patient_form_submissions
    $sql_submission = "INSERT INTO patient_form_submissions
                        (patient_id, submitted_by_user_id, form_name, form_directory,
                         treating_clinician, chief_complaint, evaluation_summary_diagnosis, submission_notes, form_data)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_submission = $pdo->prepare($sql_submission);
    $stmt_submission->execute([
        $patient_id, $submitted_by_user_id, $form_name, $form_directory,
        $treating_clinician, $chief_complaint, $evaluation_summary_diagnosis, $submission_notes,
        $form_data_to_store_in_json
    ]);
    $submission_id = $pdo->lastInsertId();

    // Insert into submission_vitals if data exists
    if ($has_vitals_data) {
        $sql_vitals = "INSERT INTO submission_vitals
                        (submission_id, temperature, pulse_rate, bp_systolic, bp_diastolic, respiratory_rate,
                         oxygen_saturation, height_cm, weight_kg, bmi, pain_scale)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_vitals = $pdo->prepare($sql_vitals);
        $stmt_vitals->execute([
            $submission_id,
            $vitals_data['temperature'], $vitals_data['pulse_rate'], $vitals_data['bp_systolic'], $vitals_data['bp_diastolic'],
            $vitals_data['respiratory_rate'], $vitals_data['oxygen_saturation'], $vitals_data['height_cm'],
            $vitals_data['weight_kg'], $vitals_data['bmi'], $vitals_data['pain_scale']
        ]);
    }

    // Insert into submission_clinical_details if data exists
    if ($has_clinical_details_data) {
        $sql_clinical = "INSERT INTO submission_clinical_details
                            (submission_id, allergies, medical_history_summary, current_medications,
                             evaluation_treatment_plan_summary, evaluation_short_term_goals, evaluation_long_term_goals)
                            VALUES (?, ?, ?, ?, ?, ?, ?)"; // Added one placeholder for allergies
        $stmt_clinical = $pdo->prepare($sql_clinical);
        $stmt_clinical->execute([
            $submission_id,
            $clinical_details_data['allergies'], // Added allergies
            $clinical_details_data['medical_history_summary'], $clinical_details_data['current_medications'],
            $clinical_details_data['evaluation_treatment_plan_summary'], $clinical_details_data['evaluation_short_term_goals'],
            $clinical_details_data['evaluation_long_term_goals']
        ]);
    }

    $pdo->commit();

    // Prepare response
    $response = [
        'success' => true,
        'submission_id' => $submission_id,
        'message' => 'Form data saved successfully.' // Generic success message
    ];

    $user_role = SessionManager::get("role");

    // Specific redirection for new patient registration (demographics -> general info)
    if ($form_name === 'demo.html' && $form_directory === 'patient_general_info' && ($user_role === 'receptionist' || $user_role === 'clinician')) {
        // This was the old flow for new patients, it's now handled by add_patient.php directly.
        // This if-block might need review if demo.html is still used post add_patient.php.
        // For now, assume add_patient.php is comprehensive and this specific redirect might not be hit often for this form.
        // If it is, it should probably go to dashboard or patient view.
        // $response['next_form_url'] = "fill_patient_form.php?form_name=general-information.html&form_directory=patient_general_info&patient_id=" . urlencode($patient_id);
        // $response['message'] = 'Demographics form submitted. Proceeding to General Information form.';
    }
    // Nurse workflow: Vitals -> General Overview
    elseif ($user_role === 'nurse' && $form_name === 'vital_signs.html' && $form_directory === 'patient_general_info') {
        $response['next_form_url'] = "fill_patient_form.php?form_name=general_patient_overview.html&form_directory=patient_general_info&patient_id=" . urlencode($patient_id);
        $response['message'] = 'Vital signs saved. Proceeding to General Patient Overview.';
    }
    // After nurse submits general_patient_overview.html, or any other form not part of a specific sequence
    else {
        // Default success message is already set. No specific next_form_url for other cases here.
        // Could redirect to dashboard or patient history view.
        // $response['redirect_url'] = '../pages/dashboard.php'; // Example
    }

    echo json_encode($response);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Log detailed error to server logs
    error_log("Database transaction error in save_submission.php: " . $e->getMessage());
    // Send generic error to client
    echo json_encode(['error' => 'Database error occurred while saving submission. Please try again or contact support.']);
} finally {
    // Connection is often managed by db_connect.php or script lifecycle, so explicit close might not be needed here.
    // If $pdo was created in this script, then: $pdo = null;
}

?>
