#!/bin/bash
cd /opt/lampp/htdocs/TMS_ERP/backend
php8.4 artisan serve --host=127.0.0.1 --port=8000 &
echo "Backend started on http://127.0.0.1:8000"
php8.4 artisan queue:work &
echo "Queue worker started"
php8.4 artisan schedule:work &
echo "Scheduler started"

cd /opt/lampp/htdocs/TMS_ERP/frontend
npm run dev -- --host=127.0.0.1 --port=5173 &
echo "Frontend started on http://127.0.0.1:5173"
