NWD PANEL — SMM services panel
================================

Stack: PHP 8+ / MySQL / Apache. Test locally before deployment.

LOCAL INSTALL
1. Install XAMPP (Apache, PHP 8+, MySQL).
2. Copy the SMM_PRO_Legitimated_Panel folder into C:/xampp/htdocs/.
3. Create/import the database using database.sql in phpMyAdmin.
4. Review config.php and set the database name/user/password for your environment.
5. For multi-provider support, review and run migration_multi_platform.sql against the same database.
6. Open the project using the matching local URL, for example http://localhost/SMM_PRO_Legitimated_Panel/.
7. Register a user and open /admin/login.php for the admin area. Verify/reset admin credentials in the database before use.

WHAT WAS UPDATED IN THIS BUILD
- Branding text changed from MGL PANEL / MGL to NWD PANEL / NWD.
- Existing page layout/components were retained.
- Premium dark theme palette applied: #0B0F19 background, #111827 surfaces,
  #00D4FF cyan accent, #7C3AED purple, #F8FAFC text, #94A3B8 secondary text,
  #22C55E success and #EF4444 error/cancel.
- The project already includes customer pages and multi-provider integration files;
  inspect CHANGES_README.md for its multi-provider migration and setup notes.

IMPORTANT — NOT YET PRODUCTION-READY
This is a codebase/theme update, not a verified production deployment. I cannot
activate your domain, hosting, SSL, or run a real provider test order without
hosting access, valid provider API credentials, and a payment-provider account.
The payment and provider integrations must be tested in your own staging setup.
Before accepting real users or money, verify authentication/authorization, CSRF
protection, input validation, rate limits, API-key encryption/access controls,
transaction integrity, webhook signatures, logging, backups, HTTPS, and admin
passwords. Never use real API secrets in public source control. Configure cron
for order/service syncing only after reviewing its security settings.

Use only lawful, platform-compliant social media marketing services. Do not sell
fake engagement, spam, compromised accounts, or services intended to bypass
platform safeguards.
