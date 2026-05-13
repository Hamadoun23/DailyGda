# Documentation technique — GDA (dailygda)

Application **Laravel** orientée gestion de chantier : projets, phases, sous-phases, tâches, saisies quotidiennes, photos par catégorie et rapports (aperçu HTML + PDF DomPDF). L’interface principale est une **SPA légère** : pages servies par Blade, logique et appels API dans `public/js/gda-app.js`, styles dans `public/css/gda.css`.

---

## Structure du dépôt (vue d’ensemble)

| Zone | Rôle |
|------|------|
| `app/Http/Controllers/Api/` | Contrôleurs REST pour le front et le PDF |
| `app/Http/Controllers/Concerns/ResolvesProject.php` | Résolution du projet courant (header / query) + contrôle d’accès |
| `app/Http/Middleware/` | Dont `EnsureApiActor` (utilisateur Sanctum ou utilisateur par défaut) |
| `app/Models/` | Modèles Eloquent alignés sur les tables métier |
| `app/Support/` | Helpers métier (`ReportPresentation`, `GdaStatus`, etc.) |
| `database/migrations/` | Schéma SQL versionné |
| `database/seeders/` | Données de démarrage (si présents) |
| `routes/web.php` | Pages HTML (`/`, `/login`, `/projets`) |
| `routes/api.php` | Préfixe `/api` : login + routes protégées par middleware |
| `resources/views/` | Layouts Blade et partials chantier / auth / PDF |
| `public/js/` | `gda-app.js`, `report-structure-en.js` (libellés anglais côté client) |
| `public/css/gda.css` | Thème et composants UI + styles aperçu rapport |
| `resources/data/` | Données JSON de structure / traduction si utilisées |
| `storage/app/public/` | Fichiers uploadés (photos) ; lien symbolique `public/storage` |

---

## Base de données (schéma logique)

Le métier s’articule autour du **projet** et d’une hiérarchie **phase → sous-phase → tâche**. Les **progressions** et **statuts** au jour le jour sont portés par les **mises à jour quotidiennes** (`daily_updates`), pas par une colonne `progress` permanente sur `tasks`.

### Tables principales

- **`users`** — Utilisateurs Laravel ; champs GDA : `role` (`chef_chantier`, `ingenieur`, `controle_qualite`, `direction`), `avatar_initials`, `is_active`.
- **`personal_access_tokens`** — Jetons **Sanctum** pour l’API (login renvoie un token).
- **`projects`** — Nom, description, client, dates, statut (`planifie`, `en_cours`, `termine`, `suspendu`).
- **`project_user`** — Pivot **many-to-many** : quels utilisateurs sont affectés à quels projets (hors rôle « direction » qui peut tout voir selon la logique du trait `ResolvesProject`).
- **`phases`** — Phases d’un projet (`project_id`, `name`, `sort_order`).
- **`sub_phases`** — Sous-phases rattachées à une phase (`phase_id`, `name`, `sort_order`). Introduites après la migration initiale des tâches.
- **`tasks`** — Après migration : `sub_phase_id`, activité, `start_day`, `duration_days`, ordre, champs optionnels (responsable, ressources, risques selon évolution du schéma). La liaison directe `phase_id` + colonne texte `subphase` a été remplacée par `sub_phase_id`.
- **`daily_updates`** — Une ligne par **(tâche, date de rapport)** : `progress` (0–100), `status` (`non_demarre`, `en_cours`, `termine`, `annule`), commentaire, `user_id`. Contrainte d’unicité sur `(task_id, report_date)`.
- **`photos`** — Fichiers du projet : `category` (`avant`, `pendant`, `apres`, `securite`, `qualite`), chemin disque, métadonnées.
- **`reports`** — Rapports générés : date, température, météo, pagination affichée, progression globale, notes, horodatage de génération.

### Tables Laravel standard

- **`cache`**, **`jobs`**, **`job_batches`**, **`failed_jobs`** (selon migrations `0001_01_01_*`) — infrastructure queue / cache si activée.

---

## Migrations (`database/migrations/`)

Les fichiers sont exécutés dans l’ordre chronologique des timestamps ; l’essentiel du domaine GDA est en `2026_05_04_*` et `2026_05_11_*`.

| Fichier | Contenu |
|---------|---------|
| `0001_01_01_000000_create_users_table.php` | Table `users` de base |
| `0001_01_01_000001_create_cache_table.php` | Cache |
| `0001_01_01_000002_create_jobs_table.php` | Files d’attente |
| `2026_05_04_110509_create_personal_access_tokens_table.php` | Sanctum |
| `2026_05_04_120000_add_gda_fields_to_users_table.php` | Rôle et champs GDA sur `users` |
| `2026_05_04_120001_create_projects_table.php` | `projects` |
| `2026_05_04_120002_create_project_user_table.php` | Pivot projet ↔ utilisateur |
| `2026_05_04_120003_create_phases_table.php` | `phases` |
| `2026_05_04_120004_create_tasks_table.php` | Création initiale de `tasks` (avec `phase_id` / `subphase`) |
| `2026_05_04_120005_create_daily_updates_table.php` | `daily_updates` |
| `2026_05_04_120006_create_photos_table.php` | `photos` |
| `2026_05_04_120007_create_reports_table.php` | `reports` |
| `2026_05_11_140000_create_sub_phases_and_relink_tasks.php` | Crée `sub_phases`, migre les données, ajoute `sub_phase_id`, supprime `phase_id` / `subphase` sur `tasks` (down non supporté) |
| `2026_05_12_120000_replace_bloque_with_annule_on_daily_updates.php` | Évolution de l’énumération de statut (bloqué → annulé) |

Commandes utiles : `php artisan migrate`, `php artisan migrate:status`, `php artisan storage:link` pour les photos en `public/storage`.

---

## Modèles Eloquent (`app/Models/`)

| Modèle | Relations / notes courtes |
|--------|---------------------------|
| `User` | Projets via `project_user` ; méthodes de rôle (`isDirection()`, etc.) |
| `Project` | Phases, photos, rapports, utilisateurs |
| `Phase` | Projet, sous-phases |
| `SubPhase` | Phase, tâches |
| `Task` | `subPhase`, `dailyUpdates`, scopes du type `forProject` |
| `DailyUpdate` | Tâche, utilisateur, date |
| `Photo` | Projet, utilisateur ; accesseur d’URL vers `storage/...` |
| `Report` | Projet, utilisateur |

---

## API (`routes/api.php`)

- Préfixe **`/api`** (config Laravel par défaut).
- **`POST /api/login`** — Authentification (hors groupe middleware `EnsureApiActor` pour permettre l’obtention du token).
- Toutes les routes ci-dessous passent par **`EnsureApiActor`** : si un Bearer Sanctum valide est présent, l’utilisateur est celui du token ; sinon un **utilisateur par défaut** (premier en base) est injecté (mode développement / démo documenté dans le middleware).

### Résolution du projet actif

Beaucoup de contrôleurs utilisent le trait **`ResolvesProject`** : le projet courant est déterminé par le header **`X-Project-Id`** ou le query param **`project_id`**, sinon le **premier projet** par `id`. Les utilisateurs non-direction doivent appartenir au projet (pivot `project_user`).

### Liste des endpoints (résumé)

| Méthode | Chemin | Contrôleur |
|---------|--------|------------|
| GET | `/whoami` | `AuthController` |
| POST | `/logout` | `AuthController` |
| GET | `/projects` | `ProjectController@index` |
| POST | `/projects` | `ProjectController@store` |
| PUT | `/projects/{project}` | `ProjectController@update` |
| DELETE | `/projects/{project}` | `ProjectController@destroy` |
| GET | `/projects/{project}/phases` | `PhaseController@index` |
| POST | `/projects/{project}/phases` | `PhaseController@store` |
| PUT | `/phases/{phase}` | `PhaseController@update` |
| DELETE | `/phases/{phase}` | `PhaseController@destroy` |
| POST | `/phases/{phase}/sub-phases` | `SubPhaseController@store` |
| PUT | `/sub-phases/{subPhase}` | `SubPhaseController@update` |
| DELETE | `/sub-phases/{subPhase}` | `SubPhaseController@destroy` |
| GET | `/project` | `ProjectController@show` (projet résolu) |
| GET | `/dashboard` | `DashboardController@index` |
| GET/POST/PUT | `/tasks`, `/tasks/{task}`, etc. | `TaskController` |
| GET/POST/PUT | `/daily`, `/daily/{daily}`, `/daily/batch` | `DailyUpdateController` |
| GET/POST/DELETE | `/photos`, `/photos/{photo}` | `PhotoController` |
| GET | `/reports` | `ReportController@index` |
| POST | `/reports/generate` | `ReportController@generate` |
| GET | `/reports/{report}/pdf` | `ReportController@pdf` (DomPDF, locale possible) |

Les contrôleurs sont dans **`App\Http\Controllers\Api\`**, à l’exception de `Controller.php` (classe de base).

---

## Vues Blade (`resources/views/`)

- **`layouts/gda.blade.php`** — Layout principal (titre, favicon, stack `@push('scripts')`, slot `@yield('content')`).
- **`partials/gda-header.blade.php`** — Barre supérieure (logo, libellé projet, navigation).
- **`chantier/index.blade.php`** — Page d’accueil chantier : étend le layout, inclut header, sidebar, overlays, `main-pages`, configure `window.GDA_API_BASE` et charge `gda-app.js`.
- **`chantier/partials/sidebar.blade.php`** — Navigation latérale, sélecteur de projet, progression, pied de page.
- **`chantier/partials/main-pages.blade.php`** — Zones « pages » (tableau de bord, saisie du jour, tâches, photos, rapport).
- **`chantier/partials/overlays.blade.php`** — Modales (ex. langue du rapport).
- **`chantier/partials/header.blade.php`** — Variante header si utilisée.
- **`auth/login.blade.php`** + **`auth/partials/login-card.blade.php`** — Connexion.
- **`projects/index.blade.php`** — Écran liste / gestion des projets (même stack JS que le chantier si applicable).
- **`reports/pdf.blade.php`** — HTML/CSS dédiés **DomPDF** (tableau des tâches, pied de page progression, pages photos par section).

Le rendu « application » repose fortement sur **`gda-app.js`** qui manipule le DOM des partials et consomme l’API.

---

## Fichiers complémentaires utiles

- **`app/Support/ReportPresentation.php`** — Textes du rapport, formats de dates, libellés de catégories photos selon la locale.
- **`public/js/report-structure-en.js`** — Structure / traductions côté navigateur pour l’aperçu anglais.
- **`README.md`** — Instructions projet (installation, commandes) si présentes.

---

## Résumé du flux typique

1. L’utilisateur ouvre **`/`** → vue `chantier.index` → JS charge le catalogue projets, le projet courant (`localStorage` + `/api/project`), tâches, dashboard, etc.
2. Les **mises à jour du jour** alimentent `daily_updates` ; le **dashboard** et le **rapport** agrègent tâches + dernières saisies.
3. Les **photos** sont stockées sous `storage/app/public`, servies via **`/storage/...`** après `storage:link`.
4. Le **PDF** est généré côté serveur (`ReportController@pdf`) à partir de données compilées (dont sections photos en base64) et du template `reports/pdf.blade.php`.

Pour toute évolution de schéma : créer une **nouvelle migration**, mettre à jour les **modèles** et les **contrôleurs** concernés, puis adapter **`gda-app.js`** et les vues si les champs exposés à l’API changent.
