# SystemInfo — дашборд состояния сервера

Стек: Debian 13 · nginx 1.26 · PHP 8.4-FPM · PostgreSQL 17 · Laravel 13 · Vue 3 + ECharts.
Публикация: `https://90.188.226.68/system/` (самоподписанный сертификат `/etc/ssl/legion/legion.crt`).
Доступ закрыт логином Laravel (сессии в БД).

## Компоненты

| Что | Где |
| --- | --- |
| Код приложения | `/var/www/systeminfo` |
| nginx | сниппет `deploy/nginx-systeminfo.conf` → `/etc/nginx/snippets/systeminfo.conf`, подключается из конфига хоста `/etc/nginx/sites-available/legion` |
| Сборщик метрик | `deploy/systeminfo-metrics.service` → `/etc/systemd/system/` (user `www-data`, `artisan systeminfo:collect --loop`) |
| Захват экрана | `deploy/systeminfo-screen.sh` → `/usr/local/bin/`, `deploy/systeminfo-screen.service` (user `warlord`, X11 → `/run/systeminfo/screen.jpg`) |
| Чистка истории | `routes/console.php` → `systeminfo:prune` ежедневно 04:30 (cron `/etc/cron.d/systeminfo`) |

## Как приложение отдаётся по /system

- на хосте: `root /var/www/sites` и симлинк `/var/www/sites/system -> /var/www/systeminfo/public`;
- PHP — отдельным regex-локейшеном с `SCRIPT_NAME=/system/index.php`. По SCRIPT_NAME Laravel
  вычисляет свою базу и сам подставляет префикс в `asset()`, `route()` и `url()`, поэтому
  приложение можно переносить между корнем и подкаталогом без пересборки фронтенда;
- **нельзя** отдавать каталог через `alias` + `try_files`: SPA-роуты получают 404,
  а `index.php` может уйти как статический файл (утечка исходника);
- `/login` → 301 на `/system/login` — совместимость со старым адресом, когда дашборд был в корне.

Обновить конфиг приложения:

```bash
sudo cp deploy/nginx-systeminfo.conf /etc/nginx/snippets/systeminfo.conf
sudo nginx -t && sudo systemctl reload nginx
```

## Обслуживание

```bash
sudo systemctl status systeminfo-metrics systeminfo-screen
sudo journalctl -u systeminfo-metrics -f          # лог сбора метрик
sudo journalctl -u systeminfo-screen -f           # лог захвата экрана

cd /var/www/systeminfo
php artisan systeminfo:collect                    # один сэмпл вручную
php artisan systeminfo:prune --days=7             # почистить историю
php artisan systeminfo:admin --email=me@local     # создать/сменить пользователя дашборда

npm run build                                     # пересборка фронтенда (Vue 3)
sudo chown -R warlord:www-data /var/www/systeminfo
sudo chown -R www-data:www-data /var/www/systeminfo/storage /var/www/systeminfo/bootstrap/cache
php artisan optimize && sudo systemctl reload php8.4-fpm
```

## Переменные окружения (`.env`)

```
APP_URL=https://90.188.226.68/system
SYSTEMINFO_SCREEN_PATH=/run/systeminfo/screen.jpg
SYSTEMINFO_SCREEN_INTERVAL=4     # период обновления скриншота, с (сервис захвата и фронтенд)
SYSTEMINFO_METRICS_INTERVAL=5    # период сбора метрик, с
SYSTEMINFO_PRUNE_DAYS=14         # сколько дней истории держать
```

## Ограничения

- Скриншот отдаётся только авторизованным (middleware `auth`), наружу никак не кэшируется.
- Захват экрана работает, только пока в системе есть активная X-сессия пользователя `warlord`.
- История метрик: 5 с × 14 дней ≈ 242 тыс. строк; эндпоинт истории отдаёт ≤ 420 точек (агрегация в SQL).
- Сертификат самоподписанный → предупреждение браузера.
