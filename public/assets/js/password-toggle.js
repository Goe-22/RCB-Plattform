document.querySelectorAll('input[type="password"]').forEach((input, index) => {
    if (!input.id) input.id = `password-field-${index + 1}`;

    const field = document.createElement('div');
    field.className = 'password-field';
    input.parentNode.insertBefore(field, input);
    field.appendChild(input);

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'password-toggle';
    toggle.textContent = 'Anzeigen';
    toggle.setAttribute('aria-controls', input.id);
    toggle.setAttribute('aria-pressed', 'false');
    toggle.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        toggle.textContent = visible ? 'Ausblenden' : 'Anzeigen';
        toggle.setAttribute('aria-pressed', String(visible));
    });
    field.appendChild(toggle);
});
