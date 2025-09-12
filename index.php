<?php
// --- Configuration : adapter ici ---
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

// --- Connexion ---
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    echo "Connexion échouée : " . htmlspecialchars($e->getMessage());
    exit;
}

// --- Suppression (si POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $idToDelete = (int)$_POST['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `ID` = ?");
    $stmt->execute([$idToDelete]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- Recherche ---
$where = "";
$params = [];
if (!empty($_GET['search'])) {
    $search = "%".$_GET['search']."%";
    $where = "WHERE NOM_DE_MARQUE LIKE :search OR CODE LIKE :search OR NUM_ENREGISTREMENT LIKE :search";
    $params[':search'] = $search;
}

// --- Récupérer lignes ---
$sql = "SELECT * FROM `$table` $where ORDER BY ID ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Liste des médicaments</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    table th, table td { white-space: nowrap; }
    .actions { min-width: 180px; }
  </style>
</head>
<body class="p-4">
<div class="container">
  <h2 class="mb-3">Médicaments</h2>

  <!-- Barre de recherche -->
  <form class="mb-3 d-flex" method="get">
    <input type="text" name="search" class="form-control me-2" 
           placeholder="Rechercher par Nom, Code ou Numéro d’enregistrement"
           value="<?= h($_GET['search'] ?? '') ?>">
    <button class="btn btn-success" type="submit">Rechercher</button>
    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary ms-2">Réinitialiser</a>
  </form>

  <table class="table table-bordered table-sm table-striped">
    <thead class="table-dark">
      <tr>
        <th>Actions</th>
        <th>ID</th>
        <th>NUM_ENREGISTREMENT</th>
        <th>CODE</th>
        <th>DENOMINATION_COMMUNE_INTERNATIONALE</th>
        <th>NOM_DE_MARQUE</th>
        <th>FORME</th>
        <th>DOSAGE</th>
        <th>COND</th>
        <th>LISTE</th>
        <th>P1</th>
        <th>P2</th>
        <th>OBS</th>
        <th>LABORATOIRE</th>
        <th>PAYS</th>
        <th>DATE_INITIALE</th>
        <th>DATE_FINALE</th>
        <th>TYPE</th>
        <th>STATUT</th>
        <th>DUREE</th>
        <th>PRIX</th>
        <th>REMBOURSEMENT</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="22" class="text-center">Aucun enregistrement trouvé.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="actions">
              <a class="btn btn-sm btn-primary" href="edit.php?id=<?= h($r['ID']) ?>">Edit</a>
              
              <form style="display:inline" method="post" 
                    onsubmit="return confirm('Confirmer la suppression de l\'ID <?= h($r['ID']) ?> ?')">
                <input type="hidden" name="delete_id" value="<?= h($r['ID']) ?>">
                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
              </form>
            </td>
            <td><?= h($r['ID'] ?? '') ?></td>
            <td><?= h($r['NUM_ENREGISTREMENT'] ?? '') ?></td>
            <td><?= h($r['CODE'] ?? '') ?></td>
            <td><?= h($r['DENOMINATION_COMMUNE_INTERNATIONALE'] ?? '') ?></td>
            <td><?= h($r['NOM_DE_MARQUE'] ?? '') ?></td>
            <td><?= h($r['FORME'] ?? '') ?></td>
            <td><?= h($r['DOSAGE'] ?? '') ?></td>
            <td><?= h($r['COND'] ?? '') ?></td>
            <td><?= h($r['LISTE'] ?? '') ?></td>
            <td><?= h($r['P1'] ?? '') ?></td>
            <td><?= h($r['P2'] ?? '') ?></td>
            <td><?= h($r['OBS'] ?? '') ?></td>
            <td><?= h($r['LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT'] ?? '') ?></td>
            <td><?= h($r['PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT'] ?? '') ?></td>
            <td><?= h($r['DATE_DENREGISTREMENT_INITIAL'] ?? '') ?></td>
            <td><?= h($r['DATE_DENREGISTREMENT_FINAL'] ?? '') ?></td>
            <td><?= h($r['TYPE'] ?? '') ?></td>
            <td><?= h($r['STATUT'] ?? '') ?></td>
            <td><?= h($r['DUREE_DE_STABILITE'] ?? '') ?></td>
            <td><?= h($r['PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT'] ?? '') ?></td>
            <td><?= h($r['REMBOURSEMENT'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
