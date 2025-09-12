<?php
// --- Configuration ---
$host = 'localhost';
$db   = 'pharmacie';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$table = 'medicament';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Connexion échouée : " . htmlspecialchars($e->getMessage()));
}

// --- Vérifier si un ID est passé ---
if (isset($_GET['id'])) {
    $idToDelete = (int) $_GET['id'];

    if ($idToDelete > 0) {
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `ID` = ?");
        $stmt->execute([$idToDelete]);
    }
}

// Redirection vers la liste
header("Location: index.php");
exit;
