console.log('--- carga login.js ---');
var loginForm = document.getElementById('formulario_login');
var usernameInput = document.getElementById('username');
var passwordInput = document.getElementById('password');
var usernameStorageKey = loginForm.dataset.usernameStorageKey;
var errorMessage = document.getElementById('error_message');

// el login debe seguir funcionando si el navegador bloquea el almacenamiento local
try {
    if (usernameInput.value === '') {
        usernameInput.value = localStorage.getItem(usernameStorageKey) || '';
    }
} catch (error) {
    console.warn('No se pudo recuperar el ultimo usuario.', error);
}

if (usernameInput.value === '') {
    usernameInput.focus();
} else {
    passwordInput.focus();
}

// mostrar el mensaje de error si se recibe
var phpErrorMessage = errorMessage.textContent.trim();
if (phpErrorMessage !== '') {
    errorMessage.style.display = 'block';
}

// conserva el contrato actual del endpoint de login
loginForm.addEventListener('submit', function(event) {
    event.preventDefault();

    try {
        localStorage.setItem(usernameStorageKey, usernameInput.value.trim());
    } catch (error) {
        console.warn('No se pudo guardar el ultimo usuario.', error);
    }

    passwordInput.value = base64EncodeUTF8(passwordInput.value);
    loginForm.submit();
});

function base64EncodeUTF8(str) {
    const bytes = new TextEncoder().encode(str);
    let binary = '';
    bytes.forEach(b => binary += String.fromCharCode(b));
    return btoa(binary);
}
