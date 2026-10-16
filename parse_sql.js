const fs = require('fs');
const path = require('path');

const sqlPath = path.join(__dirname, 'pharmacie.sql');
const content = fs.readFileSync(sqlPath, 'utf8');

const keys = [
  "ID",
  "NUM_ENREGISTREMENT",
  "CODE",
  "DENOMINATION_COMMUNE_INTERNATIONALE",
  "NOM_DE_MARQUE",
  "FORME",
  "DOSAGE",
  "COND",
  "LISTE",
  "P1",
  "P2",
  "OBS",
  "LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT",
  "PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT",
  "DATE_DENREGISTREMENT_INITIAL",
  "DATE_DENREGISTREMENT_FINAL",
  "TYPE",
  "STATUT",
  "DUREE_DE_STABILITE",
  "PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT",
  "REMBOURSEMENT"
];

function cleanVal(v) {
  v = v.trim();
  if (v.toUpperCase() === 'NULL') return null;
  if ((v.startsWith("'") && v.endsWith("'")) || (v.startsWith('"') && v.endsWith('"'))) {
    return v.substring(1, v.length - 1).replace(/''/g, "'").replace(/\\'/g, "");
  }
  const num = Number(v);
  return isNaN(num) ? v : num;
}

function parseTuple(valStr) {
  const result = [];
  let cur = '';
  let inQuotes = false;
  let quoteChar = '';
  let escaped = false;

  for (let i = 0; i < valStr.length; i++) {
    const c = valStr[i];
    if (escaped) {
      cur += c;
      escaped = false;
      continue;
    }
    if (c === '\\') {
      escaped = true;
      continue;
    }
    if ((c === "'" || c === '"') && !inQuotes) {
      inQuotes = true;
      quoteChar = c;
    } else if (c === quoteChar && inQuotes) {
      inQuotes = false;
    } else if (c === ',' && !inQuotes) {
      result.push(cleanVal(cur));
      cur = '';
    } else {
      cur += c;
    }
  }
  result.push(cleanVal(cur));
  return result;
}

const medicines = [];
const lines = content.split('\n');
let inInsert = false;

for (let i = 0; i < lines.length; i++) {
  let line = lines[i].trim();
  if (line.includes('INSERT INTO `medicament`')) {
    inInsert = true;
    continue;
  }
  if (!inInsert) continue;

  if (line.startsWith('(')) {
    // Check if ends with ), or );
    let trimmed = line;
    if (trimmed.endsWith(';')) trimmed = trimmed.slice(0, -1).trim();
    if (trimmed.endsWith(',')) trimmed = trimmed.slice(0, -1).trim();
    if (trimmed.startsWith('(') && trimmed.endsWith(')')) {
      trimmed = trimmed.substring(1, trimmed.length - 1);
    }
    try {
      const vals = parseTuple(trimmed);
      if (vals.length >= keys.length) {
        const obj = {};
        keys.forEach((k, idx) => {
          obj[k] = vals[idx] !== undefined ? vals[idx] : null;
        });
        medicines.push(obj);
      }
    } catch (e) {
      console.error(`Error parsing line ${i}:`, e.message);
    }
  }

  if (line.endsWith(';')) {
    inInsert = false;
  }
}

console.log(`Parsed ${medicines.length} medicines.`);
if (medicines.length > 0) {
  console.log('Sample medicine:', medicines[0]);
  fs.writeFileSync(path.join(__dirname, 'medicines.json'), JSON.stringify(medicines, null, 2), 'utf8');
  console.log('Saved medicines.json successfully!');
}
