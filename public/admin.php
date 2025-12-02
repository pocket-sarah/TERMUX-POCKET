<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin</title>
<style>
body{background:#111;color:#ff0066;font-family:monospace;padding:30px}
input,button{background:black;color:#ff0066;border:1px solid #ff0066;padding:8px;margin:5px}
</style>
</head>
<body>

<h2>Admin Console</h2>

<form method="post">
<input type="password" name="key" placeholder="ACCESS KEY">
<button>ENTER</button>
</form>

<?php
if(isset($_POST["key"])) {
    if($_POST["key"] === "POCKET_ROOT") {
        echo "<p>Access Granted</p>";
    } else {
        echo "<p>Access Denied</p>";
    }
}
?>

</body>
</html>