window.KEYCRM_UTM_CONFIG = {
  endpoint: 'https://gerc.store/keycrm-send.php',

  // Замовлення з сайту OpenCart
  sourceId: 2,

  // Якщо на формі є data-keycrm-source-id — він має пріоритет
  useFormSourceId: true,

  // Опційно: підставити source_id за utm_source (лише для форм, що йдуть напряму в keycrm-send.php)
  sourceMap: {
    facebook: 1,
    fb: 1,
    opencart: 2,
    site: 2,
    telegram: 3,
    tg: 3,
    viber: 4,
    whatsapp: 5,
    wa: 5,
    tiktok: 6,
    'tik-tok': 6,
    calls: 7,
    instagram: 8,
    insta: 8,
    ig: 8,
    'telegram-bot': 9,
    pickup: 10,
    samoviviz: 10,
    drop: 11,
    prom: 12
  },

  fields: {
    name: 'name',
    phone: 'phone',
    email: 'email',
    productSku: 'product_sku',
    quantity: 'quantity',
    price: 'price'
  },

  formSelector: 'form[data-keycrm-form]',

  onSuccess: function () {},

  onError: function (error) {
    console.error('KeyCRM:', error);
    alert('Не вдалося відправити заявку. Спробуйте ще раз.');
  }
};
