<?php
// podria dividir este archivo en varios otros, esto es un comienzo:
require_once __DIR__ . '/manejo_errores.php';

// permitiendo optar por no utilizar el autoload, acelera la carga cuando no es necesario su uso
if(!isset($usa_autoload) || $usa_autoload == true){
	require_once(RUTA . '/vendor/autoload.php');
}

// para el funcionamiento de la lectura y generación de archivos XLSX
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
// var dumps bonitos --> funciones dump() y dd() (dump&die)
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\ContextProvider\CliContextProvider;
use Symfony\Component\VarDumper\Dumper\ContextProvider\SourceContextProvider;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\Dumper\ServerDumper;
use Symfony\Component\VarDumper\VarDumper;
//-----------------------------------------------------------
//FUNCIONES
//-----------------------------------------------------------

//solo existen por defecto en php8 o posterior
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('strrevpos')) {
	function strrevpos($instr, $needle) {
		$rev_pos = strpos (strrev($instr), strrev($needle));
		if ($rev_pos===false) return false;
		else return strlen($instr) - $rev_pos - strlen($needle);
	};
}
if (!function_exists('str_ends_with')) {
	function str_ends_with($haystack, $needle) {
		$length = strlen($needle);
		return $length > 0 ? substr($haystack, -$length) === $needle : true;
	}
}

//elimina el texto dentro de etiquetas html, con excepciones si se requiere, o con exclusividad
function strip_tags_content($text, $tags = '', $invert = FALSE) {
	preg_match_all('/<(.+?)[\s]*\/?[\s]*>/si', trim($tags), $tags);
	$tags = array_unique((array)$tags[1]);
	if(is_array($tags) AND count($tags) > 0) {
		if($invert == FALSE) {
			return preg_replace('@<(?!(?:'. implode('|', $tags) .')\b)(\w+)\b.*?>.*?</\1>@si', '', $text);
		}	
		else {
            return preg_replace('@<('. implode('|', $tags) .')\b.*?>.*?</\1>@si', '', $text);
		}
	}
	elseif($invert == FALSE) {
		return preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $text);
	}
	return $text;
}
// $text = '<b>sample</b> text with <del>tags</del>';				// los <div> generan saltos de línea misteriosos, ojo
// echo strip_tags_content($text) . '<br>';							// borra todo entre tags
// echo strip_tags_content($text, '<del>') . '<br>';				// deja sólo el tag especificado
// echo strip_tags_content($text, '<del>', true) . '<br>';			// borra solo el tag especificado

//alternativa a var_dump
function dump_debug($input, $collapse=false) {
    $recursive = function($data, $level=0) use (&$recursive, $collapse) {
        global $argv;
        $isTerminal = isset($argv);
        if (!$isTerminal && $level == 0 && !defined("DUMP_DEBUG_SCRIPT")) {
            define("DUMP_DEBUG_SCRIPT", true);
            echo '<script language="Javascript">function toggleDisplay(id) {';
            echo 'var state = document.getElementById("container"+id).style.display;';
            echo 'document.getElementById("container"+id).style.display = state == "inline" ? "none" : "inline";';
            echo 'document.getElementById("plus"+id).style.display = state == "inline" ? "inline" : "none";';
            echo '}</script>'."\n";
        }
        $type = !is_string($data) && is_callable($data) ? "Callable" : ucfirst(gettype($data));
        $type_data = null;
        $type_color = null;
        $type_length = null;
        switch ($type) {
            case "String": 
                $type_color = "green";
                $type_length = strlen($data);
                $type_data = "\"" . htmlentities($data) . "\""; break;
            case "Double": 
            case "Float": 
                $type = "Float";
                $type_color = "#0099c5";
                $type_length = strlen($data);
                $type_data = htmlentities($data); break;
            case "Integer": 
                $type_color = "red";
                $type_length = strlen($data);
                $type_data = htmlentities($data); break;
            case "Boolean": 
                $type_color = "#92008d";
                $type_length = strlen($data);
                $type_data = $data ? "TRUE" : "FALSE"; break;
            case "NULL": 
                $type_length = 0; break;
            case "Array": 
                $type_length = count($data);
        }
        if (in_array($type, array("Object", "Array"))) {
            $notEmpty = false;
            foreach($data as $key => $value) {
                if (!$notEmpty) {
                    $notEmpty = true;
                    if ($isTerminal) {
                        echo $type . ($type_length !== null ? "(" . $type_length . ")" : "")."\n";
                    } else {
                        $id = substr(md5(rand().":".$key.":".$level), 0, 8);
                        echo "<a href=\"javascript:toggleDisplay('". $id ."');\" style=\"text-decoration:none\">";
                        echo "<span style='color:#666666'>" . $type . ($type_length !== null ? "(" . $type_length . ")" : "") . "</span>";
                        echo "</a>";
                        echo "<span id=\"plus". $id ."\" style=\"display: " . ($collapse ? "inline" : "none") . ";\">&nbsp;&#10549;</span>";
                        echo "<div id=\"container". $id ."\" style=\"display: " . ($collapse ? "" : "inline") . ";\">";
                        echo "<br />";
                    }
                    for ($i=0; $i <= $level; $i++) {
                        echo $isTerminal ? "|    " : "<span style='color:black'>|</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                    }
                    echo $isTerminal ? "\n" : "<br />";
                }
                for ($i=0; $i <= $level; $i++) {
                    echo $isTerminal ? "|    " : "<span style='color:black'>|</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                }
                echo $isTerminal ? "[" . $key . "] => " : "<span style='color:black'>[" . $key . "]&nbsp;=>&nbsp;</span>";
                call_user_func($recursive, $value, $level+1);
            }
            if ($notEmpty) {
                for ($i=0; $i <= $level; $i++) {
                    echo $isTerminal ? "|    " : "<span style='color:black'>|</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
                }
                if (!$isTerminal) {
                    echo "</div>";
                }
            } else {
                echo $isTerminal ? 
                        $type . ($type_length !== null ? "(" . $type_length . ")" : "") . "  " : 
                        "<span style='color:#666666'>" . $type . ($type_length !== null ? "(" . $type_length . ")" : "") . "</span>&nbsp;&nbsp;";
            }
        } else {
            echo $isTerminal ? 
                    $type . ($type_length !== null ? "(" . $type_length . ")" : "") . "  " : 
                    "<span style='color:#666666'>" . $type . ($type_length !== null ? "(" . $type_length . ")" : "") . "</span>&nbsp;&nbsp;";
            if ($type_data != null) {
                echo $isTerminal ? $type_data : "<span style='color:" . $type_color . "'>" . $type_data . "</span>";
            }
        }
        echo $isTerminal ? "\n" : "<br />";
    };
    call_user_func($recursive, $input);
}

//convierte un string, o todo string en un array u objeto, a utf8 --> usado en debugging
function utf8izar($d) 
{
    if (is_array($d)) {
        foreach ($d as $k => $v) {
            $d[$k] = utf8izar($v);
        }
    } else if (is_object($d)) {
        foreach ($d as $k => $v) {
            $d->$k = utf8izar($v);
        }
    } else if (is_string($d)) {
        // Verificar si el string ya está en UTF-8
        if (mb_detect_encoding($d, 'UTF-8', true) === false) {
            return utf8_encode($d);
        }
    }
    return $d;
}

//formatea numeros como moneda, ignora decimales más allá del segundo
function format_number(
	$num, //numero a formatear
	$desnudo = false //si esto es true, desnuda el numero y lo deja en formato operable
)
{
	if ($num === '' || is_null($num) || !$num) return '';
	$num = strval($num);
	if(strpos($num, ',') === false && strpos($num, '.') === false){
		$num_decimales = 0;
	}else{
		$num_decimales = 3;
		$num .= '0';
	}
	//repito este proceso hasta obtener dos decimales
	while($num_decimales >= 3){
		$num = substr($num, 0, -1);
		//primero determina separador de decimales
		$comarray = explode(',', $num);
		$separador = strlen($comarray[count($comarray) - 1]) < 3 ? ',' : '.';
		//luego la cantidad de decimales
		$truarray = explode($separador, $num);
		$num_decimales = strlen($truarray[count($truarray) - 1]);
	}
	//ahora desnuda el número y lo convierte en un array
	$naked = str_split(
		filter_var(
			str_replace(array(',','.'), '', $num), FILTER_SANITIZE_NUMBER_FLOAT
		)
	);
	//si quiero el numero sin nada raro, para operar
	if($desnudo){
		$respuesta = '';
		$n = count($naked) - 1;
		//recorro el array para poner punto de decimales, de atras para adelante
		for($x = $n; $x >= 0; $x--){
			if($x == $n && $num_decimales == 1){
				$respuesta .= '0';
			}
			if($x == $n && $num_decimales == 0){
				$respuesta .= '00.';
			}
			$respuesta .= $naked[$x];
			if($x == $n - $num_decimales + 1){
				$respuesta .= '.';
				if($x == 0){
					$respuesta .= '0';
				}
			}
		}
		$naked_num = (double) strrev($respuesta);
		return $naked_num;
	}
	
	//si quiero el numero adornado y bonito
	$respuesta = '';
	$puntos = 0;
	$n = count($naked) - 1;
	//recorro el array para poner separadores, de atras para adelante
	for($x = $n; $x >= 0; $x--){
		if($x == $n && $num_decimales == 1){
			$respuesta .= '0';
		}
		if($x == $n && $num_decimales == 0){
			$respuesta .= '00,';
		}
		$respuesta .= $naked[$x];
		if($x == $n - $num_decimales + 1){
			$respuesta .= ',';
			if($x == 0){
				$respuesta .= '0';
			}
		}
		if($x == $n - ($num_decimales + 2 + $puntos * 3) && $x != 0){
			$respuesta .= '.';
			$puntos++;
		}
		if($x == 0){
			// $respuesta .= ' $';	// si corresponde lo pondré cuando dibuje el número
		}
	}
	return strrev($respuesta);
}

//busca un objeto dentro de un array de ellos, devuelve el objeto completo
function findElem(
	$prop, //propiedad del objeto a comparar
	$comp, //comparador, la propiedad debe tener este valor
	$array_obj	//el array de objetos en el que buscar
){
	foreach ($array_obj as $linea) {
		if ($linea->$prop == $comp) {
			return $linea;
		}
	}
	return null;
}

//mandar datos por post ---> versión vieja de llamarAPI(), pero no la borro por retrocompatibilidad
// * Deprecated 
function get_webpage(
	$url, 			//'www.elchoto.com/api/medidadelapija' o '200.201.202.203:8888/apiporonga'
	$params			//array asociativo estilo [['username'=>'Carlos_Menem77', 'medidas'=>'90-60-90']]
){
	
	try{
		$options = array(
			CURLOPT_RETURNTRANSFER => true,   				// return web page
			CURLOPT_HEADER         => false,  				// don't return headers
			CURLOPT_FOLLOWLOCATION => true,   				// follow redirects
			CURLOPT_MAXREDIRS      => 10,     				// stop after 10 redirects
			CURLOPT_ENCODING       => "",     				// handle compressed
			CURLOPT_USERAGENT      => "app_comisiones", 	// name of client
			CURLOPT_AUTOREFERER    => true,   				// set referrer on redirect
			CURLOPT_CONNECTTIMEOUT => 120,    				// time-out on connect
			CURLOPT_TIMEOUT        => 120,    				// time-out on response

			CURLOPT_POST 		   => true,
			CURLOPT_POSTFIELDS	   => $params,
		); 

		$ch = curl_init($url);
		curl_setopt_array($ch, $options);
		$content  = curl_exec($ch);
		curl_close($ch);
		return $content;
	}
	
	catch(Exception $e){
		return "Error tratando de contactar con la api \n Descripción: " . $e->GetMessage();
	}
}

//función armada para llamar apis de zetti/woocommerce específicamente, pero puede funcionar para otros endpoints
function llamarAPI($method, $url, $data, $auth, $access_token = false)
{
	// $method: POST, PUT, GET etc
	// $data: array("param" => "value") en un get seria cosa.php?param=value
	// $auth: usuario:contraseña, o false si no se usa
	// $access_token: del objeto recibido por generarToken(), la propiedad access_token (sólo de ser necesario un token)
	try{
		//inicializa
		$curl = curl_init();
		//configura según corresponda corresponde
		switch ($method) {
			case "POST":
				curl_setopt($curl, CURLOPT_POST, true);
				if ($data) {
					curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
				}
				break;
			case "PUT":
				//curl_setopt($curl, CURLOPT_PUT, true);
				curl_setopt($curl, CURLOPT_POST, true);
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
				if ($data) {
					curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
					//$url = sprintf("%s?%s", $url, http_build_query($data));
				}
				break;
			default:
				if ($data) {
					$url = sprintf("%s?%s", $url, http_build_query($data));
				}
		}
        
        if($auth){
            // autenticación básica
            curl_setopt($curl, CURLOPT_USERPWD, $auth);
            // url
            curl_setopt($curl, CURLOPT_URL, $url);
        }else{
            // url
            curl_setopt($curl, CURLOPT_URL, $url);
        }
        
		//define headers dependiendo de si se está pasando un access token o no
		if($access_token){
			//si hay token, lo usa
			$headers = array(
				'Content-Type:application/json',
				'Authorization:Bearer ' . $access_token 
			);
		}else{
			//si no hace una autenticación simple
			$headers = array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Basic ' . base64_encode($auth) //usuario:contraseña
			);
		}
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        
		//setea opciones de configuración
		curl_setopt_array($curl, array(
			// otras opciones de configuración
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_RETURNTRANSFER 	=> true,   					// return web page
			CURLOPT_HEADER         	=> false,  					// don't return headers
			CURLOPT_FOLLOWLOCATION 	=> true,   					// follow redirects
			CURLOPT_MAXREDIRS      	=> 10,     					// stop after 10 redirects
			CURLOPT_ENCODING       	=> '',     					// handle compressed
			CURLOPT_USERAGENT      => 'global', 				        // name of client
			CURLOPT_AUTOREFERER    	=> true,   					// set referrer on redirect
			CURLOPT_CONNECTTIMEOUT 	=> 120,    					// time-out on connect
			CURLOPT_TIMEOUT        	=> 0,    					// time-out on response
			CURLOPT_HTTP_VERSION	=> CURL_HTTP_VERSION_NONE, 	//CURL_HTTP_VERSION_1_1 en script de León
		));

		//ejecuta
		$result = curl_exec($curl);
		//cierra curl
		curl_close($curl);
		//devuelve respuesta
		if(!$result){return("No se obtuvo respuesta del servidor");}
		return json_decode($result);
	}
	catch(Exception $e){
		return "Error tratando de contactar con el endpoint en $url \n Descripción: " . $e->GetMessage();
	}
}


//genera un csv desde un array bidimenisonal dado
function generarCSV(
	$array, 	//array bidimensional para generar archivo
	$ruta, 		//'C:\wamp64\www\Desarrollos\app_random\algo.csv' o '/var/www/html/app_random/algo.csv'
	$delimitador = ';', 	
	$encapsulador = '"'	
){
	$fp = fopen($ruta, 'w');
	//fputs($fp, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
	fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
	//$array = array_map("utf8_decode", $array);
	foreach ($array as $linea) {
		fputcsv($fp, $linea, $delimitador, $encapsulador);
	}
	fclose($fp);

	// $spreadsheet = new Spreadsheet();
	// $sheet = $spreadsheet->getActiveSheet();
	// $sheet->fromArray($array);	 
	// $writer = IOFactory::createWriter($spreadsheet, "Csv");
	// $writer->setDelimiter(';');  // Set delimiter.
	// $writer->save($ruta);	
}

//llama a leer_excel() o leer_csv() segun corresponda
function leer_grilla(
	$archivo, 	//'C:\wamp64\www\Desarrollos\app_random\algo.csv' o '/var/www/html/app_random/algo.csv'
	$pag = 0,	//pagina del excel a leer
	$delimitador = ';',	// ';' o ','
	$files = false		// si se trae informacion desde $_FILES, poner el nombre del input aca
){
	$array_total = array();
	$ext = 
		$files ? 
		ucwords(after_last ('.', basename($_FILES[$files]['name']))) : 
		ucwords(after_last ('.', $archivo));
	//lectura de archivos .csv
	if( str_ends_with(strtolower($archivo), '.csv') ){
		$array_total = leer_csv($archivo, $delimitador, $files);
	}else{
		$array_total = leer_excel($archivo, $pag, $ext, $files);
	}
	return $array_total;
}
// function leer_excel($archivo, $pag, $ext, $files){
// 	return array('ERROR','Librerías de Excel no instaladas o no habilitadas en el archivo de funciones.');
// }

//lee un archivo csv y devuelve en un array bidimenisonal
function leer_csv(
	$archivo, 			//'C:\wamp64\www\Desarrollos\app_random\algo.csv' o '/var/www/html/app_random/algo.csv'
	$delimitador = ';',	// ';' o ','
	$files = false
){
	if($files){
		$archivo = $_FILES[$files]['tmp_name'];
	}
	$array_total = array();
	if(!empty($archivo) && !empty($delimitador) && is_file($archivo)){

		$fp = fopen($archivo,"r");
		while ($data = fgetcsv($fp, 0, $delimitador)){
			$num = count($data);
			$array_total[] = 
				//$data; 
				array_map("utf8_encode",$data);
		}
		fclose($fp);
		return $array_total;
	}
	else{
		return $array_total;
	}
}

//busca y devuelve la primer línea que pueda ser considerada titular de un array bidimencional
function identificar_titulos(
	$array,						//array bidimencional
	$altura_titulos=true,		//si es true, devuelve la altura donde fueron encontrados en la posicion 0
	$indice_tolerancia = 0 		//float entre 0 y 1, por defecto 0.35
){
	//------------------------------------------------------------------------
	//ENCUENTRA LA LINEA DE TITULOS
	//------------------------------------------------------------------------
	$titulos = false; $altura=0;
	$n = count($array[0]);		//determina cantidad de celdas en una línea
	while($titulos === false){
		//define la máxima cantidad de campos nulos en la línea que se busca, usando el índice de tolerancia
		$max_nulls = ceil( $n - $n * (1 - $indice_tolerancia) ); //echo '<br>MAX_NULLS: '.$max_nulls;
		$n_linea = -1;
		foreach($array as $linea){
			$null_counter = 0;
			foreach($linea as $celda){
				//si es nulo o un campo numerico, no cuenta (format number devuelve 0 cuando hay solo texto)
				if( $celda === null || format_number($celda, true) != false || $celda === '#REF!'){
					$null_counter++;
				}
			}
			$n_linea++;
			//si la línea tiene demasiados nulls, es improbable que sea los títulos, la saltea
			if($null_counter >= $max_nulls){
				continue;
			//la primer línea con información completa se considera correcta
			}else{
				$titulos = $linea;
				$altura = $n_linea;
				break;
			}
		}
		//si aun no encontró nada, aumenta la tolerancia de nulls para el próximo intento en un 10%
		$indice_tolerancia += 0.1;	
	}

	//------------------------------------------------------------------------
	//LIMPIA LA LINEA DE TITULOS
	//------------------------------------------------------------------------
	$titulos[0] = str_replace( array('ï»¿','"'),'', $titulos[0]);	//limpia el BOM si lo encuentra
	$titulos = limpia_linea($titulos);								//quita celdas vacías en los extremos

	//------------------------------------------------------------------------
	//BUSCA POSIBLE MULTICOLUMNA (separada por una o mas celdas vacías)
	//------------------------------------------------------------------------
	$posible = array(array(false), array(true));
	//recorre titulos
	$nullcount=0; $valid_null=array();
	for($x=0; $x<count($titulos);$x++){
		//si encuentra un null o un elemento vacío
		if( $titulos[$x] === null || $titulos[$x] === '' ){
			//divide en trozos
			$posible = array_chunk(
				array_diff(
					$titulos,
				array(null,'') ),							//quitando los nulls y vacios previamente
				($x - $nullcount)							//del tamaño de la posición en que se encontró
			);
			//si el primer array y el último tras la división son iguales, se considera correcto
			//en caso contrario no hace cambios
			if( count( array_diff($posible[0], $posible[ count($posible)-1 ]) ) == 0 ){
				$titulos = $posible[0];
				//y vuelve a agregar los espacios vacios
				foreach($valid_null as $v){
					array_splice($titulos, $v, 0, '');
				}
				break;
			}else{
				$valid_null[]=$x;
				$nullcount++; 
			}
		}
	}

	if($altura_titulos){
		$titulos[] = $altura;
	}

	return $titulos;
}

//elimina elementos nulos al principio y final de un array
function limpia_linea(
	$array_uni		//array_unidimencional
){
	//elimina espacios vacios al principio del array
	$n=count($array_uni);
	for($x=0; $x<$n; $x++){
		if( $array_uni[$x] === null || $array_uni[$x] === '' ){
			unset($array_uni[$x]);
		}else{
			break;
		}
	}
	//elimina espacios al final del array
	$array_uni = array_values($array_uni);
	$n=count($array_uni);
	for($x=$n-1; $x>0; $x--){
		if( $array_uni[$x] === null || $array_uni[$x] === '' ){
			unset($array_uni[$x]);
		}else{
			break;
		}
	}
	return $array_uni;
}

//unifica un excel multicolumna desde los titulos
function unificar_multi_columna($array, $titulos, $coment=false){
	$n_tit = count($titulos); $n = count($array);
	$cortes = array(); $celdas_utiles=array();
	//var_dump($titulos);
	//recorre líneas del excel para determinar por donde cortar
	for($x=0; $x<$n; $x++){
		$linea = $array[$x];
		$m = count($linea);
		$tit=0; $tit_ant=0; $celdas=array(); $z=$n_tit;
		$ultimo_bien = false;
		//recorre celdas de la línea
		for($y=0; $y<$m; $y++){
			//si encuentra un título en la celda, pasa al siguiente
			if(str_replace( array('ï»¿','"'),'', $linea[$y]) == $titulos[$tit] ){
				$celdas[] = $y;
				if($tit==0){
					$z = $y;
				}
				$ultimo_bien = true;
				$tit_ant = $tit;
				$tit++;
			}
			else
			//si el anterior estuvo bien y este no, reintenta y lo sobreescribe
			if( $ultimo_bien && $linea[$y] == $titulos[$tit_ant]){
				$celdas = array($y);
				if($tit==0){
					$z = $y;
				}
				$ultimo_bien = false;
			}
			//si no corresponde la celda al título esperado, reinicia
			else{
				$celdas=array();
				$tit=0;
				$tit_ant=0;
				$ultimo_bien = false;
				$z=$n_tit;
			}
			//si se encuentra la secuencia de títulos completa
			if($tit==$n_tit){
				//agrega la celda de corte al array
				if( $z > $n_tit){
					$cortes[] = $z;
				}
				$celdas_utiles = array_merge($celdas_utiles, $celdas);
				$celdas=array();
				$tit=0;$tit_ant=0;
			}
		}
		if(count($cortes) > 0){
			break;
		}
	}
	$celdas_utiles = array_unique($celdas_utiles, SORT_NUMERIC );
	//$celdas_utiles = array(1,2,3);
	if($coment){
		echo '<br>CELDAS DE CORTE PARA MULTICOLUMNAS:';
		highlight_string("<?php\n\$data =\n" . var_export($cortes, true) . ";\n?>");
		echo '<br>CELDAS UTILES:';
		highlight_string("<?php\n\$data =\n" . var_export($celdas_utiles, true) . ";\n?>");
	}

	//recorre líneas del excel y las corta por donde corresponde
	for($x=0; $x<$n; $x++){
		//recorre celdas en linea
		$m = count($array[$x]);
		for($y=($m-1); $y>=0; $y--){
			//si la celda no es útil
			if(!in_array($y, $celdas_utiles)){
				//la elimina
				unset($array[$x][$y]);
			}
			//si es una celda de corte
			if(in_array($y, $cortes)){
				//la manda abajo
				$array[] = array_slice($array[$x],$y,$n_tit);
			}
		}
		$array[$x] = array_slice( array_values($array[$x]), 0, $n_tit);
	}

	//var_dump($array);
	return $array;
}

//elimina reiteraciones de títulos o líneas vacías
function eliminar_redundancia($array, $titulos, $indice_tolerancia = 0.2, $coment=false){
	$n_tit = count($titulos); $n = count($array);
	//recorre líneas apiladas para determinar si hay que eliminarlas
	for($x=0; $x<$n; $x++){
		$linea = $array[$x];
		$tit=0; $nulos=0;
		//recorre celdas de la línea
		for($y=0; $y<$n_tit; $y++){
			//si encuentra un título en la celda, pasa al siguiente y suma un encontrado
			if(str_replace( array('ï»¿','"'),'', $linea[$y]) == $titulos[$tit]){
				$tit++;
			}
			//si la celda está vacía, suma una nula
			if($linea[$y] == null || $linea[$y] == ''){
				$nulos++;
			}
			//si hay al menos un dato relevante, no borra nada
			if( $y>0 && $tit==0 && $nulos==0 ){
				break;
			}

			//si se encuentra la secuencia de títulos completa, está todo o parcialmente vacío
			$max_nulls = ceil( $n_tit - $n_tit * (1 - $indice_tolerancia) );
			if($tit==$n_tit || $nulos >= $max_nulls ){
				//borra la línea
				unset($array[$x]);
				break;
			}
		}
	}
	//reagrega los títulos nuevamente
	array_unshift($array, $titulos);

	//limpía duplicados y devuelve el array resultante
	return array_unique($array, SORT_REGULAR);
}

//devuelve un array de todos los archivos y carpetas dentro de un directorio
function obtener_archivos_directorio(
	$ruta, //'C:\wamp64\www\Desarrollos\app_random' o '/var/www/html/app_random'
	$subcarpetas = false, 	//si queremos que se examinen las subcarpetas dentro del directorio
	$profundidad = true,	//si queremos que el contenido de las subcarpetas esté dentro de sub-arrays
	$dibujar = false,
	$ignorar_carpetas = false
){
    // Se comprueba que realmente sea la ruta de un directorio
    if (is_dir($ruta)){
        // Abre un gestor de directorios para la ruta indicada
        $gestor = opendir($ruta);
		$array = array();
		
		if($dibujar){
			echo '<ul>';
		}
        // Recorre todos los elementos del directorio
        while (($archivo = readdir($gestor)) !== false)  {
                
            $ruta_completa = $ruta . "/" . $archivo;

            // Se muestran todos los archivos y carpetas excepto "." y ".."
            if ($archivo != "." && $archivo != "..") {
                // Si es un directorio se recorre recursivamente, si se activó esa opción
                if (is_dir($ruta_completa) && $subcarpetas && !$ignorar_carpetas) {
					if($profundidad){
						if($dibujar){
							echo '<li>'.$archivo.'</li>';
						}
						$array[$archivo] = obtener_archivos_directorio($ruta_completa, $subcarpetas, $profundidad, $dibujar);
					}else{
						$array = array_merge($array, obtener_archivos_directorio($ruta_completa, $subcarpetas, $profundidad, $dibujar));
					}
                } else {
					if(!$ignorar_carpetas || !is_dir($ruta_completa)){
						$array[] = $archivo;
						if($dibujar){
							echo '<li><a href="descargar.php?ruta='.$ruta_completa.'">'.$archivo.'</a></li>';
						}
					}
                }
            }
        }
		if($dibujar){
			echo '</ul>';
		}
        // Cierra el gestor de directorios
        closedir($gestor);
        return $array;
    } else {
		return false;
    }
}

//borra archivos de un directorio que no estén en un array que le pasemos
function borra_archivos_noUsados(
	$ruta,			//ruta al directorio para borrar archivos
	$conservar		//archivos a conservar
){
	$subcarpetas = true; $profundidad = true;
	
	// Se comprueba que realmente sea la ruta de un directorio
    if (is_dir($ruta)){
        // Abre un gestor de directorios para la ruta indicada
        $gestor = opendir($ruta);
		$array = array();
		
        // Recorre todos los elementos del directorio
        while (($archivo = readdir($gestor)) !== false)  {
                
            $ruta_completa = $ruta . "/" . $archivo;

            // Se muestran todos los archivos y carpetas excepto "." y ".."
            if ($archivo != "." && $archivo != "..") {
                // Si es un directorio se recorre recursivamente, si se activó esa opción
                if (is_dir($ruta_completa) && $subcarpetas) {
					if($profundidad){
						borra_archivos_noUsados($ruta_completa, $conservar);
					}
                } else {
					$n=count($conservar);
					$found=false;
					for($x=0; $x < $n; $x++){
						if(str_contains($archivo,$conservar[$x])){
							$found = true; break;
						}
					}
					if(!$found){
						unlink($ruta_completa);
					}
                }
            }
        }
        
        // Cierra el gestor de directorios
        closedir($gestor);
	}
}

//determina si un array es asociativo o no, o si los elementos dentro del array lo son 
function is_associative(array $array, bool $recursivo = false) {
	//array vacio = false
	if (array() === $array){ return false; }

	//chequea este array para ver si es asociativo o no
	if(!$recursivo){
		return count(array_filter(array_keys($array), 'is_string')) > 0;
	}

	//recursivo (ignora si el array padre es asociativo o no)
	foreach($array as $nivel_siguiente){
		if(!is_associative($nivel_siguiente)){ return false; }
	}
	return true;
}

//convierte un array asociativo en uno escalar
function escalarizar(array $array, bool $recursivo = false) {
	//array vacio = false
	if (array() === $array){ return array(); }

	//convierte este array en
	if(!$recursivo){
		$nuevo_array = array();
		foreach($array as $key => $val){
			$nuevo_array[] = $val;
		}
		return $nuevo_array;
	}

	//modo recursivo 
	$nuevo_array = array();
	foreach($array as $nivel_siguiente){
		$nuevo_array[] = escalarizar($nivel_siguiente);
	}
	return $nuevo_array;
}

//buscar un valor en un array bidimensional
function recursive_array_search($needle,$haystack) {
    foreach($haystack as $key=>$value) {
        $current_key=$key;
        if($needle===$value OR (is_array($value) && recursive_array_search($needle,$value) !== false)) {
            return $current_key;
        }
    }
    return false;
}

//after ('@', 'biohazard@online.ge');
//returns 'online.ge'
//from the first occurrence of '@'
function after ($esto, $inthat){
	if (!is_bool(strpos($inthat, $esto)))
	return substr($inthat, strpos($inthat,$esto)+strlen($esto));
};

//after_last ('[', 'sin[90]*cos[180]');
//returns '180]'
//from the last occurrence of '['
function after_last ($esto, $inthat){
	if (!is_bool(strrevpos($inthat, $esto)))
	return substr($inthat, strrevpos($inthat, $esto)+strlen($esto));
};

//before ('@', 'biohazard@online.ge');
//returns 'biohazard'
//from the first occurrence of '@'
function before ($esto, $inthat){
	return substr($inthat, 0, strpos($inthat, $esto));
};

//before_last ('[', 'sin[90]*cos[180]');
//returns 'sin[90]*cos['
//from the last occurrence of '['
function before_last ($esto, $inthat){
	return substr($inthat, 0, strrevpos($inthat, $esto));
};

//between ('@', '.', 'biohazard@online.ge');
//returns 'online'
//from the first occurrence of '@'
function between ($esto, $that, $inthat){
	return before ($that, after($esto, $inthat));
};

//between_last ('[', ']', 'sin[90]*cos[180]');
//returns '180'
//from the last occurrence of '['
function between_last ($esto, $that, $inthat){
	return after_last($esto, before_last($that, $inthat));
};

//determina el tamaño máximo de archivos que se puede subir teniendo en cuenta post_max_size y upload_max_filesize
function file_upload_max_size() {
	static $max_size = -1;
	if ($max_size < 0) {
		// Start with post_max_size.
		$post = ini_get('post_max_size');
		$post_max_size = parse_size($post);
		if ($post_max_size > 0) {
			$max_size = $post_max_size;
		}

		// If upload_max_size is less, then reduce. Except if upload_max_size is
		// zero, which indicates no limit.
		$upload = ini_get('upload_max_filesize');
		$upload_max = parse_size($upload);
		if ($upload_max > 0 && $upload_max < $max_size) {
			$max_size = $upload_max;
		}
	}

	//pasa de bytes a MB para dibujarlo
	return $max_size == 0 ? 'Sin límite' : $max_size / 1024 / 1024 . ' MB';
}

//pasa de MB o GB (M o G, la nomenclatura de php.ini) a bytes para hacer comparaciones
function parse_size($size) {
	$unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
	$size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
	if ($unit) {
		// Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
		return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
	}else {
		return round($size);
	}
}

function filtrar_array_objetos($array, $prop, $val_que_no){
	$respuesta = array();
	$n = count($array);
	for($x=0; $x<$n; $x++){
		if($array[$x]->$prop != $val_que_no ){
			$respuesta[] = $array[$x];
		}
	}
	return $respuesta;
}

/* */ //deshabilitar si no se usan para evitar errores en el archivo
//-----------------------------------------------------------
//FUNCIONES CON DEPENDENCIAS
//-----------------------------------------------------------
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//es necesario usar "composer require phpoffice/phpspreadsheet" en la carpeta raiz

//lee un archivo xlsx y devuelve en un array bidimenisonal
//es necesario usar "composer require phpoffice/phpspreadsheet" en la carpeta raiz
function leer_excel(
	$archivo, 	//'C:\wamp64\www\Desarrollos\app_random\algo.xlsx' o '/var/www/html/app_random/algo.xlsx'
	$pag = 1,	//pagina del excel a leer
	$ext = 'Xlsx',
	$files = false,
	$silent = false
){
	if($files){
		$archivo = $_FILES[$files]['tmp_name'];
	}
	$reader = IOFactory::createReader($ext); 
	$spreadsheet = $reader->load($archivo);    
	$pag_ok = ($pag-1) < $spreadsheet->getSheetCount() ? ($pag-1) : $spreadsheet->getSheetCount() - 1;    
	if( !$silent && $pag_ok != ($pag-1) ) {
		echo '<br><i style="margin-left:25px; color:red;">Página inexistente. Se utiliza la pág.<b>'.($pag_ok+1).'</b> (última página con datos) en su lugar.</i>';
	}              
    $spreadsheet->setActiveSheetIndex($pag_ok);
    $array_total = $spreadsheet->getActiveSheet()->toArray(); 

	return $array_total;
}

//genera un archivo excel desde un array en la carpeta seleccionada
//es necesario usar "composer require phpoffice/phpspreadsheet" en la carpeta raiz
function xlsx_desde_array(
	$array, 			//array (bidimencional), las celdas del excel o sea
	$exportar=false,	//bool, si genera o no un archivo desde el spreadsheet
	$dir='prueba.xlsx'	//string, ruta completa para exportar, ej: '/var/www/html/app_random/algo.xlsx'
){
	//genera spreadsheet
	$spreadsheet = new Spreadsheet;
	$sheet = $spreadsheet->getActiveSheet();
	$sheet->fromArray($array, NULL, 'A1');
	//si se seleccionó exportar:
	if($exportar){
		//formatea
		$sheet	->getStyle('A1:Z1')
				->getFont()
				->setBold(true);
		$sheet	->getStyle('A1:Z1')
				->getFont()
				->setSize(15);
		$sheet	->getStyle('A1:Z'.count($array))
				->getAlignment()
				->setHorizontal( Alignment::HORIZONTAL_CENTER );      
		$sheet	->getStyle('A2:Z'.count($array))
				->getFont()
				->setSize(9);
		foreach (range('A','Z') as $col) {
			$sheet->getColumnDimension($col)->setAutoSize(true);
		}
		//genera archivo desde spreadsheet
		$writer = new Xlsx($spreadsheet);
		$writer->save($dir);
	}
	//devuelve spreadsheet	
	return $spreadsheet;
}

//genera una hoja de excel para un documento ya inicializado
function sheetFromData(
	object $spreadsheet, 
	array $array_datos, 
	string $nombre_pagina, 
	array $options = array(
		'bodyFontSize', 
		'titleFontSize',
		'explicitString',
		'explicitMoney',
		'coeficienteFuente',
		'fuente',
		'crear_pagina'
	)
){
	// coeficienteFuente: multiplicador al tamaño de celdas dependiendo de la fuente utilizada (Calibri = 1.005, Consolas = 1.25) aprox
	$default_values = array(
		'bodyFontSize' => 10, 
		'titleFontSize' => 10, 
		'explicitString' => array(), 
		'explicitMoney' => array(),  
		'coeficienteFuente' => 1.25,  
		'fuente' => 'Consolas',
		'crear_pagina' => true
	);
	$options = array_merge($default_values, $options);
	if($options['crear_pagina']){
		$spreadsheet->createSheet();
	}
	$spreadsheet->setActiveSheetIndex( count($spreadsheet->getAllSheets()) - 1);
	$sheet = $spreadsheet->getActiveSheet();
	$sheet->setTitle($nombre_pagina);
	
	$rowIndex = 1;
	foreach ($array_datos as $row) {
		$columnIndex = 1;
		foreach ($row as $value) {
			$cell = $sheet->getCellByColumnAndRow($columnIndex, $rowIndex);
			if ( in_array($columnIndex,$options['explicitString']) ) {
				$cell->setValueExplicit($value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
			} else
			if ( in_array($columnIndex,$options['explicitMoney']) ) {
				$cell->getStyle()->getNumberFormat()->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
				$cell->setValue($value);
			} else {
				$cell->setValue($value);
			}
			$columnIndex++;
		}
		$rowIndex++;
	}
	// Inicializar un array para realizar un seguimiento del ancho máximo de cada columna
	$maxColumnWidth = array();
	$lineaTitulos = true; // Variable para controlar si estamos en la primera fila
	// Recorrer los datos para determinar el ancho máximo de cada columna
	$rowIndex = 1;
	foreach ($array_datos as $row) {
		$columnIndex = 1;
		foreach ($row as $cellValue) {
			$formateo = 5;
			//multiplicador según tamaño de fuente (presumiendo titulos con negritas)
			$multiplicador_tamano = 
				//si son titulos
				$lineaTitulos ? 
				//van en negrita (proporción de normal a negrita aproximada 1.333), diferencia de 2 en un string de 15
				$options['titleFontSize'] * 0.133 : 
				//si no, letra normal
				($options['bodyFontSize'] + 1) * 0.1
			;
			$columnWidth = strlen($cellValue) * $multiplicador_tamano * $options['coeficienteFuente']; 
			// si no está seteado, es que es el título, a ese se le suma X por el filtro
			if (!isset($maxColumnWidth[$columnIndex])){
				$maxColumnWidth[$columnIndex] = $columnWidth + 2;
			} 
			//si no es el titulo
			if($rowIndex > 1){
				if( in_array($columnIndex,$options['explicitMoney']) && $columnWidth > 1){
					$columnWidth = $columnWidth + $formateo;
				}
				if ($columnWidth > $maxColumnWidth[$columnIndex] ) {
					$maxColumnWidth[$columnIndex] = $columnWidth;
				}
			}
			$columnIndex++;
		}
		$lineaTitulos = false; // Después de procesar la primera fila, establecemos la variable en falso
		$rowIndex++;
	}
	// Establecer el ancho de las columnas en la hoja de cálculo
	foreach ($maxColumnWidth as $columnIndex => $width) {
		$columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex);
		$sheet->getColumnDimension($columnLetter)->setWidth($width);
	}
	$rango_title = 'A1:' . $sheet->getHighestColumn() . '1';
	$sheet->getStyle($rango_title)->getFont()->setSize($options['titleFontSize']);
	$sheet->getStyle($rango_title)->getFont()->setBold(true);
	$rango_cuerpo = 'A2:'.$sheet->getHighestColumn() . $sheet->getHighestRow();
	$sheet->getStyle($rango_cuerpo)->getFont()->setSize($options['bodyFontSize']);
	$rango_total = 'A1:'.$sheet->getHighestColumn() . $sheet->getHighestRow();
	$sheet->getStyle($rango_title)->getAlignment()->setHorizontal( PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER );
	$sheet->getStyle($rango_total)->getFont()->setName($options['fuente']);
	$sheet->setAutoFilter($sheet->calculateWorksheetDimension());    
	$sheet->freezePane('A2');
}
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
/* */
function pedir_permiso(
	int $id_app, 
	object $user
){
	$acceso_concedido = false;
	$n = count($user->permisos);
	// primero busca permiso explícito de nivel 1 o 2 para la aplicación
	for($x=0; $x<$n; $x++){
		if($user->permisos[$x]->id == $id_app && $user->permisos[$x]->nivel < 3){
			$acceso_concedido = true;
			break;
		}
	}
	return $acceso_concedido;
}

function permiso_por_grupo(
	object $user
){
	$acceso_concedido = false;
	$n = count($user->info_zweb->grupos_usuario) ?? 0;
	// primero busca permiso explícito de nivel 1 o 2 para la aplicación
	for($x=0; $x<$n; $x++){
		if(in_array($user->info_zweb->grupos_usuario[$x]['id'], GRUPOS_CON_ACCESO)){
			$acceso_concedido = true;
			break;
		}
	}
	return $acceso_concedido;
}

function acortarString($string) {
    if (strlen($string) > 30) {
        return substr($string, 0, 24) . '...';
    } else {
        return $string;
    }
}
function login($login_string){
	$con = new HClasses\Conexiones\DataBase('zweb');
    $user = new HClasses\Hub\Usuarios\User (
        $login_string, 
        $con, 
        true
    );
	$permisos_app = [];
    // si el login es correcto
    if ($user->pass_verified) { 
        // determina permiso de acceso
        $acceso_concedido = pedir_permiso(ID_APLICACION, $user) || permiso_por_grupo($user) || FREE_FOR_ALL;
        if($acceso_concedido){
			$n = count($user->permisos);
			for($x=0; $x<$n; $x++){
				if($user->permisos[$x]->id == ID_APLICACION){
					$permisos_app = $user->permisos[$x]->permisos_internos;
					break;
				}
			}
            return (object) ['response_code' => 2, 'message' => 'Acceso concedido', 'permisos_app' => $permisos_app, 'user' => $user];
        }else {
            return (object) ['response_code' => 1, 'message' => 'El usuario no tiene permiso para acceder a esta aplicación', 'permisos_app' => null, 'user' => null];
        }
    }
    // si el login falla
    else{
        return (object) ['response_code' => 0, 'message' => $user->error->getMessage(), 'permisos_app' => null, 'user' => null];
    }
}

?>
