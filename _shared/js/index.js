console.log('--- carga index.js ---');

window.addEventListener('resize', ajustarAltura);
ajustarAltura();

// mantiene la unidad vh alineada con el alto visible en navegadores moviles
function ajustarAltura() {
    let vh = window.innerHeight * 0.01;
    document.documentElement.style.setProperty('--vh', `${vh}px`);
}
