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

  // ─── Core Engine ────────────────────────────────────────────────────────────
  const I18N = {
    currentLang: 'en',

    /** Get translated string, fallback to English, then key itself */
    t: function (key) {
      const dict = DICT[this.currentLang] || {};
      return dict[key] || DICT['en'][key] || key;
    },

    /** Apply translations to all [data-i18n] elements in the DOM */
    applyTranslations: function () {
      const lang = this.currentLang;
      // Set HTML lang attribute
      document.documentElement.lang = lang;
      // Set RTL direction for Arabic etc.
      document.documentElement.dir = RTL_LANGS.includes(lang) ? 'rtl' : 'ltr';

      document.querySelectorAll('[data-i18n]').forEach(function (el) {
        const key = el.getAttribute('data-i18n');
        const translation = I18N.t(key);
        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
          el.placeholder = translation;
        } else {
          el.textContent = translation;
        }
      });

      // Also handle data-i18n-html (for elements needing innerHtml)
      document.querySelectorAll('[data-i18n-html]').forEach(function (el) {
        const key = el.getAttribute('data-i18n-html');
        el.innerHTML = I18N.t(key);
      });
    },

    /** Persist and apply a language */
    changeLanguage: function (lang) {
      if (!DICT[lang]) lang = 'en';
      this.currentLang = lang;
      try { localStorage.setItem('jmp2p_lang', lang); } catch (e) {}
      this.applyTranslations();
      // Update switcher UI if present
      const switcher = document.getElementById('i18n-lang-switcher');
      if (switcher) switcher.value = lang;
    },

    /** Main init — detects language from cache, then IP */
    init: function () {
      // 1. Check localStorage first
      let saved = null;
      try { saved = localStorage.getItem('jmp2p_lang'); } catch (e) {}

      if (saved && DICT[saved]) {
        this.changeLanguage(saved);
        return;
      }

      // 2. Detect via IP (ipapi.co is free, no API key needed)
      // Apply English immediately to avoid blank page while fetching
      this.changeLanguage('en');

      var self = this;
      fetch('https://ipapi.co/json/', { cache: 'force-cache' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var countryCode = (data && data.country_code) ? data.country_code.toUpperCase() : null;
          var lang = (countryCode && COUNTRY_LANG[countryCode]) ? COUNTRY_LANG[countryCode] : 'en';
          // Save detected lang (without persisting, so next visit re-detects if user moved country)
          self.currentLang = lang;
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
  // Observe DOM mutations and re-translate new [data-i18n] nodes
  if (typeof MutationObserver !== 'undefined') {
    var observer = new MutationObserver(function (mutations) {
      var hasNew = false;
      mutations.forEach(function (m) {
        m.addedNodes.forEach(function (n) {
          if (n.nodeType === 1 && (n.hasAttribute('data-i18n') || n.querySelector && n.querySelector('[data-i18n]'))) {
            hasNew = true;
          }
        });
      });
      if (hasNew) I18N.applyTranslations();
    });
    observer.observe(document.body || document.documentElement, { childList: true, subtree: true });
  }
})();
