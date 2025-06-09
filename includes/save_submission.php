<?php
session_start();
require_once 'db_connect.php'; // Provides $pdo

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

// Basic initial validation
$patient_id = $data['patient_id'] ?? null;
$submitted_by_user_id = $data['clinician_id'] ?? null; // Assuming clinician_id is the user_id
$form_name = $data['form_name'] ?? 'unknown_form';

if (empty($patient_id) || empty($submitted_by_user_id) || $form_name === 'unknown_form') {
    echo json_encode(['error' => 'Missing required data: patient_id, clinician_id, or form_name.']);
    exit;
}

// Helper function to extract value from data array using multiple possible keys
function get_field_value($data, $keys, $default = null) {
    foreach ($keys as $key) {
        if (isset($data[$key]) && $data[$key] !== '') { // Consider empty strings as not set for some fields
            return $data[$key];
        }
    }
    return $default;
}

// Define keys for extraction
$all_extracted_keys = [];

$treating_clinician_keys = ['treating_clinician', 'GA_B5_TreatingClinician', 'PA_P1_B5_TreatingClinician'];
$all_extracted_keys = array_merge($all_extracted_keys, $treating_clinician_keys);
$chief_complaint_keys = ['chief-complaint', 'chief_complaint', 'GA_B6_ChiefComplaint', 'PA_P1_B6_ChiefComplaint', 'presenting_complaint'];
$all_extracted_keys = array_merge($all_extracted_keys, $chief_complaint_keys);
$eval_diag_keys = ['medical-diagnosis', 'evaluation_summary_diagnosis', 'GA2_B8_PtDiagnosis', 'PA_P3_B1_Assessment', 'cervical_specific_diagnosis', 'lumbar_specific_diagnosis', 'thoracic_specific_diagnosis', 'neuro_specific_diagnosis', 'pediatric_specific_diagnosis', 'diagnosis'];
$all_extracted_keys = array_merge($all_extracted_keys, $eval_diag_keys);
$submission_notes_keys = ['submission_notes', 'notes', 'assessment_notes', 'general_notes'];
$all_extracted_keys = array_merge($all_extracted_keys, $submission_notes_keys);

// Vitals keys
$temperature_keys = ['temperature', 'temp', 'vital_temperature']; $all_extracted_keys = array_merge($all_extracted_keys, $temperature_keys);
$pulse_rate_keys = ['pulse-rate', 'pulse', 'vital_pulse_rate']; $all_extracted_keys = array_merge($all_extracted_keys, $pulse_rate_keys);
$bp_systolic_keys = ['bp-systolic', 'systolic', 'bp_sys', 'vital_bp_systolic']; $all_extracted_keys = array_merge($all_extracted_keys, $bp_systolic_keys);
$bp_diastolic_keys = ['bp-diastolic', 'diastolic', 'bp_dia', 'vital_bp_diastolic']; $all_extracted_keys = array_merge($all_extracted_keys, $bp_diastolic_keys);
$respiratory_rate_keys = ['respiratory-rate', 'resp_rate', 'vital_respiratory_rate']; $all_extracted_keys = array_merge($all_extracted_keys, $respiratory_rate_keys);
$oxygen_saturation_keys = ['oxygen-saturation', 'spo2', 'vital_oxygen_saturation']; $all_extracted_keys = array_merge($all_extracted_keys, $oxygen_saturation_keys);
$height_cm_keys = ['height_cm', 'height', 'vital_height']; $all_extracted_keys = array_merge($all_extracted_keys, $height_cm_keys);
$weight_kg_keys = ['weight_kg', 'weight', 'vital_weight']; $all_extracted_keys = array_merge($all_extracted_keys, $weight_kg_keys);
$bmi_keys = ['bmi', 'vital_bmi']; $all_extracted_keys = array_merge($all_extracted_keys, $bmi_keys);
$pain_scale_keys = ['pain-scale', 'pain', 'vital_pain_scale']; $all_extracted_keys = array_merge($all_extracted_keys, $pain_scale_keys);

// Clinical Details keys
$medical_history_summary_keys = ['medical_history_summary', 'history-of-present-illness', 'medical_history', 'PA_P1_B7_MedHistory']; $all_extracted_keys = array_merge($all_extracted_keys, $medical_history_summary_keys);
$current_medications_keys = ['current_medications', 'medications', 'PA_P1_B8_Meds']; $all_extracted_keys = array_merge($all_extracted_keys, $current_medications_keys);
$eval_treatment_plan_summary_keys = ['evaluation_treatment_plan_summary', 'treatment_plan', 'plan_of_care', 'PA_P3_B2_PlanOfCare']; $all_extracted_keys = array_merge($all_extracted_keys, $eval_treatment_plan_summary_keys);
$eval_short_term_goals_keys = ['evaluation_short_term_goals', 'short_term_goals', 'PA_P3_B3_ShortTermGoals']; $all_extracted_keys = array_merge($all_extracted_keys, $eval_short_term_goals_keys);
$eval_long_term_goals_keys = ['evaluation_long_term_goals', 'long_term_goals', 'PA_P3_B4_LongTermGoals']; $all_extracted_keys = array_merge($all_extracted_keys, $eval_long_term_goals_keys);

// Extract data for patient_form_submissions
$treating_clinician = get_field_value($data, $treating_clinician_keys);
$chief_complaint = get_field_value($data, $chief_complaint_keys);
$evaluation_summary_diagnosis = get_field_value($data, $eval_diag_keys);
$submission_notes = get_field_value($data, $submission_notes_keys);

// Extract data for submission_vitals
$vitals_data = [
    'temperature' => get_field_value($data, $temperature_keys),
    'pulse_rate' => get_field_value($data, $pulse_rate_keys),
    'bp_systolic' => get_field_value($data, $bp_systolic_keys),
    'bp_diastolic' => get_field_value($data, $bp_diastolic_keys),
    'respiratory_rate' => get_field_value($data, $respiratory_rate_keys),
    'oxygen_saturation' => get_field_value($data, $oxygen_saturation_keys),
    'height_cm' => get_field_value($data, $height_cm_keys),
    'weight_kg' => get_field_value($data, $weight_kg_keys),
    'bmi' => get_field_value($data, $bmi_keys),
    'pain_scale' => get_field_value($data, $pain_scale_keys),
];
$has_vitals_data = false;
foreach ($vitals_data as $value) {
    if ($value !== null) {
        $has_vitals_data = true;
        break;
    }
}

// Extract data for submission_clinical_details
$clinical_details_data = [
    'medical_history_summary' => get_field_value($data, $medical_history_summary_keys),
    'current_medications' => get_field_value($data, $current_medications_keys),
    'evaluation_treatment_plan_summary' => get_field_value($data, $eval_treatment_plan_summary_keys),
    'evaluation_short_term_goals' => get_field_value($data, $eval_short_term_goals_keys),
    'evaluation_long_term_goals' => get_field_value($data, $eval_long_term_goals_keys),
];
$has_clinical_details_data = false;
foreach ($clinical_details_data as $value) {
    if ($value !== null) {
        $has_clinical_details_data = true;
        break;
    }
}

// Determine form_directory (simple inference, can be expanded)
$form_directory = 'unknown_directory';
if (isset($data['form_name'])) {
    $fn = $data['form_name'];
    if (strpos($fn, 'general-information') !== false || strpos($fn, 'basic_info') !== false) {
        $form_directory = 'patient_general_info';
    } elseif (strpos($fn, 'generalAssessmentForm') !== false || strpos($fn, 'cervical') !== false ||
              strpos($fn, 'lumbar') !== false || strpos($fn, 'neuro') !== false ||
              strpos($fn, 'pediatric_assessment') !== false || strpos($fn, 'thoracic') !== false ||
              strpos($fn, 'evaluation') !== false ) {
        $form_directory = 'patient_evaluation_form';
    }
}


// Create $remaining_data for JSON storage
$remaining_data = $data;
$unique_extracted_keys = array_unique($all_extracted_keys); // Ensure keys are unique before unsetting
foreach ($unique_extracted_keys as $key_to_remove) {
    unset($remaining_data[$key_to_remove]);
}
// Also remove keys that are part of the main table structure but not in $all_extracted_keys
unset($remaining_data['patient_id']);
unset($remaining_data['clinician_id']);
unset($remaining_data['form_name']);
// 'treating_clinician' is handled by $all_extracted_keys

$form_data_to_store_in_json = json_encode($remaining_data);


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
                            (submission_id, medical_history_summary, current_medications, 
                             evaluation_treatment_plan_summary, evaluation_short_term_goals, evaluation_long_term_goals) 
                            VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_clinical = $pdo->prepare($sql_clinical);
        $stmt_clinical->execute([
            $submission_id,
            $clinical_details_data['medical_history_summary'], $clinical_details_data['current_medications'],
            $clinical_details_data['evaluation_treatment_plan_summary'], $clinical_details_data['evaluation_short_term_goals'],
            $clinical_details_data['evaluation_long_term_goals']
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'submission_id' => $submission_id
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} finally {
    // $pdo = null; // Connection is often managed by db_connect.php or script lifecycle
}

?>