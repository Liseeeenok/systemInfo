# SystemInfo — заметки по развёртыванию (legion)

Стек: Debian 13 · nginx 1.26 · PHP 8.4-FPM · PostgreSQL 17 · Laravel 13 · Vue 3 + ECharts.
Публикация: `https://90.188.226.68/` (самоподписанный сертификат `/etc/ssl/legion/legion.crt`).

## Компоненты

| Что | Где |
| --- | --- |
| Код приложения | `/var/www/systeminfo` |
| nginx-конфиг | `/etc/nginx/sites-available/systeminfo` (симлинк в `sites-enabled`, `default` выключен) |
| Сборщик метрик | `deploy/systeminfo-metrics.service` → `/etc/systemd/system/` (user `www-data`, `artisan systeminfo:collect --loop`) |
| Захват экрана | `deploy/systeminfo-screen.sh` → `/usr/local/bin/`, `deploy/systeminfo-screen.service` (user `warlord`, X11 → `/run/systeminfo/screen.jpg`) |
| Чистка истории | `routes/console.php` → `systeminfo:prune` ежедневно 04:30 (нужен cron на `artisan schedule:run`) |

## Обслуживание

```bash
sudo systemctl status systeminfo-metrics systeminfo-screen
sudo journalctl -u systeminfo-metrics -f          # лог сбора метрик
sudo journalctl -u systeminfo-screen -f           # лог захвата экрана

cd /var/www/systeminfo
php artisan systeminfo:collect                    # один сэмпл вручную
php artisan systeminfo:prune --days=7             # почистить историю
php artisan systeminfo:admin --email=me@local     # создать/сменить пользователя

npm run build                                     # пересборка фронтенда (Vue 3)
sudo chown -R www-data:www-data /var/www/systeminfo
php artisan optimize && sudo systemctl reload php8.4-fpm
```

## Переменные окружения (`.env`)

```
SYSTEMINFO_SCREEN_PATH=/run/systeminfo/screen.jpg
SYSTEMINFO_SCREEN_INTERVAL=4     # период обновления скриншота, с (сервис захвата и фронтенд)
SYSTEMINFO_METRICS_INTERVAL=5    # период сбора метрик, с
SYSTEMINFO_PRUNE_DAYS=14         # сколько дней истории держать
```

## Ограничения

- Скриншот отдаётся только авторизованным (middleware `auth`), наружу никак не кэшируется.
- Захват экрана работает, только пока в системе есть активная X-сессия пользователя `warlord`.
- История метрик: 5 с × 14 дней ≈ 242 тыс. строк; эндпоинт истории отдаёт ≤ 420 точек (агрегация в SQL).
