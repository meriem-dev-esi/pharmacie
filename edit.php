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

// --- Charger l'enregistrement ---
if (!isset($_GET['id'])) {
    die("Aucun ID fourni !");
}

$id = (int) $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `ID` = ?");
$stmt->execute([$id]);
$medicament = $stmt->fetch();

if (!$medicament) {
    die("Médicament introuvable !");
}

// --- Sauvegarder les modifications ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = "UPDATE `$table`
            SET NUM_ENREGISTREMENT=?, CODE=?, DENOMINATION_COMMUNE_INTERNATIONALE=?, NOM_DE_MARQUE=?, FORME=?, DOSAGE=?, COND=?, LISTE=?, P1=?, P2=?, OBS=?, LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT=?, PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT=?, DATE_DENREGISTREMENT_INITIAL=?, DATE_DENREGISTREMENT_FINAL=?, TYPE=?, STATUT=?, DUREE_DE_STABILITE=?, PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT=?, REMBOURSEMENT=?
            WHERE ID=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $_POST['NUM_ENREGISTREMENT'],
        $_POST['CODE'],
        $_POST['DENOMINATION_COMMUNE_INTERNATIONALE'],
        $_POST['NOM_DE_MARQUE'],
        $_POST['FORME'],
        $_POST['DOSAGE'],
        $_POST['COND'],
        $_POST['LISTE'],
        $_POST['P1'],
        $_POST['P2'],
        $_POST['OBS'],
        $_POST['LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT'],
        $_POST['PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT'],
        $_POST['DATE_DENREGISTREMENT_INITIAL'],
        $_POST['DATE_DENREGISTREMENT_FINAL'],
        $_POST['TYPE'],
        $_POST['STATUT'],
        $_POST['DUREE_DE_STABILITE'],
        $_POST['PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT'],
        $_POST['REMBOURSEMENT'],
        $id
    ]);

    header("Location: index.php");
    exit;
}

// --- fonction utilitaire ---
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Modifier un médicament</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
  <h2>Modifier un médicament</h2>
  <form method="post" class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Numéro d’enregistrement</label>
      <input type="text" name="NUM_ENREGISTREMENT" value="<?= h($medicament['NUM_ENREGISTREMENT']) ?>" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">Code</label>
      <input type="text" name="CODE" value="<?= h($medicament['CODE']) ?>" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">DCI</label>
      <input type="text" name="DENOMINATION_COMMUNE_INTERNATIONALE" value="<?= h($medicament['DENOMINATION_COMMUNE_INTERNATIONALE']) ?>" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">Nom de marque</label>
      <input type="text" name="NOM_DE_MARQUE" value="<?= h($medicament['NOM_DE_MARQUE']) ?>" class="form-control">
    </div>
    <div class="col-md-4">
      <label class="form-label">Forme</label>
      <input type="text" name="FORME" value="<?= h($medicament['FORME']) ?>" class="form-control">
    </div>
    <div class="col-md-4">
      <label class="form-label">Dosage</label>
      <input type="text" name="DOSAGE" value="<?= h($medicament['DOSAGE']) ?>" class="form-control">
    </div>
    <div class="col-md-4">
      <label class="form-label">Conditionnement</label>
      <input type="text" name="COND" value="<?= h($medicament['COND']) ?>" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">Liste</label>
      <input type="text" name="LISTE" value="<?= h($medicament['LISTE']) ?>" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">P1</label>
      <input type="text" name="P1" value="<?= h($medicament['P1']) ?>" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">P2</label>
      <input type="text" name="P2" value="<?= h($medicament['P2']) ?>" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">OBS</label>
      <input type="text" name="OBS" value="<?= h($medicament['OBS']) ?>" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">Laboratoire détenteur</label>
      <input type="text" name="LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT" value="<?= h($medicament['LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT']) ?>" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">Pays du laboratoire</label>
      <input type="text" name="PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT" value="<?= h($medicament['PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT']) ?>" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">Date initiale</label>
      <input type="date" name="DATE_DENREGISTREMENT_INITIAL" value="<?= h($medicament['DATE_DENREGISTREMENT_INITIAL']) ?>" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">Date finale</label>
      <input type="date" name="DATE_DENREGISTREMENT_FINAL" value="<?= h($medicament['DATE_DENREGISTREMENT_FINAL']) ?>" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">Type</label>
      <input type="text" name="TYPE" value="<?= h($medicament['TYPE']) ?>" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">Statut</label>
      <input type="text" name="STATUT" value="<?= h($medicament['STATUT']) ?>" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">Durée de stabilité</label>
      <input type="text" name="DUREE_DE_STABILITE" value="<?= h($medicament['DUREE_DE_STABILITE']) ?>" class="form-control">
    </div>
    <div class="col-md-3">
      <label class="form-label">Prix</label>
      <input type="text" name="PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT" value="<?= h($medicament['PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT']) ?>" class="form-control">
    </div>
    <div class="col-md-6">
      <label class="form-label">Remboursement</label>
      <input type="text" name="REMBOURSEMENT" value="<?= h($medicament['REMBOURSEMENT']) ?>" class="form-control">
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-success">Enregistrer</button>
      <a href="index.php" class="btn btn-secondary">Annuler</a>
    </div>
  </form>
</div>
</body>
</html>
