<?php
/**
 * Plugin_Cookies — WordPress plugin & service cookie database.
 *
 * 600+ entries covering 400+ plugins, themes, and third-party services.
 * Lookup tiers: exact → prefix (longest first) → regex.
 *
 * @package NahnuAS
 */

namespace NahnuAS;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Plugin_Cookies {

	private static array $exact = [

		// ═══════════════════════════════════════════════════════════════════
		// WORDPRESS CORE
		// ═══════════════════════════════════════════════════════════════════
		'wordpress_test_cookie'            => ['WordPress','necessary',  'Checks browser cookie support.'],
		'wp_lang'                          => ['WordPress','preferences','Admin UI language selection.'],
		'wordpress_gdpr_allowed_services'  => ['WordPress','necessary',  'GDPR service consent.'],
		'wp_user_level'                    => ['WordPress','necessary',  'Legacy user role cookie.'],

		// ═══════════════════════════════════════════════════════════════════
		// WOOCOMMERCE ECOSYSTEM
		// ═══════════════════════════════════════════════════════════════════
		'woocommerce_cart_hash'            => ['WooCommerce','necessary',  'Cart contents hash.'],
		'woocommerce_items_in_cart'        => ['WooCommerce','necessary',  'Cart has items indicator.'],
		'woocommerce_recently_viewed'      => ['WooCommerce','preferences','Recently viewed products.'],
		'wc_cart_created'                  => ['WooCommerce','necessary',  'Cart creation timestamp.'],
		'store_notice1'                    => ['WooCommerce','necessary',  'Store notice dismissed.'],
		'woocommerce_session_'             => ['WooCommerce','necessary',  'WooCommerce session.'],
		// Subscriptions
		'wcs_cart'                         => ['WooCommerce Subscriptions','necessary','Subscription cart.'],
		// Bookings
		'wc_bookings_cart'                 => ['WooCommerce Bookings','necessary','Bookings cart item.'],
		// Stripe
		'_wc_stripe_session'               => ['WooCommerce Stripe','necessary','Stripe session.'],
		// PayPal
		'_wc_paypal_session'               => ['WooCommerce PayPal','necessary','PayPal session.'],
		// Square
		'_wc_square_session'               => ['WooCommerce Square','necessary','Square session.'],
		// Amazon Pay
		'amazon_Login_state_cache'         => ['WooCommerce Amazon Pay','necessary','Amazon Pay state.'],
		// Klarna
		'klarna_checkout_order_id'         => ['Klarna','necessary','Klarna order in progress.'],
		// Afterpay
		'afterpay_checkout'                => ['Afterpay','necessary','Afterpay checkout session.'],

		// YITH Plugins
		'yith_wcwl_products'               => ['YITH Wishlist','preferences','Wishlist items.'],
		'yith_wcan_session_'               => ['YITH Ajax Filter','necessary','Filter session.'],
		'yith_wcmbs_member'                => ['YITH Membership','necessary','Membership state.'],
		'yith_wcet_ticket'                 => ['YITH Event Tickets','necessary','Event ticket session.'],
		'yith_wcbs_bundle'                 => ['YITH Product Bundles','necessary','Bundle session.'],

		// ═══════════════════════════════════════════════════════════════════
		// PAYMENT GATEWAYS
		// ═══════════════════════════════════════════════════════════════════
		'__stripe_mid'                     => ['Stripe','necessary','Stripe fraud prevention.'],
		'__stripe_sid'                     => ['Stripe','necessary','Stripe session ID.'],
		'm'                                => ['Stripe','necessary','Stripe device identifier.'],
		'ts'                               => ['PayPal','necessary','PayPal fraud token.'],
		'enforce_policy'                   => ['PayPal','necessary','PayPal policy enforcement.'],
		'tsrce'                            => ['PayPal','necessary','PayPal source tracking.'],
		'x-pp-s'                           => ['PayPal','necessary','PayPal session.'],
		'mollie_checkout_id'               => ['Mollie Payments','necessary','Mollie checkout ID.'],
		'_payfast_session'                 => ['PayFast','necessary','PayFast checkout session.'],
		'spreedly_payment_method_token'    => ['Spreedly','necessary','Spreedly payment token.'],
		'braintree_session'                => ['Braintree (PayPal)','necessary','Braintree session.'],
		'authorize_net_cim_nonce'          => ['Authorize.Net','necessary','Authorize.Net nonce.'],

		// ═══════════════════════════════════════════════════════════════════
		// EASY DIGITAL DOWNLOADS
		// ═══════════════════════════════════════════════════════════════════
		'edd_items_in_cart'                => ['Easy Digital Downloads','necessary','EDD cart indicator.'],
		'edd_user_address'                 => ['Easy Digital Downloads','necessary','EDD cached address.'],

		// ═══════════════════════════════════════════════════════════════════
		// SURECART
		// ═══════════════════════════════════════════════════════════════════
		'sc_session'                       => ['SureCart','necessary','SureCart checkout session.'],

		// ═══════════════════════════════════════════════════════════════════
		// MEMBERSHIP & SUBSCRIPTIONS
		// ═══════════════════════════════════════════════════════════════════
		'memberpress_login_'               => ['MemberPress','necessary','MemberPress auth session.'],
		'mp_account'                       => ['MemberPress','necessary','MemberPress account state.'],
		'pmpro_checkout'                   => ['Paid Memberships Pro','necessary','PMPro checkout.'],
		'rcp_user_subscription'            => ['Restrict Content Pro','necessary','RCP subscription.'],
		's2member_login_attempt_'          => ['s2Member','necessary','s2Member login throttle.'],
		'wlm_login'                        => ['WishList Member','necessary','WishList auth state.'],
		'profilepress_auth'                => ['ProfilePress','necessary','ProfilePress auth token.'],
		'um_user_id'                       => ['Ultimate Member','necessary','UM user session.'],
		'um_secure_admin_nonce'            => ['Ultimate Member','necessary','UM admin nonce.'],
		'peters_loginizer'                 => ['LoginWP','necessary','LoginWP redirect state.'],
		'wps-hide-login'                   => ['WPS Hide Login','necessary','Custom login URL.'],
		'accessally_progress'              => ['AccessAlly','necessary','AccessAlly course progress.'],
		'wishlistpro_login'                => ['WishList Pro','necessary','WishList Pro auth.'],

		// ═══════════════════════════════════════════════════════════════════
		// LMS / COURSES
		// ═══════════════════════════════════════════════════════════════════
		'learndash_course_progress'        => ['LearnDash','necessary','Course progress.'],
		'learndash_quiz_attempt'           => ['LearnDash','necessary','Quiz attempt data.'],
		'llms_login_redirect'              => ['LifterLMS','necessary','LifterLMS login redirect.'],
		'llms_checkout'                    => ['LifterLMS','necessary','LifterLMS checkout.'],
		'tutor_order_redirect'             => ['Tutor LMS','necessary','Tutor redirect.'],
		'tutor_quiz'                       => ['Tutor LMS','necessary','Tutor quiz session.'],
		'sfwd_quiz_attempt'                => ['LearnDash','necessary','LearnDash quiz attempt.'],
		'masteriyo_cart'                   => ['Masteriyo LMS','necessary','Masteriyo cart.'],
		'lp_user_session'                  => ['LearnPress','necessary','LearnPress user session.'],
		'coursepress_'                     => ['CoursePress','necessary','CoursePress session.'],
		'sensei_purchase_redirect'         => ['Sensei LMS','necessary','Sensei redirect.'],
		'woothemes_sensei'                 => ['Sensei LMS','necessary','Sensei session.'],

		// ═══════════════════════════════════════════════════════════════════
		// COMMUNITY & SOCIAL
		// ═══════════════════════════════════════════════════════════════════
		'bp-activity-oldestpage'           => ['BuddyPress','necessary','Activity pagination.'],
		'bp_new_page'                      => ['BuddyPress','necessary','Page tracking.'],
		'bpb_session'                      => ['BuddyBoss','necessary','BuddyBoss session.'],
		'bbp_visit_counts'                 => ['bbPress','analytics','Forum visit count.'],
		'bbp_user_session'                 => ['bbPress','necessary','bbPress user session.'],
		'peepso_session'                   => ['PeepSo','necessary','PeepSo community session.'],
		'wp_foro_session'                  => ['wpForo','necessary','wpForo session.'],
		'asgaros_forum_session'            => ['Asgaros Forum','necessary','Asgaros session.'],
		'discourse_sso'                    => ['WP Discourse','necessary','Discourse SSO token.'],

		// ═══════════════════════════════════════════════════════════════════
		// EVENTS
		// ═══════════════════════════════════════════════════════════════════
		'tribe_events_view'                => ['The Events Calendar','preferences','Events view preference.'],
		'tribe_tickets_arsevent'           => ['Event Tickets','necessary','Ticket cart event ID.'],
		'ee_session'                       => ['Event Espresso','necessary','Event Espresso session.'],
		'em_cart'                          => ['Events Manager','necessary','Events Manager cart.'],
		'mec_session'                      => ['Modern Events Calendar','necessary','MEC session.'],

		// ═══════════════════════════════════════════════════════════════════
		// PAGE BUILDERS
		// ═══════════════════════════════════════════════════════════════════
		'elementor'                        => ['Elementor','necessary','Elementor editor state.'],
		'divi_state'                       => ['Divi','preferences','Divi builder state.'],
		'divi_mobile'                      => ['Divi','preferences','Divi mobile detection.'],
		'fl_builder_cache'                 => ['Beaver Builder','necessary','Beaver Builder cache.'],
		'brizy_editor'                     => ['Brizy','necessary','Brizy editor session.'],
		'oxygen_vsb_last_save'             => ['Oxygen Builder','necessary','Oxygen last save time.'],
		'bricks_editor'                    => ['Bricks Builder','necessary','Bricks editor session.'],
		'ct_session'                       => ['Cornerstone (X Theme)','necessary','Cornerstone session.'],
		'vc_action_nonce'                  => ['WPBakery Page Builder','necessary','WPBakery nonce.'],
		'generateblocks_editor'            => ['GenerateBlocks','necessary','GenerateBlocks editor.'],
		'kadence_editor'                   => ['Kadence Blocks','necessary','Kadence editor state.'],

		// ═══════════════════════════════════════════════════════════════════
		// MULTILINGUAL
		// ═══════════════════════════════════════════════════════════════════
		'wpml_browser_redirect_test'       => ['WPML','necessary','WPML redirect test.'],
		'_icl_current_language'            => ['WPML','preferences','WPML active language.'],
		'icl_current_language'             => ['WPML','preferences','WPML language.'],
		'pll_language'                     => ['Polylang','preferences','Polylang language.'],
		'trp_language'                     => ['TranslatePress','preferences','TranslatePress language.'],
		'_weglot'                          => ['Weglot','preferences','Weglot language.'],
		'weglot_lang'                      => ['Weglot','preferences','Weglot active language.'],
		'mlp_language'                     => ['MultilingualPress','preferences','MLP language.'],
		'loco_lang'                        => ['Loco Translate','preferences','Loco Translate preview.'],
		'qtranslate_'                      => ['qTranslate-XT','preferences','qTranslate language.'],

		// ═══════════════════════════════════════════════════════════════════
		// SEO PLUGINS
		// ═══════════════════════════════════════════════════════════════════
		'wpseo_visited'                    => ['Yoast SEO','necessary','Yoast SEO admin flag.'],
		'rank_math_visited'                => ['Rank Math','necessary','Rank Math admin flag.'],
		'aioseo_visit'                     => ['All in One SEO','necessary','AIOSEO admin flag.'],
		'seopress_consent'                 => ['SEOPress','necessary','SEOPress consent.'],
		'slim_seo_consent'                 => ['Slim SEO','necessary','Slim SEO consent.'],
		'tss_ga_disabled'                  => ['The SEO Framework','analytics','TSF GA disabled.'],

		// ═══════════════════════════════════════════════════════════════════
		// CACHING
		// ═══════════════════════════════════════════════════════════════════
		'rocket_has_js'                    => ['WP Rocket','necessary','WP Rocket JS detection.'],
		'lscache_vary'                     => ['LiteSpeed Cache','necessary','LiteSpeed vary key.'],
		'w3tc_logged_in'                   => ['W3 Total Cache','necessary','W3TC logged-in.'],
		'wpfc_cache_not_served'            => ['WP Fastest Cache','necessary','WPFC cache bypass.'],
		'wpo_wpc_logged_in'                => ['WP Optimize','necessary','WPO logged-in flag.'],
		'comet_cache_uuid'                 => ['Comet Cache','necessary','Comet Cache user ID.'],
		'swift_cache_'                     => ['Swift Performance','necessary','Swift cache flag.'],
		'hummingbird_cache'                => ['Hummingbird (WPMU Dev)','necessary','Hummingbird flag.'],
		'sg_cachepress_id'                 => ['SiteGround Optimizer','necessary','SG cache ID.'],
		'wpe-auth'                         => ['WP Engine','necessary','WP Engine auth.'],
		'STYXKEY'                          => ['Pantheon','necessary','Pantheon cache bypass.'],
		'kinsta-cache-zone'                => ['Kinsta','necessary','Kinsta cache zone.'],
		'flywheel-no-cache'                => ['Flywheel','necessary','Flywheel cache bypass.'],
		'pressable_cache'                  => ['Pressable','necessary','Pressable cache flag.'],

		// ═══════════════════════════════════════════════════════════════════
		// SECURITY
		// ═══════════════════════════════════════════════════════════════════
		'wordfence_verifiedHuman'          => ['Wordfence','necessary','Wordfence bot check.'],
		'wf_loginalerted_'                 => ['Wordfence','necessary','Wordfence login alert.'],
		'wf_session'                       => ['Wordfence','necessary','Wordfence session.'],
		'itsec_new_user'                   => ['Solid Security','necessary','Solid Security new user.'],
		'itsec_lockout'                    => ['Solid Security','necessary','Solid Security lockout.'],
		'sucuri_cloudproxy_uuid'           => ['Sucuri','necessary','Sucuri WAF visitor ID.'],
		'aios_visitor'                     => ['All In One Security','necessary','AIOS visitor fingerprint.'],
		'shield_sec'                       => ['Shield Security','necessary','Shield Security session.'],
		'cerber_session'                   => ['WP Cerber Security','necessary','Cerber session.'],
		'cleantalk_pixel_session'          => ['CleanTalk Anti-Spam','necessary','CleanTalk session.'],
		'rsssl_all_cached'                 => ['Really Simple SSL','necessary','RSSL detection cache.'],
		'rsssl_consent'                    => ['Really Simple Security','necessary','RSSL consent state.'],
		'malcare_session'                  => ['MalCare Security','necessary','MalCare session.'],
		'wpns_challenge'                   => ['Melapress Login Security','necessary','Login challenge.'],
		'login_sec_nonce'                  => ['Login Security Solution','necessary','Login nonce.'],

		// ═══════════════════════════════════════════════════════════════════
		// JETPACK
		// ═══════════════════════════════════════════════════════════════════
		'jetpackState'                     => ['Jetpack','necessary','Jetpack state.'],
		'jetpack_sso_remember_me'          => ['Jetpack','necessary','Jetpack SSO.'],
		'jpp_math_pass'                    => ['Jetpack Protect','necessary','Jetpack bot math.'],

		// ═══════════════════════════════════════════════════════════════════
		// FORMS
		// ═══════════════════════════════════════════════════════════════════
		'wpcf7_redirect_cache'             => ['Contact Form 7','necessary','CF7 redirect.'],
		'wpforms_lead_'                    => ['WPForms','necessary','WPForms lead data.'],
		'gform_pending_submissions_key'    => ['Gravity Forms','necessary','GF pending key.'],
		'fluentform_visitor_id'            => ['Fluent Forms','analytics','FF anonymous visitor.'],
		'frm_trans_id'                     => ['Formidable Forms','necessary','Formidable transaction.'],
		'ninja_forms_session'              => ['Ninja Forms','necessary','Ninja Forms session.'],
		'hf_session'                       => ['HappyForms','necessary','HappyForms session.'],
		'everest_forms_'                   => ['Everest Forms','necessary','Everest Forms data.'],
		'ws_form_session'                  => ['WS Form','necessary','WS Form session.'],
		'caldera_forms_'                   => ['Caldera Forms','necessary','Caldera Forms data.'],
		'mc4wp_form'                       => ['MC4WP Mailchimp Forms','necessary','MC4WP form submission.'],
		'hubspot_form_'                    => ['HubSpot Forms','marketing','HubSpot form data.'],

		// ═══════════════════════════════════════════════════════════════════
		// NEWSLETTER & EMAIL MARKETING
		// ═══════════════════════════════════════════════════════════════════
		'mailpoet_subscriber'              => ['MailPoet','marketing','MailPoet subscriber.'],
		'newsletter_consent'               => ['The Newsletter Plugin','marketing','Newsletter consent.'],
		'mailster_subscriber'              => ['Mailster','marketing','Mailster subscriber.'],
		'mc4wp_landing'                    => ['MC4WP','marketing','Mailchimp landing page.'],
		'ck_subscriber_id'                 => ['ConvertKit','marketing','ConvertKit subscriber.'],
		'_drip_client_id'                  => ['Drip','marketing','Drip CRM client ID.'],
		'omnisendSessionID'                => ['Omnisend','marketing','Omnisend session.'],
		'omnisendContactID'                => ['Omnisend','marketing','Omnisend contact.'],
		'sib_cuid'                         => ['Brevo (Sendinblue)','marketing','Brevo contact ID.'],
		'ac_enable_tracking'               => ['ActiveCampaign','marketing','ActiveCampaign tracking.'],
		'__kla_id'                         => ['Klaviyo','marketing','Klaviyo customer ID.'],
		'aweber_visitor_id'                => ['AWeber','marketing','AWeber visitor.'],
		'getresponse_visitor'              => ['GetResponse','marketing','GetResponse visitor.'],
		'moosend_session'                  => ['Moosend','marketing','Moosend session.'],
		'sender_session'                   => ['Sender.net','marketing','Sender.net session.'],
		'fluentcrm_visitor'                => ['FluentCRM','marketing','FluentCRM visitor.'],
		'_atuvc'                           => ['AddThis','marketing','AddThis sharing counter.'],
		'_atuvs'                           => ['AddThis','marketing','AddThis session share.'],

		// ═══════════════════════════════════════════════════════════════════
		// POPUPS & LEAD GENERATION
		// ═══════════════════════════════════════════════════════════════════
		'pum-open'                         => ['Popup Maker','preferences','Popup open state.'],
		'pum_sub_data'                     => ['Popup Maker','necessary','Popup Maker subscription.'],
		'om_user_id'                       => ['OptinMonster','marketing','OptinMonster visitor.'],
		'tve_refers'                       => ['Thrive Leads','analytics','Thrive referral.'],
		'tve_tcm'                          => ['Thrive Comments','analytics','Thrive Comments.'],
		'sumo_visitors'                    => ['Sumo','analytics','Sumo visitor.'],
		'sleeknote_visited'                => ['Sleeknote','marketing','Sleeknote visitor.'],
		'privy_user'                       => ['Privy','marketing','Privy visitor.'],
		'convertflow_visitor'              => ['ConvertFlow','marketing','ConvertFlow visitor.'],
		'wisepops_'                        => ['Wisepops','marketing','Wisepops visitor.'],
		'getsitecontrol_'                  => ['GetSiteControl','marketing','GetSiteControl visitor.'],
		'unbounce_'                        => ['Unbounce','analytics','Unbounce visitor.'],
		'instapage_session'                => ['Instapage','analytics','Instapage session.'],

		// ═══════════════════════════════════════════════════════════════════
		// AFFILIATE & TRACKING
		// ═══════════════════════════════════════════════════════════════════
		'affwp_ref'                        => ['AffiliateWP','marketing','AffiliateWP referral.'],
		'affwp_campaign'                   => ['AffiliateWP','marketing','AffiliateWP campaign.'],
		'PAPVisitorId'                     => ['Post Affiliate Pro','marketing','PAP visitor ID.'],
		'easy_affiliate_click'             => ['Easy Affiliate','marketing','Easy Affiliate click.'],
		'solid_affiliate_referral'         => ['Solid Affiliate','marketing','Solid Affiliate ref.'],
		'slicewp_referral'                 => ['SliceWP','marketing','SliceWP referral.'],
		'wpa_ref'                          => ['WP Affiliate Platform','marketing','WP Affiliate ref.'],
		'tapfiliate_click_id'              => ['Tapfiliate','marketing','Tapfiliate click ID.'],

		// ═══════════════════════════════════════════════════════════════════
		// BOOKING & APPOINTMENTS
		// ═══════════════════════════════════════════════════════════════════
		'bookly_basket'                    => ['Bookly','necessary','Bookly basket.'],
		'amelia_locale'                    => ['Amelia','preferences','Amelia locale.'],
		'amelia_employee'                  => ['Amelia','preferences','Amelia employee.'],
		'wpbc_bookings'                    => ['WP Booking Calendar','necessary','WPBC session.'],
		'ssa_session'                      => ['Simply Schedule Appointments','necessary','SSA session.'],
		'motopress_hotel'                  => ['MotoPress Hotel Booking','necessary','Hotel booking.'],
		'lp_booking'                       => ['LatePoint','necessary','LatePoint booking.'],
		'startbooking_session'             => ['StartBooking','necessary','StartBooking session.'],
		'fcrm_booking'                     => ['FluentBooking','necessary','FluentBooking session.'],
		'woo_appointments_cart'            => ['WooCommerce Appointments','necessary','WC appointments.'],

		// ═══════════════════════════════════════════════════════════════════
		// DONATIONS
		// ═══════════════════════════════════════════════════════════════════
		'give_session_'                    => ['Give (WP Donation)','necessary','Give donation session.'],
		'charitable_donation_id'           => ['Charitable','necessary','Charitable donation.'],
		'gs_donation_session'              => ['GiveWP (Stripe)','necessary','GiveWP session.'],
		'leaky_paywall_subscription'       => ['Leaky Paywall','necessary','Leaky Paywall state.'],

		// ═══════════════════════════════════════════════════════════════════
		// JOBS
		// ═══════════════════════════════════════════════════════════════════
		'job-manager-'                     => ['WP Job Manager','necessary','Job Manager data.'],
		'resumemanager_resume_saved'       => ['WP Resume Manager','necessary','Resume saved flag.'],
		'indeed_jobs_session'              => ['Indeed Jobs','necessary','Indeed session.'],
		'bj_applied_jobs'                  => ['Job Board WP','preferences','Applied jobs.'],
		'wpjb_session'                     => ['WP Job Board','necessary','WP Job Board session.'],
		'careerfy_application'             => ['Careerfy','necessary','Careerfy application.'],

		// ═══════════════════════════════════════════════════════════════════
		// ANALYTICS
		// ═══════════════════════════════════════════════════════════════════
		'_ga'                              => ['Google Analytics','analytics','GA unique visitor.'],
		'_gid'                             => ['Google Analytics','analytics','GA 24h user.'],
		'_gat'                             => ['Google Analytics','analytics','GA throttle.'],
		'monsterinsights_uid'              => ['MonsterInsights','analytics','MonsterInsights ID.'],
		'wp_statistics_hits'               => ['WP Statistics','analytics','WP Statistics hits.'],
		'wps_session'                      => ['WP Statistics','analytics','WP Statistics session.'],
		'clicky'                           => ['Clicky','analytics','Clicky visitor.'],
		'_jsuid'                           => ['Clicky','analytics','Clicky UID.'],
		'burst_total_pageviews'            => ['Burst Statistics','analytics','Burst pageviews.'],
		'ia_session'                       => ['Independent Analytics','analytics','IA session.'],
		'pa_s'                             => ['Plausible Analytics','analytics','Plausible session.'],
		'fathom_session'                   => ['Fathom Analytics','analytics','Fathom session.'],
		'exactmetrics_'                    => ['ExactMetrics','analytics','ExactMetrics data.'],
		'CONSENT'                          => ['Google','necessary','Google consent state.'],
		'SOCS'                             => ['Google','necessary','Google consent mgmt.'],
		'AEC'                              => ['Google','necessary','Google anti-abuse.'],
		'NID'                              => ['Google','preferences','Google preferences.'],
		'1P_JAR'                           => ['Google Ads','marketing','Google ad cookie.'],
		'__gads'                           => ['Google AdSense','marketing','AdSense targeting.'],
		'__gpi'                            => ['Google AdSense','marketing','Google Publisher tag.'],
		'_hjid'                            => ['Hotjar','analytics','Hotjar visitor.'],
		'_hjFirstSeen'                     => ['Hotjar','analytics','Hotjar first visit.'],
		'_hjAbsoluteSessionInProgress'     => ['Hotjar','analytics','Hotjar active session.'],
		'_pk_ref'                          => ['Matomo','analytics','Matomo referral.'],
		'_pk_cvar'                         => ['Matomo','analytics','Matomo custom vars.'],
		'woopra_session'                   => ['Woopra','analytics','Woopra session.'],
		'wooTracker'                       => ['Woopra','analytics','Woopra tracker.'],
		'ajs_user_id'                      => ['Segment','analytics','Segment user ID.'],
		'ajs_anonymous_id'                 => ['Segment','analytics','Segment anonymous ID.'],
		'_lo_uid'                          => ['Lucky Orange','analytics','Lucky Orange visitor.'],
		'_lo_v'                            => ['Lucky Orange','analytics','Lucky Orange visits.'],
		'fs_uid'                           => ['FullStory','analytics','FullStory session.'],
		'fs_lua'                           => ['FullStory','analytics','FullStory activity.'],
		'mouseflow'                        => ['Mouseflow','analytics','Mouseflow visitor.'],
		'mf_user'                          => ['Mouseflow','analytics','Mouseflow user.'],
		'_insp_uid'                        => ['Inspectlet','analytics','Inspectlet user.'],
		'_insp_sess'                       => ['Inspectlet','analytics','Inspectlet session.'],
		'_insp_nv'                         => ['Inspectlet','analytics','Inspectlet new visitor.'],
		'SL_C_'                            => ['Smartlook','analytics','Smartlook visitor.'],
		'_pendo_meta'                      => ['Pendo','analytics','Pendo meta.'],
		'_pendo_visitorId'                 => ['Pendo','analytics','Pendo visitor.'],
		'_vwo_uuid'                        => ['VWO','analytics','VWO visitor UUID.'],
		'_vis_opt_s'                       => ['VWO','analytics','VWO session.'],
		'_vis_opt_exp_'                    => ['VWO','analytics','VWO experiment.'],
		'_vwo_ds'                          => ['VWO','analytics','VWO data store.'],
		'optimizelyEndUserId'              => ['Optimizely','analytics','Optimizely user ID.'],
		'ABTasty'                          => ['AB Tasty','analytics','AB Tasty visitor.'],
		'ABTastySession'                   => ['AB Tasty','analytics','AB Tasty session.'],
		'_conv_v'                          => ['Convert.com','analytics','Convert visitor.'],
		'_conv_s'                          => ['Convert.com','analytics','Convert session.'],
		'cebs'                             => ['Crazy Egg','analytics','Crazy Egg session.'],
		'cebsp'                            => ['Crazy Egg','analytics','Crazy Egg browser.'],
		'_hp2_id.'                         => ['Heap','analytics','Heap user ID.'],
		'_hp2_ses_props.'                  => ['Heap','analytics','Heap session.'],
		'_gcl_aw'                          => ['Google Ads','marketing','Google Ads click.'],
		'_gcl_dc'                          => ['Google Ads','marketing','Google Ads DoubleClick.'],
		// Adobe Analytics
		's_vi'                             => ['Adobe Analytics','analytics','Adobe visitor ID.'],
		's_fid'                            => ['Adobe Analytics','analytics','Adobe fallback visitor.'],
		's_cc'                             => ['Adobe Analytics','analytics','Adobe cookie check.'],
		'AMCVS_'                           => ['Adobe Experience Cloud','analytics','Adobe MCID new visit.'],
		'AMCV_'                            => ['Adobe Experience Cloud','analytics','Adobe MCID.'],
		's_sq'                             => ['Adobe Analytics','analytics','Adobe previous link.'],

		// ═══════════════════════════════════════════════════════════════════
		// ADVERTISING NETWORKS
		// ═══════════════════════════════════════════════════════════════════
		'_fbp'                             => ['Meta Pixel','marketing','Meta browser ID.'],
		'_fbc'                             => ['Meta Pixel','marketing','Meta last click.'],
		'fr'                               => ['Meta (Facebook)','marketing','Meta ad ID.'],
		'_uetsid'                          => ['Microsoft Ads','marketing','Microsoft UET session.'],
		'_uetvid'                          => ['Microsoft Ads','marketing','Microsoft UET visitor.'],
		'MUID'                             => ['Microsoft','marketing','Microsoft browser ID.'],
		'_ttp'                             => ['TikTok Pixel','marketing','TikTok visitor ID.'],
		'tt_sessionid'                     => ['TikTok','marketing','TikTok session.'],
		'_pinterest_ct_ua'                 => ['Pinterest','marketing','Pinterest tracking.'],
		'_pinterest_sess'                  => ['Pinterest','marketing','Pinterest session.'],
		'_pin_unauth'                      => ['Pinterest','marketing','Pinterest unauthenticated.'],
		'li_sugr'                          => ['LinkedIn','marketing','LinkedIn browser ID.'],
		'bcookie'                          => ['LinkedIn','marketing','LinkedIn browser.'],
		'bscookie'                         => ['LinkedIn','marketing','LinkedIn secure ID.'],
		'liap'                             => ['LinkedIn','marketing','LinkedIn cross-domain.'],
		'twid'                             => ['Twitter/X','marketing','Twitter user ID.'],
		'guest_id'                         => ['Twitter/X','marketing','Twitter guest ID.'],
		'guest_id_ads'                     => ['Twitter/X','marketing','Twitter ads guest.'],
		'sc_at'                            => ['Snapchat','marketing','Snapchat attribution.'],
		'IDE'                              => ['Google DoubleClick','marketing','DoubleClick ad ID.'],
		'DSID'                             => ['Google DoubleClick','marketing','DoubleClick signed-in.'],
		't_gid'                            => ['Taboola','marketing','Taboola visitor ID.'],
		'taboola_usg'                      => ['Taboola','marketing','Taboola usage data.'],
		'OB-USER-TOKEN'                    => ['Outbrain','marketing','Outbrain user token.'],
		'obuid'                            => ['Outbrain','marketing','Outbrain user ID.'],
		'uid'                              => ['Criteo','marketing','Criteo user ID.'],
		'acris'                            => ['Criteo','marketing','Criteo retail sync.'],
		'cto_lwid'                         => ['Criteo','marketing','Criteo lightweight ID.'],
		'TDID'                             => ['The Trade Desk','marketing','Trade Desk user ID.'],
		'TDCPM'                            => ['The Trade Desk','marketing','Trade Desk match.'],
		'uuid2'                            => ['AppNexus (Xandr)','marketing','AppNexus user ID.'],
		'anj'                              => ['AppNexus (Xandr)','marketing','AppNexus journey.'],
		'rpb'                              => ['Rubicon Project','marketing','Rubicon bidder.'],
		'rpx'                              => ['Rubicon Project','marketing','Rubicon extended.'],
		'BAIDUID'                          => ['Baidu','marketing','Baidu user ID.'],
		'BIDUPSID'                         => ['Baidu','marketing','Baidu PSID.'],
		'__qca'                            => ['Quantcast','marketing','Quantcast visitor.'],
		'ljt_reader'                       => ['Sovrn','marketing','Sovrn reader ID.'],
		'ELOQUA'                           => ['Oracle Eloqua','marketing','Eloqua visitor.'],
		'ELQSTATUS'                        => ['Oracle Eloqua','marketing','Eloqua status.'],
		'_mkto_trk'                        => ['Marketo','marketing','Marketo tracker.'],
		'pardot'                           => ['Salesforce Pardot','marketing','Pardot visitor.'],
		'visitor_id'                       => ['Salesforce Pardot','marketing','Pardot visitor ID.'],
		'ss_cid'                           => ['Crazy Egg','marketing','Crazy Egg campaign.'],

		// ═══════════════════════════════════════════════════════════════════
		// SOCIAL SHARING
		// ═══════════════════════════════════════════════════════════════════
		'swfw_session'                     => ['Social Warfare','analytics','Social Warfare session.'],
		'sss_sharing'                      => ['Sassy Social Share','analytics','Sassy Social session.'],
		'addtoany_consent'                 => ['AddToAny','analytics','AddToAny consent.'],
		'sharethis'                        => ['ShareThis','marketing','ShareThis visitor.'],
		'monarch_subscriber'               => ['Monarch (Elegant Themes)','analytics','Monarch social.'],

		// ═══════════════════════════════════════════════════════════════════
		// REVIEWS & RATINGS
		// ═══════════════════════════════════════════════════════════════════
		'wpr_review'                       => ['WP Product Review','analytics','Review viewed.'],
		'judegme_visitor'                  => ['Judge.me','analytics','Judge.me visitor.'],
		'okendo_customer'                  => ['Okendo','analytics','Okendo customer.'],
		'stamped_visitor'                  => ['Stamped.io','analytics','Stamped visitor.'],
		'reviews_io_user'                  => ['Reviews.io','analytics','Reviews.io user.'],
		'trustpilot'                       => ['Trustpilot','marketing','Trustpilot widget.'],
		'_yotpo_visitor_id'                => ['Yotpo','marketing','Yotpo visitor.'],

		// ═══════════════════════════════════════════════════════════════════
		// LIVE CHAT & SUPPORT
		// ═══════════════════════════════════════════════════════════════════
		'tawkUUID'                         => ['Tawk.to','necessary','Tawk.to visitor ID.'],
		'tawk_uuid'                        => ['Tawk.to','necessary','Tawk.to UUID.'],
		'_tawk_sso_key'                    => ['Tawk.to','necessary','Tawk.to SSO key.'],
		'__lc_vt'                          => ['LiveChat','necessary','LiveChat visitor token.'],
		'__lc_cid'                         => ['LiveChat','necessary','LiveChat client ID.'],
		'__lc2_cid'                        => ['LiveChat','necessary','LiveChat 2 client.'],
		'__lc2_cst'                        => ['LiveChat','necessary','LiveChat 2 session.'],
		'tidio_state'                      => ['Tidio','necessary','Tidio state.'],
		'lyro-conversation-id'             => ['Tidio','necessary','Tidio conversation.'],
		'crisp-client'                     => ['Crisp','necessary','Crisp session.'],
		'__zlcmid'                         => ['Zendesk','necessary','Zendesk chat session.'],
		'zdVisitorId'                      => ['Zendesk','necessary','Zendesk visitor.'],
		'fc_widget_session'                => ['Freshchat','necessary','Freshchat session.'],
		'fre_data'                         => ['Freshchat','necessary','Freshchat data.'],
		'intercom-id-'                     => ['Intercom','marketing','Intercom visitor.'],
		'intercom-session-'                => ['Intercom','marketing','Intercom session.'],
		'hubspotutk'                       => ['HubSpot','marketing','HubSpot tracking.'],
		'__hstc'                           => ['HubSpot','marketing','HubSpot 13-month.'],
		'__hssc'                           => ['HubSpot','marketing','HubSpot session.'],
		'__hssrc'                          => ['HubSpot','marketing','HubSpot new session.'],
		'hs_ab_test'                       => ['HubSpot','analytics','HubSpot A/B.'],
		'driftt_aid'                       => ['Drift','marketing','Drift anonymous ID.'],
		'drift_aid'                        => ['Drift','marketing','Drift visitor.'],
		'drift_campaign_refresh'           => ['Drift','marketing','Drift campaign.'],
		'olark_session'                    => ['Olark','necessary','Olark chat session.'],
		'hblid'                            => ['Olark','necessary','Olark visitor ID.'],
		'olfsk'                            => ['Olark','necessary','Olark session key.'],
		'UserMatchHistory'                 => ['LinkedIn','marketing','LinkedIn match history.'],

		// ═══════════════════════════════════════════════════════════════════
		// CONSENT MANAGEMENT PLATFORMS
		// ═══════════════════════════════════════════════════════════════════
		'cookielawinfo-checkbox-'          => ['CookieYes','necessary','CookieYes consent.'],
		'viewed_cookie_policy'             => ['CookieYes','necessary','CookieYes shown flag.'],
		'CookieLawInfoConsent'             => ['CookieYes','necessary','CookieYes record.'],
		'cmplz_consent_status'             => ['Complianz','necessary','Complianz consent.'],
		'cmplz_policy_id'                  => ['Complianz','necessary','Complianz policy ID.'],
		'borlabs-cookie'                   => ['Borlabs Cookie','necessary','Borlabs consent.'],
		'rcb-consent-'                     => ['Real Cookie Banner','necessary','RCB consent.'],
		'CookieConsent'                    => ['Cookiebot','necessary','Cookiebot consent.'],
		'OptanonConsent'                   => ['OneTrust','necessary','OneTrust consent.'],
		'OptanonAlertBoxClosed'            => ['OneTrust','necessary','OneTrust closed.'],
		'osano_consentmanager'             => ['Osano','necessary','Osano consent.'],
		'cookie_notice_accepted'           => ['Cookie Notice','necessary','Cookie Notice consent.'],
		'moove_gdpr_popup'                 => ['GDPR Cookie Consent','necessary','Moove GDPR.'],
		'gdpr_cookie_consent_option'       => ['GDPR Cookie Compliance','necessary','GDPR consent.'],
		'euconsent-v2'                     => ['IAB TCF','necessary','IAB TCF consent string.'],
		'addtl_consent'                    => ['IAB TCF','necessary','IAB additional consent.'],
		'cooked_consent'                   => ['Cooked','necessary','Cooked consent.'],
		'nc_woo_cookie'                    => ['Woo Cookie Consent','necessary','WC cookie consent.'],
		'cookie_consent'                   => ['Generic Consent','necessary','Cookie consent state.'],
		'cookieconsent_status'             => ['Cookie Consent (orestbida)','necessary','orejonscc consent.'],
		'cc_cookie'                        => ['Cookie Consent (orestbida)','necessary','orejonscc data.'],
		'consent_status'                   => ['WP Consent API','necessary','Consent API status.'],

		// ═══════════════════════════════════════════════════════════════════
		// CLOUDFLARE / CDN / INFRASTRUCTURE
		// ═══════════════════════════════════════════════════════════════════
		'__cf_bm'                          => ['Cloudflare','necessary','Cloudflare bot mgmt.'],
		'cf_clearance'                     => ['Cloudflare','necessary','Cloudflare challenge.'],
		'__cflb'                           => ['Cloudflare','necessary','Cloudflare LB.'],
		'__cfruid'                         => ['Cloudflare','necessary','Cloudflare visitor.'],
		'_cfuvid'                          => ['Cloudflare','necessary','Cloudflare rate limit ID.'],

		// ═══════════════════════════════════════════════════════════════════
		// GOOGLE ADDITIONAL
		// ═══════════════════════════════════════════════════════════════════
		'YSC'                              => ['YouTube','marketing','YouTube session.'],
		'VISITOR_INFO1_LIVE'               => ['YouTube','marketing','YouTube viewer data.'],
		'PREF'                             => ['YouTube','preferences','YouTube preferences.'],
		'GPS'                              => ['YouTube','marketing','YouTube location.'],
		'_GRECAPTCHA'                      => ['Google reCAPTCHA','necessary','reCAPTCHA verification.'],
		'__Secure-ENID'                    => ['Google','necessary','Google encrypted ID.'],

		// ═══════════════════════════════════════════════════════════════════
		// ACCESSIBILITY WIDGETS
		// ═══════════════════════════════════════════════════════════════════
		'acsbapp_'                         => ['accessiBe','necessary','accessiBe settings.'],
		'acsbRibbonPosition'               => ['accessiBe','preferences','accessiBe ribbon.'],
		'userway_settings'                 => ['UserWay','preferences','UserWay settings.'],
		'equalweb_settings'                => ['EqualWeb','preferences','EqualWeb settings.'],
		'audioeye_'                        => ['AudioEye','preferences','AudioEye settings.'],

		// ═══════════════════════════════════════════════════════════════════
		// MISCELLANEOUS WORDPRESS PLUGINS
		// ═══════════════════════════════════════════════════════════════════
		'updraftplus'                      => ['UpdraftPlus','necessary','UpdraftPlus session.'],
		'wpmailsmtp_redirect'              => ['WP Mail SMTP','necessary','WP Mail SMTP redirect.'],
		'wp_consent_api'                   => ['WP Consent API','necessary','WP Consent API.'],
		'advanced_ads_browser_width'       => ['Advanced Ads','necessary','Advanced Ads width.'],
		'advads_viewed_ads'                => ['Advanced Ads','analytics','Advanced Ads views.'],
		'tablepress_js_paging'             => ['TablePress','preferences','TablePress paging.'],
		'barn2_product_table'              => ['Barn2 Plugins','preferences','Barn2 table prefs.'],
		'nextendssl_state'                 => ['Nextend Social Login','necessary','OAuth state.'],
		'nextendssl_referrer'              => ['Nextend Social Login','necessary','OAuth referrer.'],
		'dokan_dash'                       => ['Dokan Multivendor','necessary','Dokan dashboard.'],
		'wcfm_session'                     => ['WCFM Marketplace','necessary','WCFM session.'],
		'wpmem_auth'                       => ['WP-Members','necessary','WP-Members auth.'],
		'relevanssi_hits'                  => ['Relevanssi','analytics','Relevanssi hits.'],
		'searchwp_live'                    => ['SearchWP','necessary','SearchWP nonce.'],
		'tribe_tickets_'                   => ['Event Tickets (TEC)','necessary','Event ticket.'],
		'wpsc_'                            => ['WP e-Commerce','necessary','WPSC session.'],
		'mp_session'                       => ['Marketpress','necessary','Marketpress session.'],
		'wpml_referer_lang'                => ['WPML','preferences','WPML referrer lang.'],
		'loco_preview_'                    => ['Loco Translate','preferences','Loco preview.'],
		'wpgdpr_compliance'                => ['WP GDPR Compliance','necessary','WP GDPR consent.'],
		'wccm_cart_token'                  => ['Checkout Manager','necessary','Checkout token.'],
		'geodir_search'                    => ['GeoDirectory','necessary','GeoDirectory search.'],
		'wc_order_attribution'             => ['WooCommerce Attribution','analytics','WC attribution.'],
		'wc_session_expiry'                => ['WooCommerce','necessary','WC session expiry.'],
		// Photo galleries
		'envira_gallery_'                  => ['Envira Gallery','preferences','Envira preferences.'],
		'modula_gallery_'                  => ['Modula Gallery','preferences','Modula preferences.'],
		'foogallery_paged'                 => ['FooGallery','preferences','FooGallery page.'],
		// Slider
		'rs_reset_layer'                   => ['Revolution Slider','preferences','RevSlider state.'],
		'smart_slider'                     => ['Smart Slider 3','preferences','Smart Slider.'],
		// PHP/server
		'phpsessid'                        => ['PHP','necessary','PHP session ID.'],
		'laravel_session'                  => ['Laravel','necessary','Laravel session.'],
		'csrf_token'                       => ['Security','necessary','CSRF token.'],
		'xsrf-token'                       => ['Security','necessary','XSRF token.'],
	];

	// ── Prefix table (longest first) ─────────────────────────────────────────
	private static array $prefixes = [
		// WooCommerce (specific first)
		'wp_woocommerce_session_'          => ['WooCommerce','necessary','WooCommerce cart session.'],
		'wc_session_cookie_'               => ['WooCommerce','necessary','WooCommerce session token.'],
		'woocommerce_session_'             => ['WooCommerce','necessary','WooCommerce session.'],
		'woocommerce_'                     => ['WooCommerce','necessary','WooCommerce cookie.'],
		'wc_'                              => ['WooCommerce','necessary','WooCommerce cookie.'],
		// YITH
		'yith_'                            => ['YITH Plugins','necessary','YITH plugin cookie.'],
		// WordPress core (after WooCommerce)
		'wordpress_logged_in_'             => ['WordPress','necessary','WordPress login.'],
		'wordpress_sec_'                   => ['WordPress','necessary','WordPress secure auth.'],
		'wordpress_'                       => ['WordPress','necessary','WordPress auth.'],
		'wp_settings_'                     => ['WordPress','preferences','WordPress settings.'],
		'wp_postpass_'                     => ['WordPress','necessary','WordPress post password.'],
		'wp-settings-'                     => ['WordPress','preferences','WordPress editor.'],
		'comment_author_'                  => ['WordPress','preferences','Comment author.'],
		'wp_'                              => ['WordPress','necessary','WordPress cookie.'],
		// LMS
		'learndash_'                       => ['LearnDash','necessary','LearnDash cookie.'],
		'sfwd_'                            => ['LearnDash','necessary','LearnDash cookie.'],
		'llms_'                            => ['LifterLMS','necessary','LifterLMS cookie.'],
		'lifterlms_'                       => ['LifterLMS','necessary','LifterLMS cookie.'],
		'tutor_'                           => ['Tutor LMS','necessary','Tutor LMS cookie.'],
		'masteriyo_'                       => ['Masteriyo LMS','necessary','Masteriyo cookie.'],
		'lp_'                              => ['LearnPress','necessary','LearnPress cookie.'],
		'sensei_'                          => ['Sensei LMS','necessary','Sensei cookie.'],
		// Membership
		'memberpress_'                     => ['MemberPress','necessary','MemberPress cookie.'],
		'rcp_'                             => ['Restrict Content Pro','necessary','RCP cookie.'],
		'pmpro_'                           => ['Paid Memberships Pro','necessary','PMPro cookie.'],
		's2member_'                        => ['s2Member','necessary','s2Member cookie.'],
		'wlm_'                             => ['WishList Member','necessary','WishList Member.'],
		'um_'                              => ['Ultimate Member','necessary','Ultimate Member.'],
		'bp_'                              => ['BuddyPress','necessary','BuddyPress cookie.'],
		'bbp_'                             => ['bbPress','necessary','bbPress cookie.'],
		'peepso_'                          => ['PeepSo','necessary','PeepSo cookie.'],
		'wpforo_'                          => ['wpForo','necessary','wpForo cookie.'],
		// Events
		'tribe_'                           => ['The Events Calendar','preferences','Events cookie.'],
		'ee_'                              => ['Event Espresso','necessary','Event Espresso.'],
		'mec_'                             => ['Modern Events Calendar','necessary','MEC cookie.'],
		// Builders
		'elementor_'                       => ['Elementor','necessary','Elementor cookie.'],
		'divi_'                            => ['Divi','preferences','Divi cookie.'],
		'et_'                              => ['Elegant Themes','preferences','Elegant Themes.'],
		'fl_'                              => ['Beaver Builder','necessary','Beaver Builder.'],
		'brizy_'                           => ['Brizy','necessary','Brizy cookie.'],
		'bricks_'                          => ['Bricks Builder','necessary','Bricks cookie.'],
		'trp_'                             => ['TranslatePress','preferences','TranslatePress.'],
		'wpml_'                            => ['WPML','preferences','WPML cookie.'],
		'icl_'                             => ['WPML','preferences','WPML language.'],
		// SEO
		'wpseo_'                           => ['Yoast SEO','necessary','Yoast cookie.'],
		'rank_math_'                       => ['Rank Math','necessary','Rank Math cookie.'],
		'aioseo_'                          => ['All in One SEO','necessary','AIOSEO cookie.'],
		'seopress_'                        => ['SEOPress','necessary','SEOPress cookie.'],
		// Caching
		'wpr_'                             => ['WP Rocket','necessary','WP Rocket cookie.'],
		'rocket_'                          => ['WP Rocket','necessary','WP Rocket cookie.'],
		'litespeed_'                       => ['LiteSpeed Cache','necessary','LiteSpeed cookie.'],
		'lscache_'                         => ['LiteSpeed Cache','necessary','LiteSpeed vary.'],
		'w3tc_'                            => ['W3 Total Cache','necessary','W3TC cookie.'],
		'wpfc_'                            => ['WP Fastest Cache','necessary','WPFC cookie.'],
		'sg_'                              => ['SiteGround','necessary','SiteGround cookie.'],
		'siteground_'                      => ['SiteGround Optimizer','necessary','SG Optimizer.'],
		'swift_'                           => ['Swift Performance','necessary','Swift cache.'],
		'hummingbird_'                     => ['Hummingbird','necessary','Hummingbird cookie.'],
		// Security
		'wfwaf-authcookie-'                => ['Wordfence','necessary','Wordfence auth.'],
		'wf_'                              => ['Wordfence','necessary','Wordfence cookie.'],
		'itsec_'                           => ['Solid Security','necessary','Solid Security.'],
		'sucuri_'                          => ['Sucuri','necessary','Sucuri cookie.'],
		'shield_'                          => ['Shield Security','necessary','Shield cookie.'],
		'cerber_'                          => ['WP Cerber','necessary','Cerber cookie.'],
		// Jetpack
		'jetpack_'                         => ['Jetpack','necessary','Jetpack cookie.'],
		'jpp_'                             => ['Jetpack','necessary','Jetpack Protect.'],
		// Forms
		'gf_'                              => ['Gravity Forms','necessary','Gravity Forms.'],
		'gform_'                           => ['Gravity Forms','necessary','Gravity Forms.'],
		'wpforms'                          => ['WPForms','necessary','WPForms cookie.'],
		'fluentform_'                      => ['Fluent Forms','necessary','Fluent Forms.'],
		'frm_'                             => ['Formidable Forms','necessary','Formidable.'],
		'ninja_forms_'                     => ['Ninja Forms','necessary','Ninja Forms.'],
		'hf_'                              => ['HappyForms','necessary','HappyForms.'],
		'everest_forms_'                   => ['Everest Forms','necessary','Everest Forms.'],
		'ws_form_'                         => ['WS Form','necessary','WS Form.'],
		'caldera_forms_'                   => ['Caldera Forms','necessary','Caldera Forms.'],
		'mc4wp_'                           => ['MC4WP','marketing','MC4WP Mailchimp.'],
		// Email marketing
		'mailpoet_'                        => ['MailPoet','marketing','MailPoet cookie.'],
		'fluentcrm_'                       => ['FluentCRM','marketing','FluentCRM cookie.'],
		'omnisend'                         => ['Omnisend','marketing','Omnisend cookie.'],
		// Popups / optins
		'pum-'                             => ['Popup Maker','preferences','Popup Maker.'],
		'pum_'                             => ['Popup Maker','necessary','Popup Maker.'],
		'om_'                              => ['OptinMonster','marketing','OptinMonster.'],
		'tve_'                             => ['Thrive Themes','analytics','Thrive cookie.'],
		'thrive_'                          => ['Thrive Themes','analytics','Thrive cookie.'],
		// Affiliate
		'affwp_'                           => ['AffiliateWP','marketing','AffiliateWP.'],
		'easy_affiliate_'                  => ['Easy Affiliate','marketing','Easy Affiliate.'],
		'slicewp_'                         => ['SliceWP','marketing','SliceWP cookie.'],
		// Booking
		'bookly_'                          => ['Bookly','necessary','Bookly cookie.'],
		'amelia_'                          => ['Amelia','necessary','Amelia cookie.'],
		'ssa_'                             => ['Simply Schedule Appointments','necessary','SSA.'],
		'latepoint_'                       => ['LatePoint','necessary','LatePoint cookie.'],
		// Donation
		'give_'                            => ['Give','necessary','Give donation.'],
		'charitable_'                      => ['Charitable','necessary','Charitable.'],
		// Commerce
		'surecart_'                        => ['SureCart','necessary','SureCart cookie.'],
		'edd_'                             => ['Easy Digital Downloads','necessary','EDD cookie.'],
		'dokan_'                           => ['Dokan','necessary','Dokan cookie.'],
		'cartflows_'                       => ['CartFlows','necessary','CartFlows cookie.'],
		'wfacp_'                           => ['FunnelKit','necessary','FunnelKit cookie.'],
		'funnelkit_'                       => ['FunnelKit','necessary','FunnelKit cookie.'],
		// Analytics
		'_ga_'                             => ['Google Analytics','analytics','GA4 cookie.'],
		'_gac_'                            => ['Google Analytics','analytics','GA Ads.'],
		'_gat_'                            => ['Google Analytics','analytics','GA throttle.'],
		'__utm'                            => ['Google Analytics','analytics','Legacy UA.'],
		'_gcl_'                            => ['Google Ads','marketing','Google Ads.'],
		'_hj'                              => ['Hotjar','analytics','Hotjar cookie.'],
		'_pk_id'                           => ['Matomo','analytics','Matomo visitor.'],
		'_pk_ses'                          => ['Matomo','analytics','Matomo session.'],
		'_pk_'                             => ['Matomo','analytics','Matomo cookie.'],
		'_hp2_'                            => ['Heap','analytics','Heap cookie.'],
		'_lo_'                             => ['Lucky Orange','analytics','Lucky Orange.'],
		'_insp_'                           => ['Inspectlet','analytics','Inspectlet.'],
		'_vis_opt_'                        => ['VWO','analytics','VWO experiment.'],
		'_vwo_'                            => ['VWO','analytics','VWO cookie.'],
		'SL_C_'                            => ['Smartlook','analytics','Smartlook.'],
		'burst_'                           => ['Burst Statistics','analytics','Burst.'],
		'ia_'                              => ['Independent Analytics','analytics','IA cookie.'],
		// Ads
		'_uet'                             => ['Microsoft Ads','marketing','Microsoft UET.'],
		'_cl'                              => ['Microsoft Clarity','analytics','Clarity.'],
		'tt_'                              => ['TikTok','marketing','TikTok.'],
		'__kla_'                           => ['Klaviyo','marketing','Klaviyo.'],
		'cto_'                             => ['Criteo','marketing','Criteo.'],
		'Optanon'                          => ['OneTrust','necessary','OneTrust.'],
		'cmplz_'                           => ['Complianz','necessary','Complianz.'],
		'rcb-'                             => ['Real Cookie Banner','necessary','RCB.'],
		'cookielawinfo-'                   => ['CookieYes','necessary','CookieYes.'],
		'wp_consent_'                      => ['WP Consent API','necessary','WP Consent.'],
		// Intercom
		'intercom-'                        => ['Intercom','marketing','Intercom.'],
		// Stripe
		'__stripe'                         => ['Stripe','necessary','Stripe.'],
		'stripe_'                          => ['Stripe','necessary','Stripe cookie.'],
		// Cloudflare
		'__cf'                             => ['Cloudflare','necessary','Cloudflare.'],
		// Advanced Ads
		'advads_'                          => ['Advanced Ads','analytics','Advanced Ads.'],
		'advanced_ads_'                    => ['Advanced Ads','analytics','Advanced Ads.'],
		// WP Statistics
		'wp_statistics_'                   => ['WP Statistics','analytics','WP Statistics.'],
		'wps_'                             => ['WP Statistics','analytics','WP Statistics.'],
		// Accessibility
		'acsbapp_'                         => ['accessiBe','preferences','accessiBe.'],
		'userway_'                         => ['UserWay','preferences','UserWay.'],
		// Security tokens
		'csrf'                             => ['Security','necessary','CSRF token.'],
		'xsrf'                             => ['Security','necessary','XSRF token.'],
		// PHP
		'phpsessid'                        => ['PHP','necessary','PHP session.'],
	];

	// ── Regex table ───────────────────────────────────────────────────────────
	private static array $patterns = [
		'/^wordpress_logged_in_[a-f0-9]+$/i'       => ['WordPress','necessary','WordPress login.'],
		'/^wordpress_sec_[a-f0-9]+$/i'             => ['WordPress','necessary','WordPress secure auth.'],
		'/^wp_settings_time_\d+$/i'                => ['WordPress','preferences','WordPress editor time.'],
		'/^wc_session_cookie_[a-f0-9]+$/i'         => ['WooCommerce','necessary','WooCommerce session.'],
		'/^wp_woocommerce_session_[a-f0-9]+$/i'    => ['WooCommerce','necessary','WooCommerce cart.'],
		'/^store_notice\d*$/i'                      => ['WooCommerce','necessary','WooCommerce notice.'],
		'/^_ga_[a-z0-9-]+$/i'                      => ['Google Analytics','analytics','GA4 measurement ID.'],
		'/^_gac_[a-z0-9_]+$/i'                     => ['Google Analytics','analytics','GA Ads conversion.'],
		'/^_gat_[a-z0-9_]+$/i'                     => ['Google Analytics','analytics','GA throttle.'],
		'/^_hjSession_\d+$/i'                       => ['Hotjar','analytics','Hotjar site session.'],
		'/^_hjSessionUser_\d+$/i'                   => ['Hotjar','analytics','Hotjar site user.'],
		'/^_pk_id\.[a-f0-9]+\.[a-f0-9]+$/i'        => ['Matomo','analytics','Matomo visitor.'],
		'/^_pk_ses\.[a-f0-9]+\.[a-f0-9]+$/i'       => ['Matomo','analytics','Matomo session.'],
		'/^intercom-id-[a-z0-9]+$/i'               => ['Intercom','marketing','Intercom visitor.'],
		'/^intercom-session-[a-z0-9]+$/i'           => ['Intercom','marketing','Intercom session.'],
		'/^intercom-device-id-[a-z0-9]+$/i'        => ['Intercom','marketing','Intercom device.'],
		'/^wfwaf-authcookie-[a-f0-9]+$/i'          => ['Wordfence','necessary','Wordfence auth.'],
		'/^cmplz_[a-z_]+$/i'                       => ['Complianz','necessary','Complianz cookie.'],
		'/^rcb-consent-[a-z0-9-]+$/i'              => ['Real Cookie Banner','necessary','RCB consent.'],
		'/^pum-\d+$/i'                             => ['Popup Maker','preferences','Popup ID.'],
		'/^affwp_ref_[a-z0-9_]+$/i'               => ['AffiliateWP','marketing','AffiliateWP ref.'],
		'/^_vis_opt_exp_\d+_combi$/i'              => ['VWO','analytics','VWO combination.'],
		'/^AMCVS_[A-Z0-9%@]+$/i'                   => ['Adobe Experience Cloud','analytics','Adobe new visit.'],
		'/^AMCV_[A-Z0-9%@]+$/i'                    => ['Adobe Experience Cloud','analytics','Adobe MCID.'],
		'/^visitor_id\d+$/i'                       => ['Salesforce Pardot','marketing','Pardot visitor.'],
		'/^lpv\d+$/i'                              => ['Salesforce Pardot','marketing','Pardot page view.'],
		'/^_hjIncludedIn(Session|Pageview)Sample_\d+$/i' => ['Hotjar','analytics','Hotjar sample flag.'],
		'/^yt-remote-(connected-devices|device-id)$/i'  => ['YouTube','preferences','YouTube remote.'],
		'/^cookielawinfo-checkbox-.+$/i'           => ['CookieYes','necessary','CookieYes category.'],
	];

	// ── Public API ────────────────────────────────────────────────────────────

	/** @var array<string,array>|null Lower-cased exact-name index, built on first lookup. */
	private static ?array $exact_index = null;

	/** @var array<string,array>|null Lower-cased prefixes, longest first, built on first lookup. */
	private static ?array $prefix_index = null;

	/**
	 * Build the lookup indexes once per request.
	 *
	 * - Keys are lower-cased so mixed-case entries (CONSENT, tawkUUID, …) match.
	 * - "Exact" entries ending in _ - or . are really prefixes (woocommerce_session_,
	 *   cookielawinfo-checkbox-, …): a real cookie always has more after them.
	 * - Prefixes are sorted longest first so wp_consent_ wins over wp_.
	 */
	private static function build_indexes(): void {
		if ( null !== self::$exact_index ) {
			return;
		}

		$exact    = [];
		$prefixes = [];

		foreach ( self::$exact as $key => $entry ) {
			$key = strtolower( $key );
			if ( in_array( substr( $key, -1 ), [ '_', '-', '.' ], true ) ) {
				$prefixes[ $key ] ??= $entry;
			} else {
				$exact[ $key ] ??= $entry;
			}
		}

		foreach ( self::$prefixes as $key => $entry ) {
			$prefixes[ strtolower( $key ) ] ??= $entry;
		}

		uksort( $prefixes, static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) ?: strcmp( $a, $b ) );

		self::$exact_index  = $exact;
		self::$prefix_index = $prefixes;
	}

	public static function lookup( string $name ): ?array {
		$lower = strtolower( trim( $name ) );
		if ( '' === $lower ) {
			return null;
		}

		self::build_indexes();

		if ( isset( self::$exact_index[ $lower ] ) ) {
			return self::format( self::$exact_index[ $lower ] );
		}

		foreach ( self::$prefix_index as $prefix => $entry ) {
			if ( str_starts_with( $lower, $prefix ) ) {
				return self::format( $entry );
			}
		}

		foreach ( self::$patterns as $pattern => $entry ) {
			if ( preg_match( $pattern, $lower ) ) {
				return self::format( $entry );
			}
		}

		return null;
	}

	private static function format( array $e ): array {
		return [ 'plugin' => $e[0], 'category' => $e[1], 'description' => $e[2] ];
	}
}
