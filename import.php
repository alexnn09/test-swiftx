<?php

$data = [
    [
        "id" => 1,
        "fio" => "Иванов Дмитрий Сергеевич",
        "phone" => 2137,
        "post" => "Оператор",
        "office" => "Северный",
        "address" => "Дзержинского, 20",
    ],
    [
        "id" => 2,
        "fio" => "Кочкина Дарья Алексеевна",
        "phone" => 2101,
        "post" => "Администратор",
        "office" => "Центральный",
        "address" => "Ленина, 51",
    ],
    [
        "id" => 3,
        "fio" => "Сергеев Юрий Витальевич",
        "phone" => 2112,
        "post" => "Управляющий",
        "office" => "Центральный",
        "address" => "Ленина, 51",
    ],
    [
        "id" => 4,
        "fio" => "Баранов Сергей Вадимович",
        "phone" => 2137,
        "post" => "Специалист",
        "office" => "Северный",
        "address" => "Дзержинского, 20",
    ],
    [
        "id" => 5,
        "fio" => "Михайлов Максим Юрьевич",
        "phone" => 2122,
        "post" => "Оператор",
        "office" => "Западный",
        "address" => "Солнечная, 37А",
    ],
    [
        "id" => 6,
        "fio" => "Фомина Анна Валентиновна",
        "phone" => 2120,
        "post" => "Кассир",
        "office" => "Центральный",
        "address" => "Ленина, 51",
    ],
    [
        "id" => 7,
        "fio" => "Носков Андрей Витальевич",
        "phone" => 2131,
        "post" => "Специалист",
        "office" => "Октябрьский",
        "address" => "Октябрьский, 98",
    ],
    [
        "id" => 8,
        "fio" => "Соколова Анна Валерьевна",
        "phone" => 2130,
        "post" => "Бухгалтер",
        "office" => "Центральный",
        "address" => "Ленина, 51",
    ],
    [
        "id" => 9,
        "fio" => "Шахматов Алексей Алексеевич",
        "phone" => 2125,
        "post" => "Специалист",
        "office" => "Западный",
        "address" => "Солнечная, 37А",
    ],
    [
        "id" => 10,
        "fio" => "Ершов Дмитрий Иванович",
        "phone" => 2137,
        "post" => "Специалист",
        "office" => "Октябрьский",
        "address" => "Октябрьский, 98",
    ],
];

$users = [
    [
        "id" => 1,
        "login" => "admin",
        "passwd" => "test123"
    ],
    [
        "id" => 2,
        "login" => "manager",
        "passwd" => "qweasd"
    ]
];

$import = new importData($data, $users);
$import->importData();

class importData
{
    private $dsn = 'mysql:host=localhost';
    private $user = 'user';
    private $password = 'password';
    private $dbName = 'db_name';
    private $pdo;
    private $data;
    private $staff;
    private $offices;
    private $officeIdByHash;
    private $users;

    public function __construct(
        array $data,
        array $users
    )
    {
        $this->data = $data;
        $this->users = $users;
        $pdo = new PDO($this->dsn, $this->user, $this->password);
        $pdo->exec('CREATE DATABASE ' . $this->dbName);
        $this->pdo = new PDO($this->dsn . ";dbname=" . $this->dbName, $this->user, $this->password);

        $this->createTables();
        $this->prepareData();
    }

    private function createTables()
    {
        $this->pdo->exec('
                CREATE TABLE staff (
                    id INT UNSIGNED AUTO_INCREMENT primary key NOT NULL,
                      fio VARCHAR(255) NOT NULL,
                       phone SMALLINT UNSIGNED NOT NULL,
                        post VARCHAR(255) DEFAULT NULL,
                         office INT UNSIGNED NOT NULL)
                         ');

        $this->pdo->query('
                CREATE TABLE office (
                    id INT UNSIGNED AUTO_INCREMENT primary key NOT NULL,
                     hash VARCHAR(32) NOT NULL,
                      office_name VARCHAR(255) NOT NULL,
                       address VARCHAR(255) NOT NULL)
                       ');

        $this->pdo->query('
                CREATE TABLE user (
                    id INT UNSIGNED AUTO_INCREMENT primary key NOT NULL,
                    login VARCHAR(255) NOT NULL,
                    passwd VARCHAR(255) NOT NULL,
                    last_auth DATETIME DEFAULT NULL
                )');
    }

    private function prepareData()
    {
        foreach ($this->data as $item) {
            $officeHash = md5($item['office'] . $item['address']);

            $this->staff[] = [
                'id' => $item['id'],
                'fio' => $item['fio'],
                'phone' => $item['phone'],
                'post' => $item['post'],
                'officeHash' => $officeHash
            ];

            if (!$this->offices[$officeHash]) {
                $this->offices[$officeHash] = [
                    'hash' => $officeHash,
                    'office_name' => $item['office'],
                    'address' => $item['address']
                ];
            }
        }
    }

    public function importData()
    {
        $this->importOffices();
        $this->importStaff();
        $this->importUsers();
    }

    private function importOffices()
    {
        foreach ($this->offices as $office) {
            $this->pdo->query("
                INSERT INTO office (hash, office_name, address)
                VALUE ('" . $office['hash'] . "', '" . $office['office_name'] . "', '" . $office["address"] . "');
            ");
            $this->officeIdByHash[$office["hash"]] = $this->pdo->lastInsertId();
        }
    }

    private function importStaff()
    {
        foreach ($this->staff as $staff) {
            $this->pdo->query("
                INSERT INTO staff (id, fio, phone, post, office)
                VALUE (
                    '" . $staff['id'] . "',
                    '" . $staff['fio'] . "',
                    '" . $staff["phone"] . "',
                    '" . $staff['post'] . "',
                    '" . $this->officeIdByHash[$staff["officeHash"]] . "'
                    )
            ");
        }
    }

    private function importUsers()
    {
        foreach ($this->users as $user) {
            $this->pdo->query("
                INSERT INTO user (id, login, passwd)
                VALUE (
                    '" . $user['id'] . "',
                    '" . $user['login'] . "',
                    '" . $user["passwd"] . "'
                    )
            ");
        }
    }
}