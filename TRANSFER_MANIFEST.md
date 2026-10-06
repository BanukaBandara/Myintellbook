# Myintellibook Clean Transfer Package Manifest

**Generated Date:** 2026-10-07  
**Source Path:** `D:\Projects\Myintellbook_live`  
**Package Export Path:** `D:\Projects\Myintellibook_transfer_package`  
**ZIP Archive:** `D:\Projects\Myintellibook_transfer_package.zip`  
**Status of Original Working Project:** **100% UNTOUCHED, UNMODIFIED, AND PRESERVED**

---

## 1. Project Structure Summary

The Myintellibook application is a full-stack Laravel 12 + Vue 3 / Vite enterprise platform containing an advanced online dispute resolution system ("Tribunal").

The project repository is structured into two main sub-projects plus root configurations:
- **`MIB_backend/`**: Laravel 12 API backend containing database migrations, Eloquent models, API controllers, services, notifications, and PHPUnit test suite.
- **`MIB_frontend/`**: Vue 3 + TypeScript + Vite + Tailwind/Custom CSS frontend containing pinia stores, routing, views, modal components, and Cypress E2E tests.
- **Root Files**: Workspace documentation and tooling (`README.md`).

---

## 2. Included Folders & Files Summary

A total of **637 clean source files** (~35.49 MB) have been transferred to the package:

| Directory / Component | File Count | Primary Contents |
| :--- | :---: | :--- |
| `MIB_backend/app/` | 134 | Controllers, Models, Services, Notifications, Requests, Resources, Enums, Observers, Providers |
| `MIB_backend/bootstrap/` | 2 | `app.php`, `cache/.gitignore` |
| `MIB_backend/config/` | 15 | Configuration files (`app.php`, `tribunal.php`, `database.php`, `cors.php`, `reverb.php`, etc.) |
| `MIB_backend/database/` | 86 | 79 migrations, seeders (`DatabaseSeeder.php`, `QuestionsSeeder.php`, etc.), factories |
| `MIB_backend/routes/` | 4 | `api.php`, `web.php`, `console.php`, `channels.php` |
| `MIB_backend/resources/` | 4 | Blade templates and default CSS/JS views |
| `MIB_backend/tests/` | 17 | Feature test suites (`TribunalDecisionTest.php`, `TribunalHearingTest.php`, etc.) and Unit tests |
| `MIB_backend/storage/` | 10 | Preserved `.gitignore` files maintaining all required storage directory trees |
| `MIB_backend/` (Root configs) | 12 | `artisan`, `composer.json`, `composer.lock`, `phpunit.xml`, `.env.example`, `.gitignore`, `.editorconfig` |
| `MIB_backend/.github/` | 4 | GitHub Actions workflow definitions |
| `MIB_frontend/src/` | 280 | Vue components, views, stores, services, layouts, assets, styles, router, TypeScript types |
| `MIB_frontend/cypress/` | 10 | Cypress E2E tests, fixtures, and configurations |
| `MIB_frontend/public/` | 3 | Public icons and assets (`favicon.ico`, `webIcon.ico`, `webIcon.svg`) |
| `MIB_frontend/.vscode/` | 1 | `extensions.json` team extension recommendations |
| `MIB_frontend/` (Root configs) | 18 | `package.json`, `package-lock.json`, `vite.config.ts`, `vitest.config.ts`, `tsconfig*.json`, etc. |
| Project Root | 1 | `README.md` |
| Transfer Package Docs | 3 | `TRANSFER_MANIFEST.md`, `EXCLUDED_FILES.md`, `MERGE_REVIEW_FILES.md` |

---

## 3. Excluded Folders & Files Summary

A total of **77,898 files** (~463.99 MB) were evaluated and excluded based on safety and clean transfer rules:

| Category | File Count | Approximate Size | Justification |
| :--- | :---: | :---: | :--- |
| **Git Internal Data (`.git/`)** | 903 | 30.46 MB | Version control database and branch pointers; will not corrupt target git repo. |
| **Backend Vendor (`MIB_backend/vendor/`)** | 48,281 | 226.68 MB | Composer dependencies; platform-dependent, regenerated via `composer install`. |
| **Frontend Dependencies (`MIB_frontend/node_modules/`)** | 28,510 | 200.77 MB | Node modules; platform-dependent, regenerated via `npm install`. |
| **Frontend Build Output (`MIB_frontend/dist/`)** | 133 | 3.30 MB | Production bundle distribution; rebuilt on demand. |
| **Local Environment (`MIB_backend/.env`)** | 1 | 2.3 KB | Machine-specific secrets and database passwords. Preserved safe `.env.example`. |
| **Bootstrap Discovery Cache** | 2 | 24.9 KB | Generated `packages.php` & `services.php` with machine-specific local paths. |
| **Runtime Logs (`storage/logs/*.log`)** | 17 | 1.61 MB | Ephemeral runtime application logs and stack traces. |
| **Blade View Caches (`storage/framework/views/*.php`)** | 6 | 0.05 MB | Compiled PHP view cache files. |
| **Runtime User Uploads (`storage/app/private/*`)** | 49 | 1.11 MB | Uploaded test evidence PDFs and verification docs. |
| **PHPUnit Result Cache (`.phpunit.result.cache`)** | 1 | 31.4 KB | Test execution tracker cache. |
| **Storage Symlink (`MIB_backend/public/storage`)** | 1 | N/A | Windows junction/symlink with machine-specific path. |

*(For full per-file details, consult [EXCLUDED_FILES.md](file:///D:/Projects/Myintellibook_transfer_package/EXCLUDED_FILES.md)).*

---

## 4. Tribunal-Specific Files Detected

The package includes **213 files** dedicated directly to the Tribunal dispute resolution system:

### A. Backend Tribunal Architecture
1. **Controllers & Endpoints:**
   - `app/Http/Controllers/Tribunal/TribunalCaseController.php` (Case filing, acknowledgment, responses)
   - `app/Http/Controllers/Tribunal/TribunalCaseRoomController.php` (Shared case room, questions, notices)
   - `app/Http/Controllers/Tribunal/TribunalDecisionController.php` (Final decisions, findings, orders)
   - `app/Http/Controllers/Tribunal/TribunalEvidenceController.php` (Evidence management, challenges, downloads)
   - `app/Http/Controllers/Tribunal/TribunalHearingController.php` (Hearing sessions, testimony, witnesses)
   - `app/Http/Controllers/Tribunal/TribunalJuryController.php` (Juror case queue, acceptance, recusals)
   - `app/Http/Controllers/Tribunal/TribunalMeController.php` (Juror profile and state)
   - `app/Http/Controllers/Tribunal/TribunalMediationController.php` (Mediation offers, proposals, counters)
   - `app/Http/Controllers/Tribunal/TribunalRepresentationController.php` (Lawyer representation requests)
   - `app/Http/Controllers/Tribunal/TribunalConversationController.php` (Private client-lawyer chat)
   - `app/Http/Controllers/Admin/AdminTribunalJuryPanelController.php` (Admin jury panel provisioning)
2. **Models & Entities:**
   - `TribunalCase.php`, `TribunalCaseParty.php`, `TribunalCaseResponse.php`, `TribunalCaseEvent.php`
   - `TribunalJuryPanel.php`, `TribunalJuryPanelAssignment.php`, `TribunalJuryPanelEvent.php`
   - `TribunalHearing.php`, `TribunalHearingEntry.php`, `TribunalHearingParticipant.php`
   - `TribunalWitness.php`, `TribunalDeliberation.php`, `TribunalDeliberationNote.php`
   - `TribunalFinding.php`, `TribunalDecision.php`, `TribunalDecisionOrder.php`
   - `TribunalEvidence.php`, `TribunalEvidenceChallenge.php`
   - `TribunalMediation.php`, `TribunalMediationProposal.php`
   - `TribunalRepresentationRequest.php`, `TribunalRepresentativeConversation.php`
3. **Services:**
   - `TribunalCaseService.php`, `TribunalCaseRoomService.php`, `TribunalCaseEventService.php`
   - `TribunalJuryPanelService.php`, `TribunalJuryPanelAssignmentService.php`, `JurySelectionService.php`
   - `TribunalHearingService.php`, `TribunalWitnessService.php`
   - `TribunalDeliberationService.php`, `TribunalDecisionService.php`
   - `TribunalEvidenceService.php`, `TribunalMediationService.php`
   - `TribunalRepresentationService.php`, `TribunalRespondentService.php`
   - `TribunalConversationService.php`
4. **Enums & State Machines:**
   - `TribunalCaseStatus.php`, `TribunalHearingStatus.php`, `TribunalHearingEntryType.php`
   - `TribunalWitnessStatus.php`, `TribunalDeliberationStatus.php`, `TribunalDecisionStatus.php`
   - `TribunalJuryPanelStatus.php`, `TribunalJuryPanelAssignmentStatus.php`
5. **Notifications (Email/Database):**
   - 35 distinct Tribunal notification classes covering all lifecycle milestones (e.g., `TribunalHearingStartedNotification`, `TribunalDecisionPublishedNotification`, `TribunalWitnessApprovedNotification`, `TribunalJuryPanelAssignedNotification`, etc.).
6. **Form Requests & API Resources:**
   - Dedicated validation requests and JSON resources formatting Tribunal case rooms, evidence, hearings, jury panels, and decisions.

### B. Frontend Tribunal Architecture
1. **Views & Pages:**
   - `views/tribunal/TribunalCases.vue` (Case list and filtering)
   - `views/tribunal/CreateTribunalCase.vue` (Case intake form)
   - `views/tribunal/TribunalCaseDetails.vue` (Comprehensive case hub with tabbed workspaces)
   - `views/tribunal/TribunalJuryCases.vue` (Juror case overview)
   - `views/tribunal/TribunalRepresentationRequests.vue` (Lawyer intake queue)
   - `views/tribunal/TribunalRepresentedCases.vue` (Lawyer caseload)
   - `views/admin/AdminJuryPanels.vue` (Admin management of jury panels)
   - `views/jury/JuryDashboard.vue` (Juror portal home)
   - `views/jury/JuryAssignedCases.vue` (Juror active cases)
   - `views/jury/JuryCaseDetails.vue` (Juror Case Room workspace)
2. **Components & Sub-Tabs:**
   - `components/tribunal/JuryHearingTab.vue` (Hearing controls, testimony entry, transcript)
   - `components/tribunal/JuryDeliberationTab.vue` (Private jury panel deliberation, findings, decision drafting)
   - `components/tribunal/PartyHearingTab.vue` (Party-facing hearing status and questions)
   - `components/tribunal/PartyDecisionTab.vue` (Official published tribunal judgment and remedies)
   - `components/tribunal/TribunalClientChatModal.vue` (Confidential lawyer-client communications)
   - `components/commonComponents/SubmitCase.vue`
3. **Layouts & State Stores:**
   - `layouts/JuryPanelLayout.vue` (Dedicated juror portal sidebar and header navigation)
   - `stores/tribunal.ts` (Tribunal state store)
   - `stores/juryPanel.ts` (Jury panel state store)
   - `services/tribunalService.ts` (Axios API connector for all tribunal endpoints)
   - `types/tribunal.ts` (Complete TypeScript domain interfaces)

---

## 5. Shared Files Containing Tribunal Changes

These files serve shared application roles and contain integrated Tribunal functionality:
1. `MIB_backend/routes/api.php`
2. `MIB_backend/app/Models/User.php`
3. `MIB_backend/app/Models/TribunalCase.php`
4. `MIB_backend/app/Providers/AppServiceProvider.php`
5. `MIB_backend/bootstrap/app.php`
6. `MIB_backend/app/helpers/helpers.php`
7. `MIB_backend/app/Services/UserService.php`
8. `MIB_backend/app/Services/ProfileService.php`
9. `MIB_backend/app/Http/Resources/ProfileResource.php`
10. `MIB_backend/app/Http/Requests/AnswerRequest.php`
11. `MIB_backend/database/seeders/DatabaseSeeder.php`
12. `MIB_backend/composer.json` & `composer.lock`
13. `MIB_frontend/src/router/index.ts`
14. `MIB_frontend/src/services/auth.ts`
15. `MIB_frontend/src/stores/User/userProfile.ts`
16. `MIB_frontend/src/types/tribunal.ts`
17. `MIB_frontend/src/types/userGeneralInfoType.ts`
18. `MIB_frontend/src/types/profileListSearch.ts`
19. `MIB_frontend/src/App.vue`
20. `MIB_frontend/src/assets/main.css`
21. `MIB_frontend/src/components/navBar.vue`
22. `MIB_frontend/src/components/commonComponents/navBarDropDown.vue`
23. `MIB_frontend/src/components/commonComponents/navBarItems.vue`
24. `MIB_frontend/src/components/commonComponents/navBarMobile.vue`
25. `MIB_frontend/src/components/commonComponents/navBarSearch.vue`
26. `MIB_frontend/src/components/commonComponents/userUpperSection.vue`
27. `MIB_frontend/src/components/userLogin/formComponent.vue`
28. `MIB_frontend/src/components/userRegister/formComponent.vue`
29. `MIB_frontend/src/views/FrontPage.vue`
30. `MIB_frontend/src/views/User/Home.vue`
31. `MIB_frontend/src/views/User/Login.vue`
32. `MIB_frontend/src/views/User/ProfilePage.vue`
33. `MIB_frontend/src/views/User/Register.vue`
34. `MIB_frontend/src/views/User/showUserProfile.vue`
35. `MIB_frontend/package.json` & `package-lock.json`

*(For detailed instructions on safely merging each of these files, consult [MERGE_REVIEW_FILES.md](file:///D:/Projects/Myintellibook_transfer_package/MERGE_REVIEW_FILES.md)).*

---

## 6. Migration Files Included

The package includes **79 database migration files** covering both core legacy schemas and the full evolution of the Tribunal dispute resolution platform.

### Key Tribunal Migrations:
- `2026_09_15_225049_create_tribunal_cases_table.php` (Core case registry)
- `2026_09_15_225100_create_tribunal_case_parties_table.php` (Complainant & respondent party tracking)
- `2026_09_17_013725_create_tribunal_case_responses_table.php` (Formal respondent responses)
- `2026_09_17_122535_create_tribunal_batch2_tables.php` (Evidence and preliminary hearings)
- `2026_09_17_173000_create_tribunal_batch3_tables.php` (Case room & mediation)
- `2026_09_18_180000_create_tribunal_batch4_tables.php` (Representation & lawyer verification)
- `2026_09_19_180000_create_tribunal_batch5_tables.php` (Adjudicator assignment and procedural notices)
- `2026_10_02_140000_create_tribunal_jury_panels_tables.php` (Jury Panel accounts and management)
- `2026_10_02_150000_create_tribunal_jury_panel_assignments_tables.php` (Automatic jury panel assignment system)
- `2026_10_02_160000_create_tribunal_hearings_tables.php` (Hearings, testimony entries, witness tracking)
- `2026_10_02_170000_create_tribunal_deliberations_and_decisions_tables.php` (Private deliberations, findings, binding final decisions)

---

## 7. Tests Included

The package includes **27 automated test suites**:
- **Backend PHPUnit Feature & Unit Tests:**
  - `tests/Feature/TribunalDecisionTest.php`
  - `tests/Feature/TribunalHearingTest.php`
  - `tests/Feature/TribunalJuryPanelAdminTest.php`
  - `tests/Feature/TribunalJuryPanelAssignmentTest.php`
  - `tests/Feature/TribunalJuryPanelCaseRoomTest.php`
  - `tests/Feature/TribunalJuryPortalTest.php`
  - `tests/Feature/TribunalBatch2Test.php`
  - `tests/Feature/TribunalBatch4Test.php`
  - `tests/Feature/TribunalBatch5Test.php`
  - `tests/Feature/ProfessionalVerificationTest.php`
  - `tests/Feature/Auth/*` (Registration, Login, Password Reset, Verification tests)
  - `tests/Unit/ExampleTest.php`
- **Frontend Cypress E2E Tests:**
  - `cypress/e2e/example.cy.ts`
  - `cypress/e2e/spec.cy.ts`
  - `cypress/e2e/User/login_user.cy.ts`
  - `cypress/e2e/User/register_user.cy.ts`
  - `cypress/e2e/User/change_password.cy.ts`
  - `cypress/e2e/User/password_resert.cy.ts`
  - `cypress/e2e/User/user_details_form.cy.ts`

---

## 8. Dependency Files Included

- **Backend:**
  - `MIB_backend/composer.json`
  - `MIB_backend/composer.lock` (pins exact verified packages)
- **Frontend:**
  - `MIB_frontend/package.json`
  - `MIB_frontend/package-lock.json` (pins exact npm dependency tree)

---

## 9. Uncertain Files Kept

- **`MIB_frontend/cloudflared-linux-amd64.deb` (20.16 MB):**
  - **Status:** Committed binary package in git history.
  - **Decision:** **KEPT**. In accordance with instructions not to automatically delete or exclude committed assets without certainty. Can be deleted on destination if Cloudflare tunnel package is not required in deployment.
- **`MIB_backend/run-backend.bat` & `run-migrate.bat`:**
  - **Decision:** **KEPT**. Developer workflow helper scripts.
- **`MIB_frontend/compose.yaml` & `Dockerfile` & `nginx.conf`:**
  - **Decision:** **KEPT**. Container deployment definitions.

---

## 10. Validation Commands Executed & Results

Both backend and frontend were validated prior to package finalization:

### Backend Validation:
1. `composer install --no-interaction`
   - **Result:** Success (Exit Code: 0). 147 packages resolved cleanly.
2. `php artisan optimize:clear`
   - **Result:** Success (Exit Code: 0). All config, route, view, and event caches cleared cleanly.
3. `php artisan route:list`
   - **Result:** Success (Exit Code: 0). All 168 application routes (including all 56 Tribunal routes) registered with zero route resolution errors.
4. `php artisan migrate:status`
   - **Result:** Success (Exit Code: 0). All 79 migration files detected and schema status verified.
5. `php artisan test --filter Tribunal`
   - **Result:** **100% PASS** (Exit Code: 0).
   - **Metrics:** **182 tests passed, 552 assertions, execution time: 10.76s**.

### Frontend Validation:
1. `npm install`
   - **Result:** Success (Exit Code: 0). Cypress cache verified and 689 npm packages installed.
2. `npm run type-check` (`vue-tsc --build`)
   - **Result:** **100% PASS** (Exit Code: 0). Zero TypeScript compiler errors across all components, stores, routes, and types.
3. `npm run build-only` (`vite build`)
   - **Result:** **100% PASS** (Exit Code: 0). Production bundle built successfully in 8.16s.

---

## 11. Crucial Warnings Before Copying Into New Repo

1. **Do Not Overwrite `.git`:** Ensure you do not initialize or copy an old `.git` directory into the destination.
2. **Review Shared Files First:** Consult `MERGE_REVIEW_FILES.md` and use git merge or diff tools for `routes/api.php`, `User.php`, and `router/index.ts`.
3. **Environment Setup:** Copy `MIB_backend/.env.example` to `MIB_backend/.env` on the destination, set database connection details, and run `php artisan key:generate`.
4. **Storage Link:** Run `php artisan storage:link` on the destination to create a clean public storage symlink.
5. **Database Migration:** Run `php artisan migrate` on the destination to establish or update the schema.
