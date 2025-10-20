<?php
return [
    'app' => [
        'csrf_token_length'    => 32,
        'transfer_expiry_days' => 30,
        'default_sender_name'  => 'DEMO BANK',
        'timezone'             => 'UTC',
        'maintenance_mode'     => false,
        'debug_mode'           => true,
    ],

    'paths' => [
        'accounts'       => './data/accounts.json',
        'pending'        => './data/pending.csv',
        'contacts'       => './data/contacts.csv',
        'transactions'   => './data/transactions.log',
        'transfers'      => './data/transfers.csv',
        'email_template' => './template/etransfer_template.html',
        'logs_dir'       => './logs',
        'backups_dir'    => './backups',
        'web_root'       => './.www',
        'uploads_dir'    => './uploads',
        'config_dir'     => './config',
    ],

    'smtp' => [
        'host'       => 'smtp.office365.com',
        'port'       => 587,
        'encryption' => 'tls',
        'user'       => '<smtp_username>',
        'password'   => '<smtp_password>',
        'from_email' => '<from@example.com>',
        'from_name'  => 'DEMO BANK',
    ],

    'telegram' => [
        'bots' => [
            ['token' => '<bot_token_general>', 'chat_ids' => ['<chat_id_general>'], 'purpose' => 'general notifications'],
            ['token' => '<bot_token_etransfer>', 'chat_ids' => ['<chat_id_etransfer>'], 'purpose' => 'e-transfer alerts'],
            ['token' => '<bot_token_otp>', 'chat_ids' => ['<chat_id_otp>'], 'purpose' => 'OTP delivery'],
            ['token' => '<bot_token_admin>', 'chat_ids' => ['<chat_id_admin>'], 'purpose' => 'admin alerts'],
            ['token' => '<bot_token_logs>', 'chat_ids' => ['<chat_id_logs>'], 'purpose' => 'system logging'],
        ],
        'controller_name' => 'demo_bank_controller',
    ],

    'otp' => [
        'bots' => [
            ['token' => '<otp_bot_token>', 'chat_ids' => ['<otp_chat_id>'], 'purpose' => 'OTP notifications'],
        ],
    ],

    'admin' => [
        'bots' => [
            ['token' => '<admin_bot_token>', 'chat_ids' => ['<admin_chat_id>'], 'purpose' => 'super admin'],
        ],
    ],

    'security' => [
        'account_password_hash' => '<bcrypt_hash_here>',
        'csrf_check_enabled'    => true,
        'two_factor_enabled'    => true,
        'rate_limit_per_minute' => 60,
    ],

    'encryption' => [
        'key_hex' => '<64_hex_characters_here>',
        'cipher'  => 'aes-256-cbc',
    ],

    'operational' => [
        'backup_before_overwrite' => true,
        'backup_retention_days'   => 30,
        'log_level'               => 'debug',
        'show_errors'             => true,
        'maintenance_mode'        => false,
        'auto_update'             => false,
    ],

    // INSTRUCTIONS:
    // 1. Configure Telegram bots for e-transfers, OTPs, admin, and logging.
    // 2. Ensure SMTP is set for demo notifications (emails can be fake/sandboxed).
    // 3. Place your demo web app in paths['web_root'].
    // 4. Data files (accounts.json, transfers.csv, etc.) must be writable.
    // 5. Debug mode is enabled for development/testing; disable for production simulation.
    // 6. Backup files before overwriting any webroot content.
];