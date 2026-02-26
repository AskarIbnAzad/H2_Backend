<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class H2MigrateFromCsv extends Command
{
    protected $signature = 'h2:migrate-from-csv
                            {--path=h2import : Folder under storage/app containing CSV files from old DB}';

    protected $description = 'Migrate all data from old H2 CSV exports into new schema using DB facade only';

    public function handle()
    {
//      php artisan h2:migrate-from-csv --path=h2import
//      If you keep the default --path=h2import you can even run:
//      php artisan h2:migrate-from-csv

        $folder   = trim($this->option('path'), '/');
        $basePath = storage_path('app/' . $folder);

        $this->info("CSV base path: {$basePath}");

        if (!is_dir($basePath)) {
            $this->error("Directory does not exist: {$basePath}");
            return self::FAILURE;
        }

        // Turn off FKs so we can insert in any order
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            /*
             * LOOKUP / TAXONOMY TABLES
             */
            $this->migrateAdministrationMethods("{$basePath}/administration_methods.csv");
            $this->migrateCountries("{$basePath}/countries.csv");
            $this->migrateDiseases("{$basePath}/diseases.csv");
            $this->migrateSpecies("{$basePath}/species.csv");
            $this->migrateSystems("{$basePath}/systems.csv");
            $this->migrateOrgans("{$basePath}/organs.csv");
            $this->migrateBioCategories("{$basePath}/bio_categories.csv");
            $this->migrateBioSub("{$basePath}/bio_sub.csv");
            $this->migrateBioBridge("{$basePath}/bio_bridge.csv");
            $this->migrateKeywords("{$basePath}/keywords.csv");
            $this->migrateStudyTypes("{$basePath}/study_type.csv");
            $this->migrateResearchTopics("{$basePath}/research_topic.csv");
            $this->migrateRoles("{$basePath}/roles.csv");
            $this->migrateVerifiedAuthors("{$basePath}/verified_authors.csv");

            /*
             * USERS & AUTH
             */
            $this->migrateUsers("{$basePath}/users.csv");
            $this->migratePasswordOtps("{$basePath}/password_otps.csv");
            $this->migratePasswordResetTokens("{$basePath}/password_reset_tokens.csv");
            $this->migratePersonalAccessTokens("{$basePath}/personal_access_tokens.csv");
            $this->migrateFailedJobs("{$basePath}/failed_jobs.csv");

            /*
             * CONTACT / FEEDBACK / CLAIMS
             */
            $this->migrateContactSubmissions("{$basePath}/contact.csv");
            $this->migrateArticleFeedback("{$basePath}/feedback.csv");
            $this->migrateArticleClaims("{$basePath}/claims.csv");

            /*
             * ARTICLES / PORTAL / FINAL ARTICLES
             */
            $articlesMap = $this->loadArticlesMap("{$basePath}/articles.csv");
            $this->migratePortalArticles("{$basePath}/article_public_data.csv", $articlesMap);
            $this->migrateFinalArticles("{$basePath}/final_article.csv");
            $this->migrateFinalArticleRevisions("{$basePath}/final_article_revisions.csv");

            /*
             * PIVOT TABLES (relations between articles & taxonomies)
             * These depend on articles + lookup tables above
             */
            $this->migratePSpecies("{$basePath}/p_species.csv");
            $this->migratePOrgans("{$basePath}/p_organs.csv");
            $this->migratePStudyType("{$basePath}/p_study_type.csv");
            $this->migratePResearchTopics("{$basePath}/p_research_topics.csv");
            $this->migratePAdministration("{$basePath}/p_administration.csv");
//            $this->migratePBiomaker("{$basePath}/p_biomaker.csv");

            /*
             * OTHER
             */
            $this->migrateGraphData("{$basePath}/graph_data.csv");
            $this->migrateTutorials("{$basePath}/tutorials.csv");

        } catch (\Throwable $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->error("Error: " . $e->getMessage());
            throw $e;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info('H2 CSV migration completed.');
        return self::SUCCESS;
    }

    /* ==========================================================
     * Generic CSV reader – yields associative rows
     * ======================================================== */

    protected function readCsv(string $path): \Generator
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("CSV file not found: {$path}");
        }

        $file = new \SplFileObject($path);
        $file->setFlags(
            \SplFileObject::READ_CSV
            | \SplFileObject::READ_AHEAD
            | \SplFileObject::SKIP_EMPTY
        );
        $file->setCsvControl(',');

        $header = null;

        foreach ($file as $row) {
            if ($row === [null] || $row === false) {
                continue;
            }

            if ($header === null) {
                $header = $row;
                continue;
            }

            $assoc = [];
            foreach ($header as $i => $colName) {
                if ($colName === null || $colName === '') {
                    continue;
                }
                $assoc[$colName] = $row[$i] ?? null;
            }

            yield $assoc;
        }
    }

    protected function insertBatch(string $table, array &$batch, int $batchSize = 500): void
    {
        if (count($batch) >= $batchSize) {
            DB::table($table)->insert($batch);
            $batch = [];
        }
    }

    /* ==========================================================
     * ADMINISTRATION METHODS
     * ======================================================== */

    protected function migrateAdministrationMethods(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("administration_methods.csv not found, skipping.");
            return;
        }

        $this->info("Migrating administration_methods...");

        DB::table('administration_methods')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'status'     => 'Active',
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('administration_methods', $batch);
        }

        if ($batch) {
            DB::table('administration_methods')->insert($batch);
        }

        $this->info('administration_methods done.');
    }

    /* ==========================================================
     * COUNTRIES / DISEASES / SPECIES / SYSTEMS / ORGANS
     * ======================================================== */

    protected function migrateCountries(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("countries.csv not found, skipping.");
            return;
        }

        $this->info("Migrating countries...");

        DB::table('countries')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'parent_id'  => $row['parent_id'] !== '' ? (int)$row['parent_id'] : null,
                'status'     => $this->mapCountryStatus($row['status'] ?? null),
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('countries', $batch);
        }

        if ($batch) {
            DB::table('countries')->insert($batch);
        }

        $this->info('countries done.');
    }

    protected function mapCountryStatus(?string $old): string
    {
        if ($old === 'Active') {
            return 'Active';
        }
        return 'Inactive';
    }

    protected function migrateDiseases(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("diseases.csv not found, skipping.");
            return;
        }

        $this->info("Migrating diseases...");

        DB::table('diseases')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'parent_id'  => $row['parent_id'] !== '' ? (int)$row['parent_id'] : null,
                'status'     => 'Active',
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('diseases', $batch);
        }

        if ($batch) {
            DB::table('diseases')->insert($batch);
        }

        $this->info('diseases done.');
    }

    protected function migrateSpecies(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("species.csv not found, skipping.");
            return;
        }

        $this->info("Migrating species...");

        DB::table('species')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'parent_id'  => $row['parent_id'] !== '' ? (int)$row['parent_id'] : null,
                'status'     => 'Active',
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('species', $batch);
        }

        if ($batch) {
            DB::table('species')->insert($batch);
        }

        $this->info('species done.');
    }

    protected function migrateSystems(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("systems.csv not found, skipping.");
            return;
        }

        $this->info("Migrating systems...");

        DB::table('systems')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'parent_id'  => null,
//                'parent_id'  => $row['parent_id'] !== '' ? (int)$row['parent_id'] : null,
                'status'     => 'Active',
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('systems', $batch);
        }

        if ($batch) {
            DB::table('systems')->insert($batch);
        }

        $this->info('systems done.');
    }

    protected function migrateOrgans(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("organs.csv not found, skipping.");
            return;
        }

        $this->info("Migrating organs...");

        DB::table('organs')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'parent_id'  => null,
//                'parent_id'  => $row['parent_id'] !== '' ? (int)$row['parent_id'] : null,
                'status'     => 'Active',
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('organs', $batch);
        }

        if ($batch) {
            DB::table('organs')->insert($batch);
        }

        $this->info('organs done.');
    }

    /* ==========================================================
     * BIO CATEGORIES / BIO SUB / BIO BRIDGE
     * ======================================================== */

    protected function migrateBioCategories(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("bio_categories.csv not found, skipping.");
            return;
        }

        $this->info("Migrating bio_categories...");

        DB::table('bio_categories')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'status'     => 'Active',
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('bio_categories', $batch);
        }

        if ($batch) {
            DB::table('bio_categories')->insert($batch);
        }

        $this->info('bio_categories done.');
    }

    protected function migrateBioSub(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("bio_sub.csv not found, skipping.");
            return;
        }

        $this->info("Migrating bio_sub...");

        DB::table('bio_sub')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'parent_id'  => isset($row['parent_id']) && $row['parent_id'] !== '' ? (int)$row['parent_id'] : null,
                'status'     => $this->mapBioSubStatus($row['status'] ?? null),
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('bio_sub', $batch);
        }

        if ($batch) {
            DB::table('bio_sub')->insert($batch);
        }

        $this->info('bio_sub done.');
    }

    protected function mapBioSubStatus(?string $old): string
    {
        if ($old === 'Approved') {
            return 'Approved';
        }
        if ($old === 'Deleted') {
            return 'Deleted';
        }
        // Requested -> Pending
        return 'Pending';
    }

    protected function migrateBioBridge(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("bio_bridge.csv not found, skipping.");
            return;
        }

        $this->info("Migrating bio_bridge...");

        DB::table('bio_bridge')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'cat_id'     => (int)$row['cat_id'],
                'sub_id'     => (int)$row['sub_id'],
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('bio_bridge', $batch);
        }

        if ($batch) {
            DB::table('bio_bridge')->insert($batch);
        }

        $this->info('bio_bridge done.');
    }

    /* ==========================================================
     * KEYWORDS / STUDY TYPES / RESEARCH TOPICS
     * ======================================================== */

    protected function migrateKeywords(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("keywords.csv not found, skipping.");
            return;
        }

        $this->info("Migrating keywords...");

        DB::table('keywords')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'keyword'    => $row['keyword'],
                'status'     => 'Active',
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('keywords', $batch);
        }

        if ($batch) {
            DB::table('keywords')->insert($batch);
        }

        $this->info('keywords done.');
    }

    protected function migrateStudyTypes(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("study_type.csv not found, skipping.");
            return;
        }

        $this->info("Migrating study_types...");

        DB::table('study_types')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'parent_id'  => null,
                'status'     => 'Active',
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('study_types', $batch);
        }

        if ($batch) {
            DB::table('study_types')->insert($batch);
        }

        $this->info('study_types done.');
    }

    protected function migrateResearchTopics(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("research_topic.csv not found, skipping.");
            return;
        }

        $this->info("Migrating research_topics...");

        DB::table('research_topics')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'parent_id'  => null,
                'status'     => 'Active',
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('research_topics', $batch);
        }

        if ($batch) {
            DB::table('research_topics')->insert($batch);
        }

        $this->info('research_topics done.');
    }

    /* ==========================================================
     * ROLES / USERS / VERIFIED AUTHORS
     * ======================================================== */

    protected function migrateRoles(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("roles.csv not found, skipping.");
            return;
        }

        $this->info("Migrating roles...");

        DB::table('roles')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'          => (int)$row['id'],
                'name'        => $row['name'],
                'description' => null,
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('roles', $batch);
        }

        if ($batch) {
            DB::table('roles')->insert($batch);
        }

        $this->info('roles done.');
    }

    // Normalize any datetime/date/time field:
    // - null, empty string, "NULL", "0000-00-00 00:00:00" => current time
    // - otherwise, return as-is
    // Replace your existing helper with this one
    protected function normalizeDateTimeOrNow($value): string
    {
        if ($value === null) {
            return now()->toDateTimeString();
        }

        $v = trim((string) $value);

        // Common "no value" cases
        if (
            $v === '' ||
            strtoupper($v) === 'NULL' ||
            $v === '0000-00-00 00:00:00'
        ) {
            return now()->toDateTimeString();
        }

        // Accept only proper datetime format: 2025-05-21 04:31:21
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $v)) {
            // If it's something weird like 'revision"...', treat it as invalid
            return now()->toDateTimeString();
        }

        return $v;
    }

    protected function migrateUsers(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("users.csv not found, skipping.");
            return;
        }

        $this->info("Migrating users...");

        DB::table('users')->truncate();

        // Build map: role name => role id
        $roleMap = DB::table('roles')->pluck('id', 'name')->toArray();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $oldRoleName = $row['role'] ?? null;
            $roleId = $oldRoleName && isset($roleMap[$oldRoleName]) ? $roleMap[$oldRoleName] : null;

            // Keep email_verified_at null if empty/"NULL"
            $rawVerified = isset($row['email_verified_at']) ? trim($row['email_verified_at']) : '';
            $emailVerifiedAt = null;
            if ($rawVerified !== '' && strtoupper($rawVerified) !== 'NULL') {
                $emailVerifiedAt = $rawVerified; // assume it's valid datetime
            }

            $batch[] = [
                'id'                => (int)$row['id'],
                'name'              => $row['name'],
                'email'             => $row['email'],
                'email_verified_at' => $emailVerifiedAt,
                'password'          => $row['password'],
                'role_id'           => $roleId,
                'status'            => $this->mapUserStatus($row['status'] ?? null),
                'remember_token'    => $row['remember_token'] ?: null,

                // Use helper here:
                'created_at'        => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'        => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];

            $this->insertBatch('users', $batch);
        }

        if ($batch) {
            DB::table('users')->insert($batch);
        }

        $this->info('users done.');
    }

    protected function mapUserStatus(?string $old): string
    {
        if ($old === 'Active') {
            return 'Active';
        }
        // 'In-Active' or others -> Inactive
        return 'Inactive';
    }

    protected function migrateVerifiedAuthors(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("verified_authors.csv not found, skipping.");
            return;
        }

        $this->info("Migrating verified_authors...");

        DB::table('verified_authors')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'                      => (int)$row['id'],
                'name'                    => $row['name'],
                'orcid'                   => $row['orcid'] ?? null,
                'email'                   => null,
                'institution_affiliation' => $row['childrens'] ?? null,
                'author_h_index'          => null,
                'parent_id'               => $row['parent_id'] !== '' ? (int)$row['parent_id'] : null,
                'is_featured'             => isset($row['is_featured']) ? (int)$row['is_featured'] : 0,
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('verified_authors', $batch);
        }

        if ($batch) {
            DB::table('verified_authors')->insert($batch);
        }

        $this->info('verified_authors done.');
    }

    /* ==========================================================
     * AUTH / JOBS
     * ======================================================== */

    protected function migratePasswordOtps(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("password_otps.csv not found, skipping.");
            return;
        }

        $this->info("Migrating password_otps...");

        DB::table('password_otps')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'               => (int) $row['id'],
                'email'            => $row['email'],
                'purpose'          => $row['purpose'] ?: 'password_reset',
                'otp_hash'         => $row['otp_hash'],
                'attempts'         => (int) ($row['attempts'] ?? 0),

                // Use the helper for all datetime-ish fields
                'expires_at'       => $this->normalizeDateTimeOrNow($row['expires_at'] ?? null),
                'resend_after'     => $this->normalizeDateTimeOrNow($row['resend_after'] ?? null),
                'verified_at'      => $this->normalizeDateTimeOrNow($row['verified_at'] ?? null),
                'token_expires_at' => $this->normalizeDateTimeOrNow($row['token_expires_at'] ?? null),
                'created_at'       => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'       => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),

                'verify_token'     => $row['verify_token'] ?: null,
            ];

            $this->insertBatch('password_otps', $batch);
        }

        if ($batch) {
            DB::table('password_otps')->insert($batch);
        }

        $this->info('password_otps done.');
    }

    protected function migratePasswordResetTokens(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("password_reset_tokens.csv not found, skipping.");
            return;
        }

        $this->info("Migrating password_reset_tokens...");

        DB::table('password_reset_tokens')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'email'      => $row['email'],
                'token'      => $row['token'],
                'created_at' => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
            ];
            $this->insertBatch('password_reset_tokens', $batch);
        }

        if ($batch) {
            DB::table('password_reset_tokens')->insert($batch);
        }

        $this->info('password_reset_tokens done.');
    }

    protected function migratePersonalAccessTokens(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("personal_access_tokens.csv not found, skipping.");
            return;
        }

        $this->info("Migrating personal_access_tokens...");

        DB::table('personal_access_tokens')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'             => (int) $row['id'],
                'tokenable_type' => $row['tokenable_type'],
                'tokenable_id'   => (int) $row['tokenable_id'],
                'name'           => $row['name'],
                'token'          => $row['token'],

                // CSV has ["*"] as text, DB column is text, so just keep it as string
                'abilities'      => $row['abilities'] ?: '["*"]',

                // Use the datetime normalizer so 'NULL', '', '0000...' all become a valid datetime
                'last_used_at'   => $this->normalizeDateTimeOrNow($row['last_used_at'] ?? null),
                'expires_at'     => $this->normalizeDateTimeOrNow($row['expires_at'] ?? null),
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];

            // your existing batch insert helper
            $this->insertBatch('personal_access_tokens', $batch);
        }

        if ($batch) {
            DB::table('personal_access_tokens')->insert($batch);
        }

        $this->info('personal_access_tokens done.');
    }

    protected function migrateFailedJobs(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("failed_jobs.csv not found, skipping.");
            return;
        }

        $this->info("Migrating failed_jobs...");

        DB::table('failed_jobs')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'uuid'       => $row['uuid'],
                'connection' => $row['connection'],
                'queue'      => $row['queue'],
                'payload'    => $row['payload'],
                'exception'  => $row['exception'],
                'failed_at'  => $row['failed_at'] ?: null,
            ];
            $this->insertBatch('failed_jobs', $batch);
        }

        if ($batch) {
            DB::table('failed_jobs')->insert($batch);
        }

        $this->info('failed_jobs done.');
    }

    /* ==========================================================
     * CONTACT / FEEDBACK / CLAIMS
     * ======================================================== */

    protected function migrateContactSubmissions(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("contact.csv not found, skipping (contact_submissions).");
            return;
        }

        $this->info("Migrating contact -> contact_submissions...");

        DB::table('contact_submissions')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'email'      => $row['email'],
                'subject'    => null,
                'message'    => $row['message'],
                'attachment' => $row['attachment'] ?: null,
                'status'     => 'New',
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('contact_submissions', $batch);
        }

        if ($batch) {
            DB::table('contact_submissions')->insert($batch);
        }

        $this->info('contact_submissions done.');
    }

    protected function migrateArticleFeedback(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("feedback.csv not found, skipping (article_feedback).");
            return;
        }

        $this->info("Migrating feedback -> article_feedback...");

        DB::table('article_feedback')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'user'       => $row['user'],
                'article_id' => $row['article_id'] !== '' ? (int)$row['article_id'] : null,
                'page_url'   => $row['page_url'] ?: null,
                'feedback'   => $row['feedback'],
                'status'     => $this->mapFeedbackStatus($row['status'] ?? null),
                'created_at' => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at' => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('article_feedback', $batch);
        }

        if ($batch) {
            DB::table('article_feedback')->insert($batch);
        }

        $this->info('article_feedback done.');
    }

    protected function mapFeedbackStatus(?string $old): string
    {
        $old = strtolower((string)$old);
        return match ($old) {
            'in progress' => 'In Progress',
            'reviewed'    => 'Reviewed',
            'resolved'    => 'Resolved',
            default       => 'Pending',
        };
    }

    protected function migrateArticleClaims(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("claims.csv not found, skipping (article_claims).");
            return;
        }

        $this->info("Migrating claims -> article_claims...");

        DB::table('article_claims')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $status = strtolower((string)($row['status'] ?? 'pending'));
            $mappedStatus = match ($status) {
                'approved' => 'Approved',
                'rejected' => 'Rejected',
                default    => 'Pending',
            };

            $batch[] = [
                'id'                  => (int)$row['id'],
                'full_name'           => $row['full_name'],
                'email'               => $row['email'],
                'affiliation'         => $row['affiliation'] ?: null,
                'position_title'      => $row['position_title'] ?: null,
                'orcid_id'            => $row['orcid_id'] ?: null,
                'explanation'         => $row['explanation'],
                'supporting_evidence' => $row['supporting_evidence'] ?: null,
                'final_article_id'    => (int)$row['final_article_id'],
                'status'              => $mappedStatus,
                'user_id'             => $row['user_id'] !== '' ? (int)$row['user_id'] : null,
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('article_claims', $batch);
        }

        if ($batch) {
            DB::table('article_claims')->insert($batch);
        }

        $this->info('article_claims done.');
    }

    /* ==========================================================
     * FINAL ARTICLES -> articles (new)
     * ======================================================== */

    protected function mapArticleStatus(?string $old): string
    {
        // Exact enum values from your new (and old) schema
        $allowed = [
            'Unverified',
            'Verified',
            'Draft',
            'In Review',
            'Flagged for Review',
            'Review Complete',
        ];

        $default = 'Unverified';

        // Normalize input
        $v = trim((string) $old);

        // Empty or NULL → default
        if ($v === '') {
            return $default;
        }

        // 1) Case-insensitive exact match to any allowed value
        foreach ($allowed as $opt) {
            if (strcasecmp($opt, $v) === 0) { // case-insensitive compare
                return $opt;
            }
        }

        // 2) Optional legacy / typo mappings (only if you need them)
        $lower = strtolower($v);

        // Example: if any row had "pending" or "draft" etc.
        if (in_array('Draft', $allowed, true) && in_array($lower, ['pending', 'draft'], true)) {
            return 'Draft';
        }

        if (in_array('In Review', $allowed, true) && in_array($lower, ['in review', 'review', 'reviewing'], true)) {
            return 'In Review';
        }

        if (in_array('Flagged for Review', $allowed, true) && in_array($lower, ['flagged', 'flagged for review'], true)) {
            return 'Flagged for Review';
        }

        if (in_array('Review Complete', $allowed, true) && in_array($lower, ['review complete', 'completed'], true)) {
            return 'Review Complete';
        }

        // 3) If nothing matched, return a safe default that is valid for the enum
        return $default;
    }

    protected function migrateFinalArticles(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("final_article.csv not found, skipping (articles).");
            return;
        }

        $this->info("Migrating final_article -> articles...");

        DB::table('articles')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'             => (int)$row['id'],
                'mhid'           => $row['mhid'],
                'doi'            => $row['doi'] ?: null,
                'pmid'           => $row['pmid'] ?: null,
                'reviewer_id'    => $row['reviewer_id'] !== '' ? (int)$row['reviewer_id'] : null,
                'verified_by'    => $row['verified_by'] !== '' ? (int)$row['verified_by'] : null,
                'added_by'       => (int)($row['addedBy'] ?? 1),

                // OLD:
                // 'status'         => $row['status'],

                // NEW: enum-safe
                'status'         => $this->mapArticleStatus($row['status'] ?? null),

                'is_trending'    => $row['is_trending'] !== '' ? (int)$row['is_trending'] : 0,
                'is_highlighted' => 0,
                'rank_score'     => null,
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];

            $this->insertBatch('articles', $batch);
        }

        if ($batch) {
            DB::table('articles')->insert($batch);
        }

        $this->info('articles (from final_article) done.');
    }

    protected function migrateFinalArticleRevisions(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("final_article_revisions.csv not found, skipping (article_revisions).");
            return;
        }

        $this->info("Migrating final_article_revisions -> article_revisions...");

        DB::table('article_revisions')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'article_id' => (int)$row['article_id'],
                'changed_by' => (int)$row['changed_by'],
                'changes'    => $row['changes'],
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('article_revisions', $batch);
        }

        if ($batch) {
            DB::table('article_revisions')->insert($batch);
        }

        $this->info('article_revisions done.');
    }

    /* ==========================================================
     * PORTAL ARTICLES (article_public_data + articles.csv map)
     * ======================================================== */

    protected function loadArticlesMap(string $csvPath): array
    {
        $map = [
            'by_pmid' => [],
            'by_doi'  => [],
        ];

        if (!file_exists($csvPath)) {
            $this->warn("articles.csv not found, portal_articles will not have outcome/admin_approval.");
            return $map;
        }

        foreach ($this->readCsv($csvPath) as $row) {
            $pmid = trim((string)($row['pmid'] ?? ''));
            $doi  = trim((string)($row['doi'] ?? ''));

            if ($pmid !== '') {
                $map['by_pmid'][$pmid] = $row;
            }
            if ($doi !== '') {
                $map['by_doi'][$doi] = $row;
            }
        }

        return $map;
    }

    protected function migratePortalArticles(string $csvPath, array $articlesMap): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("article_public_data.csv not found, skipping (portal_articles).");
            return;
        }

        $this->info("Migrating article_public_data + articles -> portal_articles...");

        DB::table('portal_articles')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $pmid = trim((string)($row['pmid'] ?? ''));
            $doi  = trim((string)($row['doi'] ?? ''));

            $match = null;

            if ($pmid !== '' && isset($articlesMap['by_pmid'][$pmid])) {
                $match = $articlesMap['by_pmid'][$pmid];
            } elseif ($doi !== '' && isset($articlesMap['by_doi'][$doi])) {
                $match = $articlesMap['by_doi'][$doi];
            }

            $outcome       = $match['outcome'] ?? null;
            $adminApproval = $this->mapAdminApproval($match['admin_approval'] ?? null);

            $batch[] = [
                'id'               => (int)$row['id'],
                'title'            => $row['title'],
                'authors'          => $row['authors'] ?? null,
                'year'             => $row['year'] ?: null,
                'country'          => $row['country'] ?: null,
                'grant_country'    => $row['grantCountry'] ?? null,
                'research_country' => $row['researchCountry'] ?? null,
                'pmid'             => $pmid ?: null,
                'doi'              => $doi ?: null,
                'abstract'         => $row['abstract'] ?? null,
                'publisher'        => $row['publisher'] ?? null,
                'journal'          => $row['journal'] ?? null,
                'journal_url'      => $row['journalURL'] ?? null,
                'volume'           => $row['volume'] ?? null,
                'pages'            => $row['pages'] ?? null,
                'impact_factor'    => $row['impactFactor'] ?? null,
                'h_index'          => $row['HIndex'] ?? null,
                'sci_mago'         => $row['sciMAGO'] ?? null,
                'outcome'          => $outcome,
                'admin_approval'   => $adminApproval,
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];

            $this->insertBatch('portal_articles', $batch);
        }

        if ($batch) {
            DB::table('portal_articles')->insert($batch);
        }

        $this->info('portal_articles done.');
    }

    protected function mapAdminApproval(?string $old): string
    {
        // Old enum: Approved/Pending/Rejected/Revisions
        return match ($old) {
            'Approved' => 'Approved',
            'Rejected' => 'Rejected',
            default    => 'Pending', // Pending or Revisions
        };
    }

    /* ==========================================================
     * GRAPH DATA / TUTORIALS
     * ======================================================== */

    protected function migrateGraphData(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("graph_data.csv not found, skipping.");
            return;
        }

        $this->info("Migrating graph_data...");

        DB::table('graph_data')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'lbl'        => $row['lbl'],
                'type'       => $row['type'],
                'meta'       => $row['meta'] ?? null,
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('graph_data', $batch);
        }

        if ($batch) {
            DB::table('graph_data')->insert($batch);
        }

        $this->info('graph_data done.');
    }

    protected function migrateTutorials(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("tutorials.csv not found, skipping.");
            return;
        }

        $this->info("Migrating tutorials...");

        DB::table('tutorials')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'          => (int)$row['id'],
                'title'       => $row['title'],
                'description' => $row['description'] ?? null,
                'video_url'   => $row['video_url'] ?? null,
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];
            $this->insertBatch('tutorials', $batch);
        }

        if ($batch) {
            DB::table('tutorials')->insert($batch);
        }

        $this->info('tutorials done.');
    }

    /* ==========================================================
     * PIVOT TABLES: p_species, p_organs, p_study_type, etc.
     * ======================================================== */

    protected function migratePSpecies(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("p_species.csv not found, skipping.");
            return;
        }

        $this->info("Migrating p_species...");

        DB::table('article_species')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'article_id' => (int)$row['article_id'],
                'species_id' => (int)$row['specie_id'],
                'verified' => true,
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];

            $this->insertBatch('article_species', $batch);
        }

        if ($batch) {
            DB::table('article_species')->insert($batch);
        }

        $this->info('article_species done.');
    }

    protected function migratePOrgans(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("p_organs.csv not found, skipping.");
            return;
        }

        $this->info("Migrating p_organs...");

        DB::table('article_organs')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'article_id' => (int)$row['article_id'],
                'organ_id'   => (int)$row['organ_id'],
                'verified' => true,
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];

            $this->insertBatch('article_organs', $batch);
        }

        if ($batch) {
            DB::table('article_organs')->insert($batch);
        }

        $this->info('article_organs done.');
    }

    protected function migratePStudyType(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("p_study_type.csv not found, skipping.");
            return;
        }

        $this->info("Migrating p_study_type...");

        DB::table('article_study_types')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'            => (int)$row['id'],
                'article_id'    => (int)$row['article_id'],
                'study_type_id' => (int)$row['study_type_id'],
                'verified' => true,
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];

            $this->insertBatch('article_study_types', $batch);
        }

        if ($batch) {
            DB::table('article_study_types')->insert($batch);
        }

        $this->info('article_study_types done.');
    }

    protected function migratePResearchTopics(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("p_research_topics.csv not found, skipping.");
            return;
        }

        $this->info("Migrating p_research_topics...");

        DB::table('article_research_topics')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'                => (int)$row['id'],
                'article_id'        => (int)$row['article_id'],
                'research_topic_id' => (int)$row['rt_id'],
                'verified' => true,
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];

            $this->insertBatch('article_research_topics', $batch);
        }

        if ($batch) {
            DB::table('article_research_topics')->insert($batch);
        }

        $this->info('article_research_topics done.');
    }

    protected function migratePAdministration(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("p_administration.csv not found, skipping.");
            return;
        }

        $this->info("Migrating p_administration...");

        DB::table('article_administration_methods')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'                       => (int)$row['id'],
                'article_id'               => (int)$row['article_id'],
                'administration_method_id' => (int)$row['administration_id'],
                'verified' => true,
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];

            $this->insertBatch('article_administration_methods', $batch);
        }

        if ($batch) {
            DB::table('article_administration_methods')->insert($batch);
        }

        $this->info('article_administration_methods done.');
    }

    protected function migratePBiomaker(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            $this->warn("p_biomaker.csv not found, skipping.");
            return;
        }

        $this->info("Migrating p_biomaker...");

        DB::table('p_biomaker')->truncate();

        $batch = [];

        foreach ($this->readCsv($csvPath) as $row) {
            $batch[] = [
                'id'         => (int)$row['id'],
                'article_id' => (int)$row['article_id'],
                'cat_id'     => isset($row['cat_id']) && $row['cat_id'] !== '' ? (int)$row['cat_id'] : null,
                'sub_id'     => isset($row['sub_id']) && $row['sub_id'] !== '' ? (int)$row['sub_id'] : null,
                // ✅ now protected against garbage like " 255"
                'created_at'     => $this->normalizeDateTimeOrNow($row['created_at'] ?? null),
                'updated_at'     => $this->normalizeDateTimeOrNow($row['updated_at'] ?? null),
            ];

            $this->insertBatch('p_biomaker', $batch);
        }

        if ($batch) {
            DB::table('p_biomaker')->insert($batch);
        }

        $this->info('p_biomaker done.');
    }
}
