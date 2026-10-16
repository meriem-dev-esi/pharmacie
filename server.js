const http = require('http');
const fs = require('fs');
const path = require('path');
const url = require('url');

const PORT = process.env.PORT || 3000;
const DATA_FILE = path.join(__dirname, 'medicines.json');
const PUBLIC_DIR = path.join(__dirname, 'public');

// In-memory database
let medicines = [];

function loadData() {
  try {
    if (fs.existsSync(DATA_FILE)) {
      const raw = fs.readFileSync(DATA_FILE, 'utf8');
      medicines = JSON.parse(raw);
      console.log(`Loaded ${medicines.length} medicines from ${DATA_FILE}`);
    } else {
      console.warn('medicines.json not found, running parse_sql.js...');
      require('./parse_sql.js');
      const raw = fs.readFileSync(DATA_FILE, 'utf8');
      medicines = JSON.parse(raw);
    }
  } catch (err) {
    console.error('Error loading medicines data:', err);
    medicines = [];
  }
}

function saveData() {
  try {
    fs.writeFileSync(DATA_FILE, JSON.stringify(medicines, null, 2), 'utf8');
    return true;
  } catch (err) {
    console.error('Error saving data:', err);
    return false;
  }
}

loadData();

// Helper to parse price string to number for sorting/calculations
function parsePrice(priceStr) {
  if (!priceStr || typeof priceStr !== 'string') return 0;
  const match = priceStr.match(/([\d\s,.]+)\s*DA/i);
  if (match) {
    const clean = match[1].replace(/\s/g, '').replace(',', '.');
    return parseFloat(clean) || 0;
  }
  return 0;
}

// MIME types
const MIME_TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon'
};

function sendJSON(res, status, data) {
  res.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type'
  });
  res.end(JSON.stringify(data));
}

function parseBody(req) {
  return new Promise((resolve, reject) => {
    let body = '';
    req.on('data', chunk => {
      body += chunk.toString();
      if (body.length > 10 * 1024 * 1024) {
        req.connection.destroy();
        reject(new Error('Payload too large'));
      }
    });
    req.on('end', () => {
      try {
        resolve(body ? JSON.parse(body) : {});
      } catch (e) {
        resolve({});
      }
    });
    req.on('error', reject);
  });
}

const server = http.createServer(async (req, res) => {
  const parsedUrl = url.parse(req.url, true);
  const pathname = parsedUrl.pathname;
  const query = parsedUrl.query;
  const method = req.method;

  // Handle CORS preflight
  if (method === 'OPTIONS') {
    res.writeHead(204, {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type'
    });
    res.end();
    return;
  }

  // --- API Routes ---

  // 1. GET /api/stats : Summary statistics for dashboard
  if (pathname === '/api/stats' && method === 'GET') {
    const total = medicines.length;
    let reimbursable = 0;
    let nonReimbursable = 0;
    let generics = 0;
    let princeps = 0;
    const formsMap = {};
    const labsMap = {};
    const countriesMap = {};

    for (const m of medicines) {
      if (m.REMBOURSEMENT && m.REMBOURSEMENT.toUpperCase().includes('OUI')) {
        reimbursable++;
      } else if (m.REMBOURSEMENT && m.REMBOURSEMENT.toUpperCase().includes('NON')) {
        nonReimbursable++;
      }

      if (m.TYPE && m.TYPE.toLowerCase().startsWith('ge')) {
        generics++;
      } else if (m.TYPE && m.TYPE.toLowerCase().startsWith('re')) {
        princeps++;
      }

      if (m.FORME) {
        const formClean = m.FORME.trim().toUpperCase();
        formsMap[formClean] = (formsMap[formClean] || 0) + 1;
      }

      if (m.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT) {
        const labClean = m.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT.trim().toUpperCase();
        labsMap[labClean] = (labsMap[labClean] || 0) + 1;
      }

      if (m.PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT) {
        const cClean = m.PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT.trim().toUpperCase();
        countriesMap[cClean] = (countriesMap[cClean] || 0) + 1;
      }
    }

    // Top 5 forms
    const topForms = Object.entries(formsMap)
      .sort((a, b) => b[1] - a[1])
      .slice(0, 8)
      .map(([name, count]) => ({ name, count }));

    // Top 5 labs
    const topLabs = Object.entries(labsMap)
      .sort((a, b) => b[1] - a[1])
      .slice(0, 8)
      .map(([name, count]) => ({ name, count }));

    // Distinct countries
    const countries = Object.keys(countriesMap).sort();

    return sendJSON(res, 200, {
      total,
      reimbursable,
      nonReimbursable,
      generics,
      princeps,
      topForms,
      topLabs,
      countries
    });
  }

  // 2. GET /api/medicines : List with search, filtering, sorting, and pagination
  if (pathname === '/api/medicines' && method === 'GET') {
    const search = (query.search || '').trim().toLowerCase();
    const reimbursement = (query.reimbursement || '').trim();
    const liste = (query.liste || '').trim();
    const form = (query.form || '').trim().toLowerCase();
    const country = (query.country || '').trim().toLowerCase();
    const type = (query.type || '').trim().toLowerCase();
    const page = Math.max(1, parseInt(query.page) || 1);
    const limit = Math.max(1, Math.min(100, parseInt(query.limit) || 20));
    const sortBy = query.sortBy || 'ID';
    const sortOrder = query.sortOrder === 'desc' ? -1 : 1;

    let filtered = medicines.filter(m => {
      // Global text search across brand, dci, code, lab, num
      if (search) {
        const matchBrand = m.NOM_DE_MARQUE && m.NOM_DE_MARQUE.toLowerCase().includes(search);
        const matchDci = m.DENOMINATION_COMMUNE_INTERNATIONALE && m.DENOMINATION_COMMUNE_INTERNATIONALE.toLowerCase().includes(search);
        const matchCode = m.CODE && m.CODE.toLowerCase().includes(search);
        const matchNum = m.NUM_ENREGISTREMENT && m.NUM_ENREGISTREMENT.toLowerCase().includes(search);
        const matchLab = m.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT && m.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT.toLowerCase().includes(search);
        if (!matchBrand && !matchDci && !matchCode && !matchNum && !matchLab) return false;
      }

      // Reimbursement filter
      if (reimbursement) {
        if (reimbursement === 'OUI' && (!m.REMBOURSEMENT || !m.REMBOURSEMENT.toUpperCase().includes('OUI'))) return false;
        if (reimbursement === 'NON' && (!m.REMBOURSEMENT || !m.REMBOURSEMENT.toUpperCase().includes('NON'))) return false;
        if (reimbursement === 'NULL' && m.REMBOURSEMENT) return false;
      }

      // Prescription list filter
      if (liste) {
        if (!m.LISTE || !m.LISTE.toLowerCase().includes(liste.toLowerCase())) return false;
      }

      // Form filter
      if (form) {
        if (!m.FORME || !m.FORME.toLowerCase().includes(form)) return false;
      }

      // Country filter
      if (country) {
        if (!m.PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT ||
            !m.PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT.toLowerCase().includes(country)) return false;
      }

      // Type filter (Ge or Re)
      if (type) {
        if (!m.TYPE || !m.TYPE.toLowerCase().startsWith(type)) return false;
      }

      return true;
    });

    // Sorting
    filtered.sort((a, b) => {
      let va = a[sortBy];
      let vb = b[sortBy];

      if (sortBy === 'PRIX') {
        va = parsePrice(a.PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT);
        vb = parsePrice(b.PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT);
        return (va - vb) * sortOrder;
      }

      if (va === null || va === undefined) va = '';
      if (vb === null || vb === undefined) vb = '';

      if (typeof va === 'number' && typeof vb === 'number') {
        return (va - vb) * sortOrder;
      }

      return String(va).localeCompare(String(vb), 'fr', { numeric: true }) * sortOrder;
    });

    const total = filtered.length;
    const totalPages = Math.ceil(total / limit) || 1;
    const start = (page - 1) * limit;
    const items = filtered.slice(start, start + limit);

    return sendJSON(res, 200, {
      items,
      total,
      page,
      limit,
      totalPages
    });
  }

  // 3. GET /api/medicines/equivalents?dci=...&dosage=... : Generic equivalent finder
  if (pathname === '/api/medicines/equivalents' && method === 'GET') {
    const dci = (query.dci || '').trim().toLowerCase();
    const dosage = (query.dosage || '').trim().toLowerCase();
    const excludeId = parseInt(query.excludeId) || 0;

    if (!dci) {
      return sendJSON(res, 400, { error: 'Paramètre DCI requis' });
    }

    const matches = medicines.filter(m => {
      if (m.ID === excludeId) return false;
      if (!m.DENOMINATION_COMMUNE_INTERNATIONALE) return false;
      const matchDci = m.DENOMINATION_COMMUNE_INTERNATIONALE.toLowerCase().includes(dci) ||
                       dci.includes(m.DENOMINATION_COMMUNE_INTERNATIONALE.toLowerCase());
      if (!matchDci) return false;

      // If dosage provided, prefer matching dosage
      if (dosage && m.DOSAGE) {
        const d1 = dosage.replace(/\s/g, '').toLowerCase();
        const d2 = m.DOSAGE.replace(/\s/g, '').toLowerCase();
        return d1 === d2 || d2.includes(d1) || d1.includes(d2);
      }
      return true;
    });

    return sendJSON(res, 200, {
      count: matches.length,
      equivalents: matches.slice(0, 50)
    });
  }

  // 4. GET /api/medicines/:id : Single medicine details
  const singleMatch = pathname.match(/^\/api\/medicines\/(\d+)$/);
  if (singleMatch && method === 'GET') {
    const id = parseInt(singleMatch[1]);
    const item = medicines.find(m => m.ID === id);
    if (!item) {
      return sendJSON(res, 404, { error: 'Médicament non trouvé' });
    }
    return sendJSON(res, 200, item);
  }

  // 5. POST /api/medicines : Create new medicine
  if (pathname === '/api/medicines' && method === 'POST') {
    const body = await parseBody(req);
    if (!body.NOM_DE_MARQUE || !body.DENOMINATION_COMMUNE_INTERNATIONALE) {
      return sendJSON(res, 400, { error: 'Le nom de marque et la DCI sont obligatoires.' });
    }

    const maxId = medicines.reduce((max, m) => Math.max(max, m.ID || 0), 0);
    const newMed = {
      ID: maxId + 1,
      NUM_ENREGISTREMENT: body.NUM_ENREGISTREMENT || '',
      CODE: body.CODE || '',
      DENOMINATION_COMMUNE_INTERNATIONALE: body.DENOMINATION_COMMUNE_INTERNATIONALE,
      NOM_DE_MARQUE: body.NOM_DE_MARQUE,
      FORME: body.FORME || '',
      DOSAGE: body.DOSAGE || '',
      COND: body.COND || '',
      LISTE: body.LISTE || '',
      P1: body.P1 || '',
      P2: body.P2 || '',
      OBS: body.OBS || '',
      LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT: body.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT || '',
      PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT: body.PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT || 'ALGERIE',
      DATE_DENREGISTREMENT_INITIAL: body.DATE_DENREGISTREMENT_INITIAL || new Date().toISOString().slice(0, 10),
      DATE_DENREGISTREMENT_FINAL: body.DATE_DENREGISTREMENT_FINAL || '',
      TYPE: body.TYPE || 'Ge',
      STATUT: body.STATUT || 'F',
      DUREE_DE_STABILITE: body.DUREE_DE_STABILITE || '24 MOIS',
      PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT: body.PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT || '',
      REMBOURSEMENT: body.REMBOURSEMENT || 'OUI'
    };

    medicines.unshift(newMed);
    saveData();
    return sendJSON(res, 201, { message: 'Médicament ajouté avec succès', medicine: newMed });
  }

  // 6. PUT /api/medicines/:id : Update medicine
  if (singleMatch && method === 'PUT') {
    const id = parseInt(singleMatch[1]);
    const idx = medicines.findIndex(m => m.ID === id);
    if (idx === -1) {
      return sendJSON(res, 404, { error: 'Médicament non trouvé' });
    }

    const body = await parseBody(req);
    medicines[idx] = {
      ...medicines[idx],
      ...body,
      ID: id // keep original ID
    };

    saveData();
    return sendJSON(res, 200, { message: 'Médicament mis à jour', medicine: medicines[idx] });
  }

  // 7. DELETE /api/medicines/:id : Delete medicine
  if (singleMatch && method === 'DELETE') {
    const id = parseInt(singleMatch[1]);
    const idx = medicines.findIndex(m => m.ID === id);
    if (idx === -1) {
      return sendJSON(res, 404, { error: 'Médicament non trouvé' });
    }

    const removed = medicines.splice(idx, 1)[0];
    saveData();
    return sendJSON(res, 200, { message: 'Médicament supprimé', id: removed.ID });
  }

  // 8. GET /api/export-csv : Export CSV
  if (pathname === '/api/export-csv' && method === 'GET') {
    const search = (query.search || '').trim().toLowerCase();
    const reimbursement = (query.reimbursement || '').trim();

    let exportList = medicines;
    if (search || reimbursement) {
      exportList = exportList.filter(m => {
        if (search) {
          const matchBrand = m.NOM_DE_MARQUE && m.NOM_DE_MARQUE.toLowerCase().includes(search);
          const matchDci = m.DENOMINATION_COMMUNE_INTERNATIONALE && m.DENOMINATION_COMMUNE_INTERNATIONALE.toLowerCase().includes(search);
          if (!matchBrand && !matchDci) return false;
        }
        if (reimbursement) {
          if (reimbursement === 'OUI' && (!m.REMBOURSEMENT || !m.REMBOURSEMENT.toUpperCase().includes('OUI'))) return false;
          if (reimbursement === 'NON' && (!m.REMBOURSEMENT || !m.REMBOURSEMENT.toUpperCase().includes('NON'))) return false;
        }
        return true;
      });
    }

    const headers = [
      'ID', 'Num Enregistrement', 'Code', 'DCI', 'Nom de Marque', 'Forme', 'Dosage',
      'Conditionnement', 'Liste', 'Laboratoire', 'Pays', 'Prix', 'Remboursement'
    ];

    const csvRows = [headers.join(';')];
    for (const m of exportList) {
      csvRows.push([
        m.ID,
        `"${(m.NUM_ENREGISTREMENT || '').replace(/"/g, '""')}"`,
        `"${(m.CODE || '').replace(/"/g, '""')}"`,
        `"${(m.DENOMINATION_COMMUNE_INTERNATIONALE || '').replace(/"/g, '""')}"`,
        `"${(m.NOM_DE_MARQUE || '').replace(/"/g, '""')}"`,
        `"${(m.FORME || '').replace(/"/g, '""')}"`,
        `"${(m.DOSAGE || '').replace(/"/g, '""')}"`,
        `"${(m.COND || '').replace(/"/g, '""')}"`,
        `"${(m.LISTE || '').replace(/"/g, '""')}"`,
        `"${(m.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT || '').replace(/"/g, '""')}"`,
        `"${(m.PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT || '').replace(/"/g, '""')}"`,
        `"${(m.PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT || '').replace(/"/g, '""')}"`,
        `"${(m.REMBOURSEMENT || '').replace(/"/g, '""')}"`
      ].join(';'));
    }

    // Add UTF-8 BOM so Excel opens it with proper accents
    const csvContent = '\uFEFF' + csvRows.join('\r\n');
    res.writeHead(200, {
      'Content-Type': 'text/csv; charset=utf-8',
      'Content-Disposition': 'attachment; filename="medicaments_algerie.csv"'
    });
    return res.end(csvContent);
  }

  // --- Serve Static Frontend Files ---
  let filePath = path.join(PUBLIC_DIR, pathname === '/' ? 'index.html' : pathname);

  fs.stat(filePath, (err, stats) => {
    if (err || !stats.isFile()) {
      filePath = path.join(PUBLIC_DIR, 'index.html');
    }

    const ext = path.extname(filePath).toLowerCase();
    const contentType = MIME_TYPES[ext] || 'application/octet-stream';

    fs.readFile(filePath, (readErr, content) => {
      if (readErr) {
        res.writeHead(404, { 'Content-Type': 'text/plain' });
        res.end('Not Found');
        return;
      }
      res.writeHead(200, { 'Content-Type': contentType });
      res.end(content);
    });
  });
});

server.listen(PORT, () => {
  console.log(`====================================================`);
  console.log(` Pharmacie DZ - Web App démarrée avec succès !`);
  console.log(` Accédez à : http://localhost:${PORT}`);
  console.log(` Base : ${medicines.length} médicaments algériens`);
  console.log(`====================================================`);
});
