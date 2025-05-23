// Toggle each fieldset on legend click
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
