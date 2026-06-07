# Payment Provider Readiness Checklist

Дата проверки: 2026-06-07
Проект: Safi Life public site, catalog, cart/order, support, i18n

## Статус

Техническая часть сайта в целом готова к предварительной проверке: публичные маршруты доступны через SPA fallback, каталог и заказ работают через backend API, support реализован через сайт, floating WhatsApp/Telegram/phone контакты в исходниках не рендерятся.

Перед финальной отправкой банку/платежному провайдеру остаются контентно-юридические пункты: заполнить полные юридические реквизиты, политики/оферту, заменить внешние stock/CDN изображения на утвержденные локальные бренд-ассеты и убедиться, что production-каталог наполнен реальными активными товарами.

## Что проверено

### Public Pages

Проверены публичные маршруты:

- `/`
- `/about`
- `/products`
- `/business`
- `/marketing`
- `/how-to-start`
- `/faq`
- `/contacts`
- `/news`

Маршруты обслуживаются через `routes/web.php` SPA fallback и описаны в `resources/js/safi/router/routes.tsx`.

Готово:

- Основные публичные страницы подключены в роутере.
- `/products`, `/faq`, `/news` берут данные из публичных API, а не из статичного списка на странице.
- Убрана публичная footer-ссылка `Демо кабинета`.
- Убран нерабочий mock-like contact form на `/contacts`; вместо него показан путь обращения через сайт-поддержку в кабинете.

Осталось:

- `/legal` содержит публичный текст о том, что юридический раздел еще в стадии наполнения. Перед подачей нужно заменить его на финальные юридические реквизиты, пользовательское соглашение, политику конфиденциальности и условия продаж/доставки/возврата, если они требуются платежным провайдером.
- На публичных страницах еще есть внешние изображения (`wordpress.com`, `unsplash.com`). Для production-проверки лучше заменить их на локальные утвержденные файлы в `public/` или `storage`.

### Products

Проверено:

- Public API: `GET /api/public/products`.
- Public detail API: `GET /api/public/products/{product}`.
- Frontend catalog: `resources/js/safi/pages/ProductsPage.tsx`.
- Product resource: `app/Http/Resources/ProductResource.php`.

Готово:

- Публичный каталог показывает только `active` товары и исключает deposit-products.
- Карточка товара показывает фото, описание, цену, PV и остаток.
- `inactive` товар недоступен в public API detail и не покупается через order API.
- Если у товара нет фото, frontend использует локальный placeholder `/images/product-placeholder.svg`, поэтому broken image не должен появляться из-за пустого `image_url`.
- Admin CRUD поддерживает `name`, `description`, `price`, `pv`, `stock_quantity`, `status`, upload image, optional category.

Осталось:

- Проверить production DB: активные товары должны быть реальными, с финальными названиями, описаниями, ценами, PV, остатками и фото.
- По возможности не использовать внешние URL для товарных фото в production; предпочтительно `storage/app/public/products` и storage URL.

### Cart And Orders

Проверено:

- Cart UI: `resources/js/safi/pages/CartPage.tsx`.
- Order API: `POST /api/orders`.
- Validation: `app/Http/Requests/Order/StoreOrderRequest.php`.
- Stock/order logic: `app/Http/Controllers/Api/OrderController.php`.
- Tests: `tests/Feature/Orders/ProductStockOrderTest.php`, `tests/Feature/ProductOrderApiTest.php`, `tests/Feature/Orders/UserOrdersPageApiTest.php`.

Готово:

- Checkout требует `recipient_name`, `phone`, `city`, `delivery_address`.
- Заказ создается только для авторизованного пользователя.
- Нельзя купить inactive товар.
- Нельзя купить товар без остатка или больше доступного stock.
- Stock уменьшается после создания заказа.
- При отмене заказа через admin stock возвращается.
- User не может смотреть чужой заказ.

Осталось:

- Перед подключением платежей определить финальную схему оплаты: пока заказ создается как `pending`, а администратор меняет статус вручную.
- Добавить публичные условия оплаты/доставки/возврата в legal/footer, если это требование провайдера.

### Support

Проверено:

- Feature flag: `support: true`, `floatingExternalContacts: false`.
- User support page: `/dashboard/support`.
- Admin/support page: `/admin/support` and `/support/tickets` routes.
- API: `/api/dashboard/support-tickets`, `/api/support/tickets`, `/api/admin/support-tickets`.
- Tests: `tests/Feature/SupportTicketAccessTest.php`, `tests/Feature/SupportTicketPermissionTest.php`, `tests/Feature/FrontendContentTest.php`.

Готово:

- Пользователь может создать обращение через сайт, смотреть свои обращения и закрыть обращение.
- Support/admin/super_admin могут видеть обращения, отвечать и менять статус.
- Floating WhatsApp/Telegram/phone кнопки не рендерятся в исходниках публичных страниц и layout.
- На `/contacts` нет внешних messenger/phone CTA; пользователь направляется в website support.

Осталось:

- Если платежный провайдер потребует публичный неавторизованный канал связи, нужно согласовать формат и добавить рабочую форму/API, а не визуальную заглушку.

### Languages

Проверено:

- Locale files: `ru.json`, `kz.json`, `kg.json`, `en.json`, `mn.json`.
- Runtime translation layer: `resources/js/safi/i18n/runtimeTranslations.ts`.
- Public content language tests: `FaqLanguageTest`, `PublicNewsLanguageTest`, `PublicProductsLanguageTest`, `LanguageMiddlewareTest`.

Готово:

- Ключевые словари есть для RU/KZ/KG/EN/MN.
- Product/order/status labels локализуются через API/resource/helper слой.
- Новые тексты `/contacts` добавлены во все пять целевых языков.

Осталось:

- Провести ручной visual QA по каждому языку на production-контенте: в коде еще есть часть fallback-текстов на русском, которые runtime layer переводит, но для банковской проверки лучше пройти ключевые страницы в каждом языке браузером.

### Footer, Contact, Legal

Проверено:

- `resources/js/safi/components/layout/Footer.tsx`.
- `resources/js/safi/pages/ContactsPage.tsx`.
- `resources/js/safi/pages/LegalPage.tsx`.

Готово:

- Footer больше не показывает `Демо кабинета`.
- Contacts page больше не содержит нерабочую форму с `preventDefault`.
- Внешние WhatsApp/Telegram/phone floating contacts отсутствуют.
- Есть email `info@safilife.kz` и адрес уровня города: Республика Казахстан, г. Алматы.

Осталось:

- Добавить финальные юридические реквизиты компании.
- Добавить или уточнить публичные документы: пользовательское соглашение, политика конфиденциальности, условия оплаты, доставки, возврата/отмены, disclaimer по доходам и продуктам.
- Проверить, достаточно ли текущего contact email и адреса уровня города для конкретного платежного провайдера.

## Итог По Страницам

Готовы технически:

- `/`
- `/about`
- `/products`
- `/business`
- `/marketing`
- `/how-to-start`
- `/faq`
- `/contacts`
- `/news`

Требуют финального production-контента перед отправкой:

- `/products`: реальные товары и фото в production DB/storage.
- `/about`: заменить stock/external image на утвержденный бренд-ассет.
- `/legal`: заполнить полные юридические данные и политики.
- Footer/header: желательно заменить внешний logo URL на локальный бренд-ассет.

## Команды Проверки

В рамках этапа выполнены:

```bash
php artisan test
npm run build
```

Результат:

- `php artisan test`: passed, 341 tests, 2197 assertions.
- `npm run build`: passed.
- Build warning: Vite сообщает о `main` chunk больше 500 kB. Это warning по оптимизации bundle size, не ошибка сборки.
