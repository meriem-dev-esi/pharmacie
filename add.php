<?php
/**
 * Pharmacie DZ - Formulaire d'ajout d'un médicament
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

$errorMessage = null;

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Connexion MySQL échouée : " . htmlspecialchars($e->getMessage()));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numEnreg = trim($_POST['NUM_ENREGISTREMENT'] ?? '');
    $code     = trim($_POST['CODE'] ?? '');
    $dci      = trim($_POST['DENOMINATION_COMMUNE_INTERNATIONALE'] ?? '');
    $marque   = trim($_POST['NOM_DE_MARQUE'] ?? '');
    $forme    = trim($_POST['FORME'] ?? '');
    $dosage   = trim($_POST['DOSAGE'] ?? '');
    $cond     = trim($_POST['COND'] ?? '');
    $liste    = trim($_POST['LISTE'] ?? '');
    $p1       = trim($_POST['P1'] ?? '');
    $p2       = trim($_POST['P2'] ?? '');
    $obs      = trim($_POST['OBS'] ?? '');
    $lab      = trim($_POST['LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT'] ?? '');
    $pays     = trim($_POST['PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT'] ?? '');
    $dateInit = !empty($_POST['DATE_DENREGISTREMENT_INITIAL']) ? $_POST['DATE_DENREGISTREMENT_INITIAL'] : null;
    $dateFin  = !empty($_POST['DATE_DENREGISTREMENT_FINAL']) ? $_POST['DATE_DENREGISTREMENT_FINAL'] : null;
    $type     = trim($_POST['TYPE'] ?? 'Ge');
    $statut   = trim($_POST['STATUT'] ?? '');
    $duree    = trim($_POST['DUREE_DE_STABILITE'] ?? '');
    $prix     = trim($_POST['PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT'] ?? '');
    $remb     = trim($_POST['REMBOURSEMENT'] ?? 'NON');

    if (empty($marque) || empty($dci)) {
        $errorMessage = "Le nom de marque et la DCI sont obligatoires.";
    } else {
        try {
            // Obtenir le prochain ID si non auto-incrémenté
            $maxId = (int)$pdo->query("SELECT MAX(ID) FROM `$table`")->fetchColumn();
            $newId = $maxId + 1;

            $sql = "INSERT INTO `$table` (
                ID, NUM_ENREGISTREMENT, CODE, DENOMINATION_COMMUNE_INTERNATIONALE, NOM_DE_MARQUE,
                FORME, DOSAGE, COND, LISTE, P1, P2, OBS,
                LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT,
                PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT,
                DATE_DENREGISTREMENT_INITIAL, DATE_DENREGISTREMENT_FINAL,
                TYPE, STATUT, DUREE_DE_STABILITE, PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT, REMBOURSEMENT
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $newId, $numEnreg, $code, $dci, $marque,
                $forme, $dosage, $cond, $liste, $p1, $p2, $obs,
                $lab, $pays, $dateInit, $dateFin,
                $type, $statut, $duree, $prix, $remb
            ]);

            header("Location: index.php?msg=created");
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
  <title>Ajouter un médicament - Pharmacie DZ</title>
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
      <h3 class="fw-bold mb-0">Ajouter un nouveau médicament</h3>
    </div>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">Annuler</a>
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
        <input type="text" name="NOM_DE_MARQUE" required class="form-control" placeholder="ex: RESTILON, DOLIPRANE">
      </div>

      <div class="col-md-6">
        <label class="form-label small fw-semibold">DCI (Substance Active) *</label>
        <input type="text" name="DENOMINATION_COMMUNE_INTERNATIONALE" required class="form-control" placeholder="ex: PARACETAMOL">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Code National</label>
        <input type="text" name="CODE" class="form-control" placeholder="ex: 15 F 099">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">N° d’Enregistrement</label>
        <input type="text" name="NUM_ENREGISTREMENT" class="form-control" placeholder="ex: 352/15 F 099/16">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Type de Produit</label>
        <select name="TYPE" class="form-select">
          <option value="Ge">Générique (Ge)</option>
          <option value="Re">Princeps / Référence (Re)</option>
        </select>
      </div>

      <!-- Spécifications Galéniques -->
      <div class="col-12 mt-4"><h6 class="fw-bold text-success border-bottom pb-2 mb-3">Galénique & Présentation</h6></div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Forme</label>
        <input type="text" name="FORME" class="form-control" placeholder="ex: COMP. PELLI., SIROP">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Dosage</label>
        <input type="text" name="DOSAGE" class="form-control" placeholder="ex: 500MG, 10MG/ML">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Conditionnement</label>
        <input type="text" name="COND" class="form-control" placeholder="ex: B/20, FL/120ML">
      </div>

      <!-- Réglementation & Prix -->
      <div class="col-12 mt-4"><h6 class="fw-bold text-success border-bottom pb-2 mb-3">Réglementation, Prix & Chifa</h6></div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Prix Public (DA)</label>
        <input type="text" name="PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT" class="form-control" placeholder="ex: PCSU 150,00 DA">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Remboursement Chifa</label>
        <select name="REMBOURSEMENT" class="form-select">
          <option value="OUI">OUI (Remboursable)</option>
          <option value="NON">NON (Non remboursable)</option>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Liste (Tableau)</label>
        <select name="LISTE" class="form-select">
          <option value="">Hors Liste (Vente libre)</option>
          <option value="Liste I">Liste I (Rouge)</option>
          <option value="Liste II">Liste II (Vert)</option>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label small fw-semibold">Laboratoire Titulaire</label>
        <input type="text" name="LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT" class="form-control" placeholder="ex: SAIDAL, EL KENDI">
      </div>

      <div class="col-md-6">
        <label class="form-label small fw-semibold">Pays du Laboratoire</label>
        <input type="text" name="PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT" value="ALGERIE" class="form-control">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Date Initiale</label>
        <input type="date" name="DATE_DENREGISTREMENT_INITIAL" class="form-control">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Date Finale</label>
        <input type="date" name="DATE_DENREGISTREMENT_FINAL" class="form-control">
      </div>

      <div class="col-md-4">
        <label class="form-label small fw-semibold">Durée de Stabilité</label>
        <input type="text" name="DUREE_DE_STABILITE" class="form-control" placeholder="ex: 24 MOIS, 36 MOIS">
      </div>

      <div class="col-12 mt-4 pt-3 border-top d-flex justify-content-end gap-2">
        <a href="index.php" class="btn btn-light border px-4">Annuler</a>
        <button type="submit" class="btn btn-success px-5 fw-semibold">Enregistrer le médicament</button>
      </div>
    </form>
  </div>
</div>

<script>
  if (window.lucide) lucide.createIcons();
</script>
</body>
</html>
