/**
 * js/i18n.js — Auto IP-based Internationalization
 * Detects the visitor's country via IP, maps it to the national language,
 * and translates all [data-i18n] elements instantly. Zero user effort required.
 */
(function () {
  'use strict';

  // ─── Country → Language Map (based on primary national language) ───────────
  const COUNTRY_LANG = {
    // Swahili
    TZ:'sw', KE:'sw', UG:'sw', RW:'sw', BI:'sw', KM:'sw',
    // French
    FR:'fr', SN:'fr', CI:'fr', ML:'fr', BF:'fr', GN:'fr', TG:'fr', BJ:'fr',
    NE:'fr', CM:'fr', GA:'fr', CG:'fr', CF:'fr', DJ:'fr', MG:'fr', MU:'fr',
    TD:'fr', BE:'fr', CH:'fr', LU:'fr', MC:'fr', HT:'fr', CD:'fr', RE:'fr',
    // Arabic
    SA:'ar', AE:'ar', EG:'ar', DZ:'ar', MA:'ar', TN:'ar', LY:'ar', SD:'ar',
    IQ:'ar', JO:'ar', SY:'ar', LB:'ar', KW:'ar', QA:'ar', BH:'ar', OM:'ar',
    YE:'ar', MR:'ar', SO:'ar', PS:'ar', SS:'ar',
    // Portuguese
    BR:'pt', PT:'pt', MZ:'pt', AO:'pt', CV:'pt', ST:'pt', GW:'pt', TL:'pt',
    GQ:'pt',
    // Spanish
    ES:'es', MX:'es', CO:'es', AR:'es', PE:'es', VE:'es', CL:'es', EC:'es',
    GT:'es', CU:'es', BO:'es', DO:'es', HN:'es', PY:'es', SV:'es', NI:'es',
    CR:'es', PA:'es', UY:'es',
    // Chinese
    CN:'zh', TW:'zh', HK:'zh', MO:'zh', SG:'zh',
    // Hindi
    IN:'hi',
    // Russian
    RU:'ru', BY:'ru', KZ:'ru', KG:'ru', TJ:'ru', TM:'ru', UZ:'ru', MD:'ru',
    AM:'ru', AZ:'ru', GE:'ru',
    // English (explicit list — all others default to en)
    US:'en', GB:'en', CA:'en', AU:'en', NZ:'en', ZA:'en', NG:'en', GH:'en',
    ZM:'en', ZW:'en', MW:'en', BW:'en', LS:'en', SZ:'en', NA:'en', SL:'en',
    LR:'en', GM:'en', IE:'en', IN_EN:'en',
  };

  // ─── Translation Dictionaries ────────────────────────────────────────────────
  const DICT = {

    en: {
      // Nav
      'nav.prices':       'Live Prices',
      'nav.about':        'About',
      'nav.how':          'How It Works',
      'nav.app':          'Mobile App',
      'nav.contact':      'Contact',
      'nav.start_trade':  'Start a Trade',
      'nav.status':       'Desk online — instant confirmations',
      // Hero / converter
      'lrc.title':        'Live Rate Converter',
      'lrc.desk_online':  'Desk Online',
      'lrc.amount':       'Amount',
      'lrc.from':         'From',
      'lrc.to':           'To',
      'lrc.you_get':      'You get',
      'lrc.start_trade':  'Start Trade',
      'lrc.view_rates':   'View all rates →',
      'lrc.buy_rate':     'BUY rate',
      'lrc.sell_rate':    'SELL rate',
      // Order form
      'order.customer_info':  'Customer Info',
      'order.back':           'Back to Orders',
      'order.confirm_buyer':  'Confirm Buyer Payment',
      'order.verify_desc':    'Verify that the buyer\'s payment has been received and details match before proceeding.',
      'order.loading':        'Loading order details…',
      'order.order_num':      'Order #',
      'order.buyer':          'Buyer',
      'order.verified_name':  'Verified Name',
      'order.email':          'Email',
      'order.payment_method': 'Payment Method',
      'order.fiat_amount':    'Fiat Amount',
      'order.payment_proofs': 'Payment Proofs',
      'order.payment_verified':'Payment Verified – Continue',
      'order.send_crypto':    'Send Crypto to Buyer',
      'order.send_desc':      'Review the buyer\'s platform details and release the crypto securely.',
      'order.transfer_details':'Transfer Details',
      'order.platform':       'Platform',
      'order.platform_uid':   'Platform UID',
      'order.platform_email': 'Platform Email',
      'order.amount_to_send': 'Amount to Send',
      'order.send_completed': 'Send Crypto Completed',
      'order.copy':           'Copy',
      // Sell order
      'sell.title':          'Confirm Seller Transfer',
      'sell.desc':           'Verify that the seller\'s crypto was received successfully before proceeding with payment.',
      'sell.seller_info':    'Seller Info',
      'sell.seller':         'Seller',
      'sell.platform':       'Platform',
      'sell.recipient_email':'Recipient Email',
      'sell.recipient_uid':  'Recipient UID',
      'sell.amount_sent':    'Amount Sent',
      'sell.proof_attach':   'Proof Attachments',
      'sell.confirm_cont':   'Confirm & Continue',
      'sell.pay_seller':     'Pay the Seller',
      'sell.pay_desc':       'Send the payment to the seller\'s account and mark the order as complete after transfer.',
      'sell.payout_details': 'Payout Details',
      'sell.payout_channel': 'Payout Channel',
      'sell.payout_name':    'Payout Name',
      'sell.account_number': 'Account Number',
      'sell.amount_to_pay':  'Amount to Pay',
      'sell.pay_api':        'Pay via API',
      'sell.mark_done':      'Mark Payout Done',
      'sell.back':           'Back',
      // App section
      'app.eyebrow':         'Official Mobile App',
      'app.title':           'Trade crypto from anywhere in minutes.',
      'app.desc':            'The JM P2P mobile app is almost here — a native trading experience for Tanzania with instant M-Pesa, Tigo Pesa, CRDB & NMB support, right in your pocket.',
      'app.notify_btn':      'Notify Me',
      'app.success':         '🎉 Awesome — you\'re on the list!',
      'app.pill1':           '⚡ Lightning Fast',
      'app.pill2':           '🔒 Bank-grade Security',
      'app.pill3':           '📱 iOS & Android',
      // Errors
      'error.verify_email':  'Please verify your email address to continue.',
      'error.network':       'Network error. Please try again.',
      'error.generic':       'Something went wrong. Please try again.',
      // Ticker
      'ticker.1':            '24/7 desk – instant confirmations',
      'ticker.2':            'Best spreads • Transparent rates',
      'ticker.3':            'Secure P2P • CRDB supported',
      // Hero
      'hero.eyebrow':        'LIVE P2P USDT DESK — 24/7 INSTANT CONFIRMATIONS',
      'hero.heading1':       'Fast, Secure, and Reliable',
      'hero.heading2':       'Crypto Trading in Tanzania.',
      'hero.subtitle':       'Public rates. Private speed. A premium P2P desk built for trust, clarity, and instant settlement.',
      'hero.volume':         'Volume',
      'hero.traders':        'Traders',
      'hero.desk':           'Desk',
      'hero.view_prices':    'View Live Prices',
      'hero.whatsapp':       'WhatsApp Chat',
      // Converter extra
      'lrc.buy':             'BUY',
      'lrc.sell':            'SELL',
      // Price section
      'price.currently':     'Currently Offer',
      'price.preview':       'Price Preview',
      'price.buy':           'Buy',
      'price.sell':          'Sell',
      'price.buy_details':   'Buy Details',
      'price.asset':         'Asset',
      'price.price_tzs':     'Price (TZS)',
      'price.limits':        'Limits',
      'price.action':        'Action',
      'price.buy_methods':   'Buy Payment Methods',
      'price.more_methods':  'Want more payment methods or higher limits? Click below to switch into',
      'price.adv_mode':      'Advancement Advertisements Mode',
      'price.place_order':   'Place an Order',
      // About section
      'about.title':         'About',
      'about.subtitle':      'Personal, transparent, and built around your speed of business.',
      'about.p1_prefix':     'At',
      'about.p1':            ', we make crypto simple, safe, and fast. Founded by Jordan Mwinuka, we focus on honest pricing, instant delivery, and clear communication—so you trade with confidence.',
      'about.p2':            'We operate a hybrid pricing model powered by public APIs and precision manual control, ensuring competitive spreads while protecting execution quality across market conditions.',
      // How It Works
      'how.title':           'How It Works',
      'how.subtitle':        'From quote to confirmation in four clean steps.',
      'how.step1':           '1) Check Price',
      'how.step1_desc':      'See the latest buy/sell rates for your platform.',
      'how.step2':           '2) Submit Order',
      'how.step2_desc':      'Fill a short form with your details and amount.',
      'how.step3':           '3) Pay & Upload',
      'how.step3_desc':      'Follow payment access, upload your receipt, add exchange email.',
      'how.step4':           '4) Confirm',
      'how.step4_desc':      'We verify and release crypto/fiat instantly.',
      // App mockup
      'app.instant':         'Instant Settlement',
      'app.methods':         'M-Pesa · Tigo Pesa · CRDB',
      'app.zero_fees':       'Zero App Fees',
      'app.on_pairs':        'On selected pairs',
      'app.greeting':        'Good morning 👋',
      'app.portfolio':       'Total Portfolio Value',
      'app.buy_crypto':      'Buy Crypto',
      'app.sell_crypto':     'Sell Crypto',
      'app.market':          'Market',
      'app.see_all':         'See all →',
      // Order wizard
      'wizard.title':        'Start Your Order',
      'wizard.subtitle':     'Complete order wizard with P2P integration and step-by-step guidance',
      'wizard.step':         'Step',
      'wizard.of':           'of',
      // Footer
      'footer.rights':       '. All rights reserved.',
      'footer.privacy':      'Privacy',
      'footer.terms':        'Terms',
      'footer.back_top':     'Back to top',
      'footer.support':      'Support',
      'footer.email':        'Email',
      'footer.logout':       'Logout',
      // Header buttons
      'header.login':        'Log In',
      'header.create':       'Create Account',
      // How It Works (native HTML 5-step)
      'hiw.heading':         'How It Works',
      'hiw.sub':             'Your trade from start to finish in five clear steps.',
      'hiw.s1_title':        'Create Account',
      'hiw.s1_desc':         'Sign up with your name and email. Your account secures your order history and saved payment details.',
      'hiw.s2_title':        'Check Live Rates',
      'hiw.s2_desc':         'View real-time buy/sell rates on our live price board. Use the calculator to see exactly what you get.',
      'hiw.s3_title':        'Place Your Order',
      'hiw.s3_desc':         'Choose buy or sell, enter amount, pick payment method, and provide your exchange platform details.',
      'hiw.s4_title':        'Pay & Upload Proof',
      'hiw.s4_desc':         'Complete payment via M-Pesa, bank, or crypto. Upload a screenshot of your receipt as proof.',
      'hiw.s5_title':        'We Verify & Deliver',
      'hiw.s5_desc':         'Our team reviews your proof, confirms the transaction, and delivers your crypto or TZS.',
    },

    sw: {
      'nav.prices':       'Bei za Sasa',
      'nav.about':        'Kuhusu',
      'nav.how':          'Jinsi Inavyofanya Kazi',
      'nav.app':          'App ya Simu',
      'nav.contact':      'Wasiliana',
      'nav.start_trade':  'Anza Biashara',
      'nav.status':       'Dawati lipo mtandaoni — uthibitisho wa haraka',
      'lrc.title':        'Kibadilishaji cha Bei za Sasa',
      'lrc.desk_online':  'Dawati Lipo Mtandaoni',
      'lrc.amount':       'Kiasi',
      'lrc.from':         'Kutoka',
      'lrc.to':           'Kwenda',
      'lrc.you_get':      'Unapata',
      'lrc.start_trade':  'Anza Biashara',
      'lrc.view_rates':   'Angalia bei zote →',
      'lrc.buy_rate':     'Bei ya KUNUNUA',
      'lrc.sell_rate':    'Bei ya KUUZA',
      'order.customer_info':  'Maelezo ya Mteja',
      'order.back':           'Rudi kwa Maagizo',
      'order.confirm_buyer':  'Thibitisha Malipo ya Mnunuzi',
      'order.verify_desc':    'Hakikisha malipo ya mnunuzi yamepokelewa na maelezo yanafanana kabla ya kuendelea.',
      'order.loading':        'Inapakia maelezo ya agizo…',
      'order.order_num':      'Agizo #',
      'order.buyer':          'Mnunuzi',
      'order.verified_name':  'Jina Lililothibitishwa',
      'order.email':          'Barua pepe',
      'order.payment_method': 'Njia ya Malipo',
      'order.fiat_amount':    'Kiasi cha Fedha',
      'order.payment_proofs': 'Uthibitisho wa Malipo',
      'order.payment_verified':'Malipo Yamethibitishwa – Endelea',
      'order.send_crypto':    'Tuma Crypto kwa Mnunuzi',
      'order.send_desc':      'Kagua maelezo ya jukwaa la mnunuzi na uachilie crypto kwa usalama.',
      'order.transfer_details':'Maelezo ya Uhamisho',
      'order.platform':       'Jukwaa',
      'order.platform_uid':   'UID ya Jukwaa',
      'order.platform_email': 'Barua pepe ya Jukwaa',
      'order.amount_to_send': 'Kiasi cha Kutuma',
      'order.send_completed': 'Utumaji wa Crypto Umekamilika',
      'order.copy':           'Nakili',
      'sell.title':          'Thibitisha Uhamisho wa Muuzaji',
      'sell.desc':           'Hakikisha crypto ya muuzaji imepokelewa kwa mafanikio kabla ya kuendelea na malipo.',
      'sell.seller_info':    'Maelezo ya Muuzaji',
      'sell.seller':         'Muuzaji',
      'sell.platform':       'Jukwaa',
      'sell.recipient_email':'Barua pepe ya Mpokeaji',
      'sell.recipient_uid':  'UID ya Mpokeaji',
      'sell.amount_sent':    'Kiasi Kilichotumwa',
      'sell.proof_attach':   'Viambatisho vya Uthibitisho',
      'sell.confirm_cont':   'Thibitisha & Endelea',
      'sell.pay_seller':     'Lipa Muuzaji',
      'sell.pay_desc':       'Tuma malipo kwa akaunti ya muuzaji na uashirie agizo kama limekamilika baada ya uhamisho.',
      'sell.payout_details': 'Maelezo ya Malipo',
      'sell.payout_channel': 'Njia ya Malipo',
      'sell.payout_name':    'Jina la Malipo',
      'sell.account_number': 'Nambari ya Akaunti',
      'sell.amount_to_pay':  'Kiasi cha Kulipa',
      'sell.pay_api':        'Lipa kupitia API',
      'sell.mark_done':      'Alama Malipo Yamekamilika',
      'sell.back':           'Rudi',
      'app.eyebrow':         'App Rasmi ya Simu',
      'app.title':           'Fanya biashara ya crypto popote kwa dakika.',
      'app.desc':            'App ya JM P2P ya simu inakuja hivi karibuni — uzoefu wa biashara wa asili kwa Tanzania na msaada wa M-Pesa, Tigo Pesa, CRDB & NMB mara moja, mfukoni mwako.',
      'app.notify_btn':      'Niarifu',
      'app.success':         '🎉 Vizuri sana — uko kwenye orodha!',
      'app.pill1':           '⚡ Haraka Sana',
      'app.pill2':           '🔒 Usalama wa Benki',
      'app.pill3':           '📱 iOS & Android',
      'error.verify_email':  'Tafadhali thibitisha anwani yako ya barua pepe ili uendelee.',
      'error.network':       'Hitilafu ya mtandao. Tafadhali jaribu tena.',
      'error.generic':       'Kuna tatizo fulani. Tafadhali jaribu tena.',
      'ticker.1':            'Dawati la 24/7 – uthibitisho wa haraka',
      'ticker.2':            'Spread bora • Bei za uwazi',
      'ticker.3':            'P2P Salama • CRDB inasaidiwa',
      'hero.eyebrow':        'DAWATI LA P2P USDT — UTHIBITISHO WA HARAKA 24/7',
      'hero.heading1':       'Haraka, Salama, na ya Kuaminika',
      'hero.heading2':       'Biashara ya Crypto Tanzania.',
      'hero.subtitle':       'Bei za wazi. Kasi ya kibinafsi. Dawati la P2P la hali ya juu lililojengwa kwa uaminifu, uwazi, na malipo ya papo hapo.',
      'hero.volume':         'Kiasi',
      'hero.traders':        'Wafanyabiashara',
      'hero.desk':           'Dawati',
      'hero.view_prices':    'Angalia Bei za Sasa',
      'hero.whatsapp':       'WhatsApp Chat',
      'lrc.buy':             'NUNUA',
      'lrc.sell':            'UZA',
      'price.currently':     'Tunatoa Sasa',
      'price.preview':       'Muhtasari wa Bei',
      'price.buy':           'Nunua',
      'price.sell':          'Uza',
      'price.buy_details':   'Maelezo ya Kununua',
      'price.asset':         'Mali',
      'price.price_tzs':     'Bei (TZS)',
      'price.limits':        'Vikomo',
      'price.action':        'Kitendo',
      'price.buy_methods':   'Njia za Malipo ya Kununua',
      'price.more_methods':  'Unataka njia zaidi za malipo au vikomo vikubwa? Bonyeza hapa chini kubadilisha kwenda',
      'price.adv_mode':      'Hali ya Matangazo ya Juu',
      'price.place_order':   'Weka Agizo',
      'about.title':         'Kuhusu Sisi',
      'about.subtitle':      'Ya kibinafsi, ya uwazi, na iliyojengwa kuzunguka kasi ya biashara yako.',
      'about.p1_prefix':     'Katika',
      'about.p1':            ', tunafanya crypto kuwa rahisi, salama, na haraka. Ilianzishwa na Jordan Mwinuka, tunalenga bei za uaminifu, utoaji wa papo hapo, na mawasiliano wazi—ili ufanye biashara kwa ujasiri.',
      'about.p2':            'Tunaendesha mfumo wa bei wa mseto unaotumia API za umma na udhibiti sahihi wa mikono, kuhakikisha spread za ushindani huku tukilinda ubora wa utekelezaji katika hali zote za soko.',
      'how.title':           'Jinsi Inavyofanya Kazi',
      'how.subtitle':        'Kutoka bei hadi uthibitisho kwa hatua nne rahisi.',
      'how.step1':           '1) Angalia Bei',
      'how.step1_desc':      'Angalia bei za hivi karibuni za kununua/kuuza kwa jukwaa lako.',
      'how.step2':           '2) Wasilisha Agizo',
      'how.step2_desc':      'Jaza fomu fupi yenye maelezo yako na kiasi.',
      'how.step3':           '3) Lipa na Pakia',
      'how.step3_desc':      'Fuata upatikanaji wa malipo, pakia risiti yako, ongeza barua pepe ya kubadilishana.',
      'how.step4':           '4) Thibitisha',
      'how.step4_desc':      'Tunathibitisha na kutoa crypto/fedha mara moja.',
      'app.instant':         'Malipo ya Papo Hapo',
      'app.methods':         'M-Pesa · Tigo Pesa · CRDB',
      'app.zero_fees':       'Bila Ada za App',
      'app.on_pairs':        'Kwa jozi zilizochaguliwa',
      'app.greeting':        'Habari za asubuhi 👋',
      'app.portfolio':       'Thamani ya Jumla ya Mkoba',
      'app.buy_crypto':      'Nunua Crypto',
      'app.sell_crypto':     'Uza Crypto',
      'app.market':          'Soko',
      'app.see_all':         'Ona zote →',
      'wizard.title':        'Anza Agizo Lako',
      'wizard.subtitle':     'Mchawi kamili wa agizo na muunganisho wa P2P na mwongozo wa hatua kwa hatua',
      'wizard.step':         'Hatua',
      'wizard.of':           'ya',
      'footer.rights':       '. Haki zote zimehifadhiwa.',
      'footer.privacy':      'Faragha',
      'footer.terms':        'Masharti',
      'footer.back_top':     'Rudi juu',
      'footer.support':      'Msaada',
      'footer.email':        'Barua pepe',
      'footer.logout':       'Toka',
      // Header buttons
      'header.login':        'Ingia',
      'header.create':       'Fungua Akaunti',
      // How It Works (native HTML 5-step)
      'hiw.heading':         'Jinsi Inavyofanya Kazi',
      'hiw.sub':             'Biashara yako kutoka mwanzo hadi mwisho kwa hatua tano rahisi.',
      'hiw.s1_title':        'Fungua Akaunti',
      'hiw.s1_desc':         'Jisajili kwa jina lako na barua pepe. Akaunti yako inalinda historia ya maagizo yako na maelezo ya malipo yaliyohifadhiwa.',
      'hiw.s2_title':        'Angalia Bei za Sasa',
      'hiw.s2_desc':         'Angalia bei za kununua/kuuza kwa wakati halisi kwenye bodi yetu ya bei. Tumia kikokotoo kuona unachopata.',
      'hiw.s3_title':        'Weka Agizo Lako',
      'hiw.s3_desc':         'Chagua kununua au kuuza, ingiza kiasi, chagua njia ya malipo, na toa maelezo ya jukwaa lako la kubadilishana.',
      'hiw.s4_title':        'Lipa na Pakia Uthibitisho',
      'hiw.s4_desc':         'Kamilisha malipo kupitia M-Pesa, benki, au crypto. Pakia picha ya risiti yako kama uthibitisho.',
      'hiw.s5_title':        'Tunathibitisha na Kutoa',
      'hiw.s5_desc':         'Timu yetu inakagua uthibitisho wako, inathibitisha muamala, na kutoa crypto au TZS yako.',
    },

    fr: {
      'nav.prices':       'Prix en Direct',
      'nav.about':        'À Propos',
      'nav.how':          'Comment Ça Marche',
      'nav.app':          'Application Mobile',
      'nav.contact':      'Contact',
      'nav.start_trade':  'Démarrer un Échange',
      'nav.status':       'Bureau en ligne — confirmations instantanées',
      'lrc.title':        'Convertisseur de Taux en Direct',
      'lrc.desk_online':  'Bureau en Ligne',
      'lrc.amount':       'Montant',
      'lrc.from':         'De',
      'lrc.to':           'Vers',
      'lrc.you_get':      'Vous recevez',
      'lrc.start_trade':  'Démarrer l\'Échange',
      'lrc.view_rates':   'Voir tous les taux →',
      'lrc.buy_rate':     'Taux d\'ACHAT',
      'lrc.sell_rate':    'Taux de VENTE',
      'order.customer_info':  'Infos Client',
      'order.back':           'Retour aux Commandes',
      'order.confirm_buyer':  'Confirmer le Paiement de l\'Acheteur',
      'order.verify_desc':    'Vérifiez que le paiement de l\'acheteur a été reçu et que les détails correspondent avant de continuer.',
      'order.loading':        'Chargement des détails de la commande…',
      'order.order_num':      'Commande #',
      'order.buyer':          'Acheteur',
      'order.verified_name':  'Nom Vérifié',
      'order.email':          'E-mail',
      'order.payment_method': 'Mode de Paiement',
      'order.fiat_amount':    'Montant Fiat',
      'order.payment_proofs': 'Preuves de Paiement',
      'order.payment_verified':'Paiement Vérifié – Continuer',
      'order.send_crypto':    'Envoyer des Crypto à l\'Acheteur',
      'order.send_desc':      'Vérifiez les détails de la plateforme de l\'acheteur et libérez les crypto en toute sécurité.',
      'order.transfer_details':'Détails du Transfert',
      'order.platform':       'Plateforme',
      'order.platform_uid':   'UID de la Plateforme',
      'order.platform_email': 'E-mail de la Plateforme',
      'order.amount_to_send': 'Montant à Envoyer',
      'order.send_completed': 'Envoi de Crypto Terminé',
      'order.copy':           'Copier',
      'sell.title':          'Confirmer le Transfert du Vendeur',
      'sell.desc':           'Vérifiez que les crypto du vendeur ont été reçues avec succès avant de procéder au paiement.',
      'sell.seller_info':    'Infos Vendeur',
      'sell.seller':         'Vendeur',
      'sell.platform':       'Plateforme',
      'sell.recipient_email':'E-mail du Destinataire',
      'sell.recipient_uid':  'UID du Destinataire',
      'sell.amount_sent':    'Montant Envoyé',
      'sell.proof_attach':   'Pièces Jointes',
      'sell.confirm_cont':   'Confirmer & Continuer',
      'sell.pay_seller':     'Payer le Vendeur',
      'sell.pay_desc':       'Envoyez le paiement au compte du vendeur et marquez la commande comme complète.',
      'sell.payout_details': 'Détails du Paiement',
      'sell.payout_channel': 'Canal de Paiement',
      'sell.payout_name':    'Nom de Paiement',
      'sell.account_number': 'Numéro de Compte',
      'sell.amount_to_pay':  'Montant à Payer',
      'sell.pay_api':        'Payer via API',
      'sell.mark_done':      'Marquer comme Terminé',
      'sell.back':           'Retour',
      'app.eyebrow':         'Application Mobile Officielle',
      'app.title':           'Échangez des crypto de n\'importe où en quelques minutes.',
      'app.desc':            'L\'application mobile JM P2P arrive bientôt — une expérience de trading native pour la Tanzanie avec le support instantané M-Pesa, Tigo Pesa, CRDB & NMB, dans votre poche.',
      'app.notify_btn':      'M\'avertir',
      'app.success':         '🎉 Super — vous êtes sur la liste!',
      'app.pill1':           '⚡ Ultra Rapide',
      'app.pill2':           '🔒 Sécurité Bancaire',
      'app.pill3':           '📱 iOS & Android',
      'error.verify_email':  'Veuillez vérifier votre adresse e-mail pour continuer.',
      'error.network':       'Erreur réseau. Veuillez réessayer.',
      'error.generic':       'Quelque chose s\'est mal passé. Veuillez réessayer.',
    },

    ar: {
      'nav.prices':       'الأسعار المباشرة',
      'nav.about':        'حول',
      'nav.how':          'كيف يعمل',
      'nav.app':          'التطبيق المحمول',
      'nav.contact':      'اتصل بنا',
      'nav.start_trade':  'ابدأ التداول',
      'nav.status':       'المكتب متصل — تأكيدات فورية',
      'lrc.title':        'محول الأسعار المباشرة',
      'lrc.desk_online':  'المكتب متصل',
      'lrc.amount':       'المبلغ',
      'lrc.from':         'من',
      'lrc.to':           'إلى',
      'lrc.you_get':      'ستحصل على',
      'lrc.start_trade':  'ابدأ التداول',
      'lrc.view_rates':   'عرض جميع الأسعار →',
      'lrc.buy_rate':     'سعر الشراء',
      'lrc.sell_rate':    'سعر البيع',
      'order.back':           'العودة إلى الطلبات',
      'order.confirm_buyer':  'تأكيد دفع المشتري',
      'order.loading':        'جارٍ تحميل تفاصيل الطلب…',
      'order.order_num':      'طلب #',
      'order.buyer':          'المشتري',
      'order.email':          'البريد الإلكتروني',
      'order.payment_method': 'طريقة الدفع',
      'order.fiat_amount':    'المبلغ بالعملة الورقية',
      'order.copy':           'نسخ',
      'sell.back':           'رجوع',
      'sell.mark_done':      'تأشير على أنه تم',
      'sell.pay_api':        'الدفع عبر API',
      'app.notify_btn':      'أخبرني',
      'app.success':         '🎉 رائع — أنت على القائمة!',
      'error.verify_email':  'يرجى التحقق من عنوان بريدك الإلكتروني للمتابعة.',
      'error.network':       'خطأ في الشبكة. يرجى المحاولة مرة أخرى.',
      'error.generic':       'حدث خطأ ما. يرجى المحاولة مرة أخرى.',
    },

    pt: {
      'nav.prices':       'Preços ao Vivo',
      'nav.about':        'Sobre',
      'nav.how':          'Como Funciona',
      'nav.app':          'App Móvel',
      'nav.contact':      'Contacto',
      'nav.start_trade':  'Iniciar Negociação',
      'nav.status':       'Balcão online — confirmações instantâneas',
      'lrc.title':        'Conversor de Taxa ao Vivo',
      'lrc.desk_online':  'Balcão Online',
      'lrc.amount':       'Valor',
      'lrc.from':         'De',
      'lrc.to':           'Para',
      'lrc.you_get':      'Você recebe',
      'lrc.start_trade':  'Iniciar Negociação',
      'lrc.view_rates':   'Ver todas as taxas →',
      'lrc.buy_rate':     'Taxa de COMPRA',
      'lrc.sell_rate':    'Taxa de VENDA',
      'order.back':           'Voltar aos Pedidos',
      'order.confirm_buyer':  'Confirmar Pagamento do Comprador',
      'order.loading':        'Carregando detalhes do pedido…',
      'order.copy':           'Copiar',
      'sell.back':           'Voltar',
      'sell.mark_done':      'Marcar como Concluído',
      'app.notify_btn':      'Notifique-me',
      'app.success':         '🎉 Ótimo — você está na lista!',
      'error.verify_email':  'Por favor, verifique seu endereço de e-mail para continuar.',
      'error.network':       'Erro de rede. Por favor, tente novamente.',
      'error.generic':       'Algo deu errado. Por favor, tente novamente.',
    },

    es: {
      'nav.prices':       'Precios en Vivo',
      'nav.about':        'Acerca de',
      'nav.how':          'Cómo Funciona',
      'nav.app':          'App Móvil',
      'nav.contact':      'Contacto',
      'nav.start_trade':  'Iniciar Operación',
      'nav.status':       'Escritorio en línea — confirmaciones instantáneas',
      'lrc.title':        'Conversor de Tasas en Vivo',
      'lrc.desk_online':  'Escritorio en Línea',
      'lrc.amount':       'Monto',
      'lrc.from':         'De',
      'lrc.to':           'A',
      'lrc.you_get':      'Recibes',
      'lrc.start_trade':  'Iniciar Operación',
      'lrc.view_rates':   'Ver todas las tasas →',
      'lrc.buy_rate':     'Tasa de COMPRA',
      'lrc.sell_rate':    'Tasa de VENTA',
      'order.back':           'Volver a Pedidos',
      'order.confirm_buyer':  'Confirmar Pago del Comprador',
      'order.loading':        'Cargando detalles del pedido…',
      'order.copy':           'Copiar',
      'sell.back':           'Volver',
      'sell.mark_done':      'Marcar como Completado',
      'app.notify_btn':      'Notifícame',
      'app.success':         '🎉 Genial — ¡estás en la lista!',
      'error.verify_email':  'Por favor, verifica tu correo electrónico para continuar.',
      'error.network':       'Error de red. Por favor, inténtalo de nuevo.',
      'error.generic':       'Algo salió mal. Por favor, inténtalo de nuevo.',
    },

    zh: {
      'nav.prices':       '实时价格',
      'nav.about':        '关于我们',
      'nav.how':          '如何使用',
      'nav.app':          '手机应用',
      'nav.contact':      '联系我们',
      'nav.start_trade':  '开始交易',
      'nav.status':       '桌台在线 — 即时确认',
      'lrc.title':        '实时汇率转换器',
      'lrc.desk_online':  '桌台在线',
      'lrc.amount':       '金额',
      'lrc.from':         '从',
      'lrc.to':           '到',
      'lrc.you_get':      '您将获得',
      'lrc.start_trade':  '开始交易',
      'lrc.view_rates':   '查看所有汇率 →',
      'lrc.buy_rate':     '买入价格',
      'lrc.sell_rate':    '卖出价格',
      'order.back':           '返回订单',
      'order.confirm_buyer':  '确认买家付款',
      'order.loading':        '正在加载订单详情…',
      'order.copy':           '复制',
      'sell.back':           '返回',
      'sell.mark_done':      '标记为已完成',
      'app.notify_btn':      '通知我',
      'app.success':         '🎉 太棒了 — 您已加入列表！',
      'error.verify_email':  '请验证您的电子邮件地址以继续。',
      'error.network':       '网络错误，请重试。',
      'error.generic':       '发生错误，请重试。',
    },

    hi: {
      'nav.prices':       'लाइव मूल्य',
      'nav.about':        'हमारे बारे में',
      'nav.how':          'यह कैसे काम करता है',
      'nav.app':          'मोबाइल ऐप',
      'nav.contact':      'संपर्क',
      'nav.start_trade':  'ट्रेड शुरू करें',
      'nav.status':       'डेस्क ऑनलाइन — तत्काल पुष्टि',
      'lrc.title':        'लाइव रेट कनवर्टर',
      'lrc.desk_online':  'डेस्क ऑनलाइन',
      'lrc.amount':       'राशि',
      'lrc.from':         'से',
      'lrc.to':           'को',
      'lrc.you_get':      'आपको मिलता है',
      'lrc.start_trade':  'ट्रेड शुरू करें',
      'lrc.view_rates':   'सभी दरें देखें →',
      'lrc.buy_rate':     'खरीद दर',
      'lrc.sell_rate':    'बिक्री दर',
      'order.back':           'ऑर्डर पर वापस जाएं',
      'order.copy':           'कॉपी',
      'sell.back':           'वापस',
      'app.notify_btn':      'मुझे सूचित करें',
      'app.success':         '🎉 बढ़िया — आप सूची में हैं!',
      'error.verify_email':  'जारी रखने के लिए कृपया अपना ईमेल सत्यापित करें।',
      'error.network':       'नेटवर्क त्रुटि। कृपया पुनः प्रयास करें।',
      'error.generic':       'कुछ गलत हो गया। कृपया पुनः प्रयास करें।',
    },

    ru: {
      'nav.prices':       'Актуальные Курсы',
      'nav.about':        'О Нас',
      'nav.how':          'Как Это Работает',
      'nav.app':          'Мобильное Приложение',
      'nav.contact':      'Контакты',
      'nav.start_trade':  'Начать Обмен',
      'nav.status':       'Офис онлайн — мгновенные подтверждения',
      'lrc.title':        'Конвертер Курсов в Реальном Времени',
      'lrc.desk_online':  'Офис Онлайн',
      'lrc.amount':       'Сумма',
      'lrc.from':         'Из',
      'lrc.to':           'В',
      'lrc.you_get':      'Вы получите',
      'lrc.start_trade':  'Начать Обмен',
      'lrc.view_rates':   'Посмотреть все курсы →',
      'lrc.buy_rate':     'Курс ПОКУПКИ',
      'lrc.sell_rate':    'Курс ПРОДАЖИ',
      'order.back':           'Назад к Заказам',
      'order.copy':           'Копировать',
      'sell.back':           'Назад',
      'app.notify_btn':      'Уведомить меня',
      'app.success':         '🎉 Отлично — вы в списке!',
      'error.verify_email':  'Пожалуйста, подтвердите email для продолжения.',
      'error.network':       'Ошибка сети. Пожалуйста, попробуйте снова.',
      'error.generic':       'Что-то пошло не так. Пожалуйста, попробуйте снова.',
    },
  };

  // RTL languages
  const RTL_LANGS = ['ar', 'he', 'fa', 'ur'];

  // ─── Auto-tagging map: match React-rendered elements by href or text ───────
  // Maps { href → i18n key } for nav links
  const HREF_I18N_MAP = {
    '#prices':  'nav.prices',
    '#about':   'nav.about',
    '#how':     'nav.how',
    '#contact': 'nav.contact',
  };

  // Maps { English text → i18n key } for labels/buttons with no reliable href
  const TEXT_I18N_MAP = {
    'View Prices':            'nav.prices',
    'View Live Prices':       'nav.prices',
    'Start Trade':            'lrc.start_trade',
    'Start a Trade':          'nav.start_trade',
    'Live Rate Converter':    'lrc.title',
    'DESK ONLINE':            'lrc.desk_online',
    'Desk Online':            'lrc.desk_online',
    'AMOUNT':                 'lrc.amount',
    'Amount':                 'lrc.amount',
    'FROM':                   'lrc.from',
    'TO':                     'lrc.to',
    'YOU GET':                'lrc.you_get',
    'You get':                'lrc.you_get',
    'View all rates →':       'lrc.view_rates',
    'View all rates →':       'lrc.view_rates',
    'BUY RATE':               'lrc.buy_rate',
    'SELL RATE':              'lrc.sell_rate',
    'BUY rate':               'lrc.buy_rate',
    'SELL rate':              'lrc.sell_rate',
    'WhatsApp Chat':          'hero.whatsapp',
    // Ticker
    '24/7 desk – instant confirmations':  'ticker.1',
    'Best spreads • Transparent rates':   'ticker.2',
    'Secure P2P • CRDB supported':        'ticker.3',
    // Hero
    'LIVE P2P USDT DESK — 24/7 INSTANT CONFIRMATIONS': 'hero.eyebrow',
    'Fast, Secure, and Reliable':         'hero.heading1',
    'Crypto Trading in Tanzania.':        'hero.heading2',
    'Buy and sell USDT instantly with Tanzania\'s most trusted exchange. Public rates, private speed, and 24/7 settlement.': 'hero.subtitle',
    'Volume':                 'hero.volume',
    'Traders':                'hero.traders',
    'Desk':                   'hero.desk',
    'View Live Prices':       'hero.view_prices',
    // Converter
    'buy':                    'lrc.buy',
    'sell':                   'lrc.sell',
    'BUY':                    'lrc.buy',
    'SELL':                   'lrc.sell',
    'From':                   'lrc.from',
    'To':                     'lrc.to',
    // Price section
    'Currently Offer':        'price.currently',
    'Price Preview':          'price.preview',
    'Buy':                    'price.buy',
    'Sell':                   'price.sell',
    'Buy Details':            'price.buy_details',
    'Asset':                  'price.asset',
    'Price (TZS)':            'price.price_tzs',
    'Limits':                 'price.limits',
    'Action':                 'price.action',
    'Buy Payment Methods':    'price.buy_methods',
    'Want more payment methods or higher limits? Click below to switch into': 'price.more_methods',
    'Advancement Advertisements Mode': 'price.adv_mode',
    'Place an Order':         'price.place_order',
    // About
    'Personal, transparent, and built around your speed of business.': 'about.subtitle',
    'At':                     'about.p1_prefix',
    ', we make crypto simple, safe, and fast. Founded by Jordan Mwinuka, we focus on honest pricing, instant delivery, and clear communication—so you trade with confidence.': 'about.p1',
    'We operate a hybrid pricing model powered by public APIs and precision manual control, ensuring competitive spreads while protecting execution quality across market conditions.': 'about.p2',
    // How It Works
    'From quote to confirmation in four clean steps.': 'how.subtitle',
    '1) Check Price':         'how.step1',
    'See the latest buy/sell rates for your platform.': 'how.step1_desc',
    '2) Submit Order':        'how.step2',
    'Fill a short form with your details and amount.': 'how.step2_desc',
    '3) Pay & Upload':        'how.step3',
    'Follow payment access, upload your receipt, add exchange email.': 'how.step3_desc',
    '4) Confirm':             'how.step4',
    'We verify and release crypto/fiat instantly.': 'how.step4_desc',
    // App section
    'Official Mobile App':    'app.eyebrow',
    'The JM P2P mobile app is almost here — a native trading experience for Tanzania with instant M-Pesa, Tigo Pesa, CRDB & NMB support, right in your pocket.': 'app.desc',
    '⚡ Lightning Fast':      'app.pill1',
    '🔒 Bank-grade Security': 'app.pill2',
    '📱 iOS & Android':       'app.pill3',
    'Notify Me':              'app.notify_btn',
    'Instant Settlement':     'app.instant',
    'M-Pesa · Tigo Pesa · CRDB': 'app.methods',
    'Zero App Fees':          'app.zero_fees',
    'On selected pairs':      'app.on_pairs',
    'Good morning 👋':        'app.greeting',
    'Total Portfolio Value':  'app.portfolio',
    'Buy Crypto':             'app.buy_crypto',
    'Sell Crypto':            'app.sell_crypto',
    'Market':                 'app.market',
    'See all →':              'app.see_all',
    // Order wizard
    'Start Your Order':       'wizard.title',
    'Complete order wizard with P2P integration and step-by-step guidance': 'wizard.subtitle',
    'Step':                   'wizard.step',
    'of':                     'wizard.of',
    // Footer
    '. All rights reserved.': 'footer.rights',
    'Privacy':                'footer.privacy',
    'Terms':                  'footer.terms',
    'Back to top':            'footer.back_top',
    'Support':                'footer.support',
    'Email':                  'footer.email',
    'Logout':                 'footer.logout',
  };

  // ─── Core Engine ────────────────────────────────────────────────────────────
  const I18N = {
    currentLang: 'en',

    /** Get translated string, fallback to English, then key itself */
    t: function (key) {
      const dict = DICT[this.currentLang] || {};
      return dict[key] || DICT['en'][key] || key;
    },

    /**
     * Auto-discover React-rendered elements and stamp data-i18n on them.
     * Runs before applyTranslations so the main loop picks them up.
     */
    autoTag: function () {
      // 1. Tag nav links by href
      var header = document.querySelector('header');
      if (header) {
        header.querySelectorAll('a[href]').forEach(function (a) {
          if (a.hasAttribute('data-i18n')) return; // already tagged
          var href = a.getAttribute('href');
          var key = HREF_I18N_MAP[href];
          if (key) {
            // Only tag simple text links (not the logo link)
            var text = a.textContent.trim();
            if (text.length < 40 && !a.querySelector('img')) {
              a.setAttribute('data-i18n', key);
            }
          }
        });
      }

      // 2. Tag elements by matching text content
      // Use both direct-text-only matching and full textContent for leaf nodes
      var allEls = document.querySelectorAll('span, div, a, button, label, p, h1, h2, h3, h4, th, td, em, strong');
      allEls.forEach(function (el) {
        if (el.hasAttribute('data-i18n')) return;
        // Skip elements with child elements entirely — auto-tagging a parent
        // then setting textContent would destroy all its children (e.g. h1
        // containing a gradient <span> + <br> + text node)
        var childElements = el.querySelectorAll(':scope > *');
        if (childElements.length > 0) return;

        // Leaf element — use textContent directly
        var text = el.textContent.trim();

        var key = TEXT_I18N_MAP[text];
        if (key) {
          el.setAttribute('data-i18n', key);
        }
      });

      // 3. Tag elements by CSS class patterns (for elements with specific class names)
      var CLASS_I18N_MAP = {
        'hero-eyebrow': 'hero.eyebrow',
        'app-teaser-eyebrow': 'app.eyebrow',
        'app-teaser-desc': 'app.desc',
        'app-teaser-notify-btn': 'app.notify_btn',
        'atsec-greeting': 'app.greeting',
        'atsec-bal-label': 'app.portfolio',
        'atsec-mkt-title': 'app.market',
        'atsec-mkt-see': 'app.see_all',
      };
      Object.keys(CLASS_I18N_MAP).forEach(function (cls) {
        var el = document.querySelector('.' + cls);
        if (el && !el.hasAttribute('data-i18n')) {
          el.setAttribute('data-i18n', CLASS_I18N_MAP[cls]);
        }
      });

      // 4. Tag app teaser pills by content
      document.querySelectorAll('.app-teaser-pill').forEach(function (el) {
        if (el.hasAttribute('data-i18n')) return;
        var text = el.textContent.trim();
        var key = TEXT_I18N_MAP[text];
        if (key) el.setAttribute('data-i18n', key);
      });

      // 5. Tag app mockup badge elements by class
      document.querySelectorAll('.atsec-bn').forEach(function (el) {
        if (el.hasAttribute('data-i18n')) return;
        var text = el.textContent.trim();
        var key = TEXT_I18N_MAP[text];
        if (key) el.setAttribute('data-i18n', key);
      });
      document.querySelectorAll('.atsec-bs').forEach(function (el) {
        if (el.hasAttribute('data-i18n')) return;
        var text = el.textContent.trim();
        var key = TEXT_I18N_MAP[text];
        if (key) el.setAttribute('data-i18n', key);
      });
      document.querySelectorAll('.atsec-bal-btn').forEach(function (el) {
        if (el.hasAttribute('data-i18n')) return;
        var text = el.textContent.trim();
        var key = TEXT_I18N_MAP[text];
        if (key) el.setAttribute('data-i18n', key);
      });
    },

    /** Apply translations to all [data-i18n] elements in the DOM */
    applyTranslations: function () {
      var lang = this.currentLang;
      // Set HTML lang attribute
      document.documentElement.lang = lang;
      // Set RTL direction for Arabic etc.
      document.documentElement.dir = RTL_LANGS.includes(lang) ? 'rtl' : 'ltr';

      // Auto-discover and tag React-rendered elements first
      this.autoTag();

      document.querySelectorAll('[data-i18n]').forEach(function (el) {
        var key = el.getAttribute('data-i18n');
        var translation = I18N.t(key);
        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
          if (el.placeholder !== translation) el.placeholder = translation;
        } else {
          if (el.textContent !== translation) el.textContent = translation;
        }
      });

      // Also handle data-i18n-html (for elements needing innerHTML)
      document.querySelectorAll('[data-i18n-html]').forEach(function (el) {
        var key = el.getAttribute('data-i18n-html');
        el.innerHTML = I18N.t(key);
      });
    },

    /** Persist and apply a language (only when user explicitly chooses) */
    changeLanguage: function (lang) {
      if (!DICT[lang]) lang = 'en';
      this.currentLang = lang;
      try { localStorage.setItem('jmp2p_lang_explicit', 'true'); } catch (e) {}
      try { localStorage.setItem('jmp2p_lang', lang); } catch (e) {}
      this.applyTranslations();
      // Update switcher UI if present
      var switcher = document.getElementById('i18n-lang-switcher');
      if (switcher) switcher.value = lang;
    },

    /** Main init — always detects via IP unless user explicitly chose a language */
    init: function () {
      // 1. Only honour localStorage if the user *explicitly* chose a language
      var saved = null;
      var explicit = false;
      try { saved = localStorage.getItem('jmp2p_lang'); } catch (e) {}
      try { explicit = localStorage.getItem('jmp2p_lang_explicit') === 'true'; } catch (e) {}

      if (explicit && saved && DICT[saved]) {
        this.currentLang = saved;
        this.applyTranslations();
        return;
      }

      // 2. Show English immediately while we detect the real language
      this.currentLang = 'en';
      this.applyTranslations();

      // 3. Detect via IP geolocation
      var self = this;
      fetch('https://get.geojs.io/v1/ip/country.json', { cache: 'force-cache' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var countryCode = (data && data.country) ? data.country.toUpperCase() : null;
          var lang = (countryCode && COUNTRY_LANG[countryCode]) ? COUNTRY_LANG[countryCode] : 'en';
          self.currentLang = lang;
          try { localStorage.setItem('jmp2p_lang', lang); } catch (e) {}
          self.applyTranslations();
        })
        .catch(function () {
          // Silently fail — English stays as default
        });
    },
  };

  // Expose globally
  window.i18n = I18N;

  // Auto-run on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { I18N.init(); });
  } else {
    I18N.init();
  }

  // Re-apply on dynamic content (React mounts etc.)
  // Observe DOM mutations to discover React updates
  if (typeof MutationObserver !== 'undefined') {
    var _i18nTimer = null;
    var isTranslating = false; // Prevent observer loop
    
    var observer = new MutationObserver(function () {
      if (isTranslating) return; // Skip mutations caused by applyTranslations
      clearTimeout(_i18nTimer);
      _i18nTimer = setTimeout(function () {
        isTranslating = true;
        I18N.applyTranslations();
        // Give browser time to apply DOM updates before listening again
        setTimeout(function() { isTranslating = false; }, 10);
      }, 50);
    });
    observer.observe(document.body || document.documentElement, { childList: true, subtree: true, characterData: true });
  }
})();
