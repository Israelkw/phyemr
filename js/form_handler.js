function initFormSubmissionHandler(formId, messageElementId) {
    const form = document.getElementById(formId);
    if (!form) {
        console.error(`Form with ID "${formId}" not found.`);
        return;
    }

    const messageElement = document.getElementById(messageElementId);
    if (!messageElement) {
        console.error(`Message element with ID "${messageElementId}" not found.`);
        return;
    }

    form.addEventListener('submit', function(event) {
        event.preventDefault();
        messageElement.textContent = ''; // Clear previous messages
        messageElement.className = ''; // Clear previous classes

        const formData = {};
        const elements = form.elements;

        for (let i = 0; i < elements.length; i++) {
            const element = elements[i];
            if (element.name) {
                if (element.type === 'select-multiple') {
                    formData[element.name] = [];
                    for (let j = 0; j < element.options.length; j++) {
                        if (element.options[j].selected) {
                            formData[element.name].push(element.options[j].value);
                        }
                    }
                } else {
                    formData[element.name] = element.value;
                }
            }
        }

        // Retrieve patient_id and clinician_id from global window variables
        // These should have been set by fill_patient_form.php
        if (typeof window.currentPatientId === 'undefined' || typeof window.currentClinicianId === 'undefined') {
            console.error('Patient ID or Clinician ID is not defined. Ensure they are set in fill_patient_form.php.');
            messageElement.textContent = 'Error: Critical patient or clinician information is missing. Cannot submit form.';
            messageElement.className = 'error-message';
            return; // Prevent submission
        }

        formData.patient_id = window.currentPatientId;
        formData.clinician_id = window.currentClinicianId;
        
        // form_name might also come from window.currentFormName if needed, 
        // but existing logic takes it from form.name or formId.
        // If window.currentFormName is more reliable, could use:
        // formData.form_name = window.currentFormName || form.name || formId;
        // For now, stick to existing form_name logic unless specified otherwise.
        if (!formData.form_name && window.currentFormName) {
             formData.form_name = window.currentFormName;
        } else if (!formData.form_name) {
            formData.form_name = form.name || formId; // Fallback if window.currentFormName also not set
        }

        // Add CSRF token to payload if available
        if (window.csrfToken) {
            formData.csrf_token = window.csrfToken;
        } else {
            console.warn('CSRF token not found on window object. Submission might fail server-side validation.');
            // Optionally, prevent submission:
            // messageElement.textContent = 'Error: CSRF token missing. Cannot submit form.';
            // messageElement.className = 'error-message';
            // return;
        }

        fetch('../php/save_submission.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData),
        })
        .then(response => {
            if (response.ok) {
                return response.json().then(data => {
                    if (data.success) {
                        if (data.next_form_url) {
                            if (messageElement) { // Optionally show message briefly before redirect
                                messageElement.className = 'alert alert-success'; // Use Bootstrap class
                                messageElement.textContent = data.message || 'Redirecting to the next form...';
                            }
                            // The URL from the server is "fill_patient_form.php?..."
                            // This will correctly navigate as the current page is also in the 'pages' directory.
                            window.location.href = data.next_form_url;
                        } else {
                            if (messageElement) {
                                messageElement.className = 'alert alert-success'; // Use Bootstrap class
                                messageElement.textContent = data.message || 'Form submitted successfully!';
                            }
                            if (form) form.reset(); // Reset form on general success
                        }
                    } else {
                        // Handle server-side errors reported in a JSON response
                        messageElement.textContent = `Error: ${data.error || 'Unknown server error.'}`;
                        messageElement.className = 'error-message'; // Optional: for styling
                    }
                }).catch(error => {
                    // Handle JSON parsing errors
                    messageElement.textContent = 'Error: Failed to parse server response.';
                    messageElement.className = 'error-message';
                    console.error('JSON parsing error:', error);
                });
            } else {
                // Handle HTTP errors (e.g., 404, 500)
                return response.text().then(text => {
                    messageElement.textContent = `Error: ${text || response.statusText || 'Server error.'}`;
                    messageElement.className = 'error-message';
                    console.error('Server response error:', response.status, text);
                });
            }
        })
        .catch(error => {
            // Handle network errors or other fetch-related issues
            messageElement.textContent = `Network Error: ${error.message || 'Could not connect to the server.'}`;
            messageElement.className = 'error-message';
            console.error('Fetch error:', error);
        });
    });
}
