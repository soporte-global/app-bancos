console.log('--- carga header.js ---');
$(document).ready(function() {
    console.log('--- carga header.js ---', 'document.ready');
    let menuAbierto = false;
    const paginasBloqueoAuto = [
        // 'carga', 
        // 'transporte', 
        // 'descarga'
    ];

    // Abre el menú al clickear en el botón
    $(".menu-icon").click(function() {
        const buttonRect = this.getBoundingClientRect();
        const menuLeft = buttonRect.left - 20;
        const maxMenuWidth = $("body").width() - menuLeft - 10; // Adjust as needed
        const width = $(".menu-left").width() + 5;
        // const menuTop = buttonRect.bottom + 15;
        $(".dropdown-menu").css({
            display: "block",
            left: "-5px",
            top: "59px",
            width: `${width}px`,
            maxWidth: `${maxMenuWidth}px` // Limit menu width to body width
        });
        if (!menuAbierto) {
            $(".dropdown-menu").animate({ maxHeight: "75vh" }, 600, function() {
                $(this).css( {overflowY:"auto"} );
                menuAbierto = true;
            });
        }
    });
    // abre ajustes de color al clickear en el botón del menú
    $('#color_setting_selectors').on('click',function(){
        $('.contenedor-ajustes').slideToggle(200);
    });
    // cambia las reglas CSS dinámicamente al mover los sliders
    $('.color_slider').on('input',function(){
        let hue = $('#hue_slider').val();
        let sat = $('#sat_slider').val();
        let lum = $('#lum_slider').val();
        let style = $('#css_dinamico');
        style.html(
        // uso del overlay
            "body:not(:has(header.error)).color_variable{"+
                "filter: hue-rotate("+hue+"deg) saturate("+sat+") brightness("+lum+");"+
            "}"+
        // elementos que cambian
            // "body header, body .espaciador, body .login, body .afterheader{"+
            //     "filter: hue-rotate("+hue+"deg) saturate("+sat+") brightness("+lum+");"+
            // "}"+
        // excepciones
            "body:not(:has(header.error)).color_variable .anula_variacion{"+
                "filter: hue-rotate("+(-hue)+"deg);"+
            "}"+
            ""
        );
    });
    // llama al evento input por primera vez sobre los valores predeterminados
    $('.color_slider').trigger('input');
    // Alterna los temas configurados y conserva la elección entre recargas.
    const temaApp = document.getElementById('tema_app');
    const selectorTema = document.getElementById('tema_oscuro');
    if (temaApp !== null && selectorTema !== null) {
        $(selectorTema).on('change', function() {
            const tema = this.checked ? 'oscuro' : 'claro';
            temaApp.href = this.checked ? temaApp.dataset.temaOscuro : temaApp.dataset.temaClaro;
            document.cookie = temaApp.dataset.cookieTema + '=' + tema
                + '; path=/; max-age=31536000; SameSite=Lax';
        });
    }
    // al soltar el slider y definir un color, lo guarda en $_SESSION
    const guardar_colores = function(){
        let hue = $('#hue_slider').val();
        let sat = $('#sat_slider').val();
        let lum = $('#lum_slider').val();
        consultar_bbdd
        ( 
            '_shared/php/session_color.php', 							// ruta desde el index del archivo con las instrucciones php a llamar, string
            {
                hue:hue,
                sat:sat,
                lum:lum
            }, 									                // objeto con datos a enviar por ajax 
            'session_color', 									// nombre para la funcion de carga de la consulta, si está vacío se usa la url
            function(respuesta){
                console.log(':::session_color',respuesta);
            }
        );
    };
    $('.color_slider').on('change', guardar_colores);
    $('#reset_colors').on('click',function(e){
        e.preventDefault();
        $('#hue_slider').val(0);
        $('#sat_slider').val(1);
        $('#lum_slider').val(1);
        $('.color_slider').trigger('input');
        guardar_colores();
    });
    // cierra el menú al clickear en cualquier lado (que no sea el menú)
    $(document).on("click", cierra_menu);
    // si el checkbox está tildado, bloquea cualquier evento en pantalla fuera del header
    $('#bloquear_pantalla').on('change', function() {
        $('body').toggleClass('bloqueo-activo', this.checked);
    });
    if (paginasBloqueoAuto.includes(window.contextoApp.app.pagina) && !$('header').hasClass('error')) {
        $('#bloquear_pantalla').click();
    }
    $('.dropdown-menu').appendTo('body');

    function cierra_menu (event) {
        if (
            $(event.target).closest('.dropdown-menu, .menu-icon').length === 0 &&
            menuAbierto
        ) {
            $(".dropdown-menu").css( {overflowY:"hidden"} );
            $(".dropdown-menu").animate({ maxHeight: "0" }, 50, function() {
                $('.contenedor-ajustes').hide();
                $(".dropdown-menu").css("display", "none");
                menuAbierto = false;
            });
        }
    }
});
