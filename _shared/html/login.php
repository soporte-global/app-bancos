<!-- definir el espacio pantalla -->
<div class="centrado loginWraper">
    <!-- login -->
    <div class="login gradiente_inv2">
        <!-- titulo -->
        <div class="titulo">
            <h1 class="title">
                GLOBAL LOGIN: <span id="extra_titulo"></span>
            </h1>
        </div>
        <form action="_shared/php/procesar_login.php" id="formulario_login" method="post"
              data-username-storage-key="<?php echo htmlspecialchars('global_apps:ultimo_usuario:' . FolderName, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="input-container">
                <label for="username">Usuario:</label>
                <input type="text" id="username" name="username" required autocomplete="username" value="<?php echo htmlspecialchars((string) ($username ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="input-container">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required autocomplete="current-password"  value=""> 
            </div>
            <button class="boton" type="submit">Ingresar</button>
            <div class="error-message" id="error_message">
                <?php 
                    echo htmlspecialchars((string) ($_SESSION['GENERAL']['login_error'] ?? ''), ENT_QUOTES, 'UTF-8');
                    unset($_SESSION['GENERAL']['login_error']);
                ?>
            </div>
        </form>
    </div>
</div>
<script src="_shared/js/login.js?v=<?php echo (int) filemtime(RUTA . '/_shared/js/login.js'); ?>"></script>
