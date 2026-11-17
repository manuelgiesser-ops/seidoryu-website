<?php
declare(strict_types=1);
if (!defined('SEIDORYU_EDITOR')) { http_response_code(404); exit; }
function setup_hash(): string {
    $file=data_dir().'/setup-hash.txt';
    return is_file($file)?trim((string)file_get_contents($file)):'';
}
const BLOG_ROOT = '/seidoryu/';
function data_dir(): string {
    $doc = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    if (!$doc || $doc === '/') throw new RuntimeException('Privater Speicher nicht verfügbar.');
    $dir = dirname($doc).'/seidoryu-preview-private';
    if (!is_dir($dir) && !@mkdir($dir,0700,true) && !is_dir($dir)) throw new RuntimeException('Privater Speicher nicht beschreibbar.');
    $real=realpath($dir);
    if (!$real || $real===$doc || str_starts_with($real,$doc.DIRECTORY_SEPARATOR) || !is_writable($real)) throw new RuntimeException('Privater Speicher nicht sicher verfügbar.');
    @chmod($real,0700);
    return $real;
}
function initial_state(): array {
    $seed=json_decode((string)file_get_contents(__DIR__.'/../blog/seed.json'),true,512,JSON_THROW_ON_ERROR);
    foreach ($seed as &$p) { $p['status']='published'; $p['image']=''; $p['image_alt']=''; }
    unset($p);
    return ['users'=>[], 'posts'=>$seed, 'attempts'=>[]];
}
function with_state(callable $fn, bool $write=false) {
    $dir=data_dir(); $lock=fopen($dir.'/state.lock','c');
    if (!$lock || !flock($lock,$write?LOCK_EX:LOCK_SH)) throw new RuntimeException('Speicher nicht verfügbar.');
    try {
        $file=$dir.'/state.json'; $s=is_file($file)?json_decode((string)file_get_contents($file),true,512,JSON_THROW_ON_ERROR):initial_state();
        $result=$fn($s);
        if ($write) {
            $tmp=tempnam($dir,'state-'); if(!$tmp) throw new RuntimeException('Speichern fehlgeschlagen.');
            try { $json=json_encode($s,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
                if(file_put_contents($tmp,$json)===false)throw new RuntimeException('Speichern fehlgeschlagen.');
                chmod($tmp,0600); if(!rename($tmp,$file))throw new RuntimeException('Speichern fehlgeschlagen.');
            } finally { if(is_file($tmp)) @unlink($tmp); }
        }
        return $result;
    } finally { flock($lock,LOCK_UN); fclose($lock); }
}
function security_headers(): void {
    header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: DENY'); header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store, private'); header('X-Robots-Tag: noindex, nofollow, noarchive');
}
function session_begin(): void {
    if(session_status()===PHP_SESSION_ACTIVE)return;
    ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
    session_name('seidoryu_editor');
    session_set_cookie_params(['lifetime'=>0,'path'=>BLOG_ROOT,'secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    session_start();
    if(isset($_SESSION['last']) && time()-$_SESSION['last']>3600){$_SESSION=[];session_regenerate_id(true);}
    $_SESSION['last']=time();
    if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
}
function current_user(): ?array {
    if(empty($_SESSION['user']))return null;
    $name=$_SESSION['user']; $u=with_state(fn($s)=>$s['users'][$name]??null);
    if(!$u || !hash_equals($u['version'],(string)($_SESSION['version']??''))){unset($_SESSION['user']);return null;}
    return ['name'=>$name,'role'=>$u['role']];
}
function csrf(): void {
    if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])) throw new RuntimeException('Sicherheitsprüfung fehlgeschlagen. Bitte neu laden.');
}
function login_attempt(string $name,string $pw,string $token): bool {
    return with_state(function(&$s)use($name,$pw,$token){
        $now=time();$ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown');
        $s['attempts']=array_filter($s['attempts'],fn($v)=>$v['until']>$now);
        $attempt=$s['attempts'][$ip]??['count'=>0,'until'=>$now+900];
        if($attempt['count']>=8)return false;
        $attempt['count']++;$s['attempts'][$ip]=$attempt;
        $name=strtolower(trim($name));
        if(!$s['users']){
            if(setup_hash()===''||!password_verify($token,setup_hash())||!preg_match('/^[a-z0-9_-]{3,32}$/D',$name)||strlen($pw)<14||strlen($pw)>72)return false;
            $s['users'][$name]=['hash'=>password_hash($pw,PASSWORD_DEFAULT),'role'=>'admin','version'=>bin2hex(random_bytes(16))];
        } else {
            $u=$s['users'][$name]??null;
            if(!password_verify($pw,$u['hash']??password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT))||!$u)return false;
        }
        unset($s['attempts'][$ip]);session_regenerate_id(true);
        $_SESSION=['user'=>$name,'version'=>$s['users'][$name]['version'],'csrf'=>bin2hex(random_bytes(32)),'last'=>$now];
        return true;
    },true);
}
function clean(string $key,int $max,bool $required=true):string {
    $v=$_POST[$key]??'';if(!is_string($v))throw new RuntimeException('Ungültige Eingabe.');
    $v=trim($v);if(($required&&$v==='')||strlen($v)>$max)throw new RuntimeException('Bitte die Eingaben und Feldlängen prüfen.');
    return $v;
}
function save_image():?string {
    $f=$_FILES['cover']??null;
    if(!$f||$f['error']===UPLOAD_ERR_NO_FILE)return null;
    if($f['error']!==UPLOAD_ERR_OK||$f['size']>8*1024*1024||!is_uploaded_file($f['tmp_name']))throw new RuntimeException('Bild konnte nicht geladen werden (max. 8 MB).');
    $info=@getimagesize($f['tmp_name']);$mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!$info||!isset($allowed[$mime])||$info['mime']!==$mime||$info[0]>6000||$info[1]>6000||$info[0]*$info[1]>24000000)throw new RuntimeException('Nur JPG, PNG oder WebP bis 24 Megapixel.');
    $dir=data_dir();$name=bin2hex(random_bytes(20)).'.'.$allowed[$mime];$path=$dir.'/'.$name;
    if(function_exists('imagecreatefromstring')&&function_exists('imagewebp')){
        $im=@imagecreatefromstring((string)file_get_contents($f['tmp_name']));if(!$im)throw new RuntimeException('Bild ist beschädigt.');
        $factor=min(1,1600/max($info[0],$info[1]));$ow=max(1,(int)round($info[0]*$factor));$oh=max(1,(int)round($info[1]*$factor));
        $out=imagecreatetruecolor($ow,$oh);imagealphablending($out,false);imagesavealpha($out,true);imagefill($out,0,0,imagecolorallocatealpha($out,0,0,0,127));imagecopyresampled($out,$im,0,0,0,0,$ow,$oh,$info[0],$info[1]);
        $name=bin2hex(random_bytes(20)).'.webp';$path=$dir.'/'.$name;if(!imagewebp($out,$path,86))throw new RuntimeException('Bild konnte nicht gespeichert werden.');
    } else { if(!move_uploaded_file($f['tmp_name'],$path))throw new RuntimeException('Bild konnte nicht gespeichert werden.'); }
    chmod($path,0600);return $name;
}
function image_url(string $name):string{return $name?BLOG_ROOT.'blog/api.php?image='.rawurlencode($name):'';}
function e($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function go(string $query=''):never{header('Location: '.BLOG_ROOT.'redaktion/'.$query, true,303);exit;}

// Only local links within this website are allowed in public club content.
function local_site_link(string $url): bool {
    if(!str_starts_with($url,BLOG_ROOT)||preg_match('~[\\x00-\\x20\\x7f\\\\\\\\]~',$url))return false;
    $parts=parse_url($url);
    return is_array($parts)&&!isset($parts['scheme'],$parts['host'])&&!isset($parts['host'])&&str_starts_with($parts['path']??'',BLOG_ROOT)&&!preg_match('~(?:^|/)\\.\\.(?:/|$)~',rawurldecode($parts['path']??''));
}
