<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Invalid JSON']); exit;
}

$id        = isset($input['id']) ? (int)$input['id'] : 0;
$type      = trim($input['type'] ?? '');
$color     = trim($input['color'] ?? '');
$from      = trim($input['from'] ?? '');
$to        = trim($input['to'] ?? '');
$imageData = $input['imageData'] ?? null;

if ($id <= 0 || !in_array($type, ['color','gradient','image'], true)) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Invalid id or type']); exit;
}
if ($type === 'color' && $color === '') {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Color required']); exit;
}
if ($type === 'gradient' && ($from === '' || $to === '')) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Gradient from/to required']); exit;
}
if ($type === 'image' && empty($imageData)) {
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>'Image data required']); exit;
}

$host="127.0.0.1"; $port="5432"; $dbname="dinadrawing"; $username="kai"; $password="DND2025";

try {
  $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname",$username,$password,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
  ]);

  $banner_image_db = null;

  if ($type === 'image') {
    if (!preg_match('#^data:image/(png|jpe?g|webp);base64,#i',$imageData,$m)) {
      throw new Exception('Unsupported image header');
    }
    $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
    $raw = substr($imageData, strpos($imageData, ',')+1);
    $bin = base64_decode($raw, true);
    if ($bin === false) throw new Exception('Base64 decode failed');
    if (strlen($bin) < 2000) throw new Exception('Image too small (len='.strlen($bin).')');

    $root = dirname(__DIR__, 3); // /Applications/XAMPP/xamppfiles/htdocs/dinadrawing
    $dir  = $root . '/Assets/uploads/banners';
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new Exception('Cannot create dir');
    if (!is_writable($dir)) throw new Exception('Not writable: '.$dir);

    $filename = "event_{$id}.".$ext;
    $fullpath = $dir.'/'.$filename;
    if (file_put_contents($fullpath,$bin) === false) throw new Exception('File write failed');
    $banner_image_db = "Assets/uploads/banners/".$filename;
  }

  $stmt = $pdo->prepare("
    UPDATE events SET
      banner_type = :type,
      banner_color = :color,
      banner_from  = :from,
      banner_to    = :to,
      banner_image = :image
    WHERE id = :id
  ");
  $stmt->execute([
    ':id'=>$id,
    ':type'=>$type,
    ':color'=>$type==='color' ? $color : null,
    ':from'=>$type==='gradient' ? $from : null,
    ':to'=>$type==='gradient' ? $to : null,
    ':image'=>$type==='image' ? $banner_image_db : null
  ]);

  $imageUrl = $banner_image_db ? '/dinadrawing/'.$banner_image_db : null; // lowercase

  echo json_encode([
    'success'=>true,
    'banner'=>[
      'type'=>$type,
      'color'=>$type==='color' ? $color : null,
      'from'=>$type==='gradient' ? $from : null,
      'to'=>$type==='gradient' ? $to : null,
      'imageUrl'=>$imageUrl
    ]
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'Save failed','detail'=>$e->getMessage()]);
}

