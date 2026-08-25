<?php
$nombre = isset($sesion) ? $sesion->nombreMostrado() : null;
$permitirCambiosColor = true;
$permisosMenu = isset($permisos_app) && is_array($permisos_app) ? $permisos_app : [];
?>
<audio id="successSound" src="src/audio/ok_bip.wav"></audio>
<audio id="errorSound" src="src/audio/error.mp3"></audio>
<header class="top">
    <div class="menu-left" style="min-width: unset;">
        <nav class="menu-options">
            <button class="menu-icon" type="button" aria-label="Abrir menu">
                <i class="fas fa-bars fa-lg"></i>
            </button>
            <span id="bienvenida">
                <?php if (isset($error)): ?>
                    ERROR
                <?php elseif ($nombre !== null): ?>
                    Bienvenido/a
                    <span class="nombre_mostrar"><?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </span>
            <div class="dropdown-menu excepcion-bloqueo">
                <datalist id="snap-sat">
                    <option value="0.5">
                    <option value="1">
                    <option value="1.5">
                </datalist>
                <datalist id="snap-lum">
                    <option value="0.75">
                    <option value="1">
                    <option value="1.25">
                </datalist>
                <?php if (isset($sesion)): ?>
                    <?php
                    $homeExiste = is_file(RUTA . '/app/html/home.html')
                        || is_file(RUTA . '/app/html/home.php');
                    $homeEnPermisos = false;
                    foreach ($permisosMenu as $permisoMenu) {
                        if (strtolower(trim((string) $permisoMenu->nombreInterno())) === 'home') {
                            $homeEnPermisos = true;
                            break;
                        }
                    }
                    ?>
                    <?php if (MOSTRAR_HOME_AUTOMATICO && $homeExiste && !$homeEnPermisos): ?>
                        <a href="index.php"><i class="fas fa-home"></i> HOME</a>
                    <?php endif; ?>
                    <?php foreach ($permisosMenu as $permisoMenu): ?>
                        <?php
                        $destino = (string) $permisoMenu->nombreInterno();
                        $icono = htmlspecialchars((string) $permisoMenu->icono(), ENT_QUOTES, 'UTF-8');
                        $descripcion = htmlspecialchars((string) $permisoMenu->descripcion(), ENT_QUOTES, 'UTF-8');
                        ?>
                        <a href="index.php?pag=<?php echo rawurlencode($destino); ?>">
                            <i class="<?php echo $icono; ?>"></i> <?php echo $descripcion; ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if (DIAGNOSTICO_HABILITADO): ?>
                        <a href="index.php?shared=diagnostico">
                            <i class="fas fa-stethoscope"></i> DIAGNOSTICO
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!isset($error) && $permitirCambiosColor): ?>
                    <?php
                    $color = isset($_SESSION['COLOR_CONF']) && is_array($_SESSION['COLOR_CONF'])
                        ? $_SESSION['COLOR_CONF']
                        : [];
                    $hue = isset($color['hue']) && is_numeric($color['hue'])
                        ? max(0, min(360, (float) $color['hue']))
                        : 0;
                    $sat = isset($color['sat']) && is_numeric($color['sat'])
                        ? max(0, min(2, (float) $color['sat']))
                        : 1;
                    $lum = isset($color['lum']) && is_numeric($color['lum'])
                        ? max(0.5, min(1.5, (float) $color['lum']))
                        : 1;
                    ?>
                    <a id="color_setting_selectors"><i class="fas fa-cog"></i> AJUSTES DE COLOR</a>
                    <div class="contenedor-ajustes">
                        <label for="hue_slider">H:</label>
                        <input type="range" min="0" max="360" value="<?php echo $hue; ?>" class="color_slider" id="hue_slider"><br>
                        <label for="sat_slider">S:</label>
                        <input type="range" step="0.01" min="0" max="2" value="<?php echo $sat; ?>" class="color_slider" id="sat_slider" list="snap-sat"><br>
                        <label for="lum_slider">L:</label>
                        <input type="range" step="0.01" min="0.5" max="1.5" value="<?php echo $lum; ?>" class="color_slider" id="lum_slider" list="snap-lum"><br>
                        <input type="button" class="boton" value="reset" id="reset_colors">
                    </div>
                <?php endif; ?>

                <?php if (isset($sesion) || isset($error)): ?>
                    <a href="_shared/php/logout.php" id="logout-menu">
                        <i class="fas fa-power-off"></i> CERRAR SESION
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
    <div class="menu-right">
        <a class="titulo-link" href="index.php">
            <span class="logo_global" role="img" aria-label="Logo"></span>
            <span class="titulo_app"><?php echo htmlspecialchars(NOMBRE, ENT_QUOTES, 'UTF-8'); ?></span>
        </a>
    </div>
</header>
<div class="espaciador"></div>
<script src="_shared/js/header.js?v=<?php echo (int) filemtime(RUTA . '/_shared/js/header.js'); ?>"></script>
