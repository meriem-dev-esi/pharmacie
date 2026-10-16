// State management
let state = {
  search: '',
  reimbursement: '',
  liste: '',
  form: '',
  country: '',
  sortBy: 'NOM_DE_MARQUE',
  sortOrder: 'asc',
  page: 1,
  limit: 24,
  viewMode: 'grid', // 'grid' | 'table'
  cart: [], // { medicine, quantity }
  currentDetailItem: null
};

// Initialize Lucide icons
function refreshIcons() {
  if (window.lucide) {
    lucide.createIcons();
  }
}

// Toast notification helper
function showToast(message, type = 'info') {
  const container = document.getElementById('toastContainer');
  const toast = document.createElement('div');
  const bgClass = type === 'success' ? 'bg-emerald-600' : type === 'error' ? 'bg-rose-600' : 'bg-slate-800';

  toast.className = `${bgClass} text-white text-xs sm:text-sm px-4 py-2.5 rounded-xl shadow-lg flex items-center space-x-2 pointer-events-auto transform transition-all duration-300 translate-y-2 opacity-0`;
  toast.innerHTML = `<span>${message}</span>`;
  container.appendChild(toast);

  setTimeout(() => {
    toast.classList.remove('translate-y-2', 'opacity-0');
  }, 10);

  setTimeout(() => {
    toast.classList.add('opacity-0', 'translate-y-2');
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

// Cache for static deployment mode
let staticMedicinesCache = null;

async function getStaticMedicines() {
  if (staticMedicinesCache) return staticMedicinesCache;
  const res = await fetch('medicines.json');
  if (!res.ok) throw new Error('Impossible de charger medicines.json');
  staticMedicinesCache = await res.json();
  return staticMedicinesCache;
}

function parsePriceVal(p) {
  if (!p || typeof p !== 'string') return 0;
  const match = p.match(/([\d\s,.]+)\s*DA/i);
  if (match) {
    const clean = match[1].replace(/\s/g, '').replace(',', '.');
    return parseFloat(clean) || 0;
  }
  return 0;
}

// Fetch stats on load
async function loadStats() {
  try {
    let data;
    try {
      const res = await fetch('/api/stats');
      if (res.ok) {
        data = await res.json();
      }
    } catch (e) {}

    if (!data) {
      // Calculate stats client-side in static mode
      const all = await getStaticMedicines();
      const total = all.length;
      let reimbursable = 0;
      let generics = 0;
      const labsSet = new Set();

      for (const m of all) {
        if (m.REMBOURSEMENT && m.REMBOURSEMENT.toUpperCase().includes('OUI')) reimbursable++;
        if (m.TYPE && m.TYPE.toLowerCase().startsWith('ge')) generics++;
        if (m.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT) {
          labsSet.add(m.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT.trim().toUpperCase());
        }
      }
      data = {
        total,
        reimbursable,
        generics,
        topLabs: Array.from(labsSet).slice(0, 8)
      };
    }

    document.getElementById('statTotal').textContent = Number(data.total).toLocaleString('fr-FR');
    document.getElementById('statReimbursable').textContent = Number(data.reimbursable).toLocaleString('fr-FR');
    document.getElementById('statGenerics').textContent = Number(data.generics).toLocaleString('fr-FR');
    document.getElementById('statLabs').textContent = data.topLabs.length > 0 ? `${data.topLabs.length}+` : '0';

    if (data.total > 0) {
      const pct = Math.round((data.reimbursable / data.total) * 100);
      document.getElementById('statReimbursablePercent').textContent = `${pct}% remboursés par Chifa`;
    }
  } catch (err) {
    console.error('Failed to load stats:', err);
  }
}

// Main fetch medicines with query builder
async function loadMedicines() {
  const container = document.getElementById('medicinesContainer');
  container.innerHTML = `
    <div class="flex flex-col items-center justify-center py-20 text-slate-400">
      <div class="w-8 h-8 border-3 border-emerald-500 border-t-transparent rounded-full animate-spin mb-3"></div>
      <p class="text-sm">Recherche en cours dans la nomenclature...</p>
    </div>
  `;

  const params = new URLSearchParams({
    page: state.page,
    limit: state.limit,
    sortBy: state.sortBy,
    sortOrder: state.sortOrder
  });

  if (state.search) params.append('search', state.search);
  if (state.reimbursement) params.append('reimbursement', state.reimbursement);
  if (state.liste) params.append('liste', state.liste);
  if (state.form) params.append('form', state.form);
  if (state.country) params.append('country', state.country);

  try {
    let data;
    try {
      const res = await fetch(`/api/medicines?${params.toString()}`);
      if (res.ok) {
        data = await res.json();
      }
    } catch (e) {}

    if (!data) {
      // Client-side fallback for static Vercel / GitHub Pages
      const all = await getStaticMedicines();
      const search = (state.search || '').trim().toLowerCase();
      const reimbursement = state.reimbursement;
      const liste = (state.liste || '').toLowerCase();
      const form = (state.form || '').toLowerCase();
      const country = (state.country || '').toLowerCase();

      let filtered = all.filter(m => {
        if (search) {
          const matchBrand = m.NOM_DE_MARQUE && m.NOM_DE_MARQUE.toLowerCase().includes(search);
          const matchDci = m.DENOMINATION_COMMUNE_INTERNATIONALE && m.DENOMINATION_COMMUNE_INTERNATIONALE.toLowerCase().includes(search);
          const matchCode = m.CODE && m.CODE.toLowerCase().includes(search);
          const matchNum = m.NUM_ENREGISTREMENT && m.NUM_ENREGISTREMENT.toLowerCase().includes(search);
          const matchLab = m.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT && m.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT.toLowerCase().includes(search);
          if (!matchBrand && !matchDci && !matchCode && !matchNum && !matchLab) return false;
        }

        if (reimbursement === 'OUI' && (!m.REMBOURSEMENT || !m.REMBOURSEMENT.toUpperCase().includes('OUI'))) return false;
        if (reimbursement === 'NON' && (!m.REMBOURSEMENT || !m.REMBOURSEMENT.toUpperCase().includes('NON'))) return false;

        if (liste && (!m.LISTE || !m.LISTE.toLowerCase().includes(liste))) return false;
        if (form && (!m.FORME || !m.FORME.toLowerCase().includes(form))) return false;
        if (country && (!m.PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT || !m.PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT.toLowerCase().includes(country))) return false;

        return true;
      });

      filtered.sort((a, b) => {
        if (state.sortBy === 'PRIX') {
          const va = parsePriceVal(a.PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT);
          const vb = parsePriceVal(b.PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT);
          return (va - vb) * (state.sortOrder === 'desc' ? -1 : 1);
        }
        const va = a[state.sortBy] || '';
        const vb = b[state.sortBy] || '';
        return String(va).localeCompare(String(vb), 'fr', { numeric: true }) * (state.sortOrder === 'desc' ? -1 : 1);
      });

      const total = filtered.length;
      const totalPages = Math.ceil(total / state.limit) || 1;
      const start = (state.page - 1) * state.limit;
      const items = filtered.slice(start, start + state.limit);

      data = { items, total, page: state.page, limit: state.limit, totalPages };
    }

    renderMedicines(data.items);
    updatePagination(data.total, data.page, data.totalPages);
    updateResultCount(data.total);
    refreshIcons();
  } catch (err) {
    container.innerHTML = `
      <div class="bg-rose-50 border border-rose-200 text-rose-700 p-6 rounded-xl text-center">
        <p class="font-medium">Erreur lors de la récupération des médicaments.</p>
        <p class="text-xs mt-1 text-rose-500">${err.message}</p>
        <button onclick="loadMedicines()" class="mt-3 px-3 py-1.5 bg-rose-600 text-white rounded-lg text-xs font-semibold">Réessayer</button>
      </div>
    `;
  }
}

function updateResultCount(total) {
  document.getElementById('resultCount').textContent = `${Number(total).toLocaleString('fr-FR')} résultat${total > 1 ? 's' : ''}`;
}

// Render grid cards or table
function renderMedicines(items) {
  const container = document.getElementById('medicinesContainer');
  if (!items || items.length === 0) {
    container.innerHTML = `
      <div class="bg-white border border-slate-200 rounded-2xl p-12 text-center shadow-xs">
        <div class="w-14 h-14 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-3">
          <i data-lucide="search-x" class="w-7 h-7"></i>
        </div>
        <h3 class="text-base font-semibold text-slate-800">Aucun médicament trouvé</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1">Essayez de modifier votre mot-clé ou de réinitialiser certains filtres de recherche.</p>
        <button onclick="resetFilters()" class="mt-4 px-4 py-2 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition">
          Réinitialiser les filtres
        </button>
      </div>
    `;
    return;
  }

  if (state.viewMode === 'grid') {
    renderGrid(items, container);
  } else {
    renderTable(items, container);
  }
}

function renderGrid(items, container) {
  const html = `
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      ${items.map(m => createCardHTML(m)).join('')}
    </div>
  `;
  container.innerHTML = html;
}

function createCardHTML(m) {
  const isReimbursable = m.REMBOURSEMENT && m.REMBOURSEMENT.toUpperCase().includes('OUI');
  const isNonReimbursable = m.REMBOURSEMENT && m.REMBOURSEMENT.toUpperCase().includes('NON');
  const isListe1 = m.LISTE && m.LISTE.includes('I') && !m.LISTE.includes('II');
  const isListe2 = m.LISTE && m.LISTE.includes('II');
  const isGeneric = m.TYPE && m.TYPE.toLowerCase().startsWith('ge');

  return `
    <div class="bg-white rounded-xl border border-slate-200/90 shadow-2xs hover:shadow-md hover:border-emerald-300 transition-all duration-200 flex flex-col justify-between p-4 group">
      <div>
        <!-- Badges row -->
        <div class="flex items-center justify-between gap-1 mb-2">
          <div class="flex items-center space-x-1.5 flex-wrap gap-y-1">
            <span class="px-2 py-0.5 text-[10px] font-bold rounded ${isGeneric ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'}">
              ${isGeneric ? 'Générique' : 'Princeps'}
            </span>
            ${isListe1 ? '<span class="px-2 py-0.5 text-[10px] font-bold rounded bg-rose-100 text-rose-800">Liste I</span>' : ''}
            ${isListe2 ? '<span class="px-2 py-0.5 text-[10px] font-bold rounded bg-amber-100 text-amber-800">Liste II</span>' : ''}
          </div>
          <div>
            ${isReimbursable 
              ? '<span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-emerald-100 text-emerald-800 flex items-center space-x-1"><i data-lucide="check" class="w-3 h-3"></i><span>Chifa</span></span>' 
              : isNonReimbursable 
                ? '<span class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-slate-100 text-slate-500">Non remb.</span>'
                : ''}
          </div>
        </div>

        <!-- Medicine Name & DCI -->
        <h4 class="text-base font-bold text-slate-900 group-hover:text-emerald-700 transition-colors line-clamp-1" title="${escapeHtml(m.NOM_DE_MARQUE || '')}">
          ${escapeHtml(m.NOM_DE_MARQUE || 'Sans nom')}
        </h4>
        <p class="text-xs font-medium text-emerald-700 line-clamp-1 mt-0.5" title="${escapeHtml(m.DENOMINATION_COMMUNE_INTERNATIONALE || '')}">
          ${escapeHtml(m.DENOMINATION_COMMUNE_INTERNATIONALE || 'Non spécifié')}
        </p>

        <!-- Galenic Specs -->
        <div class="mt-3 grid grid-cols-2 gap-2 text-[11px] bg-slate-50/70 p-2.5 rounded-lg border border-slate-100">
          <div>
            <span class="text-slate-400 block text-[10px]">Forme & Dosage</span>
            <span class="font-semibold text-slate-700 truncate block">${escapeHtml(m.FORME || '-')} ${escapeHtml(m.DOSAGE || '')}</span>
          </div>
          <div>
            <span class="text-slate-400 block text-[10px]">Conditionnement</span>
            <span class="font-semibold text-slate-700 truncate block">${escapeHtml(m.COND || '-')}</span>
          </div>
          <div class="col-span-2">
            <span class="text-slate-400 block text-[10px]">Laboratoire</span>
            <span class="text-slate-700 truncate block font-medium">${escapeHtml(m.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT || '-')} (${escapeHtml(m.PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT || 'DZ')})</span>
          </div>
        </div>
      </div>

      <!-- Price & Actions footer -->
      <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
        <div>
          <span class="text-[10px] text-slate-400 block">Prix public</span>
          <span class="text-sm font-bold text-slate-900">${escapeHtml(m.PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT || 'N/C')}</span>
        </div>

        <div class="flex items-center space-x-1">
          <!-- View details -->
          <button onclick="openDetailModal(${m.ID})" class="p-1.5 text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition" title="Consulter la fiche complète">
            <i data-lucide="eye" class="w-4 h-4"></i>
          </button>
          <!-- Equivalents -->
          <button onclick="findEquivalents('${escapeAttr(m.DENOMINATION_COMMUNE_INTERNATIONALE || '')}', '${escapeAttr(m.DOSAGE || '')}', ${m.ID})" class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Génériques équivalents">
            <i data-lucide="repeat" class="w-4 h-4"></i>
          </button>
          <!-- Add to prescription cart -->
          <button onclick="addToCart(${m.ID})" class="p-1.5 text-emerald-600 hover:text-emerald-800 hover:bg-emerald-100/70 rounded-lg transition" title="Ajouter à l'ordonnance">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
          </button>
          <!-- Edit -->
          <button onclick="openEditModal(${m.ID})" class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition" title="Modifier">
            <i data-lucide="edit-3" class="w-4 h-4"></i>
          </button>
          <!-- Delete -->
          <button onclick="deleteMedicine(${m.ID})" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Supprimer">
            <i data-lucide="trash-2" class="w-4 h-4"></i>
          </button>
        </div>
      </div>
    </div>
  `;
}

function renderTable(items, container) {
  const html = `
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
          <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold">
            <tr>
              <th class="px-4 py-3">Médicament</th>
              <th class="px-3 py-3">DCI (Molécule)</th>
              <th class="px-3 py-3">Forme / Dosage</th>
              <th class="px-3 py-3">Laboratoire</th>
              <th class="px-3 py-3">Prix</th>
              <th class="px-3 py-3">Chifa</th>
              <th class="px-4 py-3 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-slate-700">
            ${items.map(m => {
              const isRemb = m.REMBOURSEMENT && m.REMBOURSEMENT.toUpperCase().includes('OUI');
              return `
                <tr class="hover:bg-slate-50/80 transition-colors">
                  <td class="px-4 py-3">
                    <span class="font-bold text-slate-900 block">${escapeHtml(m.NOM_DE_MARQUE || '-')}</span>
                    <span class="text-[10px] text-slate-400 font-mono">${escapeHtml(m.CODE || '')}</span>
                  </td>
                  <td class="px-3 py-3 max-w-[200px] truncate text-emerald-700 font-medium" title="${escapeHtml(m.DENOMINATION_COMMUNE_INTERNATIONALE || '')}">
                    ${escapeHtml(m.DENOMINATION_COMMUNE_INTERNATIONALE || '-')}
                  </td>
                  <td class="px-3 py-3 whitespace-nowrap">
                    <div>${escapeHtml(m.FORME || '')}</div>
                    <div class="text-[10px] text-slate-400">${escapeHtml(m.DOSAGE || '')} (${escapeHtml(m.COND || '')})</div>
                  </td>
                  <td class="px-3 py-3 max-w-[150px] truncate" title="${escapeHtml(m.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT || '')}">
                    ${escapeHtml(m.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT || '-')}
                  </td>
                  <td class="px-3 py-3 font-semibold text-slate-900 whitespace-nowrap">
                    ${escapeHtml(m.PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT || '-')}
                  </td>
                  <td class="px-3 py-3 whitespace-nowrap">
                    ${isRemb 
                      ? '<span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-semibold">OUI</span>' 
                      : '<span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-400 text-[10px]">NON</span>'}
                  </td>
                  <td class="px-4 py-3 text-right whitespace-nowrap space-x-1">
                    <button onclick="openDetailModal(${m.ID})" class="p-1 text-slate-400 hover:text-emerald-600 rounded" title="Détails">
                      <i data-lucide="eye" class="w-4 h-4"></i>
                    </button>
                    <button onclick="addToCart(${m.ID})" class="p-1 text-emerald-600 hover:text-emerald-800 rounded" title="Ajouter ordonnance">
                      <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    </button>
                    <button onclick="openEditModal(${m.ID})" class="p-1 text-slate-400 hover:text-amber-600 rounded" title="Modifier">
                      <i data-lucide="edit-3" class="w-4 h-4"></i>
                    </button>
                    <button onclick="deleteMedicine(${m.ID})" class="p-1 text-slate-400 hover:text-rose-600 rounded" title="Supprimer">
                      <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                  </td>
                </tr>
              `;
            }).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `;
  container.innerHTML = html;
}

// Pagination controls
function updatePagination(total, page, totalPages) {
  document.getElementById('currentPageNum').textContent = page;
  document.getElementById('totalPagesNum').textContent = totalPages;
  document.getElementById('prevPageBtn').disabled = page <= 1;
  document.getElementById('nextPageBtn').disabled = page >= totalPages;
}

function changePage(delta) {
  state.page += delta;
  loadMedicines();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function onLimitChange() {
  state.limit = parseInt(document.getElementById('limitSelect').value) || 24;
  state.page = 1;
  loadMedicines();
}

// Search debounce
let searchTimeout = null;
const searchInput = document.getElementById('searchInput');
searchInput.addEventListener('input', (e) => {
  const val = e.target.value;
  document.getElementById('clearSearchBtn').classList.toggle('hidden', !val);

  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    state.search = val;
    state.page = 1;
    loadMedicines();
  }, 350);
});

function clearSearch() {
  searchInput.value = '';
  document.getElementById('clearSearchBtn').classList.add('hidden');
  state.search = '';
  state.page = 1;
  loadMedicines();
}

// Filter changes
function onFilterChange() {
  state.reimbursement = document.getElementById('filterReimbursement').value;
  state.liste = document.getElementById('filterListe').value;
  state.form = document.getElementById('filterForm').value;
  state.country = document.getElementById('filterCountry').value;

  const sortSelect = document.getElementById('sortBy');
  const selectedOpt = sortSelect.options[sortSelect.selectedIndex];
  state.sortBy = sortSelect.value;
  state.sortOrder = selectedOpt.getAttribute('data-order') || 'asc';

  state.page = 1;
  loadMedicines();
}

function resetFilters() {
  document.getElementById('searchInput').value = '';
  document.getElementById('clearSearchBtn').classList.add('hidden');
  document.getElementById('filterReimbursement').value = '';
  document.getElementById('filterListe').value = '';
  document.getElementById('filterForm').value = '';
  document.getElementById('filterCountry').value = '';
  document.getElementById('sortBy').selectedIndex = 0;

  state.search = '';
  state.reimbursement = '';
  state.liste = '';
  state.form = '';
  state.country = '';
  state.sortBy = 'NOM_DE_MARQUE';
  state.sortOrder = 'asc';
  state.page = 1;

  loadMedicines();
}

function setViewMode(mode) {
  state.viewMode = mode;
  const gridBtn = document.getElementById('viewGridBtn');
  const tableBtn = document.getElementById('viewTableBtn');

  if (mode === 'grid') {
    gridBtn.className = 'p-1 rounded-md text-emerald-600 bg-white shadow-2xs transition';
    tableBtn.className = 'p-1 rounded-md text-slate-500 hover:text-slate-800 transition';
  } else {
    tableBtn.className = 'p-1 rounded-md text-emerald-600 bg-white shadow-2xs transition';
    gridBtn.className = 'p-1 rounded-md text-slate-500 hover:text-slate-800 transition';
  }

  loadMedicines();
}

// ==================== DETAIL MODAL ====================
async function openDetailModal(id) {
  try {
    let m;
    try {
      const res = await fetch(`/api/medicines/${id}`);
      if (res.ok) m = await res.json();
    } catch (e) {}

    if (!m) {
      const all = await getStaticMedicines();
      m = all.find(item => item.ID === id);
    }

    if (!m) throw new Error('Médicament introuvable');
    state.currentDetailItem = m;

    document.getElementById('modalBrandName').textContent = m.NOM_DE_MARQUE || 'Sans nom';
    document.getElementById('modalDci').textContent = m.DENOMINATION_COMMUNE_INTERNATIONALE || 'Non spécifié';
    document.getElementById('modalForme').textContent = m.FORME || '-';
    document.getElementById('modalDosage').textContent = m.DOSAGE || '-';
    document.getElementById('modalCond').textContent = m.COND || '-';
    document.getElementById('modalPrice').textContent = m.PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT || 'Non renseigné';
    document.getElementById('modalCode').textContent = m.CODE || '-';
    document.getElementById('modalStabilite').textContent = m.DUREE_DE_STABILITE || '-';
    document.getElementById('modalLab').textContent = m.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT || '-';
    document.getElementById('modalCountry').textContent = m.PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT || '-';
    document.getElementById('modalNumEnreg').textContent = m.NUM_ENREGISTREMENT || '-';
    document.getElementById('modalDateInit').textContent = m.DATE_DENREGISTREMENT_INITIAL || '-';

    const dispParts = [];
    if (m.P1) dispParts.push(`P1: ${m.P1}`);
    if (m.P2) dispParts.push(`P2: ${m.P2}`);
    document.getElementById('modalDisp').textContent = dispParts.join(' / ') || 'Officine / Hôpital';

    // Badges
    const typeBadge = document.getElementById('modalTypeBadge');
    typeBadge.textContent = m.TYPE && m.TYPE.toLowerCase().startsWith('ge') ? 'Générique (Ge)' : 'Princeps (Re)';

    const listeBadge = document.getElementById('modalListeBadge');
    listeBadge.textContent = m.LISTE || 'Hors Liste';

    const rembBadge = document.getElementById('modalRemboursementBadge');
    const isRemb = m.REMBOURSEMENT && m.REMBOURSEMENT.toUpperCase().includes('OUI');
    rembBadge.textContent = isRemb ? 'Remboursable Chifa' : 'Non Remboursable';
    rembBadge.className = isRemb 
      ? 'px-2 py-0.5 rounded text-[11px] font-semibold bg-teal-100 text-teal-800' 
      : 'px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-500';

    // Hook buttons
    document.getElementById('modalFindEquivBtn').onclick = () => {
      closeDetailModal();
      findEquivalents(m.DENOMINATION_COMMUNE_INTERNATIONALE, m.DOSAGE, m.ID);
    };

    document.getElementById('modalAddToCartBtn').onclick = () => {
      addToCart(m.ID);
    };

    document.getElementById('detailModal').classList.remove('hidden');
    refreshIcons();
  } catch (err) {
    showToast('Erreur chargement détails: ' + err.message, 'error');
  }
}

function closeDetailModal() {
  document.getElementById('detailModal').classList.add('hidden');
}

// ==================== EQUIVALENTS FINDER ====================
async function findEquivalents(dci, dosage, excludeId) {
  if (!dci) {
    showToast('Aucune DCI spécifiée pour ce médicament.', 'error');
    return;
  }

  const container = document.getElementById('equivListContainer');
  document.getElementById('equivSubstanceTitle').textContent = `DCI : ${dci}`;
  container.innerHTML = `
    <div class="py-10 text-center text-slate-400">
      <div class="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
      <p class="text-xs">Recherche des génériques équivalents...</p>
    </div>
  `;
  document.getElementById('equivModal').classList.remove('hidden');
  refreshIcons();

  try {
    let data;
    try {
      const params = new URLSearchParams({ dci, dosage: dosage || '', excludeId });
      const res = await fetch(`/api/medicines/equivalents?${params.toString()}`);
      if (res.ok) data = await res.json();
    } catch (e) {}

    if (!data) {
      const all = await getStaticMedicines();
      const dciLower = dci.toLowerCase();
      const dosageClean = (dosage || '').replace(/\s/g, '').toLowerCase();

      const matches = all.filter(m => {
        if (m.ID === excludeId) return false;
        if (!m.DENOMINATION_COMMUNE_INTERNATIONALE) return false;
        const matchDci = m.DENOMINATION_COMMUNE_INTERNATIONALE.toLowerCase().includes(dciLower) ||
                         dciLower.includes(m.DENOMINATION_COMMUNE_INTERNATIONALE.toLowerCase());
        if (!matchDci) return false;

        if (dosageClean && m.DOSAGE) {
          const d2 = m.DOSAGE.replace(/\s/g, '').toLowerCase();
          return dosageClean === d2 || d2.includes(dosageClean) || dosageClean.includes(d2);
        }
        return true;
      });

      data = {
        count: matches.length,
        equivalents: matches.slice(0, 50)
      };
    }

    if (!data.equivalents || data.equivalents.length === 0) {
      container.innerHTML = `
        <div class="p-8 text-center text-slate-500">
          <p class="font-medium text-sm">Aucun autre équivalent répertorié pour cette molécule.</p>
          <p class="text-xs text-slate-400 mt-1">Ce produit est soit unique sur le marché algérien, soit sous brevet exclusif.</p>
        </div>
      `;
      return;
    }

    container.innerHTML = `
      <p class="text-xs font-semibold text-slate-500 mb-2">${data.equivalents.length} médicament(s) équivalent(s) disponible(s) :</p>
      <div class="divide-y divide-slate-100 border border-slate-100 rounded-xl overflow-hidden">
        ${data.equivalents.map(eq => `
          <div class="p-3 bg-white hover:bg-slate-50 flex items-center justify-between text-xs transition">
            <div>
              <div class="flex items-center space-x-2">
                <span class="font-bold text-slate-900">${escapeHtml(eq.NOM_DE_MARQUE || '')}</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">${escapeHtml(eq.TYPE || 'Ge')}</span>
              </div>
              <div class="text-slate-500 text-[11px] mt-0.5">
                ${escapeHtml(eq.FORME || '')} - ${escapeHtml(eq.DOSAGE || '')} (${escapeHtml(eq.COND || '')})
              </div>
              <div class="text-slate-400 text-[10px]">
                Laboratoire : ${escapeHtml(eq.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT || 'N/A')}
              </div>
            </div>
            <div class="text-right flex items-center space-x-3">
              <div>
                <span class="font-bold text-emerald-700 block">${escapeHtml(eq.PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT || '-')}</span>
                <span class="text-[10px] text-slate-400">${eq.REMBOURSEMENT === 'OUI' ? 'Remboursable' : 'Non remb.'}</span>
              </div>
              <button onclick="addToCart(${eq.ID})" class="p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-lg" title="Ajouter à l'ordonnance">
                <i data-lucide="plus" class="w-4 h-4"></i>
              </button>
            </div>
          </div>
        `).join('')}
      </div>
    `;
    refreshIcons();
  } catch (err) {
    container.innerHTML = `<div class="p-4 text-xs text-rose-600">Erreur : ${err.message}</div>`;
  }
}

function closeEquivModal() {
  document.getElementById('equivModal').classList.add('hidden');
}

// ==================== PRESCRIPTION CART ====================
async function addToCart(id) {
  try {
    const res = await fetch(`/api/medicines/${id}`);
    if (!res.ok) throw new Error('Médicament non trouvé');
    const medicine = await res.json();

    const existing = state.cart.find(c => c.medicine.ID === id);
    if (existing) {
      existing.quantity += 1;
    } else {
      state.cart.push({ medicine, quantity: 1 });
    }

    updateCartUI();
    showToast(`"${medicine.NOM_DE_MARQUE}" ajouté à l'ordonnance.`, 'success');
  } catch (err) {
    showToast(err.message, 'error');
  }
}

function updateCartUI() {
  const badge = document.getElementById('cartCountBadge');
  const count = state.cart.reduce((acc, item) => acc + item.quantity, 0);

  if (count > 0) {
    badge.textContent = count;
    badge.classList.remove('hidden');
  } else {
    badge.classList.add('hidden');
  }

  renderCartItems();
}

function parseDA(priceStr) {
  if (!priceStr) return 0;
  const match = priceStr.match(/([\d\s,.]+)\s*DA/i);
  if (match) {
    const clean = match[1].replace(/\s/g, '').replace(',', '.');
    return parseFloat(clean) || 0;
  }
  return 0;
}

function renderCartItems() {
  const container = document.getElementById('cartItemsContainer');
  if (state.cart.length === 0) {
    container.innerHTML = `
      <div class="py-12 text-center text-slate-400">
        <i data-lucide="shopping-bag" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
        <p class="text-sm font-medium">L'ordonnance est actuellement vide</p>
        <p class="text-xs text-slate-400 mt-1">Ajoutez des médicaments depuis le catalogue pour calculer le prix et la prise en charge Chifa.</p>
      </div>
    `;
    document.getElementById('cartTotalPrice').textContent = '0.00 DA';
    document.getElementById('cartReimbursableTotal').textContent = '0.00 DA';
    document.getElementById('cartPatientShare').textContent = '0.00 DA';
    refreshIcons();
    return;
  }

  let total = 0;
  let remboursableTotal = 0;

  const html = state.cart.map((item, idx) => {
    const m = item.medicine;
    const unitPrice = parseDA(m.PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT);
    const linePrice = unitPrice * item.quantity;
    total += linePrice;

    const isRemb = m.REMBOURSEMENT && m.REMBOURSEMENT.toUpperCase().includes('OUI');
    if (isRemb) {
      remboursableTotal += linePrice;
    }

    return `
      <div class="p-3 bg-white border border-slate-200 rounded-xl flex items-center justify-between text-xs">
        <div class="flex-1 pr-3">
          <div class="flex items-center space-x-2">
            <span class="font-bold text-slate-900">${escapeHtml(m.NOM_DE_MARQUE)}</span>
            ${isRemb ? '<span class="text-[9px] px-1.5 py-0.2 rounded bg-teal-100 text-teal-800 font-bold">Chifa</span>' : ''}
          </div>
          <p class="text-slate-500 text-[11px] truncate">${escapeHtml(m.DENOMINATION_COMMUNE_INTERNATIONALE || '')}</p>
          <span class="text-slate-400 text-[10px]">${unitPrice > 0 ? unitPrice.toFixed(2) + ' DA' : 'Prix libre'}</span>
        </div>

        <div class="flex items-center space-x-3">
          <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden">
            <button onclick="changeCartQty(${idx}, -1)" class="px-2 py-1 bg-slate-50 hover:bg-slate-100 text-slate-600">-</button>
            <span class="px-2.5 py-1 font-bold text-slate-800">${item.quantity}</span>
            <button onclick="changeCartQty(${idx}, 1)" class="px-2 py-1 bg-slate-50 hover:bg-slate-100 text-slate-600">+</button>
          </div>
          <div class="w-20 text-right font-bold text-slate-900">
            ${linePrice.toFixed(2)} DA
          </div>
          <button onclick="removeCartItem(${idx})" class="p-1 text-slate-400 hover:text-rose-600 rounded">
            <i data-lucide="trash-2" class="w-4 h-4"></i>
          </button>
        </div>
      </div>
    `;
  }).join('');

  container.innerHTML = html;

  // Patient pays 20% ticket modérateur for reimbursable items + 100% of non-reimbursable items
  const patientShare = (remboursableTotal * 0.20) + (total - remboursableTotal);

  document.getElementById('cartTotalPrice').textContent = `${total.toFixed(2)} DA`;
  document.getElementById('cartReimbursableTotal').textContent = `${remboursableTotal.toFixed(2)} DA`;
  document.getElementById('cartPatientShare').textContent = `${patientShare.toFixed(2)} DA`;

  refreshIcons();
}

function changeCartQty(index, delta) {
  state.cart[index].quantity += delta;
  if (state.cart[index].quantity <= 0) {
    state.cart.splice(index, 1);
  }
  updateCartUI();
}

function removeCartItem(index) {
  state.cart.splice(index, 1);
  updateCartUI();
}

function clearCart() {
  state.cart = [];
  updateCartUI();
}

function toggleCartModal() {
  const modal = document.getElementById('cartModal');
  modal.classList.toggle('hidden');
  if (!modal.classList.contains('hidden')) {
    renderCartItems();
  }
}

// ==================== CRUD OPERATIONS ====================
function openCreateModal() {
  document.getElementById('formModalTitle').textContent = 'Ajouter un Nouveau Médicament';
  document.getElementById('medicineForm').reset();
  document.getElementById('formId').value = '';
  document.getElementById('formCountry').value = 'ALGERIE';
  document.getElementById('formRemboursement').value = 'OUI';
  document.getElementById('formType').value = 'Ge';
  document.getElementById('formModal').classList.remove('hidden');
}

async function openEditModal(id) {
  try {
    const res = await fetch(`/api/medicines/${id}`);
    if (!res.ok) throw new Error('Introuvable');
    const m = await res.json();

    document.getElementById('formModalTitle').textContent = `Modifier : ${m.NOM_DE_MARQUE || ''}`;
    document.getElementById('formId').value = m.ID;
    document.getElementById('formBrand').value = m.NOM_DE_MARQUE || '';
    document.getElementById('formDci').value = m.DENOMINATION_COMMUNE_INTERNATIONALE || '';
    document.getElementById('formForme').value = m.FORME || '';
    document.getElementById('formDosage').value = m.DOSAGE || '';
    document.getElementById('formCond').value = m.COND || '';
    document.getElementById('formPrice').value = m.PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT || '';
    document.getElementById('formRemboursement').value = (m.REMBOURSEMENT && m.REMBOURSEMENT.includes('NON')) ? 'NON' : 'OUI';
    document.getElementById('formListe').value = m.LISTE || '';
    document.getElementById('formType').value = (m.TYPE && m.TYPE.toLowerCase().startsWith('re')) ? 'Re' : 'Ge';
    document.getElementById('formCode').value = m.CODE || '';
    document.getElementById('formLab').value = m.LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT || '';
    document.getElementById('formCountry').value = m.PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT || 'ALGERIE';

    document.getElementById('formModal').classList.remove('hidden');
  } catch (err) {
    showToast('Erreur: ' + err.message, 'error');
  }
}

function closeFormModal() {
  document.getElementById('formModal').classList.add('hidden');
}

async function handleFormSubmit(e) {
  e.preventDefault();

  const id = document.getElementById('formId').value;
  const payload = {
    NOM_DE_MARQUE: document.getElementById('formBrand').value.trim(),
    DENOMINATION_COMMUNE_INTERNATIONALE: document.getElementById('formDci').value.trim(),
    FORME: document.getElementById('formForme').value.trim(),
    DOSAGE: document.getElementById('formDosage').value.trim(),
    COND: document.getElementById('formCond').value.trim(),
    PRIX_PORTE_SUR_LA_DECISION_DENREGISTREMENT: document.getElementById('formPrice').value.trim(),
    REMBOURSEMENT: document.getElementById('formRemboursement').value,
    LISTE: document.getElementById('formListe').value,
    TYPE: document.getElementById('formType').value,
    CODE: document.getElementById('formCode').value.trim(),
    LABORATOIRES_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT: document.getElementById('formLab').value.trim(),
    PAYS_DU_LABORATOIRE_DETENTEUR_DE_LA_DECISION_DENREGISTREMENT: document.getElementById('formCountry').value.trim()
  };

  const isEdit = Boolean(id);
  const endpoint = isEdit ? `/api/medicines/${id}` : '/api/medicines';
  const method = isEdit ? 'PUT' : 'POST';

  try {
    const res = await fetch(endpoint, {
      method,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Erreur lors de la sauvegarde');

    closeFormModal();
    showToast(isEdit ? 'Médicament mis à jour avec succès !' : 'Nouveau médicament enregistré !', 'success');
    loadMedicines();
    loadStats();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

async function deleteMedicine(id) {
  if (!confirm(`Êtes-vous sûr de vouloir supprimer définitivement le médicament #${id} ?`)) {
    return;
  }

  try {
    const res = await fetch(`/api/medicines/${id}`, { method: 'DELETE' });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Erreur de suppression');

    showToast('Médicament supprimé avec succès.', 'success');
    loadMedicines();
    loadStats();
  } catch (err) {
    showToast(err.message, 'error');
  }
}

// Helpers
function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function escapeAttr(str) {
  if (!str) return '';
  return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// Global keyboard escape listener
window.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeDetailModal();
    closeEquivModal();
    closeFormModal();
    document.getElementById('cartModal').classList.add('hidden');
  }
});

// App startup
document.addEventListener('DOMContentLoaded', () => {
  loadStats();
  loadMedicines();
});
