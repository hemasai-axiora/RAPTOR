<?php
/**
 * ApidocsController — Interactive API Explorer powered by Scalar & OpenAPI 3.1.
 */

declare(strict_types=1);

class ApidocsController extends Controller {

    public function index() {
        // Enforce session security
        $this->requireAuth();

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!doctype html>
<html lang="en">
  <head>
    <title>Raptor Platform REST API Reference</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" type="image/png" href="logo.png">
    <style>
      body {
        margin: 0;
        background-color: #0f172a;
      }
    </style>
  </head>
  <body>
    <script
      id="api-reference"
      data-url="openapi.json"
      data-configuration='{
        "theme": "purple",
        "darkMode": true,
        "showSidebar": true
      }'
    ></script>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
  </body>
</html>
        <?php
        exit();
    }
}

