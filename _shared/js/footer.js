console.log('--- carga footer.js --- <as module>');

let cosasenelfooter = '';
switch (window.contextoApp.app.pagina) {
    case "esto_no_es_home":
        armaFooter(cosasenelfooter);
    break;
    default:
        $('header.footer').hide();
    //     cosasenelfooter = 
    //     `<div style="color:white;margin-left:5px;">PRUEBA DE TILDECITOS:
    //         <input type="checkbox" class="switch">
    //         <input type="checkbox" class="switch gold">
    //         <input type="checkbox" class="switch blue">
    //         <input type="checkbox" class="switch red">
    //         <input type="checkbox" class="switch green">
    //         <input type="checkbox" class="switch warning">
    //         <input type="checkbox" class="switch warning gold">
    //         <input type="checkbox" class="switch warning blue">
    //         <input type="checkbox" class="switch warning red">
    //         <input type="checkbox" class="switch warning green">
    //     </div>`;
    //     armaFooter(cosasenelfooter);
    // break;
    break;
}

function armaFooter(cosasenelfooter){
    $('header.footer').append(
        cosasenelfooter
    ).show();    
}
