<?php
/**
 * Pharmacie DZ - Modification d'une fiche médicament
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

if (!isset($_GET['id'])) {
    die("Aucun ID fourni !");
}

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM `$table` WHERE `ID` = ?");
$stmt->execute([$id]);
$medicament = $stmt->fetch();

if (!$medicament) {
    die("Médicament introuvable !");
}

$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $marque = trim($_POST['NOM_DE_MARQUE'] ?? '');
    $dci    = trim($_POST['DENOMINATION_COMMUNE_INTERNATIONALE'] ?? '');

    if (empty($marque) || empty($dci)) {
        $errorMessage = "Le nom de marque et la DCI sont obligatoires.";
    } else {
        try {
            $sql = "UPDATE `$table`
                    SET NUM_ENREGISTREMENT=?, CODE=?, DENOMINATION_COMMUNE_INTERNATIONALE=?, NOM_DE_MARQUE=?, FORME=?, DOSAGE=?, COND=?, LISTE=?, P1=?, P2=?, OBS=?, LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT=?, PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT=?, DATE_DENREGISTREMENT_INITIAL=?, DATE_DENREGISTREMENT_FINAL=?, TYPE=?, STATUT=?, DUREE_DE_STABILITE=?, PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT=?, REMBOURSEMENT=?
                    WHERE ID=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['NUM_ENREGISTREMENT'] ?? '',
                $_POST['CODE'] ?? '',
                $dci,
                $marque,
                $_POST['FORME'] ?? '',
                $_POST['DOSAGE'] ?? '',
                $_POST['COND'] ?? '',
                $_POST['LISTE'] ?? '',
                $_POST['P1'] ?? '',
                $_POST['P2'] ?? '',
                $_POST['OBS'] ?? '',
                $_POST['LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT'] ?? '',
                $_POST['PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT'] ?? '',
                !empty($_POST['DATE_DENREGISTREMENT_INITIAL']) ? $_POST['DATE_DENREGISTREMENT_INITIAL'] : null,
                !empty($_POST['DATE_DENREGISTREMENT_FINAL']) ? $_POST['DATE_DENREGISTREMENT_FINAL'] : null,
                $_POST['TYPE'] ?? 'Ge',
                $_POST['STATUT'] ?? '',
                $_POST['DUREE_DE_STABILITE'] ?? '',
                $_POST['PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT'] ?? '',
                $_POST['REMBOURSEMENT'] ?? 'NON',
                $id
            ]);

            header("Location: index.php?msg=updated");
            exit;
        } catch (PDOException $e) {
            $errorMessage = "Erreur SQL : " . $e->getMessage();
        }
    }
}

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr" class="h-100">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Modifier <?= h($medicament['NOM_DE_MARQUE']) ?> - Pharmacie DZ</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; }
    .card { border-radius: 1rem; border: 1px solid #e2e8f0; }
  </style>
</head>
<body class="py-4">
<div class="container" style="max-width: 860px;">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center gap-2">
      <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-circle p-2" title="Retour">
        <i data-lucide="arrow-left" style="width: 18px; height: 18px;"></i>
      </a>
      <div>
        <h3 class="fw-bold mb-0">Modifier la fiche médicament</h3>
        <div class="text-muted small">ID #<?= $id ?> &bull; <?= h($medicament['NOM_DE_MARQUE']) ?></div>
      </div>
    </div>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">Retour à la liste</a>
  </div>

  <?php if ($errorMessage): ?>
    <div class="alert alert-danger"><?= h($errorMessage) ?></div>
  <?php endif; ?>

  <div class="card p-4 shadow-sm bg-white">
    <form method="post" class="row g-3">
      <!-- Section Informations Générales -->
      <div class="col-12"><h6 class="fw-bold text-success border-bottom pb-2 mb-3">Identification</h6></div>

      <div class="col-md-6">
        <label class="form-label small fw-semibold">Nom de Marque *</label>
        <input type="text" name="NOM_DE_MARQUE" required value="<?= h($medicament['NOM_DE_MARQUE']) ?>" class="form-control">
      </div>

      <div class="col-md-6">
        <label class="form-label small fw-semibold">DCI (Substance Active) *</label>
        <input type="text" name="DENOMINATION_COMMUNE_INTERNATIONALE" required value="<?= h($medicament['DENOMINATION_COMMUNE_INTERNATIONALE']) ?>" class="form-control">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Code National</label>
        <input type="text" name="CODE" value="<?= h($medicament['CODE']) ?>" class="form-control">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">N° d’Enregistrement</label>
        <input type="text" name="NUM_ENREGISTREMENT" value="<?= h($medicament['NUM_ENREGISTREMENT']) ?>" class="form-control">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Type de Produit</label>
        <select name="TYPE" class="form-select">
          <option value="Ge" <?= stripos($medicament['TYPE'] ?? '', 'ge') === 0 ? 'selected' : '' ?>>Générique (Ge)</option>
          <option value="Re" <?= stripos($medicament['TYPE'] ?? '', 're') === 0 ? 'selected' : '' ?>>Princeps / Référence (Re)</option>
        </select>
      </div>

      <!-- Spécifications Galéniques -->
      <div class="col-12 mt-4"><h6 class="fw-bold text-success border-bottom pb-2 mb-3">Galénique & Présentation</h6></div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Forme</label>
        <input type="text" name="FORME" value="<?= h($medicament['FORME']) ?>" class="form-control">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Dosage</label>
        <input type="text" name="DOSAGE" value="<?= h($medicament['DOSAGE']) ?>" class="form-control">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Conditionnement</label>
        <input type="text" name="COND" value="<?= h($medicament['COND']) ?>" class="form-control">
      </div>

      <!-- Réglementation & Prix -->
      <div class="col-12 mt-4"><h6 class="fw-bold text-success border-bottom pb-2 mb-3">Réglementation, Prix & Chifa</h6></div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Prix Public (DA)</label>
        <input type="text" name="PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT" value="<?= h($medicament['PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT']) ?>" class="form-control">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Remboursement Chifa</label>
        <select name="REMBOURSEMENT" class="form-select">
          <option value="OUI" <?= stripos($medicament['REMBOURSEMENT'] ?? '', 'OUI') !== false ? 'selected' : '' ?>>OUI (Remboursable)</option>
          <option value="NON" <?= stripos($medicament['REMBOURSEMENT'] ?? '', 'NON') !== false ? 'selected' : '' ?>>NON (Non remboursable)</option>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Liste (Tableau)</label>
        <select name="LISTE" class="form-select">
          <option value="" <?= empty($medicament['LISTE']) ? 'selected' : '' ?>>Hors Liste (Vente libre)</option>
          <option value="Liste I" <?= stripos($medicament['LISTE'] ?? '', 'Liste I') !== false && stripos($medicament['LISTE'] ?? '', 'Liste II') === false ? 'selected' : '' ?>>Liste I (Rouge)</option>
          <option value="Liste II" <?= stripos($medicament['LISTE'] ?? '', 'Liste II') !== false ? 'selected' : '' ?>>Liste II (Vert)</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label small fw-semibold">Laboratoire Titulaire</label>
        <input type="text" name="LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT" value="<?= h($medicament['LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT']) ?>" class="form-control">
      </div>

      <div class="col-md-6">
        <label class="form-label small fw-semibold">Pays du Laboratoire</label>
        <input type="text" name="PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT" value="<?= h($medicament['PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT']) ?>" class="form-control">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Date Initiale</label>
        <input type="date" name="DATE_DENREGISTREMENT_INITIAL" value="<?= h($medicament['DATE_DENREGISTREMENT_INITIAL']) ?>" class="form-control">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Date Finale</label>
        <input type="date" name="DATE_DENREGISTREMENT_FINAL" value="<?= h($medicament['DATE_DENREGISTREMENT_FINAL']) ?>" class="form-control">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Durée de Stabilité</label>
        <input type="text" name="DUREE_DE_STABILITE" value="<?= h($medicament['DUREE_DE_STABILITE']) ?>" class="form-control">
      </div>

      <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end gap-2">
        <a href="index.php" class="btn btn-light border px-4">Annuler</a>
        <button type="submit" class="btn btn-success px-5 fw-semibold">Enregistrer les modifications</button>
      </div>
    </form>
  </div>
</div>

<script>
  if (window.lucide) lucide.createIcons();
</script>
</body>
</html>
