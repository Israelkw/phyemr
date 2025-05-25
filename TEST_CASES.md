# Application Test Cases

This document outlines test cases for the patient management application, focusing on role-based access and functionality.

## I. User Login & Basic Navigation

*   **TC-LOGIN-001:** Verify successful login for 'receptionist' role with correct credentials.
*   **TC-LOGIN-002:** Verify successful login for 'nurse' role with correct credentials.
*   **TC-LOGIN-003:** Verify successful login for 'clinician' role with correct credentials.
*   **TC-LOGIN-004:** Verify failed login with incorrect credentials for any role.
*   **TC-LOGIN-005:** Verify that the navigation bar and dashboard display role-specific links after login for receptionist.
*   **TC-LOGIN-006:** Verify that the navigation bar and dashboard display role-specific links after login for nurse.
*   **TC-LOGIN-007:** Verify that the navigation bar and dashboard display role-specific links after login for clinician.

## II. Receptionist Workflow

*   **TC-REC-001: Register New Patient & Assign**
    1.  Login as 'receptionist1'.
    2.  Navigate to "Register & Assign Patient".
    3.  Fill in patient demographics (First Name, Last Name, DOB).
    4.  Select 'clinician1' from the "Assign to Clinician" dropdown.
    5.  Submit the form.
    6.  **Expected:** Success message. Patient data stored in session, including `assigned_clinician_id` = clinician1's ID.

*   **TC-REC-002: Attempt to access non-receptionist pages**
    1.  Login as 'receptionist1'.
    2.  Attempt to directly navigate to `nurse_select_patient.php`.
    3.  **Expected:** Redirected to dashboard or login, with an unauthorized message.
    4.  Attempt to directly navigate to `view_my_patients.php`.
    5.  **Expected:** Redirected to dashboard or login, with an unauthorized message.

## III. Nurse Workflow

*   **TC-NURSE-001: Record Vitals for a Patient**
    1.  Login as 'nurse1'.
    2.  Navigate to "Select Patient for Vitals/Info".
    3.  Select the patient registered in **TC-REC-001**.
    4.  On the "Select Form for Patient" page, choose "Vital Signs Record".
    5.  Fill in vital signs data (e.g., temperature, heart rate).
    6.  Submit the form.
    7.  **Expected:** Success message. Vital signs data saved as a JSON file associated with the patient. The JSON should include `submitted_by_user_id` (nurse1's ID) and `submitted_by_user_role` ('nurse').

*   **TC-NURSE-002: Record General Overview for a Patient**
    1.  Login as 'nurse1'.
    2.  Navigate to "Select Patient for Vitals/Info".
    3.  Select the patient registered in **TC-REC-001**.
    4.  On the "Select Form for Patient" page, choose "General Patient Overview".
    5.  Fill in general overview data.
    6.  Submit the form.
    7.  **Expected:** Success message. General overview data saved as JSON, associated with the patient, and correctly attributed to nurse1.

*   **TC-NURSE-003: Attempt to access non-nurse pages**
    1.  Login as 'nurse1'.
    2.  Attempt to directly navigate to `add_patient.php`.
    3.  **Expected:** Redirected to dashboard or login, with an unauthorized message.

## IV. Clinician Workflow

*   **TC-CLINICIAN-001: View Assigned Patient**
    1.  Login as 'clinician1'.
    2.  Navigate to "My Assigned Patients".
    3.  **Expected:** The patient registered in **TC-REC-001** (and assigned to clinician1) should be listed.

*   **TC-CLINICIAN-002: View Patient Not Assigned**
    1.  Login as 'clinician2' (assuming another clinician exists who was *not* assigned the patient from TC-REC-001).
    2.  Navigate to "My Assigned Patients".
    3.  **Expected:** The patient registered in **TC-REC-001** should *not* be listed.

*   **TC-CLINICIAN-003: View Patient History (Assigned Patient)**
    1.  Login as 'clinician1'.
    2.  Navigate to "View Patient History".
    3.  Select the patient registered in **TC-REC-001**.
    4.  **Expected:**
        *   The "Vital Signs Record" submitted by 'nurse1' (**TC-NURSE-001**) should be listed.
        *   The "General Patient Overview" submitted by 'nurse1' (**TC-NURSE-002**) should be listed.
        *   Details like form category, submitter name ("Nurse [FirstName] [LastName]"), and date should be correct.

*   **TC-CLINICIAN-004: Submit Clinical Evaluation Form**
    1.  Login as 'clinician1'.
    2.  Navigate to "Select Patient for Form".
    3.  Select the patient registered in **TC-REC-001**.
    4.  Choose a clinical evaluation form (e.g., "General Assesment Form" from `patient_evaluation_form/`).
    5.  Fill in the form data.
    6.  Submit the form.
    7.  **Expected:** Success message. Form data saved as JSON, associated with the patient, and attributed to 'clinician1'.

*   **TC-CLINICIAN-005: View Updated Patient History**
    1.  Login as 'clinician1'.
    2.  Navigate to "View Patient History" and select the patient from **TC-REC-001**.
    3.  **Expected:** The clinical evaluation form submitted in **TC-CLINICIAN-004** should now also be listed, correctly attributed to 'clinician1'.

*   **TC-CLINICIAN-006: Attempt to View History of Unassigned Patient via URL**
    1.  Login as 'clinician2'.
    2.  Attempt to directly navigate to `view_patient_history.php?patient_id=<ID_of_patient_from_TC-REC-001>`.
    3.  **Expected:** An error message ("Invalid patient selection or patient not assigned to you") should be displayed. No history details should be visible.

*   **TC-CLINICIAN-007: Clinician Registers a Patient (Self-Assignment)**
    1.  Login as 'clinician1'.
    2.  Navigate to "Add Patient".
    3.  Fill in patient demographics. (No clinician assignment dropdown should be visible/required for clinician).
    4.  Submit.
    5.  **Expected:** Success. New patient registered and automatically `assigned_clinician_id` is clinician1's ID. This patient should appear in "My Assigned Patients" for clinician1.

## V. Data Integrity & Display

*   **TC-DATA-001:** Verify that all submitted form data (JSON files) accurately reflects the input.
*   **TC-DATA-002:** Verify timestamps for form submissions are correctly recorded and displayed.
*   **TC-DATA-003:** Verify patient demographic data is displayed correctly wherever patient lists appear.

```
