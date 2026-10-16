<?php
/**
 * Pharmacie DZ - Registre National des Médicaments d'Algérie
 * Interface Web moderne et performante (PHP / MySQL / Bootstrap 5 / Lucide Icons)
 */

// --- Configuration Base de Données ---
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

$dbConnected = false;
$dbError = null;
$pdo = null;

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $dbConnected = true;
} catch (PDOException $e) {
    $dbConnected = false;
    $dbError = $e->getMessage();
}

function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Variables d'état
$rows = [];
$totalRows = 0;
$totalPages = 1;
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = in_array((int)($_GET['limit'] ?? 25), [10, 25, 50, 100]) ? (int)$_GET['limit'] : 25;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$filterRemb = trim($_GET['remboursement'] ?? '');
$filterListe = trim($_GET['liste'] ?? '');
$filterType = trim($_GET['type'] ?? '');
$sortBy = in_array($_GET['sort'] ?? '', ['NOM_DE_MARQUE', 'DENOMINATION_COMMUNE_INTERNATIONALE', 'ID']) ? $_GET['sort'] : 'NOM_DE_MARQUE';
$sortOrder = ($_GET['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

// Stats globales
$statTotal = 0;
$statRemb = 0;
$statGen = 0;
$statLabs = 0;

if ($dbConnected) {
    // --- Traitement de la suppression ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
        $idToDelete = (int)$_POST['delete_id'];
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE `ID` = ?");
        $stmt->execute([$idToDelete]);
        header("Location: index.php?msg=deleted&page=" . $page . "&search=" . urlencode($search));
        exit;
    }

    try {
        // Statistiques rapides
        $statTotal = (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        $statRemb  = (int)$pdo->query("SELECT COUNT(*) FROM `$table` WHERE UPPER(REMBOURSEMENT) LIKE '%OUI%'")->fetchColumn();
        $statGen   = (int)$pdo->query("SELECT COUNT(*) FROM `$table` WHERE LOWER(TYPE) LIKE 'ge%'")->fetchColumn();
        $statLabs  = (int)$pdo->query("SELECT COUNT(DISTINCT LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT) FROM `$table`")->fetchColumn();

        // Construction de la requête avec filtres
        $whereClauses = [];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = "(NOM_DE_MARQUE LIKE :search 
                               OR DENOMINATION_COMMUNE_INTERNATIONALE LIKE :search 
                               OR CODE LIKE :search 
                               OR NUM_ENREGISTREMENT LIKE :search 
                               OR LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT LIKE :search)";
            $params[':search'] = "%$search%";
        }

        if ($filterRemb === 'OUI') {
            $whereClauses[] = "UPPER(REMBOURSEMENT) LIKE '%OUI%'";
        } elseif ($filterRemb === 'NON') {
            $whereClauses[] = "UPPER(REMBOURSEMENT) LIKE '%NON%'";
        }

        if ($filterListe !== '') {
            $whereClauses[] = "LISTE LIKE :liste";
            $params[':liste'] = "%$filterListe%";
        }

        if ($filterType !== '') {
            $whereClauses[] = "LOWER(TYPE) LIKE :type";
            $params[':type'] = strtolower($filterType) . "%";
        }

        $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

        // Total après filtrage
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` $whereSql");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = max(1, ceil($totalRows / $limit));

        // Sélection paginée
        $sql = "SELECT * FROM `$table` $whereSql ORDER BY `$sortBy` $sortOrder LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (PDOException $e) {
        $dbError = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-100">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pharmacie DZ - Gestion de la Nomenclature</title>
  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>

  <style>
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background-color: #f8fafc;
      color: #1e293b;
    }
    .navbar-brand-badge {
      font-size: 0.68rem;
      padding: 0.2rem 0.5rem;
      border-radius: 9999px;
      background-color: #d1fae5;
      color: #065f46;
      font-weight: 700;
    }
    .card-kpi {
      border: 1px solid #e2e8f0;
      border-radius: 0.85rem;
      background: #ffffff;
      box-shadow: 0 1px 3px rgba(0,0,0,0.03);
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .card-kpi:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }
    .kpi-icon {
      width: 48px;
      height: 48px;
      border-radius: 0.75rem;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .table thead th {
      background-color: #f1f5f9;
      color: #475569;
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      border-bottom: 2px solid #e2e8f0;
      white-space: nowrap;
    }
    .table tbody td {
      vertical-align: middle;
      font-size: 0.825rem;
    }
    .badge-chifa {
      background-color: #dcfce7;
      color: #15803d;
      font-weight: 600;
      border-radius: 9999px;
      padding: 0.25rem 0.6rem;
      font-size: 0.72rem;
    }
    .badge-non-remb {
      background-color: #f1f5f9;
      color: #64748b;
      font-weight: 500;
      border-radius: 9999px;
      padding: 0.25rem 0.6rem;
      font-size: 0.72rem;
    }
    .badge-liste-1 {
      background-color: #ffe4e6;
      color: #be123c;
      font-weight: 600;
      padding: 0.2rem 0.5rem;
      border-radius: 0.35rem;
      font-size: 0.7rem;
    }
    .badge-liste-2 {
      background-color: #fef3c7;
      color: #92400e;
      font-weight: 600;
      padding: 0.2rem 0.5rem;
      border-radius: 0.35rem;
      font-size: 0.7rem;
    }
    .badge-generic {
      background-color: #e0f2fe;
      color: #0369a1;
      font-weight: 700;
      padding: 0.2rem 0.45rem;
      border-radius: 0.35rem;
      font-size: 0.7rem;
    }
    .badge-princeps {
      background-color: #f3e8ff;
      color: #7e22ce;
      font-weight: 700;
      padding: 0.2rem 0.45rem;
      border-radius: 0.35rem;
      font-size: 0.7rem;
    }
    .price-tag {
      font-weight: 700;
      color: #0f172a;
    }
  </style>
</head>
<body class="d-flex flex-column h-100">

  <!-- Barre de Navigation Supérieure -->
  <header class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top py-2 shadow-sm">
    <div class="container-fluid px-4">
      <a class="navbar-brand d-flex items-center gap-2" href="index.php">
        <div class="bg-success text-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
          <i data-lucide="pill" style="width: 22px; height: 22px;"></i>
        </div>
        <div>
          <div class="d-flex align-items-center gap-2">
            <span class="fw-bold fs-5 text-dark">Pharmacie DZ</span>
            <span class="navbar-brand-badge">Officiel PHP</span>
          </div>
          <div class="text-muted" style="font-size: 0.72rem;">Nomenclature nationale des médicaments d'Algérie</div>
        </div>
      </a>

      <div class="d-flex align-items-center gap-2 ms-auto">
        <!-- Lien vers version SPA Node.js -->
        <a href="http://localhost:3000" target="_blank" class="btn btn-sm btn-outline-secondary d-none d-md-inline-flex align-items-center gap-1.5" title="Accéder à l'application web temps-réel Node.js">
          <i data-lucide="sparkles" style="width: 15px; height: 15px;" class="text-warning"></i>
          <span>Version Web SPA (3000)</span>
        </a>

        <!-- Bouton Ajouter -->
        <a href="add.php" class="btn btn-sm btn-success d-inline-flex align-items-center gap-1 px-3 py-2 fw-semibold shadow-sm">
          <i data-lucide="plus-circle" style="width: 16px; height: 16px;"></i>
          <span>Nouveau Médicament</span>
        </a>
      </div>
    </div>
  </header>

  <!-- Conteneur Principal -->
  <main class="container-fluid px-4 py-4 flex-grow-1">

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
      <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 py-2" role="alert">
        <i data-lucide="check-circle" style="width: 18px; height: 18px;"></i>
        <div>Médicament supprimé avec succès.</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'created'): ?>
      <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 py-2" role="alert">
        <i data-lucide="check-circle" style="width: 18px; height: 18px;"></i>
        <div>Nouveau médicament enregistré avec succès dans la nomenclature.</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
      <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 py-2" role="alert">
        <i data-lucide="check-circle" style="width: 18px; height: 18px;"></i>
        <div>Fiche médicament mise à jour avec succès.</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <?php if (!$dbConnected): ?>
      <!-- Alerte Déconnexion MySQL -->
      <div class="card border-warning bg-warning-subtle p-4 rounded-4 mb-4 shadow-sm">
        <div class="d-flex align-items-start gap-3">
          <div class="bg-warning text-dark p-2 rounded-3">
            <i data-lucide="database-zap" style="width: 28px; height: 28px;"></i>
          </div>
          <div class="flex-grow-1">
            <h5 class="fw-bold mb-1">Serveur MySQL non détecté</h5>
            <p class="text-muted small mb-2">
              Impossible de se connecter à la base MySQL locale (<code><?= h($dbError) ?></code>).
            </p>
            <div class="bg-white p-3 rounded-3 border border-warning-subtle small mb-3">
              <strong>Pour utiliser la version PHP :</strong>
              <ol class="mb-0 mt-1 ps-3">
                <li>Lancez <strong>XAMPP Control Panel</strong> et démarrez <strong>MySQL</strong>.</li>
                <li>Importez le fichier <code>pharmacie.sql</code> dans phpMyAdmin (base : <code>pharmacie</code>).</li>
                <li>Rechargez cette page.</li>
              </ol>
            </div>
            <div class="d-flex gap-2">
              <a href="http://localhost:3000" target="_blank" class="btn btn-success btn-sm fw-semibold">
                Ouvrir la version autonome (Node.js / 4 627 médicaments prêts)
              </a>
              <a href="index.php" class="btn btn-outline-dark btn-sm">
                Réessayer la connexion
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php else: ?>

      <!-- Cartes Statistiques KPI -->
      <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
          <div class="card-kpi p-3 d-flex align-items-center gap-3">
            <div class="kpi-icon bg-success-subtle text-success">
              <i data-lucide="database" style="width: 24px; height: 24px;"></i>
            </div>
            <div>
              <div class="text-uppercase text-muted fw-semibold" style="font-size: 0.7rem;">Total Enregistrés</div>
              <div class="fs-4 fw-bold text-dark"><?= number_format($statTotal, 0, ',', ' ') ?></div>
              <div class="text-muted" style="font-size: 0.7rem;">Nomenclature nationale</div>
            </div>
          </div>
        </div>

        <div class="col-6 col-lg-3">
          <div class="card-kpi p-3 d-flex align-items-center gap-3">
            <div class="kpi-icon bg-info-subtle text-info">
              <i data-lucide="shield-check" style="width: 24px; height: 24px;"></i>
            </div>
            <div>
              <div class="text-uppercase text-muted fw-semibold" style="font-size: 0.7rem;">Remboursables Chifa</div>
              <div class="fs-4 fw-bold text-success"><?= number_format($statRemb, 0, ',', ' ') ?></div>
              <div class="text-success fw-medium" style="font-size: 0.7rem;">
                <?= $statTotal > 0 ? round(($statRemb / $statTotal) * 100) : 0 ?>% remboursés
              </div>
            </div>
          </div>
        </div>

        <div class="col-6 col-lg-3">
          <div class="card-kpi p-3 d-flex align-items-center gap-3">
            <div class="kpi-icon bg-primary-subtle text-primary">
              <i data-lucide="layers" style="width: 24px; height: 24px;"></i>
            </div>
            <div>
              <div class="text-uppercase text-muted fw-semibold" style="font-size: 0.7rem;">Génériques (Ge)</div>
              <div class="fs-4 fw-bold text-primary"><?= number_format($statGen, 0, ',', ' ') ?></div>
              <div class="text-muted" style="font-size: 0.7rem;">Équivalents bio</div>
            </div>
          </div>
        </div>

        <div class="col-6 col-lg-3">
          <div class="card-kpi p-3 d-flex align-items-center gap-3">
            <div class="kpi-icon bg-warning-subtle text-warning">
              <i data-lucide="building-2" style="width: 24px; height: 24px;"></i>
            </div>
            <div>
              <div class="text-uppercase text-muted fw-semibold" style="font-size: 0.7rem;">Laboratoires</div>
              <div class="fs-4 fw-bold text-dark"><?= number_format($statLabs, 0, ',', ' ') ?></div>
              <div class="text-muted" style="font-size: 0.7rem;">Algérie & Monde</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Barre de Recherche et Filtres -->
      <div class="card border-0 rounded-4 shadow-sm p-3 mb-4 bg-white">
        <form method="get" class="row g-2 align-items-end">
          <!-- Recherche Texte -->
          <div class="col-12 col-md-4">
            <label class="form-label text-muted fw-semibold small mb-1">Recherche globale</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0">
                <i data-lucide="search" style="width: 16px; height: 16px;" class="text-muted"></i>
              </span>
              <input type="text" name="search" class="form-control border-start-0 ps-0 bg-light" 
                     placeholder="Nom, DCI, Code ou Laboratoire..." 
                     value="<?= h($search) ?>">
            </div>
          </div>

          <!-- Filtre Remboursement -->
          <div class="col-6 col-sm-4 col-md-2">
            <label class="form-label text-muted fw-semibold small mb-1">Remboursement</label>
            <select name="remboursement" class="form-select bg-light">
              <option value="">Tous</option>
              <option value="OUI" <?= $filterRemb === 'OUI' ? 'selected' : '' ?>>Remboursable (OUI)</option>
              <option value="NON" <?= $filterRemb === 'NON' ? 'selected' : '' ?>>Non remboursable (NON)</option>
            </select>
          </div>

          <!-- Filtre Liste / Tableau -->
          <div class="col-6 col-sm-4 col-md-2">
            <label class="form-label text-muted fw-semibold small mb-1">Liste Tableau</label>
            <select name="liste" class="form-select bg-light">
              <option value="">Toutes</option>
              <option value="Liste I" <?= $filterListe === 'Liste I' ? 'selected' : '' ?>>Liste I</option>
              <option value="Liste II" <?= $filterListe === 'Liste II' ? 'selected' : '' ?>>Liste II</option>
            </select>
          </div>

          <!-- Filtre Type -->
          <div class="col-6 col-sm-4 col-md-2">
            <label class="form-label text-muted fw-semibold small mb-1">Type</label>
            <select name="type" class="form-select bg-light">
              <option value="">Tous</option>
              <option value="Ge" <?= $filterType === 'Ge' ? 'selected' : '' ?>>Générique (Ge)</option>
              <option value="Re" <?= $filterType === 'Re' ? 'selected' : '' ?>>Princeps (Re)</option>
            </select>
          </div>

          <!-- Boutons Actions -->
          <div class="col-6 col-sm-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-success flex-grow-1 fw-semibold">
              Filtrer
            </button>
            <a href="index.php" class="btn btn-outline-secondary" title="Réinitialiser">
              <i data-lucide="rotate-ccw" style="width: 16px; height: 16px;"></i>
            </a>
          </div>
        </form>
      </div>

      <!-- En-tête des résultats et pagination supérieure -->
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-muted small">
          Affichage de <strong><?= count($rows) ?></strong> sur <strong><?= number_format($totalRows, 0, ',', ' ') ?></strong> médicament(s)
          <?php if ($search || $filterRemb || $filterListe || $filterType): ?>
            <span class="badge bg-secondary-subtle text-secondary ms-1">Filtres actifs</span>
          <?php endif; ?>
        </div>

        <div class="d-flex align-items-center gap-2">
          <form method="get" class="d-flex align-items-center gap-1 small">
            <input type="hidden" name="search" value="<?= h($search) ?>">
            <input type="hidden" name="remboursement" value="<?= h($filterRemb) ?>">
            <input type="hidden" name="liste" value="<?= h($filterListe) ?>">
            <input type="hidden" name="type" value="<?= h($filterType) ?>">
            <label class="text-muted" for="limitSelect">Lignes :</label>
            <select id="limitSelect" name="limit" onchange="this.form.submit()" class="form-select form-select-sm py-0" style="width: auto;">
              <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10</option>
              <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25</option>
              <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
              <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
            </select>
          </form>
        </div>
      </div>

      <!-- Tableau Moderne des Médicaments -->
      <div class="card border-0 rounded-4 shadow-sm overflow-hidden bg-white mb-4">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th class="ps-3">Médicament (Marque)</th>
                <th>DCI (Substance Active)</th>
                <th>Forme & Dosage</th>
                <th>Conditionnement</th>
                <th>Laboratoire & Pays</th>
                <th>Prix</th>
                <th>Chifa</th>
                <th class="text-end pe-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($rows)): ?>
                <tr>
                  <td colspan="8" class="text-center py-5 text-muted">
                    <i data-lucide="package-search" style="width: 42px; height: 42px;" class="mb-2 text-secondary opacity-50 d-block mx-auto"></i>
                    <div class="fw-semibold">Aucun médicament trouvé</div>
                    <div class="small">Modifiez vos critères ou réinitialisez la recherche.</div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <?php 
                    $isRemb = !empty($r['REMBOURSEMENT']) && stripos($r['REMBOURSEMENT'], 'OUI') !== false;
                    $isNonRemb = !empty($r['REMBOURSEMENT']) && stripos($r['REMBOURSEMENT'], 'NON') !== false;
                    $isListe1 = !empty($r['LISTE']) && stripos($r['LISTE'], 'Liste I') !== false && stripos($r['LISTE'], 'Liste II') === false;
                    $isListe2 = !empty($r['LISTE']) && stripos($r['LISTE'], 'Liste II') !== false;
                    $isGen = !empty($r['TYPE']) && stripos($r['TYPE'], 'ge') === 0;
                  ?>
                  <tr>
                    <td class="ps-3">
                      <div class="d-flex align-items-center gap-1.5 mb-1">
                        <span class="badge <?= $isGen ? 'badge-generic' : 'badge-princeps' ?>">
                          <?= $isGen ? 'Générique' : 'Princeps' ?>
                        </span>
                        <?php if ($isListe1): ?>
                          <span class="badge-liste-1">Liste I</span>
                        <?php elseif ($isListe2): ?>
                          <span class="badge-liste-2">Liste II</span>
                        <?php endif; ?>
                      </div>
                      <a href="#" class="fw-bold text-dark text-decoration-none view-detail-link"
                         data-bs-toggle="modal" 
                         data-bs-target="#detailModal"
                         data-med='<?= h(json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>
                        <?= h($r['NOM_DE_MARQUE'] ?: 'Sans nom') ?>
                      </a>
                      <div class="text-muted font-monospace" style="font-size: 0.7rem;"><?= h($r['CODE'] ?? '') ?></div>
                    </td>

                    <td style="max-width: 220px;" class="text-truncate">
                      <span class="fw-medium text-success text-truncate d-block" title="<?= h($r['DENOMINATION_COMMUNE_INTERNATIONALE']) ?>">
                        <?= h($r['DENOMINATION_COMMUNE_INTERNATIONALE'] ?: '-') ?>
                      </span>
                      <span class="text-muted" style="font-size: 0.72rem;"><?= h($r['NUM_ENREGISTREMENT'] ?: '') ?></span>
                    </td>

                    <td>
                      <div class="fw-semibold text-dark"><?= h($r['FORME'] ?: '-') ?></div>
                      <div class="text-muted small"><?= h($r['DOSAGE'] ?: '-') ?></div>
                    </td>

                    <td>
                      <span class="text-secondary"><?= h($r['COND'] ?: '-') ?></span>
                    </td>

                    <td style="max-width: 180px;" class="text-truncate">
                      <div class="text-truncate fw-medium" title="<?= h($r['LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT']) ?>">
                        <?= h($r['LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT'] ?: '-') ?>
                      </div>
                      <div class="text-muted" style="font-size: 0.7rem;">
                        <?= h($r['PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT'] ?: 'DZ') ?>
                      </div>
                    </td>

                    <td>
                      <span class="price-tag"><?= h($r['PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT'] ?: 'N/C') ?></span>
                    </td>

                    <td>
                      <?php if ($isRemb): ?>
                        <span class="badge-chifa d-inline-flex align-items-center gap-1">
                          <i data-lucide="check" style="width: 12px; height: 12px;"></i> OUI
                        </span>
                      <?php elseif ($isNonRemb): ?>
                        <span class="badge-non-remb">NON</span>
                      <?php else: ?>
                        <span class="text-muted small">-</span>
                      <?php endif; ?>
                    </td>

                    <td class="text-end pe-3">
                      <div class="btn-group btn-group-sm">
                        <!-- Voir Détails -->
                        <button type="button" class="btn btn-light border text-muted view-detail-link"
                                data-bs-toggle="modal" 
                                data-bs-target="#detailModal"
                                data-med='<?= h(json_encode($r, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'
                                title="Voir la fiche complète">
                          <i data-lucide="eye" style="width: 15px; height: 15px;"></i>
                        </button>

                        <!-- Modifier -->
                        <a href="edit.php?id=<?= h($r['ID']) ?>" class="btn btn-light border text-muted" title="Modifier">
                          <i data-lucide="edit-3" style="width: 15px; height: 15px;"></i>
                        </a>

                        <!-- Supprimer -->
                        <form method="post" action="index.php" style="display:inline;"
                              onsubmit="return confirm('Confirmez-vous la suppression du médicament <?= addslashes(h($r['NOM_DE_MARQUE'])) ?> (ID <?= h($r['ID']) ?>) ?');">
                          <input type="hidden" name="delete_id" value="<?= h($r['ID']) ?>">
                          <button type="submit" class="btn btn-light border text-danger" title="Supprimer">
                            <i data-lucide="trash-2" style="width: 15px; height: 15px;"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination Inférieure -->
      <?php if ($totalPages > 1): ?>
        <nav class="d-flex justify-content-between align-items-center flex-wrap gap-2 my-4">
          <div class="text-muted small">
            Page <strong><?= $page ?></strong> sur <strong><?= $totalPages ?></strong>
          </div>

          <ul class="pagination pagination-sm mb-0">
            <!-- Bouton Début -->
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="?page=1&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&remboursement=<?= urlencode($filterRemb) ?>&liste=<?= urlencode($filterListe) ?>&type=<?= urlencode($filterType) ?>">
                &laquo;
              </a>
            </li>

            <!-- Précédent -->
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="?page=<?= max(1, $page - 1) ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&remboursement=<?= urlencode($filterRemb) ?>&liste=<?= urlencode($filterListe) ?>&type=<?= urlencode($filterType) ?>">
                Précédent
              </a>
            </li>

            <!-- Numéros de pages dynamiques -->
            <?php
              $start = max(1, $page - 2);
              $end   = min($totalPages, $page + 2);
              for ($i = $start; $i <= $end; $i++):
            ?>
              <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&remboursement=<?= urlencode($filterRemb) ?>&liste=<?= urlencode($filterListe) ?>&type=<?= urlencode($filterType) ?>">
                  <?= $i ?>
                </a>
              </li>
            <?php endfor; ?>

            <!-- Suivant -->
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="?page=<?= min($totalPages, $page + 1) ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&remboursement=<?= urlencode($filterRemb) ?>&liste=<?= urlencode($filterListe) ?>&type=<?= urlencode($filterType) ?>">
                Suivant
              </a>
            </li>

            <!-- Fin -->
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
              <a class="page-link" href="?page=<?= $totalPages ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&remboursement=<?= urlencode($filterRemb) ?>&liste=<?= urlencode($filterListe) ?>&type=<?= urlencode($filterType) ?>">
                &raquo;
              </a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>

    <?php endif; ?>

  </main>

  <!-- Modal de Consultation Détaillée -->
  <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
        <div class="modal-header bg-light border-0 py-3">
          <div>
            <h5 class="modal-title fw-bold text-dark" id="modalBrand">Détails du médicament</h5>
            <div class="text-success small fw-medium" id="modalDci">DCI</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-sm-6 col-md-4">
              <label class="text-muted small">Numéro Enregistrement</label>
              <div class="fw-semibold" id="modalNumEnreg">-</div>
            </div>
            <div class="col-sm-6 col-md-4">
              <label class="text-muted small">Code Produit</label>
              <div class="fw-semibold" id="modalCode">-</div>
            </div>
            <div class="col-sm-6 col-md-4">
              <label class="text-muted small">Prix Officiel (DA)</label>
              <div class="fw-bold text-success fs-6" id="modalPrice">-</div>
            </div>

            <div class="col-sm-6 col-md-4">
              <label class="text-muted small">Forme</label>
              <div class="fw-semibold" id="modalForme">-</div>
            </div>
            <div class="col-sm-6 col-md-4">
              <label class="text-muted small">Dosage</label>
              <div class="fw-semibold" id="modalDosage">-</div>
            </div>
            <div class="col-sm-6 col-md-4">
              <label class="text-muted small">Conditionnement</label>
              <div class="fw-semibold" id="modalCond">-</div>
            </div>

            <div class="col-sm-6 col-md-4">
              <label class="text-muted small">Remboursement Chifa</label>
              <div class="fw-semibold" id="modalRemb">-</div>
            </div>
            <div class="col-sm-6 col-md-4">
              <label class="text-muted small">Liste (Tableau)</label>
              <div class="fw-semibold" id="modalListe">-</div>
            </div>
            <div class="col-sm-6 col-md-4">
              <label class="text-muted small">Type Produit</label>
              <div class="fw-semibold" id="modalType">-</div>
            </div>

            <div class="col-sm-6 col-md-6">
              <label class="text-muted small">Laboratoire Détenteur</label>
              <div class="fw-semibold" id="modalLab">-</div>
            </div>
            <div class="col-sm-6 col-md-6">
              <label class="text-muted small">Pays de Fabrication</label>
              <div class="fw-semibold" id="modalCountry">-</div>
            </div>

            <div class="col-sm-6 col-md-4">
              <label class="text-muted small">Date Enregistrement Initial</label>
              <div class="fw-semibold" id="modalDateInit">-</div>
            </div>
            <div class="col-sm-6 col-md-4">
              <label class="text-muted small">Date Finale</label>
              <div class="fw-semibold" id="modalDateFinal">-</div>
            </div>
            <div class="col-sm-6 col-md-4">
              <label class="text-muted small">Durée de Stabilité</label>
              <div class="fw-semibold" id="modalDuree">-</div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light border-0 py-2">
          <a id="modalEditBtn" href="#" class="btn btn-sm btn-primary">
            Modifier cette fiche
          </a>
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fermer</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Pied de page -->
  <footer class="bg-white border-top py-3 text-center text-muted small mt-auto">
    <div class="container">
      &copy; <?= date('Y') ?> Pharmacie DZ &bull; Base officielle de la nomenclature des médicaments &bull;
      <a href="http://localhost:3000" class="text-success text-decoration-none fw-semibold">Version Interactive Node.js</a>
    </div>
  </footer>

  <!-- Bootstrap 5 JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Initialiser les icônes Lucide
    if (window.lucide) {
      lucide.createIcons();
    }

    // Gestion du modal détail
    document.querySelectorAll('.view-detail-link').forEach(btn => {
      btn.addEventListener('click', function(e) {
        try {
          const raw = this.getAttribute('data-med');
          if (!raw) return;
          const data = JSON.parse(raw);

          document.getElementById('modalBrand').textContent = data.NOM_DE_MARQUE || 'Sans nom';
          document.getElementById('modalDci').textContent = data.DENOMINATION_COMMUNE_INTERNATIONALE || 'Non spécifié';
          document.getElementById('modalNumEnreg').textContent = data.NUM_ENREGISTREMENT || '-';
          document.getElementById('modalCode').textContent = data.CODE || '-';
          document.getElementById('modalPrice').textContent = data.PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT || 'N/C';
          document.getElementById('modalForme').textContent = data.FORME || '-';
          document.getElementById('modalDosage').textContent = data.DOSAGE || '-';
          document.getElementById('modalCond').textContent = data.COND || '-';
          document.getElementById('modalRemb').textContent = data.REMBOURSEMENT || 'NON';
          document.getElementById('modalListe').textContent = data.LISTE || 'Hors liste';
          document.getElementById('modalType').textContent = data.TYPE || '-';
          document.getElementById('modalLab').textContent = data.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT || '-';
          document.getElementById('modalCountry').textContent = data.PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT || 'Algérie';
          document.getElementById('modalDateInit').textContent = data.DATE_DENREGISTREMENT_INITIAL || '-';
          document.getElementById('modalDateFinal').textContent = data.DATE_DENREGISTREMENT_FINAL || '-';
          document.getElementById('modalDuree').textContent = data.DUREE_DE_STABILITE || '-';

          document.getElementById('modalEditBtn').href = 'edit.php?id=' + encodeURIComponent(data.ID);
        } catch (err) {
          console.error('Modal parse error:', err);
        }
      });
    });
  </script>
</body>
</html>
