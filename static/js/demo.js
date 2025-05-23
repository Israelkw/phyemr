// Collapsible fieldset script
document.querySelectorAll("fieldset").forEach((fieldset) => {
    const legend = fieldset.querySelector("legend");
    if (!legend) return;

    legend.setAttribute("role", "button");
    legend.setAttribute("tabindex", "0");
    legend.setAttribute("aria-expanded", fieldset.classList.contains("expanded"));

    legend.addEventListener("click", () => {
        fieldset.classList.toggle("expanded");
        const isExpanded = fieldset.classList.contains("expanded");
        legend.setAttribute("aria-expanded", isExpanded);
    });

    legend.addEventListener("keydown", (event) => {
        if (event.target === legend && (event.key === "Enter" || event.key === " ")) {
            event.preventDefault(); 
            legend.click(); 
        }
    });
});

// Placeholder form handling functions
function validateDemographicsForm() {
    var form = document.getElementById("demographyForm");
    if (!form) return false;
    if (!form.checkValidity()) {
        // Find the first invalid field and focus it for better UX
        let firstInvalidField = form.querySelector(':invalid');
        if (firstInvalidField) {
            firstInvalidField.focus();
        }
        alert("Please fill out all required fields.");
        return false;
    }
    return true;
}

function submitDemographicsForm() {
    if (validateDemographicsForm()) {
        alert("Demographics form submitted successfully!");
        // Actual form submission logic (e.g., AJAX) would go here.
        // const formData = new FormData(document.getElementById("demographyForm"));
        // fetch('/your-submit-url', { method: 'POST', body: formData })
        //   .then(response => response.json())
        //   .then(data => console.log(data))
        //   .catch(error => console.error('Error:', error));
    }
}

function resetDemographicsForm() {
    var form = document.getElementById("demographyForm");
    if (form) {
        form.reset();
    }
    // If there were any custom display elements to reset (like pain scale), do it here.
}
