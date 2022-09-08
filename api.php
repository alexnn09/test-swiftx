<?php

new api();

class api
{
    private $pdo;
    private $dsn = 'mysql:host=localhost';
    private $user = 'user';
    private $password = 'password';
    private $dbName = 'db_name';
    private $requestMethodByMethod = [
        "staff_info" => "GET",
        "staff" => "GET",
        "offices" => "GET",
        "update_staff" => "POST",
        "add_staff" => "POST",
        "delete_staff" => "POST",
        "search_staff" => "GET"
    ];
    private $requiredFields = [
        "update_staff" => [
            "id",
            "fio",
            "phone",
            "post",
            "office_id"
        ],
        "add_staff" => [
            "fio",
            "phone",
            "post",
            "office_id"
        ],
        "delete_staff" => [
            "id"
        ],
        "search_staff" => [
            "fio"
        ]
    ];
    private $fields;

    public function __construct()
    {
        try {
            $this->pdo = new PDO($this->dsn . ";dbname=" . $this->dbName, $this->user, $this->password);
        } catch (PDOException $exception) {
            $this->response([], 500, "Internal error");
        }

        $this->checkAuth();
        $this->handle();
    }

    private function checkAuth()
    {
        $login = $_REQUEST['login'];
        $password = $_REQUEST['passwd'];

        $query = "SELECT id FROM user WHERE login = '" . $login . "' AND passwd = '" . $password . "' LIMIT 1";
        $result = $this->pdo->query($query);

        if (!$result || $result->rowCount() == 0) {
            $this->response([], 401, "Authorization Required");
        } else {
            $id = $result->fetch()["id"];
            $this->pdo->query("UPDATE user WHERE id = '" . $id . "' SET last_auth = GETDATE()");
        }
    }

    private function handle()
    {
        $method = $_REQUEST["method"];

        if ($this->requestMethodByMethod[$method]) {
            $this->validateRequestMethod($method);
            $this->fillFields($method);
            $this->validateRequestData($method);
            $this->$method();
        } else {
            $this->response([], 400, "Method not found");
        }
    }

    private function response(array $array = [], int $code = 200, string $message = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode(
            [
                "status" => $code === 200 ? "ok" : "error",
                "message" => $message,
                "data" => $array
            ]
        );
        die;
    }

    private function validateRequestMethod(string $method)
    {
        if ($_SERVER["REQUEST_METHOD"] !== $this->requestMethodByMethod[$method]) {
            $this->response([], 405, 'Method not allowed');
        }
    }

    private function fillFields(string $method)
    {
        if ($this->requiredFields[$method]) {
            foreach ($this->requiredFields[$method] as $fieldKey) {
                $this->fields[$fieldKey] = $_REQUEST[$fieldKey];
            }
        }
    }

    private function validateRequestData(string $method)
    {
        $requiredData = array_flip($this->requiredFields[$method] ?? []);

        if (empty($requiredData)) {
            return;
        }

        foreach (array_keys($this->fields) as $rData) {
            if (isset ($requiredData[$rData])) {
                unset($requiredData[$rData]);
            }
        }

        if (!empty($requiredData)) {
            $this->response([], 400, 'Required fields not found: ' . implode(", ", array_keys($requiredData)));
        }
    }

    private function staff_info()
    {
        $query = '
            SELECT 
                   s.id as id,
                   s.fio as fio,
                   s.post as post,
                   s.phone as phone,
                   o.office_name as office_name,
                   o.address as address 
            FROM staff as s LEFT JOIN office as o ON s.office = o.id';

        $this->response($this->prepareRows($this->pdo->query($query)));
    }

    private function staff()
    {
        $query = 'SELECT id, fio FROM staff';

        $this->response($this->prepareRows($this->pdo->query($query)));
    }

    private function offices()
    {
        $query = 'SELECT id, office_name, address FROM office';

        $this->response($this->prepareRows($this->pdo->query($query)));
    }

    private function update_staff()
    {
        $query = "
        UPDATE staff 
        SET 
            fio = '" . $this->fields["fio"] . "',
            phone = '" . $this->fields["phone"] . "',
            post = '" . $this->fields["post"] . "',
            office = '" . $this->fields["office_id"] . "'
        WHERE id='" . $this->fields["id"] . "'";

        $this->pdo->exec($query);
        $this->response([]);
    }

    private function add_staff()
    {
        $query = "
                INSERT INTO staff (fio, phone, post, office)
                VALUE (
                    '" . $this->fields['fio'] . "',
                    '" . $this->fields["phone"] . "',
                    '" . $this->fields['post'] . "',
                    '" . $this->fields["office_id"] . "'
                    )
            ";

        $this->pdo->exec($query);
        $this->response([]);
    }

    private function delete_staff()
    {
        $query = "DELETE FROM staff WHERE id = " . $this->fields["id"];

        $this->pdo->exec($query);
        $this->response([]);
    }

    private function search_staff()
    {
        $query = "SELECT fio, phone, post FROM staff WHERE fio like '%" . $this->fields["fio"] . "%'";

        $this->response($this->prepareRows($this->pdo->query($query)));
    }

    private function prepareRows($pdoQuery)
    {
        if ($pdoQuery instanceof PDOStatement) {
            return $pdoQuery->fetchAll(PDO::FETCH_ASSOC);
        } else {
            return [];
        }
    }
}
