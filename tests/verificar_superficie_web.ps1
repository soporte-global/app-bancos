param(
    [Parameter(Mandatory = $true)]
    [string] $BaseUrl
)

$ErrorActionPreference = 'Stop'
$base = $BaseUrl.TrimEnd('/')
$casos = @(
    @{ Ruta = '/index.php'; Permitido = @(200) },
    @{ Ruta = '/_shared/css/general.css'; Permitido = @(200) },
    @{ Ruta = '/_shared/js/index.js'; Permitido = @(200) },
    @{ Ruta = '/_shared/js/contexto-app.js'; Permitido = @(200) },
    @{ Ruta = '/app/compatibilidad/contexto.js'; Permitido = @(200) },
    @{ Ruta = '/src/img/iconito_global.ico'; Permitido = @(200) },
    @{ Ruta = '/src/audio/ok_bip.wav'; Permitido = @(200) },
    @{ Ruta = '/lib/jszip/dist/jszip.js'; Permitido = @(200) },
    @{ Ruta = '/lib/tablesorter-master/css/theme.default.css'; Permitido = @(200) },
    @{ Ruta = '/_shared/php/procesar_login.php'; Permitido = @(302) },
    @{ Ruta = '/.git/config'; Permitido = @(403) },
    @{ Ruta = '/.gitignore'; Permitido = @(403) },
    @{ Ruta = '/README.md'; Permitido = @(403) },
    @{ Ruta = '/_shared/html/login.php'; Permitido = @(403) },
    @{ Ruta = '/_shared/sql/migraciones/001_global_prod_hub.sql'; Permitido = @(403) },
    @{ Ruta = '/_shared/certs/isrg-root-x1.pem'; Permitido = @(403) },
    @{ Ruta = '/app/html/home.html'; Permitido = @(403) },
    @{ Ruta = '/app/sql/migraciones/003_renombrar_nueva_app.sql'; Permitido = @(403) },
    @{ Ruta = '/app/config.php'; Permitido = @(403) },
    @{ Ruta = '/app/config.php/path-info'; Permitido = @(403) },
    @{ Ruta = '/app/config.local.php.example'; Permitido = @(403) },
    @{ Ruta = '/app/compatibilidad/contexto.php'; Permitido = @(403) },
    @{ Ruta = '/app/compatibilidad/'; Permitido = @(403) },
    @{ Ruta = '/app/php/carga_inicial.php'; Permitido = @(403) },
    @{ Ruta = '/_shared/php/contexto_app.php'; Permitido = @(403) },
    @{ Ruta = '/src/config.php'; Permitido = @(403) },
    @{ Ruta = '/src/Model/Core/README.md'; Permitido = @(403) },
    @{ Ruta = '/src/Model/Legacy/Conexiones.php'; Permitido = @(403) },
    @{ Ruta = '/composer.json'; Permitido = @(403) },
    @{ Ruta = '/composer.lock'; Permitido = @(403) },
    @{ Ruta = '/vendor/autoload.php'; Permitido = @(403) },
    @{ Ruta = '/tests/verificar_superficie_web.ps1'; Permitido = @(403) },
    @{ Ruta = '/lib/jszip/package.json'; Permitido = @(403) },
    @{ Ruta = '/lib/jszip/documentation/examples/downloader.html'; Permitido = @(403) },
    @{ Ruta = '/src/img/'; Permitido = @(403) },
    @{ Ruta = '/app/php/'; Permitido = @(403) }
)

$fallos = @()
foreach ($caso in $casos) {
    $codigo = & curl.exe -sS -o NUL -w '%{http_code}' --max-time 15 ($base + $caso.Ruta)
    $numero = [int] $codigo
    $correcto = $caso.Permitido -contains $numero
    [PSCustomObject]@{
        Estado = if ($correcto) { 'ok' } else { 'error' }
        HTTP = $numero
        Ruta = $caso.Ruta
    }
    if (-not $correcto) {
        $fallos += $caso.Ruta
    }
}

if ($fallos.Count -gt 0) {
    throw ('fallaron las comprobaciones: ' + ($fallos -join ', '))
}
