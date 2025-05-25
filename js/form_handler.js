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

        formData.form_name = form.name || formId;

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
                        messageElement.textContent = `Submission successful! ID: ${data.submission_id}`;
                        messageElement.className = 'success-message'; // Optional: for styling
                        form.reset(); // Optionally reset the form on success
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
