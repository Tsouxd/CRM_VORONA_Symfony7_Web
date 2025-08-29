window.eaAutocompleteSelectNewValue = function (fieldName, id, label) {
    const field = document.querySelector(`[name*="${fieldName}"]`);

    if (!field) return;

    // Pour les champs Select2/autocomplete
    const option = new Option(label, id, true, true); // selected
    field.appendChild(option).dispatchEvent(new Event('change'));
};