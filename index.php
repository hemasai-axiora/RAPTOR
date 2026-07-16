<?php
// Redirect root requests to the public directory, preserving the query string
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$location = "public/" . ($queryString !== '' ? '?' . $queryString : '');
header("Location: " . $location);
exit;
