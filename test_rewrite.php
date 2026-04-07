<?php
echo "<h1>Teste de Rewrite</h1>";
echo "<p>REQUEST_URI: " . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A') . "</p>";
echo "<p>QUERY_STRING: " . htmlspecialchars($_SERVER['QUERY_STRING'] ?? 'N/A') . "</p>";
echo "<p>GET params: " . htmlspecialchars(json_encode($_GET)) . "</p>";
echo "<p>Token via GET: " . htmlspecialchars($_GET['token'] ?? 'N/A') . "</p>";
