# CLAUDE.md — Ninja Sushi Platform (Laravel / PHP)

## Qué es este proyecto

Sistema de gestión para un restaurante de sushi buffet (all-you-can-eat) con 4 roles:

- **Diner** (móvil): se une a una mesa, ordena por rondas, pide la cuenta o un mesero.
- **Kitchen** (KDS): pantalla de cocina por estación, avanza platos `firing → ready → served`.
- **Floor** (gerente): supervisa mesas, temporizadores de sesión, desperdicio, solicitudes.
- **Admin**: edita el menú, precios, disponibilidad de platos.

## Reglas de negocio clave

- **Precio buffet**: tarifa fija por comensal (ej. $32.00), configurable en tabla `settings`.
- **Cargo por desperdicio**: por artículo no consumido marcado (ej. $6.00), configurable.
- **Sesión de 120 minutos** por mesa con ventana de "last call" de 20 min antes del cierre.
- Algunos platos tienen **precio adicional** (à-la-carte): campo `price` en `dishes`.
    - `price IS NULL` = incluido en buffet
    - `price = valor` = add-on con costo extra
- Algunos platos tienen **límite por ronda** (`per_round_limit`).
- **Facturación**: buffet_price × guests + extras + waste_fee × waste_count + impuesto (8.75%).

## Estructura de base de datos

### Tablas de referencia / catálogos (sembrar con seeders)

- `stations` — estaciones de cocina: `code` (sushi, hot, fry, cold, bar), `label`, `short_label`, `color`
- `categories` — categorías del menú: `code` (nigiri, maki, wok…), `label`, `sort_order`
- `tags` — etiquetas de platos: `code` (popular, spicy, raw, veg, gf, chef, premium), `label`
- `allergens` — alertas dietéticas: `label` (Shellfish allergy, Gluten-free, No raw fish…)
- `settings` — config global (1 fila): `buffet_price`, `waste_fee`, `session_minutes`, `last_call_minutes`, `tax_rate`

### Tablas de menú

- `dishes` — platos del menú:
    - `name`, `description`, `prep_minutes`
    - `category_id` FK → categories
    - `station_id` FK → stations
    - `price` decimal nullable (`NULL` = buffet, valor = add-on)
    - `per_round_limit` integer nullable
    - `is_available` boolean
    - `is_custom` boolean (creado por admin vs sembrado)
    - `sort_order`
- `dish_tag` — pivot dishes ↔ tags

### Tablas operacionales

- `restaurant_tables` — mesas físicas: `code` (T1, T2…), `seats`
- `dining_sessions` — sesión de mesa activa:
    - `restaurant_table_id` FK
    - `guests` integer
    - `status` enum: `seated`, `bill`, `paid`, `closed`
    - `waste_count` integer default 0
    - `opened_at`, `closed_at` timestamps
- `orders` — ronda de pedido:
    - `dining_session_id` FK
    - `number` integer (ticket# visible)
    - `round` integer (1a, 2a ronda…)
    - `status` enum: `new`, `prep`, `ready`, `served`
    - `placed_at` timestamp
- `order_items` — línea por plato:
    - `order_id` FK
    - `dish_id` FK nullable (snapshot por si el plato se elimina)
    - `name` string (snapshot)
    - `station_id` FK (snapshot para routing KDS)
    - `unit_price` decimal nullable (snapshot; null = buffet)
    - `qty` integer
    - `status` enum: `firing`, `ready`, `served`
    - `note` string nullable (ej. "birthday — no rush")
- `order_item_allergens` — alertas por línea:
    - `order_item_id` FK
    - `allergen_id` FK nullable
    - `label` string (snapshot)
- `service_requests` — solicitudes del comensal:
    - `dining_session_id` FK
    - `type` enum: `bill`, `server`
    - `requested_at` timestamp
    - `resolved_at` timestamp nullable (null = pendiente)
- `payments` — factura liquidada (snapshot al cobrar):
    - `dining_session_id` FK
    - `guests`, `buffet_total`, `extras_total`, `waste_total`, `tax`, `total`
    - `paid_at` timestamp
- `users` — staff: `name`, `email`, `password`, `role` enum (`kitchen`, `floor`, `admin`)

## Relaciones Eloquent

Station hasMany Dishes
Category hasMany Dishes
Dish belongsToMany Tags (dish_tag)
RestaurantTable hasMany DiningSessions
DiningSession hasMany Orders
DiningSession hasMany ServiceRequests
DiningSession hasOne Payment
Order hasMany OrderItems
OrderItem hasMany OrderItemAllergens

## Convenciones importantes

- **Snapshots en `order_items`**: guardar `name`, `unit_price`, `station_id` al momento de ordenar. El menú puede cambiar; el historial no.
- **`dish_id` nullable** en `order_items`: mantiene la FK para reportes pero no rompe el historial si el plato se borra.
- **`orders.status`** puede ser derivado de sus `order_items.status` (si todos son `served` → `served`, etc.) o cacheado. Preferir un accessor Eloquent que lo compute, y sincronizar el campo al avanzar items.
- **Indexes recomendados**: `orders.dining_session_id`, `order_items.order_id`, `order_items.(status, station_id)` (el KDS filtra por estación y estado `firing`), `dining_sessions.status`.

## Flujo de una sesión completa

1. Staff abre mesa → crea `dining_session` (`status=seated`, `opened_at=now`)
2. Comensal selecciona platos → acumula carrito solo en frontend
3. Comensal confirma ronda → crea `order` + `order_items` con `status=firing`
4. Kitchen ve tickets por estación → avanza items: `firing → ready → served`
5. Comensal pide cuenta → crea `service_request` tipo `bill`, `dining_session.status=bill`
6. Floor cobra → crea `payment`, `dining_session.status=paid → closed`

## Fuera de alcance por ahora

- Modificadores de platos (spice level, sushi preferences, etc.)
- Notificaciones en tiempo real (Laravel Reverb / Pusher)
- Módulo de reportes
