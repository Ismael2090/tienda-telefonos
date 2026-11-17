<?php require 'config.php';
if (isset($_SESSION['uid'])) { header('Location: '.($_SESSION['rol'] == 'admin' ? 'admin.php' : 'index.php')); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $s = $db->prepare("SELECT * FROM usuarios WHERE usuario=?"); $s->execute([trim($_POST['usuario'])]); $f = $s->fetch();
  if ($f && password_verify($_POST['clave'], $f['clave'])) {
    $_SESSION['uid'] = $f['id']; $_SESSION['usuario'] = $f['usuario']; $_SESSION['rol'] = $f['rol'];
    $dest = $_SESSION['volver'] ?? ($f['rol'] == 'admin' ? 'admin.php' : 'index.php'); unset($_SESSION['volver']);
    header('Location: '.$dest); exit;
  }
  $err = 'Usuario o contraseña incorrectos.';
}
auth_top('Iniciar sesión'); ?>
<div class="auth-card"><img src="media/logo.png" alt="" onerror="this.remove()"><h2>Iniciar sesión</h2>
<?php if ($err) echo '<div class="msg">'.e($err).'</div>'; ?>
<form method="post"><input name="usuario" placeholder="Nombre de usuario" required autofocus><input name="clave" type="password" placeholder="Contraseña" required><button>Entrar</button></form>
<p>¿No tienes cuenta? <a href="registro.php">Regístrate</a></p><p><a href="index.php">← Volver a la tienda</a></p></div>
</body></html>
