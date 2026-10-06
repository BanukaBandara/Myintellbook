# Merge Review Files Guide

When copying work from this transfer package into a newly cloned repository or target branch, **DO NOT blindly overwrite existing files with these shared files**. 

The files listed below contain critical Tribunal system integrations blended with core application architecture, models, routes, and authentication. Overwriting them completely could erase changes or new features introduced on the target repository branch. Instead, perform a side-by-side three-way diff (`git merge`, VS Code diff, or Beyond Compare).

---

## 1. Backend Merge Review Files

### `MIB_backend/routes/api.php`
- **Why Manual Review is Required:**
  - This is the central routing registry for the entire application. Overwriting it will overwrite any non-Tribunal routes added or modified in the target repository.
- **Tribunal Additions to Incorporate:**
  - `Admin\AdminTribunalJuryPanelController` routes (`api/admin/tribunal/jury-panels` CRUD, activate, deactivate).
  - `Tribunal\TribunalHearingController` routes (`hearings`, `entries`, `questions`, `witnesses`, `testimony`).
  - `Tribunal\TribunalDecisionController` routes (`api/tribunal/cases/{case}/decision`).
  - `Tribunal\TribunalJuryController` juror portal routes (`api/tribunal/jury/cases`, recusal, conflict of interest).
  - Shared Case Room and Mediation routes (`api/tribunal/cases/{case}/case-room/...`, `proposals`, `counter`).
- **Merge Strategy:** Copy only the Tribunal route groups and controller imports into the target `routes/api.php`.

---

### `MIB_backend/app/Models/User.php`
- **Why Manual Review is Required:**
  - The core user model contains user authentication, roles, relationships, and scopes. The target repository may have altered columns, fillables, or casts.
- **Tribunal Additions to Incorporate:**
  - `juryPanel()` relation: `hasOne(TribunalJuryPanel::class, 'user_id')`.
  - `tribunalCases()` and `representedCases()` relations.
  - Helper methods: `isJuryPanel()`, `isRepresentativeEligible()`, `activeJuryPanel()`.
- **Merge Strategy:** Merge the relationship methods and helper methods into the target `User.php` without altering destination-specific fillables or hidden fields.

---

### `MIB_backend/app/Models/TribunalCase.php`
- **Why Manual Review is Required:**
  - Central model managing Tribunal dispute resolution state machines.
- **Tribunal Additions to Incorporate:**
  - Relationships: `juryPanelAssignments()`, `activeJuryAssignment()`, `hearings()`, `activeHearing()`, `witnesses()`, `deliberation()`, `decision()`.
  - State machine status enums/scopes: Handling cases progressing through `FILED` -> `ACKNOWLEDGED` -> `RESPONSE_SUBMITTED` -> `JURY_ASSIGNED` -> `HEARING_SCHEDULED` -> `DELIBERATION` -> `DECIDED`.
- **Merge Strategy:** Compare status constants and relationship definitions with any alternative migrations or schemas in the target branch.

---

### `MIB_backend/app/Providers/AppServiceProvider.php`
- **Why Manual Review is Required:**
  - Bootstraps services, observers, rate limiters, and model morph maps for the Laravel application.
- **Tribunal Additions to Incorporate:**
  - Registration of Tribunal-related observers and event listeners.
- **Merge Strategy:** Verify that observer registrations match existing target models.

---

### `MIB_backend/bootstrap/app.php`
- **Why Manual Review is Required:**
  - Laravel 11/12 application bootstrap file defining routing configurations, middleware pipelines, and global exception handlers.
- **Tribunal Additions to Incorporate:**
  - Middleware aliases for role and access control (e.g., ensuring jury panel accounts are authenticated and restricted from standard user features).
- **Merge Strategy:** Check `withMiddleware()` and `withExceptions()` callbacks to preserve target middleware while maintaining jury panel security policies.

---

### `MIB_backend/app/helpers/helpers.php`, `UserService.php`, `ProfileService.php`
- **Why Manual Review is Required:**
  - General utility and user service layers.
- **Tribunal Additions to Incorporate:**
  - Safeguards ensuring jury panel accounts do not appear in public user directories or representative listings (`is_representative_eligible = false`).
- **Merge Strategy:** Cross-reference query filters ensuring jury panel accounts remain excluded from regular user listings.

---

### `MIB_backend/database/seeders/DatabaseSeeder.php`
- **Why Manual Review is Required:**
  - Orchestrates database seeding.
- **Tribunal Additions to Incorporate:**
  - Seeding of jury panel accounts and test Tribunal case structures.
- **Merge Strategy:** Append Tribunal seed calls without removing new domain seeders in the target branch.

---

### `MIB_backend/composer.json` & `composer.lock`
- **Why Manual Review is Required:**
  - The target branch might require newer or different package versions. Overwriting `composer.lock` can cause dependency downgrade or conflicts.
- **Merge Strategy:** Check if any packages (such as `maatwebsite/excel`, `teamtnt/laravel-scout-tntsearch-driver`, `pusher/pusher-php-server`, `laravel/reverb`) need to be merged into the target `composer.json`, then run `composer update --lock`.

---

## 2. Frontend Merge Review Files

### `MIB_frontend/src/router/index.ts`
- **Why Manual Review is Required:**
  - Central Vue router definition. Overwriting this will wipe any new routes created on the target branch.
- **Tribunal Additions to Incorporate:**
  - Route groups:
    - `/admin/jury-panels` (`AdminJuryPanels.vue`)
    - `/jury/dashboard` (`JuryDashboard.vue`)
    - `/jury/cases` (`JuryAssignedCases.vue`)
    - `/jury/cases/:id` (`JuryCaseDetails.vue`)
    - `/tribunal/cases/:id` (`TribunalCaseDetails.vue`) with sub-tabs (`JuryHearingTab`, `JuryDeliberationTab`, `PartyHearingTab`, `PartyDecisionTab`).
  - Navigation guards: `beforeEach` checks for `requiresJuryPanel` role authorization and redirect logic.
- **Merge Strategy:** Integrate the Tribunal routes and guards into the target router configuration.

---

### `MIB_frontend/src/services/auth.ts`
- **Why Manual Review is Required:**
  - Authentication service handling login tokens, roles, and redirects.
- **Tribunal Additions to Incorporate:**
  - Handling of `is_jury_panel` flag in user session and automatic redirect to `/jury/dashboard`.
- **Merge Strategy:** Keep target authentication workflows while preserving the jury panel role detection and routing redirection.

---

### `MIB_frontend/src/services/tribunalService.ts`
- **Why Manual Review is Required:**
  - API communication service for Tribunal features.
- **Tribunal Additions to Incorporate:**
  - Complete client endpoints for Case Room, Evidence, Hearings, Deliberation, Decision, Witnesses, and Jury Panels.
- **Merge Strategy:** If target has an older version, update to this transfer package's version; if target has concurrent edits, merge API endpoint methods.

---

### `MIB_frontend/src/stores/User/userProfile.ts` & `src/stores/juryPanel.ts`
- **Why Manual Review is Required:**
  - State management stores for user session and jury panels.
- **Merge Strategy:** Retain jury panel state methods and flags without altering unrelated user store states.

---

### `MIB_frontend/src/types/tribunal.ts` & Shared Types (`userGeneralInfoType.ts`, `profileListSearch.ts`)
- **Why Manual Review is Required:**
  - TypeScript contracts and interfaces.
- **Tribunal Additions to Incorporate:**
  - Interface definitions for `TribunalCase`, `TribunalHearing`, `TribunalHearingEntry`, `TribunalWitness`, `TribunalDeliberation`, `TribunalDecision`, `TribunalJuryPanel`.
- **Merge Strategy:** Merge interface definitions; do not replace existing target model properties.

---

### `MIB_frontend/src/components/navBar.vue` & `navBarDropDown.vue`, `navBarItems.vue`
- **Why Manual Review is Required:**
  - Navigation UI components.
- **Tribunal Additions to Incorporate:**
  - Dynamic navigation items for Jury Panel portal (when authenticated as a jury panel user) and Tribunal Dispute Resolution navigation links.
- **Merge Strategy:** Merge navigation links cleanly into the target layout.

---

### `MIB_frontend/package.json` & `package-lock.json`
- **Why Manual Review is Required:**
  - Frontend npm dependencies.
- **Merge Strategy:** Compare dependencies (e.g., `lucide-vue-next`, `pinia`, `vue-router`, `axios`), merge required package versions, and run `npm install`.

---

## 3. Safe Merge Order Recommendation

1. **Step 1 - Migrations First:** Copy new Tribunal migrations from `MIB_backend/database/migrations/2026_10_02_*` and run `php artisan migrate`.
2. **Step 2 - Isolated Files:** Copy all self-contained Tribunal controllers, services, models, requests, resources, notifications, and Vue views/components.
3. **Step 3 - Shared Files:** Use diff tools to merge `api.php`, `User.php`, `AppServiceProvider.php`, `router/index.ts`, `auth.ts`, and navigation components.
4. **Step 4 - Dependency Synchronization:** Check `composer.json` and `package.json`, then execute `composer install` and `npm install`.
5. **Step 5 - Automated Test Validation:** Run `php artisan test --filter Tribunal` and `npm run type-check` to guarantee integrity.
