<?php

return [
    'lock_path' => env('INSTALLER_LOCK_PATH', storage_path('app/installer/installed.lock')),
    'env_path' => env('INSTALLER_ENV_PATH', base_path('.env')),
    'backup_path' => env('INSTALLER_BACKUP_PATH', storage_path('app/installer/backups')),
    'timeout' => (int) env('INSTALLER_TIMEOUT', 600),
];
