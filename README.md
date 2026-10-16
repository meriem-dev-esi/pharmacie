# 💊 Pharmacie DZ - Registre National des Médicaments

Application web moderne, rapide et autonome pour la consultation, la recherche et la gestion de la nomenclature officielle des médicaments d'Algérie (base de **4 627 médicaments**).

---

## 🚀 Démarrage Rapide

Cette application utilise uniquement les modules natifs de **Node.js** (aucune installation `npm install` requise).

### Option 1 : Double-clic (Windows)
Double-cliquez simplement sur le fichier **`demarrer.bat`**.

### Option 2 : En ligne de commande
```bash
node server.js
```
Puis ouvrez votre navigateur sur : **[http://localhost:3000](http://localhost:3000)**

---

## ✨ Fonctionnalités Clés

1. **Recherche Instantanée & Multi-critères :**
   - Recherche en temps réel par :
     - **Nom commercial** (ex : *AMOXIL*, *DOLIPRANE*, *AUGMENTIN*)
     - **DCI / Molécule active** (ex : *PARACETAMOL*, *AMOXICILLINE*)
     - **Code produit national**
     - **Nom du laboratoire** (ex : *SAIDAL*, *EL KENDI*, *BIOCARE*)

2. **Filtres Avancés :**
   - **Remboursement Chifa :** Filtrer les médicaments remboursables (*OUI*) ou non (*NON*).
   - **Tableaux / Listes :** Liste I (ordonnance obligatoire), Liste II.
   - **Formes galéniques :** Comprimé, Gélule, Sirop, Solution, Pommade, Injectable...
   - **Origine :** Algérie, France, Jordanie, Inde, etc.
   - **Trier :** Par Nom (A-Z / Z-A), Prix croissant / décroissant, ID.

3. **Générateur d'Équivalents Thérapeutiques :**
   - Sur n'importe quel médicament, cliquez sur le bouton **Équivalents** pour afficher instantanément tous les médicaments partageant la même molécule active (DCI) et le même dosage, avec comparateur de prix direct.

4. **Calculateur d'Ordonnance & Remboursement :**
   - Ajoutez des médicaments à l'ordonnance en un clic.
   - Calcul automatique :
     - Montant total en Dinars Algériens (DA).
     - Montant remboursable par la sécurité sociale (carte Chifa).
     - Ticket modérateur estimé (reste à charge du patient).
   - Impression directe de l'ordonnance au format papier.

5. **Gestion Complète (CRUD) :**
   - **Ajouter un médicament :** Formulaire complet avec validation.
   - **Modifier :** Mise à jour en direct des fiches existantes.
   - **Supprimer :** Suppression sécurisée avec confirmation.
   - **Exporter en CSV :** Téléchargement compatible Excel (avec BOM UTF-8).

---

## 📁 Structure du Projet

```text
pharmacie/
├── public/                  # Interface utilisateur Web moderne
│   ├── index.html           # Page principale responsive (Tailwind CSS, Lucide Icons)
│   ├── app.js               # Logique applicative (recherche, filtres, panier, équivalents)
│   └── style.css            # Styles personnalisés et feuille d'impression
├── server.js                # Serveur HTTP & API REST native Node.js
├── parse_sql.js             # Script d'extraction et parsing de pharmacie.sql
├── medicines.json           # Base de données structurée (4 627 médicaments)
├── pharmacie.sql            # Fichier SQL original de la nomenclature
├── demarrer.bat             # Lanceur Windows en 1 clic
└── package.json             # Métadonnées et scripts npm
```
