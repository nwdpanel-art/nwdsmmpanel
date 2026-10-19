# Multi-Platform + Multi-Provider Update

## Kya add hua

1. **`migration_multi_platform.sql`** — sabse pehle yeh apni database
   pe run karein. Yeh naya `platforms` table, naya `providers` table
   (aapke purane `api_settings` se data auto-copy karta hai), aur
   `services` table me `platform_id` + `provider_id` columns add
   karta hai. Existing services ko keyword-match se platform assign
   kar deta hai (jo match na hon wo "Other" me chali jayengi — baad
   me `admin/services.php` se manually reassign kar sakte hain).

2. **Customer side:**
   - `platforms.php` (naya) — "New Order" ab yahan khulta hai:
     platform cards (Instagram/TikTok/Facebook/YouTube/etc), sirf
     wahi platforms show hote hain jinke paas active services hon.
   - `order.php` — ab `?platform=slug` leta hai, sirf uss platform
     ki services dropdown me dikhata hai (category step hata diya
     gaya hai — bilkul jaisa aapne bataya tha).
   - `services.php` ke "Order Now" links automatically sahi
     platform ke sath link karte hain.

3. **Admin side:**
   - `admin/providers.php` (naya) — multiple provider APIs add/
     edit/disable/delete/test kar sakte hain (JAP, ZSMMPanel,
     SMMCoder, koi bhi). Har provider ki apni USD→PKR rate aur
     markup % hoti hai.
   - `admin/import_services.php` — ab import shuru karne se pehle
     do dropdowns dikhte hain: (1) **konse provider se import
     karna hai**, (2) **konse platform pe assign karna hai**
     (ya "Auto-detect" chhod dein — category/name text se guess
     karega).
   - `admin/services.php` — Add Service form me ab **Platform**
     aur **Provider** dropdown bhi hain, aur listing table me yeh
     dono columns show hote hain.
   - Purana `admin/api_settings.php` ab sirf `providers.php` pe
     redirect karta hai (koi purana bookmark toot na jaye).

## Setup steps

1. `migration_multi_platform.sql` ko phpMyAdmin ya `mysql` CLI se
   apni `smm_panel` database pe run karein.
2. `admin/providers.php` pe jayein — dekh lein ke aapki purani
   API details naye `providers` row me aa gayi hain (migration
   auto-copy karta hai). Agar nahi to manually add kar dein.
3. Zaroorat ho to `admin/services.php` se kisi service ka platform
   theek karein (agar auto-detect galat guess kare).
4. Multiple provider add karne ke liye `admin/providers.php` pe
   "Add New Provider" form use karein, phir
   `admin/import_services.php` pe jaake wahi provider select karke
   import karein.

## Note

- Import ka provider-matching ab `provider_service_id` **+**
  `provider_id` dono se hota hai — is se agar do alag providers
  ki service IDs coincidentally same hon to bhi mix-up nahi hoga.
- Naye orders provider-aware hain: `services.provider_id` dekh kar
  hi sahi API pe order bheja jata hai, isliye ab alag-alag platforms
  ki services alag-alag providers se aa sakti hain bina kisi
  conflict ke.


## Follow-up maintenance update (2026-10-15)
- Replaced the separate admin sidebars with one shared `admin/_sidebar.php` navigation component so the same admin options appear on all admin pages.
- Made the admin sidebar vertically scrollable on desktop and horizontally navigable on small screens; active page highlighting is automatic.
- Removed the day/night toggle behavior and light-mode overrides. The site now uses one fixed Premium Dark theme with the requested `#0B0F19`, `#111827`, `#00D4FF`, `#7C3AED`, `#F8FAFC`, `#94A3B8`, `#22C55E`, and `#EF4444` palette.
- PHP syntax checks were run on the packaged PHP files. This is a source-code review/update, not a live-hosting or payment/provider integration certification.
- Before production use, configure real database credentials and provider/payment credentials securely via server environment variables, apply the supplied SQL migrations, configure HTTPS, and test provider API orders and callbacks on the target host.
