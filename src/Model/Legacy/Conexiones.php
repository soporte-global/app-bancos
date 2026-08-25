<?php
namespace HClasses\Conexiones;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * fachada de compatibilidad para los consumidores históricos.
 *
 * el código nuevo debe pedir DataBase y TokenZetti por separado.
 */
class ConexionZetti
{
    public $dbase;
    public $token;

    public function __construct($nombre_base = 'zweb', $token = null)
    {
        $this->dbase = new DataBase($nombre_base ?: 'zweb');
        $this->token = $token ?: new TokenZetti();
        $this->renovarToken();
    }

    public function renovarToken()
    {
        return $this->token->getToken();
    }
}

/**
 * conexión exclusiva a una base de datos.
 */
class DataBase
{
    public $pdo;

    public function __construct($nombre_base)
    {
        $nombre_base = strtolower(trim((string) $nombre_base));

        if ($nombre_base === 'zweb') {
            $this->pdo = new PDO(
                'pgsql:host=' . HOST . ';port=' . PORT . ';dbname=' . DBASE,
                USER,
                PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_PERSISTENT => defined('FTWEB_PERSISTENT') && FTWEB_PERSISTENT,
                ]
            );
            return;
        }

        if ($nombre_base === 'rrhh') {
            $this->pdo = new PDO(
                'mysql:host=' . HOST2 . ';port=' . PORT2 . ';dbname=' . DBASE2 . ';charset=utf8',
                USER2,
                PASS2,
                [
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]
            );
            return;
        }

        if ($nombre_base === 'bejerman') {
            $this->pdo = $this->conectarBejerman();
            return;
        }

        if ($nombre_base === 'carrito') {
            throw new InvalidArgumentException('La conexión carrito fue retirada junto con WooCommerce.');
        }

        throw new InvalidArgumentException('Base de datos desconocida: ' . $nombre_base);
    }

    private function conectarBejerman()
    {
        foreach (['HOST3', 'PORT3', 'DBASE3'] as $constante) {
            if (!defined($constante)) {
                throw new RuntimeException(
                    'La conexión Bejerman requiere configurar ' . $constante . '.'
                );
            }
        }

        return new PDO(
            'sqlsrv:Server=' . HOST3 . ',' . PORT3 . ';Database=' . DBASE3 . ';ConnectionPooling=0',
            null,
            null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
}

/**
 * cliente OAuth genérico conservado por compatibilidad.
 */
class Token
{
    public $token_data;
    protected $con_info;

    public function __construct($url, $user, $pass, $secret)
    {
        $this->con_info = (object) [
            'url' => trim((string) $url),
            'user' => (string) $user,
            'pass' => (string) $pass,
            'secret' => (string) $secret,
        ];
    }

    public function getToken()
    {
        $this->validarConfiguracion();
        $this->token_data = llamarAPI(
            'POST',
            $this->con_info->url,
            [
                'username' => $this->con_info->user,
                'password' => $this->con_info->pass,
                'grant_type' => 'password',
            ],
            $this->con_info->secret
        );
        return $this->token_data;
    }

    protected function validarConfiguracion()
    {
        foreach (['url', 'user', 'pass', 'secret'] as $campo) {
            if ($this->con_info->{$campo} === '') {
                throw new RuntimeException(
                    'Falta configurar zetti_token_' . ($campo === 'pass' ? 'password' : $campo) . '.'
                );
            }
        }
    }
}

/**
 * generador de token específico de las API de Zetti.
 */
class TokenZetti extends Token
{
    public function __construct($url = null, $user = null, $pass = null, $secret = null)
    {
        $configuracion = self::configuracion();
        parent::__construct(
            $url !== null ? $url : $configuracion['url'],
            $user !== null ? $user : $configuracion['user'],
            $pass !== null ? $pass : $configuracion['pass'],
            $secret !== null ? $secret : $configuracion['secret']
        );
    }

    private static function configuracion()
    {
        global $app_config;

        return [
            'url' => self::valorConfigurado($app_config, 'zetti_token_url', 'ZETTI_TOKEN_URL'),
            'user' => self::valorConfigurado($app_config, 'zetti_token_user', 'ZWEBUSER'),
            'pass' => self::valorConfigurado($app_config, 'zetti_token_password', 'ZWEBPASS'),
            'secret' => self::valorConfigurado($app_config, 'zetti_token_secret', 'ZETTI_TOKEN_SECRET'),
        ];
    }

    private static function valorConfigurado($configuracion, $clave, $constante_legacy)
    {
        if (is_array($configuracion) && array_key_exists($clave, $configuracion)) {
            return (string) $configuracion[$clave];
        }
        if (defined($constante_legacy)) {
            return (string) constant($constante_legacy);
        }
        return '';
    }
}
