# WebMaker Click Stats

Privacy-friendly contact statistics for WordPress sites built by WebMaker Agency.

Counts, in the site's **own database** (no third party, no cookies, no IP stored):

- phone (`tel:`), WhatsApp and email (`mailto:`) clicks, with the place on the page (header / footer / popup / floating / page)
- form opened / sent (Elementor forms and popups, Contact Form 7, WPForms)
- WooCommerce: added to cart, checkout page, orders (recorded server-side)
- traffic source label per action (Google Ads, Google organic, Facebook, Instagram, ChatGPT, TikTok, direct...) from the referrer / UTM of that page

Shown in **WP admin → Click Stats** (per month, per source, where clicked, last 50 actions) and as a dashboard widget. Admin texts are Greek on a Greek admin, otherwise English.

A click on a phone number shows *intent*, not that the call happened.

## Install

Upload the zip from the latest release in Plugins → Add New → Upload, activate. The table `{prefix}wm_clicks` is created on first load.
If the site already has the old Clean Me table `{prefix}cm_clicks`, it is kept and used (no data lost).

Remove: deactivate and delete the plugin. The table stays (drop it by hand if wanted).

## Notes

- Bots and previews are ignored; max 40 hits a minute per visitor (IP only hashed for one minute in a transient).
- Optional: if `gtag()` exists on the page the same actions are sent to GA4 as events (`click_call`, `click_whatsapp`, `click_email`, `form_open`, `begin_checkout_page`).
- Does not store anything on the visitor's device except a `sessionStorage` flag on the checkout page (once per session).

Version 1.0.0 — first generic version, derived from the Clean Me click log (16/11/2026).
