<?php
/**
 * Pharmacie DZ - Suppression d'un médicament
 */

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

$idToDelete = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

if ($idToDelete > 0) {
    $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `ID` = ?");
    $stmt->execute([$idToDelete]);
}

header("Location: index.php?msg=deleted");
exit;
