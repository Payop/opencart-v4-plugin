<?php

$_['heading_title'] = 'Payop Payment Gateway';

$_['text_payop'] = '<a href="https://payop.com" target="_blank"><img src="/extension/payop/admin/view/image/payment/payop.png" style="max-width:130px; height:auto;"></a>';
$_['text_extension'] = 'Розширення';
$_['text_success'] = 'Налаштування успішно збережено!';
$_['text_edit'] = 'Редагування Payop Payment Gateway';

$_['entry_public_id'] = 'Публічний ключ';
$_['entry_secret_key'] = 'Секретний ключ';
$_['entry_order_status_wait'] = 'Статус замовлення при очікуванні оплати';
$_['entry_order_status_success'] = 'Статус замовлення при успішній оплаті';
$_['entry_order_status_error'] = 'Статус замовлення при невдалій оплаті';
$_['entry_status'] = 'Статус';
$_['entry_sort_order'] = 'Порядок сортування';
$_['entry_ipn_url'] = 'IPN URL';

$_['help_public_id'] = 'Цей параметр можна знайти в налаштуваннях проєкту в особистому кабінеті Payop.';
$_['help_secret_key'] = 'Цей параметр можна знайти в налаштуваннях проєкту в особистому кабінеті Payop.';
$_['help_ipn_url'] = 'Скопіюйте цю URL-адресу та додайте її в налаштуваннях IPN на сайті Payop.';

$_['error_permission'] = 'У вас немає прав для керування цим модулем!';
$_['error_public_id'] = 'Поле є обов’язковим для заповнення.';
$_['error_secret_key'] = 'Поле є обов’язковим для заповнення.';

$_['entry_project_id'] = 'ID проєкту';
$_['entry_jwt_token'] = 'JWT Token';
$_['help_project_id'] = 'Укажіть ID проєкту з кабінету Payop. Він може відрізнятися від Public Key. Потрібен лише для завантаження методів.';
$_['help_jwt_token'] = 'Використовується лише для завантаження методів проєкту. Звичайний Hosted Page і перевірка invoice працюють без JWT.';
$_['help_payment_buttons'] = 'Основний Payop Hosted Page залишається доступним. Додавайте додаткові варіанти без обмеження кількості. Для ввімкнених варіантів заповніть назву кожною мовою магазину. Опис необов’язковий. Перед вибором нового Payment Method ID завантажте методи.';
$_['entry_button_name'] = 'Назва для покупця';
$_['entry_button_description'] = 'Опис для покупця';
$_['entry_integration_type'] = 'Тип інтеграції';
$_['entry_payment_method'] = 'Платіжний метод';
$_['text_payment_buttons'] = 'Додаткові варіанти оплати';
$_['text_hosted_page'] = 'Hosted Page';
$_['text_payment_method'] = 'Hosted Page with Payment Method ID';
$_['text_saved_method'] = 'Збережений ID методу:';
$_['text_select_method'] = 'Виберіть платіжний метод';
$_['text_methods_loaded'] = 'Методи завантажено. Поточні вибрані значення збережено.';
$_['text_buttons_javascript'] = 'Для редагування варіантів потрібен JavaScript. Під час збереження інших налаштувань наявні варіанти зберігаються.';
$_['button_load_methods'] = 'Завантажити платіжні методи';
$_['button_add'] = 'Додати варіант';
$_['button_remove'] = 'Видалити варіант';
$_['error_buttons'] = 'Некоректні дані варіантів оплати. Налаштування не збережено.';
$_['error_button_method'] = 'Для кожного варіанта з Payment Method ID виберіть метод, доступний цьому проєкту.';
$_['error_button_name'] = 'Заповніть назву кожною мовою магазину для всіх увімкнених варіантів.';
$_['error_methods_credentials'] = 'Укажіть ID проєкту та JWT Token для завантаження методів. Наявні варіанти збережено.';
$_['error_methods_auth'] = 'Payop відхилив JWT Token або доступ до проєкту. Перевірте токен і ID проєкту. Наявні варіанти збережено.';
$_['error_methods_network'] = 'Не вдалося підключитися до Payop. Повторіть завантаження методів. Наявні варіанти збережено.';
$_['error_methods_response'] = 'Payop не повернув список методів. Перевірте ID проєкту та JWT Token і спробуйте ще раз. Наявні варіанти збережено.';

$_['entry_revolut_fields'] = 'Запитувати додаткові дані платника Revolut';
