// Toggle each fieldset on legend click
document.querySelectorAll("fieldset").forEach((fieldset) => {
    const legend = fieldset.querySelector("legend");
    if (!legend) return;

    // Add ARIA attributes and make legend focusable for accessibility
    legend.setAttribute("role", "button");
    legend.setAttribute("tabindex", "0");
    // Set initial aria-expanded state based on if it has the 'expanded' class
    legend.setAttribute("aria-expanded", fieldset.classList.contains("expanded"));

    legend.addEventListener("click", () => {
        // Toggle the expanded class
        fieldset.classList.toggle("expanded");

        // Update aria-expanded attribute
        const isExpanded = fieldset.classList.contains("expanded");
        legend.setAttribute("aria-expanded", isExpanded);
    });

    // Allow toggling with Enter or Space key when legend is focused
    legend.addEventListener("keydown", (event) => {
        // Check if the event target is the legend itself to avoid triggering on elements *within* the legend if any were added
        if (event.target === legend && (event.key === "Enter" || event.key === " ")) {
            event.preventDefault(); // Prevent default space/enter action (like scrolling)
            legend.click(); // Simulate a click to trigger the toggle
        }
    });
});
