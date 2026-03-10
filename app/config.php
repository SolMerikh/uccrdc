<?php
// Copy this project into XAMPP htdocs and update these settings.
// Never commit real passwords.

return [
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'uccrdc_issn',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'uploads' => [
        'valid_id_dir' => __DIR__ . '/../uploads/ids',
        'publication_pdf_dir' => __DIR__ . '/../uploads/pubs',
        'max_valid_id_bytes' => 5 * 1024 * 1024,   
        'max_pdf_bytes' => 20 * 1024 * 1024,     
        'valid_id_extensions' => ['jpg', 'jpeg', 'png'],
    ],
    'mail' => [
  
        'driver' => 'smtp',
        'from_email' => 'csdsg@ucc-caloocan.edu.ph',
        'from_name' => 'UCC – RDC ISSN Portal',
        'log_path' => __DIR__ . '/../storage/mail.log',
   
        'admin_notify_to' => 'csdsg@ucc-caloocan.edu.ph',

        
        'smtp' => [
            'host' => 'smtp.gmail.com',
            'port' => 465,
            'encryption' => 'ssl',
            'username' => 'csdsg@ucc-caloocan.edu.ph', 
            'password' => 'bxahpbeaaixxhlbd',  
            'timeout' => 20,
        ],
    ],
];
