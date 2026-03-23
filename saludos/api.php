<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

$dataFile = 'data.json';

// Si no existe, intentar crearlo
if (!file_exists($dataFile)) {
    $defaultData = [
        "settings" => ["color" => "#ff0000", "auto_delete" => true],
        "messages" => []
    ];
    $saved = file_put_contents($dataFile, json_encode($defaultData), LOCK_EX);
    if ($saved === false) {
        echo json_encode(['status' => 'error', 'msg' => 'ERROR FATAL de permisos.']);
        exit;
    }
}

$jsonData = file_get_contents($dataFile);
$data = json_decode($jsonData, true);

// Borrado automático
if (isset($data['settings']['auto_delete']) && $data['settings']['auto_delete']) {
    $now = time();
    $filtered = [];
    foreach ($data['messages'] as $msg) {
        if (($now - $msg['timestamp']) < 86400) {
            $filtered[] = $msg;
        }
    }
    $data['messages'] = $filtered;
    file_put_contents($dataFile, json_encode($data), LOCK_EX);
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'get';

if ($action === 'get') {
    echo json_encode($data);
    exit;
}

if ($action === 'add') {
    $text = strip_tags($_POST['text']); 
    if ($text) {
        
        // --- INICIO FILTRO DE MALAS PALABRAS ---
        // Agregá acá todas las palabras que quieras bloquear (siempre en minúsculas)
        $insultos = ['mierda', 'puto', 'carajo', 'boludo', 'pelotudo', 'concha', 'verga', 'pija', 'maricon', 'maraca', 'puta', 'callampa', 'weon', 'webon', 'chota', 'choto']; 
        
        $esValido = true;
        // Pasamos el texto entrante a minúsculas para compararlo bien (incluso si escriben con MAYÚSCULAS)
        $textoMinuscula = mb_strtolower($text, 'UTF-8');
        
        foreach ($insultos as $insulto) {
            if (strpos($textoMinuscula, $insulto) !== false) {
                $esValido = false; // Se detectó una mala palabra
                break;
            }
        }
        // --- FIN FILTRO ---

        // Solo guardamos el mensaje si pasó el filtro limpio
        if ($esValido) {
            $data['messages'][] = ['id' => uniqid(), 'text' => $text, 'timestamp' => time()];
            
            // Límite estricto de 50 mensajes.
            if (count($data['messages']) > 50) {
                array_shift($data['messages']); 
            }

            file_put_contents($dataFile, json_encode($data), LOCK_EX);
        }
        
        // MENTIRA PIADOSA: Siempre le devolvemos un "ok" al celular, haya pasado o no el filtro.
        echo json_encode(['status' => 'ok']);
    }
    exit;
}

if ($action === 'delete') {
    $id = $_POST['id'];
    $data['messages'] = array_filter($data['messages'], function($msg) use ($id) { return $msg['id'] !== $id; });
    $data['messages'] = array_values($data['messages']);
    file_put_contents($dataFile, json_encode($data), LOCK_EX);
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($action === 'delete_all') {
    $data['messages'] = [];
    file_put_contents($dataFile, json_encode($data), LOCK_EX);
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($action === 'update_settings') {
    $color = $_POST['color'];
    $data['settings']['color'] = $color;
    $data['settings']['auto_delete'] = filter_var($_POST['auto_delete'], FILTER_VALIDATE_BOOLEAN);
    
    $saved = file_put_contents($dataFile, json_encode($data), LOCK_EX);
    if ($saved !== false) {
        echo json_encode(['status' => 'ok', 'msg' => 'Guardado con exito']);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'No se pudo guardar.']);
    }
    exit;
}
?>
