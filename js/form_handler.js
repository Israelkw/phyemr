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

        const formElementsArray = [];
        const elements = form.elements;
        const processedRadioGroups = new Set(); // To handle radio groups correctly

        for (let i = 0; i < elements.length; i++) {
            const element = elements[i];
            if (element.name) {
                const label = element.dataset.label || element.name; // Fallback to name if data-label is missing
                let value = element.value;

                switch (element.type) {
                    case 'checkbox':
                        if (element.checked) {
                            formElementsArray.push({ name: element.name, value: value, label: label });
                        }
                        // Unchecked checkboxes are not included
                        break;
                    case 'radio':
                        // Process radio group only once
                        if (!processedRadioGroups.has(element.name)) {
                            const checkedRadio = form.querySelector(`input[name="${element.name}"]:checked`);
                            if (checkedRadio) {
                                // Use the label from the actually checked radio button
                                const checkedLabel = checkedRadio.dataset.label || checkedRadio.name;
                                formElementsArray.push({ name: element.name, value: checkedRadio.value, label: checkedLabel });
                            }
                            processedRadioGroups.add(element.name);
                        }
                        break;
                    case 'select-multiple':
                        const selectedOptions = [];
                        for (let j = 0; j < element.options.length; j++) {
                            if (element.options[j].selected) {
                                selectedOptions.push(element.options[j].value);
                            }
                        }
                        if (selectedOptions.length > 0) {
                            formElementsArray.push({ name: element.name, value: selectedOptions, label: label });
                        }
                        break;
                    case 'button':
                    case 'submit':
                    case 'reset':
                    case 'fieldset': // Fieldset elements don't have values in this context
                        // Skip these elements
                        break;
                    default:
                        // For text, hidden, select-one, textarea, password, etc.
                        // Only add if value is not empty, or if it's a hidden field (which might intentionally be empty)
                        // This behavior can be adjusted based on requirements for empty fields.
                        // For now, let's include them even if empty, server-side can filter if needed.
                        formElementsArray.push({ name: element.name, value: value, label: label });
                        break;
                }
            }
        }

        const payload = {};

        // Retrieve patient_id and clinician_id from global window variables
        if (typeof window.currentPatientId === 'undefined' || typeof window.currentClinicianId === 'undefined') {
            console.error('Patient ID or Clinician ID is not defined. Ensure they are set in fill_patient_form.php.');
            messageElement.textContent = 'Error: Critical patient or clinician information is missing. Cannot submit form.';
            messageElement.className = 'error-message';
            return; // Prevent submission
        }

        payload.patient_id = window.currentPatientId;
        payload.clinician_id = window.currentClinicianId; // Server-side maps this to submitted_by_user_id
        
        if (window.currentFormName) {
            payload.form_name = window.currentFormName;
        } else {
            payload.form_name = form.name || formId; // Fallback
        }

        // Add CSRF token to payload if available
        if (window.csrfToken) {
            payload.csrf_token = window.csrfToken;
        } else {
            console.warn('CSRF token not found on window object. Submission might fail server-side validation.');
            // Optionally, prevent submission if CSRF is strictly required client-side as well
        }

        payload.fields = formElementsArray; // Add the array of field objects

        fetch('../php/save_submission.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
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
