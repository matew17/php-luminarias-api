<?php
header("Access-Control-Allow-Origin: *");
require 'Slim/Slim.php';

require "NotORM.php";

$pdo = new PDO(
    "mysql:dbname=eapsa_luminarias",
    "eapsa_luminarias",
    "Terminaitor31.",
    array (PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8')
);
$db = new NotORM($pdo);

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

//--------------------------------------------------------------------------------------------------------------------

$app->get("/", function ()
{
    echo "<h1>Luminarias API</h1>";
});

// USUARIOS
//--------------------------------------------------------------------------------------------------------------------

//Retorna el Usuario o FAIL y recibe usuario y contraseña
$app->post("/usuarios/login/", function () use ($app, $db)
{
    $app->response()->header('Access-Control-Allow-Origin: *');
    $app->response()->header("Content-Type", "application/json");
    $post = $app->request()->post();
    $respuesta = "FAIL";

    $version = $post['version'];

    foreach ($db->tbusuarios() as $usuario) {
        if ($usuario['usuario'] == $post['usuario'] && $usuario['clave'] == $post['clave']) {
            $usuario->update(array("version" =>$version));
            unset($usuario["clave"]);
            $temp = $usuario;
            $respuesta = "OK";
            break;
        }
    }

    $array = array("response" => $respuesta, "usuario" => $temp);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Guarda el token para las notificaciones push
$app->post("/usuarios/notificaciones/", function () use($app, $db)
{
    $app->response()->header("Content-Type", "application/json");
    $post = $app->request()->post();

    $usuario = $post['usuario'];
    $fcm_token = $post['fcm_token'];
    $iphone_token = $post['iphone_token'];

    $respuesta = "FAIL";
    if ($fcm_token != null){
        $usuario = $db->tbusuarios("usuario = ?", $usuario)->fetch();
        $usuario->update($post);
        $respuesta = "OK";
    } else if ($iphone_token != null){
        $usuario = $db->tbusuarios("usuario = ?", $usuario)->fetch();
        $usuario->update($post);
        $respuesta = "OK";
    }

    $array = array("respuesta" => $respuesta);
    echo json_encode($array);
});

// MAPA (GEREFERENCIACIÓN)
//--------------------------------------------------------------------------------------------------------------------

//--------------------------------------------------------------------------------------------------------------------

//Retorna todas las Luminarias
$app->get("/mapa/luminarias/", function () use ($app, $db) {
    $app->response()->header("Content-Type", "application/json");

    $respuesta = array();

    foreach ($db->tbluminarias()->order("id DESC") as $luminaria) {
        if($luminaria['longitud'] != '0' && $luminaria['latitud'] != '0'){
            $respuesta[] = $luminaria;
        }
    }

    $array = array("results" => $respuesta);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Retorna todos los Barrios
$app->get("/luminarias/barrios/", function () use ($app, $db) {
    $app->response()->header("Content-Type", "application/json");

    $respuesta = array();

    foreach ($db->tbubicaciones()->order("id DESC") as $barrio) {
        $respuesta[] = $barrio;
    }

    $array = array("results" => $respuesta);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Retorna todos los Tipos de Poste
$app->get("/luminarias/tiposPoste/", function () use ($app, $db) {
    $app->response()->header("Content-Type", "application/json");

    $respuesta = array();

    foreach ($db->tbtiposposte()->order("id DESC") as $tipoPoste) {
        $respuesta[] = $tipoPoste;
    }

    $array = array("results" => $respuesta);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Retorna todos los Tipos de Brazo
$app->get("/luminarias/tiposBrazo/", function () use ($app, $db) {
    $app->response()->header("Content-Type", "application/json");

    $respuesta = array();

    foreach ($db->tbtiposbrazo()->order("id DESC") as $tipoBrazo) {
        $respuesta[] = $tipoBrazo;
    }

    $array = array("results" => $respuesta);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Retorna todos los Tipos de Lampara
$app->get("/luminarias/tiposLampara/", function () use ($app, $db) {
    $app->response()->header("Content-Type", "application/json");

    $respuesta = array();

    foreach ($db->tbtiposlampara()->order("id DESC") as $tipoLampara) {
        $respuesta[] = $tipoLampara;
    }

    $array = array("results" => $respuesta);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Retorna la ruta para llegar desde la ubicación actual
$app->post("/luminarias/ruta/", function () use ($app, $db) {
    $app->response()->header("Content-Type", "application/json");

    $post = $app->request()->post();

    $latitud1 = $post['latitud1'];
    $longitud1 = $post['longitud1'];

    $latitud2 = $post['latitud2'];
    $longitud2 = $post['longitud2'];

    $medio_transporte = $post['medio_transporte'];

    if ($medio_transporte == null || $medio_transporte == "") {
        $medio_transporte = "driving";
    } else if ($medio_transporte == "bicycling") {
        $medio_transporte = "walking";
    }

    $urlGoogle =
	    "https://maps.googleapis.com/maps/api/directions/json?origin="
	    . $latitud1 . "," . $longitud1 . "&destination=" . $latitud2 . "," . $longitud2 . "&mode=" . $medio_transporte
	    . "&key=AIzaSyAjRerPGPcGToU4Ck60VO31e-jLwdc0JOw";

    $opts = array('http' =>
        array(
            'method' => 'GET',
            'max_redirects' => '0',
            'ignore_errors' => '1'
        )
    );

    $context = stream_context_create($opts);
    $json = file_get_contents($urlGoogle, false, $context);
    $obj = json_decode($json);
    $ruta = $obj->routes[0]->overview_polyline->points;

    //print_r($ruta);

    if ($ruta != null) {
        $respuesta = "OK";
    } else {
        $respuesta = "FAIL";
    }

    $array = array("respuesta" => $respuesta, "ruta" => $ruta, "urlGoogle" => $urlGoogle);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Crea una nueva Luminaria, retorna OK o FAIL
$app->post("/luminarias/nueva/", function () use ($app, $db)
{
    $app->response()->header("Content-Type", "application/json");
    $post = $app->request()->post();

    date_default_timezone_set('America/Bogota');
    $fecha = date("Y-m-d H:i:s");
    $post['fecha'] = $fecha;

    $result = $db->tbluminarias->insert($post);

    if ($result) {
        $temp = $db->tbluminarias("fecha = ?", $post['fecha'])->fetch();
        $respuesta = "OK";
    } else {
        $respuesta = "FAIL";
    }

    $array = array("respuesta" => $respuesta, "luminaria" => $temp);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Edita una Luminaria, retorna OK o FAIL
$app->post("/luminarias/editar/:id", function ($id) use($app, $db)
{
    $app->response()->header("Content-Type", "application/json");
    $post = $app->request()->post();

    $luminaria = $db->tbluminarias[$id];
    $result = $luminaria->update($post);

    $temp = $db->tbluminarias("id = ?", $id)->fetch();
    if($result || $temp != null) {
        $respuesta = 'OK';
    } else {
        $respuesta = 'FAIL';
    }

    $array = array("respuesta" => $respuesta, "luminaria" => $temp);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Edita una Luminaria, retorna OK o FAIL
$app->post("/luminarias/eliminar/:id", function ($id) use($app, $db)
{
    $app->response()->header("Content-Type", "application/json");
    $post = $app->request()->put();

    $post['estado'] = "D";

    $luminaria = $db->tbluminarias[$id];
    if($luminaria['estado'] == $post['estado']) {
        $respuesta = 'OK';
    } else {
        $result = $luminaria->update($post);

        if($result)
        {
            $respuesta = 'OK';
        }
        else
        {
            $respuesta = 'FAIL';
        }
    }

    $array = array("respuesta" => $respuesta);
    echo json_encode($array);
});

// GESTIÓN OPERATIVA
//--------------------------------------------------------------------------------------------------------------------

//--------------------------------------------------------------------------------------------------------------------

//Retorna todas las PQR pendientes
$app->get("/operativo/pqr/pendientes", function () use ($app, $db) {
    $app->response()->header("Content-Type", "application/json");

    $respuesta = array();

    foreach ($db->tbpqr()->order("id DESC") as $pqr) {
        if($pqr['estado'] == 'P'){
            $respuesta[] = $pqr;
        }
    }

    $array = array("results" => $respuesta);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Retorna todas las PQR respondidas
$app->get("/operativo/pqr/respondidas", function () use ($app, $db) {
    $app->response()->header("Content-Type", "application/json");

    $respuesta = array();

    foreach ($db->tbpqr()->order("id DESC") as $pqr) {
        if($pqr['estado'] == 'R' && $pqr['fechaHora'] > '2018-11-20 20:19:36'){
            $respuesta[] = $pqr;
        }
    }

    $array = array("results" => $respuesta);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Retorna todas las Ubicaciones
$app->get("/operativo/ubicaciones", function () use ($app, $db) {
    $app->response()->header("Content-Type", "application/json");

    $respuesta = array();

    foreach ($db->tbubicaciones()->order("id DESC") as $zonas) {
        $respuesta[] = $zonas;
    }

    $array = array("results" => $respuesta);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Crea una nueva PQR
$app->post("/operativo/pqr/crear", function () use ($app, $db) {
    $app->response()->header("Content-Type", "application/json");

    $post = $app->request()->post();

    $fecha = date("Y-m-d H:i:s");
    $post['fechaHora'] = $fecha;

    $result = $db->tbpqr->insert($post);
    if ($result) {
        $temp = $db->tbpqr("fechaHora = ?", $post['fechaHora'])->fetch();
        $respuesta = "OK";
    } else {
        $respuesta = "FAIL";
    }

    $array = array("respuesta" => $respuesta, "pqr" => $temp);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Edita una PQR, retorna OK o FAIL
$app->post("/operativo/pqr/responder/:id", function ($id) use($app, $db)
{
    $app->response()->header("Content-Type", "application/json");
    $post = $app->request()->post();

    $fecha = date("Y-m-d H:i:s");
    $post['fechaHoraR'] = $fecha;
    $post['estado'] = "R";

    $pqr = $db->tbpqr[$id];
    $result = $pqr->update($post);

    if($result) {
        $temp = $db->tbpqr("fechaHoraR = ?", $post['fechaHoraR'])->fetch();
        $respuesta = 'OK';
    } else {
        $respuesta = 'FAIL';
    }

    $array = array("respuesta" => $respuesta, "pqr" => $temp);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Elimina una PQR, retorna OK o FAIL
$app->post("/operativo/pqr/eliminar/:id", function ($id) use($app, $db)
{
    $app->response()->header("Content-Type", "application/json");
    $post = $app->request()->put();

    $post['estado'] = "D";

    $pqr = $db->tbpqr[$id];
    if($pqr['estado'] == $post['estado']) {
        $respuesta = 'OK';
    } else {
        $result = $pqr->update($post);

        if($result)
        {
            $respuesta = 'OK';
        }
        else
        {
            $respuesta = 'FAIL';
        }
    }

    $array = array("respuesta" => $respuesta);
    echo json_encode($array);
});

// GESTIÓN DOCUMENTAL
//--------------------------------------------------------------------------------------------------------------------

//--------------------------------------------------------------------------------------------------------------------

//Retorna todas los Documentos
$app->get("/documental/documentos/:id", function ($id) use($app, $db) {
    $app->response()->header("Content-Type", "application/json");

    $respuesta = array();

    foreach ($db->tbdocumentos()->where("id_tipo = " . $id)->order("id DESC") as $tbdocumento) {
        $respuesta[] = $tbdocumento;
    }

    $array = array("results" => $respuesta);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Retorna un Documento
$app->get("/documental/documento/:id", function ($id) use($app, $db) {
    $app->response()->header("Content-Type", "application/json");

    $respuesta = array();

	$documento = $db->tbdocumentos()->where("id = " . $id)->order("id DESC");
	$archivos = null;
	foreach ($db->tbarchivos()->where("id_documento = " . $id)->order("id DESC") as $archivo) {
		$archivos[] = $archivo;
	}

	$respuesta['documento'] = $documento[$id];
	$respuesta['archivos'] = $archivos;

	$array = array("results" => $respuesta);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Crea un nuevo Documento
$app->post("/documental/documentos/crear", function () use ($app, $db) {
    $app->response()->header("Content-Type", "application/json");

    $post = $app->request()->post();

    $post['paso'] = 0;
    $fecha = date("Y-m-d H:i:s");
    $post['fecha_creado'] = $fecha;
    $post['fecha_ultima_modificacion'] = $fecha;

    $result = $db->tbdocumentos->insert($post);
    if ($result) {
        $temp = $db->tbdocumentos("fecha_creado = ?", $fecha)->fetch();
        $respuesta = "OK";
    } else {
        $respuesta = "FAIL";
    }

    $array = array("respuesta" => $respuesta, "documento" => $temp);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Crea un nuevo Archivo
$app->post("/documental/archivos/crear", function () use ($app, $db) {
	$app->response()->header("Content-Type", "multipart/form-data");

    $post = $app->request()->post();

	$id_documento = $post['id_documento'];
	$id_usuario = $post['id_usuario'];
	$titulo = $post['titulo'];
	$paso = $post['paso'];

	$extension = $_FILES['archivo']['name'];
	$extension = pathinfo($extension, PATHINFO_EXTENSION);

	date_default_timezone_set('America/Bogota');
	$fecha = date("Y-m-d-H-i-s");
	$nombreFoto = $id_documento . "-" . $fecha . "." . $extension;

	$target_path = "documentos/";
	$target_path = $target_path . basename($nombreFoto);


	$rutaServer = $_SERVER['DOCUMENT_ROOT'] . '/app/' . $target_path;
	$rutaUrl = 'http://eapsa.com.co/app/' . $target_path;

	if (move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaServer)) {
		$respuesta = "OK";
		$fecha = date("Y-m-d-H-i-s");
		$data = array(
			"id_documento" => $id_documento,
			"id_usuario" => $id_usuario,
			"fecha" => $fecha,
			"archivo" => $rutaUrl,
			"titulo" => $titulo,
			"paso" => $paso
		);
		$result = $db->tbarchivos->insert($data);
		$temp = $result;
	} else {
		$respuesta = "FAIL";
	}

	$array = array(
		"respuesta" => $respuesta,
		"archivo" => $temp,
		"ruta" => $rutaUrl
	);
	echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Edita un Documento, retorna OK o FAIL
$app->post("/documental/documentos/editar/:id", function ($id) use($app, $db)
{
    $app->response()->header("Content-Type", "application/json");
    $post = $app->request()->post();

    $fecha = date("Y-m-d H:i:s");
    $post['fecha_ultima_modificacion'] = $fecha;

    $documento = $db->tbdocumentos[$id];
    $result = $documento->update($post);

    if($result) {
        $temp = $db->tbpqr("fecha_ultima_modificacion = ?", $post['fecha_ultima_modificacion'])->fetch();
        $respuesta = 'OK';
    } else {
        $respuesta = 'FAIL';
    }

    $array = array("respuesta" => $respuesta, "documento" => $temp);
    echo json_encode($array);
});

//--------------------------------------------------------------------------------------------------------------------

//Elimina un Documento, retorna OK o FAIL
$app->post("/documental/documentos/eliminar/:id", function ($id) use($app, $db)
{
    $app->response()->header("Content-Type", "application/json");
    $post = $app->request()->put();

    $post['estado'] = "D";

    $documento = $db->$documento[$id];
    if($documento['estado'] == $post['estado']) {
        $respuesta = 'OK';
    } else {
        $result = $documento->update($post);

        if($result)
        {
            $respuesta = 'OK';
        }
        else
        {
            $respuesta = 'FAIL';
        }
    }

    $array = array("respuesta" => $respuesta);
    echo json_encode($array);
});

//----------------------------------------------------------------------------------------------------------------

//--------------------------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------------------------
//--------------------------------------------------------------------------------------------------------------------

function getEstado($estado)
{
    switch ($estado){
        case 'B':
            $estado = 'Bueno';
            break;
        case 'R':
            $estado = 'Regular';
            break;
        case 'M':
            $estado = 'Malo';
            break;
    }

    return $estado;
}

//--------------------------------------------------------------------------------------------------------------------

function getBarrio($idUbicacion, $db)
{
    $ubicacion = $db->tbubicaciones[$idUbicacion];

    $ubicacion = $ubicacion['nombreUbicacion'];

    return $ubicacion;
}

//--------------------------------------------------------------------------------------------------------------------

function getTipoPoste($idTipoPoste, $db)
{
    $tipoPoste = $db->tbtiposposte[$idTipoPoste];

    $tipoPoste = $tipoPoste['tipoPoste'];

    return $tipoPoste;
}

//--------------------------------------------------------------------------------------------------------------------

function getTipoBrazo($idTipoBrazo, $db)
{
    $tipoBrazo = $db->tbtiposbrazo[$idTipoBrazo];

    $tipoBrazo = $tipoBrazo['tipoBrazo'];

    return $tipoBrazo;
}

//--------------------------------------------------------------------------------------------------------------------

function getTipoLampara($idTipoLampara, $db)
{
    $tipoLampara = $db->tbtiposlampara[$idTipoLampara];

    $tipoLampara = $tipoLampara['lampara'];

    return $tipoLampara;
}

//--------------------------------------------------------------------------------------------------------------------


$app->run();
