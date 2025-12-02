<?php
if (file_exists("redirect.txt")) {
  $url = file_get_contents("redirect.txt");
  header("Location: $url");
  exit();
}
?>
<html>
  <head><title>Live</title></head>
  <body>
    <h1>Server Active</h1>
  </body>
</html>