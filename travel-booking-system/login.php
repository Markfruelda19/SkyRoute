<?php
require_once 'includes/auth.php';
if (isLoggedIn()) {
    header("Location: " . (isAdmin() ? "admin/index.php" : "dashboard.php"));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];

            if ($user['role'] === 'admin') {
                header("Location: admin/index.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign In — <?= SITE_NAME ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-logo">
        <div class="brand">✈ <?= SITE_NAME ?></div>
        <p>Welcome back. Sign in to continue.</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger">⚠ <?= $error ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="juan@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" placeholder="Your password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Sign In</button>
      </form>

      <div class="auth-divider">or</div>
      <p class="text-center text-muted fs-sm">New here? <a href="register.php">Create an account</a></p>

      <div class="divider"></div>
      <div class="alert alert-info" style="font-size:0.8rem; margin:0;">
        <div><strong>Demo Admin:</strong> admin@travel.com / password</div>
      </div>
    </div>
  </div>
</body>
</html>
